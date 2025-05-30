<?php 
/**
 * 文件上传处理类
 * 
 * 包含图片处理、文件上传、模块安装等功能
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;
use Grafika\Gd\Editor;

class FileUpload{
    
    /**
     * 图像编辑器实例
     * @var Editor
     */
    public static $editor;
    
    /**
     * 上传目录信息
     * @var array
     */
    public static $upload_dir;
    
    /**
     * 是否允许WebP格式
     * @var int
     */
    public static $allow_webp;
    
    /**
     * 初始化函数
     * 
     * @author ifyn
     * @return void
     */
    public function init(){
        // 获取上传目录信息
        self::$upload_dir = wp_upload_dir();
        
        // 初始化图片处理库
        self::$editor = new Editor();
        
        // 默认不启用WebP
        self::$allow_webp = 0;
    }
    
    /**
     * 获取图片主色调
     * 
     * @author ifyn
     * @param int $thumbnail_id 图片附件ID
     * @return string 返回十六进制颜色代码
     */
    public static function get_image_dominant_color($thumbnail_id) {
        // 检查数据库缓存
        $cached_color = get_post_meta($thumbnail_id, '_dominant_color', true);
        if (!empty($cached_color)) {
           return $cached_color;
        }

        // 获取图片物理路径
        $file_path = get_attached_file($thumbnail_id);
        if (!$file_path || !is_file($file_path)) {
            return '#ffffff';
        }

        try {
            // 创建图像资源
            $image_info = getimagesize($file_path);
            if ($image_info === false) {
                return islide_get_option('theme_color');
            }
            
            $image = null;
            switch ($image_info['mime']) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($file_path);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($file_path);
                    break;
                case 'image/webp':
                    $image = imagecreatefromwebp($file_path);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($file_path);
                    break;
                default:
                    throw new \Exception("Unsupported image type: " . $image_info['mime']);
            }

            // 确保图像创建成功
            if (!$image) {
                return islide_get_option('theme_color');
            }

            // 获取原始尺寸
            $width = imagesx($image);
            $height = imagesy($image);

            // 创建采样画布（保持宽高比）
            $sample_size = 200;
            $ratio = $width / $height;
            $sample_w = $ratio >= 1 ? $sample_size : round($sample_size * $ratio);
            $sample_h = $ratio >= 1 ? round($sample_size / $ratio) : $sample_size;
            
            $resized = imagecreatetruecolor($sample_w, $sample_h);
            if (!$resized) {
                imagedestroy($image);
                return islide_get_option('theme_color');
            }
            
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $sample_w, $sample_h, $width, $height);
            imagedestroy($image);

            // 颜色统计
            $colorGroups = [];
            $total_pixels = 0;
            $tolerance = 15; // 颜色差异容忍度

            for ($x = 0; $x < $sample_w; $x += 3) { // 3px步长
                for ($y = 0; $y < $sample_h; $y += 3) {
                    $rgb = imagecolorat($resized, $x, $y);
                    $colors = imagecolorsforindex($resized, $rgb);

                    // 过滤透明像素（alpha < 50）
                    if (isset($colors['alpha']) && $colors['alpha'] > 50) {
                        continue;
                    }

                    // 转换为HEX
                    $r = $colors['red'];
                    $g = $colors['green'];
                    $b = $colors['blue'];
                    $hex = sprintf("#%02x%02x%02x", $r, $g, $b);

                    // 颜色分组
                    $groupFound = false;
                    foreach ($colorGroups as $groupHex => &$count) {
                        if (self::color_diff($hex, $groupHex) < $tolerance) {
                            $count++;
                            $groupFound = true;
                            break;
                        }
                    }
                    
                    if (!$groupFound) {
                        $colorGroups[$hex] = 1;
                    }
                    $total_pixels++;
                }
            }

            imagedestroy($resized);

            // 如果没有有效的颜色组，返回默认颜色
            if (empty($colorGroups)) {
                return islide_get_option('theme_color');
            }

            // 过滤浅色（接近白色）
            $filtered = array_filter($colorGroups, function($hex) {
                list($r, $g, $b) = sscanf($hex, "#%02x%02x%02x");
                return ($r + $g + $b) < 750; // 750=250*3 (接近白色阈值)
            }, ARRAY_FILTER_USE_KEY);

            // 如果过滤后为空则使用原始数据
            $finalColors = !empty($filtered) ? $filtered : $colorGroups;
            
            arsort($finalColors);
            $dominantHex = array_key_first($finalColors);

            // 排除接近白色 (#fffff0 到 #ffffff)
            if ($dominantHex && preg_match('/^#fffff[0-9a-f]$/i', $dominantHex)) {
                $nextColor = array_slice($finalColors, 1, 1, true);
                $dominantHex = !empty($nextColor) ? array_key_first($nextColor) : '#ffffff';
            }

            // 确保有有效的颜色值
            if (!$dominantHex) {
                $dominantHex = '#ffffff';
            }

            // 缓存结果
            update_post_meta($thumbnail_id, '_dominant_color', $dominantHex);

            return $dominantHex;

        } catch (\Throwable $e) {
            return islide_get_option('theme_color');
        }
    }

    /**
     * 计算两个颜色之间的差异
     * 
     * @author ifyn
     * @param string $hex1 第一个十六进制颜色代码
     * @param string $hex2 第二个十六进制颜色代码
     * @return int 颜色差异值
     */
    private static function color_diff($hex1, $hex2) {
        // 计算颜色差异值
        list($r1, $g1, $b1) = sscanf($hex1, "#%02x%02x%02x");
        list($r2, $g2, $b2) = sscanf($hex2, "#%02x%02x%02x");
        
        return abs($r1 - $r2) + abs($g1 - $g2) + abs($b1 - $b2);
    }

    /**
     * 图片裁剪并保存到本地
     *
     * @author ifyn
     * @param array $arg 裁剪参数
     *               - url: 图片路径
     *               - type: 编辑形式 (fill/fit/exact/exactW/exactH/smart/compress)
     *               - width: 宽度
     *               - height: 高度
     *               - gif: 是否移除gif动画效果
     *               - webp: 是否转为webp格式
     *               - ratio: 比例
     *               - quality: 图片质量
     *               - custom: 是否自定义处理
     * @return string 处理后的图片URL
     */
    public static function thumb($arg){
        // 合并默认参数
        $r = apply_filters('islide_thumb_arg', wp_parse_args($arg, array(
            'url' => '',
            'type' => 'fill',
            'width' => '500',
            'height' => '500',
            'gif' => 0,
            'webp' => false,
            'ratio' => 1.2, 
            'quality' => 85,
            'custom' => false 
        )));
        
        // 自定义处理
        if ($r['custom']) {
            return apply_filters('islide_thumb_custom', $r['url'], $r);
        }

        // 处理特殊尺寸设置
        if ($r['height'] === '100%') {
            $r['type'] = 'exactW';
            unset($r['height']);
        }

        if ($r['width'] === '100%') {
            $r['type'] = 'exactH';
            unset($r['width']);
        }

        // 根据比例计算实际尺寸
        if (isset($r['width'])) {
            $r['width'] = ceil($r['width'] * $r['ratio']);
        }
        if (isset($r['height'])) {
            $r['height'] = ceil($r['height'] * $r['ratio']);
        }
        
        // 图片为空时返回默认图片
        if (empty($r['url'])) {
            if (!isset($r['default'])) {
                return apply_filters('islide_get_default_img', islide_get_default_img(), $r);
            }
            return $r['url'];
        }
        
        // 处理非本地文件
        if (strpos($r['url'], IS_HOME_URI) === false) {
            // 如果使用的是相对地址
            if (strpos($r['url'], '//') === false) {
                $r['url'] = self::$upload_dir['baseurl'] . '/' . $r['url'];
            }
            return apply_filters('islide_thumb_no_local', $r['url'], $r);
        }
        
        // 检查是否允许裁剪
        if (!islide_get_option('media_image_crop')) {
            return $r['url'];
        }

        // 检查是否为已裁剪过的图片
        if (strpos($r['url'], '_mark_') !== false) {
            return $r['url'];
        }

        // 不处理GIF图片
        if (strpos($r['url'], '.gif') !== false) {
            return $r['url'];
        }

        // 不裁剪则返回原图
        if ($r['type'] == 'default') {
            return $r['url'];
        }
        
        // 获取原始图片的物理地址
        $rel_file_path = str_replace(self::$upload_dir['baseurl'], '', $r['url']);
        $rel_file_path = str_replace(array('/', '\\'), IS_DS, $rel_file_path);
        $basedir = str_replace(array('/', '\\'), IS_DS, self::$upload_dir['basedir']);
        $rel_file_path = $basedir . $rel_file_path;
        
        // 检查文件是否存在
        if (!is_file($rel_file_path)) {
            return $r['url'];
        }

        // 获取图片尺寸
        $img_info = getimagesize($rel_file_path);
        if (!$img_info) {
            return $r['url']; 
        }
        
        list($width, $height, $type, $attr) = $img_info;

        // 如果原图比目标尺寸小，直接返回原图
        if ((isset($r['width']) && $width < $r['width']) || 
            (isset($r['height']) && $height < $r['height'])) {
            return $r['url'];
        }
        
        $basename = basename($rel_file_path);
        $rel_file = str_replace($basedir . IS_DS, '', $rel_file_path);
        $r['height'] = isset($r['height']) ? $r['height'] : null;
        $file_path = str_replace($basename, '', $rel_file);
        
        // 生成缩略图路径
        $thumb_dir = $basedir . IS_DS . 'thumb' . IS_DS . $file_path . 
                     $r['type'] . '_w' . $r['width'] . '_h' . $r['height'] . 
                     '_g' . $r['gif'] . '_mark_' . $basename;

        // 如果缩略图已存在则直接返回
        if (is_file($thumb_dir)) {
            $basedir = str_replace(array('/', '\\'), '/', $basedir);
            $thumb_dir = str_replace(array('/', '\\'), '/', $thumb_dir);
            return apply_filters('islide_get_thumb', str_replace($basedir, self::$upload_dir['baseurl'], $thumb_dir));
        }
        
        // 创建目录
        $thumb_folder = dirname($thumb_dir);
        if (!is_dir($thumb_folder)) {
            wp_mkdir_p($thumb_folder);
        }
        
        try {
            $image = null; // 初始化图像变量
            self::$editor->open($image, $rel_file_path);
            
            if (!$image) {
                return $r['url'];
            }
            
            // 根据类型执行不同的裁剪
            switch ($r['type']) {
                case 'fit':
                    self::$editor->resizeFit($image, $r['width'], $r['height']);
                    break;
                case 'exact':
                    self::$editor->resizeExact($image, $r['width'], $r['height']);
                    break;
                case 'exactW':
                    self::$editor->resizeExactWidth($image, $r['width']);
                    break;
                case 'exactH':
                    self::$editor->resizeExactHeight($image, $r['height']);
                    break;
                case 'smart':
                    self::$editor->crop($image, $r['width'], $r['height'], 'smart');
                    break;
                case 'compress':
                    // compress 什么都不动，只保存
                    break;
                default:
                    self::$editor->resizeFill($image, $r['width'], $r['height']);
                    break;
            }

            // 处理GIF
            if ($r['gif']) {
                self::$editor->flatten($image);
            }

            // 保存图片
            if (self::$editor->save($image, $thumb_dir, null, $r['quality'], true)) {
                // 如果启用WebP，保存WebP版本
                if (self::$allow_webp) {
                    $thumb = str_replace(substr(strrchr($thumb_dir, '.'), 1), 'webp', $thumb_dir);
                    self::$editor->save($image, $thumb, null, $r['quality'], true);
                }
                
                // 返回URL
                $basedir = str_replace(array('/', '\\'), '/', $basedir);
                $thumb_dir = str_replace(array('/', '\\'), '/', $thumb_dir);
                return apply_filters('islide_get_thumb', str_replace($basedir, self::$upload_dir['baseurl'], $thumb_dir));
            }

            return apply_filters('islide_thumb_default_image', islide_get_default_img(), $r);
        } catch (\Throwable $th) {
            return $r['url'];
        }
    }
    
    
    /**
     * 清空目录
     *
     * @author ifyn
     * @param string $path 目录路径
     * @return void
     */
    public static function del_directory_file($path) {
        if (!is_dir($path)) {
            return;
        }
        
        // 扫描目录内的所有文件和目录
        $dirs = scandir($path);
        if (!$dirs) {
            return;
        }
        
        foreach ($dirs as $dir) {
            // 排除当前目录(.)和上一级目录(..)
            if ($dir != '.' && $dir != '..') {
                $sonDir = $path . '/' . $dir;
                
                if (is_dir($sonDir)) {
                    // 递归删除子目录
                    self::del_directory_file($sonDir);
                    // 删除空目录
                    @rmdir($sonDir);
                } else {
                    // 删除文件
                    @unlink($sonDir);
                }
            }
        }
    }
    
    /**
     * 上传文件重命名
     *
     * @author ifyn
     * @param string $filename 原始文件名
     * @param string $type 文件类型标识
     * @param int $post_id 关联的文章ID
     * @return string 新文件名
     */
    public static function rename_filename($filename, $type, $post_id){
        $info = pathinfo($filename);
        $ext = empty($info['extension']) ? '' : '.' . $info['extension'];
        $name = basename($filename, $ext);
        $current_user_id = get_current_user_id();
        
        return $current_user_id . str_shuffle(uniqid()) . '_' . $post_id . '_' . $type . $ext;
    }
    
    /**
     * 处理文件上传
     *
     * @author ifyn
     * @param array $request 请求参数
     *              - type: 上传类型(comment/post/avatar/cover/circle/qrcode/verify)
     *              - post_id: 关联的文章ID
     *              - file_name: 自定义文件名(可选)
     *              - set_poster: 设置为封面(可选)
     * @return array 上传结果
     */
    public static function file_upload($request){
        // 检查上传功能是否开启
        if (!islide_get_option('media_upload_allow')) {
            return array('error' => '上传功能已关闭，请联系管理员');
        }

        // 检查用户登录状态
        $user_id = get_current_user_id();
        if (!$user_id) {
            return array('error' => '请先登录');
        }

        // 验证post_id
        if ((!isset($request['post_id']) || !is_numeric($request['post_id'])) && 
            (isset($request['type']) && $request['type'] == 'post')) {
            return array('error' => '缺少文章ID');
        }

        // 参数验证
        if (!isset($request['type'])) {
            return array('error' => '请设置一个type');
        }
        
        // 验证上传类型
        $type_array = array('comment', 'post', 'avatar', 'cover', 'circle', 'qrcode', 'verify');
        if (!in_array($request['type'], $type_array)) {
            return array('error' => '不支持这个type');
        }
        
        // 文件验证
        if (!isset($_FILES['file']['size']) || $_FILES['file']['size'] <= 0) {
            return array('error' => sprintf('文件损坏，请重新选择（%s）', 
                isset($_FILES['file']['name']) ? $_FILES['file']['name'] : '未知文件'));
        }

        // 确定文件类型和大小限制
        $size = 0;
        $mime = '';
        $text = '';
        
        $upload_size = islide_get_option('media_upload_size');
        $upload_size = is_array($upload_size) ? $upload_size : array(); 
        
        if (strpos($_FILES['file']['type'], 'image') !== false) {
            $mime = 'image';
            $size = !empty($upload_size['image']) ? $upload_size['image'] : 3;
            $text = '图片';
        } elseif (strpos($_FILES['file']['type'], 'video') !== false) {
            $mime = 'video';
            $size = !empty($upload_size['video']) ? $upload_size['video'] : 50;
            $text = '视频';
        } else {
            $mime = 'file';
            $size = !empty($upload_size['file']) ? $upload_size['file'] : 30;
            $text = '文件';
        }

        // 文件大小验证
        if ($_FILES['file']['size'] > $size * 1048576) {
            return array('error' => sprintf('%s必须小于%sM，请重新选择', $text, $size));
        }
        
        // 加载必要的文件处理库
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        
        // 设置文件名
        if (!isset($request['file_name'])) {
            $_FILES['file']['name'] = self::rename_filename(
                $_FILES['file']['name'], 
                $request['type'], 
                isset($request['post_id']) ? $request['post_id'] : 0
            );
        } else {
            $_FILES['file']['name'] = $request['file_name'];
        }
        
        // 处理上传
        $post_id = isset($request['post_id']) ? (int)$request['post_id'] : 0;
        $attachment_id = media_handle_upload('file', $post_id);

        if (is_wp_error($attachment_id)) {
            return array(
                'error' => sprintf('上传失败(%s)：', $_FILES['file']['name']) . 
                           $attachment_id->get_error_message()
            );
        } else {
            // 设置为文章特色图片
            if (isset($request['set_poster']) && 
                get_post_field('post_author', absint($request['set_poster'])) == $user_id) {
                set_post_thumbnail(absint($request['set_poster']), $attachment_id);
            }
            
            return array(
                'id' => (int)$attachment_id,
                'url' => wp_get_attachment_url($attachment_id)
            );
        }
    }
}