<?php

/**
 * Description of A2W_ImportPageController
 *
 * @author Andrey
 * 
 * @autoload: a2w_init
 */
if (!class_exists('A2W_ImportPageController')) {

    class A2W_ImportPageController extends A2W_AbstractAdminPage {

        private $product_import_model;
        private $woocommerce_model;
        private $country_model;
        private $import_process;
        private $override_model;

        public function __construct() {
            $this->product_import_model = new A2W_ProductImport();
            $this->woocommerce_model = new A2W_Woocommerce();
            $this->country_model = new A2W_Country();
            $this->import_process    = A2W_ImportProcess::instance();
            $this->override_model = new A2W_Override();
            
            $products_cnt = $this->product_import_model->get_products_count();

            parent::__construct(__('Import List', 'ali2woo'), __('Import List', 'ali2woo') .' '. ($products_cnt ? ' <span class="update-plugins count-' . $products_cnt . '"><span class="plugin-count">' . $products_cnt . '</span></span>' : ''), 'import', 'a2w_import', 20);

            add_action('wp_ajax_a2w_push_product', array($this, 'ajax_push_product'));
            add_action('wp_ajax_a2w_delete_import_products', array($this, 'ajax_delete_import_products'));
            add_action('wp_ajax_a2w_update_product_info', array($this, 'ajax_update_product_info'));
            add_action('wp_ajax_a2w_link_to_category', array($this, 'ajax_link_to_category'));
            add_action('wp_ajax_a2w_get_all_products_to_import', array($this, 'ajax_get_all_products_to_import'));
            add_action('wp_ajax_a2w_get_product', array($this, 'ajax_get_product'));
            add_action('wp_ajax_a2w_split_product', array($this, 'ajax_split_product'));
            add_action('wp_ajax_a2w_import_images_action', array($this, 'ajax_import_images_action'));
            add_action('wp_ajax_a2w_import_cancel_images_action', array($this, 'ajax_import_cancel_images_action'));
            add_action('wp_ajax_a2w_search_tags', array($this, 'ajax_search_tags'));
            add_action('wp_ajax_a2w_search_products', array($this, 'ajax_search_products'));
            add_action('wp_ajax_a2w_override_product', array($this, 'ajax_override_product'));
            add_action('wp_ajax_a2w_override_order_variations', array($this, 'ajax_override_order_variations'));
            add_action('wp_ajax_a2w_cancel_override_product', array($this, 'ajax_cancel_override_product'));
            
            add_filter('tiny_mce_before_init', array($this, 'tiny_mce_before_init'), 30);

            add_filter('a2w_woocommerce_after_add_product', array($this, 'woocommerce_after_add_product'), 30, 4);
        }

        public function before_admin_render() {
            if (isset($_REQUEST['delete_id']) && $_REQUEST['delete_id']) {
                if($product = $this->product_import_model->get_product($_REQUEST['delete_id'])){
                    foreach($product['tmp_edit_images'] as $edit_image){
                        if(isset($edit_image['attachment_id'])){
                            A2W_Utils::delete_attachment($edit_image['attachment_id'], true);
                        }
                    }
                    $this->product_import_model->del_product($_REQUEST['delete_id']);
                }
                wp_redirect(admin_url('admin.php?page=a2w_import'));
            } else if ((isset($_REQUEST['action']) && $_REQUEST['action'] == "delete_all") || (isset($_REQUEST['action2']) && $_REQUEST['action2'] == "delete_all")) {
                $product_ids = $this->product_import_model->get_product_id_list();
                
                foreach($product_ids as $product_id){
                    if($product = $this->product_import_model->get_product($product_id)){
                        foreach($product['tmp_edit_images'] as $edit_image){
                            if(isset($edit_image['attachment_id'])){
                                A2W_Utils::delete_attachment($edit_image['attachment_id'], true);
                            }
                        }
                    }
                }
                
                $this->product_import_model->del_product($product_ids);

                wp_redirect(admin_url('admin.php?page=a2w_import'));
            } else if ((isset($_REQUEST['action']) && $_REQUEST['action'] == "push_all") || (isset($_REQUEST['action2']) && $_REQUEST['action2'] == "push_all")) {
                // push all
                wp_redirect(admin_url('admin.php?page=a2w_import'));
            } else if (((isset($_REQUEST['action']) && $_REQUEST['action'] == "delete") || (isset($_REQUEST['action2']) && $_REQUEST['action2'] == "delete")) && isset($_REQUEST['gi']) && is_array($_REQUEST['gi']) && $_REQUEST['gi']) {
                $this->product_import_model->del_product($_REQUEST['gi']);

                wp_redirect(admin_url('admin.php?page=a2w_import'));
            }
        }

        public function render($params = array()) {
            $serach_query = !empty($_REQUEST['s'])?$_REQUEST['s']:'';
            $sort_query = !empty($_REQUEST['o'])?$_REQUEST['o']:$this->product_import_model->default_sort();
                
            $default_shipping_to_country = a2w_get_setting('aliship_shipto', 'US');
            
            $products_cnt = $this->product_import_model->get_products_count();
            $paginator = A2W_Paginator::build($products_cnt);
            
            if (a2w_check_defined('A2W_SKIP_IMPORT_SORTING')) {
                $product_list = $this->product_import_model->get_product_list(true, $serach_query, $sort_query, $paginator['per_page'], ($paginator['cur_page'] - 1) * $paginator['per_page']);
            } else {
                $product_list_all = $this->product_import_model->get_product_list(true, $serach_query, $sort_query);
                $product_list = array_slice($product_list_all, $paginator['per_page'] * ($paginator['cur_page'] - 1), $paginator['per_page']);
                unset($product_list_all);
            }
            foreach ($product_list as &$product) {
                if(empty($product['shipping_to_country'])){
                    $product['shipping_to_country'] = $default_shipping_to_country;
                    $product['shipping_to_country_name'] = $default_shipping_to_country;
                }
                if($country = $this->country_model->get_country($product['shipping_to_country'])){
                    $product['shipping_to_country_name'] = $country['n'];
                }
                
                $tmp_all_images = A2W_Utils::get_all_images_from_product($product);

                if (empty($product['description'])) {
                    $product['description'] = '';
                }

                $product['gallery_images'] = array();
                $product['variant_images'] = array();
                $product['description_images'] = array();

                foreach ($tmp_all_images as $img_id => $img) {
                    if ($img['type'] === 'gallery') {
                        $product['gallery_images'][$img_id] = $img['image'];
                    } else if ($img['type'] === 'variant') {
                        $product['variant_images'][$img_id] = $img['image'];
                    } else if ($img['type'] === 'description') {
                        $product['description_images'][$img_id] = $img['image'];
                    }
                }
                foreach ($product['tmp_copy_images'] as $img_id => $source) {
                    if(isset($tmp_all_images[$img_id])){
                        $product['gallery_images'][$img_id] = $tmp_all_images[$img_id]['image'];
                    }
                }
                
                foreach ($product['tmp_move_images'] as $img_id => $source) {
                    if(isset($tmp_all_images[$img_id])){
                        $product['gallery_images'][$img_id] = $tmp_all_images[$img_id]['image'];
                    }
                }

                if (!isset($product['thumb_id']) && $product['gallery_images']) {
                    $k = array_keys($product['gallery_images']);
                    $product['thumb_id'] = $k[0];
                }

                if (empty($product['sku_products'])) {
                    $product['sku_products'] = array('variations' => array(), 'attributes' => array());
                }
            }
            
            $this->model_put("paginator", $paginator);
            $this->model_put("serach_query", $serach_query);
            $this->model_put("sort_query", $sort_query);
            $this->model_put("sort_list", $this->product_import_model->sort_list());
            $this->model_put("product_list", $product_list);
            $this->model_put("localizator", A2W_AliexpressLocalizator::getInstance());
            $this->model_put("categories", $this->woocommerce_model->get_categories());
            $this->model_put('countries', $this->country_model->get_countries());
            $this->model_put('override_model', $this->override_model);
            
            $this->include_view("import.php");
        }

        function tiny_mce_before_init($initArray) {
            if ($this->is_current_page()) {
                $initArray['setup'] = 'function(ed) {ed.on("change", function(e) { a2w_update_product(e.target.id, { description:encodeURIComponent(e.target.getContent())}); });}';
            }
            return $initArray;
        }

        public function woocommerce_after_add_product($result, $product_id, $product, $params){
            // remove product from process list and from import list
            $this->product_import_model->del_product($product['import_id'], true);
            $this->product_import_model->del_product($product['import_id']);
            return $result;
        }

        public function ajax_push_product() {
            a2w_init_error_handler();

            $result = A2W_ResultBuilder::buildOk();


            $background_import = a2w_get_setting('background_import', true);

            if($background_import){
                // NEW import method (in background)
                if (isset($_POST['id']) && $_POST['id']) {
                    $product = $this->product_import_model->get_product($_POST['id']);
                    if ($product) {
                        try {
                            $ts = microtime(true);

                            $steps = $this->woocommerce_model->build_steps($product);
                            
                            // process first step
                            $result = $this->woocommerce_model->add_product($product, array('step'=>'init'));
                            unset($steps[array_search('init', $steps)]);    
                            
                            if ($result['state'] !== 'error') {
                                // write firt step log
                                a2w_info_log("IMPORT[time: ".(microtime(true)-$ts).", id:".$result['product_id'].", extId: ".$_POST['id'].", step: init]");

                                // move product to pricessing list
                                $this->product_import_model->move_to_processing($_POST['id']);

                                // process all other steps
                                foreach($steps as $step){
                                    $this->import_process->push_to_queue(array('id'=>$_POST['id'], 'step'=>$step, 'product_id'=>$result['product_id']));
                                }
                                $this->import_process->save()->dispatch();
                            }
                        } catch (Throwable $e) {
                            a2w_print_throwable($e);
                            $result = A2W_ResultBuilder::buildError($e->getMessage());
                        } catch (Exception $e) {
                            a2w_print_throwable($e);
                            $result = A2W_ResultBuilder::buildError($e->getMessage());
                        }
                    } else {
                        $result = A2W_ResultBuilder::buildError("Product " . $_POST['id'] . " not find.");
                    }
                } else {
                    $result = A2W_ResultBuilder::buildError("import_product: waiting for ID...");
                }
            }else{
                // Old import method 
                ini_set("memory_limit",-1);
                set_time_limit(0);
                ignore_user_abort(true);

                if(!a2w_check_defined('A2W_DO_NOT_USE_TRANSACTION')){
                    global $wpdb;

                    wp_defer_term_counting(true);
                    wp_defer_comment_counting(true );
                    $wpdb->query('SET autocommit = 0;');

                    register_shutdown_function(function(){
                        global $wpdb;
                        $wpdb->query('COMMIT;');
                        wp_defer_term_counting(false);
                        wp_defer_comment_counting(false);
                    });
                }

                try {
                    if (isset($_POST['id']) && $_POST['id']) {
                        $product = $this->product_import_model->get_product($_POST['id']);

                        if ($product) {
                            $import_wc_product_id = $this->woocommerce_model->get_product_id_by_import_id($product['import_id']);
                            if(!a2w_check_defined('A2W_ALLOW_PRODUCT_DUPLICATION') && $import_wc_product_id){
                                $result = $this->woocommerce_model->upd_product($import_wc_product_id, $product);
                            }else{
                                $result = $this->woocommerce_model->add_product($product);
                            }
                            
                            if ($result['state'] !== 'error') {
                                $this->product_import_model->del_product($_POST['id']);
                                $result = A2W_ResultBuilder::buildOk();
                            } else {
                                $result = A2W_ResultBuilder::buildError($result['message']);
                            }
                        } else {
                            $result = A2W_ResultBuilder::buildError("Product " . $_POST['id'] . " not find.");
                        }
                    } else {
                        $result = A2W_ResultBuilder::buildError("import_product: waiting for ID...");
                    }

                    restore_error_handler();
                } catch (Throwable $e) {
                    a2w_print_throwable($e);
                    $result = A2W_ResultBuilder::buildError($e->getMessage());
                } catch (Exception $e) {
                    a2w_print_throwable($e);
                    $result = A2W_ResultBuilder::buildError($e->getMessage());
                }
            }

            echo json_encode($result);
            wp_die();
        }

        public function ajax_delete_import_products() {
            a2w_init_error_handler();
            try {
                if (isset($_POST['ids']) && $_POST['ids']) {
                    $this->product_import_model->del_product($_POST['ids']);
                }
                $result = A2W_ResultBuilder::buildOk();
                restore_error_handler();
            } catch (Throwable $e) {
                a2w_print_throwable($e);
                $result = A2W_ResultBuilder::buildError($e->getMessage());
            } catch (Exception $e) {
                a2w_print_throwable($e);
                $result = A2W_ResultBuilder::buildError($e->getMessage());
            }
            echo json_encode($result);
            wp_die();
        }

        public function ajax_update_product_info() {
            a2w_init_error_handler();
            try {
                $out_data = array();
                if (isset($_POST['id']) && $_POST['id'] && ($product = $this->product_import_model->get_product($_POST['id']))) {
                    if (isset($_POST['title']) && $_POST['title']) {
                        if(!isset($product['original_title'])) {
                            $product['original_title'] = $product['title'];
                        }
                        $product['title'] = stripslashes($_POST['title']);
                    }

                    if (!empty($_POST['sku'])) {
                        $product['sku'] = stripslashes($_POST['sku']);
                    }

                    if (isset($_POST['type']) && $_POST['type'] && in_array($_POST['type'], array('simple', 'external'))) {
                        $product['product_type'] = $_POST['type'];
                    }
                    
                    if (isset($_POST['status']) && $_POST['status'] && in_array($_POST['status'], array('publish', 'draft'))) {
                        $product['product_status'] = $_POST['status'];
                    }

                    if (isset($_POST['tags']) && $_POST['tags']) {
                        $product['tags'] = $_POST['tags']?array_map('sanitize_text_field', $_POST['tags']):array();
                    }
                    
                    if (!empty($_POST['attr_names'])) {
                        foreach($_POST['attr_names'] as $attr){
                            foreach($product['sku_products']['attributes'] as &$product_attr){
                                if($product_attr['id'] == $attr['id']){
                                    if(!isset($product_attr['original_name'])){
                                        $product_attr['original_name'] = $product_attr['name'];
                                    }
                                    $product_attr['name'] = $attr['value'];
                                    break;
                                }
                            }
                        }
                    }

                    if (isset($_POST['categories'])) {
                        $product['categories'] = array();
                        if($_POST['categories']){
                            foreach ($_POST['categories'] as $cat_id) {
                                if (intval($cat_id)) {
                                    $product['categories'][] = intval($cat_id);
                                }
                            }
                        }
                        
                    }

                    if (isset($_POST['description'])) {
                        $product['description'] = stripslashes(trim(urldecode($_POST['description'])));
                    }

                    if (isset($_POST['skip_vars']) && $_POST['skip_vars']) {
                        $product['skip_vars'] = $_POST['skip_vars'];
                    }

                    if (isset($_POST['reset_skip_vars']) && $_POST['reset_skip_vars']) {
                        $product['skip_vars'] = array();
                    }

                    if (isset($_POST['skip_images']) && $_POST['skip_images']) {
                        $product['skip_images'] = $_POST['skip_images'];
                    }

                    if (!empty($_POST['no_skip'])) {
                        $product['skip_images'] = array();
                    }

                    if (isset($_POST['thumb'])) {
                        $product['thumb_id'] = $_POST['thumb'];
                    }

                    if (isset($_POST['specs'])) {
                        $product['attribute'] = array();
                        $split_attribute_values = a2w_get_setting('split_attribute_values');
                        $attribute_values_separator = a2w_get_setting('attribute_values_separator');
                        foreach($_POST['specs'] as $attr){
                            $name = trim($attr['name']);
                            if(!empty($name)){
                                $el = array('name'=>$name);
                                if ($split_attribute_values) {
                                    $el['value'] = array_map('trim', explode($attribute_values_separator, $attr['value']));
                                } else {
                                    $el['value'] = array($attr['value']);
                                }
                                $product['attribute'][] = $el;
                            }
                        }
                    }else if (!empty($_POST['cleanSpecs'])){
                        $product['attribute'] = array();
                    }

                    if (isset($_POST['disable_var_price_change'])) {
                        if (intval($_POST['disable_var_price_change'])) {
                            $product['disable_var_price_change'] = true;
                        } else {
                            $product['disable_var_price_change'] = false;
                        }
                    }
                    
                    if (isset($_POST['disable_var_quantity_change'])) {
                        if (intval($_POST['disable_var_quantity_change'])) {
                            $product['disable_var_quantity_change'] = true;
                        } else {
                            $product['disable_var_quantity_change'] = false;
                        }
                    }

                    if (!empty($_POST['variations'])) {
                        $out_data['new_attr_mapping'] = array();
                        foreach ($_POST['variations'] as $variation) {
                            foreach ($product['sku_products']['variations'] as &$v) {
                                if ($v['id'] == $variation['variation_id']) {
                                    if (isset($variation['regular_price'])) {
                                        $v['calc_regular_price'] = floatval($variation['regular_price']);
                                    }
                                    if (isset($variation['price'])) {
                                        $v['calc_price'] = floatval($variation['price']);
                                    }
                                    if (isset($variation['quantity'])) {
                                        $v['quantity'] = intval($variation['quantity']);
                                    }

                                    if (isset($variation['sku']) && $variation['sku']) {
                                        $v['sku'] = sanitize_text_field($variation['sku']);
                                    }

                                    if (isset($variation['attributes']) && is_array($variation['attributes'])) {
                                        foreach ($variation['attributes'] as $a) {
                                            foreach ($v['attributes'] as $i => $av) {
                                                $_attr_val = false;
                                                foreach ($product['sku_products']['attributes'] as $tmp_attr) {
                                                    if(isset($tmp_attr["value"][$av])){
                                                        $_attr_val = $tmp_attr["value"][$av];
                                                        break;
                                                    }
                                                }
                                                $old_name = sanitize_text_field($_attr_val['name']);
                                                $new_name = sanitize_text_field($a['value']);
                                                if ($old_name!==$new_name && $_attr_val['id'] == $a['id']) {
                                                    $_attr_id = explode(':', $av);
                                                    $attr_id = $_attr_id[0];
                                                    $new_attr_id = $attr_id . ':' . md5($variation['variation_id'].$new_name);
                                                    if ($av !== $new_attr_id) {
                                                        $out_data['new_attr_mapping'][] = array('variation_id' => $variation['variation_id'], 'old_attr_id' => $av, 'new_attr_id' => $new_attr_id);
                                                    }
                                                    foreach ($product['sku_products']['attributes'] as $ind => $orig_attr) {
                                                        if ($orig_attr['id'] == $attr_id) {
                                                            if (!isset($orig_attr['value'][$new_attr_id])) {
                                                                $product['sku_products']['attributes'][$ind]['value'][$new_attr_id] = $product['sku_products']['attributes'][$ind]['value'][$av];
                                                                if(!isset($product['sku_products']['attributes'][$ind]['value'][$new_attr_id]['original_id'])){
                                                                    $product['sku_products']['attributes'][$ind]['value'][$new_attr_id]['original_id'] = $product['sku_products']['attributes'][$ind]['value'][$new_attr_id]['id'];
                                                                }
                                                                $product['sku_products']['attributes'][$ind]['value'][$new_attr_id]['id'] = $new_attr_id;
                                                                $product['sku_products']['attributes'][$ind]['value'][$new_attr_id]['name'] = $new_name;
                                                                if (!isset($product['sku_products']['attributes'][$ind]['value'][$new_attr_id]['src_id'])) {
                                                                    $product['sku_products']['attributes'][$ind]['value'][$new_attr_id]['src_id'] = $av;
                                                                }
                                                            }
                                                            break;
                                                        }
                                                    }

                                                    $v['attributes'][$i] = $new_attr_id;
                                                    $v['attributes_names'][$i] = sanitize_text_field($a['value']);
                                                }
                                            }
                                        }
                                    }

                                    break;
                                }
                            }
                        }
                    }

                    $this->product_import_model->upd_product($product);
                    $result = A2W_ResultBuilder::buildOk($out_data);
                } else {
                    $result = A2W_ResultBuilder::buildError("update_product_info: waiting for ID...");
                }

                restore_error_handler();
            } catch (Throwable $e) {
                a2w_print_throwable($e);
                $result = A2W_ResultBuilder::buildError($e->getMessage());
            } catch (Exception $e) {
                a2w_print_throwable($e);
                $result = A2W_ResultBuilder::buildError($e->getMessage());
            }
            echo json_encode($result);
            wp_die();
        }

        public function ajax_link_to_category() {
            if (!empty($_POST['categories']) && !empty($_POST['ids'])) {
                $new_categories = is_array($_POST['categories']) ? array_map('intval', $_POST['categories']) : array(intval($_POST['categories']));
                $ids = (is_string($_POST['ids']) && $_POST['ids']==='all')?$this->product_import_model->get_product_id_list():(is_array($_POST['ids']) ? $_POST['ids'] : array($_POST['ids']));
                foreach ($ids as $id) {
                    if ($product = $this->product_import_model->get_product($id)) {
                        $product['categories'] = $new_categories;
                        $this->product_import_model->upd_product($product);
                    }
                }
                a2w_set_setting('remember_categories', $new_categories);
            } else if (empty($_POST['categories'])) {
                a2w_del_setting('remember_categories');
            }
            echo json_encode(A2W_ResultBuilder::buildOk());
            wp_die();
        }
        
        public function ajax_get_all_products_to_import() {
            echo json_encode(A2W_ResultBuilder::buildOk(array('ids' => $this->product_import_model->get_product_id_list())));
            wp_die();
        }

        public function ajax_get_product() {
            if (!empty($_POST['id'])) {
                if ($product = $this->product_import_model->get_product($_POST['id'])) {
                    $result = A2W_ResultBuilder::buildOk(array('product'=>$product));
                }else{
                    $result = A2W_ResultBuilder::buildError("product not found");
                }
            } else {
                $result = A2W_ResultBuilder::buildError("get_product: waiting for ID...");
            }
            echo json_encode($result);
            wp_die();
        }

        public function ajax_split_product(){
            if (!empty($_POST['id']) && !empty($_POST['attr'])) {
                if ($product = $this->product_import_model->get_product($_POST['id'])) {
                    $new_products = array();
                    $attr_index = 0;
                    $split_attr = array();
                    foreach($product['sku_products']['attributes'] as $k=>$a){
                        if($a['id'] == $_POST['attr']){
                            $split_attr = $a;
                            $attr_index = $k;
                        }
                    }
                    
                    foreach($product['sku_products']['attributes'][$attr_index]['value'] as $aid=>$av){
                        // if this original attr (not generated by update)
                        if(!isset($av['original_id'])){
                            $new_product = $product;
                            $new_product['disable_add_new_variants'] = true;
                            $new_product['skip_vars'] = array();
                            $new_product['skip_attr'] = array($split_attr['name']);
                            foreach($new_product['sku_products']['variations'] as $v){

                                $skip = true;
                                foreach($v['attributes'] as $vva){
                                    $var_atr_val = isset($product['sku_products']['attributes'][$attr_index]['value'][$vva])
                                        ?$product['sku_products']['attributes'][$attr_index]['value'][$vva]:false;
                                    if($var_atr_val && ($var_atr_val['id'] === $av['id'] || (isset($var_atr_val['original_id']) && $var_atr_val['original_id']=== $av['id']))){
                                        $skip = false;
                                    }
                                }

                                if($skip){
                                    $new_product['skip_vars'][] = $v['id'];
                                }else if(!empty($v['image'])){
                                    $new_product['thumb'] = $v['image'];
                                    $new_product['thumb_id'] = md5($v['image']);
                                }
                            }
                                
                            $new_products[$av['id']] = $new_product;
                        }
                        
                    }

                    $i = 0;
                    foreach($new_products as $k=>&$new_product){
                        if($i === 0){
                            $this->product_import_model->upd_product($new_product);
                        }else{
                            $new_product['import_id'] = $new_product['id']."-".md5($k.microtime(true));
                            $this->product_import_model->add_product($new_product);
                        }
                        $i++;
                    }


                    $result = A2W_ResultBuilder::buildOk();
                }else{
                    $result = A2W_ResultBuilder::buildError("product not found");
                }
                
            } else if (!empty($_POST['id']) && !empty($_POST['vars'])) {
                if ($product = $this->product_import_model->get_product($_POST['id'])) {

                    if(count($_POST['vars']) == count($product['sku_products']['variations'])){
                        $result = A2W_ResultBuilder::buildOk();
                    }else{
                        $selected_vars = $_POST['vars'];
                        $rest_vars =  $foo = array_values(array_filter(array_map(function($v){return $v['id'];}, $product['sku_products']['variations']), 
                            function($v) use ($selected_vars) {
                                return !in_array($v, $selected_vars);
                            }
                        ));

                        $product_thumb = false;
                        $new_product_thumb = false;
                        foreach($product['sku_products']['variations'] as $v){
                            if(!$product_thumb && !empty($v['image']) && in_array($v['id'], $selected_vars)){
                                $product_thumb = $v['image'];
                            }

                            if(!$new_product_thumb && !empty($v['image']) && in_array($v['id'], $rest_vars)){
                                $new_product_thumb = $v['image'];
                            }
                        }

                        $new_product = $product;


                        $product['disable_add_new_variants'] = true;
                        $product['skip_vars'] = $rest_vars;
                        if($product_thumb){
                            $product['thumb'] = $product_thumb;
                            $product['thumb_id'] = md5($product_thumb);
                        }
                    
                        $new_product = $product;
                        $new_product['import_id'] = $new_product['id']."-".md5('new_product'.microtime(true));
                        $new_product['disable_add_new_variants'] = true;
                        $new_product['skip_vars'] = $selected_vars;
                        if($new_product_thumb){
                            $new_product['thumb'] = $new_product_thumb;
                            $new_product['thumb_id'] = md5($new_product_thumb);
                        }

                        $count_attributes = function ($p){
                            $used_attribute_values = array();
                            $var_count = 0;
                            foreach($p['sku_products']['variations'] as $var){
                                if(in_array($var['id'], $p['skip_vars'])){
                                    continue;
                                }
                                $var_count ++;
                                foreach($var['attributes'] as $var_attr_id){
                                    foreach($p['sku_products']['attributes'] as $attr){
                                        if(isset($attr['value'][$var_attr_id])){
                                            if(!isset($used_attribute_values[$attr['id']])){
                                                $used_attribute_values[$attr['id']] = array('name'=>$attr['name'], 'values'=>array());
                                            }
                                            $used_attribute_values[$attr['id']]['values'][$var_attr_id] = $var_attr_id;
                                        }
                                    }
                                }
                            }

                            if($var_count > 1){
                                return array_unique(array_values(
                                    array_map(function ($a){return $a['name'];}, 
                                        array_filter($used_attribute_values, function ($a){ return count($a['values'])<2; })
                                )));
                            } else {
                                return array();
                            }
                        };

                        $new_product['skip_attr'] = $count_attributes($new_product);
                        
                        $product['skip_attr'] = $count_attributes($product);

                        $this->product_import_model->upd_product($product);
                        $this->product_import_model->add_product($new_product);

                        $result = A2W_ResultBuilder::buildOk();
                    }

                    
                }else{
                    $result = A2W_ResultBuilder::buildError("product not found");
                }
            } else {
                $result = A2W_ResultBuilder::buildError("split_product: wrong parameters...");
            }

            echo json_encode($result);
            wp_die();
        }
        
        public function ajax_import_images_action() {
            a2w_init_error_handler();
            try {
                if (isset($_POST['id']) && $_POST['id'] && ($product = $this->product_import_model->get_product($_POST['id'])) && !empty($_POST['source']) && !empty($_POST['type']) && in_array($_POST['source'], array("description", "variant")) && in_array($_POST['type'], array("copy", "move"))) {
                    if(!empty($_POST['images'])){
                        foreach($_POST['images'] as $image){
                            if($_POST['type'] == 'copy'){
                                $product['tmp_copy_images'][$image] = $_POST['source'];
                            } else if($_POST['type'] == 'move') {
                                $product['tmp_move_images'][$image] = $_POST['source'];
                            }
                        }
                        
                        $this->product_import_model->upd_product($product);    
                    }
                    
                    $result = A2W_ResultBuilder::buildOk();
                } else {
                    $result = A2W_ResultBuilder::buildError("Error in params");
                }
                
                restore_error_handler();
            } catch (Throwable $e) {
                a2w_print_throwable($e);
                $result = A2W_ResultBuilder::buildError($e->getMessage());
            } catch (Exception $e) {
                a2w_print_throwable($e);
                $result = A2W_ResultBuilder::buildError($e->getMessage());
            }
            
            echo json_encode($result);
            wp_die();
        }
        
        public function ajax_import_cancel_images_action() {
            a2w_init_error_handler();
            try {
                if (isset($_POST['id']) && $_POST['id'] && ($product = $this->product_import_model->get_product($_POST['id'])) && !empty($_POST['image']) && !empty($_POST['source']) && !empty($_POST['type']) && in_array($_POST['source'], array("description", "variant")) && in_array($_POST['type'], array("copy", "move"))) {
                    if($_POST['type'] == 'copy'){
                        unset($product['tmp_copy_images'][$_POST['image']]);
                    } else if($_POST['type'] == 'move') {
                        unset($product['tmp_move_images'][$_POST['image']]);
                    }
                    
                    $this->product_import_model->upd_product($product);    
                    
                    $result = A2W_ResultBuilder::buildOk();
                } else {
                    $result = A2W_ResultBuilder::buildError("Error in params");
                }
                
                restore_error_handler();
            } catch (Throwable $e) {
                a2w_print_throwable($e);
                $result = A2W_ResultBuilder::buildError($e->getMessage());
            } catch (Exception $e) {
                a2w_print_throwable($e);
                $result = A2W_ResultBuilder::buildError($e->getMessage());
            }
            
            echo json_encode($result);
            wp_die();
        }

        public function ajax_search_tags() {
            a2w_init_error_handler();
            try {
                $num_in_page = 50;
                $page = !empty($_REQUEST['page'])?intval($_REQUEST['page']):1;
                $search = !empty($_REQUEST['search'])?$_REQUEST['search']:'';
                $result = $this->woocommerce_model->get_product_tags($search);
                $total_count = count($result);
                $result = array_slice($result, $num_in_page*($page-1), $num_in_page);
                
                $result = array(
                    'results'=>array_map(function($o) {return array('id'=>$o, 'text'=>$o);}, $result),
                    'pagination'=>array('more'=>$num_in_page*($page-1)+$num_in_page<$total_count)
                );
                restore_error_handler();
            } catch (Throwable $e) {
                a2w_print_throwable($e);
                $result = A2W_ResultBuilder::buildError($e->getMessage());
            } catch (Exception $e) {
                a2w_print_throwable($e);
                $result = A2W_ResultBuilder::buildError($e->getMessage());
            }
            
            echo json_encode($result);
            wp_die();
        }

        public function ajax_search_products() {
            a2w_init_error_handler();
            try {
                $num_in_page = 20;
                $page = !empty($_REQUEST['page'])?intval($_REQUEST['page']):1;
                $search = !empty($_REQUEST['search'])?$_REQUEST['search']:'';

                global $wpdb;

                $products = $wpdb->get_results($wpdb->prepare("SELECT p.ID, p.post_title, pimg.guid as thumb FROM $wpdb->posts p LEFT JOIN $wpdb->postmeta pm ON (p.ID=pm.post_id AND pm.meta_key='_thumbnail_id') LEFT JOIN $wpdb->posts pimg ON (pimg.ID=pm.meta_value) WHERE p.post_type='product' AND p.post_title like '%%%s%%' LIMIT %d, %d", $search, ($page-1) * $num_in_page, $num_in_page),ARRAY_A);
                $products = $products && is_array($products)?$products:array();
                $total_count = $wpdb->get_var($wpdb->prepare("SELECT count(ID) FROM $wpdb->posts WHERE post_type='product' AND post_title like '%%%s%%'", $search));
                $result = array(
                    'results'=>array_map(function($o) {return array('id'=>$o['ID'], 'text'=>$o['post_title'], 'thumb'=>$o['thumb']);}, $products),
                    'pagination'=>array('more'=>$num_in_page*($page-1)+$num_in_page<$total_count)
                );

                restore_error_handler();
            } catch (Throwable $e) {
                a2w_print_throwable($e);
                $result = A2W_ResultBuilder::buildError($e->getMessage());
            } catch (Exception $e) {
                a2w_print_throwable($e);
                $result = A2W_ResultBuilder::buildError($e->getMessage());
            }
            
            echo json_encode($result);
            wp_die();
        }

        public function ajax_override_order_variations(){
            $product_id = $_REQUEST['product_id'];

            $result = array("state"=>"ok");

            if(!$product_id){
                $result = array("state"=>"error", "message"=>"Wrong params.");
            }

            if($result['state']!='error') {
                $result['order_variations'] = $this->override_model->find_orders($product_id);
            }

            echo json_encode($result);
            wp_die();
        }

        public function ajax_override_product() {
            $result = array('state'=>'ok');

            $product_id = $_REQUEST['product_id'];
            $external_id = $_REQUEST['external_id'];
            $override_images = !empty($_REQUEST['override_images'])?filter_var($_REQUEST['override_images'], FILTER_VALIDATE_BOOLEAN):false;
            $override_title_description =  !empty($_REQUEST['override_title_description'])?filter_var($_REQUEST['override_title_description'], FILTER_VALIDATE_BOOLEAN):false;
            $order_variations = !empty($_REQUEST['order_variations']) && is_array($_REQUEST['order_variations'])?$_REQUEST['order_variations']:array();

            if(!$product_id || !$external_id){
                $result = array("state"=>"error", "message"=>"Wrong params.");
            }

            if($result['state']!='error') {
                $result = $this->override_model->override($product_id, $external_id, $override_images, $override_title_description, $order_variations);
            }  

            if($result['state']!='error') {
                $result['button'] = __('Override', 'ali2woo');
            }

            echo json_encode($result);
            wp_die();
        }

        public function ajax_cancel_override_product() {
            $external_id = $_REQUEST['external_id'];

            if($external_id){
                $result = $this->override_model->cancel_override($external_id);
            } else {
                $result = array("state"=>"error", "message"=>"Wrong params.");
            }

            if($result['state']!='error') {
                $result['button'] = __('Push to Shop', 'ali2woo');
                $result['override_action'] = '<li><a href="#" class="product-card-override-product">'.__('Select Product to Override', 'ali2woo').'</a></li>';               
            }

            echo json_encode($result);
            wp_die();
        }        
    }

}
