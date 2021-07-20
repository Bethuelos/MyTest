<?php
/**
 * Description of A2W_ShippingMeta
 *
 * @author MA_GROUP
 * 
 */
  
if (!class_exists('A2W_ShippingMeta')):

	class A2W_ShippingMeta {
        
        private $data = array();
        private $product_id;
        
        public function __construct($product_id) {
            $this->product_id = $product_id;
            $meta_data = get_post_meta($this->product_id, '_a2w_shipping_data', true );
            $this->data = $meta_data ? $meta_data : $this->data;
        }
        
        public function get_items($quantity, $from_country, $to_country){
            $meta_key = $from_country.$to_country;
            if (isset($this->data[$meta_key][$quantity]) ){
                return $this->data[$meta_key][$quantity];
            }
            return false;
        }

        public function get_cost(){
            return isset($this->data['cost'])?$this->data['cost']:'';
        }

        public function get_country_to(){
            return isset($this->data['country_to'])?$this->data['country_to']:'';
        }

        public function get_method(){
            return isset($this->data['method'])?$this->data['method']:'';
        }
        
        // mutations
        
        public function save_items($quantity, $from_country, $to_country, $items, $force_save = true) {
            $meta_key = $from_country.$to_country;
            if (!isset($this->data[$meta_key])) $this->data[$meta_key] = array();
            $this->data[$meta_key][$quantity] = $items;
            
            if($force_save) $this->save();
        }

        public function save_cost($cost, $force_save = true) {
            $this->data['cost'] = $cost;
            if($force_save) $this->save();
        }

        public function save_country_to($country_to, $force_save = true) {
            $this->data['country_to'] = $country_to;
            if($force_save) $this->save();
        }

        public function save_method($method, $force_save = true) {
            $this->data['method'] = $method;
            if($force_save) $this->save();
        }

        public function save() {
            update_post_meta($this->product_id, '_a2w_shipping_data', $this->data);
        }
     	
        public static function clear_in_all_product(){
            global $wpdb;
            
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key='_a2w_shipping_data'");
        }
    }

endif;
