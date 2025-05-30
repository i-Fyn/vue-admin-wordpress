<?php
namespace islide\Menu;

class Video {

    public function init() {
        // 注册自定义文章类型
        add_action('init', [__CLASS__, 'register_video_and_episode_post_types'], 10);

        // 注册自定义分类法
        add_action('init', [__CLASS__, 'register_video_taxonomies'], 10);

        // 管理视频菜单链接
        add_action('admin_menu', [__CLASS__, 'manage_video_menu_links'], 10);

    }

    
    public static function register_video_and_episode_post_types(){
    // 注册 Video 自定义文章类型
    $video_args = [
        'description'         => __('这是一个用于视频的自定义文章类型。', 'islide'),
        'public'              => true,
        'publicly_queryable'  => true,
        'exclude_from_search' => false,
        'show_in_nav_menus'   => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_admin_bar'   => true,
        'menu_position'       => 5,
        'menu_icon'           => 'dashicons-video-alt2',
        'can_export'          => true,
        'show_in_rest'        => true,
        'hierarchical'        => false,
        'has_archive'         => true,
        'query_var'           => 'video',
        'capability_type'     => 'post',
        'rewrite'             => [
            'slug'       => 'video',
            'with_front' => false,
            'pages'      => true,
            'feeds'      => true,
        ],
        'supports'            => [
            'title', 
            'editor', 
            'excerpt', 
            'author', 
            'thumbnail', 
            'comments', 
            'custom-fields', 
            'revisions',
        ],
        'labels'              => [
            'name'                  => __('视频', 'islide'),
            'singular_name'         => __('视频', 'islide'),
            'menu_name'             => __('视频', 'islide'),
            'name_admin_bar'        => __('视频', 'islide'),
            'add_new'               => __('添加视频', 'islide'),
            'add_new_item'          => __('添加新视频', 'islide'),
            'edit_item'             => __('编辑视频', 'islide'),
            'new_item'              => __('新视频', 'islide'),
            'view_item'             => __('查看视频', 'islide'),
            'search_items'          => __('搜索视频', 'islide'),
            'not_found'             => __('未找到视频', 'islide'),
            'not_found_in_trash'    => __('回收站中未找到视频', 'islide'),
            'all_items'             => __('所有视频', 'islide'),
            'featured_image'        => __('特色图片', 'islide'),
            'set_featured_image'    => __('设置特色图片', 'islide'),
            'remove_featured_image' => __('移除特色图片', 'islide'),
            'use_featured_image'    => __('用作特色图片', 'islide'),
            'insert_into_item'      => __('插入到视频中', 'islide'),
            'uploaded_to_this_item' => __('上传到此视频', 'islide'),
            'views'                 => __('筛选视频列表', 'islide'),
            'pagination'            => __('视频列表导航', 'islide'),
            'list'                  => __('视频列表', 'islide'),
        ],
    ];

    register_post_type('video', $video_args);

    // 注册 Episode 自定义文章类型
    $episode_args = [
        'description'         => __('这是一个用于视频剧集的自定义文章类型。', 'islide'),
        'public'              => true,
        'publicly_queryable'  => true,
        'exclude_from_search' => false,
        'show_in_nav_menus'   => true,
        'show_ui'             => true,
        'show_in_menu'        => false, // 不显示在主菜单中
        'show_in_admin_bar'   => true,
        'menu_position'       => 6,
        'menu_icon'           => 'dashicons-media-document',
        'can_export'          => true,
        'show_in_rest'        => true,
        'hierarchical'        => false,
        'has_archive'         => false,
        'query_var'           => 'episode',
        'capability_type'     => 'post',
        'rewrite'             => [
            'slug'       => 'episode',
            'with_front' => false,
            'pages'      => true,
            'feeds'      => false,
        ],
        'supports'            => [
            'title', 
            'editor', 
            'excerpt', 
            'author', 
            'thumbnail', 
            'comments', 
            'custom-fields', 
            'revisions',
        ],
        'labels'              => [
            'name'                  => __('剧集', 'islide'),
            'singular_name'         => __('剧集', 'islide'),
            'menu_name'             => __('剧集', 'islide'),
            'name_admin_bar'        => __('剧集', 'islide'),
            'add_new'               => __('添加剧集', 'islide'),
            'add_new_item'          => __('添加新剧集', 'islide'),
            'edit_item'             => __('编辑剧集', 'islide'),
            'new_item'              => __('新剧集', 'islide'),
            'view_item'             => __('查看剧集', 'islide'),
            'search_items'          => __('搜索剧集', 'islide'),
            'not_found'             => __('未找到剧集', 'islide'),
            'not_found_in_trash'    => __('回收站中未找到剧集', 'islide'),
            'all_items'             => __('所有剧集', 'islide'),
            'featured_image'        => __('特色图片', 'islide'),
            'set_featured_image'    => __('设置特色图片', 'islide'),
            'remove_featured_image' => __('移除特色图片', 'islide'),
            'use_featured_image'    => __('用作特色图片', 'islide'),
            'insert_into_item'      => __('插入到剧集中', 'islide'),
            'uploaded_to_this_item' => __('上传到此剧集', 'islide'),
            'views'                 => __('筛选剧集列表', 'islide'),
            'pagination'            => __('剧集列表导航', 'islide'),
            'list'                  => __('剧集列表', 'islide'),
        ],
    ];

    register_post_type('episode', $episode_args);
}



# 在 'init' 钩子上注册自定义分类法
    public static function register_video_taxonomies(){
    // 注册视频分类
    if (taxonomy_exists('video_cat')) {
        return;
    }

    $video_cat_labels = [
        'name'              => __('视频分类', 'islide'),
        'singular_name'     => __('视频分类', 'islide'),
        'search_items'      => __('搜索分类', 'islide'),
        'all_items'         => __('所有分类', 'islide'),
        'parent_item'       => __('父分类', 'islide'),
        'parent_item_colon' => __('父分类：', 'islide'),
        'edit_item'         => __('编辑分类', 'islide'),
        'update_item'       => __('更新分类', 'islide'),
        'add_new_item'      => __('添加新分类', 'islide'),
        'new_item_name'     => __('新分类名称', 'islide'),
        'menu_name'         => __('视频分类', 'islide'),
    ];

    register_taxonomy('video_cat', ['video'], [
        'labels'            => $video_cat_labels,
        'hierarchical'      => true, // 分层分类法（类似于文章类别）
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'           => 'video_cat',
        'rewrite'           => [
            'slug'       => 'video',
            'with_front' => false,
        ],
    ]);

    // 注册视频系列
    if (taxonomy_exists('video_season')) {
        return;
    }

    $video_season_labels = [
        'name'              => __('视频系列', 'islide'),
        'singular_name'     => __('视频系列', 'islide'),
        'search_items'      => __('搜索系列', 'islide'),
        'all_items'         => __('所有系列', 'islide'),
        'parent_item'       => __('父系列', 'islide'),
        'parent_item_colon' => __('父系列：', 'islide'),
        'edit_item'         => __('编辑系列', 'islide'),
        'update_item'       => __('更新系列', 'islide'),
        'add_new_item'      => __('添加新系列', 'islide'),
        'new_item_name'     => __('新系列名称', 'islide'),
        'menu_name'         => __('视频系列', 'islide'),
    ];

    register_taxonomy('video_season', ['video'], [
        'labels'            => $video_season_labels,
        'hierarchical'      => true, // 分层分类法
        'show_ui'           => true,
        'show_admin_column' => true,
        'rewrite'           => [
            'slug'       => 'video-series',
            'with_front' => false,
        ],
    ]);
}

/**
 * 自定义视频菜单和子菜单
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
    public static function manage_video_menu_links() {
    global $submenu;

    // 父菜单 slug
    $parent_slug = 'edit.php?post_type=video';

    // 删除现有的子菜单
    if (!empty($submenu[$parent_slug])) {
        unset($submenu[$parent_slug]);
    }

    // 定义子菜单
    $submenus = [
        [
            'page_title' => __('所有视频', 'islide'),
            'menu_title' => __('所有视频', 'islide'),
            'capability' => 'manage_options',
            'menu_slug'  => 'edit.php?post_type=video',
        ],
        [
            'page_title' => __('添加一个视频', 'islide'),
            'menu_title' => __('添加一个视频', 'islide'),
            'capability' => 'manage_options',
            'menu_slug'  => 'post-new.php?post_type=video',
        ],
        [
            'page_title' => __('标签', 'islide'),
            'menu_title' => __('标签', 'islide'),
            'capability' => 'manage_options',
            'menu_slug'  => 'edit-tags.php?taxonomy=post_tag&post_type=video',
        ],
        [
            'page_title' => __('视频分类', 'islide'),
            'menu_title' => __('视频分类', 'islide'),
            'capability' => 'manage_options',
            'menu_slug'  => 'edit-tags.php?taxonomy=video_cat&post_type=video',
        ],
        [
            'page_title' => __('视频系列', 'islide'),
            'menu_title' => __('视频系列', 'islide'),
            'capability' => 'manage_options',
            'menu_slug'  => 'edit-tags.php?taxonomy=video_season&post_type=video',
        ],
        [
            'page_title' => __('视频剧集', 'islide'),
            'menu_title' => __('视频剧集', 'islide'),
            'capability' => 'manage_options',
            'menu_slug'  => 'edit.php?post_type=episode&post_parent=0',
        ],
    ];

    // 添加子菜单
    foreach ($submenus as $submenu_data) {
        add_submenu_page(
            $parent_slug,
            $submenu_data['page_title'], // 页面标题
            $submenu_data['menu_title'], // 菜单标题
            $submenu_data['capability'], // 权限
            $submenu_data['menu_slug']   // 菜单 slug
        );
    }
}





}