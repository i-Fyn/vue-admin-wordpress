<?php
namespace islide\Menu;

class Book {

    public function init() {
        // 注册自定义文章类型
        add_action('init', [__CLASS__, 'register_book_and_passage_post_types'], 10);

        // 注册自定义分类法
        add_action('init', [__CLASS__, 'register_book_taxonomies'], 10);

        // 管理书籍菜单链接
        add_action('admin_menu', [__CLASS__, 'manage_book_menu_links'], 10);

    }

    
    public static function register_book_and_passage_post_types(){
    // 注册 Book 自定义文章类型
    $book_args = [
        'description'         => __('这是一个用于书籍的自定义文章类型。', 'islide'),
        'public'              => true,
        'publicly_queryable'  => true,
        'exclude_from_search' => false,
        'show_in_nav_menus'   => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_admin_bar'   => true,
        'menu_position'       => 5,
        'menu_icon'           => 'dashicons-book-alt2',
        'can_export'          => true,
        'show_in_rest'        => true,
        'hierarchical'        => false,
        'has_archive'         => true,
        'query_var'           => 'book',
        'capability_type'     => 'post',
        'rewrite'             => [
            'slug'       => 'book',
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
            'name'                  => __('书籍', 'islide'),
            'singular_name'         => __('书籍', 'islide'),
            'menu_name'             => __('书籍', 'islide'),
            'name_admin_bar'        => __('书籍', 'islide'),
            'add_new'               => __('添加书籍', 'islide'),
            'add_new_item'          => __('添加新书籍', 'islide'),
            'edit_item'             => __('编辑书籍', 'islide'),
            'new_item'              => __('新书籍', 'islide'),
            'view_item'             => __('查看书籍', 'islide'),
            'search_items'          => __('搜索书籍', 'islide'),
            'not_found'             => __('未找到书籍', 'islide'),
            'not_found_in_trash'    => __('回收站中未找到书籍', 'islide'),
            'all_items'             => __('所有书籍', 'islide'),
            'featured_image'        => __('特色图片', 'islide'),
            'set_featured_image'    => __('设置特色图片', 'islide'),
            'remove_featured_image' => __('移除特色图片', 'islide'),
            'use_featured_image'    => __('用作特色图片', 'islide'),
            'insert_into_item'      => __('插入到书籍中', 'islide'),
            'uploaded_to_this_item' => __('上传到此书籍', 'islide'),
            'views'                 => __('筛选书籍列表', 'islide'),
            'pagination'            => __('书籍列表导航', 'islide'),
            'list'                  => __('书籍列表', 'islide'),
        ],
    ];

    register_post_type('book', $book_args);

    // 注册 Episode 自定义文章类型
    $passage_args = [
        'description'         => __('这是一个用于书籍章节的自定义文章类型。', 'islide'),
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
        'query_var'           => 'passage',
        'capability_type'     => 'post',
        'rewrite'             => [
            'slug'       => 'passage',
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
            'name'                  => __('章节', 'islide'),
            'singular_name'         => __('章节', 'islide'),
            'menu_name'             => __('章节', 'islide'),
            'name_admin_bar'        => __('章节', 'islide'),
            'add_new'               => __('添加章节', 'islide'),
            'add_new_item'          => __('添加新章节', 'islide'),
            'edit_item'             => __('编辑章节', 'islide'),
            'new_item'              => __('新章节', 'islide'),
            'view_item'             => __('查看章节', 'islide'),
            'search_items'          => __('搜索章节', 'islide'),
            'not_found'             => __('未找到章节', 'islide'),
            'not_found_in_trash'    => __('回收站中未找到章节', 'islide'),
            'all_items'             => __('所有章节', 'islide'),
            'featured_image'        => __('特色图片', 'islide'),
            'set_featured_image'    => __('设置特色图片', 'islide'),
            'remove_featured_image' => __('移除特色图片', 'islide'),
            'use_featured_image'    => __('用作特色图片', 'islide'),
            'insert_into_item'      => __('插入到章节中', 'islide'),
            'uploaded_to_this_item' => __('上传到此章节', 'islide'),
            'views'                 => __('筛选章节列表', 'islide'),
            'pagination'            => __('章节列表导航', 'islide'),
            'list'                  => __('章节列表', 'islide'),
        ],
    ];

    register_post_type('passage', $passage_args);
}



# 在 'init' 钩子上注册自定义分类法
    public static function register_book_taxonomies(){
    // 注册书籍分类
    if (taxonomy_exists('book_cat')) {
        return;
    }

    $book_cat_labels = [
        'name'              => __('书籍分类', 'islide'),
        'singular_name'     => __('书籍分类', 'islide'),
        'search_items'      => __('搜索分类', 'islide'),
        'all_items'         => __('所有分类', 'islide'),
        'parent_item'       => __('父分类', 'islide'),
        'parent_item_colon' => __('父分类：', 'islide'),
        'edit_item'         => __('编辑分类', 'islide'),
        'update_item'       => __('更新分类', 'islide'),
        'add_new_item'      => __('添加新分类', 'islide'),
        'new_item_name'     => __('新分类名称', 'islide'),
        'menu_name'         => __('书籍分类', 'islide'),
    ];

    register_taxonomy('book_cat', ['book'], [
        'labels'            => $book_cat_labels,
        'hierarchical'      => true, // 分层分类法（类似于文章类别）
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'           => 'book_cat',
        'rewrite'           => [
            'slug'       => 'book',
            'with_front' => false,
        ],
    ]);

    // 注册书籍系列
    if (taxonomy_exists('book_season')) {
        return;
    }

    $book_season_labels = [
        'name'              => __('书籍系列', 'islide'),
        'singular_name'     => __('书籍系列', 'islide'),
        'search_items'      => __('搜索系列', 'islide'),
        'all_items'         => __('所有系列', 'islide'),
        'parent_item'       => __('父系列', 'islide'),
        'parent_item_colon' => __('父系列：', 'islide'),
        'edit_item'         => __('编辑系列', 'islide'),
        'update_item'       => __('更新系列', 'islide'),
        'add_new_item'      => __('添加新系列', 'islide'),
        'new_item_name'     => __('新系列名称', 'islide'),
        'menu_name'         => __('书籍系列', 'islide'),
    ];

    register_taxonomy('book_season', ['book'], [
        'labels'            => $book_season_labels,
        'hierarchical'      => true, // 分层分类法
        'show_ui'           => true,
        'show_admin_column' => true,
        'rewrite'           => [
            'slug'       => 'book-series',
            'with_front' => false,
        ],
    ]);
}

/**
 * 自定义书籍菜单和子菜单
 *
 * @since  1.0.0
 * @access public
 * @return void
 */
    public static function manage_book_menu_links() {
    global $submenu;

    // 父菜单 slug
    $parent_slug = 'edit.php?post_type=book';

    // 删除现有的子菜单
    if (!empty($submenu[$parent_slug])) {
        unset($submenu[$parent_slug]);
    }

    // 定义子菜单
    $submenus = [
        [
            'page_title' => __('所有书籍', 'islide'),
            'menu_title' => __('所有书籍', 'islide'),
            'capability' => 'manage_options',
            'menu_slug'  => 'edit.php?post_type=book',
        ],
        [
            'page_title' => __('添加一个书籍', 'islide'),
            'menu_title' => __('添加一个书籍', 'islide'),
            'capability' => 'manage_options',
            'menu_slug'  => 'post-new.php?post_type=book',
        ],
        [
            'page_title' => __('标签', 'islide'),
            'menu_title' => __('标签', 'islide'),
            'capability' => 'manage_options',
            'menu_slug'  => 'edit-tags.php?taxonomy=post_tag&post_type=book',
        ],
        [
            'page_title' => __('书籍分类', 'islide'),
            'menu_title' => __('书籍分类', 'islide'),
            'capability' => 'manage_options',
            'menu_slug'  => 'edit-tags.php?taxonomy=book_cat&post_type=book',
        ],
        [
            'page_title' => __('书籍系列', 'islide'),
            'menu_title' => __('书籍系列', 'islide'),
            'capability' => 'manage_options',
            'menu_slug'  => 'edit-tags.php?taxonomy=book_season&post_type=book',
        ],
        [
            'page_title' => __('书籍章节', 'islide'),
            'menu_title' => __('书籍章节', 'islide'),
            'capability' => 'manage_options',
            'menu_slug'  => 'edit.php?post_type=passage&post_parent=0',
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