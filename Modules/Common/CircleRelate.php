<?php
/**
 * 圈子关系管理类
 * 
 * 处理用户与圈子关系的数据操作，包括添加、更新、删除和查询
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;

class CircleRelate {
    /**
     * 更新或插入圈子与用户的关系数据
     * @author ifyn
     * @param array $new_data 要插入或更新的数据
     * @return bool 操作是否成功
     */
    public static function update_data($new_data) {
        if (empty($new_data) || !is_array($new_data)) {
            error_log('CircleRelate::update_data: 数据为空或不是数组');
            return false;
        }
        
        // 确保必要的字段存在
        if (!isset($new_data['user_id']) || !isset($new_data['circle_id'])) {
            error_log('CircleRelate::update_data: 缺少必要字段(user_id或circle_id)');
            return false;
        }
        
        // 验证用户和圈子关系
        $validation = self::validate_relationship($new_data['user_id'], $new_data['circle_id']);
        if (!$validation['success']) {
            error_log('CircleRelate::update_data: ' . $validation['message']);
            return false;
        }
        
        // 定义数据格式
        $format = array(
            'id'           => '%d',
            'user_id'      => '%d',
            'circle_id'    => '%d',
            'circle_role'  => '%s',
            'join_date'    => '%s',
            'end_date'     => '%s',
            'circle_key'   => '%s',
            'circle_value' => '%s'
        );
        
        // 准备数据格式数组
        $format_new_data = array();
        foreach ($new_data as $k => $v) {
            if (isset($format[$k])) {
                $format_new_data[] = $format[$k];
            } else {
                // 移除未定义的字段
                unset($new_data[$k]);
            }
        }
        
        // 确保数据类型正确
        if (isset($new_data['user_id'])) {
            $new_data['user_id'] = (int)$new_data['user_id'];
        }
        
        if (isset($new_data['circle_id'])) {
            $new_data['circle_id'] = (int)$new_data['circle_id'];
        }
        
        if (isset($new_data['id'])) {
            $new_data['id'] = (int)$new_data['id'];
        }
        
        // 处理日期格式
        if (isset($new_data['join_date']) && !$new_data['join_date']) {
            $new_data['join_date'] = current_time('mysql');
        }
        
        if (isset($new_data['end_date']) && !$new_data['end_date']) {
            $new_data['end_date'] = '0000-00-00 00:00:00';
        }
        
        // 验证circle_role值
        if (isset($new_data['circle_role']) && !in_array($new_data['circle_role'], ['admin', 'staff', 'member'])) {
            error_log('CircleRelate::update_data: 无效的circle_role值: ' . $new_data['circle_role']);
            return false;
        }
        
        try {
            // 根据数据是否存在执行插入或更新操作
            global $wpdb;
            $table_name = $wpdb->prefix . 'islide_circle_related';
            
            // 检查表是否存在
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            if (!$table_exists) {
                error_log('CircleRelate::update_data: 表不存在: ' . $table_name);
                return false;
            }
            
            // 开始事务处理，确保操作的原子性
            $wpdb->query('START TRANSACTION');
            
            try {
                // 确保用户在一个圈子中只有一条记录，如果用户角色发生变化，则先删除旧记录
                $delete_result = $wpdb->query($wpdb->prepare(
                    "DELETE FROM $table_name WHERE user_id = %d AND circle_id = %d",
                    $new_data['user_id'],
                    $new_data['circle_id']
                ));
                
                if ($delete_result === false) {
                    throw new \Exception('删除旧记录失败: ' . $wpdb->last_error);
                }
                
                // 插入新记录
                $insert_result = $wpdb->insert($table_name, $new_data, $format_new_data);
                if ($insert_result === false) {
                    throw new \Exception('插入新记录失败: ' . $wpdb->last_error);
                }
                
                // 提交事务
                $wpdb->query('COMMIT');
                return true;
            } catch (\Exception $e) {
                // 回滚事务
                $wpdb->query('ROLLBACK');
                throw $e;
            }
        } catch (\Exception $e) {
            // 记录错误日志
            error_log('CircleRelate::update_data 出错：' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 验证用户和圈子的关系
     * 
     * @author ifyn
     * @param int $user_id 用户ID
     * @param int $circle_id 圈子ID
     * @return array 验证结果数组，包含success和message字段
     */
    private static function validate_relationship($user_id, $circle_id) {
        $result = [
            'success' => true,
            'message' => ''
        ];
        
        // 检查用户ID
        if (!is_numeric($user_id) || $user_id <= 0) {
            $result['success'] = false;
            $result['message'] = '无效的用户ID: ' . $user_id;
            return $result;
        }
        
        // 检查圈子ID
        if (!is_numeric($circle_id) || $circle_id <= 0) {
            $result['success'] = false;
            $result['message'] = '无效的圈子ID: ' . $circle_id;
            return $result;
        }
        
        // 检查用户是否存在
        $user = get_user_by('id', $user_id);
        if (!$user) {
            $result['success'] = false;
            $result['message'] = '用户不存在，ID: ' . $user_id;
            return $result;
        }
        
        // 检查圈子是否存在
        if (!term_exists($circle_id, 'circle_cat')) {
            $result['success'] = false;
            $result['message'] = '圈子不存在，ID: ' . $circle_id;
            return $result;
        }
        
        return $result;
    }
    
    /**
     * 获取圈子关系数据
     *
     * @author  ifyn
     * @param   array $args 查询参数，支持的键：id, user_id, circle_id, circle_role, circle_key, circle_value, count
     * @return  array 查询结果数组
     */
    public static function get_data($args) {
        if (empty($args) || !is_array($args)) {
            error_log('CircleRelate::get_data: 参数为空或不是数组');
            return array();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_circle_related';
        
        // 检查表是否存在
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            error_log('CircleRelate::get_data: 表不存在: ' . $table_name);
            return array();
        }
        
        $where_clauses = array();
        $sql_params = array();
        
        // 构建查询条件
        if (isset($args['id']) && $args['id'] !== '') {
            if (!is_numeric($args['id'])) {
                error_log('CircleRelate::get_data: 无效的id参数: ' . $args['id']);
                return array();
            }
            $where_clauses[] = '`id` = %d';
            $sql_params[] = (int)$args['id'];
        }
        
        if (isset($args['user_id']) && $args['user_id'] !== '') {
            if (!is_numeric($args['user_id'])) {
                error_log('CircleRelate::get_data: 无效的user_id参数: ' . $args['user_id']);
                return array();
            }
            $where_clauses[] = '`user_id` = %d';
            $sql_params[] = (int)$args['user_id'];
        }
        
        if (isset($args['circle_id']) && $args['circle_id'] !== '') {
            if (!is_numeric($args['circle_id'])) {
                error_log('CircleRelate::get_data: 无效的circle_id参数: ' . $args['circle_id']);
                return array();
            }
            $where_clauses[] = '`circle_id` = %d';
            $sql_params[] = (int)$args['circle_id'];
        }
        
        if (isset($args['circle_role']) && $args['circle_role'] !== '') {
            if (!is_string($args['circle_role'])) {
                error_log('CircleRelate::get_data: 无效的circle_role参数类型');
                return array();
            }
            
            // 验证circle_role值
            if (!in_array($args['circle_role'], ['admin', 'staff', 'member'])) {
                error_log('CircleRelate::get_data: 无效的circle_role值: ' . $args['circle_role']);
                return array();
            }
            
            $where_clauses[] = '`circle_role` = %s';
            $sql_params[] = $args['circle_role'];
        }
        
        if (isset($args['circle_key']) && $args['circle_key'] !== '') {
            if (!is_string($args['circle_key'])) {
                error_log('CircleRelate::get_data: 无效的circle_key参数类型');
                return array();
            }
            $where_clauses[] = '`circle_key` = %s';
            $sql_params[] = sanitize_text_field($args['circle_key']);
        }
        
        if (isset($args['circle_value']) && $args['circle_value'] !== '') {
            if (!is_string($args['circle_value'])) {
                error_log('CircleRelate::get_data: 无效的circle_value参数类型');
                return array();
            }
            $where_clauses[] = '`circle_value` = %s';
            $sql_params[] = sanitize_text_field($args['circle_value']);
        }
        
        // 没有查询条件时返回空数组
        if (empty($where_clauses)) {
            error_log('CircleRelate::get_data: 缺少查询条件');
            return array();
        }
        
        // 构建 WHERE 子句
        $where_sql = implode(' AND ', $where_clauses);
        
        // 添加排序和限制
        $limit = 1; // 默认限制为1
        if (isset($args['count'])) {
            if (!is_numeric($args['count'])) {
                error_log('CircleRelate::get_data: 无效的count参数: ' . $args['count']);
            } else {
                $count = (int)$args['count'];
                // 设置合理的查询限制
                $limit = ($count > 0 && $count < 100) ? $count : 1;
            }
        }
        
        try {
            // 构建完整的 SQL 查询
            $sql = $wpdb->prepare(
                "SELECT * FROM $table_name WHERE $where_sql ORDER BY id DESC LIMIT %d",
                array_merge($sql_params, array($limit))
            );
            
            // 执行查询
            $res = $wpdb->get_results($sql, ARRAY_A);
            
            // 确保返回数组
            return is_array($res) ? $res : array();
        } catch (\Exception $e) {
            error_log('CircleRelate::get_data 出错: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * 删除圈子关系数据
     *
     * @author  ifyn
     * @param   array    $args 删除条件，支持的键：id, user_id, circle_id
     * @return  int|bool 删除的行数或失败时返回false
     */
    public static function delete_data($args) {
        if (empty($args) || !is_array($args)) {
            error_log('CircleRelate::delete_data: 参数为空或不是数组');
            return false;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_circle_related';
        
        // 检查表是否存在
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            error_log('CircleRelate::delete_data: 表不存在: ' . $table_name);
            return false;
        }
        
        $where = array();
        $where_format = array();
        
        // 构建删除条件
        if (isset($args['id'])) {
            if (!is_numeric($args['id']) || $args['id'] <= 0) {
                error_log('CircleRelate::delete_data: 无效的id参数: ' . $args['id']);
                return false;
            }
            $where['id'] = (int)$args['id'];
            $where_format[] = '%d';
        }
        
        if (isset($args['user_id']) && $args['user_id'] !== '') {
            if (!is_numeric($args['user_id']) || $args['user_id'] <= 0) {
                error_log('CircleRelate::delete_data: 无效的user_id参数: ' . $args['user_id']);
                return false;
            }
            $where['user_id'] = (int)$args['user_id'];
            $where_format[] = '%d';
        }
        
        if (isset($args['circle_id']) && $args['circle_id'] !== '') {
            if (!is_numeric($args['circle_id']) || $args['circle_id'] <= 0) {
                error_log('CircleRelate::delete_data: 无效的circle_id参数: ' . $args['circle_id']);
                return false;
            }
            $where['circle_id'] = (int)$args['circle_id'];
            $where_format[] = '%d';
        }
        
        // 没有删除条件时返回失败
        if (empty($where)) {
            error_log('CircleRelate::delete_data: 缺少删除条件');
            return false;
        }
        
        try {
            // 添加事务处理，确保删除操作的原子性
            $wpdb->query('START TRANSACTION');
            
            // 执行删除操作
            $result = $wpdb->delete($table_name, $where, $where_format);
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                error_log('CircleRelate::delete_data: 删除失败: ' . $wpdb->last_error);
                return false;
            }
            
            $wpdb->query('COMMIT');
            return $result;
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('CircleRelate::delete_data 出错: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取符合条件的圈子关系数量
     *
     * @author  ifyn
     * @param   array $arg 查询参数，支持的键：user_id, circle_id, circle_role, circle_key, circle_value
     * @return  int   匹配的记录数量
     */
    public static function get_count($arg) {
        if (empty($arg) || !is_array($arg)) {
            error_log('CircleRelate::get_count: 参数为空或不是数组');
            return 0;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_circle_related';
        
        // 检查表是否存在
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            error_log('CircleRelate::get_count: 表不存在: ' . $table_name);
            return 0;
        }
        
        $where_clauses = array();
        $sql_params = array();
        
        // 构建查询条件
        if (isset($arg['user_id']) && $arg['user_id'] !== '') {
            if (!is_numeric($arg['user_id']) || $arg['user_id'] <= 0) {
                error_log('CircleRelate::get_count: 无效的user_id参数: ' . $arg['user_id']);
                return 0;
            }
            $where_clauses[] = '`user_id` = %d';
            $sql_params[] = (int)$arg['user_id'];
        }
        
        if (isset($arg['circle_id']) && $arg['circle_id'] !== '') {
            if (!is_numeric($arg['circle_id']) || $arg['circle_id'] <= 0) {
                error_log('CircleRelate::get_count: 无效的circle_id参数: ' . $arg['circle_id']);
                return 0;
            }
            $where_clauses[] = '`circle_id` = %d';
            $sql_params[] = (int)$arg['circle_id'];
        }
        
        if (isset($arg['circle_role']) && $arg['circle_role'] !== '') {
            if (!is_string($arg['circle_role'])) {
                error_log('CircleRelate::get_count: 无效的circle_role参数类型');
                return 0;
            }
            
            // 验证circle_role值
            if (!in_array($arg['circle_role'], ['admin', 'staff', 'member'])) {
                error_log('CircleRelate::get_count: 无效的circle_role值: ' . $arg['circle_role']);
                return 0;
            }
            
            $where_clauses[] = '`circle_role` = %s';
            $sql_params[] = $arg['circle_role'];
        }
        
        if (isset($arg['circle_key']) && $arg['circle_key'] !== '') {
            if (!is_string($arg['circle_key'])) {
                error_log('CircleRelate::get_count: 无效的circle_key参数类型');
                return 0;
            }
            $where_clauses[] = '`circle_key` = %s';
            $sql_params[] = sanitize_text_field($arg['circle_key']);
        }
        
        if (isset($arg['circle_value']) && $arg['circle_value'] !== '') {
            if (!is_string($arg['circle_value'])) {
                error_log('CircleRelate::get_count: 无效的circle_value参数类型');
                return 0;
            }
            $where_clauses[] = '`circle_value` = %s';
            $sql_params[] = sanitize_text_field($arg['circle_value']);
        }
        
        // 没有查询条件时返回0
        if (empty($where_clauses)) {
            error_log('CircleRelate::get_count: 缺少查询条件');
            return 0;
        }
        
        // 构建 WHERE 子句
        $where_sql = implode(' AND ', $where_clauses);
        
        try {
            // 构建完整的 SQL 查询
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE $where_sql",
                $sql_params
            );
            
            // 执行查询并返回结果
            return (int)$wpdb->get_var($sql);
        } catch (\Exception $e) {
            error_log('CircleRelate::get_count 出错: ' . $e->getMessage());
            return 0;
        }
    }
}