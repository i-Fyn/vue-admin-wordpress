<?php
/**
 * SEO功能管理类
 * 
 * 处理站点SEO相关功能，包括元数据生成和优化
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;
use islide\Modules\Common\Post;

class Seo {
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
     * 获取文章的SEO元数据
     *
     * @author  ifyn
     * @param   int $post_id 文章ID，默认为当前文章
     * @return  array        包含SEO元数据的关联数组
     */
    public static function single_meta($post_id = 0) {
        // 如果未提供文章ID，则获取当前文章ID
        if (!$post_id) {
            global $post;
            if (!isset($post->ID)) {
                return array();
            }
            $post_id = $post->ID;
        }
        
        // 获取作者信息
        $author_id = get_post_field('post_author', $post_id);
        if (!$author_id) {
            $author_id = '';
        }
        
        // 获取文章标题
        $title = get_the_title($post_id);
        
        // 获取文章缩略图
        $thumb_url = Post::get_post_thumb($post_id);
        
        // 获取SEO描述，如果没有则使用文章摘要
        $desc = get_post_meta($post_id, 'islide_seo_description', true);
        if (empty($desc)) {
            $desc = islide_get_desc($post_id, 100);
        }
        
        // 获取SEO关键词，如果没有则使用文章标签
        $key = get_post_meta($post_id, 'islide_seo_keywords', true);
        if (empty($key)) {
            $tags = wp_get_post_tags($post_id);
            if (!empty($tags) && !is_wp_error($tags)) {
                $tag_names = array_column($tags, 'name');
                $key = implode(',', $tag_names);
            } else {
                $key = '';
            }
        }
        
        // 获取第一个分类名称
        $category_ids = wp_get_post_categories($post_id);
        $first_category_name = '';
        if (!empty($category_ids) && !is_wp_error($category_ids)) {
            $first_category_id = $category_ids[0];
            $first_category = get_category($first_category_id);
            if ($first_category && !is_wp_error($first_category)) {
                $first_category_name = $first_category->name;
            }
        }
        
        // 构建并返回SEO元数据数组
        return array(
            'id' => (int)$post_id,
            'title' => islide_get_seo_title(wptexturize($title)),
            'tag' => esc_attr($key),
            'category' => esc_attr($first_category_name),
            'description' => wptexturize(esc_attr($desc)),
            'image' => islide_get_thumb(array(
                'thumb' => $thumb_url,
                'width' => 600,
                'height' => 400
            )),
            'updated_time' => get_the_modified_date('c', $post_id),
            'published_time' => get_the_date('c', $post_id),
            'author_name' => esc_attr($author_id),
            'author' => esc_url(islide_get_option('domain_name') . '/user/' . $author_id),
            'prevnext' => Post::posts_prevnext($post_id)
        );
    }
}