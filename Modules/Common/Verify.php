<?php namespace islide\Modules\Common;
use islide\Modules\Filter;

class Verify {
    
    /**
     * 获取认证信息
     *
     * @author ifyn
     * @return array 认证信息数组
     */
    public static function get_verify_info(){

        $verify_open = islide_get_option('verify_open');

        if(!$verify_open) return array('error'=>'认证服务已关闭，无法申请认证');

        $user_id = get_current_user_id();
        
        //if(!$user_id) return array('error'=>'请先登录');
        
        $verify_group = islide_get_option('verify_group');
        
        if(empty($verify_group)) return array('error'=>'请先在后台设置认证相关数据');
        
        foreach ($verify_group as $key => &$value) {
            $value['conditions'] = isset($value['conditions']) ? $value['conditions'] : array(); 
            foreach ($value['conditions'] as $k => &$v){
                $v['allow'] = self::check_verify_condition($user_id,$v,$value['type']);
            }
        }
        
        return $verify_group;
        
    }
    
    /**
     * 获取用户认证信息
     *
     * @author ifyn
     * @return array 认证信息数组
     */
    public static function get_user_verify_info(){
        
        $user_id = get_current_user_id();

        // if(!$user_id) return array('error'=>'请先登录');
        
       //获取当前用户的认证数据
        $data = self::get_verify_data($user_id);
        
        return $data;
        
    }
    
    /**
     * 提交认证申请
     *
     * @author ifyn
     * @param array $data 认证数据
     * @return array|string 认证结果
     */
    public static function submit_verify($data){
        
        $verify_open = islide_get_option('verify_open');
        
        if(!$verify_open) return array('error'=>'认证服务已关闭，无法申请认证');
        
        $user_id = get_current_user_id();

        if(!$user_id) return array('error'=>'请先登录');
        
      //  $data = apply_filters('islide_submit_verify_before',$user_id,$data);
        if(isset($data['error'])) return array('error' => $data['error']);
        
        //认证信息数组
        $verify_group = islide_get_option('verify_group');
        if(empty($verify_group)) return array('error'=>'请先在后台设置认证相关数据');
        
        //检查认证类型
        $index = array_search($data['type'], array_column($verify_group, 'type'));
   
        if($index === false || $index != $data['index']) return array('error'=>'不存在此认证类型');
        
        $verify = $verify_group[$index];
        
        //检查认证基础认证条件是否通过
        if(!empty($verify['conditions']) && is_array($verify['conditions'])) {
            foreach ($verify['conditions'] as $key => $value) {
                if(!self::check_verify_condition($user_id,$value,$verify['type'])) return array('error'=>sprintf('你存在基础条件%s不通过，无法申请认证',$value['name']));
            }
        }
        
        //检查认证标题名称
        $data['title'] = sanitize_text_field(wp_unslash(str_replace(array('{{','}}'),'',wp_strip_all_tags($data['title']))));
        if(empty($data['title'])) return array('error' => '请填写认证标题');
        if(islideGetStrLen($data['title']) > 30) return array('error' => '认证标题太长，请限制在1-30个字符之内');
        
        //检查参数
        $document = self::check_verify_document($verify,$data);
        if(isset($document['error'])) return $document;

        // 0 为人工审核信息 1 为自动审核信息
        $verify_check = !!(int)$verify['verify_check'];
        
        //准备参数
        $args = array(
            'user_id' => $user_id,
            'type' => $data['type'],
            'title' => $data['title'],
            'verified' => 0, //是否实名
            'status' => $verify_check ? 1 : 0, //0为待审核 1为审核通过 2审核未通过 3已支付
            'date' => current_time('mysql'),
            'money'=>Filter::get_order_price_by_user_and_value($user_id,0,$data['type']),
            'credit'=>Filter::get_order_price_by_user_and_value($user_id,1,$data['type']),
            'data' => !empty($document) ? maybe_serialize($document) : ''
        );
        
        if(self::update_verify_data($args)) {
            
            do_action('islide_submit_verify_success',$user_id,$data);
            
            if($verify_check) {
                
                update_user_meta($user_id,'islide_verify',$data['title']);
                update_user_meta($user_id,'islide_verify_type',$data['type']);
                
                do_action('islide_submit_verify_check_success',$user_id,$data);
                
                return 'success';
            }
            
            return 'pending';
        }
        
        return array('error'=>'服务错误请重新尝试');
    }
    
    /**
     * 获取用户认证数据
     *
     * @author ifyn
     * @param int $user_id 用户ID
     * @return array 用户认证数据数组
     */
    public static function get_verify_data($user_id){
        global $wpdb;
        $table_name = isset($wpdb->prefix) ? $wpdb->prefix . 'islide_verify' : '';
        if (empty($table_name)) return array();

        $res = is_object($wpdb) && method_exists($wpdb, 'get_row') ? $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id), ARRAY_A
        ) : false;
        
        $default = array(
            'user_id' => 0,
            'type' => '',
            'title' => '',
            'money' => 0,
            'credit' => 0,
            // 'verified' => 0,
            'status' => 0, 
            'date' => current_time('mysql'),
            'data' => '',
            'step' => 1, //步骤
            // 'value' => '',
            'opinion' => ''
        );
        
        if(!$res){
            return $default;
        }
        
        // 将合并默认数组和结果数组中与默认数组键相同的元素，并返回合并后的数组
        $data = array_merge($default, array_intersect_key($res, $default));
        
        $data['data'] = maybe_unserialize($data['data']);
        $data['data'] = empty($data['data']) ? new \stdClass : $data['data'];
        
        switch ($data['status']) {
            case '0':
                $data['step'] = 3;
                break;
            case '1':
            case '2':
                $data['step'] = 4;
                break;
        }
        
        return array_map(function($value) {
            return is_numeric($value) ? (int)$value : $value;
        }, $data);
    }
    
    /**
     * 更新用户认证数据
     *
     * @author ifyn
     * @param array $data 认证数据
     * @return bool 是否成功
     */
    public static function update_verify_data($data){
        
        if(empty($data['type'])) return false;

        global $wpdb;
        $table_name = isset($wpdb->prefix) ? $wpdb->prefix . 'islide_verify' : '';
        if (empty($table_name)) return false;
        
        $verify_id = is_object($wpdb) && method_exists($wpdb, 'get_var') ? $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE user_id = %d", $data['user_id'])) : false;

        if (!empty($verify_id)) {
            
            $where = array(
                'id' => $verify_id,
                'user_id' => $data['user_id'],
            );
            
            if(is_object($wpdb) && method_exists($wpdb, 'update') && $wpdb->update( $table_name , $data , $where )) {
                return true;
            };
            
        }else {
            
            $default = array(
                'user_id' => 0,
                'type' => '',
                'title' => '',
                'money' => 0,
                'credit' => 0,
                'verified' => 0,
                'status' => 0, 
                'date' => current_time('mysql'),
                'data' => '',
                'value' => '',
                'opinion' => ''
            );
    
            $args = wp_parse_args( $data ,$default );
            
            $format = array(
                '%d', // user_id
                '%s', // type
                '%s', // title
                '%d', // money
                '%d', // credit
                '%d', // verified
                '%d', // status
                '%s',  // date
                '%s', // data
                '%s', // value
                '%s' // opinion
            );
            
            if(is_object($wpdb) && method_exists($wpdb, 'insert') && $wpdb->insert( $table_name, $args, $format)) {
                return true;
            };
        }
        
        return false;
    }
    
    /**
     * 检查认证条件是否完成
     *
     * @author ifyn
     * @param int $user_id 用户ID
     * @param array $condition 认证条件数组
     * @param string $type 认证类型
     * @return bool 是否通过认证条件
     */
    public static function check_verify_condition($user_id, $condition, $type) {
        $completed_count = (int)apply_filters('islide_user_verify_condition_value',$user_id,$condition['key'],$type);
        return ($completed_count >= (int)$condition['value'] && $completed_count !== 0);
    }
    
    /**
     * 检查认证条件是否完成
     *
     * @author ifyn
     * @param int $user_id 用户ID
     * @param array $condition 认证条件数组
     * @return bool 是否通过认证条件
     */
    public static function check_verify_document($verify, $data) {
        
        $default = array(  
            'title' => '', // 认证信息  
            'company' => '', // 公司名称  
            'credit_code' => '', // 信用代码  
            'business_license' => '', // 营业执照  
            'business_auth' => '', // 认证申请公函  
            'official_site' => '', // 官方网站  
            'supplement' => '', // 补充资料  
            'operator' => '', // 运营者  
            'email' => '',  
            'telephone' => '', // 运营者手机号  
            'id_card' => '', // 身份证号  
            'idcard_hand' => '', // 手持身份证  
            'idcard_front' => '', // 身份证正面  
            'idcard_verso' => '', // 身份证背面  
        );
        
        $data = array_merge($default, array_intersect_key($data, $default));
        
        if(!empty($verify['verify_info_types']) && is_array($verify['verify_info_types'])) {
            $types = $verify['verify_info_types'];
            
            foreach ($types as $value) {
                if($value == 'personal') {
                    $data['operator'] = sanitize_text_field(wp_unslash(str_replace(array('{{','}}'),'',wp_strip_all_tags($data['operator']))));
                    
                    if(islideGetStrLen($data['operator']) > 6 || empty($data['operator'])) return array('error' => '姓名应在2位到6位之间');
                    
                    if(!is_email($data['email']) && !empty($data['email'])) return array('error' => '请输入正确的邮箱地址');
                    $data['email'] = sanitize_email($data['email']);
                    
                    if(!preg_match("/^1[3456789]{1}\d{9}$/", $data['telephone'])) return array('error' => '请输入正确的手机号码');
                    
                    // if(!self::validation_filter_id_card($data['id_card'])) return array('error' => '身份证号码错误');
                    
                    $data['idcard_front'] = esc_url($data['idcard_front']);
                    if(empty($data['idcard_front']) || !attachment_url_to_postid($data['idcard_front']))  return array('error' => '请上传身份证正面照');
                    
                    $data['idcard_verso'] = esc_url($data['idcard_verso']);
                    if(empty($data['idcard_verso']) || !attachment_url_to_postid($data['idcard_verso']))  return array('error' => '请上传身份证背面照');
                    
                    $data['idcard_hand'] = esc_url($data['idcard_hand']);
                    if(empty($data['idcard_hand']) || !attachment_url_to_postid($data['idcard_hand']))  return array('error' => '请上传手持身份证照');
                    
                    
                }elseif ($value == 'official') {
                    $data['company'] = sanitize_text_field(wp_unslash(str_replace(array('{{','}}'),'',wp_strip_all_tags($data['company']))));
                    if(empty($data['company'])) return array('error' => '请输入公司名称');
                    
                    if(!preg_match("/^[a-z\d]*$/i",$data['credit_code']) || empty($data['credit_code'])) return array('error' => '请输入正确的统一社会信用代码');
                    
                    $data['business_license'] = esc_url($data['business_license']);
                    if(empty($data['business_license']) || !attachment_url_to_postid($data['business_license']))  return array('error' => '请上传营业执照');
                    
                    $data['business_auth'] = esc_url($data['business_auth']);
                    if(empty($data['business_auth']) || !attachment_url_to_postid($data['business_auth']))  return array('error' => '请上传认证申请公函');
                    
                    $data['supplement'] = esc_url(sanitize_text_field(trim($data['supplement'])));
                    $data['official_site'] = esc_url(sanitize_text_field(trim($data['official_site'])));
                }
            }
            
            //过滤空值
            $data = array_filter($data);
            ksort($data);
            
            return $data;
        }
        
        return '';
        
    }
    
    /**
     * 校验身份证号码
     *
     * @author ifyn
     * @param string $id 身份证号码
     * @return bool 是否有效
     */
    public static function validation_filter_id_card($id){
        // return true;
        $id = strtoupper($id);
        $regx = "/(^\d{15}$)|(^\d{17}([0-9]|X)$)/";
        
        $arr_split = [];
        if(!preg_match($regx, $id)){
            return false;
        }
        
        if(15==strlen($id)){
            // 检查15位
            $regx = "/^(\d{6})+(\d{2})+(\d{2})+(\d{2})+(\d{3})$/";
            
            @preg_match($regx, $id, $arr_split);
            // 检查生日日期是否正确
            $dtm_birth = "19" . $arr_split[2] . '/' . $arr_split[3] . '/' . $arr_split[4];

            if($arr_split[2] < 71 || $arr_split[3] > 12 || $arr_split[4] > 31) return false;

            if(!wp_strtotime($dtm_birth)){
                return false;
            }else{
                return true;
            }
        }else{
            
            // 检查18位
            $regx = "/^(\d{6})+(\d{4})+(\d{2})+(\d{2})+(\d{3})([0-9]|X)$/";
            @preg_match($regx, $id, $arr_split);
            
            $dtm_birth = $arr_split[2] . '/' . $arr_split[3] . '/' . $arr_split[4];
            
            if($arr_split[2] < 1971 || $arr_split[3] > 12 || $arr_split[4] > 31) return false;
            
            //检查生日日期是否正确
            if(!wp_strtotime($dtm_birth)) {
                return false;
            }else{
                
                //检验18位身份证的校验码是否正确。
                //校验位按照ISO 7064:1983.MOD 11-2的规定生成，X可以认为是数字10。
                $arr_int = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
                $arr_ch = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
                $sign = 0;
                
                for ( $i = 0; $i < 17; $i++ ){
                    $b = (int) $id[$i];
                    $w = $arr_int[$i];
                    $sign += $b * $w;
                }
                $n = $sign % 11;
                $val_num = $arr_ch[$n];
                
                if ($val_num != substr($id,17, 1)){
                    return false;
                }else{
                    return true;
                }
            }
        }
    }
    
   /**
    * 获取认证列表（分页）
    *
    * @author ifyn
    * @param array $request 请求参数
    * @return array 认证列表及分页信息
    */
   public static function islide_get_verify_list($request) {
    global $wpdb;
    $table_name = isset($wpdb->prefix) ? $wpdb->prefix . 'islide_verify' : '';
    if (empty($table_name)) return array('data'=>[], 'pages'=>0, 'count'=>0, 'paged'=>1);

    // 获取分页参数
    $paged = isset($request['paged']) ? max(1, intval($request['paged'])) : 1;
    $per_page = isset($request['size']) ? max(1, intval($request['size'])) : 10;
    $offset = ($paged - 1) * $per_page;

    // 获取总数
    $total = is_object($wpdb) && method_exists($wpdb, 'get_var') ? $wpdb->get_var("SELECT COUNT(*) FROM $table_name") : 0;
    $pages = $per_page > 0 ? ceil($total / $per_page) : 0;

    // 获取数据
    $results = is_object($wpdb) && method_exists($wpdb, 'get_results') ? $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table_name ORDER BY date DESC LIMIT %d OFFSET %d", $per_page, $offset),
        ARRAY_A
    ) : array();

    // 字段映射
    $field_map = array(
        'id'       => 'ID',
        'status'   => '认证状态',
        'user_id'  => '认证用户',
        'type'     => '认证类型',
        'title'    => '认证名称',
        'money'    => '认证金额费用',
        'credit'   => '认证积分费用',
        'verified' => '是否实名',
        'date'     => '申请时间',
        'data'     => '认证资料',
        'opinion'  => '审核意见'
    );

    $data = array();

    foreach ($results as $row) {
    $item = [];

    foreach ($field_map as $key => $label) {
        if ($key === 'data') {
            $item['data'] = function_exists('maybe_unserialize') ? maybe_unserialize($row['data']) : $row['data'];
        } elseif (in_array($key, ['money', 'credit'])) {
            $item[$key] = is_numeric($row[$key]) ? (int)$row[$key] : 0; // 转换为 int
        } else {
            $item[$key] = isset($row[$key]) ? $row[$key] : '';
        }
    }

    // 添加用户昵称
    $user = function_exists('get_userdata') ? get_userdata($row['user_id']) : null;
    $item['name'] = $user && isset($user->display_name) ? $user->display_name : '未知用户';

    $data[] = $item;
}

    return array(
        'data'  => $data,
        'pages' => $pages,
        'count' => intval($total),
        'paged' => $paged,
    );
}

}