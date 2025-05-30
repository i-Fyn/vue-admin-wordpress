<?php
/**
 * 评论功能管理类
 * 
 * 处理评论相关的功能，包括评论发布、审核、过滤和用户评论管理
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;
use islide\Modules\Common\IpLocation;

class Comment {
    /**
     * 初始化评论相关的钩子和过滤器
     * 
     * @author  ifyn
     * @return  void
     */
    public function init() {
        // 过滤评论内容，移除特定字符
        add_filter('comment_text', array(__CLASS__, 'remove_kh'));
        
        // 当评论从待审核状态变更为已发布状态时触发
        add_action('comment_unapproved_to_approved', array($this, 'comment_unapproved_to_approved_action'));
        add_action('comment_unapproved_to_approved_action', array($this, 'comment_unapproved_to_approved'));
        
        // 评论插入数据库后立即触发
        add_action('comment_post', array($this, 'comment_unapproved_to_approved'));
        
        // 评论插入数据库后记录IP位置信息
        add_action('wp_insert_comment', array($this, 'insert_comment_ip_location'), 10, 2);
        
        // 设置评论按时间降序排序
        add_filter('comments_template_query_args', function($args) {
            $args['order'] = 'DESC';
            return $args;
        });
    }
    
    /**
     * 保存评论的IP位置信息
     * 
     * @author  ifyn
     * @param   int      $comment_id 评论ID
     * @param   WP_Comment $comment  评论对象
     * @return  void
     */
    public function insert_comment_ip_location($comment_id, $comment) {
        if (empty($comment_id) || empty($comment) || empty($comment->comment_author_IP)) {
            return;
        }
        
        $data = IpLocation::get($comment->comment_author_IP);
        
        if (isset($data['error'])) {
            return;
        }
        
        update_comment_meta($comment_id, 'islide_comment_ip_location', $data);
    }
    
    /**
     * 处理评论从待审核到已批准的状态变更
     * 
     * @author  ifyn
     * @param   WP_Comment $comment 评论对象
     * @return  void
     */
    public function comment_unapproved_to_approved_action($comment) {
        if (empty($comment) || empty($comment->comment_ID)) {
            return;
        }
        
        do_action("comment_unapproved_to_approved_action", $comment->comment_ID);
    }
    
    /**
     * 处理已批准评论的后续操作
     * 
     * @author  ifyn
     * @param   int     $comment_id 评论ID
     * @return  boolean 处理是否成功
     */
    public function comment_unapproved_to_approved($comment_id) {
        if (empty($comment_id)) {
            return false;
        }

        $comment = get_comment($comment_id);
        if (!$comment) {
            return false;
        }

        // 如果评论未得到批准
        if ($comment->comment_approved != 1) {
            return false;
        }
        
        // 获取评论的作者
        $comment_author = $comment->user_id ? (int)$comment->user_id : (string)$comment->comment_author;

        // 获取评论的文章ID
        $post_id = (int)$comment->comment_post_ID;
        if (!$post_id) {
            return false;
        }
        
        // 保存文章最后一次评论时间，用于查询排序
        update_post_meta($post_id, 'islide_last_comment_date', $comment->comment_date);
        
        // 获取评论所在的文章作者ID
        $post_author = (int)get_post_field('post_author', $post_id);

        // 处理父级评论信息
        $parent_comment_author = false;
        if ($comment->comment_parent > 0) {
            $parent_comment = get_comment($comment->comment_parent);
            if ($parent_comment) {
                $parent_comment_author = $parent_comment->user_id ? (int)$parent_comment->user_id : false;
            }
        }
        
        // 判断是否是自己评论自己的文章
        $is_self = $post_author === $comment_author;

        // 获取文章类型信息
        $post_type = get_post_type($post_id);
        $post_type_name = function_exists('islide_get_type_name') ? islide_get_type_name($post_type) : $post_type;
        
        // 触发评论钩子，允许其他功能响应评论事件
        do_action('islide_comment_post', array(
            'comment_id' => $comment_id,
            'comment_author' => $comment_author,
            'parent_comment_id' => $comment->comment_parent,
            'parent_comment_author' => $parent_comment_author,
            'post_id' => $post_id,
            'post_author' => $post_author,
            'post_type_name' => $post_type_name,
            'is_self' => $is_self,
        ));
        
        return true;
    }
    
    /**
     * 过滤评论内容，删除特定括号
     * 
     * @author  ifyn
     * @param   string $comment_text 原始评论内容
     * @return  string 处理后的评论内容
     */
    public static function remove_kh($comment_text) {
        if (empty($comment_text)) {
            return '';
        }
        
        // 移除特定括号
        $comment_text = str_replace(array('{{', '}}'), '', $comment_text);
        
        // 应用安全过滤并返回
        return wp_kses_post(self::comment_filters($comment_text));
    }
    
    /**
     * 应用评论内容过滤器
     * 
     * @author  ifyn
     * @param   string $comment_text 原始评论内容
     * @return  string 处理后的评论内容
     */
    public static function comment_filters($comment_text) {
        if (empty($comment_text)) {
            return '';
        }
        
        return apply_filters('islide_comment_filters', $comment_text);
    }
    
    /**
     * 获取指定用户的评论数量
     * 
     * @author  ifyn
     * @param   int $user_id 用户ID
     * @return  int 评论数量
     */
    public static function get_user_comment_count($user_id) {
        if (empty($user_id)) {
            return 0;
        }
        
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(comment_ID) FROM ' . $wpdb->comments . ' WHERE user_id = %d',
            $user_id
        ));
        
        return $count ? (int)$count : 0;
    }
    
    /**
     * 评论投票（点赞/取消点赞）
     *
     * @author  ifyn
     * @param   string $type       投票类型 like/dislike
     * @param   int    $comment_id 评论ID
     * @return  array  操作结果，成功返回点赞数，失败返回错误信息
     */
    public static function comment_vote($type, $comment_id) {
        // 验证用户是否登录
        $user_id = get_current_user_id();
        if (!$user_id) {
            return array('error' => '请先登录之后再参与投票哦！');
        }
        
        // 验证评论是否存在
        $comment_id = (int)$comment_id;
        $comment = get_comment($comment_id);
        
        if (!$comment) {
            return array('error' => '投票的评论不存在');
        }
        
        // 获取评论作者
        $comment_author = $comment->user_id ? (int)$comment->user_id : (string)$comment->comment_author;
        
        // 验证不能点评自己的评论
        if ($user_id == $comment_author) {
            return array('error' => '不能点评自己的评论');
        }
        
        // 获取用户已点赞的评论列表
        $comment_likes = get_user_meta($user_id, 'islide_comment_likes', true);
        $comment_likes = is_array($comment_likes) ? $comment_likes : array();
        $key = array_search($comment_id, $comment_likes);
        
        // 获取当前评论的点赞数
        $comment_like = (int)get_comment_meta($comment_id, 'islide_comment_like', true);
        
        // 执行点赞或取消点赞
        if ($key === false) {
            // 添加点赞
            $comment_likes[] = $comment_id;
            $comment_like += 1;
        } else {
            // 取消点赞
            unset($comment_likes[$key]);
            $comment_like = max(0, $comment_like - 1); // 确保不会小于0
        }
        
        // 更新用户点赞列表和评论点赞数
        update_user_meta($user_id, 'islide_comment_likes', array_values($comment_likes)); // 重新索引数组
        update_comment_meta($comment_id, 'islide_comment_like', $comment_like);
        
        // 触发评论投票钩子
        do_action('islide_comment_vote', $comment_id, $user_id, $key === false);
        
        return array('like' => $comment_like);
    }
    
    /**
     * 获取指定用户的评论列表
     * 
     * @author  ifyn
     * @param   int    $user_id 用户ID
     * @param   int    $paged   当前页数
     * @param   int    $size    每页显示数量
     * @return  array  包含评论信息的数组或错误信息
     */
    public static function get_user_comment_list($user_id, $paged, $size) {
        // 参数验证和转换
        $user_id = (int)$user_id;
        $paged = (int)$paged;
        $size = (int)$size;
        
        if (empty($user_id)) {
            return array('error' => '用户ID不能为空');
        }
        
        if ($size > 20 || $size < 1) {
            return array('error' => '请求数量错误');
        }
        
        if ($paged < 1) {
            return array('error' => '请求页码错误');
        }
        
        // 设置查询参数
        $args = array(
            'user_id' => $user_id,
            'status' => 'approve',
            'number' => $size,
            'offset' => ($paged - 1) * $size,
            'orderby' => 'comment_date',
            'order' => 'DESC'
        );
        
        // 获取评论列表
        $comments = get_comments($args);
        
        // 存储评论信息的数组
        $comment_list = array();
        
        // 遍历评论列表，处理每个评论的信息
        foreach ($comments as $comment) {
            // 获取评论所属的文章
            $post_id = (int)$comment->comment_post_ID;
            $comment_post = get_post($post_id);
            
            // 获取父级评论
            $comment_parent = null;
            if ($comment->comment_parent > 0) {
                $comment_parent = get_comment($comment->comment_parent);
            }
            
            // 构建评论数据
            $comment_data = array(
                'comment_post' => $comment_post ? array(
                    'id' => $post_id,
                    'title' => $comment_post->post_title,
                    'link' => get_permalink($comment_post->ID),
                    'type' => $comment_post->post_type,
                ) : null,
                'comment' => array(
                    'id' => (int)$comment->comment_ID,
                    'parent_id' => (int)$comment->comment_parent,
                    'content' => self::remove_kh($comment->comment_content),
                    'date' => function_exists('islide_time_ago') ? 
                              islide_time_ago($comment->comment_date, true) : 
                              $comment->comment_date,
                    'post_id' => $post_id
                )
            );
            
            // 添加父级评论信息（如果存在）
            if ($comment_parent) {
                $comment_data['comment_parent'] = array(
                    'id' => (int)$comment_parent->comment_ID,
                    'parent_id' => (int)$comment_parent->comment_parent,
                    'content' => self::remove_kh($comment_parent->comment_content),
                    'date' => function_exists('islide_time_ago') ? 
                             islide_time_ago($comment_parent->comment_date, true) : 
                             $comment_parent->comment_date,
                    'post_id' => (int)$comment_parent->comment_post_ID
                );
            } else {
                $comment_data['comment_parent'] = null;
            }
            
            $comment_list[] = $comment_data;
        }
        
        // 获取评论总数
        global $wpdb;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(comment_ID) AS total FROM $wpdb->comments WHERE comment_approved = 1 AND user_id = %d", 
            $user_id
        ));
        
        // 计算总页数并返回评论信息
        return array(
            'pages' => ceil((int)$count / $size),
            'total' => (int)$count,
            'data' => $comment_list
        );
    }
}