<?php

/**
 * Description of A2W_ShippingFrontendPageController
 *
 * @author MA_GROUP
 * @autoload: true
 *
 */
if (!class_exists('A2W_ShippingFrontendPageController')):

    class A2W_ShippingFrontendPageController extends A2W_AbstractController {

        private $woocommerce_model;
        private $shipping_loader;

        function __construct() {
            parent::__construct(A2W()->plugin_path() . '/view/');
            if (a2w_get_setting('aliship_frontend') && A2W_Woocommerce::is_woocommerce_installed() ) {
                add_action('init', array($this, 'init'), 10, 1);
            }
        }

        public function init() {
            $this->woocommerce_model = new A2W_Woocommerce();
            $this->shipping_loader = new A2W_ShippingLoader();

            $a2w_shipping = new A2W_ShippingMethod();
            if ($a2w_shipping->use_title === "yes")
                add_filter( 'woocommerce_cart_shipping_method_full_label', array($this,'remove_shipping_label'), 10, 2 );

            add_filter('woocommerce_shipping_chosen_method', array($this, 'reset_default_shipping_method'), 10, 2);
            add_action('woocommerce_before_cart', array($this, 'render_wc_fake_shipping_calc_form'));
            add_action('woocommerce_before_cart', array($this, 'render_country_dropdown_in_cart'));

            // woocomerce 3.4+ has this hook woocommerce_after_cart_item_name
            add_action('woocommerce_after_cart_item_name', array($this, 'render_shipping_dropdown_cart'), 1, 2);
            add_filter('woocommerce_cart_item_name', array($this, 'render_shipping_dropdown_checkout'), 1, 3);

            add_action('wp_enqueue_scripts', array($this, 'assets'));

            add_filter('woocommerce_validate_postcode', array($this, 'validate_postcode'), 10, 3);

            if (WC()->version < '3.1.2')
                add_action('woocommerce_add_order_item_meta', array($this, 'add_order_item_data_legacy'), 10, 2);
            else
                add_action('woocommerce_new_order_item', array($this, 'add_order_item_data'), 10, 2);

            add_filter('woocommerce_display_item_meta', array($this, 'display_item_meta'), 10, 3);
        }

        public function validate_postcode ($valid, $postcode, $country){
            return isset($_POST['a2w_skip_postcode_validate'])?true:$valid;
        }

        public function remove_shipping_label ($label, $method){

            if ($method->id == 'a2w'){
                $label = preg_replace( '/^.+:/', '', $label );
                $label = str_replace($method->label, '', $label);
            }

            return $label;
        }

        public function reset_default_shipping_method($method, $available_methods){

            if (isset($available_methods['a2w'])) $method = 'a2w';

            return $method;
        }

        public function render_wc_fake_shipping_calc_form(){
            if ( 'no' === get_option( 'woocommerce_enable_shipping_calc' ) || ! WC()->cart->needs_shipping() ){

                $this->model_put("selected_country", $this->get_default_shipping_country());

                $this->include_view('shipping/wc_fake_shipping_calc_form.php');
            }
        }

        public function render_country_dropdown_in_cart() {
            $countries = array_merge(array('' => __('Select a Country', 'ali2woo')), $this->get_countries());

            $this->model_put("countries", $countries);

            $this->model_put("default_country", $this->get_default_shipping_country());

            $this->include_view('shipping/shipping_country_dropdown.php');
        }

        public function render_shipping_dropdown_cart($cart_item = null, $cart_item_key = null) {
            if ($cart_item_key && is_cart()) {
                $ext_id = $this->woocommerce_model->get_product_external_id($cart_item['product_id']);

                if($ext_id){
                    $ship_to = $this->get_default_shipping_country();
                    $default_tariff_code = a2w_get_setting('fulfillment_prefship', 'EMS_ZX_ZX_US'); //ePacket
                    $quantity = $cart_item['quantity'];
                    $price = $cart_item['data']->get_price();
                    $ship_from_post_id = ($cart_item['data']->post_type === 'product_variation')?$cart_item['variation_id']:$cart_item['product_id'];
                    $ship_from = get_post_meta($ship_from_post_id, '_a2w_country_code', true);

                    $shipping_meta = new A2W_ShippingMeta($cart_item['product_id']);

                    $shipping_data = $this->shipping_loader->load($shipping_meta, $ext_id, $ship_from, $ship_to, $quantity, $price, $price);

                    $shipping_methods = $shipping_data['data']['ways'];
                    $normalized_shipping_methods = array();
                    $default_shipping_method = '';
                    if (!empty($shipping_methods)) {

                        $search_tariff_code = isset($cart_item['a2w_shipping_method']) ? $cart_item['a2w_shipping_method'] : $default_tariff_code;

                        $was_found = false;

                        foreach ($shipping_methods as $method) {

                            if ($method['serviceName'] == $search_tariff_code)
                                $was_found = true;

                            $normalized_shipping_methods[$method['serviceName']] = $method['company'] . ", " . $method['time'] . " " . __('days', 'ali2woo') . ", " . ($method['price'] > 0 ? $method['price'] . " " . $method['currency'] : __('free shipping', 'ali2woo'));
                        }

                        if (!$was_found)
                            $default_shipping_method = $shipping_methods[0]['serviceName'];
                        else
                            $default_shipping_method = $search_tariff_code;
                    }

                    global $woocommerce;

                    $woocommerce->cart->cart_contents[$cart_item_key]['a2w_shipping_method'] = $default_shipping_method;

                    $woocommerce->cart->set_session();

                    $this->model_put("cart_item_key", $cart_item_key);
                    $this->model_put("shipping_methods", $normalized_shipping_methods);
                    $this->model_put("default_shipping_method", $default_shipping_method);

                    ob_start();
                    $this->include_view('shipping/shipping_method_dropdown.php');
                    echo ob_get_clean();
                }
            }
        }

        public function render_shipping_dropdown_checkout($title = null, $cart_item = null, $cart_item_key = null) {
            if ($cart_item_key && is_checkout()) {

                if ($cart_item['data']->post_type === 'product') {
                    $product_id = $cart_item['product_id'];
                } else if ($cart_item['data']->post_type === 'product_variation') {
                    $product_id = $cart_item['variation_id'];
                }

                $ext_id = $this->woocommerce_model->get_product_external_id($product_id);

                if($ext_id){
                    $ship_to = $this->get_default_shipping_country();
                    $default_tariff_code = a2w_get_setting('fulfillment_prefship', 'EMS_ZX_ZX_US'); //ePacket
                    $quantity = $cart_item['quantity'];
                    $price = $cart_item['data']->get_price();
                    $ship_from_post_id = ($cart_item['data']->post_type === 'product_variation')?$cart_item['variation_id']:$cart_item['product_id'];
                    $ship_from = get_post_meta($ship_from_post_id, '_a2w_country_code', true);

                    $shipping_meta = new A2W_ShippingMeta($cart_item['product_id']);

                    $shipping_data = $this->shipping_loader->load($shipping_meta, $ext_id, $ship_from, $ship_to, $quantity, $price, $price);

                    $shipping_methods = $shipping_data['data']['ways'];
                    $normalized_shipping_methods = array();
                    $default_shipping_method = '';
                    if (!empty($shipping_methods)) {

                        $search_tariff_code = isset($cart_item['a2w_shipping_method']) ? $cart_item['a2w_shipping_method'] : $default_tariff_code;

                        $was_found = false;

                        foreach ($shipping_methods as $method) {

                            if ($method['serviceName'] == $search_tariff_code)
                                $was_found = true;

                            $normalized_shipping_methods[$method['serviceName']] = $method['company'] . ", " . $method['time'] . " " . __('days', 'ali2woo') . ", " . ($method['price'] > 0 ? $method['price'] . " " . $method['currency'] : __('free shipping', 'ali2woo'));
                        }

                        if (!$was_found)
                            $default_shipping_method = $shipping_methods[0]['serviceName'];
                        else
                            $default_shipping_method = $search_tariff_code;
                    }

                    global $woocommerce;

                    $woocommerce->cart->cart_contents[$cart_item_key]['a2w_shipping_method'] = $default_shipping_method;

                    // $woocommerce->cart->maybe_set_cart_cookies();

                    $woocommerce->cart->set_session();

                    $this->model_put("title", $title);
                    $this->model_put("cart_item_key", $cart_item_key);
                    $this->model_put("shipping_methods", $normalized_shipping_methods);
                    $this->model_put("default_shipping_method", $default_shipping_method);

                    ob_start();
                    $this->include_view('shipping/shipping_method_dropdown.php');
                    return ob_get_clean();

                }
            }

            return $title;
        }



        function assets() {
            if (/*is_singular('product') || */is_cart() || is_checkout()) {
                wp_enqueue_script('a2w-aliexpress-shipping-script', A2W()->plugin_url() . '/assets/js/shipping.js', array(), A2W()->version, true);

                wp_enqueue_style('a2w-aliexpress-shipping-product-style', A2W()->plugin_url() . '/assets/css/shipping.css', array(), A2W()->version);

                $script_data = array(
                    'lang' => array(
                        'select_shipping_method' => __('Select a Shipping method...', 'ali2woo'),
                        'shipping_country_should_be_the_same' => __('Shipping country should be the same for all items in your order. You can reset it, if clear your shopping cart.', 'ali2woo')),
                    'ajaxurl' => admin_url('admin-ajax.php'),
                );
                wp_localize_script('a2w-aliexpress-shipping-script', 'a2w_ali_ship_data', $script_data);
            }
        }

        public function add_order_item_data_legacy($item_id, $values) {

            if (isset($values['a2w_shipping_method'])) {
                $service_name = $values['a2w_shipping_method'];
                $shipping_title = A2W_ShippingPostType::get_item(false, $service_name);
                $shipping_title = is_array($shipping_title) ? $shipping_title['title'] : '';

                woocommerce_add_order_item_meta($item_id, 'Shipping', $shipping_title);
                woocommerce_add_order_item_meta($item_id, 'a2w_shipping_code', $service_name);
            }
        }

        public function add_order_item_data($item_id, $order_item) {

            if ( isset($order_item->legacy_values) ){
                $values = $order_item->legacy_values;

                if (isset($values['a2w_shipping_method'])) {
                    $service_name = $values['a2w_shipping_method'];
                    $shipping_title = A2W_ShippingPostType::get_item(false, $service_name);
                    $shipping_title = is_array($shipping_title) ? $shipping_title['title'] : '';

                    wc_add_order_item_meta($item_id, 'Shipping', $shipping_title);
                    wc_add_order_item_meta($item_id, 'a2w_shipping_code', $service_name);
                }
            }

        }

        public function display_item_meta($html, $item, $args){
            $strings = array();
            $html    = '';
            $args    = wp_parse_args(
                $args,
                array(
                    'before'       => '<ul class="wc-item-meta"><li>',
                    'after'        => '</li></ul>',
                    'separator'    => '</li><li>',
                    'echo'         => true,
                    'autop'        => false,
                    'label_before' => '<strong class="wc-item-meta-label">',
                    'label_after'  => ':</strong> ',
                )
            );

            $product           = is_callable( array( $this, 'get_product' ) ) ? $this->get_product() : false;
            $a2w_formatted_key    = rawurldecode( 'a2w_shipping_code');
            $a2w_attribute_key = str_replace( 'attribute_', '', $a2w_formatted_key );
            $a2w_display_key   = wc_attribute_label( $a2w_attribute_key, $product );

            foreach ( $item->get_formatted_meta_data() as $meta_id => $meta ) {
                if ($meta->display_key != $a2w_display_key) {
                    $value = $args['autop'] ? wp_kses_post($meta->display_value) : wp_kses_post(make_clickable(trim($meta->display_value)));
                    $strings[] = $args['label_before'] . wp_kses_post($meta->display_key) . $args['label_after'] . $value;
                }
            }

            if ( $strings ) {
                $html = $args['before'] . implode( $args['separator'], $strings ) . $args['after'];
            }

            return $html;
        }

        public static function update_shipping_and_totals_in_cart() {

            if (!isset($_POST['calc_shipping_postcode']))
                $_POST['calc_shipping_postcode'] = '';

            WC_Shortcode_Cart::calculate_shipping();
            // Also calc totals before we check items so subtotals etc are up to date
            WC()->cart->calculate_totals();

            // Also update user meta!
            $customer_id = apply_filters('woocommerce_checkout_customer_id', get_current_user_id());
            if ($customer_id && !empty($_POST['calc_shipping_country'])) {
                $customer = new WC_Customer($customer_id);
                $customer->set_shipping_country(strval($_POST['calc_shipping_country']));
                $customer->save();
            }
        }

        //Private functions:

        private function get_default_shipping_country() {

            $country = WC()->customer->get_shipping_country();
            if (is_null($country) || empty($country)) {
                $country = $_POST['calc_shipping_country'] = a2w_get_setting('aliship_shipto');
                $this->update_shipping_and_totals_in_cart();
            }
            return $country;
        }

        private function get_countries() {
            $countries = array_merge(WC()->countries->get_allowed_countries(), WC()->countries->get_shipping_countries());
            return $countries;
        }

    }

endif;

