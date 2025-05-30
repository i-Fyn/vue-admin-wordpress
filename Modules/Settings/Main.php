<?php
namespace islide\Modules\Settings;

/**后台设置**/

class Main{
    public function init(){

        //创建设置页面
        $this->main_options_page();

        //加载后台使用的CSS和JS文件
        add_action( 'admin_enqueue_scripts', array( $this, 'setup_admin_scripts' ),99999 ); //csf_enqueue
        
        //加载自定义图标
        add_filter( 'csf_field_icon_add_icons',function (){
            require IS_THEME_DIR.'/Library/remix-icon.php';
            return get_default_icons();
        });
        
        //加载设置项
        $this->load_settings();
        
        // WordPress上传 支持SVG文件类型
        add_action('upload_mimes', array( $this, 'add_file_types_to_uploads')); 
        
        //保存主题时候保存必要的wp设置
        add_action("csf_islide_main_options_saved", function (){
            /**
             * 刷新固定连接
             */
            flush_rewrite_rules();
        });
    }
    
     /**
     * 先创建设置页面
     *
     * @return void
     * @author
     * @version 1.0.0
     * @since 2023
     */
    public function main_options_page(){

        $prefix  = 'islide_main_options';
        
        //开始构建
        \CSF::createOptions($prefix, array(
            'menu_title'         => 'islide主题设置',
            'menu_slug'          => 'islide_main_options',
            'framework_title'    => 'islide主题',
            // 'footer_text'        => 'islideua主题 V' . wp_get_theme()['Version'],
            // 'footer_credit'      => '<i class="fa fa-fw fa-heart-o" aria-hidden="true"></i> ',
            'theme'              => 'dark', //后台暗黑模式 dark light
            //配置
            'show_in_customizer' => true, //在wp-customize中也显示相同的选项
            'show_reset_section' => true, //标志显示框架的重置部分按钮。
            'show_reset_all'     => false, //显示框架重置按钮的标志。
            
        ));
        


        
    }
    

    
    /**
     * 将SVG文件类型添加到WordPress上传文件类型中
     *
     * @param array $file_types 当前已允许的文件类型
     * @return array $file_types 添加了SVG文件类型的新文件类型数组
     */
    function add_file_types_to_uploads($file_types){
        $new_filetypes = array();
        $new_filetypes['svg'] = 'image/svg+xml'; // 添加SVG文件类型
        $file_types = array_merge($file_types, $new_filetypes );
        return $file_types;
    }

    /**
     * 再加载后台的设置页面及设置项
     *
     * @return bool
     */
    public function load_settings(){
        
            //数据统计
            $echarts = new Echarts();
            $echarts->init();
            
            do_action('islide_setting_action');
        
            //常规设置
            $normal = new Normal();
            $normal->init();
            
            //模块设置
            $template = new Template();
            $template->init();
            
            //用户相关
            $users = new Users();
            $users->init();
            
            //社区圈子
            $circle = new Circle();
            $circle->init();
        
            //系统相关
            $system = new System();
            $system->init();
            
            //备份设置
            $template = new Backup();
            $template->init();
            
            //Tax分类页面设置项
            $tax = new Taxonomies();
            $tax->init();
            
            //文章类型页面设置项
            $post = new Post();
            $post->init();
            
            //视频类型页面设置项
            $Video = new Video();
            $Video->init();
            
            //卡密管理页面设置项
            $card = new Card();
            $card->init();
            
            //消息管理设置
            $message = new Message();
            $message->init();
            
            //订单管理设置
            $orders = new Orders();
            $orders->init();
            
            //提现管理设置
            $withdrawal = new Withdrawal();
            $withdrawal->init();
            
            //举报管理设置
            $report = new Report();
            $report->init();
            
            //提现管理设置
            $verify = new Verify();
            $verify->init();
            
            //商品类型
            $shop = new Shop();
            $shop->init();
            
            //公告类型
            $notice = new Notice();
            $notice->init();
            
            //书籍
            $book = new Book();
            $book->init();
            

            
    }
    
    /**
    * 获取设置项
    *
    * @param string $where 设置项的组别，默认是某个组别设置项的类名
    * @param string $key 设置项的KEY
    *
    * @return string
    * @return int
    * @return array
    * @author
    * @version 1.0.0
    * @since 2023
    */
    public static function get_option($key = ''){

        global $_GLOBALS;

        if(isset($_GLOBALS['islide_main_options'])) {
            $settings = $_GLOBALS['islide_main_options'];
        } else {
            $settings = get_option('islide_main_options');
            $_GLOBALS['islide_main_options'] = $settings;
        }
        
        if($key == '') {
            return $settings;
        } else if(isset($settings[$key])){
            return $settings[$key];
        }
    
        return '';
    }
    
    /**
     * 加载后台使用的CSS和JS文件
     *
     * @return void
     * @author
     * @version 1.0.0
     * @since 2023
     */
    public function setup_admin_scripts(){ 
        
        wp_enqueue_script( 'islide-admin',IS_THEME_URI.'/Assets/admin/admin.js?v='.IS_VERSION, array(), IS_VERSION, true );
        wp_enqueue_style( 'islide-admin', IS_THEME_URI.'/Assets/admin/admin.css?v='.IS_VERSION, IS_VERSION, null);
        wp_enqueue_style( 'islide-fonts', IS_THEME_URI.'/Assets/fontend/fonts/remixicon.css?v='.IS_VERSION , array() , IS_VERSION , 'all');
        
        global $pagenow;
        
        if(in_array( $pagenow, array( 'post.php', 'post-new.php' ) )){
            $download_template = islide_get_option('single_post_download_template_group');
            $download_template = is_array($download_template) ? $download_template : array();
    
            wp_localize_script( 'islide-admin', 'islidedownloadtemplate',$download_template);
        }
    }
}
