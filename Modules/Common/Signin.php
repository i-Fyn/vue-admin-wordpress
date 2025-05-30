<?php
/**
 * 签到系统管理类
 * 
 * 处理用户签到、连续签到、签到统计等功能
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;

class Signin {
    /**
     * 初始化函数，注册钩子
     *
     * @author  ifyn
     * @return  void
     */
    public function init() {
        // 可以在此处添加初始化代码
    }
    
    /**
     * 用户签到
     *
     * @author  ifyn
     * @return  array 签到结果，包括成功或失败的状态和相应的信息
     */
    public static function user_signin() {
        // 获取当前用户ID
        $user_id = get_current_user_id();
        
        // 如果用户未登录，则返回错误信息
        if (!$user_id) {
            return array('error' => '请先登录');
        }
    
        // 获取当前日期和时间
        $current_time = wp_strtotime(current_time('mysql'));
        
        $date = wp_date('Y-m-d', $current_time);
        $time = wp_date('H:i:s', $current_time);
    
        // 如果用户已经签到过，则返回错误信息
        if (self::has_signed_in($user_id, $date)) {
            return array('error' => '您今日已经签到过了，明日再来吧。');
        }
    
        // 向数据库插入签到记录
        if (self::insert_signin_record($user_id, $date, $time)) {
            // 更新用户的连续签到天数
            $consecutive_days = self::update_consecutive_days($user_id, $date);
            
            // 应用签到奖励过滤器
            $value = apply_filters('islide_update_signin', $user_id, $consecutive_days, $date);
            
            if (isset($value['error'])) {
                return $value;
            }
            
            // 签到成功触发钩子
            do_action('islide_user_signin', $user_id, $value);
    
            return array(
                'success' => true, 
                'message' => '签到成功！',
                'value' => $value,
                'consecutiveDays' => $consecutive_days
            );
        }
    
        return array('error' => '签到失败，请重试。');
    }
    
    /**
     * 检查用户是否已签到
     *
     * @author  ifyn
     * @param   int    $user_id 用户ID
     * @param   string $date    日期，格式为Y-m-d
     * @return  bool            用户是否已签到
     */
    public static function has_signed_in($user_id, $date) {
        if (!$user_id || !$date) {
            return false;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_sign_in';
    
        // 查询数据库，统计指定用户和日期的记录数量
        $count = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND sign_in_date = %s", $user_id, $date)
        );
    
        // 如果记录数量大于0，则表示用户已签到
        return $count > 0;
    }
    
    /**
     * 插入签到记录
     *
     * @author  ifyn
     * @param   int    $user_id 用户ID
     * @param   string $date    日期，格式为Y-m-d
     * @param   string $time    时间，格式为H:i:s
     * @return  bool            插入操作是否成功
     */
    public static function insert_signin_record($user_id, $date, $time) {
        if (!$user_id || !$date || !$time) {
            return false;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_sign_in';
        
        // 先检查是否已存在当天的签到记录
        $count = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND sign_in_date = %s", $user_id, $date)
        );
        
        if ($count > 0) {
            return true; // 已存在记录则直接返回成功
        }
        
        // 向数据库插入一条新的签到记录
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'sign_in_date' => $date,
                'sign_in_time' => $time,
            ),
            array('%d', '%s', '%s')
        );
    
        // 返回插入结果
        return $result !== false;
    }
    
    /**
     * 更新用户的连续签到天数
     *
     * @author  ifyn
     * @param   int    $user_id 用户ID
     * @param   string $date    日期，格式为Y-m-d
     * @return  int             用户的连续签到天数
     */
    public static function update_consecutive_days($user_id, $date) {
        if (!$user_id || !$date) {
            return 1;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_sign_in';
    
        // 初始连续签到天数为1
        $consecutive_days = 1;
    
        // 获取昨天的日期
        $previous_date = wp_date('Y-m-d', wp_strtotime($date . ' -1 day'));
    
        // 检查用户昨天是否已经签到
        if (self::has_signed_in($user_id, $previous_date)) {
            // 如果用户昨天已签到，则从数据库中获取昨天的连续签到天数并加1
            $prev_days = $wpdb->get_var(
                $wpdb->prepare("SELECT consecutive_days FROM $table_name WHERE user_id = %d AND sign_in_date = %s", $user_id, $previous_date)
            );
            
            if ($prev_days !== null) {
                $consecutive_days = (int)$prev_days + 1;
            }
        }
    
        // 更新用户当天的连续签到天数
        $wpdb->update(
            $table_name,
            array('consecutive_days' => $consecutive_days),
            array('user_id' => $user_id, 'sign_in_date' => $date),
            array('%d'),
            array('%d', '%s')
        );
        
        // 返回连续签到天数
        return $consecutive_days;
    }
    
    /**
     * 获取用户签到信息
     *
     * @author  ifyn
     * @param   string $date 签到日期，格式为Y-m，默认为当前月份
     * @return  array        包含签到信息的数组
     */
    public static function get_sign_in_info($date) {
        global $wpdb;
        
        // 格式化日期参数
        $sign_in_date = !empty($date) ? sanitize_text_field($date) : wp_date('Y-m');
    
        // 获取当前用户ID
        $user_id = get_current_user_id();
        if (!$user_id) {
            return array('error' => '请先登录');
        }
    
        // 获取当前月份的签到信息
        $sign_ins = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}islide_sign_in WHERE user_id = %d AND sign_in_date LIKE %s ORDER BY sign_in_date",
            $user_id,
            $sign_in_date . '%'
        ), ARRAY_A);
        
        // 错误处理
        if ($wpdb->last_error) {
            return array('error' => $wpdb->last_error);
        }

        // 计算签到天数
        $sign_days = is_array($sign_ins) ? count($sign_ins) : 0;
        
        // 获取当前日期信息
        $current_date = wp_date('Y-m-d');
        $current_day = wp_date('j');
        
        // 构造响应数据
        $response = array(
            'isCheckIn' => false, // 今天是否已签到
            'allDays' => (int)wp_date('t', wp_strtotime($sign_in_date . '-01')), // 本月总天数
            'curYear' => (int)wp_date('Y', wp_strtotime($sign_in_date . '-01')), // 当前年份
            'curMonth' => (int)wp_date('n', wp_strtotime($sign_in_date . '-01')), // 当前月份
            'curDay' => (int)$current_day, // 当前日期
            'curDate' => $current_date, // 当前完整日期
            'signDays' => $sign_days, // 本月签到天数
            'consecutiveDays' => isset($sign_ins[$sign_days - 1]) ? (int)$sign_ins[$sign_days - 1]['consecutive_days'] : 0, // 连续签到天数
            'signDaysList' => array(), // 已签到日期列表
            'signBonusDaysList' => array(), // 签到奖励列表
        );
        
        // 处理签到数据
        if (!empty($sign_ins)) {
            // 提取已签到的日期
            $response['signDaysList'] = array_map(function($sign_in) {
                return (int)wp_date('j', wp_strtotime($sign_in['sign_in_date']));
            }, $sign_ins);
            
            // 提取签到奖励数据
            $response['signBonusDaysList'] = array_map(function($sign_in) {
                return isset($sign_in['value']) ? maybe_unserialize($sign_in['value']) : null;
            }, $sign_ins);
            
            // 检查今天是否已签到
            if (in_array((int)$current_day, $response['signDaysList'])) {
                $response['isCheckIn'] = true;
            }
        }
        
        return array('data' => $response);
    }
    
    /**
     * 处理补签请求
     *
     * @author  ifyn
     * @param   array $post_data 包含用户ID和日期的数组
     * @return  array            补签结果
     */
    public static function handle_supplement_request($post_data) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return array('success' => false, 'message' => '请先登录');
        }
        
        if (!isset($post_data['date']) || empty($post_data['date'])) {
            return array('success' => false, 'message' => '日期参数不能为空');
        }
        
        $date = sanitize_text_field($post_data['date']);
    
        if (self::has_signed_in($user_id, $date)) {
            return array('success' => false, 'message' => '您已经签到过了。');
        } else {
            if (self::insert_supplement_record($user_id, $date)) {
                return array('success' => true, 'message' => '补签成功！');
            } else {
                return array('success' => false, 'message' => '补签失败，请重试。');
            }
        }
    }
    
    /**
     * 插入补签记录
     *
     * @author  ifyn
     * @param   int    $user_id 用户ID
     * @param   string $date    补签日期，格式为Y-m-d
     * @return  bool            插入操作是否成功
     */
    public static function insert_supplement_record($user_id, $date) {
        if (!$user_id || !$date) {
            return false;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_sign_in';
    
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'sign_in_date' => $date,
                'is_supplement' => 1,
                'supplement_date' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s')
        );
    
        return $result !== false;
    }
    
    /**
     * 获取用户的签到记录
     *
     * @author  ifyn
     * @param   int $user_id 用户ID
     * @return  array        用户的签到记录数组
     */
    public static function get_user_signin_records($user_id) {
        if (!$user_id) {
            return array();
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_sign_in';
    
        $records = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d ORDER BY sign_in_date ASC", $user_id)
        );
    
        return $records ? $records : array();
    }
    
    /**
     * 获取签到排行榜
     *
     * @author  ifyn
     * @return  array 签到排行榜数据
     */
    public static function get_signin_ranking() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_sign_in';
    
        $query = "SELECT user_id, COUNT(*) AS total_signins, MAX(consecutive_days) AS max_consecutive_days, SUM(bonus_points) AS total_bonus_points
            FROM $table_name
            GROUP BY user_id
            ORDER BY total_signins DESC, max_consecutive_days DESC, total_bonus_points DESC";
        
        $ranking = $wpdb->get_results($query);
    
        return $ranking ? $ranking : array();
    }
    
    /**
     * 获取用户的连续签到天数
     *
     * @author  ifyn
     * @param   int $user_id 用户ID
     * @return  int          连续签到天数
     */
    public static function get_consecutive_days($user_id) {
        if (!$user_id) {
            return 0;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_sign_in';

        // 获取今天的日期
        $today = wp_date('Y-m-d');

        // 获取用户最近一次签到记录
        $latest = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT sign_in_date, consecutive_days FROM $table_name 
                WHERE user_id = %d 
                ORDER BY sign_in_date DESC 
                LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );

        // 如果没有签到记录，返回 0
        if (!$latest) {
            return 0;
        }

        // 获取签到日期
        $last_sign_date = $latest['sign_in_date'];
        $consecutive_days = (int)$latest['consecutive_days'];

        // 如果最后一次签到不是今天或昨天，则断签
        $yesterday = wp_date('Y-m-d', wp_strtotime('-1 day'));
        if ($last_sign_date !== $today && $last_sign_date !== $yesterday) {
            return 0;
        }

        return $consecutive_days;
    }
}