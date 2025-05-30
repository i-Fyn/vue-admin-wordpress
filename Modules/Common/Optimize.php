<?php namespace islide\Modules\Common;

/**
 * 优化类
 * @author ifyn
 * @package Optimize
 */
class Optimize {

    /**
     * 初始化方法
     * @author ifyn
     * @return void
     */
    public function init() {
        try {
            $optimize = islide_get_option('islide_system_optimize');

             // 禁用 Gutenberg 编辑器
             add_filter('use_block_editor_for_post', '__return_false');
             remove_action('wp_enqueue_scripts', 'wp_common_block_scripts_and_styles');
             
            if (!is_array($optimize)) {
                return;
            }

            add_filter('use_block_editor_for_post', '__return_false');

            // 移除多余的meta标签
            if(isset($optimize['optimize_remove_meta_tags']) && $optimize['optimize_remove_meta_tags']) {
                add_action('after_setup_theme', array($this, 'remove_meta_tags'));
            }
            
            // 禁用embed功能
            if(isset($optimize['optimize_disable_embeds']) && $optimize['optimize_disable_embeds']) {
                add_action('init', array($this, 'disable_embeds'));
            }
            
            //禁用响应式图片属性
            add_filter('use_default_gallery_style', '__return_false');
            
            // 禁用缩放尺寸 禁用WordPress大图像自动缩放功能
            add_filter('big_image_size_threshold', '__return_false');
            
            //禁用word press全局全局CSS样式
            if(isset($optimize['optimize_remove_styles']) && $optimize['optimize_remove_styles']) {
                add_action('wp_enqueue_scripts', array($this, 'remove_wordpress_styles'));
            }
            
            // 禁用全局样式表
            add_action('init', array($this, 'disable_global_styles'));
            // 禁用获取全局样式表的URL
            add_action('init', array($this, 'disable_global_stylesheet'));
            
            remove_action('pre_post_update', 'wp_save_post_auto_draft');
            
            // 移除多余的图片尺寸 禁用自动生成的图片尺寸
            if(isset($optimize['optimize_disable_image_sizes']) && $optimize['optimize_disable_image_sizes']) {
                add_action('intermediate_image_sizes_advanced', array($this, 'disable_image_sizes'));
            }
            
            // 移除菜单多余的CLASS和ID沉余
            if(isset($optimize['optimize_remove_menu_class']) && $optimize['optimize_remove_menu_class']) {
                add_filter('nav_menu_css_class', array($this, 'remove_menu_classes'), 99, 1);
                add_filter('nav_menu_item_id', array($this, 'remove_menu_classes'), 99, 1);
                add_filter('page_css_class', array($this, 'remove_menu_classes'), 99, 1);
            }
            
            // 移除WordPress自带的Emoji表情支持
            if(isset($optimize['optimize_disable_emoji']) && $optimize['optimize_disable_emoji']) {
                add_action('init', array($this, 'disable_wp_emoji'));
            }
            
            // 移除WordPress自带的Pingback功能
            if(isset($optimize['optimize_disable_pingback']) && $optimize['optimize_disable_pingback']) {
                add_action('pre_ping', array($this, 'disable_pingback'));
            }
            
            // 禁用文章自动保存 自动草稿 auto-draft
            if(isset($optimize['optimize_disable_autosave']) && $optimize['optimize_disable_autosave']) {
                add_action('wp_print_scripts', array($this, 'disable_autosave'));
                add_action('admin_init', array($this, 'disable_auto_drafts'));
            }
            
            // 禁用文章修订版本
            if(isset($optimize['optimize_disable_revisions']) && $optimize['optimize_disable_revisions']) {
                add_filter('wp_revisions_to_keep', array($this, 'disable_revisions'), 10, 2);
            }
            
            // 禁用 WordPress 自动更新
            if(isset($optimize['optimize_disable_wp_update']) && $optimize['optimize_disable_wp_update']) {
                add_filter('auto_update_core', '__return_false');
                add_filter('auto_update_plugin', '__return_false');
                add_filter('auto_update_theme', '__return_false');
            }
            
            // 禁用WordPress自带的XML-RPC接口
            if(isset($optimize['optimize_disable_xmlrpc']) && $optimize['optimize_disable_xmlrpc']) {
                add_filter('xmlrpc_enabled', '__return_false');
            }
            
            // 禁用WordPress自带的REST API 移除 wp-json
            add_filter('rest_jsonp_enabled', '__return_false');
            
            // 自定义 WordPress wp-json 路径
            add_filter('rest_url_prefix', function() {
                return 'wp-json';
            });
            
            //禁用工具条删除WP工具栏
            if(isset($optimize['optimize_disable_admin_bar']) && $optimize['optimize_disable_admin_bar']) {
                add_filter('show_admin_bar', '__return_false');
            }
            
            // 禁用 Gutenberg 编辑器
            add_filter('use_block_editor_for_post', '__return_false');
            remove_action('wp_enqueue_scripts', 'wp_common_block_scripts_and_styles');
            
            // 禁用 Gutenberg 编辑器 和 经典编辑器 中的小工具区块编辑器
            if(isset($optimize['optimize_disable_widgets_block']) && $optimize['optimize_disable_widgets_block']) {
                add_filter('gutenberg_use_widgets_block_editor', '__return_false');
                add_filter('use_widgets_block_editor', '__return_false');
            }
            
            //禁用WordPress中的Open Sans字体
            if(isset($optimize['optimize_disable_open_sans']) && $optimize['optimize_disable_open_sans']) {
                add_action('wp_enqueue_scripts', array($this, 'disable_open_sans'));
            }
            
            //限制非管理员访问WordPress后台
            add_action('admin_init', array($this, 'restrict_admin_access'), 1);
            
            //禁用RSS订阅
            if(isset($optimize['optimize_disable_rss']) && $optimize['optimize_disable_rss']) {
                add_action('do_feed', array($this, 'disable_feed'), 1);
                add_action('do_feed_rdf', array($this, 'disable_feed'), 1);
                add_action('do_feed_rss', array($this, 'disable_feed'), 1);
                add_action('do_feed_rss2', array($this, 'disable_feed'), 1);
                add_action('do_feed_atom', array($this, 'disable_feed'), 1);
                add_action('do_feed_rss2_comments', array($this, 'disable_feed'), 1);
                add_action('do_feed_atom_comments', array($this, 'disable_feed'), 1);
            }
            
            //禁用日期归档
            add_action('template_redirect', array($this, 'disable_date_archives'));
        } catch (Exception $e) {
            error_log('Optimize init error: ' . $e->getMessage());
        }
    }

    /**
     * 移除多余的meta标签
     * @author ifyn
     * @return void
     */
    public function remove_meta_tags() {
        try {
            remove_action('wp_head', 'wp_generator');
            remove_action('wp_head', 'rsd_link');
            remove_action('wp_head', 'wlwmanifest_link');
            remove_action('wp_head', 'wp_shortlink_wp_head', 10);
            remove_action('wp_head', 'feed_links', 2);
            remove_action('wp_head', 'feed_links_extra', 3);
            remove_action('wp_head', 'rest_output_link_wp_head', 10);
            remove_action('wp_head', 'wp_resource_hints', 2);
            remove_action('template_redirect', 'wp_shortlink_header', 11);
            remove_action('wp_head', 'index_rel_link', 10, 1);
            remove_action('wp_head', 'start_post_rel_link', 10, 1);
            remove_action('wp_head', 'parent_post_rel_link', 10, 0);
            remove_action('wp_head', 'adjacent_posts_rel_link', 10, 0);
            remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);
            remove_action('wp_head', 'rel_canonical', 10, 0);
        } catch (Exception $e) {
            error_log('Remove meta tags error: ' . $e->getMessage());
        }
    }

    /**
     * 禁用embed功能
     * @author ifyn
     * @return void
     */
    public function disable_embeds() {
        try {
            global $wp;
            $wp->public_query_vars = array_diff($wp->public_query_vars, array('embed'));
            remove_action('rest_api_init', 'wp_oembed_register_route');
            add_filter('embed_oembed_discover', '__return_false');
            remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
            remove_action('wp_head', 'wp_oembed_add_discovery_links');
            remove_action('wp_head', 'wp_oembed_add_host_js');
            add_filter('tiny_mce_plugins', array($this, 'disable_embeds_tiny_mce_plugin'));
            add_filter('rewrite_rules_array', array($this, 'disable_embeds_rewrites'));
            remove_action('template_redirect', 'wp_old_slug_redirect');
        } catch (Exception $e) {
            error_log('Disable embeds error: ' . $e->getMessage());
        }
    }

    /**
     * 禁用embed TinyMCE插件
     * @author ifyn
     * @param array $plugins TinyMCE插件列表
     * @return array 修改后的插件列表
     */
    public function disable_embeds_tiny_mce_plugin($plugins) {
        if (!is_array($plugins)) {
            return array();
        }
        return array_diff($plugins, array('wpembed'));
    }

    /**
     * 禁用embed重写规则
     * @author ifyn
     * @param array $rules 重写规则数组
     * @return array 修改后的规则数组
     */
    public function disable_embeds_rewrites($rules) {
        if (!is_array($rules)) {
            return array();
        }
        foreach ($rules as $rule => $rewrite) {
            if (false !== strpos($rewrite, 'embed=true')) {
                unset($rules[$rule]);
            }
        }
        return $rules;
    }
    
    /**
     * 在插件激活时移除嵌入式内容的重写规则
     * @author ifyn
     * @return void
     */
    public function disable_embeds_remove_rewrite_rules() {
        try {
            add_filter('rewrite_rules_array', array($this, 'disable_embeds_rewrites'));
            flush_rewrite_rules();
        } catch (Exception $e) {
            error_log('Remove rewrite rules error: ' . $e->getMessage());
        }
    }
    
    /**
     * 在插件停用时刷新重写规则
     * @author ifyn
     * @return void
     */
    public function disable_embeds_flush_rewrite_rules() {
        try {
            remove_filter('rewrite_rules_array', array($this, 'disable_embeds_rewrites'));
            flush_rewrite_rules();
        } catch (Exception $e) {
            error_log('Flush rewrite rules error: ' . $e->getMessage());
        }
    }
    
    /**
     * 移除多余的图片尺寸
     * @author ifyn
     * @param array $sizes 图片尺寸数组
     * @return array 修改后的图片尺寸数组
     */
    public function disable_image_sizes($sizes) {
        if (!is_array($sizes)) {
            return array();
        }
        unset($sizes['thumbnail']);
        unset($sizes['medium']);
        unset($sizes['large']);
        unset($sizes['medium_large']);
        unset($sizes['1536x1536']);
        unset($sizes['2048x2048']);
        return $sizes;
    }
    
    /**
     * 禁用WordPress全局CSS样式
     * @author ifyn
     * @return void
     */
    public function remove_wordpress_styles() {
        try {
            wp_deregister_style('global-styles');
            wp_dequeue_style('global-styles');
            wp_deregister_style('wp-block-library');
            wp_dequeue_style('wp-block-library');
            wp_dequeue_style('wp-block-library-theme');
            wp_dequeue_style('wc-block-style');
            wp_deregister_style('classic-theme-styles');
            wp_dequeue_style('classic-theme-styles');
        } catch (Exception $e) {
            error_log('Remove WordPress styles error: ' . $e->getMessage());
        }
    }

    /**
     * 禁用全局样式表
     * @author ifyn
     * @return void
     */
    public function disable_global_styles() {
        try {
            remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
        } catch (Exception $e) {
            error_log('Disable global styles error: ' . $e->getMessage());
        }
    }

    /**
     * 禁用获取全局样式表的URL
     * @author ifyn
     * @return void
     */
    public function disable_global_stylesheet() {
        try {
            remove_filter('stylesheet_uri', 'wp_get_global_stylesheet');
        } catch (Exception $e) {
            error_log('Disable global stylesheet error: ' . $e->getMessage());
        }
    }

    /**
     * 移除菜单多余的CLASS和ID
     * @author ifyn
     * @param array $classes 菜单项的CSS类数组
     * @return array|string 修改后的CSS类数组或空字符串
     */
    public function remove_menu_classes($classes) {
        if (!is_array($classes)) {
            return '';
        }
        return array_filter($classes, function($class) {
            if ($class === 'current-menu-item') {
                return true;
            }
            return (false === strpos($class, 'menu') && false === strpos($class, 'page'));
        });
    }

    /**
     * 移除WordPress自带的Emoji表情支持
     * @author ifyn
     * @return void
     */
    public function disable_wp_emoji() {
        try {
            remove_action('wp_head', 'print_emoji_detection_script', 7);
            remove_action('admin_print_scripts', 'print_emoji_detection_script');
            remove_action('wp_print_styles', 'print_emoji_styles');
            remove_action('admin_print_styles', 'print_emoji_styles');
            remove_filter('the_content_feed', 'wp_staticize_emoji');
            remove_filter('comment_text_rss', 'wp_staticize_emoji');
            remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
            add_filter('tiny_mce_plugins', array($this, 'disable_emoji_tinymce'));
        } catch (Exception $e) {
            error_log('Disable WP emoji error: ' . $e->getMessage());
        }
    }

    /**
     * 禁用Emoji TinyMCE插件
     * @author ifyn
     * @param array $plugins TinyMCE插件列表
     * @return array 修改后的插件列表
     */
    public function disable_emoji_tinymce($plugins) {
        if (!is_array($plugins)) {
            return array();
        }
        return array_diff($plugins, array('wpemoji'));
    }

    /**
     * 移除WordPress自带的Pingback功能
     * @author ifyn
     * @param array $links 链接数组
     * @return array 修改后的链接数组
     */
    public function disable_pingback($links) {
        if (!is_array($links)) {
            return array();
        }
        foreach ($links as $key => $link) {
            if (false !== strpos($link, 'xmlrpc')) {
                $links[$key] = '';
            }
        }
        return $links;
    }

    /**
     * 禁用文章自动保存
     * @author ifyn
     * @return void
     */
    public function disable_autosave() {
        try {
            wp_deregister_script('autosave');
        } catch (Exception $e) {
            error_log('Disable autosave error: ' . $e->getMessage());
        }
    }
    
    /**
     * 禁用文章自动草稿
     * @author ifyn
     * @return void
     */
    public function disable_auto_drafts() {
        try {
            remove_action('pre_post_update', 'wp_save_post_auto_draft');
        } catch (Exception $e) {
            error_log('Disable auto drafts error: ' . $e->getMessage());
        }
    }
    
    /**
     * 禁用文章修订版本
     * @author ifyn
     * @param int $num 修订版本数量
     * @param WP_Post $post 文章对象
     * @return int 返回0表示禁用修订版本
     */
    public function disable_revisions($num, $post) {
        return 0;
    }
    
    /**
     * 移除后台谷歌字体
     * @author ifyn
     * @return void
     */
    public function disable_open_sans() {
        try {
            wp_deregister_style('open-sans');
            wp_register_style('open-sans', false);
            wp_enqueue_style('open-sans', '');
        } catch (Exception $e) {
            error_log('Disable Open Sans error: ' . $e->getMessage());
        }
    }
    
    /**
     * 限制非管理员访问WordPress后台
     * @author ifyn
     * @return void
     */
    public function restrict_admin_access() {
        try {
            if (!current_user_can('manage_options') && '/wp-admin/admin-ajax.php' != $_SERVER['PHP_SELF']) {
                wp_redirect(home_url());
                exit;
            }
        } catch (Exception $e) {
            error_log('Restrict admin access error: ' . $e->getMessage());
        }
    }
    
    /**
     * 禁用RSS订阅
     * @author ifyn
     * @return void
     */
    public function disable_feed() {
        try {
            if (is_ssl()) {
                wp_die(__('RSS feed is disabled.'));
            } else {
                wp_redirect(home_url());
                exit;
            }
        } catch (Exception $e) {
            error_log('Disable feed error: ' . $e->getMessage());
        }
    }
    
    /**
     * 禁用日期归档页面
     * @author ifyn
     * @return void
     */
    public function disable_date_archives() {
        try {
            if (is_date() || is_day()) {
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                nocache_headers();
            }
        } catch (Exception $e) {
            error_log('Disable date archives error: ' . $e->getMessage());
        }
    }
}