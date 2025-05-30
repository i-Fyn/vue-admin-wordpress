<?php

/**
* 定义常量
*/

define('IS_DS',DIRECTORY_SEPARATOR);  // 斜杠 /
define('IS_HOME_URI',home_url()); //首页 http://www.islide.com
define('IS_THEME_DIR', get_template_directory() ); ///www/wwwroot/www.islide.com/wp-content/themes/islide 主题文件目录
define('IS_VERSION', '1.2.7' ); //主题版本号
define('IS_THEME_URI', get_template_directory_uri() );


//初始化自动加载类
require 'loader.php';




/**
 * 
* 主题启用后进行的操作
 */
if ( ! function_exists( 'islide_setup' ) ) :
    
function islide_setup() {

    $arg = array(
        'top-menu' => '顶部页眉菜单（不支持三级菜单）',
        'channel-menu' => '左侧菜单顶部',
        'channel-menu-bottom' => '左侧菜单底部（需要开启顶部菜单才能显示）',
    );

    //注册菜单
    register_nav_menus($arg);
    
    //支持友情链接
    add_theme_support( 'automatic-feed-links' );

    //支持title标签https://www.yudouyudou.com/WordPress/334.html
    add_theme_support( 'title-tag' );

    //支持缩略图
    add_theme_support( 'post-thumbnails' );

    // 启用 WordPress 主题自定义器中的选择性刷新小工具区块功能
    add_theme_support( 'customize-selective-refresh-widgets' );
    
    //开启文章格式
    add_theme_support( 'post-formats', array( 'image', 'status' ,'gallery', 'video') );
    
    //禁止转义某些符号
    add_filter( 'run_wptexturize', '__return_false', 9999);
    
    //开启友情连接
    add_filter('pre_option_link_manager_enabled','__return_true');
    
}

endif;
add_action( 'after_setup_theme', 'islide_setup' );


/**
 * 获取设置项
 *
 * @param string $key 设置项KEY
 *
 * @return void
 * @author 青青草原上
 * @version 1.0.0
 * @since 2023
 */
function islide_get_option($key = ''){
    return islide\Modules\Settings\Main::get_option($key);
}



//获取全部社交登录全部类型
function get_oauth_types() {
    return apply_filters('islide_get_oauth_types',array(
        'qq' => array(
            'name' => 'QQ',
            'icon' => 'ri-qq-fill',
            'type' => 'qq'
        ),
        'wx' => array(
            'name' => '微信',
            'icon' => 'ri-wechat-fill',
            'type' => 'weixin'
        ),
        'sina' => array(
            'name' => '微博',
            'icon' => 'ri-weibo-fill',
            'type' => 'weibo'
        ),
        'alipay' => array(
            'name' => '支付宝',
            'icon' => 'ri-alipay-fill',
            'type' => 'alipay'
        ),
        'baidu' => array(
            'name' => '百度',
            'icon' => 'ri-baidu-fill',
            'type' => 'baidu'
        ),
        'github' => array(
            'name' => 'Github',
            'icon' => 'ri-github-fill',
            'type' => 'github'
        ),
        // 'gitee' => array(
        //     'name' => '码云',
        //     'icon' => 'ri-gitee-fill',
        //     'type' => 'gitee'
        // ),
        'dingtalk' => array(
            'name' => '钉钉',
            'icon' => 'ri-dingding-fill',
            'type' => 'dingtalk'
        ),
        // 'huawei' => array(
        //     'name' => '华为',
        //     'icon' => 'ri-huawei-fill',
        //     'type' => 'huawei'
        // ),
        'google' => array(
            'name' => '谷歌',
            'icon' => 'ri-google-fill',
            'type' => 'google'
        ),
        'microsoft' => array(
            'name' => '微软',
            'icon' => 'ri-windows-fill',
            'type' => 'microsoft'
        ),
        'facebook' => array(
            'name' => 'facebook',
            'icon' => 'ri-facebook-circle-fill',
            'type' => 'facebook'
        ),
        'twitter' => array(
            'name' => '推特',
            'icon' => 'ri-twitter-fill',
            'type' => 'twitter'
        ),
    ));
}

/**
 * 根据类型获取类型名称
 *
 * @param string $type 要获取名称的类型
 * @return string|null 返回类型的名称，如果类型无效则返回 null
 */
function islide_get_type_name($type){

    if(!$type) return;

    $arg = apply_filters('islide_get_type_name', array(
        'post'=>'文章', 
        'page'=>'页面',
        'shop'=>'商品',
        'user'=>'用户',
        'post_tag'=>'标签',
        'category'=>'分类',
        'video'=>'视频',
        'topic'=>'话题',
        'circle'=>'瞬间',
        'circle_cat'=>'圈子社区',
    ));

    if(isset($arg[$type])) return $arg[$type];

    return;
}

/**
 * 获取 post_status 的描述
 *
 * @param string $status post_status 的值
 * @return string post_status 的描述
 */
function islide_get_post_status_name($status) {
    $post_status = array(
        'publish' => '已发布',
        'draft' => '草稿',
        'pending' => '待审核',
        'private' => '私密',
        'trash' => '回收站',
        'auto-draft' => '自动草稿',
        'inherit' => '继承',
        'future' => '未来发布'
    );

    if (array_key_exists($status, $post_status)) {
        return $post_status[$status];
    }
    
    return;
}

/**
 * 文章类型
 */
function islide_get_post_types(){

    $types = apply_filters('islide_get_post_types', array(
        'post'=>'文章', 
        'shop'=>'商品',
        'video'=>'课程',
        'circle'=>'帖子',
    ));
    
    return $types;
}

/**
 * 获取搜索类型
 */
function islide_get_search_type(){
    
    $arg = apply_filters('islide_get_search_type', array(
        'post'=>'文章',
        'video'=>'视频',
        'circle'=>'帖子',
    ));
    
    
    
    return $arg;
}


/**
 * 初始化主题功能
 */
function islide_theme_init() {
    
    remove_image_size('post-thumbnail'); // 禁用通过 set_post_thumbnail_size() 添加的图片尺寸
    remove_image_size('another-size');   // 禁用任何其他添加的图片尺寸

}

add_action( 'init', 'islide_theme_init' ); 


// 注册激活钩子 embed功能 移除嵌入式内容的重写规则。
register_activation_hook( __FILE__, array('\islide\Modules\Common\Optimize','disable_embeds_remove_rewrite_rules'));

// 注册停用钩子 embed功能 刷新重写规则。
register_deactivation_hook( __FILE__, array('\islide\Modules\Common\Optimize','disable_embeds_flush_rewrite_rules'));


/**
 * 自定义函数，用于将时间戳转换为“多久之前”的格式
 *
 * @param int $ptime 时间戳
 * @param bool $return 是否返回结果，默认为 false
 * 
 * @return string 时间差字符串，如“3分钟前”，如果 $return 为 true，则返回字符串，否则直接输出
 */
function islide_time_ago($ptime,$return = false) {
    // 将时间戳转换为时间差字符串
    return \islide\Modules\Common\Post::time_ago($ptime,$return);
}

/**
 * 根据图片URL获取图片ID
 *
 * @param string $image_url 图片URL
 * 
 * @return int 图片ID
 */
function islide_get_image_id($image_url) { //attachment_url_to_postid( $image_url )
    global $wpdb;
    $attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s';", $image_url )); 
    return $attachment[0]; 
}

/**
 * 获取缩略图
 *
 * @param array $arg 缩略图参数：url->图片地址,type->裁剪方式,width->裁剪宽度,height->裁剪高度,gif->是否显示动图
 * @return string 裁剪后的图片地址
 */
function islide_get_thumb($arg){
    $url = \islide\Modules\Common\FileUpload::thumb($arg);
    return get_relative_upload_path($url);
}

/**
 * 获取img标签html
 *
 * @param array $arg 参数：class->类名,alt->属性,src->图片地址
 * @param boolean $lazy 是否开启图片懒加载
 * 
 * @return string img标签html
 */
function islide_get_img($arg, $lazy = true){
    //$arg = apply_filters('islide_get_thumb_action', $arg);
    
    // 获取图片class
    $class = isset($arg['class']) && $arg['class'] ? ' class="'.implode(" ",$arg['class']):'';
    
    // 获取图片alt
    $alt = isset($arg['alt']) && $arg['alt'] ? ' alt="'.$arg['alt'].'"':'';
    
    //图片自定义属性
    $attribute = isset($arg['attribute']) && $arg['attribute'] ? ' '.$arg['attribute']:'';
    
    // 如果图片地址存在
    if(isset($arg['src']) && $arg['src']){
        
        // 获取图片懒加载属性
        $lazyload = islide_lazyload($arg['src'],$lazy);
        
        // 如果class属性不为空
        if(!empty($class)) {
            $class .= $lazyload;
        }else{
            $class .= ' class="'.$lazyload;
        }
        
        // 获取完整的图片标签
        $arg = '<img'.$class.$alt.$attribute.'>';
    }
    
    // 如果是数组，则返回空字符串
    if(is_array($arg)) return '';
    
    // 返回图片标签
    return $arg;
}

/**
 * 获取用户头像标签
 *
 * @param array $arg 参数：src->头像地址,alt->属性,pendant->头像挂件地址
 * 
 * @return string 用户头像标签
 */
function islide_get_avatar($arg,$mark = 1){
    // 头像挂件
    $pendant = isset($arg['pendant']) && $arg['pendant'] ? '<img src="'.$arg['pendant'].'" class="avatar-pendant" alt="用户头像框挂件">':'';
    
    // 徽章
    $badge = isset($arg['badge']) && $arg['badge'] ? '<img src="'.$arg['badge'].'" class="avatar-badge" alt="用户头像徽章">':'';
    
    // 获取图片alt
    $alt = isset($arg['alt']) && $arg['alt'] ? ' alt="'.$arg['alt'].'"':'';
    
    // 如果头像地址存在
    if(isset($arg['src']) && $arg['src']){
        // 获取用户头像标签
 
         $arg = '
        <div class="user-avatar">
            <img src="'.$arg['src'].'" class="avatar-face w-h" '.$alt.'>
            '.$pendant.$badge.'
        </div>';   
    } else {
         /*   <img src="'.$arg['src'].'" class="avatar-face w-h" '.$alt.'>*/
        // 如果没有头像地址，则返回空字符串
        return "";
    }
    // 如果是数组，则返回空字符串
    if(is_array($arg)) return '';
    
    // 返回用户头像标签
    return $arg;
}

/**
 * 获取图片懒加载属性
 *
 * @param string $src 图片地址
 * @param boolean $lazy 是否开启图片懒加载
 * 
 * @return string 图片懒加载属性
 */
function islide_lazyload($src, $lazy = true){
    // 获取是否开启图片懒加载选项
    $open = islide_get_option('islide_image_lazyload');
    
    // 如果开启图片懒加载选项并且需要懒加载
    if($open && $lazy){
        // 获取默认的懒加载图片
        $default_img = islide_get_option('lazyload_default_img');
        
        // 返回图片懒加载属性
        return ' lazyload" data-src="'.$src.'" src="'.$default_img.'"';
    }

    // 如果不需要懒加载，则返回原始的图片属性
    return '" src="'.$src.'"';
}

/**
 * 获取页面宽度
 *
 * @param boolean $show_widget 是否含小工具，true为含小工具，false为不含小工具
 * 
 * @return int 页面宽度
 */
function islide_get_page_width($show_widget,$widget_width = 0,$page=''){
    
    if($page == 'circle'){
        
    }else{
        // 获取页面宽度
        $page_width = (int)islide_get_option('wrapper_width');
        $sidebar_width = islide_get_option('sidebar_width');
    }

    if($show_widget){
        // 获取小工具宽度
        $width = $widget_width ? $widget_width : $sidebar_width;
        return (int)$page_width - (int)$width;
        
    } else {
        // 如果不含小工具，则返回页面宽度
        return $page_width;
    }

}

/**
 * 返回文章正文字符串中的第一张图片
 *
 * @param string $content 文章正文字符串
 * @param int|string $i 选择返回第几张图片，可选值为数字或字符串'all'，默认为0，即返回第一张图片
 *
 * @return string|array|false 返回图片的URL地址，如果未找到图片则返回false；如果$i为'all'，则返回所有图片的URL数组
 */
function islide_get_first_img($content, $i = 0) {
    // 定义正则表达式，匹配文章内容中的图片标签
    $pattern = '/<img[^>]+src="([^">]+)"/i';

    // 使用正则表达式匹配所有图片
    preg_match_all($pattern, $content, $matches);

    // 如果没有匹配到图片，返回false
    if (empty($matches[1])) {
        return false;
    }

    // 如果$i为'all'，返回所有图片的URL数组
    if ($i === 'all') {
        return $matches[1];
    }

    // 确保$i是整数且不超过图片数量
    $i = intval($i);
    if ($i < 0 || $i >= count($matches[1])) {
        return false;
    }

    // 返回指定序号的图片URL
    return $matches[1][$i];
}

/**
 * 获取默认图片的URL地址
 *
 * @return string 默认图片的URL地址
 */
function islide_get_default_img(){
    // 获取主题选项中设置的默认图片地址
    $default_imgs = islide_get_option('islide_default_imgs');

    // 如果为空，则返回默认的图片地址
    if(empty($default_imgs)){
        return IS_THEME_URI.'/Assets/fontend/images/default-img.jpg';
    }

    // 如果设置了默认图片，则将其转化为数组，并随机获取其中的一个元素，即为默认图片的附件ID
    $arr = explode(',', $default_imgs);

    // 获取附件URL
    return wp_get_attachment_url($arr[array_rand($arr, 1)]);
}

/**
 * 修改文章摘要的显示内容
 *
 * @param string $text 原始的文章摘要内容
 *
 * @return string 修改后的文章摘要内容
 */
function islide_change_excerpt( $text){    
    // 判断传入的参数$text是否为字符串类型，如果不是则直接返回
    if(is_string($text)){
        // 在文章摘要中查找第一个左方括号[的位置，如果没有找到则返回原始摘要内容
        $pos = strpos( $text, '[');
        if ($pos === false)
        {
            return $text;
        }

        // 截取左方括号之前的部分作为新的摘要内容，并使用rtrim函数去除末尾的空格
        return rtrim (substr($text, 0, $pos) );
    }
    return $text;
}
add_filter('get_the_excerpt', 'islide_change_excerpt');




function islide_substr_by_unit($str, $max_len) {
    $result = '';
    $length = 0;
    $asciiCount = 0;

    $chars = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);

    foreach ($chars as $char) {
        if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $char)) {
            if ($length >= $max_len) break;
            $result .= $char;
            $length += 1;
        } elseif (preg_match('/[\x00-\x7F]/', $char)) {
            $asciiCount += 1;
            $result .= $char;
            if ($asciiCount == 4) {
                $length += 1;
                $asciiCount = 0;
            }
        } else {
            if ($length >= $max_len) break;
            $result .= $char;
            $length += 1;
        }

        if ($length >= $max_len) break;
    }

    return $result;
}
/**
 * 获取描述
 *
 * @param int $post_id 文章ID
 * @param int $size 截取长度
 * @param string $content 需要截取的内容
 *
 * @return string 截取以后的字符串
 */
function islide_get_desc($post_id, $size, $content = '') {
    // 获取内容
    if ($content) {
        $content = strip_shortcodes($content);
    } else {
        $content = get_post_field('post_excerpt', $post_id);
        $content = $content ? $content : strip_shortcodes(get_post_field('post_content', $post_id));
    }

    // 预处理文本
    $content = wp_strip_all_tags($content); // 去除 HTML
    $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
    $content = str_replace(array('{{', '}}'), '', $content);

    // 截取指定单位长度
    $desc = islide_substr_by_unit($content, $size);

    return trim($desc . '...');
}




/**
 * 获取空数据提示 HTML 结构
 *
 * @param string $text 提示信息文本
 * @param string $image 图片文件名
 * @return string 返回 HTML 结构字符串
 */
function islide_get_empty($text,$image){
    return '
    <div class="empty islide-radius box">
        <img src="'.IS_THEME_URI.'/Assets/fontend/images/'.$image.'" class="empty-img"> 
        <p class="empty-text">' . $text . '</p>
    </div>';
}

/**
 * 将数字格式化为易于阅读的格式，例如将1000格式化为1k，1000000格式化为1m
 *
 * @param int $num 待格式化的数字
 *
 * @return string 格式化后的字符串
 *
 * @version 1.0.0
 * @since 2023
 */
function islide_number_format($num) {
    // 如果$num为空，则将其转化为0
    $num = $num === '' ? 0 : $num;

    // 如果$num大于1000，则进行格式化操作
    if($num>1000) {
        $x = round($num);
        $x_number_format = number_format($x);
        $x_array = explode(',', $x_number_format);
        $x_parts = array('k', 'm', 'b', 't');
        $x_count_parts = count($x_array) - 1;
        $x_display = $x;
        $x_display = $x_array[0] . ((int) $x_array[1][0] !== 0 ? '.' . $x_array[1][0] : '');
        $x_display .= $x_parts[$x_count_parts - 1];

        return $x_display;
    }

    // 如果$num小于等于1000，则直接返回$num
    return $num;
}

/**
 * 将十六进制颜色值转化为RGB颜色值，并添加透明度
 *
 * @param string $hex 十六进制颜色值
 *
 * @return string RGBA格式的颜色值
 *
 * @version 1.0.0
 * @since 2023
 */
function islide_hex2rgb($hex) {
    // 去除$hex中的#字符
    $hex = str_replace("#", "", $hex);

    // 如果$hex的长度为3，则每个字符重复一次，例如#abc转化为rgb(170, 187, 204)
    if(strlen($hex) == 3) {
       $r = hexdec(substr($hex,0,1).substr($hex,0,1));
       $g = hexdec(substr($hex,1,1).substr($hex,1,1));
       $b = hexdec(substr($hex,2,1).substr($hex,2,1));
    } else {
       // 如果$hex的长度为6，则将其拆分为红、绿、蓝三个部分，并将其转化为10进制数值
       $r = hexdec(substr($hex,0,2));
       $g = hexdec(substr($hex,2,2));
       $b = hexdec(substr($hex,4,2));
    }

    // 将RGB数值和透明度0.1拼接为rgba格式的字符串，并返回
    return 'rgba('.$r.', '.$g.', '.$b.', var(--opacity,0.1))';
}



//删除钩子
function islide_remove_filters_with_method_name( $hook_name = '', $method_name = '', $priority = 0 ) {
    global $wp_filter;

    // Take only filters on right hook name and priority
    if ( ! isset( $wp_filter[ $hook_name ][ $priority ] ) || ! is_array( $wp_filter[ $hook_name ][ $priority ] ) ) {
        return false;
    }
    // Loop on filters registered
    foreach ( (array) $wp_filter[ $hook_name ][ $priority ] as $unique_id => $filter_array ) {
        // Test if filter is an array ! (always for class/method)
        if ( isset( $filter_array['function'] ) && is_array( $filter_array['function'] ) ) {
            // Test if object is a class and method is equal to param !
            if ( is_object( $filter_array['function'][0] ) && get_class( $filter_array['function'][0] ) && $filter_array['function'][1] == $method_name ) {
                // Test for WordPress >= 4.7 WP_Hook class (https://make.wordpress.org/core/2016/09/08/wp_hook-next-generation-actions-and-filters/)
                if ( is_a( $wp_filter[ $hook_name ], 'WP_Hook' ) ) {
                    unset( $wp_filter[ $hook_name ]->callbacks[ $priority ][ $unique_id ] );
                } else {
                    unset( $wp_filter[ $hook_name ][ $priority ][ $unique_id ] );
                }
            }
        }
    }
    return false;
}

/**
 * 设置cookie
 *
 * @param string $key cookie的键名
 * @param mixed $val cookie的值
 * @param int $time cookie的有效时间，默认为1天
 * 
 * @return bool 是否成功设置cookie
 */
function islide_setcookie($key,$val,$time = 86400) {
    $secure = true;
    return setcookie( $key, maybe_serialize($val), time() + $time, COOKIEPATH, COOKIE_DOMAIN ,$secure);
}

/**
 * 获取cookie
 *
 * @param string $key cookie的键名
 * 
 * @return mixed cookie的值，如果不存在则返回空字符串
 */
function islide_getcookie($key) {
    $resout = isset( $_COOKIE[$key] ) ? $_COOKIE[$key] : '';
    return maybe_unserialize(wp_unslash($resout));
}

/**
 * 删除cookie
 *
 * @param string $key cookie的键名
 * 
 * @return bool 是否成功删除cookie
 */
function islide_deletecookie($key) {
    return setcookie( $key, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
}

/**
 * JWT认证token生成前的过滤器函数
 *
 * @param array $data token中的数据
 * @param WP_User|WP_Error $user 当前用户对象或错误对象
 * 
 * @return array $data 经过过滤后的token数据
 */
function islide_jwt_auth_token($data, $user){
    if (is_array($user)){
        wp_die(__('密码错误','islide'));
    }else{
        return apply_filters('islide_jwt_auth_token', $data, $user);
    }
}

add_filter( 'jwt_auth_token_before_dispatch', 'islide_jwt_auth_token', 10, 2);


/**
 * 获取当前用户的IP地址
 * 
 */
function islide_get_user_ip() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}



/**
 * 防止在一定时间内重复操作
 *
 * @param int $key 用户ID，默认为当前用户ID
 * @return bool 返回检查结果，若已检查过则返回false，否则返回true
 */
function islide_check_repo($key = 0){

    if(!$key){
        $key = get_current_user_id();
    }

    // 从缓存中获取检查结果
    $res = wp_cache_get('islide_rp_'.$key);

    // 如果已经检查过，则返回false
    if($res) return false;

    // 将检查结果存入缓存中，并设置过期时间为2秒
    wp_cache_set('islide_rp_'.$key,1,'',2);

    // 返回true表示未检查过
    return true;
}



function islide_settings_error($type='updated',$message=''){
    $type = $type=='updated' ? 'updated' : 'error';
    if(empty($message)) $message = $type=='updated' ?  '设置已保存。' : '保存失败，请重试。';
    add_settings_error(
        'islide_settings_message',
        esc_attr( 'islide_settings_updated' ),
        $message,
        $type
    );
    settings_errors( 'islide_settings_message' );
}

//字符串表示的时间转换为 Unix 时间戳
if(!function_exists('wp_strtotime')){
    function wp_strtotime($str) {
        // 如果 $str 为空，则返回 0
        if (!$str) return 0;
    
        // 获取时区字符串和 GMT 偏移量
        $tz_string = get_option('timezone_string');
        $tz_offset = get_option('gmt_offset', 0);
    
        // 如果时区字符串不为空，则使用时区字符串
        if (!empty($tz_string)) {
            $timezone = $tz_string;
    
        // 如果 GMT 偏移量为 0，则使用 UTC 时区
        } elseif ($tz_offset == 0) {
            $timezone = 'UTC';
    
        // 否则使用 GMT 偏移量作为时区
        } else {
            $timezone = $tz_offset;
    
            // 如果 GMT 偏移量不是以 "+"、"-" 或 "U" 开头，则在前面添加 "+"
            if (substr($tz_offset, 0, 1) != "-" && substr($tz_offset, 0, 1) != "+" && substr($tz_offset, 0, 1) != "U") {
                $timezone = "+" . $tz_offset;
            }
        }
    
        // 创建 DateTime 对象，并将时区设置为指定的时区
        $datetime = new DateTime($str, new DateTimeZone($timezone));
    
        // 返回时间戳
        return $datetime->format('U');
    }
}


/**
 * 在数组的指定位置插入另一个数组
 *
 * @param array $array 原始数组
 * @param int $position 插入位置
 * @param array $insert_array 要插入的数组
 * @return void
 */
function array_insert(&$array, $position, $insert_array) {
    $first_array = array_splice($array, 0, $position);
    $array = array_merge($first_array, $insert_array, $array);
}

//获取字符串长度
function islideGetStrLen(string $str) {
    $length = 0;
    $str = preg_replace('/\s+/', '', $str); // 可选：去除空白字符
    $chars = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);
    $asciiCount = 0;

    foreach ($chars as $char) {
        if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $char)) {
            // 中文算 1 单位
            $length += 1;
        } elseif (preg_match('/[\x00-\x7F]/', $char)) {
            // 英文或半角字符累加
            $asciiCount += 1;
            if ($asciiCount === 4) {
                $length += 1;
                $asciiCount = 0;
            }
        } else {
            // 其他字符（比如 emoji、日文）也可按 1 处理
            $length += 1;
        }
    }

    // 剩余不足 4 个英文字符也算 1 单位
    if ($asciiCount > 0) {
        $length += 1;
    }

    return $length;
}



function islide_is_page($path){
$basename = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if($path == $basename){
    return true;
}else{
    return false;
}
}

add_filter('islide_is_page','islide_is_page',10,1);




function islide_page_is($path){
$basename = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if(strpos($_SERVER['REQUEST_URI'], $path)){
    return true;
}else{
    return false;
}
}

add_filter('islide_page_is','islide_page_is',10,1);





add_filter('get_avatar_url', 'custom_get_avatar_url', 10, 3);

function custom_get_avatar_url($url, $id_or_email, $args) {
    // 检查用户是否有自定义头像
    $user = false;

    if (is_numeric($id_or_email)) {
        $user = get_user_by('id', (int)$id_or_email);
    } elseif (is_string($id_or_email) && is_email($id_or_email)) {
        $user = get_user_by('email', $id_or_email);
    } elseif ($id_or_email instanceof WP_User) {
        $user = $id_or_email;
    }

    if ($user) {
        $custom_avatar = get_user_meta($user->ID, 'custom_avatar', true);
        if ($custom_avatar) {
            return $custom_avatar; // 返回自定义头像 URL
        }
    }

    // 如果没有自定义头像，返回默认头像 URL
    return  '/assets/image/default-avatar.png';;
}







function get_total_word_count() {
    global $wpdb;
    // 获取所有已发布的文章内容
    $results = $wpdb->get_results("
        SELECT post_content 
        FROM {$wpdb->posts} 
        WHERE post_status = 'publish' 
        AND post_type = 'post'
    ");
    $total_words = 0;
    // 遍历所有文章并计算字数
    if ($results) {
        foreach ($results as $post) {
            // 使用 wp_strip_all_tags 移除 HTML 标签，计算字数
            $word_count = str_word_count(wp_strip_all_tags($post->post_content));
            $total_words += $word_count;
        }
    }
    return $total_words;
}


function get_total_word_count_cached() {
    // 检查是否已缓存
    $cached_count = get_transient('total_word_count');
    if ($cached_count !== false) {
        return $cached_count;
    }

    // 未缓存时计算总字数
    $total_word_count = get_total_word_count();

    // 将结果缓存 24 小时
    set_transient('total_word_count', $total_word_count, 24 * HOUR_IN_SECONDS);

    return $total_word_count;
}





// 更新圈子权重
function update_all_circle_weights() {
    $terms = get_terms(array(
        'taxonomy'   => 'circle_cat', // 自定义分类法
        'hide_empty' => false,        // 包括所有圈子分类
    ));

    if (!is_wp_error($terms)) {
        foreach ($terms as $term) {
            $circle_id = $term->term_id;
            \islide\Modules\Common\Circle::calculate_circle_weight($circle_id); // 调用权重计算函数
        }
    }
}

// 更新话题权重
function update_all_topic_weights() {
    $terms = get_terms(array(
        'taxonomy'   => 'topic', // 自定义分类法
        'hide_empty' => false,   // 包括所有话题分类
    ));

    if (!is_wp_error($terms)) {
        foreach ($terms as $term) {
            $topic_id = $term->term_id;
            \islide\Modules\Common\Circle::calculate_topic_weight($topic_id); // 调用权重计算函数
        }
    }
}


// 通用的计划任务调度器
function schedule_custom_task($hook_name, $interval_hours) {
    // 验证参数范围
    if ($interval_hours < 1 || $interval_hours > 48) {
        return false; // 参数无效，直接返回
    }

    // 动态添加自定义时间间隔
    add_filter('cron_schedules', function ($schedules) use ($hook_name, $interval_hours) {
        $schedules[$hook_name . '_interval'] = array(
            'interval' => $interval_hours * HOUR_IN_SECONDS, // 转为秒
            'display'  => sprintf(__('Every %d hours for %s', 'text-domain'), $interval_hours, $hook_name),
        );
        return $schedules;
    });

    // 检查是否已有计划任务
    if (!wp_next_scheduled($hook_name)) {
        // 注册自定义计划
        wp_schedule_event(time(), $hook_name . '_interval', $hook_name);
    }

    return true;
}

// 初始化计划任务调度
add_action('init', 'initialize_custom_task_schedules');
function initialize_custom_task_schedules() {
    // 更新圈子权重的任务
    if (islide_get_option('circle_weight_open') == 1) {
        $circle_interval = islide_get_option('circle_cron_interval'); // 用户设置的时间间隔
        schedule_custom_task('custom_circle_weight_update', $circle_interval);
    } else {
        // 如果选项关闭，清除已有计划任务
        if (wp_next_scheduled('custom_circle_weight_update')) {
            wp_clear_scheduled_hook('custom_circle_weight_update');
        }
    }

    // 更新话题权重的任务
    if (islide_get_option('topic_weight_open') == 1) {
        $topic_interval = islide_get_option('circle_cron_interval'); // 用户设置的时间间隔
        schedule_custom_task('custom_topic_weight_update', $topic_interval);
    } else {
        // 如果选项关闭，清除已有计划任务
        if (wp_next_scheduled('custom_topic_weight_update')) {
            wp_clear_scheduled_hook('custom_topic_weight_update');
        }
    }
}

// 注册圈子权重更新的回调函数
add_action('custom_circle_weight_update', 'update_all_circle_weights');

// 注册话题权重更新的回调函数
add_action('custom_topic_weight_update', 'update_all_topic_weights');



add_filter('islide_thumb_arg', function($args) {
    return $args;
}, 10, 1);




// 在文章更新时删除摘要缓存
add_action('save_post', 'delete_deepseek_summary_on_post_update', 10, 3);

function delete_deepseek_summary_on_post_update($post_id, $post, $update) {
    // 检查摘要是否存在并删除
    $cached_summary = get_post_meta($post_id, 'deepseek_summary', true);
    if (!empty($cached_summary)) {
        delete_post_meta($post_id, 'deepseek_summary'); // 注意这里应该是 delete_post_meta 而不是 delete_post_data
    }
}


function islide_get_seo_title($title){
    return $title.' '.islide_get_option('separator').' '.get_bloginfo('name');
}



function islide_get_field_counts($table, $field, $values = [], $t = false) {
    global $wpdb;

    if (empty($values)) return [];

    $table_name = $wpdb->prefix . $table;

    // 构建 IN 查询占位符
    $placeholders = implode(',', array_fill(0, count($values), '%s'));

    // 构建 SQL 查询：SELECT field, COUNT(*) FROM table WHERE field IN (...) GROUP BY field
    $sql = $wpdb->prepare("
        SELECT {$field}, COUNT(*) as count 
        FROM {$table_name}
        WHERE {$field} IN ($placeholders)
        GROUP BY {$field}
    ", ...$values);

    $results = $wpdb->get_results($sql, ARRAY_A);
    $res = array();
    // 初始化所有值的数量为 0
    $counts = array_fill_keys($values, 0);

    foreach ($results as $row) {
        $counts[$row[$field]] = (int)$row['count'];
    }
    $res[$field]=$counts;
    if($t){
        $res['total'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    }
    return $res;
}


/**
 * 从完整 WordPress 附件 URL 提取相对路径，仅限本站上传文件
 *
 * @param string $url 附件完整 URL
 * @return string|false 相对路径（如 /wp-content/uploads/2025/05/xxx.webp），若非本站上传则返回 false
 */
function get_relative_upload_path($url) {
    if (empty($url) || !is_string($url)) {
        return $url;
    }

    // 解析 URL，获取 host 和 path
    $parsed = wp_parse_url($url);
    $current_host = wp_parse_url(home_url(), PHP_URL_HOST);

    // 判断 host 是否一致
    if (!isset($parsed['host']) || $parsed['host'] !== $current_host) {
        return $url; // 非本站域名
    }

    return '/image'.$parsed['path']; // 返回以 /wp-content/uploads 开头的相对路径
}


function convert_image_urls($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = convert_image_urls($value);
        }
    } elseif (is_string($data)) {
        if (strpos($data, 'wp-content/uploads') !== false) {
            $data = get_relative_upload_path($data);
        }
    }
    return $data;
}






