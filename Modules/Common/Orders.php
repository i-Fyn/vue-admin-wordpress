<?php
/**
 * 订单管理类
 * 
 * 处理站点内各种订单的创建、支付、查询等功能
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;

use islide\Modules\Common\User;
use islide\Modules\Common\CircleRelate;
/*
 * 商城订单项
 * $order_type //订单类型
 * choujiang : 抽奖 ，duihuan : 兑换 ，goumai : 购买 ，post_neigou : 文章内购 ，dashang : 打赏 ，xiazai : 资源下载 ，money_chongzhi : 余额充值 ，vip_goumai : VIP购买 ,credit_chongzhi : 积分购买,
 * video : 视频购买,verify : 认证付费,mission : 签到填坑 , coupon : 优惠劵订单,join_circle : 支付入圈 
 *
 * $order_commodity //商品类型
 * 0 : 虚拟物品 ，1 : 实物
 *
 * $order_state //订单状态
 * 0 : 等待付款 ，1 : 已付款未发货 ，2 : 已发货 ，3 : 已签收 ，4 : 已退款，5 : 已删除 
 */
class Orders
{
    /**
     * 初始化函数，注册钩子
     *
     * @author  ifyn
     * @return  void
     */
    public function init()
    {
        // 支付成功回调
        add_action('islide_order_notify_return', array(__CLASS__, 'order_notify_return'), 5, 1);
    }

    /**
     * 生成订单号
     *
     * @author  ifyn
     * @return  string 生成的订单号
     */
    public static function build_order_no()
    {
        $year_code = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
        $order_number = $year_code[intval(date('Y')) - 2020] . 
                        strtoupper(dechex(date('m'))) . 
                        date('d') . 
                        substr(time(), -5) . 
                        substr(microtime(), 2, 5) . 
                        sprintf('%02d', rand(0, 99));
        return $order_number;
    }

    /**
     * 创建订单
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  array       处理结果或错误信息
     */
    public static function build_order($data)
    {
        // 获取当前用户ID
        $user_id = get_current_user_id();
        
        // 检查游客购买权限
        $can_not_login_pay = apply_filters('islide_can_not_login_buy', $data);

        // 验证登录状态
        if (!$user_id && !(int)$can_not_login_pay) {
            return array('error' => __('请先登录', 'islide'));
        }

        // 数据处理与验证
        $data['order_type'] = isset($data['order_type']) ? trim($data['order_type'], " \t\n\r\0\x0B\xC2\xA0") : '';
        $data['pay_type'] = isset($data['pay_type']) ? trim($data['pay_type'], " \t\n\r\0\x0B\xC2\xA0") : '';
        $data['order_count'] = isset($data['order_count']) ? (int)$data['order_count'] : 1;
        $data['user_id'] = $user_id;

        // 验证订单类型
        $order_type = self::get_order_type();
        if (empty($data['order_type']) || !isset($order_type[$data['order_type']])) {
            return array('error' => __('订单类型错误', 'islide'));
        }

        // 验证支付类型
        if (!$data['pay_type'] || isset($data['_pay_type'])) {
            return array('error' => __('订单类型错误', 'islide'));
        }

        // 验证商品数量
        if ($data['order_count'] < 1) {
            return array('error' => __('请选择购买至少一个商品！', 'islide'));
        }

        // 验证订单金额
        if (isset($data['order_price']) && $data['order_price'] < 0) {
            return array('error' => __('订单金额错误！', 'islide'));
        }

        // 构建订单
        $data = self::build_order_action($data);

        // 处理结果
        if (!isset($data['error'])) {
            // 选择支付平台
            return Pay::pay($data);
        } else {
            return $data;
        }
    }

    /**
     * 订单操作
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  array       处理结果或错误信息
     */
    public static function build_order_action($data)
    {
        // 获取当前用户ID
        $user_id = get_current_user_id();
        
        // 订单数据处理
        $data['post_id'] = isset($data['post_id']) ? (int)$data['post_id'] : 0;
        $data['chapter_id'] = isset($data['chapter_id']) ? (int)$data['chapter_id'] : 0;
        $data['payment_method'] = $data['pay_type'];

        // 判断支付类型
        $pay_type = Pay::pay_type($data['pay_type']);
        if (isset($pay_type['error'])) {
            return $pay_type;
        }
        $data['pay_type'] = $pay_type['type'];

        // 创建订单号
        $order_id = self::build_order_no();
        $data['order_id'] = $order_id;
        
        // 检查支付金额
        $order_price = apply_filters('islide_order_price', $data);
        if (isset($order_price['error']) || is_array($order_price)) {
            return $order_price;
        }

        // 订单金额
        $data['order_price'] = $order_price;

        // 计算订单总金额
        if ($data['order_type'] === 'g' || $data['order_type'] === 'coupon') {
            // 合并支付类型
        } else if ($data['order_type'] === 'shop') {
            // 商城商品类型
            $shop_data = self::get_selected_shop_data($data);
            $vip_discount = User::get_vip_discount();
            $shop_data['vip_discount'] = $vip_discount;
            $shop_data_result = apply_filters('check_shop_purchase_data', $data, $shop_data);
            
            if ($shop_data_result['status'] === false) {
                return array('error' => __($shop_data_result['message'], 'islide'));
            }
            
            $total = $shop_data_result['total_price'];
        } else {
            // 标准订单类型，单价 * 数量
            $total = bcmul($data['order_price'], $data['order_count'], 2);
        }
        
        // 验证订单总金额
        if (isset($data['order_total']) && (float)$data['order_total'] !== (float)$total) {
            return array('error' => __('订单总金额错误', 'islide'));
        }

        $data['order_total'] = $total;

        // 处理标题
        if (isset($data['title'])) {
            $data['title'] = islide_get_desc(0, 30, urldecode($data['title']));
        }
        
        // 金额类型，0为现金，1为积分
        $data['money_type'] = $data['pay_type'] == 'credit' ? 1 : 0;

        // 商品类型，0为虚拟物品，1为实物
        $data['order_commodity'] = (int)($data['order_commodity'] ?? 0);

        // 处理订单关键字，防止注入
        $data['order_key'] = isset($data['order_key']) ? 
                             esc_sql(str_replace(array('{{', '}}'), '', sanitize_text_field($data['order_key']))) : '';

        // 处理订单数值，防止注入
        $data['order_value'] = isset($data['order_value']) && $data['order_value'] != '' ? 
                               urldecode($data['order_value']) : '';
        $data['order_value'] = esc_sql(str_replace(array('{{', '}}'), '', sanitize_text_field($data['order_value'])));

        // 处理订单内容，防止注入
        $data['order_content'] = isset($data['order_content']) ? urldecode($data['order_content']) : '';
        $data['order_content'] = isset($data['order_content']) && $data['order_content'] != '' ? 
                                esc_sql(str_replace(array('{{', '}}'), '', sanitize_text_field($data['order_content']))) : '';

        // 处理订单地址，防止注入
        $data['order_address'] = isset($data['order_address']) ? 
                                esc_sql(str_replace(array('{{', '}}'), '', sanitize_text_field($data['order_address']))) : '';

        // 安全检查
        $check_data = serialize($data);
        if (strlen($check_data) > 50000) {
            return array('error' => __('非法操作', 'islide'));
        }

        // 创建订单记录
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_order';
        $result = $wpdb->insert(
            $table_name,
            array(
                'order_id' => $data['order_id'],
                'user_id' => $data['user_id'],
                'post_id' => $data['post_id'],
                'chapter_id' => $data['chapter_id'],
                'order_type' => $data['order_type'],
                'order_commodity' => $data['order_commodity'],
                'order_state' => 0, // 等待付款
                'order_date' => current_time('mysql'),
                'order_count' => $data['order_count'],
                'order_price' => $data['order_price'],
                'order_total' => $data['order_total'],
                'money_type' => $data['money_type'],
                'order_key' => $data['order_key'],
                'order_value' => $data['order_value'],
                'order_content' => $data['order_content'],
                'pay_type' => $data['pay_type'],
                'tracking_number' => '',
                'order_address' => '', // 待处理
                'ip_address' => islide_get_user_ip(),
                'order_mobile' => isset($data['order_mobile']) ? $data['order_mobile'] : '',
            ),
            array(
                '%s', '%d', '%d', '%d', '%s', '%d', '%d', '%s', '%d', '%f',
                '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
            )
        );
        
        if (!$result) {
            return array('error' => __('创建订单失败', 'islide'));
        }

        return $data;
    }

    /**
     * 删除订单
     *
     * @author  ifyn
     * @param   int    $user_id  用户ID
     * @param   string $order_id 订单ID
     * @return  array|bool       删除成功返回true，失败返回错误信息数组
     */
    public static function delete_order($user_id, $order_id)
    {
        // 参数验证
        $user_id = (int)$user_id;
        $current_user_id = (int)get_current_user_id();

        // 检查用户是否登录
        if (!$user_id && !$current_user_id) {
            return array('error' => '请先登录');
        }

        // 检查权限
        if ($user_id !== $current_user_id && !user_can($current_user_id, 'administrator')) {
            return array('error' => '权限不足');
        }

        // 使用当前用户ID
        $user_id = $current_user_id;

        // 获取订单
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_order';
        $order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT user_id, order_state FROM $table_name WHERE user_id = %d AND order_id = %s", 
                $user_id, 
                $order_id
            ), 
            ARRAY_A
        );

        // 检查订单是否存在
        if (empty($order)) {
            return array('error' => '没有找到这个订单');
        }

        // 处理订单状态
        if ((int)$order['order_state'] === 0) {
            // 未支付订单直接删除
            if ($wpdb->delete(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'order_id' => $order_id
                )
            )) {
                return true; // 成功删除订单
            }
        } else {
            // 已支付订单修改状态为已删除
            if ($wpdb->update(
                $table_name,
                array(
                    'order_state' => 5
                ),
                array(
                    'user_id' => $user_id,
                    'order_id' => $order_id
                )
            )) {
                return true; // 成功删除订单
            }
        }

        return array('error' => '删除订单失败');
    }

    /**
     * 订单成功支付确认
     *
     * @author  ifyn
     * @param   string    $order_id 订单ID
     * @param   float|int $money    订单金额
     * @return  string|array        成功返回'success'，失败返回错误信息数组
     */
    public static function order_confirm($order_id, $money)
    {
        // 安全检查
        if (!islide_check_repo(md5($order_id))) {
            return array('error' => __('订单回调错误', 'islide'));
        }

        // 参数验证
        if (empty($order_id)) {
            return array('error' => __('订单数据不完整', 'islide'));
        }

        // 获取订单数据
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_order';
        $order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE order_id = %s",
                $order_id
            ),
            ARRAY_A
        );

        // 检查订单是否存在
        if (empty($order)) {
            return array('error' => __('没有找到这个订单', 'islide'));
        }

        // 检查订单是否已支付
        if ((int)$order['order_state'] !== 0) {
            return 'success'; // 已经支付成功，直接返回
        }

        // 验证订单金额
        if ($money && (float)$money != $order['order_total']) {
            return array('error' => __('订单金额错误', 'islide'));
        }

        // 确定订单状态
        $order_state = 1; // 默认为已付款未发货
        if ((int)$order['order_commodity'] === 0 || $order['order_type'] == 'goumai') {
            $order_state = 3; // 虚拟商品直接设为已签收
        }

        // 更新订单状态
        if (apply_filters('islide_update_orders', array('order_state' => $order_state, 'order' => $order))) {
            // 触发订单支付成功回调
            do_action('islide_order_notify_return', $order);
            return 'success';
        }

        return array('error' => __('回调错误', 'islide'));
    }

    /**
     * 支付成功回调处理
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  mixed       处理结果
     */
    public static function order_notify_return($data)
    {
        // 参数验证
        if (empty($data)) {
            return array('error' => __('更新订单失败', 'islide'));
        }

        // 构造回调方法名
        $order_type = 'callback_' . $data['order_type'];

        // 检查对应的回调方法是否存在
        if (!method_exists(__CLASS__, $order_type)) {
            // 方法不存在，触发对应的action钩子
            return do_action($order_type, $data);
        }

        // 调用对应的回调方法
        return self::$order_type($data);
    }

    /**
     * 认证服务回调
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  mixed       处理结果
     */
    public static function callback_verify($data)
    {
        return apply_filters('islide_order_callback_verify', $data);
    }

    /**
     * 支付加入圈子回调
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  mixed       处理结果或错误信息
     */
    public static function callback_join_circle($data)
    {
        // 检查用户ID
        if (empty($data['user_id'])) {
            return array('error' => '用户ID不能为空');
        }

        // 获取圈子支付套餐数据
        $pay_group = get_term_meta($data['post_id'], 'islide_circle_pay_group', true);
        $pay_group = is_array($pay_group) ? $pay_group : array();

        $order_key = $data['order_key'];

        // 检查套餐是否存在
        if (!isset($pay_group[$order_key])) {
            return array('error' => '圈子套餐不存在');
        }

        $group_info = $pay_group[$order_key];

        // 设置加入时间和到期时间
        $start_time = current_time('mysql');
        $days = isset($group_info['time']) ? intval($group_info['time']) : 30;
        
        // 处理永久会员
        if ($days === 0) {
            $end_time = '0000-00-00 00:00:00';
        } else {
            $end_time = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        }

        // 构建加入圈子数据
        $insert_data = array(
            'id'           => 0, // 插入时可为 0 或不写（交由 auto_increment 管理）
            'user_id'      => (int)$data['user_id'],
            'circle_id'    => (int)$data['post_id'],
            'circle_role'  => 'member', // 默认角色为 member，如有其他角色可以扩展
            'join_date'    => $start_time,
            'end_date'     => $end_time,
            'circle_key'   => sanitize_text_field($order_key),
            'circle_value' => isset($group_info['name']) ? sanitize_text_field($group_info['name']) : '', // 或你想存的值，比如时间/价格等
        );

        // 插入或更新圈子关系数据
        CircleRelate::update_data($insert_data);
        
        // 更新用户元数据，便于快速查询
        $user_circle_meta = get_user_meta($data['user_id'], 'islide_joined_circles', true);
        $user_circle_meta = is_array($user_circle_meta) ? $user_circle_meta : array();

        $user_circle_meta[$data['post_id']] = array(
            'circle_id' => (int)$data['post_id'],
            'end_date'  => $end_time
        );

        update_user_meta($data['user_id'], 'islide_joined_circles', $user_circle_meta);

        return apply_filters('islide_order_callback_join_circle', $data);
    }
    
    /**
     * 回调文章内容隐藏阅读
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  mixed       处理结果
     */
    public static function callback_post_neigou($data)
    {
        // 非游客支付处理
        if (!empty($data['user_id'])) {
            // 获取已购买用户列表
            $buy_data = get_post_meta($data['post_id'], 'islide_buy_user', true);
            $buy_data = is_array($buy_data) ? $buy_data : array();

            // 检查用户是否已在列表中
            if (!in_array($data['user_id'], $buy_data)) {
                $buy_data[] = (int)$data['user_id'];
                update_post_meta($data['post_id'], 'islide_buy_user', $buy_data);
            }
        }

        return apply_filters('islide_order_callback_post_neigou', $buy_data, $data);
    }

    /**
     * 余额充值回调
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  mixed       处理结果
     */
    public static function callback_money_chongzhi($data)
    {
        return apply_filters('islide_order_callback_money_chongzhi', $data);
    }

    /**
     * 积分充值回调
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  mixed       处理结果
     */
    public static function callback_credit_chongzhi($data)
    {
        return apply_filters('islide_order_callback_credit_chongzhi', $data);
    }

    /**
     * VIP购买回调
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  mixed       处理结果
     */
    public static function callback_vip_goumai($data)
    {
        // 参数验证
        if (empty($data['user_id']) || empty($data['order_key'])) {
            return array('error' => '参数不完整');
        }

        // 获取用户当前VIP状态
        $user_vip = get_user_meta($data['user_id'], 'islide_vip', true);

        // 开通VIP天数
        $vip_day = (int)$data['order_value'];
        $end = '';

        // 处理VIP时间
        if ($user_vip && $user_vip === $data['order_key']) {
            // 续费同类型VIP
            $user_vip_exp_date = get_user_meta($data['user_id'], 'islide_vip_exp_date', true);

            if ($vip_day == 0) {
                $end = 0; // 续费永久
            } else if ((string)$user_vip_exp_date !== '0') {
                // 在现有时间基础上增加
                $end = (int)$user_vip_exp_date + 86400 * $vip_day;
            } else {
                // 永久会员不需要增加时间
                $end = wp_strtotime('+' . $vip_day . ' day');
            }
        } else {
            // 新开通VIP
            update_user_meta($data['user_id'], 'islide_vip', $data['order_key']);
            
            if ($vip_day == 0) {
                $end = 0; // 开通永久
            } else {
                $end = wp_strtotime('+' . $vip_day . ' day');
            }
        }

        // 更新VIP时间
        update_user_meta($data['user_id'], 'islide_vip_exp_date', $end);

        return apply_filters('islide_callback_vip_goumai', $data['order_key'], $data);
    }

    /**
     * 文章资源下载回调
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  mixed       处理结果
     */
    public static function callback_xiazai($data)
    {
        // 非游客支付处理
        if (!empty($data['user_id'])) {
            // 获取已购买用户列表
            $buy_data = get_post_meta($data['post_id'], 'islide_download_buy', true);
            $buy_data = is_array($buy_data) ? $buy_data : array();

            // 初始化下载类型的数组
            $buy_data[$data['order_key']] = isset($buy_data[$data['order_key']]) && is_array($buy_data[$data['order_key']]) 
                                          ? $buy_data[$data['order_key']] 
                                          : array();
            
            // 添加用户到购买列表
            $buy_data[$data['order_key']][] = (int)$data['user_id'];

            // 更新元数据
            update_post_meta($data['post_id'], 'islide_download_buy', $buy_data);
        }

        return apply_filters('islide_order_callback_xiazai', $buy_data, $data);
    }

    /**
     * 视频购买回调
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  mixed       处理结果
     */
    public static function callback_video($data)
    {
        // 非游客支付处理
        if (!empty($data['user_id'])) {
            // 按视频组处理
            if (!empty($data['order_key'])) {
                // 获取已购买用户列表
                $buy_data = get_post_meta($data['post_id'], 'islide_video_buy_group', true);
                $buy_data = is_array($buy_data) ? $buy_data : array();

                // 初始化视频组的数组
                $buy_data[$data['order_key']] = isset($buy_data[$data['order_key']]) && is_array($buy_data[$data['order_key']]) 
                                             ? $buy_data[$data['order_key']] 
                                             : array();
                
                // 添加用户到购买列表
                if (!in_array($data['user_id'], $buy_data[$data['order_key']])) {
                    $buy_data[$data['order_key']][] = (int)$data['user_id'];
                    update_post_meta($data['post_id'], 'islide_video_buy_group', $buy_data);
                }
            } else {
                // 单视频处理
                $buy_data = get_post_meta($data['post_id'], 'islide_video_buy', true);
                $buy_data = is_array($buy_data) ? $buy_data : array();

                // 添加用户到购买列表
                if (!in_array($data['user_id'], $buy_data)) {
                    $buy_data[] = (int)$data['user_id'];
                    update_post_meta($data['post_id'], 'islide_video_buy', $buy_data);
                }
            }
        }

        return apply_filters('islide_order_callback_video', $buy_data, $data);
    }

    /**
     * 获取订单类型
     *
     * @author  ifyn
     * @param   string $type 要获取的订单类型名称，为空则返回所有类型
     * @return  string|array 指定类型的名称或所有类型的关联数组
     */
    public static function get_order_type($type = '')
    {
        $types = apply_filters('islide_order_type', array(
            'product' => '产品购买',
            'shop' => '商品购买',
            'post_neigou' => '文章内购',
            'xiazai' => '资源下载',
            'money_chongzhi' => '余额充值',
            'vip_goumai' => 'VIP购买',
            'credit_chongzhi' => '积分购买',
            'video' => '视频购买',
            'join_circle' => '支付入圈',
            'verify' => '认证',
            'sponsor' => '赞助'
        ));

        return isset($types[$type]) ? $types[$type] : $types;
    }

    /**
     * 获取订单状态
     *
     * @author  ifyn
     * @param   int|string $state 要获取的状态代码，为空则返回所有状态
     * @return  string|array      指定状态的名称或所有状态的关联数组
     */
    public static function get_order_state($state = '')
    {
        $states = array(
            0 => '待支付',
            1 => '已付款未发货',
            2 => '已发货',
            3 => '已完成',
            4 => '已退款',
            5 => '已删除'
        );
        return isset($states[$state]) ? $states[$state] : $states;
    }

    /**
     * 获取用户订单列表数据
     *
     * @author  ifyn
     * @param   int $user_id 用户ID
     * @param   int $paged   页码
     * @param   int $state   订单状态（6表示除已退款和已删除外的所有状态）
     * @return  array        订单列表数据和分页信息，或错误信息
     */
    public static function get_user_orders($user_id, $paged, $state)
    {
        // 参数验证
        $user_id = (int)$user_id;
        $current_user_id = (int)get_current_user_id();
        $state = (int)$state;
        $paged = max(1, (int)$paged);

        // 检查用户是否登录
        if (!$user_id && !$current_user_id) {
            return array('error' => '请先登录');
        }

        // 验证订单状态
        $_state = self::get_order_state($state);
        if ((!$_state || $state == 4 || $state == 5) && $state != 6) {
            return array('error' => '错误订单状态');
        }

        // 使用当前用户ID
        $user_id = $current_user_id;

        // 分页参数
        $size = 3;
        $offset = ($paged - 1) * $size;

        // 准备查询
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_order';
        $query = '';
        
        // 构建状态条件
        if ($state === 6) {
            // 全部状态，排除已退款和已删除
            $query .= $wpdb->prepare(" AND order_state != %d AND order_state != %d", 4, 5);
        } else {
            // 特定状态
            $query .= $wpdb->prepare(" AND order_state = %d", $state);
        }

        // 获取订单数据
        $orders = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name
                WHERE user_id = %d $query 
                ORDER BY order_date DESC 
                LIMIT %d,%d",
                $user_id,
                $offset,
                $size
            ),
            ARRAY_A
        );

        // 获取符合条件的订单总数
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name
                WHERE user_id = %d $query",
                $user_id
            )
        );

        // 格式化订单数据
        $data = array();
        foreach ($orders as $value) {
            $order_state = self::get_order_state($value['order_state']);
            $order_type = self::get_order_type($value['order_type']);
            $product = self::get_order_product($value);

            $data[] = array(
                'id' => (int)$value['id'],
                'post_id' => (int)$value['post_id'],
                'user_id' => (int)$value['user_id'],
                'order_id' => $value['order_id'],
                'order_price' => (float)$value['order_price'],
                'order_total' => (float)$value['order_total'],
                'order_count' => (int)$value['order_count'],
                'order_date' => $value['order_date'],
                '_order_state' => $order_state,
                'order_state' => (int)$value['order_state'],
                '_order_type' => $order_type,
                'order_type' => $value['order_type'],
                'pay_type' => $value['pay_type'],
                'product' => $product
            );
        }

        // 返回结果
        return array(
            'pages' => ceil($count / $size),
            'count' => (int)$count,
            'data' => $data
        );
    }

    /**
     * 获取订单产品信息
     *
     * @author  ifyn
     * @param   array $order 订单数据
     * @return  array        产品信息
     */
    public static function get_order_product($order)
    {
        if (empty($order) || !is_array($order)) {
            return array(
                'name' => '未知商品',
                'count' => '',
                'link' => '#',
                'whisper' => '#',
                'thumb' => '',
            );
        }

        $type = $order['order_type'];
        $post_id = (int)$order['post_id'];

        // 处理余额充值类型
        if ($type == 'money_chongzhi') {
            return array(
                'name' => '余额充值 ' . ((int)$order['order_value'] ?: (int)$order['order_total']) . ' 元',
                'count' => '',
                'link' => '/account/center',
                'whisper' => '/message?whisper=1',
                'thumb' => 'https://www.islide.com/wp-content/uploads/2023/09/余额.svg',
            );
        } 
        // 处理积分充值类型
        else if ($type == 'credit_chongzhi') {
            return array(
                'name' => '购买 ' . ((int)$order['order_value'] ?: (int)$order['order_total']) . ' 积分',
                'count' => ((int)$order['order_value'] ?: (int)$order['order_total']) . ' 积分 x 1',
                'link' => '/account/center',
                'whisper' => '/message?whisper=1',
                'thumb' => 'https://www.islide.com/wp-content/uploads/2023/09/余额-1.svg',
            );
        } 
        // 处理VIP购买类型
        else if ($type == 'vip_goumai') {
            $roles = User::get_user_roles();
            $vip = isset($roles[$order['order_key']]) ? $roles[$order['order_key']] : array('name' => '会员', 'icon' => '', 'image' => '');
            
            return array(
                'name' => $vip['name'],
                'count' => (int)$order['order_value'] === 0 ? '永久' : $order['order_value'] . '天',
                'link' => '/vip',
                'whisper' => '/message?whisper=1',
                'thumb' => !empty($vip['icon']) ? $vip['icon'] : $vip['image'],
            );
        } 
        // 处理加入圈子类型
        else if ($type == 'join_circle') {
            $circle = \islide\Modules\Common\Circle::get_circle_data($post_id);
            return array(
                'name' => '加入' . (isset($circle['name']) ? $circle['name'] : '圈子'),
                'count' => (int)$order['order_value'] === 0 ? '永久' : $order['order_value'] . '天',
                'link' => isset($circle['link']) ? $circle['link'] : '#',
                'whisper' => '/message?whisper=1',
                'thumb' => isset($circle['icon']) ? $circle['icon'] : '',
            );
        } 
        // 处理商城商品类型
        else if ($type == 'shop') {
            $author_id = get_post_field('post_author', $post_id);
            return array(
                'name' => get_the_title($post_id),
                'count' => 'x ' . (int)$order['order_count'],
                'link' => '/' . get_post_type($post_id) . '/' . $post_id,
                'whisper' => '/message?whisper=' . $author_id,
                'thumb' => islide_get_thumb(array(
                    'url' => \islide\Modules\Common\Post::get_post_thumb($post_id), 
                    'width' => 100, 
                    'height' => 100
                )),
            );
        } 
        // 处理其他类型
        else {
            // 如果有章节ID，使用章节ID代替帖子ID
            if (!empty($order['chapter_id'])) {
                $post_id = (int)$order['chapter_id'];
            }

            $author_id = get_post_field('post_author', $post_id);
            return array(
                'name' => get_the_title($post_id),
                'count' => 'x 1',
                'link' => '/' . get_post_type($post_id) . '/' . $post_id,
                'whisper' => '/message?whisper=' . $author_id,
                'thumb' => islide_get_thumb(array(
                    'url' => \islide\Modules\Common\Post::get_post_thumb($post_id), 
                    'width' => 100, 
                    'height' => 100
                )),
            );
        }
    }

    /**
     * 获取选中的商品规格数据
     *
     * @author  ifyn
     * @param   array $data 订单数据
     * @return  array       商品规格信息
     */
    public static function get_selected_shop_data($data) {
        // 验证参数
        if (empty($data) || empty($data['post_id'])) {
            return array('price' => 0, 'stock' => 0, 'limit' => 0, 'sold' => 0, 'discount' => 100);
        }
        
        // 获取商品元数据
        $shop_metabox = get_post_meta($data['post_id'], 'single_shop_metabox', true);
        if (empty($shop_metabox) || !is_array($shop_metabox)) {
            return array('price' => 0, 'stock' => 0, 'limit' => 0, 'sold' => 0, 'discount' => 100);
        }
    
        // 检查是否是多规格商品
        $is_multi_spec = isset($shop_metabox['is_shop_multi']) ? $shop_metabox['is_shop_multi'] : 0;
    
        // 默认商品数据
        $details = array(
            'price' => 0,
            'stock' => 0,
            'limit' => 0,
            'sold' => 0,
            'discount' => 100
        );
    
        // 处理多规格商品
        if ($is_multi_spec == "1" && isset($shop_metabox['specifications']) && is_array($shop_metabox['specifications'])) {
            // 检查批量设置
            foreach ($shop_metabox['specifications'] as $sku) {
                if (isset($sku['name']) && $sku['name'] === '批量设置' && 
                    isset($sku['price']) && isset($sku['stock'])) {
                    return array(
                        'price' => (float)$sku['price'],
                        'stock' => (int)$sku['stock'],
                        'limit' => (int)($sku['limit'] ?? 0),
                        'sold' => (int)($sku['sold'] ?? 0),
                        'discount' => (int)($sku['discount'] ?? 100),
                    );
                }
            }
    
            // 处理特定规格
            if (isset($data['order_spec']) && is_array($data['order_spec'])) {
                // 拼接规格组合
                $selected_spec = implode(' / ', array_map(
                    function($key, $value) {
                        return "$key:$value";
                    },
                    array_keys($data['order_spec']),
                    $data['order_spec']
                ));
                
                // 查找匹配规格
                foreach ($shop_metabox['specifications'] as $sku) {
                    if (isset($sku['name']) && $sku['name'] === $selected_spec) {
                        return array(
                            'price' => (float)($sku['price'] ?? 0),
                            'stock' => (int)($sku['stock'] ?? 0),
                            'limit' => (int)($sku['limit'] ?? 0),
                            'sold' => (int)($sku['sold'] ?? 0),
                            'discount' => (int)($sku['discount'] ?? 100),
                        );
                    }
                }
            }
        } 
        // 处理单规格商品
        else {
            return array(
                'price' => (float)($shop_metabox['islide_shop_price'] ?? 0),
                'stock' => (int)($shop_metabox['islide_shop_stock'] ?? 0),
                'limit' => (int)($shop_metabox['islide_single_limit'] ?? 0),
                'sold' => (int)($shop_metabox['islide_shop_count'] ?? 0),
                'discount' => (int)($shop_metabox['islide_single_discount'] ?? 100),
            );
        }
    
        // 无匹配规格，返回默认值
        return $details;
    }

    /**
     * 检查用户是否购买过指定内容
     * @param int $post_id 内容ID
     * @param int $user_id 用户ID
     * @return bool 是否购买过
     */
    public static function islide_post_neigou_has_purchased($post_id, $user_id) {
        // 获取已购买用户列表
        $buy_users = get_post_meta($post_id, 'islide_buy_user', true);
        
        // 处理数据格式，确保是数组
        $buy_users = is_array($buy_users) ? $buy_users : array();
        
        // 检查用户是否在购买列表中
        return in_array((int)$user_id, $buy_users);
    }

    /**
     * 获取订单列表API处理函数
     *
     * @author  ifyn
     * @param   WP_REST_Request $request 请求对象
     * @return  array                    订单列表数据和分页信息
     */
    public static function get_order_list_api($request) {
        global $wpdb;
        $params = $request->get_params();

        $table = $wpdb->prefix . 'islide_order';

        $paged = isset($params['paged']) ? max(1, intval($params['paged'])) : 1;
        $size = isset($params['size']) ? max(1, intval($params['size'])) : 20;
        $offset = ($paged - 1) * $size;

        $where = 'WHERE 1=1';
        $args = [];

        // 搜索关键词
        if (!empty($params['search'])) {
            $search = '%' . $wpdb->esc_like($params['search']) . '%';
            $where .= " AND (order_id LIKE %s OR user_id LIKE %s)";
            $args[] = $search;
            $args[] = $search;
        }

        // 筛选订单状态
        if (isset($params['order_state']) && $params['order_state'] !== '') {
            $where .= " AND order_state = %d";
            $args[] = intval($params['order_state']);
        }

        // 筛选订单类型
        if (!empty($params['order_type'])) {
            $where .= " AND order_type = %s";
            $args[] = sanitize_text_field($params['order_type']);
        }

        // 指定用户
        if (!empty($params['user_id'])) {
            $where .= " AND user_id = %d";
            $args[] = intval($params['user_id']);
        }

        $orderby = isset($params['orderby']) ? sanitize_sql_orderby($params['orderby']) : 'id';
        $order = strtoupper($params['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        // 总数查询
        $total_sql = "SELECT COUNT(*) FROM $table $where";
        $total = $wpdb->get_var($wpdb->prepare($total_sql, ...$args));
        $pages = ceil($total / $size);

        // 查询数据
        $query = "SELECT * FROM $table $where ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $args[] = $size;
        $args[] = $offset;
        $results = $wpdb->get_results($wpdb->prepare($query, ...$args), ARRAY_A);

        // 处理附加字段
        foreach ($results as &$row) {
            $user = get_userdata($row['user_id']);
            $row['user_display'] = $user ? $user->display_name : '游客';
            $row['post_title'] = get_the_title($row['post_id']);
            $row['order_state_text'] = ['待支付', '已付款未发货', '已发货', '已完成', '已退款', '已删除'][$row['order_state']] ?? '';
        }

        return [
            'data'  => $results,
            'pages' => $pages,
            'count' => (int) $total,
            'paged' => $paged,
        ];
    }

    /**
     * 更新订单字段API处理函数
     *
     * @author  ifyn
     * @param   WP_REST_Request $request 请求对象
     * @return  array                    更新结果
     */
    public static function update_order_field($request) {
        global $wpdb;

        $table = $wpdb->prefix . 'islide_order';

        $params = $request->get_json_params();

        $id    = isset($params['id']) ? intval($params['id']) : 0;
        $field = sanitize_key($params['field']);
        $value = sanitize_text_field($params['value']);

        if (!$id || !$field) {
          return  array('error' => '参数不完整');
        }

        // 允许更新的字段白名单
        $allowed_fields = ['order_state', 'tracking_number', 'order_address'];
        if (!in_array($field, $allowed_fields)) {
          return  array('error' => '不允许更新该字段');
        }

        // 更新操作
        $result = $wpdb->update(
            $table,
            [$field => $value],
            ['id' => $id],
            ['%s'],
            ['%d']
        );

        if ($result === false) {
            return array('error' => '数据库更新失败');
        }

        return [
            'success' => true,
            'message' => '订单已更新',
            'field'   => $field,
            'value'   => $value
        ];
    }
    
    /**
     * 获取订单统计数据
     *
     * @author  ifyn
     * @return  array 订单统计信息
     */
    public static function get_order_statistics() {
        global $wpdb;
        $table = $wpdb->prefix . 'islide_order';

        // 获取总订单数
        $total_orders = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        // 获取今日订单数
        $today = current_time('Y-m-d'); // WordPress 当前时区的日期
        $today_orders = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE DATE(order_date) = %s",
            $today
        ));

        return [
            'total' => $total_orders,
            'today' => $today_orders,
        ];
    }

    /**
     * 保存用户收货地址
     *
     * @author  ifyn
     * @param   array $data 地址数据
     * @return  array       保存结果
     */
    public static function islide_save_address($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'islide_address';

        $user_id = get_current_user_id();
        if (!$user_id) {
            return array('error' => '请先登录');
        }

        // 通用保存字段
        $save_data = [
            'user_id'        => $user_id,
            'name'           => sanitize_text_field($data['name']),
            'phone'          => sanitize_text_field($data['phone']),
            'province'       => sanitize_text_field($data['province']),
            'city'           => sanitize_text_field($data['city']),
            'district'       => sanitize_text_field($data['district']),
            'address_detail' => sanitize_text_field($data['address_detail']),
            'postal_code'    => sanitize_text_field($data['postal_code'] ?? ''),
            'is_default'     => isset($data['is_default']) && $data['is_default'] ? 1 : 0,
        ];

        if (!empty($data['id'])) {
            // 有 id，是编辑模式
            $id = intval($data['id']);
            $address = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d AND user_id = %d", $id, $user_id));
            if (!$address) {
                return array('error' => '地址不存在或无权限');
            }

            // 更新
            $wpdb->update($table, $save_data, ['id' => $id, 'user_id' => $user_id]);

            // 如果是默认地址，需要取消其他地址的默认
            if ($save_data['is_default']) {
                $wpdb->query($wpdb->prepare("UPDATE $table SET is_default = 0 WHERE user_id = %d AND id != %d", $user_id, $id));
            }

            return ['success' => true, 'message' => '地址更新成功'];

        } else {
            // 没 id，是添加模式
            $wpdb->insert($table, $save_data);
            $insert_id = $wpdb->insert_id;

            // 新增后，如果是默认地址，需要取消其他地址的默认
            if ($save_data['is_default']) {
                $wpdb->query($wpdb->prepare("UPDATE $table SET is_default = 0 WHERE user_id = %d AND id != %d", $user_id, $insert_id));
            }

            return ['success' => true, 'message' => '地址添加成功', 'address_id' => $insert_id];
        }
    }

    /**
     * 删除用户收货地址
     *
     * @author  ifyn
     * @param   int $id 地址ID
     * @return  array   删除结果
     */
    public static function islide_delete_address($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'islide_address';

        $user_id = get_current_user_id();
        if (!$user_id) {
            return array('error' => '请先登录');
        }

        $address = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d AND user_id = %d", $id, $user_id));
        if (!$address) {
            return array('error' => '地址不存在');
        }

        $wpdb->delete($table, ['id' => $id, 'user_id' => $user_id]);

        return ['success' => true];
    }

    /**
     * 获取单个收货地址
     *
     * @author  ifyn
     * @param   int $id 地址ID
     * @return  array   地址数据或错误信息
     */
    public static function islide_get_address($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'islide_address';

        $user_id = get_current_user_id();
        if (!$user_id) {
            return array('error' => '请先登录');
        }

        $id = intval($id);

        // 查询这条地址
        $address = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d AND user_id = %d", $id, $user_id), ARRAY_A);

        if (!$address) {
            return array('error' => '地址不存在或无权限');
        }

        return ['success' => true, 'data' => $address];
    }

    /**
     * 获取用户收货地址列表
     *
     * @author  ifyn
     * @return  array 地址列表
     */
    public static function islide_get_address_list() {
        global $wpdb;
        $table = $wpdb->prefix . 'islide_address';

        $user_id = get_current_user_id();
        if (!$user_id) {
            return [];
        }

        $list = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d ORDER BY is_default DESC, id DESC", $user_id));

        return $list;
    }

    /**
     * 设置默认收货地址
     *
     * @author  ifyn
     * @param   int $id 地址ID
     * @return  array   处理结果
     */
    public static function islide_set_default_address($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'islide_address';

        $user_id = get_current_user_id();
        if (!$user_id) {
            return array('error' => '请先登录');
        }

        $address = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d AND user_id = %d", $id, $user_id));
        if (!$address) {
            return array('error' => '地址不存在');
        }

        // 全部取消默认
        $wpdb->update($table, ['is_default' => 0], ['user_id' => $user_id]);

        // 设置当前为默认
        $wpdb->update($table, ['is_default' => 1], ['id' => $id, 'user_id' => $user_id]);

        return ['success' => true];
    }
}