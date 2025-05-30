<?php namespace islide\Modules\Settings;
use islide\Modules\Common\Circle;
use islide\Modules\Common\User;
use islide\Modules\Common\CircleRelate;
/**
 * 分类设置
 * 
 * */
class Taxonomies{
    
    //设置主KEY
    public static $prefix = 'islide_tax';

    public function init(){
         // Create taxonomy options
        \CSF::createTaxonomyOptions( self::$prefix, array(
            'taxonomy'  => array( 'category', 'post_tag', 'video_cat','circle_cat','topic'),
            'data_type' => 'unserialize', // 序列化. `serialize` or `unserialize` 单个 id获取值
        ));
        
        add_action( 'csf_islide_tax_circle_saved', array($this,'save_circle_action'), 10, 2 );
        
        //注册分类设置
        $this->register_taxonomy_metabox();
        
        $this->register_taxonomy_circle_metabox();
        //表格
        add_action('admin_init', array($this,'custom_taxonomy_columns'));
    }
    
    
    // 添加分类ID列
    function custom_taxonomy_column($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb']; // 复选框
        unset($columns['cb']);
        $new_columns['id'] = 'ID';
        return array_merge($new_columns, $columns);
    }
    
    // 显示分类ID
    function custom_taxonomy_custom_content($value, $column_name, $tax_id) {
        if ($column_name === 'id') {
            return $tax_id;
        }
        
    }
    
    // 封装循环部分为函数
    function custom_taxonomy_columns() {
        $excluded_taxonomies = array('nav_menu', 'post_format', 'wp_theme', 'wp_template_part_area','link_category'); // 要排除的分类和标签类型
        $all_taxonomies = get_taxonomies(); // 获取所有已注册的分类和标签类型
        
        $taxonomies = array_diff($all_taxonomies, $excluded_taxonomies); // 排除不需要的分类和标签类型
        foreach ($taxonomies as $taxonomy) {
            add_filter('manage_edit-' . $taxonomy . '_columns', array($this, 'custom_taxonomy_column'));
            add_action('manage_' . $taxonomy . '_custom_column', array($this, 'custom_taxonomy_custom_content'), 10, 3);
        }
    }

    public function register_taxonomy_metabox(){
        $taxonomy = isset($_GET['taxonomy']) ? $_GET['taxonomy'] : null;
        $args = array(
            'title' => 'islide主题分类设置',
            'fields' => array(
                array(
                    'id'    => 'seo_title',
                    'type'  => 'text',
                    'title' => 'SEO标题',
                ),
                array(
                    'id'    => 'seo_keywords',
                    'type'  => 'text',
                    'title' => 'SEO关键词',
                ),
                array(
                    'id'    => 'islide_tax_img',
                    'type'  => 'upload',
                    'title' => '特色图',
                    'preview' => true,
                    'library' => 'image',
                ),
                array(
                    'id'    => 'islide_tax_cover',
                    'type'  => 'upload',
                    'title' => '特色背景图',
                    'preview' => true,
                    'library' => 'image',
                ),
                array(
                    'id'          => 'islide_tax_sticky_posts',
                    'type'        => 'select',
                    'title'       => '分类置顶文章',
                    'placeholder' => '搜索选择置顶文章',
                    'chosen'      => true,
                    'ajax'        => true,
                    'multiple'    => true,
                    'sortable'    => true,
                    'options'     => 'posts',
                    'query_args'  => array(
                        'post_type'  => array('post','video','circle')
                    ),
                    'settings'   => array(
                        'min_length' => 1
                    )
                ),
               
            )
        );
        
        if($taxonomy == 'circle_cat' || $taxonomy == 'topic') {
            unset($args['fields'][8]);
            unset($args['fields'][6]);
            unset($args['fields'][5]);
        }
        
        \CSF::createSection( self::$prefix, $args);

    }
    
    public function register_taxonomy_circle_metabox(){
        //serialize
        // $meta = get_term_meta( 11, 'islide_tax', true );
        
        //unserialize
        //print_r(get_term_meta( 11, 'seo_title', true ));
         //Create a section
         
        \CSF::createTaxonomyOptions('islide_tax_circle', array(
            'taxonomy'  => array('circle_cat'),
            'data_type' => 'unserialize', // 序列化. `serialize` or `unserialize` 单个 id获取值
        ));
        
        $circle_cats = Circle::get_circle_cats();
        $cats = array();
        
        if(!isset($circle_cats['error'])) {
            foreach ($circle_cats as $k => $v) {
                $cats[$v['name']] = $v['name'];
            }
        }
        
        $roles = User::get_user_roles();

        $roles_options = array();
        
        foreach ($roles as $key => $value) {
            $roles_options[$key] = $value['name'];
        }
        
        $moment_roles = array(
            'admin' => '圈子创建者',
            'staff' => '圈子版主'
        );
        
        foreach ($roles as $key => $value) {
            $moment_roles[$key] = $value['name'];
        }
        
        $default_roles = array_keys($moment_roles);
        
        //圈子名字
        $circle_name = islide_get_option('circle_name');
         
        \CSF::createSection( 'islide_tax_circle', array(
            'title' => sprintf('islide主题%s设置',$circle_name), 
            'fields' => array(
                array(
                    'id'    => 'islide_circle_official',
                    'type'  => 'switcher',
                    'title'  => '官方圈认证',
                    'desc'  => '是否是官方的圈子，开启后会显示官方圈子标签',
                    'default' => false
                ),
                array(
                    'id'    => 'islide_circle_cat',
                    'type'  => 'radio',
                    'title'  => sprintf('选择%s分类',$circle_name), 
                    'options' => $cats,
                    'desc'  => '如果不选择分类，筛选中不会显示',
                    'inline' => true
                ),
                array(
                    'id'          => 'islide_circle_admin',
                    'type'        => 'select',
                    'title'       => '创建者',
                    'subtitle'    => '及圈子管理员、圈主',
                    'options'     => 'user',
                    'placeholder' => '输入用户ID、昵称、邮箱以搜索用户',
                    'ajax'        => true,
                    'chosen'      => true,
                    'default'     => 1,
                    'settings'    => array(
                        'min_length' => 1,
                        'width' => '50%',
                    )
                ),
                
                array(
                    'id'          => 'islide_circle_staff',
                    'type'        => 'select',
                    'title'       => '圈子版主',
                    'subtitle'    => '及版主',
                    'options'     => 'user',
                    'placeholder' => '输入用户ID、昵称、邮箱以搜索用户',
                    'ajax'        => true,
                    'chosen'      => true,
                    'multiple'    => true,
                    'sortable'    => true,
                    'settings'    => array(
                        'min_length' => 1,
                    )
                ),
                array(
                    'id'         => 'islide_circle_privacy',
                    'type'       => 'radio',
                    'title'      => sprintf('%s隐私',$circle_name),
                    'inline'     => true,
                    'options'    => array(
                        'public'   => sprintf('%s内帖子公开显示',$circle_name),
                        'private'  => sprintf('%s内帖子只对圈友开放',$circle_name),
                    ),
                    'default'    => 'public',
                ),
                array(
                    'id'         => 'islide_circle_type',
                    'type'       => 'radio',
                    'title'      => sprintf('%s类型',$circle_name),
                    'inline'     => true,
                    'options'    => array(
                        'free'   => '免费',
                        'money'  => '付费',
                        'credit' => '积分',
                        'roles'  => '专属',
                        'password'  => '密码',
                    ),
                    'default'    => 'free',
                ),
                array(
                    'id'       => 'islide_circle_password',
                    'type'       => 'text',
                    'title'    => '入圈密码',
                    'desc'       => '密码长度自定义',
                    'default'  => '1234',
                    'dependency' => array( 
                        array( 'islide_circle_type', '==', 'password' ),
                    )
                ),
                array(
                    'id'         => 'islide_circle_roles',
                    'type'       => 'checkbox',
                    'title'      => '请选择允许加入圈子的用户组',
                    'inline'     => true,
                    'options'    => $roles_options,
                    'desc'       => sprintf('如果您修改了此权限，请前往%s%s设置%s中重置以下该%s的数据。%s如果没有用户组，请前往%s用户设置%s进行设置','<a href="'.admin_url('/admin.php?page=b2_circle_data').'" target="_blank">',$circle_name,'</a>',$circle_name,'<br>','<a href="'.admin_url().'/admin.php?page=b2_normal_user" target="_blank">','</a>'),
                    'dependency'   => array(
                        array( 'islide_circle_type', '==', 'roles' )
                    ),
                ),
                array(
                    'id'        => 'islide_circle_pay_group',
                    'type'      => 'group',
                    'title'     => '圈子付费及积分支付',
                    'button_title' => '新增付费入圈时效',
                    'fields'    => array(
                        array(
                            'id'    => 'name',
                            'type'  => 'text',
                            'title' => sprintf(__('名称%s','islide'),'<span class="red">（必填）</span>'),
                            'desc' => sprintf('入圈支付时显示，比如 %s 等等','<code>月付</code>、<code>季付</code>、<code>年付</code>、<code>永久有效</code>')
                        ),
                        array(
                            'id'         => 'price',
                            'type'       => 'number',
                            'title'      => '购买价格',
                            'default'    => '',
                        ),
                        array(
                            'id'      => 'time',
                            'type'    => 'spinner',
                            'title'   => '有效期',
                            'desc'    => '加入圈子有效期限。填<code>0</code>则为永久有效',
                            'min'     => 0,
                            'step'    => 1,
                            'default' => '',
                            'unit'    => '天',
                        ),
                        array(
                            'id'          => 'discount',
                            'type'        => 'spinner',
                            'title'       => '会员入圈折扣比例（暂未实现）',
                            'min'         => 0,
                            'max'         => 100,
                            'step'        => 1,
                            'unit'        => '%',
                            'default'     => 100,
                        ),
                    ),
                    'default'   => array(
                        array(
                            'name' => '永久有效',
                            'time'     => 0,
                            'price'    => 1,
                            'discount'     => 100,
                        )
                    ),
                    'dependency' => array( 
                        array( 'islide_circle_type', 'any', 'money,credit' ),
                    )
                ),
                array(
                    'id'        => 'islide_circle_tags',
                    'type'      => 'group',
                    'title'     => '圈子帖子板块（标签）',
                    'button_title' => '新增板块',
                    'desc' => '增加后不要随意改动，用户在当前圈子发布帖子的时候需要选择该帖子所属板块，方便帖子筛选管理。',
                    'fields'    => array(
                        array(
                            'id'    => 'name',
                            'type'  => 'text',
                            'title' => '板块名称（必填）',
                        ),
                        
                    ),
                    'default'   => array(
                        array(
                            'name'     => '综合',
                        )
                    ),
                ),
                array(
                    'id'        => 'islide_circle_recommends',
                    'type'      => 'group',
                    'title'     => '圈子推荐栏连接',
                    'button_title' => '新增推荐',
                    'desc' => '用作圈子信息下方显示',
                    'fields'    => array(
                        array(
                            'id'    => 'name',
                            'type'  => 'text',
                            'title' => '推荐名称（必填）',
                        ),
                        array(
                            'id'    => 'link',
                            'type'  => 'text',
                            'title' => '连接地址（必填）',
                        ),
                        array(
                            'id'    => 'icon',
                            'type'  => 'upload',
                            'title' => '图片',
                            'preview' => true,
                            'library' => 'image',
                        ),
                    ),
                    'default'   => array(
                        array(
                            'name'     => '圈子版规',
                        )
                    ),
                ),
                array(
                    'id'          => 'islide_circle_info_show',
                    'type'        => 'select',
                    'title'       => '圈子顶部信息显示',
                    'options'     => array(
                        0         => '关闭',
                        'global'  => '使用全局设置',
                        'pc'      => 'pc端',
                        'mobile'  => '移动端',
                        'all'     => 'pc端和移动端都显示'
                    ),
                    'default'     => 'global',
                    'desc'        => '圈子顶部信息，如果关闭PC端显示，可以在侧边栏添加【圈子信息】小工具'
                ),
                array(
                    'id'          => 'islide_circle_input_show',
                    'type'        => 'select',
                    'title'       => '圈子帖子发布框显示',
                    'options'     => array(
                        0         => '关闭',
                        'global'  => '使用全局设置',
                        'pc'      => 'pc端',
                        'mobile'  => '移动端',
                        'all'     => 'pc端和移动端都显示'
                    ),
                    'default'     => 'global',
                ),
                array(
                    'id'      => 'islide_circle_join_post_open',
                    'type'    => 'select',
                    'title'   => '加入圈子才能发帖',
                    'options'     => array(
                        0  => '关闭',
                        1  => '开启',
                        'global'  => '使用全局设置',
                        
                    ),
                    'default'     => 'global',
                ),
                array(
                    'id'          => 'islide_circle_post_open',
                    'type'        => 'select',
                    'title'       => '是否允许用户发帖(发帖功能)',
                    'subtitle'    => '您可以在这里单独给圈子编辑器功能设置。',
                    //'placeholder' => 'Select an option',
                    'options'     => array(
                        0  => '关闭',
                        'global'  => '使用全局设置',
                        1  => '自定义该功能',
                    ),
                    'default'     => 'global',
                    'desc'        => '如果使用全局设置，这里请选择全局设置，并且在主题设置->圈子社区->发帖功能设置（全局）中编辑全局权限'
                ),
                array(
                    'id'        => 'islide_circle_post',
                    'type'      => 'fieldset',
                    'title'     => '',
                    'fields'    => array(
                        array(
                            'id'      => 'min_word_limit',
                            'type'    => 'spinner',
                            'title'   => '最小帖子内容字数限制',
                            'desc'    => '内容最小字数限制',
                            'min'     => 1,
                            'step'    => 10,
                            'unit'    => '个',
                            'default' => 5,
                        ),
                        array(
                            'id'      => 'max_word_limit',
                            'type'    => 'spinner',
                            'title'   => '最大帖子内容字数限制',
                            'desc'    => '内容最大字数限制',
                            'min'     => 1,
                            'step'    => 10,
                            'unit'    => '个',
                            'default' => 500,
                        ),
                        array(
                            'id'      => 'image_count',
                            'type'    => 'spinner',
                            'title'   => '最多上传图片多少张',
                            'desc'    => sprintf('需要给当前用户允许上传图片的权限%s','<a target="_blank" href="'.admin_url('/admin.php?page=islide_main_options#tab=%e5%b8%b8%e8%a7%84%e8%ae%be%e7%bd%ae/%e5%aa%92%e4%bd%93%e5%8f%8a%e6%9d%83%e9%99%90%ef%bc%88%e5%85%a8%e5%b1%80%ef%bc%89').'">媒体设置</a>'),
                            'min'     => 0,
                            'step'    => 1,
                            'default' => 9,
                            'unit'    => '个',
                        ),
                        array(
                            'id'      => 'video_count',
                            'type'    => 'spinner',
                            'title'   => '最多上传视频多少个',
                            'desc'    => sprintf('需要给当前用户允许上传视频的权限%s','<a target="_blank" href="'.admin_url('/admin.php?page=islide_main_options#tab=%e5%b8%b8%e8%a7%84%e8%ae%be%e7%bd%ae/%e5%aa%92%e4%bd%93%e5%8f%8a%e6%9d%83%e9%99%90%ef%bc%88%e5%85%a8%e5%b1%80%ef%bc%89').'">媒体设置</a>'),
                            'min'     => 0,
                            'step'    => 1,
                            'default' => 1,
                            'unit'    => '个',
                        ),
                        array(
                            'id'      => 'file_count',
                            'type'    => 'spinner',
                            'title'   => '最多上传文件多少个',
                            'desc'    => sprintf('需要给当前用户允许上传文件的权限%s','<a target="_blank" href="'.admin_url('/admin.php?page=islide_main_options#tab=%e5%b8%b8%e8%a7%84%e8%ae%be%e7%bd%ae/%e5%aa%92%e4%bd%93%e5%8f%8a%e6%9d%83%e9%99%90%ef%bc%88%e5%85%a8%e5%b1%80%ef%bc%89').'">媒体设置</a>'),
                            'min'     => 0,
                            'step'    => 1,
                            'default' => 1,
                            'unit'    => '个',
                        ),
                    ),
                    'dependency' => array( 'islide_circle_post_open', '==', '1' ),
                ),
                array(
                    'id'        => 'islide_circle_editor_toolbar',
                    'type'      => 'group',
                    'title'     => '工具栏按钮',
                    'subtitle' => '这只是配置工具栏按钮显示与隐藏排序等',
                    'button_title' => '新增工具按钮',
                    'desc' => '请不要重复添加相同的工具',
                    'max' => 8,
                    'fields'    => array(
                        array(
                            'id'    => 'name',
                            'type'  => 'text',
                            'title' => '工具按钮名称',
                        ),
                        array(
                            'id'    => 'name_show',
                            'type'  => 'switcher',
                            'title' => '显示按钮名称',
                            'desc'  => '意思就是不显示文字，只显示图标',
                            'default' => true,
                        ),
                        array(
                            'id'    => 'icon',
                            'type'  => 'text',
                            'title' => '工具按钮图标',
                        ),
                        array(
                            'id'         => 'tool',
                            'title'      => '工具按钮类型',
                            'type'       => 'radio',
                            'inline'     => true,
                            'options'    => array(
                                'circle_cat'   => '圈子',
                                'topic'   => '话题',
                                'emoji'   => '表情',
                                'image'   => '图片',
                                'video'   => '视频',
                                'file'   => '文件',
                                'vote'   => '投票',
                                'privacy'   => '阅读权限',
                                //'publish' => '发布',     
                            ),
                        ),
                    ),
                    'default'   => array(
                        array(
                            'name'     => '圈子',
                            'name_show' => true,
                            'icon' => 'ri-donut-chart-line',
                            'tool' => 'circle_cat',
                        ),
                        array(
                            'name'     => '话题',
                            'name_show' => true,
                            'icon' => 'ri-hashtag',
                            'tool' => 'topic',
                        ),
                        array(
                            'name'     => '表情',
                            'name_show' => true,
                            'icon' => 'ri-emotion-line',
                            'tool' => 'emoji',
                        ),
                        array(
                            'name'     => '图片',
                            'name_show' => true,
                            'icon' => 'ri-gallery-line',
                            'tool' => 'image',
                        ),
                        array(
                            'name'     => '视频',
                            'name_show' => true,
                            'icon' => 'ri-video-line',
                            'tool' => 'video',
                        ),
                        array(
                            'name'     => '投票',
                            'name_show' => true,
                            'icon' => 'ri-chat-poll-line',
                            'tool' => 'vote',
                        ),
                        array(
                            'name'     => '公开',
                            'name_show' => true,
                            'icon' => 'ri-earth-line',
                            'tool' => 'privacy',
                        ),
                    ),
                    'dependency' => array( 'islide_circle_post_open', '==', '1' ),
                ),
                array(
                    'id'          => 'islide_circle_moment_role_open',
                    'type'        => 'select',
                    'title'       => '发帖权限',
                    'subtitle'    => '您可以在这里单独给某个帖子设置发布阅读权限等。',
                    //'placeholder' => 'Select an option',
                    'options'     => array(
                        'global'  => '使用全局设置',
                        1  => '自定义该权限',
                    ),
                    'default'     => 'global',
                    'desc'        => '如果使用全局设置，这里请选择全局设置，并且在主题设置->圈子社区->圈子权限（全局）中编辑全局权限'
                ),
                array(
                    'id'        => 'islide_circle_moment',
                    'type'      => 'fieldset',
                    'title'     => '',
                    'fields'    => array(
                        array(
                            'id'         => 'insert',
                            'type'       => 'checkbox',
                            'title'      => '允许发帖',
                            'inline'     => true,
                            'options'    => $moment_roles,
                            'default'    => $default_roles,
                        ),
                        array(
                            'id'         => 'insert_public',
                            'type'       => 'checkbox',
                            'title'      => '允许发帖无需审核',
                            'inline'     => true,
                            'options'    => $moment_roles,
                            'default'    => $default_roles,
                        )
                    ),
                    'dependency' => array( 'islide_circle_moment_role_open', '==', '1' ),
                ),
                array(
                    'id'        => 'islide_circle_moment_type_role',
                    'type'      => 'fieldset',
                    'title'     => '',
                    'fields'    => array(
                        array(
                            'type'    => 'subheading',
                            'content' => '发帖功能权限',
                        ),
                        array(
                            'id'         => 'vote',
                            'type'       => 'checkbox',
                            'title'      => '允许发布投票',
                            'inline'     => true,
                            'options'    => $moment_roles,
                            'default'    => $default_roles,
                        ),
                        array(
                            'id'         => 'ask',
                            'type'       => 'checkbox',
                            'title'      => '允许发布提问',
                            'inline'     => true,
                            'options'    => $moment_roles,
                            'default'    => $default_roles,
                        ),
                        array(
                            'id'         => 'card',
                            'type'       => 'checkbox',
                            'title'      => '允许发布文章卡片',
                            'inline'     => true,
                            'options'    => $moment_roles,
                            'default'    => $default_roles,
                        ),
                    ),
                    'dependency' => array( 'islide_circle_moment_role_open', '==', '1' ),
                ),
                array(
                    'id'        => 'islide_circle_moment_role',
                    'type'      => 'fieldset',
                    'title'     => '',
                    'fields'    => array(
                        array(
                            'type'    => 'subheading',
                            'content' => '发帖隐私能力设置权限',
                        ),
                        array(
                            'id'         => 'login',
                            'type'       => 'checkbox',
                            'title'      => '允许设置权限登录可见',
                            'inline'     => true,
                            'options'    => $moment_roles,
                            'default'    => $default_roles,
                        ),
                        array(
                            'id'         => 'money',
                            'type'       => 'checkbox',
                            'title'      => '允许设置权限付费可见',
                            'inline'     => true,
                            'options'    => $moment_roles,
                            'default'    => $default_roles,
                        ),
                        array(
                            'id'         => 'credit',
                            'type'       => 'checkbox',
                            'title'      => '允许设置权限积分支付可见',
                            'inline'     => true,
                            'options'    => $moment_roles,
                            'default'    => $default_roles,
                        ),
                        array(
                            'id'         => 'comment',
                            'type'       => 'checkbox',
                            'title'      => '允许设置权限评论可见',
                            'inline'     => true,
                            'options'    => $moment_roles,
                            'default'    => $default_roles,
                        ),
                        array(
                            'id'         => 'password',
                            'type'       => 'checkbox',
                            'title'      => '允许设置权限密码可见',
                            'inline'     => true,
                            'options'    => $moment_roles,
                            'default'    => $default_roles,
                        ),
                        array(
                            'id'         => 'fans',
                            'type'       => 'checkbox',
                            'title'      => '允许设置权限粉丝可见',
                            'inline'     => true,
                            'options'    => $moment_roles,
                            'default'    => $default_roles,
                        ),
                        array(
                            'id'         => 'roles',
                            'type'       => 'checkbox',
                            'title'      => '允许设置权限组可见',
                            'inline'     => true,
                            'options'    => $moment_roles,
                            'default'    => $default_roles,
                        ),
                    ),
                    'dependency' => array( 'islide_circle_moment_role_open', '==', '1' ),
                ),
                array(
                    'id'        => 'islide_circle_moment_manage_role',
                    'type'      => 'fieldset',
                    'title'     => '',
                    'fields'    => array(
                        array(
                            'type'    => 'heading',
                            'content' => '帖子管理权限',
                        ),
                        array(
                            'id'         => 'edit',
                            'type'       => 'checkbox',
                            'title'      => '允许编辑自己管理下的帖子',
                            'inline'     => true,
                            'options'    => array(
                                'admin' => '圈子创建者',
                                'staff' => '圈子版主'
                            ),
                            'default'    => array(
                                'admin',
                                'staff'
                            ),
                        ),
                        array(
                            'id'         => 'delete',
                            'type'       => 'checkbox',
                            'title'      => '允许删除自己管理圈子下的帖子',
                            'inline'     => true,
                            'options'    => array(
                                'admin' => '圈子创建者',
                                'staff' => '圈子版主'
                            ),
                            'default'    => array(
                                'admin',
                                'staff'
                            ),
                        ),
                        array(
                            'id'         => 'best',
                            'type'       => 'checkbox',
                            'title'      => '允许加精自己管理圈子下的帖子',
                            'inline'     => true,
                            'options'    => array(
                                'admin' => '圈子创建者',
                                'staff' => '圈子版主'
                            ),
                            'default'    => array(
                                'admin',
                                'staff'
                            ),
                        ),
                        array(
                            'id'         => 'sticky',
                            'type'       => 'checkbox',
                            'title'      => '允许置顶自己管理圈子下的帖子',
                            'inline'     => true,
                            'options'    => array(
                                'admin' => '圈子创建者',
                                'staff' => '圈子版主'
                            ),
                            'default'    => array(
                                'admin',
                                'staff'
                            ),
                        ),
                        array(
                            'id'         => 'public',
                            'type'       => 'checkbox',
                            'title'      => '允许审核自己管理圈子下的帖子',
                            'inline'     => true,
                            'options'    => array(
                                'admin' => '圈子创建者',
                                'staff' => '圈子版主'
                            ),
                            'default'    => array(
                                'admin',
                                'staff'
                            ),
                        ),
                    ),
                    'dependency' => array( 'islide_circle_moment_role_open', '==', '1' ),
                ),
            )
        ));
        
         \CSF::createSection( 'islide_tax_circle', array(
            'title' => '圈子TAB选卡栏设置', 
            'fields' => array(
                array(
                    'id'          => 'islide_circle_tabbar_open',
                    'type'        => 'select',
                    'title'       => '圈子TAB选卡栏',
                    'subtitle'    => '您可以在这里单独给圈子设置选项栏目。',
                    'options'     => array(
                        'global'  => '使用全局设置',
                        1  => '自定义该选项栏',
                    ),
                    'default'     => 'global',
                    'desc'        => '如果使用全局设置，这里请选择全局设置，并且在主题设置->圈子社区->圈子圈子板块编辑全局权限'
                ),
                array(
                    'id'        => 'islide_circle_tabbar',
                    'type'      => 'group',
                    'title'     => '自定义筛选工具栏',
                    'subtitle'     => '根据这个工具可以实现无数种可能',
                    'button_title' => '新增栏目按钮',
                    'fields'    => array(
                        array(
                            'id'    => 'name',
                            'type'  => 'text',
                            'title' => 'Tab栏目名称',
                        ),
                        array(
                            'id'    => 'icon',
                            'type'  => 'icon',
                            'title' => 'Tab栏目图标',
                            'icon'  => '只会在作为左边导航时显示',
                        ),
                        array(
                            'id'         => 'tab_type',
                            'title'      => 'Tab栏目类型',
                            'type'       => 'radio',
                            'inline'     => true,
                            'options'    => array(
                                'all'   => '综合',
                            ),
                        ),
                        array(
                            'id'         => 'author__in',
                            'title'      => '筛选用户',
                            'subtitle'   => '筛选用户，一般情况下填写官方的成员id，也就是说筛选官方的帖子',
                            'type'       => 'select',
                            'placeholder' => '搜索用户',
                            'ajax'        => true,
                            'chosen'      => true, //开启这个框架报错
                            'multiple'    => true,
                            'sortable'    => true,
                            'options'     => 'users',
                        ),
                        array(
                            'id'         => 'topic',
                            'title'      => '筛选话题',
                            'subtitle'   => '在不选择话题则默认筛选全部，支持多选',
                            'type'       => 'select',
                            'placeholder' => '选择话题',
                            'ajax'        => true,
                             'chosen'      => true,
                            'multiple'    => true,
                            'sortable'    => true,
                            'options'     => 'categories',
                            'query_args'  => array(
                                'taxonomy'  => array('topic')
                            ),
                        ),
                        array(
                            'type'    => 'subheading',
                            'content' => '更加细化的筛选',
                        ),
                        array(
                            'id'      => 'best',
                            'type'    => 'radio',
                            'title'   => '精华',
                            'options'    => array(
                                '1'   => '开启',
                            ),
                        ),
                        array(
                            'id'         => 'file',
                            'title'      => '帖子文件类型',
                            'type'       => 'radio',
                            'inline'     => true,
                            'options'    => array(
                                'image'   => '图片',
                                'video'   => '视频',
                                'file'   => '文件',
                                'card '   => '文章卡',
                            ),
                        ),
                        array(
                            'id'         => 'type',
                            'title'      => '帖子类型(还未实现)',
                            'type'       => 'radio',
                            'inline'     => true,
                            'options'    => array(
                                'vote'   => '投票',
                                'ask'   => '问答',
                            ),
                        ),
                        array(
                            'id'         => 'orderby',
                            'title'      => '默认排序',
                            'type'       => 'select',
                            'inline'     => true,
                            'options'    => array(
                                'date'   => '默认时间',
                                'modified'   => '修改时间',
                                'weight'   => '权重',
                                'views'   => '浏览量',
                                'like'   => '点赞数量',
                                'comments'   => '评论数量',
                                'comment_date'   => '回复时间',
                                'random '   => '随机',
                            ),
                        ),
                        array(
                            'type'    => 'subheading',
                            'content' => '列表',
                        ),
                        array(
                            'id'         => 'list_style_type',
                            'title'      => '帖子列表风格样式',
                            'type'       => 'radio',
                            'inline'     => true,
                            'options'    => array(
                                'list-1'   => '常规',
                                'list-2'   => '简约',
                                'list-3'   => '瀑布流',
                            ),
                            'default' => 'list-1',
                        ),
                        array(
                            'id'         => 'video_play_type',
                            'title'      => '帖子列表视频播放方式',
                            'type'       => 'radio',
                            'inline'     => true,
                            'options'    => array(
                                'none'   => '不播放',
                                'click'   => '点击播放',
                                'scroll'   => '滚动播放',
                                'mouseover'   => '鼠标移入播放',
                            ),
                            'default' => 'click',
                            'desc'=>'注意：如果列表风格选择的是瀑布流，则视频滚动播放不会生效',
                            'dependency' => array(
                                array('list_style_type', '!=', 'list-2')
                            ),
                        ),
                    ),
                    'dependency' => array( 'islide_circle_tabbar_open', '==', '1' ),
                ),
                array(
                    'id'       => 'islide_circle_tabbar_index',
                    'type'     => 'spinner',
                    'title'    => '默认显示第几个栏目',
                    'subtitle' => '根据上面设置的工具栏目，选择合适的显示',
                    'max'      => 10,
                    'min'      => 0,
                    'step'     => 1,
                    'unit'     => '个',
                    'default'  => 0,
                    'desc'     => '从0开始计数，添0就是默认第一个',
                    'dependency' => array( 'islide_circle_tabbar_open', '==', '1' ),
                ),
                array(
                    'id'      => 'islide_circle_left_sidebar',
                    'type'    => 'switcher',
                    'title'   => '显示在左侧侧边栏',
                    'default' => false,
                    'dependency' => array( 'islide_circle_tabbar_open', '==', '1' ),
                ),
            )
        ));
    }
    
    /**
     * 保存圈子设置时的操作，处理圈子管理员和员工关系
     * @author ifyn
     * @param array $data 圈子设置数据
     * @param int $term_id 圈子ID
     * @return void
     */
    public function save_circle_action($data, $term_id){
        // 参数验证
        if (empty($data) || !is_array($data) || empty($term_id) || !is_numeric($term_id)) {
            error_log('save_circle_action: 参数无效');
            return;
        }
        
        $term_id = (int) $term_id;
        
        // 检查圈子是否存在
        if (!term_exists($term_id, 'circle_cat')) {
            error_log('save_circle_action: 圈子不存在，ID: ' . $term_id);
            return;
        }
        
        // 引入CircleRelate类
        if (!class_exists('islide\Modules\Common\CircleRelate')) {
            require_once dirname(__DIR__) . '/Common/CircleRelate.php';
        }
        
        // 使用完全限定名称
        $circleRelate = 'islide\Modules\Common\CircleRelate';
        
        // 处理圈子管理员
        $this->handle_circle_admin($data, $term_id, $circleRelate);
        
        // 处理圈子版主
        $this->handle_circle_staff($data, $term_id, $circleRelate);
    }
    
    /**
     * 处理圈子管理员关系
     * @author ifyn
     * @param array $data 圈子设置数据
     * @param int $term_id 圈子ID
     * @param string $circleRelate CircleRelate类的完全限定名称
     * @return void
     */
    private function handle_circle_admin($data, $term_id, $circleRelate) {
        if (empty($data['islide_circle_admin'])) {
            return;
        }
        
        $admin_id = (int) $data['islide_circle_admin'];
        
        // 检查用户是否存在
        if (!get_user_by('id', $admin_id)) {
            error_log('handle_circle_admin: 用户不存在，ID: ' . $admin_id);
            return;
        }
        
        // 检查当前管理员
        $current_admin = $circleRelate::get_data([
            'circle_id' => $term_id,
            'circle_role' => 'admin'
        ]);
        
        // 获取当前管理员ID
        $current_admin_id = !empty($current_admin) && isset($current_admin[0]['user_id']) 
            ? (int) $current_admin[0]['user_id'] 
            : 0;
        
        // 只有在管理员变更或不存在时才进行操作
        if ($current_admin_id !== $admin_id) {
            // 将新管理员设置为admin角色
            $result = $circleRelate::update_data([
                'user_id' => $admin_id,
                'circle_id' => $term_id,
                'circle_role' => 'admin',
                'join_date' => current_time('mysql')
            ]);
            
            if (!$result) {
                error_log('handle_circle_admin: 更新新管理员失败，用户ID: ' . $admin_id . '，圈子ID: ' . $term_id);
            }
            
            // 如果存在旧管理员且不是新管理员，将其角色改为普通成员
            if ($current_admin_id > 0 && $current_admin_id !== $admin_id) {
                $result = $circleRelate::update_data([
                    'user_id' => $current_admin_id,
                    'circle_id' => $term_id,
                    'circle_role' => 'member',
                    'join_date' => isset($current_admin[0]['join_date']) ? $current_admin[0]['join_date'] : current_time('mysql')
                ]);
                
                if (!$result) {
                    error_log('handle_circle_admin: 更新旧管理员失败，用户ID: ' . $current_admin_id . '，圈子ID: ' . $term_id);
                }
            }
        }
    }
    
    /**
     * 处理圈子版主关系
     * @author ifyn
     * @param array $data 圈子设置数据
     * @param int $term_id 圈子ID
     * @param string $circleRelate CircleRelate类的完全限定名称
     * @return void
     */
    private function handle_circle_staff($data, $term_id, $circleRelate) {
        // 获取当前所有版主
        $current_staff = $circleRelate::get_data([
            'circle_id' => $term_id,
            'circle_role' => 'staff',
            'count' => 50 // 设置较大的数量限制以获取所有版主
        ]);
        
        // 转换为ID数组
        $current_staff_ids = !empty($current_staff) ? array_column($current_staff, 'user_id') : [];
        
        // 新设置的版主列表
        $new_staff_ids = isset($data['islide_circle_staff']) && is_array($data['islide_circle_staff']) 
            ? array_map('intval', $data['islide_circle_staff']) 
            : [];
        
        // 对每个新版主，如果不在当前版主列表中，则添加或更新
        foreach ($new_staff_ids as $staff_id) {
            // 跳过无效用户ID
            if (empty($staff_id) || !get_user_by('id', $staff_id)) {
                continue;
            }
            
            // 跳过已是管理员的用户
            if ($staff_id == $data['islide_circle_admin']) {
                continue;
            }
            
            if (!in_array($staff_id, $current_staff_ids)) {
                $result = $circleRelate::update_data([
                    'user_id' => $staff_id,
                    'circle_id' => $term_id,
                    'circle_role' => 'staff',
                    'join_date' => current_time('mysql')
                ]);
                
                if (!$result) {
                    error_log('handle_circle_staff: 添加版主失败，用户ID: ' . $staff_id . '，圈子ID: ' . $term_id);
                }
            }
        }
        
        // 找出需要移除版主权限的用户
        $staff_to_remove = array_diff($current_staff_ids, $new_staff_ids);
        
        foreach ($staff_to_remove as $staff_id) {
            $result = $circleRelate::update_data([
                'user_id' => $staff_id,
                'circle_id' => $term_id,
                'circle_role' => 'member',
                'join_date' => current_time('mysql')
            ]);
            
            if (!$result) {
                error_log('handle_circle_staff: 移除版主失败，用户ID: ' . $staff_id . '，圈子ID: ' . $term_id);
            }
        }
    }
}