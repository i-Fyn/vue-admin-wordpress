<?php
namespace islide\Modules\Settings;
//后台备份设置

class Backup{

    //设置主KEY
    public static $prefix = 'islide_main_options';

    public function init(){ 
        
        $this->backup_options_page();
        
        //保存主题时候保存必要的wp设置
        add_action("csf_islide_main_options_saved", function(){
            $this->backup_options('auto');
        });
        
        // 注册Ajax回调函数
        add_action('wp_ajax_backup_current', array($this,'backup_current_callback'));
        add_action('wp_ajax_delete_restore_backup', array($this,'delete_restore_backup_callback'));
    }
    
    /**
     * 备份设置
     *
     * @return void
     * @version 1.0.0
     * @since 2023
     */
    public function backup_options_page(){
        
        //备份设置
        \CSF::createSection(self::$prefix, array(
            'id'     => 'islide_backup_options',
            'title'  => '备份恢复',
            'icon'   => 'fa fa-fw fa-copy',
            'fields' => array(
                array(
                    'type'    => 'submessage',
                    'style'   => 'warning',
                    'content' =>  $this->backup_settings_content()
                ),
                array(
                    'type'    => 'backup'
                ),
            )
        ));

    }
    
    public function backup_settings_content(){
        
        $backup_data  = get_option(self::$prefix . '_backup');
        

        $lists = '';
        
        if($backup_data) {
            foreach ($backup_data as $key => $value) {
                $lists .= '<div class="backup-item">
                    <div class="item-left">'.$value['date'].' 【'.$this->get_backup_type($value['type']).'】</div>
                    <div class="item-right">
                        <div class="action-restore button-primary" data-index="'.$key.'">恢复</div>
                        <div class="action-delete button csf-warning-primary" data-index="'.$key.'">删除</div>
                    </div>
                </div>';
            }
        }
    
        return '
            <h3 style="color:#fd4c73;">
                <i class="csf-tab-icon fa fa-fw fa-copy"></i> 备份及恢复
            </h3>
            <div style="margin:10px 0">
                <p>系统会在重置、更新等重要操作时自动备份主题设置，您可以此进行恢复备份或手动备份</p>
                <p>恢复备份后，请先保存一次主题设置，然后刷新后再做其它操作！</p>
                <p><b>备份列表：</b></p>
                <div id="backup-list">'.$lists.'</div>
            </div>
            <div class="backup-current-btn button-primary">备份当前配置</div>
            <div class="ajax-notice" style="margin-top: 10px;"></div>
        ';
    }
    
    public function get_backup_type($type) {
        $types = array(
            'auto' => '自动备份',
            'manual' => '手动备份',
        );
        
        return isset($types[$type]) ? $types[$type] : '';
    }
    
    public function backup_options($type) {
        // 获取当前时间
        $current_time = current_time('timestamp');
        
        // 获取备份数据
        $backup_data = get_option(self::$prefix.'_backup');
        if (!$backup_data) {
            $backup_data = array();
        }
        
        // 获取上次备份时间
        $last_backup_time = 0;
        foreach ($backup_data as $backup) {
            if (isset($backup['date']) && $backup['type'] === $type) {
                $last_backup_time = strtotime($backup['date']);
                break;
            }
        }
        
        // 如果上次备份时间不存在或距离当前时间超过一天，则进行备份
        if (!$last_backup_time || ($current_time - $last_backup_time) > 86400 || in_array($type, array('manual', 'click'))) {
            // 添加当前设置到备份数据中
            $options = get_option(self::$prefix);
            if ($options) {
                
                //开头插入数据
                array_unshift($backup_data, array(
                    'date' => date('Y-m-d H:i:s', $current_time),
                    'type' => $type,
                    'data' => $options,
                ));
                
                // 保留最近的10个备份数据
                $backup_data = array_slice($backup_data, 0, 5);
                
                // 更新备份数据
                update_option(self::$prefix.'_backup', $backup_data);
                
                return true;
            }
        }
        
        return false;
    }
    
    //手动备份
    public function backup_current_callback() {
        // 检查用户权限
        if (!current_user_can('manage_options')) {
            // 返回错误消息
            wp_send_json_error('你没有执行此操作的权限');
        }
        
        if($this->backup_options('manual')) {
            // 返回成功消息
            wp_send_json_success('备份当前设置成功');
        }else {
            // 返回错误消息
            wp_send_json_error('备份当前设置失败');
        }
        
    }
     /**
     * 删除或恢复备份的回调函数。
     * 
     * 此函数检查用户权限并对备份数据执行请求的操作。
     *
     * @return void
     */
    public function delete_restore_backup_callback() {
        // 检查用户权限
        if (!current_user_can('manage_options')) {
            // 返回错误消息
            wp_send_json_error('你没有执行此操作的权限');
        }
        
        // 获取备份数据
        $backup_data = get_option(self::$prefix.'_backup');
        
        // 获取要操作的备份索引
        $backup_index = isset($_REQUEST['backup_index']) ? intval($_REQUEST['backup_index']) : -1;
        
        if ($backup_index >= 0 && $backup_index < count($backup_data)) {
            // 获取要操作的备份数据
            $backup = $backup_data[$backup_index];
            
            // 判断操作类型
            $action = isset($_REQUEST['_action']) ? $_REQUEST['_action'] : '';
            
            if ($action == 'delete') {
                // 判断备份数据的数量是否小于等于3
                if (count($backup_data) <= 3) {
                    // 返回错误消息
                    wp_send_json_error('至少保留三份备份');
                }
                
                // 删除指定备份
                array_splice($backup_data, $backup_index, 1);
                
                // 更新备份数据
                update_option(self::$prefix.'_backup', $backup_data);
                
                // 返回成功消息
                wp_send_json_success('删除'.$backup['date'].'备份成功');
            } elseif ($action == 'restore') {
                // 恢复备份数据
                update_option(self::$prefix, $backup['data']);
                
                // 返回成功消息
                wp_send_json_success('恢复'.$backup['date'].'备份成功');
            } else {
                // 返回错误消息
                wp_send_json_error('无效的操作类型');
            }
        } else {
            // 返回错误消息
            wp_send_json_error('无效的备份索引');
        }
    }
}