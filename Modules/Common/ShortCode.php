<?php
/**
 * 短代码功能管理类
 * 
 * 处理站点内短代码的注册和解析功能
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;

class ShortCode {
    /**
     * 初始化函数，注册短代码
     *
     * @author  ifyn
     * @return  void
     */
    public function init() {
        if (!is_admin()) {
            // 注册隐藏内容短代码
            add_shortcode('content_hide', array(__CLASS__, 'content_hide'));
        }
    }
    
    /**
     * 从内容中提取指定短代码及其内容
     *
     * @author  ifyn
     * @param   string $content        要处理的内容
     * @param   string $shortcode_name 短代码名称
     * @return  array                  处理结果，包含移除短代码后的内容和短代码内容
     */
    public static function get_shortcode_content($content, $shortcode_name) {
        if (empty($content) || empty($shortcode_name)) {
            return array(
                'content' => $content,
                'shortcode_content' => ''
            );
        }
        
        // 获取短代码正则表达式
        $pattern = get_shortcode_regex(array($shortcode_name)); 
        preg_match_all('/' . $pattern . '/', $content, $matches);
        
        $shortcode_content = '';
        
        // 如果找到匹配的短代码
        if (!empty($matches[5])) {
            $shortcode_content = $matches[5][0];
            $content = str_replace($matches[0][0], '', $content);
        }
        
        // 返回处理结果
        $result = array(
            'content' => $content,
            'shortcode_content' => $shortcode_content
        );
        
        return $result;
    }
    
    /**
     * 提取内容中所有隐藏内容块
     *
     * @author  ifyn
     * @param   string $content 要处理的文章内容
     * @return  array           所有隐藏内容块数组
     */
    public static function extract_content_hide_blocks($content) {
        if (empty($content)) {
            return array();
        }
        
        $matches = array();
        $result = preg_match_all('/\[content_hide\](.*?)\[\/content_hide\]/is', $content, $matches);
        
        if ($result && !empty($matches[1])) {
            return $matches[1]; // 返回所有匹配内容数组
        }
        
        return array();
    }
    
    /**
     * 处理隐藏内容短代码
     *
     * @author  ifyn
     * @param   array  $atts    短代码属性
     * @param   string $content 短代码内部内容
     * @return  string          处理后的短代码输出
     */
    public static function content_hide($atts, $content = null) {
        // 短代码处理逻辑，可以根据实际需求扩展
        // 示例: 这里可以添加检查用户权限的逻辑
        $user_id = get_current_user_id();
        
        if (!$content) {
            return '';
        }
        
        // 可以在这里添加权限检查，如购买内容、会员专享等
        // 目前默认返回隐藏内容的占位符
        return '<div class="content-hide-placeholder">' . __('此处内容已被隐藏，需要特定权限查看', 'islide') . '</div>';
    }
}