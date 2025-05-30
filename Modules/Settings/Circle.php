<?php
namespace islide\Modules\Settings;
use islide\Modules\Common\User;

//圈子相关设置
class Circle{

    //设置主KEY
    public static $prefix = 'islide_main_options';
    
    public $circle_name;

    public function init(){
        
        //圈子名字
        $this->circle_name = islide_get_option('circle_name') ?: '社区圈子';
        
        $this->circle_options_page();
    }
    
     /**
     * 圈子相关设置
     *
     * @return void
     * 
     * @version 1.2.0
     * @since 2023/10/27
     */
    public function circle_options_page(){
        
        \CSF::createSection(self::$prefix, array(
            'id'    => 'islide_circle_options',
            'title' => '社区圈子',
            'icon'  => 'fa fa-fw fa-user-o',
        ));
        
        //加载自定义模块设置
        $this->circle_normal_settings();
        
        $this->circle_home_settings();
        
        $this->circle_settings();
        
        $this->circle_post_settings();
        
        $this->circle_role_settings();
        
        $this->topic_settings();
    }
    
    //常规设置
    public function circle_normal_settings(){
        \CSF::createSection(self::$prefix, array(
            'parent'    => 'islide_circle_options',
            'title'     => '综合',
            'icon'      => 'fab fa-instalod',
            'fields'    => array(
                // array(
                //     'id'    => 'img_logo',
                //     'type'  => 'upload',
                //     'title' => '网站LOGO',
                //     'preview' => true,
                //     'library' => 'image',
                // ),
                array(
                    'type'    => 'heading',
                    'content' => '功能',
                ),
                array(
                    'id'      => 'circle_open',
                    'type'    => 'switcher',
                    'title'   => '社区论坛功能',
                    'desc'    => '是否启用圈子社区圈子功能',
                    'default' => true,
                ),
                array(
                    'id'         => 'default_post_circle',
                    'title'      => '圈子首页默认发帖圈子',
                    'subtitle'   => '用户在不选择圈子的清空下，默认投稿到这个圈子中',
                    'type'       => 'select',
                    'placeholder' => '选择默认发帖圈子',
                    'chosen'     => true,
                    'sortable'   => true,
                    'options'     => 'categories',
                    'query_args'  => array(
                        'taxonomy'  => array('circle_cat')
                    ),
                    'desc'       => '如果不设置则需要在首页发帖的时候会提示选择圈子，如果设置必须<code>关闭此圈子的加入圈子才能发帖</code>功能',
                ),
                array(
                    'type'    => 'heading',
                    'content' => '热门权重',
                ),
                array(
                    'id'      => 'circle_weight_open',
                    'type'    => 'switcher',
                    'title'   => '开启社区圈子与话题权重计算',
                    'desc'    => '根据权重算法给每个圈子和话题进行得分，关注数，发帖数量，讨论度，帖子流量等等多维影响因子计算，主要用于热门圈子和热门话题接近真实的排序',
                    'default' => true,
                ),
                array(
                    'id'      => 'circle_cron_interval',
                    'type'    => 'spinner',
                    'title'   => '每隔几小时执行一次权重计算',
                    'desc'    => '建议根据服务器性能来，最小为1小时间隔，最大为48小时间隔',
                    'min'     => 1,
                    'max'     => 48,
                    'step'    => 1,
                    'default' => 1,
                    'unit'    => '小时',
                    'dependency' => array( 'circle_weight_open', '==', '1' )
                ),
                array(
                    'id'     => 'circle_rank_hot',
                    'type'   => 'fieldset',
                    'title'  => '圈子&话题热门上榜',
                    'subtitle' => '显示相关热门图标用于吸引人流',
                    'fields' => array(
                        array(
                            'type'    => 'subheading',
                            'content' => '圈子&话题热门上榜',
                        ),
                        array(
                            'id'    => 'rank_ask',
                            'type'  => 'spinner',
                            'title' => '圈子上榜',
                            'subtitle' => '圈子上榜权重值要求',
                            'desc'  => '比如前3名会显示图标热榜第一，第二，第三，后3名显示图标推荐(根据圈子多少，人气来配置)',
                            'min'     => 1,
                            'max'     => 48,
                            'step'    => 1,
                            'default' => 500,
                            'unit'    => '权重值',
                        ),
                        array(
                            'id'     => 'circle_rank_hot',
                            'type'   => 'fieldset',
                            'title'  => '',
                            'fields' => array(
                                array(
                                    'type'    => 'subheading',
                                    'content' => '圈子上榜自定义显示',
                                ),
                                array(
                                    'id'    => 'before',
                                    'type'  => 'spinner',
                                    'title' => '前几名？',
                                    'min'     => 1,
                                    'max'     => 10,
                                    'step'    => 1,
                                    'default' => 3,
                                    'unit'    => '名',
                                ),
                                array(
                                    'id'    => 'before_text',
                                    'type'  => 'text',
                                    'title' => '前几名显示文字名称图标',
                                    'default' => '热榜第${1}',
                                    'desc'  => '${1}会被替换成排名数字'
                                ),
                                array(
                                    'id'    => 'after',
                                    'type'  => 'spinner',
                                    'title' => '后几名？',
                                    'min'     => 1,
                                    'max'     => 10,
                                    'step'    => 1,
                                    'default' => 2,
                                    'unit'    => '名',
                                ),
                                array(
                                    'id'    => 'after_text',
                                    'type'  => 'text',
                                    'title' => '后几名显示文字名称图标',
                                    'default' => '推荐',
                                ),
                            )
                        ),
                        array(
                            'id'    => 'hot',
                            'type'  => 'spinner',
                            'title' => '圈子&话题热门',
                            'subtitle' => '热门比上一次权重计算值大于多少属于热门',
                            'desc'  => '显示热门图标，如果网站人气高，这里适当的调大一点，不然每个都是热门圈子，这样可能不太好',
                            'min'     => 1,
                            'step'    => 1,
                            'default' => 100,
                            'unit'    => '权重值',
                        ),
                    ),
                    'dependency' => array( 'circle_weight_open', '==', '1' )
                ),
                array(
                    'type'    => 'heading',
                    'content' => 'SEO',
                ),
                array(
                    'id'    => 'circle_name',
                    'type'  => 'text',
                    'title' => '社区圈子名称',
                    'default' => '社区圈子'
                ),
                array(
                    'id'          => 'circle_keywords',
                    'type'        => 'text',
                    'title'       => '社区圈子首页SEO关键词',
                    'placeholder' => '自定义圈子首页的SEO关键字(keywords)',
                    'desc'        => '建议使用英文的,隔开，一般3-5个关键词即可，多了会有堆砌嫌疑。',
                    'default'     => ''
                ),
                array(
                    'id'          => 'circle_description',
                    'type'        => 'textarea',
                    'title'       => '社区圈子首页SEO描述',
                    'placeholder' => '自定义圈子首页的SEO描述(description)',
                    'desc'        => '描述你站点的主营业务，一般不超过200个字。',
                    'attributes'  => array(
                        'rows'    => 5,
                    ),
                    'default'     => ''
                ),
                array(
                    'type'    => 'heading',
                    'content' => '圈子分类',
                ),
                array(
                    'id'        => 'circle_cats',
                    'type'      => 'group',
                    'title'     => '圈子分类',
                    'button_title' => '新增分类',
                    'desc' => '增加后不要随意改动，用户在创建圈子的时候需要选择该圈子所属分类，方便圈子的分类管理。',
                    'fields'    => array(
                        array(
                            'id'    => 'name',
                            'type'  => 'text',
                            'title' => '分类名称（必填）',
                        ),
                        
                    ),
                    'default'   => array(
                        array(
                            'name'     => '官方',
                        ),
                        array(
                            'name'     => '游戏',
                        ),
                        array(
                            'name'     => '交友',
                        ),
                        array(
                            'name'     => '反馈',
                        ),
                        array(
                            'name'     => '动漫',
                        ),
                    ),
                ),
            )
        ));
    }
    
    //圈子首页
    public function circle_home_settings(){
        \CSF::createSection(self::$prefix, array(
            'parent'    => 'islide_circle_options',
            'title'     => '圈子首页',
            'icon'      => 'fab fa-instalod',
            'fields'    => array(
                array(
                    'type'    => 'heading',
                    'content' => '圈子首页布局',
                ),
                array(
                    'id'        => 'circle_home_layout',
                    'type'      => 'fieldset',
                    'title'     => '',
                    'fields'    => array(
                        array(
                            'type'    => 'subheading',
                            'content' => '布局',
                        ),
                        array(
                            'id'      => 'sidebar_open',
                            'type'    => 'switcher',
                            'title'   => '开启首页右侧边栏小工具',
                            'default' => true,
                        ),
                        array(
                            'id'       => 'sidebar_width',
                            'type'     => 'spinner',
                            'title'    => '首页右侧侧边栏小工具的宽度',
                            'max'      => 1000,
                            'min'      => 0,
                            'step'     => 10,
                            'unit'     => 'px',
                            'default'  => 280,
                            'dependency' => array('sidebar_open', '!=', '', '', 'visible'),
                        ),
                        array(
                            'id'      => 'sidebar_fixed',
                            'type'    => 'switcher',
                            'title'   => '模块小工具浮动',
                            'desc'       => '开启后小工具跟随浏览器滚动，此设置才会生效并只在PC端生效',
                            'default' => true,
                            'dependency' => array('sidebar_open', '!=', '', '', 'visible'),
                        ),
                        array(
                            'id'         => 'sidebar_select',
                            'type'       => 'sorter',
                            'title'      => ' ',
                            'subtitle'   => '侧边栏选择',
                            'default'    => array(
                                'enabled'    => array(
                                    'hotCircle'  => '热门圈子',
                                    'hotTopic'   => '热门话题',
                                ),
                                'disabled'   => array(),
                           ),
                           'dependency' =>array('sidebar_open', '!=', '', '', 'visible'),
                            ),
                    ),
                ),
                array(
                    'type'    => 'heading',
                    'content' => '圈子首页顶部幻灯',
                ),
                array(
                    'id'        => 'circle_home_slider',
                    'type'      => 'fieldset',
                    'title'     => '',
                    'fields'    => array(
                        array(
                            'id'      => 'open',
                            'type'    => 'switcher',
                            'title'   => '开启首页幻灯',
                            'default' => true,
                        ),
                        array(
                            'id'        => 'height',
                            'type'      => 'spinner',
                            'title'     => '幻灯的高度',
                            'unit'      => 'px',
                            'default'   => '295',
                            'max'       => 1000,
                            'dependency' => array( 'open', '==', '1' ),
                        ),
                        array(
                            'id'        => 'mobile_height',
                            'type'      => 'spinner',
                            'title'     => '幻灯的高度(移动端)',
                            'unit'      => 'px',
                            'default'   => '400',
                            'max'       => 1000,
                            'dependency' => array( 'open', '==', '1' ),
                        ),
                        array(
                            'id'        => 'speed',
                            'type'      => 'spinner',
                            'title'     => '幻灯自动切换速度',
                            'unit'      => '毫秒',
                            'default'   => '4000',
                            'desc' => '设为0则禁止自动切换，设为具体的数值，比如4000，则4秒切换一次',
                            'dependency' => array( 'open', '==', '1' ),
                        ),
                        array(
                            'id'        => 'slider_list',
                            'type'      => 'textarea',
                            'title'     => '幻灯内容',
                            'desc'      => sprintf(__('支持所有文章类型（文章，活动，商品等），每组占一行，排序与此设置相同。图片可以在%s上传或选择。
                                %s
                                支持的格式如下：
                                %s','islide'),
                                '<a target="__blank" href="'.admin_url('/upload.php').'">媒体中心</a>','<br>','
                                <br>文章ID+幻灯图片地址：<code>123<span class="red">|</span>https://xxx.com/wp-content/uploads/xxx.jpg</code><br>
                                文章ID+文章默认的缩略图：<code>3434<span class="red">|</span>0</code><br>
                                网址连接+幻灯图片地址+标题（适合外链到其他网站）：<code>https://www.xxx.com/123.html<span class="red">|</span>https://xxx.com/wp-content/uploads/xxx.jpg<span class="red">|</span>标题</code><br>
                            '),
                            'dependency' => array( 'open', '==', '1' ),
                        ),
                    ),
                ),
                array(
                    'type'    => 'heading',
                    'content' => '圈子首页TAB栏',
                ),
                array(
                    'id'        => 'circle_home_tabbar',
                    'type'      => 'group',
                    'title'     => '自定义筛选工具栏',
                    'subtitle'     => '根据这个工具可以实现无数种可能',
                    'button_title' => '新增栏目按钮',
                    // 'max' => 8,
                    'fields'    => array(
                        array(
                            'id'    => 'name',
                            'type'  => 'text',
                            'title' => 'Tab栏目名称',
                        ),
                        array(
                            'id'    => 'icon',
                            'type'  => 'icon',
                            'title' => 'Tab栏目图标',
                            'icon'  => '只会在作为左边导航时显示',
                        ),
                        array(
                            'id'         => 'tab_type',
                            'title'      => 'Tab栏目类型',
                            'type'       => 'radio',
                            'inline'     => true,
                            'options'    => array(
                                'all'   => '综合',
                                'follow'   => '关注（展示用户关注的所有圈子的帖子）',
                                'circle'   => sprintf('%s（展示）',$this->circle_name),
                            ),
                        ),
                        array(
                            'id'         => 'author__in',
                            'title'      => '筛选用户',
                            'subtitle'   => '筛选用户，一般情况下填写官方的成员id，也就是说筛选官方的帖子',
                            'type'       => 'select',
                            'placeholder' => '搜索用户',
                            'chosen'     => true,
                            'multiple'   => true,
                            'sortable'   => true,
                            'options'     => 'users',
                            'dependency' => array('tab_type', '==', 'all'),
                        ),
                        array(
                            'id'         => 'circle_cat',
                            'title'      => sprintf('筛选%s',$this->circle_name),
                            'subtitle'   => sprintf('在不选择%s则默认筛选全部，支持多选',$this->circle_name),
                            'desc'       => sprintf('注意如果你有设置某个%s内帖子只对圈友开放，这里就是选择了也不会显示的',$this->circle_name),
                            'type'       => 'select',
                            'placeholder' => sprintf('选择%s',$this->circle_name),
                            'chosen'     => true,
                            'multiple'   => true,
                            'sortable'   => true,
                            'options'     => 'categories',
                            'query_args'  => array(
                                'taxonomy'  => array('circle_cat')
                            ),
                            'dependency' => array('tab_type', '==', 'all'),
                        ),
                        array(
                            'id'         => 'not_circle_cat',
                            'title'      => sprintf('排除筛选%s',$this->circle_name),
                            'subtitle'   => sprintf('排除不想显示的%s，支持多选',$this->circle_name),
                            'type'       => 'select',
                            'placeholder' => sprintf('排除选择%s',$this->circle_name),
                            'chosen'     => true,
                            'multiple'   => true,
                            'sortable'   => true,
                            'options'     => 'categories',
                            'query_args'  => array(
                                'taxonomy'  => array('circle_cat')
                            ),
                            'dependency' => array('tab_type', '==', 'all'),
                        ),
                        array(
                            'id'         => 'topic',
                            'title'      => '筛选话题',
                            'subtitle'   => '在不选择话题则默认筛选全部，支持多选',
                            'type'       => 'select',
                            'placeholder' => '选择话题',
                            'chosen'     => true,
                            'multiple'   => true,
                            'sortable'   => true,
                            'options'     => 'categories',
                            'query_args'  => array(
                                'taxonomy'  => array('topic')
                            ),
                            'dependency' => array('tab_type', '==', 'all'),
                        ),
                        array(
                            'type'    => 'heading',
                            'content' => '更加细化的筛选',
                            'dependency' => array('tab_type', '==', 'all'),
                        ),
                        array(
                            'id'      => 'best',
                            'type'    => 'radio',
                            'title'   => '精华',
                            'options'    => array(
                                '1'   => '开启',
                                '0'   => '关闭',
                            ),
                            'default'    => '0',
                            'dependency' => array('tab_type', '==', 'all'),
                        ),
                        array(
                            'id'         => 'type',
                            'title'      => '帖子类型',
                            'type'       => 'radio',
                            'inline'     => true,
                            'options'    => array(
                                'question'   => '问答',
                                'card'   => '文章',
                            ),
                            'default'    => 'card',
                            'dependency' => array('tab_type', '==', 'all'),
                        ),
                        array(
                            'id'         => 'style',
                            'title'      => '帖子样式',
                            'type'       => 'radio',
                            'inline'     => true,
                            'options'    => array(
                                'list'   => '列表',
                                'grid'   => '小红薯',
                            ),
                            'default'    => 'list',
                            'dependency' => array('type', '==', 'card'),
                        ),
                        array(
                            'id'         => 'orderby',
                            'title'      => '默认排序',
                            'type'       => 'select',
                            'inline'     => true,
                            'options'    => array(
                                'date'   => '默认时间',
                                'modified'   => '修改时间',
                                'weight'   => '权重',
                                'views'   => '浏览量',
                                'like'   => '点赞数量',
                                'comments'   => '评论数量',
                                'comment_date'   => '回复时间',
                                'random '   => '随机',
                            ),
                            'dependency' => array('tab_type', '!=', 'circle'),
                        ),
                        array(
                            'type'    => 'heading',
                            'content' => '列表',
                            'dependency' => array('tab_type', 'any', 'all,follow'),
                        ),
                        array(
                            'id'      => 'waterFall',
                            'type'    => 'switcher',
                            'title'   => '开启瀑布流',
                            'default' => false,
                            'dependency' => array(
                                array('tab_type', 'any', 'all,follow'),
                                array('type', '!=', 'question'),
                                array('style', '==', 'grid'),
                            ),
                        ),
                        array(
                            'id'        => 'list_grid_column',
                            'type'      => 'spinner',
                            'title'     => '列表<span class="green">列数</span>',
                            'unit'      => '列',
                            'default'   => 1,
                            'desc'      => '设置帖子列表 <span class="green">一行一共几列</span> 请直接填写数字（只在PC端生效）',
                            'max'       => 6,
                        ),
                    ),
                    'default' => array(
                        array(
                            'name'     => '关注',
                            'tab_type' => 'follow',
                            'type' => 'card',
                            'style' => 'list',
                            'list_grid_column' => 1,
                        ),
                        array(
                            'name'     => '全部',
                            'tab_type' => 'all',
                            'type' => 'card',
                            'style' => 'list',
                            'list_grid_column' => 1,
                        ),
                        array(
                            'name'     => '交流',
                            'tab_type' => 'all',
                            'type' => 'card',
                            'style' => 'grid',
                            'list_grid_column' => 4,
                            'waterFall' => true,
                        ),
                        array(
                            'name'     => '问答',
                            'tab_type' => 'all',
                            'type' => 'question',
                            'list_grid_column' => 1,
                        ),
                        array(
                            'name'     => '精华',
                            'tab_type' => 'all',
                            'type' => 'card',
                            'style' => 'list',
                            'list_grid_column' => 1,
                            'best'     => '1',
                        ),
                        array(
                            'name'     => '圈子',
                            'tab_type' => 'circle',
                        ),
                    )
                ),
                array(
                    'id'       => 'circle_home_tabbar_index',
                    'type'     => 'spinner',
                    'title'    => '首页默认显示第几个栏目',
                    'subtitle' => '根据上面设置的工具栏目，选择合适的显示',
                    'max'      => 10,
                    'min'      => 0,
                    'step'     => 1,
                    'unit'     => '个',
                    'default'  => 1,
                    'desc'     => '从0开始计数，添0就是默认第一个'
                ),
                array(
                    'id'      => 'circle_home_left_sidebar',
                    'type'    => 'switcher',
                    'title'   => '显示在左侧侧边栏',
                    'default' => false,
                ),
                // array(
                //     'type'    => 'heading',
                //     'content' => '圈子左侧侧边栏',
                // ),
                // array(
                //     'id'      => 'circle_left_sidebar_open',
                //     'type'    => 'switcher',
                //     'title'   => '开启圈子左侧侧边栏',
                //     'default' => true,
                // ),
                // array(
                //     'id'      => 'circle_left_sidebar_custom',
                //     'type'    => 'switcher',
                //     'title'   => '允许用户对圈子左侧侧边栏自定义',
                //     'desc'    => '每个用户在前端可以对侧边栏自定义属于个人的侧边栏',
                //     'default' => true,
                // ),
                // array(
                //     'id'        => 'circle_left_sidebar',
                //     'type'      => 'group',
                //     'title'     => '自定义左侧侧边栏',
                //     'button_title' => '新增栏目按钮',
                //     'max' => 8,
                //     'fields'    => array(
                //         array(
                //             'id'    => 'name',
                //             'type'  => 'text',
                //             'title' => '栏目名称',
                //         ),
                //         array(
                //             'id'         => 'type',
                //             'title'      => '栏目类型',
                //             'type'       => 'radio',
                //             'inline'     => true,
                //             'options'    => array(
                //                 'custom'   => '自定义',
                //                 'circle'   => '圈子',
                //             ),
                //             'default' => 'custom'
                //         ),
                //         array(
                //             'id'         => 'circle_cat',
                //             'title'      => sprintf('显示%s',$this->circle_name),
                //             'subtitle'   => sprintf('%s支持多选',$this->circle_name),
                //             'type'       => 'select',
                //             'placeholder' => sprintf('选择%s',$this->circle_name),
                //             'chosen'     => true,
                //             'multiple'   => true,
                //             'sortable'   => true,
                //             'options'     => 'categories',
                //             'query_args'  => array(
                //                 'taxonomy'  => array('circle_cat')
                //             ),
                //             'dependency' => array('type', '==', 'circle'),
                //         ),
                //         array(
                //             'id'    => 'link',
                //             'type'  => 'text',
                //             'title' => '自定义跳转连接',
                //             'dependency' => array('type', '==', 'custom'),
                //         ),
                //         array(
                //             'id'    => 'icon',
                //             'type'  => 'icon',
                //             'title' => '自定义图标',
                //             'dependency' => array('type', '==', 'custom'),
                //         ),
                //         array(
                //             'id'    => 'img_icon',
                //             'type'  => 'upload',
                //             'title' => '自定义图片图标',
                //             'preview' => true,
                //             'library' => 'image',
                //             'dependency' => array('type', '==', 'custom'),
                //         ),
                //         array(
                //             'id'    => 'event',
                //             'type'  => 'text',
                //             'title' => '自定义点击事件',
                //             'dependency' => array('type', '==', 'custom'),
                //         ),
                //     )
                // ),
            )
        ));
    }
    
    //圈子设置
    public function circle_settings(){
        \CSF::createSection(self::$prefix, array(
            'parent'    => 'islide_circle_options',
            'title'     => '圈子板块',
            'icon'      => 'fab fa-instalod',
            'fields'    => array(
                array(
                    'type'    => 'heading',
                    'content' => '圈子页面布局',
                ),
                array(
                    'id'        => 'circle_layout',
                    'type'      => 'fieldset',
                    'title'     => '',
                    'fields'    => array(
                        array(
                            'type'    => 'subheading',
                            'content' => '布局',
                        ),
                        array(
                            'id'      => 'sidebar_open',
                            'type'    => 'switcher',
                            'title'   => '开启首页右侧边栏小工具',
                            'default' => true,
                        ),
                        array(
                            'id'       => 'sidebar_width',
                            'type'     => 'spinner',
                            'title'    => '首页右侧侧边栏小工具的宽度',
                            'max'      => 1000,
                            'min'      => 0,
                            'step'     => 10,
                            'unit'     => 'px',
                            'default'  => 280,
                            'dependency' => array('sidebar_open', '!=', '', '', 'visible'),
                        ),
                        array(
                            'id'      => 'sidebar_fixed',
                            'type'    => 'switcher',
                            'title'   => '模块小工具浮动',
                            'desc'       => '开启后小工具跟随浏览器滚动，此设置才会生效并只在PC端生效',
                            'default' => true,
                            'dependency' => array('sidebar_open', '!=', '', '', 'visible'),
                        ),
                        array(
                            'id'         => 'sidebar_select',
                            'type'       => 'sorter',
                            'title'      => ' ',
                            'subtitle'   => '侧边栏选择',
                            'default'    => array(
                                'enabled'    => array(
                                    'hotCircle'  => '热门圈子',
                                    'hotTopic'   => '热门话题',
                                ),
                                'disabled'     => array(
                                    'rule'  => '规则',
                                    'admin'  => '版主',
                                ),
                           ),
                           'dependency' =>array('sidebar_open', '!=', '', '', 'visible'),
                            ),
                    ),
                ),
                array(
                    'id'          => 'circle_info_show',
                    'type'        => 'select',
                    'title'       => '圈子顶部信息显示',
                    'options'     => array(
                        0   => '关闭',
                        'pc'      => 'pc端',
                        'mobile'  => '移动端',
                        'all'     => 'pc端和移动端都显示'
                    ),
                    'default'     => 'all',
                    'desc'        => '圈子顶部信息，如果关闭PC端显示，可以在侧边栏添加【圈子信息】小工具'
                ),
                array(
                    'id'          => 'circle_input_show',
                    'type'        => 'select',
                    'title'       => '圈子帖子发布框显示',
                    'options'     => array(
                        0   => '关闭',
                        'pc'      => 'pc端',
                        'mobile'  => '移动端',
                        'all'     => 'pc端和移动端都显示'
                    ),
                    'default'     => 'all',
                ),
                array(
                    'type'    => 'heading',
                    'content' => '圈子TAB栏',
                ),
                array(
                    'id'        => 'circle_tabbar',
                    'type'      => 'group',
                    'title'     => '自定义筛选工具栏',
                    'subtitle'     => '根据这个工具可以实现无数种可能',
                    'button_title' => '新增栏目按钮',
                    'fields'    => array(
                        array(
                            'id'    => 'name',
                            'type'  => 'text',
                            'title' => 'Tab栏目名称',
                        ),
                        array(
                            'id'    => 'icon',
                            'type'  => 'icon',
                            'title' => 'Tab栏目图标',
                            'icon'  => '只会在作为左边导航时显示',
                        ),
                        array(
                            'id'         => 'tab_type',
                            'title'      => 'Tab栏目类型',
                            'type'       => 'radio',
                            'inline'     => true,
                            'options'    => array(
                                'all'   => '综合',
                            ),
                        ),
                        array(
                            'id'         => 'author__in',
                            'title'      => '筛选用户',
                            'subtitle'   => '筛选用户，一般情况下填写官方的成员id，也就是说筛选官方的帖子',
                            'type'       => 'select',
                            'placeholder' => '搜索用户',
                            'chosen'     => true,
                            'multiple'   => true,
                            'sortable'   => true,
                            'options'     => 'users',
                        ),
                        array(
                            'id'         => 'topic',
                            'title'      => '筛选话题',
                            'subtitle'   => '在不选择话题则默认筛选全部，支持多选',
                            'type'       => 'select',
                            'placeholder' => '选择话题',
                            'chosen'     => true,
                            'multiple'   => true,
                            'sortable'   => true,
                            'options'     => 'categories',
                            'query_args'  => array(
                                'taxonomy'  => array('topic')
                            ),
                        ),
                        array(
                            'type'    => 'subheading',
                            'content' => '更加细化的筛选',
                        ),
                        array(
                            'id'      => 'best',
                            'type'    => 'radio',
                            'title'   => '精华',
                            'options'    => array(
                                '1'   => '开启',
                                '0'   => '关闭',
                            ),
                            'default'    => '0',
                        ),
                        array(
                            'id'         => 'type',
                            'title'      => '帖子类型',
                            'type'       => 'radio',
                            'inline'     => true,
                            'options'    => array(
                                'question'   => '问答',
                                'card'   => '文章',
                            ),
                        ),
                        array(
                            'id'         => 'style',
                            'title'      => '帖子样式',
                            'type'       => 'radio',
                            'inline'     => true,
                            'options'    => array(
                                'list'   => '列表',
                                'grid'   => '小红薯',
                            ),
                            'default'    => 'list',
                            'dependency' => array('type', '==', 'card'),
                        ),
                        array(
                            'id'         => 'orderby',
                            'title'      => '默认排序',
                            'type'       => 'select',
                            'inline'     => true,
                            'options'    => array(
                                'date'   => '默认时间',
                                'modified'   => '修改时间',
                                'weight'   => '权重',
                                'views'   => '浏览量',
                                'like'   => '点赞数量',
                                'comments'   => '评论数量',
                                'comment_date'   => '回复时间',
                                'random '   => '随机',
                            ),
                        ),
                        array(
                            'type'    => 'subheading',
                            'content' => '列表',
                        ),
                        array(
                            'id'      => 'waterFall',
                            'type'    => 'switcher',
                            'title'   => '开启瀑布流',
                            'default' => true,
                            'dependency' => array(
                                array('tab_type', 'any', 'all,follow'),
                                array('type', '!=', 'question'),
                            ),
                        ),
                        array(
                            'id'        => 'list_grid_column',
                            'type'      => 'spinner',
                            'title'     => '列表<span class="green">列数</span>',
                            'unit'      => '列',
                            'default'   => 1,
                            'desc'      => '设置帖子列表 <span class="green">一行一共几列</span> 请直接填写数字（只在PC端生效）',
                            'max'       => 6,
                        ),
    
                    ),
                    'default' => array(
                        array(
                            'name'     => '全部',
                            'tab_type' => 'all',
                            'type' => 'card',
                            'style' => 'list',
                            'list_grid_column' => 1,
                        ),
                        array(
                            'name'     => '问答',
                            'tab_type' => 'all',
                            'type' => 'question',
                            'list_grid_column' => 1,
                        ),
                        array(
                            'name'     => '精华',
                            'tab_type' => 'all',
                            'type' => 'card',
                            'style' => 'list',
                            'list_grid_column' => 1,
                            'best'     => '1',
                        ),
                    )
                ),
                array(
                    'id'       => 'circle_tabbar_index',
                    'type'     => 'spinner',
                    'title'    => '默认显示第几个栏目',
                    'subtitle' => '根据上面设置的工具栏目，选择合适的显示',
                    'max'      => 10,
                    'min'      => 0,
                    'step'     => 1,
                    'unit'     => '个',
                    'default'  => 0,
                    'desc'     => '从0开始计数，添0就是默认第一个'
                ),
                array(
                    'id'      => 'circle_left_sidebar',
                    'type'    => 'switcher',
                    'title'   => '显示在左侧侧边栏',
                    'default' => false,
                ),
            )
        ));
    }
    
    //发帖功能设置 moment
    public function circle_post_settings(){
        \CSF::createSection(self::$prefix, array(
            'parent'    => 'islide_circle_options',
            'title'     => '发帖功能设置',
            'icon'      => 'fab fa-instalod',
            'fields'    => array(
                array(
                    'type'    => 'heading',
                    'content' => '发帖功能设置（全局）',
                ),
                array(
                    'id'      => 'circle_join_post_open',
                    'type'    => 'switcher',
                    'title'   => '加入圈子才能发帖',
                    'default' => true,
                ),
                array(
                    'id'      => 'circle_post_open',
                    'type'    => 'switcher',
                    'title'   => '是否允许用户发帖',
                    'default' => true,
                ),
                array(
                    'id'      => 'circle_half_open',
                    'type'    => 'switcher',
                    'title'   => '圈子半开放',
                    'default' => true,
                    'desc'     => '单开无效；创建圈子时选择关闭公开显示，普通用户可获取帖子列表，但隐藏内容需要先验证是否加入圈子；关闭后，未加入圈子，无权限获取列表。'
                ),
                array(
                    'id'        => 'circle_post',
                    'type'      => 'fieldset',
                    'title'     => '',
                    'fields'    => array(
                        array(
                            'id'      => 'min_word_limit',
                            'type'    => 'spinner',
                            'title'   => '最小帖子内容字数限制',
                            'desc'    => '内容最小字数限制',
                            'min'     => 1,
                            'step'    => 10,
                            'unit'    => '个',
                            'default' => 5,
                        ),
                        array(
                            'id'      => 'max_word_limit',
                            'type'    => 'spinner',
                            'title'   => '最大帖子内容字数限制',
                            'desc'    => '内容最大字数限制',
                            'min'     => 1,
                            'step'    => 10,
                            'unit'    => '个',
                            'default' => 500,
                        ),
                        array(
                            'id'      => 'image_count',
                            'type'    => 'spinner',
                            'title'   => '最多上传图片多少张',
                            'desc'    => sprintf('需要给当前用户允许上传图片的权限%s','<a target="_blank" href="'.admin_url('/admin.php?page=islide_main_options#tab=%e5%b8%b8%e8%a7%84%e8%ae%be%e7%bd%ae/%e5%aa%92%e4%bd%93%e5%8f%8a%e6%9d%83%e9%99%90%ef%bc%88%e5%85%a8%e5%b1%80%ef%bc%89').'">媒体设置</a>'),
                            'min'     => 0,
                            'step'    => 1,
                            'default' => 9,
                            'unit'    => '个',
                        ),
                        array(
                            'id'      => 'video_count',
                            'type'    => 'spinner',
                            'title'   => '最多上传视频多少个',
                            'desc'    => sprintf('需要给当前用户允许上传视频的权限%s','<a target="_blank" href="'.admin_url('/admin.php?page=islide_main_options#tab=%e5%b8%b8%e8%a7%84%e8%ae%be%e7%bd%ae/%e5%aa%92%e4%bd%93%e5%8f%8a%e6%9d%83%e9%99%90%ef%bc%88%e5%85%a8%e5%b1%80%ef%bc%89').'">媒体设置</a>'),
                            'min'     => 0,
                            'step'    => 1,
                            'default' => 1,
                            'unit'    => '个',
                        ),
                        array(
                            'id'      => 'file_count',
                            'type'    => 'spinner',
                            'title'   => '最多上传文件多少个',
                            'desc'    => sprintf('需要给当前用户允许上传文件的权限%s','<a target="_blank" href="'.admin_url('/admin.php?page=islide_main_options#tab=%e5%b8%b8%e8%a7%84%e8%ae%be%e7%bd%ae/%e5%aa%92%e4%bd%93%e5%8f%8a%e6%9d%83%e9%99%90%ef%bc%88%e5%85%a8%e5%b1%80%ef%bc%89').'">媒体设置</a>'),
                            'min'     => 0,
                            'step'    => 1,
                            'default' => 1,
                            'unit'    => '个',
                        ),
                    ),
                    'dependency'  => array('circle_post_open', '!=', '', '', 'visible'),
                ),
                array(
                    'type'    => 'heading',
                    'content' => '发帖编辑器设置',
                ),
                array(
                    'id'        => 'circle_editor_toolbar',
                    'type'      => 'group',
                    'title'     => '工具栏按钮',
                    'subtitle' => '这只是配置工具栏按钮显示与隐藏排序等',
                    'button_title' => '新增工具按钮',
                    'desc' => '请不要重复添加相同的工具',
                    'max' => 8,
                    'fields'    => array(
                        array(
                            'id'    => 'name',
                            'type'  => 'text',
                            'title' => '工具按钮名称',
                        ),
                        array(
                            'id'    => 'name_show',
                            'type'  => 'switcher',
                            'title' => '显示按钮名称',
                            'desc'  => '意思就是不显示文字，只显示图标',
                            'default' => true,
                        ),
                        array(
                            'id'    => 'icon',
                            'type'  => 'text',
                            'title' => '工具按钮图标',
                        ),
                        array(
                            'id'         => 'tool',
                            'title'      => '工具按钮类型',
                            'type'       => 'radio',
                            'inline'     => true,
                            'options'    => array(
                                'circle_cat'   => '圈子',
                                'topic'   => '话题',
                                'emoji'   => '表情',
                                'image'   => '图片',
                                'video'   => '视频',
                                'vote'   => '投票',
                            ),
                        ),
                    ),
                    'default'   => array(
                        array(
                            'name'     => '圈子',
                            'name_show' => true,
                            'icon' => 'ri-donut-chart-line',
                            'tool' => 'circle_cat',
                        ),
                        array(
                            'name'     => '话题',
                            'name_show' => true,
                            'icon' => 'ri-hashtag',
                            'tool' => 'topic',
                        ),
                        array(
                            'name'     => '表情',
                            'name_show' => true,
                            'icon' => 'ri-emotion-line',
                            'tool' => 'emoji',
                        ),
                        array(
                            'name'     => '图片',
                            'name_show' => true,
                            'icon' => 'ri-gallery-line',
                            'tool' => 'image',
                        ),
                        array(
                            'name'     => '视频',
                            'name_show' => true,
                            'icon' => 'ri-video-line',
                            'tool' => 'video',
                        ),
                        array(
                            'name'     => '投票',
                            'name_show' => true,
                            'icon' => 'ri-chat-poll-line',
                            'tool' => 'vote',
                        ),
                        array(
                            'name_show' => true,
                            'icon' => 'ri-seedling-line',
                            'tool' => 'recommend',
                        )
                    ),
                ),
            )
        ));
    }
    
    //圈子权限设置 moment
    public function circle_role_settings(){
        
        // $circle_cats = Circle::get_circle_cats();
        // $cats = array();
        
        // if(!isset($circle_cats['error'])) {
        //     foreach ($circle_cats as $k => $v) {
        //         $cats[$v['name']] = $v['name'];
        //     }
        // }
        
        $roles = User::get_user_roles();

        $roles_options = array(
            'admin' => '圈子创建者',
            'staff' => '圈子版主'
        );
        
        foreach ($roles as $key => $value) {
            $roles_options[$key] = $value['name'];
        }
        
        $default_roles = array_keys($roles_options);
        
        $_roles = array();
        foreach ($roles as $key => $value) {
            $_roles[$key] = $value['name'];
        }
        
        \CSF::createSection(self::$prefix, array(
            'parent'    => 'islide_circle_options',
            'title'     => '权限相关',
            'icon'      => 'fab fa-instalod',
            'fields'    => array(
                array(
                    'type'    => 'heading',
                    'content' => '圈子创建权限',
                ),
                array(
                    'id'         => 'create_circle',
                    'type'       => 'checkbox',
                    'title'      => '允许创建圈子',
                    'inline'     => true,
                    'options'    => $_roles,
                    'default'    => array_keys($_roles),
                ),
                array(
                    'type'    => 'heading',
                    'content' => '话题创建权限',
                ),
                array(
                    'id'         => 'create_topic',
                    'type'       => 'checkbox',
                    'title'      => '允许创建话题',
                    'inline'     => true,
                    'options'    => $_roles,
                    'default'    => array_keys($_roles),
                ),
                array(
                    'type'    => 'heading',
                    'content' => '发帖权限（全局）',
                ),
                array(
                    'id'        => 'circle_moment',
                    'type'      => 'fieldset',
                    'title'     => '',
                    'fields'    => array(
                        array(
                            'id'         => 'insert',
                            'type'       => 'checkbox',
                            'title'      => '允许发帖',
                            'inline'     => true,
                            'options'    => $roles_options,
                            'default'    => $default_roles,
                        ),
                        array(
                            'id'         => 'insert_public',
                            'type'       => 'checkbox',
                            'title'      => '允许发帖无需审核',
                            'inline'     => true,
                            'options'    => $roles_options,
                            'default'    => $default_roles,
                        ),
                        array(
                            'id'         => 'edit',
                            'type'       => 'checkbox',
                            'title'      => '允许编辑自己帖子',
                            'inline'     => true,
                            'options'    => $roles_options,
                            'default'    => $default_roles,
                            'desc' => '可以设置允许多少天后不允许编辑',
                        ),
                        array(
                            'id'         => 'delete',
                            'type'       => 'checkbox',
                            'title'      => '允许删除自己帖子',
                            'inline'     => true,
                            'options'    => $roles_options,
                            'default'    => $default_roles,
                            'desc' => '可以设置允许多少天后不允许删除',
                        ),
                    ),
                ),
                array(
                    'type'    => 'subheading',
                    'content' => '发帖功能权限',
                ),
                array(
                    'id'        => 'circle_moment_type_role',
                    'type'      => 'fieldset',
                    'title'     => '',
                    'fields'    => array(
                        array(
                            'id'         => 'vote',
                            'type'       => 'checkbox',
                            'title'      => '允许发布投票',
                            'inline'     => true,
                            'options'    => $roles_options,
                            'default'    => $default_roles,
                        ),
                        array(
                            'id'         => 'ask',
                            'type'       => 'checkbox',
                            'title'      => '允许发布提问',
                            'inline'     => true,
                            'options'    => $roles_options,
                            'default'    => $default_roles,
                        ),
                        array(
                            'id'         => 'card',
                            'type'       => 'checkbox',
                            'title'      => '允许发布文章',
                            'inline'     => true,
                            'options'    => $roles_options,
                            'default'    => $default_roles,
                        ),
                    ),
                ),
                array(
                    'type'    => 'subheading',
                    'content' => '发帖隐私能力设置权限',
                ),
                array(
                    'id'        => 'circle_moment_role',
                    'type'      => 'fieldset',
                    'title'     => '',
                    'fields'    => array(
                        array(
                            'id'         => 'login',
                            'type'       => 'checkbox',
                            'title'      => '允许设置权限登录可见',
                            'inline'     => true,
                            'options'    => $roles_options,
                            'default'    => $default_roles,
                        ),
                        array(
                            'id'         => 'money',
                            'type'       => 'checkbox',
                            'title'      => '允许设置权限付费可见',
                            'inline'     => true,
                            'options'    => $roles_options,
                            'default'    => $default_roles,
                        ),
                        array(
                            'id'         => 'credit',
                            'type'       => 'checkbox',
                            'title'      => '允许设置权限积分支付可见',
                            'inline'     => true,
                            'options'    => $roles_options,
                            'default'    => $default_roles,
                        ),
                        array(
                            'id'         => 'comment',
                            'type'       => 'checkbox',
                            'title'      => '允许设置权限评论可见',
                            'inline'     => true,
                            'options'    => $roles_options,
                            'default'    => $default_roles,
                        ),
                        array(
                            'id'         => 'password',
                            'type'       => 'checkbox',
                            'title'      => '允许设置权限密码可见',
                            'inline'     => true,
                            'options'    => $roles_options,
                            'default'    => $default_roles,
                        ),
                        array(
                            'id'         => 'fans',
                            'type'       => 'checkbox',
                            'title'      => '允许设置权限粉丝可见',
                            'inline'     => true,
                            'options'    => $roles_options,
                            'default'    => $default_roles,
                        ),
                        array(
                            'id'         => 'roles',
                            'type'       => 'checkbox',
                            'title'      => '允许设置权限组可见',
                            'inline'     => true,
                            'options'    => $roles_options,
                            'default'    => $default_roles,
                        ),
                    ),
                ),
                array(
                    'type'    => 'heading',
                    'content' => '帖子管理权限',
                ),
                array(
                    'id'        => 'circle_moment_manage_role',
                    'type'      => 'fieldset',
                    'title'     => '',
                    'fields'    => array(
                        array(
                            'id'         => 'edit',
                            'type'       => 'checkbox',
                            'title'      => '允许编辑自己管理下的帖子',
                            'inline'     => true,
                            'options'    => array(
                                'admin' => '圈子创建者',
                                'staff' => '圈子版主'
                            ),
                            'default'    => array(
                                'admin',
                                'staff'
                            ),
                        ),
                        array(
                            'id'         => 'delete',
                            'type'       => 'checkbox',
                            'title'      => '允许删除自己管理圈子下的帖子',
                            'inline'     => true,
                            'options'    => array(
                                'admin' => '圈子创建者',
                                'staff' => '圈子版主'
                            ),
                            'default'    => array(
                                'admin',
                                'staff'
                            ),
                        ),
                        array(
                            'id'         => 'best',
                            'type'       => 'checkbox',
                            'title'      => '允许加精自己管理圈子下的帖子',
                            'inline'     => true,
                            'options'    => array(
                                'admin' => '圈子创建者',
                                'staff' => '圈子版主'
                            ),
                            'default'    => array(
                                'admin',
                                'staff'
                            ),
                        ),
                        array(
                            'id'         => 'sticky',
                            'type'       => 'checkbox',
                            'title'      => '允许置顶自己管理圈子下的帖子',
                            'inline'     => true,
                            'options'    => array(
                                'admin' => '圈子创建者',
                                'staff' => '圈子版主'
                            ),
                            'default'    => array(
                                'admin',
                                'staff'
                            ),
                        ),
                        array(
                            'id'         => 'public',
                            'type'       => 'checkbox',
                            'title'      => '允许审核自己管理圈子下的帖子',
                            'inline'     => true,
                            'options'    => array(
                                'admin' => '圈子创建者',
                                'staff' => '圈子版主'
                            ),
                            'default'    => array(
                                'admin',
                                'staff'
                            ),
                        ),
                    ),
                ),
            )
        ));
        
    }
    
    //话题设置
    public function topic_settings(){
        \CSF::createSection(self::$prefix, array(
            'parent'    => 'islide_circle_options',
            'title'     => '话题页面',
            'icon'      => 'fab fa-instalod',
            'fields'    => array(
                array(
                    'type'    => 'heading',
                    'content' => '话题页面布局',
                ),
                array(
                    'id'        => 'topic_layout',
                    'type'      => 'fieldset',
                    'title'     => '',
                    'fields'    => array(
                        array(
                            'type'    => 'subheading',
                            'content' => '布局',
                        ),
                        array(
                            'id'      => 'sidebar_open',
                            'type'    => 'switcher',
                            'title'   => '开启首页右侧边栏小工具',
                            'default' => true,
                        ),
                        array(
                            'id'       => 'sidebar_width',
                            'type'     => 'spinner',
                            'title'    => '首页右侧侧边栏小工具的宽度',
                            'max'      => 1000,
                            'min'      => 0,
                            'step'     => 10,
                            'unit'     => 'px',
                            'default'  => 280,
                            'dependency' => array('sidebar_open', '!=', '', '', 'visible'),
                        ),
                        array(
                            'id'      => 'sidebar_fixed',
                            'type'    => 'switcher',
                            'title'   => '模块小工具浮动',
                            'desc'       => '开启后小工具跟随浏览器滚动，此设置才会生效并只在PC端生效',
                            'default' => true,
                            'dependency' => array('sidebar_open', '!=', '', '', 'visible'),
                        ),
                        array(
                            'id'         => 'sidebar_select',
                            'type'       => 'sorter',
                            'title'      => ' ',
                            'subtitle'   => '侧边栏选择',
                            'default'    => array(
                                'enabled'    => array(
                                   'hotTopic'   => '热门话题',
                                   'hotCircle'  => '热门圈子', 
                                ),
                                'disabled'     => array(
                                    
                                ),
                           ),
                           'dependency' =>array('sidebar_open', '!=', '', '', 'visible'),
                            ),
                    ),
                ),
                array(
                    'id'          => 'topic_info_show',
                    'type'        => 'select',
                    'title'       => '圈子顶部信息显示',
                    'options'     => array(
                        0   => '关闭',
                        'pc'      => 'pc端',
                        'mobile'  => '移动端',
                        'all'     => 'pc端和移动端都显示'
                    ),
                    'default'     => 'all',
                    'desc'        => '圈子顶部信息，如果关闭PC端显示，可以在侧边栏添加【圈子信息】小工具'
                ),
                array(
                    'type'    => 'heading',
                    'content' => '话题TAB栏',
                ),
                array(
                    'id'        => 'topic_tabbar',
                    'type'      => 'group',
                    'title'     => '自定义筛选工具栏',
                    'subtitle'     => '根据这个工具可以实现无数种可能',
                    'button_title' => '新增栏目按钮',
                    'fields'    => array(
                        array(
                            'id'    => 'name',
                            'type'  => 'text',
                            'title' => 'Tab栏目名称',
                        ),
                        array(
                            'id'    => 'icon',
                            'type'  => 'icon',
                            'title' => 'Tab栏目图标',
                            'icon'  => '只会在作为左边导航时显示',
                        ),
                        array(
                            'id'         => 'tab_type',
                            'title'      => 'Tab栏目类型',
                            'type'       => 'radio',
                            'inline'     => true,
                            'options'    => array(
                                'all'   => '综合',
                            ),
                        ),
                        array(
                            'id'         => 'author__in',
                            'title'      => '筛选用户',
                            'subtitle'   => '筛选用户，一般情况下填写官方的成员id，也就是说筛选官方的帖子',
                            'type'       => 'select',
                            'placeholder' => '搜索用户',
                            'chosen'     => true,
                            'multiple'   => true,
                            'sortable'   => true,
                            'options'     => 'users',
                        ),
                        array(
                            'id'         => 'circle_cat',
                            'title'      => '筛选圈子',
                            'subtitle'   => '在不选择话题则默认筛选全部，支持多选',
                            'type'       => 'select',
                            'placeholder' => '选择圈子',
                            'chosen'     => true,
                            'multiple'   => true,
                            'sortable'   => true,
                            'options'     => 'categories',
                            'query_args'  => array(
                                'taxonomy'  => array('circle_cat')
                            ),
                        ),
                        array(
                            'type'    => 'subheading',
                            'content' => '更加细化的筛选',
                        ),
                        array(
                            'id'      => 'best',
                            'type'    => 'radio',
                            'title'   => '精华',
                            'options'    => array(
                                '1'   => '开启',
                                '0'   => '关闭',
                            ),
                            'default'    => '0',
                        ),
                        array(
                            'id'         => 'type',
                            'title'      => '帖子类型',
                            'type'       => 'radio',
                            'inline'     => true,
                            'options'    => array(
                                'question'   => '问答',
                                'card'   => '文章',
                            ),
                        ),
                        array(
                            'id'         => 'style',
                            'title'      => '帖子样式',
                            'type'       => 'radio',
                            'inline'     => true,
                            'options'    => array(
                                'list'   => '列表',
                                'grid'   => '小红薯',
                            ),
                            'default'    => 'list',
                            'dependency' => array('type', '==', 'card'),
                        ),
                        array(
                            'id'         => 'orderby',
                            'title'      => '默认排序',
                            'type'       => 'select',
                            'inline'     => true,
                            'options'    => array(
                                'date'   => '默认时间',
                                'modified'   => '修改时间',
                                'weight'   => '权重',
                                'views'   => '浏览量',
                                'like'   => '点赞数量',
                                'comments'   => '评论数量',
                                'comment_date'   => '回复时间',
                                'random '   => '随机',
                            ),
                        ),
                        array(
                            'type'    => 'subheading',
                            'content' => '列表',
                        ),
                        array(
                            'id'      => 'waterFall',
                            'type'    => 'switcher',
                            'title'   => '开启瀑布流',
                            'default' => true,
                            'dependency' => array(
                                array('tab_type', 'any', 'all,follow'),
                                array('type', '!=', 'question'),
                            ),
                        ),
                        array(
                            'id'        => 'list_grid_column',
                            'type'      => 'spinner',
                            'title'     => '列表<span class="green">列数</span>',
                            'unit'      => '列',
                            'default'   => 1,
                            'desc'      => '设置帖子列表 <span class="green">一行一共几列</span> 请直接填写数字（只在PC端生效）',
                            'max'       => 6,
                            
                        ),
                    ),
                    'default' => array(
                        array(
                            'name'     => '全部',
                            'tab_type' => 'all',
                            'type' => 'card',
                            'style' => 'list',
                            'list_grid_column' => 1,
                        ),
                        array(
                            'name'     => '问答',
                            'tab_type' => 'all',
                            'type' => 'question',
                            'list_grid_column' => 1,
                        ),
                        array(
                            'name'     => '精华',
                            'tab_type' => 'all',
                            'type' => 'card',
                            'style' => 'list',
                            'list_grid_column' => 1,
                            'best'     => '1',
                        ),
                    )
                ),
                array(
                    'id'       => 'topic_tabbar_index',
                    'type'     => 'spinner',
                    'title'    => '默认显示第几个栏目',
                    'subtitle' => '根据上面设置的工具栏目，选择合适的显示',
                    'max'      => 10,
                    'min'      => 0,
                    'step'     => 1,
                    'unit'     => '个',
                    'default'  => 0,
                    'desc'     => '从0开始计数，添0就是默认第一个'
                ),
                array(
                    'id'      => 'topic_left_sidebar',
                    'type'    => 'switcher',
                    'title'   => '显示在左侧侧边栏',
                    'default' => false,
                ),
            )
        ));
    }
}