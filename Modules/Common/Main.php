<?php namespace islide\Modules\Common;

class Main{

    public function init(){ 
        //系统优化
        $optimize = new Optimize();
        $optimize->init();
        
        //邮件SMTP
        $email = new Email();
        $email->init();
        

        //用户登录与注册
        $login = new Login();
        $login->init();

        //用户相关
        $user = new User();
        $user->init();


        $circle = new Circle();
        $circle->init();
        
        
        //客户端
        $user_agent = new UserAgent();
        $user_agent->init();
        
        //用户相关的数字变化记录
        $record = new Record();
        $record->init();

        //文件上传
        $fileUpload = new FileUpload();
        $fileUpload->init();

        //文章相关
        $post = new Post();
        $post->init();

        //短代码
        $shortcode = new ShortCode();
        $shortcode->init();

        //评论相关函数
        $comment = new Comment();
        $comment->init();

        //播放器
        $Player = new Player();
        $Player->init();

        //seo
        $seo = new Seo();
        $seo->init();
     

        //订单管理
        $orders = new Orders();
        $orders->init();

        //消息通知
        $message = new Message();
        $message->init();

        //任务
        $task = new Task();
        $task->init();

        //分销
        $distribution = new Distribution();
        $distribution->init();
        // print_r(wp_create_nonce('wp_rest'));
        //rest api
        $resapi = new RestApi();
        $resapi->init();

        // $cache = new Cache();
        // $cache->init();
        
        //前台编辑器按钮
        $editor = new Editor();
        $editor->init();
        
        $shop = new Shop();
        $shop->init();
        
        $notice = new Notice();
        $notice->init();
        
        $book = new Book();
        $book->init();
        
    }
}