<?php 
/**
 * 用户数字变化记录管理类
 * 
 * 处理用户余额、积分、佣金等数值变化的记录和查询功能
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;
use islide\Modules\Common\User;
/**
 * 用户数字变化记录操作
 * 
 * */

class Record {
    /**
     * 初始化函数，注册钩子
     *
     * @author  ifyn
     * @return  void
     */
    public function init(){
        
        //购买者数字记录（余额）
        add_filter('islide_balance_pay_after',array($this,'balance_pay_after_record'),5, 2);

        //购买者数字记录（积分）
        add_filter('islide_credit_pay_after',array($this,'credit_pay_after_record'),5, 2);
    }
    
    /**
     * 更新数据变化记录
     *
     * @author  ifyn
     * @param   array $new_data 新数据数组，包含记录类型、用户ID、变化值等
     * @return  int|bool        成功返回插入ID，失败返回false
     */
    public static function update_data($new_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_change_record'; 
        
        if(!isset($new_data['record_type'])) return false;
        
        if(!isset($new_data['total'])){
            
            //余额记录
            if($new_data['record_type'] === 'money'){
                
                $new_data['total'] = User::money_change($new_data['user_id'],$new_data['value']);
                
            }else if($new_data['record_type'] === 'credit'){
                
                $new_data['total'] = User::credit_change($new_data['user_id'],$new_data['value']);
                
            }else if($new_data['record_type'] === 'exp') {
                
                $new_data['total'] = User::exp_change($new_data['user_id'],$new_data['value']);
                
            }else if($new_data['record_type'] === 'commission') {
                
                $new_data['total'] = User::commission_change($new_data['user_id'],$new_data['value']);
                
            }elseif (strpos($new_data['record_type'], 'vip') === 0) {
                $new_data['record_type'] = 'vip';
                $new_data['total'] = User::vip_change($new_data['user_id'], $new_data['value'], $new_data['record_type']);
            }else{
                return false; 
            }

            if($new_data['total'] < 0) return false;
        }
        
        $arr = array(
            'sign_in' => '签到奖励',
            'task' => '任务奖励',
            'recharge' => '充值',
            'sell' => '出售',
            'admin' => '管理员操作'
        );
        
        $default = array(
            'user_id' => 0,
            'record_type' => '',
            'value' => 0,
            'total' => 0,
            'type' => '',
            'type_text' => '',
            'content' => '',
            'date' => current_time('mysql'),
            'status' => '',
            'record_key'=>'',
            'record_value'=>''
        );

        $args = wp_parse_args( $new_data,$default);
        
        $format = array(
            '%d', // user_id
            '%s', // record_type
            '%s', // value
            '%s', // total
            '%s', // type
            '%s', // type_text
            '%s', // content
            '%s',  // date
            '%s', // status
            '%s', // record_key
            '%s', // record_value
        );
        
        if($wpdb->insert($table_name, $args, $format)){
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * 获取用户记录列表
     *
     * @author  ifyn
     * @param   array $args 查询参数，包含type和paged
     * @return  array       记录列表数据或错误信息
     */
    public static function get_record_list($args) {
        $current_user_id = get_current_user_id();
        $paged = isset($args['paged']) ? (int)$args['paged'] : 1;

        if(!$current_user_id) return array('error'=>'请先登录');
        
        if(!isset($args['type']) || !in_array($args['type'],array('exp','money','credit','commission'))) return array('error'=>'类型错误');
        
        $user_id = $current_user_id;
        
        $size = 10;
        $offset = ($paged-1)*$size;
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_change_record';
        
        //获取订单数据
        $records = $wpdb->get_results(
            $wpdb->prepare("
                SELECT * FROM $table_name
                WHERE user_id = %d
                AND record_type = %s
                ORDER BY date DESC 
                LIMIT %d,%d
            ",
                $user_id,
                $args['type'],
                $offset,
                $size
            ),ARRAY_A);
        
        $count = $wpdb->get_var(
            $wpdb->prepare("
                SELECT COUNT(*) FROM $table_name
                WHERE user_id = %d
                AND record_type = %s
            ",
                $user_id,
                $args['type']
            ));
            
        return array(
            'pages' => ceil($count/$size),
            'count' => $count,
            'data' => $records
        );
    }
    
    /**
     * 获取提现记录列表
     *
     * @author  ifyn
     * @param   WP_REST_Request $request 请求对象
     * @return  array                    提现记录列表
     */
    public static function islide_get_withdrawal_list($request) {
        global $wpdb;
        $table = $wpdb->prefix . 'islide_change_record';

        $paged = max(1, intval($request->get_param('paged') ?: 1));
        $size = max(1, intval($request->get_param('size') ?: 20));
        $offset = ($paged - 1) * $size;

        $status = $request->get_param('status');
        $record_type = $request->get_param('record_type');
        $user_id = $request->get_param('user_id'); // 可选，支持筛选某个用户

        $where = "WHERE `type` = 'withdrawal'";
        $params = [];

        if ($status !== null && $status !== '') {
            $where .= " AND `status` = %d";
            $params[] = intval($status);
        }

        if ($record_type) {
            $where .= " AND `record_type` = %s";
            $params[] = sanitize_text_field($record_type);
        }

        if ($user_id) {
            $where .= " AND `user_id` = %d";
            $params[] = intval($user_id);
        }

        // 查询数据
        $final_params = array_merge($params, [$size, $offset]);
        $query = $wpdb->prepare("SELECT * FROM $table $where ORDER BY id DESC LIMIT %d OFFSET %d", ...$final_params);
        $rows = $wpdb->get_results($query, ARRAY_A);

        $data = array();
        foreach ($rows as $item){
            $user = get_userdata($item['user_id']);
            $item['name'] = $user ? $user->display_name : '未知用户';
            $data[] = $item;
        }

        // 查询总数
        $count_query = $wpdb->prepare("SELECT COUNT(*) FROM $table $where", ...$params);
        $total = $wpdb->get_var($count_query);

        return [
            'data' => $data,
            'total' => intval($total),
            'pages' => ceil($total / $size),
            'paged' => $paged,
        ];
    }
    
    /**
     * 处理余额支付后的记录
     *
     * @author  ifyn
     * @param   array $data    订单数据
     * @param   float $balance 支付用户的总金钱余额
     * @return  array          处理后的订单数据
     */
    public function balance_pay_after_record($data,$balance){
        $array = array(
            'shop'=>array(
                'type' => 'shop',
                'type_text'=>'购买商品',
            ),
            'video'=>array(
                'type' => 'video',
                'type_text'=>'购买视频',
            ),
            'xiazai'=>array(
                'type' => 'xiazai',
                'type_text'=>'购买下载资源',
            ),
            'post_neigou'=>array(
                'type' => 'post_neigou',
                'type_text'=>'购买隐藏内容',
            ),
            'vip_goumai'=>array(
                'type' => 'vip_goumai',
                'type_text'=>'开通VIP会员',
            ),
            'credit_chongzhi'=>array(
                'type' => 'credit_chongzhi',
                'type_text'=>'购买积分',
            ),
            'product'=>array(
                'type' => 'product',
                'type_text'=>'购买产品',
            ),
            'join_circle'=>array(
                'type' => 'circle',
                'type_text'=>'支付入圈',
            ),
        );
        
        if(!isset($array[$data['order_type']])) return $data;
        
        self::update_data(array(
                'user_id' => $data['user_id'],
                'record_type' => 'money',
                'value' => -$data['order_total'],
                'total'=> $balance,
                'type' => $array[$data['order_type']]['type'],
                'type_text' => $array[$data['order_type']]['type_text']
            )
        );
        
        return apply_filters('islide_balance_pay_after_record',$data);
    }
    
    /**
     * 处理积分支付后的记录
     *
     * @author  ifyn
     * @param   array $data  订单数据
     * @param   int   $credit 支付用户的总积分余额
     * @return  array         处理后的订单数据
     */
    public function credit_pay_after_record($data,$credit){
        
        $array = array(
            'shop'=>array(
                'type' => 'shop',
                'type_text'=>'购买商品',
            ),
            'video'=>array(
                'type' => 'video',
                'type_text'=>'购买视频',
            ),
            'xiazai'=>array(
                'type' => 'xiazai',
                'type_text'=>'购买下载资源',
            ),
            'post_neigou'=>array(
                'type' => 'post_neigou',
                'type_text'=>'购买隐藏内容',
            ),
            'join_circle'=>array(
                'type' => 'circle',
                'type_text'=>'支付入圈',
            ),
        );
        
        if(!isset($array[$data['order_type']])) return $data;
        
        self::update_data(array(
                'user_id' => $data['user_id'],
                'record_type' => 'credit',
                'value' => -$data['order_total'],
                'total'=> $credit,
                'type' => $array[$data['order_type']]['type'],
                'type_text' => $array[$data['order_type']]['type_text']
            )
        );
        
        return apply_filters('islide_credit_pay_after_record',$data);
    }
    
    
    /**
     * 更新提现记录字段
     *
     * @author  ifyn
     * @param   WP_REST_Request $request 请求对象
     * @return  array                    更新结果
     */
    public static function islide_update_withdrawal_field($request) {
        global $wpdb;
        $params = $request->get_params();
    
        $id = absint($params['id'] ?? 0);
        $field = sanitize_text_field($params['field'] ?? '');
        $value = sanitize_text_field($params['value'] ?? '');
    
        if (!$id || !$field) {
            return array('error'=>'参数不完整');
        }
    
        $table = $wpdb->prefix . 'islide_change_record';
        $result = $wpdb->update($table, [ $field => $value ], [ 'id' => $id ]);
    
        if ($result === false) {
            return array('error'=>'更新失败');
        }
    
        return ['success' => true, 'message' => '更新成功'];
    }
    
    /**
     * 获取提现统计数据
     *
     * @author  ifyn
     * @return  array 提现记录统计数据
     */
    public static function islide_get_withdrawal_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'islide_change_record';
    
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE type = 'withdrawal'");
        $unprocessed = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE type = 'withdrawal' AND status = 0");
    
        return [
            'total' => $total,
            'unprocessed' => $unprocessed
        ];
    }
    
    /**
     * 批量删除提现记录
     *
     * @author  ifyn
     * @param   WP_REST_Request $request 请求对象
     * @return  array                    删除结果
     */
    public static function islide_delete_withdrawal_list($request) {
        global $wpdb;
        $ids = $request->get_param('ids');
    
        if (!is_array($ids) || empty($ids)) {
            return array('error'=>'无效的 ID 列表');
        }
    
        $table = $wpdb->prefix . 'islide_change_record';
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = "DELETE FROM $table WHERE id IN ($placeholders)";
    
        $result = $wpdb->query($wpdb->prepare($sql, ...$ids));
    
        if ($result === false) {
            return array('error'=>'删除失败');
        }
    
        return ['success' => true, 'deleted' => $result];
    }
    
    
}