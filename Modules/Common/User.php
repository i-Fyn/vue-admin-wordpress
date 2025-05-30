<?php namespace islide\Modules\Common;
use islide\Modules\Common\Login;
use islide\Modules\Common\Post;
use islide\Modules\Templates\Modules\Posts;
use islide\Modules\Common\Oauth;
use islide\Modules\Common\IpLocation;
use islide\Modules\Common\Record;
use islide\Modules\Common\Message;
use islide\Modules\Common\Distribution;

/**
 * 用户功能管理类
 * 
 * 提供用户信息获取、用户权限管理、经验值管理、收藏关注功能等
 * 
 * @author ifyn
 * @version 1.0.0
 * @since 2024/7/1
 */
class User{

    /**
     * 初始化方法
     * 
     * @author ifyn
     * @return void
     */
    public function init(){
        
    }
    
    /**
     * 获取用户全部信息
     * 
     * @param int $user_id 用户ID
     * @return array 用户信息数组
     * @author ifyn
     */
    public static function get_author_info($user_id){

        $user_id = (int)$user_id;
        
        if(empty($user_id)) {
            return array();
        }
        
        //当前用户id
        $current_user_id = get_current_user_id();

        $current = false;
        $admin = user_can($current_user_id, 'administrator');

        $self = $user_id == $current_user_id ? true : false;

        $key_following = false;
        if($current_user_id){
            $following = get_user_meta($user_id,'islide_fans',true);
            $following = is_array($following) ? $following : array();
            $key_following = in_array($current_user_id,$following);
        }
        
        $data = array();
        if($self && $user_id){
            $data = array(
            'self' => $self ? true : false,
            'admin' => $admin,
            'id' => $self || $admin ? $user_id : 0,
            'money' => $self || $admin ? self::get_money($user_id) : 0,
            'credit' => $self || $admin ? self::get_credit($user_id) : 0,
            'is_follow' => $key_following !== false ? true : false,
            'commission' => Distribution::get_user_commission($user_id),
        );
        }

        $public_data = self::get_user_public_data($user_id,true);
        
        if(isset($data['id']) && isset($public_data['id'])){
        unset($public_data['id']);
        }
        
        //合并数组
        $data = array_merge($data, $public_data);
            
        return $data;
    }
    
    /**
     * 获取用户公开信息
     *
     * @param int $user_id 用户ID
     * @return array 用户公开信息数组
     * @author ifyn
     */
    public static function get_user_public_data($user_id, $is_admin = false){
        if(empty($user_id)) {
            return array();
        }

        $data = get_userdata($user_id);
        if(!$data) {
            return array();
        }
        
        //头像
        $avatar = get_avatar_url($user_id, array('size'=>160));
        
        $pendant = '';
        
        $vip = self::get_user_vip($user_id);
        
        $badge = isset($vip['icon']) && !empty($vip['icon']) ? $vip['icon'] : '';
        
        $verify = self::get_user_verify($user_id);
        
        $stats_count = self::get_user_stats_count($user_id);
        $followers_count = self::get_user_followers_stats_count($user_id); //获取关注数计数
        
        $badge = get_relative_upload_path(isset($verify['icon']) && !empty($verify['icon']) ? $verify['icon'] : $badge);
        
        //ip属地
        $ip_location = get_user_meta($user_id, 'islide_login_ip_location', true);  
        
        $name = isset($data->display_name) ? esc_attr($data->display_name) : '';
        $user_data = array(
            'id'     => $user_id,
            'name'   => $name,
            'avatar' => $avatar,
            'desc'   => isset($data->description) && !empty($data->description) ? esc_attr($data->description) : islide_get_option('user_desc'),
            'desc'   => isset($data->description) && !empty($data->description)? esc_attr($data->description) : islide_get_option('user_desc'),
            'pendant'=> $pendant,
            'badge'  => $badge,
            'lv'     => self::get_user_lv($user_id),
            'vip'    => $vip,
            'verify' => $verify,
            'ip' => IpLocation::build_location($ip_location),
            'stats'=>$stats_count,
            'followers'=>$followers_count,
            'login'=> ( $user_id && ($user_id == get_current_user_id())) ? true : false
        );
        
        $user_data['cover'] = get_relative_upload_path(apply_filters('islide_get_user_cover_url', $user_id));
            
        return $user_data;
    }
    
    
    /**
     * 获取用户的统计数据
     *
     * @param int $user_id 用户 ID
     * @return array 包含用户统计数据的关联数组
     */
    public static function get_user_stats_count($user_id) {
        $stats = array(
            'posts_views_count' => self::get_user_posts_meta_sum($user_id, 'views'), // 获取用户的文章浏览次数总和
            'posts_like_count' => self::get_user_posts_meta_sum($user_id,'islide_post_like'),
            'posts_count' => count_user_posts($user_id, 'post'), // 获取用户的文章数量
            'dynamic_count' => count_user_posts($user_id, 'circle'),// 获取用户的动态数量
            'comments_count' => Comment::get_user_comment_count($user_id), // 获取用户的评论数量
            'favorites_count' => self::get_user_meta_count($user_id, 'islide_user_favorites') // 获取用户的收藏数量
        );
        return $stats;
    }
    
    //获取用户昵称
    public static function get_user_name_html($user_id,$user_name = ''){
        $lv = self::get_user_lv($user_id);
        $vip = self::get_user_vip($user_id);
        
        if(!$user_name) {
            $user_name = get_the_author_meta('display_name',$user_id)?? '';
        }
        
        $html = '<div class="user-info-name">';
        $html .= '<a target="_blank" class="user-name no-hover" href="/user/'.$user_id.'">'.esc_attr($user_name).'</a>';
        $html .= '<span class="user-lv">'.(!empty($lv['icon']) ? '<img src="'.$lv['icon'].'" class="lv-img-icon" alt="'.$lv['lv'].'">' : $lv['name']).'</span>';
        $html .= $vip && !empty($vip['image']) ? '<span class="user-vip"><img src="'.$vip['image'].'" class="vip-img-icon" alt="'.$vip['name'].'"></span>' : '';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 获取所有设置项中的权限等级和VIP
     *
     * @return array 权限等级的数组
     * @author ifyn
     * @version 1.0.0
     * @since 2023
     */
    public static function get_user_roles(){
        
        $vip_data = (array)islide_get_option('user_vip_group');
        $lv_data = (array)islide_get_option('user_lv_group');
        
        $_lv_data = array();
        if(!empty($lv_data)){
            foreach($lv_data as $k => $v){
                if(!isset($v['name'])){
                    $v = [];
                    $v['name'] = '未设置';
                }
                $_lv_data['lv'.$k] = $v;
            }
        }
        
        $_vip_data = array();
        if(!empty($vip_data)){
            foreach($vip_data as $k => $v){
                
                if(!isset($v['name'])){
                    $v = [];
                    $v['name'] = '未设置';
                }
                
                if(isset($v['vip_group'])) {
                    unset($v['vip_group']);
                }
                
                $_vip_data['vip'.$k] = $v;
            }
        }

        return array_merge($_vip_data, $_lv_data);
    }
    
    /**
     * 变更用户VIP状态
     * 
     * @param int $user_id 用户ID
     * @param int $day VIP天数，9999为永久
     * @param string $vip VIP等级，为空则保持原等级
     * @return mixed 成功返回到期天数，失败返回false
     * @author ifyn
     */
    public static function vip_change($user_id, $day, $vip = ''){
        
        if(empty($user_id) || empty($day)) {
            return false;
        }

        $user_vip = get_user_meta($user_id, 'islide_vip', true);
        $vip = $vip ?: $user_vip;
        
        if(empty($vip)) {
            return false;
        }
        
        //开通vip天数
        $vip_day = (int)$day;
        $end = '';
        
        //续费
        if($user_vip) {
            
            $user_vip_exp_date = get_user_meta($user_id, 'islide_vip_exp_date', true);
            
            if($vip_day == 9999 || (string)$user_vip_exp_date === '0'){
                $end = 0; //续费永久
            }else if((string)$user_vip_exp_date !== '0'){
                $end = $user_vip_exp_date + 86400 * $vip_day;
            }else{
                $end = wp_strtotime('+'.$vip_day.' day');
            }
        
        //开通vip   
        }else{
            
            update_user_meta($user_id, 'islide_vip', $vip);
            if($vip_day == 9999){
                $end = 0; //开通永久
            }else{
                $end = wp_strtotime('+'.$vip_day.' day');
            }
        }
        
        //更新vip时间
        update_user_meta($user_id, 'islide_vip_exp_date', $end);
        
        return $end ? $end/86400 : 0;
    }
    
    /**
     * 获取用户vip信息
     * 
     * @param int $user_id 用户ID
     * @return array|false VIP信息数组或false(无VIP)
     * @author ifyn
     */
    public static function get_user_vip($user_id){
        if(empty($user_id)) {
            return array();
        }
    
        //获取VIP用户等级并检查vip是否已经过期，取消会员
        $vip = self::check_user_vip_time($user_id);

        if($vip === false) {
            return array();
        }
        
        return $vip;
    }
    
    /**
     * 获取VIP会员折扣
     * 
     * @return float 折扣值(0-100)
     * @author ifyn
     */
    public static function get_vip_discount() {
        $user_id = get_current_user_id();
        $discount = 100;
    
        if($user_id) {
            $user_lv = self::get_user_vip($user_id);
    
            if(!empty($user_lv)) {
                $vip_lv = $user_lv['name'];
                $vip_setting = islide_get_option('user_vip_group');
    
                if(is_array($vip_setting) && !empty($vip_setting)) {
                    foreach($vip_setting as $vip) {
                        if(isset($vip['name']) && strtolower($vip['name']) === strtolower($vip_lv)) {
                            $discount = isset($vip['shop_discount']) && is_numeric($vip['shop_discount']) 
                                ? (float)$vip['shop_discount'] 
                                : 100;
                            break;
                        }
                    }
                }
            }
        }
    
        return $discount;
    }
    
    /**
     * 检查VIP用户是否过期
     *
     * @param int $user_id 用户ID
     * @return array|false 如果VIP有效返回VIP信息数组，否则返回false
     * @author ifyn
     * @version 1.0.0
     * @since 2023
     */
    public static function check_user_vip_time($user_id){
        if(empty($user_id)) {
            return false;
        }

        $user_vip_exp_date = get_user_meta($user_id, 'islide_vip_exp_date', true);
        if(empty($user_vip_exp_date) && $user_vip_exp_date !== '0') {
            return false;
        }
    
        $user_vip = get_user_meta($user_id, 'islide_vip', true);
        if(empty($user_vip)) {
            return false;
        }
        
        $vip_data = islide_get_option('user_vip_group');
        $vip_index = (string)preg_replace('/\D/s', '', $user_vip);
    
        if(!isset($vip_data[$vip_index])) {
            return false;
        }
    
        if(isset($vip_data[$vip_index]['vip_group'])) {
            unset($vip_data[$vip_index]['vip_group']);
        }
    
        //获取VIP用户等级
        $vip = $vip_data[$vip_index];
    
        //如果是永久会员
        if((string)$user_vip_exp_date === '0'){
            return array(
                'name' => $vip['name'],
                'lv' => $user_vip,
                'image' => $vip['image'],
                'icon' => $vip['icon'],
                'color' => isset($vip['color']) ? $vip['color'] : '',
                'time' => 0,
                'date' => 0,
            );
        }

        //如果不是永久会员
        if((string)$user_vip_exp_date !== '0'){
            //检查是否过期
            if($user_vip_exp_date < wp_strtotime(current_time('mysql'))){
                delete_user_meta($user_id, 'islide_vip');
                delete_user_meta($user_id, 'islide_vip_exp_date');
                return false;
            }
            
            return apply_filters('islide_check_user_vip_time', array(
                'name' => $vip['name'],
                'lv' => $user_vip,
                'image' => $vip['image'],
                'icon' => $vip['icon'],
                'color' => isset($vip['color']) ? $vip['color'] : '',
                'time' => ceil(($user_vip_exp_date - wp_strtotime(current_time('mysql'))) / DAY_IN_SECONDS), //剩余到期天数
                'date' => wp_date('Y-m-d', $user_vip_exp_date),
            ));
        }
        
        return false;
    }
    
    /**
     * 检查用户是否有VIP
     * 
     * @param int $user_id 用户ID
     * @return bool 是否有VIP
     * @author ifyn
     */
    public static function is_vip($user_id) {
        if(empty($user_id)) {
            return false;
        }
        
        $vip_exp_date = get_user_meta($user_id, 'islide_vip_exp_date', true);
        if(empty($vip_exp_date) && $vip_exp_date !== '0') {
            return false;
        }
        
        $is_permanent_vip = ($vip_exp_date === '0');
        if($is_permanent_vip || ($vip_exp_date && $vip_exp_date > time())) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 获取某个用户的等级
     *
     * @param int $user_id 用户ID
     * @return array 用户等级信息数组
     * @author ifyn
     * @version 1.0.0
     * @since 2023
     */
    public static function get_user_lv($user_id){
        
        if(empty($user_id)) {
            return array();
        }
        
        $user_lv = self::update_user_lv($user_id);
        if($user_lv === false) {
            return array();
        }
        
        $lv_data = islide_get_option('user_lv_group');
        
        if(!isset($lv_data[$user_lv])) {
            return array();
        }
        
        //获取用户等级
        $lv = isset($lv_data[$user_lv]) ? $lv_data[$user_lv] : array();
        $_lv = array();
        
        if($lv) {
            //获取用户经验值
            $user_lv_exp = get_user_meta($user_id, 'islide_lv_exp', true);
            $user_lv_exp = $user_lv_exp ? (int)$user_lv_exp : 0;
            
            //下一个等级经验
            $next_lv_exp = isset($lv_data[$user_lv + 1]['exp']) ? (int)$lv_data[$user_lv + 1]['exp'] : (int)$lv_data[$user_lv]['exp'];
            
            //计算经验比例
            $exp_ratio = 0;
            //如果用户当前经验大于下一级经验并且为最后一级
            if($user_lv_exp > $next_lv_exp && !isset($lv_data[$user_lv + 3])) {
                $exp_ratio = 100;
            } elseif($next_lv_exp != 0) {
                $exp_ratio = bcmul(round($user_lv_exp/$next_lv_exp, 2), 100, 2);
            } else {
                $exp_ratio = 100;
            }
            
            $_lv = array(
                'name' => $lv_data[$user_lv]['name'],
                'exp'  => $user_lv_exp,
                'icon' => $lv_data[$user_lv]['image'],
                'lv'   => $user_lv,
                'next_lv_name' => isset($lv_data[$user_lv + 1]) ? $lv_data[$user_lv + 1]['name'] : $lv_data[$user_lv]['name'],
                'next_lv_exp'  => $next_lv_exp,
                'next_lv_icon' => isset($lv_data[$user_lv + 1]) ? $lv_data[$user_lv + 1]['image'] : $lv_data[$user_lv]['image'],
                'exp_ratio' => $exp_ratio
            );
        }
        
        return $_lv;
    }
    
    /**
     * 重建更新普通用户的等级
     *
     * @param int $user_id 用户ID
     * @return int|false 重设的等级，如果更新失败则返回false
     * @author ifyn
     */
    public static function update_user_lv($user_id){
        if(empty($user_id)) {
            return false;
        }
        
        //获取当前用户等级
        $user_lv = get_user_meta($user_id, 'islide_lv', true);
        $user_lv = $user_lv ? (int)$user_lv : 0;
        
        //获取用户经验值
        $user_lv_exp = get_user_meta($user_id, 'islide_lv_exp', true);
        $user_lv_exp = $user_lv_exp ? (int)$user_lv_exp : 0;
        
        //获取等级设置项
        $lv_data = islide_get_option('user_lv_group');
        if(empty($lv_data)) {
            return false;
        }
        
        $lv = 0;
        
        foreach($lv_data as $k => $v) {
            if(!isset($v['exp'])) {
                continue;
            }
            
            //如果当前用户经验大于等于升级经验
            if($user_lv_exp >= (int)$v['exp']) {
                $lv = $k;
            } else {
                break;
            }
        }
        
        if($lv != $user_lv) {
            update_user_meta($user_id, 'islide_lv', $lv);
            do_action('islide_update_user_lv', $user_id, $lv_data[$lv]);
        }
        
        return $lv;
    }
    
    /**
     * 获取某个用户的认证信息
     *
     * @param int $user_id 用户ID
     * @return array 认证信息数组
     * @author ifyn
     * @version 1.0.0
     * @since 2024/5/16
     */
    public static function get_user_verify($user_id){
        if(empty($user_id)) {
            return array();
        }
        
        $type = get_user_meta($user_id, 'islide_verify_type', true);
        if(empty($type)) {
            return array();
        }
        
        $verify_group = islide_get_option('verify_group');
        if(empty($verify_group)) {
            return array();
        }
        
        $result = array_filter($verify_group, function($item) use ($type) {
            return isset($item['type']) && $item['type'] === $type;
        });
        
        if(empty($result)) {
            return array();
        }
        
        $verify = reset($result); // 获取符合条件的第一个元素
        
        $title = get_user_meta($user_id, 'islide_verify', true);
        
        return array(
            'icon' => isset($verify['icon']) ? $verify['icon'] : '',
            'name' => isset($verify['name']) ? $verify['name'] : '',
            'title' => $title,
            'type' => $type
        );
    }
    
    /**
     * 获取用户自定义字段Array数量 关注数量 粉丝数量 收藏数量
     *
     * @param int $user_id 用户ID
     * @param string $mata_key 元数据键名
     * @return int 数量
     * @author ifyn
     */
    public static function get_user_meta_count($user_id, $mata_key){
        if(empty($user_id) || empty($mata_key)) {
            return 0;
        }

        $count = 0;
        
        $meta_value = get_user_meta($user_id, $mata_key, true);
        
        if(is_array($meta_value)) {
            $count = count($meta_value);
            //判断是否是多维数组
            if($count == count($meta_value, 1) && isset($meta_value[0])) { //不是
                return $count;
            } else {
                return count($meta_value, 1) - $count;
            }
        }
        
        return $count;
    }
    
    /**
     * 统计用户自定义字段的数量
     *
     * @param string $meta_key 自定义字段的键名
     * @param string $meta_value 自定义字段的值
     * @return int 自定义字段的数量
     * @author ifyn
     */
    public static function count_users_custom_field($meta_key, $meta_value) {
        if(empty($meta_key) || empty($meta_value)) {
            return 0;
        }
        
        $args = array(
            'meta_query' => array(
                array(
                    'key' => $meta_key,
                    'value' => $meta_value,
                    'compare' => '=',
                ),
            ),
            'count_total' => true, // 仅获取数量，而不是用户数据
        );
    
        $query = new \WP_User_Query($args);
        $count = $query->get_total();

        return $count;
    }
    
    /**
     * 获取指定用户发布的文章中指定自定义字段的值之和
     * 自定义字段的值有可能是序列化数组，如果是数组则计算每个数组的个数之和
     *
     * @param int $user_id 用户ID
     * @param string $meta_key 自定义字段的键名
     * @return int 自定义字段的值之和
     * @author ifyn
     */
    public static function get_user_posts_meta_sum($user_id, $meta_key) {
        if(empty($user_id) || empty($meta_key)) {
            return 0;
        }
        
        global $wpdb;
        $sum = 0;
        
        // 查询指定用户发布的文章中指定自定义字段的值
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT meta_value
            FROM $wpdb->postmeta
            INNER JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->postmeta.post_id
            WHERE $wpdb->posts.post_author = %d
            AND $wpdb->postmeta.meta_key = %s
        ", $user_id, $meta_key));
        
        // 遍历查询结果，计算自定义字段的值之和
        foreach($results as $result) {
            $value = maybe_unserialize($result->meta_value);
            if(is_array($value)) {
                // 如果自定义字段的值是数组，计算数组中元素的个数之和
                $sum += count($value);
            } else {
                // 如果自定义字段的值不是数组，转换为整数并加到总和中
                $sum += intval($value);
            }
        }
        return $sum;
    }
    
    public static function get_user_posts_stats($user_id) {
        global $wpdb;

        $total_comments = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(comment_ID)
            FROM {$wpdb->comments}
            INNER JOIN {$wpdb->posts} ON {$wpdb->comments}.comment_post_ID = {$wpdb->posts}.ID
            WHERE {$wpdb->posts}.post_author = %d
            AND {$wpdb->comments}.comment_approved = '1'
        ", $user_id));

        $stats = array(
            'total_views' => array(
                'name' => '阅读',
                'count' => self::get_user_posts_meta_sum($user_id, 'views')
            ),
            'total_likes' => array(
                'name' => '点赞',
                'count' => self::get_user_posts_meta_sum($user_id, 'islide_post_like'),
            ),
            'total_comments' => array(
                'name' => '评论',
                'count' => intval($total_comments),
            ),
            'total_favorites' => array(
                'name' => '收藏',
                'count' => self::get_user_posts_meta_sum($user_id, 'islide_post_favorites'),
            ),
            'total_shares' => array(
                'name' => '分享',
                'count' => 0
            ),
        );
    
        return $stats;
    }
    
    //获取当前用户的附件
    public static function get_current_user_attachments($type,$paged){
        $user_id = get_current_user_id();

        if(!$user_id) return array('error'=>__('请先登录','islide'));
        
        $offset = ($paged -1)*12;

        if(!$type) return array('error'=>__('文件类型错误','islide'));

        $supported_mimes = '';

        if($type == 'image'){
            $supported_mimes  = array( 'image/jpeg', 'image/gif', 'image/png', 'image/bmp', 'image/tiff', 'image/x-icon','image/svg' );
        }
        
        if($type == 'video'){
            $supported_mimes  = array( 'video/mp4', 'video/x-ms-asf', 'video/x-ms-wmv', 'video/x-ms-wmx', 'video/x-ms-wm', 'video/avi','video/divx','video/x-flv','video/quicktime','video/mpeg','video/ogg','video/webm','video/x-matroska','video/3gpp','video/3gpp2');
        }

        if(!$supported_mimes) return array('error'=>__('参数错误','islide'));

        $attachment_query   = array(
            'post_type'         => 'attachment',
            'post_status'       => 'inherit',
            'post_mime_type'    => $supported_mimes,
            'posts_per_page'    => 12,
            'post__not_in' => array(1),
            'author'=>$user_id,
            'offset'=>$offset
        );

        $the_query = new \WP_Query($attachment_query);

        $arr = array();
        $_pages = 0;
        
        if ( $the_query->have_posts() ){
            $_pages = $the_query->max_num_pages;
            while ( $the_query->have_posts() ) {
                $the_query->the_post();

                $att_url = wp_get_attachment_url($the_query->post->ID);
                
                $arr[] = array(
                    'id' => $the_query->post->ID,
                    'url' => $att_url,
                    'thumb' => $type == 'image' ? islide_get_thumb(array('url'=>$att_url,'width'=>100,'height'=>100)) : ''
                );
            } 
            
        }
        wp_reset_postdata();
        return array(
            'pages'=>$_pages,
            'data'=>$arr
        );
    }
    
    //用户收藏
    public static function user_favorites($post_id){

        $current_user_id = get_current_user_id();

        if(!$current_user_id) return array('error'=>'请登录');
        if(!$post_id) return array('error'=>'参数不全');

        $post_type = get_post_type($post_id);

        //获取用户的收藏数据
        $favorites = get_user_meta($current_user_id,'islide_user_favorites',true);
        $favorites = is_array($favorites) ? $favorites : array();

        //获取文章的收藏数据
        $post_favorites = get_post_meta($post_id, 'islide_post_favorites', true );
        $post_favorites = is_array($post_favorites) ? $post_favorites : array();

        
        $key_f = isset($favorites[$post_type]) ? array_search($post_id,$favorites[$post_type]) : false;
        $key_u = array_search($current_user_id,$post_favorites);

        $add = false;

        //如果不存在此收藏，加入
        if($key_f === false && $key_u === false){
            $favorites[$post_type][] = $post_id;
            $post_favorites[] = $current_user_id;
            $add = true;
        }else{
            unset($favorites[$post_type][$key_f]);
            unset($post_favorites[$key_u]);
        }

        update_user_meta($current_user_id,'islide_user_favorites',$favorites);
        update_post_meta($post_id,'islide_post_favorites',$post_favorites);
        
        //收藏钩子
        do_action('islide_post_favorite',$post_id,$current_user_id,$add);
        
        return array('message' => $add === true ? '收藏成功！' : '收藏取消！','count' => count($post_favorites));;
    }
    
    public static function get_user_favorites_arg($user_id,$post_type){
        $favorites = get_user_meta($user_id,'islide_user_favorites',true);
        $favorites = is_array($favorites) ? $favorites : array();

        if(!isset($favorites[$post_type])) return;

        $arr = array();

        foreach ($favorites[$post_type] as $k => $v) {
            if(get_post_type($v) === $post_type && get_post_status($v) === 'publish'){
                $arr[] = $v;
            }
        }
        
        return $arr;
    }
    
    /**
     * 获取用户收藏列表
     *
     * @param array $data 包含以下参数:
     *      - int $user_id 用户ID
     *      - int $paged 页码
     *      - int $size 每页数量
     *      - string $post_type 文章类型
     * @return array 收藏文章列表或错误信息
     * @author ifyn
     * @version 1.0.0
     * @since 2023
     */
    public static function get_user_favorites_list($data){
        // 检查参数是否为空
        if(empty($data)) {
            return array('error' => '参数为空');
        }
        
        // 如果是REST API的request对象，获取参数
        if(is_object($data) && method_exists($data, 'get_params')) {
            $data = $data->get_params();
        }
        
        // 检查参数是否为数组
        if(!is_array($data)) {
            return array('error' => '参数不是数组格式');
        }

        $post_type = isset($data['post_type']) ? $data['post_type'] : '';
        $user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;
        $paged = isset($data['paged']) ? (int)$data['paged'] : 1;
        $size = isset($data['size']) ? (int)$data['size'] : 10;
        
        $current_user_id = get_current_user_id();
        
        if($size > 20) {
            return array('error' => '请求数量过多');
        }
        if($paged < 1 || !$user_id || !$size) {
            return array('error' => '请求格式错误', 'details' => array(
                'paged' => $paged,
                'user_id' => $user_id,
                'size' => $size
            ));
        }

        $favorites = self::get_user_favorites_arg($user_id, $post_type);

        $empty = array(
            'pages' => 0,
            'count' => 0,
            'data' => ''
        );

        if(empty($favorites)) {
            return $empty;
        }
        
        $offset = ($paged - 1) * $size;
        
        $arr = array();
        
        for($i = $offset; $i < $offset + $size; $i++) {
            if(isset($favorites[$i])){
                $arr[] = $favorites[$i];
            }
        }
        
        if(empty($arr)) {
            return $empty;
        }
        
        $data['post__in'] = $arr;
        
        if($post_type == 'circle'){
            $data = Circle::get_moments($data);
        } else {
            $data = Post::get_custom_posts($data);
        }
            
        return array(
            'pages' => ceil(count($favorites) / $size),
            'count' => count($favorites),
            'data' => $data['data']
        );
    }
    
    /**
     * 关注取消关注
     *
     * @param int $user_id 关注用户的ID
     * @return array 操作结果
     * @author ifyn
     * @version 1.0.0
     * @since 2023
     */
    public static function user_follow_action($user_id){
        $user_id = (int)$user_id;
        $current_user_id = get_current_user_id();
        
        if(empty($current_user_id)) {
            return array('error' => '请先登录');
        }

        if(empty($user_id)) {
            return array('error' => '参数错误');
        }
        
        if($current_user_id == $user_id) {
            return array('error' => '不能关注自己');
        }
        
        if(!get_user_by('id', $user_id)) {
            return array('error' => '关注的用户不存在');
        }
        
        //关注数据
        $follow = get_user_meta($current_user_id, 'islide_follow', true);
        $follow = is_array($follow) ? $follow : array();
        
        $key_follow = array_search($user_id, $follow);
        
        $action = false;
        
        //关注 判断是否在关注列表
        if($key_follow === false){
            $follow[] = $user_id;
            $action = true;
        } else { //取消关注
            unset($follow[$key_follow]);
        }
        
        //关注用户的粉丝
        $fans = get_user_meta($user_id, 'islide_fans', true);
        $fans = is_array($fans) ? $fans : array();
        
        $key_fans = array_search($current_user_id, $fans);
        
        //增加粉丝
        if($key_fans === false){
            $fans[] = $current_user_id;
            $action = true;
        } else { //删除粉丝
            unset($fans[$key_fans]);
        }
        
        //返回数组的所有值（非键名）
        update_user_meta($user_id, 'islide_fans', array_values($fans));
        update_user_meta($current_user_id, 'islide_follow', array_values($follow));
        
        //钩子
        do_action('islide_user_follow_action', $user_id, $current_user_id, $action);
        
        return $action;
    }
    
    /**
     * 检查是否已经关注
     *
     * @param int $user_id 被检查的用户ID
     * @return array 关注状态信息
     * @author ifyn
     */
    public static function check_follow($user_id){
        $current_user_id = (int)get_current_user_id();

        $arr = array(
            'is_follow' => false,
            'is_self' => false
        );
        
        if(empty($current_user_id) || empty($user_id)) {
            return $arr;
        }

        $follow = get_user_meta($current_user_id, 'islide_follow', true);
        $follow = is_array($follow) ? $follow : array();

        $key_follow = array_search($user_id, $follow);

        if($key_follow !== false){
            $arr['is_follow'] = true;
        }

        if((int)$user_id === (int)$current_user_id){
            $arr['is_self'] = true;
        }

        return $arr;
    }
    
    /**
     * 获取用户的关注和粉丝数量
     *
     * @param int $user_id 用户ID
     * @return array 包含用户关注和粉丝数量的关联数组
     * @author ifyn
     */
    public static function get_user_followers_stats_count($user_id) {
        if(empty($user_id)) {
            return array(
                'follow' => 0,
                'fans' => 0,
                'is_follow' => false,
                'is_self' => false
            );
        }
        
        $current_user_id = (int)get_current_user_id();
        
        $follow = self::get_user_meta_count($user_id, 'islide_follow'); // 获取用户关注的人数
        $fans = self::get_user_meta_count($user_id, 'islide_fans'); // 获取关注该用户的人数
    
        $arr = array(
            'follow' => $follow, // 用户关注的人数
            'fans' => $fans, // 关注该用户的人数
            'is_follow' => false,
            'is_self' => false
        );
        
        if(empty($current_user_id)) {
            return $arr;
        }

        $follow_list = get_user_meta($current_user_id, 'islide_follow', true);
        $follow_list = is_array($follow_list) ? $follow_list : array();

        $key_follow = array_search($user_id, $follow_list);

        if($key_follow !== false){
            $arr['is_follow'] = true;
        }

        if((int)$user_id === (int)$current_user_id){
            $arr['is_self'] = true;
        }
        
        return $arr;
    }
    
    /**
     * 获取关注列表
     *
     * @param int $user_id 用户ID
     * @param int $paged 页码
     * @param int $size 每页数量
     * @return array 关注用户列表
     * @author ifyn
     * @version 1.0.0
     * @since 2023
     */
    public static function get_follow_list($user_id, $paged, $size){
        $current_user_id = get_current_user_id();
        $user_id = (int)$user_id;
        $paged = (int)$paged;
        $size = (int)$size;
        
        if($size > 20) {
            return array('error' => '请求数量过多');
        }
        if($paged < 1) {
            return array('error' => '请求格式错误');
        }
        
        $_follow = get_user_meta($current_user_id, 'islide_follow', true);
        $_follow = is_array($_follow) ? $_follow : array();
        
        //获取用户关注ids 倒序
        $follow_raw = get_user_meta($user_id, 'islide_follow', true);
        $follow = array_reverse(is_array($follow_raw) ? $follow_raw : []);
        
        if(empty($follow)){
            return array(
                'pages' => 0,
                'data' => array()
            );
        }
        
        $offset = ($paged - 1) * $size;

        $arr = array();

        for($i = $offset; $i < $offset + $size; $i++) {
            if(isset($follow[$i])){
                $user_data = self::get_user_public_data($follow[$i]);
                if(!empty($user_data)) {
                    $arr[] = $user_data;
                }
            }
        }
        
        if(empty($arr)) {
            return array('error' => '暂无数据');
        }
        
        return array(
            'pages' => ceil(count($follow) / $size),
            'data' => $arr,
        );
    }


    /**
     * 获取用户粉丝列表
     *
     * @param int $user_id 用户ID
     * @param int $paged 页码
     * @param int $size 每页数量
     * @return array 粉丝用户列表
     * @author ifyn
     * @version 1.0.0
     * @since 2025
     */
    public static function get_fans_list( $user_id,$paged,$size ){
        $current_user_id = get_current_user_id();
        $user_id = (int)$user_id;
        $paged = (int)$paged;
        $size = (int)$size;
        
        if((int)$size > 20) return array('error'=>'请求数量过多');
        if((int)$paged < 0) return array('error'=>'请求格式错误');
        
        $follow = get_user_meta($current_user_id,'islide_follow',true);
        $follow = is_array($follow) ? $follow : array();
        
        //获取用户关注ids
        $fans = get_user_meta($user_id,'islide_fans',true);
        
        if(empty($fans)){
            return array(
                'pages'=>0,
                'data'=>array()
            );
        }

        $offest = ($paged - 1)*$size;

        $arr = array();
        
        for ($i=$offest; $i < $offest + $size; $i++) {
            if(isset($fans[$i]) && get_user_by( 'id', $fans[$i] )){
                $arr[]= self::get_user_public_data($fans[$i]);
            }
        }
        
        return array(
            'pages'=>ceil(count($fans)/$size),
            'data'=>$arr,
        );
    }
    
    /**
     * 改变用户积分
     *
     * @param int $user_id 用户ID
     * @param int $_credit 积分变化值
     * @return mixed 操作结果
     * @author ifyn
     */
    public static function credit_change($user_id, $_credit){
        if(empty($user_id)) {
            return false;
        }
        
        //获取当前用户的积分
        $credit = get_user_meta($user_id, 'islide_credit', true);
        $credit = $credit ? (int)$credit : 0;

        //积分增减
        $credit = (int)$_credit + $credit;

        if($credit < 0){
            return false;
        }

        //更新积分
        update_user_meta($user_id, 'islide_credit', (int)$credit);
        
        return apply_filters('islide_change_credit', $credit, $user_id);
    }
    
    /**
     * 获取用户积分
     *
     * @param int $user_id 用户ID
     * @return int 用户积分
     * @author ifyn
     */
    public static function get_credit($user_id){
        if(empty($user_id)) {
            return 0;
        }
        
        $credit = get_user_meta($user_id, 'islide_credit', true);
        return $credit ? (int)$credit : 0;
    }
    
    /**
     * 改变用户余额
     *
     * @param int $user_id 用户ID
     * @param float $_money 余额变化值
     * @return bool 操作结果
     * @author ifyn
     */
    public static function money_change($user_id, $_money){
        if(empty($user_id)) {
            return false;
        }

        $money = get_user_meta($user_id, 'islide_money', true);
        $money = $money ? (float)$money : 0;

        //金额增减
        $money = bcadd((float)$_money, (float)$money, 2);

        if($money < 0) {
            return false;
        }

        update_user_meta($user_id, 'islide_money', $money);
        return true;
    }
    
    /**
     * 获取用户余额
     *
     * @param int $user_id 用户ID
     * @return string 用户余额，格式化为小数点后两位
     * @author ifyn
     */
    public static function get_money($user_id){
        if(empty($user_id)) {
            return 0;
        }

        $money = get_user_meta($user_id, 'islide_money', true);
        $money = $money ? (float)$money : 0;

        return number_format($money, 2, ".", "");
    }
    
    /**
     * 用户等级经验变更
     *
     * @param int $user_id 用户ID
     * @param int $_exp 变更的经验值
     * @return mixed 操作结果
     * @author ifyn
     * @version 1.0.0
     * @since 2023
     */
    public static function exp_change($user_id, $_exp){
        if(empty($user_id)) {
            return false;
        }

        //获取当前用户 lv 等级经验
        $exp = get_user_meta($user_id, 'islide_lv_exp', true);
        $exp = $exp ? (int)$exp : 0;

        //经验增减
        $exp = (int)$_exp + $exp;

        if($exp < 0){
            return false;
        }

        //更新经验
        update_user_meta($user_id, 'islide_lv_exp', (int)$exp);
        
        return apply_filters('islide_change_exp', $exp, $user_id);
    }
    
    /**
     * 改变用户佣金
     *
     * @param int $user_id 用户ID
     * @param float $_money 佣金变化值
     * @return mixed 操作结果
     * @author ifyn
     */
    public static function commission_change($user_id, $_money){
        if(empty($user_id)) {
            return false;
        }

        $money = get_user_meta($user_id, 'islide_commission_money', true);
        $money = $money ? (float)$money : 0;

        //金额增减
        $money = bcadd((float)$_money, (float)$money, 2);

        if($money < 0) {
            return false;
        }

        update_user_meta($user_id, 'islide_commission_money', $money);

        return apply_filters('islide_change_commission_money', $money, $user_id);
    }
    
    /**
     * 获取用户自定义数据
     *
     * @param int $user_id 用户ID
     * @return array 用户自定义数据
     * @author ifyn
     * @version 1.0.0
     * @since 2023
     */
    public static function get_user_custom_data($user_id){
        if(empty($user_id)) {
            return array();
        }
        
        $custom_data = array();
        
        //积分计数
        $credit = self::get_credit($user_id);
        
        //rmb计数
        $money = self::get_money($user_id);
        
        $custom_data = array(
            'credit' => $credit,
            'money' => number_format($money, 2),
        );
        
        return $custom_data;
    }
    
    /**
     * 申请提现
     *
     * @param float $money 提现金额
     * @param string $type 提现类型(money或commission)
     * @return array 操作结果
     * @author ifyn
     */
    public static function cash_out($money, $type){
        $user_id = get_current_user_id();

        if(empty($user_id)) {
            return array('error' => '请先登录');
        }

        if(!in_array($type, array('money', 'commission'))) {
            return array('error' => '提现类型错误');
        }
        
        $open_key = $type. '_withdrawal_open';
        if(!islide_get_option($open_key)) {
            return array('error' => '禁止提现');
        }
        
        $qrcode = self::get_user_qrcode($user_id);
        if(empty($qrcode['weixin']) && empty($qrcode['alipay'])) {
            return array('error' => '请先设置提现至收款账户');
        }

        //检查提现金额
        if(!is_numeric($money)){
            return array('error' => '请输入数字提现金额');
        }

        $money = (float)$money;
        
        $withdrawal = islide_get_option($type. '_withdrawal');
        if($money < (float)$withdrawal['limit']){
            return array('error' => '提现金额太少，最低提现金额为 '.(float)$withdrawal['limit'].'元');
        }

        if($money > 99999 || $money <= 0) {
            return array('error' => '提现金额错误');
        }

        //检查余额是否充足
        if($type == 'money'){
            $my_money = get_user_meta($user_id, 'islide_money', true);
        } else {
            $my_money = get_user_meta($user_id, 'islide_commission_money', true);
        }
        
        $my_money = (float)$my_money;

        if($my_money < $money) {
            return array('error' => '余额不足，无法提现');
        }

        //计算手续费
        $ratio = !empty($withdrawal['ratio']) ? (int)$withdrawal['ratio'] / 100 : 0;
        $_money = bcmul($ratio, $money, 2);

        //真实提现金额
        $c_money = bcsub($money, $_money, 2);
       
        Record::update_data(array(
            'user_id' => $user_id,
            'record_type' => $type,
            'value' => -$money, //提现金额
            'type' => 'withdrawal',
            'type_text' => '申请提现',
            'content' => '手续费￥'.$_money.'，实际到账金额为￥'.$c_money,
            'status' => 0, //记录状态
            'record_key' => $c_money,
            'record_value' => $_money
        ));
        
        if($type == 'commission') {
            $message_data = array(
                'sender_id' => 0,
                'receiver_id' => $user_id,
                'title' => '佣金提现申请通知',
                'content' => '申请提现需后台人工处理，一个工作日内到账，请耐心等待',
                'type' => 'distribution',
                'mark' => array(
                    'meta' => array(
                        array(
                            'key'=> '申请金额',
                            'value'=> '￥'.$money,
                        ),
                        array(
                            'key'=> '手续费用',
                            'value'=> '￥'.$_money,
                        ),
                        array(
                            'key'=> '实际到账',
                            'value'=> '￥'.$c_money,
                        )
                    )
                )
            );
        } else {
            $message_data = array(
                'sender_id' => 0,
                'receiver_id' => $user_id,
                'title' => '申请提现',
                'content' => '您申请了提现，手续费￥'.$_money.'，实际到账金额为￥'.$c_money.'，一个工作日内到账，请耐心等待',
                'type' => 'wallet',
            );
        }
        
        Message::update_message($message_data);
        
        return array('success' => true, 'message' => '提现申请已提交');
    }
    
    /**
     * 获取VIP信息
     * 
     * @return array VIP信息数组
     * @author ifyn
     */
    public static function get_vip_info(){
        $user_id = get_current_user_id();
        
        $vip_data = (array)islide_get_option('user_vip_group');
        $vip_page = islide_get_option('islide_vip_page');
        $money = get_user_meta($user_id, 'islide_money', true);
        $money = $money ? (float)$money : 0;
        
        $data = array(
            'data'  => $vip_data,
            'vip_page' => $vip_page,
            'money' => $money,
            'user_data' => array(
                'avatar' => get_avatar_url($user_id, array('size' => 160)),
                'name'  => get_the_author_meta('display_name', $user_id),
                'vip'   => self::get_user_vip($user_id),
            )
        );
        
        return $data;
    }
    
    /**
     * 获取用户充值余额与积分设置信息
     * 
     * @return array 充值设置信息数组
     * @author ifyn
     */
    public static function get_recharge_info() {
        $user_id = get_current_user_id();
        
        if(empty($user_id)) {
            return array('error' => __('请先登录', 'islide'));
        }
        
        $balance_data = islide_get_option('pay_balance_group');
        $balance_custom_open = islide_get_option('pay_balance_custom_open');
        $balance_custom_limit = islide_get_option('pay_balance_custom_limit');
        
        $credit_data = islide_get_option('pay_credit_group');
        $credit_custom_open = islide_get_option('pay_credit_custom_open');
        $credit_custom_limit = islide_get_option('pay_credit_custom_limit');
        $ratio = islide_get_option('pay_credit_ratio');
        
        $credit = get_user_meta($user_id, 'islide_credit', true);
        $credit = $credit ? (int)$credit : 0;
        
        $money = get_user_meta($user_id, 'islide_money', true);
        $money = $money ? (float)$money : 0;

        return array(
            'credit' => (int)$credit,
            'money' => $money,
            'ratio' => $ratio,
            'credit_data' => $credit_data,
            'balance_data' => $balance_data,
            'credit_custom_limit' => $credit_custom_limit,
            'balance_custom_limit' => $balance_custom_limit,
            'balance_custom_open' => $balance_custom_open,
            'credit_custom_open' => $credit_custom_open
        );
    }
    
    /**
     * 获取用户设置信息
     * 
     * @return array 用户设置信息
     * @author ifyn
     */
    public static function get_user_settings() {
        $current_user = wp_get_current_user();
        
        if(empty($current_user) || empty($current_user->ID)) {
            return array('error' => '请先登录');
        }
        
        $user_data = $current_user;
        $userData = get_user_meta($user_data->ID, 'islide_user_custom_data', true);
        
        $data = array(
            'display_name' => $user_data->display_name,
            'sex' => isset($userData['gender']) ? $userData['gender'] : 0,
            'description' => get_user_meta($user_data->ID, 'description', true),
            'avatar' => get_avatar_url($user_data->ID, array('size' => 160))
        );

        return apply_filters('islide_get_user_settings', $data, $user_data->ID);
    }
    
    /**
     * 保存用户头像
     * 
     * @param string $url 头像URL
     * @param int $attachment_id 附件ID
     * @return array 操作结果
     * @author ifyn
     */
    public static function save_avatar($url, $attachment_id){
        if(empty($url) || empty($attachment_id)) {
            return array('error' => '参数不全');
        }
        
        $user_id = get_current_user_id();
        if(empty($user_id)) {
            return array('error' => '请先登录');
        }
        
        //删除原来头像
        $avatar_id = (int)get_user_meta($user_id, 'islide_user_avatar', true);
        if($avatar_id) {
            wp_delete_attachment($avatar_id, true);
        }
        
        // 检查附件是否存在
        $attachment_post = get_post($attachment_id);
        if(!$attachment_post) {
            return array('error' => '附件不存在');
        }
        
        // 更新用户头像 ID
        update_user_meta($user_id, 'islide_user_avatar', $attachment_id);
        
        $avatar = get_avatar_url($user_id, array('size' => 100));

        return array(
            'avatar' => $avatar,
            'msg' => '修改成功.'
        );
    }
    
    /**
     * 保存用户封面
     * 
     * @param string $url 封面URL
     * @param int $attachment_id 附件ID
     * @return array 操作结果
     * @author ifyn
     */
    public static function save_cover($url, $attachment_id) {
        if(empty($url) || empty($attachment_id)) {
            return array('error' => '参数不全');
        }
    
        $user_id = get_current_user_id();
        if(empty($user_id)) {
            return array('error' => '请先登录');
        }
    
        // 删除原来的封面
        $cover_id = (int)get_user_meta($user_id, 'islide_user_cover', true);
        if($cover_id) {
            wp_delete_attachment($cover_id, true);
        }
    
        // 检查附件是否存在
        $attachment_post = get_post($attachment_id);
        if(!$attachment_post) {
            return array('error' => '附件不存在');
        }
    
        // 更新用户封面
        update_user_meta($user_id, 'islide_user_cover', $attachment_id);
    
        // 获取封面 URL
        $cover = wp_get_attachment_url($attachment_id);
    
        return array(
            'cover' => $cover,
            'msg' => '修改成功.'
        );
    }
    
    /**
     * 保存用户信息
     *
     * @param array $request 请求参数
     * @return array 操作结果
     * @author ifyn
     */
    public static function save_user_info($request) {
        $user_id = get_current_user_id();
        
        // 判断用户是否登录
        if(empty($user_id)) {
            return array('error' => '无权限进行此项操作');
        }
    
        $nickname = isset($request['nickname']) && $request['nickname'] ? sanitize_text_field($request['nickname']) : '';
        $description = isset($request['description']) && $request['description'] ? wp_kses_post(sanitize_text_field($request['description'])) : '';
        $sex = isset($request['sex']) && is_numeric($request['sex']) ? intval($request['sex']) : null;
        
        if($nickname) {
            $check_nickname = Login::check_nickname($nickname);
            if(isset($check_nickname['error'])) {
                return $check_nickname;
            }
            
            //更新昵称
            $arr = array(
                'display_name' => $nickname,
                'ID' => $user_id
            );
            $res = wp_update_user($arr);
    
            if(is_wp_error($res)){
                return array('error' => $res->get_error_message());
            }
        }
        
        if($description) {
            //更新描述，移除双大括号防止模板注入
            update_user_meta($user_id, 'description', str_replace(array('{{', '}}'), '', sanitize_text_field($description)));
        }
        
        if($sex !== null) {
            if($sex !== 1 && $sex !== 0) {
                return array('error' => '性别错误');
            }
            
            $userData = get_user_meta($user_id, 'islide_user_custom_data', true);
            $userData = is_array($userData) ? $userData : array();
            $userData['gender'] = (int)$sex;
    
            update_user_meta($user_id, 'islide_user_custom_data', $userData);
        }
    
        return array('msg' => '保存成功.');
    }
    
    /**
     * 保存收款二维码
     * 
     * @param string $type 类型(alipay或weixin)
     * @param string $url 二维码URL
     * @return mixed 二维码缩略图或错误信息
     * @author ifyn
     */
    public static function save_qrcode($type, $url){
        $user_id = get_current_user_id();
        if(empty($user_id)) {
            return array('error' => '请先登录');
        }

        if(!in_array($type, array('alipay', 'weixin'))) {
            return array('error' => '参数错误');
        }
        
        $attachment_id = attachment_url_to_postid($url);
        if(empty($attachment_id)) {
            return array('error' => '二维码地址错误');
        }

        $qrcode = get_user_meta($user_id, 'islide_qcode', true);
        $qrcode = is_array($qrcode) ? $qrcode : array();

        $qrcode[$type] = esc_url($url);

        update_user_meta($user_id, 'islide_qcode', $qrcode);

        return islide_get_thumb(array(
            'url' => $url,
            'type' => 'fill',
            'width' => 120,
            'height' => '100%'
        ));
    }
    
    /**
     * 获取用户收款二维码
     * 
     * @param int $user_id 用户ID
     * @return array 二维码URL数组
     * @author ifyn
     */
    public static function get_user_qrcode($user_id){
        if(empty($user_id)) {
            return array('error' => '请先登录');
        }
        
        $qrcode = get_user_meta($user_id, 'islide_qcode', true);
        $qrcode = is_array($qrcode) ? $qrcode : array();
        
        return array(
            'weixin' => isset($qrcode['weixin']) ? islide_get_thumb(array(
                'url' => $qrcode['weixin'],
                'type' => 'fill',
                'width' => 120,
                'height' => '100%'
            )) : '',
            'alipay' => isset($qrcode['alipay']) ? islide_get_thumb(array(
                'url' => $qrcode['alipay'],
                'type' => 'fill',
                'width' => 120,
                'height' => '100%'
            )) : '',  
        );
    }
    
    /**
     * 修改当前用户密码
     * @param string $password 新密码
     * @param string $confirm_password 确认密码
     * @return string|array 返回成功或错误消息
     */
    public static function change_password( $password, $confirm_password ) {
        $user_id = get_current_user_id();
    
        // 判断用户是否登录
        if ( !$user_id ) {
            return array( 'error' => '无权限进行此项操作' );
        }
    
        // 判断两次输入的密码是否一致
        if ( $password !== $confirm_password ) {
            return array( 'error' => '两次密码输入不一致！' );
        }
    
        // 判断密码长度是否符合要求
        if ( strlen( $password ) < 6 ) {
            return array( 'error' => '密码必须大于或等于6位' );
        }
    
        // 更新用户密码
        wp_set_password( $password, $user_id );
    
        return array('msg'=>'密码修改成功.');
    }
    
    /**
     * 修改当前用户邮箱或手机号
     * @param array $data 包含新邮箱或手机号和验证码的数组
     * @return array 包含成功或错误消息的数组
     */
    public static function change_email_or_phone( $data ) {
        $user_id = get_current_user_id();
    
        // 判断用户是否登录
        if (!$user_id) {
            return array('error' => '无权限进行此项操作' );
        }
    
        // 检查操作类型是否合法
        if (!isset($data['type']) || !in_array($data['type'], array('email', 'phone'))) {
            return array('error' => '非法操作');
        }
        
        // 检查新邮箱或手机号格式是否正确
        if ($data['type'] == 'email' && ! is_email( $data['username'])) {
            return array('error' => '请输入正确的邮箱格式。');
        }elseif ($data['type'] == 'phone' && ! Login::is_phone($data['username'])) {
            return array('error' => '请输入正确的手机号格式。');
        }
    
        // 检查新邮箱或手机号是否已被注册
        if ($data['type'] == 'email' && email_exists($data['username'])) {
            return array( 'error'=> '该邮箱已经被注册，请使用其他邮箱。');
        } elseif ($data['type'] == 'phone' && username_exists( $data['username'])) {
            return array( 'error'=> '该手机号已经被注册，请使用其他手机号。');
        }
        
        // 检查验证码
        $check = Login::code_check($data);
        if (isset($check['error'])) {
            return $check;
        }
    
        // 更新用户邮箱或手机号
        $update_data = array(
            'ID' => $user_id,
        );
        
        if ($data['type'] == 'email' && !is_email( $data['username'])) {
            $update_data['user_email'] = sanitize_email($data['username']);
            $result = wp_update_user( $update_data );
        } else if(Login::is_phone($data['username'])) {
            global $wpdb;
            $result = $wpdb->update($wpdb->users,array('user_login' => esc_sql($data['username'])),$update_data);
            wp_cache_delete('user_'.$user_id,'users');
            
            //清除用户缓存
            clean_user_cache($user_id);
            
            Login::login_user($user_id);
        }

        if (is_wp_error($result)) {
            return array('error'=> '更新失败，请稍后再试。');
        }
    
        // 返回成功响应
        return array('msg'=>'更新成功.');
    }
    
    /**
     * 获取当前用户的账户安全信息
     *
     * @return array 包含账户安全分数、安全状态和完善提示的数组
     */
    public static function get_account_security_info() {
        // 获取当前用户信息
        $current_user = wp_get_current_user();
    
        // 初始化账户安全分数和完善提示数组
        $score = 100;
        $suggest = [];
    
        // 检查用户是否绑定了邮箱
        if (empty($current_user->user_email)) {
            $score -= 15;
            $suggest[] = '绑定邮箱';
        }
    
        // 检查用户是否绑定了手机号码
        if (empty($current_user->user_login) || !Login::is_phone($current_user->user_login)) {
            $score -= 15;
            $suggest[] = '绑定手机';
        }
    
        // 检查用户是否设置了密码
        if (empty($current_user->user_pass)) {
            $score -= 15;
            $suggest[] = '设置密码';
        }
    
        // 根据账户安全分数计算用户的安全状态
        if ($score == 100) {
            $status = '安全';
        } elseif ($score >= 70) {
            $status = '低风险';
        } else {
            $status = '高风险';
        }
    
        // 根据账户安全分数和完善提示数组计算用户的完善提示
        if ($score == 100) {
            $suggest_str = '无需完善任何信息';
        } else {
            $suggest_str = implode('、', $suggest);
        }
    
        // 返回包含账户安全分数、安全状态和完善提示的数组
        return array(
            'score' => $score,
            'status' => $status,
            'suggest' => $suggest_str
        );
    }
    
    /**
     * 获取当前用户等级成长信息
     *
     * @return array 
     */
    public static function get_user_lv_info() {
        $user_id = get_current_user_id();
        
        if(!$user_id) return array('error'=>'请先登录.');
        
        return array(
            'lv' => self::get_user_lv($user_id),
            'lv_group' => islide_get_option('user_lv_group')
        );
    }

    /**
     * 获取用户的文章列表
     *
     * @param int $paged 页数
     * @param int $size 数量
     * @param string $post_type 收藏文章类型
     * 
     * @return array 
     * @author 青青草原上
     * @version 1.0.4
     * @since 2023
     */
    
    public static function get_user_posts($paged,$size,$post_type,$post_status = ''){
        
        $user_id = get_current_user_id();
        $paged = (int)$paged;
        $size = (int)$size;
        $types = islide_get_post_types();
        $post_status = $post_status?:array('publish','pending','draft','trash');

        if(!$user_id) return array('error' => '请先登录');
        
        if(!isset($types[$post_type])) return array('error' => '请求类型错误');
        
        if ($size > 20 || $size < 1) {
            return array('error' => '请求数量错误');
        }
        
        if ($paged < 1) {
            return array('error' => '请求格式错误');
        }
        
        $args = array(
            'post_type' => $post_type,
            'posts_per_page' => $size,
            'orderby' => 'date',
            'offset' => ($paged - 1) * $size,
            'post_status' => $post_status,
            'ignore_sticky_posts' => true
        );
    
        $args['author'] = $user_id;
    
        $the_query = new \WP_Query($args);
    
        $post_data = array();
        $_pages = 1;
        $_count = 0;
    
        if ($the_query->have_posts()) {
            $_pages = $the_query->max_num_pages;
            $_count = $the_query->found_posts;
    
            while ($the_query->have_posts()) {
                $the_query->the_post();
                
                $post_id = get_the_ID();
                $date = get_the_time('Y-m-d H:i:s');
                $status = get_post_status();
                
                $thumb_url = Post::get_post_thumb($post_id,true);
                $thumb = '';
                if($thumb_url){
                    $thumb = islide_get_thumb(array('url'=>$thumb_url,'width'=>166,'height'=>106,'ratio'=>1));
                }
                
                $post_data[] = array(
                    'id'=>$post_id,
                    'title'=> get_the_title(),
                    'link'=> get_permalink(),
                    'thumb'=> $thumb,
                    'date' => islide_time_ago($date,true),
                    'type' => get_post_type(),
                    'post_status' => $status,
                    'status' => islide_get_post_status_name($status),
                    'post_meta' => array(
                        'collect' => Post::get_post_favorites($post_id)['count'],
                        'like' => Post::get_post_vote($post_id)['like'],
                        'comment' => islide_number_format(get_comments_number($post_id)),
                        'views' => (int)get_post_meta($post_id,'views',true)
                    )
                );
            }
    
            wp_reset_postdata();
        }
    
        return array(
            'data' => $post_data,
            'pages' => $_pages,
            'count' => $_count
        );
    } 
    
    /**
     * 获取用户动态列表
     *
     * @param int $user_id 用户ID
     * @param int $paged 当前页码
     * @param int $size 每页数量
     * @return array 包含动态数据的数组或错误信息
     * @author ifyn
     */
    public static function get_user_dynamic_list($user_id, $paged, $size) {
        $user_id = (int)$user_id;
        $paged = (int)$paged;
        $size = (int)$size;
    
        if ($size > 20 || $size < 1) {
            return array('error' => '请求数量错误');
        }
        if ($paged < 1) {
            return array('error' => '请求格式错误');
        }
    
        $args = array(
            'post_type' => 'circle',
            'posts_per_page' => $size,
            'orderby' => 'date',
            'offset' => ($paged - 1) * $size,
            'post_status' => 'publish',
            'ignore_sticky_posts' => true
        );
    
        $args['author'] = $user_id;
    
        $the_query = new \WP_Query($args);
    
        $post_data = array();
        $_pages = 1;
        $_count = 0;
    
        if ($the_query->have_posts()) {
            $_pages = $the_query->max_num_pages;
            $_count = $the_query->found_posts;
    
            while ($the_query->have_posts()) {
                $the_query->the_post();
                $post_data[] = self::get_dynamic_item($the_query->post);
            }
    
            wp_reset_postdata();
        }
    
        return array(
            'data' => $post_data,
            'pages' => $_pages,
            'count' => $_count
        );
    }
    
    /**
     * 获取单条动态详细信息
     * 
     * @param object $post WordPress文章对象
     * @return array 格式化后的动态数据
     * @author ifyn
     */
    public static function get_dynamic_item($post){
        if(empty($post) || !is_object($post)) {
            return array();
        }
        
        $post_id = $post->ID;
        $post_author = $post->post_author;
        
        $post_content = $post->post_content;
        $post_type = $post->post_type;
        
        $thumb_url = Post::get_post_thumb($post_id);
        
        // 获取文章中所有图片
        $images = islide_get_first_img($post_content, 'all');
        
        $thumb = '';
        if($thumb_url){
            $thumb = islide_get_thumb(array('url' => $thumb_url, 'width' => 240, 'height' => 320, 'ratio' => 1));
        }
        
        return array(
            'id' => $post_id,
            'title' => $post->post_title,
            'link' => get_permalink($post_id),
            'thumb' => $thumb,
            'images' => $images,
            'desc' => islide_get_desc(0, 120, $post_content),
            'date' => Post::time_ago($post->post_date, true),
            'type' => $post_type,
            'user_data' => self::get_user_public_data($post_author),
            'post_meta' => array(
                'collect' => Post::get_post_favorites($post_id)['count'],
                'like' => Post::get_post_vote($post_id)['like'],
                'comment' => islide_number_format(get_comments_number($post_id)),
            )
        );
    }
    
    /**
     * 获取用户社交登录信息
     * 
     * @param int $user_id 用户ID
     * @return array 用户的社交登录信息
     * @author ifyn
     */
    public static function get_user_oauth_info($user_id){
        if(empty($user_id)) {
            return array();
        }
        
        $oauths = Oauth::get_enabled_oauths();
        if(empty($oauths) || !is_array($oauths)) {
            return array();
        }
        
        $data = array();
        $oauth_data = get_user_meta($user_id, 'islide_oauth', true);
        $oauth_data = is_array($oauth_data) ? $oauth_data : array();

        foreach($oauths as $key => $value) {
            $openid = get_user_meta($user_id, 'islide_oauth_'.$key.'_openid', true);

            $data[$key] = array(
                'is_binding' => !empty($openid),
                'avatar' => isset($oauth_data[$key]['avatar']) ? $oauth_data[$key]['avatar'] : '',
                'name' => isset($value['name']) ? $value['name'] : '',
                'user_name' => isset($oauth_data[$key]['nickname']) ? esc_attr($oauth_data[$key]['nickname']) : '',
                'type' => isset($value['type']) ? $value['type'] : '',
            );
        }
        
        return $data;
    }
    
    /**
     * 获取用户安全相关信息
     * 
     * @return array 用户安全信息或错误信息
     * @author ifyn
     */
    public static function get_secure_info(){
        $user_id = get_current_user_id();
        if(empty($user_id)) {
            return array('error' => '您没有权限进行此操作');
        }
        
        $user_data = get_userdata($user_id);
        $security_info = self::get_account_security_info();
        $oauth = self::get_user_oauth_info($user_id);
        
        $data = array(
            'user_data' => $user_data,
            'security_info' => $security_info,
            'oauth' => $oauth
        );
        
        return $data;
    }
    
    /**
     * 解除社交账号绑定
     * 
     * @param string $type 社交账号类型
     * @return array|bool 操作结果，成功返回true，失败返回错误信息
     * @author ifyn
     */
    public static function un_binding($type) {
        $user_id = get_current_user_id();

        if(empty($user_id)) {
            return array('error' => '您没有权限进行此操作');
        }
        
        if(empty($type)) {
            return array('error' => '参数错误');
        }
        
        $oauth_data = get_user_meta($user_id, 'islide_oauth', true);
        $oauth_data = is_array($oauth_data) ? $oauth_data : array();
        
        if(isset($oauth_data[$type])) {
            unset($oauth_data[$type]);
            update_user_meta($user_id, 'islide_oauth', $oauth_data);
            delete_user_meta($user_id, 'islide_oauth_'.$type.'_openid');
            return true;
        }
        
        return array('error' => '未知错误，请重试');
    }
    
    /**
     * 用户搜索功能
     * 
     * @param string $key 搜索关键词
     * @return array 匹配的用户数组或错误信息
     * @author ifyn
     */
    public static function search_users($key){
        $user_id = get_current_user_id();

        if(empty($user_id)) {
            return array('error' => '您没有权限进行此项操作');
        }

        if(empty($key)) {
            return array('error' => '用户名不可为空');
        }

        $user_query = new \WP_User_Query(array(
            'search' => '*'.esc_sql($key).'*',
            'search_columns' => array(
                'display_name',
            ),
            'number' => 20,
            'paged' => 1
        ));

        $results = $user_query->get_results();
        if(empty($results)) {
            return array('error' => '未找到相关用户');
        }

        $users = array();
        foreach($results as $user) {
            $user_data = self::get_user_public_data($user->ID);
            if(!empty($user_data)) {
                $users[] = $user_data;
            }
        }
    
        if(!empty($users)) {
            return $users;
        }

        return array('error' => '未找到相关用户');
    }
    
}