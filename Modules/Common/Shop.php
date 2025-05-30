<?php
/**
 * 店铺功能管理类
 * 
 * 处理商品、用户数据、评论等相关功能
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;
use islide\Modules\Common\User;
use islide\Modules\Common\IpLocation;
use islide\Modules\Common\UserAgent;

class Shop{
    
    /**
     * 初始化函数，注册钩子
     *
     * @author  ifyn
     * @return  void
     */
    public function init(){
       
    }
    
    /**
     * 获取商品数据
     *
     * @author  ifyn
     * @param   int $post_id 商品文章ID
     * @return  array        商品数据数组
     */
    public static function get_shop_data($post_id) {
        $meta = get_post_meta($post_id, 'single_shop_metabox', true);
        if (!is_array($meta)) {
            $meta = array();
        }
        
        $is_multi = isset($meta['is_shop_multi']) ? $meta['is_shop_multi'] : '0';

        // ✅ 图库处理
        $gallery = [];
        if (!empty($meta['shop_gallery'])) {
            $ids = explode(',', $meta['shop_gallery']);
            foreach ($ids as $id) {
                $gallery[] = islide_get_thumb([
                    'url' => wp_get_attachment_url(trim($id)),
                    'width' => 380,
                    'height' => 380,
                    'ratio' => 2
                ]);
            }
        }

        // ✅ 服务保障
        $guarantees = isset($meta['shop_guarantees']['enabled']) ? array_values($meta['shop_guarantees']['enabled']) : [];

        // ✅ 属性规格组（用于按钮渲染）
        $spec_groups = isset($meta['spec_groups']) ? $meta['spec_groups'] : [];
        
        $shop_attr = isset($meta['shop_attr']) ? $meta['shop_attr'] : '';

        // ✅ 返回体基础
        $data = [
            'type' => $is_multi === '1' ? 'multi' : 'single',
            'roles' => isset($meta['islide_shop_roles']) ? $meta['islide_shop_roles'] : [],
            'limit' => (int)(isset($meta['islide_single_limit']) ? $meta['islide_single_limit'] : 0),
            'sold' => (int)(isset($meta['islide_shop_count']) ? $meta['islide_shop_count'] : 0),
            'gallery' => $gallery,
            'guarantees' => $guarantees,
            'spec_groups' => $spec_groups,
            'attr' => $shop_attr,
            'commodity' => isset($meta['islide_shop_type']) ? $meta['islide_shop_type'] : ''
        ];

        if ($is_multi === '1') {
            $specs = [];
            $min = null;
            $max = null;
            $lowest_discount = 100;

            if (isset($meta['specifications']) && is_array($meta['specifications'])) {
                foreach ($meta['specifications'] as $spec) {
                    if (!empty($spec['name']) && $spec['name'] !== '批量设置') {
                        $price = floatval($spec['price']);
                        $discount = floatval(isset($spec['discount']) ? $spec['discount'] : 100);
                        $real_price = $discount > 0 ? $price * $discount / 100 : $price;

                        $specs[] = [
                            'name' => $spec['name'],
                            'price' => $real_price,
                            'oldPrice' => $price,
                            'stock' => (int)$spec['stock'],
                            'sold' => (int)$spec['sold'],
                            'limit' => (int)$spec['limit'],
                            'discount' => (int)$discount
                        ];

                        if ($min === null || $real_price < $min) {
                            $min = $real_price;
                            $lowest_discount = $discount;
                        }
                        if ($max === null || $real_price > $max) $max = $real_price;
                    }
                }
            }

            $data['specs'] = $specs;
            $data['price'] = $min ?? 0;
            $data['oldPrice'] = ($min && $lowest_discount > 0) ? round($min / ($lowest_discount / 100), 2) : 0;
            $data['priceRange'] = [$min ?? 0, $max ?? 0];

        } else {
            $price = floatval(isset($meta['islide_shop_price']) ? $meta['islide_shop_price'] : 0);
            $discount = floatval(isset($meta['islide_single_discount']) ? $meta['islide_single_discount'] : 100);
            $real_price = $discount > 0 ? $price * $discount / 100 : $price;

            $data['price'] = round($real_price, 2);
            $data['oldPrice'] = round($price, 2);
            $data['priceRange'] = [$real_price, $real_price];
            $data['stock'] = (int)(isset($meta['islide_shop_stock']) ? $meta['islide_shop_stock'] : 0);
            $data['discount'] = (int)$discount;
        }

        return $data;
    }
    
    /**
     * 获取登录协议信息
     *
     * @author  ifyn
     * @return  array 登录协议信息
     */
    public static function get_login_agreement(){
        $register_check = islide_get_option('allow_register_check');
        $check_type = $register_check ? islide_get_option('register_check_type') : '';
        
        $login_text = '手机号或邮箱';

        switch ($check_type) {
            case 'tel':
                $login_text = '手机号';
                break;
            case 'email':
                $login_text = '邮箱';
                break;
            case 'telandemail':
                $login_text = '手机号或邮箱';
                break;
            default:
                $login_text = '用户名';
                break;
        }
        
        //用户协议与隐私政策
        $agreement = islide_get_option('agreement');
        $agreement = $agreement ? $agreement : array();
        return array(
        'login_type' =>   $login_text,
        'agreement'=>$agreement
        );
        
    }
    
    
    /**
     * 获取用户公共数据
     *
     * @author  ifyn
     * @return  array 用户公共数据
     */
    public static function get_user_public_data(){
        $user_id = get_current_user_id();
        if(!$user_id){
           // return array('error'=>'请先登录！');
           $user_id = 1;
           $isLogin = false;
        }else {
           $isLogin = true;
        }
        
        $credit = get_user_meta($user_id,'islide_credit',true);
        $credit = $credit ? (int)$credit : 0;
        
        $money = get_user_meta($user_id,'islide_money',true);
        $money = $money ? $money : 0;
        
        $data = get_userdata($user_id);
        
        //头像
        $avatar = get_avatar_url($user_id,array('size'=>160));
        
        $pendant = '';//'https://upload-bbs.mihoyo.com/upload/2023/07/30/f2ca181eb7d74b212fb9d9c17d340fbe_7860532445602988547.png';
        $vip = User::get_user_vip($user_id);
        
        $badge = isset($vip['icon']) && !empty($vip['icon']) ? $vip['icon'] : '';
        
        $verify = User::get_user_verify($user_id);
        
        $followers_count = User::get_user_followers_stats_count($user_id); //获取关注数计数
        $stats_count = User::get_user_stats_count($user_id); //获取统计计数

        $badge = get_relative_upload_path(isset($verify['icon']) && !empty($verify['icon']) ? $verify['icon'] : $badge);
        
        //ip属地
        $ip_location = get_user_meta( $user_id, 'islide_login_ip_location',true);  
        
        $data = array(
            'isLogin' => $isLogin,
            'id'     => $user_id,
            'name'   => isset($data->display_name) ? esc_attr($data->display_name) : '',
            'avatar' => $avatar,
            'desc'   => isset($data->description) && !empty($data->description)? esc_attr($data->description) : islide_get_option('user_desc'),
            'pendant'=> $pendant,
            'badge'  => $badge,
            'lv'     => User::get_user_lv($user_id),
            'vip'    => $vip,
            'verify' => $verify,
            'credit' => $credit,
            'money' =>$money,
            'admin' => user_can($user_id, 'administrator' ),
            'cover'=>apply_filters('islide_get_user_cover_url', $user_id),
            'commission' => \islide\Modules\Common\Distribution::get_user_commission($user_id),
            'followers_count'=>$followers_count,
            'stats_count'=>$stats_count,
            'ip' => IpLocation::build_location($ip_location)
        );
        return $data;
    }
    
    /**
     * 获取用户文章统计数据
     *
     * @author  ifyn
     * @return  array|array 用户文章统计数据或错误信息
     */
    public static function get_user_posts_stats() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return array('error' => '请先登录！');
        }
        
        global $wpdb;

        // 查询用户文章的评论总数
        $total_comments = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(comment_ID)
            FROM {$wpdb->comments}
            INNER JOIN {$wpdb->posts} ON {$wpdb->comments}.comment_post_ID = {$wpdb->posts}.ID
            WHERE {$wpdb->posts}.post_author = %d
            AND {$wpdb->comments}.comment_approved = '1'
        ", $user_id));

        // 构建统计数据
        $stats = array(
            'total_views' => array(
                'name' => '阅读',
                'count' => User::get_user_posts_meta_sum($user_id, 'views')
            ),
            'total_likes' => array(
                'name' => '点赞',
                'count' => User::get_user_posts_meta_sum($user_id, 'islide_post_like'),
            ),
            'total_comments' => array(
                'name' => '评论',
                'count' => (int)$total_comments,
            ),
            'total_favorites' => array(
                'name' => '收藏',
                'count' => User::get_user_posts_meta_sum($user_id, 'islide_post_favorites'),
            ),
            'total_shares' => array(
                'name' => '分享',
                'count' => 0
            ),
        );
    
        return $stats;
    }
    
    /**
     * 发送评论
     *
     * @author  ifyn
     * @param   array  $args       评论数据
     * @param   string $user_agent 用户代理字符串
     * @return  array              评论结果或错误信息
     */
    public static function send_comment($args, $user_agent = false) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return array('error' => '请先登录之后再参与讨论');
        }
        
        // 移除不需要的字段
        if (isset($args['_wp_unfiltered_html_comment'])) {
            unset($args['_wp_unfiltered_html_comment']);
        }
        
        // 检查评论长度
        if (!isset($args['comment']) || strlen($args['comment']) < 2) {
            return array('error' => '评论字数过少！');
        }
        
        // 删除标签，防止XSS攻击
        $args['comment'] = strip_tags($args['comment']);

        // 提交评论
        $comment = wp_handle_comment_submission(wp_unslash($args));
        
        // 保存用户代理信息
        if ($comment && !is_wp_error($comment) && $user_agent) {
            $browser = UserAgent::get_browsers($user_agent);
            $os = UserAgent::get_os($user_agent);
            $user_data = array(
                'browser' => $browser,
                'os'      => $os,
            );
            update_comment_meta($comment->comment_ID, 'user_agent', json_encode($user_data));
        }

        if (is_wp_error($comment)) {
            return array('error' => $comment->get_error_messages());
        }
        
        $post_id = isset($args['comment_post_ID']) ? (int)$args['comment_post_ID'] : 0;
        $comments_data = self::get_comments_data($comment);
        
        if ($comments_data) {
            $comments_data['children'] = [];
            return array('data' => $comments_data);
        } else {
            return array('data' => []);
        }
    }

    /**
     * 获取评论数据
     *
     * @author  ifyn
     * @param   object $comment 评论对象
     * @return  array           格式化后的评论数据
     */
    public static function get_comments_data($comment) {
        if (!$comment || !isset($comment->comment_ID)) {
            return null;
        }
            
        $children = array();
        
        $top_comment_id = self::get_top_comment_id($comment);
        
        if ($top_comment_id == 0) {
            $children = self::get_flat_children($comment->comment_ID);
        }
        
        // 获取评论作者信息
        $author = User::get_user_public_data($comment->user_id);
        
        // 获取IP属地信息
        $comment_ip_location = get_comment_meta($comment->comment_ID, 'islide_comment_ip_location', true);
        $ip_location = IpLocation::build_location($comment_ip_location);
        
        // 处理用户代理信息
        $user_agent = get_comment_meta($comment->comment_ID, 'user_agent', true);
        $user_os = null;
        $user_browser = null;
        
        // 检查是否有数据
        if (!empty($user_agent)) {
            // 将 JSON 字符串解析为对象
            $user_agent_data = json_decode($user_agent);
        
            if ($user_agent_data && !is_wp_error($user_agent_data)) {
                // 访问对象中的属性
                $user_os = isset($user_agent_data->os->title) ? $user_agent_data->os->title : 'Unknown OS';
                $user_browser = isset($user_agent_data->browser->title) ? $user_agent_data->browser->title : 'Unknown Browser';
            }
        }
        
        // 获取父评论信息
        $comment_parent = $comment->comment_parent ? get_comment($comment->comment_parent) : null;
        $parent_user = null;
        
        if ($comment_parent && !is_wp_error($comment_parent)) {
            $parent_user_data = get_userdata($comment_parent->user_id);
            if ($parent_user_data) {
                $parent_user = [
                    'name' => trim(esc_attr($parent_user_data->display_name)),
                    'comment_id' => $comment->comment_parent,
                ];
            }
        }
        
        // 构建评论数据
        $comments = [
            'id'       => (int)$comment->comment_ID,
            'parent'   => $top_comment_id == 0 ? (int)$comment->comment_ID : (int)$comment->comment_parent,
            'parent_user' => $parent_user,
            'top_comment_id' => $top_comment_id == 0 ? (int)$comment->comment_ID : (int)$top_comment_id,
            'author'   => $author,
            'content'  => apply_filters('islide_comment_filters', $comment->comment_content),
            'date'     => get_comment_date('', $comment),
            'datetime' => get_comment_date('c', $comment),
            'children' => $children,
            'sticky'   => filter_var(get_comment_meta($comment->comment_ID, 'islide_comment_sticky', true), FILTER_VALIDATE_BOOLEAN),
            'is_self'  => (get_current_user_id() && get_current_user_id() == $comment->user_id),
            'user_os' => $user_os,
            'user_browser' => $user_browser,
            'ip' => $ip_location,
            'likes' => (int)get_comment_meta($comment->comment_ID, 'islide_comment_like', true),
            'votes' => self::get_comment_vote($comment->comment_ID),
        ];
        
        return $comments;
    }
    
    /**
     * 获取文章评论的平铺树结构
     *
     * @author  ifyn
     * @param   array $data 请求数据，包含post_id、paged、size等参数
     * @return  array       评论列表数据或错误信息
     */
    public static function get_post_comments_flat_tree($data) {
        $post_id = (int)$data['post_id'];
        $paged = isset($data['paged']) ? max(1, intval($data['paged'])) : 1;
        $per_page = isset($data['size']) ? max(1, intval($data['size'])) : 10;
        
        if (!get_post($post_id)) {
            return ['error' => '文章不存在'];
        }
    
        $args = [
            'post_id' => $post_id,
            'status'  => 'approve',
            'orderby' => 'comment_date_gmt',
            'order'   => 'DESC',
            'number'  => $per_page,
            'offset'  => ($paged - 1) * $per_page,
            'parent'  => 0,
        ];
        
        // 处理排序参数
        if (isset($data['orderby']) && $data['orderby']) {
            if (in_array($data['orderby'], array('islide_comment_like'))) {
                $args['orderby']  = 'meta_value_num';
                $args['meta_key'] = $data['orderby'];
            } else {
                $args['order'] = $data['orderby'];
            }
        }
        
        // 筛选作者评论
        if (!empty($data['author']) && $data['author'] === true) {
            $author = get_post_field('post_author', $post_id);
            if ($author) {
                $args['author__in'] = [(int)$author];
            }
        }
    
        // 置顶评论查询
        $sticky_query = new \WP_Comment_Query();
        $sticky_args = array_merge($args, [
            'meta_key'   => 'islide_comment_sticky',
            'meta_value' => '1',
        ]);
        $sticky_comments = $sticky_query->query($sticky_args);
        
        // 用 ID 记录置顶的，避免重复
        $sticky_ids = wp_list_pluck($sticky_comments, 'comment_ID');
        
        $remaining = $per_page - count($sticky_comments);
        $non_sticky_comments = [];
        
        // 查询非置顶评论
        if ($remaining > 0) {
            $non_sticky_query = new \WP_Comment_Query();
            $non_sticky_args = array_merge($args, [
                'meta_query' => [
                    [
                        'key'     => 'islide_comment_sticky',
                        'compare' => 'NOT EXISTS',
                    ],
                ],
                'offset' => ($paged - 1) * $per_page - count($sticky_comments),
                'number' => $remaining,
            ]);
            $non_sticky_comments = $non_sticky_query->query($non_sticky_args);
        }
        
        $top_level_comments = array_merge($sticky_comments, $non_sticky_comments);
    
        // 手动查询 parent = 0 的总数
        global $wpdb;
        $total_top = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->comments}
            WHERE comment_post_ID = %d
            AND comment_approved = '1'
            AND comment_parent = 0
        ", $post_id));
    
        $comments = [];
    
        foreach ($top_level_comments as $comment) {
            $comment_data = self::get_comments_data($comment);
            if ($comment_data) {
                $comments[] = $comment_data;
            }
        }
    
        return [
            'data'  => $comments,
            'total' => intval($total_top),
            'pages' => ceil($total_top / $per_page),
            'paged' => $paged,
        ];
    }
    
    /**
     * 获取所有子评论的平铺结构（非递归嵌套）
     *
     * @author  ifyn
     * @param   int $parent_id 父评论ID
     * @return  array          子评论数组
     */
    public static function get_flat_children($parent_id) {
        if (!$parent_id) {
            return [];
        }
        
        $all_children = get_comments([
            'parent' => $parent_id,
            'status' => 'approve',
            'order'  => 'ASC',
        ]);
    
        $flat = [];
    
        foreach ($all_children as $child) {
            $child_data = self::get_comments_data($child);
            if ($child_data) {
                $flat[] = $child_data;

                // 查找该子评论的子评论，继续平铺
                $sub_children = self::get_flat_children($child->comment_ID);
                if (!empty($sub_children)) {
                    $flat = array_merge($flat, $sub_children);
                }
            }
        }
    
        return $flat;
    }
    
    /**
     * 获取顶级评论ID
     *
     * @author  ifyn
     * @param   object $comment 评论对象
     * @return  int             顶级评论ID
     */
    public static function get_top_comment_id($comment) {
        if (!$comment || !isset($comment->comment_ID)) {
            return 0;
        }
        
        $top_comment_id = $comment->comment_parent;
        
        // 如果没有父评论，返回0表示自己就是顶级评论
        if (!$top_comment_id) {
            return 0;
        }
        
        // 循环查找顶级父评论
        $parent_comment = $comment;
        while ($parent_comment->comment_parent) {
            $parent_comment = get_comment($parent_comment->comment_parent);
            if (!$parent_comment || is_wp_error($parent_comment)) {
                break;
            }
            $top_comment_id = $parent_comment->comment_ID;
        }
        
        return $top_comment_id;
    }
    
    /**
     * 获取评论投票状态
     *
     * @author  ifyn
     * @param   int $comment_id 评论ID
     * @return  bool            当前用户是否已点赞该评论
     */
    public static function get_comment_vote($comment_id) {
        if (!$comment_id) {
            return false;
        }
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }
        
        $comment_likes = get_user_meta($user_id, 'islide_comment_likes', true);
        $comment_likes = is_array($comment_likes) ? $comment_likes : array();
        
        return in_array($comment_id, $comment_likes);
    }
    
    /**
     * 删除评论
     *
     * @author  ifyn
     * @param   WP_REST_Request $request 请求对象
     * @return  array                    删除结果
     */
    public static function delete_comment($request) {
        $comment_id = $request->get_param('id') ?? 0;
        // 获取当前用户ID
        $current_user_id = get_current_user_id();
    
        if (!$current_user_id) {
            return ['error' => '请先登录'];
        }
    
        // 获取评论对象
        $comment = get_comment($comment_id);
        if (!$comment) {
            return ['error' => '评论不存在'];
        }
    
        // 检查是否有权限删除：自己发的评论 或 管理员
        if ((int) $comment->user_id !== $current_user_id && !current_user_can('administrator')) {
            return ['error' => '您无权删除此评论'];
        }
    
        // 删除评论（第二个参数 true 表示彻底删除而非移动到垃圾箱）
        $deleted = wp_delete_comment($comment_id, true);
    
        if (!$deleted) {
            return ['error' => '删除失败，请稍后重试'];
        }
    
        return ['success' => true, 'message' => '评论已删除'];
    }
    
    /**
     * 切换评论置顶状态
     *
     * @author  ifyn
     * @param   WP_REST_Request $request 请求对象
     * @return  array                    操作结果
     */
    public static function islide_toggle_comment_sticky($request) {
        $comment_id = $request->get_param('id') ?? 0;
        $comment = get_comment($comment_id);
        if (!$comment) {
            return ['error' => '评论不存在'];
        }
    
        // 权限校验：当前用户是管理员或评论作者
        if (!current_user_can('moderate_comments') && get_current_user_id() !== intval($comment->user_id)) {
            return ['error' => '无权限操作'];
        }
    
        // 获取当前是否置顶
        $is_sticky = get_comment_meta($comment_id, 'islide_comment_sticky', true);
    
        if ($is_sticky) {
            delete_comment_meta($comment_id, 'islide_comment_sticky');
            return ['success' => true, 'message' => '已取消置顶'];
        } else {
            update_comment_meta($comment_id, 'islide_comment_sticky', 1);
            return ['success' => true, 'message' => '评论已置顶'];
        }
    }
}