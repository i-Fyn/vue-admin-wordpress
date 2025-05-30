<?php
namespace islide\Modules\Settings;
use islide\Modules\Common\User;
use islide\Modules\Common\Message;
use islide\Modules\Common\Record;
//用户相关设置
class Users{

    //设置主KEY
    public static $prefix = 'islide_main_options';

    public function init(){
        
        add_action ( 'personal_options_update', array( $this, 'save_profile_form' ));
        add_action( 'edit_user_profile_update', array( $this, 'save_profile_form' ));
        
        //过滤掉积分或余额变更原因
        add_filter('csf_user_profile_options_save', function ($data,$user_id){
            delete_user_meta($user_id,'islide_change_why');
            unset($data['islide_change_why']);
            return $data;
        },10,2);
        
        add_action ( 'csf_user_profile_options_saved', array( $this, 'save_profile_saved' ),10,2);
        $this->register_user_profile_metabox();
        
        
        add_filter('manage_users_columns', array($this,'custom_users_column' ));
        add_filter('manage_users_sortable_columns', array($this,'custom_users_column_sortable') );
        add_filter('manage_users_custom_column', array($this,'custom_users_column_content'), 10, 3 );
        add_filter('views_users',  array($this,'custom_users_views'));
        add_filter('users_list_table_query_args', array($this, 'custom_users_column_orderby'));
        
        $this->users_options_page();
    }
    
    // 添加自定义列
    public function custom_users_column( $columns ) {
        unset($columns['name']); // 可能需要移除默认列
        unset($columns['role']);

        array_insert($columns,1,array('id'=>'ID'));
        array_insert($columns,3,array('user_name'=>'昵称'));
        $columns['user_assets'] = '财富';
        $columns['user_lv'] = '等级';
        $columns['user_vip'] = '会员';
        $columns['user_vip_end'] = '会员过期时间';
        $columns['registration_date'] = '注册日期'; // add new
        $columns['ip_location'] = '登录详情'; // add new
        return $columns;
    
    }
    
    // 添加或移除可排序的列
    public function custom_users_column_sortable( $sortable_columns ) {
        return wp_parse_args( array( 
            'id'=>'id',
            'registration_date' => 'registered',
            'user_name' => 'display_name',
            'user_lv' => 'user_lv',
            'user_vip' => 'user_vip',
            'user_vip_end'=>'user_vip_end',
         ), $sortable_columns );
    }
    
    // 显示自定义列的内容
    public function custom_users_column_content($value, $column_name, $user_id){
        // 根据列名和用户ID获取相应的内容
        switch ($column_name) {
            case 'id':
                return $user_id;
                break;
            case 'user_name':
                return '<a href="'.get_author_posts_url($user_id).'">'.get_the_author_meta('display_name', $user_id).'</a>'; // 获取用户的昵称
                break;
            case 'user_assets':
                $user_data = User::get_user_custom_data( $user_id );
                return '<span>余额：'.$user_data['money'].' <br> 积分：'.$user_data['credit'].'</span>';
                break;
            case 'user_lv':
                $lv = User::get_user_lv($user_id); // 获取用户的等级
                return '<span class="user-lv">'.(!empty($lv['icon']) ? '<img src="'.$lv['icon'].'" style=" width: auto; height: 16px; ">' : $lv['name']).'</span>';
                break;
            case 'user_vip':
                $vip = User::get_user_vip($user_id); // 获取用户的会员状态
                return '<span class="user-vip">'.(!empty($vip['image']) ? '<img src="'.$vip['image'].'" style=" width: auto; height: 16px; ">' : $vip['name']).'</span>';
                break;
            case 'user_vip_end':
                $end_date = get_user_meta($user_id,'islide_vip_exp_date',true); // 获取用户的会员过期时间
                
                if(empty($end_date)) return;
                
                if($end_date === '0'){
                    return '永不过期';
                }else{
                    return wp_date("Y-m-d H:i:s",$end_date);
                }
                
                return;
                break;
            case 'registration_date':
                return get_date_from_gmt(get_the_author_meta( 'registered', $user_id ),'Y-m-d H:i:s' );
                break;
            case 'ip_location':
                $ip_location = get_user_meta( $user_id, 'islide_login_ip_location',true);
                
                if(!empty($ip_location) && is_array($ip_location)) {
                    return '<span>时间：'.$ip_location['date'].' <br> IP：'.$ip_location['ip'].'<br> 属地：'.$ip_location['nation'] . $ip_location['province'] . $ip_location['city'] . $ip_location['district'].'</span>';
                }
                break;
            default:
                break;
        }
    
        // 输出内容
        return $value;
        
    }
    
    // 添加自定义用户筛选视图
    public function custom_users_views($views){
        $vip_data = islide_get_option('user_vip_group');
        $custom_view = array();
        
        foreach ($vip_data as $key => $value) {
            $custom_view['vip' . $key] = '<a'.(isset($_REQUEST['vip']) && $_REQUEST['vip'] == $key ? ' class="current"' : '').' href="' . admin_url('users.php?vip='.$key) . '">'.$value['name'].'<span class="count">（'.User::count_users_custom_field('islide_vip','vip' .$key).'）</span></a>';
        }
        
        $views = array_merge($views, $custom_view);
        return $views;
    }
    
    // 处理自定义列的排序
    public function custom_users_column_orderby($vars){

        //默认排序方式为注册时间
        if (!isset($vars['orderby'])) {
            $vars = array_merge( $vars, array(
                'orderby' => 'registered'
            ));
        }else {
            if ('user_vip' == $vars['orderby'] ) {
                $vars = array_merge($vars, array(
                    'meta_key' => 'islide_vip',
                    'orderby' => 'meta_value'
                ));
            }
            
            if ('user_lv' == $vars['orderby'] ) {
                $vars = array_merge($vars, array(
                    'meta_key' => 'islide_lv',
                    'orderby' => 'meta_value_num'
                ));
            }
        }

        if(isset($_REQUEST['vip'])) {
            $vars = array_merge($vars, array(
                'meta_key' => 'islide_vip',
                'meta_value' => 'vip'.$_REQUEST['vip']
            ));
        }
        
        return $vars;
    }
    
    /**
     * 注册用户metabox 设置
     *
     * @return void
     * 
     * @version 1.0.0
     * @since 2023
     */
    public function register_user_profile_metabox(){
        
      $prefix = 'user_profile_options';
      
        \CSF::createProfileOptions( $prefix, array(
            'data_type' => 'unserialize', // The type of the database save options. `serialize` or `unserialize`
        ));
      
        \CSF::createSection( $prefix, array(
          'title'  => '用户钱包',
          'fields' => array(
                array(
                    'id'    => 'islide_credit',
                    'type'  => 'text',
                    'title' => '积分',
                    'output_mode' => 'width',
                ),
                array(
                    'id'    => 'islide_money',
                    'type'  => 'text',
                    'title' => '余额',
                ),
                array(
                    'id'      => 'islide_change_why',
                    'type'    => 'textarea',
                    'title'   => '积分或余额变更原因',
                    'desc'    => '当用户的积分或余额有变更的时候，可以在此备注原因，用户的钱包通知将会显示变更原因'
                ),
            )
        ));
        
        
        $lvs = User::get_user_roles();
        
        $lv = array();
        $vip = array(''=>'无');

        foreach ($lvs as $k => $v) {
            if(strpos($k,'vip') !== false){
                $vip[$k] = $v['name']; 
            }

            if(strpos($k,'lv') !== false){
                $lv[$k] = $v['name'];
            }
        }
        
        \CSF::createSection( $prefix, array(
          'title'  => '用户等级',
          'fields' => array(
                array(
                    'id'         => 'islide_lv',
                    'type'       => 'text',
                    'title'      => '用户等级',
                    'attributes' => array(
                        'readonly' => 'readonly',
                    ),
                ),
                array(
                    'id'    => 'islide_lv_exp',
                    'type'  => 'text',
                    'title' => '用户等级经验值',
                ),
            )
        ));
        
        \CSF::createSection( $prefix, array(
          'title'  => '用户VIP等级',
          'fields' => array(
                array(
                    'id'    => 'islide_vip',
                    'type'  => 'radio',
                    'title' => 'VIP等级',
                    'options' => $vip
                ),
                array(
                    'id'         => 'islide_vip_exp_date',
                    'type'       => 'date',
                    'title'      => '会员有效期',
                    'desc'       => '<p>请输入或选择有效期，请确保格式正确，例如：<code>2023-10-10 23:59:59</code></p>如果需要设置为“永久有效会员”，请手动设置为：<code>0</code>',
                    'settings'   => array(
                        'dateFormat'  => 'yy-mm-dd 23:59:59',
                        'changeMonth' => true,
                        'changeYear'  => true,
                    ),
                    'default' => 0,
                    'sanitize' => function ( $value ) {
                        if(!is_numeric($value)) {
                            return wp_strtotime( $value );
                        }
                        
                        return $value;
                    }
                )
            )
        ));
    }
    
    public function save_profile_saved($data,$user_id){
        
        if(!current_user_can('administrator')) return;
        
        if(isset($data['islide_vip']) && (string)$data['islide_vip'] === '') {
            delete_user_meta($user_id,'islide_vip');
            delete_user_meta($user_id,'islide_vip_exp_date');
        }
    }
    
    public function save_profile_form($user_id){
        
        if(!current_user_can('administrator')) return;
        
        if(!isset($_REQUEST['user_profile_options'])) return;
        $options = $_REQUEST['user_profile_options'];

        //积分变动
        $credit = (int)get_user_meta($user_id,'islide_credit',true);
        if(isset($options['islide_credit']) && $credit !== (int)$options['islide_credit']){
            $credit = (int)$options['islide_credit'] - $credit;
            Message::update_message(array(
                'sender_id' => 0,
                'receiver_id' => $user_id,
                'title' => '积分变动',
                'content' => sprintf('叮咚！管理员对您的积分变更：%s 积分，变更原因：%s',$credit < 0 ? '减少 '.abs($credit) : '增加 '.$credit,$options['islide_change_why'] ?: '未注明'),
                'type' => 'wallet',
            ));
            
            Record::update_data(array(
                'user_id' => $user_id,
                'record_type' => 'credit',
                'value' => $credit,
                'type' => 'admin',
                'type_text' => '管理员操作',
                'total' => (int)$options['islide_credit'],
                'content' => $options['islide_change_why'] ?: '未注明',
            ));
        }
        
        //余额变动
        $money = (int)get_user_meta($user_id,'islide_money',true);
        if(isset($options['islide_money']) && $money !== (int)$options['islide_money']){
            $money = (float)$options['islide_money'] - $money;
            Message::update_message(array(
                'sender_id' => 0,
                'receiver_id' => $user_id,
                'title' => '余额变动',
                'content' => sprintf('叮咚！管理员对您的余额变更：%s 元，变更原因：%s',$money < 0 ? '减少 '.abs($money) : '增加 '.$money,$options['islide_change_why'] ?: '未注明'),
                'type' => 'wallet',
            ));
            
            Record::update_data(array(
                'user_id' => $user_id,
                'record_type' => 'money',
                'value' => $money,
                'type' => 'admin',
                'type_text' => '管理员操作',
                'total' => (float)$options['islide_money'],
                'content' => $options['islide_change_why'] ?: '未注明',
            ));
        }
        
        //经验变动
        $exp = (int)get_user_meta($user_id,'islide_lv_exp',true);
        if(isset($options['islide_lv_exp']) && $exp !== (int)$options['islide_lv_exp']){
            $exp = (float)$options['islide_lv_exp'] - $exp;
            
            Record::update_data(array(
                'user_id' => $user_id,
                'record_type' => 'exp',
                'value' => $exp,
                'type' => 'admin',
                'type_text' => '管理员操作',
                'total' => (int)$options['islide_lv_exp'],
                'content' => $options['islide_change_why'] ?: '未注明',
            ));
        }

        //会员变动
        if(isset($options['islide_vip']) && isset($options['islide_vip_exp_date'])) {
            
            $vip = get_user_meta($user_id,'islide_vip',true);
            $exp_date = get_user_meta($user_id,'islide_vip_exp_date',true);
            
            
            $new_exp_date = !is_numeric($options['islide_vip_exp_date']) ? wp_strtotime($options['islide_vip_exp_date']) : $options['islide_vip_exp_date'];
            $new_vip_date = (string)$options['islide_vip_exp_date'] === '0' ? '永久' : wp_date('Y-m-d',$new_exp_date);
            
            $roles = User::get_user_roles();
            $new_vip = $roles[$options['islide_vip']]['name'];
            
            if((string)$options['islide_vip'] === '' && $options['islide_vip'] != $vip) {
                Message::update_message(array(
                    'sender_id' => 0,
                    'receiver_id' => $user_id,
                    'title' => '',
                    'content' => '叮咚！管理员手动取消（关闭）你的VIP会员，你无法继续享受权益。',
                    'type' => 'vip',
                ));
                
            }else if(($vip !== (string)$options['islide_vip'] || $exp_date != $new_exp_date) && $new_vip) {
                Message::update_message(array(
                    'sender_id' => 0,
                    'receiver_id' => $user_id,
                    'title' => '',
                    'content' => '叮咚！管理员手动变更或开通：'.$new_vip.'服务，目前有效期至'.$new_vip_date.'。',
                    'type' => 'vip',
                ));
                
            }
        }
        
        
    }
    
    /**
     * 用户相关设置
     *
     * @return void
     * 
     * @version 1.0.0
     * @since 2023
     */
    public function users_options_page(){
        
        \CSF::createSection(self::$prefix, array(
            'id'    => 'islide_users_options',
            'title' => '用户相关',
            'icon'  => 'fa fa-fw fa-user-o',
        ));
        
        //加载自定义模块设置
        $this->users_normal_settings();
        
        $this->users_login_settings();
        
        $this->users_vip_settings();
        
        $this->users_balance_settings();
        
        $this->users_credit_settings();
        
        $this->users_lv_settings();
        
        //用户签到设置
        $this->users_signin_settings();
        
        $this->users_task_settings();
        
        //推广返佣
        $this->users_distribution_settings();
        
        //认证设置
        $this->users_verify_settings();
    }
    
    //常规设置
    public function users_normal_settings(){
        \CSF::createSection(self::$prefix, array(
            'parent'      => 'islide_users_options',
            'title'       => '综合设置',
            'icon'        => 'fa fa-fw fa-user-o',
            'fields'      => array(
                array(
                    'id'      => 'account_rewrite_slug',
                    'type'    => 'text',
                    'title'   => '个人中心URL别名',
                    'default' => 'account',
                    'desc'    => '开启固定链接之后，可以在此自定义用户中心的链接后缀URL别名，默认为<code>account</code>
                    <div style="color:#ff4021;"><i class="fa fa-fw fa-info-circle fa-fw"></i>如非必要，建议留空保持默认</div>',
                    
                ),
                array(
                    'id'      => 'avatar_default_img',
                    'title'   => '用户默认头像',
                    'type' => 'upload' ,
                    'library' => 'image', 
                    'desc'    => '用户默认头像，建议尺寸100px*100px',
                    'preview' => true,
                    'default' => IS_THEME_URI. '/Assets/fontend/images/default-avatar.png',
                ),
                array(
                    'id'      => 'user_cover_img',
                    'type'    => 'upload',
                    'title'   => '用户默认封面',
                    'library' => 'image',
                    'preview' => true,
                    'desc'    => '默认封面图，建议尺寸1000x400,如果分类页未开启侧边栏，请选择更大的尺寸',
                    'help'    => '用户可在用户中心设置自己的封面图，如用户未单独设置则显示此图像',
                    'default' => '',
                    
                ),
                array(
                    'id'      => 'user_desc',
                    'type'    => 'text',
                    'title'   => '用户默认签名',
                    'help'    => '用户未设置签名时候，显示的签名',
                    'default' => '这家伙很懒，什么都没有留下...',
                    
                ),
                array(
                    'id'         => 'user_ip_location_show',
                    'title'      => '显示用户IP属地',
                    'type'       => 'switcher',
                    'default'    => true,
                    // 'desc'       => '开启此功能后，用户每次登录重新更新地址<br>由于需要使用网络接口通过用户IP地址获取地理位置，不能保证所有地址都能显示<br>如果您的服务器使用了代理功能，则无法正确的获取用户IP地址，则也无法正常显示<br><a href="'.admin_url('/admin.php?page=islide_main_options#tab=常规设置/ip归属地').'" target="_blank">常规设置/ip归属地</a>设置使用其他接口',
                ),
            )
        ));
    }
    
    //注册与登录
    public function users_login_settings(){
        //注册与登录
        \CSF::createSection(self::$prefix, array(
            'parent'     => 'islide_users_options',
            'title'      => '注册与登录',
            'icon'       => 'fa fa-fw fa-copy',
            'fields'     => array(
                array(
                    'id'      => 'allow_login',
                    'type'    => 'switcher',
                    'title'   => '开启登录功能',
                    'label'   => '前台开启登录功能',
                    'desc'    => '开启后前台允许用户登录，关闭后用户无法登录，不影响后台管理员登录',
                    'default' => true,
                ),
                array(
                    'id'      => 'allow_register',
                    'type'    => 'switcher',
                    'title'   => '开启注册功能',
                    'label'   => '前台开启注册功能',
                    'desc'    => '建议将wp设置->常规中的<任何人都可以注册>的勾选项去掉，防止机器人注册。',
                    'default' => true,
                ),
                array(
                    'id'      => 'login_time',
                    'type'    => 'spinner',
                    'title'   => '登陆时效',
                    'desc'    => '用户登陆之后将会有一段时间保持登陆状态，这里可以设置登陆状态的时效，出于安全考虑，一般不超过7天。',
                    'max'     => 365,
                    'min'     => 1,
                    'step'    => 1,
                    'unit'    => '天',
                    'default' => 7,
                ),
                array(
                    'id'      => 'allow_cookie',
                    'type'    => 'switcher',
                    'title'   => '开启cookie兼容模式',
                    'desc'    => '不知道干啥用的，请关闭，不用开启。如果您使用了一些插件，涉及到用户登录的情况，请开启此项。',
                    'default' => true,
                ),
                array(
                    'title' => '开启使用邀请码注册',
                    'id' => 'invite_code_type',
                    'type' => 'select',
                    'options' => array(
                        0 => '关闭',
                        1 => '必填',
                        2 => '选填',
                    ),
                    'default' => 0,
                ),
                array(
                    'title'      => '邀请码获取地址',
                    'id'         => 'invite_code_url',
                    'type'       => 'textarea',
                    'desc'       => '您可以在此处填写HTML内容 <code>'.esc_html('<a href="获取邀请码地址">获取邀请码</a>').'</code>',
                    'default' => '<a href="获取邀请码地址">获取邀请码</a>',
                    'dependency' => array('invite_code_type', '!=', '0'),
                ),
                array(
                    'title'      => '',
                    'id'         => 'agreement',
                    'type'       => 'fieldset',
                    'fields'     => array(
                        array(
                            'title' => '隐私政策网址',
                            'id'    => 'privacy',
                            'type'  => 'text',
                        ),
                        array(
                            'title' => '用户协议网址',
                            'id'    => 'agreement',
                            'type'  => 'text',
                        ),
                    ),
                ),
                array(
                    'id'      => 'allow_slider_captcha',
                    'type'    => 'switcher',
                    'title'   => '允许登录或注册滑块验证',
                    'label'   => '用于登录注册防止暴力破解攻击',
                    'default' => true,
                    
                ),
                array(
                    'id'      => 'allow_register_check',
                    'type'    => 'switcher',
                    'title'   => '允许注册身份验证',
                    'label'   => '用于注册，找回密码需要验证邮箱或手机号',
                    'default' => false,
                    
                ),
                array(
                    'id'         => 'register_check_type',
                    'type'       => 'radio',
                    'title'      => ' ',
                    'subtitle'   => '验证方式',
                    'options'    => array(
                        'email'       => '邮箱验证',
                        'tel'         => '手机验证',
                        'telandemail' => '邮箱或手机验证',
                    ),
                    'default'    => 'email',
                    'dependency' => array('allow_register_check', '==', '1'),
                ),
                array(
                    'type'    => 'heading',
                    'content' => '发送手机短信设置',
                    'dependency' =>  array(
                        array('allow_register_check', '==', '1'),
                        array('register_check_type', 'any', 'tel,telandemail'),
                    ),
                ),
                //手机短信
                array(
                    'title' => '选择手机短信服务商',
                    'id' => 'sms_type',
                    'type' => 'select',
                    'options' => array(
                        'aliyun' => '阿里云',
                        'tencent'   => '腾讯云',
                        // 'yunpian'   => '云片',
                        // 'juhe'   => '聚合',
                        // 'zhongzheng'   => '中正云',
                        // 'submail'   => '赛邮云',
                        // 'others'   => '其他'
                    ),
                    'default' => 'aliyun',
                    'dependency' =>  array(
                        array('allow_register_check', '==', '1'),
                        array('register_check_type', 'any', 'tel,telandemail'),
                    ),
                ),
                //阿里云短信设置
                array(
                    'title'      => '',
                    'id'         => 'aliyun_sms',
                    'type'       => 'fieldset',
                    'fields'     => array(
                        array(
                            'type'    => 'subheading',
                            'content' => '阿里云短信配置',
                        ),
                        array(
                            'content' => '阿里云短信申请地址：<a target="_blank" href="https://www.aliyun.com/product/sms">https://www.aliyun.com/product/sms</a>',
                            'style'   => 'info',
                            'type'    => 'submessage',
                        ),
                        array(
                            'title' => 'AccessKey Id',
                            'id'    => 'key_id',
                            'type'  => 'text',
                            'desc'    => '阿里云控制台->鼠标放到右上角头像上->accessKeys->AccessKey ID。',
                        ),
                        array(
                            'title' => 'Access Key Secret',
                            'id'    => 'key_secret',
                            'type'  => 'text',
                            'desc'    => '阿里云控制台->鼠标放到右上角头像上->accessKeys->Access Key Secret。',
                        ),
                        array(
                            'title' => '签名名称',
                            'id'    => 'sign_name',
                            'type'  => 'text',
                            'desc'    => '阿里云控制台->短信服务控制台->国内消息->签名管理->签名名称。',
                        ),
                        array(
                            'title' => '模板CODE',
                            'id'    => 'template_code',
                            'type'  => 'text',
                            'desc'    => '阿里云控制台->短信服务控制台->国内消息->模板管理->模板CODE。',
                        ),
                    ),
                    'dependency' =>  array(
                        array('allow_register_check', '==', '1'),
                        array('register_check_type', 'any', 'tel,telandemail'),
                        array('sms_type', 'any', 'aliyun'),
                    ),
                ),
                //腾讯云短信设置
                array(
                    'title'      => '',
                    'id'         => 'tencent_sms',
                    'type'       => 'fieldset',
                    'fields'     => array(
                        array(
                            'type'    => 'subheading',
                            'content' => '腾讯云短信配置',
                        ),
                        array(
                            'content' => '腾讯云短信申请地址：<a target="_blank" href="https://cloud.tencent.com/product/sms">https://cloud.tencent.com/product/sms</a>',
                            'style'   => 'info',
                            'type'    => 'submessage',
                        ),
                        array(
                            'title' => 'SDK AppID',
                            'id'    => 'app_id',
                            'type'  => 'text',
                            'desc'    => '腾讯云短信控制台->应用管理->应用列表',
                        ),
                        array(
                            'title' => 'App Key',
                            'id'    => 'app_key',
                            'type'  => 'text',
                            'desc'    => '腾讯云短信控制台->应用管理->应用列表',
                        ),
                        array(
                            'title' => '签名名称',
                            'id'    => 'sign_name',
                            'type'  => 'text',
                            'desc'    => '短信签名内容，签名信息可登录 腾讯短信控制台->国内短信->签名管理->内容 中查看。',
                        ),
                        array(
                            'title' => '短信模板ID',
                            'id'    => 'template_id',
                            'type'  => 'text',
                            'desc'    => '模板 ID，必须填写已审核通过的模板 ID。模板ID可登录 腾讯短信控制台->国内短信->正文模板->ID 中查看',
                        ),
                    ),
                    'dependency' =>  array(
                        array('allow_register_check', '==', '1'),
                        array('register_check_type', 'any', 'tel,telandemail'),
                        array('sms_type', 'any', 'tencent'),
                    ),
                ),
                //社交登录设置
                array(
                    'type'    => 'heading',
                    'content' => '社交登录设置',
                ),
                array(
                    'title'      => '社交登录强制绑定',
                    'id'         => 'force_binding',
                    'default'    => false,
                    'type'       => 'switcher',
                    'desc'       => '要求用户填写手机号码或邮箱，与上面选择的身份验证形式一致并且启用。'
                ),
                array(
                    'title'      => 'QQ登录',
                    'id'         => 'oauth_qq_open',
                    'default'    => false,
                    'type'       => 'switcher',
                ),
                array(
                    'title'      => 'QQ登录配置',
                    'id'         => 'oauth_qq',
                    'type'       => 'fieldset',
                    'fields'     => array(
                        array(
                            'content' => '<h4><b>回调地址：</b>' . esc_url(home_url('/oauth?type=qq')) . '</h4>QQ登录申请地址：<a target="_blank" href="https://connect.qq.com/">https://connect.qq.com</a>',
                            'style'   => 'info',
                            'type'    => 'submessage',
                        ),
                        array(
                            'title' => 'App ID',
                            'id'    => 'app_id',
                            'type'  => 'text',
                        ),
                        array(
                            'title' => 'App Key',
                            'id'    => 'app_secret',
                            'type'  => 'text',
                        ),
                    ),
                    'dependency' => array('oauth_qq_open', '!=', ''),
                ),
                array(
                    'title'      => '微博登录',
                    'id'         => 'oauth_weibo_open',
                    'default'    => false,
                    'type'       => 'switcher',
                ),
                array(
                    'title'      => '微博登录配置',
                    'id'         => 'oauth_weibo',
                    'type'       => 'fieldset',
                    'fields'     => array(
                        array(
                            'content' => '<h4><b>回调地址：</b>' . esc_url(home_url('/oauth?type=weibo')) . '</h4>
                                微博登录申请地址：<a target="_blank" href="https://open.weibo.com/development/">https://open.weibo.com/development</a><br>
                            ',
                            'style'   => 'info',
                            'type'    => 'submessage',
                        ),
                        array(
                            'title' => 'App ID',
                            'id'    => 'app_id',
                            'type'  => 'text',
                        ),
                        array(
                            'title' => 'App Secret',
                            'id'    => 'app_secret',
                            'type'  => 'text',
                        ),
                    ),
                    'dependency' => array('oauth_weibo_open', '!=', ''),
                ),
                array(
                    'title'      => '彩虹聚合登录',
                    'id'         => 'oauth_juhe_open',
                    'default'    => false,
                    'type'       => 'switcher',
                ),
                array(
                    'title'      => '彩虹聚合配置',
                    'id'         => 'oauth_juhe',
                    'type'       => 'fieldset',
                    'fields'     => array(
                        array(
                            'content' => '<p><b>彩虹聚合登录是一个常用的第三方聚合登录程序，可以让用户通过其他第三方平台进行快速注册和登录。</b></p>
<li>您可以选择自己搭建彩虹聚合登录系统或使用现成的第三方提供商。</li>
<li>搭建自己的系统需要具备相关技能，可以通过搜索引擎获取搭建和使用方法。</li>
<li>选择第三方服务商时要注意可靠性和稳定性，以避免未来登录功能无法正常使用的问题。</li>
<li>根据需求和技术能力选择适合的方案，并确保服务商可靠，以保证用户能正常使用网站。</li>
                            ',
                            'style'   => 'info',
                            'type'    => 'submessage',
                        ),
                        array(
                            'title' => '接口地址',
                            'id'    => 'gateway',
                            'type'  => 'text',
                            'desc'  => '接口地址，例如：<code>https://xxx.xxxx.com</code>',
                        ),
                        array(
                            'title' => 'App ID',
                            'id'    => 'app_id',
                            'type'  => 'text',
                        ),
                        array(
                            'title' => 'App Key',
                            'id'    => 'app_key',
                            'type'  => 'text',
                        ),
                        array(
                            'id'          => 'types',
                            'title'       => '选择启用的登录方式',
                            'type'        => 'select',
                            'default'     => '',
                            'desc'        => '此处启用的方式，请确保彩虹聚合登录的服务商已提供该登录方式<br/>允许与下方主题自带的登录方式同时启用，如果此处启用的登录方式和下方相同登录方式同时开启，则此处优先',
                            'placeholder' => '选择需要开启的登录方式',
                            'options'     => array(
                                'qq'        => 'QQ登录',
                                'wx'    => '微信',
                                'alipay'    => '支付宝',
                                'sina'     => '微博',
                                'baidu'     => '百度',
                                'github'    => 'GitHub',
                                //'gitee'     => 'Gitee',
                                'dingtalk'  => '钉钉',
                                //'huawei'    => '华为',
                                'google'    => 'Google',
                                'microsoft' => 'Microsoft',
                                'facebook'  => 'Facebook',
                                'twitter'   => 'Twitter',
                            ),
                            'chosen'      => true,
                            'multiple'    => true,
                        ),
                    ),
                    'dependency' => array('oauth_juhe_open', '!=', ''),
                ),
            )
        ));
    }
    
    //vip
    public function users_vip_settings(){
        $user_vip_group = islide_get_option('user_vip_group');
        $user_vip_group = is_array($user_vip_group) ? $user_vip_group : array();

        $tabs = array();
        
        foreach ($user_vip_group as $vip) {
            $tabs[] = array(
                'title'     => $vip['name'],
                'fields'    => array(
                    array(
                        'id'    => 'allow_read',
                        'type'  => 'switcher',
                        'title' => '是否允许查看所有隐藏内容',
                        'desc'  => '对文章中隐藏代码包裹起来的内容有效',
                        'default'    => true,
                    ),
                    array(
                        'id'    => 'allow_download_count',
                        'type'  => 'spinner',
                        'title' => '允许每天免费下载的次数',
                        'desc'  => '为防止有人恶意采集下载资源，可在此处设置允许每天下载的次数。如果当天下载次数达到最大，将使用积分支付下载。（为0则不限制）',
                        'min'     => 0,
                        'step'    => 10,
                        'default' => 0,
                        'unit'    => '次',
                    ),
                    array(
                        'id'    => 'allow_videos',
                        'type'  => 'switcher',
                        'title' => '是否允许查看所有付费视频',
                        'desc'  => '将会允许此等级用户免费查看所有付费视频内容',
                        'default'    => true,
                    ),
                )
            );
        }
        
        \CSF::createSection(self::$prefix, array(
            'parent'     => 'islide_users_options',
            'title'      => 'VIP会员',
            'icon'       => 'fab fa-vimeo',
            'fields'     => array(
                array(
                    'type'    => 'subheading',
                    'content' => '会员开通页面设置',
                ),
                array(
                    'id'        => 'islide_vip_page',
                    'type'      => 'fieldset',
                    'title'     => '',
                    'fields'    => array(
                        array(
                            'type'    => 'subheading',
                            'content' => '页面设置',
                        ),
                        array(
                            'id'    => 'title',
                            'type'  => 'text',
                            'title' => '页面标题',
                        ),
                        array(
                            'id'    => 'desc',
                            'type'  => 'text',
                            'title' => '标题下描述',
                        ),
                        array(
                            'id'        => 'faqs',
                            'type'      => 'group',
                            'title'     => '问题解答',
                            'button_title' => '新增问题解答',
                            'fields'    => array(
                                array(
                                    'id'    => 'key',
                                    'type'  => 'text',
                                    'title' => '问题',
                                ),
                                array(
                                    'id'    => 'value',
                                    'type'  => 'textarea',
                                    'title' => '解答',
                                ),
                            ),
                            'default'   => array(
                                array(
                                    'key'     => '如何确认会员是否生效？',
                                    'value'    => '当您成功付款后，可访问 会员中心 查看会员详情。',
                                ),
                                array(
                                    'key'     => '个人VIP和企业VIP之间有什么区别？如何选择？',
                                    'value'    => '没什么区别',
                                ),
                            ),
                        ),
                    ),
                    'default'        => array(
                        'title'     => '焕发无限可能',
                        'desc'    => '享受卓越设计和无限创意的完美结合・让建站变得如此简单',
                    ),
                ),
                array(
                    'type'    => 'subheading',
                    'content' => '会员等级设置',
                ),
                array(
                    'id'        => 'user_vip_group',
                    'type'      => 'group',
                    'title'     => '',
                    'button_title' => '新增会员',
                    'accordion_title_number' => true,
                    'fields'    => array(
                        array(
                            'id'    => 'name',
                            'type'  => 'text',
                            'title' => '会员显示名称',
                            'desc'=>sprintf('比如 %s 等等','<code>超级会员</code><code>白金会员</code>')
                        ),
                        array(
                            'id'    => 'desc',
                            'type'  => 'text',
                            'title' => '会员作用描述',
                            'desc'=>sprintf('比如 %s 等等','<code>适用于所有设计爱好者</code><code>适用于专业团队</code>')
                        ),
                        array(
                            'id'      => 'icon',
                            'type' => 'upload',
                            'title'   => '显示会员图标',
                            'desc' => sprintf('尺寸：%s 最佳。显示在用户头像右下角，并且在个人中心我的订单中开通会员订单记录显示的图标，多个地方会用上','<code>48x48</code>或<code>28x28</code>'),
                            'library' => 'image', 
                            'preview' => true,
                        ),
                        array(
                            'id'      => 'image',
                            'type' => 'upload',
                            'title'   => '显示图标',
                            'desc' => '显示在用户名称后面',
                            'library' => 'image', 
                            'preview' => true,
                        ),
                        array(
                            'id'    => 'free_read',
                            'type'  => 'switcher',
                            'title' => '免费查看文章隐藏内容',
                            'desc'  => '对文章中隐藏代码包裹起来的内容有效',
                            'default'    => true,
                        ),
                        array(
                            'id'    => 'free_read_count',
                            'type'  => 'spinner',
                            'title' => '每天免费查看隐藏内容的次数',
                            'desc'  => '为9999则不限制',
                            'min'     => 1,
                            'step'    => 10,
                            'default' => 9999,
                            'unit'    => '次',
                            'dependency' => array('free_read', '==', '1'),
                        ),
                        array(
                            'id'    => 'free_download',
                            'type'  => 'switcher',
                            'title' => '免费下载资源',
                            'desc'  => '对文章中隐藏代码包裹起来的内容有效',
                            'default'    => true,
                        ),
                        array(
                            'id'    => 'free_download_count',
                            'type'  => 'spinner',
                            'title' => '每天免费下载的次数',
                            'desc'  => '为防止有人恶意采集下载资源，可在此处设置允许每天下载的次数。如果当天下载次数达到最大，将使用积分支付下载。（为9999则不限制）',
                            'min'     => 1,
                            'step'    => 10,
                            'default' => 9999,
                            'unit'    => '次',
                            'dependency' => array('free_download', '==', '1'),
                        ),
                        array(
                            'id'    => 'free_video',
                            'type'  => 'switcher',
                            'title' => '免费观看所有付费视频',
                            'desc'  => '将会允许此等级用户免费查看所有付费视频内容',
                            'default'    => true,
                        ),
                        array(
                            'id'    => 'free_video_count',
                            'type'  => 'spinner',
                            'title' => '每天免费观看视频的次数',
                            'desc'  => '为9999则不限制',
                            'min'     => 1,
                            'step'    => 10,
                            'default' => 9999,
                            'unit'    => '次',
                            'dependency' => array('free_video', '==', '1'),
                        ),
                        array(
                            'id'          => 'shop_discount',
                            'type'        => 'spinner',
                            'title'       => '商城折扣比例',
                            'min'         => 0,
                            'max'         => 100,
                            'step'        => 1,
                            'unit'        => '%',
                            'default'     => 100,
                        ),
                        array(
                            'id'     => 'signin_bonus',
                            'type'   => 'fieldset',
                            'title'  => '每日签到（额外）奖励',
                            'fields' => array(
                                array(
                                    'type'    => 'subheading',
                                    'content' => '在初始签到奖励额外奖励（功能暂未实现）',
                                ),
                                array(
                                    'id'       => 'credit',
                                    'type'     => 'text',
                                    'title'    => '奖励积分',
                                    'desc'     => '随机获得积分：<code>xx-xx</code> 例如: 10-100，如果是固定值请使用 <code>xx</code> 例如 45',
                                    'default'  => '1-5',
                                ),
                                array(
                                    'id'         => 'exp',
                                    'title'      => '奖励经验值',
                                    'type'       => 'text',
                                    'default'  => '1-5',
                                    'desc'     => '同上设置原理，请根据您设置的等级经验合理设置每日签到奖励',
                                ),
                            ),
                        ),
                        array(
                            'id'        => 'vip_group',
                            'type'      => 'group',
                            'title'     => '会员商品',
                            'button_title' => '新增会员商品',
                            'fields'    => array(
                                array(
                                    'id'    => 'name',
                                    'type'  => 'text',
                                    'title' => sprintf(__('会员商品名称%s','islide'),'<span class="red">（必填）</span>'),
                                    'desc' => sprintf('购买会员时显示，比如 %s 等等','<code>1个月</code>、<code>月卡</code>、<code>年卡</code>、<code>永久</code>')
                                ),
                                array(
                                    'id'      => 'time',
                                    'type'    => 'spinner',
                                    'title'   => '会员有效期',
                                    'desc'    => '开通会员的时长。填<code>0</code>则为永久会员',
                                    'min'     => 0,
                                    'step'    => 1,
                                    'default' => '',
                                    'unit'    => '天',
                                ),
                                array(
                                    'id'         => 'price',
                                    'type'       => 'number',
                                    'title'      => '会员购买价格',
                                    'unit'       => '元',
                                    'default'    => '',
                                ),
                                array(
                                    'id'          => 'discount',
                                    'type'        => 'spinner',
                                    'title'       => '折扣比例',
                                    'min'         => 0,
                                    'max'         => 100,
                                    'step'        => 1,
                                    'unit'        => '%',
                                    'default'     => 100,
                                ),
                            ),
                            'default'   => array(
                                array(
                                    'time'     => 1,
                                    'price'    => 25,
                                    'discount'     => 100,
                                )
                            ),
                        ),
                    ),
                    'default'   => array(
                        array(
                            'name'     => '白金会员',
                            'image'    => 'https://www.islide.com/wp-content/uploads/2023/04/6f539f40c9587258e7ea8ec1fd03175b_3059008835456131563.png',
                            'vip_group' => array(
                                array(
                                    'time'     => 1,
                                    'price'    => 25,
                                    'discount'     => 100,
                                )
                            )
                        ),
                    ),
                ),
                // array(
                //     'id'            => 'user_vip_allows',
                //     'type'          => 'tabbed',
                //     'title'         => '会员权益',
                //     'tabs'          => $tabs
                // ),
            )
        ));
    }
    
    //余额充值
    public function users_balance_settings(){
        \CSF::createSection(self::$prefix, array(
            'parent'     => 'islide_users_options',
            'title'      => '余额充值',
            'icon'       => 'fa fa-fw fa-jpy',
            'fields'     => array(
                array(
                    'content' => '<p><b>用户余额功能：</b></p>
                    <li>开启后用户可充值到余额，余额可用于全站消费</li>
                    <li>在下方设置默认的分成比例，同时支持单独为每一个用户设置独立的分成比例</li>
                    <li>同时您可以在用户管理中，为某一个用户手动赠送余额</li>',
                    'style'   => 'warning',
                    'type'    => 'submessage',
                ),
                array(
                    'id'      => 'money_withdrawal_open',
                    'type'    => 'switcher',
                    'title'   => '开启余额提现',
                    'default' => false,
                ),
                array(
                    'id'        => 'money_withdrawal',
                    'type'      => 'fieldset',
                    'fields'    => array(
                        array(
                            'id'       => 'limit',
                            'type'     => 'number',
                            'title'    => '余额超过多少允许提现',
                            'desc'     => '当用户余额高于多少时候，才能发起提现(不能为0，不能为小数)',
                            'default'  => '50',
                            'unit'     => '元',
                        ),
                        array(
                            'id'       => 'ratio',
                            'type'     => 'number',
                            'title'    => '提现手续费',
                            'desc'     => '如果网站不抽成，请设置为0。',
                            'default'  => 5,
                            'unit'     => '%',
                        ),
                    ),
                    'dependency' => array('money_withdrawal_open', '!=', '', '', 'visible'),
                ),
                array(
                    'id'      => 'pay_balance_open',
                    'type'    => 'switcher',
                    'title'   => '用户余额',
                    'label'   => '启用余额充值 / 支付功能',
                    'default' => true,
                ),
                array(
                    'id'        => 'pay_balance_group',
                    'type'      => 'group',
                    'title'     => '余额商品',
                    'button_title' => '新增余额商品选项',
                    'accordion_title_prefix' => '充值 ￥',
                    'max' => 5,
                    'fields'    => array(
                        array(
                            'id'         => 'price',
                            'type'       => 'number',
                            'title'      => '充值金额',
                            'unit'       => '元',
                            'default'    => '',
                        ),
                        array(
                            'id'          => 'discount',
                            'type'        => 'spinner',
                            'title'       => '折扣比例',
                            'min'         => 0,
                            'max'         => 100,
                            'step'        => 1,
                            'unit'        => '%',
                            'default'     => 100,
                        ),
                    ),
                    'default'   => array(
                        array(
                            'price'    => 1,
                            'discount'     => 100,
                        ),
                        array(
                            'price'    => 6,
                            'discount'     => 100,
                        ),
                        array(
                            'price'    => 30,
                            'discount'     => 100,
                        ),
                        array(
                            'price'    => 98,
                            'discount'     => 90,
                        ),
                        array(
                            'price'    => 198,
                            'discount'     => 90,
                        ),
                    ),
                    'dependency' => array('pay_balance_open', '!=', '', '', 'visible')
                ),
                array(
                    'id'      => 'pay_balance_custom_open',
                    'type'    => 'switcher',
                    'title'   => '自定义充值金额',
                    'label'   => '允许用户手动输入自定义充值',
                    'default' => true,
                    'dependency' => array('pay_balance_open', '!=', '', '', 'visible')
                ),
                array(
                    'id'         => 'pay_balance_custom_limit',
                    'type'       => 'spinner',
                    'title'      => '自定义充值金额最低限制',
                    'min'         => 1,
                    'step'        => 5,
                    'unit'       => '元',
                    'dependency' => array('pay_balance_open|pay_balance_custom_open', '!=|!=', '', '', 'visible'),
                    'default'    => 1,
                ),
            )
        ));
    }
    
    //积分充值
    public function users_credit_settings(){
        \CSF::createSection(self::$prefix, array(
            'parent'     => 'islide_users_options',
            'title'      => '积分充值',
            'icon'       => 'fa fa-fw fa-rub',
            'fields'     => array(
                array(
                    'content' => '<p><b>用户积分功能：</b></p>
                    <li>开启后用户可充值到余额，余额可用于全站消费</li>
                    <li>在下方设置默认的分成比例，同时支持单独为每一个用户设置独立的分成比例</li>
                    <li>同时您可以在用户管理中，为某一个用户手动赠送余额</li>',
                    'style'   => 'warning',
                    'type'    => 'submessage',
                ),
                array(
                    'id'      => 'pay_credit_open',
                    'type'    => 'switcher',
                    'title'   => '用户积分',
                    'label'   => '启用积分充值 / 支付积分',
                    'default' => true,
                ),
                array(
                    'id'        => 'pay_credit_group',
                    'type'      => 'group',
                    'title'     => '积分商品',
                    'button_title' => '新增积分商品选项',
                    'accordion_title_prefix' => '积分',
                    'max' => 5,
                    'fields'    => array(
                        array(
                            'id'         => 'credit',
                            'type'       => 'number',
                            'title'      => '积分',
                            'unit'       => '积分',
                            'default'    => '',
                        ),
                        array(
                            'id'         => 'price',
                            'type'       => 'number',
                            'title'      => '购买金额',
                            'unit'       => '元',
                            'default'    => '',
                        ),
                        array(
                            'id'          => 'discount',
                            'type'        => 'spinner',
                            'title'       => '折扣比例',
                            'min'         => 0,
                            'max'         => 100,
                            'step'        => 1,
                            'unit'        => '%',
                            'default'     => 100,
                        ),
                    ),
                    'default'   => array(
                        array(
                            'credit' => 60,
                            'price'    => 6,
                            'discount'     => 100,
                        ),
                        array(
                            'credit' => 300,
                            'price'    => 30,
                            'discount'     => 90,
                        ),
                        array(
                            'credit' => 980,
                            'price'    => 98,
                            'discount'     => 80,
                        ),
                    ),
                    'dependency' => array('pay_credit_open', '!=', '', '', 'visible')
                ),
                array(
                    'id'      => 'pay_credit_custom_open',
                    'type'    => 'switcher',
                    'title'   => '自定义充值积分',
                    'label'   => '允许用户手动输入自定义充值',
                    'default' => true,
                    'dependency' => array('pay_credit_open', '!=', '', '', 'visible')
                ),
                array(
                    'id'         => 'pay_credit_custom_limit',
                    'type'       => 'spinner',
                    'title'      => '自定义充值积分金额最低限制',
                    'min'         => 1,
                    'step'        => 5,
                    'unit'       => '元',
                    'dependency' => array('pay_credit_open|pay_credit_custom_open', '!=|!=', '', '', 'visible'),
                    'default'    => 1,
                ),
                array(
                    'id'         => 'pay_credit_ratio',
                    'type'       => 'spinner',
                    'title'      => '积分购买汇率',
                    'desc'      => '1元人民币兑换多少积分',
                    'min'         => 1,
                    'step'        => 5,
                    'unit'       => '积分',
                    'dependency' => array('pay_credit_open|pay_credit_custom_open', '!=|!=', '', '', 'visible'),
                    'default'    => 10,
                ),
            )
        ));
    }
    
    //等级
    public function users_lv_settings(){
        
        $user_lv_group = islide_get_option('user_lv_group');
        $user_lv_group = is_array($user_lv_group) ? $user_lv_group : array();

        $tabs = array();

        foreach ($user_lv_group as $key=>$lv) {
            $tabs[] = array(
                'title'     => $lv['name'],
                'fields'    => array(
                    array(
                        'id'    => 'allow_read',
                        'type'  => 'switcher',
                        'title' => '允许查看所有隐藏内容',
                        'desc'  => '对文章中隐藏代码包裹起来的内容有效',
                        'default'    => false,
                    ),
                    array(
                        'id'    => 'allow_download_count',
                        'type'  => 'spinner',
                        'title' => '允许每天免费下载的次数',
                        'desc'  => '为防止有人恶意采集下载资源，可在此处设置允许每天下载的次数。如果当天下载次数达到最大，将使用积分支付下载。（为0则不限制）',
                        'min'     => 0,
                        'step'    => 10,
                        'default' => 0,
                        'unit'    => '次',
                    ),
                    array(
                        'id'    => 'allow_videos',
                        'type'  => 'switcher',
                        'title' => '允许查看所有付费视频',
                        'desc'  => '将会允许此等级用户免费查看所有付费视频内容',
                        'default'    => false,
                    ),
                    array(
                        'id'    => 'allow_publish_post',
                        'type'  => 'switcher',
                        'title' => '文章相关',
                        'subtitle' => '允许发布文章',
                        'default'    => false,
                    ),
                    array(
                        'id'    => 'allow_publish_post_no_audit',
                        'type'  => 'switcher',
                        'title' => ' ',
                        'subtitle' => '允许发布文章无需审核直接发布',
                        'default'    => false,
                    ),
                    array(
                        'id'    => 'allow_upload_image',
                        'type'  => 'switcher',
                        'title' => ' ',
                        'subtitle' => '允许上传图片',
                        'default'    => false,
                    ),
                    array(
                        'id'    => 'allow_upload_video',
                        'type'  => 'switcher',
                        'title' => ' ',
                        'subtitle' => '允许上传视频',
                        'default'    => false,
                    ),
                    array(
                        'id'    => 'allow_message',
                        'type'  => 'switcher',
                        'title' => '私信',
                        'subtitle' => '允许发送私信',
                        'default'    => false,
                    ),
                    array(
                        'id'    => 'allow_comment',
                        'type'  => 'switcher',
                        'title' => '评论',
                        'subtitle' => '允许发布评论',
                        'default'    => false,
                    ),
                    array(
                        'id'    => 'allow_comment_no_audit',
                        'type'  => 'switcher',
                        'title' => ' ',
                        'subtitle' => '允许发布评论无需审核直接发布',
                        'default'    => false,
                    ),
                    array(
                        'id'    => 'allow_comment_delete',
                        'type'  => 'switcher',
                        'title' => ' ',
                        'subtitle' => '允许删除评论',
                        'default'    => false,
                    ),
                )
            );
        }
        
        //注册与登录
        \CSF::createSection(self::$prefix, array(
            'parent'     => 'islide_users_options',
            'title'      => '用户等级',
            'icon'       => 'fa fa-fw fa-copy',
            'fields'     => array(
                array(
                    'id'        => 'user_lv_group',
                    'type'      => 'group',
                    'title'     => '用户等级',
                    'button_title' => '新增等级',
                    'accordion_title_number' => true,
                    'fields'    => array(
                        array(
                            'id'    => 'name',
                            'type'  => 'text',
                            'title' => '等级显示名称',
                            'desc'=>sprintf('比如 %s 等等','<code>LV1</code><co1e>初出茅庐</code>')
                        ),
                        array(
                            'id'      => 'image',
                            'type' => 'upload',
                            'title'   => '等级图标',
                            'library' => 'image', 
                            'preview' => true,
                        ),
                        array(
                            'id'      => 'exp',
                            'title'   => '等级升级需要经验',
                            'type'    => 'spinner',
                            'desc'    => '',
                            'max'     => 10000000000000000,
                            'min'     => 0,
                            'step'    => 50,
                            'default' => 0,
                        ),
                    ),
                    'default'   => array(
                        array(
                            'name'     => 'M1',
                            'image'    => 'https://www.islide.com/wp-content/uploads/2023/04/6f539f40c9587258e7ea8ec1fd03175b_3059008835456131563.png',
                            'exp'      => 0,
                        ),
                    ),
                ),
                // array(
                //     'id'            => 'user_lv_allows',
                //     'type'          => 'tabbed',
                //     'title'         => '等级权益',
                //     'tabs'          => $tabs
                // ),
            )
        ));
    }
    
    //签到
    public function users_signin_settings() {
        
        $user_vip_group = islide_get_option('user_vip_group');
        $user_vip_group = is_array($user_vip_group) ? $user_vip_group : array();

        $vip_options = array();
        
        foreach ($user_vip_group as $Key => $vip) {
            $vip_options['vip'.$Key] =  $vip['name'];
        }
        
        \CSF::createSection(self::$prefix, array(
            'parent'     => 'islide_users_options',
            'title'      => '签到奖励',
            'icon'       => 'fa fa-fw fa-rub',
            'fields'     => array(
                array(
                    'content' => '<p><b>用户签到功能：</b></p>',
                    'style'   => 'warning',
                    'type'    => 'submessage',
                ),
                array(
                    'id'     => 'signin_bonus',
                    'type'   => 'fieldset',
                    'title'  => '每日签到奖励',
                    'fields' => array(
                        array(
                            'type'    => 'subheading',
                            'content' => '初始签到奖励设置',
                        ),
                        array(
                            'id'       => 'credit',
                            'type'     => 'text',
                            'title'    => '奖励积分',
                            'desc'     => '随机获得积分：<code>xx-xx</code> 例如: 10-100，如果是固定值请使用 <code>xx</code> 例如 45',
                            'default'  => '10-20',
                        ),
                        array(
                            'id'         => 'exp',
                            'title'      => '奖励经验值',
                            'type'       => 'text',
                            'default'  => '10-20',
                            'desc'     => '同上设置原理，请根据您设置的等级经验合理设置每日签到奖励',
                        ),
                    ),
                ),
                array(
                    'id'      => 'signin_consecutive_open',
                    'type'    => 'switcher',
                    'title'   => '启用连续签到',
                    'default' => true,
                ),
                array(
                    'id'        => 'signin_bonus_group',
                    'type'      => 'group',
                    'title'     => '连续签到奖励',
                    'button_title' => '新增连续签到奖励',
                    'accordion_title_prefix' => '天数：',
                    'fields'    => array(
                        array(
                            'id'       => 'day',
                            'type'     => 'spinner',
                            'title'    => '连续签到几天',
                            'desc'     => '此为用户连续签到当前设置天数后，奖励用户',
                            'min'      => 1,
                            'step'     => 5,
                            'unit'     => '天',
                            'default'  => 5,
                        ),
                        array(
                            'id'       => 'credit',
                            'type'     => 'spinner',
                            'title'    => '奖励积分',
                            'desc'     => '',
                            'min'      => 0,
                            'step'     => 5,
                            'default'  => 20,
                        ),
                        array(
                            'id'         => 'exp',
                            'title'      => '奖励经验值',
                            'type'       => 'spinner',
                            'min'      => 0,
                            'step'     => 5,
                            'unit'     => '点',
                            'default'  => 20,
                        ),
                        array(
                            'id'     => 'vip',
                            'type'   => 'fieldset',
                            'title'  => '奖励vip',
                            'fields' => array(
                                array(
                                    'type'    => 'subheading',
                                    'content' => '奖励vip设置',
                                ),
                                array(
                                    'id'         => 'vip',
                                    'type'       => 'radio',
                                    'title'      => '选择奖励vip等级',
                                    'inline'     => true,
                                    'options'    => $vip_options
                                ),
                                array(
                                    'id'         => 'day',
                                    'title'      => '奖励vip天数',
                                    'type'       => 'spinner',
                                    'min'      => 0,
                                    'step'     => 5,
                                    'unit'     => '天',
                                    'default'  => 0,
                                    'desc'     => '设置 大于或等于 9999天为永久',
                                ),
                            ),
                        ),
                    ),
                    'default'   => array(
                        array(
                            'day'    => 5,
                            'credit' => 20,
                            'exp'    => 20,
                        )
                    ),
                    'dependency' => array('signin_consecutive_open', '!=', '', '', 'visible')
                )
            )
        ));
    }
    
    //任务
    public function users_task_settings() {
        
        $user_vip_group = islide_get_option('user_vip_group');
        $user_vip_group = is_array($user_vip_group) ? $user_vip_group : array();

        $options = array(
            'credit' => '奖励积分',
            'exp' => '奖励经验值',
        );
        
        foreach ($user_vip_group as $Key => $vip) {
            $options['vip'.$Key] =  '奖励'.$vip['name'];
        }
        
        \CSF::createSection(self::$prefix, array(
            'parent'     => 'islide_users_options',
            'title'      => '每日任务',
            'icon'       => 'fa fa-fw fa-rub',
            'fields'     => array(
                array(
                    'content' => '<p><b>每日任务功能：</b></p>
                    <li>超过这些次数以后将不再增加积分</li>
                    <li>日常任务：每日凌晨00：00重置任务次数</li>',
                    'style'   => 'warning',
                    'type'    => 'submessage',
                ),
                array(
                    'id'      => 'task_open',
                    'type'    => 'switcher',
                    'title'   => '启用任务系统',
                    'default' => true,
                ),
                array(
                    'id'        => 'newbie_task_group',
                    'type'      => 'group',
                    'title'     => '初次见面',
                    'subtitle' =>  '请勿重复添加相同的任务类型，可能会导致错误',
                    'button_title' => '新增新手任务',
                    'accordion_title_number' => true,
                    'fields'    => array(
                        array(
                            'id'      => 'name',
                            'type'    => 'text',
                            'title'   => '起个名字，方便后台浏览',
                        ),
                        array(
                            'id'       => 'task_type', 
                            'type'     => "select",
                            'title'    => '任务类型',
                            'placeholder'  => '选择一个选项',
                            'inline'   => true,
                            'options'  => array(
                                'task_user_register' => '新用户注册',
                            ),
                        ),
                        array(
                            'id'        => 'task_bonus',
                            'type'      => 'group',
                            'title'     => '任务奖励',
                            'subtitle' =>  '请勿重复添加相同的奖励，可能会导致错误',
                            'button_title' => '新增任务奖励',
                            'max' => 3,
                            'fields'    => array(
                                array(
                                    'id'         => 'key',
                                    'type'       => 'radio',
                                    'title'      => '奖励类型',
                                    'inline'     => true,
                                    'options'    => $options,
                                    'default'  => 'credit',
                                    'desc'  => '如果你选择的是VIP等级，如果当前用户未开通会员则奖励对应你选择的VIP会员等级和天数，相反如果用户已有会员，则直接在原有会员基础上增加天数',
                                ),
                                array(
                                    'id'         => 'value',
                                    'title'      => '奖励值',
                                    'type'       => 'spinner',
                                    'min'      => 0,
                                    'step'     => 5,
                                    'unit'     => '（点/天）',
                                    'default'  => 0,
                                    'desc'     => '如果选择是奖励会员，则单位是 天',
                                ),
                            ),
                        )
                    ),
                    'default'   => array(
                        // array(
                        //     'day'    => 5,
                        //     'credit' => 20,
                        //     'exp'    => 20,
                        // )
                    ),
                    'dependency' => array('task_open', '!=', '', '', 'visible')
                ),
                array(
                    'id'        => 'daily_task_group',
                    'type'      => 'group',
                    'title'     => '日常任务',
                    'subtitle' =>  '请勿重复添加相同的任务类型，可能会导致错误',
                    'button_title' => '新增日常任务',
                    'accordion_title_number' => true,
                    'fields'    => array(
                        array(
                            'id'      => 'name',
                            'type'    => 'text',
                            'title'   => '起个名字，方便后台浏览',
                        ),
                        array(
                            'id'       => 'task_type', 
                            'type'     => "select",
                            'title'    => '任务类型',
                            'inline'   => true,
                            'placeholder'  => '选择一个选项',
                            'options'  => array(
                                '主动'    => array(
                                    'task_post' => '发布文章',
                                    'task_comment' => '发布评论',
                                    'task_follow' => '关注他人',
                                    'task_like' => '文章点赞'
                                ),
                                '被动'    => array(
                                    'task_fans' => '被他人关注',
                                    'task_post_comment' => '文章被评论',
                                    'task_post_like' => '文章被点赞',
                                    'task_comment_like' => '评论被点赞',
                                ),
                            ),
                        ),
                        array(
                            'id'         => 'task_count',
                            'title'      => '每日任务可完成次数',
                            'type'       => 'spinner',
                            'min'      => 1,
                            'step'     => 1,
                            'unit'     => '次',
                            'default'  => 1,
                        ),
                        array(
                            'id'        => 'task_bonus',
                            'type'      => 'group',
                            'title'     => '任务奖励',
                            'subtitle' =>  '请勿重复添加相同的奖励，可能会导致错误',
                            'button_title' => '新增任务奖励',
                            'max' => 3,
                            'fields'    => array(
                                array(
                                    'id'         => 'key',
                                    'type'       => 'radio',
                                    'title'      => '奖励类型',
                                    'inline'     => true,
                                    'options'    => $options,
                                    'default'  => 'credit',
                                    'desc'  => '如果你选择的是VIP等级，如果当前用户未开通会员则奖励对应你选择的VIP会员等级和天数，相反如果用户已有会员，则直接在原有会员基础上增加天数',
                                ),
                                array(
                                    'id'         => 'value',
                                    'title'      => '奖励值',
                                    'type'       => 'spinner',
                                    'min'      => 0,
                                    'max'      => 99999999,
                                    'step'     => 5,
                                    'unit'     => '（点/天）',
                                    'default'  => 10,
                                    'desc'     => '如果选择是奖励会员，则单位是 天',
                                ),
                            ),
                        )
                    ),
                    'dependency' => array('task_open', '!=', '', '', 'visible')
                ),
                array(
                    'id'        => 'recom_task_group',
                    'type'      => 'group',
                    'title'     => '推荐任务',
                    'subtitle' =>  '请勿重复添加相同的任务类型，可能会导致错误',
                    'button_title' => '新增推荐任务',
                    'accordion_title_number' => true,
                    'fields'    => array(
                        array(
                            'id'      => 'name',
                            'type'    => 'text',
                            'title'   => '起个名字，方便后台浏览',
                        ),
                        array(
                            'id'       => 'task_type', 
                            'type'     => "select",
                            'title'    => '任务类型',
                            'inline'   => true,
                            'placeholder'  => '选择一个选项',
                            'options'  => array(
                                '主动'    => array(
                                    'task_post' => '发布 N 篇文章',
                                    'task_comment' => '发布 N 条评论',
                                    'task_follow' => '关注 N 个人',
                                    'task_vip' => '开通会员',
                                    'task_sign_in' => '连续签到 N 天'
                                ),
                                '被动'    => array(
                                    'task_registration' => '注册时间达到 N 天',
                                    'task_fans' => '获得 N 个粉丝',
                                    'task_post_views' => '文章总获得 N 次点击（阅读量）',
                                    'task_post_like' => '文章总获得 N 次点赞（喜欢）',
                                    'task_post_favorite' => '文章总获得 N 次收藏',
                                    'task_comment_like' => '评论获得 N 次点赞',
                                ),
                            ),
                        ),
                        array(
                            'id'         => 'task_count',
                            'title'      => '设置满足的 N 值',
                            'type'       => 'spinner',
                            'min'      => 1,
                            'max'      => 99999999,
                            'step'     => 50,
                            'unit'     => '',
                            'default'  => 1,
                            'dependency' => array('task_type', 'not-any', 'task_vip'),
                        ),
                        array(
                            'id'        => 'task_bonus',
                            'type'      => 'group',
                            'title'     => '任务奖励',
                            'subtitle' =>  '请勿重复添加相同的奖励，可能会导致错误',
                            'button_title' => '新增任务奖励',
                            'max' => 3,
                            'fields'    => array(
                                array(
                                    'id'         => 'key',
                                    'type'       => 'radio',
                                    'title'      => '奖励类型',
                                    'inline'     => true,
                                    'options'    => $options,
                                    'default'  => 'credit',
                                    'desc'  => '如果你选择的是VIP等级，当前用户未开通会员则奖励对应你选择的VIP会员等级和天数，相反如果用户已有会员，则直接在原有会员基础上增加天数',
                                ),
                                array(
                                    'id'         => 'value',
                                    'title'      => '奖励值',
                                    'type'       => 'spinner',
                                    'min'      => 0,
                                    'max'      => 99999999,
                                    'step'     => 5,
                                    'unit'     => '（点/天）',
                                    'default'  => 1,
                                    'desc'     => '如果选择是奖励会员，则单位是 天',
                                ),
                            ),
                        )
                    ),
                    'dependency' => array('task_open', '!=', '', '', 'visible')
                )
            )
        ));
    }
    
    //分销
    public function users_distribution_settings() {
        
        $user_vip_group = islide_get_option('user_vip_group');
        $user_vip_group = is_array($user_vip_group) ? $user_vip_group : array();

        $options = array();
        
        $user_lv_group = array(
            'lv' => array(
                'name'=>'普通用户'
            ),
        );
        
        // 更改$user_vip_group数组的键
        $user_vip_group_modified = array();
        foreach ($user_vip_group as $key => $vip) {
            $newKey = 'vip' . $key;
            $user_vip_group_modified[$newKey] = $vip;
        }
        
        // 合并$user_lv_group和$user_vip_group_modified数组
        $arr = array_merge($user_lv_group, $user_vip_group_modified);
        
        $types = \islide\Modules\Common\Orders::get_order_type();
        unset($types['money_chongzhi']);
        $default_types = array_keys($types);
        
        foreach ($arr as $Key => $value) {
            $options[] = array(
                'title'  => $value['name'],
                'fields' => array(
                    array(
                        'id'        => $Key,
                        'type'      => 'fieldset',
                        'fields'    => array(
                            array(
                                'id'      => 'types',
                                'title'   => '返佣订单',
                                'type'    => 'checkbox',
                                'desc'    => '返佣的订单类型，全部关闭，则代表不参与推广返佣',
                                'default' => $default_types,
                                'options' => $types,
                            ),
                            array(
                                'id'         => 'lv1_ratio',
                                'title'      => '一级推广分红比例',
                                'type'       => 'spinner',
                                'max'        => 100,
                                'min'        => 0,
                                'step'       => 1,
                                'unit'       => '%',
                                'default'    => 10,
                                'dependency' => array('types', '!=', '', '', 'visible'),
                                'desc'       => '填0则则代表不参与推广返佣',
                            ),
                            array(
                                'id'         => 'lv2_ratio',
                                'title'      => '二级推广分红比例',
                                'type'       => 'spinner',
                                'max'        => 100,
                                'min'        => 0,
                                'step'       => 1,
                                'unit'       => '%',
                                'default'    => 5,
                                'desc'       => '填0则不启用二级推广',
                                'dependency' => array('types', '!=', '', '', 'visible'),
                            ),
                            array(
                                'id'         => 'lv3_ratio',
                                'title'      => '三级推广分红比例',
                                'type'       => 'spinner',
                                'max'        => 100,
                                'min'        => 0,
                                'step'       => 1,
                                'unit'       => '%',
                                'default'    => 3,
                                'desc'       => '填0则不启用三级推广',
                                'dependency' => array('types', '!=', '', '', 'visible'),
                            ),
                        ),
                    ),
                ),
            );
        }
        
        \CSF::createSection(self::$prefix, array(
            'parent'     => 'islide_users_options',
            'title'      => '推广返佣',
            'icon'       => 'fa fa-fw fa-jpy',
            'fields'     => array(
                array(
                    'content' => '<p><b>推广返佣功能：</b></p>',
                    'style'   => 'warning',
                    'type'    => 'submessage',
                ),
                array(
                    'id'      => 'distribution_open',
                    'type'    => 'switcher',
                    'title'   => '启用推广返佣',
                    'default' => false,
                ),
                array(
                    'id'         => 'distribution',
                    'title'      => '返佣规则',
                    'subtitle'   => '为不同用户类型设置返佣规则',
                    'type'       => 'tabbed',
                    'tabs'       => $options,
                    'dependency' => array('distribution_open', '!=', '', '', 'visible'),
                ),
                array(
                    'id'      => 'commission_withdrawal_open',
                    'type'    => 'switcher',
                    'title'   => '开启佣金提现',
                    'default' => false,
                ),
                array(
                    'id'        => 'commission_withdrawal',
                    'type'      => 'fieldset',
                    'fields'    => array(
                        array(
                            'id'       => 'limit',
                            'type'     => 'number',
                            'title'    => '佣金超过多少允许提现',
                            'desc'     => '当用户推广佣金高于多少时候，才能发起提现(不能为0，不能为小数)',
                            'default'  => '50',
                            'unit'     => '元',
                        ),
                        array(
                            'id'       => 'ratio',
                            'type'     => 'number',
                            'title'    => '提现手续费',
                            'desc'     => '如果网站不抽成，请设置为0。',
                            'default'  => 5,
                            'unit'     => '%',
                        ),
                    ),
                    'dependency' => array('commission_withdrawal_open', '!=', '', '', 'visible'),
                ),
            )
        ));
    }
    
    //认证
    public function users_verify_settings() {
         \CSF::createSection(self::$prefix, array(
            'parent'     => 'islide_users_options',
            'title'      => '认证设置',
            'icon'       => 'fa fa-fw fa-jpy',
            'fields'     => array(
                array(
                    'type'    => 'subheading',
                    'content' => '认证设置',
                ),
                array(
                    'id'      => 'verify_open',
                    'type'    => 'switcher',
                    'title'   => '开启认证',
                    'default' => true,
                ),
                array(
                    'id'        => 'verify_group',
                    'type'      => 'group',
                    'title'     => '新增认证（添加后请勿随意删除）',
                    'subtitle' =>  '请勿重复添加相同的类型，可能会导致错误',
                    'button_title' => '新增认证',
                    'accordion_title_number' => true,
                    'fields'    => array(
                        array(
                            'id'      => 'name',
                            'type'    => 'text',
                            'title'   => '认证类型名称',
                            'desc'   => sprintf('认证类型名称、比如%s其他认证自定义','<code>个人认证</code><code>企业认证</code>'),
                        ),
                        array(
                            'id'      => 'type',
                            'type'    => 'text',
                            'title'   => '认证类型',
                            'desc'   => '自定义认证类型（唯一），请使用英文与认证名称对应含义相同，用作辨别是个人还是企业获取其他',
                        ),
                        array(
                            'id'      => 'icon',
                            'type'    => 'upload',
                            'title'   => '认证图标',
                            'desc'    => sprintf('尺寸：%s 最佳。显示在用户头像右下角','<code>48x48</code>或<code>28x28</code>'),
                            'library' => 'image', 
                            'preview' => true,
                        ),
                        array(
                            'id'      => 'desc',
                            'type'    => 'textarea',
                            'title'   => '认证描述',
                            'desc'    => '比如适合个人、或企业申请认证、其他等等',
                        ),
                        array(
                            'id'      => 'image',
                            'type'    => 'upload',
                            'title'   => '申请页面认证图标',
                            'desc'    => sprintf('尺寸：%s 最佳','<code>128x128</code>'),
                            'library' => 'image', 
                            'preview' => true,
                        ),
                        array(
                            'id'         => 'verify_info_types',
                            'type'       => 'checkbox',
                            'title'      => '认证所需的必要收集的资料',
                            'options'    => array(
                                'personal' => '个人信息（包含姓名、身份证号、身份证照片、手持身份照片）',
                                // 'business' => 'Option 2',
                                'official' => '企业/机构（包含事业单位证明/营业执照、统一社会信用代码、企业全称/机构名称、官方网址、机构认证申请公函、补充文件）',
                            ),
                            // 'default'    => array( 'option-1', 'option-3' )
                        ),
                        array(
                            'id'         => 'verify_check',
                            'type'       => 'radio',
                            'title'      => '认证信息审核',
                            'inline'     => true,
                            'options'    => array(
                                0       => '认证信息人工审核',
                                1         => '认证信息自动审核',
                            ),
                            'default'  => 0,
                            'desc'  => '如果选择自动审核，用户提交以后自动生效，否则需要管理员在认证列表中进行手动审核',
                        ),
                        array(
                            'id'        => 'conditions',
                            'type'      => 'group',
                            'title'     => '认证条件',
                            'subtitle' =>  '满足你设置的条件后才可申请，请勿重复添加相同的条件，可能会导致错误',
                            'button_title' => '新增认证条件',
                            // 'max' => 3,
                            'fields'    => array(
                                 array(
                                    'id'      => 'name',
                                    'type'    => 'text',
                                    'title'   => '起个名字，方便后台浏览',
                                    'desc'    => '比如 发布作品数量 >= 30'
                                ),
                                array(
                                    'id'       => 'key', 
                                    'type'     => "select",
                                    'title'    => '条件类型',
                                    'inline'   => true,
                                    'placeholder'  => '选择一个选项',
                                    'options'  => array(
                                        '主动'    => array(
                                            'post' => '发布 N 篇文章',
                                            'credit' => '支付积分 N',
                                            'money' => '支付金额 N 元',
                                            'bind_phone' => '绑定手机号'
                                        ),
                                        '被动'    => array(
                                            'registered' => '注册时间达到 N 天',
                                            'fans' => '获得 N 个粉丝',
                                        ),
                                    ),
                                    'default'  => 'fans'
                                ),
                                array(
                                    'id'         => 'value',
                                    'title'      => '设置满足的 N 值',
                                    'type'       => 'spinner',
                                    'min'      => 1,
                                    'step'     => 5,
                                    'default'  => 1,
                                    'desc'     => '必须满足（大于）当前设定条件值，也就是根据用户条件类型对应的条件值来判断是否满足认证申请',
                                    'dependency' => array('key', 'any', 'fans,post,credit,money,registered'),
                                ),
                            ),
                           
                        )
                    ),
                    'default' => array(
                        array(
                            'name' => '个人认证',
                            'type' => 'per',
                            'icon' => IS_THEME_URI. '/Assets/fontend/images/personel-verify-badge.svg',
                            'desc' => '适合公众人物、领域专家、影视明星等',
                            'image' => ''
                        ),
                        array(
                            'name' => '企业认证',
                            'type' => 'bus',
                            'icon' => IS_THEME_URI. '/Assets/fontend/images/business-verify-badge.svg',
                            'desc' => '适合企业、个人工商户申请',
                            'image' => ''
                        ) 
                    )
                ),
                array(
                    'type'    => 'subheading',
                    'content' => '认证页面设置',
                ),
                array(
                    'id'        => 'islide_verify_page',
                    'type'      => 'fieldset',
                    'title'     => '',
                    'fields'    => array(
                        array(
                            'type'    => 'subheading',
                            'content' => '页面设置',
                        ),
                        array(
                            'id'    => 'title',
                            'type'  => 'text',
                            'title' => '页面标题',
                        ),
                        array(
                            'id'    => 'desc',
                            'type'  => 'text',
                            'title' => '标题下描述',
                        ),
                        array(
                            'title' => '认证服务协议地址',
                            'id'    => 'agreement',
                            'type'  => 'text',
                        ),
                        array(
                            'id'        => 'faqs',
                            'type'      => 'group',
                            'title'     => '问题解答',
                            'button_title' => '新增问题解答',
                            'fields'    => array(
                                array(
                                    'id'    => 'key',
                                    'type'  => 'text',
                                    'title' => '问题',
                                ),
                                array(
                                    'id'    => 'value',
                                    'type'  => 'textarea',
                                    'title' => '解答',
                                ),
                            ),
                            'default'   => array(
                                array(
                                    'key'     => '认证周期所需时间以及认证进度查询？',
                                    'value'    => '认证审核结果会在 7 个工作日内通过系统私信告知私信中，会明确您审核通过结果或失败原因',
                                ),
                                array(
                                    'key'     => '认证未通过怎么办？',
                                    'value'    => '申请认证通过与否都会系统私信通知，根据私信内容补充资料后重新申请即可',
                                ),
                                array(
                                    'key'     => '认证后能否修改认证类型，如何修改？',
                                    'value'    => '暂不支持个人修改类型，如有需求，请联系在线客服',
                                ),
                                array(
                                    'key'     => '认证是否永久有效，什么情况会被取消认证？',
                                    'value'    => '认证是永久有效，但如有以下情况将取消认证：1、提交申请的资料失效或资料存在不实、造假等情况。2、发布不当内容或违反网站条约等',
                                ),
                            ),
                        ),
                    ),
                    'default'        => array(
                        'title'     => '认证',
                        'desc'    => '专属身份标识，享多重认证权益！',
                    ),
                )
            )
        ));
    }
}