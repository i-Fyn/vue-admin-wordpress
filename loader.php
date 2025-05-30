<?php
namespace islide;

use islide\Modules\Settings\Main as SettingsLoader;
use islide\Modules\Common\Main as CommonLoader;
use islide\Sql\Main as SqlLoader;
use islide\Menu\Main as MenuLoader;
use islide\Modules\Filter as FilterLoader;

if ( ! class_exists( 'islide', false ) ) {
    class islide{
        public function __construct(){

            spl_autoload_register('self::autoload');

            $this->load_library();

            $this->load_modules();
            
        }

        /**
         * 加载外部依赖
         *
         * @return void

         * @version 1.0.0
         * @since 2023
         */
        public function load_library(){
             $is_admin = is_admin() || $GLOBALS['pagenow'] === 'wp-login.php';
            //Jwt_Auth 鉴权
            if($is_admin && (!defined('AUTH_KEY') || strlen(AUTH_KEY) < 64)){
                add_filter( 'admin_notices', function (){
                    echo '<div class="notice notice-error"><p>wordpress AUTH_KEY 未有设置</p></div>';
                });
            }
                        
            //Jwt_Auth 鉴权
            if($is_admin && (!defined('AUTH_KEY') || strlen(AUTH_KEY) < 64)){
                add_filter( 'admin_notices', function (){
                    echo '<div class="notice notice-error"><p>wordpress AUTH_KEY 未有设置</p></div>';
                });
            }

            if(!class_exists('Jwt_Auth')){

                if(!defined('JWT_AUTH_SECRET_KEY')){
                    define('JWT_AUTH_SECRET_KEY', strrev(AUTH_KEY));
                }
    
                if(!defined('JWT_AUTH_CORS_ENABLE')){
                    define('JWT_AUTH_CORS_ENABLE', true);
                }
    
                require_once IS_THEME_DIR .IS_DS.'Library'.IS_DS.'jwt'.IS_DS.'jwt-auth.php';
                
            }else{
                if($is_admin){
                    add_filter( 'admin_notices', function (){
                        echo '<div class="notice notice-error"><p>islide主题不兼容 JWT（JWT Authentication for WP-API） 插件，请到插件页面删除插件</p></div>';
                    });
                }
            }
            //if($is_admin){
                
                //加载 https://pucqx.cn/4519.html
            require_once IS_THEME_DIR.IS_DS.'Library'.IS_DS.'codestar-framework'.IS_DS.'codestar-framework.php';
            //}
            
            //加载图片裁剪库
            require IS_THEME_DIR.'/Library/Grafika/Grafika.php';
            
            /**
             * 加载WeChatDeveloper
             * 
             * @version 1.0.3
             * @since 2023/9/3
             */
            require_once IS_THEME_DIR.IS_DS.'Library'.IS_DS.'WeChatDeveloper'.IS_DS.'include.php';
            
        }

        /**
         * 加载模块
         *
         * @return void

         * @version 1.0.0
         * @since 2023
         */
        public function load_modules(){
            
        
            //加载设置项
            if(is_admin()){
                $settings = new SettingsLoader();
                $settings->init();
            }
            
                        //加载公共类
            $common = new CommonLoader();
            $common->init();
            
            //加载数据库
            $sql = new SqlLoader();
            $sql->init();
            
            $menu = new MenuLoader();
            $menu->init();
            
            $filter = new FilterLoader();
            $filter->init();
        }

        /**
         * 自动加载命名空间
         *
         * @return void

         * @version 1.0.0
         * @since 2023
         */
        public static function autoload($class){

            // //主题模块
            if (strpos($class, 'islide\\') !== false) {
                $class = str_replace('islide\\','',$class);
                require_once IS_THEME_DIR.IS_DS.str_replace('\\', IS_DS, $class).'.php';
            }
            
            
            //图片裁剪库
            if(preg_match("/^Grafika\\\/i", $class)){
                $filename = IS_THEME_DIR.IS_DS.'Library'.IS_DS.str_replace('\\', IS_DS, $class).'.php';
                require_once $filename;
            }
        }
    }

    new islide();
    
}