<?php
namespace islide\Modules\Settings;
use islide\Modules\Common\Message as CommonMessage;

/**
* 消息管理设置
*
* @version 1.0.3
* @since 2023
*/
class Message{

    public function init(){
        add_action( 'islide_message_options_save_before', array($this,'save_action'), 10, 2 );
        
        if ( class_exists('IS_CSF')) {
            $this->message_list_page();
            $this->message_options_page();
        }
    }
    
    //卡密管理
    public function message_list_page(){
        //开始构建
        \IS_CSF::instance('message_list_page',array(
            'menu_title'              => '消息管理', //页面的title信息 和 菜单标题
            'menu_slug'               => 'islide_message_list', //别名
            'callback' => array($this,'callback_message_list_page'),
            'menu_type'               => 'submenu', //submenu 子菜单
            'menu_parent'             => 'islide_main_page', //父级菜单项的别名
            'tab_group'    => 'message_list_page', 
            'tab_title'    => '消息管理',
            'save_option' => false,
        ));
    }
    
    public function callback_message_list_page($form) {
        $ref_url = admin_url('admin.php?'.$_SERVER['QUERY_STRING']);
        $ref_url = remove_query_arg(array('paged'),$ref_url);
        $message_table = new MessageListTable();
        $message_table->prepare_items();
        
        $status = isset($_REQUEST['status']) ? $_REQUEST['status'] : 'all';
        $type = isset($_REQUEST['type']) ? $_REQUEST['type'] : 'all';
        
    ?>
        
        <div class="wrap">
			<?php echo $form->options_page_tab_nav_output(); ?>
			<div class="wrap">
			    <ul class="subsubsub" style=" display: block; width: 100%; ">
                    <li><a  href="<?php echo remove_query_arg(array('type','s','paged','user_id'),$ref_url); ?>" class="<?php echo $type === 'all' ? 'current' : ''; ?>">全部<span class="count">（<?php echo $message_table->get_type_count('all'); ?>）</span></a></li>
                    <?php foreach ($message_table->get_message_types() as $key => $value) {?>
                        <li>| <a href="<?php echo add_query_arg('type',$key,$ref_url); ?>" class="<?php echo $type === $key ? 'current' : ''; ?>"><?php echo $value['name'] ?><span class="count">（<?php echo $message_table->get_type_count($key); ?>）</span></a></li>
                    <?php } ?>
                </ul>
    			<form action="" method="get">
    			    <?php
                        $message_table->search_box( '搜索', 'search_id' );
                    ?>
    			    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
    			    <?php $message_table->display(); ?>
    			</form>
			</div>
		</div>
        
        <?
    }
    
    //推送消息
    public function message_options_page(){
        
        $user_vip_group = islide_get_option('user_vip_group');
        $user_vip_group = is_array($user_vip_group) ? $user_vip_group : array();

        $vip_options = array();
        
        foreach ($user_vip_group as $Key => $vip) {
            $vip_options['vip'.$Key] =  $vip['name'];
        }
        
        \IS_CSF::instance('message_options',array(
            'menu_title'              => '推送消息', //页面的title信息 和 菜单标题
            'menu_slug'               => 'islide_message_push', //别名
            'menu_type'               => 'submenu', //submenu 子菜单
            'menu_parent'             => '', //父级菜单项的别名
            'tab_group'    => 'message_list_page', 
            'tab_title'    => '推送消息',
            'save_option' => false,
            'fields' => array(
                array(
                    'id'         => 'type',
                    'type'       => 'radio',
                    'title'      => '消息类型',
                    'inline'     => true,
                    'options'    => array(
                        'chat' => '聊天',
                        'system' => '系统通知',
                        'vip' => '会员通知',
                    ),
                    'default'    => 'chat'
                ),
                array(
                    'id'          => 'sender_id',
                    'type'        => 'select',
                    'title'       => '发消息用户',
                    'subtitle'    => '选择发送消息的用户',
                    'options'     => 'user',
                    'placeholder' => '输入用户ID、昵称、邮箱以搜索用户',
                    'ajax'        => true,
                    'chosen'      => true,
                    'default'     => 1,
                    'settings'    => array(
                        'min_length' => 1,
                        'width' => '50%',
                    ),
                    'dependency'  => array('type', '==', 'chat'),
                ),
                array(
                    'id'         => 'receiver_type',
                    'type'       => 'select',
                    'title'      => '接收消息用户',
                    'inline'     => true,
                    'options'    => array(
                        'all' => '所有用户',
                        'select' => '选择用户',
                    ),
                    'default'    => 'all'
                ),
                array(
                    'id'          => 'receiver_id',
                    'type'        => 'select',
                    'title'       => '选择接收消息的用户',
                    'subtitle'    => '选择接收消息的用户',
                    'options'     => 'user',
                    'placeholder' => '输入用户ID、昵称、邮箱以搜索用户',
                    'ajax'        => true,
                    'multiple'    => true,
                    'chosen'      => true,
                    'default'     => '',
                    'settings'    => array(
                        'min_length' => 1,
                        'width' => '50%',
                    ),
                    'dependency'  => array('receiver_type', '==', 'select'),
                ),
                array(
                    'id'          => 'style', //文件模板样式
                    'type'        => 'image_select',
                    'title'       => '布局风格样式',
                    'options'     => array(
                        'normal' => IS_THEME_URI.'/Assets/admin/images/message-style-1.png',
                        'image' => IS_THEME_URI.'/Assets/admin/images/message-style-2.png',
                        'card' => IS_THEME_URI.'/Assets/admin/images/message-style-3.png',
                        //'activity' => '活动'
                    ),
                    'class'       => 'module_type',
                    'default'     => 'normal',
                    'dependency'  => array('type', '!=', 'system'),
                ),
                array(
                    'id'          => 'title',
                    'title'       => '消息标题',
                    'type'        => 'text',
                    'placeholder' => '请输入消息标题',
                    'subtitle'    => '（非必填）标题建议在20-30个字符之间',
                    'default'     => '',
                    'dependency'  => array('style', 'not-any', 'image'),
                ),
                array(
                    'id'          => 'content',
                    'type'        => 'wp_editor',
                    'title'       => '消息内容',
                    'placeholder' => '请输入消息内容',
                    'sanitize'    => false,
                    'media_buttons' => false,
                    'default'     => '',
                    'dependency'  => array('style', '!=', 'image'),
                ),
                array(
                    'id'      => 'image',
                    'type'    => 'media',
                    'title'   => '图片消息',
                    'library' => 'image',
                    'dependency'  => array('style', '==', 'image'),
                ),
            )
        ));
    }
    
    public function save_action( $this_options, $instance ) {
        
        if($this_options['receiver_type'] == 'select' && empty($this_options['receiver_id'])) {
            return $instance->notice = '接收者为空'; 
        }
        
        if($this_options['receiver_type'] == 'all') {
            $this_options['receiver_id'] = 10000001;
        }
        
        $image_data = !empty($this_options['image']['url']) ? $this_options['image'] : '';
            
        if(empty($this_options['content']) && empty($image_data)) {
            return $instance->notice = '消息不可为空'; 
        }
        
        if($this_options['style'] == 'card' && empty($this_options['title'])) {
            return $instance->notice = '标题不可为空';
        }
        
        if(isset($this_options['type'])) {
            
            if(in_array($this_options['type'],array('vip','chat'))) {
                
                if(empty($this_options['sender_id'])) {
                    return $instance->notice = '发送者为空'; 
                }
                
                $data = array(
                    'sender_id' => $this_options['type'] == 'vip' ? 0 : (int)$this_options['sender_id'],
                    'receiver_id' => (int)$this_options['receiver_id'],
                    'content' => $this_options['content'],
                    'type' => $this_options['type'] == 'vip' ? 'vip' : 'chat',
                );
                
                if($image_data) {
                    
                    $data['content'] = '[图片]';
                    
                    $data['mark'] = array(
                        'id' => $image_data['id'],
                        'url' => $image_data['url'],
                        'width' => $image_data['width'],
                        'height' => $image_data['height'],
                        'type' => 'image'
                    );
                }
                
                if($this_options['style'] = 'card') {
                    $data['title'] = $this_options['title'];
                }
            }elseif ($this_options['type'] == 'system') {
                $data = array(
                    'sender_id' => 0,
                    'receiver_id' => (int)$this_options['receiver_id'],
                    'title' => $this_options['title'],
                    'content' => $this_options['content'],
                    'type' => 'system',
                );
            }
            
            if(CommonMessage::update_message($data)) {
                
                do_action('islide_send_message_action');
                
                return $instance->notice = '发送消息成功';
            }
            
        }else {
            return $instance->notice = '发送消息失败，请检查设置是否正确';
        }
    }
}