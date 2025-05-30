<?php
/**
 * 卡密管理类
 * 
 * 用于处理卡密充值、生成、查询等功能
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;
use islide\Modules\Common\User;
use islide\Modules\Common\Orders;

class Card
{
    /**
     * 使用卡密进行充值
     * 
     * @author  ifyn
     * @param   string $code 卡密代码
     * @return  array 处理结果，成功返回成功信息，失败返回错误信息
     */
    public static function card_pay($code)
    {
        $user_id = get_current_user_id();
        // 移除所有可能的空白字符
        $code = trim($code, " \t\n\r\0\x0B\xC2\xA0");

        if (!$user_id) {
            return array('error' => '请先登录');
        }
        
        if (empty($code)) {
            return array('error' => '激活码未填写');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_card';

        // 查询卡密信息
        $res = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE card_code=%s",
                $code
            ),
            ARRAY_A
        );

        // 卡密验证
        if (empty($res)) {
            return array('error' => '激活码不存在');
        } 
        
        if ((int) $res['status'] === 1) {
            return array('error' => '激活码已被使用');
        } 
        
        if (empty($res['type']) || $res['type'] == 'invite') {
            return array('error' => '激活码类型错误');
        }

        // 更新卡密状态
        $update_result = $wpdb->update(
            $table_name,
            array(
                'status' => 1,
                'user_id' => (int) $user_id
            ),
            array('id' => (int) $res['id']),
            array('%d', '%d'),
            array('%d')
        );
        
        if ($update_result) {
            if ((float) $res['value'] <= 0) {
                return array('error' => '金额错误');
            }

            $type = 'callback_' . $res['type'];

            // 如果不存在对应的回调方法，则触发动作钩子
            if (!method_exists(__CLASS__, $type)) {
                return do_action($type, $res);
            }

            // 调用对应类型的回调方法
            return self::$type($res);
        }

        return array('error' => '网络错误，请稍后重试');
    }

    /**
     * 余额卡密充值回调
     * 
     * @author  ifyn
     * @param   array $data 卡密数据
     * @return  array 处理结果
     */
    public static function callback_money($data)
    {
        if (!isset($data['value']) || !is_numeric($data['value'])) {
            return array('error' => '卡密金额无效');
        }
        
        $value = (float) $data['value'];
        
        if ($value <= 0) {
            return array('error' => '卡密金额必须大于0');
        }
        
        // 创建订单
        $order_res = Orders::build_order(array(
            'order_price' => (int) $value,
            'order_type' => 'money_chongzhi',
            'title' => '余额充值',
            'pay_type' => 'card',
        ));

        if (isset($order_res['error'])) {
            return $order_res;
        }

        // 确认订单
        $order_confirm = Orders::order_confirm($order_res['order_id'], (int) $value);

        if (isset($order_confirm['error'])) {
            return $order_confirm;
        }

        return array('msg' => "您已成功使用激活码充值余额 {$value} 元~");
    }

    /**
     * 积分卡密充值回调
     * 
     * @author  ifyn
     * @param   array $data 卡密数据
     * @return  array 处理结果
     */
    public static function callback_credit($data)
    {
        if (!isset($data['value']) || !is_numeric($data['value'])) {
            return array('error' => '卡密积分无效');
        }
        
        $value = (float) $data['value'];
        
        if ($value <= 0) {
            return array('error' => '卡密积分必须大于0');
        }
        
        // 创建订单
        $order_res = Orders::build_order(array(
            'order_price' => (int) $value,
            'order_type' => 'credit_chongzhi',
            'title' => '积分充值',
            'pay_type' => 'card',
        ));

        if (isset($order_res['error'])) {
            return $order_res;
        }

        // 确认订单
        $order_confirm = Orders::order_confirm($order_res['order_id'], (int) $value);

        if (isset($order_confirm['error'])) {
            return $order_confirm;
        }

        return array('msg' => "您已成功使用激活码充值积分 {$value} 元~");
    }

    /**
     * VIP卡密购买回调
     * 
     * @author  ifyn
     * @param   array $data 卡密数据
     * @return  array 处理结果
     */
    public static function callback_vip($data)
    {
        if (!isset($data['card_key']) || empty($data['card_key']) || 
            !isset($data['card_value']) || !is_numeric($data['card_value'])) {
            return array('error' => '卡密会员信息无效');
        }
        
        if (!isset($data['value']) || !is_numeric($data['value']) || (float) $data['value'] <= 0) {
            return array('error' => '卡密金额无效');
        }
        
        // 创建订单
        $order_res = Orders::build_order(array(
            'order_price' => (int) $data['value'],
            'order_type' => 'vip_goumai',
            'order_key' => $data['card_key'],
            'order_value' => (int) $data['card_value'],
            'title' => '开通会员',
            'pay_type' => 'card',
        ));

        if (isset($order_res['error'])) {
            return $order_res;
        }

        // 确认订单
        $order_confirm = Orders::order_confirm($order_res['order_id'], (int) $data['value']);

        if (isset($order_confirm['error'])) {
            return $order_confirm;
        }

        // 获取会员角色信息
        $roles = User::get_user_roles();

        $vip = '';
        if (isset($roles[$data['card_key']])) {
            $vip = $roles[$data['card_key']]['name'];
        }

        $day = (int) $data['card_value'] === 0 ? '永久' : $data['card_value'] . '天';

        return array('msg' => "您已成功激活{$day}{$vip}~");
    }

    /**
     * 获取卡密列表
     * 
     * @author  ifyn
     * @param   array $request 请求参数，包含分页和筛选信息
     * @return  array 卡密列表数据
     */
    public static function islide_get_card_list($request)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_card';

        // 分页参数
        $paged = isset($request['paged']) ? max(1, intval($request['paged'])) : 1;
        $per_page = isset($request['size']) ? max(1, intval($request['size'])) : 10;
        $offset = ($paged - 1) * $per_page;

        // 筛选卡密类型（如 money、credit、vip、invite）
        $type_filter = isset($request['type']) ? sanitize_text_field($request['type']) : '';
        $where = '';
        $args = [];

        if (!empty($type_filter) && $type_filter !== 'all') {
            $where = "WHERE type = %s";
            $args[] = $type_filter;
        }

        // 确保表存在
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            return [
                'error' => '卡密数据表不存在',
                'data' => [],
                'pages' => 0,
                'count' => 0,
                'paged' => $paged,
            ];
        }

        // 总数量查询
        $total_sql = "SELECT COUNT(*) FROM $table_name $where";
        $total = $args ? $wpdb->get_var($wpdb->prepare($total_sql, ...$args)) : $wpdb->get_var($total_sql);

        // 计算总页数
        $pages = ceil($total / $per_page);

        // 获取数据
        $data_sql = "SELECT * FROM $table_name $where ORDER BY id DESC LIMIT %d OFFSET %d";
        $args[] = $per_page;
        $args[] = $offset;

        $results = $wpdb->get_results($wpdb->prepare($data_sql, ...$args), ARRAY_A);

        $data = [];
        if (is_array($results)) {
            foreach ($results as $row) {
                $item = [
                    'id' => intval($row['id']),
                    'card_code' => $row['card_code'],
                    'type' => $row['type'],
                    'value' => is_numeric($row['value']) ? floatval($row['value']) : 0,
                    'card_key' => isset($row['card_key']) ? $row['card_key'] : '',
                    'card_value' => isset($row['card_value']) ? $row['card_value'] : '',
                    'status' => intval($row['status']),
                    'user_id' => intval($row['user_id']),
                ];
                
                // 获取用户信息
                if (!empty($row['user_id'])) {
                    $user = get_userdata($row['user_id']);
                    $item['name'] = $user ? $user->display_name : '未知用户';
                } else {
                    $item['name'] = '未使用';
                }

                $data[] = $item;
            }
        }

        return [
            'data' => $data,
            'pages' => (int) $pages,
            'count' => (int) $total,
            'paged' => (int) $paged,
        ];
    }

    /**
     * 生成卡密
     * 
     * @author  ifyn
     * @param   WP_REST_Request $request REST API请求对象
     * @return  array 生成结果
     */
    public static function generate_card($request)
    {
        global $wpdb;

        $data = $request->get_json_params();
        
        // 验证必要参数
        if (!isset($data['type']) || !isset($data['value']) || !isset($data['count'])) {
            return array(
                'success' => false,
                'message' => '参数不完整',
                'cards' => []
            );
        }

        // 处理参数
        $type = sanitize_text_field($data['type']);
        $value = floatval($data['value']);
        $count = intval($data['count']);
        $card_key = isset($data['card_key']) ? sanitize_text_field($data['card_key']) : '';
        $card_value = isset($data['card_value']) ? sanitize_text_field($data['card_value']) : '';

        // 验证参数合法性
        if (empty($type) || $count <= 0 || $value < 0) {
            return array(
                'success' => false,
                'message' => '参数无效',
                'cards' => []
            );
        }

        // 如果是VIP卡密，需要检查VIP参数
        if ($type === 'vip' && (empty($card_key) || !is_numeric($card_value))) {
            return array(
                'success' => false,
                'message' => 'VIP卡密参数无效',
                'cards' => []
            );
        }

        $table = $wpdb->prefix . 'islide_card';
        $cards = [];
        $insert_count = 0;

        // 批量生成卡密
        for ($i = 0; $i < $count; $i++) {
            $code = self::create_guid();
            
            $result = $wpdb->insert(
                $table, 
                [
                    'card_code' => $code,
                    'type' => $type,
                    'value' => $value,
                    'card_key' => $type === 'vip' ? $card_key : '',
                    'card_value' => $type === 'vip' ? $card_value : '',
                    'status' => 0,
                    'user_id' => 0,
                ],
                [
                    '%s', '%s', '%f', '%s', '%s', '%d', '%d'
                ]
            );
            
            if ($result) {
                $cards[] = $code;
                $insert_count++;
            }
        }

        if ($insert_count === 0) {
            return array(
                'success' => false,
                'message' => '生成卡密失败',
                'cards' => []
            );
        }

        return array(
            'success' => true,
            'message' => "成功生成 {$insert_count} 个卡密",
            'cards' => $cards
        );
    }

    /**
     * 创建唯一GUID用于卡密
     * 
     * @author  ifyn
     * @return  string 生成的GUID
     */
    public static function create_guid()
    {
        $guid = '';
        $uid = uniqid("", true);

        // 收集环境数据增加随机性
        $data = defined('AUTH_KEY') ? AUTH_KEY : '';
        $data .= isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
        $data .= isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $data .= isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '';
        $data .= isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : '';
        $data .= isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $data .= isset($_SERVER['REMOTE_PORT']) ? $_SERVER['REMOTE_PORT'] : '';
        $data .= mt_rand(10000, 99999); // 额外添加随机数增加唯一性

        // 生成哈希
        $hash = strtoupper(hash('ripemd128', $uid . $guid . md5($data)));

        // 格式化为标准GUID格式
        $guid = substr($hash, 0, 4) . '-' . 
                substr($hash, 8, 4) . '-' . 
                substr($hash, 12, 4) . '-' . 
                substr($hash, 16, 4) . '-' . 
                substr($hash, 20, 4);

        return $guid;
    }
}