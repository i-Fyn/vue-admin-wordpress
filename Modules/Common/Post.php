<?php
/**
 * 文章功能管理类
 * 
 * 处理文章的获取、发布、更新、删除以及相关元数据操作
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;

use islide\Modules\Common\User;
use islide\Modules\Common\Circle;
// use \Firebase\JWT\JWT;
use islide\Modules\Templates\Single;
use islide\Modules\Common\IpLocation;
use islide\Modules\Common\ShortCode;
use islide\Modules\Common\FileUpload;
use islide\Modules\Common\Seo;
use islide\Modules\Common\Shop;

/**
 * 文章处理类
 */
class Post
{
    /**
     * 初始化函数，注册钩子
     *
     * @author  ifyn
     * @return  void
     */
    public function init()
    {
        // 隐藏主题自带的自定义字段
        // add_filter('post_gallery', array($this,'post_gallery'), 10, 2);
        // add_action('save_post', array(__CLASS__,'save_post_qrcode'), 10, 3);
        // add_filter('post_link', array(__CLASS__,'post_link'), 9, 3);
        // add_action('transition_post_status', array($this,'publish_post'), 999, 3);
        // add_filter('wp_insert_post_data', array($this,'insert_post_data'), 10, 2);

        // 注册下载数据过滤器
        add_filter('filter_download_data', array($this, 'filter_download_data'), 10, 2);
    }

    /**
     * 获取文章meta内容
     *
     * @author  ifyn
     * @param   int $post_id 文章ID，默认为当前文章
     * @return  array        文章meta内容
     */
    public static function get_post_meta($post_id = 0)
    {
        // 如果未提供文章ID，则获取当前文章ID
        if (!$post_id) {
            global $post;
            if (!isset($post->ID))
                return [];
            $post_id = $post->ID;
        }

        // 获取文章作者ID
        $user_id = get_post_field('post_author', $post_id);
        $user_data = get_userdata($user_id);

        $name = isset($user_data->display_name) ? esc_attr($user_data->display_name) : '';
        $avatar = get_avatar_url($user_id, array('size'=>160));

        // 获取浏览量
        $view = (int) get_post_meta($post_id, 'views', true);
        $like = (int) get_post_meta($post_id, 'islide_post_like', true);
        $comment = (int) get_post_meta($post_id, 'islide_post_comment', true);

        // 获取发布日期
        $date = get_the_date('Y-m-d H:i:s', $post_id);

        $type = get_post_type($post_id);

        //更新浏览量
        update_post_meta($post_id, 'views', $view + 1);

        // 构建返回数据
        return array(
            'id' => $post_id,
            'date' => self::time_ago($date,true),
            'desc' => islide_get_desc($post_id, 50),
            'thumb' => islide_get_thumb(array(
                'url' => self::get_post_thumb($post_id),
                'width' => 100,
                'height' => 100,
                'ratio' => 1,
            )),
            'title' => get_the_title($post_id),
            'type' => $type,
            'author' => $name,
            'avatar' => $avatar,
            'link' => '/' . $type . '/' . $post_id,
            'views' => $view,
            'likes' => $like,
            'comments' => $comment
        );

    }

    /**
     * 获取自定义文章列表
     *
     * @author  ifyn
     * @param   WP_REST_Request $request 请求对象
     * @return  array                    文章列表数据和分页信息
     */
    public static function get_custom_posts($request, $simple = false)
    {
        // 获取请求参数
        $params = is_object($request) && method_exists($request, 'get_json_params') ? $request->get_json_params() : $request;

        // 初始化查询参数
        $args = [
            'posts_per_page' => isset($params['size']) ? intval($params['size']) : 10,
            'paged' => isset($params['paged']) ? intval($params['paged']) : 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        // 筛查状态
        $user_id = get_current_user_id();

        // 根据用户权限设置文章状态过滤
        if ((isset($params['author']) && (int) $user_id === (int) $params['author'][0] && (int) $params['author'][0] !== 0) || 
            (user_can($user_id, 'manage_options') && isset($params['author']) && (int) $params['author'][0])) {
            $params['post_status'] = array('publish', 'pending', 'draft');  // 文章状态 发布 草稿
        } else {
            $params['post_status'] = array('publish');
        }

        $args['post_status'] = $params['post_status'];

        // 初始化空结果
        $empty = array(
            'pages' => 0,
            'paged' => 0,
            'data' => array()
        );

        // 指定文章
        if (isset($params['post__in']) && !empty($params['post__in'])) {
            $args['post__in'] = $params['post__in'];
        }

        // 指定类型
        if (isset($params['type']) && !empty($params['type'])) {
            $args['post_type'] = $params['type'];
        }

        if (isset($params['post_type']) && !empty($params['post_type'])) {
            $args['post_type'] = $params['post_type'];
        }

        // 重置分类ID
        if (isset($params['cat_id']) && !empty($params['cat_id']) && $params['cat_id'] == 0) {
            unset($params['cat_id']);
        }

        // 搜索参数
        if (isset($params['search']) && !empty($params['search'])) {
            $args['search_tax_query'] = true;
            $args['s'] = esc_attr($params['search']);
        }

        // 分类和标签查询条件
        $tax_query = [];

        // 自动识别分类信息
        if (isset($params['cat_id'])) {
            $cat_ids = self::normalize_input_to_array($params['cat_id']);
            foreach ($cat_ids as $cat_id) {
                $term = get_term($cat_id);
                if ($term && !is_wp_error($term)) {
                    $tax_query[] = [
                        'taxonomy' => $term->taxonomy,
                        'field' => 'term_id',
                        'terms' => $cat_id,
                        'include_children' => true,
                        'operator' => 'IN',
                    ];
                }
            }
        }

        // 自动识别标签信息
        if (isset($params['tag_id'])) {
            $tag_ids = self::normalize_input_to_array($params['tag_id']);
            foreach ($tag_ids as $tag_id) {
                $term = get_term($tag_id);
                if ($term && !is_wp_error($term)) {
                    $tax_query[] = [
                        'taxonomy' => $term->taxonomy,
                        'field' => 'term_id',
                        'terms' => $tag_id,
                        'include_children' => true,
                        'operator' => 'IN',
                    ];
                }
            }
        }

        // 如果有分类或标签筛选条件，添加到查询参数
        if (!empty($tax_query)) {
            if (isset($params['cat_id']) && is_array($params['cat_id']) && count($params['cat_id']) > 1) {
                $args['tax_query'] = [
                    'relation' => 'OR', // 默认逻辑关系，"AND" 或 "OR"
                    ...$tax_query,
                ];
            } else {
                $args['tax_query'] = [
                    'relation' => 'AND', // 默认逻辑关系，"AND" 或 "OR"
                    ...$tax_query,
                ];
            }
        }

        // 自定义字段筛选
        if (isset($params['metas']) && !empty($params['metas'])) {
            // 直接构建 meta_query
            if (!empty($params['metas']['meta_key']) && !empty($params['metas']['meta_value'])) {
                $args['meta_query'][] = array(
                    'key' => $params['metas']['meta_key'],
                    'value' => $params['metas']['meta_value'],
                    'compare' => "="
                );
            }
        }

        // 自定义作者
        if (isset($params['author__in']) && !empty($params['author__in'])) {
            $args['author__in'] = $params['author__in'];
        }

        if (isset($params['date']) && !empty($params['date'])) {
            $date = $params['date'];
            $date_query = [];

            if (!empty($date['year'])) {
                $date_query['year'] = (int) $date['year'];
            }

            if (!empty($date['month'])) {
                $date_query['month'] = (int) $date['month'];
            }

            if (!empty($date['day'])) {
                $date_query['day'] = (int) $date['day'];
            }

            if (!empty($date_query)) {
                $args['date_query'] = [$date_query];
            }
        }

        // 处理排序方式
        if (isset($params['sort'])) {
            switch ($params['sort']) {
                case 'views':
                    $args['meta_key'] = 'views';
                    $args['orderby'] = 'meta_value_num';
                    break;
                case 'likes':
                    $args['meta_key'] = 'islide_post_like';
                    $args['orderby'] = 'meta_value_num';
                    break;
                case 'comments':
                    $args['orderby'] = 'comment_count';
                    break;
                case 'modified':
                    $args['orderby'] = 'modified';
                    break;
                case 'random':
                    $args['orderby'] = 'rand';
                    break;
                default:
                    $args['orderby'] = 'date';
            }
        }

        // 执行查询
        $query = new \WP_Query($args);
        // 获取文章数据
        $posts = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $post_info = $simple ? self::get_post_meta($post_id) :  self::get_post_all_meta($post_id);
                $posts[] = $post_info;
            }
        }
        wp_reset_postdata();

        // 构建返回数据
        $response = [
            'data' => $posts,
            'paged' => $args['paged'],
            'pages' => $query->max_num_pages,
            'count' => $query->found_posts,
        ];

        return $response;
    }

    /**
     * 将输入标准化为数组形式
     *
     * @param mixed $input 输入值，可以是单个值或数组
     * @return array 返回数组
     */
    public static function normalize_input_to_array($input)
    {
        if (is_array($input)) {
            return array_map('intval', $input); // 转换为整数数组
        }
        return [(int) $input]; // 单个值转换为数组
    }

    public static function get_post_content_hide($post_id, $hide_content)
    {

        if (empty($hide_content))
            return [];

        $user_id = get_current_user_id();
        $role = apply_filters('check_reading_hide_content_role', $post_id, $user_id);


        if (!$role || is_array($role)) {
            $process = [];
            if ($role['allow']) {
                $process = [];
                foreach ($hide_content as $ct) {
                    $process[] = apply_filters('the_content', $ct);
                }
            } else {

                foreach ($hide_content as $ct) {
                    $process[] = '';
                }

            }
            $role['content'] = $process;
        }

        return $role;
    }

    public static function get_post_all_meta($post_id)
    {
        $thumb = self::get_post_thumb($post_id);
        $thumb_id = attachment_url_to_postid($thumb);
        $date = get_the_date('Y-m-d H:i:s', $post_id);
        $date_ = get_the_date('Y-m-d', $post_id);
        $is_self = (get_current_user_id() && get_current_user_id() == get_post_field('post_author', $post_id));
        $user_id = get_post_field('post_author', $post_id);
        $user_data = User::get_user_public_data($user_id);

        $or = get_post_field('post_content', $post_id);

        $content_hide = ShortCode::extract_content_hide_blocks($or, $post_id);//array

        $content_hide = self::get_post_content_hide($post_id, $content_hide);


        $content = array(
            'content' => apply_filters('the_content', $or),
            'content_hide' => $content_hide,
        );

        $ip_location = get_post_meta($post_id, 'islide_post_ip_location', true);
        $type = get_post_type($post_id);

        $post_info = [
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'excerpt' => islide_get_desc($post_id, 150),
            'content' => $content,
            'summary' => $type === 'post' ? get_post_meta($post_id, 'deepseek_summary', true) : '' ,
            'thumb' => array(
                'id' => $thumb_id,
                'color' => $type === 'post' ? FileUpload::get_image_dominant_color($thumb_id) : '',
                'default' => islide_get_thumb(array(
                    'url' => $thumb,
                    'width' => 500,
                    'height' => 200,
                    'ratio' => 2,
                )),
                'full' => wp_get_attachment_image_src($thumb_id, 'full'),
            ),
            'author' => $user_data,
            'is_self' => $is_self,
            'date' => islide_time_ago($date, true),
            'meta' => array(
                'views' => (int) get_post_meta($post_id, 'views', true),
                'comment' => islide_number_format(get_comments_number($post_id)),
                'like' => self::get_post_vote($post_id),
                'collect' => self::get_post_favorites($post_id),
                'share' => 0
            ),
            'cats' => get_the_category($post_id) ? get_the_category($post_id) : array(),
            'tags' => get_the_tags($post_id) ? get_the_tags($post_id) : array(),
            'type' => $type,
            'ip' => IpLocation::build_location($ip_location),
            'link' => '/' . $type . '/' . $post_id,
            'seo' => Seo::single_meta($post_id)
        ];
        if ($type === 'video') {
            $post_info['video'] = get_post_meta($post_id, 'single_video_metabox', true);
        }
        if ($type === 'shop') {
            $post_info['shop'] = Shop::get_shop_data($post_id);
        }
        if ($type === 'post') {
            $download_open = get_post_meta($post_id, 'islide_single_post_download_open', true);
            if (!$download_open) {
                $post_info['download'] = '0';
            }
            $download_data = get_post_meta($post_id, 'islide_single_post_download_group', true);
            $download_data = is_array($download_data) ? $download_data : array();
            if (!$download_data || !is_array($download_data)) {
                $post_info['download'] = '0';
            } else {
                $post_info['download'] = '1';
            }
        }
        return $post_info;
    }


    /**
     * 获取文章缩略图
     *
     * @author  ifyn
     * @param   int     $post_id    文章ID，默认为当前文章
     * @param   boolean $no_default 是否不使用默认图片
     * @return  string              文章缩略图URL
     */
    public static function get_post_thumb($post_id = 0, $no_default = false)
    {
        if (!$post_id) {
            global $post;
            if (!isset($post->ID))
                return '';
            $post_id = $post->ID;
        }

        //文章缩略图地址
        $post_thumbnail_url = get_the_post_thumbnail_url($post_id, 'full');

        if ($post_thumbnail_url) {
            return esc_url($post_thumbnail_url);
        } else {
            $post_content = get_post_field('post_content', $post_id);

            //获取文章第一张图
            $thumb = islide_get_first_img($post_content);

            if (!$thumb && !$no_default) {
                return islide_get_default_img();
            }
        }

        return apply_filters('islide_get_post_thumb', $thumb, $post_id, $no_default);
    }

    /**
     * 获取文章相关推荐
     *
     * @author  ifyn
     * @param   int $post_id 文章ID
     * @param   int $count   推荐数量，默认为6
     * @return  array        相关文章数据
     */
    public static function get_posts_related($post_id, $count = 6)
    {
        if (!(int) $post_id)
            return;

        $categorys = get_the_terms($post_id, 'category');
        $tags = get_the_terms($post_id, 'post_tag');

        $args = array(
            'showposts' => $count,
            'ignore_sticky_posts' => 1,
            'post_type' => 'post',
            'post_status' => 'publish',
            'order' => 'DESC',
            'orderby' => 'meta_value_num',
            'meta_key' => 'views',
            'tax_query' => array(
                'relation' => 'OR',
                array(
                    'taxonomy' => 'category',
                    'field' => 'term_id',
                    'terms' => array_column((array) $categorys, 'term_id'),
                ),
                array(
                    'taxonomy' => 'post_tag',
                    'field' => 'term_id',
                    'terms' => array_column((array) $tags, 'term_id'),
                ),
            ),
        );

        $the_query = new \WP_Query($args);

        $post_data = array();
        $_pages = 1;
        $_count = 0;

        if ($the_query->have_posts()) {
            $_pages = $the_query->max_num_pages;
            $_count = $the_query->found_posts;

            while ($the_query->have_posts()) {
                $the_query->the_post();
                $post_data[] = self::get_post_all_meta($the_query->post->ID);
            }

            wp_reset_postdata();
        }

        unset($the_query);
        return array(
            'count' => $_count,
            'pages' => $_pages,
            'data' => $post_data
        );
    }

    /**
     * 转换时间格式为人性化显示
     *
     * @author  ifyn
     * @param   string  $ptime  时间字符串，格式为'Y-m-d H:i:s'
     * @param   boolean $return 是否直接返回文本而不包装HTML
     * @return  string          格式化后的时间显示
     */
    public static function time_ago($ptime, $return = false)
    {
        if (!is_string($ptime))
            return;

        $_ptime = strtotime($ptime);
        $etime = current_time('timestamp') - $_ptime;

        if ($etime < 1) {
            $text = __('刚刚', 'islide');
        } else {
            $interval = array(
                60 * 60 => __('小时前', 'islide'),
                60 => __('分钟前', 'islide'),
                1 => __('秒前', 'islide')
            );

            if ($etime <= 84600) {
                foreach ($interval as $secs => $str) {
                    $d = $etime / $secs;
                    if ($d >= 1) {
                        $r = round($d);
                        $text = $r . $str;
                        break;
                    }
                }
            } else {
                $date = date_create($ptime);

                $y = date_format($date, "y");
                if ($y == date('y')) {
                    $text = sprintf(__('%s月%s日', 'islide'), date_format($date, "n"), date_format($date, "j"));
                } else {
                    $text = sprintf(__('%s年%s月%s日', 'islide'), $y, date_format($date, "n"), date_format($date, "j"));
                }
            }
        }

        if ($return)
            return $text;

        return '<time class="islide-timeago" datetime="' . $ptime . '" itemprop="datePublished">' . $text . '</time>';
    }


    /**
     * 保存文章IP位置信息
     *
     * @author  ifyn
     * @param   int $post_id 文章ID
     * @return  void
     */
    public static function insert_last_insert_post($post_id)
    {
        // 获取用户IP
        $ip = islide_get_user_ip();
        
        // 获取IP位置信息
        $data = IpLocation::get($ip);

        // 检查是否获取成功
        if (isset($data['error']))
            return;
            
        // 添加日期信息
        $data['date'] = current_time('mysql');

        // 更新文章元数据
        update_post_meta($post_id, 'islide_post_ip_location', $data);
    }

    /**
     * 处理文章发布或更新
     *
     * @author  ifyn
     * @param   array $data 文章数据
     * @return  array       处理结果或错误信息
     */
    public static function insert_post($data)
    {
        // 获取当前用户ID
        $user_id = get_current_user_id();

        // 检查用户是否已登录
        if (!$user_id)
            return array('error' => __('请先登录，才能发布文章', 'islide'));

        // 检查是否允许投稿
        if (!islide_get_option('write_allow'))
            return array('error' => __('投稿功能已被关闭，请联系管理员', 'islide'));

        // 文本审查 (未启用)
        // $censor = apply_filters('islide_text_censor', $data['title'].$data['content'].$data['excerpt']);
        // if(isset($censor['error'])) return $censor;

        // 判断是否是编辑文章
        $edit = isset($data['post_id']) && (int) $data['post_id'] !== 0;

        // 编辑模式下检查权限
        if ($edit) {
            if ((get_post_field('post_author', $data['post_id']) != $user_id || 
                get_post_type($data['post_id']) != 'post') && 
                !user_can($user_id, 'administrator') && 
                !user_can($user_id, 'editor')) {
                return array('error' => __('非法操作，不能编辑他人文章', 'islide'));
            }
        }

        // 检查文章标题
        if (!isset($data['title']) || !$data['title']) {
            return array('error' => __('标题不可为空', 'islide'));
        }

        // 检查文章内容
        if (!isset($data['content']) || !$data['content']) {
            return array('error' => __('内容不可为空', 'islide'));
        }

        // 检查文章分类
        if (!isset($data['cats']) || !$data['cats']) {
            return array('error' => __('请选择文章分类', 'islide'));
        }

        $post_id = false;

        // 设置文章状态
        if ($data['type'] !== 'draft') {
            if ((user_can($user_id, 'manage_options') || user_can($user_id, 'editor'))) {
                // 管理员直接发布
                $data['type'] = 'publish';
            } else {
                // 普通用户需要审核
                $data['type'] = 'pending';
            }
            
            // 可以通过角色权限检查添加自定义发布权限
            // $can_publish = User::check_user_media_role($user_id,'post');
            // if($can_publish){
            //     $data['type'] = 'publish';
            // }
        } else {
            // 保存为草稿
            $data['type'] = 'draft';
        }

        // 处理标题，移除特殊字符
        $data['title'] = str_replace(array('{{', '}}'), '', sanitize_text_field($data['title']));

        // 编辑模式下获取原作者ID
        if ($edit) {
            $user_id = get_post_field('post_author', $data['post_id']);
        }

        // 准备文章数据
        $arg = array(
            'ID' => $edit ? $data['post_id'] : null,
            'post_title' => $data['title'],
            'post_content' => wp_slash($data['content']),
            'post_status' => $data['type'],
            'post_author' => $user_id,
            'post_category' => $data['cats'],
            //'post_excerpt'=>$data['excerpt'],
        );

        // 更新或插入文章
        if ($edit) {
            $post_id = wp_update_post($arg);
        } else {
            $post_id = wp_insert_post($arg);
        }

        // 处理发布结果
        if ($post_id) {
            // 设置IP位置信息
            self::insert_last_insert_post($post_id);

            // 设置标签
            if (!empty($data['tags'])) {
                $tags = array();
                foreach ($data['tags'] as $key => $value) {
                    $tags[] = str_replace(array('{{', '}}'), '', sanitize_text_field($value));
                }
                wp_set_post_tags($post_id, $tags, false);
            }

            // 设置特色图
            $thumb_id = self::get_attached_id_by_url($data['thumb']);
            if ($thumb_id) {
                set_post_thumbnail($post_id, $thumb_id);
            }

            // 隐藏内容权限设置
            if (isset($data['role'])) {
                // 设置隐藏内容角色
                if (isset($data['role']['key']) && $data['role']['key']) {
                    update_post_meta(
                        $post_id, 
                        'islide_post_content_hide_role', 
                        esc_attr(sanitize_text_field(wp_unslash($data['role']['key'])))
                    );
                }

                // 设置余额或积分
                if (isset($data['role']['num']) && !empty($data['role']['num'])) {
                    if (in_array($data['role']['key'], array('money', 'credit'))) {
                        if ($data['role']['num'] <= 0)
                            return array('error' => __('金额错误', 'islide'));
                        update_post_meta($post_id, 'islide_post_price', (int) $data['role']['num']);
                    }

                    // 设置密码
                    if ($data['role']['key'] == 'password') {
                        update_post_meta($post_id, 'islide_post_password', (int) $data['role']['num']);
                    }
                }

                // 设置角色权限
                if (isset($data['role']['roles']) && !empty($data['role']['roles'])) {
                    foreach ($data['role']['roles'] as $k => $v) {
                        $data['role']['roles'][$k] = esc_attr(sanitize_text_field($v));
                    }
                    update_post_meta($post_id, 'islide_post_roles', $data['role']['roles']);
                }
            }

            // 处理文章中的图片挂载
            $regex = '/src="([^"]*)"/';
            preg_match_all($regex, $data['content'], $matches);
            $matches = array_reverse($matches);

            if (!empty($matches[0])) {
                foreach ($matches[0] as $k => $v) {
                    $thumb_id = self::get_attached_id_by_url($v);
                    if ($thumb_id) {
                        // 检查是否挂载过
                        if (!wp_get_post_parent_id($thumb_id) || (int) wp_get_post_parent_id($thumb_id) === 1) {
                            wp_update_post(
                                array(
                                    'ID' => $thumb_id,
                                    'post_parent' => $post_id
                                )
                            );
                        }
                    }
                }
            }
            
            // 返回成功信息
            return array(
                'msg' => $data['type'] == 'draft' ? '草稿保存成功，你可选择继续编辑或发布文章' : '发布成功，等待管理员审核',
                'url' => $data['type'] == 'draft' ? '/write?id=' . $post_id : '/' . get_post_type($post_id) . '/' . $post_id
            );
        }

        return array('error' => __('发布失败', 'islide'));
    }

    /**
     * 根据图片地址获取附件ID
     *
     * @author  ifyn
     * @param   string $url 图片URL
     * @return  int         附件ID
     */
    public static function get_attached_id_by_url($url)
    {
        // 使用WordPress内置函数获取ID
        return attachment_url_to_postid($url);

        // 备用方法（注释掉的代码）
        $path = parse_url($url);

        if ($path['path']) {
            global $wpdb;

            $sql = $wpdb->prepare(
                "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND guid LIKE %s",
                'attachment',
                '%' . $path['path'] . '%'
            );

            $post_id = $wpdb->get_var($sql);

            return (int) $post_id;
        }
    }

    /**
     * 文章投票
     *
     * @author  ifyn
     * @param   string $type    投票类型，如like、dislike
     * @param   int    $post_id 文章ID
     * @return  array           投票结果或错误信息
     */
    public static function post_vote($type, $post_id)
    {
        $user_id = get_current_user_id();
        if (!$user_id)
            return array('error' => '请先登录之后再参与参与投票哦！');

        $post_author = (int) get_post_field('post_author', $post_id);
        if (!$post_author)
            return array('error' => '参数错误');

        if ($user_id === $post_author)
            return array('error' => '不能给自己投票');

        // 用户喜欢的评论
        $post_likes = get_user_meta($user_id, 'islide_post_likes', true);
        $post_likes = is_array($post_likes) ? $post_likes : array();
        $key = array_search($post_id, $post_likes);

        $post_like = (int) get_post_meta($post_id, 'islide_post_like', true);

        if ($key === false) {
            $post_likes[] = $post_id;
            $post_like += 1;
        } else {
            unset($post_likes[$key]);
            $post_like -= 1;
        }

        update_user_meta($user_id, 'islide_post_likes', $post_likes);
        update_post_meta($post_id, 'islide_post_like', $post_like);

        do_action('islide_post_vote', $post_id, $user_id, $key === false);

        return array(
            'message' => $key === false ? '点赞成功！' : '点赞取消！',
            'is_like' => $key === false ? true : false,
            'like' => $post_like,
            'count' => $post_like
        );
    }

    /**
     * 获取文章投票状态
     *
     * @author  ifyn
     * @param   int $post_id 文章ID
     * @return  array        投票状态信息
     */
    public static function get_post_vote($post_id)
    {
        if (!$post_id)
            return;

        $user_id = get_current_user_id();

        $post_likes = get_user_meta($user_id, 'islide_post_likes', true);
        $post_likes = is_array($post_likes) ? $post_likes : array();

        $is_like = in_array($post_id, $post_likes);

        $post_like = (int) get_post_meta($post_id, 'islide_post_like', true);

        return array(
            'is_like' => $is_like,
            'like' => $post_like,
            'count' => $post_like,
            'is' => $is_like,
        );
    }

    /**
     * 获取用户是否收藏文章和文章收藏数量
     *
     * @author  ifyn
     * @param   int $post_id 文章ID
     * @return  array        收藏状态信息
     */
    public static function get_post_favorites($post_id)
    {
        if (!$post_id)
            return;

        $current_user_id = get_current_user_id();

        // 获取文章的收藏数据
        $post_favorites = get_post_meta($post_id, 'islide_post_favorites', true);
        $post_favorites = is_array($post_favorites) ? $post_favorites : array();

        $is_favorite = in_array($current_user_id, $post_favorites);

        return array(
            'is_collect' => $is_favorite,
            'count' => count($post_favorites),
            'is' => $is_favorite,
        );
    }

    /**
     * 获取指定用户文章投稿的所有分类
     *
     * @author  ifyn
     * @param   int $user_id 用户ID
     * @return  array        分类信息数组
     */
    public static function get_user_post_categories($user_id)
    {
        $categories = array(); // 存储分类的数组
        $categories_info = array(); // 存储最终返回的分类信息
        
        $args = array(
            'author' => $user_id, // 筛选指定用户的文章
            'post_type' => 'post', // 文章类型为post
            'posts_per_page' => -1, // 获取所有文章
            'fields' => 'ids', // 只获取文章ID，加快查询速度
            'category__not_in' => array(1) // 排除默认分类，加快查询速度
        );
        
        $post_ids = get_posts($args); // 获取文章ID列表
        
        if (!empty($post_ids)) {
            $categories = get_the_category($post_ids[0]); // 获取第一篇文章的分类列表
            
            for ($i = 1; $i < count($post_ids); $i++) {
                $post_categories = get_the_category($post_ids[$i]); // 获取文章的分类列表
                $categories = array_merge($categories, $post_categories); // 合并分类列表
            }
            
            $categories = array_unique($categories, SORT_REGULAR); // 去重
            
            foreach ($categories as $category) {
                $category_info = array(
                    'name' => $category->name, // 分类名称
                    'url' => get_category_link($category->term_id), // 分类链接
                    'id' => $category->term_id // 分类ID
                );
                $categories_info[] = $category_info; // 存储分类信息到数组中
            }
        }
        
        return $categories_info;
    }

    /**
     * 获取文章下载数据
     *
     * @author  ifyn
     * @param   int $post_id 文章ID
     * @return  array|array  下载资源数据或错误信息
     */
    public static function get_post_download_data($post_id)
    {
        $post_id = (int) $post_id;
        $user_id = get_current_user_id();

        $download_open = get_post_meta($post_id, 'islide_single_post_download_open', true);
        // 是否开启文章下载功能
        if (!$download_open)
            return array('error' => '文章下载未开启');

        $download_data = get_post_meta($post_id, 'islide_single_post_download_group', true);
        $download_data = is_array($download_data) ? $download_data : array();
        $download_data = apply_filters('filter_download_data', $download_data, $post_id);
        if (!$download_data || !is_array($download_data))
            return array('error' => '文章没有下载资源');

        $data = array();
        $index = 0;

        $user_lv = array(
            'lv' => User::get_user_lv($user_id),
            'vip' => User::get_user_vip($user_id)
        );
        // 获取是否开启游客支付
        $can_not_login_pay = get_post_meta($post_id, 'islide_down_not_login_buy', true);
        foreach ($download_data as $key => $value) {
            $rights = apply_filters('islide_get_download_rights', $value['rights']);
            $can = apply_filters('check_user_can_download', $post_id, $user_id, $rights, $index);
            $not_login_pay = false;

            if ($can_not_login_pay) {
                foreach ($rights as $k => $v) {
                    if ($v['lv'] === 'not_login' || $v['lv'] === 'all') {
                        $not_login_pay = true;
                    }
                }
            }

            $data[] = array(
                'title' => !empty($value['title']) ? $value['title'] : get_the_title($post_id),
                'link' => '/download?post_id=' . $post_id . '&index=' . $index,
                'attrs' => !empty($value['attrs']) ? self::get_download_attrs($value['attrs']) : array(),
                'rights' => $rights,
                'current_user' => array(
                    'can' => $can,
                    'lv' => $user_lv,
                    'not_login_pay' => $not_login_pay
                ),
                'can_not_login_pay' => $can_not_login_pay,
            );

            $index++;
        }

        return $data;
    }

    /**
     * 将下载数据中的属性字符串转换成数组
     *
     * @author  ifyn
     * @param   string $attrs 属性字符串，每行一个，格式为"key|value"
     * @return  array         转换后的属性数组
     */
    public static function get_download_attrs($attrs)
    {
        if (!$attrs)
            return array();

        $attrs = trim($attrs, " \t\n\r");
        $attrs = explode(PHP_EOL, $attrs);

        $args = array();

        foreach ($attrs as $k => $v) {
            $v = trim($v, " \t\n\r");
            $_v = explode('|', $v);
            if (!isset($_v[0]) && !isset($_v[1]))
                continue;

            $args[] = array(
                'key' => $_v[0],
                'value' => $_v[1]
            );
        }

        return $args;
    }

    /**
     * 获取下载页面数据
     *
     * @author  ifyn
     * @param   int $post_id 文章ID
     * @param   int $index   下载资源索引
     * @return  array        下载页面数据或错误信息
     */
    public static function get_download_page_data($post_id, $index)
    {
        $user_id = get_current_user_id();

        $download_open = get_post_meta($post_id, 'islide_single_post_download_open', true);
        // 是否开启文章下载功能
        if (!$download_open)
            return array('error' => '文章下载未开启');

        $download_data = get_post_meta($post_id, 'islide_single_post_download_group', true);
        $download_data = is_array($download_data) ? $download_data : array();
        $download_data = apply_filters('filter_download_data', $download_data, $post_id);

        if (!$download_data || !isset($download_data[$index]))
            return array('error' => '没有找到您要下载的资源');

        $data = $download_data[$index];

        $rights = apply_filters('islide_get_download_rights', $data['rights']);

        $can = apply_filters('check_user_can_download', $post_id, $user_id, $rights, $index);

        return array(
            'title' => get_the_title($post_id),
            'attrs' => !empty($data['attribute']) ? $data['attribute'] : array(),
            'links' => !empty($data['download_group']) ? self::get_download_links($post_id, $user_id, $data['download_group']) : array(),
            'can' => $can,
        );
    }

    /**
     * 处理下载内容过滤
     *
     * @author  ifyn
     * @param   array $data    下载数据
     * @param   int   $post_id 文章ID
     * @return  array          处理后的下载数据
     */
    public static function filter_download_data($data, $post_id)
    {
        $orderby = get_post_meta($post_id, 'islide_download_data_orderby', true);

        if (is_array($data) && $orderby) {
            $data = array_reverse($data);
        }

        return $data;
    }

    /**
     * 获取下载链接
     *
     * @author  ifyn
     * @param   int   $post_id 文章ID
     * @param   int   $user_id 用户ID
     * @param   array $data    下载数据
     * @return  array          处理后的下载链接
     */
    public static function get_download_links($post_id, $user_id, $data)
    {
        if (!$data || !is_array($data))
            return array();

        $arg = array();

        foreach ($data as $key => $value) {
            if (class_exists('Jwt_Auth_Public') && !empty($value['url'])) {
                $issuedAt = time();
                $expire = $issuedAt + 300; // 5分钟时效

                $token = array(
                    "iss" => IS_HOME_URI,
                    "iat" => $issuedAt,
                    "nbf" => $issuedAt,
                    'exp' => $expire,
                    'data' => array(
                        'url' => $value['url'],
                        'sign' => md5($post_id . AUTH_KEY . $user_id),
                        'user_id' => $user_id,
                        'post_id' => $post_id,
                    )
                );

                $token = \Firebase\JWT\JWT::encode($token, AUTH_KEY);
            }

            // 加密下载地址
            $arg[] = array(
                'name' => $value['name'] ?: '下载',
                'token' => $token ?: '',
                'jy' => $value['jy'],
                'tq' => $value['tq']
            );
        }

        return $arg;
    }

    /**
     * 获取文件的真实地址
     *
     * @author  ifyn
     * @param   string $token JWT令牌
     * @return  string|array  文件地址或错误信息
     */
    public static function download_file($token)
    {
        try {
            // 检查验证码
            $decoded = \Firebase\JWT\JWT::decode($token, AUTH_KEY, array('HS256'));

            if (!isset($decoded->data->sign) || !isset($decoded->data->user_id)) {
                return array('error' => '参数错误');
            }

            $sign = md5($decoded->data->post_id . AUTH_KEY . $decoded->data->user_id);

            if ($sign !== $decoded->data->sign)
                return array('error' => '参数错误');

            $down_count = apply_filters('check_user_can_download_all', $decoded->data->user_id);

            if (is_array($down_count) && !in_array($decoded->data->post_id, $down_count['posts']) && 
                (int) $down_count['count'] < 9999 && 
                get_user_meta($decoded->data->user_id, 'islide_download_count', true)) {

                $down_count['posts'][] = $decoded->data->post_id;
                $down_count['count'] = (int) $down_count['count'] - 1;

                update_user_meta($decoded->data->user_id, 'islide_download_count', $down_count);
            }

            return $decoded->data->url;

        } catch (\Firebase\JWT\ExpiredException $e) {  // token过期
            return array('error' => '网页时效过期，请重新发起');
        } catch (\Exception $e) {  // 其他错误
            return array('error' => '解码失败');
        }
    }

    /**
     * 生成用户角色数据
     *
     * @author  ifyn
     * @param   int   $user_id 用户ID
     * @return  array          用户角色数据
     */
    public static function generate_role_data($user_id)
    {
        // 获取用户等级和 VIP 信息
        $lv = User::get_user_lv($user_id);
        $vip = User::get_user_vip($user_id);

        // 获取各种配置选项
        $create_normal_post = islide_get_option('create_normal_post');
        $media_role_list = islide_get_option('media_upload_role');
        $media_count_list = islide_get_option('circle_post');

        $role_data = array();
        $roles = User::get_user_roles();
        foreach ($roles as $key => $value) {
            $role_data[$key] = $value['name'];
        }

        // 公共检查函数
        $check_role = function ($list, $key = 'null') use ($lv, $vip) {
            if (isset($list[$key])) {
                return Circle::check_insert_role($list[$key], $lv, $vip);
            }
            return Circle::check_insert_role($list, $lv, $vip);
        };

        return [
            // 创建权限
            'write_allow' => islide_get_option('write_allow') == '1' ? true : false,
            'can_create_normal_post' => $check_role($create_normal_post),

            // 媒体角色
            'media_role' => [
                'image' => $check_role($media_role_list, 'image'),
                'video' => $check_role($media_role_list, 'video'),
                'file' => $check_role($media_role_list, 'file'),
            ],

            // 媒体计数
            'media_count' => [
                'min_word_limit' => $media_count_list['min_word_limit'],
                'max_word_limit' => $media_count_list['max_word_limit'],
                'image_count' => $media_count_list['image_count'],
                'video_count' => $media_count_list['video_count'],
                'file_count' => $media_count_list['file_count'],
            ],
            'roles' => $role_data,
        ];
    }

    /**
     * 获取文章的上一篇和下一篇
     *
     * @author  ifyn
     * @param   int   $post_id 文章ID
     * @return  array          上一篇和下一篇文章信息
     */
    public static function posts_prevnext($post_id){
        $type = get_post_type($post_id);
        if ($type) {
            $prev_post = get_previous_post($post_id);
            $next_post = get_next_post($post_id);

            $args = array(
                'number' => 1, 
                'orderby' => 'rand', 
                'post_status' => 'publish',
                'post_type' => $type
            );
            
            $user_id = get_post_field('post_author', $post_id);
            $author_data = User::get_author_info($user_id);

            // 如果没有上一篇或者下一篇，则显示随机文章
            if (empty($prev_post)) {
                $rand_posts = get_posts($args);
                $prev_post = !empty($rand_posts) ? $rand_posts[0] : null;
            }
            
            if (empty($next_post)) {
                $rand_posts = get_posts($args);
                $next_post = !empty($rand_posts) ? $rand_posts[0] : null;
            }
            
            if (!empty($prev_post)) {
                unset($prev_post->post_content);
            }
            
            if (!empty($next_post)) {
                unset($next_post->post_content);
            }

            return array(
                'author' => $author_data,
                'prev_post' => $prev_post,
                'next_post' => $next_post
            );
        }
        
        return null;
    }
}