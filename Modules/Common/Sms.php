<?php
/**
 * 短信发送功能管理类
 * 
 * 处理多种短信服务商的短信发送功能
 * 
 * @package islide\Modules\Common
 * @author  ifyn
 */
namespace islide\Modules\Common;

//短信
class Sms {
    /**
     * 发送短信验证码
     *
     * @author  ifyn
     * @param   string $phoneNumber 手机号码
     * @param   string $code        验证码
     * @return  string|array        发送成功返回提示信息，失败返回错误数组
     */
    public static function send($phoneNumber,$code){
        if (empty($phoneNumber) || empty($code)) {
            return array('error' => '手机号或验证码不能为空');
        }
        
        $sms_type = islide_get_option('sms_type');

        if(!method_exists(__CLASS__,$sms_type)) return array('error'=>'短信服务商不存在');
        
        return self::$sms_type($phoneNumber,$code);
    }
    
    /**
     * 通过阿里云发送短信
     *
     * @author  ifyn
     * @param   string $phoneNumber 不带国家码的手机号
     * @param   string $code        验证码
     * @return  string|array        发送成功返回提示信息，失败返回错误数组
     */
    public static function aliyun($phoneNumber,$code){
        if (empty($phoneNumber) || empty($code)) {
            return array('error' => '手机号或验证码不能为空');
        }
        
        $aliyun = islide_get_option('aliyun_sms');
        
        if (empty($aliyun['key_id']) || empty($aliyun['key_secret']) || empty($aliyun['sign_name']) || empty($aliyun['template_code'])) {
            return array('error' => '请检查阿里云短信设置，缺失参数');
        }
        
        $params = array(
            'Action'   => 'SendSms',
            'Version'  => '2017-05-25',
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce' => uniqid(mt_rand(0,0xffff), true),
            'SignatureVersion' => '1.0',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'Format' => 'JSON',
        );
        
        $params['AccessKeyId'] = $aliyun['key_id'];
        
        //必填: 短信接收号码
        $params['PhoneNumbers'] = $phoneNumber;

        //必填: 短信签名，应严格按'签名名称'填写，请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/sign
        $params['SignName'] = $aliyun['sign_name'];

        //必填: 短信模板Code，应严格按'模板CODE'填写, 请参考: https://dysms.console.aliyun.com/dysms.htm#/develop/template
        $params['TemplateCode'] = $aliyun['template_code'];

        //可选: 设置模板参数, 假如模板中存在变量需要替换则为必填项
        $params['TemplateParam'] = json_encode(array(
            'code' => $code,
        ));
        
        ksort($params);
        
        $sortedQueryStringTmp = "";
        foreach ($params as $key => $value) {
            $sortedQueryStringTmp .= "&" . self::aliyunEncode($key) . "=" . self::aliyunEncode($value);
        }
        
        $stringToSign = "GET&%2F&" . self::aliyunEncode(substr($sortedQueryStringTmp, 1));
        
        $sign = base64_encode(hash_hmac("sha1", $stringToSign, $aliyun['key_secret'] . "&",true));
        
        //签名
        $params ['Signature'] = $sign;
        
        $url = 'http://dysmsapi.aliyuncs.com/?' . http_build_query ( $params );
        
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "x-sdk-client" => "php/2.0.0"
        ));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $result = curl_exec ( $ch );
        curl_close ( $ch );
        
        $result = json_decode ( $result, true );

        if (isset ( $result ['Code'] ) && $result ['Code'] == 'OK') {
            return '验证码已发送至您的手机，注意查收';
        }else{
            $message = isset($result['Message']) ? $result['Message'] : '未知错误';
            $code = isset($result['Code']) ? $result['Code'] : '未知代码';
            return array('error' => $message . '。错误代码：' . $code);
        }
    }
    
    /**
     * 阿里云URL编码
     *
     * @author  ifyn
     * @param   string $str 需要编码的字符串
     * @return  string      编码后的字符串
     */
    private static function aliyunEncode($str){
        if (empty($str)) {
            return '';
        }
        
        $res = urlencode($str);
        $res = preg_replace("/\+/", "%20", $res);
        $res = preg_replace("/\*/", "%2A", $res);
        $res = preg_replace("/%7E/", "~", $res);
        return $res;
    }
    
    /**
     * 通过腾讯云发送短信
     *
     * @author  ifyn
     * @param   string $phoneNumber 不带国家码的手机号
     * @param   string $msg         验证码
     * @return  string|array        发送成功返回提示信息，失败返回错误数组
     */
    public static function tencent($phoneNumber, $msg){
        if (empty($phoneNumber) || empty($msg)) {
            return array('error' => '手机号或验证码不能为空');
        }
        
        $tencent = islide_get_option('tencent_sms');
        
        if (empty($tencent['app_id']) || empty($tencent['app_key']) || empty($tencent['sign_name']) || empty($tencent['template_id'])) {
            return array('error' => '请检查腾讯云短信设置，缺失参数');
        }
        
        // 短信应用 SDK AppID
        $appid = $tencent['app_id'];
        // 短信应用 SDK AppKey
        $appkey = $tencent['app_key'];
        // 签名参数
        $sign = $tencent['sign_name'];
        // 短信模板 ID
        $template_id = $tencent['template_id'];
        
        $random = rand(100000, 999999);
        $curTime = time();
        $wholeUrl =  'https://yun.tim.qq.com/v5/tlssmssvr/sendsms?sdkappid=' . $appid . '&random=' . $random;

        // 按照协议组织 post 包体
        $data = new \stdClass();
        $tel = new \stdClass();
        $tel->nationcode = '86'; //国家码，如 86 为中国
        $tel->mobile = ''.$phoneNumber;

        $data->tel = $tel;
        $data->sig = hash('sha256', 'appkey='.$appkey.'&random='.$random.'&time='.$curTime.'&mobile='.$phoneNumber);
        $data->tpl_id = $template_id;
        $data->params = array($msg,5); //验证码、时效
        $data->sign = $sign;
        $data->time = $curTime;
        $data->extend = ''; //扩展码，可填空串
        $data->ext = ''; //服务端原样返回的参数，可填空串

        return self::tencentSendCurlPost($wholeUrl, $data);
    }
    
    /**
     * 腾讯云短信发送POST请求
     *
     * @author  ifyn
     * @param   string    $url     请求URL
     * @param   \stdClass $dataObj 请求数据对象
     * @return  string|array       发送成功返回提示信息，失败返回错误数组
     */
    public static function tencentSendCurlPost($url, $dataObj){
        if (empty($url) || empty($dataObj)) {
            return array('error' => '请求参数不完整');
        }
        
        $curl = curl_init();
        
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($dataObj));
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);  //超时10秒
        
        $ret = curl_exec($curl);
        if ($ret === false) {
            $error = curl_error($curl);
            curl_close($curl);
            return array('error' => '网络连接错误: ' . $error);
        } else {
            $rsp = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if (200 != $rsp) {
                $error = curl_error($curl);
                curl_close($curl);
                return array('error' => $rsp.' '. $error);
            } else {
                    $result = $ret;
            }
        }

        curl_close($curl);
    
        $result = json_decode ( $result, true );
        if($result['result'] == 0) return '验证码已发送至您的手机，注意查收';

        $errmsg = isset($result['errmsg']) ? $result['errmsg'] : '未知错误';
        return array('error' => $errmsg);
    }
}