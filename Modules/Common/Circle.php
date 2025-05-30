<?php
namespace islide\Modules\Common;
use islide\Modules\Common\Post;
use islide\Modules\Common\User;
use islide\Modules\Common\Search;
use islide\Modules\Common\ShortCode;
use islide\Modules\Common\Comment;
use islide\Modules\Common\CircleRelate;
use islide\Modules\Common\Seo;
use islide\Modules\Common\IpLocation;

class Circle
{
    public static function init()
    {
        add_action('wp_insert_comment', array(__CLASS__, 'set_default_answer_vote_count'), 10, 2);
    }

    /**
     * 在评论创建时设置默认点赞数
     * @author ifyn
     * @param int $comment_id 评论ID
     * @param \WP_Comment $comment 评论对象
     */
    public static function set_default_answer_vote_count($comment_id, $comment)
    {
        if ($comment->comment_type === 'circle_answer') {
            add_comment_meta($comment_id, 'circle_answer_like_count', 0);
            add_comment_meta($comment_id, 'circle_answer_dislike_count', 0);
        }
    }

    /**
     * 获取圈子设置
     * @author ifyn
     * @param int $circle_id 圈子ID
     * @param string $type 设置类型
     * @return mixed 圈子设置
     */
    public static function get_circle_settings($circle_id, $type)
    {
        // 参数类型检查
        if (!is_int($circle_id) || !is_string($type)) {
            return '';
        }

        if ($type == 'circle_tabbar' && get_term_meta($circle_id, 'islide_circle_tabbar_open', true) === '1') {
            $default = get_term_meta($circle_id, 'islide_circle_tabbar', true);
            $default = $default ? $default : '';
        } else {
            $default = islide_get_option($type);
            $default = $default ? $default : '';
        }


        return $default;
    }

    /**
     * 获取搜索建议 搜索文章、用户、标签、分类和自定义分类法
     * @author ifyn
     * @param array $data 搜索参数
     * @return array 结果数组
     */
    public static function get_search_circle($data)
    {
        // 参数类型检查
        if (!is_array($data)) {
            return array('error' => '参数格式错误');
        }

        if (!isset($data['type']) || !in_array($data['type'], array('circle_cat', 'topic'))) {
            return array('error' => '搜索类型错误');
        }

        $taxonomy = sanitize_text_field($data['type']);
        $search_terms = isset($data['keyword']) ? $data['keyword'] : '';

        // 检查搜索词的类型
        if (is_string($search_terms)) {
            $search_terms = array($search_terms);
        } elseif (!is_array($search_terms)) {
            return array();
        }

        // 过滤和验证搜索词
        $search_terms = array_map('sanitize_text_field', $search_terms);
        if (empty($search_terms)) {
            return array();
        }

        // 搜索标签、分类和自定义分类法
        $term_args = array(
            'name__like' => $search_terms[0],
            'hide_empty' => false,
            'number' => 10,
        );

        $terms = get_terms($taxonomy, $term_args);
        $results = []; // ✅ 确保 `$results` 是一个空数组

        // 判断是否有分类和标签结果
        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $similarity = Search::calculate_similarity($term->name, $search_terms);

                if ($taxonomy == 'circle_cat') {
                    $term_info = self::get_circle_data($term->term_id);
                } else {
                    $term_info = self::get_topic_data($term->term_id);
                }

                $term_info['similarity'] = $similarity;
                $results[] = $term_info;
            }
        }

        // ✅ 确保 `$results` 不是 `null`，否则直接返回空数组
        if (empty($results)) {
            return [];
        }

        // 根据相似度对结果数组进行排序
        usort($results, function ($a, $b) {
            return $b['similarity'] - $a['similarity'];
        });

        return $results;
    }


    /**
     * 记录动态最后插入的IP定位
     * @author ifyn
     * @param int $moment_id 动态ID
     * @return void
     */
    public static function insert_last_insert_moment($moment_id)
    {
        if (!is_int($moment_id) || $moment_id <= 0) {
            return;
        }
        $ip = islide_get_user_ip();
        //端口
        $data = IpLocation::get($ip);

        if (isset($data['error']))
            return;
        $data['date'] = current_time('mysql');

        update_post_meta($moment_id, 'islide_moment_ip_location', $data);
    }

    /**
     * 发布帖子
     * @author ifyn
     * @param array $data 帖子数据
     * @return array 结果
     */
    public static function insert_moment($data)
    {
        if (!is_array($data)) {
            return array('error' => '参数错误');
        }
        $user_id = get_current_user_id();

        if (!$user_id)
            return array('error' => '请先登录，才能发布帖子');

        //按 ID 或名称更改当前用户。
        wp_set_current_user($user_id);

        //圈子名字
        $circle_name = islide_get_option('circle_name');

        $data['circle_id'] = (int) $data['circle_id'];

        if (!$data['circle_id']) {
            //默认圈子
            $default_circle_id = islide_get_option('default_post_circle');

            if (empty($default_circle_id)) {
                return array('error' => sprintf('请选择帖子所在的%s', $circle_name));
            }

            $data['circle_id'] = (int) $default_circle_id;
        } else {
            // 检查圈子是否存在
            $circle_exist = term_exists($data['circle_id'], 'circle_cat');

            if ($circle_exist == 0 || $circle_exist == null)
                return array('error' => sprintf('%s不存在，请重新选择', $circle_name));
        }

        if (!self::get_circle_settings($data['circle_id'], 'circle_post_open')) {
            return array('error' => sprintf('%s发帖功能已被关闭，请联系管理员', $circle_name));
        }


        //发帖权限
        $moment_role = self::check_insert_moment_role($user_id, $data['circle_id']);
        if (empty($moment_role))
            return array('error' => '权限错误');

        //检查是否需要加入圈子才能发帖
        if ($moment_role['is_join_circle_post'] && !$moment_role['in_circle']) {
            return array('error' => sprintf('你需要加入%s才能发帖', $circle_name));
        }

        //检查是否有发帖权限
        if (!$moment_role['can_create_moment']) {
            return array('error' => sprintf('当前你无权限在%s发帖', $circle_name));
        }

        //帖子id
        $data['moment_id'] = isset($data['moment_id']) ? (int) $data['moment_id'] : 0;

        //检查标题
        $data['title'] = sanitize_text_field(wp_unslash(str_replace(array('{{', '}}'), '', $data['title'])));
        if (islideGetStrLen($data['title']) > 100)
            return array('error' => '标题太长，请限制在1-100个字符之内');

        //检查帖子内容
        $content = wp_strip_all_tags(str_replace(array('{{', '}}'), '', $data['content']));
        if (islideGetStrLen($content) > $moment_role['media_count']['max_word_limit'] || islideGetStrLen($content) < $moment_role['media_count']['min_word_limit']) {
            return array('error' => sprintf('内容长度请控制在%s-%s个字符之内', $moment_role['media_count']['min_word_limit'], $moment_role['media_count']['max_word_limit']));
        }

        if (isset($data['type']) && $data['type'] !== 'card') {
            $content = $data['content'];
        }

        //检查是否帖子隐私权限
        $privacy_role = self::check_privacy_role($data, $moment_role['privacy_role']);
        if (isset($privacy_role['error']))
            return $privacy_role;

        //检查帖子类型
        $moment_type = self::check_moment_type($data, $moment_role['type_role']);
        if (isset($moment_type['error'])) {
            return $moment_type;
        }

        // 设置帖子类型和投票数据
        $circle_meta = array(
            'circle_type' => isset($data['type']) ? $data['type'] : 'card'
        );

        //检查媒体
        if ($circle_meta['circle_type'] === 'card') {
            $check_media = self::check_media($data, $moment_role);
            if (isset($check_media['error'])) {
                return $check_media;
            }
        }



        // 如果有投票数据
        if (isset($data['vote_title']) && isset($data['vote_options']) && isset($data['vote_end_time'])) {
            $check_vote = self::check_vote_data($data);
            if (isset($check_vote['error'])) {
                return $check_vote;
            }
            $circle_meta['vote_title'] = $check_vote['data']['vote_title'];
            $circle_meta['vote_options'] = $check_vote['data']['vote_options'];
            $circle_meta['vote_end_time'] = $check_vote['data']['vote_end_time'];
        }

        //检查是否有无需审核直接发布权限
        $post_status = 'pending';

        if ($moment_role['can_moment_public']) {
            $post_status = 'publish';
        }

        //如果标题为空 自动设置标题
        $auto_title = false;
        if (empty($data['title'])) {
            $data['title'] = mb_strimwidth($content, 0, 100, '', 'utf-8');
            if (strlen($data['title']) < strlen($content))
                $data['title'] = $data['title'] . ' ......';
            $auto_title = true;
        }

        //准备文章发布参数
        $args = array(
            'post_type' => 'circle',
            'post_title' => $data['title'],
            'post_content' => $content,
            'post_status' => $post_status,
            'post_author' => $user_id,
        );

        //判断是发布还是修改帖子
        if (!empty($data['moment_id'])) {
            if (get_post_type($data['moment_id']) !== 'circle')
                return array('error' => '帖子不存在');

            //如果都不是 检查当前用户是否有编辑帖子的权力
            $manage_role = apply_filters('islide_check_manage_moment_role', array('user_id' => $user_id, 'circle_id' => $data['circle_id'], 'post_id' => $data['moment_id']));

            if (!$manage_role['can_edit'])
                return array('error' => '您无权限编辑帖子');

            if ($manage_role['is_self']) {
                $can_delete = self::check_user_can_delete($user_id, $data['moment_id']);
                if (isset($can_delete['error']))
                    return $can_delete;
            }

            unset($args['post_author']);
            $args['ID'] = (int) $data['moment_id'];

            $post_id = wp_update_post($args);
        }
        //发布帖子
        else {
            $post_id = wp_insert_post($args);
        }

        if ($post_id) {
            //设置IP
            self::insert_last_insert_moment($post_id);

            //设置圈子
            wp_set_post_terms($post_id, array($data['circle_id']), 'circle_cat');

            //设置话题
            preg_match_all('/#([^#]+)#/', $content, $topics);
            $_topics = array();

            if (!empty($topics[1])) {
                foreach ($topics[1] as $topic) {
                    $term = term_exists($topic, 'topic');
                    if ($term !== 0 && $term !== null) {
                        $_topics[] = $topic;
                    }
                }
            }

            // 设置文章的话题
            wp_set_post_terms($post_id, $_topics, 'topic');

            //设置圈子板块（标签）
            if (!empty($data['tag'])) {
                $tags = get_term_meta($data['circle_id'], 'islide_circle_tags', true);
                $tags = !empty($tags) && is_array($tags) ? $tags : array();
                $key = array_search($data['tag'], array_column($tags, 'name'));

                if ($key !== false) {
                    update_post_meta($post_id, 'islide_circle_tag', $data['tag']);
                }
            } else {
                delete_post_meta($post_id, 'islide_circle_tag');
            }

            //设置帖子隐私权限
            if (is_array($privacy_role)) {
                if (isset($privacy_role['type']) && !empty($privacy_role['type'])) {
                    update_post_meta($post_id, 'islide_post_content_hide_role', $privacy_role['type']);
                }

                //余额
                if (isset($privacy_role['value']) && !empty($privacy_role['value'])) {
                    if (in_array($privacy_role['type'], array('money', 'credit'))) {
                        update_post_meta($post_id, 'islide_post_price', (int) $privacy_role['value']);
                    }

                    if ($privacy_role['type'] == 'password') {
                        update_post_meta($post_id, 'islide_post_password', (int) $privacy_role['value']);
                    }
                }

                //等级
                if ($privacy_role['type'] == 'roles' && isset($privacy_role['roles']) && !empty($privacy_role['roles'])) {
                    foreach ($privacy_role['roles'] as $k => $v) {
                        $privacy_role['roles'][$k] = esc_attr(sanitize_text_field($v));
                    }
                    update_post_meta($post_id, 'islide_post_roles', $privacy_role['roles']);
                }
            }


            // 如果是问题类型，添加悬赏数据
            if ($circle_meta['circle_type'] === 'question' && isset($data['reward'])) {
                $circle_meta['reward'] = array(
                    'type' => $data['reward']['type'],
                    'value' => (int) $data['reward']['value']
                );
            }

            update_post_meta($post_id, 'single_circle_metabox', $circle_meta);


            if ($circle_meta['circle_type'] === 'card') {
                //图片挂载到当前帖子
                if (!empty($check_media['image']) || (isset($data['moment_id']) && (int) $data['moment_id'])) {
                    update_post_meta($post_id, 'islide_circle_image', $check_media['image']);

                    foreach ($check_media['image'] as $k => $value) {
                        if ((int) $value['id']) {
                            //检查是否挂载过
                            if (!wp_get_post_parent_id($value['id']) || (int) wp_get_post_parent_id($value['id']) === 1) {
                                wp_update_post(
                                    array(
                                        'ID' => $value['id'],
                                        'post_parent' => $post_id
                                    )
                                );
                            }
                        }
                    }
                }
                //视频挂载到当前帖子
                if (!empty($check_media['video']) || (isset($data['moment_id']) && (int) $data['moment_id'])) {
                    foreach ($check_media['video'] as $k => &$value) {
                        if ((int) $value['id']) {
                            //检查是否挂载过
                            if (!wp_get_post_parent_id((int) $value['id']) || (int) wp_get_post_parent_id((int) $value['id']) === 1) {
                                wp_update_post(
                                    array(
                                        'ID' => (int) $value['id'],
                                        'post_parent' => $post_id
                                    )
                                );
                            }

                            if (!empty($value['thumb'])) {
                                $thumb_id = attachment_url_to_postid($value['thumb']);
                                if ($thumb_id) {
                                    set_post_thumbnail((int) $value['id'], $thumb_id);
                                }
                            }

                            unset($value['thumb']);
                        }
                    }
                    if (!empty($check_media['video'])) {
                        update_post_meta($post_id, 'islide_circle_video', $check_media['video']);
                    } else {
                        delete_post_meta($post_id, 'islide_circle_video');
                    }
                }
            }

            return array('message' => '发布成功', 'data' => self::get_moment_data($post_id, $user_id));
        }

        return array('error' => '发布失败');
    }

    /**
     * 检查发布帖子的权限
     * @author ifyn
     * @param int $user_id 用户ID
     * @param int $circle_id 圈子ID
     * @param bool $editor 是否为编辑器
     * @return array 权限数据
     */
    public static function check_insert_moment_role($user_id, $circle_id = 0, $editor = false)
    {
        if (!is_int($user_id) || !is_int($circle_id)) {
            return array('error' => '参数类型错误');
        }
        $role_data = self::generate_role_data($user_id, $circle_id);
        if ($editor) {
            $media_size = islide_get_option('media_upload_size');
            $media_size = is_array($media_size) ? array_map('intval', $media_size) : array();

            $role_data['editor'] = array(
                'toolbar' => self::get_circle_settings($circle_id, 'circle_editor_toolbar'),
                'media_size' => $media_size
            );

            $roles = User::get_user_roles();
            foreach ($roles as $key => $value) {
                $role_data['roles'][$key] = $value['name'];
            }
        }

        return $role_data;
    }

    /**
     * @author ifyn
     * @param array $data 帖子数据
     * @param array $type_role 类型权限
     * @return bool|array
     */
    public static function check_moment_type($data, $type_role)
    {
        try {
            if (!isset($data['type'])) {
                $data['type'] = 'card';
            }

            // 检查类型是否在允许范围内
            $allowed_types = array('card', 'question');
            if (!in_array($data['type'], $allowed_types)) {
                return array(
                    'error' => '不支持的帖子类型'
                );
            }

            // 检查用户是否有权限发布该类型
            if (!in_array($data['type'], $type_role)) {
                return array(
                    'error' => '您没有权限发布该类型的帖子'
                );
            }

            // 如果是问题类型，检查悬赏积分
            if ($data['type'] === 'question') {
                if (!isset($data['reward']) || !isset($data['reward']['type']) || !isset($data['reward']['value'])) {
                    return array(
                        'error' => '问题类型必须设置悬赏积分'
                    );
                }

                if ($data['reward']['type'] !== 'credit') {
                    return array(
                        'error' => '悬赏类型必须是积分'
                    );
                }

                if (!is_numeric($data['reward']['value']) || $data['reward']['value'] <= 0) {
                    return array(
                        'error' => '悬赏积分必须大于0'
                    );
                }
            }

            return true;
        } catch (\Exception $e) {
            error_log('Check moment type error: ' . $e->getMessage());
            return array(
                'error' => '检查帖子类型失败'
            );
        }
    }

    /**
     * 检查媒体
     * @author ifyn
     * @param array $data 媒体数据
     * @param array $moment_role 权限数据
     * @return array 媒体附件
     */
    public static function check_media($data, $moment_role)
    {
        if (!is_array($data) || !is_array($moment_role)) {
            return array('error' => '参数类型错误');
        }
        $media_role = (array) $moment_role['media_role'];
        $media_count = (array) $moment_role['media_count'];

        $args = array('image' => '图片', 'video' => '视频', 'file' => '文件', 'card' => '卡片');

        $attachment = array(
            'image' => array(),
            'video' => array(),
            'file' => array(),
            'card' => array()
        );

        foreach ($args as $key => $value) {
            if (isset($data[$key]) && !empty((array) $data[$key])) {
                $files = (array) $data[$key];
                if (isset($media_role[$key]) && $media_role[$key] === true) {

                    // 修复未定义变量$count，使用配置中的最大数量
                    $max_count = isset($media_count[$key . '_count']) ? $media_count[$key . '_count'] : 0;
                    if (count($files) > $max_count)
                        return array('error' => sprintf('最多允许发布带有%s的%s', $max_count, $value));

                    foreach ($files as $v) {
                        $post_type = get_post_type((int) $v['id']);
                        $url = !empty($v['url']) ? esc_url(sanitize_text_field($v['url'])) : '';
                        $thumb = !empty($v['thumb']) ? esc_url(sanitize_text_field($v['thumb'])) : '';

                        if ($post_type) {
                            if ($key == 'video') {
                                $attachment[$key][] = array(
                                    'id' => (int) $v['id'],
                                    'thumb' => $thumb
                                );
                            } else {
                                $attachment[$key][] = array(
                                    'id' => (int) $v['id']
                                );
                            }
                        } elseif ($url) {

                            if (!$thumb)
                                return array('error' => sprintf('请设置%s的封面', $value));

                            $attachment[$key][] = array(
                                'id' => 0,
                                'url' => $url,
                                'thumb' => $thumb
                            );
                        }
                    }

                } else {
                    return array('error' => sprintf('无权发布带有%s的帖子', $value));
                }
            }

        }

        return $attachment;
    }

    /**
     * 检查是否帖子隐私权限
     * @author ifyn
     * @param array $data 帖子数据
     * @param array $privacy_role 权限数据
     * @return array|bool
     */
    public static function check_privacy_role($data, $privacy_role)
    {
        if (!isset($data['privacy']['type']) || empty($data['privacy']['type']))
            return array('error' => '请设置帖子的阅读权限');

        $privacy_type = $data['privacy']['type'];
        $value = isset($data['privacy']['value']) && is_numeric($data['privacy']['value']) ? (int) $data['privacy']['value'] : 0;

        if ($privacy_type !== 'none') {
            $title = str_replace(array('{{', '}}'), '', $data['title']);
            $title = sanitize_text_field($title);

            if (islideGetStrLen($title) < 2)
                return array('error' => '请设置一个标题，让用户了解您隐藏的是什么内容！');
        } else {
            return true;
        }

        if (!isset($privacy_role[$privacy_type]) || $privacy_role[$privacy_type] !== true)
            return array('error' => '你无权发布相关的隐私权限');

        if ($privacy_type == 'money' || $privacy_type == 'credit') {
            if ($value <= 0 || $value > 99999)
                return array('error' => '阅读权限，设置的价格错误');
        } elseif ($privacy_type == 'password') {
            if ($value <= 1000 || $value > 9999)
                return array('error' => '密码阅读，请设置正确的长度为4位的数字');
        } elseif ($privacy_type == 'roles') {
            $roles = User::get_user_roles();
            $_roles = isset($data['privacy']['roles']) && is_array($data['privacy']['roles']) ? (array) $data['privacy']['roles'] : array();

            if (empty($_roles))
                return array('error' => '限制等级阅读，请至少设置一个用户组限制');

            foreach ($_roles as $value) {
                if (!isset($roles[$value]))
                    return array('error' => sprintf('不存在%s此用户组', $value));
            }
        }

        if (!isset($data['privacy']['content']) || !$data['privacy']['content']) {
            return array('error' => '请填写你需要隐藏的内容');
        }

        return $data['privacy'];
    }

    /**
     * 创建圈子
     * @author ifyn
     * @param array $data 圈子数据
     * @return array|bool
     */
    public static function create_circle($data)
    {
        $user_id = get_current_user_id();
        if (!$user_id)
            return array('error' => '请先登录');

        $type = isset($data['type']) ? (int) $data['type'] : '';

        //if(!in_array($type,array('topic','circle_cat'))) return array('error'=>'创建类型错误');

        //圈子名字
        $circle_name = islide_get_option('circle_name');

        $circle_id = isset($data['id']) ? (int) $data['id'] : 0;

        $is_edit = !!$circle_id;


        if ($is_edit) {
            $circle = self::is_circle_exists($circle_id);
            if (is_array($circle) && isset($circle['error']))
                return $circle;
        }

        $role = self::check_insert_moment_role($user_id, $circle_id);

        if (empty($role['can_create_circle']) && !$is_edit)
            return array('error' => sprintf('您没有权限创建%s', $circle_name));

        if (empty($role['is_circle_staff']) && empty($role['is_admin']) && $is_edit)
            return array('error' => sprintf('您没有权限修改%s', $circle_name));

        //获取圈子分类
        $circle_cats = self::get_circle_cats();
        if (isset($circle_cats['error']))
            return $circle_cats;

        if (!in_array($data['circle_cat'], array_column($circle_cats, 'name')))
            return array('error' => sprintf('请选择%s类别', $circle_name));

        //基础资料检查
        if (empty($data['name']) || empty($data['desc']) || empty($data['slug']) || empty($data['cover']) || empty($data['icon'])) {
            return array('error' => sprintf('请完善%s资料', $circle_name));
        }

        $name = sanitize_text_field(wp_unslash(str_replace(array('{{', '}}'), '', $data['name'])));
        $desc = sanitize_text_field(wp_unslash(str_replace(array('{{', '}}'), '', $data['desc'])));
        $slug = sanitize_text_field(wp_unslash(str_replace(array('{{', '}}'), '', $data['slug'])));

        if (islidegetStrLen($name) < 2 || islidegetStrLen($name) > 20) {
            return array('error' => sprintf('%s名称必须大于2个字符，小于10个字符', $circle_name));
        }

        $name_circle = get_term_by('name', $name, 'circle_cat');
        if ($name_circle && $name_circle->term_id !== $circle_id)
            return array('error' => sprintf('%s[%s]已被创建，请更换其他名称', $circle_name, $name));

        if (!$slug)
            return array('error' => sprintf('请填写%s英文网页地址', $circle_name));

        if (mb_strlen($slug, 'utf-8') !== strlen($slug))
            return array('error' => '请使用纯英文网页地址');

        //不用 wp_update_term 自动检查此别名"square"已被其他项目使用
        $slug_circle = get_term_by('slug', $slug, 'circle_cat');
        if ($slug_circle && $slug_circle->term_id !== $circle_id)
            return array('error' => sprintf('%s[%s]地址已存在，请更换英文网页地址', $circle_name, $slug));

        if (islidegetStrLen($desc) < 10 || islidegetStrLen($desc) > 100) {
            return array('error' => sprintf('%s简介必须大于10个字符，小于100个字符', $circle_name));
        }

        $icon = esc_url(sanitize_text_field($data['icon']));
        $cover = esc_url(sanitize_text_field($data['cover']));

        if (!attachment_url_to_postid($icon) || !attachment_url_to_postid($cover))
            return array('error' => sprintf('请完善%s图标与背景图', $circle_name));

        /********检查开始*********/
        if (!$is_edit || (!empty($role['is_circle_admin']) || !empty($role['is_admin']) && $is_edit)) {
            $circle_privacy = self::check_circle_privacy($data);
            if (isset($circle_privacy['error']))
                return $circle_privacy;

            $circle_layout = isset($data['layout']) && is_array($data['layout']) ? $data['layout'] : array();

            $arr = array('global', '0', '1', 'pc', 'mobile', 'all');

            foreach ($circle_layout as $value) {
                if (!in_array($value, $arr))
                    return array('error' => '参数非法');
            }

            $circle_role = isset($data['role']) && is_array($data['role']) ? $data['role'] : array();
            foreach ($circle_role as $value) {
                if (!in_array($value, $arr))
                    return array('error' => '参数非法');
            }
        }

        $args = array(
            'name' => $name,
            'description' => $desc,
            'slug' => $slug,
        );

        //判断是修改
        if (!empty($circle_id)) {
            $term = wp_update_term($circle_id, 'circle_cat', $args);
        } else {
            $term = wp_insert_term($name, 'circle_cat', $args);
        }

        if (is_wp_error($term)) {
            return array('error' => $term->get_error_message());
        }

        $circle_id = $term['term_id'];

        if ($circle_id) {

            //保存特色图与背景
            update_term_meta($circle_id, 'islide_tax_img', $icon);
            update_term_meta($circle_id, 'islide_tax_cover', $cover);

            //保存圈子分类
            update_term_meta($circle_id, 'islide_circle_cat', $data['circle_cat']);

            if ((!empty($role['is_circle_admin']) || !empty($role['is_admin']) && $is_edit) || !$is_edit) {

                //圈子隐私(帖子是否公开显示)
                update_term_meta($circle_id, 'islide_circle_privacy', $circle_privacy['privacy']);
                //圈子加入权限
                update_term_meta($circle_id, 'islide_circle_type', $circle_privacy['type']); //权限类型

                if ($circle_privacy['type'] == 'password') {
                    update_term_meta($circle_id, 'islide_circle_password', $circle_privacy['password']);
                } elseif ($circle_privacy['type'] == 'roles') {
                    update_term_meta($circle_id, 'islide_circle_roles', $circle_privacy['roles']);
                } elseif (in_array($circle_privacy['type'], array('money', 'credit'))) {
                    update_term_meta($circle_id, 'islide_circle_pay_group', $circle_privacy['pay_group']);
                }

                update_term_meta($circle_id, 'islide_circle_join_post_open', $circle_role['join_post']);

                update_term_meta($circle_id, 'islide_circle_info_show', $circle_layout['info_show']);

                update_term_meta($circle_id, 'islide_circle_input_show', $circle_layout['editor_show']);
            }

            if (!$is_edit) {

                //保存圈子创建者
                if (
                    CircleRelate::update_data(array(
                        'user_id' => $user_id,
                        'circle_id' => $circle_id,
                        'circle_role' => 'admin',
                        'join_date' => current_time('mysql')
                    ))
                ) {
                    update_term_meta($circle_id, 'islide_circle_admin', $user_id);
                }
            }


            //圈子板块
            //....

            //圈子推荐
            //....
            return array(
                'data' => array(
                    'circle_id' => $circle_id,
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $desc,
                    'icon' => $icon,
                    'cover' => $cover
                )
            );
        }

        return array('error' => sprintf('%s创建失败', $circle_name));
    }

    /**
     * 检查是否圈子隐私权限
     * @author ifyn
     * @param array $data 圈子数据
     * @return array
     */
    public static function check_circle_privacy($data)
    {
        $circle_name = islide_get_option('circle_name');

        if (!isset($data['privacy']['type']) || empty($data['privacy']['type']))
            return array('error' => sprintf('请设置%s的权限', $circle_name));

        if (!isset($data['privacy']['privacy']))
            return array('error' => sprintf('请设置%s帖子隐私', $circle_name));

        $privacy_types = array('free', 'money', 'credit', 'roles', 'password');
        $privacy_type = $data['privacy']['type'];

        if (!in_array($privacy_type, $privacy_types))
            return array('error' => sprintf('%s的权限非法', $circle_name));

        if ($privacy_type == 'password') {

            if (!isset($data['privacy']['password']) || empty($data['privacy']['password']) || (int) $data['privacy']['password'] <= 1000 || (int) $data['privacy']['password'] > 9999)
                return array('error' => sprintf('请设置%s正确的长度为4位的数字密码', $circle_name));

        } elseif ($privacy_type == 'money' || $privacy_type == 'credit') {
            if (!isset($data['privacy']['pay_group']) || empty($data['privacy']['pay_group']))
                return array('error' => sprintf('请设置%s支付信息', $circle_name));

            $pay_group = is_array($data['privacy']['pay_group']) ? $data['privacy']['pay_group'] : array();

            foreach ($pay_group as $key => $value) {
                foreach ($value as $k => $v) {

                    if ($k != 'name') {
                        if (!is_numeric($v) || $v < 0 || $v > 9999)
                            return array('error' => '请填写数字，且最大长度为4位');
                    } else {
                        if (islidegetStrLen($v) < 1 || islidegetStrLen($v) > 10) {
                            return array('error' => '支付信息名称必须大于1个字符，小于10个字符');
                        }

                        $data['privacy']['pay_group'][$key][$k] = sanitize_text_field(wp_unslash(str_replace(array('{{', '}}'), '', $v)));
                    }
                }
            }

        } elseif ($privacy_type == 'roles') {
            $roles = User::get_user_roles();
            $_roles = isset($data['privacy']['roles']) && is_array($data['privacy']['roles']) ? (array) $data['privacy']['roles'] : array();
            if (empty($_roles))
                return array('error' => sprintf('专属%s，请至少设置一个用户组限制', $circle_name));

            foreach ($_roles as $value) {
                if (!isset($roles[$value]))
                    return array('error' => sprintf('不存在%s此用户组', $value));
            }
        }

        if ($data['privacy']['privacy'] === false) {
            $data['privacy']['privacy'] = 'private';
        } else {
            $data['privacy']['privacy'] = 'public';
        }

        return $data['privacy'];
    }

    /**
     * 创建话题
     * @author ifyn
     * @param array $data 话题数据
     * @return array
     */
    public static function create_topic($data)
    {
        $user_id = get_current_user_id();
        if (!$user_id)
            return array('error' => '请先登录');

        $role = self::check_insert_moment_role($user_id, 0);

        if (empty($role['can_create_topic']) && empty($role['is_admin'])) {
            return array('error' => '您没有权限创建或修改话题');
        }

        // 基础资料检查
        if (empty($data['name']) || empty($data['desc']) || empty($data['slug']) || empty($data['icon'])) {
            return array('error' => '请完善话题资料');
        }

        $name = sanitize_text_field(wp_unslash(str_replace(array('{{', '}}'), '', $data['name'])));
        $desc = sanitize_text_field(wp_unslash(str_replace(array('{{', '}}'), '', $data['desc'])));
        $slug = sanitize_text_field(wp_unslash(str_replace(array('{{', '}}'), '', $data['slug'])));
        $icon = esc_url(sanitize_text_field($data['icon']));

        if (islidegetStrLen($name) < 2 || islidegetStrLen($name) > 20) {
            return array('error' => '话题名称必须大于2个字符，小于20个字符');
        }

        if (!$slug)
            return array('error' => '请填写话题英文网页地址');

        if (mb_strlen($slug, 'utf-8') !== strlen($slug))
            return array('error' => '请使用纯英文网页地址');

        if (islidegetStrLen($desc) < 10 || islidegetStrLen($desc) > 100) {
            return array('error' => '话题简介必须大于10个字符，小于100个字符');
        }

        if (!attachment_url_to_postid($icon)) {
            return array('error' => '请完善话题图标');
        }

        // 判断是否为修改操作
        if (!empty($data['id']) && (int) $data['id'] > 0) {
            $topic_id = (int) $data['id'];

            // 检查话题是否存在
            $term = get_term($topic_id, 'topic');
            if (is_wp_error($term) || empty($term)) {
                return array('error' => '要修改的话题不存在');
            }

            // 更新话题
            $args = array(
                'name' => $name,
                'description' => $desc,
                'slug' => $slug,
            );

            $updated_term = wp_update_term($topic_id, 'topic', $args);
            if (is_wp_error($updated_term)) {
                return array('error' => $updated_term->get_error_message());
            }

            // 更新话题元数据
            update_term_meta($topic_id, 'islide_tax_img', $icon);
            update_term_meta($topic_id, 'islide_topic_admin', $user_id);

            return array(
                'data' => array(
                    'topic_id' => $topic_id,
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $desc,
                    'icon' => $icon
                )
            );
        } else {
            // 创建话题
            if (get_term_by('name', $name, 'topic')) {
                return array('error' => sprintf('[%s] 已被创建，请更换其他名称', $name));
            }

            if (get_term_by('slug', $slug, 'topic')) {
                return array('error' => sprintf('[%s] 地址已存在，请更换英文网页地址', $slug));
            }

            $args = array(
                'name' => $name,
                'description' => $desc,
                'slug' => $slug,
            );

            $term = wp_insert_term($name, 'topic', $args);

            if (is_wp_error($term)) {
                return array('error' => $term->get_error_message());
            }

            $topic_id = $term['term_id'];

            if ($topic_id) {
                // 保存元数据
                update_term_meta($topic_id, 'islide_tax_img', $icon);
                update_term_meta($topic_id, 'islide_topic_admin', $user_id);

                return array(
                    'data' => array(
                        'topic_id' => $topic_id,
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $desc,
                        'icon' => $icon
                    )
                );
            }

            return array('error' => '话题创建失败');
        }
    }

    /**
     * 加入圈子
     * @author ifyn
     * @param array $data 加入数据
     * @return array|string
     */
    public static function join_circle($data)
    {
        $user_id = get_current_user_id();
        if (!$user_id)
            return array('error' => '请先登录');

        $circle_id = $data['circle_id'] = isset($data['circle_id']) ? (int) $data['circle_id'] : 0;

        //检查圈子是否存在
        $circle = self::is_circle_exists($circle_id);
        if (is_array($circle) && isset($circle['error']))
            return $circle;

        $circle_id = (int) $circle_id;

        if (self::is_user_joined_circle($user_id, $circle_id)) {
            return array('error' => '您已经加入了，无需再次加入');
        }

        if (apply_filters('islide_check_user_join_circle_role', $user_id, $data)) {
            if (
                CircleRelate::update_data(array(
                    'user_id' => $user_id,
                    'circle_id' => $circle_id,
                    'circle_role' => 'member',
                    'join_date' => current_time('mysql')
                ))
            ) {
                $user_circle_meta = get_user_meta($user_id, 'islide_joined_circles', true);
                $user_circle_meta = is_array($user_circle_meta) ? $user_circle_meta : [];
                $user_circle_meta[$circle_id] = array(
                    'circle_id' => $circle_id,
                    'end_date' => '0000-00-00 00:00:00',
                );

                update_user_meta($user_id, 'islide_joined_circles', $user_circle_meta);
                return array(
                    'data' => array(
                        'circle_id' => $circle_id,
                        'user_id' => $user_id,
                        'join_date' => current_time('mysql')
                    )
                );
            }
        }

        return array('error' => '加入失败，您还未获得加入资格');
    }

    /**
     * 获取加入权限
     * @author ifyn
     * @param int $user_id 用户ID
     * @param int $circle_id 圈子ID
     * @return array 权限数据
     */
    public static function get_circle_role_data($user_id, $circle_id)
    {
        $data = array(
            'type' => 'free',
            'type_name' => '免费',
            'roles' => array(),
            'pay_group' => array(),
            'allow' => false
        );

        $circle_type = get_term_meta($circle_id, 'islide_circle_type', true);
        $data['type'] = $circle_type ?: 'free';

        if ($data['type'] == 'roles') {
            $roles = (array) get_term_meta($circle_id, 'islide_circle_roles', true);

            $lv = User::get_user_lv($user_id);
            $vip = User::get_user_vip($user_id);
            $lvs = array();

            if (!empty($lv['lv'])) {
                $lvs[] = 'lv' . $lv['lv'];
            }

            if (!empty($vip['lv'])) {
                $lvs[] = $vip['lv'];
            }

            if (!empty(array_intersect($roles, $lvs))) {
                $data['allow'] = true;
            }

            $_roles = User::get_user_roles();
            foreach ($roles as $key => $value) {
                if (isset($_roles[$value])) {
                    $data['roles'][] = array(
                        'lv' => $value,
                        'name' => $_roles[$value]['name'],
                        'image' => $_roles[$value]['image'],
                    );
                }
            }

            $data['type_name'] = '专属';
        } elseif ($data['type'] == 'credit' || $data['type'] == 'money') {

            $pay_group = (array) get_term_meta($circle_id, 'islide_circle_pay_group', true);
            $data['pay_group'] = (array) $pay_group;
            $data['type_name'] = '付费';
        } else if ($data['type'] == 'password') {
            $data['type_name'] = '密码';
        }

        return $data;
    }

    /**
     * 检查圈子用户有效期
     * @author ifyn
     * @param int $user_id 用户ID
     * @return void
     */
    public static function circle_user_pass($user_id)
    {

        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_circle_related';

        $res = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table_name WHERE `user_id`=%d AND `circle_key`!=''", $user_id)
            ,
            ARRAY_A
        );

        if ($res) {
            $ids = array();
            foreach ($res as $k => $v) {
                if ($v['end_date'] !== '0000-00-00 00:00:00') {
                    if ($v['end_date'] < current_time('mysql')) {
                        $ids[] = array(
                            'id' => $v['id'],
                            'circle_id' => $v['circle_id']
                        );
                    }
                }
            }

            if (!empty($ids)) {
                foreach ($ids as $v) {
                    CircleRelate::delete_data(array('circle_id' => $v['circle_id'], 'user_id' => $user_id));
                }
            }
        }

        return;
    }

    /**
     * 获取某个圈子用户
     * @author ifyn
     * @param array $data 查询参数
     * @return array 用户列表
     */
    public static function get_circle_users($data)
    {
        $paged = isset($data['paged']) ? (int) $data['paged'] : 1;
        $size = isset($data['size']) ? (int) $data['size'] : 10;
        $circle_id = isset($data['circle_id']) ? (int) $data['circle_id'] : 0;
        $type = isset($data['type']) ? $data['type'] : 'staff';

        if ($size > 20)
            return array('error' => '请求数量过多');
        if ($paged < 0)
            return array('error' => '请求格式错误');

        $offset = ($paged - 1) * (int) $size;

        $user_id = get_current_user_id();

        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_circle_related';

        $where_condition = ($type == 'staff') ? "AND (circle_role = 'admin' OR circle_role = 'staff')" : "";

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT COUNT(*) FROM $table_name
                WHERE circle_id = %s $where_condition",
                $circle_id
            )
        );

        $res = $wpdb->get_results(
            $wpdb->prepare(
                "
            SELECT * FROM $table_name
            WHERE circle_id = %s $where_condition
            ORDER BY circle_role ASC,join_date ASC LIMIT %d,%d",
                $circle_id,
                $offset,
                $size
            ),
            ARRAY_A
        );

        $list = array();

        foreach ($res as $value) {
            $user = get_user_by('ID', $value['user_id']);
            if ($user) {

                $list[] = array_merge(array(
                    'date' => $value['join_date'],
                    'role' => $value['circle_role'],
                    'is_self' => $value['user_id'] == $user_id,
                    'in_circle' => true
                ), User::get_user_public_data($value['user_id']));
            }
        }

        return array(
            'pages' => ceil($count / $size), // 计算总页数
            'count' => $count,
            'list' => $list

        );
    }

    /**
     * 用户搜索
     * @author ifyn
     * @param string $key 关键词
     * @param int $circle_id 圈子ID
     * @return array 用户列表
     */
    public static function circle_search_users($key, $circle_id)
    {

        if (!$key)
            return array();

        $user_id = get_current_user_id();

        $user_query = new \WP_User_Query(array(
            'search' => '*' . $key . '*',
            'search_columns' => array(
                'display_name',
            ),
            'number' => 20,
            'paged' => 1
        ));

        $results = $user_query->get_results();

        $users = array();

        foreach ($results as $key => $user) {
            $users[] = array_merge(array(
                // 'id' => $user->ID,
                // 'name' => User::get_user_name_html($user->ID),
                // 'link' => get_author_posts_url($user->ID),
                // 'avatar'=> get_avatar_url($user->ID,array('size'=>100)),
                // 'desc' => get_the_author_meta('description',$user->ID)?:'这个人很懒什么都没有留下~',
                'date' => '',
                'role' => self::is_user_circle_staff($user->ID, $circle_id),
                'is_self' => $user->ID == $user_id,
                'in_circle' => self::is_user_joined_circle($user->ID, $circle_id)
            ), User::get_user_public_data($user->ID));
            ;
        }

        return $users;
    }

    /**
     * 获取圈子分类
     * @author ifyn
     * @return array 圈子分类
     */
    public static function get_circle_cats()
    {

        $circle_cats = islide_get_option('circle_cats');
        if (empty($circle_cats)) {
            $circle_name = islide_get_option('circle_name');
            return array('error' => sprintf('请设置%s分类', $circle_name));
        }

        return $circle_cats;
    }

    /**
     * 获取所有圈子信息
     * @author ifyn
     * @return array 圈子信息
     */
    public static function get_all_circles()
    {

        $circle_cats = self::get_circle_cats();
        if (isset($circle_cats['error']))
            return $circle_cats;

        $user_id = get_current_user_id();

        array_unshift($circle_cats, array('name' => '我的'));

        $data = array(
            'cats' => $circle_cats,
            'list' => array(),
        );

        unset($circle_cats[0]);
        foreach ($circle_cats as $v) {
            $args = array(
                'taxonomy' => 'circle_cat',
                'orderby' => 'count',
                //'meta_key' => 'islide_hot_weight',
                'number' => 60,
                //'exclude' => array(get_option('_circle_default')),
                'order' => 'DESC',
                'meta_query' => array(
                    array(
                        'key' => 'islide_circle_cat',
                        'value' => $v['name'],
                        'compare' => '='
                    ),
                ),
                'hide_empty' => false,
                'cache_domain' => 'islide_circle_cats'
            );

            $circles = get_terms($args);

            $circle_data = array();

            if (!empty($circles)) {
                foreach ($circles as $k => $_v) {
                    $circle_data[$k] = self::get_circle_data($_v->term_id);
                }
            }

            $data['list'][] = array(
                'cat_name' => $v['name'],
                'list' => $circle_data
            );
        }

        //获取用户的圈子
        $user_circles = self::get_user_circles($user_id);
        $_user_circles = array(
            'cat_name' => '我的',
            'list' => array()
        );
        foreach ($user_circles as $value) {

            if (!empty($value['list'])) {
                $value['list'][0]['cat_name'] = $value['cat_name'];
            }

            $_user_circles['list'] = array_merge($_user_circles['list'], $value['list']);
        }

        array_insert($data['list'], 0, array($_user_circles));

        return $data;
    }

    /**
     * 获取某个用户创建的圈子、管理的圈子和加入的圈子
     * @author ifyn
     * @param int $user_id 用户ID
     * @return array
     */
    public static function get_user_circles($user_id)
    {

        if (!$user_id)
            return array();

        $res = CircleRelate::get_data(array(
            'user_id' => $user_id,
            //'circle_role'=>'member',
            'count' => 49
        ));

        $ids = array();
        if (!empty($res)) {
            $ids = array_column($res, 'circle_id');
        }

        $circles = get_terms(array(
            'taxonomy' => 'circle_cat',
            'hide_empty' => false,
            'include' => $ids,
            // 'meta_query' => array(
            //     'relation' => 'OR',
            //     array(
            //         'key' => 'islide_circle_admin',
            //         'value' => $user_id,
            //         'compare' => '=',
            //     ),
            //     array(
            //         'key' => 'islide_circle_staff', 
            //         'value' => ':"' . $user_id . '";', // 使用正则表达式匹配序列化数据中的某个值
            //         'compare' => 'REGEXP',
            //     ),
            //     // array(
            //     //     'key' => 'islide_circle_staff', 
            //     //     'value' => '%i:'. $user_id .';%', // 使用LIKE比较运算符进行模糊匹配
            //     //     'compare' => 'LIKE',
            //     // ),
            // ),
            'orderby' => 'count',
            'order' => 'DESC',
            //'meta_key' => 'islide_hot_weight',
            'cache_domain' => 'islide_circle_cat'
        ));

        $created_circles = array();
        $managed_circles = array();
        $joined_circles = array();

        foreach ($circles as $circle) {

            $check = self::is_user_circle_staff($user_id, $circle->term_id);
            $circle_data = self::get_circle_data($circle->term_id);

            if ($check == 'admin') {
                $created_circles[] = $circle_data;
            } elseif ($check == 'staff') {
                $managed_circles[] = $circle_data;
            } else {
                $joined_circles[] = $circle_data;
            }
        }
        //圈子名字
        //$circle_name = islide_get_option('circle_name');

        $result = array(
            'created' => array(
                'cat_name' => '我创建的',
                'list' => $created_circles
            ),
            'managed' => array(
                'cat_name' => '我是版主的',
                'list' => $managed_circles
            ),
            'joined' => array(
                'cat_name' => '我加入的',
                'list' => $joined_circles
            ),
        );

        return $result;
    }

    /**
     * 获取圈子的创建者和版主信息
     *
     * @param int $circle_id 圈子ID
     * @return array 返回包含圈子创建者和版主信息的数组
     */
    public static function get_circle_admins($circle_id)
    {

        //创建者
        $admin = get_term_meta($circle_id, 'islide_circle_admin', true);
        $admin = !empty($admin) ? (int) $admin : 1;
        $admin_data = User::get_user_public_data($admin);

        //版主及工作人员
        $staff = get_term_meta($circle_id, 'islide_circle_staff', true);
        $staff = !empty($staff) && is_array($staff) ? $staff : array();
        $staff_data = array();

        if ($staff) {
            foreach ($staff as $value) {
                $staff_data[] = User::get_user_public_data($value);
            }
        }

        $users = array(
            'admin' => $admin_data,//创建者
            'staff' => $staff_data, //版主及工作人员
        );

        return $users;
    }

    /**
     * 检查用户是否为圈子的创建者或版主
     *
     * @param int $user_id 用户ID
     * @param int $circle_id 圈子ID
     * @return string|bool 返回'admin'表示用户是圈子的创建者，返回'staff'表示用户是圈子的版主或工作人员，返回false表示用户既不是创建者也不是版主或工作人员
     */
    public static function is_user_circle_staff($user_id, $circle_id)
    {

        if (!$user_id || !$circle_id)
            return false;

        //创建者
        $admin = get_term_meta($circle_id, 'islide_circle_admin', true);
        $admin = !empty($admin) ? (int) $admin : 1;

        if ((int) $user_id === $admin) {
            return 'admin';
        }

        //版主及工作人员
        $staff = get_term_meta($circle_id, 'islide_circle_staff', true);
        $staff = !empty($staff) && is_array($staff) ? $staff : array();

        if (in_array($user_id, $staff)) {
            return 'staff';
        }

        return false;
    }

    public static function is_circle_staff($user_id, $circle_id)
    {

        if (!$user_id || !$circle_id)
            return false;

        //版主及工作人员
        $staff = get_term_meta($circle_id, 'islide_circle_staff', true);
        $staff = !empty($staff) && is_array($staff) ? $staff : array();

        if (in_array($user_id, $staff)) {
            return true;
        }

        return false;
    }

    public static function is_circle_admin($user_id, $circle_id)
    {

        if (!$user_id || !$circle_id)
            return false;

        //创建者
        $admin = get_term_meta($circle_id, 'islide_circle_admin', true);
        $admin = !empty($admin) ? (int) $admin : 1;

        if ((int) $user_id === $admin) {
            return true;
        }

        return false;
    }




    /**
     * 检查用户是否加入某个圈子
     *
     * @param int $user_id 用户ID
     * @param int $circle_id 圈子ID
     * @return bool 返回true表示用户已加入圈子，返回false表示用户未加入圈子
     */
    public static function is_user_joined_circle($user_id, $circle_id)
    {
        $user_role = self::check_user_circle_access($user_id, $circle_id);
        return $user_role;
    }

    /**
     * 检查圈子是否存在
     *
     * @param int $circle_id 圈子ID
     * @return array 如果圈子存在，返回圈子数据数组；如果圈子不存在，返回错误信息
     */
    public static function is_circle_exists($circle_id)
    {
        //获取圈子
        $circle = get_term_by('id', $circle_id, 'circle_cat');

        if (!$circle || is_wp_error($circle)) {
            $circle_name = islide_get_option('circle_name');
            return array('error' => sprintf('%s不存在', $circle_name));
        }

        return $circle;
    }

    /**
     * 获取某个圈子的详细信息
     * @author ifyn
     * @param int $circle_id 圈子ID
     * @return array 圈子详细信息数组
     */
    public static function get_circle_data($circle_id)
    {
        global $_GLOBALS;
        $user_id = get_current_user_id();

        // 检查 $_GLOBALS 中是否已缓存了圈子数据
        if (isset($_GLOBALS['islide_circle_data'][$circle_id])) {
            return $_GLOBALS['islide_circle_data'][$circle_id];
        }

        //有多少个圈子
        $circle_count = wp_count_terms('circle_cat');

        $circle = self::is_circle_exists($circle_id);

        if (is_array($circle) && isset($circle['error']))
            return $circle;
        $original_icon = get_term_meta($circle->term_id, 'islide_tax_img', true);
        $icon = islide_get_thumb(array('url' => $original_icon, 'width' => 150, 'height' => 150)) ?? '';

        $original_cover = get_term_meta($circle->term_id, 'islide_tax_cover', true);
        $cover = islide_get_thumb(array('url' => $original_cover, 'width' => 804, 'height' => 288)) ?? '';

        //圈子管理员及版主
        $admins = self::get_circle_admins($circle->term_id);

        //圈子板块（标签）
        $tags = get_term_meta($circle_id, 'islide_circle_tags', true);
        $tags = !empty($tags) && is_array($tags) ? $tags : array();

        //获取圈子分类
        $circle_cat = get_term_meta($circle_id, 'islide_circle_cat', true);

        $is_admin = user_can($user_id, 'administrator') || user_can($user_id, 'editor');

        //是否是管理员或版主
        $is_circle_staff = self::is_user_circle_staff($user_id, $circle->term_id);

        //是否需要加入圈子才能发帖
        $join_post_open = !!self::get_circle_settings($circle->term_id, 'circle_join_post_open');

        $in_circle = self::is_user_joined_circle($user_id, $circle->term_id) || $is_admin || $is_circle_staff;

        $privacy = get_term_meta($circle->term_id, 'islide_circle_privacy', true);


        if (islide_get_option('circle_half_open')) {
            $privacy = 'protected';
        }

        $recommends = get_term_meta($circle->term_id, 'islide_circle_recommends', true);
        $recommends = !empty($recommends) && is_array($recommends) ? $recommends : array();

        $stickys = get_term_meta($circle->term_id, 'islide_tax_sticky_posts', true);
        $sticky_posts = array();
        if (!empty($stickys)) {
            foreach ($stickys as $post_id) {
                $a = array(
                    "id" => $post_id,
                    "title" => get_the_title($post_id),
                );
                $sticky_posts[] = $a;
            }
        }


        $circle_data = array(
            'id' => $circle->term_id,
            'name' => esc_attr($circle->name),
            'slug' => $circle->slug,
            'desc' => esc_attr($circle->description),
            'original_icon' => get_relative_upload_path($original_icon),
            'original_cover' => get_relative_upload_path($original_cover),
            'icon' => $icon,
            'cover' => $cover,
            'link' => '/circle/' . $circle->term_id,
            'circle_count' => islide_number_format($circle_count), //圈子数量
            'circle_tags' => $tags, //圈子板块
            'circle_cat' => $circle_cat,
            'circle_badge' => self::get_circle_badge($circle->term_id),

            //是否已经加入该圈子
            'in_circle' => $in_circle,

            'join_date' =>false,
            'end_date' =>false,

            //显示权限
            'privacy' => $privacy,

            //是否是管理员或版主
            'is_circle_staff' => $is_circle_staff,

            'is_join_circle_post' => $join_post_open,

            //用户数
            'user_count' => CircleRelate::get_count(array('circle_id' => $circle_id)),

            //文章数
            'post_count' => islide_number_format($circle->count), //wp_count_posts('circle')->publish 

            //浏览量
            'views' => (int) get_term_meta($circle->term_id, 'views', true),

            'recommends' => $recommends,
            //置顶文章
            'stickys' => $sticky_posts,

            'weight' => get_term_meta($circle->term_id, 'islide_hot_weight', true),
        );

        // 获取用户的加入时间和期限信息
        if ($user_id && $in_circle) {
            $circle_relation = CircleRelate::get_data(array(
                'user_id' => $user_id,
                'circle_id' => $circle_id
            ));
            
            if (!empty($circle_relation)) {
                $circle_data['join_date'] = $circle_relation[0]['join_date'];
                $circle_data['end_date'] = $circle_relation[0]['end_date'];
            }
        }

        $statistics = self::get_circle_or_topic_statistics($circle->term_id);

        $mergedArray = array_merge($admins, array_merge($statistics, $circle_data));

        $_GLOBALS['islide_circle_data'][$circle_id] = $mergedArray;

        $views = (int) get_term_meta($circle->term_id, 'views', true);
        update_term_meta($circle->term_id, 'views', $views + 1);


        return $mergedArray;
    }

    /**
     * 获取圈子的管理配置信息（如权限、布局等）
     * @author ifyn
     * @param int $circle_id 圈子ID
     * @return array 管理配置信息数组
     */
    public static function get_manage_circle($circle_id)
    {
        $user_id = get_current_user_id();
        if (!$user_id)
            return array('error' => '请先登录');

        $circle = self::is_circle_exists($circle_id);

        if (is_array($circle) && isset($circle['error']))
            return $circle;

        $circle_staff = self::is_user_circle_staff($user_id, $circle_id);

        if ($circle_staff !== 'admin' && !user_can($user_id, 'manage_options'))
            return array('error' => '无权获取设置项');

        $join_post = get_term_meta($circle_id, 'islide_circle_join_post_open', true);
        $join_post = $join_post ?: 'global';

        $info_show = get_term_meta($circle_id, 'islide_circle_info_show', true);
        $info_show = $info_show ?: 'global';

        $editor_show = get_term_meta($circle_id, 'islide_circle_input_show', true);
        $editor_show = $editor_show ?: 'global';

        $circle_type = get_term_meta($circle_id, 'islide_circle_type', true);
        $circle_type = $circle_type ?: 'free';

        $circle_roles = get_term_meta($circle_id, 'islide_circle_roles', true);
        $circle_roles = $circle_roles ?: array();

        $pay_group = get_term_meta($circle_id, 'islide_circle_pay_group', true);
        $pay_group = $pay_group ?: array();

        $password = get_term_meta($circle_id, 'islide_circle_password', true);

        $privacy = get_term_meta($circle_id, 'islide_circle_privacy', true);
        $privacy = !$privacy || $privacy == 'public' ? true : false;

        return array(
            'privacy' => array(
                'type' => $circle_type,
                'password' => $password,
                'roles' => $circle_roles,
                'pay_group' => $pay_group,
                'privacy' => $privacy
            ),
            'role' => array(
                'join_post' => $join_post
            ),
            'layout' => array(
                'info_show' => $info_show,
                'editor_show' => $editor_show
            )
        );
    }

    /**
     * 获取指定分类下的今日评论数、全部评论数和今日发帖数
     *
     * @param int $term_id 分类ID
     * @return array 包含今日评论数、全部评论数和今日发帖数的关联数组
     */
    public static function get_circle_or_topic_statistics($term_id)
    {
        global $wpdb;

        // 检查是否已经缓存了统计数据
        if (isset($GLOBALS['taxonomy_statistics'][$term_id])) {
            return $GLOBALS['taxonomy_statistics'][$term_id];
        }
        // 获取今日评论数
        $daily_comments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(comment_ID) FROM $wpdb->comments WHERE comment_date >= CURDATE() AND comment_post_ID IN (
                SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d
            )",
            $term_id
        ));
        // 获取所有评论数
        $all_comments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(comment_ID) FROM $wpdb->comments WHERE comment_post_ID IN (
                SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d
            )",
            $term_id
        ));
        // 获取今日发帖数
        $daily_posts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = 'circle' AND post_status = 'publish' AND DATE(post_date) = CURDATE() AND ID IN (
                SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d
            )",
            $term_id
        ));
        // 缓存统计数据
        $GLOBALS['taxonomy_statistics'][$term_id] = array(
            'today_comment_count' => $daily_comments,
            'comment_count' => $all_comments,
            'today_post_count' => $daily_posts
        );
        return $GLOBALS['taxonomy_statistics'][$term_id];
    }

    /**
     * 获取圈子的徽章标签（如官方、热榜、排行等）
     * @author ifyn
     * @param int $circle_id 圈子ID
     * @return array 徽章标签数组
     */
    public static function get_circle_badge($circle_id)
    {
        $data = array();

        $official = get_term_meta($circle_id, 'islide_circle_official', true);

        if ($official) {
            $data['official'] = array(
                'icon' => 'ri-star-smile-fill',
                'name' => '官方'
            );
        }

        $hot_rank = get_term_meta($circle_id, 'islide_hot_rank', true); //热榜

        if ($hot_rank) {
            $circle_hot = islide_get_option('circle_rank_hot');
            $rank_hot = isset($circle_hot['circle_rank_hot']) ? $circle_hot['circle_rank_hot'] : array();
            $before = !empty($rank_hot['before']) ? (int) $rank_hot['before'] : 0;
            $after = !empty($rank_hot['after']) ? (int) $rank_hot['after'] : 0;

            $ones = array('一', '二', '三', '四', '五', '六', '七', '八', '九');

            if ($hot_rank < $before) {

                $data['rank'] = array(
                    'icon' => 'ri-fire-fill',
                    'name' => str_replace('${1}', $ones[$hot_rank - 1], $rank_hot['before_text'])
                );
            } else if ($hot_rank < $before + $after) {
                $data['recom'] = array(
                    'icon' => 'ri-fire-fill',
                    'name' => str_replace('${1}', $hot_rank, $rank_hot['after_text'])
                );
            }

        }

        $hot = get_term_meta($circle_id, 'islide_hot', true); //热门
        if ($hot && !$hot_rank) {
            $data['hot'] = array(
                'icon' => 'ri-fire-fill',
                'name' => '热榜'
            );
        }

        return $data;
    }


    /**
     * 获取用户创建的圈子
     * @author ifyn
     * @param int $user_id 用户ID
     * @return array 用户创建的圈子列表
     */
    public static function get_user_created_circles($user_id)
    {
        if (!is_int($user_id) || $user_id <= 0) {
            return array();
        }
        // ... existing code ...
    }

    /**
     * 获取用户加入的圈子
     * @author ifyn
     * @param int $user_id 用户ID
     * @return array 用户加入的圈子列表
     */
    public static function get_user_joined_circles($user_id)
    {
        if (!is_int($user_id) || $user_id <= 0) {
            return array();
        }
        // ... existing code ...
    }

    /**
     * 获取用户创建的话题
     * @author ifyn
     * @param int $user_id 用户ID
     * @return array 用户创建的话题列表
     */
    public static function get_user_created_topic($user_id)
    {
        if (!is_int($user_id) || $user_id <= 0) {
            return array();
        }
        // ... existing code ...
    }

    /**
     * 获取话题
     *
     * @param int $user_id 用户ID
     * @return array 返回包含创建的圈子、管理的圈子和加入的圈子的数组
     */
    public static function get_topics($data)
    {

        $paged = isset($data['paged']) ? (int) $data['paged'] : 1;
        $size = isset($data['size']) ? (int) $data['size'] : 20;

        if ($size > 20)
            return array('error' => '请求数量过多');
        if ($paged < 0)
            return array('error' => '请求格式错误');

        $offest = ($paged - 1) * $size;

        /**
         * 'name'：按术语名称排序（默认值）
            'slug'：按术语别名（slug）排序
            'term_group'：按术语分组排序
            'term_id'：按术语ID排序
            'description'：按术语描述排序
            'count'：按术语关联的对象数量排序
         * */

        // 构建参数数组
        $args = array(
            'taxonomy' => 'topic', // 自定义分类法的名称
            'orderby' => 'count', // 按照数值进行排序
            'order' => 'DESC', // 降序排列
            'number' => $size,
            'offset' => $offest,
            'hide_empty' => false,
        );

        // //创建时间
        // if($args['orderby'] === 'term_id'){
        //     $args['order'] = 'DESC';
        // }

        // //权重
        // if($args['orderby'] === 'weight'){
        //     $args['orderby'] = 'meta_value_num';
        //     $args['meta_key'] = 'islide_hot_weight';
        // }

        // 获取符合条件的分类
        $topics = get_terms($args);

        $data = array();

        if (!empty($topics)) {
            foreach ($topics as $k => $v) {
                $data[] = self::get_topic_data($v->term_id);
            }
        }

        // 获取总话题数
        $total_terms = wp_count_terms('topic');

        return array(
            'pages' => ceil($total_terms / $size), // 计算总页数
            'count' => $total_terms,
            'list' => $data

        );
    }

    /**
     * 获取单个话题的详细信息
     * @author ifyn
     * @param int $topic_id 话题ID
     * @return array 话题详细信息数组
     */
    public static function get_topic_data($topic_id)
    {
        $user_id = get_current_user_id();

        //获取圈子
        $topic = get_term_by('id', $topic_id, 'topic');

        if (!$topic) {
            return array('error' => '话题不存在！');
        }

        $icon = islide_get_thumb(array('url' => get_term_meta($topic->term_id, 'islide_tax_img', true), 'width' => 150, 'height' => 150, 'default' => false)) ?: '';
        $cover = islide_get_thumb(array('url' => get_term_meta($topic->term_id, 'islide_tax_cover', true), 'width' => 1200, 'height' => 300, 'default' => false)) ?: '/assets/image/topic-header-bg.png';

        //创建者
        $admin = get_term_meta($topic_id, 'islide_topic_admin', true);
        $admin = !empty($admin) ? (int) $admin : 1;
        $admin_data = User::get_user_public_data($admin);

        // 获取话题关注数
        $follow_count = (int) get_term_meta($topic_id, 'islide_topic_follow_count', true);

        // 检查当前用户是否关注了该话题
        $is_followed = false;
        if ($user_id) {
            $is_followed = self::is_user_followed_topic($user_id, $topic_id);
        }

        $topic_data = array(
            'id' => $topic->term_id,
            'name' => esc_attr($topic->name),
            'desc' => esc_attr($topic->description),
            'icon' => $icon,
            'cover' => $cover,
            'weight' => get_term_meta($topic->term_id, 'islide_hot_weight', true),
            //是否是创建者
            'is_topic_admin' => $admin == $user_id,

            //用户数
            'user_count' => $follow_count,

            //文章数
            'post_count' => islide_number_format($topic->count), //wp_count_posts('circle')->publish 

            //浏览量
            'views' => get_term_meta($topic->term_id, 'views', true),

            'admin' => $admin_data,

            'slug' => $topic->slug,

            // 关注相关
            'is_followed' => $is_followed
        );

        return $topic_data;
    }


    public static function get_tabbar($id)
    {
        // 先验证 id 合法性
        if (!is_numeric($id) || $id <= 0) {
            return islide_get_option('circle_home_tabbar') ?: [];
        }

        $tax = get_term($id);

        // 检查 term 是否有效
        if (is_wp_error($tax) || !$tax || empty($tax->term_id)) {
            return islide_get_option('circle_home_tabbar') ?: [];
        }

        $taxonomy = $tax->taxonomy;
        $term_id = $tax->term_id;

        if ($taxonomy === 'topic') {
            $tabbar = islide_get_option('topic_tabbar');
        } else {
            $tabbar = self::get_circle_settings($term_id, 'circle_tabbar');
        }

        return !empty($tabbar) ? $tabbar : [];
    }

    /**
     * 获取圈子或话题tabbar的默认索引
     * @author ifyn
     * @param int $id 圈子ID或话题ID
     * @return int 默认tabbar索引
     */
    public static function get_default_tabbar_index($id)
    {
        // 如果 $id 非法，直接返回默认配置
        if (!is_numeric($id) || $id <= 0) {
            return (int) islide_get_option('circle_home_tabbar_index');
        }
        

        // 获取 term 数据
        $tax = get_term($id);

        // 检查是否是 WP_Error 或 null
        if (is_wp_error($tax) || !$tax || empty($tax->term_id)) {
            return (int) islide_get_option('circle_home_tabbar_index');
        }

        $taxonomy = $tax->taxonomy;
        $term_id = $tax->term_id;

        if ($taxonomy === 'topic') {
            $index = islide_get_option('topic_tabbar_index');
        } else {
            $index = self::get_circle_settings($term_id, 'circle_tabbar_index');
        }

        return (int) $index;
    }

    /**
     * 判断是否显示左侧边栏
     * @author ifyn
     * @param int $id 圈子ID或话题ID
     * @return bool 是否显示
     */
    public static function get_show_left_sidebar($id)
    {
        $tax = get_term($id);
        $taxonomy = isset($tax->taxonomy) ? $tax->taxonomy : '';
        $term_id = isset($tax->term_id) ? $tax->term_id : 0;

        if (!$term_id) {
            $left_sidebar = islide_get_option('circle_home_left_sidebar');
        } else {
            if ($taxonomy == 'topic') {
                $left_sidebar = islide_get_option('topic_left_sidebar');
            } else {
                $left_sidebar = self::get_circle_settings($term_id, 'circle_left_sidebar');
            }
        }

        return !!$left_sidebar;
    }




    //获取帖子数据列表
    public static function get_moment_list($data)
    {

        if (isset($data['tab_type']) && $data['tab_type'] != 'all' && $data['tab_type'] != 'follow')
            return array(
                'count' => 0,
                'pages' => 0,
                'data' => []
            );

        if (isset($data['tab_type']) && $data['tab_type'] == 'follow') {
            $user_id = get_current_user_id();
            $res = CircleRelate::get_data(array(
                'user_id' => $user_id,
                'count' => 49
            ));

            $data['circle_cat'] = array_column($res, 'circle_id');

            if (empty($data['circle_cat']))
                return array(
                    'count' => 0,
                    'pages' => 0,
                    'data' => []
                );
        }

        //获取帖子数据
        $_moment_data = self::get_moments($data);

        if (isset($_moment_data['error'])) {
            return $_moment_data;
        }

        $moment_data = $_moment_data['data'];

        return array(
            'count' => $_moment_data['count'],
            'pages' => $_moment_data['pages'],
            'paged' => $_moment_data['paged'],
            'data' => $moment_data,

        );
    }

    /**
     * 处理帖子数据列表中的单条数据，格式化内容和隐藏内容
     * @author ifyn
     * @param array $value 帖子数据
     * @return array 格式化后的帖子数据
     */
    public static function get_moment_list_item($value)
    {

        $content = ShortCode::get_shortcode_content($value['content'], 'content_hide');

        $content_hide = self::get_moment_content_hide($value['id'], $content['shortcode_content']);


        $array = array(
            'content' => $content['content'],
            'content_hide' => $content_hide,
        );
        $value['content'] = $array;
        return $value;

    }


    public static function get_moment_content_hide($post_id, $content)
    {

        if (!$content)
            return '';
        $str = '';
        $user_id = get_current_user_id();
        $role = apply_filters('check_reading_hide_content_role', $post_id, $user_id);


        if (!$role || is_array($role)) {
            if ($role['allow']) {
                $role['content'] = apply_filters('the_content', $content);
            } else {
                $role['content'] = '';
            }
        }

        return $role;
    }



    //获取帖子数据
    public static function get_moment_data($moment_id, $user_id = 0)
    {
        if (get_post_type($moment_id) !== 'circle')
            return array('error' => '帖子不存在');

        //帖子作者
        $author_id = get_post_field('post_author', $moment_id);

        if (!$author_id)
            return array();

        $author_data = User::get_user_public_data($author_id);

        //获取帖子所属圈子
        $circle = get_the_terms($moment_id, 'circle_cat');
        if (!empty($circle)) {
            $circle = $circle[0];
        }

        //话题
        $moment_topics = get_the_terms($moment_id, 'topic');
        $topics = array();
        if ($moment_topics && !is_wp_error($moment_topics)) {
            foreach ($moment_topics as $topic) {
                $topic_icon = islide_get_thumb(array('url' => get_term_meta($topic->term_id, 'islide_tax_img', true), 'width' => 50, 'height' => 50, 'default' => false)) ?: '';
                $topics[] = array(
                    'id' => (int) $topic->term_id,
                    'name' => $topic->name,
                    'icon' => $topic_icon
                );
            }
        }

        //帖子附件
        $attacment = self::get_moment_attachment($moment_id);
        $title = get_the_title($moment_id);

        $content = get_post_field('post_content', $moment_id);

        if (self::get_moment_type($moment_id) == 'card') {
            $content = preg_replace("/(\n\s*){1,}/", "<br>", html_entity_decode($content));
            $content = Comment::comment_filters($content);
        }

        preg_match_all('/#([^#]+)#/', $content, $_topics);
        if (!empty($_topics[1])) {
            foreach ($_topics[1] as $topic) {
                // 检查话题是否存在
                $term = term_exists($topic, 'topic');

                if ($term !== 0 && $term !== null) {
                    // 替换话题为链接 \''.$key.'\')
                    $content = str_replace("#$topic#", '', $content);
                }
            }
        }

        $content = ShortCode::get_shortcode_content($content, 'content_hide');

        $content_hide = self::get_moment_content_hide($moment_id, $content['shortcode_content']);

        $content = array(
            'content' => apply_filters('the_content', $content['content']),
            'content_hide' => $content_hide,
        );

        //精华
        $best = get_post_meta($moment_id, 'islide_circle_best', true);

        //置顶 
        $stickys = get_term_meta((int) $circle->term_id, 'islide_tax_sticky_posts', true);
        $stickys = !empty($stickys) ? $stickys : array();

        $status = get_post_status($moment_id);

        //板块（标签）
        $tag = get_post_meta($moment_id, 'islide_circle_tag', true);

        //IpLocation
        $ip_location = get_post_meta($moment_id, 'islide_moment_ip_location', true);

        $is_self = (get_current_user_id() && get_current_user_id() == get_post_field('post_author', $moment_id));

        $excluded_terms = get_terms(array('taxonomy' => 'circle_cat', 'meta_key' => 'islide_circle_privacy', 'meta_value' => 'private', 'fields' => 'ids'));

        $user_role = self::get_circle_user_role($user_id, (int) $circle->term_id);

        $visible = true;

        if (in_array((int) $circle->term_id, $excluded_terms) && !$user_role['in_circle']) {
            $visible = false;
            $content['content'] = islide_get_desc((int) $moment_id, 50, $content['content']);
            if (isset($content['content_hide']) && !empty($content['content_hide'])) {
                $content['content_hide']['allow'] = false;
                $content['content_hide']['authority'] = 'privacy';
                $content['content_hide']['content'] = '圈子隐私内容，请先加入！';
            }
        }

        $views = (int) get_post_meta((int) $moment_id, 'views', true);
        update_post_meta((int) $moment_id, 'views', $views + 1);

        // 获取帖子类型和投票数据
        $circle_meta = get_post_meta($moment_id, 'single_circle_metabox', true);
        $circle_meta = !empty($circle_meta) && is_array($circle_meta) ? $circle_meta : array();
        $post_type = isset($circle_meta['circle_type']) ? $circle_meta['circle_type'] : 'card';

        if (empty($circle_meta)) {
            update_post_meta($moment_id, 'single_circle_metabox', array('circle_type' => 'card'));
        }

        $data = array(
            'id' => (int) $moment_id,
            'date' => islide_time_ago(get_the_date('Y-n-j G:i:s', $moment_id), 'true'),
            'title' => $title,
            'excerpt' => islide_get_desc((int) $moment_id, 100),
            'content' => $content,
            'link' => '/moment/' . $moment_id,
            'author' => $author_data,
            'attachment' => $attacment,
            'meta' => array(
                'views' => (int) get_post_meta($moment_id, 'views', true),
                'comment' => islide_number_format(get_comments_number($moment_id)),
                'like' => Post::get_post_vote($moment_id),
                'collect' => Post::get_post_favorites($moment_id),
                'share' => 0
            ),
            'circle' => array(
                'id' => (int) $circle->term_id,
                'name' => $circle->name,
                'icon' => islide_get_thumb(array('url' => get_term_meta($circle->term_id, 'islide_tax_img', true), 'width' => 150, 'height' => 150)) ?: '',
            ),
            'is_self' => $is_self,
            'visible' => $visible,
            'tag' => $tag,
            'topics' => $topics,
            'status' => $status,
            'status_name' => islide_get_post_status_name($status),
            'best' => !!$best,
            'sticky' => in_array($moment_id, $stickys) ? true : false,
            'manage_role' => apply_filters('islide_check_manage_moment_role', array('user_id' => $user_id, 'post_id' => $moment_id, 'circle_id' => (int) $circle->term_id)),
            'ip' => IpLocation::build_location($ip_location),
            'seo' => Seo::single_meta((int) $moment_id),
            'type' => $post_type
        );

        // 如果是投票类型，添加投票数据
        $vote_data = self::get_vote_data($moment_id, $user_id);
        if (!isset($vote_data['error'])) {
            $data['vote'] = $vote_data['data'];
        }

        // 如果是问题类型，添加悬赏数据
        if ($post_type === 'question' && isset($circle_meta['reward'])) {
            global $wpdb;
            $answer_count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $wpdb->comments 
                WHERE comment_post_ID = %d 
                AND comment_parent = 0 
                AND comment_approved = 1",
                $moment_id
            ));

            // 获取最近回答时间
            $last_answer_time = $wpdb->get_var($wpdb->prepare(
                "SELECT comment_date FROM $wpdb->comments 
                WHERE comment_post_ID = %d 
                AND comment_parent = 0 
                AND comment_approved = 1 
                ORDER BY comment_date DESC 
                LIMIT 1",
                $moment_id
            ));

            // 获取问题文章对象
            $question = get_post($moment_id);

            // 如果没有回答，使用问题发布时间
            $last_time = $last_answer_time ?: $question->post_date;

            $data['question'] = array(
                'reward' => $circle_meta['reward'],
                'is_adopted' => get_post_meta($moment_id, 'circle_has_adopted_answer', true) === '1',
                'answer_count' => (int) $answer_count,
                'last_time' => islide_time_ago($last_time, true)
            );
        }

        return $data;
    }

    //获取帖子类型
    public static function get_moment_type($moment_id)
    {
        $circle_meta = get_post_meta($moment_id, 'single_circle_metabox', true);
        $circle_meta = !empty($circle_meta) && is_array($circle_meta) ? $circle_meta : array();
        $post_type = isset($circle_meta['circle_type']) ? $circle_meta['circle_type'] : 'card';
        return $post_type;
    }

    /**
     * 获取帖子的附件（图片、视频等）
     * @author ifyn
     * @param int $moment_id 帖子ID
     * @return array 附件信息
     */
    public static function get_moment_attachment($moment_id)
    {
        $attachment = array(
            'image' => array(),
            'file' => array(),
            'video' => array(),
            'card' => array()
        );

        //图片
        $image = get_post_meta($moment_id, 'islide_circle_image', true);

        if (!empty($image)) {
            foreach ($image as $k => $v) {
                $img_data = wp_get_attachment_metadata($v['id']);

                if ($img_data) {
                    $full_size = wp_get_attachment_url($v['id']);

                    $thumb = islide_get_thumb(array('url' => $full_size, 'type' => 'compress', 'quality' => 70));

                    $attachment['image'][] = array(
                        'id' => $v['id'],
                        'thumb' => $thumb,
                        'url' => $full_size,
                        'full' => wp_get_attachment_image_src($v['id'], 'full'),
                    );
                }
            }
        }

        //视频
        $video = get_post_meta($moment_id, 'islide_circle_video', true);

        if (!empty($video)) {

            foreach ($video as $k => $v) {
                $thumb = '';
                $name = '';
                if (isset($v['id']) && !empty($v['id'])) {
                    $video_data = wp_get_attachment_metadata($v['id']);
                    $url = wp_get_attachment_url($v['id']);
                    $name = get_the_title($v['id']);
                    //获取附件的封面
                    $video_image = wp_get_attachment_image_src(get_post_thumbnail_id($v['id']), 'full'); // 获取附件的封面图像

                    if ($video_image) {
                        $thumb = $video_image[0]; // 封面图像的URL
                    }

                } else {
                    $url = $v['url'];
                    $thumb = $v['thumb'];
                }

                $attachment['video'][] = array(
                    'id' => $v['id'],
                    'name' => $name,
                    'post_id' => $moment_id,
                    'width' => $video_data['width'],
                    'height' => $video_data['height'],
                    'url' => $url,
                    'full' => $video_image,
                    'duration' => isset($video_data['length']) ? gmdate("i:s", $video_data['length']) : 0,
                    'filesize' => isset($video_data['filesize']) ? $video_data['filesize'] : 0,
                    'mime_type' => isset($video_data['mime_type']) ? $video_data['mime_type'] : '',
                    'poster' => $thumb
                );

            }
        }

        //卡片
        if (self::get_moment_type($moment_id) === 'card' && empty($attachment['video']) && empty($attachment['image'])) {
            $thumb = Post::get_post_thumb($moment_id);
            $thumb_id = attachment_url_to_postid($thumb);
            $attachment['image'][] = array(
                'thumb' => islide_get_thumb(array(
                    'url' => $thumb,
                    'width' => 500,
                    'height' => 200,
                    'ratio' => 2,
                )),
                'full' => wp_get_attachment_image_src($thumb_id, 'full'),
            );

        }

        //问答+投票,放到image中
        if (self::get_moment_type($moment_id) === 'question' || self::get_moment_type($moment_id) === 'vote') {
            $thumb = Post::get_post_thumb($moment_id);
            $thumb_id = attachment_url_to_postid($thumb);
            $attachment['image'][] = array(
                'thumb' => islide_get_thumb(array(
                    'url' => $thumb,
                    'width' => 500,
                    'height' => 200,
                    'ratio' => 2,
                )),
                'full' => wp_get_attachment_image_src($thumb_id, 'full'),
            );
        }

        return $attachment;
    }

    public static function get_moments($data)
    {
        $paged = isset($data['paged']) ? (int) $data['paged'] : 1;
        $size = isset($data['size']) ? (int) $data['size'] : 10;

        $offset = ($paged - 1) * (int) $size;

        $user_id = get_current_user_id();

        $circle_id = isset($data['circle_cat']) ? (int) $data['circle_cat'] : 0;

        $role = self::check_insert_moment_role($user_id, $circle_id);

        if ((isset($data['author__in']) && (int) $user_id === (int) $data['author__in'][0] && (int) $data['author__in'][0] !== 0) || (user_can($user_id, 'manage_options') && isset($data['author__in']) && (int) $data['author__in'][0])) {
            $data['post_status'] = array('publish', 'pending', 'draft');
        } elseif (!empty($role['is_circle_staff']) || !empty($role['is_admin'])) {

            $data['post_status'] = isset($data['post_status']) && !empty($data['post_status']) ? $data['post_status'] : array('publish', 'pending');
        } else {
            $data['post_status'] = array('publish');
        }

        $args = array(
            'post_type' => 'circle',
            'posts_per_page' => $size,
            'orderby' => 'date', //默认时间降序排序
            'order' => 'DESC',
            'tax_query' => array(
                'relation' => 'AND',
            ),
            'meta_query' => array(
                'relation' => 'AND',
            ),
            'offset' => $offset,
            'paged' => $paged,
            'ignore_sticky_posts' => 1,
            'post_status' => $data['post_status'],
            'suppress_filters' => false,
        );

        //排序
        if (isset($data['orderby']) && !empty($data['orderby'])) {
            switch ($data['orderby']) {
                case 'random':
                    $args['orderby'] = 'rand';
                    break;
                case 'modified':
                    $args['orderby'] = 'modified'; //修改时间
                    break;
                case 'views':
                    $args['meta_key'] = 'views';
                    $args['orderby'] = 'meta_value_num';
                    break;
                case 'like':
                    $args['meta_key'] = 'islide_post_like';
                    $args['orderby'] = 'meta_value_num';
                    break;
                case 'comments':
                    $args['orderby'] = 'comment_count';
                    break;
                case 'comment_date':
                    $args['meta_key'] = 'islide_last_comment_date';
                    $args['orderby'] = 'meta_value';
                    break;
                case 'weight':
                    $args['meta_query']['islide_hot_weight'] = array(
                        array(
                            'key' => 'islide_hot_weight'
                        )
                    );
                    $args['orderby'] = 'meta_value';
                    $args['order'] = array('islide_hot_weight' => 'DESC');
            }
        }


        //如果存在用户
        if (isset($data['author__in']) && !empty($data['author__in'])) {
            $args['author__in'] = $data['author__in'];
        }

        if (isset($data['post__in']) && !empty($data['post__in'])) {
            $args['post__in'] = $data['post__in'];
        }

        $excluded_terms = get_terms(array('taxonomy' => 'circle_cat', 'meta_key' => 'islide_circle_privacy', 'meta_value' => 'private', 'fields' => 'ids'));

        //如果是存在圈子
        if (isset($data['circle_cat']) && !empty($data['circle_cat'])) {
            if (!islide_get_option('circle_half_open')) {
                $user_role = self::get_circle_user_role($user_id, $data['circle_cat']);
                if (in_array($data['circle_cat'], $excluded_terms) && !$user_role['in_circle']) {
                    return array(
                        'error' => '圈子隐私,请先加入！'
                    );
                }
            }
            array_push($args['tax_query'], array(
                'taxonomy' => 'circle_cat',
                'field' => 'id',
                'terms' => (array) $data['circle_cat'],
                'include_children' => true,
                'operator' => 'IN'
            ));
        } else {
            //如果是首页且未打开半开放，则排除私密圈子
            if (!islide_get_option('circle_half_open')) {
                array_push($args['tax_query'], array(
                    'taxonomy' => 'circle_cat',
                    'field' => 'id',
                    'terms' => $excluded_terms,
                    'operator' => 'NOT IN'
                ));
            }
        }

        //如果是存在话题
        if (isset($data['topic']) && !empty($data['topic'])) {
            array_push($args['tax_query'], array(
                'taxonomy' => 'topic',
                'field' => 'id',
                'terms' => (array) $data['topic'],
                'include_children' => true,
                'operator' => 'IN'
            ));
        }

        //如果是视频 图片 文件筛选
        if (isset($data['file']) && !empty($data['file'])) {
            $fliter = array('image', 'video', 'file');

            if (!in_array($data['file'], $fliter)) {
                return array('error' => '参数错误');
            }

            array_push($args['meta_query'], array(
                'key' => 'islide_circle_' . $data['file'],
                'compare' => 'EXISTS'
            ));
        }

        //如果是文章类型 card question vote
        if (isset($data['type']) && !empty($data['type'])) {
            $type = $data['type'];
            $args['meta_query'] = array(
                array(
                    'key' => 'single_circle_metabox',
                    'value' => '"circle_type";s:' . strlen($type) . ':"' . $type . '"',
                    'compare' => 'LIKE'
                )
            );
        }

        //如果是文章权限筛选
        if (isset($data['role']) && !empty($data['role'])) {
            $fliter = array('none', 'login', 'comment', 'money', 'credit', 'roles', 'fans', 'password');
            if (!in_array($data['role'], $fliter)) {
                return array('error' => '参数错误');
            }
            array_push($args['meta_query'], array(
                'key' => 'islide_post_content_hide_role',
                'value' => $data['role'],
                'compare' => '='
            ));
        }

        //如果是板块（标签）筛选
        if (isset($data['tag']) && !empty($data['tag'])) {
            array_push($args['meta_query'], array(
                'key' => 'islide_circle_tag',
                'value' => $data['tag'],
                'compare' => '='
            ));
        }

        //精华帖子筛选
        if (isset($data['best']) && !empty($data['best'])) {
            array_push($args['meta_query'], array(
                'key' => 'islide_circle_best',
                'value' => 1,
                'compare' => '='
            ));
        }

        //搜索
        if (isset($data['search']) && !empty($data['search'])) {
            $args['search_tax_query'] = true;
            $args['s'] = esc_attr($data['search']);
        }

        $the_query = new \WP_Query($args);

        $arr = array();
        $_pages = 0;
        $_count = 0;
        if ($the_query->have_posts()) {

            $_pages = $the_query->max_num_pages;
            $_count = $the_query->found_posts;

            while ($the_query->have_posts()) {

                $the_query->the_post();

                $moment_data = self::get_moment_data($the_query->post->ID, $user_id);
                if (!isset($moment_data['error'])) {
                    $arr[] = $moment_data;
                }
            }
            wp_reset_postdata();
        }

        return array(
            'count' => $_count,
            'pages' => $_pages,
            'paged' => $paged,
            'data' => $arr
        );
    }

    //根据文章id获取圈子id
    public static function get_circle_id_by_post_id($post_id)
    {

        $circle_id = 0;

        $circle = get_the_terms($post_id, 'circle_cat');
        if (!empty($circle)) {
            $circle_id = (int) $circle[0]->term_id;
        }

        return $circle_id;
    }

    /**
     * 获取编辑帖子时所需的数据
     * @author ifyn
     * @param int $moment_id 帖子ID
     * @return array 编辑所需数据
     */
    public static function get_edit_moment_data($moment_id)
    {
        $moment_id = (int) $moment_id;
        if (get_post_type($moment_id) !== 'circle')
            return array('error' => '帖子不存在');

        $user_id = get_current_user_id();

        //获取帖子所属圈子
        $circle_id = self::get_circle_id_by_post_id($moment_id);

        $manage_role = apply_filters('islide_check_manage_moment_role', array('user_id' => $user_id, 'circle_id' => $circle_id, 'post_id' => $moment_id));

        if (!$manage_role['can_edit'])
            return array('error' => '您无权限修改');

        //帖子附件
        $attacment = self::get_moment_attachment($moment_id);
        $images = array();
        foreach ($attacment['image'] as $image) {
            $images[] = array(
                'id' => $image['id'],
                'url' => $image['url'],
            );
        }

        $videos = array();
        foreach ($attacment['video'] as $video) {
            $videos[] = array(
                'id' => $video['id'],
                'url' => $video['url'],
                'name' => $video['name'],
                'thumbList' => array(
                    array('url' => $video['poster'])
                ),
                'progress' => 100,
                'success' => true,
                'size' => $video['filesize'],
            );
        }

        $title = html_entity_decode(get_the_title($moment_id));

        $content = html_entity_decode(get_post_field('post_content', $moment_id));
        $shortcode_content = ShortCode::get_shortcode_content($content, 'content_hide');
        $content = !empty($shortcode_content['content']) ? $shortcode_content['content'] : $content;
        $content_hide = !empty($shortcode_content['shortcode_content']) ? $shortcode_content['shortcode_content'] : '';

        $role_type = get_post_meta($moment_id, 'islide_post_content_hide_role', true);
        $role_value = '';
        if ($role_type == 'password') {
            $role_value = get_post_meta($moment_id, 'islide_post_password', true);
        } else {
            $role_value = get_post_meta($moment_id, 'islide_post_price', true);
        }

        $roles = get_post_meta($moment_id, 'islide_post_roles', true);
        $roles = !empty($roles) ? $roles : array();

        //板块（标签）
        $tag = get_post_meta($moment_id, 'islide_circle_tag', true);

        return array(
            'id' => (int) $moment_id,
            'title' => $title,
            'content' => $content,
            'circle_id' => $circle_id,
            'tag' => $tag,
            'privacy' => array(
                'type' => $role_type ? $role_type : 'none',
                'value' => $role_value,
                'roles' => $roles,
                'content' => $content_hide
            ),
            'type' => '',
            'image' => $images,
            'video' => $videos,
        );
    }

    /**
     * 设置帖子为精华/取消精华
     * @author ifyn
     * @param int $moment_id 帖子ID
     * @return array 操作结果
     */
    public static function set_moment_best($moment_id)
    {
        $user_id = get_current_user_id();

        if (!$user_id)
            return array('error' => '请先登录');

        //圈子
        $circle_id = self::get_circle_id_by_post_id($moment_id);

        $manage_role = apply_filters('islide_check_manage_moment_role', array('user_id' => $user_id, 'circle_id' => $circle_id));

        if (!$manage_role['can_best'])
            return array('error' => '您无权限修改');

        $best = get_post_meta($moment_id, 'islide_circle_best', true);

        $type = true;

        if ($best) {
            delete_post_meta($moment_id, 'islide_circle_best');
            $type = false;
        } else {
            update_post_meta($moment_id, 'islide_circle_best', 1);
        }

        return array(
            'message' => $type ? '成功加精' : '取消加精',
            'type' => $type
        );
    }

    /**
     * 设置帖子为置顶/取消置顶
     * @author ifyn
     * @param int $moment_id 帖子ID
     * @return array 操作结果
     */
    public static function set_moment_sticky($moment_id)
    {
        $user_id = get_current_user_id();

        if (!$user_id)
            return array('error' => '请先登录');

        //圈子
        $circle_id = self::get_circle_id_by_post_id($moment_id);
        $manage_role = apply_filters('islide_check_manage_moment_role', array('user_id' => $user_id, 'circle_id' => $circle_id));
        if (!$manage_role['can_sticky'])
            return array('error' => '您无权限修改');

        $type = true;

        if ($circle_id) {
            $stickys = get_term_meta($circle_id, 'islide_tax_sticky_posts', true);
            $stickys = is_array($stickys) ? $stickys : array();
            if (in_array($moment_id, $stickys)) {
                $stickys = array_diff($stickys, array($moment_id));
                update_term_meta($circle_id, 'islide_tax_sticky_posts', $stickys);
                $type = false;
            } else {
                $stickys[] = $moment_id;
                update_term_meta($circle_id, 'islide_tax_sticky_posts', $stickys);
            }
        }

        return array(
            'message' => $type ? '成功置顶' : '取消置顶',
            'type' => $type
        );
    }

    /**
     * 检查用户是否有删除帖子权限
     * @author ifyn
     * @param int $user_id 用户ID
     * @param int $post_id 帖子ID
     * @return mixed 权限信息或剩余可操作时间
     */
    public static function check_user_can_delete($user_id, $post_id)
    {

        $author = (int) get_post_field('post_author', $post_id);

        if (user_can($user_id, 'manage_options')) {
            return 'admin';
        }

        if ($author !== (int) $user_id)
            return array('error' => '没有权限');

        $status = get_post_status($post_id);

        if ($status == 'pending')
            return 'pending';

        if ($status == 'draft')
            return 'draft';

        $post_date = get_the_time('Y-n-j G:i:s', $post_id);

        $m = round((wp_strtotime(current_time('mysql')) - wp_strtotime($post_date)) / 60);

        if (get_post_type($post_id) === 'circle') {
            $edit_time = 30;//话题发布之后多长时间内允许删除或编辑
        } else {
            $edit_time = 30;
        }

        if ($m >= $edit_time) {
            return array('error' => sprintf('已过期，无法删除，请联系管理员'));
        }

        return $edit_time - $m;
    }

    /**
     * 删除帖子
     * @author ifyn
     * @param int $moment_id 帖子ID
     * @return array 操作结果
     */
    public static function delete_moment($moment_id)
    {
        $user_id = get_current_user_id();

        if (!$user_id)
            return array('error' => '请先登录');

        $circle_id = self::get_circle_id_by_post_id($moment_id);

        $manage_role = apply_filters('islide_check_manage_moment_role', array('user_id' => $user_id, 'circle_id' => $circle_id, 'post_id' => $moment_id));

        if (!$manage_role['can_delete'])
            return array('error' => '您无权限删除');

        if ($manage_role['is_self']) {
            $can_delete = self::check_user_can_delete($user_id, $moment_id);
            if (isset($can_delete['error']))
                return $can_delete;
        }

        $type = wp_trash_post($moment_id, true) ? true : false;

        return array(
            'message' => $type ? '删除成功' : '删除失败',
            'type' => $type
        );
    }

    //帖子审核
    public static function change_moment_status($post_id)
    {
        $user_id = get_current_user_id();

        if (!$user_id)
            return array('error' => '请先登录');

        wp_set_current_user($user_id);

        $circle_id = self::get_circle_id_by_post_id($post_id);

        $manage_role = apply_filters('islide_check_manage_moment_role', array('user_id' => $user_id, 'circle_id' => $circle_id));
        if (!$manage_role['can_public'])
            return array('error' => '您无权限修改');
        // if(get_post_status($post_id) === 'pending'){
        //     $data['status'] = 'publish';
        //     apply_filters( 'insert_ask_action', $data);
        // }

        return wp_update_post(array('ID' => $post_id, 'post_status' => 'publish'));
    }

    /**
     * 移除圈子用户
     * @author ifyn
     * @param int $user_id 用户ID
     * @param int $circle_id 圈子ID
     * @return array 操作结果
     */
    public static function remove_circle_user($user_id, $circle_id)
    {
        $current_user_id = get_current_user_id();
        if (!$current_user_id)
            return array('error' => '请先登录');
        $user = get_user_by('id', $user_id);
        if (empty($user))
            return array('error' => '此用户不存在');
        //检查圈子是否存在
        $circle = self::is_circle_exists($circle_id);
        if (is_array($circle) && isset($circle['error']))
            return $circle;
        $role = self::check_insert_moment_role($current_user_id, $circle_id);
        if (empty($role['is_circle_staff']) && empty($role['is_admin']))
            return array('error' => '您没有权限移除此用户');
        //是否是管理员或版主
        $is_circle_staff = self::is_user_circle_staff($user_id, $circle_id);
        if ($is_circle_staff) {
            if ((!empty($role['is_circle_admin']) || !empty($role['is_admin'])) && $is_circle_staff == 'staff') {
                //版主及工作人员
                $staff = get_term_meta($circle_id, 'islide_circle_staff', true);
                $staff = !empty($staff) && is_array($staff) ? $staff : array();
                $key_staff = array_search($user_id, $staff);
                if ($key_staff !== false) {
                    unset($staff[$key_staff]);
                    update_term_meta($circle_id, 'islide_circle_staff', array_values($staff));
                }
                CircleRelate::update_data(array(
                    'user_id' => $user_id,
                    'circle_id' => $circle_id,
                    'circle_role' => 'member',
                    'join_date' => current_time('mysql')
                ));
                //钩子
                do_action('islide_remove_circle_staff_action', array(
                    'from' => $current_user_id,
                    'to' => $user_id,
                    'circle_id' => $circle_id
                ));
                return array('msg' => '移除成功！');
            } else {
                return array('error' => '您没有权限移除此用户');
            }
        } else {
            CircleRelate::delete_data(array('circle_id' => $circle_id, 'user_id' => $user_id));
        }
        return array('msg' => '移除成功！');
    }


    //设置版主
    public static function set_user_circle_staff($user_id, $circle_id)
    {
        if (!is_int($user_id) || $user_id <= 0 || !is_int($circle_id) || $circle_id <= 0) {
            return array('error' => '参数错误');
        }

        try {
            // 参数验证
            $user_id = absint($user_id);
            $circle_id = absint($circle_id);
            $current_user_id = get_current_user_id();

            // 检查用户登录状态
            if (!$current_user_id) {
                return array('error' => '请先登录');
            }

            // 检查目标用户是否存在
            $user = get_user_by('id', $user_id);
            if (empty($user) || !is_object($user)) {
                return array('error' => '此用户不存在');
            }

            // 检查圈子是否存在
            $circle = self::is_circle_exists($circle_id);
            if (is_array($circle) && isset($circle['error'])) {
                return $circle;
            }

            // 检查当前用户权限
            $role = self::check_insert_moment_role($current_user_id, $circle_id);
            if (empty($role['is_circle_admin']) && empty($role['is_admin'])) {
                return array('error' => '您没有权限设置版主');
            }

            // 检查用户是否已经是版主
            $is_circle_staff = self::is_user_circle_staff($user_id, $circle_id);
            if ($is_circle_staff) {
                return array('error' => '此用户已是版主，无需设置');
            }

            // 获取并更新版主列表
            $staff = get_term_meta($circle_id, 'islide_circle_staff', true);
            $staff = !empty($staff) && is_array($staff) ? $staff : array();

            // 检查用户是否已在版主列表中
            $key_staff = array_search($user_id, $staff);
            if ($key_staff === false) {
                $staff[] = $user_id;
                $update_result = update_term_meta($circle_id, 'islide_circle_staff', array_values($staff));
                if (!$update_result) {
                    return array('error' => '更新版主列表失败');
                }
            }

            // 更新用户角色关系
            $update_result = CircleRelate::update_data(array(
                'user_id' => $user_id,
                'circle_id' => $circle_id,
                'circle_role' => 'staff',
                'join_date' => current_time('mysql')
            ));

            if ($update_result) {
                // 触发设置版主动作钩子
                do_action('islide_set_circle_staff_action', array(
                    'from' => $current_user_id,
                    'to' => $user_id,
                    'circle_id' => $circle_id
                ));

                return array('msg' => '设置版主成功！');
            }

            return array('error' => '设置版主失败！');
        } catch (\Exception $e) {
            return array('error' => '设置版主时发生错误：' . $e->getMessage());
        }
    }

    /**
     * 邀请用户加入圈子
     * @author ifyn
     * @param int $user_id 被邀请的用户ID
     * @param int $circle_id 圈子ID
     * @return array 成功返回带msg字段的数组，失败返回带error字段的数组
     */
    public static function invite_user_join_circle($user_id, $circle_id)
    {
        if (!is_int($user_id) || $user_id <= 0 || !is_int($circle_id) || $circle_id <= 0) {
            return array('error' => '参数错误');
        }

        try {
            // 参数验证
            $user_id = absint($user_id);
            $circle_id = absint($circle_id);
            $current_user_id = get_current_user_id();

            // 检查用户登录状态
            if (!$current_user_id) {
                return array('error' => '请先登录');
            }

            // 检查圈子是否存在
            $circle = self::is_circle_exists($circle_id);
            if (is_array($circle) && isset($circle['error'])) {
                return $circle;
            }

            // 检查目标用户是否存在
            $user = get_user_by('id', $user_id);
            if (empty($user) || !is_object($user)) {
                return array('error' => '此用户不存在');
            }

            // 检查用户是否已经加入圈子
            if (self::is_user_joined_circle($user_id, $circle_id)) {
                return array('error' => '此用户已经加入圈子');
            }

            // 检查当前用户权限
            $role = self::check_insert_moment_role($current_user_id, $circle_id);
            if (empty($role['is_circle_admin']) && empty($role['is_admin'])) {
                return array('error' => '您没有权限邀请');
            }

            // 更新用户角色关系
            $update_result = CircleRelate::update_data(array(
                'user_id' => $user_id,
                'circle_id' => $circle_id,
                'circle_role' => 'member',
                'join_date' => current_time('mysql')
            ));

            if ($update_result) {
                // 更新用户元数据
                $user_circle_meta = get_user_meta($user_id, 'islide_joined_circles', true);
                $user_circle_meta = is_array($user_circle_meta) ? $user_circle_meta : [];
                $user_circle_meta[$circle_id] = array(
                    'circle_id' => $circle_id,
                    'end_date' => '0000-00-00 00:00:00', // 永久有效
                );
                update_user_meta($user_id, 'islide_joined_circles', $user_circle_meta);

                // 触发邀请加入圈子动作钩子
                do_action('islide_invite_join_circle_action', array(
                    'from' => $current_user_id,
                    'to' => $user_id,
                    'circle_id' => $circle_id
                ));

                return array('msg' => '邀请成功！');
            }

            return array('error' => '邀请失败！');
        } catch (\Exception $e) {
            return array('error' => '邀请用户时发生错误：' . $e->getMessage());
        }
    }


    /**
     * 检查用户是否具有特定权限
     * @author ifyn
     * @param array $data 权限列表数组
     * @param array $lv 用户等级信息
     * @param array $vip 用户VIP信息
     * @param string $circle_admin 是否为圈子管理员
     * @param string $circle_staff 是否为圈子员工
     * @return bool 用户是否有权限
     */
    public static function check_insert_role($data, $lv, $vip, $circle_admin = '', $circle_staff = '')
    {
        if (empty($data) || !is_array($data)) {
            return false;
        }

        try {
            // 构造要检查的值
            $user_level = isset($lv['lv']) ? 'lv' . $lv['lv'] : 'lv-1';
            $user_vip = isset($vip['lv']) ? $vip['lv'] : 'vip-1';
            $values_to_check = [$user_level, $user_vip, $circle_admin, $circle_staff];

            // 判断是否存在交集
            return self::checkIfArrayContains($data, $values_to_check);
        } catch (\Exception $e) {
            // 出现异常则默认没有权限
            return false;
        }
    }

    /**
     * 检查两个数组是否有交集
     * @author ifyn
     * @param array $array1 第一个数组
     * @param array $array2 第二个数组
     * @return bool 是否有交集
     */
    private static function checkIfArrayContains($array1, $array2)
    {
        if (!is_array($array1) || !is_array($array2)) {
            return false;
        }

        try {
            // 移除 $array2 中的空值，防止错误匹配
            $filteredArray2 = array_filter($array2, function ($value) {
                return !empty($value);
            });

            // 如果 $filteredArray2 为空，直接返回 false
            if (empty($filteredArray2)) {
                return false;
            }

            // 获取交集
            $intersection = array_intersect($array1, $filteredArray2);

            // 只要交集不为空，说明有匹配项
            return !empty($intersection);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取用户在圈子中的角色信息
     * @author ifyn
     * @param int $user_id 用户ID
     * @param int $circle_id 圈子ID
     * @return array 用户角色信息数组，包含in_circle、is_circle_admin、is_circle_staff和is_admin字段
     */
    public static function get_circle_user_role($user_id, $circle_id)
    {
        if (!is_int($user_id) || $user_id <= 0 || !is_int($circle_id) || $circle_id <= 0) {
            return [
                'in_circle' => false,
                'is_circle_admin' => false,
                'is_circle_staff' => false,
                'is_admin' => false,
            ];
        }

        try {
            $is_circle_staff = self::is_user_circle_staff($user_id, $circle_id);
            $is_admin = user_can($user_id, 'administrator') || user_can($user_id, 'editor');
            $in_circle = self::is_user_joined_circle($user_id, $circle_id) || $is_admin || $is_circle_staff;

            return [
                'in_circle' => $in_circle,         // 是否在圈子中
                'is_circle_admin' => self::is_circle_admin($user_id, $circle_id), // 是否为圈子管理员
                'is_circle_staff' => self::is_circle_staff($user_id, $circle_id), // 是否为圈子员工
                'is_admin' => $is_admin,           // 是否为网站管理员
            ];
        } catch (\Exception $e) {
            // 异常情况返回默认值
            return [
                'in_circle' => false,
                'is_circle_admin' => false,
                'is_circle_staff' => false,
                'is_admin' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 生成用户在圈子中的权限数据
     * @author ifyn
     * @param int $user_id 用户ID
     * @param int $circle_id 圈子ID
     * @return array 用户权限数据数组
     */
    public static function generate_role_data($user_id, $circle_id)
    {
        if (!is_int($user_id)) {
            return [
                'can_create_circle' => false,
                'can_create_topic' => false,
                'can_create_moment' => false,
                'can_moment_public' => false,
                'type_role' => [],
                'privacy_role' => [],
                'media_role' => [],
                'media_count' => [],
                'is_admin' => false,
                'is_circle_admin' => false,
                'is_circle_staff' => false,
                'is_join_circle_post' => false,
                'in_circle' => false
            ];
        }

        try {
            // 获取用户等级和 VIP 信息
            $lv = User::get_user_lv($user_id);
            $vip = User::get_user_vip($user_id);

            // 获取各种配置选项
            $create_circle_list = islide_get_option('create_circle') ?: [];
            $create_topic_list = islide_get_option('create_topic') ?: [];
            $create_moment_list = islide_get_option('circle_moment') ?: [];
            $is_join_circle_post = islide_get_option('circle_join_post_open') ?: 0;
            $publish_role = islide_get_option('circle_post_open') ?: 0;
            $type_role_list = islide_get_option('circle_moment_type_role') ?: [];
            $privacy_role_list = islide_get_option('circle_moment_role') ?: [];
            $media_role_list = islide_get_option('media_upload_role') ?: [];
            $media_count_list = islide_get_option('circle_post') ?: [
                'min_word_limit' => 10,
                'max_word_limit' => 10000,
                'image_count' => 9,
                'video_count' => 1,
                'file_count' => 3
            ];

            // 获取用户在圈子中的角色信息
            $user_role = self::get_circle_user_role($user_id, $circle_id);

            // 提取圈子角色
            $circle_admin = $user_role['is_circle_admin'] ? "admin" : "";
            $circle_staff = $user_role['is_circle_staff'] ? "staff" : "";

            // 公共检查函数
            $check_role = function ($list, $key = 'null') use ($lv, $vip, $circle_admin, $circle_staff) {
                if (isset($list[$key])) {
                    return self::check_insert_role($list[$key], $lv, $vip, $circle_admin, $circle_staff);
                }
                return self::check_insert_role($list, $lv, $vip, $circle_admin, $circle_staff);
            };

            return [
                // 创建权限
                'can_create_circle' => $check_role($create_circle_list),
                'can_create_topic' => $check_role($create_topic_list),
                'can_create_moment' => $check_role($create_moment_list, 'insert'),
                'can_moment_public' => $check_role($create_moment_list, 'insert_public'),

                // 动态类型角色
                'type_role' => [
                    'vote' => $check_role($type_role_list, 'vote'),
                    'ask' => $check_role($type_role_list, 'ask'),
                    'card' => $check_role($type_role_list, 'card'),
                ],

                // 隐私角色
                'privacy_role' => array_merge(
                    [
                        'none' => true,
                        'login' => $check_role($privacy_role_list, 'login'),
                        'money' => $check_role($privacy_role_list, 'money'),
                        'credit' => $check_role($privacy_role_list, 'credit'),
                        'comment' => $check_role($privacy_role_list, 'comment'),
                        'password' => $check_role($privacy_role_list, 'password'),
                        'fans' => $check_role($privacy_role_list, 'fans'),
                        'roles' => $check_role($privacy_role_list, 'roles'),
                    ],
                    ['publish' => ($publish_role == 1)]
                ),

                // 媒体角色
                'media_role' => [
                    'image' => $check_role($media_role_list, 'image'),
                    'video' => $check_role($media_role_list, 'video'),
                    'file' => $check_role($media_role_list, 'file'),
                ],

                // 媒体计数
                'media_count' => [
                    'min_word_limit' => isset($media_count_list['min_word_limit']) ? (int) $media_count_list['min_word_limit'] : 10,
                    'max_word_limit' => isset($media_count_list['max_word_limit']) ? (int) $media_count_list['max_word_limit'] : 10000,
                    'image_count' => isset($media_count_list['image_count']) ? (int) $media_count_list['image_count'] : 9,
                    'video_count' => isset($media_count_list['video_count']) ? (int) $media_count_list['video_count'] : 1,
                    'file_count' => isset($media_count_list['file_count']) ? (int) $media_count_list['file_count'] : 3,
                ],

                // 用户和圈子状态
                'is_admin' => $user_role['is_admin'],
                'is_circle_admin' => $user_role['is_circle_admin'],
                'is_circle_staff' => $user_role['is_circle_staff'],
                'is_join_circle_post' => ($is_join_circle_post == 1),
                'in_circle' => $user_role['in_circle'],
            ];
        } catch (\Exception $e) {
            return [
                'error' => '获取角色数据失败: ' . $e->getMessage(),
                'can_create_circle' => false,
                'can_create_topic' => false,
                'can_create_moment' => false,
                'can_moment_public' => false,
                'type_role' => [],
                'privacy_role' => [],
                'media_role' => [],
                'media_count' => [],
                'is_admin' => false,
                'is_circle_admin' => false,
                'is_circle_staff' => false,
                'is_join_circle_post' => false,
                'in_circle' => false
            ];
        }
    }



    /**
     * 计算圈子热度权重
     * @author ifyn
     * @param int $circle_id 圈子ID
     * @return array|int 计算成功返回包含权重信息的数组，失败返回0
     */
    public static function calculate_circle_weight($circle_id)
    {
        if (!is_int($circle_id) || $circle_id <= 0) {
            return 0;
        }

        try {
            // 获取圈子元数据
            $circle = self::is_circle_exists($circle_id);
            if (!$circle || is_array($circle) && isset($circle['error'])) {
                return 0; // 如果圈子不存在，权重为 0
            }

            // 获取统计数据
            $statistics = self::get_circle_or_topic_statistics($circle_id);

            // 数据来源
            $user_count = CircleRelate::get_count(array('circle_id' => $circle_id)) ?: 0; // 用户数
            $post_count = (int) $circle->count ?: 0; // 帖子数
            $views = (int) get_term_meta($circle_id, 'views', true) ?: 0; // 浏览量
            $today_comment_count = $statistics['today_comment_count'] ?? 0; // 今日评论数
            $today_post_count = $statistics['today_post_count'] ?? 0; // 今日发帖数
            $comment_count = $statistics['comment_count'] ?? 0; // 总评论数

            // 计算新的权重
            $new_weight =
                ($user_count * 2) +        // 用户数权重
                ($post_count * 3) +       // 帖子数权重
                ($views * 1) +            // 浏览量权重
                ($today_comment_count * 5) + // 今日评论权重
                ($today_post_count * 4) + // 今日发帖权重
                ($comment_count * 2);     // 总评论数权重

            // 更新权重到元数据
            update_term_meta($circle_id, 'islide_hot_weight', $new_weight);

            $arg = array(
                'user_count' => $user_count,
                'post_count' => $post_count,
                'views' => $views,
                'statistics' => $statistics,
                'weight' => $new_weight,
            );

            return $arg;
        } catch (\Exception $e) {
            // 出现异常时返回0
            return 0;
        }
    }




    public static function calculate_topic_weight($topic_id)
    {
        if (!is_int($topic_id) || $topic_id <= 0) {
            return 0;
        }

        try {
            // 获取话题元数据
            $topic = self::get_topic_data($topic_id);
            if (!$topic || (is_array($topic) && isset($topic['error']))) {
                return 0; // 如果话题不存在或数据无效，权重为 0
            }

            // 获取统计数据
            $statistics = self::get_circle_or_topic_statistics($topic_id);

            // 数据来源
            $post_count = is_object($topic) ? intval($topic->post_count ?? 0) : (is_array($topic) ? intval($topic['post_count'] ?? 0) : 0); // 帖子数
            $views = is_object($topic) ? intval($topic->views ?? 0) : (is_array($topic) ? intval($topic['views'] ?? 0) : 0); // 浏览量
            $today_comment_count = intval($statistics['today_comment_count'] ?? 0); // 今日评论数
            $today_post_count = intval($statistics['today_post_count'] ?? 0); // 今日发帖数
            $comment_count = intval($statistics['comment_count'] ?? 0); // 总评论数

            // 计算新的权重
            $weight =
                ($post_count * 3) +       // 帖子数权重
                ($views * 1) +           // 浏览量权重
                ($today_comment_count * 5) + // 今日评论权重
                ($today_post_count * 4) + // 今日发帖权重
                ($comment_count * 2);    // 总评论数权重

            // 更新权重到元数据
            update_term_meta($topic_id, 'islide_hot_weight', $weight);

            return $weight;
        } catch (\Exception $e) {
            // 出现异常时返回0
            return 0;
        }
    }


    public static function get_hot_topic_data($limit = 6)
    {
        try {
            $limit = is_int($limit) && $limit > 0 ? $limit : 6;

            $query_args = array(
                'taxonomy' => 'topic',
                'orderby' => 'meta_value_num',
                'meta_key' => 'islide_hot_weight',
                'order' => 'DESC',
                'number' => $limit,
                'hide_empty' => false,
            );

            $topics = get_terms($query_args);
            $array = array();

            if (!empty($topics) && !is_wp_error($topics)) {
                foreach ($topics as $topic) {
                    $topic_data = self::get_topic_data($topic->term_id);
                    if ($topic_data && !isset($topic_data['error'])) {
                        $array[] = $topic_data;
                    }
                }
            }

            return $array;
        } catch (\Exception $e) {
            return array();
        }
    }


    /**
     * 获取热门圈子数据
     * @author ifyn
     * @param int $limit 获取的圈子数量，默认为6
     * @return array 热门圈子数据列表
     */
    public static function get_hot_circle_data($limit = 6)
    {
        try {
            $limit = is_int($limit) && $limit > 0 ? $limit : 6;

            $query_args = array(
                'taxonomy' => 'circle_cat',
                'orderby' => 'meta_value_num',
                'meta_key' => 'islide_hot_weight',
                'order' => 'DESC',
                'number' => $limit,
                'hide_empty' => false,
            );

            $circles = get_terms($query_args);
            $array = array();

            if (!empty($circles) && !is_wp_error($circles)) {
                foreach ($circles as $circle) {
                    $circle_data = self::get_circle_data($circle->term_id);
                    if ($circle_data && !isset($circle_data['error'])) {
                        $array[] = $circle_data;
                    }
                }
            }

            return $array;
        } catch (\Exception $e) {
            return array();
        }
    }

    /**
     * 检查用户对圈子的访问权限
     * @author ifyn
     * @param int $user_id 用户ID
     * @param int $circle_id 圈子ID
     * @return bool 用户是否有访问权限
     */
    public static function check_user_circle_access($user_id, $circle_id)
    {
        if (!is_int($user_id) || $user_id <= 0 || !is_int($circle_id) || $circle_id <= 0) {
            return false;
        }

        try {
            // 检查用户是否为管理员
            if (user_can($user_id, 'administrator') || user_can($user_id, 'editor')) {
                return true;
            }

            // 检查用户是否为圈子管理员或版主
            if (self::is_user_circle_staff($user_id, $circle_id)) {
                return true;
            }

            // 获取用户的圈子元数据
            $meta = get_user_meta($user_id, 'islide_joined_circles', true);
            $meta = is_array($meta) ? $meta : [];

            // 用户是否有该圈子记录
            if (!isset($meta[$circle_id])) {
                return false;
            }

            $end_date = $meta[$circle_id]['end_date'] ?? '';

            // 永久有效（0000-00-00 00:00:00）
            if ($end_date === '0000-00-00 00:00:00') {
                return true;
            }

            $end_time = strtotime($end_date);
            if (!$end_time) {
                return false; // 无效的日期格式
            }

            $now = time();

            if ($end_time > $now) {
                // 未过期
                return true;
            } else {
                // 已过期，清理 user_meta 和数据库记录
                unset($meta[$circle_id]);
                update_user_meta($user_id, 'islide_joined_circles', $meta);

                global $wpdb;
                if (isset($wpdb) && is_object($wpdb)) {
                    $table_name = $wpdb->prefix . 'islide_circle_related';
                    $wpdb->delete($table_name, [
                        'user_id' => $user_id,
                        'circle_id' => $circle_id
                    ]);
                }

                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 检查投票数据
     * @author ifyn
     * @param array $data 投票数据
     * @return array 检查结果
     */
    public static function check_vote_data($data)
    {
        if (!isset($data['vote_title']) || empty($data['vote_title'])) {
            return array(
                'error' => '投票标题不能为空'
            );
        }

        if (!isset($data['vote_options']) || !is_array($data['vote_options']) || count($data['vote_options']) < 2) {
            return array(
                'error' => '投票选项至少需要2个'
            );
        }

        // 验证时间格式
        if (!isset($data['vote_end_time']) || empty($data['vote_end_time'])) {
            return array(
                'error' => '投票截止时间不能为空'
            );
        }

        // 验证时间格式是否为 YYYY-MM-DD HH:mm:ss
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['vote_end_time'])) {
            return array(
                'error' => '投票截止时间格式不正确，请使用 YYYY-MM-DD HH:mm:ss 格式'
            );
        }

        $timestamp = strtotime($data['vote_end_time']);
        if ($timestamp === false) {
            return array(
                'error' => '投票截止时间格式不正确'
            );
        }

        // 验证时间是否已过期
        if ($timestamp <= current_time('timestamp')) {
            return array(
                'error' => '投票截止时间不能早于当前时间'
            );
        }

        return array(
            'data' => array(
                'vote_title' => $data['vote_title'],
                'vote_options' => $data['vote_options'],
                'vote_end_time' => $data['vote_end_time']
            )
        );
    }

    /**
     * 提交投票
     * @author ifyn
     * @param array $request 请求数据
     * @return array 处理结果
     */
    public static function submit_moment_vote($request)
    {
        $post_id = isset($request['post_id']) ? intval($request['post_id']) : 0;
        if (!$post_id) {
            return array(
                'error' => 'post_id不能为空'
            );
        }
        $option_index = isset($request['option_index']) ? intval($request['option_index']) : 0;
        $user_id = get_current_user_id();
        if (!$user_id) {
            return array('error' => '请先登录');
        }

        // 获取投票数据
        $circle_meta = get_post_meta($post_id, 'single_circle_metabox', true);
       
        // 检查是否已经投票
        $voted_users = get_post_meta($post_id, 'circle_voted_users', true);
        $voted_users = !empty($voted_users) && is_array($voted_users) ? $voted_users : array();
        if (in_array($user_id, $voted_users)) {
            return array(
                'error' => '您已经参与过投票'
            );
        }

        // 检查选项索引是否有效
        if (!isset($circle_meta['vote_options'][$option_index])) {
            return array(
                'error' => '无效的投票选项'
            );
        }

        // 更新投票数据
        $circle_meta['vote_options'][$option_index]['votes']++;
        update_post_meta($post_id, 'single_circle_metabox', $circle_meta);

        // 记录已投票用户
        $voted_users[] = $user_id;
        update_post_meta($post_id, 'circle_voted_users', $voted_users);

        // 保存用户投票的选项索引
        update_post_meta($post_id, 'circle_user_vote_' . $user_id, $option_index);

        // 计算总票数
        $total_votes = 0;
        foreach ($circle_meta['vote_options'] as $option) {
            $total_votes += isset($option['votes']) ? $option['votes'] : 0;
        }

        // 计算每个选项的百分比
        $options = array_map(function ($option, $index) use ($total_votes, $option_index) {
            $votes = isset($option['votes']) ? $option['votes'] : 0;
            $percentage = $total_votes > 0 ? round(($votes / $total_votes) * 100, 1) : 0;
            return array(
                'text' => $option['option_text'],
                'votes' => $votes,
                'percentage' => $percentage,
                'is_voted' => $index == $option_index
            );
        }, $circle_meta['vote_options'], array_keys($circle_meta['vote_options']));

        return array(
            'data' => array(
                'title' => $circle_meta['vote_title'],
                'options' => $options,
                'total_votes' => $total_votes,
                'has_voted' => true,
                'end_time' => $circle_meta['vote_end_time'],
                'is_ended' => strtotime($circle_meta['vote_end_time']) <= current_time('timestamp')
            )
        );
    }

    /**
     * 获取投票数据
     * @author ifyn
     * @param int $post_id 帖子ID
     * @param int $user_id 用户ID
     * @return array 投票数据
     */
    public static function get_vote_data($post_id, $user_id = 0)
    {
        $circle_meta = get_post_meta($post_id, 'single_circle_metabox', true);

        if(!$circle_meta || !isset($circle_meta['vote_title']) || empty($circle_meta['vote_title']) || !isset($circle_meta['vote_options']) || !is_array($circle_meta['vote_options']) || empty($circle_meta['vote_options']) ){
            return array(
                'error' => '投票数据不存在',
            );
        }
        
        $voted_users = get_post_meta($post_id, 'circle_voted_users', true);
        $voted_users = !empty($voted_users) && is_array($voted_users) ? $voted_users : array();
        $has_voted = $user_id > 0 && in_array($user_id, $voted_users);

        // 获取用户投票的选项
        $user_vote_option = -1;
        if ($has_voted) {
            $user_vote_option = get_post_meta($post_id, 'circle_user_vote_' . $user_id, true);
        }

        // 计算总票数
        $total_votes = 0;
        foreach ($circle_meta['vote_options'] as $option) {
            $total_votes += isset($option['votes']) ? $option['votes'] : 0;
        }

        // 计算每个选项的百分比
        $options = array_map(function ($option, $index) use ($total_votes, $user_vote_option) {
            $votes = isset($option['votes']) ? $option['votes'] : 0;
            $percentage = $total_votes > 0 ? round(($votes / $total_votes) * 100, 1) : 0;
            return array(
                'text' => $option['option_text'],
                'votes' => $votes,
                'percentage' => $percentage,
                'is_voted' => $index == $user_vote_option  // 添加是否投票标记
            );
        }, $circle_meta['vote_options'], array_keys($circle_meta['vote_options']));

        return array(
            'data' => array(
                'title' => $circle_meta['vote_title'],
                'options' => $options,
                'total_votes' => $total_votes,
                'has_voted' => $has_voted,
                'end_time' => $circle_meta['vote_end_time'],
                'is_ended' => strtotime($circle_meta['vote_end_time']) <= current_time('timestamp')
            )
        );
    }

    /**
     * 关注话题
     * @author ifyn
     * @param int $topic_id 话题ID
     * @return array 处理结果
     */
    public static function follow_topic($topic_id)
    {
        try {
            $user_id = get_current_user_id();
            if (!$user_id) {
                return array('error' => '请先登录');
            }
            // 检查话题是否存在
            $topic = get_term($topic_id, 'topic');
            if (!$topic || is_wp_error($topic)) {
                return array(
                    'error' => '话题不存在'
                );
            }

            // 获取用户已关注的话题
            $followed_topics = get_user_meta($user_id, 'islide_followed_topics', true);
            $followed_topics = !empty($followed_topics) && is_array($followed_topics) ? $followed_topics : array();

            // 检查是否已经关注
            if (in_array($topic_id, $followed_topics)) {
                return array(
                    'error' => '已经关注过该话题'
                );
            }

            // 添加关注
            $followed_topics[] = $topic_id;
            update_user_meta($user_id, 'islide_followed_topics', $followed_topics);

            // 更新话题关注数
            $follow_count = (int) get_term_meta($topic_id, 'islide_topic_follow_count', true);
            update_term_meta($topic_id, 'islide_topic_follow_count', $follow_count + 1);

            return array(
                'data' => array(
                    'topic_id' => $topic_id,
                    'follow_count' => $follow_count + 1
                )
            );
        } catch (\Exception $e) {
            error_log('Follow topic error: ' . $e->getMessage());
            return array(
                'error' => '关注失败'
            );
        }
    }

    /**
     * 取消关注话题
     * @author ifyn
     * @param int $user_id 用户ID
     * @param int $topic_id 话题ID
     * @return array 处理结果
     */
    public static function unfollow_topic($topic_id)
    {
        try {
            $user_id = get_current_user_id();
            if (!$user_id) {
                return array('error' => '请先登录');
            }
            // 检查话题是否存在
            $topic = get_term($topic_id, 'topic');
            if (!$topic || is_wp_error($topic)) {
                return array(
                    'error' => '话题不存在'
                );
            }

            // 获取用户已关注的话题
            $followed_topics = get_user_meta($user_id, 'islide_followed_topics', true);
            $followed_topics = !empty($followed_topics) && is_array($followed_topics) ? $followed_topics : array();

            // 检查是否已经关注
            if (!in_array($topic_id, $followed_topics)) {
                return array(
                    'error' => '未关注该话题'
                );
            }

            // 取消关注
            $followed_topics = array_diff($followed_topics, array($topic_id));
            update_user_meta($user_id, 'islide_followed_topics', $followed_topics);

            // 更新话题关注数
            $follow_count = (int) get_term_meta($topic_id, 'islide_topic_follow_count', true);
            $follow_count = max(0, $follow_count - 1); // 确保不会出现负数
            update_term_meta($topic_id, 'islide_topic_follow_count', $follow_count);

            return array(
                'data' => array(
                    'topic_id' => $topic_id,
                    'follow_count' => $follow_count
                )
            );
        } catch (\Exception $e) {
            error_log('Unfollow topic error: ' . $e->getMessage());
            return array(
                'error' => '取消关注失败'
            );
        }
    }

    /**
     * 获取用户关注的话题列表
     * @author ifyn
     * @param int $user_id 用户ID
     * @return array 关注的话题列表
     */
    public static function get_user_followed_topics($user_id)
    {
        try {
            // 获取用户已关注的话题ID列表
            $followed_topics = get_user_meta($user_id, 'islide_followed_topics', true);
            $followed_topics = !empty($followed_topics) && is_array($followed_topics) ? $followed_topics : array();

            if (empty($followed_topics)) {
                return array(
                    'data' => array()
                );
            }

            // 获取话题详细信息
            $topics = array();
            foreach ($followed_topics as $topic_id) {
                $topic = get_term($topic_id, 'topic');
                if ($topic && !is_wp_error($topic)) {
                    $topics[] = array(
                        'id' => $topic->term_id,
                        'name' => $topic->name,
                        'slug' => $topic->slug,
                        'description' => $topic->description,
                        'icon' => islide_get_thumb(array('url' => get_term_meta($topic_id, 'islide_tax_img', true), 'width' => 50, 'height' => 50, 'default' => false)) ?: '',
                        'follow_count' => (int) get_term_meta($topic_id, 'islide_topic_follow_count', true),
                        'post_count' => $topic->count
                    );
                }
            }

            return array(
                'data' => $topics
            );
        } catch (\Exception $e) {
            error_log('Get user followed topics error: ' . $e->getMessage());
            return array(
                'error' => '获取关注话题失败'
            );
        }
    }

    /**
     * 检查用户是否关注了话题
     * @author ifyn
     * @param int $user_id 用户ID
     * @param int $topic_id 话题ID
     * @return bool 是否关注
     */
    public static function is_user_followed_topic($user_id, $topic_id)
    {
        try {
            $followed_topics = get_user_meta($user_id, 'islide_followed_topics', true);
            $followed_topics = !empty($followed_topics) && is_array($followed_topics) ? $followed_topics : array();
            return in_array($topic_id, $followed_topics);
        } catch (\Exception $e) {
            error_log('Check user followed topic error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 记录评论的IP信息
     * @author ifyn
     * @param int $comment_id 评论ID
     * @return void
     */
    private static function insert_comment_ip($comment_id)
    {
        if (!is_int($comment_id) || $comment_id <= 0) {
            return;
        }
        $ip = islide_get_user_ip();
        $data = IpLocation::get($ip);

        if (isset($data['error']))
            return;
        $data['date'] = current_time('mysql');

        update_comment_meta($comment_id, 'islide_comment_ip_location', $data);
    }

    // 获取评论的IP信息
    public static function get_comment_ip_location($comment_id)
    {
        $ip_location = get_comment_meta($comment_id, 'islide_comment_ip_location', true);
        return IpLocation::build_location($ip_location);
    }

    /**
     * 提交回答
     * @author ifyn
     * @param array $args 回答参数
     * @param bool $user_agent 是否包含用户代理信息
     * @return array 提交结果
     */
    public static function submit_answer($args, $user_agent = false)
    {
        try {
            if (empty($args['moment_id']) || empty($args['content'])) {
                return array(
                    'error' => '参数错误'
                );
            }

            $moment_id = intval($args['moment_id']);
            $content = wp_kses_post($args['content']);
            $user_id = get_current_user_id();

            if (!$user_id) {
                return array(
                    'error' => '请先登录'
                );
            }

            // 查看moment所属圈子
            $circle_id = self::get_circle_id_by_post_id($moment_id);

            $privacy = get_term_meta($circle_id, 'islide_circle_privacy', true);


            if ($privacy !== 'public') {
                return array(
                    'error' => '您没有权限回答，请先加入圈子'
                );
            }

            // 检查用户是否已经回答过
            $existing_answer = get_comments(array(
                'post_id' => $moment_id,
                'user_id' => $user_id,
                'parent' => 0,
                'type' => 'circle_answer',
                'status' => 'approve',
                'count' => true
            ));

            if ($existing_answer > 0) {
                return array(
                    'error' => '您已经回答过该问题'
                );
            }

            $comment_data = array(
                'comment_post_ID' => $moment_id,
                'comment_content' => $content,
                'user_id' => $user_id,
                'comment_type' => 'circle_answer',
                'comment_approved' => 1
            );

            if ($user_agent) {
                $comment_data['comment_agent'] = $_SERVER['HTTP_USER_AGENT'];
            }

            $comment_id = wp_insert_comment($comment_data);

            if (!$comment_id) {
                return array(
                    'error' => '提交回答失败'
                );
            }

            // 记录IP信息
            self::insert_comment_ip($comment_id);

            // 更新动态的回答数
            $answer_count = get_post_meta($moment_id, 'circle_answer_count', true);
            update_post_meta($moment_id, 'circle_answer_count', intval($answer_count) + 1);

            $comment = get_comment($comment_id);
            $answer_data = self::get_answer_data($comment);

            return array(
                'data' => $answer_data,
            );
        } catch (\Exception $e) {
            error_log('Submit answer error: ' . $e->getMessage());
            return array(
                'error' => '提交回答失败'
            );
        }
    }

    /**
     * 获取回答数据
     * @author ifyn
     * @param object $comment 评论对象
     * @return array 回答数据
     */
    public static function get_answer_data($comment)
    {
        $user_id = get_current_user_id();
        $vote_status = self::get_answer_vote_status($comment->comment_ID, $user_id);

        // 获取父评论信息
        $parent = null;
        if ($comment->comment_parent > 0) {
            $parent_comment = get_comment($comment->comment_parent);
            if ($parent_comment) {
                $parent = array(
                    'id' => $parent_comment->comment_ID,
                    'user' => User::get_user_public_data($parent_comment->user_id)
                );
            }
        }

        // 获取顶层评论信息
        $top_parent = null;
        if ($comment->comment_parent > 0) {
            $current = $comment;
            while ($current->comment_parent > 0) {
                $current = get_comment($current->comment_parent);
                if (!$current)
                    break;
            }
            if ($current) {
                $top_parent = array(
                    'id' => $current->comment_ID,
                    'user' => User::get_user_public_data($current->user_id)
                );
            }
        }

        return array(
            'id' => $comment->comment_ID,
            'content' => $comment->comment_content,
            'date' => islide_time_ago($comment->comment_date, true),
            'user' => User::get_user_public_data($comment->user_id),
            'like_count' => intval(get_comment_meta($comment->comment_ID, 'circle_answer_like_count', true)),
            'dislike_count' => intval(get_comment_meta($comment->comment_ID, 'circle_answer_dislike_count', true)),
            'vote_status' => $vote_status,
            'is_best' => get_comment_meta($comment->comment_ID, 'circle_answer_is_best', true) === '1',
            'is_sticky' => get_comment_meta($comment->comment_ID, 'circle_answer_is_sticky', true) === '1',
            'ip' => self::get_comment_ip_location($comment->comment_ID),
            'is_self' => $comment->user_id == $user_id,
            'parent' => $parent,
            'top_parent' => $top_parent,
            'children_count' => 0,
            'children' => array()
        );
    }

    /**
     * 获取动态的回答列表
     * @author ifyn
     * @param array $data 查询参数
     * @return array 回答列表
     */
    public static function get_moment_answers($data)
    {
        try {
            if (empty($data['moment_id'])) {
                return array(
                    'error' => '参数错误'
                );
            }

            $moment_id = intval($data['moment_id']);
            $page = isset($data['paged']) ? intval($data['paged']) : 1;
            $per_page = isset($data['per_page']) ? intval($data['per_page']) : 20;
            $orderby = isset($data['orderby']) ? $data['orderby'] : 'date'; // date或like_count
            $order = isset($data['order']) ? strtoupper($data['order']) : 'DESC'; // ASC或DESC

            $answers = array();

            // 先获取采纳的回答
            $adopted_answer_id = get_post_meta($moment_id, 'circle_has_adopted_answer', true);
            if ($adopted_answer_id === '1') {
                $adopted_comments = get_comments(array(
                    'post_id' => $moment_id,
                    'type' => 'circle_answer',
                    'status' => 'approve',
                    'meta_key' => 'circle_answer_is_best',
                    'meta_value' => '1',
                    'parent' => 0
                ));

                if (!empty($adopted_comments)) {
                    $adopted_comment = $adopted_comments[0];
                    $answer_data = self::get_answer_data($adopted_comment);
                    $answers[] = $answer_data;
                }
            }

            // 获取其他回答
            $args = array(
                'post_id' => $moment_id,
                'type' => 'circle_answer',
                'status' => 'approve',
                'number' => $per_page,
                'offset' => ($page - 1) * $per_page,
                'parent' => 0,
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => 'circle_answer_is_best',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => 'circle_answer_is_best',
                        'value' => '1',
                        'compare' => '!='
                    )
                )
            );

            // 根据排序方式设置orderby
            if ($orderby === 'like_count') {
                $args['orderby'] = array(
                    'meta_value_num' => $order,
                    'comment_date' => 'DESC'
                );
                $args['meta_key'] = 'circle_answer_like_count';
            } else {
                $args['orderby'] = 'comment_date';
                $args['order'] = $order;
            }

            // 获取总回答数
            $total_args = array(
                'post_id' => $moment_id,
                'type' => 'circle_answer',
                'status' => 'approve',
                'count' => true,
                'parent' => 0,
            );
            $total = get_comments($total_args);

            $comments = get_comments($args);
            foreach ($comments as $comment) {
                $answer_data = self::get_answer_data($comment);
                $answers[] = $answer_data;
            }

            // 获取所有子评论
            $all_children = get_comments(array(
                'post_id' => $moment_id,
                'type' => 'circle_answer',
                'status' => 'approve',
                'hierarchical' => 'flat',
                'orderby' => 'comment_date',
                'order' => 'DESC' // 修改为DESC，最新的在最上面
            ));

            // 过滤出所有子评论
            $children = array();
            foreach ($all_children as $child) {
                if ($child->comment_parent > 0) {
                    $children[] = $child;
                }
            }

            // 将子评论分配到对应的顶层评论中
            foreach ($children as $child) {
                $child_data = self::get_answer_data($child);
                $top_parent_id = $child_data['top_parent']['id'];

                // 找到对应的顶层评论
                foreach ($answers as &$answer) {
                    if ($answer['id'] == $top_parent_id) {
                        if (!isset($answer['children'])) {
                            $answer['children'] = array();
                        }
                        $answer['children'][] = $child_data;
                        break;
                    }
                }
            }

            // 更新每个顶层评论的children_count
            foreach ($answers as &$answer) {
                $answer['children_count'] = isset($answer['children']) ? count($answer['children']) : 0;
            }

            return array(
                'data' => $answers,
                'pages' => ceil($total / $per_page),
                'count' => intval($total),
                'paged' => $page
            );
        } catch (\Exception $e) {
            error_log('Get moment answers error: ' . $e->getMessage());
            return array(
                'error' => '获取回答列表失败'
            );
        }
    }

    /**
     * 采纳回答
     * @author ifyn
     * @param int $answer_id 回答ID
     * @return array 操作结果
     */
    public static function adopt_answer($answer_id)
    {
        try {
            $answer_id = intval($answer_id);
            $comment = get_comment($answer_id);

            if (!$comment || $comment->comment_type !== 'circle_answer') {
                return array(
                    'error' => '回答不存在'
                );
            }

            $moment_id = $comment->comment_post_ID;
            $user_id = get_current_user_id();

            // 检查是否是动态作者
            $moment = get_post($moment_id);
            if (!$moment || $moment->post_author != $user_id) {
                return array(
                    'error' => '只有动态作者可以采纳回答'
                );
            }

            // 检查是否已经采纳过回答
            $has_adopted = get_post_meta($moment_id, 'circle_has_adopted_answer', true);
            if ($has_adopted === '1') {
                return array(
                    'error' => '该动态已经采纳过回答'
                );
            }

            // 设置回答为最佳答案
            update_comment_meta($answer_id, 'circle_answer_is_best', '1');
            update_post_meta($moment_id, 'circle_has_adopted_answer', '1');

            return array(
                'data' => array(
                    'answer_id' => $answer_id
                )
            );
        } catch (\Exception $e) {
            error_log('Adopt answer error: ' . $e->getMessage());
            return array(
                'error' => '采纳回答失败'
            );
        }
    }

    /**
     * 删除回答
     * @author ifyn
     * @param int $answer_id 回答ID
     * @return array 操作结果
     */
    public static function delete_answer($answer_id)
    {
        try {
            $answer_id = intval($answer_id);
            $comment = get_comment($answer_id);

            if (!$comment || $comment->comment_type !== 'circle_answer') {
                return array(
                    'error' => '回答不存在'
                );
            }

            $moment_id = $comment->comment_post_ID;
            $user_id = get_current_user_id();

            // 检查权限：回答作者、动态作者、圈子管理员可以删除
            $can_delete = false;

            // 回答作者
            if ($comment->user_id == $user_id) {
                $can_delete = true;
            }

            // 动态作者
            $moment = get_post($moment_id);
            if ($moment && $moment->post_author == $user_id) {
                $can_delete = true;
            }

            // 圈子管理员
            $circle_id = self::get_circle_id_by_post_id($moment_id);
            if (self::is_circle_admin($user_id, $circle_id)) {
                $can_delete = true;
            }

            if (!$can_delete) {
                return array(
                    'error' => '您没有权限删除该回答'
                );
            }

            // 删除回答
            wp_delete_comment($answer_id, true);

            // 更新动态的回答数
            $answer_count = get_post_meta($moment_id, 'circle_answer_count', true);
            if ($answer_count > 0) {
                update_post_meta($moment_id, 'circle_answer_count', intval($answer_count) - 1);
            }

            // 如果删除的是最佳答案，清除采纳状态
            if (get_comment_meta($answer_id, 'circle_answer_is_best', true) === '1') {
                delete_post_meta($moment_id, 'circle_has_adopted_answer');
            }

            return array(
                'data' => array(
                    'answer_id' => $answer_id
                )
            );
        } catch (\Exception $e) {
            error_log('Delete answer error: ' . $e->getMessage());
            return array(
                'error' => '删除回答失败'
            );
        }
    }

    /**
     * 点赞或点踩回答
     * @author ifyn
     * @param int $answer_id 回答ID
     * @param string $type 操作类型：like或dislike
     * @return array 操作结果
     */
    public static function vote_answer($answer_id, $type)
    {
        try {
            $user_id = get_current_user_id();
            if (!$user_id) {
                return array(
                    'error' => '请先登录'
                );
            }

            $answer = get_comment($answer_id);
            if (!$answer || $answer->comment_type !== 'circle_answer') {
                return array(
                    'error' => '回答不存在'
                );
            }

            // 检查是否已经投票
            $vote_status = self::get_answer_vote_status($answer_id, $user_id);
            if ($vote_status === $type) {
                // 如果已经投票，则取消投票
                delete_comment_meta($answer_id, 'circle_answer_vote_' . $type, $user_id);
                self::update_answer_vote_count($answer_id);
                return array(
                    'data' => array(
                        'status' => 'cancel',
                        'like_count' => intval(get_comment_meta($answer_id, 'circle_answer_like_count', true)),
                        'dislike_count' => intval(get_comment_meta($answer_id, 'circle_answer_dislike_count', true))
                    )
                );
            } else if ($vote_status) {
                // 如果投了另一种票，则先取消之前的投票
                delete_comment_meta($answer_id, 'circle_answer_vote_' . $vote_status, $user_id);
            }

            // 添加新的投票
            add_comment_meta($answer_id, 'circle_answer_vote_' . $type, $user_id);
            self::update_answer_vote_count($answer_id);

            return array(
                'data' => array(
                    'status' => $type,
                    'like_count' => intval(get_comment_meta($answer_id, 'circle_answer_like_count', true)),
                    'dislike_count' => intval(get_comment_meta($answer_id, 'circle_answer_dislike_count', true))
                )
            );
        } catch (\Exception $e) {
            error_log('Vote answer error: ' . $e->getMessage());
            return array(
                'error' => '操作失败'
            );
        }
    }

    /**
     * 获取用户对回答的投票状态
     * @author ifyn
     * @param int $answer_id 回答ID
     * @param int $user_id 用户ID
     * @return string|null 投票状态：like、dislike或null
     */
    private static function get_answer_vote_status($answer_id, $user_id)
    {
        $like = get_comment_meta($answer_id, 'circle_answer_vote_like');
        $dislike = get_comment_meta($answer_id, 'circle_answer_vote_dislike');

        if (!empty($like) && in_array($user_id, $like)) {
            return 'like';
        }
        if (!empty($dislike) && in_array($user_id, $dislike)) {
            return 'dislike';
        }
        return null;
    }

    /**
     * 更新回答的投票计数
     * @author ifyn
     * @param int $answer_id 回答ID
     */
    private static function update_answer_vote_count($answer_id)
    {
        $like = get_comment_meta($answer_id, 'circle_answer_vote_like');
        $dislike = get_comment_meta($answer_id, 'circle_answer_vote_dislike');

        $like_count = !empty($like) ? count($like) : 0;
        $dislike_count = !empty($dislike) ? count($dislike) : 0;

        update_comment_meta($answer_id, 'circle_answer_like_count', $like_count);
        update_comment_meta($answer_id, 'circle_answer_dislike_count', $dislike_count);
    }

    /**
     * 取消采纳回答
     * @author ifyn
     * @param int $answer_id 回答ID
     * @return array 处理结果
     */
    public static function cancel_adopt_answer($answer_id)
    {
        $comment = get_comment($answer_id);
        if (!$comment) {
            return array('error' => '回答不存在');
        }

        $post_id = $comment->comment_post_ID;
        $user_id = get_current_user_id();

        // 检查是否是动态作者
        $post = get_post($post_id);
        if (!$post || $post->post_author != $user_id) {
            return array('error' => '您没有权限取消采纳');
        }

        // 检查是否已被采纳
        $is_best = get_comment_meta($answer_id, 'circle_answer_is_best', true);
        if ($is_best !== '1') {
            return array('error' => '该回答未被采纳');
        }

        // 取消采纳标记
        delete_comment_meta($answer_id, 'circle_answer_is_best');
        delete_post_meta($post_id, 'circle_has_adopted_answer');

        return array(
            'data' => array(
                'answer_id' => $answer_id,
                'post_id' => $post_id
            )
        );
    }

    /**
     * 给回答添加评论
     * @author ifyn
     * @param array $args 评论参数
     * @param bool $user_agent 是否记录用户代理
     * @return array 操作结果
     */
    public static function submit_answer_comment($args, $user_agent = false)
    {
        try {
            // 检查用户是否登录
            $user_id = get_current_user_id();
            if (!$user_id) {
                return array(
                    'error' => '请先登录'
                );
            }

            // 检查参数
            if (empty($args['answer_id']) || empty($args['content'])) {
                return array(
                    'error' => '参数错误'
                );
            }

            $answer_id = intval($args['answer_id']);
            $content = trim($args['content']);

            // 检查回答是否存在
            $answer = get_comment($answer_id);
            if (!$answer || $answer->comment_type !== 'circle_answer') {
                return array(
                    'error' => '回答不存在'
                );
            }

            // 检查内容长度
            if (mb_strlen($content) < 2) {
                return array(
                    'error' => '评论内容太短'
                );
            }

            if (mb_strlen($content) > 1000) {
                return array(
                    'error' => '评论内容不能超过1000字'
                );
            }

            // 准备评论数据
            $comment_data = array(
                'comment_post_ID' => $answer->comment_post_ID,
                'comment_content' => $content,
                'comment_parent' => $answer_id,
                'user_id' => $user_id,
                'comment_type' => 'circle_answer',
                'comment_approved' => 1
            );

            // 插入评论
            $comment_id = wp_insert_comment($comment_data);

            if (!$comment_id) {
                return array(
                    'error' => '评论发布失败'
                );
            }

            // 记录IP信息
            self::insert_comment_ip($comment_id);

            // 获取评论数据
            $comment = get_comment($comment_id);
            $comment_data = self::get_answer_data($comment);

            return array(
                'data' => $comment_data
            );
        } catch (\Exception $e) {
            error_log('Submit answer comment error: ' . $e->getMessage());
            return array(
                'error' => '评论发布失败'
            );
        }
    }

}