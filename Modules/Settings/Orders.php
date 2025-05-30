<?php
namespace islide\Modules\Settings;
use islide\Modules\Common\Orders as CommonOrders;

/**
* 订单管理设置
*
* @version 1.2
* @since 2023/12/17
*/
class Orders{

    public function init(){
        if ( class_exists('IS_CSF')) {
            $this->orders_list_page();
        }
    }
    
    //卡密管理
    public function orders_list_page(){
        //开始构建
        \IS_CSF::instance('orders_list_page',array(
            'menu_title'              => '订单管理', //页面的title信息 和 菜单标题
            'menu_slug'               => 'islide_orders_list', //别名
            'callback' => array($this,'callback_orders_list_page'),
            'menu_type'               => 'submenu', //submenu 子菜单
            'menu_parent'             => 'islide_main_page', //父级菜单项的别名
            'menu_capability'         => 'manage_options',
            'save_option' => false,
        ));
    }
    
    public function callback_orders_list_page($form) {
        $ref_url = admin_url('admin.php?'.$_SERVER['QUERY_STRING']);
        $ref_url = remove_query_arg(array('paged'),$ref_url);
        $orders_table = new OrdersListTable();
        $orders_table->prepare_items();
        
        $state = isset($_REQUEST['order_state']) ? $_REQUEST['order_state'] : 'all';
        $type = isset($_REQUEST['order_type']) ? $_REQUEST['order_type'] : 'all';
    ?>
        
        <div class="wrap">
            <h2>订单管理</h2>
			<?php echo $form->options_page_tab_nav_output(); ?>
			<div class="wrap">
			    <ul class="subsubsub" style=" display: block; width: 100%; ">
                    <li><a  href="<?php echo remove_query_arg(array('order_state','s','paged','user_id'),$ref_url); ?>" class="<?php echo $state == 'all' ? 'current' : ''; ?>">全部<span class="count">（<?php echo $orders_table->get_count('order_state'); ?>）</span></a></li>
                    <?php foreach (CommonOrders::get_order_state() as $key => $value) {?>
                        <li>| <a href="<?php echo add_query_arg('order_state',$key,$ref_url); ?>" class="<?php echo $state === (string)$key ? 'current' : ''; ?>"><?php echo $value ?><span class="count">（<?php echo $orders_table->get_count('order_state',$key); ?>）</span></a></li>
                    <?php } ?>
                </ul>
			    <ul class="subsubsub" style=" display: block; width: 100%; ">
                    <li><a  href="<?php echo remove_query_arg(array('order_type','s','paged','user_id'),$ref_url); ?>" class="<?php echo $type === 'all' ? 'current' : ''; ?>">全部<span class="count">（<?php echo $orders_table->get_count('order_type'); ?>）</span></a></li>
                    <?php foreach (CommonOrders::get_order_type() as $key => $value) {?>
                        <li>| <a href="<?php echo add_query_arg('order_type',$key,$ref_url); ?>" class="<?php echo $type === $key ? 'current' : ''; ?>"><?php echo $value ?><span class="count">（<?php echo $orders_table->get_count('order_type',$key); ?>）</span></a></li>
                    <?php } ?>
                </ul>
    			<form action="" method="get">
    			    <?php
                        $orders_table->search_box( '搜索', 'search_id' );
                    ?>
    			    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
    			    <?php $orders_table->display(); ?>
    			</form>
			</div>
		</div>
        
        <?
    }
    
}