<?php
namespace islide\Modules\Settings;
use islide\Modules\Common\User;
//后台常规设置

class Normal{
    
    //设置主KEY
    public static $prefix = 'islide_main_options';
    
    //默认设置项
    public static $default_settings = array(
        
    );

    public function init(){
        $this->normal_options_page();
    }
    
    /**
    * 常规设置
    *
    * @return void
    * 
    * @version 1.0.0
    * @since 2023
    */
    public function normal_options_page(){
        
         \CSF::createSection(self::$prefix, array(
            'id'    => 'islide_normal_options',
            'title' => '常规设置',
            'icon'  => 'fa fa-bullseye',
        ));
        
        //SEO设置
        $this->seo_settings();
        
        //常规设置
        $this->normal_settings();
        
        //前台文章投稿
        $this->write_settings();
        
        //媒体及权限
        $this->media_settings();
        
        //支付设置
        $this->pay_settings();
        
        //密码验证设置
        $this->verification_code_settings();
        
        //IP归属地设置
        $this->ip_location_settings();
        
        //邮件设置
        $this->email_settings();
        
        //邮件设置
        $this->report_settings();
        
        //自定义代码设置
        $this->custom_code_settings();
        
    }
    //SEO设置
    public function seo_settings(){
        
        \CSF::createSection(self::$prefix, array(
            'parent'    => 'islide_normal_options',
            'id' => 'islide_normal_seo',
            'title'     => 'SEO设置',
            'icon'      => 'fa fab fa-opera',
            'fields'    => array(
                array(
                    'id'    => 'domain_name',
                    'type'  => 'text',
                    'title' => '网站域名',
                    'desc'    => '例如：https://www.example.com',
                ),
                array(
                    'id'    => 'img_logo',
                    'type'  => 'upload',
                    'title' => '网站LOGO',
                    'preview' => true,
                    'library' => 'image',
                ),
                array(
                    'id'    => 'text_logo',
                    'type'  => 'text',
                    'title' => '文字LOGO',
                ),
                array(
                    'id'      => 'separator',
                    'type'    => 'text',
                    'title'   => '网站连接符',
                    'desc'    => '标题与描述之间的分隔符，默认-。',
                    'default' => '-'
                ),
                array(
                    'id'        => 'launch_date', // 字段 ID
                    'type'      => 'date',       // 字段类型
                    'title'     => '网站上线日期',  // 字段标题
                    'settings'  => array(
                        'dateFormat' => 'yy-mm-dd', // 日期格式
                        ),
                    ),
                array(
                    'id'      => 'remove_category_tag',
                    'type'    => 'switcher',
                    'title'   => '分类目录是否去掉category标签',
                    'desc'    => '如果您之前的分类链接里面有category标签，此时去掉可能影响之前的收录。设置完成以后，请重新保存一下固定链接',
                    'default' => 1
                ),
                array(
                    'id'          => 'home_keywords',
                    'type'        => 'text',
                    'title'       => '网站首页SEO关键词',
                    'placeholder' => '自定义网站的SEO关键字(keywords)',
                    'desc'        => '建议使用英文的,隔开，一般3-5个关键词即可，多了会有堆砌嫌疑。',
                    'default'     => ''
                ),
                array(
                    'id'          => 'home_description',
                    'type'        => 'textarea',
                    'title'       => '网站首页SEO描述',
                    'placeholder' => '自定义网站的SEO描述(description)',
                    'desc'        => '描述你站点的主营业务，一般不超过200个字。',
                    'attributes'  => array(
                        'rows'    => 5,
                    ),
                    'default'     => ''
                ),
            )
        ));
    }
    
    //常规设置
    public function normal_settings() {
        \CSF::createSection(self::$prefix, array(
            'parent'    => 'islide_normal_options',
            'id' => 'islide_normal_main',
            'title'     => '常规设置',
            'icon'      => 'fa fa-bullseye',
            'fields'    => array(
                array(
                    'id'      => 'islide_image_lazyload',
                    'type'    => 'switcher',
                    'title'   => '开启全站图片懒加载',
                    'default' => true,
                ),
                array(
                    'id'    => 'lazyload_default_img',
                    'type'  => 'upload',
                    'title'    => ' ',
                    'subtitle' => '懒加载预载图',
                    'desc'     => '图片加载前显示的占位图像',
                    'class'    => 'compact',
                    'preview' => true,
                    'library' => 'image',
                    'dependency' => array('islide_image_lazyload', '!=', '', '', 'visible'),
                ),
                array(
                    'id'          => 'islide_default_imgs',
                    'type'        => 'gallery',
                    'title'       => '全局默认缩略图',
                    'add_title'   => '新增图片',
                    'edit_title'  => '编辑图片',
                    'clear_title' => '清空图片',
                    'default'     => false,
                    'desc'        => '可以设置多个默认缩略图，当您的文章没有指定缩略图，并且文章内部没有图片的时候，随机显示这些缩略图。',
                ),
            )
        ));
    }
    
    //前台文章投稿
    public function write_settings(){
        $roles = User::get_user_roles();
        $_roles = array();
        foreach ($roles as $key => $value) {
            $_roles[$key] = $value['name'];
        }
        \CSF::createSection(self::$prefix, array(
            'parent'    => 'islide_normal_options',
            'id' => 'islide_normal_write',
            'title'     => '前台投稿',
            'icon'      => 'fa fa-pencil',
            'fields'    => array(
                array(
                    'id'      => 'write_allow',
                    'type'    => 'switcher',
                    'title'   => __('是否允许用户投稿'),
                    'default' => true,
                    //'desc'    => '此功能启用后，可以在<a href="' . zib_get_admin_csf_url('功能&权限/基本权限') . '">权限管理</a>中设置用户的发布、审核权限',
                ),
                array(
                    'id'          => 'write_cats',
                    'type'        => 'select',
                    'title'       => __('允许投稿的分类'),
                    'placeholder' => '允许选择的分类，为空则允许选择全部分类',
                    'default'     => array(),
                    'options'     => 'categories',
                    'chosen'      => true,
                    'multiple'    => true,
                    'sortable'    => true,
                    'dependency'  => array('write_allow', '!=', '', '', 'visible'),
                ),
                array(
                    'id'         => 'create_normal_post',
                    'type'       => 'checkbox',
                    'title'      => '允许投稿用户',
                    'inline'     => true,
                    'options'    => $_roles,
                    'default'    => array_keys($_roles),
                    'dependency'  => array('write_allow', '!=', '', '', 'visible'),
                ),
                
            )
        ));
    }
    
    //媒体及权限
    public function media_settings(){
        $roles = \islide\Modules\Common\User::get_user_roles();

        $roles_options = array(
            'admin' => '圈子创建者',
            'staff' => '圈子版主'
        );
        
        foreach ($roles as $key => $value) {
            $roles_options[$key] = $value['name'];
        }
        
        $default_roles = array_keys($roles_options);
        
        \CSF::createSection(self::$prefix, array(
            'parent'    => 'islide_normal_options',
            'id' => 'islide_normal_media',
            'title'     => '媒体及权限（全局）',
            'icon'      => 'fa fa-pencil',
            'fields'    => array(
                array(
                    'type'    => 'heading',
                    'content' => '媒体及权限相关设置',
                ),
                array(
                    'id'      => 'media_upload_allow',
                    'type'    => 'switcher',
                    'title'   => '是否启用前台媒体上传',
                    'default' => true,
                ),
                array(
                    'id'      => 'media_image_crop',
                    'type'    => 'switcher',
                    'title'   => '图像优化自动裁剪成缩略图',
                    'default' => true,
                    'desc' => '这里的意思，上传保存的是原图，在大量调用图像比如封面之类的，用原图可能加载很慢，所以裁剪相同大小缩略图可以大量节省您的宽带，优化网站打开速度。建议开启。不能裁剪远程图片返回原图。'
                ),
                array(
                    'content' => '<p><b>当前PHP环境配置限制的最大上传大小为：' . ini_get('upload_max_filesize') . '</b></p>
                    <li>上传文件大小，不能超过php.ini的配置。</li>
                    <li>请考虑服务器负荷，以及服务器最大能支持的范围</li>',
                    'style'   => 'info',
                    'type'    => 'submessage',
                ),
                array(
                    'id'        => 'media_upload_size',
                    'type'      => 'fieldset',
                    'title'     => '',
                    'fields'    => array(
                        array(
                            'id'      => 'image',
                            'type'    => 'spinner',
                            'title'   => '最大上传图像大小',
                            'desc'    => '前端允许上传的最大图像大小，全站的图片体积都不允许超过此范围',
                            'min'     => 0,
                            'step'    => 1,
                            'default' => 3,
                            'unit'    => 'M',
                        ),
                        array(
                            'id'      => 'video',
                            'type'    => 'spinner',
                            'title'   => '最大上传视频大小',
                            'desc'    => '前端允许上传的最大视频大小，全站的视频体积都不允许超过此范围',
                            'min'     => 0,
                            'step'    => 10,
                            'default' => 50,
                            'unit'    => 'M',
                        ),
                        array(
                            'id'      => 'file',
                            'type'    => 'spinner',
                            'title'   => '最大上传文件大小',
                            'desc'    => '前端允许上传的最大视频大小，全站的视频体积都不允许超过此范围',
                            'min'     => 0,
                            'step'    => 10,
                            'default' => 10,
                            'unit'    => 'M',
                        ),
                    )
                ),
                array(
                    'type'    => 'subheading',
                    'content' => '媒体上传权限设置',
                ),
                array(
                    'id'        => 'media_upload_role',
                    'type'      => 'fieldset',
                    'title'     => '',
                    'fields'    => array(
                        array(
                            'id'         => 'image',
                            'type'       => 'checkbox',
                            'title'      => '允许上传图像',
                            'inline'     => true,
                            'options'    => $roles_options,
                            'default'    => $default_roles,
                            'desc'       => '全部取消则关闭该功能'
                        ),
                        array(
                            'id'         => 'video',
                            'type'       => 'checkbox',
                            'title'      => '允许上传视频',
                            'inline'     => true,
                            'options'    => $roles_options,
                            'default'    => $default_roles,
                            'desc'       => '全部取消则关闭该功能'
                        ),
                        array(
                            'id'         => 'file',
                            'type'       => 'checkbox',
                            'title'      => '允许上传文件',
                            'inline'     => true,
                            'options'    => $roles_options,
                            'default'    => $default_roles,
                            'desc'       => '全部取消则关闭该功能'
                        ),
                    )
                ),
            )
        ));
    }
    
    //支付设置
    public function pay_settings(){
        \CSF::createSection(self::$prefix, array(
            'parent'    => 'islide_normal_options',
            'id' => 'islide_normal_pay',
            'title'     => '支付设置',
            'icon'      => 'fab fa-alipay',
            'fields'    => array(
                array(
                    'content' => '<p><b>第三方支付不能保证100%安全，islide主题仅提供API接入服务，收款平台的可靠性请自行斟酌！</b></p>
                    <li>涉及到资金及信息安全，请勿使用盗版主题</li>
                    <li>收款接口选用，有相关执照的商家推荐使用官方接口。个人用户推荐使用讯虎PAY和Payjs</li>
                    <li>如需定制其它收款接口，欢迎与我联系<a href="http://wpa.qq.com/msgrd?v=3&amp;uin=3130153916&amp;site=qq&amp;menu=yes" target="_blank">QQ 3130153916</a></li>
                    <li>请不要使用个人支付接口从事违法活动，否则删除授权，并向公安机关举报！</li>',
                    'style'   => 'warning',
                    'type'    => 'submessage',
                ),
                array(
                    'id'      => 'pay_alipay',
                    'title'   => '支付宝收款接口',
                    'type'    => "select",
                    'options' => array(
                        '0'       => '关闭',
                        'alipay'  => '支付宝官方',
                        'xunhu'   => '迅虎支付（虎皮椒v4）',
                        'xunhu_hupijiao'   => '虎皮椒支付v3',
                        'yipay'   => '易支付 OR 码支付',
                    ),
                    'default' => '0',
                ),
                array(
                    'id'      => 'pay_wechat',
                    'title'   => '微信收款接口',
                    'type'    => "select",
                    'options' => array(
                        '0'         => '关闭',
                        'wecatpay'  => '微信官方',
                        'xunhu'   => '迅虎支付（虎皮椒v4）',
                        'xunhu_hupijiao'   => '虎皮椒支付v3',
                        'yipay'   => '易支付 OR 码支付',
                    ),
                    'default' => '0',
                ),
                //支付宝官方
                array(
                    'id'         => 'alipay',
                    'type'       => 'accordion',
                    'title'      => '支付宝官方',
                    'accordions' => array(
                        array(
                            'title'  => '支付宝官方',
                            'fields' => array(
                                array(
                                    'id'         => 'alipay_type',
                                    'type'       => 'radio',
                                    'title'      => '支付方式',
                                    'inline'     => true,
                                    'options'    => array(
                                        'normal' => '企业',
                                        'scan' => '当面付',
                                    ),
                                    'default'    => 'normal',
                                    'desc'    => '如果您是个人用户只能选择当面付，当面付支持手机和移动端支付',
                                ),
                                array(
                                    'id'         => 'appid',
                                    'type'       => 'text',
                                    'title'   => 'APPID',
                                    'desc'    => '打开链接： https://open.alipay.com 账户中心->密钥管理->开放平台密钥，填写您支付的应用的APPID',
                                ),
                                array(
                                    'id'         => 'private_key',
                                    'type'       => 'textarea',
                                    'title'   => '应用私钥',
                                    'class'      => 'compact',
                                    'desc'    => 'islide主题使用的是 RSA2 算法生成的私钥。请使用 RSA2 算法来生成。',
                                ),
                                array(
                                    'id'         => 'public_key',
                                    'type'       => 'textarea',
                                    'title'   => '支付宝公钥',
                                    'class'      => 'compact',
                                    'desc'    => '请在 账户中心->密钥管理->开放平台密钥，找到添加了支付功能的应用，根据你的加密类型，查看支付宝公钥。',
                                ),
                            )
                        )
                    ),
                ),
                //微信官方
                array(
                    'id'         => 'wecatpay',
                    'type'       => 'accordion',
                    'title'      => '微信官方',
                    'accordions' => array(
                        array(
                            'title'  => '微信官方',
                            'fields' => array(
                            )
                        )
                    ),
                ),
                //迅虎支付
                array(
                    'id'         => 'xunhu',
                    'type'       => 'accordion',
                    'title'      => '迅虎支付（虎皮椒v4）',
                    'accordions' => array(
                        array(
                            'title'  => '迅虎支付设置（支持支付宝和微信）',
                            'fields' => array(
                                array(
                                    'content' => '<p>迅虎支付又叫虎皮椒V4，是迅虎网络打造的一个全新的个人收款平台，申请简单，适合个人站长</p>
                                    <li>微信、支付宝支持PC端扫码支付</li>
                                    <li>微信支持微信内支付、APP跳转支付（H5支付）</li>
                                    <li>支付宝APP跳转支付（H5支付）</li>
                                    <li style="color:#ff2153;">请务请联系讯虎客服手动设置微信返回域名以及配置小票页面</li>
                                    <li>开通地址：<a target="_blank" href="https://pay.xunhuweb.com">点击跳转</a></li>',
                                    'style'   => 'info',
                                    'type'    => 'submessage',
                                ),
                                array(
                                    'id'         => 'xunhu_mchid',
                                    'type'       => 'text',
                                    'title'   => '商户号 MCHID',
                                    'desc'    => '进入迅虎支付平台，查看商户ID',
                                ),
                                array(
                                    'id'         => 'xunhu_appsecret',
                                    'type'       => 'text',
                                    'title'   => 'API密钥 PRIVATE KEY',
                                    'class'      => 'compact',
                                    'desc'    => '进入迅虎支付平台，查看应用PRIVATE KEY',
                                ),
                                array(
                                    'id'         => 'xunhu_gateway',
                                    'type'       => 'text',
                                    'title'   => '支付网关',
                                    'class'      => 'compact',
                                    'default'=> 'https://admin.xunhuweb.com',
                                    'desc'    => '不知道这个做什么用的请保持默认即可，默认网关：https://admin.xunhuweb.com。迅虎支付申请地址：<a href="https://pay.xunhuweb.com" target="_blank">https://pay.xunhuweb.com</a>',
                                ),
                                // array(
                                //     'id'      => 'xunhu_alipay_v2',
                                //     'type'    => 'switcher',
                                //     'title'   => '支付宝 2.0 WAP支付',
                                //     'default' => false,
                                //     'label'   => '如开通的支付宝接口为2.0版本，需开启此项',
                                    
                                // ),
                            )
                        )
                    ),
                ),
                array(
                    'id'         => 'xunhu_hupijiao',
                    'type'       => 'accordion',
                    'title'      => '虎皮椒支付v3',
                    'accordions' => array(
                        array(
                            'title'  => '虎皮椒支付v3设置（支持支付宝和微信）',
                            'fields' => array(
                                array(
                                    'content' => '<p>虎皮椒是迅虎网络旗下的支付产品，无需营业执照、无需企业，申请简单。适合个人站长申请，有一定的费用</p>
                                    <li>支持PC端扫码支付</li>
                                    <li>支付宝支持移动端跳转APP支付</li>
                                    <li>微信支持微信APP内支付</li>
                                    <li>注意：需要宝塔防火墙关闭禁止海外访问，才能回调成功</li>
                                    <li>开通地址：<a target="_blank" href="https://admin.xunhupay.com/sign-up/12207.html">点击跳转</a></li>',
                                    'style'   => 'info',
                                    'type'    => 'submessage',
                                ),
                                array(
                                    'title'   => '微信：appid',
                                    'id'      => 'wechat_appid',
                                    'default' => '',
                                    'type'    => 'text',
                                ),
                                array(
                                    'title'   => '微信：appsecret',
                                    'class'   => 'compact',
                                    'id'      => 'wechat_appsecret',
                                    'default' => '',
                                    'type'    => 'text',
                                ),
                                array(
                                    'title' => '支付宝：appid',
                                    'id'    => 'alipay_appid',
                                    'type'  => 'text',
                                ),
                                array(
                                    'title'   => '支付宝：appsecret',
                                    'class'   => 'compact',
                                    'id'      => 'alipay_appsecret',
                                    'default' => '',
                                    'type'    => 'text',
                                ),
                                array(
                                    'title'   => '支付网关',
                                    'id'      => 'hupijiao_gateway',
                                    'default' => 'https://api.xunhupay.com/payment/do.html',
                                    'type'    => 'text',
                                    'desc'    => '如果服务商单独提供了网关地址，请在此填写，默认为<code>https://api.xunhupay.com/payment/do.html</code>',
                                ),
                            ),
                        ),
                    ),
                ),
                //易支付
                array(
                    'id'         => 'yipay',
                    'type'       => 'accordion',
                    'title'      => '易支付 OR 码支付',
                    'subtitle'       => '现在网上大多数码支付都可以使用易支付接口',
                    'accordions' => array(
                        array(
                            'title'  => '易支付 OR 码支付（支持支付宝和微信）',
                            'fields' => array(
                                array(
                                    'content' => '<li>易支付是一个常见的支付系统源码，任何人都可以下载并搭建</li>
                                    <li>同时市面上多数的小规模支付平台都是由易支付修改而来，例如部分码支付、源支付等等</li>
                                    <li style="color:#ff4021;">现在网上大多数码支付都可以使用易支付接口</li>
                                    <li style="color:#ff4021;">注意：由于此接口服务商众多，主题只负责技术接入，平台可靠性请自行斟酌</li>',
                                    'style'   => 'info',
                                    'type'    => 'submessage',
                                ),
                                array(
                                    'id'      => 'yipay_id',
                                    'title'   => '商户ID',
                                    'type'    => 'text',
                                    'default' => '',
                                ),
                                array(
                                    'id'      => 'yipay_key',
                                    'title'   => '商户KEY',
                                    'type'    => 'text',
                                    'class'   => 'compact',
                                    'default' => '',
                                ),
                                array(
                                    
                                    'id'      => 'yipay_gateway',
                                    'title'   => '接口地址',
                                    'type'    => 'text',
                                    'default' => '',
                                    'desc'    => '如果服务商提供的接口地址，示例：<code>http(s)://xxx.xxxx.com</code>，后面不带 /',
                                ),
                            ),
                        ),
                    ),
                ),
            )
        ));
    }
    
    //密码验证设置
    public function verification_code_settings(){
        \CSF::createSection(self::$prefix, array(
            'parent'    => 'islide_normal_options',
            'title'     => '密码验证',
            'icon'      => 'fas fa-key',
            'fields'    => array(
                array(
                    'content' => '<p><b>密码验证用于文章隐蔽内容或资源下载，用户输入设定验证密码才可查看隐藏内容或下载</b></p>
                    <li>配合公众号体验更加，公众号涨粉</li>',
                    'style'   => 'info',
                    'type'    => 'submessage',
                ),
                array(
                    'id'     => 'password_verify',
                    'type'   => 'fieldset',
                    'title'  => '',
                    'fields' => array(
                        array(
                            'id'       => 'code',
                            'type'       => 'text',
                            'title'    => '验证密码',
                            'desc'       => '密码长度自定义',
                            'default'  => '1234',
                        ),
                        array(
                            'id'      => 'day',
                            'type'    => 'spinner',
                            'title'   => '有效期',
                            'min'     => 1,
                            'unit'    => '天',
                            'desc'       => '用户多少天后需要重新输入密码',
                            'default' => 1,
                        ),
                        array(
                            'id'    => 'qrcode_img',
                            'type'  => 'upload',
                            'title'    => '微信公众号二维码或其他图片',
                            'desc'     => '尽量用小图（100px * 100px）',
                            'preview' => true,
                            'library' => 'image',
                        ),
                        array(
                            'id'       => 'tips_text',
                            'type'       => 'textarea',
                            'title'    => '提示文字信息',
                            'desc'       => '支持HTML代码，请注意代码规范及标签闭合',
                            'default'  => '<p>微信扫一扫关注公众号，回复【验证码】获取密码</p>',
                        ),
                    )
                )
            )
        ));
    }
    
    public function ip_location_settings(){
        \CSF::createSection(self::$prefix, array(
            'parent'    => 'islide_normal_options',
            'title'     => 'IP归属地',
            'icon'      => 'fas fa-map-marker-alt',
            'fields'    => array(
                // array(
                //     'content' => '<p><b>密码验证用于文章隐蔽内容或资源下载，用户输入设定验证密码才可查看隐藏内容或下载</b></p>
                //     <li>配合公众号体验更加，公众号涨粉</li>',
                //     'style'   => 'info',
                //     'type'    => 'submessage',
                // ),
                array(
                    'type'    => 'heading',
                    'content' => 'IP归属地',
                ),
                //手机短信
                array(
                    'title' => '选择位置服务商',
                    'id' => 'ip_location_type',
                    'type' => 'radio',
                    'inline'  => true,
                    'options' => array(
                        'tencent'   => '腾讯位置服务',
                        'amap'   => '高德位置服务',
                        'pconline' => '太平洋免费接口（无需配置）'
                    ),
                    'default' => 'tencent',
                ),
                array(
                    'title' => 'IP属地显示格式',
                    'id' => 'ip_location_format',
                    'type' => 'select',
                    'options' => array(
                        'np'   => '国家+省份',
                        'npc'   => '国家+省份+城市',
                        'p'   => '省份',
                        'pc'   => '省份+城市',
                    ),
                    'default' => 'p',
                ),
                //腾讯位置服务设置
                array(
                    'title'      => '',
                    'id'         => 'tencent_ip_location',
                    'type'       => 'fieldset',
                    'fields'     => array(
                        array(
                            'type'    => 'subheading',
                            'content' => '腾讯位置服务（免费 10000次/日）',
                        ),
                        array(
                            'content' => '腾讯位置服务申请地址：<a target="_blank" href="https://lbs.qq.com/dev/console/application/mine">https://lbs.qq.com/dev/console/application/mine</a><br>添加KEY时，启用产品请勾选WebServiceAPI，同时建议选择签名校验',
                            'style'   => 'info',
                            'type'    => 'submessage',
                        ),
                        array(
                            'title' => 'App Key',
                            'id'    => 'app_key',
                            'type'  => 'text',
                        ),
                        array(
                            'title' => '签名校验 Secret Key',
                            'id'    => 'secret_key',
                            'type'  => 'text',
                            'desc'  => '如果应用KEY设置了签名校验，请在此填写签名校验的Secret key',
                        ),
                    ),
                    'dependency' =>  array(
                        array('ip_location_type', '==', 'tencent'),
                    ),
                ),
                //高德位置服务设置
                array(
                    'title'      => '',
                    'id'         => 'amap_ip_location',
                    'type'       => 'fieldset',
                    'fields'     => array(
                        array(
                            'type'    => 'subheading',
                            'content' => '高德位置服务（个人免费 5000次/日）',
                        ),
                        array(
                            'content' => '高德位置服务申请地址：<a target="_blank" href="https://console.amap.com/dev/key/app">https://console.amap.com/dev/key/app</a><br>添加KEY时，服务平台请选择Web服务，同时建议开启数字签名权限<br>仅支持IPV4，不支持国外IP解析',
                            'style'   => 'info',
                            'type'    => 'submessage',
                        ),
                        array(
                            'title' => 'Key',
                            'id'    => 'app_key',
                            'type'  => 'text',
                        ),
                        array(
                            'title' => '签名校验 Secret Key',
                            'id'    => 'secret_key',
                            'type'  => 'text',
                            'desc'  => '如果应用KEY设置了数据签名，请在此填写数据签名的秘钥',
                        ),
                    ),
                    'dependency' =>  array(
                        array('ip_location_type', '==', 'amap'),
                    ),
                ),
            )
        ));
    }
    
    //邮件
    public function email_settings(){
        \CSF::createSection(self::$prefix, array(
            'parent'      => 'islide_normal_options',
            'title'       => 'Email Smtp',
            'icon'        => 'fa fa-fw fa-envelope-o',
            'description' => '',
            'fields'      => array(
                array(
                    'content'   => '<p>频繁发送邮件，可能会被收件服务器列为垃圾邮件</p><p>功能和Wordpress SMTP插件一致，所以！不能和其他SMTP插件一起开启！</p>',
                    'style'     => 'warning',
                    'type'      => 'submessage',
                ),
                array(
                    'id'    => 'email_from_name',
                    'title' => '自定义发件人名称',
                    'type'  => 'text',
                    'desc'  => '列如：islide主题',
                    'default' => get_bloginfo('title'),
                ),
                array(
                    'title'   => '邮件SMTP',
                    'id'      => 'email_smtp_open',
                    'type'    => 'switcher',
                    'default' => false,
                ),
                array(
                    'title'      => '',
                    'id'         => 'email_smtp',
                    'type'       => 'fieldset',
                    'fields'     => array(
                        array(
                            'type'    => 'subheading',
                            'content' => 'SMTP配置',
                        ),
                        // array(
                        //     'id'    => 'form',
                        //     'title' => '发件人地址',
                        //     'type'  => 'text',
                        // ),
                        
                        array(
                            'id'    => 'host',
                            'title' => 'SMTP邮件服务器地址',
                            'type'  => 'text',
                            'desc'  => '列如：smtp.qq.com 、smtp.163.com',
                        ),
                        array(
                            'id' => 'smtp_secure',
                            'title' => 'SMTP加密方式',
                            'type' => 'radio',
                            'inline'  => true,
                            'options' => array(
                                'none' => 'None',
                                'ssl'   => 'SSL',
                                'tls'   => 'TLS',
                            ),
                            'default' => 'none',
                        ),
                        array(
                            'id'    => 'port',
                            'title' => 'SMTP邮件服务器端口',
                            'type'  => 'number',
                            'desc'  => '列如：80、465',
                        ),
                        array(
                            'id'    => 'smtp_auth',
                            'title' => 'SMTP AUTH认证',
                            'type'  => 'switcher',
                            'default'    => true,
                        ),
                        array(
                            'id'    => 'username',
                            'title' => 'SMTP 用户名',
                            'subtitle' => '及发件人地址',
                            'type'  => 'text',
                            'desc'  => '例如：xxxxx@qq.com'
                        ),
                        array(
                            'id'    => 'password',
                            'title' => 'SMTP 密码',
                            'subtitle' => '国内一般用的是授权码',
                            'type'  => 'text',
                        ),
                    ),
                    'dependency'  => array('email_smtp_open', '!=', '', '', 'visible'),
                ),
            ),
        ));
    }
    
    //投诉举报
    public function report_settings(){
        \CSF::createSection(self::$prefix, array(
            'parent'      => 'islide_normal_options',
            'title'       => '投诉举报',
            'icon'        => 'fa fa-fw fa-envelope-o',
            'description' => '',
            'fields'      => array(
                array(
                    'type'    => 'heading',
                    'content' => '投诉举报',
                ),
                array(
                    'id'      => 'report_open',
                    'type'    => 'switcher',
                    'title'   => '投诉举报功能',
                    'desc'    => '是否启用全站文章与帖子投诉举报功能',
                    'default' => true,
                ),
                array(
                'id'        => 'report_types',
                'type'      => 'repeater',
                'title'     => '自定义举报类型',
                'button_title' => '增加类型',
                'fields'    => array(
                    array(
                        'id'    => 'type',
                        'type'  => 'text',
                        //'title' => '举报类型',
                    ),
                ),
                'default'   => array(
                    array(
                        'type' => '色情低俗',
                    ),
                    array(
                        'type' => '违法违规',
                    ),
                    array(
                        'type' => '不实信息',
                    ),
                    array(
                        'type' => '违规营销',
                    ),
                    array(
                        'type' => '政治敏感',
                    ),
                    array(
                        'type' => '危害人身安全',
                    ),
                    array(
                        'type' => '未成年相关',
                    ),
                    array(
                        'type' => '侵犯权益',
                    ),
                    array(
                        'type' => '其他',
                    ),
                )
                ),
            ),
        ));
    }
    
    //自定义代码
    public function custom_code_settings(){
        \CSF::createSection(self::$prefix, array(
            'parent'      => 'islide_normal_options',
            'id'          => 'islide_normal_code',
            'title'       => '自定义代码',
            'icon'        => 'fa fa-fw fa-code',
            'description' => '',
            'fields'      => array(
                array(
                    'content'   => '<p><b>自定义代码提醒事项：</b></p><li>任何情况下都不建议修改主题源文件，自定义代码可放于此处</li><li>在此处添加的自定义代码会保存到数据库，不会因主题升级而丢失</li><li>使用自义定代码，需要有一定的代码基础</li><li>代码不规范、或代码错误将会引起意料不到的问题</li><li>如果网站遇到未知错误，请首先检查此处的代码是否规范、无误</li>',
                    'style'     => 'warning',
                    'type'      => 'submessage',
                ),
                array(
                    'title'     => __('头部HTML标签代码', 'islide'),
                    'subtitle'  => sprintf(__( '你可以添加站点的%s等标签，通常情况下，这里是用来放置第三方台验证站点所有权时使用的。', 'islide' ),'<code>'.htmlspecialchars('<meta>、<link>、<style>、<script>').'</code>'),
                    'id'        => 'header_code',
                    'default'   => '',
                    'settings'  => array(
                        'theme' => 'dracula',
                    ),
                    'sanitize'  => false,
                    'type'      => 'code_editor',
                ),
                array(
                    'title'     => __('底部HTML标签代码', 'islide'),
                    'subtitle'  => sprintf(__( '你可以添加站点的%s等标签，通常情况下，这里是用来加载额外的JS、css文件，或者放置统计代码。', 'islide' ),'<code>'.htmlspecialchars('<style>、<script>').'</code>'),
                    'id'        => 'footer_code',
                    'default'   => '',
                    'settings'  => array(
                        'theme' => 'dracula',
                    ),
                    'sanitize'  => false,
                    'type'      => 'code_editor',
                )
            ),
        ));
    }
    
}