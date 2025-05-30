<?php
namespace islide\Modules\Settings;

/**
 * 数据图表设置类
 * @author ifyn
 */
class Echarts {
    /**
     * 设置主KEY
     * @var string
     */
    public static $prefix = 'islide_main_options';

    /**
     * 初始化方法
     * @author ifyn
     * @return void
     */
    public function init() {
        try {
            // 加载图标用js
            add_action('admin_enqueue_scripts', array($this, 'load_enqueue_admin_script'));
            
            \CSF::createSection(self::$prefix, array(
                'id'    => 'islide_echarts_options',
                'title' => '数据统计',
                'icon'  => 'fas fa-chart-bar',
                'fields' => array(
                    array(
                        'type'     => 'callback',
                        'function' => array($this,'echarts_page_cb')
                    ),
                )
            ));
        } catch (Exception $e) {
            error_log('Echarts init error: ' . $e->getMessage());
        }
    }
    
    /**
     * 显示待处理事项和快捷入口
     * @author ifyn
     * @return void
     */
    public function echarts_page_top_cb() {
        try {
            // 获取待审文章数量
            $pending_posts = wp_count_posts('post')->pending;
            
            // 获取待审评论数量
            $pending_comments = wp_count_comments()->moderated;
            
            global $wpdb;
            
            // 获取待审提现数量
            $table_name = $wpdb->prefix . 'islide_change_record';
            $withdrawal_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE type = %s AND status = %d",
                'withdrawal',
                0
            ));
            
            // 获取待审举报数量
            $table_name = $wpdb->prefix . 'islide_report';
            $report_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE status = %d",
                0
            ));
            
            // 获取待审认证数量
            $table_name = $wpdb->prefix . 'islide_verify';
            $verify_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE status = %d",
                0
            ));
            
            echo '
                <div class="echarts-top">
                    <div class="left">
                        <div class="title">待处理事项</div>
                        <ul>
                            <li>
                                <a href="'.esc_url(admin_url('edit.php?post_status=pending')).'">
                                    <div>待审文章</div>
                                    <div>'.esc_html($pending_posts).'</div>
                                </a>
                            </li>
                            <li>
                                <a href="'.esc_url(admin_url('edit-comments.php?comment_status=moderated')).'">
                                    <div>待审评论</div>
                                    <div>'.esc_html($pending_comments).'</div>
                                </a>
                            </li>
                            <li>
                                <a href="'.esc_url(admin_url('admin.php?page=withdrawal_list_page&status=0')).'">
                                    <div>待审提现</div>
                                    <div>'.esc_html($withdrawal_count).'</div>
                                </a>
                            </li>
                            <li>
                                <a href="'.esc_url(admin_url('admin.php?page=report_list_page&status=0')).'">
                                    <div>待审举报</div>
                                    <div>'.esc_html($report_count).'</div>
                                </a>
                            </li>
                            <li>
                                <a href="'.esc_url(admin_url('admin.php?page=verify_list_page&status=0')).'">
                                    <div>待审认证</div>
                                    <div>'.esc_html($verify_count).'</div>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="right">
                        <div class="title">快捷入口</div>
                        <ul>
                            <li>
                                <a href="'.esc_url(admin_url('admin.php?page=islide_card_bulid')).'">
                                    <i class="fas fa-money-check"></i>
                                    <div>生成卡密</div>
                                </a>
                            </li>
                            <li>
                                <a href="'.esc_url(admin_url('admin.php?page=islide_message_push')).'">
                                    <i class="fas fa-comment-alt"></i>
                                    <div>推送消息</div>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            ';
        } catch (Exception $e) {
            error_log('Echarts page top error: ' . $e->getMessage());
            echo '<div class="notice notice-error"><p>加载数据时发生错误</p></div>';
        }
    }
    
    /**
     * 数据统计页面
     * @author ifyn
     * @return void
     */
    public function echarts_page_cb() {
        try {
            $this->echarts_page_top_cb();
            
            echo '<div class="data-container">';
                $this->echarts_order();
                $this->echarts_wp();
            echo '</div>';
        } catch (Exception $e) {
            error_log('Echarts page error: ' . $e->getMessage());
            echo '<div class="notice notice-error"><p>加载数据时发生错误</p></div>';
        }
    }
    
    /**
     * 显示订单统计
     * @author ifyn
     * @return void
     */
    public function echarts_order() {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'islide_order';
            
            // 查询30天每日收入
            $thirtyDaysData = $wpdb->get_results("
                SELECT DATE(order_date) AS date, SUM(order_total) AS total
                FROM $table_name
                WHERE order_state != '0' AND order_state != '4' AND money_type = 0 AND pay_type != 'balance'
                AND order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(order_date)
            ", ARRAY_A) ?: array();
            
            // 查询年每月收入
            $yearlyData = $wpdb->get_results("
                SELECT DATE_FORMAT(order_date, '%Y-%m') AS date, SUM(order_total) AS total 
                FROM $table_name 
                WHERE order_state != '0' AND order_state != '4' AND money_type = 0 AND order_type != 'balance' 
                AND YEAR(order_date) = YEAR(CURDATE()) 
                GROUP BY DATE_FORMAT(order_date, '%Y-%m')
            ", ARRAY_A) ?: array();
            
            // 自动填充缺失的日期和月份数据
            $thirtyDaysData = self::fillMissingDates($thirtyDaysData, 30);
            $yearlyData = self::fillMissingMonths($yearlyData);
            
            // 获取最近7天的每日收入数据
            $sevenDaysData = array_slice($thirtyDaysData, -7);
        
            wp_localize_script('islide-admin', 'showOrderData', array(
                'daily' => $sevenDaysData,
                'monthly' => $thirtyDaysData,
                'yearly' => $yearlyData
            ));
            
            $data = $wpdb->get_results("
                SELECT 
                COALESCE(SUM(CASE WHEN DATE(order_date) = CURDATE() THEN order_total ELSE 0 END), 0) AS day,
                COALESCE(SUM(CASE WHEN DATE(order_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN order_total ELSE 0 END), 0) AS yesterday,
                COALESCE(SUM(CASE WHEN YEAR(order_date) = YEAR(CURRENT_DATE()) AND MONTH(order_date) = MONTH(CURRENT_DATE()) THEN order_total ELSE 0 END), 0) AS month,
                COALESCE(SUM(CASE WHEN YEAR(order_date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND MONTH(order_date) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) THEN order_total ELSE 0 END), 0) AS last_month,
                COALESCE(SUM(CASE WHEN YEAR(order_date) = YEAR(CURRENT_DATE()) THEN order_total ELSE 0 END), 0) AS year,
                COALESCE(SUM(CASE WHEN YEAR(order_date) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 YEAR)) THEN order_total ELSE 0 END), 0) AS last_year,
                COALESCE(SUM(IF(order_state != '0',order_total,0)), 0) as total,
                COALESCE(SUM(IF(order_state = '4',order_total,0)), 0) as refund
                FROM $table_name WHERE order_state != '0' AND order_state != '4' AND money_type = 0 AND order_type != 'balance' 
            ", ARRAY_A) ?: array(array(
                'day' => 0,
                'yesterday' => 0,
                'month' => 0,
                'last_month' => 0,
                'year' => 0,
                'last_year' => 0,
                'total' => 0,
                'refund' => 0
            ));
            
            self::getIncomeData();
            
            $this->render_order_section($data);
            
        } catch (Exception $e) {
            error_log('Echarts order error: ' . $e->getMessage());
            echo '<div class="notice notice-error"><p>加载订单数据时发生错误</p></div>';
            // 提供默认数据
            $this->render_order_section(array(array(
                'day' => 0,
                'yesterday' => 0,
                'month' => 0,
                'last_month' => 0,
                'year' => 0,
                'last_year' => 0,
                'total' => 0,
                'refund' => 0
            )));
        }
    }
    
    /**
     * 获取收入数据
     * @author ifyn
     * @return void
     */
    public static function getIncomeData() {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'islide_order';
            
            // 定义订单类型映射
            $types = array(
                'choujiang' => '抽奖',
                'duihuan' => '兑换',
                'goumai' => '购买',
                'post_neigou' => '文章内购',
                'dashang' => '打赏',
                'xiazai' => '资源下载',
                'money_chongzhi' => '余额充值',
                'vip_goumai' => 'VIP购买',
                'credit_chongzhi' => '积分购买',
                'video' => '视频购买',
                'verify' => '认证付费',
                'mission' => '签到填坑',
                'coupon' => '优惠劵订单',
                'circle_join' => '支付入圈',
                'circle_read_answer_pay' => '付费查看提问答案',
                'product' => '产品购买'
            );
            
            // 初始化收入数组
            $income_array = array(
                'today_income' => array(),
                'seven_days_income' => array(),
                'thirty_days_income' => array(),
                'total_income' => array()
            );
            
            // 定义查询语句
            $queries = array(
                'today_income' => "SELECT order_type, SUM(order_total) as value 
                                 FROM $table_name 
                                 WHERE order_state != '0' 
                                 AND order_state != '4' 
                                 AND money_type = 0 
                                 AND DATE(order_date) = CURDATE() 
                                 GROUP BY order_type",
                'seven_days_income' => "SELECT order_type, SUM(order_total) as value 
                                      FROM $table_name 
                                      WHERE order_state != '0' 
                                      AND order_state != '4' 
                                      AND money_type = 0 
                                      AND order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                                      GROUP BY order_type",
                'thirty_days_income' => "SELECT order_type, SUM(order_total) as value 
                                       FROM $table_name 
                                       WHERE order_state != '0' 
                                       AND order_state != '4' 
                                       AND money_type = 0 
                                       AND order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                                       GROUP BY order_type",
                'total_income' => "SELECT order_type, SUM(order_total) as value 
                                 FROM $table_name 
                                 WHERE order_state != '0' 
                                 AND order_state != '4' 
                                 AND money_type = 0 
                                 GROUP BY order_type"
            );
            
            // 执行查询并处理结果
            foreach ($queries as $key => $query) {
                $results = $wpdb->get_results($query, ARRAY_A);
                
                foreach ($types as $type => $name) {
                    $found = false;
                    if ($results) {
                        foreach ($results as $result) {
                            if ($result['order_type'] == $type) {
                                $income_array[$key][] = array(
                                    'value' => floatval($result['value']),
                                    'name' => $name
                                );
                                $found = true;
                                break;
                            }
                        }
                    }
                    if (!$found) {
                        $income_array[$key][] = array(
                            'value' => 0,
                            'name' => $name
                        );
                    }
                }
            }
            
            // 本地化脚本数据
            wp_localize_script('islide-admin', 'showIncomeData', $income_array);
            
        } catch (Exception $e) {
            error_log('Get income data error: ' . $e->getMessage());
            wp_localize_script('islide-admin', 'showIncomeData', array(
                'today_income' => array(),
                'seven_days_income' => array(),
                'thirty_days_income' => array(),
                'total_income' => array()
            ));
        }
    }
    
    /**
     * 填充缺失的日期数据
     * @author ifyn
     * @param array $data 原始数据
     * @param int $days 天数
     * @return array 填充后的数据
     */
    public static function fillMissingDates($data, $days) {
        try {
            $dates = array();
            $result = array();
        
            // 获取日期范围
            $start_date = wp_date('Y-m-d', strtotime("-$days days"));
            $end_date = wp_date('Y-m-d');
        
            // 生成日期数组
            $current_date = $start_date;
            while ($current_date <= $end_date) {
                $dates[] = $current_date;
                $current_date = wp_date('Y-m-d', strtotime($current_date . ' +1 day'));
            }
        
            // 填充缺失的日期数据
            foreach ($dates as $date) {
                $found = false;
                foreach ($data as $item) {
                    if ($item['date'] == $date) {
                        $result[] = $item;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $result[] = array('date' => $date, 'total' => 0);
                }
            }
        
            return $result;
        } catch (Exception $e) {
            error_log('Fill missing dates error: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * 填充缺失的月份数据
     * @author ifyn
     * @param array $data 原始数据
     * @return array 填充后的数据
     */
    private function fillMissingMonths($data) {
        try {
            $months = array();
            $result = array();
        
            // 获取月份范围
            $start_month = wp_date('Y-m', strtotime("-1 year"));
            $end_month = wp_date('Y-m');
        
            // 生成月份数组
            $current_month = $start_month;
            while ($current_month <= $end_month) {
                $months[] = $current_month;
                $current_month = wp_date('Y-m', strtotime($current_month . ' +1 month'));
            }
        
            // 填充缺失的月份数据
            foreach ($months as $month) {
                $found = false;
                foreach ($data as $item) {
                    if ($item['date'] == $month) {
                        $result[] = $item;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $result[] = array('date' => $month, 'total' => 0);
                }
            }
        
            return $result;
        } catch (Exception $e) {
            error_log('Fill missing months error: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * 显示WordPress统计数据
     * @author ifyn
     * @return void
     */
    public function echarts_wp() {
        try {
            global $wpdb;
            $today = wp_date('Y-m-d');
            $yesterday = wp_date('Y-m-d', strtotime('-1 day'));
            
            // 获取最近七天的日期
            $dates = array();
            for ($i = 6; $i >= 0; $i--) {
                $dates[] = wp_date('Y-m-d', strtotime("-$i days"));
            }
            
            // 获取各类统计数据
            $post_counts = self::get_counts('post', $dates);
            $comments_counts = self::get_counts('comment', $dates);
            $users_counts = self::get_counts('user', $dates);
            $sign_ins_counts = self::get_counts('sign_in', $dates);
            
            // 确保所有数组都有足够的元素
            $post_counts = array_pad($post_counts, 7, 0);
            $comments_counts = array_pad($comments_counts, 7, 0);
            $users_counts = array_pad($users_counts, 7, 0);
            $sign_ins_counts = array_pad($sign_ins_counts, 7, 0);
            
            $data = array(
                'post' => array(
                    'title' => '文章数',
                    'today' => end($post_counts),
                    'yesterday' => $post_counts[count($post_counts) - 2],
                    'total' => wp_count_posts('post')->publish
                ),
                'comment' => array(
                    'title' => '评论数',
                    'today' => end($comments_counts),
                    'yesterday' => $comments_counts[count($comments_counts) - 2],
                    'total' => wp_count_comments()->approved
                ),
                'user' => array(
                    'title' => '用户数',
                    'today' => end($users_counts),
                    'yesterday' => $users_counts[count($users_counts) - 2],
                    'total' => count_users()['total_users']
                ),
                'sign_in' => array(
                    'title' => '今日签到',
                    'today' => end($sign_ins_counts),
                    'yesterday' => $sign_ins_counts[count($sign_ins_counts) - 2],
                    'total' => end($sign_ins_counts)
                ),
            );
            
            wp_localize_script('islide-admin', 'showData', array(
                'posts' => $post_counts,
                'comments' => $comments_counts,
                'users' => $users_counts,
                'sign_ins' => $sign_ins_counts,
                'dates' => $dates
            ));
            
            $this->render_wp_section($data);
            
        } catch (Exception $e) {
            error_log('Echarts wp error: ' . $e->getMessage());
            echo '<div class="notice notice-error"><p>加载统计数据时发生错误</p></div>';
            // 提供默认数据
            $this->render_wp_section(array(
                'post' => array(
                    'title' => '文章数',
                    'today' => 0,
                    'yesterday' => 0,
                    'total' => 0
                ),
                'comment' => array(
                    'title' => '评论数',
                    'today' => 0,
                    'yesterday' => 0,
                    'total' => 0
                ),
                'user' => array(
                    'title' => '用户数',
                    'today' => 0,
                    'yesterday' => 0,
                    'total' => 0
                ),
                'sign_in' => array(
                    'title' => '今日签到',
                    'today' => 0,
                    'yesterday' => 0,
                    'total' => 0
                )
            ));
        }
    }
    
    /**
     * 获取指定类型的统计数据
     * @author ifyn
     * @param string $type 统计类型 (post|comment|user|sign_in)
     * @param array $dates 日期数组
     * @return array 统计数据数组
     */
    public static function get_counts($type, $dates) {
        if (!is_array($dates) || empty($dates)) {
            return array();
        }
        
        try {
            global $wpdb;
            $counts = array();
            
            foreach ($dates as $date) {
                if (!self::is_valid_date($date)) {
                    $counts[] = 0;
                    continue;
                }
                
                switch ($type) {
                    case 'post':
                        $counts[] = self::get_post_count($date);
                        break;
                    case 'comment':
                        $counts[] = self::get_comment_count($date);
                        break;
                    case 'user':
                        $counts[] = self::get_user_count($date);
                        break;
                    case 'sign_in':
                        $counts[] = self::get_sign_in_count($date, $wpdb);
                        break;
                    default:
                        $counts[] = 0;
                }
            }
            
            return $counts;
            
        } catch (Exception $e) {
            error_log('Get counts error: ' . $e->getMessage());
            return array_fill(0, count($dates), 0);
        }
    }
    
    /**
     * 验证日期格式
     * @author ifyn
     * @param string $date 日期字符串
     * @return bool
     */
    private static function is_valid_date($date) {
        return (bool)strtotime($date);
    }
    
    /**
     * 获取文章数量
     * @author ifyn
     * @param string $date 日期
     * @return int
     */
    private static function get_post_count($date) {
        $args = array(
            'post_type' => 'post',
            'posts_per_page' => -1,
            'date_query' => array(
                array(
                    'after' => $date,
                    'before' => $date,
                    'inclusive' => true,
                ),
            ),
        );
        $query = new \WP_Query($args);
        return $query->post_count;
    }
    
    /**
     * 获取评论数量
     * @author ifyn
     * @param string $date 日期
     * @return int
     */
    private static function get_comment_count($date) {
        $args = array(
            'status' => 'approve',
            'date_query' => array(
                array(
                    'after' => $date,
                    'before' => $date,
                    'inclusive' => true,
                ),
            ),
        );
        $comments_count = get_comments($args);
        return count($comments_count);
    }
    
    /**
     * 获取用户数量
     * @author ifyn
     * @param string $date 日期
     * @return int
     */
    private static function get_user_count($date) {
        $args = array(
            'role__not_in' => array('administrator'),
            'date_query' => array(
                array(
                    'after' => $date,
                    'before' => $date,
                    'inclusive' => true,
                ),
            ),
        );
        return count(get_users($args));
    }
    
    /**
     * 获取签到数量
     * @author ifyn
     * @param string $date 日期
     * @param \wpdb $wpdb WordPress数据库对象
     * @return int
     */
    private static function get_sign_in_count($date, $wpdb) {
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}islide_sign_in WHERE DATE(sign_in_date) = %s",
            $date
        ));
    }
    
    /**
     * 加载管理页面所需的脚本和样式
     * @author ifyn
     * @param string $hook 当前页面钩子
     * @return void
     */
    public static function load_enqueue_admin_script($hook) {
        if ('toplevel_page_islide_main_options' != $hook) {
            return;
        }
        
        try {
            wp_enqueue_script(
                'echarts',
                IS_THEME_URI . '/Assets/admin/echarts.min.js?v=5.4.2',
                array(),
                IS_VERSION,
                true
            );
        } catch (Exception $e) {
            error_log('Load admin script error: ' . $e->getMessage());
        }
    }
    
    /**
     * 渲染订单统计部分
     * @author ifyn
     * @param array $data 订单数据
     * @return void
     */
    private function render_order_section($data) {
        // 确保数据存在且有效
        if (!is_array($data) || empty($data)) {
            $data = array(
                array(
                    'day' => 0,
                    'yesterday' => 0,
                    'month' => 0,
                    'last_month' => 0,
                    'year' => 0,
                    'last_year' => 0,
                    'total' => 0
                )
            );
        }

        $order_data = $data[0] ?? array(
            'day' => 0,
            'yesterday' => 0,
            'month' => 0,
            'last_month' => 0,
            'year' => 0,
            'last_year' => 0,
            'total' => 0
        );

        echo '<section class="data-info">';
        echo '<ul class="data-card">
                <li class="card-item">
                    <div class="header">今日收入</div>
                    <div class="body">
                        <span class="value">' . esc_html($order_data['day'] ?: 0) . '</span>
                    </div>
                    <div class="footer">
                        <div class="left">
                            <span class="label">昨日：</span>
                            '.($order_data['yesterday'] > 0 ?'
                            <span class="value" style=" color: #5bbf60; "><i title="fas fa-caret-up" class="fas fa-caret-up"></i> ' . esc_html($order_data['yesterday']) . '</span>':'<span class="value">--</span>').'
                        </div>
                    </div>
                </li>
                <li class="card-item">
                    <div class="header">当月收入</div>
                    <div class="body">
                        <span class="value">' . esc_html($order_data['month'] ?: 0) . '</span>
                    </div>
                    <div class="footer">
                        <div class="left">
                            <span class="label">上月：</span>
                            '.($order_data['last_month'] > 0 ?'
                            <span class="value" style=" color: #5bbf60; "><i title="fas fa-caret-up" class="fas fa-caret-up"></i> ' . esc_html($order_data['last_month']) . '</span>':'<span class="value">--</span>').'
                        </div>
                    </div>
                </li>
                <li class="card-item">
                    <div class="header">今年收入</div>
                    <div class="body">
                        <span class="value">' . esc_html($order_data['year'] ?: 0) . '</span>
                    </div>
                    <div class="footer">
                        <div class="left">
                            <span class="label">去年：</span>
                            '.($order_data['last_year'] > 0 ?'
                            <span class="value" style=" color: #5bbf60; "><i title="fas fa-caret-up" class="fas fa-caret-up"></i> ' . esc_html($order_data['last_year']) . '</span>':'<span class="value">--</span>').'
                        </div>
                    </div>
                </li>
                <li class="card-item">
                    <div class="header">总收入</div>
                    <div class="body">
                        <span class="value">' . esc_html($order_data['total'] ?: 0) . '</span>
                    </div>
                </li>
            </ul>';
        
        echo '<div class="data-tabs">
                <div onclick="orderEchartsPie(showIncomeData.today_income, \'今日\')">今日</div>
                <div onclick="showSevenDaysData()">最近7天</div>
                <div onclick="showThirtyDaysData()">最近30天</div>
                <div onclick="showYearlyData()">最近1年</div>
            </div>';
        
        echo '<div class="data-chart">
                <div id="order-echarts" style="width: 60%;height:450px;"></div>
                <div id="order-echarts-Pie" style="width: 40%;height:450px;"></div>
            </div>';
        
        echo '</section>';
    }
    
    /**
     * 渲染WordPress统计部分
     * @author ifyn
     * @param array $data 统计数据
     * @return void
     */
    private function render_wp_section($data) {
        $li = '';
        foreach ($data as $key => $value) {
            // 确保所有必需的键都存在
            $value = array_merge(array(
                'title' => '',
                'today' => 0,
                'yesterday' => 0,
                'total' => 0
            ), $value);

            $li .= '<li class="card-item">
                <div class="header">' . esc_html($value['title']) . '</div>
                <div class="body">
                    <span class="value">' . esc_html($value['total']) . '</span>
                </div>
                <div class="footer">
                    <div class="left">
                        <span class="label">今日：</span>
                        '.($value['today'] > 0 ?'
                        <span class="value" style=" color: #ff4684; "><i title="fas fa-caret-up" class="fas fa-caret-up"></i> ' . esc_html($value['today']) . '</span>':'<span class="value">--</span>').'
                    </div>
                    <div class="right">
                        <span class="label">昨日：</span>
                        '.($value['yesterday'] > 0 ?'
                        <span class="value" style=" color: #5bbf60; "><i title="fas fa-caret-up" class="fas fa-caret-up"></i> ' . esc_html($value['yesterday']) . '</span>':'<span class="value">--</span>').'
                    </div>
                </div>
            </li>';
        }

        echo '<section class="data-info">';
        echo '<ul class="data-card">'.$li.'</ul>';
        echo '<div class="data-chart">
                <div id="data-echarts" style="width: 100%;height:400px;"></div>
            </div>';
        echo '</section>';
    }
}