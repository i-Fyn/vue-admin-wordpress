<?php
/**
 * 邀请码功能管理类
 * 
 * 处理邀请码的验证和使用
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;

class Invite {
    /**
     * 检查邀请码是否有效
     * 
     * @author  ifyn
     * @param   string  $inviteCode  用户输入的邀请码
     * @return  array|bool           成功返回邀请码信息数组，失败返回错误信息数组，无需邀请码时返回false
     */
    public static function checkInviteCode($inviteCode) {
        // 标准化邀请码格式（去除空格并转为大写）
        $inviteCode = strtoupper(trim($inviteCode, " \t\n\r\0\x0B\xC2\xA0"));
        
        // 获取系统邀请码设置
        $invite_type = islide_get_option('invite_code_type');
        
        // 邀请码功能未开启，用户未填邀请码
        if ($invite_type == 0 && $inviteCode == '') {
            return false;
        }
        
        // 邀请码注册未开启但用户已填写邀请码
        if ($invite_type == 0 && $inviteCode != '') {
            return array('error' => '当前不允许使用邀请码');
        }
        
        // 邀请码必填但用户未填
        if ($invite_type == 1 && $inviteCode == '') {
            return array('error' => '请输入邀请码');
        }
        
        // 邀请码选填但用户未填
        if ($invite_type == 2 && $inviteCode == '') {
            return false;
        }
        
        // 验证邀请码是否存在且有效
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_card';

        // 安全查询数据库
        $res = $wpdb->get_row(
            $wpdb->prepare("
                SELECT * FROM $table_name
                WHERE card_code = %s
                ",
                $inviteCode
            ), 
            ARRAY_A
        );
        
        // 验证查询结果
        if (empty($res)) {
            return array('error' => '邀请码不存在');
        } elseif ((int)$res['status'] === 1) {
            return array('error' => '邀请码已被使用');
        } elseif (empty($res['type']) || $res['type'] !== 'invite') {
            return array('error' => '邀请码类型错误');
        }
        
        return $res;
    }

    /**
     * 标记邀请码为已使用状态
     * 
     * @author  ifyn
     * @param   int     $user_id     使用邀请码的用户ID
     * @param   string  $inviteCode  要使用的邀请码
     * @return  mixed               成功返回处理结果，失败返回错误信息数组
     */
    public static function useInviteCode($user_id, $inviteCode) {
        // 参数验证
        if (empty($user_id) || !is_numeric($user_id)) {
            return array('error' => '用户ID无效');
        }
        
        if (empty($inviteCode)) {
            return array('error' => '邀请码不能为空');
        }
        
        $inviteCode = trim($inviteCode);
        
        // 再次检查邀请码是否有效
        $inviteInfo = self::checkInviteCode($inviteCode);
        if (isset($inviteInfo['error'])) {
            return $inviteInfo; // 邀请码无效，使用失败
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'islide_card';
        
        // 更新邀请码状态
        $result = $wpdb->update(
            $table_name, 
            array( 
                'status' => 1,
                'user_id' => (int)$user_id,
                'use_time' => current_time('mysql')
            ), 
            array('id' => (int)$inviteInfo['id']),
            array( 
                '%d',
                '%d',
                '%s'
            ), 
            array('%d') 
        );
        
        if ($result !== false) {
            // 触发邀请码使用后的钩子
            return apply_filters('islide_invite_code_used', (int)$user_id, $inviteInfo);
        }
        
        return array('error' => '网络错误，请稍后重试');
    }
}