<?php
/**
 * 任务系统管理类
 * 
 * 处理用户任务的创建、完成、检查和奖励发放
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;
use islide\Modules\Common\Message;

//任务系统
class Task {
    /**
     * 任务列表静态变量
     * 
     * @var array
     */
    public static $tasks  = array();
    
    /**
     * 初始化任务系统并注册相关钩子
     *
     * @author  ifyn
     * @return  void
     */
    public function init(){
        
        if(!self::$tasks)  {
            
            $options = islide_get_option();
            
            self::$tasks = apply_filters('islide_tasks',array(
                'newbie_task' => isset($options['newbie_task_group']) ? $options['newbie_task_group'] : [],
                'daily_task' => isset($options['daily_task_group']) ? $options['daily_task_group'] : [],
                'recom_task' => isset($options['recom_task_group']) ? $options['recom_task_group'] : [],
            ));
        }
        
        //WP自带用户注册钩子
        add_action( 'user_register', array($this,'task_user_register'),10,1);
        
        //发布文章
        add_action('transition_post_status', array($this,'task_post'), 999, 3 );
        
        //关注用户与取消关注通知
        add_action('islide_user_follow_action',array($this,'task_follow'),10,3);
        
        //文章点赞
        add_action('islide_post_vote',array($this,'task_post_like'),10,3);
        
        //评论通知
        add_action('islide_comment_post',array($this,'task_comment'));
        
        //评论点赞
        add_action('islide_comment_vote',array($this,'task_comment_like'),10,3);
    }
    
    /**
     * 完成指定任务
     *
     * @author  ifyn
     * @param   int    $user_id   用户ID
     * @param   string $type      任务类型(newbie_task, daily_task, recom_task)
     * @param   string $task_type 具体任务标识符
     * @return  mixed             任务结果
     */
    public static function complete_task($user_id,$type,$task_type) {
        if (!$user_id || !$type || !$task_type) {
            return false;
        }
        
        $task = apply_filters('islide_complete_task',$user_id,$type,$task_type);
        if($task) {
            do_action('islide_complete_task_action',$user_id,$task,$type);
            return $task;
        }
        
        return false;
    }
    
    /**
     * 刷新用户每日任务
     *
     * @author  ifyn
     * @param   int   $user_id 用户ID
     * @return  array          用户每日任务数据
     */
    public static function refresh_tasks($user_id) {
        if (!$user_id) {
            return array();
        }
        
        //日常任务
        $daily_task = get_user_meta($user_id, 'islide_daily_task', true);
        $daily_task = !empty($daily_task) ? $daily_task : array();
        
        // 如果上次刷新时间不是今天，则进行任务刷新
        if(empty($daily_task) || (isset($daily_task['time']) && $daily_task['time'] < current_time('Y-m-d'))){
            
            $daily_task = array(
                'time' => current_time('Y-m-d'),
                'tasks' => array()
            );
            
            update_user_meta($user_id,'islide_daily_task',$daily_task);
        }
        
        return $daily_task;
    }
    
    /**
     * 获取用户已完成的任务
     *
     * @author  ifyn
     * @param   int   $user_id 用户ID
     * @return  array          用户完成的各类任务数据
     */
    public static function user_completed_tasks($user_id) {
        if (!$user_id) {
            return array(
                'newbie_task' => array(),
                'daily_task' => array('tasks' => array()),
                'recom_task' => array(),
            );
        }
        
        //新手任务
        $newbie_task = get_user_meta($user_id, 'islide_newbie_task', true);
        $newbie_task = !empty($newbie_task) ? $newbie_task : array();
        
        //每日任务
        $daily_task = self::refresh_tasks($user_id);
        
        //推荐任务
        $recom_task = get_user_meta($user_id, 'islide_recom_task', true);
        $recom_task = !empty($recom_task) ? $recom_task : array();
        
        return array(
            'newbie_task' => $newbie_task,
            'daily_task' => $daily_task,
            'recom_task' => $recom_task,
        ); 
    }
    
    /**
     * 获取任务数据，包括完成状态
     *
     * @author  ifyn
     * @param   int    $user_id 用户ID
     * @param   string $key     筛选的任务奖励关键字
     * @return  array|array     任务数据或错误信息
     */
    public static function get_task_data($user_id,$key = '') {
        $user_id = $user_id ? $user_id : get_current_user_id();
        if(!$user_id) return array('error'=>'请先登录');

        //获取任务列表
        $tasks = self::get_tasks($key);
        
        //获取用户完成的任务
        $completedTasks = self::user_completed_tasks($user_id);
        
        foreach ($tasks as $key => &$value) {
            foreach ($value as $k => &$v){
                
                //新手任务
                if($key == 'newbie_task') {
                    $v['completed_count'] = isset($completedTasks['newbie_task'][$v['task_type']]) ? $completedTasks['newbie_task'][$v['task_type']] : 0;;
                    $v['task_count'] = 1;
                    $v['is_completed'] = isset($completedTasks['newbie_task'][$v['task_type']]) ? true : false;
                }
                
                //日常任务
                elseif ($key == 'daily_task') {
                    //完成计数
                    $v['completed_count'] = isset($completedTasks['daily_task']['tasks'][$v['task_type']]) ? $completedTasks['daily_task']['tasks'][$v['task_type']] : 0;
                    $v['is_completed'] = false;
                }
                
                //推荐任务
                else {
                    $v['completed_count'] = self::check_task_condition($user_id,$v);
                    $v['is_completed'] = isset($completedTasks['recom_task'][$v['task_type']]) ? true : false;
                }
            }
        }
        
        return $tasks;
    }
    
    /**
     * 检查任务条件是否满足
     *
     * @author  ifyn
     * @param   int   $user_id 用户ID
     * @param   array $task    任务数据
     * @return  int            完成计数
     */
    public static function check_task_condition($user_id,$task) {
        if (!$user_id || !is_array($task) || empty($task['task_type'])) {
            return 0;
        }
        
        $task_count = (int)$task['task_count'];
        $completed_count = (int)apply_filters('islide_recom_task_completed_count',$user_id,$task['task_type']);
        
        if($completed_count >= $task_count) {
            self::complete_task($user_id,'recom_task',$task['task_type']);
        }
        
        return $completed_count;
    }
    
    /**
     * 获取任务列表
     *
     * @author  ifyn
     * @param   string $key 筛选的任务奖励关键字
     * @return  array       任务列表
     */
    public static function get_tasks($key = '') {
        // 返回任务列表，可以根据需要自定义任务
        $options = self::$tasks;
        $data = array(
            'newbie_task' => (isset($options['newbie_task'])&&!empty($options['newbie_task'])) ? self::filterArrayByKeyword($options['newbie_task'],$key) : [],
            'daily_task' => (isset($options['daily_task'])&&!empty($options['daily_task'])) ? self::filterArrayByKeyword($options['daily_task'],$key) : [],
            'recom_task' => (isset($options['recom_task'])&&!empty($options['recom_task'])) ? self::filterArrayByKeyword($options['recom_task'],$key) : [],
        );
        
        return $data;
    }
    
    /**
     * 检查指定任务类型是否存在
     *
     * @author  ifyn
     * @param   string $task     任务分类(newbie_task, daily_task, recom_task)
     * @param   string $taskType 具体任务标识符
     * @return  array            任务数据或空数组
     */
    public static function checkTaskType($task,$taskType) {
        $tasks = self::$tasks;
        foreach ($tasks[$task] as $value) {
            if ($value['task_type'] == $taskType) {
                return $value;
            }
        }
        
        return array();
    }
    
    /**
     * 处理用户注册任务
     *
     * @author  ifyn
     * @param   int $user_id 新注册用户的ID
     * @return  void
     */
    public function task_user_register($user_id) {
        if (!$user_id) {
            return;
        }
        
        self::complete_task($user_id,'newbie_task','task_user_register');
    }
    
    /**
     * 处理发布文章任务
     *
     * @author  ifyn
     * @param   string  $new_status 新状态
     * @param   string  $old_status 旧状态
     * @param   WP_Post $post       文章对象
     * @return  bool                处理结果
     */
    public function task_post($new_status, $old_status, $post) {
        if (!is_object($post)) {
            return false;
        }
        
        //文章作者
        $user_id = $post->post_author;
        if($new_status == 'publish' && ($old_status == 'new' || $old_status == 'pending' || $old_status == 'draft')) {
            self::complete_task($user_id,'daily_task','task_post');
            return true;
        }
        
        return false;
    }
    
    /**
     * 处理文章点赞或文章被点赞任务
     *
     * @author  ifyn
     * @param   int  $post_id         被点赞的文章ID
     * @param   int  $current_user_id 点赞用户ID
     * @param   bool $success         点赞是否成功
     * @return  void
     */
    public function task_post_like($post_id,$current_user_id,$success) {
        if (!$post_id || !$current_user_id || !$success) {
            return;
        }
        
        //获取文章作者id
        $author_id = get_post_field('post_author', $post_id);
        self::complete_task($current_user_id,'daily_task','task_like');
        self::complete_task($author_id,'daily_task','task_post_like');
    }
    
    /**
     * 处理发布评论或文章被评论任务
     *
     * @author  ifyn
     * @param   array $comment 评论数据
     * @return  void
     */
    public function task_comment($comment) {
        if (!is_array($comment) || empty($comment)) {
            return;
        }
        
        //给文章作者发送通知
        if(isset($comment['is_self']) && !$comment['is_self'] && empty($comment['parent_comment_author'])) {
            self::complete_task($comment['post_author'],'daily_task','task_post_comment');
        }
        
        self::complete_task($comment['comment_author'],'daily_task','task_comment');
    }
    
    /**
     * 处理评论被点赞任务
     *
     * @author  ifyn
     * @param   int  $comment_id      被点赞的评论ID
     * @param   int  $current_user_id 点赞用户ID
     * @param   bool $success         点赞是否成功
     * @return  void
     */
    public function task_comment_like($comment_id,$current_user_id,$success) {
        if (!$comment_id || !$current_user_id || !$success) {
            return;
        }
        
        //获取评论作者id
        $comment = get_comment($comment_id);
        $comment_author = $comment->user_id ? (int)$comment->user_id : (string)$comment->comment_author;
        
        //如果是自己则不发送消息
        if($comment_author == $current_user_id) return;
        
        self::complete_task($comment_author,'daily_task','task_comment_like');
    }
    
    /**
     * 处理关注他人或被他人关注任务
     *
     * @author  ifyn
     * @param   int  $user_id         被关注用户ID
     * @param   int  $current_user_id 关注用户ID
     * @param   bool $success         关注是否成功
     * @return  void
     */
    public function task_follow($user_id, $current_user_id, $success) {
        if (!$user_id || !$current_user_id || !$success) {
            return;
        }
        
        self::complete_task($user_id,'daily_task','task_fans');
        self::complete_task($current_user_id,'daily_task','task_follow');
    }
    
    /**
     * 根据指定关键字筛选数组中包含关键字的元素
     * 
     * @author  ifyn
     * @param   array  $array 原始数组
     * @param   string $key   指定的关键字
     * @return  array         包含符合条件的元素的新数组
     */
    public static function filterArrayByKeyword($array, $key) {
        if (empty($array) || !is_array($array)) {
            return array();
        }
        
        if (empty($key)) {
            return $array;
        }
        
        $result = array_filter($array, function($element) use ($key) {
            if (isset($element['task_bonus']) && is_array($element['task_bonus'])) {
                foreach ($element['task_bonus'] as $bonus) {
                    if (isset($bonus['key']) && $bonus['key'] == $key) {
                        return true;
                    }
                }
            }
            return false;
        });
    
        return array_values($result);
    }
}