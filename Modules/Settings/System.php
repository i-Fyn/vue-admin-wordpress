<?php
namespace islide\Modules\Settings;

//系统相关设置

class System{

    //设置主KEY
    public static $prefix = 'islide_main_options';

    public function init(){ 
        
        $this->system_options_page();
    }
    
    /**
     * 系统相关
     *
     * @return void
     * 
     * @version 1.0.0
     * @since 2023
     */
    public function system_options_page(){
        
        \CSF::createSection(self::$prefix, array(
            'id'    => 'islide_system_options',
            'title' => '系统相关',
            'icon'  => 'fas fa-robot',
        ));
        
        //加载系统优化设置
        $this->system_optimize_settings();
    }
    
    //常规设置
    public function system_optimize_settings(){
        \CSF::createSection(self::$prefix, array(
            'parent'      => 'islide_system_options',
            'title'       => '系统优化',
            'icon'        => 'fab fa-wordpress',
            'fields'      => array(
                array(
                    'id'     => 'islide_system_optimize',
                    'type'   => 'fieldset',
                    'title'  => '',
                    'fields' => array(
                        array(
                            'id'      => 'optimize_remove_meta_tags',
                            'type'    => 'switcher',
                            'title'   => '移除一些不必要的HTML头部标签和链接',
                            'desc'    => '这些标签和链接对于网站的正常运行并不是必需的，而且有些甚至可能会暴露网站的一些信息，因此移除它们可以提高网站的安全性和性能。',
                            'default' => true,
                        ),
                        array(
                            'id'      => 'optimize_disable_embeds',
                            'type'    => 'switcher',
                            'title'   => '禁用embed功能',
                            'desc'    => '禁用WordPress中的嵌入式内容（embeds）功能，这些功能可能会增加网站的负载和安全风险，而且对于一些网站来说并不是必需的，因此禁用它们可以提高网站的性能和安全。',
                            'default' => true,
                        ),
                        array(
                            'id'      => 'optimize_remove_styles',
                            'type'    => 'switcher',
                            'title'   => '禁用wordpress全局CSS样式',
                            'desc'    => '用于移除一些WordPress自带的CSS样式表，以提高网站性能和减少页面加载时间。',
                            'default' => true,
                        ),
                        array(
                            'id'      => 'optimize_disable_image_sizes',
                            'type'    => 'switcher',
                            'title'   => '禁用自动生成的图片尺寸',
                            'desc'    => '禁用WordPress中默认定义的一些图片尺寸，以减少服务器存储和带宽消耗。禁用这些尺寸可以避免WordPress在上传图片时自动生成多余的图片尺寸，从而减少不必要的文件和数据库存储。这对于需要优化网站性能和减少服务器负载的站点来说非常有用。',
                            'default' => true,
                        ),
                        array(
                            'id'      => 'optimize_remove_menu_class',
                            'type'    => 'switcher',
                            'title'   => '移除默认导航菜单和页面中添加的CSS类和ID',
                            'desc'    => '用于在导航菜单和页面中移除默认添加的CSS类和ID，这些CSS类和ID太多显得太过凌乱',
                            'default' => true,
                        ),
                        array(
                            'id'      => 'optimize_disable_emoji',
                            'type'    => 'switcher',
                            'title'   => '禁用WordPress中的Emoji表情功能',
                            'subtitle'=> '主题自带的表情不受影响',
                            'desc'    => 'Emoji表情也会增加页面加载时间和带宽消耗，特别是在移动设备上访问网站时。因此，禁用Emoji表情功能以提高网站性能和速度。',
                            'default' => true,
                        ),
                        array(
                            'id'      => 'optimize_disable_pingback',
                            'type'    => 'switcher',
                            'title'   => '禁用WordPress中的Pingback功能',
                            'desc'    => 'Pingback是一种自动化的远程通信协议，用于在博客文章之间建立链接。当某篇文章中包含另一篇文章的链接时，WordPress会自动发送Pingback请求给被链接的文章，以通知其被链接。然而，Pingback请求也会增加页面加载时间和带宽消耗，特别是在大量链接的情况下。因此，一些站点可能希望禁用Pingback功能以提高网站性能和速度。',
                            'default' => true,
                        ),
                        array(
                            'id'      => 'optimize_disable_autosave',
                            'type'    => 'switcher',
                            'title'   => '禁用WordPress中的自动保存功能',
                            'desc'    => '禁用就不会自动保存文章或页面的草稿，从而减少服务器负载和数据库存储，以提高网站性能和速度。',
                            'default' => true,
                        ),
                        array(
                            'id'      => 'optimize_disable_revisions',
                            'type'    => 'switcher',
                            'title'   => '禁用WordPress中的文章或页面修订版本',
                            'desc'    => '修订版本会增加数据库存储和查询负载，禁用修订版本以提高网站性能和速度。',
                            'default' => true,
                        ),
                        array(
                            'id'      => 'optimize_disable_wp_update',
                            'type'    => 'switcher',
                            'title'   => '禁用WordPress的自动更新功能',
                            'desc'    => '禁用WordPress的自动更新，禁用插件自动更新，禁用自动主题更新。禁用用于确保网站不会在不经过管理员许可的情况下自动更新，从而避免潜在的兼容性问题或其他问题。',
                            'default' => true,
                        ),
                        array(
                            'id'      => 'optimize_disable_xmlrpc',
                            'type'    => 'switcher',
                            'title'   => '禁用WordPress的XML-RPC功能',
                            'desc'    => 'XML-RPC可能成为黑客攻击的入口，禁用它可以提高网站的安全性。',
                            'default' => true,
                        ),
                        array(
                            'id'      => 'optimize_disable_admin_bar',
                            'type'    => 'switcher',
                            'title'   => '禁用WordPress管理工具栏',
                            'desc'    => '管理工具栏显示在网站的顶部的黑条，隐藏以提供更清晰的用户体验。',
                            'default' => true,
                        ),
                        array(
                            'id'      => 'optimize_disable_gutenberg',
                            'type'    => 'switcher',
                            'title'   => '禁用古腾堡区块编辑器',
                            'desc'    => '有些管理员可能更喜欢使用传统的编辑器，或者不需要使用区块编辑器。可以禁用区块编辑器，使管理员可以使用传统的编辑器。',
                            'default' => false,
                        ),
                        array(
                            'id'      => 'optimize_disable_widgets_block',
                            'type'    => 'switcher',
                            'title'   => '禁用WordPress的小工具区块编辑器',
                            'desc'    => '有些管理员可能更喜欢使用传统的小工具编辑器，或者根本不需要使用小工具区块编辑器。可以禁用小工具区块编辑器，使管理员可以使用传统的小工具编辑器。',
                            'default' => false,
                        ),
                        array(
                            'id'      => 'optimize_disable_open_sans',
                            'type'    => 'switcher',
                            'title'   => '禁用Google Open Sans字体',
                            'desc'    => '加速网站加载速度：Open Sans字体需要从Google服务器加载，而在中国访问Google服务器的速度可能会很慢，从而导致网站加载缓慢。禁用Open Sans字体可以减少网站需要加载的资源，从而加速网站的加载速度，提高用户体验。',
                            'default' => true,
                        ),
                        array(
                            'id'      => 'optimize_disable_rss',
                            'type'    => 'switcher',
                            'title'   => '禁用网站的RSS和Atom订阅功能',
                            'desc'    => '禁用了后用户尝试访问域名/feed时，将会收到一个HTTP 404错误，表示该页面不存在。这样可以确保网站的内容不被未经授权的用户访问，提高网站的安全性。',
                            'default' => true,
                        ),
                    )
                )
            )
        ));
    }
}