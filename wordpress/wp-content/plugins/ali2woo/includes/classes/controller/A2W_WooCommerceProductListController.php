<?php
/* * class
 * Description of A2W_WooCommerceProductListController
 *
 * @author MA_GROUP
 * 
 * @autoload: a2w_init
 */
if (!class_exists('A2W_WooCommerceProductListController')) {

    class A2W_WooCommerceProductListController {

        private $bulk_actions = array();
        private $bulk_actions_text = array();
        private $woocommerce_model;
        private $aliexpress_model;
        private $sync_model;
        private $reviews_model;
        private $localizator;

        public function __construct() {
            if (is_admin()) {
                add_action('admin_footer-edit.php', array($this, 'scripts'));
                add_action('load-edit.php', array($this, 'bulk_actions'));
                add_filter('post_row_actions', array($this, 'row_actions'), 2, 150);
                add_action('admin_enqueue_scripts', array($this, 'assets'));
                add_action('admin_init', array($this, 'init'));

                add_action('wp_ajax_a2w_product_info', array($this, 'ajax_product_info'));
                add_action('wp_ajax_a2w_sync_products', array($this, 'ajax_sync_products'));
                add_action('wp_ajax_a2w_sync_products_reviews', array($this, 'ajax_sync_products_reviews'));
                
                add_action('wp_ajax_a2w_get_product_id', array($this, 'ajax_get_product_id'));
                
            }

            $this->woocommerce_model = new A2W_Woocommerce();
            $this->reviews_model = new A2W_Review();
            $this->aliexpress_model = new A2W_Aliexpress();
            $this->sync_model = new A2W_Synchronize();
            $this->localizator = A2W_AliexpressLocalizator::getInstance();
        }

        function init() {

            $this->bulk_actions[] = 'a2w_product_update_manual';

            $this->bulk_actions_text['a2w_product_update_manual'] = __("AliExpress Sync", 'ali2woo');

            list($this->bulk_actions, $this->bulk_actions_text) = apply_filters('a2w_wcpl_bulk_actions_init', array($this->bulk_actions, $this->bulk_actions_text));
        }

        function row_actions($actions, $post) {
            if ('product' === $post->post_type) {
                $external_id = get_post_meta($post->ID, "_a2w_external_id", true);
                if ($external_id) {
                    $actions = array_merge($actions, array('a2w_product_info' => sprintf('<a class="a2w-product-info" id="a2w-%1$d" data-external-id="%2$s" href="#">%3$s</a>', $post->ID, $external_id, 'Aliexpress Info')));
                }
            }

            return $actions;
        }

        function assets() {

            wp_enqueue_style('a2w-wc-pl-style', A2W()->plugin_url() . '/assets/css/wc_pl_style.css', array(), A2W()->version);
            
            wp_style_add_data( 'a2w-wc-pl-style', 'rtl', 'replace' );
              
            wp_enqueue_script('jquery-ui-dialog');
            wp_enqueue_script('a2w-wc-pl-script', A2W()->plugin_url() . '/assets/js/wc_pl_script.js', array(), A2W()->version);

            wp_enqueue_script('a2w-sprintf-script', A2W()->plugin_url() . '/assets/js/sprintf.js', array(), A2W()->version);

            $lang_data = array(
                'please_wait_data_loads' => _x('Please wait, data loads..', 'Status', 'ali2woo'),
                'process_update_d_of_d' => _x('Process update %d of %d.', 'Status', 'ali2woo'),
                'process_update_d_of_d_erros_d' => _x('Process update %d of %d. Errors: %d.', 'Status', 'ali2woo'),
                'complete_result_updated_d_erros_d' => _x('Complete! Result updated: %d; errors: %d.', 'Status', 'ali2woo'),
            );

            wp_localize_script('a2w-wc-pl-script', 'a2w_wc_pl_script', 
                array('lang' => $lang_data, 
                      'lang_cookies'=>A2W_AliexpressLocalizator::getInstance()->getLocaleCookies(false),
                      'locale'=>$this->localizator->getLangCode(),
                      'currency'=>$this->localizator->currency,
                      'chrome_ext_import'=>a2w_check_defined('A2W_CHROME_EXT_IMPORT'),
                      'chrome_url'=>A2W()->chrome_url,
                )
            );
        }

        function scripts() {
            global $post_type;

            if ($post_type == 'product') {

                foreach ($this->bulk_actions as $action) {
                    $text = $this->bulk_actions_text[$action];
                    ?>
                    <script type="text/javascript">
                        jQuery(document).ready(function () {
                            jQuery('<option>').val('<?php echo $action; ?>').text('<?php echo $text; ?>').appendTo("select[name='action']");
                            jQuery('<option>').val('<?php echo $action; ?>').text('<?php echo $text; ?>').appendTo("select[name='action2']");
                        });
                    </script>
                    <?php
                }
            }
        }

        function bulk_actions() {
            global $typenow;
            $post_type = $typenow;

            if ($post_type == 'product') {

                $wp_list_table = _get_list_table('WP_Posts_List_Table');
                $action = $wp_list_table->current_action();

                $allowed_actions = $this->bulk_actions;
                if (!in_array($action, $allowed_actions))
                    return;

                check_admin_referer('bulk-posts');

                // make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
                if (isset($_REQUEST['post'])) {
                    $post_ids = array_map('intval', $_REQUEST['post']);
                }

                if (empty($post_ids))
                    return;

                $sendback = remove_query_arg(array_merge($allowed_actions, array('untrashed', 'deleted', 'ids')), wp_get_referer());
                if (!$sendback)
                    $sendback = admin_url("edit.php?post_type=$post_type");

                $pagenum = $wp_list_table->get_pagenum();
                $sendback = add_query_arg('paged', $pagenum, $sendback);
                
                $sendback = apply_filters('a2w_wcpl_bulk_actions_perform', $sendback, $action, $post_ids);

                $sendback = remove_query_arg(array('action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status', 'post', 'bulk_edit', 'post_view'), $sendback);

                wp_redirect($sendback);
                exit();
            }
        }

        function ajax_product_info() {
            $result = array("state" => "ok", "data" => "");

            $post_id = isset($_POST['id']) ? $_POST['id'] : false;

            if (!$post_id) {
                $result['state'] = 'error';
                echo json_encode($result);
                wp_die();
            }

            $external_id = get_post_meta($post_id, "_a2w_external_id", true);

            $time_value = get_post_meta($post_id, '_a2w_last_update', true);
            $time_value = $time_value ? date("Y-m-d H:i:s", $time_value) : 'not updated';

            $product_url = get_post_meta($post_id, '_product_url', true);
            if(!$product_url){
                $product_url = get_post_meta($post_id, '_a2w_original_product_url', true);    
            }
            
            $content = array();

            $content[] = "Product: <a target='_blank' href='" . $product_url . "'>here</a>";

            $seller_url = get_post_meta($post_id, '_a2w_seller_url', true);
            $seller_name = get_post_meta($post_id, '_a2w_seller_name', true);

            if ($seller_url && $seller_name) {
                $content[] = "Seller: <a target='_blank' href='" . $seller_url . "'>" . $seller_name . "</a>";
            }
            

            $content[] = "External ID: <span class='a2w_value'>" . $external_id . "</span>";
            $content[] = "Last auto-update: <span class='a2w_value'>" . $time_value . "</span>";

            $content = apply_filters('a2w_ajax_product_info', $content, $post_id, $external_id);
            $result['data'] = array('content' => $content, 'id' => $post_id);

            echo json_encode($result);
            wp_die();
        }

        function ajax_sync_products() {
            a2w_init_error_handler();
            try {
                $ids = isset($_POST['ids']) ? (is_array($_POST['ids']) ? $_POST['ids'] : array($_POST['ids'])) : array();
                
                $on_price_changes = a2w_get_setting('on_price_changes');
                $on_stock_changes = a2w_get_setting('on_stock_changes');

                $products = array();
                foreach ($ids as $post_id) {
                    $product = $this->woocommerce_model->get_product_by_post_id($post_id, false);
                    if ($product) {
                        $product['disable_var_price_change'] = $product['disable_var_price_change'] || $on_price_changes !== "update";
                        $product['disable_var_quantity_change'] = $product['disable_var_quantity_change'] || $on_stock_changes !== "update";
                        $products[strval($product['id'])] = $product;
                    }
                }

                $result = array("state" => "ok", "update_state" => array('ok' => count($ids), 'error' => 0));
                if(count($products)>0){
                    $product_ids = array_map(function($p) {
                        $complex_id = $p['id'].';'.$p['import_lang'];

                        $shipping_meta = new A2W_ShippingMeta($p['post_id']);

                        $country_to = $shipping_meta->get_country_to();
                        if(!empty($country_to)) $complex_id .= ';'.$country_to;

                        $method = $shipping_meta->get_method();
                        if(!empty($method)) $complex_id .= ';'.$method;
                        
                        return $complex_id;
                    }, $products);

                    $apd_items = empty($_POST['apd_items'])?array():$_POST['apd_items'];

                    foreach($apd_items as $k=>$adpi) {
                        $apd_items[$k]['apd'] = json_decode(stripslashes($adpi['apd']));
                    }
                    $data = empty($apd_items)?array():array('data'=>array('apd_items'=>$apd_items));

                    $res = $this->aliexpress_model->sync_products($product_ids, 
                        array_merge(array('manual_update'=>1, 'pc'=>$this->sync_model->get_product_cnt()), $data)
                    );
                    if ($res['state'] === 'error') {
                        $result = $res;
                    } else {
                        foreach ($res['products'] as $product) {
                            $product = array_replace_recursive($products[strval($product['id'])], $product);
                            $product = A2W_PriceFormula::apply_formula($product);
                            $this->woocommerce_model->upd_product($product['post_id'], $product, array('manual_update'=>1));
                        }
                    }
                }
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
        
        function ajax_sync_products_reviews() {
            a2w_init_error_handler();
            try {
                $ids = isset($_POST['ids']) ? (is_array($_POST['ids']) ? $_POST['ids'] : array($_POST['ids'])) : array();
                
                $error = 0;
                foreach ($ids as $post_id) {
                    $external_id = $this->woocommerce_model->get_product_external_id($post_id);
                    if ($external_id) {
                        try {
                            $this->reviews_model->load($post_id, true);
                        } catch (Throwable $e) {
                            a2w_print_throwable($e);
                            $error++;
                        } catch (Exception $e) {
                            a2w_print_throwable($e);
                            $error++;
                        }
                    } else{
                        $error++;
                    }
                }

                $result = array("state" => "ok", "update_state" => array('ok' => count($ids), 'error' => 0));
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
        
        function ajax_get_product_id(){
            if (!empty($_POST['post_id'])) {
                $id = $this->woocommerce_model->get_product_external_id($_POST['post_id']);
                if($id){
                    $result = A2W_ResultBuilder::buildOk(array('id'=>$id));
                }else{
                    $result = A2W_ResultBuilder::buildError('uncknown ID');
                }
            }else{
                $result = A2W_ResultBuilder::buildError("get_product_id: waiting for ID...");
            }
            echo json_encode($result);
            wp_die();
        }

    }

}
