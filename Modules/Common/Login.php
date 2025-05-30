<?php
/**
 * 用户登录注册功能管理类
 * 
 * 负责处理用户登录、注册、验证码、找回密码等相关功能
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;

use \Firebase\JWT\JWT;
use islide\Modules\Common\Sms;
use islide\Modules\Common\Oauth;
use islide\Modules\Common\Invite;
use islide\Modules\Common\IpLocation;

class Login{
    /**
     * 初始化函数，注册必要的钩子
     * 
     * @author  ifyn
     * @return  void
     */
    public function init(){
        // 过滤JWT令牌返回参数
        add_filter('jwt_auth_token_before_dispatch', array($this, 'rebulid_jwt_token'), 10, 2);
        
        // JWT令牌有效期
        add_filter('jwt_auth_expire', array($this, 'jwt_auth_expire'));
        
        // 支持HTML邮件
        add_filter('wp_mail_content_type', array($this, 'mail_content_type'));
        
        // 身份验证cookie有效期
        add_filter('auth_cookie_expiration', function($expiration, $user_id = 0, $remember = true) {
            if($remember) {
                $expiration = (int)islide_get_option('login_time') * DAY_IN_SECONDS;
            }
            return $expiration;
        }, 99, 3);
        
        // 自动设置JWT令牌
        add_action('set_current_user', array($this, 'islide_auto_set_token'));
        
        // 注销时删除JWT令牌cookie
        add_action('wp_logout', function() {
            islide_deletecookie('islide_token');
        });
        
        // 记录用户登录信息
        add_action('islide_user_login', array($this, 'insert_last_login')); 
    }
    
    /**
     * 记录用户最后登录的IP和位置信息
     * 
     * @author  ifyn
     * @param   object $user WP_User对象
     * @return  void
     */
    public function insert_last_login($user){
        if (!$user || !isset($user->data) || !isset($user->data->ID)) {
            return;
        }
        
        $ip = islide_get_user_ip();
        if (empty($ip)) {
            return;
        }
        
        // 获取IP位置信息
        $data = IpLocation::get($ip);
        
        if (isset($data['error'])) {
            return;
        }
        
        $data['date'] = current_time('mysql');
        
        update_user_meta($user->data->ID, 'islide_login_ip_location', $data);  
    }

    /**
     * 设置邮件内容类型为HTML
     * 
     * @author  ifyn
     * @return  string 内容类型
     */
    public function mail_content_type(){
        return "text/html";
    }

    /**
     * 设置JWT令牌有效期
     * 
     * @author  ifyn
     * @param   int $issuedAt 令牌发行时间戳
     * @return  int 过期时间戳
     */
    public function jwt_auth_expire($issuedAt) {
        $login_time = (int)islide_get_option('login_time');
        if ($login_time <= 0) {
            $login_time = 7; // 默认7天
        }
        return $issuedAt + $login_time * DAY_IN_SECONDS;
    }

    /**
     * 重构JWT令牌响应数据
     * 
     * @author  ifyn
     * @param   array   $data JWT令牌数据
     * @param   object  $user WP_User对象
     * @return  array   修改后的令牌数据
     */
    public function rebulid_jwt_token($data, $user){
        // 检查是否允许登录
        if (!islide_get_option('allow_login')) {
            wp_die(__('登录已关闭', 'islide'), '', array('response' => 400));
        }

        // 检查用户对象有效性
        if (!$user || !isset($user->data) || !isset($user->data->ID)) {
            wp_die(__('用户数据无效', 'islide'), '', array('response' => 400));
        }

        // 设置令牌过期时间
        $expiration = (int)islide_get_option('login_time') * DAY_IN_SECONDS;
        
        // 设置JWT令牌cookie
        islide_setcookie('islide_token', $data['token'], $expiration);

        // PC端设置WordPress原生cookie
        $allow_cookie = apply_filters('islide_login_cookie', islide_get_option('allow_cookie'));
        if ((string)$allow_cookie === '1') {
            wp_set_auth_cookie($user->data->ID, true);
        }

        // 构建返回数据
        $_data = array('token' => $data['token']);
        
        // 触发登录相关钩子
        do_action('islide_user_login', $user);
        do_action('wp_login', $user->user_login, $user);
        
        return $_data;
    }
    
    /**
     * 用户退出登录
     * 
     * @author  ifyn
     * @return  boolean 是否成功退出
     */
    public static function login_out(){
        $user_id = get_current_user_id();
        
        // 获取cookie设置
        $allow_cookie = apply_filters('islide_login_cookie', islide_get_option('allow_cookie'));
        
        // 开启cookie兼容模式时调用WP退出函数
        if ((string)$allow_cookie === '1') {
            wp_logout();
        }
        
        // 删除JWT令牌cookie
        islide_deletecookie('islide_token');

        // 触发自定义退出登录钩子
        do_action('islide_login_out', $allow_cookie);

        return true;
    }
    
    /**
     * 接收用户ID进行登录
     * 
     * @author  ifyn
     * @param   int     $user_id 用户ID
     * @return  array   登录结果
     */
    public static function login_user($user_id) {
        if (empty($user_id) || !is_numeric($user_id)) {
            return array('error' => '无效的用户ID');
        }
        
        // 应用登录过滤器
        $token = apply_filters('islide_login_user', $user_id);
        
        if (!isset($token['error'])) {
            do_action('islide_user_social_login', $user_id);
        }
        
        return $token;
    }
    
    /**
     * 重设用户密码
     *
     * @author  ifyn
     * @param   array   $request 请求参数
     *                  - username: 用户名/邮箱/手机号
     *                  - password: 新密码
     *                  - confirmPassword: 确认密码
     *                  - code: 验证码
     * @return  array|boolean 重设结果，成功返回true，失败返回错误信息
     */
    public static function rest_password($request){
        // 参数验证
        if (empty($request['password'])) {
            return array('error' => '请输入密码');
        }

        if (empty($request['confirmPassword'])) {
            return array('error' => '请输入确认密码');
        }

        if ($request['password'] !== $request['confirmPassword']) {
            return array('error' => '两次密码不同，请重新输入');
        }

        if (strlen($request['password']) < 6) {
            return array('error' => '密码必须大于6位');
        }

        // 检查验证码
        $res = self::code_check($request);
        if (isset($res['error'])) {
            return $res;
        }
        
        $user_id = 0;
        
        // 查找用户
        if (is_email($request['username']) && email_exists($request['username'])) {
            $user = get_user_by('email', $request['username']);
            $user_id = $user->ID;
        } elseif (self::is_phone($request['username']) && username_exists($request['username'])) {
            $user = get_user_by('login', $request['username']);
            $user_id = $user->ID;
        }
        
        if ($user_id) {
            wp_set_password($request['confirmPassword'], $user_id);
            return true;
        }
        
        return array('error' => '绑定的账号不存在');
    }
    
    /**
     * 用户注册验证
     *
     * @author  ifyn
     * @param   array   $request 注册信息
     *                  - username: 用户名/邮箱/手机号
     *                  - password: 密码
     *                  - nickname: 昵称
     *                  - invite_code: 邀请码(可选)
     *                  - captcha: 滑块验证数据(可选)
     *                  - code: 验证码(可选)
     * @return  array|string 注册结果，成功返回成功信息，失败返回错误信息
     */
    public static function regeister($request){
        // 检查是否允许注册
        if (!islide_get_option('allow_register')) {
            return array('error' => __('注册已关闭', 'islide'));
        }
        
        // 参数验证
        if (empty($request['username']) || empty($request['password']) || empty($request['nickname'])) {
            return array('error' => __('请填写完整注册信息', 'islide'));
        }
        
        // 检查邀请码
        $invite = null;
        if (isset($request['invite_code']) && !empty($request['invite_code'])) {
            $invite = Invite::checkInviteCode($request['invite_code']);
            if (isset($invite['error'])) {
                return $invite;
            }
        }
        
        // 检查昵称
        $nickname = self::check_nickname($request['nickname']);
        if (isset($nickname['error'])) {
            return $nickname;
        }
        
        // 检查用户名
        $username = self::check_username($request['username']);
        if (isset($username['error'])) {
            return $username;
        }
        
        // 检查密码
        if (strlen($request['password']) < 6) {
            return array('error' => __('密码必须大于6位', 'islide'));
        }
        
        // 执行人机验证
        $slider_captcha = islide_get_option('allow_slider_captcha');
        if (!!$slider_captcha && isset($request['captcha'])) {
            $check_captcha = self::check_slider_captcha($request['captcha']);
            if (isset($check_captcha['error'])) {
                return $check_captcha;
            }
        }
        
        // 检查是否需要验证码
        $register_check = islide_get_option('allow_register_check');
        $check_type = '';
        
        if ($register_check) {
            // 验证方式
            $check_type = islide_get_option('register_check_type');
            
            $check_type = self::check_str_type($request['username'], $check_type);
            
            if (isset($check_type['error'])) {
                return $check_type;
            }
            
            // 检查验证码
            if (isset($request['code'])) {
                $res = self::code_check($request);
                if (isset($res['error'])) {
                    return $res;
                }
            } else {
                return array('error' => __('请输入验证码', 'islide'));
            }
        }
        
        return self::regeister_action($request, $check_type, $invite);
    }
    
    /**
     * 执行用户注册
     *
     * @author  ifyn
     * @param   array   $data 注册数据
     * @param   string  $check_type 验证类型
     * @param   array   $invite 邀请码信息
     * @return  array|string 注册结果
     */
    public static function regeister_action($data, $check_type, $invite){
        // 创建用户
        $user_id = 0;
        if (is_email($data['username'])) {
            // 使用随机用户名创建用户
            $user_id = wp_create_user(md5($data['username']) . rand(1, 9999), $data['password']);
        } else {
            // 直接使用输入的用户名创建用户
            $user_id = wp_create_user($data['username'], $data['password']);
        }

        // 检查用户创建是否成功
        if (is_wp_error($user_id)) {
            return array('error' => $user_id->get_error_message());
        }
        
        // 处理推荐人关系
        if (isset($data['ref']) && !empty($data['ref'])) {
            $lv1 = (int)$data['ref'];
            if ($lv1 > 0 && $lv1 !== $user_id) {
                update_user_meta($user_id, 'islide_referrer_id', $lv1);
    
                // 获取二级三级推荐人
                $lv2 = (int)get_user_meta($lv1, 'islide_referrer_id', true);
                $lv3 = (int)get_user_meta($lv2, 'islide_referrer_id', true);
                if ($lv2 && $lv2 !== $user_id) {
                    update_user_meta($user_id, 'islide_lv2_referrer_id', $lv2);
                }
                if ($lv3 && $lv3 !== $user_id) {
                    update_user_meta($user_id, 'islide_lv3_referrer_id', $lv3);
                }
            }
        }

        // 如果是邮箱注册，更换用户的登录名
        $rand = rand(100, 999);
        $email = '';
        if (is_email($data['username'])) {
            $email = $data['username'];
            
            global $wpdb;
            $wpdb->update(
                $wpdb->users, 
                array('user_login' => 'user' . $user_id . '_' . $rand), 
                array('ID' => (int)$user_id)
            );
            $data['username'] = 'user' . $user_id . '_' . $rand;
        }

        // 删除用户默认昵称
        delete_user_meta($user_id, 'nickname');

        // 更新昵称和邮箱
        $domain = get_option('wp_site_domain');
        $arr = array(
            'display_name' => $data['nickname'],
            'ID' => $user_id,
            'user_email' => is_email($email) ? $email : $data['username'] . '@' . $domain
        );
        wp_update_user($arr);

        // 获取登录令牌
        $token = '';
        if (class_exists('Jwt_Auth_Public')) {
            $request = new \WP_REST_Request('POST', '/wp-json/jwt-auth/v1/token');
            $request->set_query_params(array(
                'username' => $data['username'],
                'password' => $data['password']
            ));
            
            $JWT = new \Jwt_Auth_Public('jwt-auth', '1.1.0');
            $token = $JWT->generate_token($request);
            
            if (is_wp_error($token)) {
                return array('error' => __('注册成功，登录失败，请重新登录', 'islide'));
            }
        }

        // 处理邀请码
        if ($invite) {
            Invite::useInviteCode($user_id, $invite['card_code']);
        }

        // 触发注册完成钩子
        do_action('islide_user_regeister', $user_id);

        if ($token) {
            return '注册成功，欢迎您' . $arr['display_name'];
        } else {
            return array('error' => __('登录失败', 'islide'));
        }
    }
    
    /**
     * 发送验证码
     *
     * @author  ifyn
     * @param   array   $request 请求参数
     *                  - username: 用户名/邮箱/手机号
     *                  - type: 验证类型(可选，默认为注册验证)
     * @return  array|string 发送结果
     */
    public static function send_code($request){
        // 参数验证
        if (empty($request['username'])) {
            return array('error' => '请输入用户名/邮箱/手机号');
        }
        
        // 检查是否允许验证
        $register_check = islide_get_option('allow_register_check');
        if (!$register_check) {
            return array('error' => '验证方式未开启');
        }
        
        // 获取验证类型
        $check_type = islide_get_option('register_check_type');
        $type = self::check_str_type($request['username'], $check_type);
        
        if (isset($type['error'])) {
            return $type;
        }
        
        // 找回密码验证
        if (isset($request['type']) && $request['type'] == 'forgot') {
            if (!email_exists($request['username']) && !username_exists($request['username'])) {
                return array('error' => '不存在此邮箱或手机号码，请重新输入');
            }
        } else {
            // 检查用户名
            $username = self::check_username($request['username']);
            if (isset($username['error'])) {
                return $username;
            }
        }
        
        // 发送验证码
        if (is_email($request['username'])) {
            return self::send_email_code(rand(100000, 999999), $request['username']);
        }

        if (self::is_phone($request['username'])) {
            return self::send_sms_code(rand(100000, 999999), $request['username']);
        }

        return array('error' => '不支持的验证方式');
    }
    
    /**
     * 发送邮箱验证码
     *
     * @author  ifyn
     * @param   int     $code 验证码
     * @param   string  $email 邮箱地址
     * @return  array|string 发送结果
     */
    public static function send_email_code($code, $email){
        if (empty($email) || !is_email($email)) {
            return array('error' => '邮箱地址无效');
        }
        
        if (empty($code) || !is_numeric($code)) {
            $code = rand(100000, 999999);
        }
        
        // 开启会话
        if (!session_id()) {
            @session_start();
        }
        
        // 保存验证码到会话
        $_SESSION['islide_captcha_code'] = $code;
        $_SESSION['islide_verification'] = $email;
        
        // 检查发送频率
        if (!empty($_SESSION['islide_captcha_time'])) {
            $time_x = wp_strtotime(current_time('mysql')) - wp_strtotime($_SESSION['islide_captcha_time']);
            if ($time_x < 60) {
                return array('error' => (60 - $time_x) . '秒后可重新发送');
            }
        }
    
        // 更新验证码发送时间
        $_SESSION['islide_captcha_time'] = current_time('mysql');
        
        $blog_name = get_bloginfo('name');
        
        // 邮件标题和内容
        $title = '[' . $blog_name . ']' . '收到验证码';
        $message = '<div style="width:700px;background-color:#fff;margin:0 auto;border: 1px solid #ccc;">
            <div style="height:64px;margin:0;padding:0;width:100%;">
                <a href="' . IS_HOME_URI . '" style="display:block;padding: 12px 30px;text-decoration: none;font-size: 24px;letter-spacing: 3px;border-bottom: 1px solid #ccc;" rel="noopener" target="_blank">
                    ' . $blog_name . '
                </a>
            </div>
            <div style="padding: 30px;margin:0;">
                <p style="font-size:14px;color:#333;">
                    ' . __('您的邮箱为：', 'islide') . '<span style="font-size:14px;color:#333;"><a href="' . $email . '" rel="noopener" target="_blank">' . $email . '</a></span>' . __('，验证码为：', 'islide') . '
                </p>
                <p style="font-size:34px;color: green;">' . $code . '</p>
                <p style="font-size:14px;color:#333;">' . __('验证码的有效期为5分钟，请在有效期内输入！', 'islide') . '</p>
                <p style="font-size:14px;color: #999;">— ' . $blog_name . '</p>
                <p style="font-size:12px;color:#999;border-top:1px dotted #E3E3E3;margin-top:30px;padding-top:30px;">
                    ' . __('本邮件为系统邮件不能回复，请勿回复。', 'islide') . '
                </p>
            </div>
        </div>';
        
        // 发送邮件
        $send = wp_mail($email, $title, $message, array('Content-Type: text/html; charset=UTF-8'));
        
        if (!$send) {
            return array('error' => '验证码发送失败');
        } else {
            return '验证码已发送至您的邮箱，注意查收';
        }
    }
    
    /**
     * 发送短信验证码
     *
     * @author  ifyn
     * @param   int     $code 验证码
     * @param   string  $phone 手机号码
     * @return  array|string 发送结果
     */
    public static function send_sms_code($code, $phone){
        if (empty($phone) || !self::is_phone($phone)) {
            return array('error' => '手机号码无效');
        }
        
        if (empty($code) || !is_numeric($code)) {
            $code = rand(100000, 999999);
        }
        
        // 开启会话
        if (!session_id()) {
            @session_start();
        }
        
        // 保存验证码到会话
        $_SESSION['islide_captcha_code'] = $code;
        $_SESSION['islide_verification'] = $phone;
        
        // 检查发送频率
        if (!empty($_SESSION['islide_captcha_time'])) {
            $time_x = wp_strtotime(current_time('mysql')) - wp_strtotime($_SESSION['islide_captcha_time']);
            if ($time_x < 60) {
                return array('error' => (60 - $time_x) . '秒后可重新发送');
            }
        }
    
        // 更新验证码发送时间
        $_SESSION['islide_captcha_time'] = current_time('mysql');
        
        // 调用短信服务发送验证码
        return Sms::send($phone, $code);
    }
    
    /**
     * 验证码验证
     *
     * @author  ifyn
     * @param   array   $request 请求参数
     *                  - username: 用户名/邮箱/手机号
     *                  - code: 验证码
     * @return  array|string 验证结果
     */
    public static function code_check($request){
        // 开启会话
        if (!session_id()) {
            @session_start();
        }

        // 参数验证
        if (!isset($request['username']) || !isset($request['code']) || 
            empty($request['username']) || empty($request['code'])) {
            return array('error' => __('请输入验证码', 'islide'));
        }

        // 验证码检查
        if (empty($_SESSION['islide_captcha_code']) || 
            $_SESSION['islide_captcha_code'] != $request['code'] || 
            empty($_SESSION['islide_verification']) || 
            $_SESSION['islide_verification'] != $request['username']) {
            
            return array('error' => '验证码错误');
        } else {
            // 验证码有效期检查
            if (!empty($_SESSION['islide_captcha_time'])) {
                $time_x = wp_strtotime(current_time('mysql')) - wp_strtotime($_SESSION['islide_captcha_time']);
                // 30分钟有效
                if ($time_x > 1800) {
                    // 关闭会话
                    session_destroy();
                    return array('error' => '验证码已过期');
                }
            }
            
            // 关闭会话
            session_destroy();
            return '验证码效验成功';
        }
    }
    
    /**
     * 检查输入字符串类型(邮箱/手机号)
     *
     * @author  ifyn
     * @param   string  $str 输入字符串
     * @param   string  $type 期望的类型(email/tel/telandemail)
     * @return  string|array 字符串类型或错误信息
     */
    public static function check_str_type($str, $type = 'email'){
        if (empty($str)) {
            return array('error' => __('请输入邮箱或手机号'));
        }
        
        if ($type == 'email') {
            if (!is_email($str)) {
                return array('error' => __('邮箱格式错误'));
            }
            return 'email';
        } else if ($type == 'tel') {
            if (!self::is_phone($str)) {
                return array('error' => __('手机号码格式有误'));
            }
            return 'tel';
        } else {
            if (is_email($str)) {
                return 'email';
            } else if (self::is_phone($str)) {
                return 'tel';
            } else {
                return array('error' => __('手机号或邮箱格式错误'));
            }
        }
    }
    
    /**
     * 验证滑块验证码
     *
     * @author  ifyn
     * @param   array   $captcha 滑块验证数据
     * @return  array|boolean 验证结果
     */
    public static function check_slider_captcha($captcha) {
        if (!is_array($captcha) || empty($captcha)) {
            return array('error' => '请完成滑块验证');
        }
        
        // 计算标准差来判断是否为机器操作
        $sum = array_sum($captcha);
        $count = count($captcha);
        
        if ($count === 0) {
            return array('error' => '验证数据无效');
        }
        
        $avg = $sum / $count;
        $sum_squares = 0;
        
        foreach ($captcha as $val) {
            $sum_squares += pow($val - $avg, 2);
        }
        
        $stddev = sqrt($sum_squares / $count);
    
        if ($stddev == 0) {
            return array('error' => '图形验证码错误');
        }
        
        return true;
    }
    
    /**
     * 验证用户昵称
     *
     * @author  ifyn
     * @param   string  $nickname 用户昵称
     * @return  string|array 处理后的昵称或错误信息
     */
    public static function check_nickname($nickname){
        // 去除所有空白字符
        $nickname = trim($nickname, " \t\n\r\0\x0B\xC2\xA0");

        if (empty($nickname)) {
            return array('error' => __('请输入昵称', 'islide'));
        }

        // 清理和安全处理
        $nickname = sanitize_text_field($nickname);
        
        // 检查昵称是否有特殊字符
        if (!preg_match("/^[\x{4e00}-\x{9fa5}A-Za-z0-9]+$/u", $nickname)) {
            return array('error' => __('昵称中包含特殊字符，请重新填写', 'islide'));
        }

        $nickname = str_replace(array('{{','}}'),'',wp_strip_all_tags($nickname));

        if(self::strLength($nickname) > 8) return array('error'=>__('昵称太长了！最多8个字符','islide'));

        //检查昵称是否重复
        global $wpdb;
        $table_name = $wpdb->prefix . 'users';
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE display_name = %s", 
            $nickname
        ));

        if($result && (int)$result->ID !== (int)get_current_user_id()){
            unset($result);
            return array('error'=>__('昵称已存在，请换一个试试','islide'));
        }
        unset($result);
        return $nickname;
    }
    
    /**
     * 检查登录用户名
     *
     * @author  ifyn
     * @param   string  $username 用户输入的登录名
     * @return  string|array 处理后的用户名或错误信息
     */
    public static function check_username($username){
        if (empty($username)) {
            return array('error' => __('请输入邮箱或手机号码', 'islide'));
        }
        
        // 获取注册验证设置
        $register_check = islide_get_option('allow_register_check');
        
        if ($register_check) {
            // 获取验证方式
            $check_type = islide_get_option('register_check_type');
    
            // 验证用户名格式
            switch ($check_type) {
                case 'email':
                    if (!is_email($username)) {
                        return array('error' => __('您输入的不是邮箱', 'islide'));
                    }
                    break;
                case 'tel':
                    if (!self::is_phone($username)) {
                        return array('error' => __('您输入的不是手机号码', 'islide'));
                    }
                    break;
                case 'telandemail':
                    if (!is_email($username) && !self::is_phone($username)) {
                        return array('error' => __('您输入的不是邮箱或手机号码', 'islide'));
                    }
                    break;
            }
            
            // 检查是否已被注册
            if (is_email($username) && email_exists($username)) {
                return array('error' => __('该邮箱已被注册', 'islide'));
            }
            
            if (self::is_phone($username) && username_exists($username)) {
                return array('error' => __('该手机号码已被注册', 'islide'));
            }
        } else {
            // 无验证的普通用户名格式检查
            if (!preg_match("/^[a-z\d]*$/i", $username) && !is_email($username)) {
                return array('error' => __('用户名只能使用字母和（或）数字', 'islide'));
            }
            
            // 检查用户名是否已存在
            if (username_exists($username)) {
                return array('error' => __('该用户名已被注册', 'islide'));
            }
        }

        // 过滤危险字符
        return str_replace(array('{{', '}}'), '', $username);
    }
    
    /**
     * 验证手机号码格式
     *
     * @author  ifyn
     * @param   string  $mobile 手机号码
     * @return  boolean 是否为有效的手机号码
     */
    public static function is_phone($mobile) {
        if (empty($mobile) || !is_string($mobile)) {
            return false;
        }
        return preg_match("/^1[3456789]{1}\d{9}$/", $mobile) ? true : false;
    }
    
    /**
     * 计算字符串长度(中英文混合)
     *
     * @author  ifyn
     * @param   string  $str 要计算的字符串
     * @param   string  $charset 字符编码，默认为utf-8
     * @return  int     字符串长度，一个中文字符算1个长度
     */
    public static function strLength($str, $charset = 'utf-8') {
        if (empty($str)) {
            return 0;
        }
        
        // 尝试转换编码
        $converted = false;
        try {
            if ($charset == 'utf-8') {
                $str = iconv('utf-8', 'gb2312', $str);
                $converted = true;
            }
        } catch (\Exception $e) {
            // 编码转换失败，使用默认方法
            $converted = false;
        }
        
        if (!$converted) {
            // 如果编码转换失败，使用 mb_strlen 计算
            if (function_exists('mb_strlen')) {
                return mb_strlen($str, 'UTF-8');
            }
            return strlen($str);
        }
        
        // 使用编码转换后的方法计算长度
        $num = strlen($str);
        $cnNum = 0;
        for ($i = 0; $i < $num; $i++) {
            if (ord(substr($str, $i + 1, 1)) > 127) {
                $cnNum++;
                $i++;
            }
        }
        
        $enNum = $num - ($cnNum * 2);
        $number = ($enNum / 2) + $cnNum;
        
        return ceil($number);
    }
    
    /**
     * 自动设置JWT令牌
     * 
     * 当用户登录状态有效但没有JWT令牌时自动生成
     *
     * @author  ifyn
     * @return  array|void 成功返回void，失败返回错误信息
     */
    public static function islide_auto_set_token(){
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }
        
        // 检查是否已有令牌
        $token = islide_getcookie('islide_token');
        if (!empty($token)) {
            return;
        }
        
        // 生成新令牌
        if (class_exists('Jwt_Auth_Public')) {
            $user_data = get_user_by('id', $user_id);
            
            if (!isset($user_data->user_login)) {
                return;
            }
    
            // 创建令牌请求
            $request = new \WP_REST_Request('POST', '/wp-json/jwt-auth/v1/token');
            $request->set_query_params(array(
                'username' => $user_data->user_login,
                'password' => $user_data->user_pass
            ));
    
            // 生成令牌
            $JWT = new \Jwt_Auth_Public('jwt-auth', '1.1.0');
            $token = $JWT->generate_token($request);
            
            if (is_wp_error($token)) {
                return;
            }
    
            if (!isset($token['token'])) {
                return;
            }
    
            // 设置令牌cookie
            $login_time = (int)islide_get_option('login_time');
            if ($login_time <= 0) {
                $login_time = 7; // 默认7天
            }
            islide_setcookie('islide_token', $token['token'], $login_time * DAY_IN_SECONDS);
        } else {
            return array('error' => __('请安装 JWT Authentication for WP-API 插件', 'islide'));
        }
    }
    
    /**
     * 检查是否需要强制绑定用户信息
     *
     * @author  ifyn
     * @param   int     $user_id 当前用户ID，默认为当前登录用户
     * @return  string|boolean 需要绑定的类型(tel/email/telandemail)或false(不需要绑定)
     */
    public static function check_force_binding($user_id = 0){
        $user_id = $user_id ? $user_id : get_current_user_id();
        
        if (empty($user_id)) {
            return false;
        }
        
        // 检查是否开启强制绑定
        $bind = islide_get_option('force_binding');
        if (empty($bind) || !$bind) {
            return false;
        }
        
        // 检查是否开启注册验证
        $register_check = islide_get_option('allow_register_check');
        if (!$register_check) {
            return false;
        }
        
        // 获取验证方式
        $check_type = islide_get_option('register_check_type');
        
        // 管理员不强制绑定
        if (user_can($user_id, 'administrator')) {
            return false;
        }
        
        // 获取用户数据
        $user_data = get_userdata($user_id);
        if (!$user_data || !isset($user_data->data)) {
            return false;
        }

        // 检查是否绑定手机号码
        if ($check_type === 'tel') {
            // 检查登录用户名是否手机号，如果是则已经绑定
            if (!self::is_phone($user_data->data->user_login)) {
                return 'tel';
            }
        }

        // 检查是否绑定了邮箱
        if ($check_type === 'email') {
            $domain = get_option('wp_site_domain');
            if (empty($user_data->data->user_email) || strpos($user_data->data->user_email, $domain) !== false) {
                return 'email';
            }
        }
        
        // 检查是否同时绑定手机号和邮箱
        if ($check_type === 'telandemail') {
            $domain = get_option('wp_site_domain');
            if ((empty($user_data->data->user_email) || strpos($user_data->data->user_email, $domain) !== false) && 
                !self::is_phone($user_data->data->user_login)) {
                return 'telandemail';
            }
        }
        
        return false;
    }
    
    /**
     * 获取登录设置
     *
     * @author  ifyn
     * @return  array 登录设置参数
     */
    public static function get_login_settings(){
        // 获取是否允许注册设置
        $allow_regeister = islide_get_option('allow_register');
         
        // 获取注册验证设置
        $register_check = islide_get_option('allow_register_check');
        $check_type = $register_check ? islide_get_option('register_check_type') : false;
        
        // 获取滑块验证设置
        $slider_captcha = !!islide_get_option('allow_slider_captcha');
        
        // 根据验证类型设置登录文本
        $login_text = '手机号或邮箱';
        switch ($check_type) {
            case 'tel':
                $login_text = '手机号';
                break;
            case 'email':
                $login_text = '邮箱';
                break;
            case 'telandemail':
                $login_text = '手机号或邮箱';
                break;
            default:
                $login_text = '用户名';
                break;
        }
        
        // 获取邀请码设置
        $invite_type = islide_get_option('invite_code_type');
        $invite_url = islide_get_option('invite_code_url');
        
        // 获取用户协议设置
        $agreement = islide_get_option('agreement');
        $agreement = $agreement ? $agreement : array();
        
        // 获取可用的第三方登录
        $oauths = Oauth::get_enabled_oauths();
        
        // 返回整合的设置
        return array(
            'check_type' => $check_type,      // 注册验证类型
            'login_text' => $login_text,      // 登录框提示文本
            'allow_register' => $allow_regeister, // 是否允许注册
            'allow_slider_captcha' => $slider_captcha, // 是否启用滑块验证
            'invite_type' => $invite_type,    // 邀请码类型
            'invite_url' => $invite_url,      // 邀请码获取地址
            'oauths' => $oauths,             // 第三方登录设置
            'agreement' => $agreement        // 用户协议设置
        );
    }
    
    
}