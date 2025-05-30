<?php namespace islide\Modules\Common;
use islide\Modules\Common\User;
use islide\Modules\Common\Post;
/**
 * 书籍模块
 * @version 1.1.0
 * @since 2025
 */
class Book {
    
	public function init() {
        // 注册API端点
        add_action('rest_api_init', function() {
            // 记录阅读进度端点
            register_rest_route('islide/v1', '/record_reading', array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'handle_record_reading'),
                'permission_callback' => function() {
                    return true; // 可以根据需要添加权限检查
                }
            ));
            
            // 获取阅读历史端点 - 改为使用POST方法
            register_rest_route('islide/v1', '/read_history', array(
                'methods' => 'POST', // 从GET改为POST
                'callback' => array(__CLASS__, 'handle_get_read_history'),
                'permission_callback' => function() {
                    return true; // 可以根据需要添加权限检查
                }
            ));
            
            // 获取章节内容端点
            register_rest_route('islide/v1', '/book/passage', array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'handle_get_passage_content'),
                'permission_callback' => function() {
                    return true; // 可以根据需要添加权限检查
                }
            ));
        });
    }
    
    /**
     * 获取章节数量计数
     * 
     * @param int $post_id 文章ID
     * @return int 章节数量
     */
    public static function get_book_count($post_id) {
        $parent_id = get_post_field('post_parent', $post_id);
        
        if($parent_id) {
            $post_id = $parent_id;
        }
        
        // 根据文章类型获取章节数
        if(!$parent_id) {
            $post_type = get_post_type($post_id);
            
            if($post_type == 'passage') {
                return 1;
            }
        }
        
        // 书籍文章的章节数
        $book_meta = get_post_meta((int)$post_id, 'single_book_metabox', true);
        if(empty($book_meta) || empty($book_meta['group'])) {
            return 0;
        }
        
        return count($book_meta['group']);
    }

    /**
     * 获取书籍章节列表
     * 
     * @param int $post_id 文章ID
     * @return array 章节列表数据
     */
    public static function get_book_passage_list($post_id) {
        $user_id = get_current_user_id();
        $current_post_id = $post_id;
        $parent_id = get_post_field('post_parent', $post_id);
        
        if($parent_id) {
            $post_id = $parent_id;
        }
        
        // 获取章节列表
        $passage_list = self::get_passage_list_by_type($post_id, $parent_id);

        if(empty($passage_list)) {
            return array();
        }
        
        // 处理权限和分组
        $processed_data = self::process_passage_permissions($passage_list, $post_id, $user_id);
        
        // 获取经过处理的章节列表
        $passages = self::groupBypassage($processed_data['passage_list']);
        
        // 获取书籍总字数
        $total_words = 0;
        
        // 计算所有章节内容的字数总和
        if (!empty($passages)) {
            foreach ($passages as $passage) {
                // 如果有内容，计算字数（去除HTML标签后）
                if (isset($passage['content'])) {
                    $plain_text = wp_strip_all_tags($passage['content']);
                    // 对于中文内容，可以直接计算字符长度
                    $total_words += mb_strlen($plain_text, 'UTF-8');
                }
            }
        }
        //删除content键值对
        foreach ($passages as $key => &$value) {
            if (isset($value['content'])) {
                unset($value['content']);
            }
        }
        
        // 获取书籍状态
        $book_status = get_post_meta($post_id, 'book_status', true);
        $book_status = $book_status ? $book_status : 'ongoing';
        $status_text = $book_status === 'completed' ? '已完结' : '连载中';
        
        // 获取书籍来源和作者信息
        $book_meta = get_post_meta($post_id, 'single_book_metabox', true);
        $book_meta = is_array($book_meta) ? $book_meta : array();
        
        $book_source = isset($book_meta['book_source']) ? $book_meta['book_source'] : 'original';
        $source_text = $book_source === 'original' ? '原创' : '转载';
        
        // 获取书籍信息
        $book = array(
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'description' => islide_get_desc($post_id, 150),
            'status' => $status_text, // 添加状态文本
            'source' => $source_text, // 添加来源信息
            'meta' => array(
                'views' => (int)get_post_meta($post_id, 'views', true),
                'comments' => islide_number_format(get_comments_number($post_id)),
                'likes' => Post::get_post_vote($post_id),
                'bookmarks' => Post::get_post_favorites($post_id),
                'shares' => 0,
                'word_count' => $total_words // 添加总字数
            ),
            'passages_count' => count($passages),
            'current_passage' => 0, // 默认值
        );
        
        // 获取用户最新的阅读记录
        $read_history = self::get_user_read_history($user_id, $post_id);
        if (!empty($read_history) && isset($read_history['last_passage_id'])) {
            $last_passage_id = $read_history['last_passage_id'];
            $current_index = array_search($last_passage_id, array_column($passages, 'id'));
            if ($current_index !== false) {
                $book['current_passage'] = $current_index + 1;
            }
        }
        
        // 添加封面图片
        $thumb_url = Post::get_post_thumb($post_id);
        if($thumb_url) {
            $book['cover'] = $thumb_url;
        }
        
        // 处理作者信息
        $author_id = get_post_field('post_author', $post_id);
        if ($book_source === 'original') {
            // 原创作品：显示WordPress用户作为作者
            if ($author_id) {
                $book['author'] = User::get_user_public_data($author_id);
            }
        } else {
            // 转载作品：显示自定义作者
            $custom_author = isset($book_meta['book_author']) ? $book_meta['book_author'] : '';
            if (!empty($custom_author)) {
                $book['author'] = array(
                    'name' => $custom_author,
                    'is_custom' => true
                );
            } else if ($author_id) {
                // 如果未设置自定义作者，则显示WordPress用户
                $book['author'] = User::get_user_public_data($author_id);
            }
        }
        
        // 添加分类信息
        $categories = get_the_terms($post_id, 'book_cat');
        if($categories && !is_wp_error($categories)) {
            $book['categories'] = array_map(function($term) {
                return array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                );
            }, $categories);
        }

        //更新views
        update_post_meta($post_id, 'views', (int)get_post_meta($post_id, 'views', true) + 1);
        
        // 构建返回数据 - 更清晰的分层结构
        return array(
            'book' => $book,
            'permissions' => $processed_data['permissions'],
            'passages' => $passages
        );
    }
    
    /**
     * 根据文章类型获取章节列表
     * 
     * @param int $post_id 文章ID
     * @param int $parent_id 父级文章ID
     * @return array 章节列表
     */
    private static function get_passage_list_by_type($post_id, $parent_id) {
        $post_type = get_post_type($post_id);
        
        if(!$parent_id && $post_type == 'passage') {
            $passage_meta = get_post_meta($post_id, 'single_passage_metabox', true);
            $passages = !empty($passage_meta['book']) ? $passage_meta['book'] : array();
             
            return array(
                array(
                    'type' => 'passage',
                    'id' => $post_id,
                    'title' => get_the_title($post_id),
                    'url' => '/passage/' . $post_id,
                )
            );
            
        } else {
            $book_meta = get_post_meta((int)$post_id, 'single_book_metabox', true);
            return !empty($book_meta['group']) ? $book_meta['group'] : array();
        }
    }
    
    /**
     * 处理章节权限
     * 
     * @param array $passage_list 章节列表
     * @param int $post_id 文章ID
     * @param int $user_id 用户ID
     * @return array 处理后的数据
     */
    private static function process_passage_permissions($passage_list, $post_id, $user_id) {
        $allowList = array();
        $index = 0;
        $free_count = 0;
        $free_book = false;
        
        // 简化处理逻辑，不再区分类型
        foreach ($passage_list as $key => &$value) {
            $passage_id = !empty($value['id']) ? $value['id'] : $post_id;
            $can = self::islide_check_user_can_book_allow($passage_id, $user_id, $index);
            
            // 记录权限状态
            $allowList[] = $can['allow'];
            
            // 无论权限是否通过，都获取基本信息
            $passage_post = get_post($passage_id);
            if ($passage_post) {
                // 提取简短摘要，无论权限如何都可以显示
                $value['excerpt'] = $passage_post->post_excerpt ?: wp_trim_words(wp_strip_all_tags($passage_post->post_content), 55, '...');
                $value['date'] = get_the_date('Y-m-d H:i:s', $passage_id);
                
                // 只有在权限通过的情况下才提供完整内容和URL
                if ($can['allow']) {
                    // 处理 Gutenberg 内容
                    $content = $passage_post->post_content;
                    $content = do_blocks($content);
                    $content = wptexturize($content);
                    $content = convert_smilies($content);
                    $content = wpautop($content);
                    
                    $value['content'] = $content;
                    $value['url'] = $value['url']; // 保留原有URL
                } else {
                    // 权限不通过则不提供内容和URL
                    $value['content'] = '';
                    $value['url'] = '';
                }
            }
            
            if(isset($can['free_count'])) {
                $free_count = $can['free_count'];
                $free_book = true;
            }
            
            $index++;
        }
        
        return array(
            'passage_list' => $passage_list,
            'permissions' => array(
                'allowList' => $allowList,
                'free_count' => $free_count,
                'free_book' => $free_book
            )
        );
    }
    
    /**
     * 获取父级文章信息
     * 
     * @param int $parent_id 父级文章ID
     * @return array 文章信息
     */
    private static function get_parent_post_info($parent_id) {
        $post_author = get_post_field('post_author', $parent_id);
        $thumb_url = Post::get_post_thumb($parent_id);
        $thumb = '';
        
        if($thumb_url) {
            $thumb = islide_get_thumb(array(
                'url' => $thumb_url,
                'width' => 180,
                'height' => 250,
                'ratio' => 2
            ));
        }
        
        return array(
            'id' => $parent_id,
            'title' => get_the_title($parent_id),
            'link' => get_permalink($parent_id),
            'thumb' => $thumb,
            'desc' => islide_get_desc($parent_id, 150),
            'user' => User::get_user_public_data($post_author),
        );
    }
    
    /**
     * 处理章节列表数据
     * 
     * @param array $array 章节列表
     * @return array 处理后的数据结构
     */
    public static function groupBypassage($array) {
        // 创建章节列表
        $book_list = array();
        
        foreach ($array as $item) {
            // 创建干净的章节数据，只保留必要字段
            $passage_item = array(
                'id' => $item['id'],
                'title' => $item['title'],
                'url' => '/book/passage/' . $item['id'],
            );
            
            // 保留动态添加的内容字段
            if (isset($item['content'])) {
                $passage_item['content'] = $item['content'];
            }
            if (isset($item['excerpt'])) {
                $passage_item['excerpt'] = $item['excerpt'];
            }
            if (isset($item['date'])) {
                $passage_item['date'] = $item['date'];
            }
            
            $book_list[] = $passage_item;
        }
        
        // 直接返回章节列表数组，不再嵌套包装
        return $book_list;
    }
    
    /**
     * 书籍权限检查函数
     *
     * @param int $book_id 书籍/章节ID
     * @param int $user_id 用户ID
     * @param int $index 章节在列表中的索引
     * @return array 权限检查结果
     */
    public static function islide_check_user_can_book_allow($book_id, $user_id, $index) {
        // 初始化结果
        $result = [
            'allow' => false,   // 是否允许阅读
            'type' => '',       // 权限类型
            'value' => 0,       // 当前付费金额或积分
            'total_value' => 0, // 总金额或积分要求
            'not_login_pay' => false, // 是否支持未登录用户购买
            'roles' => [],      // 限制角色
        ];
        
        $_post_type = get_post_type($book_id);
        
        $user_lv = [
            'lv'  => User::get_user_lv($user_id),  // 普通等级信息
            'vip' => User::get_user_vip($user_id), // VIP 等级信息
        ];
        $is_vip = !empty($user_lv['vip']) ? true : false;
        $book_meta = array();
        
        // 处理书籍章节
        if($_post_type == 'passage') {
            // VIP用户免费阅读处理
            if ($is_vip) {
                $user_free_count = self::get_user_can_free_book_count($user_id);
                if($user_free_count && $user_free_count > 0) {
                    $result['free_count'] = $user_free_count;
                    $result['allow'] = true;
                    return $result;
                }
            }
            
            $parent_id = get_post_field('post_parent', $book_id);
            $book_meta = get_post_meta((int)$parent_id, 'single_book_metabox', true);
            
            if (empty($book_meta) || empty($book_meta['islide_book_role'])) {
                $result['type'] = 'free'; // 默认视为免费
                $result['allow'] = true;
                return $result;
            }
            
            if(isset($book_meta['group']) && !empty($book_meta['group']) && isset($book_meta['group'][$index]) && $book_id == $book_meta['group'][$index]['id']) {
                $result['type'] = $book_meta['islide_book_role'];
                $result['value'] = isset($book_meta['islide_book_pay_value']) ? (int)$book_meta['islide_book_pay_value'] : 0;
                $result['total_value'] = isset($book_meta['islide_book_pay_total']) ? (int)$book_meta['islide_book_pay_total'] : 0;
                $result['not_login_pay'] = !empty($book_meta['islide_book_not_login_buy']);
                $result['roles'] = isset($book_meta['islide_book_roles']) ? $book_meta['islide_book_roles'] : array();
            }
        }
        
        // 如果未设置权限类型，默认设为免费
        if (empty($result['type'])) {
            $result['type'] = 'free';
        }
        
        // 检查权限类型
        switch ($result['type']) {
            case 'free':
                $result['allow'] = true; // 免费直接允许
                break;
                
            case 'credit':
            case 'money':
                // 检查是否已支付
                $result['allow'] = self::has_user_purchased_book($book_id, $user_id, $index) ? true : false;
                break;

            case 'login':
                // 检查是否已登录
                $result['allow'] = get_current_user_id() ? true : false;
                break;
                
            case 'comment':
                // 检查是否有评论
                $result['allow'] = self::has_user_commented($book_id, $user_id) ? true : false;
                break;
                
            case 'password':
                // 检查是否输入密码
                $result['allow'] = self::verifyPasswordCookie($book_id);
                break;
                
            case 'roles':
                // 检查用户是否具有所需角色
                $result['allow'] = self::check_user_book_roles($book_meta, $user_lv) ? true : false;
                break;

            default:
                $result['allow'] = false; // 未知类型默认为禁止
                break;
        }

        return $result;
    }

    /**
     * 获取用户可免费阅读的书籍章节数
     * 
     * @param int $user_id 用户ID
     * @return int 可免费阅读章节数
     */
    public static function get_user_can_free_book_count($user_id) {
        $user_roles = User::get_user_roles();
        $user_lv = [
            'lv'  => User::get_user_lv($user_id), // 等级信息
            'vip' => User::get_user_vip($user_id), // VIP 信息
        ];
        
        // 检查必要参数是否存在
        if (!isset($user_lv['vip'], $user_lv['vip']['lv'], $user_roles[$user_lv['vip']['lv']])) {
            return false;
        }
        
        // 获取用户等级相关数据
        $vip_level = $user_lv['vip']['lv'];
        $free_book = isset($user_roles[$vip_level]['free_book']) ? (bool) $user_roles[$vip_level]['free_book'] : false;
        
        // 如果免费阅读权限为 false，直接返回
        if (!$free_book) {
            return false;
        }
        
        $free_book_count = isset($user_roles[$vip_level]['free_book_count']) ? (int) $user_roles[$vip_level]['free_book_count'] : 0;

        // 从 wp_usermeta 获取 islide_book_count
        $meta_key = 'islide_book_count';
        $islide_free_count = get_user_meta($user_id, $meta_key, true);

        // 如果数据为空，初始化为默认值
        if (empty($islide_free_count)) {
            $islide_free_count = [
                'date' => current_time('Y-m-d'),
                'count' => $free_book_count,
                'posts' => [],
            ];
            update_user_meta($user_id, $meta_key, $islide_free_count);
        }

        // 检查日期是否为今天
        $current_date = current_time('Y-m-d');
        if ($islide_free_count['date'] !== $current_date) {
            // 日期不同，重置 count 并更新时间
            $islide_free_count['date'] = $current_date;
            $islide_free_count['count'] = $free_book_count;
            $islide_free_count['posts'] = [];
            // 更新 wp_usermeta
            update_user_meta($user_id, $meta_key, $islide_free_count);
        }

        // 返回数据
        return $islide_free_count['count'];
    }

    /**
     * 检查用户书籍角色权限
     * 
     * @param array $book_meta 书籍元数据
     * @param array $user_lv 用户等级信息
     * @return bool 是否有权限
     */
    public static function check_user_book_roles($book_meta, $user_lv) {
        // 获取书籍的限制角色列表
        if(!get_current_user_id()){
            return false;
        }
        
        $allowed_roles = isset($book_meta['islide_book_roles']) && is_array($book_meta['islide_book_roles']) 
            ? $book_meta['islide_book_roles'] 
            : [];

        // 格式化用户等级和 VIP 等级
        $user_lv_role = isset($user_lv['lv']['lv']) ? 'lv' . $user_lv['lv']['lv'] : null;  // 转换为 lv0, lv1 等格式
        $user_vip_role = isset($user_lv['vip']['lv']) ? $user_lv['vip']['lv'] : null; // VIP 等级直接使用

        // 检查用户等级或 VIP 等级是否符合书籍要求
        if (in_array($user_lv_role, $allowed_roles) || in_array($user_vip_role, $allowed_roles)) {
            return true;
        }

        return false;
    }

    /**
     * 检查用户是否已评论
     *
     * @param int $post_id 文章ID
     * @param int $user_id 用户ID
     * @return bool 是否已评论
     */
    public static function has_user_commented($post_id, $user_id) {
        $comments = get_comments([
            'post_id' => $post_id,
            'user_id' => $user_id,
            'status'  => 'approve', // 仅获取审核通过的评论
            'number'  => 1,         // 只需要一条记录
        ]);

        return !empty($comments);
    }

    /**
     * 验证密码Cookie
     * 
     * @param int $post_id 文章ID
     * @return bool 密码是否有效
     */
    public static function verifyPasswordCookie($post_id) {
        $post_id = (int)$post_id;
        if (!$post_id || !isset($_COOKIE['password_verify'])) {
            return false;
        }

        $raw = urldecode($_COOKIE['password_verify']);
        $data = json_decode($raw, true);

        if (!$data || json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        $code = trim($data['code'] ?? '', " \t\n\r\0\x0B\xC2\xA0");
        $cookie_post_id = (int)($data['post_id'] ?? 0);
        $type = $data['type'] ?? 'post';

        if (!$code || $cookie_post_id !== $post_id) {
            return false;
        }

        // 获取官方密码配置
        $verify_option = islide_get_option('password_verify');
        $official_code = $verify_option['code'] ?? '';

        // 获取该 post 或 term 的独立密码
        
        $password = get_post_meta($post_id, 'islide_post_password', true);

        // 验证是否匹配
        if ($code === $official_code || $code === $password) {
            return true;
        }

        return false;
    }

    /**
     * 检查用户是否购买了书籍/章节
     * 
     * @param int $book_id 书籍/章节ID
     * @param int $user_id 用户ID
     * @param int $index 章节索引
     * @return bool 是否已购买
     */
    public static function has_user_purchased_book($book_id, $user_id, $index) {
        // 获取当前 post 类型
        $post_type = get_post_type($book_id);
        
        // 如果是书籍类型
        if ($post_type == 'book') {
            // 检查是否购买了整本书
            $buy_data_all = get_post_meta($book_id, 'islide_book_buy', true);
            $buy_data_all = is_array($buy_data_all) ? $buy_data_all : [];

            if (in_array($user_id, $buy_data_all)) {
                return true; // 已购买整本书
            }
        } elseif($post_type == 'passage'){
            $post_id = get_post_field('post_parent', $book_id);
            // 检查是否购买了整本书
            $buy_data_all = get_post_meta($post_id, 'islide_book_buy', true);
            $buy_data_all = is_array($buy_data_all) ? $buy_data_all : [];
            
            if (in_array($user_id, $buy_data_all)) {
                return true; // 已购买整本书
            }
            
            // 检查是否购买了指定章节
            $buy_data_group = get_post_meta($post_id, 'islide_book_buy_group', true);
            $buy_data_group = is_array($buy_data_group) ? $buy_data_group : [];
            
            // 检查指定索引的章节是否存在用户购买记录
            return isset($buy_data_group[$index]) &&
                   is_array($buy_data_group[$index]) &&
                   in_array($user_id, $buy_data_group[$index]);
        }

        // 默认返回 false，表示未购买
        return false;
    }
    
    /**
     * 记录用户已读章节
     * 
     * @param int $user_id 用户ID
     * @param int $book_id 书籍ID
     * @param int $passage_id 章节ID
     * @return bool 是否成功记录
     */
    public static function record_user_read_passage($user_id, $book_id, $passage_id) {
        // 如果未登录，不记录阅读进度
        if (!$user_id) {
            return false;
        }
        
        // 获取书籍所有章节
        $passage_list = self::get_passage_list_by_type($book_id, 0);
        if (empty($passage_list)) {
            return false;
        }
        
        // 将章节列表处理为ID数组，便于找出当前章节索引
        $passage_ids = array_column($passage_list, 'id');
        $current_index = array_search($passage_id, $passage_ids);
        
        if ($current_index === false) {
            return false;
        }
        
        // 计算阅读进度百分比
        $total_passages = count($passage_ids);
        $progress = round(($current_index + 1) / $total_passages * 100);
        
        // 获取用户当前的阅读记录
        $meta_key = 'book_read_history';
        $read_history = get_user_meta($user_id, $meta_key, true);
        
        if (!is_array($read_history)) {
            $read_history = [];
        }
        
        // 更新该书籍的阅读记录
        $read_history[$book_id] = [
            'last_passage_id' => $passage_id,
            'last_read_time' => current_time('mysql'),
            'progress' => $progress,
            'total_passages' => $total_passages
        ];
        
        // 限制记录的书籍数量，保留最近阅读的50本
        if (count($read_history) > 50) {
            // 按最后阅读时间排序
            uasort($read_history, function($a, $b) {
                return strtotime($b['last_read_time']) - strtotime($a['last_read_time']);
            });
            
            // 只保留前50条记录
            $read_history = array_slice($read_history, 0, 50, true);
        }
        
        // 更新用户元数据
        update_user_meta($user_id, $meta_key, $read_history);
        
        // 更新书籍热度（可选）
        self::update_book_reading_heat($book_id);
        
        return true;
    }

    /**
     * 获取用户阅读历史
     * 
     * @param int $user_id 用户ID
     * @param int $book_id 书籍ID（可选，如果提供则只返回该书籍的阅读历史）
     * @return array 阅读历史记录
     */
    public static function get_user_read_history($user_id, $book_id = null) {
        if (!$user_id) {
            return [];
        }
        
        $meta_key = 'book_read_history';
        $read_history = get_user_meta($user_id, $meta_key, true);
        
        if (!is_array($read_history)) {
            return [];
        }
        
        // 如果指定了书籍ID，只返回该书籍的阅读记录
        if ($book_id && isset($read_history[$book_id])) {
            return $read_history[$book_id];
        }
        
        // 否则返回所有阅读记录
        return $read_history;
    }

    /**
     * 更新书籍阅读热度
     * 
     * @param int $book_id 书籍ID
     * @return void
     */
    private static function update_book_reading_heat($book_id) {
        // 获取当前热度
        $heat = (int)get_post_meta($book_id, 'book_reading_heat', true);
        
        // 增加热度
        $heat++;
        
        // 更新热度
        update_post_meta($book_id, 'book_reading_heat', $heat);
        
        // 可以设置一个衰减计划，例如每天自动减少一定比例的热度
        // 这里可以使用WordPress的定时任务来实现
    }

    /**
     * 获取用户阅读历史的API处理函数
     * 
     * @param mixed $request_or_book_id 请求对象或书籍ID
     * @return array 阅读历史数据
     */
    public static function handle_get_read_history($request_or_book_id = null) {
        $user_id = get_current_user_id();
        $book_id = null;
        
        // 如果未登录，返回错误
        if (!$user_id) {
            return array(
                'success' => false,
                'message' => '用户未登录'
            );
        }
        
        // 判断传入参数类型，支持直接传入book_id或请求对象
        if (is_object($request_or_book_id) && method_exists($request_or_book_id, 'get_params')) {
            // 是WP_REST_Request对象
            $params = $request_or_book_id->get_params();
            $book_id = isset($params['book_id']) ? (int)$params['book_id'] : null;
        } else if ($request_or_book_id !== null) {
            // 直接传入book_id
            $book_id = (int)$request_or_book_id;
        }
        
        // 获取阅读历史
        $history = self::get_user_read_history($user_id, $book_id);
        
        // 如果有书籍ID但没有该书籍的阅读记录
        if ($book_id && empty($history)) {
            return array(
                'success' => true,
                'book_id' => $book_id,
                'has_history' => false,
                'history' => null
            );
        }
        
        // 如果是请求单本书籍的阅读历史
        if ($book_id) {
            return array(
                'success' => true,
                'book_id' => $book_id,
                'has_history' => true,
                'history' => $history
            );
        }
        
        // 如果是请求所有阅读历史，需要添加更多信息
        if (!empty($history)) {
            $enriched_history = array();
            
            foreach ($history as $bk_id => $record) {
                // 获取书籍基本信息
                $book_title = get_the_title($bk_id);
                $book_thumb = Post::get_post_thumb($bk_id);
                
                // 获取书籍状态
                $book_status = get_post_meta($bk_id, 'book_status', true);
                $book_status = $book_status ? $book_status : 'ongoing';
                $status_text = $book_status === 'completed' ? '已完结' : '连载中';
                
                // 获取书籍来源和作者信息
                $book_meta = get_post_meta($bk_id, 'single_book_metabox', true);
                $book_meta = is_array($book_meta) ? $book_meta : array();
                
                $book_source = isset($book_meta['book_source']) ? $book_meta['book_source'] : 'original';
                $source_text = $book_source === 'original' ? '原创' : '转载';
                
                // 获取最后阅读章节信息
                $last_passage_id = $record['last_passage_id'] ?? 0;
                $last_passage_title = $last_passage_id ? get_the_title($last_passage_id) : '';
                
                // 基本书籍信息
                $book_info = array(
                    'book_id' => $bk_id,
                    'book_title' => $book_title,
                    'book_cover' => $book_thumb,
                    'book_status' => $status_text,
                    'book_source' => $source_text,
                    'last_passage_title' => $last_passage_title
                );
                
                // 处理作者信息
                $author_id = get_post_field('post_author', $bk_id);
                if ($book_source === 'original') {
                    // 原创作品：显示WordPress用户作为作者
                    if ($author_id) {
                        $author_data = User::get_user_public_data($author_id);
                        $book_info['author'] = $author_data['name'];
                        $book_info['author_avatar'] = $author_data['avatar'];
                    }
                } else {
                    // 转载作品：显示自定义作者
                    $custom_author = isset($book_meta['book_author']) ? $book_meta['book_author'] : '';
                    if (!empty($custom_author)) {
                        $book_info['author'] = $custom_author;
                        $book_info['is_custom_author'] = true;
                    } else if ($author_id) {
                        // 如果未设置自定义作者，则显示WordPress用户
                        $author_data = User::get_user_public_data($author_id);
                        $book_info['author'] = $author_data['name'];
                        $book_info['author_avatar'] = $author_data['avatar'];
                    }
                }
                
                // 添加到结果中
                $enriched_history[$bk_id] = array_merge($record, $book_info);
            }
            
            // 按最后阅读时间排序
            uasort($enriched_history, function($a, $b) {
                return strtotime($b['last_read_time']) - strtotime($a['last_read_time']);
            });
            
            return array(
                'success' => true,
                'count' => count($enriched_history),
                'history' => $enriched_history
            );
        }
        
        // 没有阅读历史
        return array(
            'success' => true,
            'count' => 0,
            'history' => array()
        );
    }

    /**
     * 记录阅读进度的API处理函数
     * 
     * @param mixed $request_or_params 请求对象或参数数组
     * @return array 处理结果
     */
    public static function handle_record_reading($request_or_params) {
        $user_id = get_current_user_id();
        $passage_id = 0;
        
        // 判断传入参数类型
        if (is_object($request_or_params) && method_exists($request_or_params, 'get_params')) {
            // 是WP_REST_Request对象
            $params = $request_or_params->get_params();
            $passage_id = isset($params['passage_id']) ? (int)$params['passage_id'] : 0;
        } else if (is_array($request_or_params)) {
            // 直接传入参数数组
            $passage_id = isset($request_or_params['passage_id']) ? (int)$request_or_params['passage_id'] : 0;
        }
        
        if (!$user_id || !$passage_id) {
            return array(
                'success' => false,
                'message' => '用户未登录或章节ID不能为空'
            );
        }

        // 获取父级书籍ID
        $book_id = get_post_field('post_parent', $passage_id);
        if (!$book_id) {
            return array(
                'success' => false,
                'message' => '无法获取父级书籍信息'
            );
        }

        $result = self::record_user_read_passage($user_id, $book_id, $passage_id);
        
        if ($result) {
            // 如果记录成功，获取更新后的阅读历史
            $history = self::get_user_read_history($user_id, $book_id);
            return array(
                'success' => true,
                'history' => $history
            );
        } else {
            return array(
                'success' => false,
                'message' => '记录阅读进度失败'
            );
        }
    }

    /**
     * 获取章节内容的API处理函数
     * 
     * @param WP_REST_Request $request 请求对象
     * @return array 章节内容数据
     */
    public static function handle_get_passage_content($request) {
        $params = $request->get_params();
        $passage_id = isset($params['passage_id']) ? (int)$params['passage_id'] : 0;
        $user_id = get_current_user_id();
        
        if (!$passage_id) {
            return array(
                'success' => false,
                'message' => '章节ID不能为空'
            );
        }
        
        // 获取章节信息
        $passage = get_post($passage_id);
        if (!$passage || $passage->post_type !== 'passage') {
            return array(
                'success' => false,
                'message' => '章节不存在'
            );
        }
        
        // 获取父级书籍ID
        $book_id = get_post_field('post_parent', $passage_id);
        if (!$book_id) {
            return array(
                'success' => false,
                'message' => '无法获取父级书籍信息'
            );
        }
        
        // 获取章节在列表中的索引
        $passage_list = self::get_passage_list_by_type($book_id, 0);
        $passage_ids = array_column($passage_list, 'id');
        $index = array_search($passage_id, $passage_ids);
        
        if ($index === false) {
            return array(
                'success' => false,
                'message' => '无法获取章节索引'
            );
        }
        
        // 检查用户权限
        $permission = self::islide_check_user_can_book_allow($passage_id, $user_id, $index);
        
        // 获取上一篇和下一篇信息
        $prev_passage = null;
        $next_passage = null;
        
        // 获取上一篇
        if ($index > 0) {
            $prev_id = $passage_ids[$index - 1];
            $prev_passage = get_post($prev_id);
            if ($prev_passage) {
                $prev_permission = self::islide_check_user_can_book_allow($prev_id, $user_id, $index - 1);
                $prev_passage = array(
                    'id' => $prev_id,
                    'title' => get_the_title($prev_id),
                    'url' => '/book/passage/' . $prev_id,
                    'permission' => $prev_permission
                );
            }
        }
        
        // 获取下一篇
        if ($index < count($passage_ids) - 1) {
            $next_id = $passage_ids[$index + 1];
            $next_passage = get_post($next_id);
            if ($next_passage) {
                $next_permission = self::islide_check_user_can_book_allow($next_id, $user_id, $index + 1);
                $next_passage = array(
                    'id' => $next_id,
                    'title' => get_the_title($next_id),
                    'url' => '/book/passage/' . $next_id,
                    'permission' => $next_permission
                );
            }
        }
        
        // 获取书籍基本信息
        $book_title = get_the_title($book_id);
        
        // 获取书籍状态
        $book_status = get_post_meta($book_id, 'book_status', true);
        $book_status = $book_status ? $book_status : 'ongoing';
        $status_text = $book_status === 'completed' ? '已完结' : '连载中';
        
        // 获取书籍来源和作者信息
        $book_meta = get_post_meta($book_id, 'single_book_metabox', true);
        $book_meta = is_array($book_meta) ? $book_meta : array();
        
        $book_source = isset($book_meta['book_source']) ? $book_meta['book_source'] : 'original';
        $source_text = $book_source === 'original' ? '原创' : '转载';
        
        // 构建书籍信息
        $book_info = array(
            'id' => $book_id,
            'title' => $book_title,
            'status' => $status_text,
            'source' => $source_text
        );
        
        // 处理作者信息
        $author_id = get_post_field('post_author', $book_id);
        if ($book_source === 'original') {
            // 原创作品：显示WordPress用户作为作者
            if ($author_id) {
                $book_info['author'] = User::get_user_public_data($author_id);
            }
        } else {
            // 转载作品：显示自定义作者
            $custom_author = isset($book_meta['book_author']) ? $book_meta['book_author'] : '';
            if (!empty($custom_author)) {
                $book_info['author'] = array(
                    'name' => $custom_author,
                    'is_custom' => true
                );
            } else if ($author_id) {
                // 如果未设置自定义作者，则显示WordPress用户
                $book_info['author'] = User::get_user_public_data($author_id);
            }
        }
        
        // 构建返回数据
        $response = array(
            'success' => true,
            'passage' => array(
                'id' => $passage_id,
                'book' => $book_info,
                'title' => get_the_title($passage_id),
                'date' => get_the_date('Y-m-d H:i:s', $passage_id),
                'excerpt' => $passage->post_excerpt ?: wp_trim_words(wp_strip_all_tags($passage->post_content), 55, '...'),
                'permission' => $permission,
                'navigation' => array(
                    'prev' => $prev_passage,
                    'next' => $next_passage
                )
            )
        );
        
        // 获取章节的预览内容
        $passage_meta = get_post_meta($passage_id, 'single_passage_metabox', true);
        $preview_content = isset($passage_meta['preview']) ? $passage_meta['preview'] : '';

        // 如果用户没有权限但有预览内容，添加预览内容
        if (!$permission['allow'] && !empty($preview_content)) {
            // 处理预览内容格式
            $preview_content = str_replace(["\r\n", "\r"], "\n", $preview_content); // 统一换行符为 \n
            $preview_content = do_blocks($preview_content);
            $preview_content = wptexturize($preview_content);
            $preview_content = convert_smilies($preview_content);
            
            $response['passage']['preview'] = $preview_content;
        }

        // 如果用户有权限，添加完整内容
        if ($permission['allow']) {
            // 处理内容格式
            $content = $passage->post_content;
            
            // 保留原始换行符
            $content = str_replace(["\r\n", "\r"], "\n", $content); // 统一换行符为 \n
            
            // 处理其他格式
            $content = do_blocks($content);
            $content = wptexturize($content);
            $content = convert_smilies($content);
            
            $response['passage']['content'] = $content;
            
            // 计算字数（去除HTML标签后）
            $plain_text = wp_strip_all_tags($content);
            $response['passage']['word_count'] = mb_strlen($plain_text, 'UTF-8');
            
            // 记录阅读进度
            self::record_user_read_passage($user_id, $book_id, $passage_id);
        }
        //更新views
        update_post_meta($passage_id, 'views', (int)get_post_meta($passage_id, 'views', true) + 1);
        
        return $response;
    }
}