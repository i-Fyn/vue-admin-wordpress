<?php
/**
 * 站点公告管理类
 * 
 * 处理公告的获取和展示功能
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;
use islide\Modules\Common\Post;

class Notice {
    
    /**
     * 初始化函数
     *
     * @author  ifyn
     * @return  void
     */
    public function init() {
        // 初始化钩子和过滤器
    }

    /**
     * 获取最新公告列表
     *
     * @author  ifyn
     * @param   int   $count 要获取的公告数量
     * @return  array        公告列表数据
     */
    public static function getNewNoticeList($count = 5) {
        // 参数验证
        $count = (int)$count;
        if ($count <= 0) {
            $count = 5;
        }
        
        $args = array(
            'post_type'      => 'notice',
            'posts_per_page' => $count,
            'post_status'    => 'publish',
            'meta_key'       => '_notice_end_date',
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
        );

        $query = new \WP_Query($args);
        $notices = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // 获取公告元数据
                $meta = get_post_meta($post_id, 'single_notice_metabox', true);
                if (!is_array($meta)) {
                    $meta = array();
                }
                
                // 判断是否展示 & 是否过期
                if (empty($meta['show_notice']) || (int)$meta['show_notice'] != 1) {
                    continue;
                }

                // 检查结束日期
                $end_date = isset($meta['end_date']) ? sanitize_text_field($meta['end_date']) : '';
                if (!empty($end_date) && strtotime($end_date . ' 23:59:59') < current_time('timestamp')) {
                    continue; // 已过期，不展示
                }

                // 获取封面图（优先缩略图）
                $thumb = Post::get_post_thumb($post_id);
                if (!$thumb) {
                    $thumb = ''; // 默认图或留空
                }

                // 构造按钮数组
                $buttons = array();
                if (!empty($meta['button_group']) && is_array($meta['button_group'])) {
                    foreach ($meta['button_group'] as $btn) {
                        if (!empty($btn['title']) && !empty($btn['link'])) {
                            $buttons[] = array(
                                'text' => sanitize_text_field($btn['title']),
                                'link' => esc_url_raw($btn['link']),
                                'type' => isset($btn['type']) ? sanitize_text_field($btn['type']) : 'default',
                            );
                        }
                    }
                }

                // 构造公告数据
                $notices[] = array(
                    'id'        => (int)$post_id,
                    'title'     => get_the_title(),
                    'thumb'     => $thumb,
                    'date'      => get_the_date('Y-m-d H:i:s', $post_id),
                    'desc'      => wp_strip_all_tags(get_the_excerpt($post_id)),
                    'timestamp' => (int)strtotime(get_the_date('Y-m-d H:i:s', $post_id)),
                    'show'      => isset($meta['show_notice']) && (int)$meta['show_notice'] == 1,
                    'buttons'   => $buttons,
                );
            }
            
            // 恢复全局文章数据
            wp_reset_postdata();
        }

        return $notices;
    }
}