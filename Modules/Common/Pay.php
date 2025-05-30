<?php
/**
 * 支付功能管理类
 * 
 * 处理多种支付方式和支付回调的核心功能
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;

/**
 * 支付处理类
 */
class Pay {
    /**
     * 检查当前使用的支付平台
     *
     * @author  ifyn
     * @param   string $type 支付类型标识
     * @return  array        支付类型信息或错误信息
     */
    public static function pay_type($type) {
        // 允许的支付类型列表
        $allowed_types = array(
            //'alipay_normal',
            'xunhu',
            'xunhu_hupijiao',
            //'wecatpay_normal',
            'balance',
            'credit',
            'card',
            'yipay',
            'alipay',
            'wecatpay',
        );
         
        // 验证支付类型是否在允许列表中
        if (!in_array($type, $allowed_types)) {
            return array('error' => __('支付类型错误！1'.$type, 'islide'));
        }
    
        // 处理支付宝类型
        if (strpos($type, 'alipay') !== false) {
            $alipay_type = islide_get_option('pay_alipay');
            
            if (!$alipay_type) {
                return array('error' => __('未启用支付宝', 'islide'));
            }
            
            return array(
                'pick' => 'alipay',
                'type' => $alipay_type
            );
        } 
        // 处理微信支付类型
        elseif (strpos($type, 'wecatpay') !== false) {
            $wecatpay_type = islide_get_option('pay_wechat');
            if (!$wecatpay_type) {
                return array('error' => __('未启用微信支付', 'islide'));
            }
            return array(
                'pick' => 'wecatpay',
                'type' => $wecatpay_type
            );
        } 
        // 处理余额支付类型
        elseif ($type === 'balance') {
            return array(
                'pick' => 'balance',
                'type' => 'balance'
            );
        } 
        // 处理积分支付类型
        elseif ($type === 'credit') {
            return array(
                'pick' => 'credit',
                'type' => 'credit'
            );
        } 
        // 处理卡密支付类型
        elseif ($type === 'card') {
            return array(
                'pick' => 'card',
                'type' => 'card'
            );
        }
    
        return array('error' => __('未知的支付类型', 'islide'));
    }
    
    /**
     * 获取当前平台允许使用的支付方式
     *
     * @author  ifyn
     * @param   string $order_type 订单类型
     * @return  array              允许的支付方式列表
     */
    public static function allow_pay_type($order_type) {
        $user_id = get_current_user_id();
        $is_mobile = wp_is_mobile();

        // 默认支付方式设置
        $allows = array(
            'wecatpay' => true,
            'alipay' => true,
            'balancepay' => $user_id ? true : false,
            'cardpay' => false
        );

        // 获取当前的支付方式配置
        $alipay_type = islide_get_option('pay_alipay');
        $wecatpay_type = islide_get_option('pay_wechat');
        
        // 检查是否启用支付宝
        if (!$alipay_type) {
            $allows['alipay'] = false;
        }
        
        // 检查是否启用微信
        if (!$wecatpay_type) {
            $allows['wecatpay'] = false;
        }
        
        // 根据订单类型调整可用支付方式
        // 余额充值不能用余额支付，可以用卡密
        if ($order_type == 'money_chongzhi') {
            $allows['balancepay'] = false;
            $allows['cardpay'] = true;
        }
        
        // 积分充值可以用卡密
        if ($order_type == 'credit_chongzhi') {
            $allows['cardpay'] = true;
        }
        
        // VIP购买不能用卡密
        if ($order_type == 'vip_goumai') {
            $allows['cardpay'] = false;
        }

        // 商城订单不能用卡密，可以用余额
        if ($order_type == 'shop') {
            $allows['cardpay'] = false;
            $allows['balancepay'] = true;
        }
        
        // 获取用户的积分余额
        $credit = get_user_meta($user_id, 'islide_credit', true);
        $allows['credit'] = $credit ? (int)$credit : 0;
        
        // 获取用户的现金余额
        $money = get_user_meta($user_id, 'islide_money', true);
        $allows['money'] = $money ? $money : 0;
        
        return $allows;
    }
    
    /**
     * 选择支付平台并执行支付处理
     *
     * @author  ifyn
     * @param   array $data 订单和支付相关数据
     * @return  array       支付结果或错误信息
     */
    public static function pay($data) {
        // 支付前处理钩子
        $data = apply_filters('islide_pay_before', $data);
        if (isset($data['error'])) return $data;

        // 处理支付类型
        if (isset($data['pay_type'])) {
            $pay_type = $data['pay_type'];
            // 处理标题中的特殊字符
            $data['title'] = str_replace(array('&', '=', ' '), '', $data['title']);
            
            // 使用对应的支付方法
            return self::$pay_type($data);
        }
        
        return array('error' => __('支付类型未指定', 'islide'));
    }
    
    /**
     * 积分支付处理
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  array       支付结果或错误信息
     */
    public static function credit($data) {
        // 验证用户登录状态
        if (!$data['user_id']) return array('error'=>__('请先登录','islide'));
        
        // 处理抽奖类型的积分支付
        if ($data['order_type'] === 'choujiang') {
            return self::credit_pay($data['order_id']);
        }
        
        // 返回积分支付信息
        return array(
            'order_id'=> $data['order_id'],
            'qrcode'=> null,
            'url' => null,
            'pay_type' => 'credit'
        );
    }
    
    /**
     * 执行积分支付操作
     *
     * @author  ifyn
     * @param   string $order_id 订单ID
     * @return  array            支付结果或错误信息
     */
    public static function credit_pay($order_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_order';

        // 获取订单数据
        $data = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE `order_id`=%s", $order_id),
            ARRAY_A
        );

        // 验证订单数据
        if (empty($data)) return array('error'=>__('支付信息错误','islide'));
        
        // 判断是否等待支付
        if ((int)$data['order_state'] !== 0) return array('error'=>__('订单已支付','islide'));

        // 验证支付类型
        if ($data['pay_type'] !== 'credit') return array('error'=>__('支付类型错误','islide'));

        // 验证用户登录状态
        if (!$data['user_id']) return array('error'=>__('请先登录','islide'));
        
        // 扣除用户积分
        $credit = User::credit_change($data['user_id'], -$data['order_total']);

        // 检查积分余额
        if ($credit === false) {
            return array('error'=>__('积分余额不足','islide'));
        }

        // 支付后处理钩子
        $data = apply_filters('islide_credit_pay_after', $data, $credit);
        
        // 支付成功回调
        return Orders::order_confirm($data['order_id'], $data['order_total']);
    }
    
    /**
     * 余额支付处理
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  array       支付结果或错误信息
     */
    public static function balance($data) {
        // 验证用户登录状态
        if (!$data['user_id']) return array('error'=>__('请先登录','islide'));

        // 返回余额支付信息
        return array(
            'order_id'=> $data['order_id'],
            'qrcode'=> null,
            'url' => null,
            'pay_type' => 'balance'
        );
    }

    /**
     * 执行余额支付操作
     *
     * @author  ifyn
     * @param   string $order_id 订单ID
     * @return  array            支付结果或错误信息
     */
    public static function balance_pay($order_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_order';

        // 获取订单数据
        $data = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE `order_id`=%s", $order_id),
            ARRAY_A
        );

        // 验证订单数据
        if (empty($data)) return array('error'=>__('支付信息错误','islide'));

        // 判断是否等待支付
        if ((int)$data['order_state'] !== 0) return array('error'=>__('订单已支付','islide'));

        // 验证支付类型
        if ($data['pay_type'] !== 'balance') return array('error'=>__('支付类型错误','islide'));

        // 验证用户登录状态
        if (!$data['user_id']) return array('error'=>__('请先登录','islide'));

        // 扣除用户余额
        $money = User::money_change($data['user_id'], -$data['order_total']);

        // 检查余额
        if ($money === false) {
            return array('error'=>sprintf(__('%s不足','islide'),'余额'));
        }

        // 支付后处理钩子可在需要时启用
        // $data = apply_filters('islide_balance_pay_after', $data, $money);
        // if(isset($data['error'])) return $data;
 
        // 支付成功回调
        return Orders::order_confirm($data['order_id'], $data['order_total']);
    }
    
    /**
     * 卡密支付处理
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  array       支付结果或错误信息
     */
    public static function card($data) {
        // 验证用户登录状态
        if (!$data['user_id']) return array('error'=>__('请先登录','islide'));

        // 返回卡密支付信息
        return array(
            'order_id'=> $data['order_id'],
            'qrcode'=> null,
            'url' => null,
            'pay_type' => 'card'
        );
    }

    /**
     * 支付宝官方设置项
     * 
     * @author  ifyn
     * @return  array 支付宝配置参数
     */
    public static function alipay_settings() {
        // 获取支付宝配置
        $alipay = islide_get_option('alipay');
        
        // 返回配置参数
        return array(
            // 沙箱模式
            'debug'       => false,
            'sign_type'   => "RSA2",
            'appid'       => trim($alipay['appid']),
            'public_key'  => trim($alipay['public_key']),
            'private_key' => trim($alipay['private_key']),
            'notify_url'  => islide_get_custom_page_url('notify'),
            'return_url'  => home_url()
        );
    }
    
    /**
     * 支付宝支付处理
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  array       支付结果或错误信息
     */
    public static function alipay($data) {
        // 获取支付宝配置
        $alipay = islide_get_option('alipay');
        $alipay_type = $alipay['alipay_type'];
        $config = self::alipay_settings();
        
        // 检测是否移动端
        $is_mobile = wp_is_mobile();
        
        try {
            // 企业支付 跳转支付
            if ($alipay_type == 'normal') {
                $config['return_url'] = $data['redirect_url'];
                // 移动端
                if ($is_mobile) {
                    $config['passback_params'] = urlencode($data['redirect_url']);
                    $pay = \We::AliPayWap($config);
                }
                // PC端
                else {
                    $pay = \We::AliPayWeb($config);
                }
            }
            
            // 当面付
            if ($alipay_type == 'scan') {
                $pay = \We::AliPayScan($config);
            }
            
            // 执行支付
            $result = $pay->apply([
                'out_trade_no' => $data['order_id'], // 商户订单号
                'total_amount' => $data['order_total'], // 支付金额
                'subject'      => $data['title']
            ]);
            
            // 企业跳转支付
            if ($alipay_type == 'normal') {
                return array(
                    'order_id' => $data['order_id'],
                    'url' => $result
                );
            }
            
            // 当面付移动端跳转支付
            if ($alipay_type == 'scan' && $is_mobile) {
                return array(
                    'order_id' => $data['order_id'],
                    'url' => $result['qr_code']
                ); 
            }
            
            // 扫码支付
            return array(
                'is_weixin' => isset($is_weixin) ? $is_weixin : false,
                'is_mobile' => $is_mobile,
                'order_id' => $data['order_id'],
                'qrcode' => $result['qr_code']
            );
           
        } catch (\Exception $e) {
            return array('error' => $e->getMessage());
        }
    }
    
    /**
     * 迅虎支付处理
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  array       支付结果或错误信息
     */
    public static function xunhu($data) {
        // 检测是否移动端
        $is_mobile = wp_is_mobile();
        
        // 付款方式
        $payment_method = $data['payment_method'] == 'alipay' ? 'alipay' : 'wechat';
        $xunhu = islide_get_option('xunhu');
        
        // 构建支付参数
        $param = array(
            'out_trade_no'  => $data['order_id'],
            'type'          => $payment_method,
            'total_fee'     => $data['order_total'] * 100,
            'body'          => $data['title'],
            'notify_url'    => islide_get_custom_page_url('notify'),
            'nonce_str'     => str_shuffle(time())
        );
        
        // 加载迅虎支付库
        require IS_THEME_DIR . '/Library/xunhu/xunhu.php';
        $xunhupay = new \XunHu($xunhu);
        
        // 移动端浏览器支付处理
        if ($is_mobile) {
            $param['redirect_url'] = $data['redirect_url'];
            $res = '';
            
            // 微信支付
            if ($payment_method === 'wechat') {
                $param['trade_type'] = "WAP";
                $param['wap_url']    = (isset($http_type) ? $http_type : 'https://') . $_SERVER['SERVER_NAME']; // h5支付域名必须备案
                $param['wap_name']   = get_bloginfo('name');
                
                $res = $xunhupay->wechat_h5($param);
            }
            
            // 支付宝
            if ($payment_method === 'alipay') {
                // 收银台
                $pay_url = $xunhupay->cashier($param);
                
                return array(
                    'url' => htmlspecialchars_decode($pay_url, ENT_NOQUOTES),
                    'order_id' => $data['order_id'],
                );
            }
            
            // 验证结果
            if (!$res) {
                return array('error' => 'Internal server error');
            }
            
            if ($res['return_code'] != 'SUCCESS') {
                return array('error' => sprintf('错误代码：%s。错误信息：%s', $res['err_code'], $res['err_msg']));
            }
            
            $sign = $xunhupay->sign($res);
    
            if (!isset($res['sign']) || $sign != $res['sign']) {
                return array('error' => 'Invalid sign!');
            }
            
            $pay_url = isset($result['mweb_url']) ? $result['mweb_url'] . '&redirect_url=' . $param['redirect_url'] : '';
            
            return array(
                'order_id' => $data['order_id'],
                'url' => array(
                    'url' => isset($result['mweb_url']) ? $result['mweb_url'] : '',
                    'data' => $param
                )
            );
        }
        
        // 扫码支付
        $res = $xunhupay->qrcode($param);

        // 验证结果
        if (!$res) {
            return array('error' => 'Internal server error');
        }
        
        if ($res['return_code'] != 'SUCCESS') {
            return array('error' => sprintf('错误代码：%s。错误信息：%s', $res['err_code'], $res['err_msg']));
        }
        
        $sign = $xunhupay->sign($res);

        if (!isset($res['sign']) || $sign != $res['sign']) {
            return array('error' => 'Invalid sign!');
        }
        
        return array(
            'is_weixin' => isset($is_weixin) ? $is_weixin : false,
            'is_mobile' => $is_mobile,
            'order_id' => $data['order_id'],
            'qrcode' => $res['code_url']
        );
    }
    
    /**
     * 虎皮椒支付处理
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  array       支付结果或错误信息
     */
    public static function xunhu_hupijiao($data) {
        // 检测是否移动端
        $is_mobile = wp_is_mobile();
        
        // 确定支付方式
        $payment_method = $data['payment_method'] == 'alipay' ? 'alipay' : 'wechat';
        $hupijiao = islide_get_option('xunhu_hupijiao');
        
        // 验证配置
        if (empty($hupijiao[$payment_method.'_appid']) || empty($hupijiao[$payment_method.'_appsecret'])) {
            return array('error' => $payment_method.'未设置appid或者appsecret');
        }

        // 构建支付参数
        $param = array(
            'version'        => '1.1', // 固定值，api 版本，目前暂时是1.1
            'lang'           => 'zh-cn', // 必须的，zh-cn或en-us 或其他，根据语言显示页面
            'trade_order_id' => $data['order_id'],
            'total_fee'      => $data['order_total'],
            'title'          => $data['title'],
            'time'           => time(),
            'notify_url'     => islide_get_custom_page_url('notify'), // 通知回调网址
            'return_url'     => $data['redirect_url'], // 用户支付成功后，我们会让用户浏览器自动跳转到这个网址
            'callback_url'   => home_url(), // 用户取消支付后，我们可能引导用户跳转到这个网址上重新进行支付
            'nonce_str'      => str_shuffle(time())
        );
        
        // 根据支付方式设置不同的参数
        if ($payment_method == 'alipay') {
            $param['appid'] = $hupijiao['alipay_appid'];
            $appsecret = $hupijiao['alipay_appsecret'];
            $param['plugins'] = $param['payment'] = 'alipay'; // 必须的，支付接口标识：wechat(微信接口)|alipay(支付宝接口)
        } else {
            $param['appid'] = $hupijiao['wechat_appid'];
            $appsecret = $hupijiao['wechat_appsecret'];
            $param['plugins'] = $param['payment'] = 'wechat';
        }
        
        // 移动端微信支付特殊处理
        if ($is_mobile && $payment_method == 'wechat') {
            $param['type'] = 'WAP';
            $param['wap_url'] = (isset($http_type) ? $http_type : 'https://') . $_SERVER['SERVER_NAME']; // h5支付域名必须备案
            $param['wap_name'] = get_bloginfo('name');
        }
        
        // 签名密钥
        $hashkey = $appsecret;
        
        // 加载虎皮椒支付库
        require IS_THEME_DIR . '/Library/xunhu_hupijiao/xunhu_hupijiao.php';
        
        // 生成签名
        $param['hash'] = \XH_Payment_Api::generate_xh_hash($param, $hashkey);
        
        // 设置支付网关
        $url = 'https://api.xunhupay.com/payment/do.html';
        if (!empty($hupijiao['hupijiao_gateway'])) {
            $url = $hupijiao['hupijiao_gateway'];
        }

        try {
            // 发送支付请求
            $response = \XH_Payment_Api::http_post($url, json_encode($param));
            $result = $response ? json_decode($response, true) : null;
            
            // 验证返回结果
            if (!$result) {
                return array('error' => 'Internal server error');
            }

            // 验证签名
            $hash = \XH_Payment_Api::generate_xh_hash($result, $hashkey);
            if (!isset($result['hash']) || $hash != $result['hash']) {
                return array('error' => 'Invalid sign!');
            }

            // 检查错误码
            if ($result['errcode'] != 0) {
                return array('error' => $result['errmsg']);
            }

            // 获取支付链接
            $pay_url = $result['url'];
            
            // 返回支付信息
            return array(
                'order_id' => $data['order_id'],
                'qrcode' => isset($result['url_qrcode']) ? $result['url_qrcode'] : '',
                'url' => $pay_url
            );

        } catch (\Exception $e) {
            return array('error' => $e->getMessage());
        }
    }
    
    /**
     * 易支付处理
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  array       支付结果或错误信息
     */
    public static function yipay($data) {
        // 确定支付方式
        $payment_method = $data['payment_method'] == 'alipay' ? 'alipay' : 'wxpay';
        $yipay = islide_get_option('yipay');
        
        // 验证配置
        if (empty($yipay['yipay_id']) || empty($yipay['yipay_key']) || empty($yipay['yipay_gateway'])) {
            return array('error' => '请检查易支付设置，缺失参数');
        }
        
        // 准备支付参数
        $param = array(
            'pid'           => trim($yipay['yipay_id'], " \t\n\r\0\x0B\xC2\xA0"),
            'type'          => $payment_method,
            'sitename'      => get_bloginfo('name'),
            'out_trade_no'  => $data['order_id'],
            'notify_url'    => islide_get_custom_page_url('notify'),
            'return_url'    => $data['redirect_url'],
            'name'          => $data['title'],
            'money'         => $data['order_total'],
            'sign_type'     => 'MD5'
        );
        
        // 排序参数
        ksort($param);
        reset($param);

        $sign = '';
        $urls = '';

        // 构建签名字符串
        foreach ($param as $key => $val) {
            if ($val == '' || $key == 'sign' || $key == 'sign_type') continue;
            if ($sign != '') {
                $sign .= "&";
                $urls .= "&";
            }
            $sign .= "$key=$val";
            $urls .= "$key=" . urlencode($val);
        }
        
        // 生成签名并构建完整URL
        $query = $urls . '&sign=' . md5($sign . trim($yipay['yipay_key'], " \t\n\r\0\x0B\xC2\xA0")) . '&sign_type=MD5';
        $url = rtrim($yipay['yipay_gateway'], '/');
        $url = $url . '/submit.php?' . $query;
        
        // 返回支付信息
        return array(
            'order_id' => $data['order_id'],
            'url' => $url
        );
    }
    
    /**
     * 处理支付回调通知
     *
     * @author  ifyn
     * @param   string $method 请求方法
     * @param   array  $post   回调数据
     * @return  mixed          处理结果或错误信息
     */
    public static function pay_notify($method, $post) {
        // 回调前处理钩子
        $post = apply_filters('islide_pay_notify_action', $post);
        
        // 判断支付平台类型
        $hupijiao = isset($post['hash']) && isset($post['trade_order_id']);
        $xunhupay = isset($post['mchid']) && isset($post['out_trade_no']) && isset($post['order_id']);

        $order_id = '';
        
        // 提取订单号 - 迅虎、易支付、支付宝
        if (isset($post['out_trade_no'])) {
            $order_id = $post['out_trade_no'];
        }
        
        // 提取订单号 - 虎皮椒
        if ($hupijiao) {
            $order_id = $post['trade_order_id'];
        }
        
        // 验证订单号
        if (!$order_id) return array('error' => __('订单获取失败', 'islide'));

        // 获取订单数据
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_order';
        $order = $wpdb->get_row(
            $wpdb->prepare("
                SELECT * FROM $table_name
                WHERE order_id LIKE %s 
                ",
                '%' . $order_id . '%'
            )
        , ARRAY_A);

        // 验证订单存在
        if (!$order) return '';

        // 确定支付平台类型
        $type = apply_filters('islide_order_check_action', array(
            'order' => $order,
            'hupijiao' => $hupijiao,
            'xunhupay' => $xunhupay
        ));

        if (!$type) return '';

        if (isset($type['error'])) return $type;

        // 迅虎支付特殊处理
        if ($type === 'xunhu') return self::xunhu_notify($post, $order);
        
        // 调用对应的支付回调处理方法
        $type = $type . '_notify';
        
        $_POST = $post;
        $_GET = $post;
        
        if (!method_exists(__CLASS__, $type)) return '';

        return self::$type($method, $post);
    }
    
    /**
     * 支付宝回调通知处理
     *
     * @author  ifyn
     * @param   string $method 请求方法
     * @param   array  $post   回调数据
     * @return  mixed          处理结果或错误信息
     */
    public static function alipay_notify($method, $post) {
        // 获取支付宝配置
        $config = self::alipay_settings();
        if (isset($post['passback_params'])) {
            $config['return_url'] = $post['passback_params'];
        }
        
        try {
            // 初始化支付宝对象
            $pay = \AliPay\App::instance($config);
            $data = $pay->notify();
            
            // GET请求处理（同步通知）
            if ($method == 'get') {
                if ($post['sign'] === $data['sign']) {
                    return true;
                } else {
                    return false;
                }
            } 
            // POST请求处理（异步通知）
            else {
                // 验证签名
                if ($post['sign'] !== $data['sign']) return array('error' => __('签名错误', 'islide'));

                // 验证支付状态
                if (in_array($data['trade_status'], ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
                    $res = Orders::order_confirm($data['out_trade_no'], $data['total_amount']);
                    if (isset($res['error'])) {
                        return $res;
                    } else {
                        return 'success';
                    }
                } else {
                    return array('error' => '支付回调错误');
                }
            }
        } catch (\Exception $e) {
            return array('error' => $e->getMessage());
        }
    }
    
    /**
     * 迅虎支付回调通知处理
     *
     * @author  ifyn
     * @param   array $post  回调数据
     * @param   array $order 订单数据
     * @return  mixed        处理结果或错误信息
     */
    public static function xunhu_notify($post, $order) {
        // 加载支付库
        require IS_THEME_DIR . '/Library/xunhu/xunhu.php';
        require IS_THEME_DIR . '/Library/xunhu_hupijiao/xunhu_hupijiao.php';
         
        $type = $order['pay_type'];
        
        // 迅虎支付处理
        if ($type == 'xunhu') {
            // 获取配置
            $xunhu = islide_get_option('xunhu');
            $xunhupay = new \XunHu($xunhu);
    
            // 验证签名
            $sign = $xunhupay->sign($post);
            if ($post['sign'] != $sign) {
                // 签名验证失败
                return array('error' => __('签名错误', 'islide'));
            }
    
            // 处理支付完成状态
            if ($post['status'] == 'complete') {
                $res = Orders::order_confirm($post['out_trade_no'], $post['total_fee'] / 100);
                return 'success';
            }
        } 
        // 虎皮椒支付处理
        else {
            // 获取支付类型和密钥
            $pay_type = isset($post['plugins']) ? $post['plugins'] : ''; // 支付接口标识：wechat(微信接口)|alipay(支付宝接口)
            $hupijiao = islide_get_option('xunhu_hupijiao');
            $appsecret = isset($hupijiao[$pay_type . '_appsecret']) ? $hupijiao[$pay_type . '_appsecret'] : '';
            
            // 验证密钥
            if (!$appsecret) return array('error' => __('回调错误', 'islide'));
            
            // 验证签名
            $hash = \XH_Payment_Api::generate_xh_hash($post, $appsecret);
            if ($post['hash'] !== $hash) {
                return array('error' => __('签名错误', 'islide'));
            }

            // 处理支付完成状态
            if ($post['status'] == 'OD') {
                $res = Orders::order_confirm($post['trade_order_id'], $post['total_fee']);
                return 'success';
            }
        }
        
        return array('error' => '回调失败');
    }
    
    /**
     * 易支付回调通知处理
     *
     * @author  ifyn
     * @param   string $method 请求方法
     * @param   array  $data   回调数据
     * @return  mixed          处理结果或错误信息
     */
    public static function yipay_notify($method, $data) {
        // 获取易支付配置
        $yipay = islide_get_option('yipay');
        
        // 验证支付状态
        if (isset($data['trade_status']) && $data['trade_status'] === 'TRADE_SUCCESS' && !empty($data['sign'])) {
            // 排序参数计算签名
            ksort($data);
            reset($data);
    
            $sign = '';
    
            foreach ($data as $key => $val) {
                if ($val == '' || $key == 'sign' || $key == 'sign_type') continue;
                if ($sign != '') {
                    $sign .= "&";
                }
                $sign .= "$key=$val";
            }

            // 计算签名
            $sign = md5($sign . trim($yipay['yipay_key'], " \t\n\r\0\x0B\xC2\xA0"));

            if (!$sign) return array('error' => '签名错误');

            // 验证签名并确认订单
            if ($sign === $data['sign']) {
                $res = Orders::order_confirm($data['out_trade_no'], false);
                if (isset($res['error'])) return $res;
                return 'success';
            }
        }

        return array('error' => '支付回调错误');
    }
    
    /**
     * AJAX检查支付结果
     *
     * @author  ifyn
     * @param   string $order_id 订单ID
     * @return  mixed            检查结果
     */
    public static function pay_check($order_id) {
        return apply_filters('islide_pay_check', $order_id);
    }
}