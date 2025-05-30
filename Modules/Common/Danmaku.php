<?php
/**
 * 弹幕功能管理类
 * 
 * 处理视频弹幕的发送和获取功能
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;

class Danmaku {
    /**
     * 初始化弹幕相关功能
     * 
     * @author  ifyn
     * @return  void
     */
    public function init() {
        // 初始化代码可在此添加
    }
    
    /**
     * 发送弹幕
     * 
     * @author  ifyn
     * @param   array  $data 弹幕数据，包含 cid, post_id, type, time, color, text 字段
     * @return  mixed  成功返回 true，失败返回包含错误信息的数组
     */
    public static function send_danmaku($data) {
        // 参数验证
        if (empty($data) || !is_array($data)) {
            return array('error' => __('参数错误'));
        }
        
        // 检查必要参数是否存在
        if (!isset($data['cid']) || !isset($data['post_id']) || !isset($data['type']) || 
            !isset($data['time']) || !isset($data['color']) || !isset($data['text'])) {
            return array('error' => __('参数不完整'));
        }
        
        // 提取参数
        $cid = isset($data['cid']) ? sanitize_text_field($data['cid']) : '';
        $post_id = isset($data['post_id']) ? (int)$data['post_id'] : 0;
        $type = isset($data['type']) ? sanitize_text_field($data['type']) : '';
        $time = isset($data['time']) ? (int)$data['time'] : 0;
        $color = isset($data['color']) ? sanitize_text_field($data['color']) : '';
        $text = isset($data['text']) ? $data['text'] : '';
        
        // 获取当前用户ID
        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            return array('error' => __('请先登录'));
        }
        
        // 清理和验证文本内容
        $text = str_replace(array('{{', '}}'), '', $text);
        $text = sanitize_textarea_field($text);
        
        if (empty($text)) {
            return array('error' => __('消息不可为空'));
        }
        
        // 检查 post_id 对应的内容是否存在
        if ($post_id && !get_post($post_id)) {
            return array('error' => __('内容不存在'));
        }
        
        // 插入数据库
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_danmuku';
        
        // 检查表是否存在
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            return array('error' => __('数据表不存在'));
        }
        
        // 准备插入数据
        $insert_data = array(
            'cid' => $cid,
            'post_id' => $post_id,
            'user_id' => $current_user_id,
            'type' => $type,
            'time' => $time,
            'color' => $color,
            'text' => $text,
            'date' => current_time('mysql')
        );
        
        // 数据格式
        $data_format = array(
            '%s', // cid
            '%d', // post_id
            '%d', // user_id
            '%s', // type
            '%d', // time
            '%s', // color
            '%s', // text
            '%s'  // date
        );
        
        // 执行插入操作
        $res = $wpdb->insert($table_name, $insert_data, $data_format);
        
        if ($res) {
            // 获取新插入的ID
            $new_id = $wpdb->insert_id;
            
            return array(
                'success' => true,
                'id' => $new_id
            );
        }
        
        return array('error' => __('插入失败，请稍后重试'));
    }
    
    /**
     * 获取指定内容的弹幕列表
     * 
     * @author  ifyn
     * @param   string $cid 内容标识符
     * @return  array  包含弹幕列表的数组或错误信息
     */
    public static function get_danmaku($cid) {
        // 参数验证
        if (empty($cid)) {
            return array('error' => __('参数错误'));
        }
        
        $cid = sanitize_text_field($cid);
        
        global $wpdb;
        $user_id = get_current_user_id();
        $table_name = $wpdb->prefix . 'islide_danmuku';
        
        // 检查表是否存在
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            return array('error' => __('数据表不存在'));
        }
        
        // 获取所有弹幕数据
        $res = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table_name WHERE `cid` = %s ORDER BY `date` DESC", $cid),
            ARRAY_A
        );
        
        // 处理查询错误
        if ($res === null) {
            return array('error' => __('查询失败，请稍后重试'));
        }
        
        // 获取弹幕总数
        $count = count($res);
        
        // 处理弹幕数据
        if ($count > 0) {
            foreach ($res as &$value) {
                // 转换数值类型字段
                $value['id'] = isset($value['id']) ? (int)$value['id'] : 0;
                $value['post_id'] = isset($value['post_id']) ? (int)$value['post_id'] : 0;
                $value['user_id'] = isset($value['user_id']) ? (int)$value['user_id'] : 0;
                $value['time'] = isset($value['time']) ? (int)$value['time'] : 0;
                
                // 标记当前用户的弹幕
                if (isset($value['user_id']) && $value['user_id'] !== 0 && $value['user_id'] == $user_id) {
                    $value['isMe'] = true;
                } else {
                    $value['isMe'] = false;
                }
                
                // 如果需要，可以在此添加用户信息
                if (isset($value['user_id']) && $value['user_id'] > 0) {
                    $user_data = get_userdata($value['user_id']);
                    if ($user_data) {
                        $value['user_name'] = $user_data->display_name;
                        $value['user_avatar'] = get_avatar_url($value['user_id'], array('size' => 32));
                    }
                }
                
                // 确保文本安全
                if (isset($value['text'])) {
                    $value['text'] = esc_html($value['text']);
                }
            }
            unset($value); // 避免引用残留
        }
        
        // 返回结果
        return array(
            'cid' => $cid,
            'danmaku' => $res,
            'count' => $count
        );
    }
    
    /**
     * 删除弹幕（可选功能）
     * 
     * @author  ifyn
     * @param   int    $id      弹幕ID
     * @param   int    $user_id 用户ID，默认为当前登录用户
     * @return  mixed  成功返回 true，失败返回包含错误信息的数组
     */
    public static function delete_danmaku($id, $user_id = 0) {
        // 参数验证
        $id = (int)$id;
        if (empty($id)) {
            return array('error' => __('参数错误'));
        }
        
        // 获取当前用户ID
        if (empty($user_id)) {
            $user_id = get_current_user_id();
            if (!$user_id) {
                return array('error' => __('请先登录'));
            }
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_danmuku';
        
        // 检查表是否存在
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            return array('error' => __('数据表不存在'));
        }
        
        // 检查弹幕是否存在及权限
        $danmaku = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id),
            ARRAY_A
        );
        
        if (!$danmaku) {
            return array('error' => __('弹幕不存在'));
        }
        
        // 检查用户权限（只能删除自己的弹幕，管理员除外）
        if ($danmaku['user_id'] != $user_id && !current_user_can('manage_options')) {
            return array('error' => __('没有权限删除此弹幕'));
        }
        
        // 执行删除操作
        $result = $wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        );
        
        if ($result) {
            return array('success' => true);
        }
        
        return array('error' => __('删除失败，请稍后重试'));
    }
    
    /**
     * 获取用户弹幕列表
     * 
     * @author  ifyn
     * @param   int    $user_id 用户ID，默认为当前登录用户
     * @param   int    $page    页码，默认为1
     * @param   int    $limit   每页数量，默认为20
     * @return  array  用户弹幕列表或错误信息
     */
    public static function get_user_danmaku($user_id = 0, $page = 1, $limit = 20) {
        // 获取当前用户ID
        if (empty($user_id)) {
            $user_id = get_current_user_id();
            if (!$user_id) {
                return array('error' => __('请先登录'));
            }
        }
        
        // 验证分页参数
        $page = max(1, (int)$page);
        $limit = min(50, max(1, (int)$limit));
        $offset = ($page - 1) * $limit;
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_danmuku';
        
        // 检查表是否存在
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            return array('error' => __('数据表不存在'));
        }
        
        // 获取总数
        $total = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE user_id = %d", $user_id)
        );
        
        // 获取弹幕列表
        $danmaku_list = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_id = %d ORDER BY date DESC LIMIT %d, %d",
                $user_id, $offset, $limit
            ),
            ARRAY_A
        );
        
        // 处理查询错误
        if ($danmaku_list === null) {
            return array('error' => __('查询失败，请稍后重试'));
        }
        
        // 处理弹幕数据
        foreach ($danmaku_list as &$item) {
            // 转换数值类型字段
            $item['id'] = (int)$item['id'];
            $item['post_id'] = (int)$item['post_id'];
            $item['user_id'] = (int)$item['user_id'];
            $item['time'] = (int)$item['time'];
            
            // 添加内容标题
            if ($item['post_id'] > 0) {
                $post = get_post($item['post_id']);
                if ($post) {
                    $item['post_title'] = $post->post_title;
                    $item['post_url'] = get_permalink($post->ID);
                }
            }
            
            // 确保文本安全
            $item['text'] = esc_html($item['text']);
        }
        unset($item); // 避免引用残留
        
        // 返回结果
        return array(
            'list' => $danmaku_list,
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        );
    }
}