<?php namespace islide\Modules\Common;

class Editor{
    public function init(){
        //add_action('admin_head', array($this,'editor_button'));
        // foreach ( array('post.php','post-new.php') as $hook ) {
        //     add_action( "admin_head-$hook", array($this,'admin_head') );
        // }
        // add_filter('mce_buttons',array($this,'fenye_editor'));
        //if ( 'true' == get_user_option( 'rich_editing' ) ) {
            add_filter( 'mce_external_plugins', array($this,'add_tinymce_plugin' ));
            add_filter( 'mce_buttons', array($this,'register_mce_button' ));
            add_filter( 'mce_buttons_2', array($this,'register_mce_button_2' ));
            add_filter('mce_css', function ($mce_css) {
                $mce_css .= $mce_css ? ',' : '';
                $mce_css .= IS_THEME_URI .'/Assets/fontend/style.css';
                return $mce_css;
            });
        //}
    }

    
    //增加插件按钮动作
    function add_tinymce_plugin($plugin_array){
        //global $pagenow;
        //if(!isset($_GET['taxonomy']) && in_array( $pagenow, array( 'post.php','post-new.php' ), true )){
            $plugin_array['editor_button'] = IS_THEME_URI .'/Assets/fontend/editor.js';
            $plugin_array['placeholder'] = IS_THEME_URI .'/Assets/fontend/editor.js';
            //https://cdn.tiny.cloud/1/qagffr3pkuv17a8on1afax661irst1hbr4e6tbv888sz91jc/tinymce/4.9.11-104/plugins/codesample/plugin.min.js
            if(islide_get_option('single_highlightjs_open')) {
                $plugin_array['codesample'] =  IS_THEME_URI .'/Assets/fontend/library/tinymce/plugins/codesample/plugin.min.js';
            }
        //}
        return $plugin_array;
    }
    
    //注册第一行按钮
    function register_mce_button($buttons){
        $is_mobile = wp_is_mobile();
        $buttons   = ["islide_h2","islide_h3", "bold","blockquote","bullist", "numlist", "alignleft", "aligncenter", "alignright", "link","codesample", "spellchecker"];
        if ($is_mobile) {
            $buttons = ["islide_h2", "bold", "bullist", "link", "spellchecker"];
        }
    
        if (!is_admin()) {
            //不是在后台
            $buttons[] = 'islide_img';
            $buttons[] = 'islide_video';
        }
    
        if ((apply_filters('tinymce_hide', false) || is_super_admin())) {
            $buttons[] = 'islide_hide';
        }
    
        if (!$is_mobile) {
            $buttons[] = 'islide_quote';
        }
        $buttons[] = 'precode';
        //$buttons[] = 'fullscreen';
        $buttons[] = 'wp_adv';
    
        return $buttons;
    }
    
    function register_mce_button_2($buttons){
        $is_mobile = wp_is_mobile();
    
        $buttons = ["styleselect", "fontsizeselect", "forecolor"];
    
        if ($is_mobile) {
            $buttons[] = 'islide_quote';
        }
    
        $buttons[] = "removeformat";
        $buttons[] = "undo";
        $buttons[] = "redo";
        return $buttons;
    }
}