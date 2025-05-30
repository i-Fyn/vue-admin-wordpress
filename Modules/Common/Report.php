<?php
/**
 * 投诉与举报管理类
 * 
 * 处理用户投诉和举报的提交、查询和处理功能
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;
use islide\Modules\Common\User;

class Report {
    /**
     * 初始化函数，注册钩子
     *
     * @author  ifyn
     * @return  void
     */
    public function init(){
        // 可以在此处添加初始化代码
    }
    
    /**
     * 提交举报信息
     *
     * @author  ifyn
     * @param   array $data 举报数据，包含reported_id、reported_type、content和type
     * @return  int|array   成功返回插入ID，失败返回错误信息
     */
    public static function report($data) {
        $user_id = get_current_user_id();
        if (!$user_id) return ['error' => '请先登录'];

        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_report';

        $reported_id = !empty($data['reported_id']) ? (int) $data['reported_id'] : 0;
        $reported_type = isset($data['reported_type']) ? sanitize_text_field($data['reported_type']) : 'post';

        if (!in_array($reported_type, ['post', 'user', 'comment','answer'])) {
            return ['error' => '无效的举报类型'];
        }

        if (!$reported_id) {
            return ['error' => '举报对象ID无效'];
        }

        // 根据类型检查被举报对象是否存在
        if ($reported_type === 'post' && !get_post_status($reported_id)) {
            return ['error' => '举报的文章不存在'];
        }

        if ($reported_type === 'user' && !get_userdata($reported_id)) {
            return ['error' => '举报的用户不存在'];
        }

        if (($reported_type === 'comment' || $reported_type === 'answer') && !get_comment($reported_id)) {
            return ['error' => '举报的评论不存在'];
        }

        // 内容处理
        $content = isset($data['content']) ? sanitize_textarea_field(str_replace(['{{', '}}'], '', $data['content'])) : '';

        if (empty($data['type'])) {
            return ['error' => '请选择举报原因'];
        }

        $types = self::get_report_types();
        if (!isset($types[$data['type']])) {
            return ['error' => '投诉类型错误'];
        }

        $insert_data = array(
            'user_id'       => $user_id,
            'reported_id'   => $reported_id,
            'reported_type' => $reported_type,
            'content'       => $content,
            'type'          => $types[$data['type']],
            'date'          => current_time('mysql'),
        );

        $insert_format = array('%d', '%d', '%s', '%s', '%s', '%s');

        if ($wpdb->insert($table_name, $insert_data, $insert_format)) {
            do_action('islide_report_insert_data', $insert_data);
            return $wpdb->insert_id;
        }

        return ['error' => '举报提交失败，请稍后重试'];
    }
    
    /**
     * 获取举报类型列表
     *
     * @author  ifyn
     * @return  array 举报类型列表
     */
    public static function get_report_types() {
        $types = islide_get_option('report_types');
        $types = !empty($types) ? array_column($types, 'type') : array();
        
        return $types;
    }
    
    /**
     * 获取举报列表
     *
     * @author  ifyn
     * @param   array $request 请求参数，包含分页、筛选等条件
     * @return  array          举报列表数据
     */
    public static function islide_get_report_list($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_report';

        // 分页参数
        $paged    = isset($request['paged']) ? max(1, intval($request['paged'])) : 1;
        $per_page = isset($request['size'])  ? max(1, intval($request['size']))  : 10;
        $offset   = ($paged - 1) * $per_page;

        // 条件筛选
        $where = 'WHERE 1=1';
        $params = [];

        if (!empty($request['type']) && $request['type'] !== 'all') {
            $where .= ' AND `type` = %s';
            $params[] = sanitize_text_field($request['type']);
        }

        if (isset($request['status']) && $request['status'] !== 'all' && $request['status'] !== '') {
            $where .= ' AND `status` = %d';
            $params[] = intval($request['status']);
        }

        if (!empty($request['user_id'])) {
            $where .= ' AND `user_id` = %d';
            $params[] = intval($request['user_id']);
        }

        // 总数统计
        $count_sql = "SELECT COUNT(*) FROM $table_name $where";
        $total = $params ? $wpdb->get_var($wpdb->prepare($count_sql, ...$params)) : $wpdb->get_var($count_sql);
        $pages = ceil($total / $per_page);

        // 数据查询
        $data_sql = "SELECT * FROM $table_name $where ORDER BY id DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $results = $wpdb->get_results($wpdb->prepare($data_sql, ...$params), ARRAY_A);

        $data = [];
        foreach ($results as $item) {
            // 添加用户昵称
            $user = get_userdata($item['user_id']);
            $item['name'] = $user ? $user->display_name : '未知用户';

            $data[] = $item;
        }

        return [
            'data'  => $data,
            'pages' => $pages,
            'count' => intval($total),
            'paged' => $paged,
        ];
    }

    /**
     * 更新举报状态
     *
     * @author  ifyn
     * @param   WP_REST_Request $request 请求对象
     * @return  array                    更新结果
     */
    public static function update_report($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_report';
        $data = $request->get_json_params();

        if (empty($data['id'])) {
            return ['error' => '缺少举报ID'];
        }

        $id = intval($data['id']);

        // 获取原始举报记录
        $report = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id), ARRAY_A);
        if (!$report) {
            return ['error' => '举报记录不存在'];
        }

        $status = isset($data['status']) ? intval($data['status']) : 0;
        $mark = isset($data['mark']) ? sanitize_text_field($data['mark']) : '';

        $update_data = [
            'status' => $status,
            'mark'   => $mark,
        ];

        $result = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $id],
            ['%d', '%s'],
            ['%d']
        );

        if ($result === false) {
            return ['error' => '数据库更新失败'];
        }

        // 处理逻辑：status 为 1 且类型为 post 或 comment -> 移入回收站
        if ($status == 1) {
            $reported_type = $report['reported_type'];
            $reported_id   = intval($report['reported_id']);
            $reported_content = isset($report['mark']) ? $report['mark'] : '';
            
            do_action('after_report_success', $reported_id, $reported_type, $reported_content);
        }

        return [
            'success' => true,
            'message' => '更新成功，已处理举报内容',
            'updated_id' => $id
        ];
    }
}