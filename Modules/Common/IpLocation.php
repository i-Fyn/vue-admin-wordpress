<?php
/**
 * IP属地功能管理类
 * 
 * 提供多种IP归属地查询方式，并格式化IP地址信息
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;

//ip属地
class IpLocation {
    /**
     * 获取IP归属地信息
     * 
     * 根据系统设置选择合适的IP查询服务
     * 
     * @author  ifyn
     * @param   string $ip IP地址
     * @return  array      IP位置信息数组或错误信息
     */
    public static function get($ip){
        // 参数验证
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return array('error' => 'IP地址无效');
        }
        
        // 获取系统设置的IP查询服务类型
        $type = islide_get_option('ip_location_type');
        
        // 验证方法是否存在
        if (!$type || !method_exists(__CLASS__, $type)) {
            return array('error' => '位置服务商不存在');
        }
        
        // 调用对应的方法
        return self::$type($ip);
    }
    
    /**
     * 通过腾讯位置服务获取IP归属地信息
     *
     * @author  ifyn
     * @param   string $ip IP地址
     * @return  array      包含IP归属地信息的数组，或者包含错误信息的数组
     */
    public static function tencent($ip){
        // 参数验证
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return array('error' => 'IP地址无效');
        }
        
        // 获取腾讯位置服务配置
        $tencent = islide_get_option('tencent_ip_location');
        
        if (empty($tencent) || !is_array($tencent) || empty($tencent['app_key'])) {
            return array('error' => '请检查腾讯位置服务设置，缺失app_key参数');
        }
        
        // 构造请求参数
        $params = array(
            'ip' => trim($ip),
            'key' => trim($tencent['app_key']),
        );
        
        // 如果有密钥，添加签名
        if (!empty($tencent['secret_key'])) {
            $params['sig'] = md5('/ws/location/v1/ip?' . http_build_query($params) . trim($tencent['secret_key']));
        }
        
        $api = 'https://apis.map.qq.com/ws/location/v1/ip?' . http_build_query($params);
        
        // 发送请求
        $res = wp_remote_get($api, array(
            'timeout' => 5,
            'sslverify' => false
        ));
        
        if (is_wp_error($res)) {
            return array('error' => '网络错误：' . $res->get_error_message());
        }
        
        // 解析响应
        $body = wp_remote_retrieve_body($res);
        if (empty($body)) {
            return array('error' => '接口返回为空');
        }
        
        $res = json_decode($body, true);
        if (!is_array($res)) {
            return array('error' => '返回数据格式错误');
        }
        
        if ((int)$res['status'] === 0) {
            if (empty($res['result']) || empty($res['result']['ad_info'])) {
                return array('error' => '位置信息不完整');
            }
            
            $ad_info = $res['result']['ad_info'];
            
            $data = array(
                'ip'       => $ip,
                'nation'   => '', // 国家
                'province' => '', // 省份
                'city'     => '', // 城市
                'district' => '', // 区域
            );
            
            // 将默认数组和提取出的参数合并
            return array_merge($data, array_intersect_key($ad_info, $data));
        } else {
            return array('error' => isset($res['message']) ? $res['message'] : '未知错误');
        }
    }
    
    /**
     * 通过高德位置服务获取IP归属地信息
     *
     * @author  ifyn
     * @param   string $ip IP地址
     * @return  array      包含IP归属地信息的数组，或者包含错误信息的数组
     */
    public static function amap($ip){
        // 参数验证
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return array('error' => 'IP地址无效');
        }
        
        // 获取高德位置服务配置
        $amap = islide_get_option('amap_ip_location');
        
        if (empty($amap) || !is_array($amap) || empty($amap['app_key'])) {
            return array('error' => '请检查高德位置服务设置，缺失key参数');
        }
        
        // 构造请求参数
        $params = array(
            'ip' => trim($ip),
            'key' => trim($amap['app_key']),
        );
        
        // 如果有密钥，添加签名
        if (!empty($amap['secret_key'])) {
            $params['sig'] = md5(http_build_query($params) . trim($amap['secret_key']));
        }
        
        $api = 'https://restapi.amap.com/v3/ip?' . http_build_query($params);
        
        // 发送请求
        $res = wp_remote_get($api, array(
            'timeout' => 5,
            'sslverify' => false
        ));
        
        if (is_wp_error($res)) {
            return array('error' => '网络错误：' . $res->get_error_message());
        }
        
        // 解析响应
        $body = wp_remote_retrieve_body($res);
        if (empty($body)) {
            return array('error' => '接口返回为空');
        }
        
        $res = json_decode($body, true);
        if (!is_array($res)) {
            return array('error' => '返回数据格式错误');
        }

        if ((int)$res['status'] === 1) {
            if (empty($res['province'])) {
                return array('error' => '高德定位失败：非法IP或国外IP');
            }
            
            $data = array(
                'ip'       => $ip,
                'nation'   => '中国', // 国家
                'province' => '', // 省份
                'city'     => '', // 城市
                'district' => '', // 区域
            );
            
            // 将默认数组和提取出的参数合并
            return array_merge($data, array_intersect_key($res, $data));
        } else {
            $error_msg = isset($res['info']) ? $res['info'] : '未知错误';
            $error_code = isset($res['infocode']) ? $res['infocode'] : '未知状态码';
            return array('error' => "错误信息：{$error_msg}，状态码：{$error_code}");
        }
    }
    
    /**
     * 通过太平洋公共接口获取IP归属地信息
     *
     * @author  ifyn
     * @param   string $ip IP地址
     * @return  array      包含IP归属地信息的数组，或者包含错误信息的数组
     */
    public static function pconline($ip){
        // 参数验证
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return array('error' => 'IP地址无效');
        }
        
        // 发送请求
        $res = wp_remote_get('https://whois.pconline.com.cn/ipJson.jsp?json=true&ip=' . trim($ip), array(
            'timeout' => 5,
            'sslverify' => false
        ));
        
        if (is_wp_error($res)) {
            return array('error' => '网络错误：' . $res->get_error_message());
        }
        
        // 获取响应的主体内容
        $body = wp_remote_retrieve_body($res);
        if (empty($body)) {
            return array('error' => '接口返回为空');
        }
        
        // 解析JSON数据（转换编码）
        try {
            $decoded_body = iconv("GBK", "UTF-8//IGNORE", $body);
            $res = json_decode($decoded_body, true);
        } catch (\Exception $e) {
            return array('error' => '编码转换错误：' . $e->getMessage());
        }
        
        if (!is_array($res)) {
            return array('error' => '返回数据格式错误');
        }
        
        if (!empty($res) && empty($res['err'])) {
            // 构建返回数据
            $data = array(
                'ip'       => $ip,
                'nation'   => isset($res['addr']) ? $res['addr'] : '', // 国家/地区
                'province' => isset($res['pro']) ? $res['pro'] : '',  // 省份
                'city'     => isset($res['city']) ? $res['city'] : '', // 城市
                'district' => isset($res['region']) ? $res['region'] : '', // 区域
            );
            
            // 检查数据有效性
            if (empty($data['province']) && empty($data['city']) && empty($data['nation'])) {
                return array('error' => '无法获取位置信息');
            }
            
            return $data;
        } else {
            $error_msg = isset($res['err']) ? $res['err'] : '定位失败';
            return array('error' => $error_msg);
        }
    }
    
    /**
     * 生成位置字符串
     *
     * 根据提供的位置信息数据生成格式化的位置字符串
     *
     * @author  ifyn
     * @param   array  $data 包含位置信息的数组
     * @return  string       格式化后的位置字符串
     */
    public static function build_location($data) {
        // 检查是否开启IP显示功能
        $open = islide_get_option('user_ip_location_show');
        if (empty($open) || !$open) {
            return '';
        }
        
        // 检查数据有效性
        if (empty($data) || !is_array($data)) {
            return '未知';
        }
        
        // 获取显示格式设置
        $format = islide_get_option('ip_location_format');
        $format = !empty($format) ? $format : 'p';
        $location = '';
        
        // 根据格式生成位置字符串
        switch ($format) {
            case 'npc':
                $nation = isset($data['nation']) ? $data['nation'] : '';
                $province = isset($data['province']) ? $data['province'] : '';
                $city = isset($data['city']) ? $data['city'] : '';
                $location = $nation . $province . $city;
                break;
            case 'np':
                $nation = isset($data['nation']) ? $data['nation'] : '';
                $province = isset($data['province']) ? $data['province'] : '';
                $location = $nation . $province;
                break;
            case 'pc':
                $province = isset($data['province']) ? $data['province'] : '';
                $city = isset($data['city']) ? $data['city'] : '';
                $location = $province . $city;
                break;
            case 'p':
                $location = isset($data['province']) ? $data['province'] : '';
                break;
            case 'c':
                $location = isset($data['city']) ? $data['city'] : '';
                break;
            default:
                break;
        }
        
        // 如果位置为空但设置了显示国家/省/市，尝试使用国家信息
        if (!$location && in_array($format, array('pc', 'p', 'c'))) {
            if (!empty($data['nation'])) {
                $location = $data['nation'];
            }
        }
        
        // 如果仍然没有位置信息，返回未知
        if (!$location) {
            return '未知';
        }
        
        // 去除多余的行政区划名称
        $location = str_replace(
            array('省', '自治区', '市', '特别行政区'), 
            '', 
            implode('', array_unique(preg_split('//u', $location, -1, PREG_SPLIT_NO_EMPTY)))
        );
        
        return $location;
    }
}