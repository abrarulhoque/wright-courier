<?php
defined('ABSPATH') or die('Direct access not allowed');

class WWC_Frontend {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Add fields to product page
        add_action('woocommerce_single_product_summary', [$this, 'add_courier_fields'], 25);
        
        // Prevent direct add to cart
        add_filter('woocommerce_product_add_to_cart_url', [$this, 'modify_add_to_cart_url'], 10, 2);
        add_filter('woocommerce_product_add_to_cart_text', [$this, 'modify_add_to_cart_text'], 10, 2);
        
        // Handle AJAX for non-logged users
        add_action('wp_ajax_wwc_calculate_quote', [$this, 'handle_ajax_quote']);
        add_action('wp_ajax_nopriv_wwc_calculate_quote', [$this, 'handle_ajax_quote']);
    }
    
    public function add_courier_fields() {
        global $product;
        
        if (!$this->is_target_product($product)) {
            return;
        }
        
        // Load template
        include WWC_PLUGIN_PATH . 'templates/product-fields.php';
    }
    
    private function is_target_product($product) {
        if (!$product) {
            return false;
        }
        
        $target_id = get_option('wwc_target_product_id', 177);
        
        // Check by product ID
        if ($product->get_id() == $target_id) {
            return true;
        }
        
        // Check by product tag
        $tags = wp_get_post_terms($product->get_id(), 'product_tag', ['fields' => 'slugs']);
        if (in_array('courier-service', $tags)) {
            return true;
        }
        
        return false;
    }
    
    public function modify_add_to_cart_url($url, $product) {
        if ($this->is_target_product($product)) {
            return '#';
        }
        return $url;
    }
    
    public function modify_add_to_cart_text($text, $product) {
        if ($this->is_target_product($product)) {
            return __('Calculate & Add to Cart', 'wright-courier');
        }
        return $text;
    }
    
    public function handle_ajax_quote() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wwc_nonce')) {
            wp_die('Security check failed');
        }
        
        $pickup = sanitize_text_field($_POST['pickup'] ?? '');
        $dropoff = sanitize_text_field($_POST['dropoff'] ?? '');
        $pickup_place_id = sanitize_text_field($_POST['pickup_place_id'] ?? '');
        $dropoff_place_id = sanitize_text_field($_POST['dropoff_place_id'] ?? '');
        $tier = sanitize_text_field($_POST['tier'] ?? 'standard');
        $addons = array_map('sanitize_text_field', $_POST['addons'] ?? []);
        
        // Validate inputs
        if (empty($pickup) || empty($dropoff)) {
            wp_send_json_error([
                'code' => 'INVALID_INPUT',
                'message' => __('Please enter both pickup and drop-off addresses.', 'wright-courier')
            ]);
        }
        
        $calculator = new WWC_Calculator();
        $google = new WWC_Google();
        
        try {
            // Get distance
            $distance_data = $google->get_distance($pickup_place_id, $dropoff_place_id, $pickup, $dropoff);
            
            if (!$distance_data['success']) {
                wp_send_json_error([
                    'code' => $distance_data['error_code'],
                    'message' => $distance_data['message']
                ]);
            }
            
            $miles = $distance_data['miles'];
            
            // Check service radius
            if ($miles > WWC_SERVICE_RADIUS_MILES) {
                wp_send_json_error([
                    'code' => 'OUT_OF_RADIUS',
                    'message' => __('Service not available for this distance (over ' . WWC_SERVICE_RADIUS_MILES . ' miles).', 'wright-courier')
                ]);
            }
            
            // Calculate pricing
            $pricing = $calculator->calculate_price($miles, $tier, $addons);
            
            // Generate breakdown HTML
            $breakdown_html = $calculator->generate_breakdown_html($pricing, $miles, $tier, $addons);
            
            wp_send_json_success([
                'miles' => $miles,
                'pricing' => $pricing,
                'breakdown_html' => $breakdown_html,
                'quote_token' => wp_create_nonce('wwc_quote_' . time())
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'code' => 'CALCULATION_ERROR',
                'message' => __('Error calculating price. Please try again.', 'wright-courier')
            ]);
        }
    }
}