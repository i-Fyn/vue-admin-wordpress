<?php

namespace islide\Modules;

use islide\Modules\Common\User;
use islide\Modules\Common\Post;
use islide\Modules\Common\Pay;
use islide\Modules\Common\RestApi;
use islide\Modules\Common\Circle;
use islide\Modules\Common\Orders;
use islide\Modules\Common\Login;
use islide\Modules\Common\Comment;
use islide\Modules\Common\Signin;
use islide\Modules\Common\Distribution;
use islide\Modules\Common\Record;
use islide\Modules\Common\Task;



class Filter {
    public function init() {
        add_filter('get_contact_list_data', array($this, 'get_contact_list_data'), 10, 2);
        add_filter('get_unread_message_count', array($this, 'get_unread_message_count'), 10, 1);
        add_filter('get_message_list_data', array($this, 'get_message_list_data'), 10, 2);
        add_filter('islide_get_user_cover_url', array($this, 'islide_get_user_cover_url'), 10, 1);
        add_filter('the_content', array($this, 'the_content'),90);
        add_filter('the_content', array($this, 'islide_filter_content_images'),60);
        add_filter('islide_get_download_rights', array($this, 'islide_get_download_rights'), 10, 1);
        add_filter('check_user_can_download_all', array($this, 'check_user_can_download_all'), 10, 1);
        add_filter('check_user_can_download', array($this, 'check_user_can_download'), 10, 4);
        add_filter('islide_order_price', array($this, 'islide_order_price'), 10, 1);
        add_filter('islide_pay_before', array($this, 'islide_pay_before'), 10, 1);
        add_filter('islide_update_orders', array($this, 'islide_update_orders'), 10, 1);
        add_filter('islide_pay_check', array($this, 'islide_pay_check'), 10, 1);
        add_filter('islide_check_user_can_video_allow', array($this, 'islide_check_user_can_video_allow'), 10, 4);
        add_filter('islide_comment_filters', array($this, 'islide_comment_filters'));
        add_filter('check_shop_purchase_data', array($this, 'check_shop_purchase_data'), 10, 2);
        add_filter('islide_check_manage_moment_role', array($this, 'islide_check_manage_moment_role'), 10, 1);
        add_filter('check_reading_hide_content_role', array($this, 'check_reading_hide_content_role'), 10, 2);
        add_filter('islide_user_verify_condition_value',array($this, 'islide_user_verify_condition_value'), 10, 3);
        add_filter('islide_recom_task_completed_count', array($this, 'islide_recom_task_completed_count'), 10, 2);
        add_filter('islide_complete_task', array($this, 'islide_complete_task'), 10, 3);
        add_filter('islide_update_signin', array($this, 'islide_update_signin'), 10, 3);
        add_filter('mark_message_as_read', array($this, 'mark_message_as_read'), 10, 3);
        add_filter('the_content', array($this, 'process_content_wp'),40);
        add_filter('the_content', array($this, 'handle_content_hide_shortcode'),50);
        add_filter('islide_calculate_commission', array($this, 'custom_calculate_commission'), 10, 1);
        add_action('islide_complete_task_action', array($this, 'islide_complete_task_action'), 10, 3);
        add_action('islide_user_signin', array($this, 'islide_user_signin_continuous'), 10, 2);
        add_action('islide_user_signin', array($this, 'islide_user_signin_daily'), 10, 2);
        add_action('after_report_success', array($this, 'after_report_success'), 10, 3);
        add_action('deleted_comment', array($this, 'after_comment_deleted'), 10, 1);
    }








public static function islide_comment_filters($comment_text) {
    // 获取缓存的表情包列表
    $cache_key = 'emoji_list_cache';
    $emoticon_packs = get_transient($cache_key);

    // 如果缓存不存在，重新生成表情包列表
    if (!$emoticon_packs) {
        $emoticon_packs = RestApi::getEmojiList(); // 替换为获取表情包列表的方法
        if (is_wp_error($emoticon_packs)) {
            return $comment_text;
        }
        $emoticon_packs = $emoticon_packs->get_data();
    }

    // 构建表情符号和图标的映射数组
    $emoticon_map = [];
    foreach ($emoticon_packs['list'] as $pack) {
        if (!isset($pack['list']) || !is_array($pack['list'])) {
            continue;
        }
        foreach ($pack['list'] as $emoticon) {
            if (isset($emoticon['name']) && isset($emoticon['icon'])) {
                $emoticon_map[$emoticon['name']] = $emoticon['icon'];
                $emoticon_map['size'] = $pack['size'];
            }
        }
    }

    // 1. 替换表情标记为图片标签
    $comment_text = preg_replace_callback('/\[(.*?)\]/', function ($matches) use ($emoticon_map) {
        $name = $matches[1];
        if (isset($emoticon_map[$name])) {
            $icon_url = esc_url($emoticon_map[$name]);
            $icon_url = get_relative_upload_path($icon_url);
            $alt_text = esc_attr($name);
            return '<img src="' . $icon_url . '" alt="' . $alt_text . '" class="emoticon-image ' . $emoticon_map['size'] . '">';
        }
        return $matches[0];
    }, $comment_text);

    // 2. 替换 [!图片](url) 为 <img src="url" class="comment-img" />
    $comment_text = preg_replace_callback('/\[!图片\]\((.*?)\)/', function ($matches) {
        $img_url = esc_url($matches[1]);
        $img_url = get_relative_upload_path($img_url);
        return '<img src="' . $img_url . '" class="comment-img" loading="lazy" data-fancybox="gallery" />';
    }, $comment_text);

    return $comment_text;
}




public static function get_user_can_free_video_count($user_id) {
    $user_roles = User::get_user_roles();
    $user_lv = [
        'lv'  => User::get_user_lv($user_id), // 等级信息
        'vip' => User::get_user_vip($user_id), // VIP 信息
    ];
    // 检查必要参数是否存在
    if (!isset($user_lv['vip'], $user_lv['vip']['lv'], $user_roles[$user_lv['vip']['lv']])) {
        return false;
    }
    // 获取用户等级相关数据
    $vip_level = $user_lv['vip']['lv'];
    $free_video = isset($user_roles[$vip_level]['free_video']) ? (bool) $user_roles[$vip_level]['free_video'] : false;
    
    
    // 如果免费下载权限为 false，直接返回
    if (!$free_video) {
        return false;
    }

    
    $free_video_count = isset($user_roles[$vip_level]['free_video_count']) ? (int) $user_roles[$vip_level]['free_video_count'] : 0;

    // 从 wp_usermeta 获取 islide_free_count
    $meta_key = 'islide_video_count';
    $islide_free_count = get_user_meta($user_id, $meta_key, true);

    // 如果数据为空，初始化为默认值
    if (empty($islide_free_count)) {
        $islide_free_count = [
            'date' => current_time('Y-m-d'),
            'count' => $free_video_count,
            'posts' => [],
        ];
        update_user_meta($user_id, $meta_key, $islide_free_count);
    }

    // 检查日期是否为今天
    $current_date = current_time('Y-m-d');
    if ($islide_free_count['date'] !== $current_date) {
        // 日期不同，重置 count 并更新时间
        $islide_free_count['date'] = $current_date;
        $islide_free_count['count'] = $free_video_count;
        $islide_free_count['posts'] = [];
        // 更新 wp_usermeta
        update_user_meta($user_id, $meta_key, $islide_free_count);
    }

    // 返回数据（包含等级对应的权限信息）
    return $islide_free_count['count'];
}



public static function check_user_video_roles($video_meta, $user_lv) {
    // 获取视频的限制角色列表
    if(!get_current_user_id()){
        return false;
    }
    $allowed_roles = isset($video_meta['islide_video_roles']) && is_array($video_meta['islide_video_roles']) 
        ? $video_meta['islide_video_roles'] 
        : [];

    // 格式化用户等级和 VIP 等级
    $user_lv_role = isset($user_lv['lv']['lv']) ? 'lv' . $user_lv['lv']['lv'] : null;  // 转换为 lv0, lv1 等格式
    $user_vip_role = isset($user_lv['vip']['lv']) ? $user_lv['vip']['lv'] : null; // VIP 等级直接使用

    // 检查用户等级或 VIP 等级是否符合视频要求
    if (in_array($user_lv_role, $allowed_roles) || in_array($user_vip_role, $allowed_roles)) {
        return true;
    }

    return false;
}

/**
 * 视频权限检查函数
 *
 * @param int $video_id 视频ID
 * @param int $user_id 用户ID
 * @param int $index 视频在播放列表中的索引
 * @return array 权限检查结果
 */
public static function islide_check_user_can_video_allow($video_id, $user_id, $index) {
     // 初始化结果
    $result = [
        'allow' => false,   // 是否允许观看
        'type' => '',       // 权限类型
        'value' => 0,       // 当前付费金额或积分
        'total_value' => 0, // 总金额或积分要求
        'not_login_pay' => false, // 是否支持未登录用户购买
        'roles' => [],      // 限制角色
    ];
    $_post_type = get_post_type($video_id);
    
    $user_lv = [
        'lv'  => User::get_user_lv($user_id),  // 普通等级信息
        'vip' => User::get_user_vip($user_id), // VIP 等级信息
    ];
    $is_vip = !empty($user_lv['vip']) ? true : false;
    $video_meta = array();
    
    if($_post_type=='episode'){
        
        //VIP
        if ($is_vip) {
            $user_free_count =  self::get_user_can_free_video_count($user_id) ? self::get_user_can_free_video_count($user_id) :0;
            // VIP 用户直接允许观看
            if($user_free_count > 0) {
            $result['free_count'] = $user_free_count;
            $result['allow'] = false;
            return $result;
            }
        }
        
        $parent_id = get_post_field('post_parent', $video_id);
        $video_meta = get_post_meta((int)$parent_id, 'single_video_metabox', true);
        
        if (empty($video_meta) || empty($video_meta['islide_video_role'])) {
            $result['type'] = 'free'; // 默认视为免费
            $result['allow'] = true;
            return $result;
        }
        
        if(isset($video_meta['group']) && !empty($video_meta['group'])  && $video_meta['group'][$index] &&$video_id == $video_meta['group'][$index]['id']  ){
            
            $result['type'] = $video_meta['islide_video_role'];
            $result['value'] = isset($video_meta['islide_video_pay_value']) ? (int)$video_meta['islide_video_pay_value'] : 0;
            $result['total_value'] = isset($video_meta['islide_video_pay_total']) ? (int)$video_meta['islide_video_pay_total'] : 0;
            $result['not_login_pay'] = !empty($video_meta['islide_video_not_login_buy']);
            $result['roles'] =isset($video_meta['islide_video_roles']) ? (int)$video_meta['islide_video_roles'] : array(); 
        }
    }
    
    // // 检查权限类型
    switch ($result['type']) {
        case 'free':
            $result['allow'] = true; // 免费直接允许
            break;
        case 'credit':
        case 'money':
            // 检查是否已支付
            $result['allow'] = self::has_user_purchased($video_id, $user_id, $index) ? true : false;
            break;

        case 'login':
            // 检查是否已登录
            $result['allow'] = get_current_user_id() ? true : false;
            break;
        case 'comment':
            // 检查是否有评论
            $result['allow'] = self::has_user_commented($video_id, $user_id) ? true : false;
            break;
        case 'password':
            // 检查是否输入密码
            $result['allow'] = self::verifyPasswordCookie($video_id);
            break;
        case 'roles':
            // 检查用户是否具有所需角色
            $result['allow'] = self::check_user_video_roles($video_meta, $user_lv) ? true : false;
            break;

        default:
            $result['allow'] = false; // 未知类型默认为禁止
            break;
    }

    return $result;
}


public static function mark_message_as_read($messages, $user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'islide_message';

    foreach ($messages as $msg) {
        $msg_id = (int)$msg['ID'];
        $receiver_id = (int)$msg['receiver_id'];

        if ($receiver_id === 10000001) {
            // 广播消息：读取并追加 user_id 到序列化数组中
            $existing_read_by = $wpdb->get_var(
                $wpdb->prepare("SELECT read_by FROM {$table} WHERE ID = %d", $msg_id)
            );
        
            $read_by_array = [];
        
            if (!empty($existing_read_by) && is_serialized($existing_read_by)) {
                $read_by_array = maybe_unserialize($existing_read_by);
            }
        
            if (!is_array($read_by_array)) {
                $read_by_array = [];
            }
        
            if (!in_array((int)$user_id, $read_by_array)) {
                $read_by_array[] = (int)$user_id;
        
                $wpdb->update(
                    $table,
                    ['read_by' => maybe_serialize($read_by_array)],
                    ['ID' => $msg_id],
                    ['%s'],
                    ['%d']
                );
            }
        } else {
            // 私聊消息：直接标记为当前用户已读
            $wpdb->update(
                $table,
                ['read_by' => (string)$user_id],
                ['ID' => $msg_id],
                ['%s'],
                ['%d']
            );
        }
    }

    return $messages;
}



public static function get_contact_list_data($current_user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'islide_message';

    // 查询最新一条 chat 消息：按联系人分组
    $chat_sql = $wpdb->prepare("
        SELECT m.*
        FROM {$table} m
        INNER JOIN (
            SELECT 
                CASE 
                    WHEN sender_id = %d THEN receiver_id
                    ELSE sender_id
                END AS contact_id,
                MAX(date) AS max_date
            FROM {$table}
            WHERE type = 'chat' AND (sender_id = %d OR receiver_id = %d)
            GROUP BY contact_id
        ) latest 
        ON (
            (m.sender_id = %d AND m.receiver_id = latest.contact_id)
            OR (m.sender_id = latest.contact_id AND m.receiver_id = %d)
        ) AND m.date = latest.max_date
        WHERE m.type = 'chat'
    ",
        $current_user_id, $current_user_id, $current_user_id,
        $current_user_id, $current_user_id
    );

    // 查询每种非 chat 类型的最新消息（如 system、notify、like 等）
    $other_sql = $wpdb->prepare("
        SELECT m.*
        FROM {$table} m
        INNER JOIN (
            SELECT type, MAX(id) AS max_id
            FROM {$table}
            WHERE type != 'chat' AND (receiver_id = %d OR receiver_id = 10000001)
            GROUP BY type
        ) latest 
        ON m.id = latest.max_id
    ", $current_user_id);

    // 合并两个查询
    $final_sql = "$chat_sql UNION $other_sql ORDER BY date DESC";

    $results = $wpdb->get_results($final_sql);
    
    return empty($results) ? [] : array_map('get_object_vars', $results);
}

public static function get_unread_message_count($receiver_id) {
    if (!$receiver_id) {
        $receiver_id = get_current_user_id();
    }

    if (!$receiver_id) {
        return [];
    }

    global $wpdb;

    // 获取当前用户相关的所有消息（含广播消息）
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, type, read_by, receiver_id 
             FROM {$wpdb->prefix}islide_message 
             WHERE receiver_id = %d OR receiver_id = 10000001",
            $receiver_id
        ),
        ARRAY_A
    );

    $unread_messages = [];

    foreach ($results as $row) {
        $type = $row['type'];
        $receiver = (int)$row['receiver_id'];
        $read_by = $row['read_by'];

        $has_read = false;

        if ($receiver === 10000001) {
            // 广播消息，read_by 是序列化数组
            if (!empty($read_by) && is_serialized($read_by)) {
                $read_by_array = maybe_unserialize($read_by);
                $has_read = is_array($read_by_array) && in_array($receiver_id, $read_by_array);
            }
        } else {
            // 普通消息，read_by 是字符串
            $has_read = (string)$read_by === (string)$receiver_id;
        }

        if (!$has_read) {
            if (!isset($unread_messages[$type])) {
                $unread_messages[$type] = 0;
            }
            $unread_messages[$type]++;
        }
    }

    return $unread_messages;
}



/**
 * 获取消息列表数据
 *
 * @param array $data 查询条件
 * @param int $current_user_id 当前用户 ID
 * @return array 消息列表、总数和页数
 */
public static function get_message_list_data($data, $current_user_id) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'islide_message';

    $paged    = isset($data['paged']) && is_numeric($data['paged']) ? (int)$data['paged'] : 1;
    $per_page = 10;
    $offset   = ($paged - 1) * $per_page;

    $type     = sanitize_text_field($data['type']);
    $send_id  = isset($data['sender_id']) ? (int)$data['sender_id'] : 0;

    // ============ ✅ 构建 where 条件 ============
    if ($type === 'chat') {
        // 双向聊天逻辑
        $where = $wpdb->prepare(
            "(sender_id = %d AND receiver_id = %d AND type = %s) 
             OR (sender_id = %d AND receiver_id = %d AND type = %s)",
            $send_id, $current_user_id, $type,
            $current_user_id, $send_id, $type
        );
    } else {
        // 单向系统/通知类消息，只看 receiver_id 为当前用户的
        $where = $wpdb->prepare(
            "(receiver_id = %d OR receiver_id = 10000001) AND type = %s",
            $current_user_id, $type
        );
    }

    // ============ ✅ 查询数据 ============
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE $where ORDER BY date DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ),
        ARRAY_A
    );

    // 格式化 ID 字段
    foreach ($results as &$result) {
        if (isset($result['id'])) {
            $result['ID'] = $result['id'];
            unset($result['id']);
        }
    }

    // ============ ✅ 总数 & 分页 ============
    $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE $where");
    $total_pages = ceil($total_count / $per_page);

    return [
        'data'  => $results,
        'count' => (int)$total_count,
        'pages' => (int)$total_pages,
    ];
}


public static function islide_get_user_cover_url($user_id) {
$cover_id = (int) get_user_meta($user_id, 'islide_user_cover', true);
if ($cover_id) {
    return  wp_get_attachment_url($cover_id);
}elseif(islide_get_option("user_cover_img") != ""){
    return islide_get_option("user_cover_img");
}else{
    return false;
}

}



public static function the_content($content) {
    $content  = str_replace(chr(194) . chr(160), ' ', $content);
    $content = preg_replace('/<p>(\s|&nbsp;|　|<br\s*\/?>)*<\/p>/i', '', $content);
    // 3. 用 div 包裹 blockquote 标签
    $content = preg_replace_callback(
        '/<blockquote\b[^>]*>(.*?)<\/blockquote>/is',
        function ($matches) {
            return '<div class="blockquote-wrapper"><div class="quote_q" ><i title="ri-double-quotes-l" class="ri-double-quotes-l" style=""></i>' . $matches[0] . '</div></div>';
        },
        $content
    );
    // 使用回调函数处理每个匹配的 h 标签
    $content = preg_replace_callback(
        '/<h([2-3])([^>]*)>(.*?)<\/h\1>/is', // 匹配 h2、h3
        function ($matches) {
            static $index = 0; // 记录锚点序号
            $level = $matches[1];
            $attrs = $matches[2]; // 原始属性
            $text = trim($matches[3]); // 提取内容

            // **检测并去除已有 id**
            $attrs = preg_replace('/\s*id="[^"]*"/i', '', $attrs); 

            // **如果 h 标签内容为空（只包含空格、换行或 <br>），则删除整个 h 标签**
            if (empty($text) || preg_match('/^\s*(<br\s*\/?>\s*)*$/i', $text)) {
                return ''; // 直接删除
            }

            // 重新生成 id
            $anchor = 'toc-' . $index++;
            return "<h{$level}{$attrs} id=\"{$anchor}\">{$text}</h{$level}>"; // 生成新的 h 标签
        },
        $content
    );
    return $content;
}




public static function islide_get_download_rights($rights_string) {
    $parsed_rights = []; // 存储解析后的权限
    $user_roles = User::get_user_roles(); // 获取用户角色定义

    // 分割权限字符串
    $rights_items = preg_split('/[\r\n]+/', $rights_string);

    foreach ($rights_items as $item) {
        $item = trim($item);
        if (empty($item)) {
            continue; // 跳过空行
        }

        $lv = '';
        $type = '';
        $value = 0;

        if (strpos($item, '|') !== false) {
            [$lv, $type_value] = explode('|', $item, 2);

            if (strpos($type_value, '=') !== false) {
                [$type, $value] = explode('=', $type_value, 2);
                $value = is_numeric($value) ? (int)$value : $value;
            } else {
                $type = $type_value;
            }
        } elseif (strpos($item, '=') !== false) {
            [$type, $value] = explode('=', $item, 2);
            $lv = 'all';
        } else {
            $lv = 'all';
            $type = $item;
            $value = 0;
        }

        // 获取用户角色名称
        $name = $user_roles[$lv]['name'] ?? '未设置';
        if ($lv === 'not_login') {
            $name = '未登录';
        } elseif ($lv === 'all') {
            $name = '所有人';
        }

        $parsed_rights[] = [
            'lv'    => $lv,
            'name'  => $name,
            'type'  => $type,
            'value' => $value,
        ];
    }

    return $parsed_rights;
}

 public static function islide_check_user_can_media_upload($user_id,$mime){
     $media_upload_role = islide_get_option('media_upload_role');
 }

 public static function check_user_can_download_all($user_id) {
    $user_roles = User::get_user_roles();
    $user_lv = [
        'lv'  => User::get_user_lv($user_id), // 等级信息
        'vip' => User::get_user_vip($user_id), // VIP 信息
    ];
    // 检查必要参数是否存在
    if (!isset($user_lv['vip'], $user_lv['vip']['lv'], $user_roles[$user_lv['vip']['lv']])) {
        return false;
    }

    // 获取用户等级相关数据
    $vip_level = $user_lv['vip']['lv'];
    $free_download = isset($user_roles[$vip_level]['free_download']) ? (bool) $user_roles[$vip_level]['free_download'] : false;
    
    
    // 如果免费下载权限为 false，直接返回
    if (!$free_download) {
        return false;
    }

    
    $free_download_count = isset($user_roles[$vip_level]['free_download_count']) ? (int) $user_roles[$vip_level]['free_download_count'] : 0;
    $free_read_count = isset($user_roles[$vip_level]['free_read_count']) ? (int) $user_roles[$vip_level]['free_read_count'] : 0;

    // 从 wp_usermeta 获取 islide_free_count
    $meta_key = 'islide_download_count';
    $islide_free_count = get_user_meta($user_id, $meta_key, true);

    // 如果数据为空，初始化为默认值
    if (empty($islide_free_count)) {
        $islide_free_count = [
            'date' => current_time('Y-m-d'),
            'count' => $free_download_count,
            'posts' => [],
        ];
        update_user_meta($user_id, $meta_key, $islide_free_count);
    }

    // 检查日期是否为今天
    $current_date = current_time('Y-m-d');
    if ($islide_free_count['date'] !== $current_date) {
        // 日期不同，重置 count 并更新时间
        $islide_free_count['date'] = $current_date;
        $islide_free_count['count'] = $free_download_count;
        $islide_free_count['posts'] = [];
        // 更新 wp_usermeta
        update_user_meta($user_id, $meta_key, $islide_free_count);
    }

    // 返回数据（包含等级对应的权限信息）
    return $islide_free_count;
}



// public static function check_user_can_download($post_id, $user_id, $rights, $index = 0) {
//     // 获取用户角色定义和当前用户的等级信息
//     $user_roles = User::get_user_roles();
//     $user_lv = [
//         'lv'  => User::get_user_lv($user_id),  // 普通等级信息
//         'vip' => User::get_user_vip($user_id), // VIP 等级信息
//     ];

//     // 获取用户下载次数数据
//     $download_count_data = apply_filters('check_user_can_download_all', $user_id);

//     // 权限优先级排序：vip > lv > all
//     $prioritized_rights = self::prioritize_user_rights($rights);

//     // 遍历排序后的权限，依次检查
//     foreach ($prioritized_rights as $right) {
//         $type = $right['type'];
//         $value = $right['value'];
//         $lv = $right['lv'];

//         // VIP 用户免费下载（检查剩余次数）
//         if (!empty($user_lv['vip']) && self::can_user_download_free($download_count_data)) {
//             return [
//                 'allow'      => true,
//                 'type'       => $type,
//                 'value'      => $value,
//                 'free_count' => $download_count_data['count'],
//                 'free_down'  => false,
//             ];
//         }

//         // 检查用户权限是否匹配
//         if (self::check_user_level_match($lv, $user_lv)) {
//             $res =  self::handle_right_type($type, $post_id, $user_id, $value, $index);
//             return $res;
//         }
//     }

//     // 默认不允许下载
//     return [
//         'allow' => false,
//         'type'  => 'none',
//     ];
// }

public static function check_user_can_download($post_id, $user_id, $rights, $index = 0) {
    $user_roles = User::get_user_roles();
    $user_lv = [
        'lv'  => User::get_user_lv($user_id),
        'vip' => User::get_user_vip($user_id),
    ];

    // 获取用户下载配额数据
    $download_count_data = apply_filters('check_user_can_download_all', $user_id);

    // 排序：vip > lv > all（不影响我们是否允许走特权）
    $prioritized_rights = self::prioritize_user_rights($rights);

    $has_vip_restriction = false;
    $matched = false;

    foreach ($prioritized_rights as $right) {
        $type = $right['type'];
        $value = $right['value'];
        $lv = $right['lv'];

        // 检查当前项是否是 vip 限制（例如 vip1、vip2...）
        if (!empty($user_lv['vip']) && $lv === $user_lv['vip']['lv']) {
            $has_vip_restriction = true;
        }

        // 如果匹配成功，则直接返回
        if (self::check_user_level_match($lv, $user_lv)) {
            $res = self::handle_right_type($type, $post_id, $user_id, $value, $index);
            if ($res['allow']) {
                return $res;
            }
        }
    }

    // 所有权限都不通过，但资源没有 VIP 限制 → 可以走 VIP 特权
    if (!$has_vip_restriction && !empty($user_lv['vip']) && self::can_user_download_free($download_count_data)) {
        return [
            'allow'      => true,
            'type'       => 'vip_free',
            'value'      => null,
            'free_count' => $download_count_data['count'],
            'free_down'  => false,
        ];
    }

    // 默认返回禁止
    return [
        'allow' => false,
        'type'  => 'none',
    ];
}

/**
 * 根据优先级排序用户权限
 *
 * @param array $rights 用户权限列表
 * @return array 排序后的权限列表
 */
public static function prioritize_user_rights($rights) {
    $priority_order = ['vip', 'lv', 'all'];
    $sorted_rights = [];

    foreach ($priority_order as $prefix) {
        foreach ($rights as $right) {
            if (strpos($right['lv'], $prefix) === 0) {
                $sorted_rights[] = $right;
            }
        }
    }

    return $sorted_rights;
}






/**
 * 检查用户是否可以免费下载
 *
 * @param array $download_count_data 用户下载次数数据
 * @return bool 是否可以免费下载
 */
public static function can_user_download_free($download_count_data) {
    return !empty($download_count_data) && $download_count_data['count'] > 0;
}


/**
 * 检查用户的等级是否匹配
 *
 * @param string $lv 权限等级
 * @param array $user_lv 用户等级信息
 * @return bool 是否匹配
 */
public static function check_user_level_match($lv, $user_lv) {
    if ($lv === 'all') {
        return true; // 所有用户都匹配
    }

    if (!empty($user_lv['lv']) && $lv === 'lv' . $user_lv['lv']['lv']) {
        return true; // 匹配普通用户等级
    }

    if (!empty($user_lv['vip']) && $lv === $user_lv['vip']['lv']) {
        return true; // 匹配 VIP 等级
    }

    return false;
}






/**
 * 处理具体的权限类型逻辑
 *
 * @param string $type 权限类型
 * @param int $post_id 文章 ID
 * @param int $user_id 用户 ID
 * @param mixed $value 权限值
 * @param int $index 索引值
 * @return array 权限结果
 */
public static function handle_right_type($type, $post_id, $user_id, $value, $index) {
    switch ($type) {
        case 'free':
            return [
                'allow' => true,
                'type'  => 'free',
                'value' => 0,
            ];

        case 'password':
            $allow = self::verifyPasswordCookie($post_id);
            return [
                'allow' => $allow,
                'type'  => $type,
                'value' => $value,
            ];

        case 'comment':
            $allow = self::has_user_commented($post_id, $user_id);
            return [
                'allow' => $allow,
                'type'  => $type,
                'value' => $value,
            ];

        case 'login':
            $allow = is_user_logged_in();
            return [
                'allow' => $allow,
                'type'  => $type,
                'value' => $value,
            ];

        case 'credit':
        case 'money':
            $allow = self::has_user_purchased($post_id, $user_id, $index);
            return [
                'allow' => $allow,
                'type'  => $type,
                'value' => $value,
            ];

        default:
            return [
                'allow' => false,
                'type'  => 'none',
            ];
    }
}



/**
 * 检查用户是否已评论
 *
 * @param int $post_id 文章 ID
 * @param int $user_id 用户 ID
 * @return bool 是否已评论
 */
public static function has_user_commented($post_id, $user_id) {
    $comments = get_comments([
        'post_id' => $post_id,
        'user_id' => $user_id,
        'status'  => 'approve', // 仅获取审核通过的评论
        'number'  => 1,         // 只需要一条记录
    ]);

    return !empty($comments);
}





/**
 * 检查用户是否已购买
 *
 * @param int $post_id 文章 ID
 * @param int $user_id 用户 ID
 * @param int $index 索引值
 * @return bool 是否已购买
 */
public static function has_user_purchased($post_id, $user_id, $index) {
    // 获取当前 post 类型
    $post_type = get_post_type($post_id);
    // 如果是普通文章类型
    if ($post_type == 'post') {
        $buy_data = get_post_meta($post_id, 'islide_download_buy', true);
        $buy_data = is_array($buy_data) ? $buy_data : [];
        
        // 检查索引是否存在并判断用户是否已购买
        return isset($buy_data[$index]) && is_array($buy_data[$index]) && in_array($user_id, $buy_data[$index]);
    }
    
    // 如果是视频类型
    elseif ($post_type == 'video') {
        // 检查是否购买了整部视频
        $buy_data_all = get_post_meta($post_id, 'islide_video_buy', true);
        $buy_data_all = is_array($buy_data_all) ? $buy_data_all : [];

        if (in_array($user_id, $buy_data_all)) {
            return true; // 已购买整部视频
        }
    }elseif($post_type == 'episode'){
        $post_id = get_post_field('post_parent', $post_id);
        // 检查是否购买了整部视频
        $buy_data_all = get_post_meta($post_id, 'islide_video_buy', true);
        $buy_data_all = is_array($buy_data_all) ? $buy_data_all : [];
        if (in_array($user_id, $buy_data_all)) {
            return true; // 已购买整部视频
        }
        // 检查是否购买了指定章节（通过 $index）
        $buy_data_group = get_post_meta($post_id, 'islide_video_buy_group', true);
        $buy_data_group = is_array($buy_data_group) ? $buy_data_group : [];
        // 检查指定索引的章节是否存在用户购买记录
        return isset($buy_data_group[$index]) &&
               is_array($buy_data_group[$index]) &&
               in_array($user_id, $buy_data_group[$index]);
    }

    // 默认返回 false，表示未购买
    return false;
}




public static function islide_order_price($data) {
    // 验证必要的数据是否存在
    if (!isset($data['order_type'], $data['post_id'], $data['order_price'])) {
        return null; // 如果缺少关键数据，直接返回 null 或适当的默认值
    }
    // 检查订单类型是否为下载
    if ($data['order_type'] === 'xiazai') {
        // 获取下载数据
        $download_data = Post::get_post_download_data($data['post_id']);
        
        // 确认下载数据和用户权限是否存在
        if (!empty($download_data) && isset($download_data['current_user']['can'])) {
            $real_price = $download_data['current_user']['can']['value'];
            // 确认价格是否匹配
            if ((float)$data['order_price'] === (float)$real_price) {
                return $real_price; // 返回实际价格
            }
        }
    }
    
   if ($data['order_type'] === 'join_circle') {
        $pay_group = (array)get_term_meta($data['post_id'], 'islide_circle_pay_group', true);
    
        if (isset($pay_group[$data['order_key']]) && !empty($pay_group[$data['order_key']]['price'])) {
            $group_item = $pay_group[$data['order_key']];
            $price = (float)$group_item['price'];
            $discount = isset($group_item['discount']) ? (float)$group_item['discount'] : 100;
            $is_vip = User::is_vip($data['user_id']);
    
            // 计算应付价格（VIP 有折扣）
            if ($is_vip && $discount < 100) {
                return round($price * ($discount / 100), 2);
            } else {
                return $price;
            }
        } else {
            return array('error' => '圈子订单配置错误');
        }
    }
    
    // 如果条件不满足，返回原始价格或其他默认值
    return $data['order_price'];
}

public static function islide_pay_before($data) {
    // 检查支付类型
    $pay_type_check = Pay::pay_type($data['payment_method']);
    
    // 确保返回值是一个数组
    if (!is_array($pay_type_check)) {
        return [
            'error' => 'Invalid response from Pay::pay_type.',
        ];
    }
    
    // 如果存在错误，直接返回错误信息
    if (isset($pay_type_check['error'])) {
        return $pay_type_check;
    }
    
    // 更新支付类型
    if (isset($pay_type_check['type'])) {
        $data['pay_type'] = $pay_type_check['type'];
        $data['payment_method'] = $pay_type_check['payment_method'];
    }

    return $data;
}





public static function islide_update_orders($data) {
    global $wpdb;

    // 确定表名
    $table_name = $wpdb->prefix . 'islide_order';

    // 验证必要数据是否存在且有效
    if (
        empty($data['order_state']) || 
        empty($data['order']) || 
        empty($data['order']['order_id'])
    ) {
        return false; // 如果缺少关键字段或无效，返回 false
    }

    // 提取数据
    $order_state = (int)$data['order_state'];
    $order_id = $data['order']['order_id'];

    // 准备更新数据
    $update_data = ['order_state' => $order_state];
    $where = ['order_id' => $order_id];

    // 执行更新
    $result = $wpdb->update(
        $table_name,      // 表名
        $update_data,     // 更新字段及其值
        $where,           // 条件
        ['%d'],           // order_state 类型
        ['%s']            // order_id 类型
    );

    // 如果更新失败，记录错误日志
    if ($result === false) {
        return false;
    }

    // 返回操作结果
    return $result !== false || $wpdb->rows_affected >= 0;
}








public static function islide_pay_check($order_id) {
    global $wpdb;

    // 确定表名
    $table_name = $wpdb->prefix . 'islide_order';

    // 查询订单数据
    $order = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT post_id, order_id, order_key, order_state, order_type 
             FROM $table_name 
             WHERE order_id = %s",
            $order_id
        ),
        ARRAY_A
    );

    // 如果订单不存在
    if (empty($order)) {
        return [
            'status' => 'fail',
            'message' => 'Order not found',
        ];
    }

    // 根据 `order_state` 设置 `status`
    $status = $order['order_state'] != 0 ? 'success' : 'fail';
    // 返回结果
    return [
        'post_id' => $order['post_id'],
        'order_id' => $order['order_id'],
        'index' => (int)$order['order_key'], // `order_key` 对应 `index`
        'status' => $status,
        'type' => $order['order_type'],
    ];
}



public static function check_shop_purchase_data($order_data, $shop_data) {
    // 初始化验证结果
    $validation_result = [
        'status' => true,
        'message' => '验证通过',
        'order_price' => 0,
        'total_price' => 0,
    ];

    // 验证库存是否充足
    if ($order_data['order_count'] > $shop_data['stock']) {
        $validation_result['status'] = false;
        $validation_result['message'] = '库存不足';
        return $validation_result;
    }

    // 验证购买数量是否超出限制
    if ($order_data['order_count'] > $shop_data['limit']) {
        $validation_result['status'] = false;
        $validation_result['message'] = '购买数量超出限制';
        return $validation_result;
    }

    // 计算普通用户的单价
    $discounted_price = round($shop_data['price'] * ($shop_data['discount'] / 100), 2);
    $validation_result['order_price'] = $discounted_price;

    // 计算总价 (包括 VIP 折扣)
    $total_price = round(
        $discounted_price * $order_data['order_count'] * ($shop_data['vip_discount'] / 100),
        2
    );
    $validation_result['total_price'] = $total_price;

    // 验证单价是否匹配
    if (round($order_data['order_price'], 2) !== $discounted_price) {
        $validation_result['status'] = false;
        $validation_result['message'] = '单价不匹配';
        return $validation_result;
    }

    // 验证总价是否匹配
    if (round($order_data['total_price'], 2) !== $total_price) {
        $validation_result['status'] = false;
        $validation_result['message'] = '总价不匹配';
        return $validation_result;
    }

    return $validation_result;
}


public static function islide_check_manage_moment_role($data){
    
    $post_id = isset($data['post_id'])?(int)$data['post_id']:0;
    $user_id = (int)$data['user_id'];
    $circle_id = (int)$data['circle_id'];
    $lv = User::get_user_lv($user_id);
    $vip = User::get_user_vip($user_id);
    $user_role = Circle::get_circle_user_role($user_id, $circle_id);
            // 提取圈子角色

    $circle_admin = $user_role['is_circle_admin'] ? "admin" : "";
    $circle_staff = $user_role['is_circle_staff'] ? "staff" : "";
    $circle_moment_manage_role =islide_get_option('circle_moment_manage_role');
    $check_role = function($list, $key = 'null') use ($lv, $vip, $circle_admin, $circle_staff) {
                if (isset($list[$key])) {
                    return Circle::check_insert_role($list[$key], $lv, $vip, $circle_admin, $circle_staff);
                }
                return Circle::check_insert_role($list, $lv, $vip, $circle_admin, $circle_staff);
            };
    $array = array(
        'can_delete' => $check_role($circle_moment_manage_role, 'delete'),
        'can_edit' =>  $check_role($circle_moment_manage_role, 'edit'),
        'can_best' =>  $check_role($circle_moment_manage_role, 'best'),
        'can_sticky' =>  $check_role($circle_moment_manage_role, 'sticky'),
        'can_public' =>  $check_role($circle_moment_manage_role, 'public'),
    );
    
    if($post_id !== 0 ){
        $array['is_self']=(isset($data['user_id']) && $data['user_id'] == get_post_field('post_author', $post_id));
    }
    
    return $array;
    
}



public static function check_reading_hide_content_role($post_id,$user_id){

$array = array(
  "post_id"=>$post_id,
  "allow"=>false,
  "authority"=>get_post_meta($post_id,'islide_post_content_hide_role',true),
  "value"=>false,
  "roles"=>array()
);

if(get_post_field('post_author', $post_id) == $user_id){
    $array['allow']=true;
    return $array;
}

if($array['authority'] && $array['authority']=="none" ){
  $array['allow']=true;
  return $array;
}

if($array['authority'] && ($array['authority']=="money" || $array['authority']=="credit"  ) ){
  $array['value']=get_post_meta($post_id,'islide_post_price',true);
  $array['allow']=Orders::islide_post_neigou_has_purchased($post_id,$user_id); 
}

if($array['authority'] && $array['authority']=="comment" ){
  $array['allow']=self::has_user_commented($post_id, $user_id) ? true : false;
}

if($array['authority'] && $array['authority']=="login" ){
  $array['allow']=get_current_user_id()? true:false;
}

if($array['authority'] && $array['authority']=="roles" ){
  $roles=get_post_meta($post_id,'islide_post_roles',true);
  $allroles = User::get_user_roles();
  $lv = User::get_user_lv($user_id);
  $vip = User::get_user_vip($user_id);
  $array['allow']=self::checkRoles($roles, $lv, $vip);
  $array['roles']=self::getRolesDetails($roles, $allroles);
}

if($array['authority'] && $array['authority']=="password" ){
  $array['allow']= self::verifyPasswordCookie($post_id);
}

return $array;


}



private static function getRolesDetails($roles, $allroles) {
    $result = [];
    // 遍历 $roles 数组，在 $allroles 中查找对应的详细信息
    foreach ($roles as $role) {
        if (isset($allroles[$role])) {
            $result[] = [
                'lv'    => $role,  // 存储角色的 key
                'name'  => $allroles[$role]['name'] ?? '',
                'image' => $allroles[$role]['image'] ?? '',
            ];
        }
    }

    return $result;
}


private static function  checkRoles($roles, $lv, $vip) {
    // 如果 $roles 为空数组，直接返回 false
    if (empty($roles)) {
        return false;
    }
    // 初始化默认值，避免 null 时报错
    $lvKey = isset($lv['lv']) ? 'lv' . $lv['lv'] : null;
    $vipKey = isset($vip['lv']) ? $vip['lv'] : null;
    // 只要 $roles 中存在 $lvKey 或 $vipKey 的任意一个，就返回 true
    return ($lvKey && in_array($lvKey, $roles)) || ($vipKey && in_array($vipKey, $roles));
}

private static function get_days_since_registration($user_id) {
    // 获取用户数据
    $user_info = get_userdata($user_id);
    
    // 检查用户是否存在
    if ($user_info) {
        // 获取用户注册时间
        $registration_time = strtotime($user_info->user_registered);
        
        // 获取当前时间
        $current_time = strtotime(current_time('mysql', 1));
        
        // 计算当前时间与注册时间之间的差值（以秒为单位）
        $difference = $current_time - $registration_time;
        
        // 将秒转换为天
        $days = floor($difference / (60 * 60 * 24));
        
        return $days;
    } else {
        return 0;
    }
}


private static function is_bind_phone($user_id){
    $user_data = get_userdata($user_id);
    return Login::is_phone($user_data->user_login) ? 1 : 0;
}


// money_type=> credit:1(积分). other:0(现金)

public static function get_order_price_by_user_and_value($user_id, $money_type,$order_value) {
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'islide_order';

    // 准备SQL查询
    $sql = $wpdb->prepare(
        "SELECT order_price FROM $table_name WHERE user_id = %d AND order_type = %s AND order_state = %d AND money_type = %d And order_value = %s",
        $user_id,
        'verify', 
        3,
        $money_type,
        $order_value
    );

    // 执行查询
    $order_price = $wpdb->get_var($sql);

    // 如果找到记录，返回order_price；否则返回0
    return $order_price ? $order_price : 0;
}


public static function islide_user_verify_condition_value($user_id,$key,$type){
    if(!$user_id){
        return 0;
    }
    switch ($key) {
        case 'post':
            return count_user_posts($user_id, 'post');
            break;
        case 'fans':
            return User::get_user_meta_count($user_id, 'islide_fans');
            break;
        case 'registered':
            return self::get_days_since_registration($user_id);
            break;
        case 'bind_phone':
            return self::is_bind_phone($user_id);
        case 'money':
            return self::get_order_price_by_user_and_value($user_id,0,$type);
        case 'credit':
            return self::get_order_price_by_user_and_value($user_id,1,$type);
            break;
    }
    return 0;
}


// 'task_post' => '发布 N 篇文章',
// 'task_comment' => '发布 N 条评论',
// 'task_follow' => '关注 N 个人',
// 'task_vip' => '开通会员',
// 'task_sign_in' => '连续签到 N 天'

// 'task_registration' => '注册时间达到 N 天',
// 'task_fans' => '获得 N 个粉丝',
// 'task_post_views' => '文章总获得 N 次点击（阅读量）',
// 'task_post_like' => '文章总获得 N 次点赞（喜欢）',
// 'task_post_favorite' => '文章总获得 N 次收藏',
// 'task_comment_like' => '评论获得 N 次点赞',


public static function islide_recom_task_completed_count($user_id,$type){
    switch ($type) {
        case 'task_vip':
            return (User::is_vip($user_id)? 1 : 0);
            break;
        case 'task_post':
            return count_user_posts($user_id, 'post');
            break;
        case 'task_comment':
            return Comment::get_user_comment_count($user_id);
            break;
        case 'task_follow':
            return User::get_user_meta_count($user_id,'islide_follow');
            break;
        case 'task_sign_in':
            return Signin::get_consecutive_days($user_id);
            break;
        case 'task_registration':
            return self::get_days_since_registration($user_id);
            break;
        case 'task_fans':
            return User::get_user_meta_count($user_id, 'islide_fans');
            break;
        case 'task_post_views':
            return User::get_user_posts_meta_sum($user_id, 'views');
            break;
        case 'task_post_like':
            return User::get_user_posts_meta_sum($user_id,'islide_post_like');
            break;
        case 'task_post_favorite':
            return User::get_user_posts_meta_sum($user_id, 'islide_post_favorites');
            break;
        case 'task_comment_like':
            return get_user_meta($user_id,'islide_comment_likes',true);
            break;
        default:
            return 0;
            break;
    }
}


public static  function islide_complete_task($user_id, $type, $task_type) {
        $completed_tasks = Task::user_completed_tasks($user_id);
        $task_data = Task::checkTaskType($type, $task_type);

        if (empty($task_data)) {
            return false;
        }

        // 如果未定义 task_count，默认为 1（推荐任务）
        if (!isset($task_data['task_count'])) {
            $task_data['task_count'] = 1;
        }

        if ($type === 'daily_task') {
            $current = $completed_tasks['daily_task']['tasks'][$task_type] ?? 0;
            return $task_data['task_count'] > $current ? $task_data : false;
        }

        if ($type === 'newbie_task') {
            return empty($completed_tasks['newbie_task'][$task_type]) ? $task_data : false;
        }

        if ($type === 'recom_task') {
            return empty($completed_tasks['recom_task'][$task_type]) ? $task_data : false;
        }

        return false;
}

/**
 * 处理任务完成后的逻辑（奖励发放、记录等）
 */
public static function islide_complete_task_action($user_id, $task, $type) {
        $meta_key = 'islide_' . $type;
        $completed = get_user_meta($user_id, $meta_key, true);
        if (!is_array($completed)) $completed = [];

        // 更新记录
        if ($type === 'daily_task') {
            if (!isset($completed['tasks']) || !is_array($completed['tasks'])) {
                $completed['tasks'] = [];
            }
            $completed['tasks'][$task['task_type']] = ($completed['tasks'][$task['task_type']] ?? 0) + 1;
        } else {
            if (!isset($completed[$task['task_type']])) {
                $completed[$task['task_type']] = 1;
            }
        }

        update_user_meta($user_id, $meta_key, $completed);

        // 发放奖励
        if (!empty($task['task_bonus'])) {
            foreach ($task['task_bonus'] as $bonus) {
                Record::update_data([
                    'user_id' => $user_id,
                    'record_type' => $bonus['key'],
                    'value' => (int)$bonus['value'],
                    'type' => $type,
                    'type_text' => $task['name'] . '（奖励）',
                    'content' => self::task_type_label($type),
                ]);
            }
        }
        
}




/**
 * 任务类型中文描述
 */
private static function task_type_label($type) {
    switch ($type) {
        case 'daily_task': return '日常任务';
        case 'newbie_task': return '新手任务';
        case 'recom_task': return '推荐任务';
        default: return '系统任务';
    }
}


public static function islide_update_signin($user_id, $consecutive_days, $date) {
    $result = array(
        'day'    => (int)$consecutive_days,
        'credit' => 0,
        'exp'    => 0,
        'vip'    => array(),
    );
    // 检查连续签到奖励是否开启
    $open = islide_get_option('signin_consecutive_open');
    if (!$open || $open !== '1') {
        return $result;
    }

    // 获取签到奖励配置
    $sign_in_award = islide_get_option('signin_bonus_group');
    if (empty($sign_in_award)) {
        return $result;
    }

    // 按连续天数筛选匹配的奖励项
    $matched_award = null;
    foreach ($sign_in_award as $award) {
        if (isset($award['day']) && (int)$award['day'] === (int)$consecutive_days) {
            $matched_award = $award;
            break;
        }
    }

    // 无匹配奖励项则返回空
    if (!$matched_award) {
        return array();
    }

     $result['credit']= isset($matched_award['credit']) ? (int)$matched_award['credit'] : 0;
     $result['exp']    = isset($matched_award['exp']) ? (int)$matched_award['exp'] : 0;
     $result['vip']    = (isset($matched_award['vip']) && isset($matched_award['vip']['vip'])) ? $matched_award['vip'] : array();


    return $result;
}


public static function islide_user_signin_continuous($user_id,$item){

    if (isset($item['credit']) && $item['credit'] > 0) {
        $type = 'credit';
        $value = (int)$item['credit'];
        Record::update_data(array(
                'user_id' => $user_id,
                'record_type' => $type,
                'value' => $value,
                'type' => 'continuous_signin',
                'type_text' => '连续签到',
                'content'     => '连续签到'.$item['day'].'天', // 任务描述
            )
        );
        
    }
    
    // 处理经验奖励
    if (isset($item['exp']) && $item['exp'] > 0) {
        $type = 'exp';
        $value = (int)$item['exp'];
        Record::update_data(array(
                'user_id' => $user_id,
                'record_type' => $type,
                'value' => $value,
                'type' => 'continuous_signin',
                'type_text' => '连续签到',
                'content'     => '连续签到'.$item['day'].'天', // 任务描述
            )
        );
        
    }
    
    if(isset($item['vip']) && !empty($item['vip'])){
        $type = $item['vip'];
        $value = $item['vip']['vip'];
        Record::update_data(array(
                'user_id' => $user_id,
                'record_type' => $type,
                'value' => $value,
                'type' => 'continuous_signin',
                'type_text' => '连续签到',
                'content'     => '连续签到'.$item['day'].'天', // 任务描述
            )
        );
        
    }
    

    
    
}


public static function islide_user_signin_daily($user_id,$item) {
    // 获取每日签到奖励配置
    $data = islide_get_option('signin_bonus');
    
    // 处理积分奖励
    if (isset($data['credit'])) {
        $credit_range = self::parse_range($data['credit']);
        if ($credit_range) {
            $credit = mt_rand($credit_range['min'], $credit_range['max']);
            $type = 'credit';
            $value = $credit;
            Record::update_data(array(
                'user_id' => $user_id,
                'record_type' => $type,
                'value' => $value,
                'type' => 'daily_signin',
                'type_text' => '日常签到',
            )
        );
        
        }
    }

    // 处理经验奖励
    if (isset($data['exp'])) {
        $exp_range = self::parse_range($data['exp']);
        if ($exp_range) {
            $exp = mt_rand($exp_range['min'], $exp_range['max']);
            $type = 'exp';
            $value = $exp;
            Record::update_data(array(
                'user_id' => $user_id,
                'record_type' => $type,
                'value' => $value,
                'type' => 'daily_signin',
                'type_text' => '日常签到',
            )
        );
        }
    }

}

/**
 * 解析范围字符串（如 "10-20"）
 * @return array|false 返回包含 min/max 的数组，无效格式返回 false
 */
private static function parse_range($input) {
    // 处理非字符串输入
    if (!is_string($input)) return false;

    // 分割字符串
    $parts = explode('-', $input);
    if (count($parts) !== 2) return false;

    // 转换数字
    $min = (int)$parts[0];
    $max = (int)$parts[1];

    // 校验有效性
    if ($min < 0 || $max < $min) return false;

    // 自动交换 min/max 如果顺序颠倒
    if ($min > $max) {
        $temp = $min;
        $min = $max;
        $max = $temp;
    }

    return ['min' => $min, 'max' => $max];
}


public static function after_report_success($id, $type, $content) {
    $user_id = 0;

    if ($type === 'post') {
        // 移入回收站
        $post = get_post($id);
        if ($post && get_post_status($id)) {
            wp_trash_post($id);
            $user_id = $post->post_author;
        }

    } elseif ($type === 'comment') {
        // 移入回收站
        $comment = get_comment($id);
        if ($comment && $comment->comment_approved != 'trash') {
            wp_set_comment_status($id, 'trash');
            $user_id = $comment->user_id;
        }

    } elseif ($type === 'user') {
        $user_id = intval($id); // id 本身是 user ID
    }

    // 扣除经验值并记录日志
    if ($user_id) {
        $type = 'exp';
        $value = -10;
        Record::update_data(array(
            'user_id' => $user_id,
            'record_type' => $type,
            'value' => $value,
            'type' => 'reported',
            'type_text' => '内容违规',
          )
        );
    }
}



public static function custom_calculate_commission($order) {
    // 获取用户 ID 和商品类型
    $user_id = $order['user_id'];
    $order_type = $order['order_type'];
    $order_total = $order['order_total'];

    // 获取推广用户（一级、二级、三级）
    $lv1 = get_user_meta($user_id, 'islide_referrer_id', true);
    $lv2 = get_user_meta($user_id, 'islide_lv2_referrer_id', true);
    $lv3 = get_user_meta($user_id, 'islide_lv3_referrer_id', true);

    // 获取推广配置
    $distribution_config = islide_get_option('distribution');
    $user_vip = get_user_meta($lv1, 'islide_vip', true);
    $vip_config = isset($distribution_config[$user_vip]) ? $distribution_config[$user_vip] : $distribution_config['lv'];

    // 如果该订单类型不在返佣类型列表中，直接返回空
    if (!in_array($order_type, $vip_config['types'])) return [];

    $commission = [];

    // 一级佣金
    if ($lv1 && !empty($vip_config['lv1_ratio'])) {
        $commission['lv1'] = round($order_total * ($vip_config['lv1_ratio'] / 100), 2);
    }

    // 二级佣金
    if ($lv2 && !empty($vip_config['lv2_ratio'])) {
        $commission['lv2'] = round($order_total * ($vip_config['lv2_ratio'] / 100), 2);
    }

    // 三级佣金
    if ($lv3 && !empty($vip_config['lv3_ratio'])) {
        $commission['lv3'] = round($order_total * ($vip_config['lv3_ratio'] / 100), 2);
    }

    $commission['total'] = array_sum($commission);

    // 立即触发分佣（可选）
    if (!empty($commission)) {
        if (isset($commission['lv1'])) {
            Distribution::distribution_commission_action($lv1, $commission['lv1'], $order, $vip_config['lv1_ratio'], 'lv1');
        }
        if (isset($commission['lv2'])) {
            Distribution::distribution_commission_action($lv2, $commission['lv2'], $order, $vip_config['lv2_ratio'], 'lv2');
        }
        if (isset($commission['lv3'])) {
            Distribution::distribution_commission_action($lv3, $commission['lv3'], $order, $vip_config['lv3_ratio'], 'lv3');
        }
    }

    return $commission;
}

public static function after_comment_deleted($comment_id) {
    delete_comment_meta($comment_id, 'islide_comment_ip_location');
    delete_comment_meta($comment_id, 'user_agent');
}


public static function process_content_wp($html) {
    // 空内容检查
    if (empty(trim($html))) {
        return $html;
    }

    // 初始化 DOM
    $dom = new \DOMDocument();
    libxml_use_internal_errors(true);
    
    // 设置编码处理
    $dom->encoding = 'UTF-8';
    $dom->loadHTML(
        '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );

    // 处理图片
    $images = $dom->getElementsByTagName('img');
    foreach ($images as $img) {
        self::processImageElement($img);
    }

    // 提取并返回 body 内容
    $body = $dom->getElementsByTagName('body')->item(0);
    return self::getInnerHtml($body);
}

// 辅助方法：获取元素内部HTML
private static function getInnerHtml($node) {
    $innerHTML = '';
    foreach ($node->childNodes as $child) {
        $innerHTML .= $node->ownerDocument->saveHTML($child);
    }
    return $innerHTML;
}


// 处理 img 元素的私有方法
private static function processImageElement($img) {
    // 保留原始 src
    if ($img->hasAttribute('src')) {
        $originalSrc = $img->getAttribute('src');
        $img->setAttribute('data-src', $originalSrc);
    }

    // 懒加载处理
    if (islide_get_option('islide_image_lazyload')) {
        $defaultImg = islide_get_option('lazyload_default_img') ?: 'https://example.com/default.jpg';
        $img->setAttribute('src', $defaultImg);
    }

    // 添加 fancybox
    if (!$img->hasAttribute('data-fancybox')) {
        $img->setAttribute('data-fancybox', 'gallery');
    }

    // 添加懒加载类
    $classes = explode(' ', trim($img->getAttribute('class')));
    if (!in_array('lazyload', $classes)) {
        $classes[] = 'lazyload';
    }
    $img->setAttribute('class', implode(' ', $classes));

    // 创建 a 标签包裹图片
    $a = $img->ownerDocument->createElement('a');
    $a->setAttribute('href', $originalSrc);
    $a->setAttribute('data-fancybox', 'gallery');
    
    // 将图片移动到 a 标签内
    $img->parentNode->replaceChild($a, $img);
    $a->appendChild($img);
}



public static function verifyPasswordCookie($post_id) {
    $post_id = (int)$post_id;
    if (!$post_id || !isset($_COOKIE['password_verify'])) {
        return false;
    }

    $raw = urldecode($_COOKIE['password_verify']);
    $data = json_decode($raw, true);

    if (!$data || json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }

    $code = trim($data['code'] ?? '', " \t\n\r\0\x0B\xC2\xA0");
    $cookie_post_id = (int)($data['post_id'] ?? 0);
    $type = $data['type'] ?? 'post';

    if (!$code || $cookie_post_id !== $post_id) {
        return false;
    }

    // 获取官方密码配置
    $verify_option = islide_get_option('password_verify');
    $official_code = $verify_option['code'] ?? '';

    // 获取该 post 或 term 的独立密码
    if ($type === 'circle') {
        $password = get_term_meta($post_id, 'islide_circle_password', true);
    } else {
        $password = get_post_meta($post_id, 'islide_post_password', true);
    }

    // 验证是否匹配
    if ($code === $official_code || $code === $password) {
        return true;
    }

    return false;
}


public static function handle_content_hide_shortcode($content) {
    $GLOBALS['__hide_content_counter'] = 0;
    return preg_replace_callback('/\[content_hide\](.*?)\[\/content_hide\]/is', function ($matches) {
        $id = $GLOBALS['__hide_content_counter']++;
        return '<div class="content-hide-block" data-id="' . $id . '"></div>';
    }, $content);
}

function islide_filter_content_images($content) {
    if (empty($content)) return $content;

    // 匹配所有 <img src="..."> 标签
    return preg_replace_callback('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', function($matches) {
        $original_tag = $matches[0];
        $img_url = $matches[1];
        $relative_path = get_relative_upload_path($img_url);
        // 替换 src
        $new_tag = str_replace($img_url, $relative_path, $original_tag);

        return $new_tag;
    }, $content);
}




}
