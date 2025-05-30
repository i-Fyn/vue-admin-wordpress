<?php
/**
 * 视频播放器功能管理类
 * 
 * 处理视频列表获取、视频权限、分章节管理等功能
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;
use islide\Modules\Common\User;

/**
 * islide player播放器
 *
 * @version 1.0.0
 * @since 2023
 * @author ifyn
 */
class Player {
    
    /**
     * 初始化函数
     *
     * @author  ifyn
     * @return  void
     */
	public function init() {
        // 初始化代码可在此添加
    }
    
    /**
     * 获取视频数量计数
     *
     * @author  ifyn
     * @param   int $post_id 文章ID
     * @return  int          视频数量
     */
    public static function get_video_count($post_id) {
        // 获取父级ID
        $parent_id = get_post_field('post_parent', $post_id);
        
        // 如果有父级ID，使用父级ID
        if ($parent_id) {
            $post_id = $parent_id;
        }
        
        // 处理不同类型的文章
        if (!$parent_id && get_post_type($post_id) == 'post') {
            // 普通文章类型
            $video_list = get_post_meta($post_id, 'islide_single_post_video_group', true);
            if (empty($video_list)) return 0;
            
            return count($video_list);
            
        } else if (!$parent_id && get_post_type($post_id) == 'episode') {
            // 剧集类型
            return 1;
        } else {
            // 视频类型
            $video_meta = get_post_meta((int)$post_id, 'single_video_metabox', true);
        
            if (empty($video_meta)) return 0;
            
            $video_list = !empty($video_meta['group']) ? $video_meta['group'] : array();
            
            return count($video_list);
        }
    }

    /**
     * 获取视频列表
     *
     * @author  ifyn
     * @param   int $post_id 文章ID
     * @return  array        视频列表数据
     */
    public static function get_video_list($post_id) {
        // 获取父级ID
        $parent_id = get_post_field('post_parent', $post_id);
        
        // 获取当前用户ID
        $user_id = get_current_user_id();
        
        // 保存当前文章ID
        $current_post_id = $post_id;
        
        // 如果有父级ID，使用父级ID
        if ($parent_id) {
            $post_id = $parent_id;
        }
        
        // 处理不同类型的文章
        if (!$parent_id && get_post_type($post_id) == 'post') {
            // 普通文章类型
            $video_list = get_post_meta($post_id, 'islide_single_post_video_group', true);
            if (empty($video_list)) return array();
            
        } else if (!$parent_id && get_post_type($post_id) == 'episode') {
            // 剧集类型
            $episode_meta = get_post_meta($post_id, 'single_episode_metabox', true);
            $episodes = !empty($episode_meta['video']) ? $episode_meta['video'] : array();
            $thumb_url = Post::get_post_thumb($post_id, true);
            $thumb = islide_get_thumb(array(
                'url' => $thumb_url,
                'width' => 106,
                'height' => 60,
                'ratio' => 1
            ));
            
            $video_list = array(
                array(
                    'chapter_title' => '',
                    'chapter_desc' => '',
                    'type' => 'episode',
                    'id' => $post_id,
                    'title' => get_the_title($post_id),
                    'pic' => $thumb_url,
                    'thumb' => $thumb,
                    'url' => isset($episodes['url']) ? $episodes['url'] : '',
                    'preview_url' => isset($episodes['preview_url']) ? $episodes['preview_url'] : '',
                )
            );
            
        } else {
            // 视频类型
            $video_meta = get_post_meta((int)$post_id, 'single_video_metabox', true);
            if (empty($video_meta)) return array();
            
            $video_list = !empty($video_meta['group']) ? $video_meta['group'] : array();
        }
        
        // 初始化权限数组
        $allowList = array();
        $index = 0;
        $permission_result = null;
        
        // 处理每个视频的权限
        foreach ($video_list as $key => &$value) {
            // 跳过章节标题
            if (isset($value['type']) && $value['type'] == 'chapter') continue;
            
            // 检查用户是否有权限观看该视频
            $can = self::islide_check_user_can_video_allow(
                (!empty($value['id']) ? $value['id'] : $post_id),
                $user_id,
                $index
            );
            
            // 根据权限设置视频URL
            $value['url'] = $can['allow'] ? $value['url'] : ''; 

            // 记录权限状态
            $allowList[] = $can['allow'];
            
            // 使用第一个视频的权限信息作为整体权限信息
            if ($index == 0 || ($can['allow'] && !$permission_result['allow'])) {
                $permission_result = $can;
            }
            
            $index++;
        }
        
        // 按章节分组处理视频列表
        $list = self::groupByChapter($video_list);
        
        // 确保权限结果存在
        if (!$permission_result) {
            $permission_result = [
                'allow' => false,
                'type' => '',
                'value' => 0,
                'total_value' => 0,
                'not_login_pay' => false,
                'roles' => [],
                'free_count' => 0,
                'free_video' => false
            ];
        }
        
        // 添加allowList到权限结果
        $permission_result['allowList'] = $allowList;
        
        // 如果有父级ID，获取父级文章信息
        $post_author = get_post_field('post_author', $parent_id ? $parent_id : $post_id);
        $thumb_url = Post::get_post_thumb($parent_id ? $parent_id : $post_id);
        $thumb = '';
            if ($thumb_url) {
                $thumb = islide_get_thumb(array(
                    'url' => $thumb_url,
                    'width' => 180,
                    'height' => 250,
                    'ratio' => 2
                ));
            }
            
            $post = array(
                'id' => $parent_id ? $parent_id : $post_id,
                'title' => get_the_title($parent_id ? $parent_id : $post_id),
                'link' => $parent_id ? '/video/' . $parent_id : '/episode/' . $post_id,
                'thumb' => $thumb,
                'content' => apply_filters('the_content', get_post_field('post_content', $parent_id ? $parent_id : $post_id)),
                'date' => islide_time_ago(get_the_date('Y-m-d H:i:s', $parent_id ? $parent_id : $post_id),true),
                'desc' => islide_get_desc($parent_id ? $parent_id : $post_id, 150),
                'user' => User::get_user_public_data($post_author),
            );
        
        // 组装返回数据
        $data = array(
            'id' => $post_id,
            'current_user' => $permission_result,
            'list' => $list,
        );
        
        $data['post'] = $post;
        
        return $data;
    }
    
    /**
     * 将原始数组按照章节进行分组，并返回新的数组
     * 
     * @author  ifyn
     * @param   array $array 原始视频数组
     * @return  array        按章节分组后的视频数组
     */
    public static function groupByChapter($array) {
        $newArray = array(); // 初始化新的数组
        $chapterIndex = -1; // 初始化章节索引
    
        // 遍历原始数组
        foreach ($array as $key => &$item) {
            if (isset($item['type']) && $item['type'] == 'chapter') { 
                // 如果元素的类型为章节，则创建新的章节，并将其添加到新的数组中
                $chapterIndex++;
                $newArray[$chapterIndex] = array(
                    'chapter_title' => isset($item['chapter_title']) ? $item['chapter_title'] : '',
                    'chapter_desc' => isset($item['chapter_desc']) ? $item['chapter_desc'] : '',
                    'video_list' => array()
                );
            } else {
                if ($chapterIndex == -1) { 
                    // 如果第一个元素的类型不是章节，则创建一个空章节并将其添加到新的数组中
                    $chapterIndex++;
                    $newArray[$chapterIndex] = array(
                        'chapter_title' => '',
                        'chapter_desc' => '',
                        'video_list' => array()
                    );
                }
                
                // 处理视频项目的链接和缩略图
                if (!empty($item['id'])) {
                    $item['link'] = get_permalink($item['id']);
                }
                
                // 清理不需要的字段
                if (isset($item['type'])) unset($item['type']);
                if (isset($item['chapter_title'])) unset($item['chapter_title']);
                if (isset($item['chapter_desc'])) unset($item['chapter_desc']);
                
                // 处理缩略图
                if (isset($item['thumb'])) {
                    $item['pic'] = $item['thumb'];
                }
                
                if (!isset($item['thumb']) || empty($item['thumb'])) {
                    $item['thumb'] = Post::get_post_thumb(isset($item['id']) ? $item['id'] : 0);
                }
                
                $item['thumb'] = islide_get_thumb(array(
                    'url' => $item['thumb'],
                    'width' => 106,
                    'height' => 60,
                    'ratio' => 1
                ));
                
                // 将元素添加到当前章节的视频列表中
                $newArray[$chapterIndex]['video_list'][] = $item;
            }
        }
    
        return $newArray; // 返回新的数组
    }

    /**
     * 获取用户可以免费观看视频的数量
     * 
     * @param int $user_id 用户ID
     * @return mixed 返回可观看数量或false
     */
    public static function get_user_can_free_video_count($user_id) {
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
        $free_video = isset($user_roles[$vip_level]['free_video']) ? (bool) $user_roles[$vip_level]['free_video'] : false;
        
        
        // 如果免费下载权限为 false，直接返回
        if (!$free_video) {
            return false;
        }

        
        $free_video_count = isset($user_roles[$vip_level]['free_video_count']) ? (int) $user_roles[$vip_level]['free_video_count'] : 0;

        // 从 wp_usermeta 获取 islide_free_count
        $meta_key = 'islide_video_count';
        $islide_free_count = get_user_meta($user_id, $meta_key, true);

        // 如果数据为空，初始化为默认值
        if (empty($islide_free_count)) {
            $islide_free_count = [
                'date' => current_time('Y-m-d'),
                'count' => $free_video_count,
                'posts' => [],
            ];
            update_user_meta($user_id, $meta_key, $islide_free_count);
        }

        // 检查日期是否为今天
        $current_date = current_time('Y-m-d');
        if ($islide_free_count['date'] !== $current_date) {
            // 日期不同，重置 count 并更新时间
            $islide_free_count['date'] = $current_date;
            $islide_free_count['count'] = $free_video_count;
            $islide_free_count['posts'] = [];
            // 更新 wp_usermeta
            update_user_meta($user_id, $meta_key, $islide_free_count);
        }

        // 返回数据（包含等级对应的权限信息）
        return $islide_free_count['count'];
    }

    /**
     * 检查用户是否符合视频角色限制
     * 
     * @param array $video_meta 视频元数据
     * @param array $user_lv 用户等级信息
     * @return bool 是否允许观看
     */
    public static function check_user_video_roles($video_meta, $user_lv) {
        // 获取视频的限制角色列表
        if(!get_current_user_id()){
            return false;
        }
        $allowed_roles = isset($video_meta['islide_video_roles']) && is_array($video_meta['islide_video_roles']) 
            ? $video_meta['islide_video_roles'] 
            : [];

        // 格式化用户等级和 VIP 等级
        $user_lv_role = isset($user_lv['lv']['lv']) ? 'lv' . $user_lv['lv']['lv'] : null;  // 转换为 lv0, lv1 等格式
        $user_vip_role = isset($user_lv['vip']['lv']) ? $user_lv['vip']['lv'] : null; // VIP 等级直接使用

        // 检查用户等级或 VIP 等级是否符合视频要求
        if (in_array($user_lv_role, $allowed_roles) || in_array($user_vip_role, $allowed_roles)) {
            return true;
        }

        return false;
    }

    /**
     * 检查用户是否已评论
     *
     * @param int $post_id 文章 ID
     * @param int $user_id 用户 ID
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
     * 检查密码验证Cookie
     *
     * @param int $post_id 文章ID
     * @return bool 密码是否正确
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
        if ($type === 'circle') {
            $password = get_term_meta($post_id, 'islide_circle_password', true);
        } else {
            $password = get_post_meta($post_id, 'islide_post_password', true);
        }

        // 验证是否匹配
        if ($code === $official_code || $code === $password) {
            return true;
        }

        return false;
    }

    /**
     * 检查用户是否已购买
     *
     * @param int $post_id 文章 ID
     * @param int $user_id 用户 ID
     * @param int $index 索引值
     * @return bool 是否已购买
     */
    public static function has_user_purchased($post_id, $user_id, $index) {
        if (!$user_id) return false;
        
        // 获取当前 post 类型
        $post_type = get_post_type($post_id);
        
        // 根据不同类型进行处理
        if ($post_type == 'post') {
            // 普通文章类型 - 检查索引对应的购买记录
            $buy_data = get_post_meta($post_id, 'islide_download_buy', true);
            $buy_data = is_array($buy_data) ? $buy_data : [];
            
            // 检查索引是否存在并判断用户是否已购买
            return isset($buy_data[$index]) && is_array($buy_data[$index]) && in_array($user_id, $buy_data[$index]);
            
        } elseif ($post_type == 'video') {
            // 视频类型 - 先检查整体购买，再检查分组购买
            
            // 检查是否购买了整部视频
            $buy_data_all = get_post_meta($post_id, 'islide_video_buy', true);
            $buy_data_all = is_array($buy_data_all) ? $buy_data_all : [];

            if (in_array($user_id, $buy_data_all)) {
                return true; // 已购买整部视频
            }
            
            // 检查是否购买了指定章节（通过 $index）
            $buy_data_group = get_post_meta($post_id, 'islide_video_buy_group', true);
            $buy_data_group = is_array($buy_data_group) ? $buy_data_group : [];
            
            // 检查指定索引的章节是否存在用户购买记录
            return isset($buy_data_group[$index]) &&
                is_array($buy_data_group[$index]) &&
                in_array($user_id, $buy_data_group[$index]);
                
        } elseif ($post_type == 'episode') {
            // 剧集类型 - 需要检查父级视频的购买记录
            $parent_id = get_post_field('post_parent', $post_id);
            if (!$parent_id) return false;
            
            // 检查是否购买了整部视频
            $buy_data_all = get_post_meta($parent_id, 'islide_video_buy', true);
            $buy_data_all = is_array($buy_data_all) ? $buy_data_all : [];
            
            if (in_array($user_id, $buy_data_all)) {
                return true; // 已购买整部视频
            }
            
            // 检查是否购买了指定章节（通过 $index）
            $buy_data_group = get_post_meta($parent_id, 'islide_video_buy_group', true);
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
     * 视频权限检查函数
     *
     * @param int $video_id 视频ID
     * @param int $user_id 用户ID
     * @param int $index 视频在播放列表中的索引
     * @param int $part 分段索引（可选）
     * @return array 权限检查结果
     */
    public static function islide_check_user_can_video_allow($video_id, $user_id, $index, $part = 0) {
        // 初始化结果
        $result = [
            'allow' => false,   // 是否允许观看
            'type' => '',       // 权限类型
            'value' => 0,       // 当前付费金额或积分
            'total_value' => 0, // 总金额或积分要求
            'not_login_pay' => false, // 是否支持未登录用户购买
            'roles' => [],      // 限制角色
            'free_count' => 0,  // 免费观看次数
            'free_video' => false, // 是否是免费视频
        ];
        $_post_type = get_post_type($video_id);
        
        $user_lv = [
            'lv'  => User::get_user_lv($user_id),  // 普通等级信息
            'vip' => User::get_user_vip($user_id), // VIP 等级信息
        ];
        $is_vip = !empty($user_lv['vip']) ? true : false;
        $video_meta = array();
        
        // 检查视频类型，处理不同类型的权限逻辑
        if ($_post_type == 'episode') {
            // 剧集类型处理
            
            // VIP用户免费观看处理
            if ($is_vip) {
                $user_free_count = self::get_user_can_free_video_count($user_id) ? self::get_user_can_free_video_count($user_id) : 0;
                if ($user_free_count > 0) {
                    $result['free_count'] = $user_free_count;
                    $result['free_video'] = true;
                    $result['allow'] = true; // 允许观看，因为有免费次数
                    $result['type'] = 'free_count'; // 标记为免费次数观看类型
                    return $result;
                }
            }
            
            $parent_id = get_post_field('post_parent', $video_id);
            $video_meta = get_post_meta((int)$parent_id, 'single_video_metabox', true);
            
            if (empty($video_meta) || empty($video_meta['islide_video_role'])) {
                $result['type'] = 'free'; // 默认视为免费
                $result['allow'] = true;
                return $result;
            }
            
            if(isset($video_meta['group']) && !empty($video_meta['group']) && isset($video_meta['group'][$index]) && $video_id == $video_meta['group'][$index]['id']) {
                $result['type'] = $video_meta['islide_video_role'];
                $result['value'] = isset($video_meta['islide_video_pay_value']) ? (int)$video_meta['islide_video_pay_value'] : 0;
                $result['total_value'] = isset($video_meta['islide_video_pay_total']) ? (int)$video_meta['islide_video_pay_total'] : 0;
                $result['not_login_pay'] = !empty($video_meta['islide_video_not_login_buy']);
                
                // 获取角色详细信息
                if(isset($video_meta['islide_video_roles']) && is_array($video_meta['islide_video_roles'])) {
                    $role_list = [];
                    $user_roles = User::get_user_roles();
                    
                    foreach($video_meta['islide_video_roles'] as $role_key) {
                        // 处理普通等级 (lv0, lv1, ...)
                        if(strpos($role_key, 'lv') === 0) {
                            $level_num = intval(substr($role_key, 2));
                            $role_info = [
                                'lv' => $role_key,
                                'name' => isset($user_roles['lv'.$level_num]['name']) ? $user_roles['lv'.$level_num]['name'] : '等级'.$level_num,
                                'image' => isset($user_roles['lv'.$level_num]['image']) ? $user_roles['lv'.$level_num]['image'] : ''
                            ];
                            $role_list[] = $role_info;
                        } 
                        // 处理VIP等级 (vip0, vip1, ...)
                        else if(strpos($role_key, 'vip') === 0) {
                            $vip_level = substr($role_key, 3);
                            $role_info = [
                                'lv' => $role_key,
                                'name' => isset($user_roles['vip'.$vip_level]['name']) ? $user_roles['vip'.$vip_level]['name'] : 'VIP'.$vip_level,
                                'image' => isset($user_roles['vip'.$vip_level]['image']) ? $user_roles['vip'.$vip_level]['image'] : ''
                            ];
                            $role_list[] = $role_info;
                        }
                    }
                    $result['roles'] = $role_list;
                }
            }
        } else if ($_post_type == 'video') {
            // 视频类型处理
            
            // VIP用户免费观看处理
            if ($is_vip) {
                $user_free_count = self::get_user_can_free_video_count($user_id) ? self::get_user_can_free_video_count($user_id) : 0;
                if ($user_free_count > 0) {
                    $result['free_count'] = $user_free_count;
                    $result['free_video'] = true;
                    $result['allow'] = true; // 允许观看，因为有免费次数
                    $result['type'] = 'free_count'; // 标记为免费次数观看类型
                    return $result;
                }
            }
            
            $video_meta = get_post_meta((int)$video_id, 'single_video_metabox', true);
            
            if (empty($video_meta) || empty($video_meta['islide_video_role'])) {
                $result['type'] = 'free'; // 默认视为免费
                $result['allow'] = true;
                return $result;
            }
            
            $result['type'] = $video_meta['islide_video_role'];
            $result['value'] = isset($video_meta['islide_video_pay_value']) ? (int)$video_meta['islide_video_pay_value'] : 0;
            $result['total_value'] = isset($video_meta['islide_video_pay_total']) ? (int)$video_meta['islide_video_pay_total'] : 0;
            $result['not_login_pay'] = !empty($video_meta['islide_video_not_login_buy']);
            
            // 获取角色详细信息
            if(isset($video_meta['islide_video_roles']) && is_array($video_meta['islide_video_roles'])) {
                $role_list = [];
                $user_roles = User::get_user_roles();
                
                foreach($video_meta['islide_video_roles'] as $role_key) {
                    if(strpos($role_key, 'lv') === 0) {
                        $level_num = intval(substr($role_key, 2));
                        $role_info = [
                            'lv' => $role_key,
                            'name' => isset($user_roles['lv'.$level_num]['name']) ? $user_roles['lv'.$level_num]['name'] : '等级'.$level_num,
                            'image' => isset($user_roles['lv'.$level_num]['image']) ? $user_roles['lv'.$level_num]['image'] : ''
                        ];
                        $role_list[] = $role_info;
                    } else if(strpos($role_key, 'vip') === 0) {
                        $vip_level = substr($role_key, 3);
                        $role_info = [
                            'lv' => $role_key,
                            'name' => isset($user_roles['vip'.$vip_level]['name']) ? $user_roles['vip'.$vip_level]['name'] : 'VIP'.$vip_level,
                            'image' => isset($user_roles['vip'.$vip_level]['image']) ? $user_roles['vip'.$vip_level]['image'] : ''
                        ];
                        $role_list[] = $role_info;
                    }
                }
                $result['roles'] = $role_list;
            }
        } else {
            // 普通文章类型
            $video_list = get_post_meta($video_id, 'islide_single_post_video_group', true);
            
            if (empty($video_list) || !isset($video_list[$index])) {
                $result['type'] = 'free'; // 默认视为免费
                $result['allow'] = true;
                return $result;
            }
            
            $video_info = $video_list[$index];
            
            // 检查视频权限设置
            $result['type'] = isset($video_info['type']) ? $video_info['type'] : 'free';
            $result['value'] = isset($video_info['value']) ? (int)$video_info['value'] : 0;
            
            // 如果没有指定权限类型，默认为免费
            if (empty($result['type'])) {
                $result['type'] = 'free';
                $result['allow'] = true;
                return $result;
            }
        }
        
        // 检查权限类型
        switch ($result['type']) {
            case 'free':
                $result['allow'] = true; // 免费直接允许
                $result['free_video'] = true;
                break;
            case 'free_count':
                // 已在之前处理
                $result['allow'] = true;
                $result['free_video'] = true;
                break;
            case 'credit':
            case 'money':
                // 检查是否已支付
                $has_purchased = self::has_user_purchased($video_id, $user_id, $index);
                $result['allow'] = $has_purchased;
                break;
            case 'login':
                // 检查是否已登录
                $result['allow'] = $user_id > 0;
                break;
            case 'comment':
                // 检查是否有评论
                $result['allow'] = self::has_user_commented($video_id, $user_id);
                break;
            case 'password':
                // 检查是否输入密码
                $result['allow'] = self::verifyPasswordCookie($video_id);
                break;
            case 'roles':
                // 检查用户是否具有所需角色
                $result['allow'] = self::check_user_video_roles($video_meta, $user_lv);
                break;
            default:
                $result['allow'] = false; // 未知类型默认为禁止
                break;
        }

        return $result;
    }
}