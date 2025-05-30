<?php
namespace islide\Modules\Settings;
use islide\Modules\Common\Message;
/**
* 订单管理设置
*
* @version 1.2
* @since 2023/12/17
*/
class Withdrawal{

    public function init(){
        if ( class_exists('IS_CSF')) {
            //加载设置项
            add_filter( 'islide_withdrawal_edit_page_field_default',  [$this,'filter_field_default'], 10, 2);
            
            add_action( 'islide_withdrawal_edit_page_save_before', array($this,'save_action'), 10, 2 );
            
            $this->withdrawal_list_page();
            
            add_action('admin_notices', array($this,'withdrawal_notice'),1);
        }
    }
    
    //提现管理
    public function withdrawal_list_page(){
        //开始构建
        \IS_CSF::instance('withdrawal_list_page',array(
            'menu_title'              => '提现管理', //页面的title信息 和 菜单标题
            'menu_slug'               => 'withdrawal_list_page', //别名
            'callback' => array($this,'callback_withdrawal_list_page'),
            'menu_type'               => 'submenu', //submenu 子菜单
            'menu_parent'             => 'islide_main_page', //父级菜单项的别名
            'menu_capability'         => 'manage_options',
            'save_option' => false,
        ));
        
        \IS_CSF::instance('withdrawal_edit_page',array(
            'menu_title'              => '操作提现', //页面的title信息 和 菜单标题
            'menu_slug'               => 'withdrawal_edit_page', //别名
            'menu_type'               => 'submenu', //submenu 子菜单
            'menu_capability'         => 'manage_options',
            //'menu_parent'             => 'withdrawal_list_page', //父级菜单项的别名
            'save_option' => false,
            'islide_page_before' =>'<a href="' . esc_url(admin_url('admin.php?page=withdrawal_list_page')) . '" class="page-title-action">返回提现列表</a>',
            'islide_page_after'  => '<input type="hidden" name="id" value="'.((int)isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0).'">',
            'fields' => array(
                array(
                    'type'    => 'submessage',
                    'style'   => 'success',
                    'content' => '如果是待审核状态，您可以使用用户上传的收款二维码进行付款，然后手动讲状态改为已提现',
                ),
                // Select with multiple and sortable AJAX search Categories
                array(
                    'id'    => 'user_id',
                    'type'  => 'text',
                    'title' => '提现用户',
                    'default' => '',
                    'attributes' => array(
                        'readonly' => 'readonly',
                    ),
                ),
                array(
                    'id'    => 'record_type',
                    'type'  => 'text',
                    'title' => '提现类型',
                    'default' => '',
                    'attributes' => array(
                        'readonly' => 'readonly',
                    ),
                ),
                array(
                    'id'       => 'value',
                    'type'     => 'number',
                    'title'    => '申请金额',
                    'unit'     => '元',
                    'attributes' => array(
                        'readonly' => 'readonly',
                    ),
                ),
                array(
                    'id'       => 'record_value',
                    'type'     => 'number',
                    'title'    => '手续费',
                    'default'  => '',
                    'unit'     => '元',
                    'attributes' => array(
                        'readonly' => 'readonly',
                    ),
                ),
                array(
                    'type'     => 'callback',
                    'function' => array($this,'user_qrcode'),
                ),
                array(
                    'id'       => 'record_key',
                    'type'     => 'number',
                    'title'    => '<span class="red">实付金额</span>',
                    'default'  => '',
                    'unit'     => '元',
                    'attributes' => array(
                        'readonly' => 'readonly',
                    ),
                ),
                array(
                    'id'    => 'date',
                    'type'  => 'text',
                    'title' => '提现时间',
                    'default' => '',
                    'attributes' => array(
                        'readonly' => 'readonly',
                    ),
                ),
                array(
                    'id'          => 'status',
                    'type'        => 'select',
                    'title'       => '提现状态',
                    'options'     => array(
                        0  => '待审核',
                        1  => '已提现',
                    ),
                ),
            )
        ));
    }
    
    public function callback_withdrawal_list_page($form) {
        $ref_url = admin_url('admin.php?'.$_SERVER['QUERY_STRING']);
        $ref_url = remove_query_arg(array('paged'),$ref_url);
        $Withdrawal_table = new WithdrawalListTable();
        $Withdrawal_table->prepare_items();
        
        $status = isset($_REQUEST['status']) ? $_REQUEST['status'] : 'all';
    ?>
        
        <div class="wrap">
            <h2>提现管理</h2>
            <?php echo $form->options_page_tab_nav_output(); ?>
            <div class="wrap">
                <ul class="subsubsub" style=" display: block; width: 100%; ">
                    <li><a  href="<?php echo remove_query_arg(array('status','s','paged','user_id'),$ref_url); ?>" class="<?php echo $status == 'all' ? 'current' : ''; ?>">全部<span class="count">（<?php echo $Withdrawal_table->get_count('all'); ?>）</span></a></li>
                    <li><a  href="<?php echo add_query_arg('status','0',$ref_url); ?>" class="<?php echo $status ==  '0' ? 'current' : ''; ?>">未提现<span class="count">（<?php echo $Withdrawal_table->get_count(0); ?>）</span></a></li>
                    <li><a  href="<?php echo add_query_arg('status','1',$ref_url); ?>" class="<?php echo $status == '1' ? 'current' : ''; ?>">已提现<span class="count">（<?php echo $Withdrawal_table->get_count(1); ?>）</span></a></li>
                </ul>
                <form action="" method="get">
                    <?php
                        $Withdrawal_table->search_box( '搜索', 'search_id' );
                    ?>
                    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
                    <?php $Withdrawal_table->display(); ?>
                </form>
            </div>
        </div>
        
        <?
    }
    
    //过滤设置项的值
    public function filter_field_default($default,$field) {
        
        $res = array();
        
        if(isset($_REQUEST['action']) && $_REQUEST['action'] === 'edit') {
            
            $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
            global $wpdb;
            $table_name = $wpdb->prefix . 'islide_change_record';
            
            $res = $wpdb->get_row($wpdb->prepare("
                    SELECT * FROM $table_name
                    WHERE id = %d AND type = 'withdrawal'
                ",
                $id
            ),ARRAY_A);
            
            //var_dump($res);
        }
        
        if($field['id'] == 'value'){
            return -$res[$field['id']];
        }
        
        if(isset($res[$field['id']])) {
            if($field['id'] == 'user_id'){
                $user_data = get_userdata($res[$field['id']]);
                return $user_data->display_name.'(ID：'.$res[$field['id']].')';
            }
            
            if($field['id'] == 'record_type'){
                $arr = array(
                    'money'=>'余额提现',
                    'commission'=>'佣金提现',
                );
                return $arr[$res[$field['id']]];
            }
            
            return $res[$field['id']];
        }
        
        return '';
    }
    
    public function user_qrcode() {
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_change_record';
        
        $res = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM $table_name
                WHERE id = %d
            ",
            $id
        ),ARRAY_A);
        
        if(!empty($res['user_id'])) {
            $qrcode = \islide\Modules\Common\User::get_user_qrcode($res['user_id']);
            echo '<div class="csf-title"><h4>用户收款码</h4></div>';
            
            if(!empty($qrcode['weixin']) || !empty($qrcode['alipay'])){
            echo '
            <div class="csf-fieldset">
                <div style=" display: flex; grid-gap: 16px; ">
                    <div style=" text-align: center; ">
                        <img src="'.$qrcode['weixin'].'">
                        <p>微信收款码</p>
                    </div>
                    <div style=" text-align: center; ">
                        <img src="'.$qrcode['alipay'].'">
                        <p>支付宝收款码</p>
                    </div>
                </div>
            </div>
            ';
            }else {
                echo '用户未设置收款码';
            }
        }
    }
    
    public function save_action( $this_options, $instance ) {
        if(isset($_REQUEST['action']) && $_REQUEST['action'] === 'edit') {
            $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
            global $wpdb;
            $table_name = $wpdb->prefix . 'islide_change_record';
            
            $res = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM $table_name
                WHERE id = %d",
                $id
            ),ARRAY_A);
            
            if($wpdb->update(
                $table_name, 
                array(
                    'status'=>(int)$this_options['status'],
                )
                , array(
                    'id'=>$id
                )
            )){
            
                if((int)$this_options['status'] === 1 && $res['status'] == 0) {
                    $user_id  = (int)$res['user_id'];   
                    if($res['record_type'] == 'commission'){
                        $withdrawal_money = get_user_meta($user_id,'islide_withdrawal_money',true);
                        $withdrawal_money = $withdrawal_money ? $withdrawal_money : 0;
                        $money = bcadd((float)$withdrawal_money,(float)-$res['value'],2);
                        
                        update_user_meta($user_id,'islide_withdrawal_money',$money);
                    
                        $message_data = array(
                            'sender_id' => 0,
                            'receiver_id' => $user_id,
                            'title' => '佣金提现到账通知',
                            'content' => '佣金申请提现，已审核通过请注意查收到账情况',
                            'type' => 'distribution',
                            'mark' => array(
                                'meta' => array(
                                    array(
                                        'key'=> '申请金额',
                                        'value'=> '￥'.-$res['value'],
                                    ),
                                    array(
                                        'key'=> '手续费用',
                                        'value'=> '￥'.$res['record_value'],
                                    ),
                                    array(
                                        'key'=> '实际到账',
                                        'value'=> '￥'.$res['record_key'],
                                    )
                                )
                                
                            )
                        );
                    }else{
                        $message_data = array(
                            'sender_id' => 0,
                            'receiver_id' => $user_id,
                            'title' => '申请提现到账',
                            'content' => sprintf('您申请提现已审核通过，请注意查收。申请金额：￥%s；手续费：￥%s；实付金额：￥%s',-$res['value'],$res['record_value'],$res['record_key']),
                            'type' => 'wallet',
                        );
                    }
                    
                    Message::update_message($message_data);
                }
            }
        }
    }
    
    public function withdrawal_notice() {
        global $pagenow;
        $Withdrawal_table = new WithdrawalListTable();
        $count = $Withdrawal_table->get_count(0);
        
        if($count && $_GET['page'] !== 'islide_main_options') {
            echo '
            <div class="notice notice-info is-dismissible">
                <h3>提现申请</h3>
                <p>您有 <b>' . $count . '</b> 个提现申请等待处理！</p>
                <p><a class="button" href="' . add_query_arg(array('page' => 'withdrawal_list_page', 'status' => 0), admin_url('admin.php')) . '">立即处理</a></p>
            </div>';
        }
    }
}