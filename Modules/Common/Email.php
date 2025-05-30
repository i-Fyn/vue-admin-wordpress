<?php namespace islide\Modules\Common;

class Email{
    public function init(){
        add_action( 'phpmailer_init', array( $this,'email_smtp' ) );
        add_filter('wp_mail_from_name', array( $this,'mail_from_name') );
        add_filter('wp_mail_content_type', array( $this,'mail_content_type') );
    }
    
    public function email_smtp($phpmailer) {
        
        $smtp_open = islide_get_option('email_smtp_open');
        
        if(empty($smtp_open)) return;
        
        $smtp = islide_get_option('email_smtp');
        $from_name = islide_get_option('email_from_name');
        
        $phpmailer->IsSMTP();
        $phpmailer->From = $smtp['username'];
        $phpmailer->FromName = $from_name;
        $phpmailer->Sender = $phpmailer->From;
        $phpmailer->AddReplyTo($phpmailer->From,$phpmailer->FromName);
        $phpmailer->Host = $smtp['host'];
        $phpmailer->SMTPSecure = $smtp['smtp_secure'];
        $phpmailer->Port = $smtp['port'];
        $phpmailer->SMTPAuth = !!$smtp['smtp_auth'];

        if( $phpmailer->SMTPAuth ){
            $phpmailer->Username = $smtp['username'];
            $phpmailer->Password = $smtp['password'];
        }
    }
    
    public function mail_from_name($name){
        $from_name = islide_get_option('email_from_name');
        if(!empty($from_name)) {
            return $from_name;
        }
        
        return get_bloginfo('name');
    }
    
    public function mail_content_type(){
        return "text/html";
    }
    
    public function mail_from() {
        $emailaddress = 'xxx@xxx.com'; //你的邮箱地址
        return $emailaddress;
    }
}