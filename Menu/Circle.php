<?php

namespace islide\Menu;

class Circle {

    public function init() {
        // 注册自定义文章类型
        add_action('init', [__CLASS__,  'register_circle_post_type']);

        // 注册圈子分类
        add_action('init', [__CLASS__,  'register_circle_category_taxonomy']);

        // 注册话题分类
        add_action('init', [__CLASS__,  'register_topic_taxonomy']);

        // 自定义后台子菜单
        add_action('admin_menu', [__CLASS__,  'reset_circle_submenus'], 100);
        
        add_action( 'save_post', [__CLASS__,  'set_default_term_for_circle'], 10, 2 );
        
        // 注册圈子元框
        add_action('csf_loaded', [$this, 'register_circle_metabox']);
    }

    // 注册圈子文章类型
    public static  function register_circle_post_type() {
        $circle_args = [
            'description'         => __('这是一个用于圈子的自定义文章类型。', 'text-domain'),
            'public'              => true,
            'publicly_queryable'  => true,
            'exclude_from_search' => false,
            'show_in_nav_menus'   => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_admin_bar'   => true,
            'menu_position'       => 5,
            'menu_icon'           => 'dashicons-groups',
            'can_export'          => true,
            'show_in_rest'        => true,
            'hierarchical'        => false,
            'has_archive'         => true,
            'query_var'           => 'circle',
            'capability_type'     => 'post',
            'rewrite'             => [
                'slug'       => 'circle',
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
                'name'                  => __('圈子', 'text-domain'),
                'singular_name'         => __('圈子', 'text-domain'),
                'menu_name'             => __('圈子', 'text-domain'),
                'name_admin_bar'        => __('圈子', 'text-domain'),
                'add_new'               => __('添加帖子', 'text-domain'),
                'add_new_item'          => __('添加新帖子', 'text-domain'),
                'edit_item'             => __('编辑帖子', 'text-domain'),
                'new_item'              => __('新帖子', 'text-domain'),
                'view_item'             => __('查看帖子', 'text-domain'),
                'search_items'          => __('搜索帖子', 'text-domain'),
                'not_found'             => __('未找到帖子', 'text-domain'),
                'not_found_in_trash'    => __('回收站中未找到帖子', 'text-domain'),
                'all_items'             => __('所有帖子', 'text-domain'),
                'featured_image'        => __('特色图片', 'text-domain'),
                'set_featured_image'    => __('设置特色图片', 'text-domain'),
                'remove_featured_image' => __('移除特色图片', 'text-domain'),
                'use_featured_image'    => __('用作特色图片', 'text-domain'),
                'insert_into_item'      => __('插入到帖子中', 'text-domain'),
                'uploaded_to_this_item' => __('上传到此帖子', 'text-domain'),
                'views'                 => __('筛选帖子列表', 'text-domain'),
                'pagination'            => __('帖子列表导航', 'text-domain'),
                'list'                  => __('帖子列表', 'text-domain'),
            ],
        ];

        register_post_type('circle', $circle_args);
    }

    // 注册圈子分类
    public static  function register_circle_category_taxonomy() {
        $circle_category_args = [
            'labels'            => [
                'name'              => __('圈子分类', 'text-domain'),
                'singular_name'     => __('圈子分类', 'text-domain'),
                'search_items'      => __('搜索圈子分类', 'text-domain'),
                'all_items'         => __('所有圈子分类', 'text-domain'),
                'parent_item'       => __('父分类', 'text-domain'),
                'parent_item_colon' => __('父分类：', 'text-domain'),
                'edit_item'         => __('编辑圈子分类', 'text-domain'),
                'update_item'       => __('更新圈子分类', 'text-domain'),
                'add_new_item'      => __('添加新圈子分类', 'text-domain'),
                'new_item_name'     => __('新圈子分类名称', 'text-domain'),
                'menu_name'         => __('圈子分类', 'text-domain'),
            ],
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'query_var'           => 'circle_cat',
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => [
                'slug' => 'circle',
            ],
        ];

        register_taxonomy('circle_cat', 'circle', $circle_category_args);
    }

    // 注册话题分类
    public static  function register_topic_taxonomy() {
        $topic_args = [
            'labels'            => [
                'name'              => __('话题', 'text-domain'),
                'singular_name'     => __('话题', 'text-domain'),
                'search_items'      => __('搜索话题', 'text-domain'),
                'all_items'         => __('所有话题', 'text-domain'),
                'edit_item'         => __('编辑话题', 'text-domain'),
                'update_item'       => __('更新话题', 'text-domain'),
                'add_new_item'      => __('添加新话题', 'text-domain'),
                'new_item_name'     => __('新话题名称', 'text-domain'),
                'menu_name'         => __('话题', 'text-domain'),
            ],
            'hierarchical'      => false,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => [
                'slug' => 'topic',
            ],
        ];

        register_taxonomy('topic', 'circle', $topic_args);
    }

    // 自定义子菜单
    public static  function reset_circle_submenus() {
        $parent_slug = 'edit.php?post_type=circle';

        global $submenu;
        if (isset($submenu[$parent_slug])) {
            unset($submenu[$parent_slug]);
        }

        add_submenu_page($parent_slug, __('所有帖子', 'text-domain'), __('所有帖子', 'text-domain'), 'manage_options', 'edit.php?post_type=circle');
        add_submenu_page($parent_slug, __('添加帖子', 'text-domain'), __('添加帖子', 'text-domain'), 'manage_options', 'post-new.php?post_type=circle');
        add_submenu_page($parent_slug, __('圈子分类', 'text-domain'), __('圈子分类', 'text-domain'), 'manage_options', 'edit-tags.php?taxonomy=circle_cat&post_type=circle');
        add_submenu_page($parent_slug, __('话题', 'text-domain'), __('话题', 'text-domain'), 'manage_options', 'edit-tags.php?taxonomy=topic&post_type=circle');
    }
    
    
    public static function set_default_term_for_circle( $post_id, $post ) {
    if ( $post->post_type !== 'circle' ) {
        return;
    }

    $default_term_id = 105; // 替换为你的默认分类 ID
    $current_terms = wp_get_post_terms( $post_id, 'circle_cat' );

    if ( empty( $current_terms ) ) {
        wp_set_post_terms( $post_id, array( $default_term_id ), 'circle_cat' );
    }
    }
    
    /**
     * 注册圈子元框
     * @author ifyn
     * @return void
     */
    public function register_circle_metabox(){
        try {
            $prefix = 'single_circle_metabox';
            
            //圈子附加信息
            \CSF::createMetabox($prefix, array(
                'title'     => '圈子设置',
                'post_type' => array('circle'),
                'context'   => 'side',
                'data_type' => 'serialize',
                'nav'       => 'inline',
                'theme'     => 'light'
            ));
            
            \CSF::createSection($prefix, array(
                'fields' => array(
                    array(
                        'id'         => 'circle_type',
                        'type'       => 'radio',
                        'title'      => '帖子类型',
                        'inline'     => true,
                        'options'    => array(
                            'normal'   => '普通帖子',
                            'question' => '提问',
                            'vote'     => '投票',
                        ),
                        'default'    => 'normal',
                    ),
                    array(
                        'id'         => 'vote_title',
                        'type'       => 'text',
                        'title'      => '投票标题',
                        'dependency' => array(
                            array('circle_type', '==', 'vote')
                        ),
                    ),
                    array(
                        'id'         => 'vote_options',
                        'type'       => 'group',
                        'title'      => '投票选项',
                        'button_title' => '添加选项',
                        'accordion_title_number' => true,
                        'fields'     => array(
                            array(
                                'id'    => 'option_text',
                                'type'  => 'text',
                                'title' => '选项内容',
                            ),
                        ),
                        'dependency' => array(
                            array('circle_type', '==', 'vote')
                        ),
                    ),
                    array(
                        'id'         => 'vote_end_time',
                        'type'       => 'datetime',
                        'title'      => '截止时间',
                        'settings'   => array(
                            'enableTime' => true,
                            'dateFormat' => 'Y-m-d H:i:s',
                            'time_24hr'  => true,
                        ),
                        'dependency' => array(
                            array('circle_type', '==', 'vote')
                        ),
                    ),
                    array(
                        'id'         => 'question_reward',
                        'type'       => 'spinner',
                        'title'      => '悬赏积分',
                        'default'    => 0,
                        'min'        => 0,
                        'dependency' => array(
                            array('circle_type', '==', 'question')
                        ),
                    ),
                )
            ));
        } catch (\Exception $e) {
            error_log('Register circle metabox error: ' . $e->getMessage());
        }
    }
    

   
   

    
}