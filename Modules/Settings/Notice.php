<?php 
namespace islide\Modules\Settings;

class Notice {
    

    public function init(){
        add_action('before_delete_post', array($this,'delete_notice_meta_box'));
        $this->register_notice_metabox();
    }

    public function register_notice_metabox(){
        $prefix = 'single_notice_metabox';
        
        \CSF::createMetabox($prefix, array(
            'title'     => '公告设置',
            'post_type' => 'notice',
            'context'   => 'side',
            'data_type' => 'serialize',
            'theme'     => 'light'
        ));

        // 基础设置
        \CSF::createSection($prefix, array(
            'fields' => array(
                array(
                    'id'      => 'show_notice',
                    'type'    => 'switcher',
                    'title'   => '展示前台',
                    'default' => 1
                ),
                array(
                    'id'        => 'end_date', 
                    'type'      => 'date',
                    'title'     => '公告截止日期',
                    'settings'  => array(
                        'dateFormat' => 'yy-mm-dd',
                    ),
                    'validate' => 'csf_validate_required',
                ),
            )
        ));

        // 按钮设置
        \CSF::createSection($prefix, array(
            'fields' => array(
                array(
                    'id'     => 'button_group',
                    'type'   => 'group',
                    'title'  => '操作按钮',
                    'fields' => array(
                        array(
                            'id'          => 'title',
                            'type'        => 'text',
                            'title'       => '按钮文字',
                            'placeholder' => '例：查看详情',
                            'validate'    => 'csf_validate_required',
                        ),
                        array(
                            'id'      => 'link',
                            'type'    => 'text',
                            'title'   => '跳转链接',
                            'default' => '#',
                            'validate' => 'csf_validate_url',
                        ),
                        array(
                            'id'      => 'type',
                            'type'    => 'select',
                            'title'   => '按钮类型',
                            'options' => array(
                                'primary' => '主要按钮',
                                'secondary' => '次要按钮',
                                'link' => '文字链接'
                            )
                        )
                    ),
                ),
            )
        ));
    }


    public function delete_notice_meta_box($post_id) {
        if (get_post_type($post_id) !== 'notice') return;

        delete_post_meta($post_id,'single_notice_metabox');
    }
    
}