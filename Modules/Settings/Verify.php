<?php
namespace islide\Modules\Settings;
use islide\Modules\Common\Message;
/**
* 订单管理设置
*
* @version 1.2
* @since 2023/12/17
*/
class Verify{

    public function init(){
        if ( class_exists('IS_CSF')) {
            //加载设置项
            add_filter( 'islide_verify_edit_page_field_default',  [$this,'filter_field_default'], 10, 2);
            
            add_action( 'islide_verify_edit_page_save_before', array($this,'save_action'), 10, 2 );
            
            $this->verify_list_page();
            
            add_action('admin_notices', array($this,'verify_notice'),1);
        }
    }
    
    //提现管理
    public function verify_list_page(){
        //开始构建
        \IS_CSF::instance('verify_list_page',array(
            'menu_title'              => '认证管理', //页面的title信息 和 菜单标题
            'menu_slug'               => 'verify_list_page', //别名
            'callback' => array($this,'callback_verify_list_page'),
            'menu_type'               => 'submenu', //submenu 子菜单
            'menu_parent'             => 'islide_main_page', //父级菜单项的别名
            'menu_capability'         => 'manage_options',
            'save_option' => false,
        ));
        
        $verify_group = islide_get_option('verify_group');
        $verify_opt = array();

        if(!empty($verify_group)) {
            $verify_opt = array_column($verify_group, 'name', 'type');
        }
        
        \IS_CSF::instance('verify_edit_page',array(
            'menu_title'              => '编辑认证', //页面的title信息 和 菜单标题
            'menu_slug'               => 'verify_edit_page', //别名
            'menu_type'               => 'submenu', //submenu 子菜单
            'menu_capability'         => 'manage_options',
            //'menu_parent'             => 'verify_list_page', //父级菜单项的别名
            'save_option' => false,
            'islide_page_before' =>'<a href="' . esc_url(admin_url('admin.php?page=verify_list_page')) . '" class="page-title-action">返回认证列表</a>',
            'islide_page_after'  => '<input type="hidden" name="id" value="'.((int)isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0).'">',
            'fields' => array(
                array(
                    'id'    => 'user_id',
                    'type'  => 'text',
                    'title' => '认证用户',
                    'default' => '',
                    'attributes' => array(
                        'readonly' => 'readonly',
                    ),
                ),
                
                array(
                    'id'       => 'money',
                    'type'     => 'number',
                    'title'    => '认证金额费用',
                    'default'  => '',
                    'unit'     => '元',
                    'attributes' => array(
                        'readonly' => 'readonly',
                    ),
                ),
                array(
                    'id'       => 'credit',
                    'type'     => 'number',
                    'title'    => '认证积分费用',
                    'default'  => '',
                    'unit'     => '积分',
                    'attributes' => array(
                        'readonly' => 'readonly',
                    ),
                ),
                array(
                    'id'    => 'date',
                    'type'  => 'text',
                    'title' => '申请认证时间',
                    'default' => '',
                    'attributes' => array(
                        'readonly' => 'readonly',
                    ),
                ),
                array(
                    'type'     => 'callback',
                    'function' => array($this,'verify_document'),
                ),
                array(
                    'id'       => 'title',
                    'type'     => 'text',
                    'title'    => '认证称号',
                ),
                array(
                    'id'          => 'type',
                    'type'        => 'select',
                    'title'       => '认证类型',
                    'options'     => $verify_opt,
                ),
                array(
                    'id'          => 'verified',
                    'type'        => 'select',
                    'title'       => '实名状态',
                    'options'     => array(
                        0  => '未实名',
                        1  => '已实名',
                    ),
                ),
                array(
                    'id'          => 'status',
                    'type'        => 'select',
                    'title'       => '认证状态',
                    'options'     => array(
                        0  => '待审核',
                        1  => '已认证',
                        2  => '未通过',
                    ),
                ),
                array(
                    'id'      => 'opinion',
                    'type'    => 'textarea',
                    'title'   => '审核意见&取消认证原因',
                    'desc'    => '简单阐述一下未通过的原因，比如资料提供不完整，图片模糊，让用户更好的提供资料<br><br>如果是已认证用户状态改为待审核，系统会发送一条取消认证消息给用户，取消原因最后说明一下',
                    'dependency' => array( 'status', 'any', '0,2' )
                ),
            )
        ));
    }
    
    public function callback_verify_list_page($form) {
        $ref_url = admin_url('admin.php?'.$_SERVER['QUERY_STRING']);
        $ref_url = remove_query_arg(array('paged'),$ref_url);
        $verify_table = new VerifyListTable();
        $verify_table->prepare_items();
        
        $status = isset($_REQUEST['status']) ? $_REQUEST['status'] : 'all';
    ?>
        
        <div class="wrap">
            <h2>认证管理</h2>
            <?php echo $form->options_page_tab_nav_output(); ?>
            <div class="wrap">
                <ul class="subsubsub" style=" display: block; width: 100%; ">
                    <li><a  href="<?php echo remove_query_arg(array('status','s','paged','user_id'),$ref_url); ?>" class="<?php echo $status == 'all' ? 'current' : ''; ?>">全部<span class="count">（<?php echo $verify_table->get_count('all'); ?>）</span></a></li>
                    <li><a  href="<?php echo add_query_arg('status','0',$ref_url); ?>" class="<?php echo $status ==  '0' ? 'current' : ''; ?>">审核中<span class="count">（<?php echo $verify_table->get_count(0); ?>）</span></a></li>
                    <li><a  href="<?php echo add_query_arg('status','1',$ref_url); ?>" class="<?php echo $status == '1' ? 'current' : ''; ?>">已认证<span class="count">（<?php echo $verify_table->get_count(1); ?>）</span></a></li>
                    <li><a  href="<?php echo add_query_arg('status','2',$ref_url); ?>" class="<?php echo $status == '2' ? 'current' : ''; ?>">未通过<span class="count">（<?php echo $verify_table->get_count(2); ?>）</span></a></li>
                </ul>
                <form action="" method="get">
                    <?php
                        $verify_table->search_box( '搜索', 'search_id' );
                    ?>
                    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
                    <?php $verify_table->display(); ?>
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
            $table_name = $wpdb->prefix . 'islide_verify';
            
            $res = $wpdb->get_row($wpdb->prepare("
                    SELECT * FROM $table_name
                    WHERE id = %d
                ",
                $id
            ),ARRAY_A);
            
            //var_dump($res);
        }
        
        
        if(isset($res[$field['id']])) {
            if($field['id'] == 'user_id'){
                $user_data = get_userdata($res[$field['id']]);
                return $user_data->display_name.'(ID：'.$res[$field['id']].')';
            }
            
            return $res[$field['id']];
        }
        
        return '';
    }
    
    public function verify_document() {
        $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_verify';
        
        $res = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM $table_name
                WHERE id = %d
            ",
            $id
        ),ARRAY_A);
        
        echo '
        <div class="csf-title"><h4>认证资料</h4></div>
        <div class="csf-fieldset">
            '.self::get_verify_data_html( $res['data'] ).'
        </div>';
    }
    
    public static function get_verify_data_html( $data ) { 
        add_thickbox();
        $data = maybe_unserialize($data);
        $data = !empty($data) ? $data : array();
        
        if(empty($data)) return '无资料';
        
        $fieldLabels = array(  
            'index' => '索引',  
            'type' => '认证类型',  
            'title' => '认证信息',  
            'company' => '公司名称',  
            'credit_code' => '信用代码',  
            'business_license' => '营业执照',  
            'business_auth' => '认证申请公函',  
            'official_site' => '官方网站',  
            'supplement' => '补充资料',  
            'operator' => '运营者',  
            'email' => '邮箱',  
            'telephone' => '手机号',  
            'id_card' => '身份证号码',  
            'idcard_hand' => '手持身份证',  
            'idcard_front' => '身份证正面',  
            'idcard_verso' => '身份证背面',  
        );
        
        $html = '<table class="verify">  
            <tr>  
                <th>信息项</th>  
                <th>详细信息</th>  
            </tr> ';
        
        foreach ($data as $key => $value) {
            if (!empty($value)) {
                $html .= '
                <tr>  
                    <td>'.htmlspecialchars($fieldLabels[$key]).'</td>  
                    <td>';
                    
                        $extension = strtolower(pathinfo($value, PATHINFO_EXTENSION));  
                        // 定义一个包含常见图片扩展名的数组  
                        $imageExtensions = array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff', 'tif', 'ico');  

                        if (in_array($extension,$imageExtensions)) {
                            $html .= '
                            <a href="'.htmlspecialchars($value).'" target="_blank" class="image-link">  
                                <img src="'.htmlspecialchars($value).'" alt="'.htmlspecialchars($fieldLabels[$key]).'" class="image-preview" />  
                            </a>';
                        }else {
                           $html .= htmlspecialchars($value);
                        }
                    
                $html .= '</td>  
                </tr>';
            }
        }
        
        $html .= '</table>';
        
        $id = rand();
        
        return '
        <style>
            table.verify {  
                width: 100%;  
                border-collapse: collapse;  
            }  
            .verify th, .verify td {  
                padding: 8px;  
                text-align: left;  
                border-bottom: 1px solid #ddd;  
            }  
            .verify th {  
                background-color: #f2f2f2;  
            }  
            .verify .image-preview {  
                max-width: 100px;  
                height: auto;  
                border: 1px solid #ccc;  
            } 
        </style>
        <a href="#TB_inline?height=395&width=320&inlineId=popup-content-'.$id.'" title="认证资料" class="thickbox">查看认证资料</a>
        <div id="popup-content-'.$id.'" style="display:none;">
            '.$html.'
        </div>';
    }
    
    public function save_action( $this_options, $instance ) {
        if(isset($_REQUEST['action']) && $_REQUEST['action'] === 'edit') {
            $id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
            global $wpdb;
            $table_name = $wpdb->prefix . 'islide_verify';
            
            $res = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM $table_name
                WHERE id = %d",
                $id
            ),ARRAY_A);
            
            if(!empty($res) && $wpdb->update(
                $table_name, 
                array(
                    'status' => (int)$this_options['status'],
                    'verified' => (int)$this_options['verified'],
                    'type' => $this_options['type'],
                    'title' => $this_options['title'],
                    'opinion' => $this_options['opinion'],
                )
                ,array(
                    'id' => $id
                )
            )){
                
                //如果修改前与修改后状态相同
                if((int)$this_options['status'] == (int)$res['status']) return false;
                
                $user_id  = (int)$res['user_id'];
                
                do_action('islide_verify_status_change',$user_id,$this_options['status'],$res['status']);
                
                //通过
                if((int)$this_options['status'] === 1) {
                    
                    update_user_meta($user_id,'islide_verify',$this_options['title']);
                    update_user_meta($user_id,'islide_verify_type',$this_options['type']);
                    
                    $msgContent = '您的认证申请已通过审核，您已拥有唯一身份标识，可在个人主页查看。';
                    
                    do_action('islide_verify_check_success',$user_id,$this_options);
                }else{
                    
                    //未通过
                    if((int)$this_options['status'] === 2 && (int)$res['status'] == 0) {
                        $msgContent = !empty($this_options['opinion']) ? $this_options['opinion'] : '您的认证申请未通过审核，请仔细检查所提交的资料，确保信息的准确性和完整性后重新提交。';
                    }
                    
                    //取消
                    if((int)$this_options['status'] === 0 && (int)$res['status'] == 1) {
                        
                        $msgContent = !empty($this_options['opinion']) ? $this_options['opinion'] : '您的认证已被取消，请联系管理员确认具体取消原因。';
                    }
                    
                    delete_user_meta($user_id, 'islide_verify');
                    delete_user_meta($user_id, 'islide_verify_type');
                }
                
                if(empty($msgContent)) return false;
                
                Message::update_message(array(
                    'sender_id' => 0,
                    'receiver_id' => (int)$user_id,
                    'title' => '认证服务',
                    'content' => $msgContent,
                    'type' => 'system',
                ));
            }
        }
    }
    
    public function verify_notice() {
        global $pagenow;
        $verify_table = new VerifyListTable();
        $count = $verify_table->get_count(0);
        
        if($count && $_GET['page'] !== 'islide_main_options') {
            echo '
            <div class="notice notice-info is-dismissible">
                <h3>认证申请</h3>
                <p>您有 <b>' . $count . '</b> 个认证申请等待处理！</p>
                <p><a class="button" href="' . add_query_arg(array('page' => 'verify_list_page', 'status' => 0), admin_url('admin.php')) . '">立即处理</a></p>
            </div>';
        }
    }
}