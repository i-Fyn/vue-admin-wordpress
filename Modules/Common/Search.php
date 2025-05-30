<?php
/**
 * 搜索功能管理类
 * 
 * 处理站内搜索相关功能，包括搜索建议、相似度计算和高亮显示
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;

class Search {
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
     * 获取搜索建议 搜索文章、用户、标签、分类和自定义分类法
     *
     * @author  ifyn
     * @param   array|string $search_terms 搜索词，可以是字符串或数组
     * @return  array                      搜索建议结果数组
     */
    public static function get_search_suggest($search_terms) {
        // 检查搜索词的类型
        if (is_string($search_terms)) {
            $search_terms = array($search_terms);
        } elseif (!is_array($search_terms)) {
            return array(); // 返回空数组，表示没有搜索结果
        }
    
        // 过滤和验证搜索词
        $search_terms = array_map('sanitize_text_field', $search_terms);
        
        if(empty($search_terms)) return array();
    
        // 设置搜索参数
        $args = array(
            'post_type' => 'any', // 搜索所有文章类型
            'orderby' => 'rand',
            's' => implode('+', $search_terms), // 使用's'参数来指定这些关键词，用加号（+）连接
            'posts_per_page' => 5, // 返回的结果数量
        );
    
        // 创建查询对象
        $search_query = new \WP_Query($args);

        // 定义结果数组
        $results = array();
    
        // 判断是否有搜索结果
        if ($search_query->have_posts()) {
            while ($search_query->have_posts()) {
                $search_query->the_post();

                $title = get_the_title();
                $post_type = get_post_type();
                
                // 获取文章信息
                $post_info = array(
                    'id' => (int)get_the_ID(),
                    'title' => self::highlight_search_terms($title, $search_terms),
                    'similarity' => self::calculate_similarity($title, $search_terms),
                    'type' => $post_type, // 获取文章类型
                    'type_name' => islide_get_type_name($post_type)
                );
                // 将文章信息添加到结果数组中
                $results[] = $post_info;
            }
        }
    
        // 重置查询对象
        wp_reset_postdata();
    
        // 搜索用户
        $user_query = new \WP_User_Query(array(
            'search' => '*'.esc_attr($search_terms[0]).'*',
            'search_columns' => array(
                'display_name',
                'user_id'
            ),
            'count_total' => true, // 获取总用户数而不是所有用户对象
            'number' => 10,
        ));
    
        // 获取用户结果
        $users = $user_query->get_results();
    
        // 判断是否有用户结果
        if (!empty($users)) {
            foreach ($users as $user) {
                
                $name = $user->display_name;
                
                // 获取用户信息
                $user_info = array(
                    'id' => (int)$user->ID,
                    'title' => self::highlight_search_terms($name, $search_terms),
                    'similarity' => self::calculate_similarity($name, $search_terms),
                    'type' => 'user', // 设置用户类型为'user'
                    'type_name' => islide_get_type_name('user')
                );
                // 将用户信息添加到结果数组中
                $results[] = $user_info;
            }
        }
        
        // 搜索标签、分类和自定义分类法
        $excluded_taxonomies = array('nav_menu', 'post_format', 'wp_theme', 'wp_template_part_area'); // 要排除的分类和标签类型
        $all_taxonomies = get_taxonomies(); // 获取所有已注册的分类和标签类型
        
        $taxonomies = array_diff($all_taxonomies, $excluded_taxonomies); // 排除不需要的分类和标签类型
        
        foreach ($taxonomies as $taxonomy) {
            $term_args = array(
                'name__like' => esc_attr($search_terms[0]),
                'hide_empty' => false, // 显示没有文章的分类和标签
                'number' => 10, // 限制搜索结果数量为10个
            );
            $terms = get_terms($taxonomy, $term_args);
        
            // 判断是否有分类和标签结果
            if (!empty($terms) && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    // 获取分类和标签信息
                    $term_info = array(
                        'id' => (int)$term->term_id,
                        'title' => self::highlight_search_terms($term->name, $search_terms),
                        'similarity' => self::calculate_similarity($term->name, $search_terms),
                        'type' => $taxonomy, // 设置分类和标签类型为分类和标签的名称
                        'type_name' => islide_get_type_name($taxonomy)
                    );
                    // 将分类和标签信息添加到结果数组中
                    $results[] = $term_info;
                }
            }
        }
    
        // 根据相似度对结果数组进行排序
        usort($results, function($a, $b) {
            return $b['similarity'] - $a['similarity'];
        });
    
        // 返回结果数组
        return array_slice($results, 0, 10);
    }
    
    /**
     * 计算文本与搜索词的相似度
     *
     * @author  ifyn
     * @param   string $text        要比较的文本
     * @param   array  $search_terms 搜索词数组
     * @return  float               相似度百分比
     */
    public static function calculate_similarity($text, $search_terms) {
        if (empty($text) || empty($search_terms)) {
            return 0;
        }
        
        $similarity = 0;
        foreach ($search_terms as $term) {
            if (empty($term)) continue;
            similar_text(strtolower($text), strtolower($term), $percent);
            $similarity += $percent;
        }
        
        return $similarity / count($search_terms); // 返回平均相似度
    }
    
    /**
     * 高亮显示文本中的搜索词
     *
     * @author  ifyn
     * @param   string $text        要高亮的文本
     * @param   array  $search_terms 搜索词数组
     * @return  string              高亮后的文本
     */
    public static function highlight_search_terms($text, $search_terms) {
        if (empty($text) || empty($search_terms)) {
            return $text;
        }
        
        $highlighted_text = $text;
        foreach ($search_terms as $term) {
            if (empty($term)) continue;
            
            // 使用正则表达式进行大小写不敏感的匹配，避免XSS攻击
            $pattern = '/(' . preg_quote(esc_html($term), '/') . ')/i';
            
            // 使用$1表示匹配到的内容，添加高亮样式
            $highlighted_text = preg_replace($pattern, '<span style="color: red;">$1</span>', $highlighted_text);
        }
        return $highlighted_text;
    }
}