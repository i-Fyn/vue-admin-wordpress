<?php
namespace islide\Modules\Settings;
use islide\Modules\Common\User;
/**
* 卡密管理设置
*
* @version 1.0.2
* @since 2023
*/
class Card{
    
    public function init(){
        add_action( 'islide_card_options_save_before', array($this,'save_action'), 10, 2 );
        
        if ( class_exists('IS_CSF')) {
            $this->card_options_page();
            $this->card_list_page();
        }
    }
    
    //卡密生成
    public function card_options_page(){
        
        $user_vip_group = islide_get_option('user_vip_group');
        $user_vip_group = is_array($user_vip_group) ? $user_vip_group : array();

        $vip_options = array();
        
        foreach ($user_vip_group as $Key => $vip) {
            $vip_options['vip'.$Key] =  $vip['name'];
        }
        
        \IS_CSF::instance('card_options',array(
            'menu_title'              => '卡密管理', //页面的title信息 和 菜单标题
            'menu_slug'               => 'islide_card_bulid', //别名
            'menu_type'               => 'submenu', //submenu 子菜单
            'menu_capability'         => 'manage_options',
            'menu_parent'             => 'islide_main_page', //父级菜单项的别名
            'tab_group'    => 'card_options_page', 
            'tab_title'    => '卡密生成',
            'save_option' => false,
            'fields' => array(
                array(
                    'id'         => 'card_type',
                    'type'       => 'radio',
                    'title'      => '生成卡密类型',
                    'inline'     => true,
                    'options'    => array(
                        'money' => '充值卡（余额）',
                        'credit' => '充值卡（积分）',
                        'invite' => '注册邀请码',
                        'vip' => 'VIP会员激活码',
                    ),
                    'default'    => 'money'
                ),
                array(
                    'id'      => 'card_count',
                    'type'    => 'spinner',
                    'title'   => '生成卡密的数量',
                    'min'     => 1,
                    'max'     => 100,
                    'step'    => 1,
                    'unit'    => '张',
                    'default' => 20,
                ),
                array(
                    'id'      => 'card_par_value',
                    'type'    => 'spinner',
                    'title'   => '生成卡密的面值',
                    'min'     => 1,
                    'step'    => 1,
                    'default' => 10,
                    'unit'    => '元',
                    'desc'    => '如果你上面选择的卡密类型为 VIP会员激活码 ,则这里为方便填写的面值为 vip 的价格，方便统计收入',
                    'dependency'   => array( 'card_type', 'any', 'money,credit,vip' ),
                ),
                array(
                    'id'      => 'card_invite_value',
                    'type'    => 'spinner',
                    'title'   => '邀请码奖励积分',
                    'min'     => 0,
                    'step'    => 1,
                    'default' => 10,
                    'unit'    => '积分',
                    'dependency'   => array( 'card_type', '==', 'invite' ),
                ),
                array(
                    'id'         => 'card_vip',
                    'type'       => 'radio',
                    'title'      => '生成会员等级',
                    'inline'     => true,
                    'options'    => $vip_options,
                    'default' => 0,
                    'dependency'   => array( 'card_type', '==', 'vip' ),
                ),
                array(
                    'id'         => 'card_vip_day',
                    'title'      => '会员有效期',
                    'type'       => 'spinner',
                    'min'      => 1,
                    'step'     => 5,
                    'unit'     => '天',
                    'default'  => 30,
                    'desc'     => '开通会员的时长。填<code>0</code>则为永久会员',
                    'dependency'   => array( 'card_type', '==', 'vip' ),
                ),
            )
        ));
    }
    
    public function save_action( $this_options, $instance ) {

        if(isset($this_options['card_type'], $this_options['card_count'])){
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'islide_card';
            
            $data = array(
                'card_code'=> $key,
                'type'=> $this_options['card_type'],
                'status'=> 0,
                'user_id'=> 0
            );
            
            if(!(int)$this_options['card_par_value']) {
                return $instance->notice = '请输入正确的面值！';
            }
            
            if(in_array($this_options['card_type'],array('money','credit','invite'))) {
                    
                $data['value'] = $this_options['card_type'] !== 'invite' ? (int)$this_options['card_par_value'] : (int)$this_options['card_invite_value']; 
                
            }else if($this_options['card_type'] == 'vip' && !empty($this_options['card_vip'])){
                $data['value'] = (int)$this_options['card_par_value'];
                $data['card_key'] = $this_options['card_vip'];
                $data['card_value'] = (int)$this_options['card_vip_day'];
                
            }else {
                return $instance->notice = '生成卡密失败，请检查设置是否正确';
            }
            
            $str_card = '';
            
            for ($i=0; $i < (int)$this_options['card_count']; $i++) {
                $key = $this->create_guid();
                
                $data['card_code'] = $key;
                
                $res = $wpdb->insert($table_name, $data);
                
                if($res) {
                    $str_card .= $key.'<br>';
                }
            }
            
            if($str_card){
                
                if(in_array($this_options['card_type'],array('money','credit'))){
                    $str = '当前面值'.(int)$this_options['card_par_value'].'元';
                }else if($this_options['card_type'] == 'invite') {
                    $str = '注册奖励'.(int)$this_options['card_invite_value'].'积分';
                }else {
                    
                    $roles = User::get_user_roles();
                    
                    $str = $roles[$this_options['card_vip']]['name'].(int)$this_options['card_vip_day'].'天';
                }
                
                return $instance->notice = sprintf($this->get_card_type($this_options['card_type']).'生成成功，'.$str.'、数量'.(int)$this_options['card_count'].'个，请前往%s','<a href="'.admin_url('/admin.php?page=islide_card_list').'">卡密列表</a><div style="background-color:#ddd;padding:10px">
                '.$str_card.'
                </div>');
            }
            
        }else {
            return $instance->notice = '生成卡密失败，请检查设置是否正确';
        }
    }
    
    public function create_guid(){
        $guid = '';
        $uid = uniqid ( "", true );
        
        $data = AUTH_KEY;
        $data .= $_SERVER ['REQUEST_TIME'];     // 请求那一刻的时间戳
        $data .= $_SERVER ['HTTP_USER_AGENT'];  // 获取访问者在用什么操作系统
        $data .= $_SERVER ['SERVER_ADDR'];      // 服务器IP
        $data .= $_SERVER ['SERVER_PORT'];      // 端口号
        $data .= $_SERVER ['REMOTE_ADDR'];      // 远程IP
        $data .= $_SERVER ['REMOTE_PORT'];      // 端口信息

        $hash = strtoupper ( hash ( 'ripemd128', $uid . $guid . md5 ( $data ) ) );

        $guid = substr ( $hash, 0, 4 ) . '-' . substr ( $hash, 8, 4 ) . '-' . substr ( $hash, 12, 4 ) . '-' . substr ( $hash, 16, 4 ) . '-' . substr ( $hash, 20, 4 );

        return $guid;
    }
    
    public function get_card_type($type){
        $types = array(
            'money' => '充值卡（余额）',
            'credit' => '充值卡（积分）',
            'invite' => '注册邀请码',
            'vip' => 'VIP会员激活码',
        );
        
        return isset($types[$type]) ? $types[$type] : '';
    }
    
    //卡密管理
    public function card_list_page(){
        //开始构建
        \IS_CSF::instance('card_list_page',array(
            'menu_title'              => '卡密管理', //页面的title信息 和 菜单标题
            'menu_slug'               => 'islide_card_list', //别名
            'callback' => array($this,'callback_card_list_page'),
            'menu_type'               => 'submenu', //submenu 子菜单
            'menu_capability'         => 'manage_options',
            'menu_parent'             => '', //父级菜单项的别名
            'tab_group'    => 'card_options_page', 
            'tab_title'    => '卡密管理',
            'save_option' => false,
        ));
    }
    
    public function callback_card_list_page($form) {
        $ref_url = admin_url('admin.php?'.$_SERVER['QUERY_STRING']);
        $ref_url = remove_query_arg(array('paged'),$ref_url);
        $card_table = new CardListTable();
        $card_table->prepare_items();
        
        $status = isset($_REQUEST['status']) ? $_REQUEST['status'] : 'all';
        $type = isset($_REQUEST['type']) ? $_REQUEST['type'] : 'all';
        
    ?>
        
        <div class="wrap">
			<?php echo $form->options_page_tab_nav_output(); ?>
			<div class="wrap">
			    <ul class="subsubsub" style=" display: block; width: 100%; ">
                    <li><a  href="<?php echo remove_query_arg(array('type','status','s','paged'),$ref_url); ?>" class="<?php echo $type === 'all' ? 'current' : ''; ?>">全部<span class="count">（<?php echo $card_table->get_type_count('all'); ?>）</span></a> |</li>
                    <li><a href="<?php echo add_query_arg('type','money',$ref_url); ?>" class="<?php echo $type === 'money' ? 'current' : ''; ?>">充值卡<span class="count">（<?php echo $card_table->get_type_count('money'); ?>）</span></a> |</li>
                    <li><a href="<?php echo add_query_arg('type','credit',$ref_url); ?>" class="<?php echo $type === 'credit' ? 'current' : ''; ?>">积分卡<span class="count">（<?php echo $card_table->get_type_count('credit'); ?>）</span></a>|</li>
                    <li><a href="<?php echo add_query_arg('type','invite',$ref_url); ?>" class="<?php echo $type === 'invite' ? 'current' : ''; ?>">邀请码<span class="count">（<?php echo $card_table->get_type_count('invite'); ?>）</span></a>|</li>
                    <li><a href="<?php echo add_query_arg('type','vip',$ref_url); ?>" class="<?php echo $type === 'vip' ? 'current' : ''; ?>">会员卡<span class="count">（<?php echo $card_table->get_type_count('vip'); ?>）</span></a></li>
                </ul>
                <ul class="subsubsub">
                    <li><a  href="<?php echo remove_query_arg(array('status','s'),$ref_url); ?>" class="<?php echo $status === 'all' ? 'current' : ''; ?>">全部<span class="count">（<?php echo $card_table->get_status_count('all'); ?>）</span></a> |</li>
                    <li><a href="<?php echo add_query_arg('status','1',$ref_url); ?>" class="<?php echo $status === '1' ? 'current' : ''; ?>">已使用<span class="count">（<?php echo $card_table->get_status_count(1); ?>）</span></a> |</li>
                    <li><a href="<?php echo add_query_arg('status','0',$ref_url); ?>" class="<?php echo $status === '0' ? 'current' : ''; ?>">未使用<span class="count">（<?php echo $card_table->get_status_count(0); ?>）</span></a></li>
                </ul>
    			<form action="" method="get">
    			    <?php
                        $card_table->search_box( '搜索', 'search_id' );
                    ?>
    			    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
    			    <?php $card_table->display(); ?>
    			</form>
			</div>
		</div>
        
        <?php
        // print_r(99999999);
    }
}
