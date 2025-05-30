<?php
/**
 * 社交平台登录管理类
 * 
 * 处理各种第三方社交平台的OAuth授权登录和账号绑定功能
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;
use \Firebase\JWT\JWT;
use islide\Modules\Common\Login;
use islide\Modules\Common\Invite;

//社交平台登录
class Oauth{
    /**
     * 初始化OAuth登录流程
     *
     * @author  ifyn
     * @param   string $type 登录类型，如"qq"、"weixin"、"weibo"、"juhe_*"等
     * @param   string $code 授权回调返回的授权码
     * @return  array        登录结果或错误信息
     */
    public static function init($type,$code){
        // 参数验证
        if (empty($type) || empty($code)) {
            return array('error' => '参数不完整');
        }
        
        //聚合登录
        if(strpos($type,'juhe_') !== false){
            //$type = str_replace('juhe_', '', $type);
            return self::callback_juhe($type,$code);
        }

        if(strpos($type,'wx_') !== false){
            $type = 'weixin';
        }
        
        $_type = 'callback_'.$type;

        if(!method_exists(__CLASS__,$_type)) return array('error'=>'不支持的登录类型');
        
        return self::$_type($type,$code);
    }
    
    /**
     * QQ登录回调处理
     *
     * @author  ifyn
     * @param   string $type 登录类型
     * @param   string $code 授权回调返回的授权码
     * @return  array        登录结果或错误信息
     */
    public static function callback_qq($type,$code){
        
        $open = islide_get_option('oauth_qq_open');
        
        $qq = islide_get_option('oauth_qq');
        
        if (!$open) {
            return array('error' => 'QQ登录未开启');
        }
        
        if (empty($qq) || empty($qq['app_id']) || empty($qq['app_secret'])) {
            return array('error' => 'QQ登录配置不完整');
        }
        
        $args = array(
            'url' => 'https://graph.qq.com/oauth2.0/token',
            'client_id' =>  trim($qq['app_id']),
            'client_secret' => trim($qq['app_secret'])
        );
        
        return self::get_token($args,$type,$code);
    }
    
    /**
     * 微博登录回调处理
     *
     * @author  ifyn
     * @param   string $type 登录类型
     * @param   string $code 授权回调返回的授权码
     * @return  array        登录结果或错误信息
     */
    public static function callback_weibo($type,$code){
        
        $weibo = islide_get_option('oauth_weibo');
        
        if (empty($weibo) || empty($weibo['app_id']) || empty($weibo['app_secret'])) {
            return array('error' => '微博登录配置不完整');
        }
        
        $args = array(
            'url' => 'https://api.weibo.com/oauth2/access_token',
            'client_id' =>  trim($weibo['app_id']),
            'client_secret' => trim($weibo['app_secret'])
        );
        
        return self::get_token($args,$type,$code);
    }
    
    /**
     * 聚合登录回调处理
     *
     * @author  ifyn
     * @param   string $type 登录类型
     * @param   string $code 授权回调返回的授权码
     * @return  array        登录结果或错误信息
     */
    public static function callback_juhe($type,$code){
        $oauths = self::get_enabled_oauths($type);
        
        if(!isset($oauths[$type])) return array('error'=>'不支持的登录类型');
        $juhe = islide_get_option('oauth_juhe');
        
        if (empty($juhe) || empty($juhe['gateway']) || empty($juhe['app_id']) || empty($juhe['app_key'])) {
            return array('error' => '聚合登录配置不完整');
        }
        
        $gateway = rtrim(trim($juhe['gateway'], " \t\n\r\0\x0B\xC2\xA0"),'/');
                
        //构造请求参数
        $params = array(
            'act' => 'callback',
            'appid' => trim($juhe['app_id'], " \t\n\r\0\x0B\xC2\xA0"),
            'appkey' => trim($juhe['app_key'], " \t\n\r\0\x0B\xC2\xA0"),
            'type' => str_replace('juhe_', '', $type),
            'code' => $code
        );
        
        $api = $gateway.'/connect.php?'.http_build_query($params);
        $res = wp_remote_post($api);
        
        if(is_wp_error($res)){
            return array('error'=>'网络错误：' . $res->get_error_message());
        }
        
        if($res['response']['code'] == 200){
            $data = json_decode($res['body'],true);

            if(isset($data['code']) && (int)$data['code'] === 0){

                return self::social_login(array(
                    'access_token'=>$data['access_token'],
                    'openid'=>$data['social_uid'],
                    'type'=>$type,
                    'user_info'=>array(
                        'type'=>$type,
                        'nickname' => isset($data['nickname']) ? $data['nickname'] : '',
                        'avatar' => isset($data['faceimg']) ? $data['faceimg'] : '',
                        'sex' => isset($data['gender']) && $data['gender'] == '男' ? 1 : 0
                    )
                ));
                
            }else {
                return array('error'=>isset($data['msg']) ? $data['msg'] : '登录失败');
            }
        }
        
        return array('error'=>'网络错误，请稍后再试');
    }
    
    /**
     * 生成JWT令牌
     *
     * @author  ifyn
     * @param   array  $data 要编码到令牌中的数据
     * @return  string       JWT令牌字符串
     */
    public static function generate_token($data) {
        if (empty($data) || !is_array($data)) {
            return '';
        }
        
        $issuedAt = time();
        $expire = $issuedAt + 600; // 10分钟时效
        $token = array(
            'iss' => IS_HOME_URI,
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $expire,
            'data' => $data
        );
        return JWT::encode($token, AUTH_KEY);
    }
    
    /**
     * 处理社交账号登录
     *
     * @author  ifyn
     * @param   array $data 社交账号信息和令牌数据
     * @return  array       登录结果或错误信息
     */
    public static function social_login($data) {
        if (empty($data) || !is_array($data)) {
            return array('error' => '数据不完整');
        }
        
        return apply_filters('islide_social_login', $data);
    }
        
    /**
     * 社交登录强制绑定后处理
     *
     * @author  ifyn
     * @param   array $data 绑定数据
     * @return  array       处理结果
     */
    public static function binding_login($data) {
        if (empty($data) || !is_array($data)) {
            return array('error' => '数据不完整');
        }
        
        return apply_filters('islide_binding_login', $data);
    }
    
    /**
     * 创建新用户
     *
     * @author  ifyn
     * @param   array       $data      社交账号数据
     * @param   array|false $invite    邀请码数据
     * @param   array|false $user_data 用户附加数据
     * @return  array                 用户登录数据或错误信息
     */
    public static function create_user($data,$invite = false,$user_data = false){
        
        if (empty($data) || !isset($data['openid'])) {
            return array('error' => '数据不完整');
        }
        
        $password = $user_data && !empty($user_data['password']) ? $user_data['password'] : wp_generate_password();
        $username = $user_data && !empty($user_data['teloremail']) ? $user_data['teloremail'] : (isset($data['unionid']) && !empty($data['unionid']) ? $data['unionid'] : $data['openid']);

        if(is_email($username)){
            $user_id = wp_create_user(md5($username).rand(1,9999), $password);
        }else{
            $user_id = wp_create_user($username, $password);
        }

        if(is_wp_error($user_id)) {
            return array('error'=>$user_id->get_error_message());
        }
        
        //如果是非手机号注册，更换一下用户的登录名
        $rand = rand(100,999);

        if(!Login::is_phone($username)){
            //更换一下用户名
            global $wpdb;
            $wpdb->update($wpdb->users, array('user_login' => 'user'.$user_id.'_'.$rand), array('ID' => $user_id));

        }
        
        $email = is_email($username) ? $username : 'user'.$user_id.'_'.$rand.'@'.get_option('wp_site_domain');
        
        //删除用户默认昵称
        delete_user_meta($user_id,'nickname');

        //昵称过滤掉特殊字符
        $nickname = isset($data['nickname']) ? preg_replace('/\ |\/|\~|\!|\@|\#|\\$|\%|\^|\&|\*|\(|\)|\_|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\.|\/|\;|\'|\`|\-|\=|\\\|\|/','',$data['nickname']) : '';

        //检查昵称是否重复
        global $wpdb;
        $table_name = $wpdb->prefix . 'users';
        $display_name = '';
        
        if (!empty($nickname)) {
            $result = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE display_name = %s", 
                $nickname
            ));

            // 设置显示名称
            $display_name = $result ? $nickname . $user_id : $nickname;
        } else {
            $display_name = 'user' . $user_id;
        }
        
        wp_update_user(array(
            'display_name' => $display_name,
            'ID' => $user_id,
            'user_email' => $email
        ));

        if($invite){
            //使用邀请码
            Invite::useInviteCode($user_id,$invite['card_code']);
        }

        //绑定用户数据
        self::binding_user($user_id,$data);
        
        do_action('islide_user_regeister',$user_id);

        //返回用户数据
        return Login::login_user($user_id);
    }
    
    /**
     * 绑定社交账号到用户
     *
     * @author  ifyn
     * @param   int   $user_id 用户ID
     * @param   array $data    社交账号数据
     * @return  bool           绑定结果
     */
    public static function binding_user($user_id, $data) {
        // 验证参数
        if (empty($user_id) || empty($data) || !is_array($data)) {
            return false;
        }
        
        // 统一数据格式
        if (isset($data['avatarUrl'])) {
            $data['avatar'] = $data['avatarUrl'];
        }

        if (isset($data['nickName'])) {
            $data['nickname'] = $data['nickName'];
        }

        // 获取并处理现有OAuth数据
        $oauth_data = get_user_meta($user_id, 'islide_oauth', true);
        $oauth_data = is_array($oauth_data) ? $oauth_data : array();
        
        // 更新OAuth数据
        $oauth_data[$data['type']]['avatar_type'] = $data['type'];
        $oauth_data[$data['type']]['nickname'] = isset($data['nickname']) ? $data['nickname'] : '';
        $oauth_data[$data['type']]['avatar'] = isset($data['avatar']) ? $data['avatar'] : '';

        // 存入头像
        if (!empty($data['avatar'])) {
            update_user_meta($user_id, 'islide_oauth_avatar', $data['avatar']);
        }
        
        // 存入OAuth关联信息
        update_user_meta($user_id, 'islide_oauth', $oauth_data);

        // 存入unionid和openid
        if (isset($data['unionid']) && !empty($data['unionid'])) {
            update_user_meta($user_id, 'islide_oauth_' . $data['type'] . '_unionid', $data['unionid']);
        }
        
        if (isset($data['openid']) && !empty($data['openid'])) {
            update_user_meta($user_id, 'islide_oauth_' . $data['type'] . '_openid', $data['openid']);
        }

        // 触发绑定用户钩子
        do_action('islide_social_binding_user', $user_id, $data);
        
        return true;
    }
    
    /**
     * 检查社交账号是否已绑定用户
     *
     * @author  ifyn
     * @param   array $data 社交账号数据
     * @return  object|false 已绑定用户信息或false
     */
    public static function check_binding_user_exist($data) {
        // 验证参数
        if (empty($data) || !is_array($data) || empty($data['type'])) {
            return false;
        }
        
        $user = false;
        
        // 优先使用unionid查询
        if (isset($data['unionid']) && !empty($data['unionid'])) {
            $user = get_users(array(
                'meta_key' => 'islide_oauth_' . $data['type'] . '_unionid',
                'meta_value' => $data['unionid']
            ));
        } else {
            // 根据类型和openid查询
            $meta_key = isset($data['mpweixin']) && $data['mpweixin'] 
                ? 'islide_oauth_mpweixin_openid' 
                : 'islide_oauth_' . $data['type'] . '_openid';
                
            $user = get_users(array(
                'meta_key' => $meta_key,
                'meta_value' => $data['openid']
            ));
        }

        // 返回用户数据或false
        if (!empty($user)) {
            return $user[0]->data;
        } else {
            return false;
        }
    }
    
    /**
     * 获取社交平台用户信息
     *
     * @author  ifyn
     * @param   array $data 包含用户访问令牌和标识的数据
     * @return  array       包含用户详细信息的数据或错误信息
     */
    public static function get_social_user_info($data) {
        // 验证参数
        if (empty($data) || !is_array($data) || empty($data['type'])) {
            return array('error' => '参数不完整');
        }
        
        // 聚合登录已包含用户信息，直接处理
        if (strpos($data['type'], 'juhe_') !== false) {
            if (isset($data['user_info']) && is_array($data['user_info'])) {
                $data['nickname'] = isset($data['user_info']['nickname']) ? $data['user_info']['nickname'] : '';
                $data['avatar'] = isset($data['user_info']['avatar']) 
                    ? str_replace('http://', 'https://', $data['user_info']['avatar']) 
                    : '';
                $data['sex'] = isset($data['sex']) ? (int)$data['sex'] : 0;
                unset($data['user_info']);
            }
            
            return $data;
        }
        
        // 验证支持的平台类型
        if (!in_array($data['type'], array('qq', 'weibo', 'weixin', 'apple', 'baidu', 'toutiao'))) {
            return array('error' => '不支持的登录类型');
        }
        
        // 如果已包含用户信息，直接处理
        if (isset($data['user_info']['nickName']) && isset($data['user_info']['avatarUrl'])) {
            $data['nickname'] = $data['user_info']['nickName'];
            $data['avatar'] = str_replace('http://', 'https://', $data['user_info']['avatarUrl']);
            $data['sex'] = 0;
            unset($data['user_info']);
            
            return $data;
        }
        
        // 根据不同平台，获取用户信息
        $url = '';
        switch ($data['type']) {
            case 'qq':
                // 参数验证
                if (empty($data['access_token']) || empty($data['openid'])) {
                    return array('error' => 'QQ登录数据不完整');
                }
                
                $qq = islide_get_option('oauth_qq');
                if (empty($qq) || empty($qq['app_id'])) {
                    return array('error' => 'QQ登录配置不完整');
                }
                
                $url = 'https://graph.qq.com/user/get_user_info?access_token=' . $data['access_token'] . 
                       '&oauth_consumer_key=' . $qq['app_id'] . '&openid=' . $data['openid'] . '&format=json';
                       
                $data['nickname'] = 'nickname';
                $data['avatar'] = 'figureurl_qq_2';
                $data['avatar1'] = 'figureurl_qq_1';
                $data['sex'] = 'gender';
                break;
                
            case 'weibo':
                // 参数验证
                if (empty($data['access_token']) || empty($data['openid'])) {
                    return array('error' => '微博登录数据不完整');
                }
                
                $url = 'https://api.weibo.com/2/users/show.json?uid=' . $data['openid'] . 
                       '&access_token=' . $data['access_token'];
                       
                $data['nickname'] = 'name';
                $data['avatar'] = 'avatar_large';
                $data['sex'] = 'gender';
                break;
                
            case 'weixin':
                // 参数验证
                if (empty($data['access_token']) || empty($data['openid'])) {
                    return array('error' => '微信登录数据不完整');
                }
                
                $url = 'https://api.weixin.qq.com/sns/userinfo?access_token=' . $data['access_token'] . 
                       '&openid=' . $data['openid'];
                       
                $data['nickname'] = 'nickname';
                $data['avatar'] = 'headimgurl';
                $data['sex'] = 'sex';
                break;
                
            default:
                return array('error' => '不支持的登录类型');
        }

        // 请求用户信息
        $user = wp_remote_get($url);

        // 处理请求错误
        if (is_wp_error($user)) {
            return array('error' => '网络错误：' . $user->get_error_message());
        }
        
        // 解析响应数据
        $user = json_decode($user['body'], true);

        // 处理错误响应
        if (isset($user['ret']) && $user['ret'] != 0) {
            return array('error' => sprintf(
                '错误代码：%s；错误信息：%s；请在百度中搜索相关错误代码进行修正。',
                $user['ret'],
                isset($user['msg']) ? $user['msg'] : '未知错误'
            ));
        }
        
        // 处理头像URL
        $avatar = isset($user[$data['avatar']]) ? $user[$data['avatar']] : '';
        
        // QQ头像备选
        if ($data['type'] === 'qq' && empty($avatar) && isset($data['avatar1']) && isset($user[$data['avatar1']])) {
            $avatar = $user[$data['avatar1']];
        }

        // 微博特殊处理
        if (($data['type'] === 'weibo' || $data['type'] === 'sina') && empty($avatar)) {
            $avatar = '';
        }
        
        // 更新数据
        $data['nickname'] = isset($user[$data['nickname']]) ? $user[$data['nickname']] : '';
        $data['avatar'] = !empty($avatar) ? str_replace('http://', 'https://', $avatar) : '';
        $data['sex'] = isset($user[$data['sex']]) ? (int)$user[$data['sex']] : 0;

        return $data;
    }
    
    /**
     * 获取社交平台访问令牌
     *
     * @author  ifyn
     * @param   array  $arg  包含请求参数的数组
     * @param   string $type 社交平台类型
     * @param   string $code 授权码
     * @return  array        包含令牌和用户ID的数据或错误信息
     */
    public static function get_token($arg, $type, $code) {
        // 验证参数
        if (empty($arg) || empty($type) || empty($code)) {
            return array('error' => '参数不完整');
        }

        // 构造请求参数
        $arg['code'] = $code;
        $arg['grant_type'] = 'authorization_code';
        $arg['redirect_uri'] = IS_HOME_URI . '/oauth?type=' . $type;

        // 微信特殊处理
        if ($type == 'weixin') {
            $arg['redirect_uri'] = str_replace(array('http://', 'https://'), '', IS_HOME_URI);
        }

        // 发送请求
        $res = wp_remote_post($arg['url'], 
            array(
                'method' => 'POST',
                'body' => $arg,
            )
        );

        // 处理请求错误
        if (is_wp_error($res)) {
            return array('error' => '网络错误：' . $res->get_error_message());
        }

        // 根据不同平台处理响应
        $data = array();
        switch ($type) {
            case 'qq':
                // 处理jsonp响应
                if (strpos($res['body'], 'callback') !== false) {
                    $lpos = strpos($res['body'], '(');
                    $rpos = strrpos($res['body'], ')');
                    $res_json = substr($res['body'], $lpos + 1, $rpos - $lpos - 1);
                    $msg = json_decode($res_json);
                    
                    if (isset($msg->error)) {
                        return array('error' => sprintf(
                            '错误代码：%s；错误信息：%s；请在百度中搜索相关错误代码进行修正。',
                            $msg->error,
                            isset($msg->error_description) ? $msg->error_description : '未知错误'
                        ));
                    }
                }
                
                // 解析响应参数
                $params = array();
                parse_str($res['body'], $params);
                
                if (empty($params) || !isset($params['access_token'])) {
                    return array('error' => 'QQ登录授权失败');
                }

                // 获取openid
                $openid_res = wp_remote_get('https://graph.qq.com/oauth2.0/me?access_token=' . $params['access_token']);

                if (is_wp_error($openid_res)) {
                    return array('error' => '网络错误：' . $openid_res->get_error_message());
                }

                $openid_content = $openid_res['body'];

                // 处理jsonp响应
                if (strpos($openid_content, 'callback') !== false) {
                    $lpos = strpos($openid_content, '(');
                    $rpos = strrpos($openid_content, ')');
                    $openid_content = substr($openid_content, $lpos + 1, $rpos - $lpos - 1);
                }

                $openid_data = json_decode($openid_content, true);
                
                if (isset($openid_data['error'])) {
                    return array('error' => sprintf(
                        '错误代码：%s；错误信息：%s；请在百度中搜索相关错误代码进行修正。',
                        $openid_data['error'],
                        isset($openid_data['error_description']) ? $openid_data['error_description'] : '未知错误'
                    ));
                }

                if (empty($openid_data) || !isset($openid_data['openid'])) {
                    return array('error' => 'QQ登录无法获取OpenID');
                }

                $data = array(
                    'access_token' => $params['access_token'],
                    'openid' => $openid_data['openid'],
                    'type' => 'qq'
                );
                break;
                
            case 'weibo':
                $msg = json_decode($res['body'], true);
                
                if (isset($msg['error'])) {
                    return array('error' => sprintf(
                        '错误代码：%s；错误信息：%s；请在百度中搜索相关错误代码进行修正。',
                        $msg['error'],
                        isset($msg['error_description']) ? $msg['error_description'] : '未知错误'
                    ));
                }
                
                if (empty($msg) || !isset($msg['access_token']) || !isset($msg['uid'])) {
                    return array('error' => '微博登录授权失败');
                }
                
                $data = array(
                    'access_token' => $msg['access_token'],
                    'openid' => $msg['uid'],
                    'type' => 'weibo'
                );
                break;
                
            case 'weixin':
                $msg = json_decode($res['body'], true);
                
                if (isset($msg['errcode'])) {
                    return array('error' => sprintf(
                        '错误代码：%s；错误信息：%s；请在百度中搜索相关错误代码进行修正。',
                        $msg['errcode'],
                        isset($msg['errmsg']) ? $msg['errmsg'] : '未知错误'
                    ));
                }
                
                if (empty($msg) || !isset($msg['access_token']) || !isset($msg['openid'])) {
                    return array('error' => '微信登录授权失败');
                }
                
                $data = array(
                    'access_token' => $msg['access_token'],
                    'openid' => $msg['openid'],
                    'unionid' => isset($msg['unionid']) && !empty($msg['unionid']) ? $msg['unionid'] : '',
                    'type' => 'weixin'
                );
                break;
                
            default:
                return array('error' => '不支持的登录类型');
        }
        
        // 确保类型正确
        $data['type'] = $type;

        // 通过社交登录处理
        return self::social_login($data);
    }
    
    /**
     * 获取已启用的社交登录选项
     *
     * @author  ifyn
     * @param   string $filter_type 可选的过滤类型
     * @return  array               可用的社交登录选项
     */
    public static function get_enabled_oauths($filter_type = '') {
        // 获取所有社交登录类型
        $types = get_oauth_types();
        if (!is_array($types)) {
            return array();
        }
        
        // 获取配置
        $options = islide_get_option();
        $data = array();
        
        // 检查各平台是否开启
        $qq_open = isset($options['oauth_qq_open']) && $options['oauth_qq_open'];
        $weixin_open = isset($options['oauth_weixin_open']) && $options['oauth_weixin_open'];
        $weibo_open = isset($options['oauth_weibo_open']) && $options['oauth_weibo_open'];
        $juhe_open = isset($options['oauth_juhe_open']) && $options['oauth_juhe_open'];
        
        // 添加QQ登录
        if ($qq_open && isset($types['qq'])) {
            $data['qq'] = $types['qq'];
            $login_url = self::social_oauth_login('qq');
            $data['qq']['url'] = isset($login_url['url']) ? $login_url['url'] : '';
        }
    
        // 添加微信登录
        if ($weixin_open && isset($types['weixin'])) {
            $data['weixin'] = $types['weixin'];
        }
        
        // 添加微博登录
        if ($weibo_open && isset($types['sina'])) {
            $data['weibo'] = $types['sina'];
            $login_url = self::social_oauth_login('weibo');
            $data['weibo']['url'] = isset($login_url['url']) ? $login_url['url'] : '';
        }
        
        // 添加聚合登录选项
        if ($juhe_open && isset($options['oauth_juhe']) && is_array($options['oauth_juhe'])) {
            $juhe = $options['oauth_juhe'];
            if (isset($juhe['types']) && !empty($juhe['types']) && is_array($juhe['types'])) {
                foreach ($juhe['types'] as $value) {
                    if (!isset($data[$value]) && isset($types[$value])) {
                        $type = 'juhe_' . $value;
                        $data[$type] = $types[$value];
                    }
                }
            }
        }
        
        // 如果指定了过滤类型，只返回该类型的数据
        if (!empty($filter_type) && isset($data[$filter_type])) {
            return array($filter_type => $data[$filter_type]);
        }
        
        return apply_filters('islide_oauth_links_arg', $data);
    }
    
    /**
     * 生成社交平台登录URL
     *
     * @author  ifyn
     * @param   string $type 社交平台类型
     * @return  array        包含登录URL和二维码的数据或错误信息
     */
    public static function social_oauth_login($type) {
        // 验证参数
        if (empty($type)) {
            return array('error' => '参数不完整');
        }
        
        // 获取配置
        $options = islide_get_option();
        $array = array();
        
        // 处理聚合登录
        if (strpos($type, 'juhe_') !== false) {
            if (!isset($options['oauth_juhe']) || !is_array($options['oauth_juhe'])) {
                return array('error' => '聚合登录配置不完整');
            }
            
            $juhe = $options['oauth_juhe'];
            
            if (!empty($juhe)) {
                $gateway = rtrim(trim($juhe['gateway'], " \t\n\r\0\x0B\xC2\xA0"), '/');
                
                // 构造请求参数
                $params = array(
                    'act' => 'login',
                    'appid' => trim($juhe['app_id'], " \t\n\r\0\x0B\xC2\xA0"),
                    'appkey' => trim($juhe['app_key'], " \t\n\r\0\x0B\xC2\xA0"),
                    'type' => str_replace('juhe_', '', $type),
                    'redirect_uri' => IS_HOME_URI . '/oauth?_type=' . $type,
                    'state' => md5(uniqid(rand(), TRUE))
                );
                
                // 发送请求
                $api = $gateway . '/connect.php?' . http_build_query($params);
                $res = wp_remote_post($api);
                
                // 处理请求错误
                if (is_wp_error($res)) {
                    return array('error' => '网络错误：' . $res->get_error_message());
                }
                
                // 处理响应
                if ($res['response']['code'] == 200) {
                    $data = json_decode($res['body'], true);
        
                    if (isset($data['code']) && (int)$data['code'] == 0) {
                        if (isset($data['qrcode']) && $data['qrcode']) {
                            $array['qrcode'] = ''; // 二维码暂时不使用
                        }
            
                        if (isset($data['url']) && $data['url']) {
                            $array['url'] = $data['url'];
                        }
                    } else {
                        return array('error' => isset($data['msg']) ? $data['msg'] : '获取登录URL失败');
                    }
                }
            }
        } else {
            // 处理QQ登录
            if ($type == 'qq') {
                if (!isset($options['oauth_qq']) || !is_array($options['oauth_qq'])) {
                    return array('error' => 'QQ登录配置不完整');
                }
                
                $qq = $options['oauth_qq'];
                
                $params = array(
                    'client_id' => trim($qq['app_id'], " \t\n\r\0\x0B\xC2\xA0"),
                    'response_type' => 'code',
                    'redirect_uri' => IS_HOME_URI . '/oauth?type=qq',
                    'state' => md5(uniqid(rand(), TRUE))
                );
                
                $array['url'] = 'https://graph.qq.com/oauth2.0/authorize?' . http_build_query($params);
            }
            // 处理微信登录
            else if ($type == 'weixin') {
                // 微信登录特殊处理，前端生成
                $array['url'] = '';
            }
            // 处理微博登录
            else if ($type == 'weibo') {
                if (!isset($options['oauth_weibo']) || !is_array($options['oauth_weibo'])) {
                    return array('error' => '微博登录配置不完整');
                }
                
                $weibo = $options['oauth_weibo'];
                
                $params = array(
                    'client_id' => trim($weibo['app_id'], " \t\n\r\0\x0B\xC2\xA0"),
                    'response_type' => 'code',
                    'redirect_uri' => IS_HOME_URI . '/oauth?type=weibo',
                );
                
                $array['url'] = 'https://api.weibo.com/oauth2/authorize?' . http_build_query($params);
            }
        }
        
        // 检查结果
        if (isset($array['url']) || isset($array['qrcode'])) {
            return $array;
        }
        
        return array('error' => '网络错误，请稍后再试');
    }
}