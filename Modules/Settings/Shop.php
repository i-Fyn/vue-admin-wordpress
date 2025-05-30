<?php namespace islide\Modules\Settings;
use islide\Modules\Common\User;

/**
 * 商品类型文章设置类
 * @author ifyn
 */
class Shop{
    
    /**
     * 初始化方法
     * @author ifyn
     * @return void
     */
    public function init(){
        try {
            //过滤掉积分或余额变更原因
            add_filter('csf_single_shop_metabox_save', function ($data){
                if (!is_array($data)) {
                    return array();
                }
                unset($data['islide_shop_batch']);
                return $data;
            },10);
            
            //保存文章执行
            add_action('save_post', array($this,'save_shop_meta_box'),98,3);
                    
            //添加导航
            add_action('admin_footer-edit.php', array($this,'islide_shop_menu'));
            add_action('admin_footer-post.php', array($this,'islide_shop_menu'));
            add_action('admin_footer-post-new.php', array($this,'islide_shop_menu'));
            add_action('admin_footer-edit-tags.php', array($this,'islide_shop_menu'));
            add_action('admin_footer-term.php', array($this,'islide_shop_menu'));
            
            //注册商品类型文章设置
            $this->register_shop_metabox();
        } catch (Exception $e) {
            error_log('Shop init error: ' . $e->getMessage());
        }
    }
    
    /**
     * 注册商品元框
     * @author ifyn
     * @return void
     */
    public function register_shop_metabox(){
        try {
            $prefix = 'single_shop_metabox';
            
            //商品附加信息
            \CSF::createMetabox($prefix, array(
                'title'     => '商品',
                'post_type' => array('shop'),
                'context'   => 'side',
                'data_type' => 'serialize',
                'nav'       => 'inline',
                'theme'     => 'light'
            ));
            
            $roles = User::get_user_roles();
            $roles_options = array();
            
            foreach ($roles as $key => $value) {
                $roles_options[$key] = $value['name'];
            }
            
            \CSF::createSection($prefix, array(
                'title'  => '商品类型',
                'fields' => array(
                    array(
                        'id'         => 'islide_shop_type',
                        'type'       => 'radio',
                        'title'      => '商品类型',
                        'inline'     => true,
                        'options'    => array(
                            '0'  => '虚拟商品',
                            '1'  => '实物商品',
                        ),
                        'default'    => '0',
                    ),
                    array(
                        'id'         => 'islide_shop_roles',
                        'type'       => 'checkbox',
                        'title'      => '允许购买的用户组',
                        'inline'     => true,
                        'options'    => $roles_options,
                    ),
                    array( 
                        'title'  =>'是否为多规格商品',
                        'id'     =>'is_shop_multi',
                        'type'   =>'select',
                        'options'=>array(
                            '0' =>'单规格商品',
                            '1' =>'多规格商品',
                        ),
                        'default'=> '0',
                        'desc'   =>__('如果此产品只有一种规格，一个价格，请选择单规格商品。如果此产品有多个规格，多个价格，请选择多规格商品','islide')
                    ),
                    array( 
                        'title'  =>'单品价格',
                        'id'     =>'islide_shop_price',
                        'type'   =>'text',
                        'default'=>'0',
                        'desc'   =>__('单品价格，单位：元','islide'),
                        'dependency' => array(
                            array( 'is_shop_multi', '==', 0 )
                        ),
                    ),
                    array( 
                        'title'  =>'单品销量',
                        'id'     =>'islide_shop_count',
                        'type'   =>'text',
                        'default'=>'0',
                        'dependency' => array(
                            array( 'is_shop_multi', '==', 0 )
                        ),
                    ),
                    array( 
                        'title'  =>'单品库存',
                        'id'     =>'islide_shop_stock',
                        'type'   =>'text',
                        'default'=>'99',
                        'dependency' => array(
                            array( 'is_shop_multi', '==', 0 )
                        ),
                    ),
                    array( 
                        'title'  =>'单品限购',
                        'id'     =>'islide_single_limit',
                        'type'   =>'text',
                        'default'=>'99',
                        'dependency' => array(
                            array( 'is_shop_multi', '==', 0 )
                        ),
                    ),
                    array( 
                        'title'  =>'单品折扣',
                        'id'     =>'islide_single_discount',
                        'type'   =>'text',
                        'default'=>'99',
                        'dependency' => array(
                            array( 'is_shop_multi', '==', 0 )
                        ),
                    ),
                )
            ));
            
            \CSF::createSection($prefix, array(
                'title'  => '商品属性',
                'fields' => array(
                    array( 
                        'title'  =>'商品属性',
                        'id'     =>'shop_attr',
                        'type'   =>'textarea',
                        'desc'   =>sprintf(__('请按照%s属性名|属性值%s的格式设置商品属性，每个属性占一行，不填则不显示此项。比如%s%s颜色|红色%s尺码|30%s'),'<code>','</code>','<br>','<code>','</code><br><code>','</code>')
                    )
                )
            ));
            
            \CSF::createSection($prefix, array(
                'title'  => '商品保障',
                'fields' => array(
                    array(
                        'id'         => 'shop_guarantees',
                        'type'       => 'sorter',
                        'title'      => ' ',
                        'default'    => array(
                            'enabled'    => array(
                                0   => '顺丰发货',
                                1   => '7天无理由退货',
                            ),
                            'disabled'     => array(
                                2   => '正品保障',
                                3   => '假一赔十',
                                4   => '全国联保',
                                5   => '售后无忧',
                                6   => '先行赔付',
                                7   => '极速退款',
                                8   => '货到付款',
                                9   => '运费险',
                                10  => '破损包赔',
                                11  => '晚发即赔',
                                12  => '30天超长质保',
                                13  => '免费换新',
                                14  => '专属客服',
                                15  => '价格保护',
                                16  => '隐私保护',
                                17  => '电子发票',
                                18  => '闪电发货',
                                19  => '定制化服务',
                                20  => '节日促销保障',
                                21  => '售后维修服务',
                                22  => '24小时客服在线',
                                23  => '开箱验货服务',
                                24  => '签到积分兑换',
                                25  => '会员专属折扣',
                                26  => '包装回收计划',
                                27  => '环保材料承诺',
                                28  => '海外直邮保障',
                                29  => '税收补贴政策',
                                30  => '多语言客服支持',
                                31  => '限时抢购保障',
                                32  => '预售定金翻倍',
                                33  => '团购优惠保障',
                                34  => '秒杀活动保障',
                                35  => '跨店满减优惠',
                                36  => '赠品保障政策',
                                37  => '定制化包装服务',
                                38  => '节日特别礼遇',
                            ),
                        ),
                    ),
                )
            ));
            
            \CSF::createSection($prefix, array(
                'title'  => '商品展示图',
                'fields' => array(
                    array(
                        'id'          => 'shop_gallery',
                        'type'        => 'gallery',
                        'title'       => '展示图片组',
                        'add_title'   => '新增图片',
                        'edit_title'  => '编辑图片',
                        'clear_title' => '清空图片',
                        'default'     => false,
                    ),
                ),
            ));
        } catch (Exception $e) {
            error_log('Register shop metabox error: ' . $e->getMessage());
        }
    }
    
    /**
     * 保存商品元框
     * @author ifyn
     * @param int $post_id 文章ID
     * @param WP_Post $post 文章对象
     * @param bool $update 是否更新
     * @return void
     */
    public function save_shop_meta_box($post_id, $post, $update) {
        try {
            // 排除自动保存和修订版本
            if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
                return;
            }
        
            // 只在文章发布时执行
            if ($post->post_status !== 'publish') {
                return;
            }
        
            // 只在文章类型为 shop 时执行
            if ($post->post_type !== 'shop') {
                return;
            }
        
            // 获取表单提交的元数据
            $shop_meta = isset($_POST['single_shop_metabox']) ? $_POST['single_shop_metabox'] : array();
        
            // 处理规格和价格
            if (!empty($shop_meta['islide_shop_spec_price']) && is_array($shop_meta['islide_shop_spec_price'])) {
                foreach ($shop_meta['islide_shop_spec_price'] as $key => $spec) {
                    // 验证必填字段
                    if (empty($spec['spec_name']) || empty($spec['spec_value']) || !isset($spec['spec_price']) || !isset($spec['spec_stock'])) {
                        unset($shop_meta['islide_shop_spec_price'][$key]);
                        continue;
                    }
                    // 强制转换价格和库存为数字
                    $shop_meta['islide_shop_spec_price'][$key]['spec_price'] = floatval($spec['spec_price']);
                    $shop_meta['islide_shop_spec_price'][$key]['spec_stock'] = intval($spec['spec_stock']);
                }
            }
        
            // 保存元数据到文章元信息
            update_post_meta($post_id, 'single_shop_metabox', $shop_meta);
        } catch (Exception $e) {
            error_log('Save shop meta box error: ' . $e->getMessage());
        }
    }
    
    /**
     * 添加导航
     * @author ifyn
     * @return void
     */
    public function islide_shop_menu() {
        try {
            global $pagenow, $current_screen;
        
            if (
                in_array($pagenow, array('edit.php', 'post-new.php', 'post.php')) ||
                isset($_GET['taxonomy']) ||
                (in_array($pagenow, array('edit.php')) && isset($_REQUEST['post_type']))
            ) {
                if (
                    (isset($_REQUEST['post_type']) && $_REQUEST['post_type'] === 'shop') ||
                    (isset($current_screen->post_type) && $current_screen->post_type === 'shop') ||
                    (isset($_GET['post']) && get_post_type($_GET['post']) === 'shop')
                ) {
                    $current1a = (in_array($pagenow, array('edit.php')) && isset($_REQUEST['post_type']) && $_REQUEST['post_type'] === 'shop') ? ' class="current"' : '';
                    $current1b = (in_array($pagenow, array('post-new.php')) && isset($_REQUEST['post_type']) && $_REQUEST['post_type'] === 'shop') ? ' class="current"' : '';
                    $current1c = (isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'shop_cat') ? ' class="current"' : '';
        
                    echo '
                        <ul class="MnTpAdn filter-links" id="tr-grabber-menu">
                            <li><a' . $current1a . ' href="' . admin_url('edit.php?post_type=shop') . '">所有商品</a></li>
                            <li><a' . $current1b . ' href="' . admin_url('post-new.php?post_type=shop') . '">添加新商品</a></li>
                            <li><a' . $current1c . ' href="' . admin_url('edit-tags.php?taxonomy=shop_cat&post_type=shop') . '">商品分类</a></li>
                        </ul>';
                }
            }
        } catch (Exception $e) {
            error_log('Shop menu error: ' . $e->getMessage());
        }
    }
}