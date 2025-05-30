<?php
namespace islide\Modules\Settings;
use islide\Modules\Common\Message;
/**
* 投诉与举报管理
*
* @version 1.2
* @since 2023/12/17
*/
class Report {

    public function init(){
        if ( class_exists('IS_CSF')) {
            //加载设置项
            $this->report_list_page();
            
            add_action('admin_notices', array($this,'report_notice'),1);
        }
    }
    
    //提现管理
    public function report_list_page(){
        //开始构建
        \IS_CSF::instance('report_list_page',array(
            'menu_title'              => '投诉与举报管理', //页面的title信息 和 菜单标题
            'menu_slug'               => 'report_list_page', //别名
            'callback' => array($this,'callback_report_list_page'),
            'menu_type'               => 'submenu', //submenu 子菜单
            'menu_parent'             => 'islide_main_page', //父级菜单项的别名
            'menu_capability'         => 'manage_options',
            'save_option' => false,
        ));
    }
    
    public function callback_report_list_page($form) {
        $ref_url = admin_url('admin.php?'.$_SERVER['QUERY_STRING']);
        $ref_url = remove_query_arg(array('paged'),$ref_url);
        $Report_table = new ReportListTable();
        $Report_table->prepare_items();
        
        $status = isset($_REQUEST['status']) ? $_REQUEST['status'] : 'all';
    ?>
        
        <div class="wrap">
            <h2>投诉与举报管理</h2>
            <?php echo $form->options_page_tab_nav_output(); ?>
            <div class="wrap">
                <ul class="subsubsub" style=" display: block; width: 100%; ">
                    <li><a  href="<?php echo remove_query_arg(array('status','s','paged','user_id'),$ref_url); ?>" class="<?php echo $status == 'all' ? 'current' : ''; ?>">全部<span class="count">（<?php echo $Report_table->get_count('all'); ?>）</span></a></li>
                    <li><a  href="<?php echo add_query_arg('status','0',$ref_url); ?>" class="<?php echo $status ==  '0' ? 'current' : ''; ?>">未处理<span class="count">（<?php echo $Report_table->get_count(0); ?>）</span></a></li>
                    <li><a  href="<?php echo add_query_arg('status','1',$ref_url); ?>" class="<?php echo $status == '1' ? 'current' : ''; ?>">已处理<span class="count">（<?php echo $Report_table->get_count(1); ?>）</span></a></li>
                </ul>
                <form action="" method="get">
                    <?php
                        $Report_table->search_box( '搜索', 'search_id' );
                    ?>
                    <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
                    <?php $Report_table->display(); ?>
                </form>
            </div>
        </div>
        
        <?
    }
    
    public function report_notice() {
        global $pagenow;
        $Report_table = new ReportListTable();
        $count = $Report_table->get_count(0);
        
        if($count && $_GET['page'] !== 'islide_main_options') {
            echo '
            <div class="notice notice-info is-dismissible">
                <h3>投诉举报</h3>
                <p>您有 <b>' . $count . '</b> 个投诉举报待处理！</p>
                <p><a class="button" href="' . add_query_arg(array('page' => 'report_list_page', 'status' => 0), admin_url('admin.php')) . '">立即处理</a></p>
            </div>';
        }
    }
}