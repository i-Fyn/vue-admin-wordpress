<?php

namespace islide\Menu;

class Shop{
    public static function init(){
        // 注册商品文章类型
        add_action('init', [__CLASS__, 'register_shop_post_type']);
        // 注册商品分类法
        add_action('init', [__CLASS__, 'register_shop_taxonomies']);
        // 添加自定义元框
        add_action('add_meta_boxes', [__CLASS__, 'add_shop_meta_box']);

        add_action('save_post', [__CLASS__, 'save_shop_meta_box'],99,2);

    }

    // 注册商品文章类型
    public static function register_shop_post_type()
    {
        if (post_type_exists('shop')) {
            return;
        }

        $labels = [
            'name'               => '商城',
            'singular_name'      => '商城',
            'menu_name'          => '商城',
            'name_admin_bar'     => '商城',
            'add_new'            => '添加新商品',
            'add_new_item'       => '添加新商品',
            'new_item'           => '新商品',
            'edit_item'          => '编辑商品',
            'view_item'          => '查看商品',
            'all_items'          => '所有商品',
            'search_items'       => '搜索商品',
            'not_found'          => '没有找到商品',
            'not_found_in_trash' => '垃圾箱中没有找到商品',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'has_archive'        => true,
            'rewrite'            => [
                'slug'       => 'shop',
                'with_front' => true,
                'feeds'      => false,
                'pages'      => true,
                'ep_mask'    => EP_PERMALINK,
            ],
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt', 'comments', 'author'],
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-groups',
            'taxonomies'         => ['post_tag', 'shop_cat'],
        ];

        register_post_type('shop', $args);
    }

    // 注册商品分类法
    public static function register_shop_taxonomies()
    {
        if (taxonomy_exists('shop_cat')) {
            return;
        }

        register_taxonomy(
            'shop_cat',
            'shop',
            [
                'hierarchical' => true,
                'labels'       => [
                    'name'              => '商品分类',
                    'singular_name'     => '分类',
                    'search_items'      => '搜索分类',
                    'all_items'         => '所有分类',
                    'parent_item'       => '父分类',
                    'parent_item_colon' => '父分类:',
                    'edit_item'         => '编辑分类',
                    'update_item'       => '更新分类',
                    'add_new_item'      => '添加新分类',
                    'new_item_name'     => '新分类名称',
                    'menu_name'         => '分类',
                ],
                'show_in_rest' => true,
            ]
        );
    }

    // 添加自定义元框
    public static function add_shop_meta_box()
    {
        add_meta_box(
            'shop_specifications',
            '商品规格',
            [__CLASS__, 'render_shop_meta_box'],
            'shop',
            'normal',
            'high'
        );
    }





// 渲染元框内容
public static function render_shop_meta_box($post) {
    // 获取已有规格数据
    $specifications = get_post_meta($post->ID, 'single_shop_metabox', true) ?: [];
    ?>
    <div id="specification-container" style="overflow:scroll;" >
        <h4>规格组</h4>
        <div id="spec-groups">
            <?php if (isset($specifications['spec_groups']) && !empty($specifications['spec_groups'])) : ?>
                <?php foreach ($specifications['spec_groups'] as $group) : ?>
                    <div class="spec-group">
                        <label>规格名称：</label>
                        <input type="text" name="spec_names[]" value="<?php echo esc_attr($group['name']); ?>" placeholder="例如：颜色">
                        <label>规格值：</label>
                        <input type="text" name="spec_values[]" value="<?php echo esc_attr(implode(',', $group['values'])); ?>" placeholder="例如：红,蓝,绿">
                        <button type="button" class="remove-spec-group">移除</button>
                    </div>
                <?php endforeach; ?>
        
            <?php endif; ?>
        </div>
        <button type="button" id="add-spec-group">添加规格组</button>
        <button type="button" id="generate-specs">生成规格表</button>
        
        
        <h4>规格表（批量设置请点击应用）</h4>
        <table id="specifications-table" border="1" style="width:100%; border-collapse: collapse; margin-top: 10px;">
            <thead>
                <tr>
                    <th>规格组合</th>
                    <th>单价</th>
                    <th>库存数量</th>
                    <th>已售数量</th>
                    <th>限购数量</th>
                    <th>限时折扣</th>
                </tr>
            </thead>
            <tbody>
                <?php if (isset($specifications['specifications']) && !empty($specifications['specifications'])) : ?>
                    <?php foreach ($specifications['specifications'] as $spec) : ?>
                        <tr>
                             <td>
                            <?php echo esc_html($spec['name']); ?>
                            <input type="hidden" name="spec_combinations[]" value="<?php echo esc_html($spec['name']); ?>">
                            </td>
                            <td><input type="number" name="spec_price[]" value="<?php echo esc_attr($spec['price']); ?>"></td>
                            <td><input type="number" name="spec_stock[]" value="<?php echo esc_attr($spec['stock']); ?>"></td>
                            <td><input type="number" name="spec_sold[]" value="<?php echo esc_attr($spec['sold']); ?>"></td>
                            <td><input type="number" name="spec_limit[]" value="<?php echo esc_attr($spec['limit']); ?>"></td>
                            <td><input type="number" name="spec_discount[]" value="<?php echo esc_attr($spec['discount']); ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <!-- 👇 批量设置行 -->
                <tr id="batch-setting-row">
                    <td>批量设置</td>
                    <input type="hidden" name="spec_combinations[]" value="批量设置">
                    <td><input type="number" placeholder="单价" class="batch-price"></td>
                    <td><input type="number" placeholder="库存" class="batch-stock"></td>
                    <td><input type="number" placeholder="已售" class="batch-sold"></td>
                    <td><input type="number" placeholder="限购" class="batch-limit"></td>
                    <td>
                        <input type="number" placeholder="折扣" class="batch-discount">
                    </td>
                    <td>
                        <button type="button" id="apply-batch" style="margin-left: 8px;">应用</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const specGroupsContainer = document.getElementById('spec-groups');
            const addSpecGroupButton = document.getElementById('add-spec-group');
            const generateButton = document.getElementById('generate-specs');
            const specTable = document.getElementById('specifications-table').querySelector('tbody');
            const applyButton = document.querySelector('#apply-batch');
            
            applyButton.addEventListener('click', function (e) {

                    const table = document.getElementById('specifications-table');
                    const batchRow = document.getElementById('batch-setting-row');
            
                    const batchValues = {
                        price: batchRow.querySelector('.batch-price').value,
                        stock: batchRow.querySelector('.batch-stock').value,
                        sold: batchRow.querySelector('.batch-sold').value,
                        limit: batchRow.querySelector('.batch-limit').value,
                        discount: batchRow.querySelector('.batch-discount').value
                    };
            
                    const rows = table.querySelectorAll('tbody tr');
            
                    rows.forEach(row => {
                        row.querySelector('input[name="spec_price[]"]').value = batchValues.price;
                        row.querySelector('input[name="spec_stock[]"]').value = batchValues.stock;
                        row.querySelector('input[name="spec_sold[]"]').value = batchValues.sold;
                        row.querySelector('input[name="spec_limit[]"]').value = batchValues.limit;
                        row.querySelector('input[name="spec_discount[]"]').value = batchValues.discount;
                    });
            });
            // 添加规格组
            addSpecGroupButton.addEventListener('click', function () {
                const groupDiv = document.createElement('div');
                groupDiv.className = 'spec-group';
                groupDiv.innerHTML = `
                    <label>规格名称：</label>
                    <input type="text" name="spec_names[]" value="颜色">
                    <label>规格值：</label>
                    <input type="text" name="spec_values[]" value="红,蓝,绿">
                    <button type="button" class="remove-spec-group">移除</button>
                `;
                specGroupsContainer.appendChild(groupDiv);

                // 添加移除按钮事件
                groupDiv.querySelector('.remove-spec-group').addEventListener('click', function () {
                    groupDiv.remove();
                });
            });

            // 生成规格表
        generateButton.addEventListener('click', function () {
            const specNames = Array.from(document.querySelectorAll('input[name="spec_names[]"]'));
            const specValues = Array.from(document.querySelectorAll('input[name="spec_values[]"]'));
        
            if (!specNames.length || !specValues.length) {
                alert('请填写至少一个规格组');
                return;
            }
        
            const groups = specNames.map((input, index) => {
                const name = input.value.trim();
                const values = specValues[index].value
                  .split(',')
                  .map(v => v.trim())
                  .filter(Boolean); // 过滤空字符串
            
                return {
                    name,
                    values
                };
            }).filter(group => group.name && group.values.length > 0); // ❗ 过滤掉空组
        
            function combineSpecs(groupIndex, currentCombo) {
                if (groupIndex === groups.length) {
                    return [currentCombo];
                }
                const group = groups[groupIndex];
                const combinations = [];
                group.values.forEach(value => {
                    combinations.push(...combineSpecs(groupIndex + 1, currentCombo.concat(`${group.name}:${value}`)));
                });
                return combinations;
            }
            const batchRow = document.getElementById('batch-setting-row');
            if (batchRow) {
                batchRow.remove(); // 先移除，稍后重新加回来
            }
        
            const combinations = combineSpecs(0, []);
            specTable.innerHTML = ''; // 清空表格内容
        
            // 生成规格组合行
            combinations.forEach(combo => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                    ${combo.join(' / ')}
                    <input type="hidden" name="spec_combinations[]" value="${combo.join(' / ')}">
                    </td>
                    <td><input type="number" name="spec_price[]" value=0 placeholder="单价"></td>
                    <td><input type="number" name="spec_stock[]" value=0 placeholder="库存数量"></td>
                    <td><input type="number" name="spec_sold[]" value=0 placeholder="已售数量"></td>
                    <td><input type="number" name="spec_limit[]" value=0 placeholder="限购数量"></td>
                    <td><input type="number" name="spec_discount[]" value=100 placeholder="限时折扣"></td>
                `;
                specTable.appendChild(row);
            });
            
            if (batchRow) {
                specTable.appendChild(batchRow);
            }

        });

        });
        

        
    </script>
    <?php
}

// 保存元数据
public static function save_shop_meta_box($post_id,$post) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
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
    
     // 保存规格组
    $spec_groups = [];
    $spec_names = isset($_POST['spec_names']) ? array_map('sanitize_text_field', $_POST['spec_names']) : [];
    $spec_values = isset($_POST['spec_values']) ? array_map('sanitize_text_field', $_POST['spec_values']) : [];

    for ($i = 0; $i < count($spec_names); $i++) {
        $spec_groups[] = [
            'name' => $spec_names[$i],
            'values' => explode(',', $spec_values[$i]),
        ];
    }
    
    $serialized_data = get_post_meta($post_id, 'single_shop_metabox', true);

    if (!$serialized_data) {
        return;
    }
    // 将序列化字符串转为数组
    $data_array = maybe_unserialize($serialized_data);
    
    // 检查数据是否为有效数组
    if (!is_array($data_array)) {
        return;
    }
    // 添加新的键值对
    $data_array['spec_groups'] = $spec_groups;

    // 保存规格数据
    $specifications = [];
    $spec_prices = isset($_POST['spec_price']) ? array_map('sanitize_text_field', $_POST['spec_price']) : [];
    $spec_stocks = isset($_POST['spec_stock']) ? array_map('sanitize_text_field', $_POST['spec_stock']) : [];
    $spec_solds = isset($_POST['spec_sold']) ? array_map('sanitize_text_field', $_POST['spec_sold']) : [];
    $spec_limit = isset($_POST['spec_limit']) ? array_map('sanitize_text_field', $_POST['spec_limit']) : [];
    $spec_discount = isset($_POST['spec_discount']) ? array_map('sanitize_text_field', $_POST['spec_discount']) : [];
    $spec_combinations = isset($_POST['spec_combinations']) ? $_POST['spec_combinations'] : [];
    
      
    for ($i = 0; $i < count($spec_prices); $i++) {
        $specifications[] = [
            'name' => sanitize_text_field($spec_combinations[$i]),
            'price' => $spec_prices[$i],
            'stock' => $spec_stocks[$i],
            'sold' => $spec_solds[$i],
            'limit'=>$spec_limit[$i],
            'discount' => $spec_discount[$i],
        ];
    }
    $data_array['specifications'] = $specifications;
    update_post_meta($post_id, 'single_shop_metabox', $data_array);
}
}