<?php
class XunHu{
    private $mchid;
    private $appsecret;
    private $url;
    private $api_url_native;
    private $api_url_cashier;
    private $api_url_alipaycashier;
    private $api_url_jsapi;
    private $api_url_query;
    private $api_url_refund;

    public function __construct(array $config){
        $this->mchid        = trim($config['xunhu_mchid'], " \t\n\r\0\x0B\xC2\xA0");
        $this->appsecret    = trim($config['xunhu_appsecret'], " \t\n\r\0\x0B\xC2\xA0");
        $api_url            = isset($config['xunhu_gateway']) ? rtrim(trim($config['xunhu_gateway'], " \t\n\r\0\x0B\xC2\xA0"), '/') : 'https://admin.xunhuweb.com';

        $this->api_url_payment       = $api_url . '/pay/payment';
        $this->api_url_cashier       = $api_url . '/pay/cashier';
        $this->api_url_alipaycashier = $api_url . '/alipaycashier';
        $this->api_url_jsapi         = $api_url . '/pay/jsapi';
        // $this->api_url_query         = $api_url . '/pay/query';
        // $this->api_url_refund        = $api_url . '/pay/refund';
    }

    // 扫码支付
    public function qrcode(array $data){
        $this->url = $this->api_url_payment;
        return $this->post($data);
    }
    
    //微信H5
    public function wechat_h5(array $data){
        $this->url = $this->api_url_payment;
        return $this->post($data);
    }
    
    //支付宝2.0
    public function alipay_v2(array $data){
        $this->url = $this->api_url_payment;
        return $this->post($data);
    }
    
    // 收银台模式
    public function cashier(array $data){
        if($data['type'] == "alipay") {
            $this->url = $this->api_url_alipaycashier;
        } else {
            $this->url = $this->api_url_cashier;
        }
        
        $data['mchid'] = $this->mchid;
        $data['sign']  = $this->sign($data);
        return $this->data_link($this->url, $data);
    }
    
     /**
     * url拼接
     * @param array $url
     * @param string $datas
     */
	public function data_link($url,$datas){
		ksort($datas);
        reset($datas);
        $pre =array();
        foreach ($datas as $key => $data){
            if(is_null($data)||$data===''){
                continue;
            }

            $pre[$key]=$data;
        }

        $arg  = '';
        $qty = count($pre);
        $index=0;
		 foreach ($pre as $key=>$val){
		 		$val=urlencode($val);
			 	$arg.="$key=$val";
	            if($index++<($qty-1)){
	                $arg.="&amp;";
	            }	
        }
        return $url.'?'.$arg;
	}
    
    /**
     * 签名方法
     * @param array $datas
     */
    public function sign(array $datas){
        ksort($datas);
        reset($datas);

        $pre =array();
        foreach ($datas as $key => $data){
            if(is_null($data)||$data===''){
                continue;
            }
            if($key=='sign'){
                continue;
            }
            $pre[$key]=$data;
        }

        $arg  = '';
        $qty = count($pre);
        $index=0;

        foreach ($pre as $key=>$val){
            $arg.="$key=$val";
            if($index++<($qty-1)){
                $arg.="&";
            }
        }

        return strtoupper(md5($arg.'&key='. $this->appsecret));
    }

    // 数据发送
    public function post($data){
        $data['mchid'] = $this->mchid;
        $data['sign']  = $this->sign($data);

        $data = json_encode($data);
        
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_POST, 1);
	    curl_setopt($ch, CURLOPT_URL, $this->url);
	    curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	            'Content-Type: application/json; charset=utf-8',
	            'Content-Length: ' . strlen($data)
	        )
	    );
	    $response = curl_exec($ch);
	    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	    curl_close($ch);
	    return json_decode($response,true);
	}
}