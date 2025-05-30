<?php
namespace islide\Menu;

class Notice {

    public function init() {
        // 注册公告文章类型
        add_action('init', [__CLASS__, 'register_notice_post_type']);
        
    }

    // 注册公告文章类型
    public static function register_notice_post_type() {
        $labels = [
            'name'               => __('公告', 'text-domain'),
            'singular_name'      => __('公告', 'text-domain'),
            'menu_name'          => __('公告', 'text-domain'),
            'add_new'            => __('新建公告', 'text-domain'),
            'add_new_item'       => __('添加新公告', 'text-domain'),
            'edit_item'          => __('编辑公告', 'text-domain'),
            'new_item'           => __('新公告', 'text-domain'),
            'view_item'          => __('查看公告', 'text-domain'),
            'search_items'       => __('搜索公告', 'text-domain'),
            'not_found'          => __('未找到公告', 'text-domain'),
            'not_found_in_trash' => __('回收站中无公告', 'text-domain')
        ];

        $args = [
            'labels'            => $labels,
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'menu_position'     => 25,
            'menu_icon'         => 'dashicons-megaphone',
            'supports'          => ['title', 'editor', 'thumbnail'],
            'show_in_rest'      => true,
            'rewrite'           => ['slug' => 'notice']
        ];

        register_post_type('notice', $args);
    }
}