<?php
/**
 * 消息功能管理类
 * 
 * 处理系统各类消息的发送、获取和管理功能
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;
use islide\Modules\Common\User;
use islide\Modules\Common\Comment;
use islide\Modules\Common\Post;

/**
 * 消息类型
 * chat -- 聊天
 * wallet -- 钱包通知
 * serve -- 服务通知
 * system -- 系统通知
 * follow -- 粉丝、新粉丝
 * like -- 赞收藏喜欢
 * comment -- 评论回复
 * 
 * //全局接收消息
 * 10000001 所有人
 * 10000002 所有VIP
 * 10000003 网站管理员
 * 
 * */

class Message{
    /**
     * 初始化函数，注册必要的钩子
     * 
     * @author  ifyn
     * @return  void
     */
    public function init(){
        // 出售者通知
        add_action('islide_order_notify_return', array($this, 'order_notify_return'), 10, 1);
        
        // 关注用户与取消关注通知
        add_action('islide_user_follow_action', array($this, 'user_follow_action_message'), 10, 3);
        
        // 文章收藏通知
        add_action('islide_post_favorite', array($this, 'post_favorite_message'), 10, 3);
        
        // 文章点赞
        add_action('islide_post_vote', array($this, 'post_vote_message'), 10, 3);
        
        // 评论通知
        add_action('islide_comment_post', array($this, 'comment_post_message'));
        
        // 评论点赞
        add_action('islide_comment_vote', array($this, 'comment_vote_message'), 10, 3);
        
        // 等级升级通知
        add_action('islide_update_user_lv', array($this, 'update_user_lv_message'), 10, 2);
        
        // 会员到期通知
        add_filter('islide_check_user_vip_time', array($this, 'check_user_vip_time_message'));
        
        // 签到成功通知
        add_action('islide_user_signin', array($this, 'user_signin_message'), 10, 2);
        
        // 任务完成通知
        add_action('islide_complete_task_action', array($this, 'task_complete_message'), 10, 2);
        
        // 认证通过通知
        add_action('islide_submit_verify_check_success', array($this, 'verify_check_success_message'), 10, 1);
    }
    
    /**
     * 创建消息
     *
     * @author  ifyn
     * @param   array $message_data 消息数据，包括以下字段：
     *                              - sender_id:   发送者ID(int)
     *                              - receiver_id: 接收者ID(int)
     *                              - title:       消息标题(string)
     *                              - content:     消息内容(string)
     *                              - type:        消息类型(string)
     *                              - post_id:     文章ID（可选，默认为0）(int)
     *                              - mark:        附加数据（可选）(mixed)
     * @return  int|false           新创建的消息ID，如果创建失败则返回false
     */
    public static function update_message($message_data) {
        global $wpdb;
        
        // 参数验证
        if (empty($message_data) || !is_array($message_data)) {
            return false;
        }
        
        if (!isset($message_data['receiver_id']) || empty($message_data['type'])) {
            return false;
        }
        
        $table_name = $wpdb->prefix . 'islide_message'; 
    
        // 处理内容，确保安全
        $content = '';
        if (isset($message_data['content'])) {
            $content = wp_unslash(wpautop($message_data['content']));
            $content = str_replace(array('{{','}}'), '', $content);
            $content = sanitize_textarea_field($content);
        }
    
        // 准备数据
        $data = array(
            'sender_id' => isset($message_data['sender_id']) ? (int)$message_data['sender_id'] : 0,
            'receiver_id' => (int)$message_data['receiver_id'],
            'title' => isset($message_data['title']) ? sanitize_text_field($message_data['title']) : '',
            'content' => $content,
            'type' => sanitize_text_field($message_data['type']),
            'date' => current_time('mysql'),
            'post_id' => isset($message_data['post_id']) ? (int)$message_data['post_id'] : 0,
            'mark' => isset($message_data['mark']) ? maybe_serialize($message_data['mark']) : ''
        );
    
        // 准备格式
        $format = array(
            '%d', // sender_id
            '%d', // receiver_id
            '%s', // title
            '%s', // content
            '%s', // type
            '%s', // date
            '%d', // post_id
            '%s'  // mark
        );
        
        // 插入数据库
        $result = $wpdb->insert($table_name, $data, $format);
        
        if ($result) {
            $message_id = $wpdb->insert_id;
            
            // 触发消息创建后的钩子
            do_action('islide_message_insert_data', $data);
            
            return $message_id;
        }
        
        return false;
    }
    
    /**
     * 删除消息
     *
     * @author  ifyn
     * @param   array $args 删除条件，可包含以下字段：
     *                      - id:         消息ID(int)
     *                      - sender_id:  发送者ID(int)
     *                      - receiver_id:接收者ID(int)
     *                      - type:       消息类型(string)
     *                      - post_id:    文章ID(int)
     * @return  int|false   影响的行数或false(失败)
     */
    public function delete_message($args) {
        global $wpdb;
        
        // 参数验证
        if (empty($args) || !is_array($args)) {
            return false;
        }
        
        $table_name = $wpdb->prefix . 'islide_message';
        $arr = array();

        // 构建条件
        if (isset($args['id'])) {
            $arr['ID'] = (int)$args['id'];
        }
        
        if (isset($args['sender_id'])) {
            $arr['sender_id'] = (int)$args['sender_id'];
        }
        
        if (isset($args['receiver_id'])) {
            $arr['receiver_id'] = (int)$args['receiver_id'];
        }
        
        if (isset($args['type']) && $args['type'] !== '') {
            $arr['type'] = sanitize_text_field($args['type']);
        }

        if (isset($args['post_id'])) {
            $arr['post_id'] = (int)$args['post_id'];
        }
        
        // 至少需要一个条件
        if (empty($arr)) {
            return false;
        }
        
        // 执行删除
        return $wpdb->delete($table_name, $arr);
    }
    
    /**
     * 发送私信消息
     *
     * @author  ifyn
     * @param   int    $user_id       接收消息的用户ID
     * @param   string $content       消息内容
     * @param   int    $attachment_id 附件ID(可选)
     * @return  array|false           消息数据数组或失败信息
     */
    public static function send_message($user_id, $content, $attachment_id = 0){
        // 验证接收者
        if (empty($user_id) || !is_numeric($user_id)) {
            return array('error' => '收件人不可为空');
        }
        
        // 验证发送者
        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            return array('error' => '请先登录');
        }
        
        // 不能给自己发私信
        if ((int)$current_user_id === (int)$user_id) {
            return array('error' => '不能给自己发私信');
        }
        
        // 验证接收者是否存在
        if (!get_user_by('id', $user_id)) {
            return array('error' => '收件人不存在');
        }
        
        // 处理内容安全问题
        $content = isset($content) ? $content : '';
        $content = str_replace(array('{{', '}}'), '', $content);
        $content = sanitize_textarea_field($content);
        
        // 检查附件
        $image_data = null;
        if (!empty($attachment_id) && is_numeric($attachment_id)) {
            $image_data = wp_get_attachment_image_src((int)$attachment_id, 'full');
        }
        
        // 验证消息内容
        if (empty($content) && !$image_data) {
            return array('error' => '消息不可为空');
        }
        
        if (!trim(strip_tags($content)) && !$image_data) {
            return array('error' => '消息非法');
        }
        
        // 准备消息数据
        $data = array(
            'sender_id' => (int)$current_user_id,
            'receiver_id' => (int)$user_id,
            'content' => $content,
            'type' => 'chat',
        );
        
        // 如果有图片附件，添加到mark字段
        if ($image_data) {
            $data['mark'] = array(
                'id' => (int)$attachment_id,
                'url' => $image_data[0],
                'width' => $image_data[1],
                'height' => $image_data[2],
                'type' => 'image'
            );
        }
        
        // 保存消息
        $message_id = self::update_message($data);
        
        if ($message_id) {
            // 触发消息发送事件
            do_action('islide_send_message_action', $data);
            
            // 获取新发送的消息数据
            global $wpdb;
            $table_name = $wpdb->prefix . 'islide_message';
            $message = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $table_name WHERE ID = %d",
                    $message_id
                ), 
                ARRAY_A
            );
            
            // 格式化返回数据
            if ($message) {
                return array(
                    'id' => (int)$message['ID'],
                    'from' => User::get_user_public_data($message['sender_id'], false),
                    'to' => User::get_user_public_data($message['receiver_id'], false),
                    'date' => self::time_ago($message['date']),
                    'title' => $message['title'],
                    'content' => Comment::comment_filters($message['content']),
                    'type' => $message['type'],
                    'mark' => maybe_unserialize($message['mark']),
                    'is_self' => false,
                    'time' => wp_strtotime($message['date']),
                    'is_read' => self::check_is_read($message['read_by'], $message['receiver_id'])
                );
            }
        }
        
        return false;
    }
    
    /**
     * 获取消息数量
     *
     * @author  ifyn
     * @param   array $args 查询条件，可包含以下字段：
     *                      - sender_id:   发送者ID(int)
     *                      - receiver_id: 接收者ID(int)
     *                      - type:        消息类型(string)
     *                      - post_id:     文章ID(int)
     *                      - read_by:     已读用户ID(int)
     * @return  int         消息数量
     */
    public static function get_message_count($args){
        global $wpdb;
        
        // 参数验证
        if (empty($args) || !is_array($args)) {
            return 0;
        }
        
        $table_name = $wpdb->prefix . 'islide_message';
        $where = '';
        $params = array();
        
        // 构建查询条件
        if (isset($args['sender_id']) && $args['sender_id'] !== '') {
            $where .= ' AND `sender_id` = %d';
            $params[] = (int)$args['sender_id'];
        }
    
        if (isset($args['receiver_id']) && $args['receiver_id'] !== '') {
            $where .= ' AND (`receiver_id` = %d OR `receiver_id` IN (10000001, 10000002, 10000003))';
            $params[] = (int)$args['receiver_id'];
        }
    
        if (isset($args['type'])) {
            $where .= ' AND `type` = %s';
            $params[] = sanitize_text_field($args['type']);
        }
    
        if (isset($args['post_id']) && $args['post_id'] !== '') {
            $where .= ' AND `post_id` = %d';
            $params[] = (int)$args['post_id'];
        }
    
        if (isset($args['read_by']) && $args['read_by'] !== '') {
            $where .= ' AND (FIND_IN_SET(%d, `read_by`) = 0 OR `read_by` = "")';
            $params[] = (int)$args['read_by'];
        }
    
        // 没有条件则返回0
        if (empty($where)) {
            return 0;
        }
        
        // 处理查询条件
        $where = ltrim($where, ' AND');
        
        // 构建并执行查询
        $query = "SELECT COUNT(*) FROM $table_name WHERE $where";
        $count = $wpdb->get_var($wpdb->prepare($query, $params));
        
        // 应用过滤器
        return apply_filters('islide_get_message_count', (int)$count, $args);
    }
    
    /**
     * 获取未读消息并按类型返回数量
     *
     * @author  ifyn
     * @param   int   $receiver_id 接收者ID，默认为当前用户
     * @return  array              关联数组，以类型为键，数量为值，包含total总数
     */
    public static function get_unread_message_count($receiver_id = 0) {
        // 获取接收者ID
        if (!$receiver_id) {
            $receiver_id = get_current_user_id();
        }
        
        // 验证接收者
        if (!$receiver_id) {
            return array('total' => 0);
        }
    
        // 应用过滤器获取结果
        $results = apply_filters('get_unread_message_count', $receiver_id);
        
        // 如果没有结果，返回空数组
        if (empty($results) || !is_array($results)) {
            return array('total' => 0);
        }
        
        // 处理结果，按类型归类
        $unread_messages = array_column($results, 'count', 'type');
        
        // 确保所有值都是整数
        foreach ($unread_messages as $k => $v) {
            $unread_messages[$k] = intval($v);
        }
        
        // 添加总数
        $unread_messages['total'] = array_sum($unread_messages);
    
        return $unread_messages;
    }
    
    /**
     * 获取联系人列表
     *
     * @author  ifyn
     * @param   int   $paged 页码
     * @return  array        联系人列表数据或错误信息
     */
    public static function get_contact_list($paged) {
        // 获取当前用户ID
        $current_user_id = get_current_user_id();

        // 检查用户登录状态
        if (!$current_user_id) {
            return array('error' => '请先登录');
        }

        // 获取联系人列表数据
        $results = apply_filters('get_contact_list_data', $current_user_id);

        // 检查是否有错误
        if (isset($results['error'])) {
            return $results;
        }
        
        // 各种消息类型的图标和名称
        $messageTypes = [
            'vip' => array('name' => '大会员', 'avatar' => IS_THEME_URI.'/Assets/fontend/images/vip.webp'),
            'wallet' => array('name' => '钱包通知', 'avatar' => IS_THEME_URI.'/Assets/fontend/images/wallet.webp'),
            'serve' => array('name' => '服务通知', 'avatar' => IS_THEME_URI.'/Assets/fontend/images/serve.webp'),
            'system' => array('name' => '系统通知', 'avatar' => IS_THEME_URI.'/Assets/fontend/images/system.webp'),
            'follow' => array('name' => '新粉丝', 'avatar' => IS_THEME_URI.'/Assets/fontend/images/follow.webp'),
            'like' => array('name' => '收到的赞', 'avatar' => IS_THEME_URI.'/Assets/fontend/images/like.webp'),
            'comment' => array('name' => '互动消息', 'avatar' => IS_THEME_URI.'/Assets/fontend/images/comment.webp'),
            'circle' => array('name' => '圈子消息', 'avatar' => 'https://www.islide.com/wp-content/uploads/2023/10/islide.png'),
            'distribution' => array('name' => '推广返佣', 'avatar' => IS_THEME_URI.'/Assets/fontend/images/wallet.webp'),
        ];
        
        // 获取未读消息数量
        $unread = self::get_unread_message_count($current_user_id);
        
        // 处理结果数据
        $data = array();
        if (is_array($results) && !empty($results)) {
            foreach ($results as $v) {
                // 处理图片信息
                if (!empty($v['mark']) && empty($v['content']) && empty($v['title'])) {
                    $mark = maybe_unserialize($v['mark']);
                    
                    if (is_array($mark) && isset($mark['type']) && $mark['type'] == 'image') {
                        $v['content'] = '[图片]';
                    }
                }
                
                // 区分聊天消息和系统消息
                if ($v['type'] == 'chat') {
                    // 跳过特殊情况
                    if (($v['sender_id'] == $current_user_id && $v['receiver_id'] >= 10000001) || (int)$v['sender_id'] === 0) {
                        continue;
                    }
                    
                    // 确定对话另一方的用户ID
                    $userId = $v['sender_id'] != $current_user_id ? $v['sender_id'] : $v['receiver_id'];
                    $userData = User::get_user_public_data($userId, true);

                    $data[] = array(
                        'id' => (int)$v['ID'],
                        'from' => $userData,
                        'date' => self::time_ago($v['date'], true),
                        'content' => $v['content'],
                        'type' => $v['type'],
                        'unread' => self::get_message_count(array(
                            'sender_id' => $v['sender_id'] == $current_user_id ? $v['receiver_id'] : $v['sender_id'],
                            'receiver_id' => $current_user_id,
                            'type' => 'chat',
                            'read_by' => $current_user_id
                        ))
                    );
                } else {
                    // 替换内容中的动态数据
                    if ($v['post_id']) {
                        $v['content'] = self::replaceDynamicData($v['content'], array('post' => get_the_title($v['post_id'])));
                    }
                    
                    $data[] = array(
                        'id' => (int)$v['ID'],
                        'from' => isset($messageTypes[$v['type']]) ? $messageTypes[$v['type']] : array('name' => '未知消息', 'avatar' => ''),
                        'date' => self::time_ago($v['date'], true),
                        'content' => $v['content'] ?: $v['title'],
                        'type' => $v['type'],
                        'unread' => isset($unread[$v['type']]) ? $unread[$v['type']] : 0
                    );
                }
            }
        }
        
        return $data;
    }
    
    /**
     * 获取指定用户的联系人信息
     *
     * @author  ifyn
     * @param   int   $user_id 用户ID
     * @return  array          联系人信息或错误信息
     */
    public static function get_contact($user_id) {
        // 获取当前用户ID
        $current_user_id = get_current_user_id();
        $user_id = (int)$user_id;
        
        // 验证用户登录状态
        if (!$current_user_id) {
            return array('error' => '请先登录');
        }
        
        // 验证联系人ID
        if (!$user_id) {
            return array('error' => '错误联系人');
        }
        
        // 不能给自己发私信
        if ((int)$user_id == $current_user_id) {
            return array('error' => '不能给自己发私信');
        }
        
        // 验证联系人是否存在
        if (!get_user_by('id', $user_id)) {
            return array('error' => '收件人不存在');
        }

        // 获取联系人信息
        $userData = User::get_user_public_data($user_id, true);
        
        // 构建返回数据
        $data = array(
            'id' => 0,
            'from' => $userData,
            'date' => self::time_ago(wp_date("Y-m-d H:i:s"), true),
            'content' => '',
            'type' => 'chat',
            'unread' => 0
        );
        
        return $data;
    }
    
    
    /**
     * 获取消息列表
     *
     * @author  ifyn
     * @param   array $data 查询参数
     *                      - type: 消息类型
     *                      - user_id: 用户ID（聊天类型时使用）
     *                      - paged: 页码
     * @return  array       消息列表数据和分页信息
     */
    public static function get_message_list($data) {
        // 获取当前用户ID
        $current_user_id = get_current_user_id();

        // 验证用户登录状态
        if (!$current_user_id) {
            return array('error' => '请先登录');
        }
        
        // 应用过滤器获取消息列表
        $_results = apply_filters('get_message_list_data', $data, $current_user_id);

        // 检查是否有错误
        if (isset($_results['error'])) {
            return $_results;
        }
        
        // 获取结果数据
        $results = isset($_results['data']) && !empty($_results['data']) ? $_results['data'] : array();
        
        // 各种消息类型的图标和名称
        $messageTypes = [
            'vip' => array('name' => '大会员', 'avatar' => IS_THEME_URI.'/Assets/fontend/images/vip.webp'),
            'wallet' => array('name' => '钱包通知', 'avatar' => IS_THEME_URI.'/Assets/fontend/images/wallet.webp'),
            'serve' => array('name' => '服务通知', 'avatar' => IS_THEME_URI.'/Assets/fontend/images/serve.webp'),
            'system' => array('name' => '系统通知', 'avatar' => IS_THEME_URI.'/Assets/fontend/images/system.webp'),
            'follow' => array('name' => '新粉丝', 'avatar' => IS_THEME_URI.'/Assets/fontend/images/follow.webp'),
            'like' => array('name' => '收到的赞', 'avatar' => IS_THEME_URI.'/Assets/fontend/images/like.webp'),
            'comment' => array('name' => '互动消息', 'avatar' => IS_THEME_URI.'/Assets/fontend/images/comment.webp'),
            'circle' => array('name' => '圈子消息', 'avatar' => 'https://www.islide.com/wp-content/uploads/2023/10/islide.png'),
            'distribution' => array('name' => '推广返佣', 'avatar' => IS_THEME_URI.'/Assets/fontend/images/wallet.webp'),
        ];
        
        $data_result = array();
        
        if (!empty($results)) {
            // 某些消息类型需要反转顺序
            if (isset($data['type']) && in_array($data['type'], array('chat', 'vip', 'circle', 'distribution'))) {
                $results = array_reverse($results);
            }
            
            // 将所有消息标为已读
            self::mark_message_as_read($results);
            
            foreach ($results as $v) {
                if ($v['type'] == 'chat') {
                    // 跳过系统消息
                    if ((int)$v['sender_id'] === 0) {
                        continue;
                    }
                    
                    $data_result[] = array(
                        'id' => (int)$v['ID'],
                        'from' => User::get_user_public_data($v['sender_id'], false),
                        'date' => self::time_ago($v['date']),
                        'title' => $v['title'],
                        'content' => Comment::comment_filters($v['content']),
                        'type' => $v['type'],
                        'mark' => maybe_unserialize($v['mark']),
                        'is_self' => (int)$v['sender_id'] == $current_user_id,
                        'time' => wp_strtotime($v['date']),
                        'is_read' => true
                    );
                } else {
                    $post = array();
                    
                    if ($v['post_id']) {
                        // 获取文章缩略图
                        $thumb = islide_get_thumb(array(
                            'url' => Post::get_post_thumb($v['post_id']),
                            'width' => 100,
                            'height' => 100,
                        ));
                        
                        // 构建文章信息
                        $post = array(
                            'title' => get_the_title($v['post_id']),
                            'link' => get_permalink($v['post_id']),
                            'post_type' => get_post_type($v['post_id']),
                            'thumb' => $thumb
                        );
                        
                        // 处理评论和点赞类型的特殊消息
                        if (($v['type'] == 'comment' || $v['type'] == 'like') && !empty($v['mark'])) {
                            $mark = maybe_unserialize($v['mark']);
                            
                            if (is_array($mark) && isset($mark[0])) {
                                $comment = get_comment($mark[0]);
                                
                                if ($comment) {
                                    if ($v['type'] == 'comment') {
                                        $v['content'] = Comment::comment_filters($comment->comment_content);
                                    } else {
                                        $v['content'] = $v['content'] . '<p>' . Comment::comment_filters($comment->comment_content) . '</p>';
                                    }
                                    
                                    // 处理回复消息
                                    if (isset($mark[1])) {
                                        $parent_comment = get_comment($mark[1]);
                                        if ($parent_comment) {
                                            $v['content'] = '回复 <a href="' . get_author_posts_url($current_user_id) . '">@' . 
                                                            wp_get_current_user()->display_name . '</a> ：' . $v['content'] . 
                                                            '<p>' . Comment::comment_filters($parent_comment->comment_content) . '</p>';
                                        }
                                    }
                                }
                            }
                        } else {
                            // 替换内容中的动态数据
                            $v['content'] = self::replaceDynamicData($v['content'], array('post' => '<a href="' . $post['link'] . '">' . $post['title'] . '</a>'));
                        }
                    }
                    
                    $data_result[] = array(
                        'id' => (int)$v['ID'],
                        'from' => $v['sender_id'] ? User::get_user_public_data($v['sender_id'], false) : 
                                 (isset($messageTypes[$v['type']]) ? $messageTypes[$v['type']] : array('name' => '系统', 'avatar' => '')),
                        'post' => $post,
                        'date' => self::time_ago($v['date']),
                        'title' => $v['title'],
                        'content' => $v['content'] ?: $v['title'],
                        'type' => $v['type'],
                        'mark' => maybe_unserialize($v['mark']),
                        'time' => wp_strtotime($v['date']),
                        'is_read' => true
                    );
                }
            }
        }
        
        return array(
            'count' => isset($_results['count']) ? (int)$_results['count'] : 0,
            'pages' => isset($_results['pages']) ? (int)$_results['pages'] : 1,
            'data' => $data_result
        );
    }
    
    /**
     * 替换字符串中的动态数据
     *
     * @author  ifyn
     * @param   string $string 要处理的字符串
     * @param   array  $data   包含动态数据的关联数组，${} 中的内容作为键名
     * @return  string         替换后的字符串
     */
    public static function replaceDynamicData($string, $data) {
        if (empty($string) || !is_string($string)) {
            return $string;
        }
        
        if (empty($data) || !is_array($data)) {
            return $string;
        }
        
        $pattern = '/\${(.*?)}/'; // 匹配 ${} 格式的正则表达式
        preg_match_all($pattern, $string, $matches); // 查找所有匹配的 ${} 标记
    
        foreach ($matches[1] as $match) {
            if (isset($data[$match]) && !empty($data[$match])) {
                $string = str_replace('${' . $match . '}', $data[$match], $string); // 替换动态数据
            }
        }
    
        return $string;
    }
    
    /**
     * 检查给定的用户ID是否为已读用户
     *
     * @author  ifyn
     * @param   string $read_by 包含已读用户ID的字符串或序列化数组
     * @param   int    $user_id 要检查的用户ID
     * @return  bool            用户是否已读消息
     */
    public static function check_is_read($read_by, $user_id) {
        if (empty($read_by)) {
            return false;
        }

        // 如果是序列化数据，反序列化并检查
        if (is_serialized($read_by)) {
            $read_by_array = maybe_unserialize($read_by);
            return is_array($read_by_array) && in_array((int)$user_id, $read_by_array);
        }

        // 否则直接比较字符串
        return (string)$read_by === (string)$user_id;
    }
    
    /**
     * 将消息标记为已读
     *
     * @author  ifyn
     * @param   array $data 消息数据数组
     * @return  bool        操作结果
     */
    public static function mark_message_as_read($data) {
        if (empty($data) || !is_array($data)) {
            return false;
        }
        
        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            return false;
        }
        
        // 应用过滤器处理已读标记
        $as_read = apply_filters('mark_message_as_read', $data, $current_user_id);
        
        return $as_read !== false;
    }
    
    /**
     * 转换时间格式为友好显示
     *
     * @author  ifyn
     * @param   string $time      时间字符串，格式为'Y-m-d H:i:s'
     * @param   bool   $date_only 是否只显示日期
     * @return  string            格式化后的时间HTML
     */
    public static function time_ago($time, $date_only = false) {
        if (!is_string($time)) {
            return '';
        }
    
        $current_time = current_time('timestamp');
        $time_diff = $current_time - wp_strtotime($time);
        
        if ($time_diff < 1) {
            $output = '刚刚';
        } elseif ($time_diff <= 84600) {
            // 当天，显示时:分
            $output = wp_date('H:i', wp_strtotime($time));
        } elseif ($time_diff <= 172800) {
            // 昨天，显示"昨天 时:分"
            $output = sprintf('昨天 %s', wp_date('H:i', wp_strtotime($time)));
        } else {
            // 其他时间
            $date_format = ($date_only || wp_date('y', wp_strtotime($time)) == wp_date('y')) ? 'n-d' : 'y-m-d';
            $time_format = $date_only ? '' : ' H:i';
            $output = wp_date($date_format . $time_format, wp_strtotime($time));
        }
        
        return '<time class="islide-timeago" datetime="' . esc_attr($time) . '" itemprop="datePublished">' . esc_html($output) . '</time>';
    }
    
    /**
     * 处理订单通知
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  array       处理结果
     */
    public function order_notify_return($data) {
        if (empty($data) || !is_array($data)) {
            return $data;
        }
        
        // 获取作者ID
        $author_id = isset($data['post_id']) ? get_post_field('post_author', $data['post_id']) : 0;
        
        // 处理VIP购买类型
        if ($data['order_type'] == 'vip_goumai' && !empty($data['user_id'])) {
            $user_vip_exp_date = get_user_meta($data['user_id'], 'islide_vip_exp_date', true);
            $vip_date = (string)$user_vip_exp_date === '0' ? '永久' : wp_date('Y-m-d', $user_vip_exp_date);
            
            $roles = User::get_user_roles();
            $vip = isset($roles[$data['order_key']]) ? $roles[$data['order_key']]['name'] : '会员';
            
            $message_data = array(
                'sender_id' => 0,
                'receiver_id' => (int)$data['user_id'],
                'title' => $vip . '服务开通成功通知',
                'content' => '恭喜您已开通' . ((int)$data['order_value'] == 0 ? '永久' : $data['order_value'] . '天') . 
                             $vip . '服务，目前有效期至' . ((int)$data['order_value'] == 0 ? '永久' : $vip_date) . '。',
                'type' => 'vip',
                'mark' => array(
                    'meta' => array(
                        array(
                            'key' => '开通类型',
                            'value' => $data['pay_type'] == 'card' ? '卡密兑换' : '充值开通', // 其他类型: 系统发放、活动赠送、会员领取
                        ),
                        array(
                            'key' => '支付金额',
                            'value' => isset($data['order_total']) ? $data['order_total'] : '',
                        ),
                        array(
                            'key' => '当前状态',
                            'value' => $vip,
                        )
                    )
                )
            );
            
            // 卡密兑换不显示支付金额
            if ($data['pay_type'] == 'card') {
                unset($message_data['mark']['meta'][1]);
            }
            
            self::update_message($message_data);
        }
        // 处理充值类型
        else if ($data['order_type'] == 'money_chongzhi' || $data['order_type'] == 'credit_chongzhi') {
            $title = $data['order_type'] == 'money_chongzhi' ? '余额充值到账' : '积分充值到账';

            if ($data['pay_type'] == 'card') {
                $content = $data['order_type'] == 'money_chongzhi' ? 
                           sprintf('您已成功使用卡密兑换余额已到账：%s元', $data['order_total']) : 
                           sprintf('您已成功使用卡密兑换积分已到账：%s积分', $data['order_total']);
            } else {
                $content = $data['order_type'] == 'money_chongzhi' ? 
                           sprintf('余额充值已到账：%s元', $data['order_total']) : 
                           sprintf('您使用 ￥%s 购买积分已到账：%s积分', $data['order_total'], $data['order_value']);
            }
            
            self::update_message(array(
                'sender_id' => 0,
                'receiver_id' => (int)$data['user_id'],
                'title' => $data['pay_type'] == 'card' ? '卡密兑换到账' : $title,
                'content' => $content,
                'type' => 'wallet',
            ));
        }
        // 处理加入圈子类型
        else if ($data['order_type'] == 'join_circle') {
            $end_date = current_time('mysql');
            $join_data = \islide\Modules\Common\CircleRelate::get_data(array(
                'user_id' => (int)$data['user_id'],
                'circle_id' => (int)$data['post_id'],
            ));
            
            if (!empty($join_data[0])) {
                if ($join_data[0]['end_date'] == '0000-00-00 00:00:00') {
                    $end_date = '永久';
                } else {
                    $end_date = $join_data[0]['end_date'];
                }
            }
            
            $circle = get_term_by('id', (int)$data['post_id'], 'circle_cat');
            if (!$circle) {
                return $data;
            }
            
            $message_data = array(
                'sender_id' => 0,
                'receiver_id' => (int)$data['user_id'],
                'title' => $circle->name . '加入成功通知',
                'content' => '恭喜您已加入 ' . $circle->name . ' ，服务有效期至' . $end_date . '。',
                'type' => 'circle',
                'mark' => array(
                    'meta' => array(
                        array(
                            'key' => '付费类型',
                            'value' => $data['pay_type'] !== 'credit' ? '金额' : '积分',
                        ),
                        array(
                            'key' => '支付' . ($data['pay_type'] !== 'credit' ? '金额' : '积分'),
                            'value' => isset($data['order_total']) ? $data['order_total'] : '',
                        )
                    )
                )
            );
            
            self::update_message($message_data);
        }
        // 处理其他订单类型
        else {
            $array = array(
                'product' => array(
                    'title' => '产品出售',
                    'type_text' => '产品',
                ),
                'shop' => array(
                    'title' => '商品出售',
                    'type_text' => '商品',
                ),
                'video' => array(
                    'title' => '视频出售',
                    'type_text' => '视频',
                ),
                'xiazai' => array(
                    'title' => '下载资源出售',
                    'type_text' => '下载资源',
                ),
                'post_neigou' => array(
                    'title' => '隐藏内容出售',
                    'type_text' => '隐藏内容',
                ),
            );
            
            // 只有当不是自己购买时才给商家发送消息
            if (!empty($data['user_id']) && $data['user_id'] != $author_id && $author_id) {
                // 给商家发送消息
                self::update_message(array(
                    'sender_id' => (int)$data['user_id'],
                    'receiver_id' => (int)$author_id,
                    'title' => isset($array[$data['order_type']]) ? $array[$data['order_type']]['title'] : '商品出售',
                    'content' => sprintf(
                        '购买了您的%s：${post}',
                        isset($array[$data['order_type']]) ? $array[$data['order_type']]['type_text'] : '商品'
                    ),
                    'type' => 'serve',
                    'post_id' => !empty($data['chapter_id']) ? (int)$data['chapter_id'] : (int)$data['post_id'],
                ));
            }
            
            // 钩子，用于发送更多相关消息
            do_action('send_author_message_after', $author_id, $data, $array);
            
            // 给购买用户发送消息
            if (!empty($data['user_id'])) {
                self::update_message(array(
                    'sender_id' => 0,
                    'receiver_id' => (int)$data['user_id'],
                    'title' => '购买成功通知',
                    'content' => sprintf(
                        '您成功购买%s：${post}',
                        isset($array[$data['order_type']]) ? $array[$data['order_type']]['type_text'] : '商品'
                    ),
                    'type' => 'serve',
                    'post_id' => !empty($data['chapter_id']) ? (int)$data['chapter_id'] : (int)$data['post_id'],
                ));
            }
        }
        
        return apply_filters('islide_order_notify_return_success', $data);
    }
    
    /**
     * 关注用户与取消关注通知
     *
     * @author  ifyn
     * @param   int  $user_id         被关注用户ID
     * @param   int  $current_user_id 当前用户ID
     * @param   bool $success         是否成功关注
     * @return  void
     */
    public function user_follow_action_message($user_id, $current_user_id, $success) {
        // 如果是自己则不发送消息
        if ($user_id == $current_user_id) {
            return;
        }
        
        if ($success) {
            // 发送关注消息
            self::update_message(array(
                'sender_id' => (int)$current_user_id,
                'receiver_id' => (int)$user_id,
                'title' => '关注通知',
                'content' => '关注了你',
                'type' => 'follow',
            ));
        } else {
            // 取消关注，删除消息
            self::delete_message(array(
                'sender_id' => (int)$current_user_id,
                'receiver_id' => (int)$user_id,
                'type' => 'follow',
            ));
        }
    }
    
    /**
     * 收藏通知
     *
     * @author  ifyn
     * @param   int  $post_id         文章ID
     * @param   int  $current_user_id 当前用户ID
     * @param   bool $success         是否成功收藏
     * @return  void
     */
    public function post_favorite_message($post_id, $current_user_id, $success) {
        // 获取文章作者id
        $author_id = get_post_field('post_author', $post_id);
        
        // 如果是自己则不发送消息
        if ($author_id == $current_user_id) {
            return;
        }
        
        if ($success) {
            // 发送收藏消息
            self::update_message(array(
                'sender_id' => (int)$current_user_id,
                'receiver_id' => (int)$author_id,
                'title' => '收藏通知',
                'content' => '收藏了你的文章',
                'type' => 'like',
                'post_id' => (int)$post_id,
            ));
        } else {
            // 取消收藏，删除消息
            self::delete_message(array(
                'sender_id' => (int)$current_user_id,
                'receiver_id' => (int)$author_id,
                'type' => 'like',
                'post_id' => (int)$post_id,
            ));
        }
    }
    
    /**
     * 文章点赞通知
     *
     * @author  ifyn
     * @param   int  $post_id         文章ID
     * @param   int  $current_user_id 当前用户ID
     * @param   bool $success         是否成功点赞
     * @return  void
     */
    public function post_vote_message($post_id, $current_user_id, $success) {
        // 获取文章作者id
        $author_id = get_post_field('post_author', $post_id);
        
        // 如果是自己则不发送消息
        if ($author_id == $current_user_id) {
            return;
        }
        
        if ($success) {
            // 发送点赞消息
            self::update_message(array(
                'sender_id' => (int)$current_user_id,
                'receiver_id' => (int)$author_id,
                'title' => '文章点赞',
                'content' => '给你的文章点了赞',
                'type' => 'like',
                'post_id' => (int)$post_id,
            ));
        } else {
            // 取消点赞，删除消息
            self::delete_message(array(
                'sender_id' => (int)$current_user_id,
                'receiver_id' => (int)$author_id,
                'type' => 'like',
                'post_id' => (int)$post_id,
            ));
        }
    }
    
    /**
     * 等级升级通知
     *
     * @author  ifyn
     * @param   int    $user_id 用户ID
     * @param   string $lv      用户升级后的等级
     * @return  void
     */
    public function update_user_lv_message($user_id, $lv) {
        // self::update_message(array(
        //     'receiver_id'=> $user_id,
        //     'title' => '等级升级了',
        //     'content' => $lv,
        //     'type' => 'comment_to_post',
        // ));
    }
    
    /**
     * 会员到期通知(暂无功能)
     *
     * @author  ifyn
     * @param   array $vip 会员数据
     * @return  array      原始会员数据
     */
    public function check_user_vip_time_message($vip) {
        // self::update_message(array(
        //     'sender_id' => 0,
        //     'receiver_id' => $data['user_id'],
        //     'title' => '超级大会员服务到期提醒',
        //     'content' => '您的超级大会员服务还有3天将到期，立即续费，继续享受大会员服务！  ',
        //     'type' => 'follow',
        // ));
        
        // self::update_message(array(
        //     'sender_id' => 0,
        //     'receiver_id' => $data['user_id'],
        //     'title' => '超级大会员服务过期通知',
        //     'content' => '您的超级大会员服务已经过期，快回来续费恢复超级大会员特权，番剧国创的快乐不能停！ ',
        //     'type' => 'follow',
        // ));
        
        return $vip;
    }
    
    /**
     * 生成会员消息
     *
     * @author  ifyn
     * @param   int    $user_id 用户ID
     * @param   string $vip     会员类型
     * @param   int    $day     会员天数
     * @param   string $type    开通类型(系统发放/充值开通/活动赠送/会员领取/卡密兑换)
     * @return  void
     */
    public static function generate_vip_message($user_id, $vip, $day, $type) {
        $user_vip = get_user_meta($user_id, 'islide_vip', true);
        $user_vip_exp_date = get_user_meta($user_id, 'islide_vip_exp_date', true);
        $vip_date = (string)$user_vip_exp_date === '0' ? '永久' : wp_date('Y-m-d', (int)$user_vip_exp_date + 86400 * $day);
        
        $roles = User::get_user_roles();
        $vip_name = !$user_vip ? $roles[$vip]['name'] : $roles[$user_vip]['name'];
        
        $message_data = array(
            'sender_id' => 0,
            'receiver_id' => (int)$user_id,
            'title' => $vip_name.'服务开通成功通知',
            'content' => '恭喜您已开通'.$day.'天'.$vip_name.'服务，目前有效期至'.$vip_date.'。',
            'type' => 'vip',
            'mark' => array(
                'meta' => array(
                    array(
                        'key'=> '开通类型',
                        'value'=> $type, //系统发放、充值开通 、活动赠送、会员领取、卡密兑换
                    ),
                    array(
                        'key'=> '当前状态',
                        'value'=> $vip_name,
                    )
                )
                
            )
        );
        
        self::update_message($message_data);
    }
    
    /**
     * 签到奖励会员通知
     *
     * @author  ifyn
     * @param   int   $user_id 用户ID
     * @param   array $bonus   奖励数据
     * @return  void
     */
    public function user_signin_message($user_id, $bonus) {
        
        if(!isset($bonus['vip']) || empty($bonus['vip'])) return;
        $bonus_vip = $bonus['vip'];
        
        if(empty($bonus_vip['day']) || $bonus_vip['day'] == '0') return;
        
        self::generate_vip_message($user_id, $bonus_vip['vip'], $bonus_vip['day'], '签到赠送');
    }
    
    /**
     * 任务完成通知
     *
     * @author  ifyn
     * @param   int   $user_id 用户ID
     * @param   array $task    任务数据
     * @return  void
     */
    public function task_complete_message($user_id, $task) {
        $task_bonus = isset($task['task_bonus']) && is_array($task['task_bonus']) ? $task['task_bonus'] : array();
        if(!$task_bonus) return;
        
        $bonus_vip = null;
        foreach ($task_bonus as $value) {
            if (strpos($value['key'], 'vip') !== false && $value['value'] != '0') {
                $bonus_vip = $value;
                break;
            }
        }
        
        if (!$bonus_vip) return;
        
        self::generate_vip_message($user_id, $bonus_vip['key'], $bonus_vip['value'], '任务活动');
    }
    
    /**
     * 认证通过通知
     *
     * @author  ifyn
     * @param   int $user_id 用户ID
     * @return  void
     */
    public function verify_check_success_message($user_id) {
        self::update_message(array(
            'sender_id' => 0,
            'receiver_id' => (int)$user_id,
            'title' => '认证服务',
            'content' => '您的认证申请已通过审核，您已拥有唯一身份标识，可在个人主页查看。',
            'type' => 'system',
        ));
    }
    
    /**
     * 获取消息列表的API处理函数
     *
     * @author  ifyn
     * @param   WP_REST_Request $request 请求对象，包含以下参数：
     *                                    - paged: 页码
     *                                    - size: 每页数量
     *                                    - type: 消息类型
     * @return  array          返回消息列表数据和分页信息
     */
    public static function get_message_list_api($request) {
        global $wpdb;

        $params = $request->get_params();

        $paged = isset($params['paged']) ? max(1, intval($params['paged'])) : 1;
        $size  = isset($params['size']) ? max(1, intval($params['size'])) : 10;
        $offset = ($paged - 1) * $size;

        $type = isset($params['type']) ? sanitize_text_field($params['type']) : '';

        $where = '1=1';
        $args = [];

        if (!empty($type)) {
            $where .= ' AND type = %s';
            $args[] = $type;
        }

        $total_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}islide_message WHERE $where";
        $total = $wpdb->get_var($args ? $wpdb->prepare($total_sql, ...$args) : $total_sql);
        $pages = ceil($total / $size);

        $data_sql = "SELECT * FROM {$wpdb->prefix}islide_message WHERE $where ORDER BY date DESC LIMIT %d OFFSET %d";
        $args[] = $size;
        $args[] = $offset;

        $results = $wpdb->get_results($wpdb->prepare($data_sql, ...$args), ARRAY_A);

        foreach ($results as &$item) {
            $item['id'] = (int)$item['ID'];
            unset($item['ID']);
            // 用户名
            $sender = get_userdata($item['sender_id']);
            $receiver = get_userdata($item['receiver_id']);
            $item['sender_name'] = $sender ? $sender->display_name : '系统';
            $item['receiver_name'] = $receiver ? $receiver->display_name : ($item['receiver_id']=='10000001'? '全部用户' : '游客');

            // 文章标题
            $item['post_title'] = '';
            $item['post_type'] = '';
            if (!empty($item['post_id']) && get_post_status($item['post_id'])) {
                $item['post_title'] = get_the_title($item['post_id']);
                $item['post_type'] = get_post_type($item['post_id']);
            }

            // mark 反序列化处理
            $item['comment_content'] = '';
            if (!empty($item['mark']) && is_serialized($item['mark'])) {
                $item['mark'] = maybe_unserialize($item['mark']);

                if ($item['type'] === 'comment' && is_array($item['mark']) && isset($item['mark'][0])) {
                    $comment = get_comment(intval($item['mark'][0]));
                    if ($comment) {
                        $item['comment_content'] = $comment->comment_content;
                    }
                }
            } else {
                // mark 不可反序列化，也要返回为原始
                $item['mark'] = $item['mark'];
            }
        }

        return [
            'data'  => $results,
            'pages' => (int)$pages,
            'count' => (int)$total,
            'paged' => (int)$paged
        ];
    }

    /**
     * 批量删除消息的API处理函数
     *
     * @author  ifyn
     * @param   WP_REST_Request $request 请求对象，包含以下参数：
     *                                    - ids: 要删除的消息ID数组
     * @return  array|WP_Error  删除结果或错误信息
     */
    public static function delete_message_list($request) {
        global $wpdb;

        $data = $request->get_json_params();

        if (!isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
            return array('error'=>'无效的请求参数，缺少 IDs');
        }

        $ids = array_map('intval', $data['ids']); // 确保都是整数
        $table_name = $wpdb->prefix . 'islide_message';

        // 构造占位符，如 (?,?,?,...)，用于 prepare
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $sql = "DELETE FROM $table_name WHERE id IN ($placeholders)";
        $prepared = $wpdb->prepare($sql, ...$ids);

        $result = $wpdb->query($prepared);

        if ($result === false) {
            return new \WP_Error('db_error', '数据库操作失败', ['status' => 500]);
        }

        return [
            'success' => true,
            'message' => '删除成功',
            'deleted' => (int)$result,
        ];
    }

    /**
     * 推送消息的API处理函数
     *
     * @author  ifyn
     * @param   WP_REST_Request $request 请求对象，包含以下参数：
     *                                    - type: 消息类型
     *                                    - receiver_type: 接收者类型(all/user)
     *                                    - receiver_id: 接收者ID(当receiver_type为user时)
     *                                    - style: 消息样式(normal/card/image)
     *                                    - title: 消息标题
     *                                    - content: 消息内容
     *                                    - image: 图片URL(当style为image时)
     * @return  WP_REST_Response|WP_Error 成功响应或错误信息
     */
    public static function push_message_api($request) {
        $data = $request->get_json_params();

        if (empty($data['type']) || empty($data['receiver_type'])) {
            return new \WP_Error('missing_fields', '请填写完整消息类型与接收对象', array('status' => 400));
        }

        $type = sanitize_text_field($data['type']);
        $receiver_type = sanitize_text_field($data['receiver_type']);
        $style = isset($data['style']) ? sanitize_text_field($data['style']) : 'normal';

        $title = isset($data['title']) ? sanitize_text_field($data['title']) : '';
        $content = isset($data['content']) ? wp_kses_post($data['content']) : '';
        $image = isset($data['image']) ? esc_url_raw($data['image']) : '';

        // 标题和内容校验
        if ($style === 'card' && empty($title)) {
            return new \WP_Error('missing_title', '卡片消息必须包含标题', array('status' => 400));
        }
        if ($style !== 'image' && empty($content)) {
            return new \WP_Error('missing_content', '消息内容不能为空', array('status' => 400));
        }

        // 组装数据
        $message_data = array(
            'sender_id' => in_array($type, ['vip', 'system']) ? 0 : get_current_user_id(),
            'type' => $type,
        );

        // 处理接收者
        if ($receiver_type === 'all') {
            $message_data['receiver_id'] = 10000001;
        } else {
            if (empty($data['receiver_id'])) {
                return new \WP_Error('missing_receiver', '请选择接收用户', array('status' => 400));
            }
            $message_data['receiver_id'] = intval($data['receiver_id']);
        }

        // 设置消息内容
        if ($style === 'image' && !empty($image)) {
            $message_data['content'] = '[图片]';
            $message_data['mark'] = array(
                'url' => $image,
                'type' => 'image',
            );
        } else {
            $message_data['content'] = $content;
        }

        if (!empty($title)) {
            $message_data['title'] = $title;
        }

        // 插入数据库
        if (self::update_message($message_data)) {
            return new \WP_REST_Response(array('message' => '消息已推送'), 200);
        } else {
            return new \WP_Error('db_error', '推送失败', array('status' => 500));
        }
    }

    /**
     * 评论通知
     *
     * @author  ifyn
     * @param   array $comment_data 评论数据
     * @return  void
     */
    public function comment_post_message($comment_data) {
        if (empty($comment_data) || !is_array($comment_data)) {
            return;
        }
        
        $comment_id = isset($comment_data['comment_ID']) ? (int)$comment_data['comment_ID'] : 0;
        $post_id = isset($comment_data['comment_post_ID']) ? (int)$comment_data['comment_post_ID'] : 0;
        $user_id = isset($comment_data['user_id']) ? (int)$comment_data['user_id'] : 0;
        
        if (!$comment_id || !$post_id || !$user_id) {
            return;
        }
        
        // 获取文章作者ID
        $post_author = get_post_field('post_author', $post_id);
        
        // 如果是回复评论
        if (isset($comment_data['comment_parent']) && $comment_data['comment_parent'] > 0) {
            $parent_comment = get_comment($comment_data['comment_parent']);
            
            if ($parent_comment && $parent_comment->user_id != $user_id) {
                // 给被回复者发送通知
                self::update_message(array(
                    'sender_id' => (int)$user_id,
                    'receiver_id' => (int)$parent_comment->user_id,
                    'title' => '评论回复',
                    'content' => '回复了你的评论',
                    'type' => 'comment',
                    'post_id' => (int)$post_id,
                    'mark' => array($comment_id, $comment_data['comment_parent']),
                ));
            }
        } else {
            // 如果是直接评论文章，且不是自己的文章
            if ($post_author != $user_id) {
                // 给文章作者发送通知
                self::update_message(array(
                    'sender_id' => (int)$user_id,
                    'receiver_id' => (int)$post_author,
                    'title' => '文章评论',
                    'content' => '评论了你的文章',
                    'type' => 'comment',
                    'post_id' => (int)$post_id,
                    'mark' => array($comment_id),
                ));
            }
        }
    }
    
    /**
     * 评论点赞通知
     *
     * @author  ifyn
     * @param   int  $comment_id      评论ID
     * @param   int  $current_user_id 当前用户ID
     * @param   bool $success         是否成功点赞
     * @return  void
     */
    public function comment_vote_message($comment_id, $current_user_id, $success) {
        $comment = get_comment($comment_id);
        
        if (!$comment || !$comment->user_id) {
            return;
        }
        
        // 如果是自己则不发送消息
        if ($comment->user_id == $current_user_id) {
            return;
        }
        
        if ($success) {
            // 发送点赞消息
            self::update_message(array(
                'sender_id' => (int)$current_user_id,
                'receiver_id' => (int)$comment->user_id,
                'title' => '评论点赞',
                'content' => '给你的评论点了赞',
                'type' => 'like',
                'post_id' => (int)$comment->comment_post_ID,
                'mark' => array($comment_id),
            ));
        } else {
            // 取消点赞，删除消息
            self::delete_message(array(
                'sender_id' => (int)$current_user_id,
                'receiver_id' => (int)$comment->user_id,
                'type' => 'like',
                'post_id' => (int)$comment->comment_post_ID,
                'mark' => array($comment_id),
            ));
        }
    }
}