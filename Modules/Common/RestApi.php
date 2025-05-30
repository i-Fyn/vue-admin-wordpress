<?php namespace islide\Modules\Common;

use islide\Modules\Templates\Modules\Posts;
use islide\Modules\Common\Post;
use islide\Modules\Common\User;
use islide\Modules\Common\Signin;
use islide\Modules\Common\FileUpload;
use islide\Modules\Common\Comment;
use islide\Modules\Common\Pay;
use islide\Modules\Common\Danmaku;
use islide\Modules\Common\Oauth;
use islide\Modules\Common\Invite;
use islide\Modules\Common\Report;
use islide\Modules\Common\Shop;
use islide\Modules\Templates\Single;
use islide\Modules\Common\ShortCode;
use islide\Modules\Common\Seo;
use islide\Modules\Common\Notice;
use islide\Modules\Common\Verify;
use islide\Modules\Common\Card;
use islide\Modules\Common\IpLocation;
use islide\Modules\Common\Book;
use islide\Modules\Common\FriendLink;


class RestApi{

    public function init(){
        add_action( 'rest_api_init', array($this,'islide_rest_regeister'));
    }
    
    public function islide_rest_regeister(){

        /************************************ 登录与注册开始 ************************************************/
        //用户注册
        register_rest_route('islide/v1','/regeister',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'regeister'),
            'permission_callback' => '__return_true'
        ));
        
        //用户登出
        register_rest_route('islide/v1','/loginOut',array(
            'methods'=>'get',
            'callback'=>array('islide\Modules\Common\Login','login_out'),
            'permission_callback' => '__return_true'
        ));
        
        //发送短信或者邮箱验证码
        register_rest_route('islide/v1','/sendCode',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'sendCode'),
            'permission_callback' => '__return_true'
        ));
        
        //获取允许的社交登录
        register_rest_route('islide/v1','/getEnabledOauths',array(
            'methods'=>'get',
            'callback'=>array(__CLASS__,'getEnabledOauths'),
            'permission_callback' => '__return_true'
        ));
        
        //获取登录设置
        register_rest_route('islide/v1','/getLoginSettings',array(
            'methods'=>'get',
            'callback'=>array('islide\Modules\Common\Login','get_login_settings'),
            'permission_callback' => '__return_true'
        ));
        
        //社交登录
        register_rest_route('islide/v1','/socialLogin',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'socialLogin'),
            'permission_callback' => '__return_true'
        ));
        
        //检查邀请码
        register_rest_route('islide/v1','/checkInviteCode',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'checkInviteCode'),
            'permission_callback' => '__return_true'
        ));
        
        //绑定登录
        register_rest_route('islide/v1','/bindingLogin',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'bindingLogin'),
            'permission_callback' => '__return_true'
        ));
        
        //重设密码
        register_rest_route('islide/v1','/resetPassword',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'resetPassword'),
            'permission_callback' => '__return_true'
        ));
        /************************************ 登录与注册结束 ************************************************/
        
        
        
        //获取菜单
        register_rest_route('islide/v1','/getMenus',array(
          'methods' =>'get',
          'callback' =>array(__CLASS__,'get_all_menu_tree'),
          'permission_callback'=>'__return_true'
        ));
        
        //获取首页模块
        register_rest_route('islide/v1','/getIndexModules',array(
          'methods' =>'get',
          'callback' =>array(__CLASS__,'getIndexModules'),
          'permission_callback'=>'__return_true'
        ));
        
        
        
        register_rest_route('islide/v1','/getPostListV2',array(
          'methods' =>'post',
          'callback' =>array(__CLASS__,'getPostListV2'),
          'permission_callback'=>'__return_true'
        ));
        
        
        register_rest_route('islide/v1','/widget',array(
          'methods' =>'post',
          'callback' =>array(__CLASS__,'get_widget_slug'),
          'permission_callback'=>'__return_true'
        ));
        
        
        register_rest_route('islide/v1','/getPostDetail',array(
          'methods' =>'get',
          'callback' =>array(__CLASS__,'getPostDetail'),
          'permission_callback'=>'__return_true'
        ));
        
        
        register_rest_route('islide/v1', '/posts', [
            'methods' => 'POST',
            'callback' => array(__CLASS__,'get_custom_posts'),
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('islide/v1', '/posts/simple', [
            'methods' => 'POST',
            'callback' => array(__CLASS__,'get_custom_posts_simple'),
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route('islide/v1', '/detail/posts', [
            'methods' => 'get',
            'callback' => array(__CLASS__,'get_single_post_data'),
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route('islide/v1', '/edit/post', [
            'methods' => 'get',
            'callback' => array(__CLASS__,'get_single_post_edit_data'),
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route('islide/v1', '/test', [
            'methods' => 'post',
            'callback' => array(__CLASS__,'test'),
            'permission_callback' => '__return_true',
        ]);
        
        
        register_rest_route('islide/v1', '/detail/taxonomy', [
            'methods' => 'get',
            'callback' => array(__CLASS__,'get_tax_data'),
            'permission_callback' => '__return_true',
        ]);
        
        
        register_rest_route('islide/v1', '/agreement', [
            'methods' => 'get',
            'callback' => array('islide\Modules\Common\Shop','get_login_agreement'),
            'permission_callback' => '__return_true',
        ]);
        
        
        register_rest_route('islide/v1', '/user', [
            'methods' => 'get',
            'callback' => array(__CLASS__,'get_user_public_data'),
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route('islide/v1', '/public/user', [
            'methods' => 'get',
            'callback' => array(__CLASS__,'get_user_public_data'),
            'permission_callback' => '__return_true',
        ]);
        
        
        register_rest_route('islide/v1', '/postStats', [
            'methods' => 'get',
            'callback' => array('islide\Modules\Common\Shop','get_user_posts_stats'),
            'permission_callback' => '__return_true',
        ]);
        
        
         register_rest_route('islide/v1', '/home', [
            'methods' => 'get',
            'callback' => array(__CLASS__,'get_site_info'),
            'permission_callback' => '__return_true',
        ]);
        
        
         register_rest_route('islide/v1', '/deepseek/summary', [
            'methods' => 'get',
            'callback' => array(__CLASS__,'deepseek_generate_summary'),
            'permission_callback' => '__return_true',
        ]);
        
        register_rest_route('islide/v1', '/page/links', [
            'methods' => 'get',
            'callback' => array('islide\Modules\Common\FriendLink','get_link_categories_and_links'),
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('islide/v1', '/apply/friend', [
            'methods' => 'POST',
            'callback' => array('islide\Modules\Common\FriendLink','add_friend_link'),
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('islide/v1', '/author/stats', [
            'methods' => 'get',
            'callback' => array(__CLASS__,'get_author_stats'),
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('islide/v1', '/config', [
            'methods' => 'get',
            'callback' => array(__CLASS__,'get_config'),
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('islide/v1', '/post/prenext', [
            'methods' => 'get',
            'callback' => array(__CLASS__,'get_post_prenext'),
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('islide/v1', '/circle/sidebar', [
            'methods' => 'get',
            'callback' => array(__CLASS__,'get_circle_sideba'),
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('islide/v1','/getUserPostCapabilities',array(
            'methods'=>'get',
            'callback'=>array(__CLASS__,'getUserPostCapabilities'),
            'permission_callback' => '__return_true'
        ));
        register_rest_route('islide/v1','/getSecureInfo',array(
            'methods'=>'get',
            'callback'=>array(__CLASS__,'getSecureInfo'),
            'permission_callback' => '__return_true'
        ));
        register_rest_route('islide/v1','/getNewNoticeList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getNewNoticeList'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('islide/v1', '/getVerifyList', array(
        'methods'  => 'post',
        'callback'=>array(__CLASS__,'getVerifyList'),
        'permission_callback' => function () {
             return current_user_can('administrator');
           },
    ));
        
        
        
        
    
        
        
        



        
        
        
        
        
        
        /************************************ 文章相关 ************************************************/
        //发布文章
        register_rest_route('islide/v1','/insertPost',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'insertPost'),
            'permission_callback' => '__return_true'
        ));
        
        //获取文章模块内容（分页显示）
        register_rest_route('islide/v1','/getPostList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getPostList'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('islide/v1','/getModulePostList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getModulePostList'),
            'permission_callback' => '__return_true'
        ));
        
        //图片视频文件上传
        register_rest_route('islide/v1','/fileUpload',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'fileUpload'),
            'permission_callback' => '__return_true'
        ));
        
        //文章点赞
        register_rest_route('islide/v1','/postVote',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'postVote'),
            'permission_callback' => '__return_true'
        ));
        
        //发表评论
        register_rest_route('islide/v1','/sendComment',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'sendComment'),
            'permission_callback' => '__return_true'
        ));
        //删除评论
        register_rest_route('islide/v1','/deleteComment',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'deleteComment'),
            'permission_callback' => '__return_true'
        ));
        
        //获取评论
        register_rest_route('islide/v1','/getCommentList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getCommentList'),
            'permission_callback' => '__return_true'
        ));
        
        //评论投票
        register_rest_route('islide/v1','/CommentVote',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'CommentVote'),
            'permission_callback' => '__return_true'
        ));
        
        //评论投票
        register_rest_route('islide/v1','/getEmojiList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getEmojiList'),
            'permission_callback' => '__return_true'
        ));
        
        //获取用户的评论列表
        register_rest_route('islide/v1','/getUserCommentList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getUserCommentList'),
            'permission_callback' => '__return_true'
        ));
        
        //获取用户动态列表
        register_rest_route('islide/v1','/getUserDynamicList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getUserDynamicList'),
            'permission_callback' => '__return_true'
        ));
        
        //获取文章下载数据
        register_rest_route('islide/v1','/getDownloadData',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getDownloadData'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('islide/v1','/getDownload',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getDownload'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('islide/v1','/getDownloadFile',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getDownloadFile'),
            'permission_callback' => '__return_true'
        ));
        
        
        //投诉与举报
        register_rest_route('islide/v1','/getReportTypes',array(
            'methods'=>'get',
            'callback'=>array('islide\Modules\Common\Report','get_report_types'),
            'permission_callback' => '__return_true'
        ));
        
        //投诉与举报
        register_rest_route('islide/v1','/postReport',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'postReport'),
            'permission_callback' => '__return_true'
        ));
        
        /****************************************课程视频相关**************************************************/
        //获取视频章节播放列表
        register_rest_route('islide/v1','/getVideoList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getVideoList'),
            'permission_callback' => '__return_true'
        ));
        register_rest_route('islide/v1','/getPassageList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getPassageList'),
            'permission_callback' => '__return_true'
        ));
        
        
        /************************************ 用户相关 ************************************************/
        //关注与取消关注
        register_rest_route('islide/v1','/userFollow',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'userFollow'),
            'permission_callback' => '__return_true'
        ));
        
        //检查是否已经关注
        register_rest_route('islide/v1','/checkFollow',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'checkFollow'),
            'permission_callback' => '__return_true'
        ));
        
        //获取用户粉丝列表
        register_rest_route('islide/v1','/getFansList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getFansList'),
            'permission_callback' => '__return_true'
        ));
        
        //获取用户关注列表
        register_rest_route('islide/v1','/getFollowList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getFollowList'),
            'permission_callback' => '__return_true'
        ));
        
        //获取当前用户的附件
        register_rest_route('islide/v1','/getCurrentUserAttachments',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getCurrentUserAttachments'),
            'permission_callback' => '__return_true'
        ));
        
        //文章收藏
        register_rest_route('islide/v1','/userFavorites',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'userFavorites'),
            'permission_callback' => '__return_true'
        ));
        
        //获取用户收藏列表
        register_rest_route('islide/v1','/getUserFavoritesList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getUserFavoritesList'),
            'permission_callback' => '__return_true'
        ));
        
        //获取用户文章列表
        register_rest_route('islide/v1','/getUserPostList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getUserPostList'),
            'permission_callback' => '__return_true'
        ));
        
        //获取vip信息
        register_rest_route('islide/v1','/getVipInfo',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getVipInfo'),
            'permission_callback' => '__return_true'
        ));
        
        //获取用户充值余额与积分设置信息
        register_rest_route('islide/v1','/getRechargeInfo',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getRechargeInfo'),
            'permission_callback' => '__return_true'
        ));
        
        //获取用户等级成长信息
        register_rest_route('islide/v1','/getUserLvInfo',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getUserLvInfo'),
            'permission_callback' => '__return_true'
        ));
        
        //修改密码
        register_rest_route('islide/v1','/changePassword',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'changePassword'),
            'permission_callback' => '__return_true'
        ));
        
        //修改当前用户邮箱或手机号
        register_rest_route('islide/v1','/changeEmailOrPhone',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'changeEmailOrPhone'),
            'permission_callback' => '__return_true'
        ));
        
        //获取用户设置项信息
        register_rest_route('islide/v1','/getUserSettings',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getUserSettings'),
            'permission_callback' => '__return_true'
        ));
        
        //保存用户信息
        register_rest_route('islide/v1','/saveUserInfo',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'saveUserInfo'),
            'permission_callback' => '__return_true'
        ));
        
        //保存用户头像
        register_rest_route('islide/v1','/saveAvatar',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'saveAvatar'),
            'permission_callback' => '__return_true'
        ));
        
        //保存用户头像
        register_rest_route('islide/v1','/saveCover',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'saveCover'),
            'permission_callback' => '__return_true'
        ));
        
        //用户签到
        register_rest_route('islide/v1','/userSignin',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'userSignin'),
            'permission_callback' => '__return_true'
        ));
        
        //获取用户签到信息
        register_rest_route('islide/v1','/getUserSignInfo',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getUserSignInfo'),
            'permission_callback' => '__return_true'
        ));
        
        //获取用户的订单
        register_rest_route('islide/v1','/getUserOrders',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getUserOrders'),
            'permission_callback' => '__return_true'
        ));
        
        //获取任务列表数据
        register_rest_route('islide/v1','/getTaskData',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getTaskData'),
            'permission_callback' => '__return_true'
        ));
        
        //获取积分、余额记录
        register_rest_route('islide/v1','/getUserRecords',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getUserRecords'),
            'permission_callback' => '__return_true'
        ));
        
        //解除绑定社交账户
        register_rest_route('islide/v1','/unBinding',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'unBinding'),
            'permission_callback' => '__return_true'
        ));
        
        //提现申请
        register_rest_route('islide/v1','/cashOut',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'cashOut'),
            'permission_callback' => '__return_true'
        ));
        
        //保存用户提现收款二维码
        register_rest_route('islide/v1','/saveQrcode',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'saveQrcode'),
            'permission_callback' => '__return_true'
        ));
        
        //获取用户收款二维码
        register_rest_route('islide/v1','/getUserQrcode',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getUserQrcode'),
            'permission_callback' => '__return_true'
        ));
        
        /************************************ 分销 ************************************************/
        
        register_rest_route('islide/v1','/getUserPartner',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getUserPartner'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('islide/v1','/getUserRebateOrders',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getUserRebateOrders'),
            'permission_callback' => '__return_true'
        ));
        
        /************************************ 用户消息相关 ************************************************/
        
        //获取用户未读信息
        register_rest_route('islide/v1','/getUnreadMsgCount',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getUnreadMsgCount'),
            'permission_callback' => '__return_true'
        ));
        
        //获取联系人列表
        register_rest_route('islide/v1','/getContact',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getContact'),
            'permission_callback' => '__return_true'
        ));
        
        //获取联系人列表
        register_rest_route('islide/v1','/getContactList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getContactList'),
            'permission_callback' => '__return_true'
        ));
        
        //获取消息列表
        register_rest_route('islide/v1','/getMessageList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getMessageList'),
            'permission_callback' => '__return_true'
        ));
        
        //发送消息
        register_rest_route('islide/v1','/sendMessage',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'sendMessage'),
            'permission_callback' => '__return_true'
        ));
        
        /************************************ 弹幕相关 ************************************************/
         
         //发送弹幕
        register_rest_route('islide/v1','/sendDanmaku',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'sendDanmaku'),
            'permission_callback' => '__return_true'
        ));
        
        //获取弹幕
        register_rest_route('islide/v1','/getDanmaku',array(
            'methods'=>'get',
            'callback'=>array(__CLASS__,'getDanmaku'),
            'permission_callback' => '__return_true'
        ));
        
        /************************************ 搜索相关 ************************************************/
         
        //获取搜索建议
        register_rest_route('islide/v1','/getSearchSuggest',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getSearchSuggest'),
            'permission_callback' => '__return_true'
        ));
        
        
        /************************************ 订单与支付相关 ************************************************/
        
        //开始支付 创建临时订单
        register_rest_route('islide/v1','/buildOrder',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'buildOrder'),
            'permission_callback' => '__return_true'
        ));
        
        //删除订单
        register_rest_route('islide/v1','/deleteOrder',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'deleteOrder'),
            'permission_callback' => '__return_true'
        ));
        
         //余额支付
        register_rest_route('islide/v1','/balancePay',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'balancePay'),
            'permission_callback' => '__return_true'
        ));

        //积分支付
        register_rest_route('islide/v1','/creditPay',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'creditPay'),
            'permission_callback' => '__return_true'
        ));

        //支付检查确认
        register_rest_route('islide/v1','/payCheck',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'payCheck'),
            'permission_callback' => '__return_true'
        ));
        
        //卡密充值与邀请码使用
        register_rest_route('islide/v1','/cardPay',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'cardPay'),
            'permission_callback' => '__return_true'
        ));
        
        //验证密码
        register_rest_route('islide/v1','/passwordVerify',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'passwordVerify'),
            'permission_callback' => '__return_true'
        ));
        
        //获取允许的付款方式
        register_rest_route('islide/v1','/allowPayType',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'allowPayType'),
            'permission_callback' => '__return_true'
        ));
        
        /************************************ 圈子相关 ************************************************/
        //发布帖子
        register_rest_route('islide/v1','/insertMoment',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'insertMoment'),
            'permission_callback' => '__return_true'
        ));
        
        //搜索圈子与话题
        register_rest_route('islide/v1','/getSearchCircle',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getSearchCircle'),
            'permission_callback' => '__return_true'
        ));
        
        //获取所有圈子
        register_rest_route('islide/v1','/getAllCircles',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getAllCircles'),
            'permission_callback' => '__return_true'
        ));
        
        //获取话题
        register_rest_route('islide/v1','/getTopics',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getTopics'),
            'permission_callback' => '__return_true'
        ));
        
        //获取话题详情
        register_rest_route('islide/v1','/getTopicData',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getTopicData'),
            'permission_callback' => '__return_true'
        ));
        //获取热门话题列表
        register_rest_route('islide/v1','/getHotTopicData',array(
            'methods'=>'get',
            'callback'=>array(__CLASS__,'getHotTopicData'),
            'permission_callback' => '__return_true'
        ));
        //获取用户在当前圈子能力及编辑器设置
        register_rest_route('islide/v1','/getUserCircleCapabilities',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getUserCircleCapabilities'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('islide/v1','/getMomentList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getMomentList'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('islide/v1','/detail/moments',array(
            'methods'=>'get',
            'callback'=>array(__CLASS__,'getMomentData'),
            'permission_callback' => '__return_true'
        ));
        
        //获取编辑帖子数据
        register_rest_route('islide/v1','/getEditMomentData',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getEditMomentData'),
            'permission_callback' => '__return_true'
        ));
        
        //帖子加精
        register_rest_route('islide/v1','/setMomentBest',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'setMomentBest'),
            'permission_callback' => '__return_true'
        ));
        
        //帖子置顶
        register_rest_route('islide/v1','/setMomentSticky',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'setMomentSticky'),
            'permission_callback' => '__return_true'
        ));
        
        //删除帖子
        register_rest_route('islide/v1','/deleteMoment',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'deleteMoment'),
            'permission_callback' => '__return_true'
        ));
        
        //审核帖子
        register_rest_route('islide/v1','/changeMomentStatus',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'changeMomentStatus'),
            'permission_callback' => '__return_true'
        ));
        
        //创建圈子
        register_rest_route('islide/v1','/createCircle',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'createCircle'),
            'permission_callback' => '__return_true'
        ));
        
        //加入圈子
        register_rest_route('islide/v1','/joinCircle',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'joinCircle'),
            'permission_callback' => '__return_true'
        ));
        
        //获取圈子权限数据
        register_rest_route('islide/v1','/getCircleRoleData',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getCircleRoleData'),
            'permission_callback' => '__return_true'
        ));
        
        //创建话题
        register_rest_route('islide/v1','/createTopic',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'createTopic'),
            'permission_callback' => '__return_true'
        ));
        
        //获取圈子数据
        register_rest_route('islide/v1','/getCircleData',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getCircleData'),
            'permission_callback' => '__return_true'
        ));
        
        //获取热门圈子列表
        register_rest_route('islide/v1','/getHotCircleData',array(
            'methods'=>'get',
            'callback'=>array(__CLASS__,'getHotCircleData'),
            'permission_callback' => '__return_true'
        ));
    
        //获取分类
        register_rest_route('islide/v1','/getCircleCats',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getCircleCats'),
            'permission_callback' => '__return_true'
        ));
        
        //获取圈子管理设置
        register_rest_route('islide/v1','/getManageCircle',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getManageCircle'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('islide/v1','/getCircleUsers',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getCircleUsers'),
            'permission_callback' => '__return_true'
        ));
        
        //圈子用户搜索
        register_rest_route('islide/v1','/circleSearchUsers',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'circleSearchUsers'),
            'permission_callback' => '__return_true'
        ));
        
        //邀请用户加入圈子
        register_rest_route('islide/v1','/inviteUserJoinCircle',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'inviteUserJoinCircle'),
            'permission_callback' => '__return_true'
        ));
        
        //设置圈子版主
        register_rest_route('islide/v1','/setUserCircleStaff',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'setUserCircleStaff'),
            'permission_callback' => '__return_true'
        ));
        
        //移除圈子用户或版主
        register_rest_route('islide/v1','/removeCircleUser',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'removeCircleUser'),
            'permission_callback' => '__return_true'
        ));
        
        //获取圈子文章管理列表
        register_rest_route('islide/v1','/getManageMomentList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getManageMomentList'),
            'permission_callback' => '__return_true'
        ));
        
        
        //获取帖子视频List
        register_rest_route('islide/v1','/getMomentVideoList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getMomentVideoList'),
            'permission_callback' => '__return_true'
        ));
        
        /************************************ 认证服务相关 ************************************************/
        
        //获取认证相关信息
        register_rest_route('islide/v1','/getVerifyInfo',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getVerifyInfo'),
            'permission_callback' => '__return_true'
        ));
        
        //获取用户认证信息
        register_rest_route('islide/v1','/getUserVerifyInfo',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getUserVerifyInfo'),
            'permission_callback' => '__return_true'
        ));
        
        //认证申请
        register_rest_route('islide/v1','/submitVerify',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'submitVerify'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('islide/v1','/page/verify',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getVerifyPage'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('islide/v1','/UpdateVerify',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'update_verify_data'),
            'permission_callback' => function () {
             return current_user_can('administrator');
           },
        ));
        
        register_rest_route('islide/v1','/getCardList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getCardList'),
                        'permission_callback' => function () {
             return current_user_can('administrator');
           },
        ));
        
        register_rest_route('islide/v1','/deleteCardList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'islide_delete_card_list'),
                        'permission_callback' => function () {
             return current_user_can('administrator');
           },
        ));
        register_rest_route('islide/v1','/generateCard',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'islide_generate_card'),
                        'permission_callback' => function () {
             return current_user_can('administrator');
           },
        ));
        
        register_rest_route('islide/v1','/getReportList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'getReportList'),
            'permission_callback' => function () {
             return current_user_can('administrator');
           },
        ));
        register_rest_route('islide/v1','/UpdateReport',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'UpdateReport'),
            'permission_callback' => function () {
             return current_user_can('administrator');
           },
        ));
        register_rest_route('islide/v1','/getLinkList',array(
            'methods'=>'post',
            'callback'=>array('islide\Modules\Common\FriendLink','get_link_list'),
            'permission_callback' => function () {
                return current_user_can('administrator');
            },
        ));
        register_rest_route('islide/v1','/UpdateLink',array(
            'methods'=>'post',
            'callback'=>array('islide\Modules\Common\FriendLink','update_friend_link'),
            'permission_callback' => function () {
                return current_user_can('administrator');
            },
        ));
        register_rest_route('islide/v1','/getMsgList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'get_message_list_api'),
            'permission_callback' => function () {
             return current_user_can('administrator');
           },
        ));
        register_rest_route('islide/v1','/deleteMsgList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'delete_message_list'),
                        'permission_callback' => function () {
             return current_user_can('administrator');
           },
        ));
        
        register_rest_route('islide/v1','/pushMsg',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'push_message_api'),
            'permission_callback' => function () {
             return current_user_can('administrator');
           },
        ));
        
        register_rest_route('islide/v1','/getOrderList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'get_order_list_api'),
            'permission_callback' => function () {
             return current_user_can('administrator');
           },
        ));
        register_rest_route('islide/v1','/updateOrderField',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'update_order_field'),
            'permission_callback' => function () {
             return current_user_can('administrator');
           },
        ));
        
        register_rest_route('islide/v1','/getOrderStats',array(
            'methods'=>'get',
            'callback'=>array(__CLASS__,'get_order_statistics'),
            'permission_callback' => function () {
             return current_user_can('administrator');
           },
        ));
        
        register_rest_route('islide/v1','/address/save',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'islide_save_address'),
            'permission_callback' => function () {
             return get_current_user_id();
           },
        ));
        register_rest_route('islide/v1','/address/detail',array(
            'methods'=>'get',
            'callback'=>array(__CLASS__,'islide_get_address_detail'),
            'permission_callback' => function () {
             return get_current_user_id();
           },
        ));
        register_rest_route('islide/v1','/address/list',array(
            'methods'=>'get',
            'callback'=>array(__CLASS__,'islide_get_address_list'),
            'permission_callback' => function () {
             return get_current_user_id();
           },
        ));
        register_rest_route('islide/v1','/getLinkStats',array(
            'methods'=>'get',
            'callback'=>array('islide\Modules\Common\FriendLink','get_friendlink_statistics'),
            'permission_callback' => function () {
                return current_user_can('administrator');
            },
        ));
        
        register_rest_route('islide/v1','/getReportStats',array(
            'methods'=>'get',
            'callback'=>array(__CLASS__,'get_report_statistics'),
            'permission_callback' => function () {
             return current_user_can('administrator');
           },
        ));
        
        register_rest_route('islide/v1','/getCardStats',array(
            'methods'=>'get',
            'callback'=>array(__CLASS__,'get_card_statistics'),
            'permission_callback' => function () {
             return current_user_can('administrator');
           },
        ));
        
        register_rest_route('islide/v1','/getVerifyStats',array(
            'methods'=>'get',
            'callback'=>array(__CLASS__,'get_verify_statistics'),
            'permission_callback' => function () {
             return current_user_can('administrator');
           },
        ));
        
        register_rest_route('islide/v1','/getWithdrawalList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'islide_get_withdrawal_list'),
            'permission_callback' => function () {
             return current_user_can('administrator');
           },
        ));
        
        register_rest_route('islide/v1','/getWithdrawalStats',array(
            'methods'=>'get',
            'callback'=>array(__CLASS__,'islide_get_withdrawal_stats'),
            'permission_callback' => function () {
             return current_user_can('administrator');
           },
        ));
        
        register_rest_route('islide/v1','/deleteWithdrawalList',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'islide_delete_withdrawal_list'),
            'permission_callback' => function () {
             return current_user_can('administrator');
           },
        ));
        
        register_rest_route('islide/v1','/updateWithdrawalField',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'islide_update_withdrawal_field'),
            'permission_callback' => function () {
             return current_user_can('administrator');
           },
        ));
        
        register_rest_route('islide/v1','/setCommentSticky',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'islide_toggle_comment_sticky'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('islide/v1','/post-years',array(
            'methods'=>'post',
            'callback'=>array(__CLASS__,'islide_get_post_years'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('islide/v1','/site-stats',array(
            'methods'=>'get',
            'callback'=>array(__CLASS__,'get_site_stats'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('islide/v1','/theme',array(
            'methods'=>'get',
            'callback'=>array(__CLASS__,'get_theme_style'),
            'permission_callback' => '__return_true'
        ));
        
        // 话题关注相关
        register_rest_route('islide/v1', '/topic/follow', array(
            'methods' => 'POST',
            'callback' => array($this, 'followTopic'),
            'permission_callback' => function () {
                return is_user_logged_in();
            }
        ));

        register_rest_route('islide/v1', '/topic/unfollow', array(
            'methods' => 'POST',
            'callback' => array($this, 'unfollowTopic'),
            'permission_callback' => function () {
                return is_user_logged_in();
            }
        ));

        register_rest_route('islide/v1', '/topic/followed', array(
            'methods' => 'GET',
            'callback' => array($this, 'getUserFollowedTopics'),
            'permission_callback' => function () {
                return is_user_logged_in();
            }
        ));

        // 圈子投票
        register_rest_route('islide/v1', '/submit_moment_vote', array(
            'methods' => 'POST',
            'callback' => array($this, 'submit_moment_vote'),
            'permission_callback' => function () {
                return is_user_logged_in();
            }
        ));

        // 回答相关接口
        register_rest_route('islide/v1', '/circle/answer/submit', array(
            'methods' => 'POST',
            'callback' => array($this, 'submit_answer'),
            'permission_callback' => function () {
                return is_user_logged_in();
            }
        ));

        register_rest_route('islide/v1', '/circle/answer/list', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_moment_answers'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('islide/v1', '/circle/answer/adopt', array(
            'methods' => 'POST',
            'callback' => array($this, 'adopt_answer'),
            'permission_callback' => function () {
                return is_user_logged_in();
            }
        ));

        register_rest_route('islide/v1', '/circle/answer/delete', array(
            'methods' => 'POST',
            'callback' => array($this, 'delete_answer'),
            'permission_callback' => function () {
                return is_user_logged_in();
            }
        ));

        // 点赞/点踩回答
        register_rest_route('islide/v1', '/answer/vote/(?P<id>\d+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'vote_answer'),
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args' => array(
                'id' => array(
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    }
                )
            )
        ));

        register_rest_route('islide/v1', '/circle/answer/cancel-adopt', array(
            'methods' => 'POST',
            'callback' => array($this, 'cancel_adopt_answer'),
            'permission_callback' => function () {
                return is_user_logged_in();
            }
        ));

        // 给回答添加评论
        register_rest_route('islide/v1', '/circle/answer/comment', array(
            'methods' => 'POST',
            'callback' => array($this, 'submit_answer_comment'),
            'permission_callback' => function () {
                return true;
            }
        ));

        // 获取所有友链的最新RSS文章
        register_rest_route('islide/v1','/getFriendsArticles',array(
            'methods'=>'POST',
            'callback'=>array('islide\Modules\Common\FriendLink','get_friends_latest_articles'),
            'permission_callback' => '__return_true',
        ));
        
        // 获取RSS数据结构示例
        register_rest_route('islide/v1','/getRssStructure',array(
            'methods'=>'get',
            'callback'=>array('islide\Modules\Common\FriendLink','get_rss_data_structure'),
            'permission_callback' => '__return_true',
        ));
        
        // 清除RSS缓存
        register_rest_route('islide/v1','/clearRssCache',array(
            'methods'=>'post',
            'callback'=>array('islide\Modules\Common\FriendLink','clear_rss_cache'),
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ));
    }
 

public static function get_site_stats() {
    // 文章总数
    $total_posts = wp_count_posts('post')->publish;

    // 建站天数（从 launch_date 开始）
    $launch_date_str = islide_get_option('launch_date');
    if(empty($launch_date_str)){
        $site_days = 0;
    }
    else{
    $site_start = strtotime($launch_date_str);
    $site_days = floor((time() - $site_start) / (60 * 60 * 24));
    }

    // 评论总数
    $total_comments = wp_count_comments();
    $total_comments = $total_comments->approved;

    // 总访问量（从 post meta 统计 'views' 字段）
    global $wpdb;
    $views = $wpdb->get_var("SELECT SUM(meta_value+0) FROM {$wpdb->postmeta} WHERE meta_key = 'views'");
    $total_views = $views ? number_format_i18n($views) : '0';

    return new \WP_REST_Response([
        'total_posts'    => $total_posts,
        'site_days'      => $site_days,
        'total_comments' => $total_comments,
        'total_views'    => $total_views,
        'total_words'    =>get_total_word_count_cached()
    ], 200);
}
 
public static function islide_get_post_years($request) {
  global $wpdb;

  $post_type = sanitize_text_field($request->get_param('type') ?: 'post');

  $results = $wpdb->get_results("
    SELECT YEAR(post_date) as year, COUNT(ID) as count
    FROM $wpdb->posts
    WHERE post_type = '$post_type'
      AND post_status = 'publish'
    GROUP BY YEAR(post_date)
    ORDER BY year DESC
  ");

  return new \WP_REST_Response($results, 200);
}
 public static function islide_toggle_comment_sticky($request) {
     $res = Shop::islide_toggle_comment_sticky($request);
    if(isset($res['error'])){
        return new \WP_Error('toggle_comment_sticky_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}

public static function islide_get_withdrawal_stats($request) {
     $res = Record::islide_get_withdrawal_stats($request);
    if(isset($res['error'])){
        return new \WP_Error('withdrawal_stats_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}

public static function islide_delete_withdrawal_list($request) {
     $res = Record::islide_delete_withdrawal_list($request);
    if(isset($res['error'])){
        return new \WP_Error('delete_withdrawal_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}

public static function islide_update_withdrawal_field($request) {
     $res = Record::islide_update_withdrawal_field($request);
    if(isset($res['error'])){
        return new \WP_Error('update_withdrawal_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}

public static function islide_get_withdrawal_list($request) {
     $res = Record::islide_get_withdrawal_list($request);
    if(isset($res['error'])){
        return new \WP_Error('withdrawal_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}  
 
public static function get_verify_statistics() {
    $counts = islide_get_field_counts('islide_verify', 'status', [0, 1,2],true);
    return $counts;
}   

public static function get_card_statistics() {
    $counts = islide_get_field_counts('islide_card', 'status', [0, 1],true);
    return $counts;
}   

public static function get_report_statistics() {
    $counts = islide_get_field_counts('islide_report', 'status', [0, 2,1],true);
    return $counts;
}




public static function islide_save_address($request){
    $res = Orders::islide_save_address($request);
    if(isset($res['error'])){
        return new \WP_Error('save_address_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}


public static function islide_get_address_detail($request){
    $res = Orders::islide_get_address($request['id']);
    if(isset($res['error'])){
        return new \WP_Error('get_address_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}

public static function islide_get_address_list($request){
    $res = Orders::islide_get_address_list($request['id']);
    if(isset($res['error'])){
        return new \WP_Error('get_address_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}


public static function get_order_statistics($request){
    $res = Orders::get_order_statistics($request);
    if(isset($res['error'])){
        return new \WP_Error('card_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}

public static function update_order_field($request){
    $res = Orders::update_order_field($request);
    if(isset($res['error'])){
        return new \WP_Error('card_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}


public static function get_order_list_api($request){
    $res = Orders::get_order_list_api($request);
    if(isset($res['error'])){
        return new \WP_Error('card_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}



public static function push_message_api($request){
    $res = Message::push_message_api($request);
    return $res;
}

public static function delete_message_list($request){
    $res = Message::delete_message_list($request);
    if(isset($res['error'])){
        return new \WP_Error('card_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}

public static function get_message_list_api($request){
    $res = Message::get_message_list_api($request);
    if(isset($res['error'])){
        return new \WP_Error('card_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}

public static function UpdateReport($request){
    $res = self::islide_update_sql_field($request,'islide_report');
    if(isset($res['error'])){
        return new \WP_Error('card_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}
public static function getReportList($request){
    $res = Report::islide_get_report_list($request);
    if(isset($res['error'])){
        return new \WP_Error('card_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}
public static function islide_generate_card($request){
    $res = Card::generate_card($request);
    if(isset($res['error'])){
        return new \WP_Error('card_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}
    
public static function islide_delete_card_list($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'islide_card';

    $params = $request->get_json_params();
    $ids = isset($params['ids']) ? array_map('intval', $params['ids']) : [];

    if (empty($ids)) {
        return new \WP_Error('invalid_ids', '缺少删除ID', ['status' => 400]);
    }

    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $sql = "DELETE FROM $table_name WHERE id IN ($placeholders)";
    $wpdb->query($wpdb->prepare($sql, ...$ids));

    return new \WP_REST_Response(['success' => true, 'deleted' => count($ids)], 200);
}
public static function getCardList($request){
    $res = Card::islide_get_card_list($request);
    if(isset($res['error'])){
        return new \WP_Error('card_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}

public static function islide_update_sql_field($request,$table_name) {
        global $wpdb;
        $params = $request->get_params();
    
        $id = absint($params['id'] ?? 0);
        $field = sanitize_text_field($params['field'] ?? '');
        $value = sanitize_text_field($params['value'] ?? '');
    
        if (!$id || !$field) {
            return array('error'=>'参数不完整');
        }
    
        $table = $wpdb->prefix . $table_name;
        $result = $wpdb->update($table, [ $field => $value ], [ 'id' => $id ]);
    
        if ($result === false) {
            return array('error'=>'更新失败');
        }
    
        return ['success' => true, 'message' => '更新成功'];
}
    
public static function update_verify_data($request) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'islide_verify';

    $data  = $request->get_json_params();

    if (empty($data['id'])) {
        return new \WP_Error('missing_id', '缺少认证ID', array('status' => 400));
    }

    $id = intval($data['id']);

    // 构建更新数组
    $update_data = array(
        'status'   => isset($data['status']) ? intval($data['status']) : 0,
        'user_id'  => isset($data['user_id']) ? intval($data['user_id']) : 0,
        'type'     => sanitize_text_field($data['type']),
        'title'    => sanitize_text_field($data['title']),
        'money'    => isset($data['money']) ? floatval($data['money']) : 0,
        'credit'   => isset($data['credit']) ? intval($data['credit']) : 0,
        'verified' => isset($data['verified']) ? intval($data['verified']) : 0,
        'date'     => current_time('mysql'), // 更新时间为当前时间
        'data'     => maybe_serialize($data['data']), // 序列化存储
        'opinion'  => sanitize_text_field($data['opinion']),
    );

    // 执行更新
    $result = $wpdb->update(
        $table_name,
        $update_data,
        array('id' => $id),
        array('%d', '%d', '%s', '%s', '%f', '%d', '%d', '%s', '%s', '%s'),
        array('%d')
    );

    if ($result === false) {
        return new \WP_Error('db_update_failed', '数据库更新失败', array('status' => 500));
    }

    $res = array(
        'success' => true,
        'message' => '更新成功',
        'updated_id' => $id
    );
    return new \WP_REST_Response($res,200);
}
    
public static function getVerifyList($request){
    $res = Verify::islide_get_verify_list($request);
    if(isset($res['error'])){
        return new \WP_Error('verify_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}

public static function getNewNoticeList($request){
    $count = (int) $request['count'];
    $res = Notice::getNewNoticeList($count);
    if(isset($res['error'])){
        return new \WP_Error('notice_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}

    
public static function getSecureInfo(){
    $res = User::get_secure_info();
    if(isset($res['error'])){
        return new \WP_Error('secure_error',$res['error'],array('status'=>403));
    }else{
        return new \WP_REST_Response($res,200);
    }
}
    
public static function getUserPostCapabilities(){
    $user_id = get_current_user_id();
    $res = Post::generate_role_data($user_id);
    return  new \WP_REST_Response($res,200);
}
public static function get_user_public_data($request){
    $user_id = (int) $request['id'];
    if(!$user_id){
        $user_id = get_current_user_id();
    }
    $res = User::get_author_info($user_id);
    return  new \WP_REST_Response($res,200);
}

public static function get_circle_sideba($request){
    $tax = (int) $request['tax'];
    $data = array(
        'tabs' => Circle::get_tabbar($tax),
        'sidebar'=>Circle::get_show_left_sidebar($tax),
        'default_index' => Circle::get_default_tabbar_index($tax)
        );
    return  new \WP_REST_Response($data,200);
}
    
public static function get_post_prenext($request){
    $post_id = $request['id'];
    $user_id = get_post_field('post_author', $post_id);
    $author_data = User::get_author_info($user_id);
    $prev_post = get_previous_post($post_id);
    $next_post = get_next_post($post_id);
    $args = array('number' => 1, 'orderby' => 'rand', 'post_status' => 'publish');
    //如果没有上一篇或者下一篇，则显示随机文章
    if (empty($prev_post)) {
        $rand_posts = get_posts($args);
        $prev_post = $rand_posts[0];

    }
    if (empty($next_post)) {
        $rand_posts = get_posts($args);
        $next_post = $rand_posts[0];
    }
    $data =  array(
            'author' => $author_data,
            'prevPost' => $prev_post,
            'nextPost' => $next_post,
        );
    return  new \WP_REST_Response($data,200);
}

public static function get_all_menu_tree(){
    $array=['top-menu','channel-menu'];
        $header_array = array();
        foreach ($array as $menu_name){
           $header_array[$menu_name]=self::get_full_menu_tree($menu_name); 
        }
    //标题
    $header_array['center_title'] = get_bloginfo('name');

    $tags = get_tags(array(
                'orderby' => 'count', // 按文章数量排序
                'order'   => 'DESC',  // 降序排列
    ));
    $header_array['tag_cloud'] = $tags; 
    
    return $header_array;
}

public static function get_full_menu_tree($menu_name) {
    // 获取菜单位置和对应的菜单 ID
    $locations = get_nav_menu_locations();
    if (!isset($locations[$menu_name])) {
        return []; // 如果菜单位置不存在，返回空数组
    }
    $menu_id = $locations[$menu_name];

    // 获取所有菜单项
    $menu_items = wp_get_nav_menu_items($menu_id);
    if (empty($menu_items)) {
        return [];
    }
    
    

    // 构建菜单树
    $menu_tree = [];
    $menu_items_by_id = [];

    foreach ($menu_items as $item) {
        $menu_items_by_id[$item->ID] = [
            'id' => $item->ID,
            'title' => $item->title,
            'url' => $item->url,
            'parent_id' => $item->menu_item_parent,
            'children' => [],
        ];
    }

    foreach ($menu_items_by_id as $id => $item) {
        if ($item['parent_id']) {
            // 是子菜单，加入到父菜单的 children 中
            $menu_items_by_id[$item['parent_id']]['children'][] = &$menu_items_by_id[$id];
        } else {
            // 是顶级菜单
            $menu_tree[] = &$menu_items_by_id[$id];
        }
    }
    return $menu_tree;
}

public static function get_config(){
    $res = get_option('islide_main_options');
    $res['tax'] = self::get_all_tags_and_categories();
    $res['blog_name'] = get_bloginfo('name');
    $array=['top-menu','channel-menu'];
    $header_array = array();
    foreach ($array as $menu_name){
       $header_array[$menu_name]=self::get_full_menu_tree($menu_name); 
    }
    $res['menu'] = $header_array;
    $res['link_category'] = get_terms([
        'taxonomy'   => 'link_category',
        'hide_empty' => false,
    ]);
    $res['password_verify']['length'] = !empty($res['password_verify']['code']) ? strlen($res['password_verify']['code']) : 4;
    unset($res['password_verify']['code']);
    $res = convert_image_urls($res);
    return new \WP_REST_Response($res,200);
}
public static function get_all_tags_and_categories() {
    // 统一获取分类和标签
    $terms = get_terms([
        'taxonomy'   => ['category', 'post_tag'], // 同时获取分类和标签
        'hide_empty' => false,                   // 包含空项
        'orderby'    => 'count',                  // 按名称排序
        'order'      => 'ASC',                   // 升序
    ]);

    // 构建结果数组
    $result = [
        'cats' => [],
        'tags'       => [],
    ];
    
    $cat_array = ['category','video_cat','circle_cat','shop_cat'];
    $tag_array = ['post_tag','topic'];

    // 分类数据
    foreach ($terms as $term) {
                $img = get_term_meta($term->term_id,'islide_tax_img',true);
                $custom_title = get_term_meta($term->term_id,'seo_title',true);
                if($custom_title){ 
                    $title = islide_get_seo_title(esc_attr($custom_title)); 
                }else{
                    $title = islide_get_seo_title($term->name);
                }
            $seo_array = [
                'title'=>$title,
                'keywords'=> get_term_meta($term->term_id,'seo_keywords',true),
                'description'=>$term->description,
                'image'=>islide_get_thumb(array('url'=>$img,'width'=>600,'height'=>400))
        ];
        if (in_array($term->taxonomy,$cat_array)) {
            $result['cats'][] = [
                'id'    => $term->term_id,
                'term_id'=>$term->term_id,
                'name'  => $term->name,
                'slug'  => $term->slug,
                'taxonomy'=>$term->taxonomy,
                'count' => $term->count,
                'seo'=>$seo_array
            ];
        } elseif (in_array($term->taxonomy,$tag_array)) {
            $result['tags'][] = [
                'id'    => $term->term_id,
                'term_id'=>$term->term_id,
                'name'  => $term->name,
                'slug'  => $term->slug,
                'taxonomy'=>$term->taxonomy,
                'count' => $term->count,
                'seo'=>$seo_array
            ];
        }
    }

    return $result;
}



public static function get_author_stats($request){
$post_id = $request['id'];
$user_id = get_post_field('post_author', $post_id)? get_post_field('post_author', $post_id):1;
$vip = User::get_user_vip($user_id);
$data = get_userdata($user_id);
$badge = get_relative_upload_path(isset($vip['icon']) && !empty($vip['icon']) ? $vip['icon'] : '');
$stats = User::get_user_stats_count($user_id); //获取统计计数
$follow = User::get_user_followers_stats_count($user_id); //获取关注数计数
 return new \WP_REST_Response(array(
        'id'=>$user_id,
        'name'   => isset($data->display_name) ? esc_attr($data->display_name) : '',
        'desc'   => isset($data->description) && !empty($data->description)? esc_attr($data->description) : islide_get_option('user_desc'),
        'avatar' => get_avatar_url($user_id,array('size'=>160)),
        'badge'=>$badge,
        'stats' => $stats,
        'follow'=> $follow
    ), 200);
}

    
// 处理 API 请求
public static function deepseek_generate_summary($request) {
    $post_id = $request['id'];
    $post = get_post($post_id);

    // 校验文章是否存在
    if (!$post) {
        return new \WP_Error('post_not_found', '文章不存在', ['status' => 404]);
    }
    
    // 检查缓存
    $cached_summary =get_post_meta($post_id, 'deepseek_summary', true);
    if ($cached_summary) {
        return new \WP_REST_Response(['id'=>$post_id,'summary' => $cached_summary], 200);
    }

    // 获取文章内容（去除 HTML 标签）
    $content = wp_strip_all_tags($post->post_content);
    if (empty($content)) {
        return new \WP_Error('empty_content', '文章内容为空', ['status' => 400]);
    }

    // 调用 DeepSeek API
    $api_key = 'sk-zcihxcmcbaxfxhkftacnzhqvdmjgvzyaorvvqtronjmlsmkp';
    $api_url = 'https://api.siliconflow.cn/v1/chat/completions';

    $response = wp_remote_post($api_url, [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ],
        'body' => json_encode([
            "model" => "deepseek-ai/DeepSeek-R1-Distill-Qwen-1.5B",
            "messages" => [
                ["role" => "user", "content" => "以下为一篇文章的内容，请你生成概括，字数不超过100,开头格式为：这篇文章讲述了xxxxx，文章全部内容：" . $content]
            ],
            "temperature" => 0.7,
            "stream"=>false,
        ]),
        'timeout' => 30
    ]);

   // 处理 API 错误
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        return new \WP_Error('api_error', 'DeepSeek API 请求失败: ' . $error_message, ['status' => 500]);
    }


    $responseData = json_decode(wp_remote_retrieve_body($response), true);
    $summary = '';
    if (isset($responseData['choices'][0]['message']['content'])) {
        $summary = $responseData['choices'][0]['message']['content'];
        update_post_meta($post_id,'deepseek_summary',$summary);
    } else {
        $summary = "无法获取响应内容";
    }

    return new \WP_REST_Response(['id'=>$post_id,'summary' => $summary], 200);
}




    
public static function get_custom_posts($request) {
    $res = Post::get_custom_posts($request);
    
    if(isset($res['error'])){
            return new \WP_Error('get_post_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    return new \WP_REST_Response($res, 200);
}



public static function get_custom_posts_simple($request) {
    $res = Post::get_custom_posts($request, true);
    
    if(isset($res['error'])){
            return new \WP_Error('get_post_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    return new \WP_REST_Response($res, 200);
}


public static function get_theme_style(){
            //自定义样式
        $options = islide_get_option();

        return '
    :root{
        --site-width:2560px;
        --wrapper-width: 1200px;
        --sidebar-width: 300px;
       
        --top-menu-width:'.$options['top_menu_width'].'px;
        --top-menu-height:'.$options['top_menu_height'].'px;
        --top-menu-bg-color:'.$options['top_menu_bg_color'].';
        --top-menu-text-color:'.$options['top_menu_text_color'].';
    }';
}




public static function get_tax_data($request) {
    $term_id = $request['id'];
    $default_settings = array(
    'post_type'=>'post-1',
    'post_order'=>'new',
    'post_row_count'=>3,
    'post_count'=>6,
    'post_thumb_ratio'=>'1/0.618',
    'waterfall_show' => false, //开启瀑布流
    'post_meta'=>array('user','date','views','like','cats','desc'),
);
$term = get_term($term_id);
$show_sidebar = get_term_meta($term->term_id,'islide_show_sidebar',true);
$settings = get_term_meta($term->term_id,'islide_tax_group',true);
$settings = is_array($settings) ? $settings : array();
//如果设置项为空，则使用默认设置
$settings = array_merge($default_settings, $settings);

//当前分类id加入设置项中
$settings['id'] = $term->term_id;

$settings['show_sidebar']=$show_sidebar;

//自定义字段筛选
$filters = self::get_fliter_data($term->term_id);
if (is_array($filters) && isset($filters['filter_open']) && $filters['filter_open'] == '1' && is_array($filters['fliter_group']) ) {
    foreach ($filters['fliter_group'] as &$group) {
        if ($group['type'] == 'cats' && !empty($group['cats'])) {
            // 直接获取分类对象
            $group['cats'] = array_map('get_term', $group['cats']);
        }

        if ($group['type'] == 'tags' && !empty($group['tags'])) {
            // 直接获取标签对象
            $group['tags'] = array_map('get_term', $group['tags']);
        }
        
        if ($group['type'] == 'tags' && !empty($group['tags'])) {
            // 直接获取标签对象
            $group['tags'] = array_map('get_term', $group['tags']);
        }
        
        
    }
}
$settings['filters'] =$filters;

//加载方式
$pagenav_type = get_term_meta($term_id,'islide_tax_pagination_type',true);
$pagenav_type = $pagenav_type ? $pagenav_type : 'page';

$settings['pagenav_type'] = $pagenav_type;
$settings['term']=$term;
   
$img = islide_get_thumb(array('url'=>get_term_meta($term->term_id,'islide_tax_img',true),'width'=>150,'height'=>150)) ?? '';
$cover = islide_get_thumb(array('url'=>get_term_meta($term->term_id,'islide_tax_cover',true),'width'=>1200,'height'=>300)) ?? '';
$settings['img'] = $img;
$settings['cover'] = $cover;
    
    
    
    
    
    return new \WP_REST_Response($settings, 200);
}



public static function get_fliter_data($term_id){

    $fliter_group = (array)islide_get_option('tax_fliter_group');

    if(!empty($fliter_group)){
        foreach ($fliter_group as $key => $value) {
            if(isset($value['fliter_group']) && $value['filter_open']){
                
                foreach ($value['fliter_group'] as $k => $v) {
                    if(isset($v['type']) && $v['type'] == 'cats') {
                        if(in_array((string)$term_id,$v['cats'])){
                            return $value;
                        }
                    }
                }
            }
        }
    }

    $filters = get_term_meta($term_id,'islide_filter',true);

    return $filters;
}

   
public static function get_single_post_edit_data($request){
    $post_id = $request['id'];
    $post = get_post($post_id);

    if (!$post) {
        return  new \WP_REST_Response(['error' => 'Post not found'], 200);
    }

    // 获取分类信息
    $categories = get_the_category($post_id);
    $tags = get_the_tags($post_id);
    
    //文章作者id
    $user_id = get_post_field('post_author', $post_id);
    
    $thumb = Post::get_post_thumb($post_id);
    
    $thumb_id = attachment_url_to_postid($thumb);
    
    $content = ShortCode::get_shortcode_content(get_post_field('post_content',$post_id),'content_hide');
        
    $content_hide = Circle::get_moment_content_hide($post_id,$content['shortcode_content']);
    
    $content = array(
        'content'=>$content['content'],
        'content_hide'=>$content_hide,
        );
    

    $is_self = (get_current_user_id() && get_current_user_id() == get_post_field('post_author', $post_id));
    
    if (!$is_self) {
        return  new \WP_REST_Response(['error' => '无权限'], 200);
    }
    // 构造返回数据
    $response = [
        'id' => $post_id,
        'title' => get_the_title($post_id),
        'excerpt' => islide_get_desc($post_id,150),
        'content' => $content,
        'thumb' => array(
                    'full'=> wp_get_attachment_image_src($thumb_id,'full'),
        ),
        'is_self'=>$is_self,
        'cats' => get_the_category($post_id)?get_the_category($post_id):array(),
        'tags' => get_the_tags($post_id)?get_the_tags($post_id):array(),
        'type' =>  get_post_type($post_id),
        'link'=>  '/'.get_post_type($post_id).'/'.$post_id,
    ];
    
    
    $roles = get_post_meta($post_id,'islide_post_roles',true);
    $roles = $roles ? $roles : array();
    $role  = get_post_meta($post_id,'islide_post_content_hide_role',true);
    $role  = $role ? $role : 'none';
    
    $num = '';
    if(in_array($role,array('money','credit'))) {
        $num = get_post_meta($post_id,'islide_post_price',true);
    }
    
    //权限
    $edit_role = array(
        'key' => $role,
        'num' => $num,
        'roles'=>$roles
    );
    
    $response['role'] = $edit_role;

    return new \WP_REST_Response($response, 200);
}

public static function get_single_post_data($request) {
    $post_id = $request['id'];
    $post = get_post($post_id);

    if (!$post) {
        return  new \WP_REST_Response(['error' => 'Post not found'], 200);
    }

    $response = Post::get_post_all_meta($post_id);

    return new \WP_REST_Response($response, 200);
}


public static function get_site_info() {
    // 获取网站名称和副标题
    $data = array(
        'site_name'    => get_bloginfo('name'),      // 网站名称
        'site_tagline' => get_bloginfo('description'), // 副标题（网站标语）
        'separator'=> islide_get_option('separator'),
        'keywords'=>islide_get_option('home_keywords'),
        'description'=>islide_get_option('home_description'),
        'aaa'=>get_term_meta(108,'islide_circle_tabbar_open')
    );
    

    // 返回 JSON 数据
    return new \WP_REST_Response($data, 200);
}


    
    
    /**
     * 用户注册
     *
     * @param object $request
     *
     * @return void
     * 
     * @version 1.0.0
     * @since 2023
     */
    public static function regeister($request){
        if(!islide_check_repo()) return new \WP_Error('regeister_error','操作频次过高',array('status'=>403));
        
        $res = Login::regeister($request->get_params());
        
        if(isset($res['error'])){
            return new \WP_Error('regeister_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response(array('msg'=>$res),200);
        }
    }
    
    /**
     * 社交登录绑定用户名
     *
     * @param object $request username:用户名是手机或者邮箱
     * 
     * @return string 验证码token
     * 
     * @version 1.0.0
     * @since 2023
     */
    public static function sendCode($request){
        if(!islide_check_repo()) return new \WP_Error('sendCode_error','操作频次过高',array('status'=>403));
        $res = Login::send_code($request);
        if(isset($res['error'])){
            return new \WP_Error('sendCode_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response(array('msg'=>$res),200);
        }
    }
    
    /**
     * 获取允许的社交登录方式
     * 
     * @version 1.1
     * @since 2023
     */
    public static function getEnabledOauths($request){
        $res = Oauth::get_enabled_oauths();
        if(isset($res['error'])){
            return new \WP_Error('sendCode_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    /**
     * 社交登录
     * 
     * @version 1.1
     * @since 2023
     */
    public static function socialLogin($request){
        $res = Oauth::social_oauth_login($request['type']);
        if(isset($res['error'])){
            return new \WP_Error('social_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    /**
     * 检查邀请码
     * 
     * @version 1.1
     * @since 2023
     */
    public static function checkInviteCode($request){
        if(!islide_check_repo($request['invite_code'])) return new \WP_Error('invite_error','别点的太快啦！',array('status'=>403));
        
        $res = Invite::checkInviteCode($request['invite_code']);
        
        if(isset($res['error'])){
            return new \WP_Error('social_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    /**
     * 绑定登录
     * 
     * @version 1.1
     * @since 2023
     */
    public static function bindingLogin($request){
        if(!islide_check_repo($request['invite_code'])) return new \WP_Error('binding_login_error','别点的太快啦！',array('status'=>403));

        if(isset($request['token'])){
            $res = OAuth::binding_login($request);
        }else{
            return new \WP_Error('binding_login_error','数据错误',array('status'=>403));
        }
        
        if(isset($res['error'])){
            return new \WP_Error('binding_login_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    /**
     * 重设密码
     * 
     * @version 1.1
     * @since 2023
     */
    public static function resetPassword($request){
        $res = Login::rest_password($request);

        if(isset($res['error'])){
            return new \WP_Error('login_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    
    
// 首页模块列表
public static function getIndexModules($request) {
    $res = islide_get_option('islide_template_index');
    if (!empty($res)) {
        foreach ($res as &$data) { // 使用引用以便修改 $res
            $nav_cat_list = array();
            $nav_cat = isset($data['nav_cat']) ? (array) $data['nav_cat'] : array();
            if ($nav_cat) {
                foreach ($nav_cat as $cat_id) {
                    $term = get_term($cat_id); // 获取自定义分类的信息
                    if ($term && !is_wp_error($term)) {
                        $nav_cat_list[] = $term;
                    }
                }
            }
            if (!empty($nav_cat_list)) {
                $data['nav_cat'] = $nav_cat_list; // 修改 $res 中的 nav_cat 值
            }
        }
        unset($data); // 避免引用污染
    }

    // 检查错误并返回结果
    if (isset($res['error'])) {
        return new \WP_Error('insertPost_error', $res['error'], array('status' => 200));
    } else {
        return new \WP_REST_Response($res, 200);
    }
}
    
    
    /************************************ 文章相关 ************************************************/
    //发布文章
    public static function insertPost($request){
        $res = Post::insert_post($request->get_params());
        
        if(isset($res['error'])){
            return new \WP_Error('insertPost_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    
    
    
    /**
     * 获取文章列表
     *
     * @param array $request
     *
     * @return array
     * 
     * @version 1.0.0
     * @since 2023
     */
    public static function getPostList($request){
        $type = str_replace('-','_',$request['post_type']);

        if(!method_exists('islide\Modules\Templates\Modules\Posts',$type)) return;

        return Posts::$type($request,$request['post_i'],true);
    }
    
    
    
    public static function getPostListV2($request){
        $type = str_replace('-','_',$request['post_type']).'_data';

        if(!method_exists('islide\Modules\Templates\Modules\Posts',$type)) return;

        return Posts::$type($request,true);
    }


public static function get_widget_slug() {
    $sidebars = ['post-sidebar', 'shop-sidebar', 'video-sidebar', 'page-sidebar', 'circle-home-sidebar', 'circle-sidebar', 'topic-sidebar'];
    
    // **缓存 index 设置**
    $index_settings = islide_get_option('islide_template_index');
    if (!empty($index_settings)) {
        foreach ($index_settings as $v) {
            if (!empty($v['key']) && !empty($v['widget_show'])) {
                $sidebars[] = $v['key'];
            }
        }
    }

    $widget_slugs = [];
    
    global $wp_registered_widgets;
    $sidebars_widgets = wp_get_sidebars_widgets();
    
    // **批量获取所有 widget 选项，减少数据库查询**
    $widget_cache = [];

    foreach ($sidebars as $sidebar_id) {
        if (empty($sidebars_widgets[$sidebar_id])) {
            continue;
        }

        $widget_slugs[$sidebar_id] = [];

        foreach ($sidebars_widgets[$sidebar_id] as $widget_id) {
            if (empty($wp_registered_widgets[$widget_id])) {
                continue;
            }

            $widget_obj = $wp_registered_widgets[$widget_id]['callback'][0] ?? null;
            if (!is_object($widget_obj) || empty($widget_obj->id_base)) {
                continue;
            }

            $id_base = $widget_obj->id_base;
            
            // **缓存查询结果，避免重复 `get_option()`**
            if (!isset($widget_cache[$id_base])) {
                $widget_cache[$id_base] = get_option("widget_{$id_base}", []);
            }

            // **优化解析 `widget_id`**
            $instance_num = (int) substr(strrchr($widget_id, '-'), 1);
            
            if (!isset($widget_cache[$id_base][$instance_num])) {
                continue;
            }

            // **存储优化后的结构**
            $widget_slugs[$sidebar_id][] = [
                'id'       => $id_base,
                'instance' => $instance_num,
                'settings' => $widget_cache[$id_base][$instance_num]
            ];
        }
    }

    return new \WP_REST_Response($widget_slugs, 200);
}
    
    /**
     * 获取模块文章列表
     *
     * @param array $request 请求参数，包含以下键值：
     *      - index: 模块索引，int类型
     *      - id: 文章分类ID，int类型，可选参数
     *      - post_paged: 文章分页数，int类型，可选参数
     * 
     * @return string 返回文章列表HTML代码
     */
    public static function getModulePostList($request){

        // 获取模块索引
        $index = (int)$request['index'] - 1;
    
        // 获取模板设置
        $template = islide_get_option('islide_template_index');
    
        // 简化代码
        $module = isset($template[$index]) ? $template[$index] : array();
    
        // 判断模块是否存在
        if(!empty($module)){
            
            // 获取页面宽度
            //$module['width'] = islide_get_page_width($module['show_widget']);
            
            //换一换
            if(isset($request['orderby']) && $request['orderby'] == 'random'){
                $module['post_order'] = $request['orderby'];
            }

            // 设置文章分类
            if(!empty($request['id'])){
                
                if(term_exists($request['id'], 'category')) {
                    $module['post_cat'] = array((int)$request['id']);
                    $module['video_cat'] = array();
                    $module['_post_type'] = 'post';
                }
                
                if(term_exists($request['id'], 'video_cat')) {
                    $module['post_cat'] = array();
                    $module['_post_type'] = 'video';
                    $module['video_cat'] = array((int)$request['id']);
                }
            }else{
                $module['post_cat'] = $module['post_cat'];
            }

            // 设置文章分页
            $module['post_paged'] = (int)$request['paged'];
            //是否是移动端
            $module['is_mobile'] = wp_is_mobile();
            return  Posts::post_1_data($module,true);
            // 返回文章列表HTML代码
            $posts = new Posts;
            $data = $posts->init($module,(int)$request['index'],true);
            $data['post_type'] = $module['post_type'];

            if($data['count'] < 1) {
                $data['data'] = islide_get_empty('暂无内容','empty.svg');
            }
            
            return $data;
        }
    
        // 如果模块不存在，则返回空字符串
        return '';
    }
    
    /**
     * 图片视频文件上传
     *
     * @param object $request
     *
     * @return void
     * 
     * @version 1.0.0
     * @since 2023
     */
    public static function fileUpload($request){

        $res = FileUpload::file_upload($request->get_params());
        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    /**
     * 图片视频文件上传
     *
     * @param object $request
     *
     * @return void
     * 
     * @version 1.0.0postVote
     * @since 2023
     */
    public static function postVote($request){

        $res = Post::post_vote($request['type'],$request['post_id']);
        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    /**
     * 发表评论
     *
     * @param object $request
     *
     * @return void
     * 
     * @version 1.0.0
     * @since 2023
     */
     
    public static function sendComment($request){
        $res = Shop::send_comment($request->get_params(),$request->get_header('user_agent'));
        if(isset($res['error'])){
            return new \WP_Error('comment_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    
    public static function deleteComment($request){
        $res = Shop::delete_comment($request);
        if(isset($res['error'])){
            return new \WP_Error('delete_comment_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    /**
     * 获取评论列表
     *
     * @param object $request
     *
     * @return void
     * 
     * @version 1.0.0
     * @since 2023
     */
    public static function getCommentList($request){

        $res = Shop::get_post_comments_flat_tree($request->get_params());
        if(isset($res['error'])){
            return new \WP_Error('comment_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    /**
     * 评论投票
     *
     * @param object $request
     *
     * @return void
     * 
     * @version 1.0.0
     * @since 2023
     */
    public static function CommentVote($request){

        $res = Comment::comment_vote($request['type'],$request['comment_id']);
        if(isset($res['error'])){
            return new \WP_Error('comment_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    /**
     * 获取表情列表
     *
     * @return void
     * 
     * @version 1.0.0
     * @since 2023
     */
    public static function getEmojiList() {
        // 检查是否已有缓存
        $cache_key = 'emoji_list_cache';
        $cached_data = get_transient($cache_key);
    
        // 如果缓存存在且有效，直接返回缓存数据
        if ($cached_data) {
            return new \WP_REST_Response($cached_data, 200);
        }
    
        // 获取配置项
        $smilies = islide_get_option('comment_smilies_arg');
    
        // 初始化结果数组
        $res = [];
    
        if (is_array($smilies) && $smilies) {
            $res['list'] = [];
    
            foreach ($smilies as $smiley) {
                // 初始化当前表情组
                $group = [
                    'name' => $smiley['name'] ?? '未命名表情组',
                    'size' => $smiley['size'] ?? 'default',
                    'list' => [],
                ];
    
                // 确保 `gallery` 存在
                if (isset($smiley['gallery'])) {
                    // 将字符串拆分成数组
                    $gallery_ids = explode(',', $smiley['gallery']);
    
                    // 批量获取图片 URL 和名称
                    $attachments = get_posts([
                        'post_type'      => 'attachment',
                        'post__in'       => $gallery_ids,
                        'post_status'    => 'inherit',
                        'fields'         => 'ids',
                        'numberposts'    => -1,
                    ]);
    
                    // 创建 ID => URL 的映射
                    $url_map = [];
                    foreach ($attachments as $attachment_id) {
                        $url_map[$attachment_id] = wp_get_attachment_url($attachment_id);
                    }
    
                    // 遍历图片 ID 列表
                    foreach ($gallery_ids as $id) {
                        if (isset($url_map[$id])) {
                            $img_url = $url_map[$id];
                            $img_name = pathinfo(basename($img_url), PATHINFO_FILENAME);
    
                            $group['list'][] = [
                                'name' => $img_name,
                                'icon' => $img_url,
                            ];
                        }
                    }
                }
    
                // 将当前表情组添加到结果列表
                $res['list'][] = $group;
            }
    
            // 设置缓存，1 小时（3600 秒）
            set_transient($cache_key, $res, 3600);
        } else {
            $res['error'] = "暂未有表情设置";
        }
    
        // 错误处理或返回结果
        if (isset($res['error'])) {
            return new \WP_Error('comment_error', $res['error'], ['status' => 403]);
        } else {
            return new \WP_REST_Response($res, 200);
        }
    }
    
    /**
     * 获取用户的评论列表
     *
     * @param [type] $request
     *
     * @return void
     * @author 青青草原上
     * @version 1.0.0
     * @since 2023
     */
    public static function getUserCommentList($request){
        $res = Comment::get_user_comment_list($request['user_id'],$request['paged'],$request['size']);

        if(isset($res['error'])){
            return new \WP_Error('comment_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    /**
     * 获取用户的动态列表
     *
     * @param [type] $request
     *
     * @return void
     * @author 青青草原上
     * @version 1.0.0
     * @since 2023
     */
    public static function getUserDynamicList($request){
        $res = User::get_user_dynamic_list($request['user_id'],$request['paged'],$request['size']);

        if(isset($res['error'])){
            return new \WP_Error('dynamic_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取内页中的下载数据
    public static function getDownloadData($request){
        $res = Post::get_post_download_data($request['post_id']);

        if(isset($res['error'])){
            return new \WP_Error('download_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    public static function getDownload($request){
        $res = Post::get_download_page_data($request['post_id'],$request['index']);

        if(isset($res['error'])){
            return new \WP_Error('download_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
     public static function getDownloadFile($request){
        $res = Post::download_file($request['token']);

        if(isset($res['error'])){
            return new \WP_Error('download_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //投诉与举报
    public static function postReport($request){
        $res = Report::report($request->get_params());

        if(isset($res['error'])){
            return new \WP_Error('post_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    /****************************************课程视频相关**************************************************/
    
    //获取视频章节播放列表
    public static function getVideoList($request){
        $res = Player::get_video_list((int)$request['post_id']);

        if(isset($res['error'])){
            return new \WP_Error('video_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        } 
    }
    
    public static function getPassageList($request){
        $res = Book::get_book_passage_list((int)$request['post_id']);

        if(isset($res['error'])){
            return new \WP_Error('video_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        } 
    }
    
    /************************************ 用户相关 ************************************************/
    
    //关注与取消关注
    public static function userFollow($request){
        $res = User::user_follow_action($request['user_id']);

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        } 
    }
    
    //检查是否已经关注
    public static function checkFollow($request){
        $res = User::check_follow($request['user_id']);

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        } 
    }
    
    //获取用户的关注列表
    public static function getFollowList($request){
        $res = User::get_follow_list($request['user_id'],$request['paged'],$request['size']);

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        } 
    }
    
    //获取用户粉丝列表
    public static function getFansList($request){
        $res = User::get_fans_list($request['user_id'],$request['paged'],$request['size']);

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        } 
    }
    
    //获取当前用户的附件
    public static function getCurrentUserAttachments($request){
        $res = User::get_current_user_attachments($request['type'],$request['paged']);

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        } 
    }
    
    //用户收藏与取消收藏
    public static function userFavorites($request){
        $res = User::user_favorites($request['post_id']);

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        } 
    }
    
    //获取用户收藏列表
    public static function getUserFavoritesList($request){
        $res = User::get_user_favorites_list($request);

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        } 
    }
    
    //获取用户文章列表
    public static function getUserPostList($request){

        $res = User::get_user_posts($request['paged'],$request['size'],$request['post_type']);

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        } 
    }
    
    //获取vip数据
    public static function getVipInfo($request){
        $res = User::get_vip_info();

        if(isset($res['error'])){
            return new \WP_Error('pay_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取用户余额与积分数据
    public static function getRechargeInfo($request){
        $res = User::get_recharge_info();

        if(isset($res['error'])){
            return new \WP_Error('pay_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取用户等级成长信息
    public static function getUserLvInfo($request){
        $res = User::get_user_lv_info();

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //修改密码
    public static function changePassword($request) {
        if(!islide_check_repo()) return new \WP_Error('user_error','操作频次过高',array('status'=>403));
        
        $res = User::change_password($request['password'],$request['confirm_password']);
        
        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //修改或绑定手机号
    public static function changeEmailOrPhone($request) {
        if(!islide_check_repo()) return new \WP_Error('user_error','操作频次过高',array('status'=>403));
        
        $res = User::change_email_or_phone($request->get_params());
        
        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取用户设置项信息
    public static function getUserSettings($request){
        $res = User::get_user_settings();

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //保存用户信息
    public static function saveUserInfo($request) {
        if(!islide_check_repo()) return new \WP_Error('user_error','操作频次过高',array('status'=>403));
        
        $res = User::save_user_info($request->get_params());
        
        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //保存用户头像
    public static function saveAvatar($request){
        if(!islide_check_repo($request['id'])) return new \WP_Error('user_error','操作频次过高',array('status'=>403));
        $res = User::save_avatar($request['url'],$request['id']);

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    
     //保存用户封面
    public static function saveCover($request){
        if(!islide_check_repo($request['id'])) return new \WP_Error('user_error','操作频次过高',array('status'=>403));
        $res = User::save_cover($request['url'],$request['id']);

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //用户签到
    public static function userSignin($request){
        if(!islide_check_repo($request['id'])) return new \WP_Error('user_error','操作频次过高',array('status'=>403));
        $res = Signin::user_signin();

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取用户签到信息
    public static function getUserSignInfo($request){
        //if(!islide_check_repo($request['id'])) return new \WP_Error('user_error','操作频次过高',array('status'=>403));
        $res = Signin::get_sign_in_info($request['date']);

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取用户的订单列表数据
    public static function getUserOrders($request){
        $res = Orders::get_user_orders($request['user_id'],$request['paged'],isset($request['state']) ? $request['state'] : 6);

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取任务列表数据
    public static function getTaskData($request){
        $res = Task::get_task_data($request['user_id'],$request['key']);

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取积分、余额记录
    public static function getUserRecords($request){
        $res = Record::get_record_list($request->get_params());

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //解除绑定社交账户
    public static function unBinding($request){
        if(!islide_check_repo()) return new \WP_Error('user_error',__('操作频次过高','islide'),array('status'=>403));
        $res = User::un_binding($request['type']);

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //提现申请
    public static function cashOut($request){
        if(!islide_check_repo()) return new \WP_Error('user_error',__('操作频次过高','islide'),array('status'=>403));
        $res = User::cash_out($request['money'],$request['type']);

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //保存收款码
    public static function saveQrcode($request){
        if(!islide_check_repo()) return new \WP_Error('user_error',__('操作频次过高','islide'),array('status'=>403));
        $res = User::save_qrcode($request['type'],$request['url']);

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取用户收款二维码
    public static function getUserQrcode($request){
        $user_id = get_current_user_id();
        $res = User::get_user_qrcode($user_id);
        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    /************************************ 分销 ************************************************/
    
    //获取当前用户所关联的用户
    public static function getUserPartner($request){
        $res = Distribution::get_user_partner($request['paged']);

        if(isset($res['error'])){
            return new \WP_Error('shop_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取用户返佣订单
    public static function getUserRebateOrders($request){
        $res = Distribution::get_user_rebate_orders($request['paged']);

        if(isset($res['error'])){
            return new \WP_Error('shop_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    /************************************ 用户信息相关 ************************************************/
    
    //获取用户未读信息数量
    public static function getUnreadMsgCount($request){
        $res = Message::get_unread_message_count();

        if(isset($res['error'])){
            return new \WP_Error('user_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取联系人
    public static function getContact($request){
        $res = Message::get_contact($request['user_id']);

        if(isset($res['error'])){
            return new \WP_Error('msg_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取信息列表
    public static function getContactList($request){
        $res = Message::get_contact_list($request['paged']);

        if(isset($res['error'])){
            return new \WP_Error('msg_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取信息列表
    public static function getMessageList($request){
        $res = Message::get_message_list($request->get_params());

        if(isset($res['error'])){
            return new \WP_Error('msg_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //发送消息
    public static function sendMessage($request){
        if(!islide_check_repo()) return new \WP_Error('msg_error',__('操作频次过高','islide'),array('status'=>403));
        
        $res = Message::send_message($request['user_id'],$request['content'],$request['image_id']);

        if(isset($res['error'])){
            return new \WP_Error('msg_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    /************************************ 弹幕相关 ************************************************/
    
    public static function sendDanmaku($request){
        $res = Danmaku::send_danmaku($request->get_params());

        if(isset($res['error'])){
            return new \WP_Error('danmaku_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        } 
    }
    
    public static function getDanmaku($request){
        $res = Danmaku::get_danmaku($request['cid']);

        if(isset($res['error'])){
            return new \WP_Error('danmaku_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        } 
    }
    
    /************************************ 搜索相关 ************************************************/
    
    //获取搜索建议
    public static function getSearchSuggest($request){
        $res = Search::get_search_suggest($request['search']);

        return $res;
    }
    
    /************************************ 订单与支付相关 ************************************************/
    //创建订单
    public static function buildOrder($request){
        if(!islide_check_repo()) return new \WP_Error('user_error',__('操作频次过高','islide'),array('status'=>403));
        $res = Orders::build_order($request->get_params());

        if(isset($res['error'])){
            return new \WP_Error('order_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //删除订单
    public static function deleteOrder($request){
        if(!islide_check_repo()) return new \WP_Error('user_error',__('操作频次过高','islide'),array('status'=>403));
        $res = Orders::delete_order($request['user_id'],$request['order_id']);

        if(isset($res['error'])){
            return new \WP_Error('order_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //余额支付
    public static function balancePay($request){
        if(!islide_check_repo($request['order_id'])) return new \WP_Error('user_error',__('操作频次过高','islide'),array('status'=>403));
        $res = Pay::balance_pay($request['order_id']);

        if(isset($res['error'])){
            return new \WP_Error('pay_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }

    //积分支付
    public static function creditPay($request){
        if(!islide_check_repo($request['order_id'])) return new \WP_Error('user_error',__('操作频次过高','islide'),array('status'=>403));
        $res = Pay::credit_pay($request['order_id']);

        if(isset($res['error'])){
            return new \WP_Error('pay_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //检查支付结果
    public static function payCheck($request){

        $res = Pay::pay_check($request['order_id']);

        return $res;
    }
    
    /**
     * 激活码或卡密充值
     * 
     * @return array
     * 
     * @version 1.0.0
     * @since 2023
     */
    public static function cardPay($request){
        
        $res = Card::card_pay($request['code']);
        
        if(isset($res['error'])){
            return new \WP_Error('pay_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    /**
     * 密码验证
     * 
     * @return array
     * 
     * @version 1.0.3
     * @since 2023/9/13
     */
    public static function passwordVerify($request){

        $code = trim($request['code'], " \t\n\r\0\x0B\xC2\xA0") ?? '';
        $post_id = (int)$request['post_id'] ?? 0;
        $type = isset($request['type']) ?? 'post';
        
        if(!$post_id) return new \WP_Error('pay_error','文章不存在',array('status'=>403));
        if(!$code) return new \WP_Error('pay_error','密码错误',array('status'=>403));
        
        $verification_code = islide_get_option('password_verify')['code'];
        
        if($type == 'circle'){
            $password = get_term_meta($post_id,'islide_circle_password',true);
        }else{
            $password = get_post_meta($post_id,'islide_post_password',true);
        }
        
        //支持个人密码和官方设置的密码
        if ($verification_code != $code && $password != $code) {
            return new \WP_Error('pay_error','密码验证错误',array('status'=>403));
        }
        
        $data = array(
           'code'=>$code,
           'post_id'=>$post_id,
           'type'=>$type
        );
        $expiration = (int)islide_get_option('login_time') * DAY_IN_SECONDS; //7 * 86400
        //设置jwt token并设置过期时间
        islide_setcookie('password_verify',json_encode($data),$expiration);

        return new \WP_REST_Response(array('msg'=>'密码验证成功，将在3秒后自动刷新当前页面！'),200);
    }
    
    //允许使用的支付付款方式
    public static function allowPayType($request){
        $res = Pay::allow_pay_type($request['order_type']);

        if(isset($res['error'])){
            return new \WP_Error('pay_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    /************************************ 圈子与话题相关 ************************************************/
    
    //发布帖子
    public static function insertMoment($request){
        $res = Circle::insert_Moment($request->get_params());
        
        if(isset($res['error'])){
            return new \WP_Error('insertMoment_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
     //搜索圈子与话题
    public static function getSearchCircle($request){
        $res = Circle::get_search_circle($request->get_params());
        
        if(isset($res['error'])){
            return new \WP_Error('search_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取所有圈子并按热门排序
    public static function getAllCircles($request){
        $res = Circle::get_all_circles();
        
        if(isset($res['error'])){
            return new \WP_Error('search_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取话题
    public static function getTopics($request){
        $res = Circle::get_topics($request->get_params());
        
        if(isset($res['error'])){
            return new \WP_Error('search_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取话题详情
    public static function getTopicData($request){
        $res = Circle::get_topic_data((int)$request['topic_id']);
        
        if(isset($res['error'])){
            return new \WP_Error('search_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }

    //获取热门话题
    public static function getHotTopicData(){
        $res = Circle::get_hot_topic_data();
        
        if(isset($res['error'])){
            return new \WP_Error('search_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取社区帖子详情
    public static function getMomentData($request){
        $user_id = get_current_user_id();
        $res = Circle::get_moment_data((int)$request['id'],$user_id);
        if(isset($res['error'])){
            return new \WP_Error('search_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }

    //获取热门圈子
    public static function getHotCircleData(){
        $res = Circle::get_hot_circle_data();
        
        if(isset($res['error'])){
            return new \WP_Error('search_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取用户在当前圈子能力及编辑器设置
    public static function getUserCircleCapabilities($request){
        $user_id = (int)get_current_user_id();
        
        $res = Circle::check_insert_moment_role($user_id,(int)$request['circle_id'],true);
        
        if(isset($res['error'])){
            return new \WP_Error('circle_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取帖子列表
    public static function getMomentList($request) {
        $circle_id = isset($request['circle_id']) ? (int) $request['circle_id'] : 0;
        
    
        $taxonomy = '';
        $term_id = 0;
    
        // ✅ 如果 circle_id 大于 0，才获取 term 信息
        if ($circle_id) {
            $tax = get_term($circle_id);
            if (is_wp_error($tax) || !$tax || empty($tax->term_id)) {
                return new \WP_Error('invalid_term', '未找到对应的圈子或分类', array('status' => 404));
            }
            $taxonomy = $tax->taxonomy;
            $term_id = $tax->term_id;
        }
    
        // ✅ 获取 tabbar 和默认 index（term_id 可能为 0）
        $tabbar = Circle::get_tabbar($term_id);
        error_log(print_r($tabbar,true));
        $default_index = Circle::get_default_tabbar_index($term_id);
        
        $index = isset($request['index']) ? (int)$request['index'] : $default_index;
        $args = isset($tabbar[$index]) && is_array($tabbar[$index]) ? $tabbar[$index] : [];
        error_log(print_r($args,true));
        // ✅ 如果指定了 term_id，补充筛选参数
        if ($term_id) {
            if ($taxonomy === 'circle_cat' && !isset($args['circle_cat'])) {
                $args['circle_cat'] = $term_id;
            }
            if ($taxonomy === 'topic' && !isset($args['topic'])) {
                $args['topic'] = $term_id;
            }
        }
    
        // ✅ 合并额外请求参数
        if (!empty($request['orderby'])) {
            $args['orderby'] = sanitize_text_field($request['orderby']);
        }
        if (!empty($request['search'])) {
            $args['search'] = sanitize_text_field($request['search']);
        }
        
        if (!empty($request['author__in'])) {
            $args['author__in'] = (int)$request['author__in'];
        }
    
        $args['paged'] = max(1, (int) ($request['paged'] ?? 1));
        $args['size'] = max(1, (int) ($request['size'] ?? 10));
    
        $res = Circle::get_moment_list($args);
    
        if (isset($res['error'])) {
            return new \WP_Error('circle_error', $res['error'], array('status' => 403));
        }
    
        return new \WP_REST_Response($res, 200);
    }
    
    //获取编辑帖子数据
    public static function getEditMomentData($request){

        $res = Circle::get_edit_moment_data($request['post_id']);

        if(isset($res['error'])){
            return new \WP_Error('circle_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //加精
    public static function setMomentBest($request){
        if(!islide_check_repo()) return new \WP_Error('user_error','操作频次过高',array('status'=>403));
        $res = Circle::set_moment_best($request['post_id']);

        if(isset($res['error'])){
            return new \WP_Error('circle_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //置顶
    public static function setMomentSticky($request){
        if(!islide_check_repo()) return new \WP_Error('user_error','操作频次过高',array('status'=>403));
        $res = Circle::set_moment_sticky($request['post_id']);

        if(isset($res['error'])){
            return new \WP_Error('circle_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //删除帖子
    public static function deleteMoment($request){
        if(!islide_check_repo()) return new \WP_Error('user_error','操作频次过高',array('status'=>403));
        $res = Circle::delete_moment($request['post_id']);

        if(isset($res['error'])){
            return new \WP_Error('circle_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //审核帖子
    public static function changeMomentStatus($request){
        if(!islide_check_repo()) return new \WP_Error('user_error','操作频次过高',array('status'=>403));
        $res = Circle::change_moment_status($request['post_id']);

        if(isset($res['error'])){
            return new \WP_Error('circle_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //创建圈子
    public static function createCircle($request){
        if(!islide_check_repo()) return new \WP_Error('user_error','操作频次过高',array('status'=>403));
        $res = Circle::create_circle($request->get_params());

        if(isset($res['error'])){
            return new \WP_Error('circle_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //加入圈子
    public static function joinCircle($request){
        if(!islide_check_repo()) return new \WP_Error('user_error','操作频次过高',array('status'=>403));
        $res = Circle::join_circle($request);

        if(isset($res['error'])){
            return new \WP_Error('circle_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取圈子权限数据
    public static function getCircleRoleData($request){
        $user_id = get_current_user_id();
        $res = Circle::get_circle_role_data($user_id,(int)$request['circle_id']);

        if(isset($res['error'])){
            return new \WP_Error('circle_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    public static function createTopic($request){
        if(!islide_check_repo()) return new \WP_Error('user_error','操作频次过高',array('status'=>403));
        $res = Circle::create_topic($request->get_params());

        if(isset($res['error'])){
            return new \WP_Error('circle_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取圈子数据
    public static function getCircleData($request){
        $res = Circle::get_circle_data((int)$request['circle_id']);
                
        if(isset($res['error']) ){
            return new \WP_Error('circle_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    public static function getCircleCats() {
        $res = Circle::get_circle_cats();
        if(isset($res['error']) ){
            return new \WP_Error('circle_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取圈子管理设置
    public static function getManageCircle($request) {
        $res = Circle::get_manage_circle((int)$request['circle_id']);
        if(isset($res['error']) ){
            return new \WP_Error('circle_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取圈子用户列表
    public static function getCircleUsers($request) {
        $res = Circle::get_circle_users($request->get_params());
        if(isset($res['error']) ){
            return new \WP_Error('circle_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取圈子用户搜索
    public static function circleSearchUsers($request) {
        $res = Circle::circle_search_users($request['key'],(int)$request['circle_id']);
        if(isset($res['error']) ){
            return new \WP_Error('circle_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //邀请用户加入圈子
    public static function inviteUserJoinCircle($request) {
        $res = Circle::invite_user_join_circle((int)$request['user_id'],(int)$request['circle_id']);
        if(isset($res['error']) ){
            return new \WP_Error('circle_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //设置版主
    public static function setUserCircleStaff($request) {
        $res = Circle::set_user_circle_staff((int)$request['user_id'],(int)$request['circle_id']);
        if(isset($res['error']) ){
            return new \WP_Error('circle_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //移除圈子用户或版主
    public static function removeCircleUser($request) {
        $res = Circle::remove_circle_user((int)$request['user_id'],(int)$request['circle_id']);
        if(isset($res['error']) ){
            return new \WP_Error('circle_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取管理帖子列表
    public static function getManageMomentList($request){
        
        $args['circle_cat'] = (int)$request['circle_id'];
        $args['orderby'] = $request['orderby'];
        
        $args['paged'] = (int)$request['paged'];
        $args['size'] = (int)$request['size'];
        $args['post_status'] = !empty($request['post_status']) ? array($request['post_status']) : array();
        $args['search'] = isset($request['search']) ? $request['search'] :'';
        
        $current_user_id = get_current_user_id();
        $role = Circle::check_insert_moment_role($current_user_id, (int)$request['circle_id']);

        if(empty($role['is_circle_staff']) && empty($role['is_admin'])){
            $res = array('error'=>'您无权管理圈子文章');
        } else{
            $res = Circle::get_moment_list($args,false);
        }
        
        if(isset($res['error'])){
            return new \WP_Error('circle_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取帖子视频List
    public static function getMomentVideoList($request){
        $res = Circle::get_moment_attachment((int)$request['post_id']);

        if(isset($res['error'])){
            return new \WP_Error('video_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res['video'],200);
        } 
    }
    
    /************************************ 认证服务相关 ************************************************/
    //获取认证相关信息
    public static function getVerifyInfo(){
        $res = Verify::get_verify_info();

        if(isset($res['error'])){
            return new \WP_Error('verify_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //获取认证相关信息
    public static function getUserVerifyInfo(){
        $res = Verify::get_user_verify_info();

        if(isset($res['error'])){
            return new \WP_Error('verify_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    //提交认证
    public static function submitVerify($request){
        if(!islide_check_repo()) return new \WP_Error('verify_error','操作频次过高',array('status'=>403));
        $res = Verify::submit_verify($request->get_params());

        if(isset($res['error'])){
            return new \WP_Error('verify_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    public static function getVerifyPage(){
        $res = islide_get_option('islide_verify_page');
        if(isset($res['error'])){
            return new \WP_Error('verify_page_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    public static function test($request){
        $res = get_post_meta(3379,'islide_single_post_download_group',true);
        if(isset($res['error'])){
            return new \WP_Error('verify_page_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }
    
    /**
     * 关注话题
     */
    public static function followTopic($request)
    {
        $topic_id = $request->get_param('topic_id');
        if (!$topic_id) {
            return array('error' => '参数错误');
        }
        $res = Circle::follow_topic($topic_id);
        if(isset($res['error'])){
            return new \WP_Error('follow_topic_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }

    /**
     * 取消关注话题
     */
    public static function unfollowTopic($request)
    {
        $topic_id = $request->get_param('topic_id');
        if (!$topic_id) {
            return array('error' => '参数错误');
        }
        $res = Circle::unfollow_topic($topic_id);
        if(isset($res['error'])){
            return new \WP_Error('unfollow_topic_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }

    /**
     * 获取用户关注的话题列表
     */
    public static function getUserFollowedTopics($request)
    {
        $user_id = get_current_user_id();
        $res = Circle::get_user_followed_topics($user_id);
        if(isset($res['error'])){
            return new \WP_Error('get_user_followed_topics_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }

    /**
     * 提交投票
     * @author ifyn
     * @param WP_REST_Request $request 请求数据
     * @return array 处理结果
     */
    public static function submit_moment_vote($request)
    {
        $res = Circle::submit_moment_vote($request->get_params());
        if(isset($res['error'])){
            return new \WP_Error('submit_moment_vote_error',$res['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($res,200);
        }
    }

    /**
     * 提交回答
     * @author ifyn
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function submit_answer($request)
    {
        $moment_id = $request->get_param('moment_id');
        $content = $request->get_param('content');

        $args = array(
            'moment_id' => $moment_id,
            'content' => $content,
        );

        $result = Circle::submit_answer($args, true);
        if(isset($result['error'])){
            return new \WP_Error('submit_answer_error',$result['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($result,200);
        }
    }

    /**
     * 获取回答列表
     * @author ifyn
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function get_moment_answers($request)
    {
        $moment_id = $request->get_param('moment_id');
        $paged = $request->get_param('paged');
        $per_page = $request->get_param('per_page');
        $orderby = $request->get_param('orderby')?: 'date';

        $data = array(
            'moment_id' => $moment_id,
            'paged' => $paged,
            'per_page' => $per_page,
            'orderby' => $orderby
        );

        $result = Circle::get_moment_answers($data);
        if(isset($result['error'])){
            return new \WP_Error('get_moment_answers_error',$result['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($result,200);
        }
    }

    /**
     * 采纳回答
     * @author ifyn
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function adopt_answer($request)
    {
        $answer_id = $request->get_param('answer_id');
        $result = Circle::adopt_answer($answer_id);
        if(isset($result['error'])){
            return new \WP_Error('adopt_answer_error',$result['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($result,200);
        }
    }

    /**
     * 删除回答
     * @author ifyn
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function delete_answer($request)
    {
        $answer_id = $request->get_param('answer_id');
        $result = Circle::delete_answer($answer_id);
        if(isset($result['error'])){
            return new \WP_Error('delete_answer_error',$result['error'],array('status'=>403));
        }else{
            return new \WP_REST_Response($result,200);
        }
    }

    /**
     * 点赞/点踩回答
     * @author ifyn
     * @param \WP_REST_Request $request 请求对象
     * @return \WP_REST_Response 响应对象
     */
    public static function vote_answer($request)
    {
        $answer_id = $request->get_param('id');
        $type = $request->get_json_params()['type'] ?? '';
        
        if (!in_array($type, array('like', 'dislike'))) {
            return new \WP_REST_Response(array('error' => '无效的投票类型'), 400);
        }
        
        $res = Circle::vote_answer($answer_id, $type);
        
        if (isset($res['error'])) {
            return new \WP_REST_Response($res, 400);
        }
        
        return new \WP_REST_Response($res['data'], 200);
    }

    /**
     * 取消采纳回答
     * @author ifyn
     * @param \WP_REST_Request $request 请求对象
     * @return \WP_REST_Response 响应对象
     */
    public static function cancel_adopt_answer($request)
    {
        $answer_id = $request->get_json_params()['answer_id'] ?? 0;
        
        if (empty($answer_id)) {
            return new \WP_REST_Response(array('error' => '参数错误'), 400);
        }
        
        $res = Circle::cancel_adopt_answer($answer_id);
        
        if (isset($res['error'])) {
            return new \WP_REST_Response($res, 400);
        }
        
        return new \WP_REST_Response($res['data'], 200);
    }

    /**
     * 给回答添加评论
     * @author ifyn
     * @param \WP_REST_Request $request 请求对象
     * @return \WP_REST_Response 响应对象
     */
    public static function submit_answer_comment($request)
    {
        $args = array(
            'answer_id' => $request->get_param('answer_id'),
            'content' => $request->get_param('content')
        );

        $res = Circle::submit_answer_comment($args);

        if (isset($res['error'])) {
            return new \WP_REST_Response($res, 400);
        }

        return new \WP_REST_Response($res, 200);
    }
}