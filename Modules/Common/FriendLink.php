<?php
/**
 * 友链管理类
 * 
 * 处理网站友情链接的添加、获取、更新和统计功能
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;

class FriendLink {

    /**
     * 获取所有链接分类及其中的链接（处理API请求）
     *
     * @return \WP_REST_Response API响应
     */
    public static function get_link_categories_and_links() {
        // 获取所有 link_category 分类
        $show_cat = islide_get_option('link_page_cat');

        $response = [];

        if (!empty($show_cat) && !is_wp_error($show_cat)) {
            foreach ($show_cat as $value) {
                $term = get_term_by('id', $value, 'link_category');
                $links = get_bookmarks(array(
                        'category' => $term->term_id,
                        'orderby'  => 'link_rating',
                        'order'    => 'DESC',
                    ));

                $response[] = array(
                    'category_id'   => $term->term_id,
                    'category_name' => $term->name,
                    'category_slug' => $term->slug,
                    'links'         => array_map(function($link) {
                        return array(
                            'id'       => $link->link_id,
                            'name'     => $link->link_name,
                            'url'      => $link->link_url,
                            'desc'     => $link->link_description,
                            'target'   => $link->link_target,
                            'rss'      => $link->link_rss,
                            'rssPosts' => self::fetch_rss_articles($link->link_rss),
                            'logo'     => $link->link_image
                        );
                    }, $links),
                );
            }
        }
        
        $page = [
            'name'   => islide_get_option('link_page_name'),
            'desc'   => islide_get_option('link_page_desc'),
            'email'  => islide_get_option('link_page_email'),
            'link'   => islide_get_option('link_page_link'),
            'rss'    => islide_get_option('link_page_rss'),
            'avatar' => islide_get_option('link_page_avatar'),
            'title'  => islide_get_option('link_page_title'),
            'column' => islide_get_option('link_page_column'),
        ];
        
        return new \WP_REST_Response(['user' => $page, 'data' => $response], 200);
    }

    /**
     * 添加友链（处理API请求）
     *
     * @param \WP_REST_Request $request API请求对象
     * @return \WP_REST_Response|\WP_Error API响应或错误
     */
    public static function add_friend_link($request) {
        $data = $request->get_json_params();
        
        // 验证必填字段
        if (empty($data['name']) || empty($data['url']) || empty($data['logo'])) {
            return new \WP_Error('missing_fields', '请填写所有必填项', ['status' => 400]);
        }

        // 检查 URL 是否有效
        if (!filter_var($data['url'], FILTER_VALIDATE_URL) || !filter_var($data['logo'], FILTER_VALIDATE_URL)) {
            return new \WP_Error('invalid_url', '请输入有效的站点/logo格式', ['status' => 400]);
        }
        
        if (isset($data['rss']) && !empty($data['rss']) && !filter_var($data['rss'], FILTER_VALIDATE_URL)) {
            return new \WP_Error('invalid_url', '请输入有效的rss网址格式', ['status' => 400]);
        }
        
        if(isset($data['email']) && !empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return new \WP_Error('invalid_email', '请输入有效的邮箱格式', ['status' => 400]);
        }

        $cat_id = intval($data['cat_id']);
        $term = get_term_by('id', $cat_id, 'link_category');

        if (!$term || !$term->term_id) {
            return new \WP_Error('cat_error', '没有该分类', ['status' => 403]);
        }

        // 创建友链
        $result = self::_add_friend_link($data);
        
        if(isset($result['error'])) {
            return new \WP_Error('add_link_error', $result['error'], ['status' => 500]);
        }
        
        return new \WP_REST_Response([
            'message' => '友链添加成功，等待审核！',
            'id' => $result['id']
        ], 200);
    }

    /**
     * 内部添加友链函数
     *
     * @param array $data 友链数据
     * @return array 处理结果
     */
    private static function _add_friend_link($data) {
        global $wpdb;
        
        // 开始数据库事务
        $wpdb->query('START TRANSACTION');

        // 构建 WordPress 友链数据
        $new_link = array(
            'link_name'        => sanitize_text_field($data['name']),
            'link_url'         => esc_url_raw($data['url']),
            'link_image'       => esc_url_raw($data['logo']),
            'link_description' => sanitize_textarea_field($data['desc']),
            'link_visible'     => 'N', // 默认不可见，需审核
        );
        
        if(isset($data['rss']) && !empty($data['rss'])) {
            $new_link['link_rss'] = esc_url_raw($data['rss']);
        }
        
        // 插入友链
        $wpdb->insert($wpdb->prefix . 'links', $new_link);
        $insert_id = $wpdb->insert_id; // 获取插入的链接 ID

        // 检查插入结果
        if (!$insert_id) {
            $wpdb->query('ROLLBACK'); // 回滚事务
            return ['error' => '数据库错误，请稍后重试'];
        }
        
        // 插入分类关联
        $term_relationships = array(
            'object_id'        => $insert_id,
            'term_taxonomy_id' => intval($data['cat_id']),
        );

        $wpdb->insert($wpdb->prefix . 'term_relationships', $term_relationships);

        if ($wpdb->last_error) { // 检查是否插入失败
            $wpdb->query('ROLLBACK'); // 回滚事务
            return ['error' => '分类关联失败，请稍后重试'];
        }

        // 更新分类文章计数
        $wpdb->query("UPDATE {$wpdb->prefix}term_taxonomy SET count = count + 1 WHERE term_taxonomy_id = " . intval($data['cat_id']));
        
        if(isset($data['email'])) {
            update_term_meta($insert_id, 'islide_links_email', sanitize_email($data['email']));
        }

        // 提交事务
        $wpdb->query('COMMIT');

        return [
            'message' => '友链添加成功，等待审核！',
            'id'      => $insert_id
        ];
    }

    /**
     * 获取友链列表（处理API请求）
     *
     * @param \WP_REST_Request $request API请求对象
     * @return \WP_REST_Response|\WP_Error API响应或错误
     */
    public static function get_link_list($request) {
        $params = $request->get_params();
        $result = self::_get_link_list($params);
        
        if(isset($result['error'])) {
            return new \WP_Error('get_link_list_error', $result['error'], ['status' => 400]);
        }
        
        return new \WP_REST_Response($result, 200);
    }

    /**
     * 内部获取友链列表函数
     *
     * @param array $params 查询参数
     * @return array 友链数据
     */
    private static function _get_link_list($params) {
        global $wpdb;

        $term_id = isset($params['id']) ? intval($params['id']) : 0;
        $paged   = isset($params['paged']) ? max(1, intval($params['paged'])) : 1;
        $size    = isset($params['size']) ? max(1, intval($params['size'])) : 10;
        $offset  = ($paged - 1) * $size;

        $all_links = [];

        if ($term_id === 0) {
            // 所有分类下的链接
            $categories = get_terms([
                'taxonomy'   => 'link_category',
                'hide_empty' => false,
            ]);

            if (!empty($categories) && !is_wp_error($categories)) {
                foreach ($categories as $cat) {
                    $links = get_bookmarks([
                        'category'        => $cat->term_id,
                        'orderby'         => 'link_rating',
                        'order'           => 'DESC',
                        'hide_invisible'  => false,
                    ]);
                    $all_links = array_merge($all_links, $links);
                }
            }
        } else {
            if (!term_exists($term_id, 'link_category')) {
                return ['error' => '无效的分类ID'];
            }

            $all_links = get_bookmarks([
                'category'        => $term_id,
                'orderby'         => 'link_rating',
                'order'           => 'DESC',
                'hide_invisible'  => false,
            ]);
        }

        $total = count($all_links);
        $pages = ceil($total / $size);
        $paged_links = array_slice($all_links, $offset, $size);

        $data = array_map(function ($link) use ($wpdb) {
            // 查出该链接真实绑定的分类 ID（可能多个，只取第一个）
            $term_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT tr.term_taxonomy_id FROM {$wpdb->term_relationships} tr 
                    JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    WHERE tr.object_id = %d AND tt.taxonomy = 'link_category' LIMIT 1",
                    $link->link_id
                )
            );

            return [
                'link_id'     => $link->link_id,
                'link_name'   => $link->link_name,
                'link_url'    => $link->link_url,
                'link_description' => $link->link_description,
                'link_target' => $link->link_target,
                'link_rss'    => $link->link_rss,
                'tid'         => intval($term_id),
                'email'       => get_term_meta($link->link_id, 'islide_links_email', true),
                'link_image'  => $link->link_image,
                'link_visible'=> $link->link_visible,
                'link_notes'  => $link->link_notes
            ];
        }, $paged_links);

        return [
            'data'  => $data,
            'pages' => $pages,
            'count' => intval($total),
            'paged' => $paged,
        ];
    }

    /**
     * 更新友链（处理API请求）
     *
     * @param \WP_REST_Request $request API请求对象
     * @return \WP_REST_Response|\WP_Error API响应或错误
     */
    public static function update_friend_link($request) {
        $params = $request->get_params();
        $result = self::_update_friend_link($params);
        
        if(isset($result['error'])) {
            return new \WP_Error('update_friend_link_error', $result['error'], ['status' => 403]);
        }
        
        return new \WP_REST_Response($result, 200);
    }

    /**
     * 内部更新友链函数
     *
     * @param array $params 更新参数
     * @return array 处理结果
     */
    private static function _update_friend_link($params) {
        global $wpdb;
        
        $id = absint($params['id'] ?? 0);
        $field = sanitize_text_field($params['field'] ?? '');
        $value = sanitize_text_field($params['value'] ?? '');

        if (!$id || !$field) {
            return ['error' => '参数不完整'];
        }
        
        if ($field == 'tid') {
            $wpdb->delete(
                $wpdb->prefix . 'term_relationships',
                ['object_id' => $id],
                ['%d']
            );

            // 重新绑定分类
            $result = $wpdb->insert(
                $wpdb->prefix . 'term_relationships',
                [
                    'object_id'        => $id,
                    'term_taxonomy_id' => intval($value),
                ],
                ['%d', '%d']
            );
            
            if ($result === false) {
                return ['error' => '更新失败'];
            }
        } elseif ($field == 'email') {
            update_term_meta($id, 'islide_links_email', $value);
        } else {
            $table = $wpdb->prefix . 'links';
            $result = $wpdb->update($table, [$field => $value], ['link_id' => $id]);
        
            if ($result === false) {
                return ['error' => '更新失败'];
            }
        }

        return ['success' => true, 'message' => '更新成功'];
    }

    /**
     * 获取友链统计信息（处理API请求）
     *
     * @return \WP_REST_Response API响应
     */
    public static function get_friendlink_statistics() {
        $counts = islide_get_field_counts('links', 'link_visible', ['N', 'Y'], true);
        return new \WP_REST_Response($counts, 200);
    }

    /**
     * 清除所有RSS缓存（处理API请求）
     *
     * @return \WP_REST_Response API响应
     */
    public static function clear_rss_cache() {
        global $wpdb;
        
        // 删除所有以islide_rss_开头的transient
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_islide_rss_%',
                '_transient_timeout_islide_rss_%'
            )
        );
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => '已清除所有RSS缓存',
            'deleted_items' => $deleted > 0 ? $deleted / 2 : 0 // 每个transient有2条记录
        ], 200);
    }

    /**
     * 返回友链RSS数据结构示例
     *
     * @return \WP_REST_Response API响应
     */
    public static function get_rss_data_structure() {
        $example = [
            'data' => [
                // 当前页的文章列表
                [
                    'title' => '示例文章标题1',
                    'link' => 'https://example.com/post/1',
                    'date' => '2小时前',
                    'author' => '作者名称',
                    'thumb' => 'https://example.com/images/thumbnail1.jpg',
                    'desc' => '文章摘要1...',
                    'content' => '文章内容1...',
                    'source' => [
                        'name' => '友情链接名称1',
                        'url' => 'https://example1.com',
                        'logo' => 'https://example1.com/logo.png',
                        'description' => '网站描述1'
                    ]
                ],
                [
                    'title' => '示例文章标题2',
                    'link' => 'https://example.com/post/2',
                    'date' => '1天前',
                    'author' => '另一位作者',
                    'thumb' => 'https://example.com/images/thumbnail2.jpg', 
                    'desc' => '文章摘要2...',
                    'content' => '文章内容2...',
                    'source' => [
                        'name' => '友情链接名称2',
                        'url' => 'https://example2.com',
                        'logo' => 'https://example2.com/logo.png',
                        'description' => '网站描述2'
                    ]
                ]
            ],
            'count' => 25,  // 总文章数
            'pages' => 3,   // 总页数
            'paged' => 2    // 当前页码
        ];
        
        // 分页参数说明
        $paging_params = [
            'limit' => '每个RSS源获取的文章数量，默认10',
            'category_id' => '友链分类ID，默认0表示所有分类',
            'paged' => '当前页码，默认1',
            'size' => '每页显示数量，默认10'
        ];
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'RSS数据结构示例',
            'example' => $example,
            'paging_params' => $paging_params,
            'usage' => '/wp-json/islide/v1/getFriendsArticles?limit=5&category_id=0&paged=1&size=10'
        ], 200);
    }

    /**
     * 获取所有友链的最新RSS文章（处理API请求）
     * 
     * @param \WP_REST_Request $request API请求对象
     * @return \WP_REST_Response|\WP_Error API响应或错误
     */
    public static function get_friends_latest_articles($request) {
        $params = $request->get_params();
        $limit = isset($params['limit']) ? intval($params['limit']) : 10;
        $category_id = isset($params['category_id']) ? intval($params['category_id']) : 0;
        $paged = isset($params['paged']) ? max(1, intval($params['paged'])) : 1;
        $size = isset($params['size']) ? max(1, intval($params['size'])) : 10;
        
        // 使用带分页功能的fetch_all_friends_articles函数
        $result = self::fetch_all_friends_articles($category_id, $limit, $paged, $size);
        
        return new \WP_REST_Response($result, 200);
    }
    
    /**
     * 获取所有友链的最新RSS文章
     * 
     * @param int $category_id 友链分类ID，0表示所有分类
     * @param int $limit 每个友链获取的文章数量限制
     * @param int $paged 当前页码
     * @param int $size 每页显示数量
     * @return array 所有友链最新文章列表，按时间排序并分页
     */
    private static function fetch_all_friends_articles($category_id = 0, $limit = 10, $paged = 1, $size = 10) {
        // 获取所有带RSS的友链
        $args = [
            'orderby'        => 'rating',
            'order'          => 'DESC',
            'limit'          => -1, // 获取所有友链
            'hide_invisible' => false
        ];
        
        if ($category_id > 0) {
            $args['category'] = $category_id;
        }
        
        $links = get_bookmarks($args);
        $all_articles = [];
        
        // 限制每个友链最多获取的文章数
        $max_total_articles = 100; // 最多收集100篇文章用于分页
        
        foreach ($links as $link) {
            // 只处理有RSS URL的友链
            if (!empty($link->link_rss)) {
                $articles = self::fetch_rss_articles($link->link_rss, $limit);
                
                // 为每篇文章添加来源信息，同时确保URL格式正确
                foreach ($articles as &$article) {
                    $link_url = html_entity_decode($link->link_url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $link_image = html_entity_decode($link->link_image, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    
                    $article['source'] = [
                        'name' => $link->link_name,
                        'url' => $link_url,
                        'logo' => $link_image,
                        'description' => $link->link_description
                    ];
                }
                
                $all_articles = array_merge($all_articles, $articles);
                
                // 如果已经收集了足够多的文章，就停止继续获取
                if (count($all_articles) >= $max_total_articles) {
                    break;
                }
            }
        }
        
        // 按时间排序
        usort($all_articles, function($a, $b) {
            // 如果date是时间差字符串（如"2小时前"），则先转换为统一格式进行比较
            $time_a = is_numeric(strtotime($a['date'])) ? strtotime($a['date']) : time();
            $time_b = is_numeric(strtotime($b['date'])) ? strtotime($b['date']) : time();
            return $time_b - $time_a;
        });
        
        // 获取总数
        $total = count($all_articles);
        
        // 计算总页数
        $pages = ceil($total / $size);
        
        // 确保页码有效
        $paged = max(1, min($paged, max(1, $pages)));
        
        // 计算偏移量
        $offset = ($paged - 1) * $size;
        
        // 获取当前页的数据
        $page_articles = array_slice($all_articles, $offset, $size);
        
        return [
            'data' => $page_articles,
            'count' => $total,
            'pages' => $pages,
            'paged' => $paged
        ];
    }

    /**
     * 从RSS URL获取文章列表
     *
     * @param string $rss_url RSS链接地址
     * @param int $max_items 最大获取文章数量
     * @return array 文章数据数组
     */
    public static function fetch_rss_articles($rss_url, $max_items = 2)
    {
        // 检查URL是否有效
        if (!filter_var($rss_url, FILTER_VALIDATE_URL)) {
            return [];
        }
        
        // 设置缓存键名
        $cache_key = 'islide_rss_' . md5($rss_url . (string)$max_items);
        $cache_time = 3600; // 缓存1小时
        
        // 尝试从缓存获取
        $cached_data = get_transient($cache_key);
        if (false !== $cached_data) {
            return $cached_data;
        }
        
        // 缓存不存在，获取RSS内容
        $rss = fetch_feed($rss_url);

        if (is_wp_error($rss)) {
            return [];
        }

        // 获取最新文章
        $rss_items = $rss->get_items(0, $rss->get_item_quantity($max_items));
        $articles = [];

        foreach ($rss_items as $item) {
            // 获取文章摘要
            $description = $item->get_description();
            $content = $item->get_content();
            
            // 优先使用缩略图
            $thumbnail = '';
            $enclosure = $item->get_enclosure();
            if ($enclosure && $enclosure->get_link()) {
                $thumbnail = $enclosure->get_link();
            } else {
                // 从内容中提取第一张图片
                preg_match('/<img.+?src=[\'"]([^\'"]+)[\'"].*?>/i', $content, $matches);
                if (isset($matches[1])) {
                    $thumbnail = $matches[1];
                }
            }
            
            // 解码HTML实体，确保URL格式正确
            $thumbnail = html_entity_decode($thumbnail, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            $articles[] = [
                'title' => $item->get_title(),
                'link' => $item->get_link(),
                'date' => islide_time_ago($item->get_date('Y-m-d H:i:s'),true),
                'author' => $item->get_author() ? $item->get_author()->get_name() : '未知',
                'thumb' => $thumbnail,
                'desc' => wp_strip_all_tags($description), // 去除HTML
                'content' => wp_strip_all_tags($content), // 去除HTML
            ];
        }

        // 按照发布时间降序排列
        usort($articles, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        // 设置缓存
        set_transient($cache_key, $articles, $cache_time);

        return $articles;
    }
} 