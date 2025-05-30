<?php namespace islide\Modules\Settings;
use islide\Modules\Common\User;

/**
 * 文章设置
 * 
 * */
class Post{
    
    //设置主KEY
    public static $prefix = 'islide_single_post_metabox';

    public function init(){
        
        //过滤掉积分或余额变更原因
        add_filter('csf_islide_single_post_metabox_save', function ($data,$user_id){
            unset($data['islide_video_batch']);
            return $data;
        },10,2);
        
        //保存文章执行
        add_action('save_post', array($this,'save_post_meta_box'),99,3);
        
        $this->register_post_metabox();
        
        $this->register_post_download();
        
        $this->register_post_seo();
        
        $this->register_post_hidden();
        
        //表格
        add_filter('manage_posts_columns', array($this,'custom_posts_custom' ));
        add_action('admin_init', array($this,'custom_all_posts_custom_sortable' ));
        add_filter('manage_posts_custom_column', array($this,'custom_posts_custom_content'), 10, 2 );
        
    }
    
    // 添加自定义列
    public function custom_posts_custom($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb']; // 复选框
        unset($columns['cb']);
        $new_columns['id'] = 'ID';
        return array_merge($new_columns, $columns);
    }
    // 使ID列可排序
    public function custom_posts_custom_sortable( $sortable_columns ) {
        $sortable_columns['id'] = 'ID';
        return $sortable_columns;
    }
    
    function custom_all_posts_custom_sortable() {
        $post_types = get_post_types(array('public' => true), 'names');
        foreach ($post_types as $post_type) {
            add_filter("manage_edit-{$post_type}_sortable_columns", array($this,'custom_posts_custom_sortable') );
        }
    }
    
    // 显示自定义列的内容 显示ID
    public function custom_posts_custom_content($column, $post_id) {
        if ($column === 'id') {
            echo $post_id;
        }
    }
    
    public function register_post_seo(){
        
        
        // Create a metabox
        \CSF::createMetabox('islide_single_post_seo', array(
            'title'     => '自定义SEO设置',
            'post_type' => array('post','page','video','episode'),
            'context'   => 'side', // The context within the screen where the boxes should display. `normal`, `side`, `advanced`
            'data_type' => 'unserialize'
        ));
         // Create a section
        \CSF::createSection('islide_single_post_seo', array(
            'fields' => array(
                array(
                    'id'          => 'islide_seo_title', //文件模板样式
                    'type'        => 'text',
                    'title'       => 'SEO标题',
                    'desc'        => '一般建议15到30个字符',
                    'default'     => '',
                ),
                
                array(
                    'id'          => 'islide_seo_keywords', //文件模板样式
                    'type'        => 'text',
                    'title'       => 'SEO关键词',
                    'desc'        => '关键词一般建议4到8个，每个关键词用英文逗号隔开',
                    'default'     => '',
                ),
                
                array(
                    'id'          => 'islide_seo_description', //文件模板样式
                    'type'        => 'textarea',
                    'title'       => 'SEO描述',
                    'desc'        => '对网页内容的精练概括',
                    'default'     => '',
                ),

            )
        ));
    }
    
    public function register_post_hidden(){
        
        
        //serialize
        // $meta = get_term_meta( 11, 'islide_tax', true );
        
        //unserialize
        //print_r(get_term_meta( 11, 'seo_title', true ));
        
        // Create a metabox
        \CSF::createMetabox('islide_single_post_hidden', array(
            'title'     => '隐藏内容阅读权限',
            'post_type' => array('post','page','video','episode','circle'),
            'context'   => 'side', // The context within the screen where the boxes should display. `normal`, `side`, `advanced`
            'data_type' => 'unserialize'
        ));
        
        $roles = User::get_user_roles();

        $roles_options = array();
        
        foreach ($roles as $key => $value) {
            $roles_options[$key] = $value['name'];
        }
        
         // Create a section
        \CSF::createSection('islide_single_post_hidden', array(
            'fields' => array(
                array(
                    'id'          => 'islide_post_content_hide_role', //文件模板样式
                    'type'        => 'select',
                    'title'       => '阅读权限',
                    'options'     => array(
                        'none'    => '无限制',
                        'money'   => '支付费用可见',
                        'credit'  => '支付积分可见',
                        'roles'   => '限制等级可见',
                        'password'=> '输入密码可见',
                        'login'   => '登录可见',
                        'comment' => '评论可见',
                    ),
                    'desc'        => '需要在文章中使用隐藏内容短代码工具将需要隐藏的内容包裹起来，否则不生效',
                    'default'     => 'none',
                ),
                array(
                    'id'          => 'islide_post_price', //文件模板样式
                    'type'        => 'number',
                    'title'       => '需要支付的金额或积分',
                    'desc'        => '请直接填写数字，比如100元',
                    'min'         => 1,
                    'default'     => '',
                    'dependency'  => array(
                        array( 'islide_post_content_hide_role', 'any', 'money,credit' )
                    ),
                ),
                array(
                    'id'      => 'islide_post_not_login_buy',
                    'type'    => 'switcher',
                    'title'   => '开启未登录用户购买功能',
                    'desc'=> '未登录用户只能使用金钱支付',
                    'default' => 0,
                    'dependency'   => array(
                        array( 'islide_post_content_hide_role', '==', 'money' )
                    ),
                ),
                array(
                    'id'       => 'islide_post_password',
                    'type'       => 'text',
                    'title'    => '输入密码',
                    'default'  => '',
                    'desc'     => '(非必填)如果不填写前往 <a href="'.admin_url('/admin.php?page=islide_main_options#tab=常规设置/密码验证').'" target="_blank">常规设置/密码验证设置</a>',
                    'dependency'   => array(
                        array( 'islide_post_content_hide_role', '==', 'password' )
                    ),
                ),
                array(
                    'id'         => 'islide_post_roles',
                    'type'       => 'checkbox',
                    'title'      => '允许免费查看的用户组',
                    'inline'     => true,
                    'options'    => $roles_options,
                    'desc'       => '（可多选）请选择允许指定免费查看的用户组',
                    'dependency'   => array(
                        array( 'islide_post_content_hide_role', '==', 'roles' )
                    ),
                ),
            )
        ));
    }

    public function register_post_metabox(){
        
        
        //serialize
        // $meta = get_term_meta( 11, 'islide_tax', true );
        
        //unserialize
        //print_r(get_term_meta( 11, 'seo_title', true ));
        
        // Create a metabox
        \CSF::createMetabox(self::$prefix, array(
            'title'     => '文章设置',//文章风格设置
            'post_type' => array('post'),
            'context'   => 'normal', // The context within the screen where the boxes should display. `normal`, `side`, `advanced`
            'data_type' => 'unserialize'
        ));
        
        $roles = User::get_user_roles();

        $roles_options = array();
        
        foreach ($roles as $key => $value) {
            $roles_options[$key] = $value['name'];
        }
        
         // Create a section
        \CSF::createSection(self::$prefix, array(
            'fields' => array(
                array(
                    'id'       => 'islide_subtitle',
                    'type'       => 'text',
                    'title'    => '文章副标题',
                    'default'  => '',
                    'desc'     => '(非必填)显示在文章标题后',
                ),
                array(
                    'id'          => 'islide_single_post_style', //文件模板样式
                    'type'        => 'radio',//'image_select',
                    'title'       => '布局风格样式',
                    'inline'     => true,
                    'options'     => array(
                        'post-style-1' => '常规样式',//'http://codestarframework.com/assets/images/placeholder/150x125-2ecc71.gif',
                        //'post-style-2' => 'http://codestarframework.com/assets/images/placeholder/150x125-2ecc71.gif',
                        'post-style-video' =>'视频样式' //'http://codestarframework.com/assets/images/placeholder/150x125-2ecc71.gif',
                    ),
                    'class'       => 'module_type',
                    'default'     => 'post-style-1',
                ),
                array(
                    'id'     => self::$prefix,
                    'type'   => 'fieldset',
                    'fields' => array(
                        array(
                            'id'         => 'role',
                            'type'       => 'radio',
                            'title'      => '观看权限',
                            'inline'     => true,
                            'options'    => array(
                                'free'   => '无限制(免费)',
                                'money'  => '支付费用观看',
                                'credit' => '支付积分观看',
                                'roles'  => '限制等级观看',
                                'comment'=> '评论观看',
                                'login'  => '登录观看',
                                'password'  => '输入密码观看',
                            ),
                            'default'    => 'free',
                        ),
                        array(
                            'id'         => 'roles',
                            'type'       => 'checkbox',
                            'title'      => '允许免费查看的用户组',
                            'inline'     => true,
                            'options'    => $roles_options,
                            'desc'       => '（可多选）请选择允许指定免费查看的用户组',
                            'dependency'   => array(
                                array( 'role', '==', 'roles' )
                            ),
                        ),
                        array(
                            'id'      => 'not_login_buy',
                            'type'    => 'switcher',
                            'title'   => '开启未登录用户购买功能',
                            'desc'=> '未登录用户只能使用金钱支付，所有在设置权限是必须是 <code>付费</code>',
                            'default' => 0,
                            'dependency'   => array(
                                array( 'role', '==', 'money' )
                            ),
                        ),
                        array(
                            'id'      => 'price_total',
                            'type'    => 'spinner',
                            'title'   => '支付的总费用',
                            'default' => 0,
                            'desc'=> '用于一次购买全部的费用',
                            'dependency'   => array(
                                array( 'role', 'any', 'money,credit' )
                            ),
                        ),
                        array(
                            'id'      => 'price_value',
                            'type'    => 'spinner',
                            'title'   => '支付的单次费用',
                            'default' => 0,
                            'desc'=> '支持每集单独购买',
                            'dependency'   => array(
                                array( 'role', 'any', 'money,credit' )
                            ),
                        )
                    ),
                    'dependency'   => array(
                        array( 'islide_single_post_style', 'any', 'post-style-2,post-style-video' ),
                    ),
                ),
                array(
                    'id'      => 'islide_video_batch',
                    'type'    => 'textarea',
                    'title'   => '视频批量导入',
                    'desc'    => sprintf('格式为%s，每组占一行。%s比如：%s%s','<code>视频地址|标题名称|特色图|视频预览地址</code>','<br>','<br><code>https://xxx.xxx.com/xxxx.mp4|第一课：学习Css选择器|https://xxx.xxx.com/xxxx.png|https://xxx.xxx.com/xxxx.mp4</code>','<br>'),
                    'dependency'   => array(
                        array( 'islide_single_post_style', '==', 'post-style-video' ),
                    ),
                ),
                array(
                    'id'        => 'islide_single_post_video_group',
                    'type'      => 'group',
                    //'title'     => '视频',
                    'accordion_title_number' => true,
                    'sanitize' => false,
                    'button_title'     => '添加一个视频',
                    'fields'    => array(
                        array(
                            'id'    => 'title',
                            'type'  => 'text',
                            'title' => '视频标题',
                            'dependency' => array('type', '==', 'episode'),
                        ),
                        array(
                            'id'    => 'thumb',
                            'type'  => 'upload',
                            'title' => '视频缩略图',
                            'preview' => true,
                            'library' => 'image',
                            'dependency' => array('type', '==', 'episode'),
                        ),
                        array(
                            'id'    => 'url',
                            'type'  => 'upload',
                            'title' => '视频地址',
                            'dependency' => array('type', '==', 'episode'),
                        ),
                        array(
                            'id'    => 'preview_url',
                            'type'  => 'upload',
                            'title' => '视频预览地址',
                            'dependency' => array('type', '==', 'episode'),
                        ),
                        array(
                            'id'         => 'type',
                            'type'       => 'button_set',
                            'title'      => '类型',
                            'options'    => array(
                                'episode'  => '视频',
                                'chapter' => '章节',
                            ),
                            'default'    => 'episode'
                        ),
                        array(
                            'id'    => 'chapter_title',
                            'type'  => 'text',
                            'title' => '视频章节标题',
                            'dependency' => array('type', '==', 'chapter'),
                        ),
                        array(
                            'id'    => 'chapter_desc',
                            'type'  => 'text',
                            'title' => '视频章节介绍',
                            'dependency' => array('type', '==', 'chapter'),
                        ),
                    ),
                    'dependency'   => array(
                        array( 'islide_single_post_style', '==', 'post-style-video' ),
                    ),
                ),
                
                
                // array(
                //     'id'         => 'islide_single_post_image_role',
                //     'type'       => 'radio',
                //     'title'      => '图片查看权限',
                //     'inline'     => true,
                //     'options'    => array(
                //         'none'   => '无限制(免费)',
                //         'money'  => '支付费用可见',
                //         'credit' => '支付积分可见',
                //         'roles'  => '限制等级可见',
                //         'comment'=> '评论可见',
                //         'login'  => '登录可见',
                //     ),
                //     'default'    => 'none',
                //     'dependency'   => array(
                //         array('islide_single_post_style', '==', 'post-style-2' ),
                //     )
                // ),
                // array(
                //     'id'         => 'islide_single_post_video_roles',
                //     'type'       => 'checkbox',
                //     'title'      => '允许免费查看的用户组',
                //     'inline'     => true,
                //     'options'    => $roles_options,
                //     'desc'       => '（可多选）请选择允许免费查看图片的用户组',
                //     'dependency'   => array(
                //         array( 'islide_single_post_style', 'any', 'post-style-2' ),
                //         array( 'islide_single_post_image_role', '==', 'roles' )
                //     ),
                // ),
                // array(
                //     'id'      => 'islide_single_post_image_num',
                //     'type'    => 'spinner',
                //     'title'   => '支付的费用',
                //     'default' => 0,
                //     'dependency'   => array(
                //         array( 'islide_single_post_style', 'any', 'post-style-2' ),
                //         array( 'islide_single_post_image_role', 'any', 'money,credit' )
                //     ),
                // ),
                // array(
                //     'id'      => 'islide_single_post_image_gallery',
                //     'type'    => 'gallery',
                //     'title'   => '付费图片组',
                //     'desc'=> '可在此处设置图片数据
                //             <p style="color:#ff4021;"><i class="fa fa-fw fa-info-circle fa-fw"></i>也可以直接在文章内容添加图片，主题会自动获取文章内容里所有图片</p>',
                //     'add_title'   => '新增图片',
                //     'edit_title'  => '编辑图片',
                //     'clear_title' => '清空图片',
                //     'default'     => false,
                //     'dependency'   => array( 'islide_single_post_style', '==', 'post-style-2' ),
                // ),
                // array(
                //     'id'      => 'islide_single_post_image_free_num',
                //     'type'    => 'spinner',
                //     'title'   => '免费查看前几张图片',
                //     'min'        => 0,
                //     'step'       => 1,
                //     'unit'       => '张',
                //     'default' => 0,
                //     'dependency'   => array(
                //         array('islide_single_post_style', '==', 'post-style-2' ),
                //         array('islide_single_post_image_role', '!=', 'none' ),
                //         //array('islide_single_post_image_gallery', '!=', '' ),
                //     )
                // ),
            )
        ));
    }
    
    public function register_post_download(){
        
        // Create a metabox
        \CSF::createMetabox('islide_single_post_download', array(
            'title'     => '下载设置',
            'post_type' => array('post','page'),
            'context'   => 'normal', // The context within the screen where the boxes should display. `normal`, `side`, `advanced`
            'data_type' => 'unserialize'
        ));
        
        //预设模板
        $template_options = array('不选择');
        $download_template = islide_get_option('single_post_download_template_group');
        if(!empty($download_template)){
            foreach ($download_template as $k => $v) {
                $template_options[] = $v['name'];
            }
        }
        
        // $roles = User::get_user_roles();
        // $roles_options = array(
        //     '全部' => array(
        //         'all' => '所有人',
        //         'all_vip' => '所有vip'
        //     )
        // );
        
        // foreach ($roles as $key => $value) {
        //     if (strpos($key, 'vip') !== false) {
        //         $roles_options['会员'][$key] = $value['name'];
        //     } elseif (strpos($key, 'lv') !== false) {
        //         $roles_options['等级'][$key] = $value['name'];
        //     }
        // }
        
        
        // Create a section
        \CSF::createSection('islide_single_post_download', array(
            'fields' => array(
                array(
                    'id'      => 'islide_single_post_download_open',
                    'type'    => 'switcher',
                    'title'   => '开启下载功能',
                    'default' => 0,
                ),
                array(
                    'id'      => 'islide_down_not_login_buy',
                    'type'    => 'switcher',
                    'title'   => '开启未登录用户购买功能',
                    'desc'=> '未登录用户只能使用金钱支付，所有在设置权限是必须是 <code>money=10</code>',
                    'default' => 0,
                ),
                array(
                    'id'      => 'islide_download_data_orderby',
                    'type'    => 'switcher',
                    'title'   => '前台倒序排序显示',
                    'default' => 0,
                ),
                array(
                    'id'        => 'islide_single_post_download_group',
                    'type'      => 'group',
                    'title'     => '',
                    'button_title'     => '添加一个下载资源项',
                    'accordion_title_number'     => true,
                    'dependency'   => array( 'islide_single_post_download_open', '==', '1' ),
                    'fields'    => array(
                        array(
                            'id'        => 'title',
                            'type'      => 'text',
                            'title'     => '资源名称',
                            'desc'=> '如果不设置，需要显示的地方默认将获取文章标题当作资源名称',
                        ),
                        array(
                            'id'      => 'preset_template',
                            'type'    => 'select',
                            'title'   => '选择预设下载模板',
                            'options' => $template_options,
                            'desc'=> sprintf('您可以前往%s页面创建预设下载模板，在此处可以选择设置好的模版，然后直接编辑，方便快速设置','<a href="'.admin_url('/admin.php?page=islide_main_options#tab=%e6%a8%a1%e5%9d%97%e8%ae%be%e7%bd%ae/%e6%96%87%e7%ab%a0%e6%a8%a1%e5%9d%97').'" target="_blank">'.'下载模版设置'.'</a>'),
                            'default' => 0,
                        ),
                        array(
                            'id'    => 'thumb',
                            'type'  => 'upload',
                            'title' => '资源缩略图',
                            'preview' => true,
                            'library' => 'image',
                        ),
                        array(
                            'id'        => 'rights',
                            'type'      => 'textarea',
                            'title'     => '下载权限',
                            'desc'=> sprintf('
                            如果权限重合则优先使用vip > lv > all。如果每日免费下载次数用完后，则继续使用对应的权限%s 
                            格式为%s，
                            比如%s
                            《权限参数》%s
                            无限制及免费：free%s
                            密码下载: password 如使用密码下载请先进行 <a href="'.admin_url('/admin.php?page=islide_main_options#tab=常规设置/密码验证').'" target="_blank">常规设置/密码验证设置</a>（可以配套使用公众号，用来引流使用）%s
                            评论下载：comment%s
                            登录下载：login%s
                            付费下载：money=10%s
                            积分下载：credit=30%s
                            《特殊权限》%s
                            所有人免费：all|free（或者credit=10这种格式）%s
                            普通用户组免费：lv|free（或者credit=10这种格式）%s
                            VIP用户免费：vip|free（或者credit=10这种格式|如果vip开启了免费下载可以不用设置）',
                            '<br>',
                            '<code>等级|权限</code>',
                            '<br><code>vip1|free</code><br><code>vip2|money=1</code><br><code>vip3|password</code><br><code>lv2|comment</code><br><code>lv3|login</code><br><code>lv4|money=10</code><br><code>lv4|credit=30</code><br><code>not_login|money=30</code>(未登录用户付费价格，未登录用户无法支付积分，如果上面关闭了未登录用户购买功能，此种设置不会生效)<br>',
                            '<br>',
                            '<br>',
                            '<br>',
                            '<br>',
                            '<br>',
                            '<br>',
                            '<br>',
                            '<br>',
                            '<br>',
                            '<br>'),
                        ),
                        array(
                            'id'        => 'download_group',
                            'type'      => 'group',
                            'title'     => '下载资源',
                            'button_title' => '增加下载资源',
                            'accordion_title_number' => true,
                            'fields'    => array(
                                array(
                                    'id'    => 'name',
                                    'type'  => 'text',
                                    'title' => '下载名称',
                                    'desc'  => '比如<code>直链下载</code>，<code>百度网盘</code>，<code>微云</code>，<code>微软网盘</code>，<code>阿里云盘</code>等。',
                                    'default' => '下载',
                                ),
                                array(
                                    'title'       => '下载地址',
                                    'id'          => 'url',
                                    'placeholder' => '上传文件或输入下载地址',
                                    'preview'     => true,
                                    'type'        => 'upload',
                                ),
                                array(
                                    'id'    => 'tq',
                                    'type'  => 'text',
                                    'title' => '提取码',
                                ),
                                array(
                                    'id'    => 'jy',
                                    'type'  => 'text',
                                    'title' => '解压密码',
                                ),
                            ),
                        ),
                        array(
                            'id'        => 'attrs',
                            'type'      => 'textarea',
                            'title'     => '资源属性',
                            'desc'=> sprintf('格式为%s，每组占一行。%s比如：%s','<code>属性名|属性值</code>','<br>','<br><code>文件格式|zip</code><br><code>文件大小|100MB</code>'),
                        ),
                        array(
                            'id'    => 'demo',
                            'type'  => 'text',
                            'title' => '演示地址',
                            'desc'=> 'http(s)://...形式的网址',
                        ),
                    ),
                    
                ),
            )
        ));
    }
    
    public function save_post_meta_box ( $post_id, $post, $update ) {
        // 排除自动保存和修订版本
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        // 只在文章发布时执行 如果文章状态不是“publish”，则不执行操作
        if($post->post_status !== 'publish'){
            return;
        }

        // 只在文章类型为 post 时执行
        if($post->post_type !== 'post'){
            return;
        }
        
        $video_meta = isset($_POST['islide_single_post_metabox']) ? $_POST['islide_single_post_metabox'] : array();
        $video_batch = isset($video_meta['islide_video_batch']) && !empty($video_meta['islide_video_batch']) ? $video_meta['islide_video_batch'] :'';

        if($video_batch) {
            $child_posts = explode(PHP_EOL, trim($video_batch, " \t\n\r") );
            
            $video_group = get_post_meta($post_id, 'islide_single_post_video_group', true );
            $video_group = !empty($video_group) && is_array($video_group) ? $video_group : array();
            
            $i = count($video_group);
            
            foreach ($child_posts as $key => $child_post) {
                $data = explode("|", trim($child_post, " \t\n\r"));
                $i++;
                $video_url = isset($data[0]) && !empty($data[0]) ? $data[0] : '';
                $title = isset($data[1]) && !empty($data[1]) ? $data[1] : '第'.$i. '集';
                $thumb = isset($data[2]) && !empty($data[2]) ? $data[2] : '';
                $preview_url = isset($data[3]) && !empty($data[3]) ? $data[3] : '';
                
                // 判断是否为有效的视频地址
                if (!filter_var($video_url, FILTER_VALIDATE_URL)) {
                    continue;
                }
                $episode_video = array();
                $episode_video['title'] = $title;
                $episode_video['type'] = 'episode';
                $episode_video['url'] = $video_url;
                $episode_video['preview_url'] = $preview_url;
                $episode_video['thumb'] = $thumb;
                
                $video_group[] = $episode_video;
                
                update_post_meta($post_id,'islide_single_post_video_group', $video_group );
            }

        }
    }
}