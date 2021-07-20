
<div class="a2w_product_tab_menu">
    <ul class="subsubsub" style="float: initial;margin-left:12px">
        <li><a href="#" data-tab="general" class="current">General</a> | </li>
        <li><a href="#" data-tab="variations">Manage Variations</a></li>
    </ul>
    <script>
    jQuery(".a2w_product_tab_menu li a").click(function () {
        jQuery(".a2w_product_tab_menu li a").removeClass('current');
        jQuery(this).addClass('current');
        
        jQuery(".a2w_product_tab").hide();
        jQuery(".a2w_product_tab."+jQuery(this).data('tab')).show();
        return false;
    });
    </script>
</div>

<div class="a2w_product_tab general">
    <div class="options_group">
        <?php 
        woocommerce_wp_text_input(array(
            'id' => '_a2w_external_id',
            'value' => get_post_meta($post_id, '_a2w_external_id', true),
            'label' => __('External Id', 'ali2woo'),
            'desc_tip' => true,
            'description' => __('External Aliexpress Product Id', 'ali2woo'),
        ));

        woocommerce_wp_text_input(array(
            'id' => '_a2w_orders_count',
            'value' => get_post_meta($post_id, '_a2w_orders_count', true),
            'label' => __('Orders count', 'ali2woo'),
            'desc_tip' => true,
            'description' => __('Aliexpress orders count', 'ali2woo'),
            'custom_attributes' => array('readonly'=>'readonly'),
        ));

        $disable_sync = get_post_meta($post_id, '_a2w_disable_sync', true);

        woocommerce_wp_checkbox(array(
            'id' => '_a2w_disable_sync',
            'value' => $disable_sync ? 'yes' : 'no',
            'label' => __('Disable synchronization?', 'ali2woo'),
            'description' => __('Disable global synchronization for this product', 'ali2woo'),
        ));
        ?>

        <script>jQuery("#_a2w_disable_sync").change(function () {if(jQuery(this).is(":checked")){jQuery("._a2w_disable_var_price_change_field, ._a2w_disable_var_quantity_change_field, ._a2w_disable_add_new_variants").hide();}else{jQuery("._a2w_disable_var_price_change_field, ._a2w_disable_var_quantity_change_field, ._a2w_disable_add_new_variants").show();}});</script>

        <?php
        woocommerce_wp_checkbox(array(
            'id' => '_a2w_disable_var_price_change',
            'value' => get_post_meta($post_id, '_a2w_disable_var_price_change', true) ? 'yes' : 'no',
            'label' => __('Disable price change?', 'ali2woo'),
            'description' => __('Disable variations price change', 'ali2woo'),
        ));
        woocommerce_wp_checkbox(array(
            'id' => '_a2w_disable_var_quantity_change',
            'value' => get_post_meta($post_id, '_a2w_disable_var_quantity_change', true) ? 'yes' : 'no',
            'label' => __('Disable quantity change?', 'ali2woo'),
            'description' => __('Disable variations quantity change', 'ali2woo'),
        ));
        woocommerce_wp_checkbox(array(
            'id' => '_a2w_disable_add_new_variants',
            'value' => get_post_meta($post_id, '_a2w_disable_add_new_variants', true) ? 'yes' : 'no',
            'label' => __('Disable add new variants?', 'ali2woo'),
            'description' => __('Disable add new variants if they appear.', 'ali2woo'),
        ));

        if ($disable_sync) {
            echo '<script>jQuery("._a2w_disable_var_price_change_field, ._a2w_disable_var_quantity_change_field, ._a2w_disable_add_new_variants").hide();</script>';
        }

        woocommerce_wp_text_input(array(
            'id' => '_a2w_product_url',
            'value' => get_post_meta($post_id, '_a2w_product_url', true),
            'label' => __('Product url', 'ali2woo'),
            'desc_tip' => true,
            'description' => __('Affiliate product url', 'ali2woo'),
            'custom_attributes' => array('readonly'=>'readonly'),
        ));

        woocommerce_wp_text_input(array(
            'id' => '_a2w_original_product_url',
            'value' => get_post_meta($post_id, '_a2w_original_product_url', true),
            'label' => __('Original product url', 'ali2woo'),
            'desc_tip' => true,
            'description' => __('Original product url', 'ali2woo'),
            'custom_attributes' => array('readonly'=>'readonly'),
        ));
        ?>
    </div>

    <div class="options_group">
        <?php $last_update = get_post_meta($post_id, '_a2w_last_update', true); ?>
        <p class="form-field _a2w_last_update_field ">
            <label>Last update</label>
            <?php if($last_update): ?>
                <span><?php echo date("F j, Y, H:i:s", $last_update); ?> <a href="#clean" id="_a2w_last_update_clean">Clean</a></span>
            <?php else: ?>
                <span>Not set</span>
            <?php endif; ?>
            <span class="woocommerce-help-tip" data-tip="Last update"></span>
            <input type="hidden" class="" name="_a2w_last_update" id="_a2w_last_update" value="<?php echo $last_update;?>" />
        </p>
        <script>jQuery("#_a2w_last_update_clean").click(function () {jQuery("#_a2w_last_update").val(""); jQuery(this).parents("span").html("Not set");jQuery.post(ajaxurl, {"action": "a2w_data_last_update_clean", "post_id":<?php echo $post_id; ?>, "type":"product"}); return false;});</script>
                
        <?php $reviews_last_update = get_post_meta($post_id, '_a2w_reviews_last_update', true); ?>
        <p class="form-field _a2w_reviews_last_update_field ">
            <label>Reviews last update</label>
            <?php if($reviews_last_update): ?>
                <span><?php echo date("F j, Y, H:i:s", $reviews_last_update); ?> <a href="#clean" id="_a2w_reviews_last_update_clean">Clean</a></span>
            <?php else: ?>
                <span>Not set</span>
            <?php endif; ?>
            <span class="woocommerce-help-tip" data-tip="Last update"></span>
            <input type="hidden" class="" name="_a2w_reviews_last_update" id="_a2w_reviews_last_update" value="<?php echo $reviews_last_update;?>" />
        </p>
        <script>jQuery("#_a2w_reviews_last_update_clean").click(function () {jQuery("#_a2w_reviews_last_update").val(""); jQuery(this).parents("span").html("Not set");jQuery.post(ajaxurl, {"action": "a2w_data_last_update_clean", "post_id":<?php echo $post_id; ?>, "type":"review"}); return false;});</script>
    </div>
</div>

<div class="a2w_product_tab variations" style="display:none">
    <div class="options_group">
        <p class="form-field _a2w_deleted_variations_attributes">
            <label for="_a2w_deleted_variations_attributes">Removed attributes</label>
            <span id="_a2w_deleted_variations_attributes">
                <?php
                $deleted_variations_attributes = get_post_meta($post_id, '_a2w_deleted_variations_attributes', true);
                if (empty($deleted_variations_attributes)) {
                    echo '<i>' . __('No deleted attributes of variations', 'ali2woo') . '</i>';
                } else {
                    foreach ($deleted_variations_attributes as $ka => $av) {
                        echo '<span class="va" style="display: inline-block;margin-right:10px;margin-bottom: 5px;background-color: #eee;padding: 0px 10px;" data-attr-id="' . urldecode($ka) . '"><i>' . $av['current_name'] . '</i> <a href="#" style="text-decoration: none;"><span class="dashicons dashicons-trash"></span></a></span> ';
                    }
                }
                ?>
            </span>
        </p>
        <script>jQuery("#_a2w_deleted_variations_attributes > span > a").click(function () {var this_v_a = jQuery(this).parents("span.va");jQuery.post(ajaxurl, {"action": "a2w_data_remove_deleted_attribute", "post_id":<?php echo $post_id; ?>, "id":jQuery(this_v_a).attr("data-attr-id")}).done(function (response) {jQuery(this_v_a).remove(); if(jQuery("#_a2w_deleted_variations_attributes > span").length==0){jQuery("#_a2w_deleted_variations_attributes").html("<i>' . __('No deleted attributes of variations', 'ali2woo') . '</i>");} }).fail(function (xhr, status, error) {console.log(error);});return false;});</script>
    </div>

                
    <div class="options_group">
        <p class="form-field _a2w_deleted_variations">
            <label for="_a2w_deleted_variations">Removed variations</label>
            <span id="_a2w_deleted_variations">
            <?php
            $skip_meta = get_post_meta($post_id, "_a2w_skip_meta", true);
            if (!empty($skip_meta['skip_vars']) && is_array($skip_meta['skip_vars'])) {
                echo '<span class="var" style="display: inline-block;margin-right:10px;margin-bottom: 5px;background-color: #eee;padding: 0px 10px;" data-attr-id="all"><a href="#" style="text-decoration: none;">RESET ALL <span class="dashicons dashicons-trash"></span></a></span> ';
                foreach ($skip_meta['skip_vars'] as $v) {
                    echo '<span class="var" style="display: inline-block;margin-right:10px;margin-bottom: 5px;background-color: #eee;padding: 0px 10px;" data-attr-id="' . $v . '"><i>' . $v . '</i> <a href="#" style="text-decoration: none;"><span class="dashicons dashicons-trash"></span></a></span> ';
                }
            } else {
                    echo '<i>' . __('No deleted variations', 'ali2woo') . '</i>';
            }
            ?>
            </span>
        </p>
        <script>jQuery("#_a2w_deleted_variations > span > a").click(function () {var this_v_a = jQuery(this).parents("span.var");var var_id = jQuery(this_v_a).attr("data-attr-id");if(var_id!='all' || confirm("Are you sure you want to reset all variations?")){jQuery.post(ajaxurl, {"action": "a2w_data_remove_deleted_variation", "post_id":<?php echo $post_id; ?>, "id":var_id}).done(function (response) {jQuery(this_v_a).remove(); if(var_id=='all'||jQuery("#_a2w_deleted_variations > span").length==0){jQuery("#_a2w_deleted_variations").html("<i>No deleted variations</i>");} }).fail(function (xhr, status, error) {console.log(error);});}return false;});</script>
    </div>

</div>

