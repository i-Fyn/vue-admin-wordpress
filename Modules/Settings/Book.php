<?php namespace islide\Modules\Settings;
use islide\Modules\Common\User;

/**
 * 书籍类型文章设置类
 * @author ifyn
 */
class Book{
    
    /**
     * 初始化方法
     * @author ifyn
     * @return void
     */
    public function init(){
        try {
            //过滤掉积分或余额变更原因
            add_filter('csf_single_book_metabox_save', function ($data){
                if (!is_array($data)) {
                    return array();
                }
                return $data;
            },10);
            
            //保存文章执行
            add_action('save_post', array($this,'save_passage_meta_box'),10,3);
            add_action('save_post', array($this,'save_book_meta_box'),99,3);

            //删除文章时执行的代码
            add_action('before_delete_post', array($this,'delete_passage_meta_box'));
            
            //添加导航
            add_action('admin_footer-edit.php', array($this,'islide_book_menu'));
            add_action('admin_footer-post.php', array($this,'islide_book_menu'));
            add_action('admin_footer-post-new.php', array($this,'islide_book_menu'));
            add_action('admin_footer-edit-tags.php', array($this,'islide_book_menu'));
            add_action('admin_footer-term.php', array($this,'islide_book_menu'));
            
            add_action('edit_form_top', array($this, 'edit_form_top'));
            
            //book
            add_filter('manage_book_posts_columns', array($this,'filter_book_columns'));
            
            //passage
            add_filter('manage_passage_posts_columns', array($this,'filter_passage_columns'));
            add_action('manage_passage_posts_custom_column', array($this,'realestate_passage_column'), 10, 2);
            add_filter('pre_get_posts', array($this,'passage_posts_pre_query'),5);
            
            //注册书籍类型文章设置
            $this->register_book_metabox();
            $this->register_passage_metabox();
        } catch (\Exception $e) {
            error_log('Book init error: ' . $e->getMessage());
        }
    }
    
    /**
     * 过滤书籍列
     * @author ifyn
     * @param array $columns 列数组
     * @return array 修改后的列数组
     */
    public function filter_book_columns($columns){
        if (!is_array($columns)) {
            return array();
        }
        
        $new = array();
        $new['author'] = '作者';
        array_insert($columns,2,$new);
        
        return $columns;
    }
    
    /**
     * 过滤章节列
     * @author ifyn
     * @param array $columns 列数组
     * @return array 修改后的列数组
     */
    public function filter_passage_columns($columns){
        if (!is_array($columns)) {
            return array();
        }
        
        $new = array();
        $new['book'] = '所属书籍';
        array_insert($columns,2,$new);
        
        return $columns;
    }
    
    /**
     * 显示章节列内容
     * @author ifyn
     * @param string $column 列名
     * @param int $post_id 文章ID
     * @return void
     */
    public function realestate_passage_column($column, $post_id){
        if ($column !== 'book' || !is_numeric($post_id)) {
            return;
        }

        $parent_id = get_post_field('post_parent', $post_id);
        if ($parent_id) {
            echo '<a class="row-title" href="' . esc_url(add_query_arg('post_parent', $parent_id)) . '">' . esc_html(get_the_title($parent_id)) . '</a>';
        } else {
            echo '无';
        }
    }
    
    /**
     * 章节文章预查询
     * @author ifyn
     * @param WP_Query $wp_query WordPress查询对象
     * @return void
     */
    public function passage_posts_pre_query($wp_query){
        global $pagenow;
        if ($pagenow !== 'edit.php' || !isset($_REQUEST['post_type']) || $_REQUEST['post_type'] !== 'passage') {
            return;
        }
        
        $post_id = isset($_REQUEST['post_parent']) ? intval($_REQUEST['post_parent']) : 0;
        if ($post_id) {
            $wp_query->set('post_parent', $post_id);
        }
    }

    /**
     * 编辑表单顶部
     * @author ifyn
     * @return void
     */
    public function edit_form_top() {
        try {
            $post_id = isset($_GET['post']) ? (int)$_GET['post'] : (isset($_REQUEST['post_ID']) ? (int)$_REQUEST['post_ID'] : 0);
            $post_type = get_post_type($post_id);
            
            if ($post_type === 'book' && $post_id) {
                echo '<a href="' . esc_url(add_query_arg('post_parent', $post_id, admin_url('edit.php?post_type=passage'))) . '" class="button">查看当前书籍的全部章节 ></a>';
                echo '<a href="' . esc_url(add_query_arg('post_parent', $post_id, admin_url('post-new.php?post_type=passage'))) . '" class="button">添加章节 ></a>';
            }
                    
            if ($post_type === 'passage') {
                $parent_id = isset($_REQUEST['post_parent']) ? intval($_REQUEST['post_parent']) : (wp_get_post_parent_id($post_id) ?: 0);
                if ($parent_id) {
                    echo '<p>您正在为 <code>' . esc_html(get_the_title($parent_id)) . '</code> 编辑或添加章节</p>';
                    echo '<a href="' . esc_url(add_query_arg('post', $parent_id, admin_url('post.php?action=edit'))) . '" class="button">< 返回查看父书籍</a>';
                    echo '<a href="' . esc_url(add_query_arg('post_parent', $parent_id, admin_url('post-new.php?post_type=passage'))) . '" class="button">继续添加新章节 ></a>';
                }
            }
        } catch (\Exception $e) {
            error_log('Edit form top error: ' . $e->getMessage());
        }
    }

    /**
     * 注册书籍元框
     * @author ifyn
     * @return void
     */
    public function register_book_metabox(){
        try {
            $prefix = 'single_book_metabox';
            
            //书籍附加信息
            \CSF::createMetabox($prefix, array(
                'title'     => '书籍',
                'post_type' => array('book'),
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
                'fields' => array(
                    array(
                        'id'         => 'islide_book_role',
                        'type'       => 'radio',
                        'title'      => '书籍观看权限',
                        'inline'     => true,
                        'options'    => array(
                            'free'     => '无限制(免费)',
                            'money'    => '支付费用观看',
                            'credit'   => '支付积分观看',
                            'roles'    => '限制等级观看',
                            'comment'  => '评论观看',
                            'login'    => '登录观看',
                            'password' => '输入密码观看',
                        ),
                        'default'    => 'free',
                    ),
                    array(
                        'id'         => 'islide_book_roles',
                        'type'       => 'checkbox',
                        'title'      => '允许免费查看的用户组',
                        'inline'     => true,
                        'options'    => $roles_options,
                        'desc'       => '（可多选）请选择允许指定免费查看书籍的用户组',
                        'dependency' => array(
                            array('islide_book_role', '==', 'roles')
                        ),
                    ),
                    array(
                        'id'         => 'islide_book_not_login_buy',
                        'type'       => 'switcher',
                        'title'      => '开启未登录用户购买功能',
                        'desc'       => '未登录用户只能使用金钱支付，所有在设置权限是必须是 <code>付费</code>',
                        'default'    => 0,
                        'dependency' => array(
                            array('islide_book_role', '==', 'money')
                        ),
                    ),
                    array(
                        'id'         => 'islide_book_pay_total',
                        'type'       => 'spinner',
                        'title'      => '支付的总费用',
                        'default'    => 0,
                        'desc'       => '用于一次购买全部的费用',
                        'dependency' => array(
                            array('islide_book_role', 'any', 'money,credit')
                        ),
                    ),
                    array(
                        'id'         => 'islide_book_pay_value',
                        'type'       => 'spinner',
                        'title'      => '支付的单章节费用',
                        'default'    => 0,
                        'desc'       => '支持每章节单独购买',
                        'dependency' => array(
                            array('islide_book_role', 'any', 'money,credit')
                        ),
                    ),
                    array(
                        'id'         => 'book_status',
                        'type'       => 'radio',
                        'title'      => '书籍状态',
                        'inline'     => true,
                        'options'    => array(
                            'ongoing'   => '连载中',
                            'completed' => '已完结',
                        ),
                        'default'    => 'ongoing',
                    ),
                    array(
                        'id'         => 'book_source',
                        'type'       => 'radio',
                        'title'      => '书籍来源',
                        'inline'     => true,
                        'options'    => array(
                            'original'   => '原创',
                            'forward' => '转载',
                        ),
                        'default'    => 'original',
                    ),
                    array(
                        'id'         => 'book_author',
                        'type'       => 'text',
                        'title'=> '作者',
                        'default'    => '',
                        'dependency'     => array(
                            array('book_source', '==', 'forward')
                        ),
                    )
                )
            ));
            
            \CSF::createSection($prefix, array(
                'fields' => $this->get_passages()
            ));
        } catch (\Exception $e) {
            error_log('Register book metabox error: ' . $e->getMessage());
        }
    }
    
    /**
     * 注册章节元框
     * @author ifyn
     * @return void
     */
    public function register_passage_metabox(){
        try {
            $prefix = 'single_passage_metabox';
            
            //书籍附加信息
            \CSF::createMetabox($prefix, array(
                'title'     => '书籍章节',
                'post_type' => array('passage'),
                'context'   => 'normal',
                'data_type' => 'serialize',
                'theme'     => 'light'
            ));
            
            \CSF::createSection($prefix, array(
                'fields' => array(
                    array(
                        'id'          => 'post_parent',
                        'type'        => 'select',
                        'title'       => '父书籍',
                        'placeholder' => '选择父书籍',
                        'chosen'      => true,
                        'ajax'        => true,
                        'options'     => 'posts',
                        'query_args'  => array(
                            'post_type' => 'book'
                        ),
                        'default'     => isset($_GET['post_parent']) ? (int)$_GET['post_parent'] : 0,
                        'settings'    => array(
                            'min_length' => 1
                        )
                    ),
                )
            ));
        } catch (\Exception $e) {
            error_log('Register passage metabox error: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取所有章节
     * @author ifyn
     * @return array 章节字段数组
     */
    public function get_passages(){
        try {
            $post_id = isset($_GET['post']) ? (int)$_GET['post'] : 
                      (isset($_REQUEST['post_ID']) ? (int)$_REQUEST['post_ID'] : 0);

            if(!$post_id) {
                return array(
                    array(
                       'type'    => 'content',
                       'title'   => '',
                       'content' => '<div style="text-align: center; padding: 30px 15px;"><span class="dashicons dashicons-warning"></span> 请先发布后，刷新页面添加章节</div>'
                    )
                );
            }
            
            $args = array(
                'post_status'    => 'publish',
                'post_type'      => 'passage',
                'post_parent'    => $post_id,
                'posts_per_page' => -1,
            );
            
            $the_query = new \WP_Query($args);
            $fields = array(
                'id'        => 'group',
                'type'      => 'group',
                'title'     => '章节列表(只显示和删除章节)',
                'accordion_title_number' => true,
                'sanitize'  => false,
                'fields'    => array(
                    array(
                        'id'    => 'title',
                        'type'  => 'text',
                        'title' => '章节标题',
                        'attributes' => array(
                            'readonly' => 'readonly',
                        ),
                    ),
                    array(
                        'id'    => 'id',
                        'type'  => 'text',
                        'title' => '章节id',
                        'class' => 'islide—post—parent—id',
                        'attributes' => array(
                            'readonly' => 'readonly',
                        ),
                    ),
                    array(
                        'id'    => 'url',
                        'type'  => 'text',
                        'title' => '章节地址',
                        'attributes' => array(
                            'readonly' => 'readonly',
                            'style'    => 'width: 100%;',
                        ),
                        'sanitize' => false,
                    ),
                    array(
                        'id'    => 'preview',
                        'type'  => 'textarea',
                        'title' => '预览内容',
                        'desc'  => '如果设置了预览内容，则付费章节将显示此内容作为免费预览',
                        'attributes' => array(
                            'style' => 'width: 100%; min-height: 100px;',
                        ),
                        'sanitize' => false,
                    ),
                ),
            );
            
            if ($the_query->have_posts()) {
                while ($the_query->have_posts()) {
                    $the_query->the_post();
                    
                    $_post_id = get_the_id();
                    $meta = get_post_meta($_post_id, 'single_passage_metabox', true);
                    $passages = !empty($meta['book']) ? $meta['book'] : array();

                    $fields['default'][] = array(
                        'id'    => $_post_id,
                        'title' => get_the_title(),
                        'url'   => '/book/passage/'.$_post_id
                    );
                }
                
                wp_reset_postdata();
            }
            
            return array($fields);
        } catch (\Exception $e) {
            error_log('Get passages error: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * 保存书籍元框
     * @author ifyn
     * @param int $post_id 文章ID
     * @param WP_Post $post 文章对象
     * @param bool $update 是否更新
     * @return void
     */
    public function save_book_meta_box($post_id, $post, $update) {
        try {
            // 排除自动保存和修订版本
            if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
                return;
            }

            // 只在文章发布时执行
            if ($post->post_status !== 'publish') {
                return;
            }

            // 只在文章类型为 book 时执行
            if ($post->post_type !== 'book') {
                return;
            }
            
            $book_meta = isset($_POST['single_book_metabox']) ? $_POST['single_book_metabox'] : array();
            
            // 处理书籍状态
            $book_status = isset($book_meta['book_status']) ? sanitize_text_field($book_meta['book_status']) : 'ongoing';
            update_post_meta($post_id, 'book_status', $book_status);
            
            // 处理批量添加章节
            $book_batch = isset($book_meta['islide_book_batch']) && !empty($book_meta['islide_book_batch']) ? $book_meta['islide_book_batch'] : '';
            
            if ($book_batch) {
                $child_posts = explode(PHP_EOL, trim($book_batch, " \t\n\r"));
                
                $book_meta = get_post_meta($post_id, 'single_book_metabox', true);
                $book_meta = !empty($book_meta) && is_array($book_meta) ? $book_meta : array();
                $book_meta['group'] = !empty($book_meta['group']) && is_array($book_meta['group']) ? $book_meta['group'] : array();
                
                $i = count($book_meta['group']);
                
                foreach ($child_posts as $child_post) {
                    $data = explode("|", trim($child_post, " \t\n\r"));
                    $i++;
                    
                    $book_url = isset($data[0]) && !empty($data[0]) ? $data[0] : '';
                    $child_title = isset($data[1]) && !empty($data[1]) ? $data[1] : '第'.$i.'章';
                    $preview_url = isset($data[2]) && !empty($data[2]) ? $data[2] : '';
                    $post_content = isset($data[3]) && !empty($data[3]) ? $data[3] : '';
                    
                    if (!filter_var($book_url, FILTER_VALIDATE_URL)) {
                        continue;
                    }
        
                    $child_post_args = array(
                        'post_title'   => $child_title,
                        'post_content' => $post_content,
                        'post_type'    => 'passage',
                        'post_status'  => 'publish',
                        'post_parent'  => $post_id,
                    );
                    
                    $child_post_id = wp_insert_post($child_post_args);
                    
                    if ($child_post_id) {
                        $passage_metabox = array(
                            'book' => array(
                                'preview_url' => $preview_url,
                                'url'         => $book_url
                            ),
                            'post_parent' => $post_id
                        );
                        
                        update_post_meta($child_post_id, 'single_passage_metabox', $passage_metabox);
                        update_post_meta($child_post_id, 'islide_seo_title', $post->post_title.':'.$child_title);
                        
                        $passage_book = $passage_metabox['book'];
                        $passage_book['id'] = $child_post_id;
                        $passage_book['title'] = $child_title;
                        $passage_book['type'] = 'passage';
                        
                        $book_meta['group'][] = $passage_book;
                        update_post_meta($post_id, 'single_book_metabox', $book_meta);
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Save book meta box error: ' . $e->getMessage());
        }
    }

    /**
     * 保存章节元框
     * @author ifyn
     * @param int $post_id 文章ID
     * @param WP_Post $post 文章对象
     * @param bool $update 是否更新
     * @return void
     */
    public function save_passage_meta_box($post_id, $post, $update) {
        try {
            // 排除自动保存和修订版本
            if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
                return;
            }
            
            // 只在文章发布时执行
            if ($post->post_status !== 'publish') {
                return;
            }

            // 只在文章类型为 passage 时执行
            if ($post->post_type !== 'passage') {
                return;
            }

            $passage_meta = isset($_POST['single_passage_metabox']) ? $_POST['single_passage_metabox'] : array();
            
            if (isset($passage_meta['post_parent'])) {
                $post_parent = sanitize_text_field($passage_meta['post_parent']);
                
                if (get_post_type($post_parent) !== 'book' && $post_parent) {
                    return;
                }
                
                $current_parent = get_post_field('post_parent', $post_id);
                if ($post_parent != $current_parent) {
                    wp_update_post(array(
                        'ID'          => $post_id,
                        'post_parent' => (int)$post_parent
                    ));
                    
                    // 删除原有的
                    $current_book_meta = get_post_meta($current_parent, 'single_book_metabox', true);
                    $current_book_meta = !empty($current_book_meta) && is_array($current_book_meta) ? $current_book_meta : array();
                    
                    if ($current_book_meta) {
                        $current_key = array_search($post_id, array_column($current_book_meta['group'], 'id'));
                        if ($current_key !== false) {
                            unset($current_book_meta['group'][$current_key]);
                            update_post_meta($current_parent, 'single_book_metabox', $current_book_meta);
                        }
                    }
                }
                
                // 添加新的
                $book_meta = get_post_meta($post_parent, 'single_book_metabox', true);
                if (!is_array($book_meta)) {
                    $book_meta = array(
                        'islide_book_role' => 'free',
                        'group'            => array()
                    );
                }
                
                if (!isset($book_meta['group']) || !is_array($book_meta['group'])) {
                    $book_meta['group'] = array();
                }
                
                $key = false;
                if (!empty($book_meta['group'])) {
                    foreach ($book_meta['group'] as $idx => $item) {
                        if (isset($item['id']) && $item['id'] == $post_id) {
                            $key = $idx;
                            break;
                        }
                    }
                }
                
                $passage_book = isset($passage_meta['book']) ? $passage_meta['book'] : array();
                $passage_book['id'] = $post_id;
                $passage_book['title'] = $post->post_title;
                $passage_book['type'] = 'passage';
                $passage_book['preview'] = isset($passage_meta['preview']) ? $passage_meta['preview'] : '';
                
                $thumb_id = get_post_thumbnail_id($post_id);
                $thumb_url = wp_get_attachment_image_src($thumb_id, 'full');
                $passage_book['thumb'] = $thumb_url[0] ?: '';
                
                if ($key !== false) {
                    $book_meta['group'][$key] = $passage_book;
                } else {
                    $book_meta['group'][] = $passage_book;
                }
                
                update_post_meta($post_parent, 'single_book_metabox', $book_meta);
            }
        } catch (\Exception $e) {
            error_log('Save passage meta box error: ' . $e->getMessage());
        }
    }
    
    /**
     * 删除章节元框
     * @author ifyn
     * @param int $post_id 文章ID
     * @return void
     */
    public function delete_passage_meta_box($post_id) {
        try {
            if (get_post_type($post_id) === 'passage') {
                $parent_id = get_post_field('post_parent', $post_id);
                
                if (!$parent_id) {
                    return;
                }
                
                $book_meta = get_post_meta($parent_id, 'single_book_metabox', true);
                $book_meta = !empty($book_meta) && is_array($book_meta) ? $book_meta : array();
                
                if ($book_meta) {
                    $key = array_search($post_id, array_column($book_meta['group'], 'id'));
                    if ($key !== false) {
                        unset($book_meta['group'][$key]);
                        update_post_meta($parent_id, 'single_book_metabox', $book_meta);
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Delete passage meta box error: ' . $e->getMessage());
        }
    }
    
    /**
     * 添加导航
     * @author ifyn
     * @return void
     */
    public function islide_book_menu() {
        try {
            global $pagenow, $current_screen;
            
            if (
                in_array($pagenow, array('edit.php'))
                || in_array($pagenow, array('post-new.php'))
                || in_array($pagenow, array('post.php'))
                || isset($_GET['taxonomy'])
                || in_array($pagenow, array('edit.php')) 
                && isset($_REQUEST['post_type'])
            ) {
                if (
                    isset($_REQUEST['post_type'])
                    && in_array($_REQUEST['post_type'], array('book','passage'))
                    || isset($current_screen->post_type)
                    && in_array($current_screen->post_type, array('book','passage'))
                    || isset($_GET['post']) 
                    && in_array(get_post_type($_GET['post']), array('book','passage'))
                ) {
                    $post_id = isset($_GET['post']) ? (int)$_GET['post'] : (isset($_GET['post_parent']) ? (int)$_GET['post_parent'] : 0);
                    $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : get_post_type($post_id);
                    
                    if ($post_type == 'passage' && $post_id) {
                        $post_id = wp_get_post_parent_id() ?: 0;
                    }

                    $current1a = in_array($pagenow, array('edit.php')) && isset($_REQUEST['post_type']) && $_REQUEST['post_type'] == 'book' ? ' class="current"' : '';
                    $current1b = in_array($pagenow, array('post-new.php')) && isset($_REQUEST['post_type']) && $_REQUEST['post_type'] == 'book' ? ' class="current"' : '';
                    $current1c = isset($_GET['taxonomy']) && $_GET['taxonomy'] == 'book_cat' ? ' class="current"' : '';
                    $current1d = isset($_GET['taxonomy']) && $_GET['taxonomy'] == 'book_season' ? ' class="current"' : '';
                    $current1e = in_array($pagenow, array('edit.php')) && isset($_REQUEST['post_type']) && $_REQUEST['post_type'] == 'passage' ? ' class="current"' : '';
                    $current1f = in_array($pagenow, array('post-new.php')) && isset($_REQUEST['post_type']) && $_REQUEST['post_type'] == 'passage' ? ' class="current"' : '';
                    
                    echo '
                        <ul class="MnTpAdn filter-links" id="tr-grabber-menu" style="display: none;">
                            <li><a'.$current1a.' href="'.admin_url('edit.php?post_type=book').'">全部书籍</a></li>
                            <li><a'.$current1b.' href="'.admin_url('post-new.php?post_type=book').'">添加书籍</a></li>
                            <li><a'.$current1c.' href="'.admin_url('edit-tags.php?taxonomy=book_cat&post_type=book').'">分类</a></li>
                            <li'.$current1d.'><a href="'.add_query_arg('post_parent', $post_id, admin_url('edit-tags.php?taxonomy=book_season&post_type=book')).'">书籍系列</a></li>
                            <li><a'.$current1e.' href="'.add_query_arg('post_parent', $post_id, admin_url('edit.php?post_type=passage')).'">全部章节</a></li>
                            <li><a'.$current1f.' href="'.add_query_arg('post_parent', $post_id, admin_url('post-new.php?post_type=passage')).'">添加章节</a></li>
                        </ul>
                    ';
                }
            }
        } catch (\Exception $e) {
            error_log('Book menu error: ' . $e->getMessage());
        }
    }
}