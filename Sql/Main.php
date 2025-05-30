<?php
namespace islide\Sql;

class Main
{
    // 构造函数，初始化时引入升级文件
    public function __construct()
    {
        // 确保只引入一次
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
    }
    public function init(){
        $this->create_table_if_not_exists();
    }

    public function create_table_if_not_exists()
    {
        $this->create_sign_in_table_if_not_exists();
        $this->create_verify_table_if_not_exists();
        $this->create_report_table_if_not_exists();
        $this->create_order_table_if_not_exists();
        $this->create_message_table_if_not_exists();
        $this->create_danmuku_table_if_not_exists();
        $this->create_circle_related_table_if_not_exists();
        $this->create_change_record_table_if_not_exists();
        $this->create_card_table_if_not_exists();
        $this->create_address_table_if_not_exists();
    }
    public function create_sign_in_table_if_not_exists()
    {
        global $wpdb;
        // 获取表名（包括前缀）
        $table_name = $wpdb->prefix . 'islide_sign_in';
        // 检查表是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // 如果表不存在，创建表
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "
                CREATE TABLE $table_name (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    user_id INT(11) NOT NULL,
                    sign_in_date DATE NOT NULL,
                    sign_in_time TIME NOT NULL,
                    value VARCHAR(1000) NOT NULL,
                    is_supplement TINYINT(1) NOT NULL DEFAULT 0,
                    supplement_date DATETIME DEFAULT NULL,
                    consecutive_days INT(11) NOT NULL DEFAULT 1,
                    PRIMARY KEY (id),
                    KEY user_id (user_id),
                    KEY sign_in_date (sign_in_date)
                ) $charset_collate;
                ";
            
            dbDelta($sql);
        }
    }

    function create_verify_table_if_not_exists()
    {
        global $wpdb;

        // 获取表名（包括前缀）
        $table_name = $wpdb->prefix . 'islide_verify';

        // 检查表是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // 如果表不存在，创建表
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "
            CREATE TABLE $table_name (
                id INT(11) NOT NULL AUTO_INCREMENT,
                user_id INT(11) NOT NULL DEFAULT 0,
                type VARCHAR(50) NOT NULL,
                title VARCHAR(100) NOT NULL,
                money FLOAT NOT NULL DEFAULT 0,
                credit FLOAT NOT NULL DEFAULT 0,
                verified INT(1) NOT NULL DEFAULT 0,
                status INT(1) NOT NULL DEFAULT 0,
                date DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                opinion TEXT NOT NULL,
                data LONGTEXT NOT NULL,
                value VARCHAR(1000) NOT NULL,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY status (status)
            ) $charset_collate;
            ";
            
            dbDelta($sql);
        }
    }

    function create_report_table_if_not_exists()
    {
        global $wpdb;

        // 获取表名（包括前缀）
        $table_name = $wpdb->prefix . 'islide_report';

        // 检查表是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // 如果表不存在，创建表
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "
            CREATE TABLE $table_name (
                id INT(11) NOT NULL AUTO_INCREMENT,
                user_id INT(11) NOT NULL DEFAULT 0,  -- 举报者ID
                reported_id INT(11) NOT NULL DEFAULT 0,  -- 被投诉举报对象ID
                reported_type VARCHAR(50) NOT NULL,  -- 被投诉举报对象类型
                type VARCHAR(100) NOT NULL,  -- 类型
                content LONGTEXT NOT NULL,  -- 投诉举报内容
                status VARCHAR(50) NOT NULL DEFAULT '0',  -- 状态
                date DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',  -- 记录时间
                mark TEXT NOT NULL,  -- 记录
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY reported_id (reported_id),
                KEY reported_type (reported_type),
                KEY type (type),
                KEY status (status),
                KEY date (date)
            ) $charset_collate;
            ";
            
            dbDelta($sql);
        }
    }


    function create_order_table_if_not_exists()
    {
        global $wpdb;

        // 获取表名（包括前缀）
        $table_name = $wpdb->prefix . 'islide_order';

        // 检查表是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // 如果表不存在，创建表
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "
            CREATE TABLE $table_name (
                id BIGINT(20) NOT NULL AUTO_INCREMENT,
                order_id VARCHAR(100) NOT NULL,  -- 订单号
                user_id INT(11) NOT NULL DEFAULT 0,  -- 用户ID
                post_id INT(11) NOT NULL DEFAULT 0,  -- 文章ID
                chapter_id INT(11) NOT NULL DEFAULT 0,  -- 章节ID
                order_name VARCHAR(1000) NOT NULL,  -- 订单名称
                order_type VARCHAR(50) NOT NULL,  -- 订单类型
                order_commodity INT(1) NOT NULL DEFAULT 0,  -- 商品类型 0 : 虚拟物品，1 : 实物
                order_state INT(11) NOT NULL DEFAULT 0,  -- 订单状态
                order_date DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',  -- 创建时间
                order_count INT(11) NOT NULL DEFAULT 0,  -- 订单数量
                order_price FLOAT NOT NULL DEFAULT 0,  -- 订单价格
                order_total FLOAT NOT NULL DEFAULT 0,  -- 订单总价格
                money_type INT(11) NOT NULL DEFAULT 0,  -- 货币类型
                order_key VARCHAR(100) NOT NULL,  -- 订单产品
                order_value VARCHAR(1000) NOT NULL,  -- 订单值
                order_content VARCHAR(1000) NOT NULL,  -- 给卖家的留言相关内容
                pay_type VARCHAR(50) NOT NULL,  -- 支付类型
                tracking_number VARCHAR(100) NOT NULL,  -- 快递查询号
                order_address VARCHAR(1000) NOT NULL,  -- 收货地址
                ip_address VARCHAR(1000) NOT NULL,  -- IP地址
                order_mobile VARCHAR(1000) NOT NULL,  -- 收货联系电话号
                PRIMARY KEY (id),
                KEY order_id (order_id),
                KEY user_id (user_id),
                KEY post_id (post_id),
                KEY chapter_id (chapter_id),
                KEY order_type (order_type),
                KEY order_state (order_state),
                KEY order_date (order_date),
                KEY order_key (order_key),
                KEY pay_type (pay_type),
                KEY tracking_number (tracking_number),
                KEY order_mobile (order_mobile)
            ) $charset_collate;
            ";

            
            dbDelta($sql);
        }
    }

    function create_message_table_if_not_exists()
    {
        global $wpdb;

        // 获取表名（包括前缀）
        $table_name = $wpdb->prefix . 'islide_message';

        // 检查表是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // 如果表不存在，创建表
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "
            CREATE TABLE $table_name (
                id INT(11) NOT NULL AUTO_INCREMENT,  -- 主键
                sender_id INT(11) NOT NULL DEFAULT 0,  -- 发送者ID
                receiver_id INT(11) NOT NULL DEFAULT 0,  -- 接收者ID
                title VARCHAR(100) NOT NULL,  -- 消息标题
                content LONGTEXT NOT NULL,  -- 消息内容
                type VARCHAR(50) NOT NULL,  -- 消息类型
                date DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',  -- 发送时间
                post_id INT(11) NOT NULL DEFAULT 0,  -- 文章ID
                mark VARCHAR(1000) NOT NULL,  -- 记录（当前评论-父评论）
                read_by LONGTEXT NOT NULL,  -- 已读用户
                PRIMARY KEY (id),
                KEY sender_id (sender_id),
                KEY receiver_id (receiver_id),
                KEY type (type),
                KEY date (date),
                KEY post_id (post_id),
                KEY mark (mark)
            ) $charset_collate;
            ";

            
            dbDelta($sql);
        }
    }

    function create_danmuku_table_if_not_exists()
    {
        global $wpdb;

        // 获取表名（包括前缀）
        $table_name = $wpdb->prefix . 'islide_danmuku';

        // 检查表是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // 如果表不存在，创建表
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "
            CREATE TABLE $table_name (
                id BIGINT(20) NOT NULL AUTO_INCREMENT,  -- 主键
                post_id INT(11) DEFAULT NULL,  -- 文章ID
                cid VARCHAR(32) NOT NULL,  -- 弹幕池ID
                user_id INT(11) DEFAULT NULL,  -- 用户ID
                type VARCHAR(128) NOT NULL,  -- 弹幕类型
                text VARCHAR(128) NOT NULL,  -- 弹幕内容
                color VARCHAR(128) NOT NULL,  -- 弹幕颜色
                time INT(11) NOT NULL DEFAULT 0,  -- 时间点
                date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  -- 发送时间
                ip VARCHAR(128) NOT NULL,  -- 用户IP
                PRIMARY KEY (id),
                KEY post_id (post_id),
                KEY user_id (user_id),
                KEY type (type),
                KEY cid (cid),
                KEY time (time),
                KEY date (date)
            ) $charset_collate;
            ";

            
            dbDelta($sql);
        }
    }

    function create_circle_related_table_if_not_exists()
    {
        global $wpdb;

        // 获取表名（包括前缀）
        $table_name = $wpdb->prefix . 'islide_circle_related';

        // 检查表是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // 如果表不存在，创建表
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "
            CREATE TABLE $table_name (
                id INT(11) NOT NULL AUTO_INCREMENT,  -- 主键
                circle_id INT(11) NOT NULL DEFAULT 0,  -- 圈子ID
                user_id INT(11) NOT NULL DEFAULT 0,  -- 记录用户ID
                circle_role VARCHAR(50) NOT NULL,  -- 圈子权限
                join_date DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',  -- 加入时间
                end_date DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',  -- 退出时间
                circle_key VARCHAR(255) NOT NULL,  -- 键
                circle_value VARCHAR(255) NOT NULL,  -- 值
                PRIMARY KEY (id),
                KEY circle_id (circle_id),
                KEY user_id (user_id),
                KEY circle_role (circle_role),
                KEY circle_key (circle_key),
                KEY circle_value (circle_value),
                KEY join_date (join_date),
                KEY end_date (end_date)
            ) $charset_collate;
            ";

            
            dbDelta($sql);
        }
    }

    function create_change_record_table_if_not_exists()
    {
        global $wpdb;

        // 获取表名（包括前缀）
        $table_name = $wpdb->prefix . 'islide_change_record';

        // 检查表是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // 如果表不存在，创建表
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "
            CREATE TABLE $table_name (
                id INT(11) NOT NULL AUTO_INCREMENT,  -- 主键
                user_id INT(11) NOT NULL DEFAULT 0,  -- 记录用户ID
                record_type VARCHAR(50) NOT NULL,  -- 记录类型
                value FLOAT NOT NULL DEFAULT 0,  -- 记录变化值
                total FLOAT NOT NULL DEFAULT 0,  -- 记录变化总值
                type VARCHAR(50) NOT NULL,  -- 记录变化类型
                type_text VARCHAR(100) NOT NULL,  -- 记录变化类型文本
                content LONGTEXT NOT NULL,  -- 记录变化的原因
                date DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',  -- 记录时间
                status VARCHAR(50) NOT NULL,  -- 状态
                record_key VARCHAR(100) NOT NULL,  -- 键
                record_value LONGTEXT NOT NULL,  -- 值
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY record_type (record_type),
                KEY type (type),
                KEY type_text (type_text),
                KEY date (date),
                KEY status (status),
                KEY record_key (record_key)
            ) $charset_collate;
            ";

            
            dbDelta($sql);
        }
    }

    function create_card_table_if_not_exists()
    {
        global $wpdb;

        // 获取表名（包括前缀）
        $table_name = $wpdb->prefix . 'islide_card';

        // 检查表是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // 如果表不存在，创建表
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "
            CREATE TABLE $table_name (
                id INT(11) NOT NULL AUTO_INCREMENT,  -- 主键
                card_code VARCHAR(255) NOT NULL,  -- 卡号
                type VARCHAR(255) NOT NULL,  -- 类型（money/credit/invite/vip）
                value INT NOT NULL DEFAULT 0,  -- 面值
                card_key VARCHAR(255) NOT NULL,  -- 键
                card_value VARCHAR(1000) NOT NULL,  -- 值
                status TINYINT(1) NOT NULL DEFAULT 0,  -- 状态（0或1）
                user_id INT(11) NOT NULL DEFAULT 0,  -- 使用者ID
                PRIMARY KEY (id),
                KEY card_code (card_code),
                KEY user_id (user_id),
                KEY status (status)
            ) $charset_collate;
            ";

            
            dbDelta($sql);
        }
    }
    
    
    function create_address_table_if_not_exists() {
    global $wpdb;

    // 表名，带上前缀
    $table_name = $wpdb->prefix . 'islide_address';

    // 检查表是否存在
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "
        CREATE TABLE $table_name (
            id INT(11) NOT NULL AUTO_INCREMENT, -- 主键
            user_id INT(11) NOT NULL DEFAULT 0, -- 用户ID
            name VARCHAR(255) NOT NULL, -- 收货人姓名
            phone VARCHAR(20) NOT NULL, -- 手机号码
            province VARCHAR(100) NOT NULL, -- 省
            city VARCHAR(100) NOT NULL, -- 市
            district VARCHAR(100) NOT NULL, -- 区
            address_detail VARCHAR(500) NOT NULL, -- 详细地址
            postal_code VARCHAR(20) DEFAULT NULL, -- 邮政编码（可选）
            is_default TINYINT(1) NOT NULL DEFAULT 0, -- 是否默认地址 0=否，1=是
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP, -- 创建时间
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- 更新时间
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY is_default (is_default)
        ) $charset_collate;
        ";

        dbDelta($sql);
    }
}


}