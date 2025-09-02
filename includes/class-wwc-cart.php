<?php
defined('ABSPATH') or die('Direct access not allowed');

class WWC_Cart {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Cart item data handling
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 10, 3);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'get_cart_item_from_session'], 10, 2);
        
        // Price calculation
        add_action('woocommerce_before_calculate_totals', [$this, 'calculate_cart_totals'], 10, 1);
        
        // Cart display
        add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);
        
        // Validation
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_add_to_cart'], 10, 6);
        
        // AJAX handlers
        add_action('wp_ajax_woocommerce_add_to_cart', [$this, 'ajax_add_to_cart']);
        add_action('wp_ajax_nopriv_woocommerce_add_to_cart', [$this, 'ajax_add_to_cart']);
    }
    
    /**
     * Add courier quote data to cart item
     */
    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        // Only process our target products
        if (!$this->is_courier_product($product_id)) {
            return $cart_item_data;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['wwc_nonce'] ?? '', 'wwc_quote')) {
            wc_add_notice(__('Security verification failed.', 'wright-courier'), 'error');
            return $cart_item_data;
        }
        
        // Get quote data
        $quote_data = $_POST['wwc_quote_data'] ?? '';
        if (empty($quote_data)) {
            wc_add_notice(__('Quote data is missing. Please recalculate.', 'wright-courier'), 'error');
            return $cart_item_data;
        }
        
        // Decode and validate quote data
        $quote = json_decode(stripslashes($quote_data), true);
        if (!$quote) {
            wc_add_notice(__('Invalid quote data. Please recalculate.', 'wright-courier'), 'error');
            return $cart_item_data;
        }
        
        // Sanitize quote data
        $sanitized_quote = wwc_sanitize_quote_data($quote);
        if (!$sanitized_quote) {
            wc_add_notice(__('Quote validation failed. Please recalculate.', 'wright-courier'), 'error');
            return $cart_item_data;
        }
        
        // Re-calculate price server-side to prevent tampering
        $calculator = new WWC_Calculator();
        $google = new WWC_Google();
        
        try {
            // Get fresh distance calculation
            $distance_result = $google->get_distance(
                $sanitized_quote['pickup']['place_id'],
                $sanitized_quote['dropoff']['place_id'],
                $sanitized_quote['pickup']['label'],
                $sanitized_quote['dropoff']['label'],
                $sanitized_quote['pickup']['lat'] ?? null,
                $sanitized_quote['pickup']['lng'] ?? null,
                $sanitized_quote['dropoff']['lat'] ?? null,
                $sanitized_quote['dropoff']['lng'] ?? null
            );
            
            if (!$distance_result['success']) {
                wc_add_notice(__('Unable to verify route. Please try again.', 'wright-courier'), 'error');
                return $cart_item_data;
            }
            
            $miles = $distance_result['miles'];
            
            // Check service radius
            if ($miles > WWC_SERVICE_RADIUS_MILES) {
                wc_add_notice(__('Service not available for this distance.', 'wright-courier'), 'error');
                return $cart_item_data;
            }
            
            // Calculate fresh pricing
            $pricing = $calculator->calculate_price($miles, $sanitized_quote['tier'], $sanitized_quote['addons']);
            
            // Store all data in cart item
            $cart_item_data['wwc_quote_data'] = [
                'pickup' => $sanitized_quote['pickup'],
                'dropoff' => $sanitized_quote['dropoff'],
                'tier' => $sanitized_quote['tier'],
                'addons' => $sanitized_quote['addons'],
                'miles' => $miles,
                'pricing' => $pricing,
                'calculated_at' => time(),
                'is_courier_service' => true
            ];
            
        } catch (Exception $e) {
            wwc_debug_log('Cart price calculation failed', $e->getMessage());
            wc_add_notice(__('Price calculation failed. Please try again.', 'wright-courier'), 'error');
            return $cart_item_data;
        }
        
        return $cart_item_data;
    }
    
    /**
     * Restore cart item data from session
     */
    public function get_cart_item_from_session($cart_item, $values) {
        if (isset($values['wwc_quote_data'])) {
            $cart_item['wwc_quote_data'] = $values['wwc_quote_data'];
        }
        
        return $cart_item;
    }
    
    /**
     * Calculate cart totals - set custom price
     */
    public function calculate_cart_totals($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        if (did_action('woocommerce_before_calculate_totals') >= 2) {
            return;
        }
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['wwc_quote_data']) && $cart_item['wwc_quote_data']['is_courier_service']) {
                $quote_data = $cart_item['wwc_quote_data'];
                $price = $quote_data['pricing']['total'];
                
                // Set the custom price
                $cart_item['data']->set_price($price);
            }
        }
    }
    
    /**
     * Display courier details in cart
     */
    public function display_cart_item_data($item_data, $cart_item) {
        if (!isset($cart_item['wwc_quote_data'])) {
            return $item_data;
        }
        
        $quote = $cart_item['wwc_quote_data'];
        
        // Add pickup address
        $item_data[] = [
            'key' => __('Pickup', 'wright-courier'),
            'value' => esc_html($quote['pickup']['label']),
            'display' => esc_html($quote['pickup']['label'])
        ];
        
        // Add dropoff address
        $item_data[] = [
            'key' => __('Drop-off', 'wright-courier'),
            'value' => esc_html($quote['dropoff']['label']),
            'display' => esc_html($quote['dropoff']['label'])
        ];
        
        // Add service tier
        $tiers = apply_filters('wwc_rates_tiers', WWC_TIERS);
        $tier_label = isset($tiers[$quote['tier']]) ? $tiers[$quote['tier']]['label'] : ucfirst($quote['tier']);
        
        $item_data[] = [
            'key' => __('Service Tier', 'wright-courier'),
            'value' => esc_html($tier_label),
            'display' => esc_html($tier_label)
        ];
        
        // Add distance
        $item_data[] = [
            'key' => __('Distance', 'wright-courier'),
            'value' => number_format($quote['miles'], 1) . ' miles',
            'display' => number_format($quote['miles'], 1) . ' miles'
        ];
        
        // Add add-ons if any
        if (!empty($quote['addons'])) {
            $addons_config = apply_filters('wwc_rates_addons', WWC_ADDONS);
            $addon_labels = [];
            
            foreach ($quote['addons'] as $addon_key) {
                if (isset($addons_config[$addon_key])) {
                    $addon_labels[] = $addons_config[$addon_key]['label'];
                }
            }
            
            if (!empty($addon_labels)) {
                $item_data[] = [
                    'key' => __('Add-ons', 'wright-courier'),
                    'value' => implode(', ', $addon_labels),
                    'display' => implode(', ', $addon_labels)
                ];
            }
        }
        
        return $item_data;
    }
    
    /**
     * Validate add to cart for courier products
     */
    public function validate_add_to_cart($passed, $product_id, $quantity, $variation_id = '', $variations = '', $cart_item_data = '') {
        // Only validate our courier products
        if (!$this->is_courier_product($product_id)) {
            return $passed;
        }
        
        // Check if quote data exists
        if (empty($_POST['wwc_quote_data'])) {
            wc_add_notice(__('Please calculate shipping quote before adding to cart.', 'wright-courier'), 'error');
            return false;
        }
        
        // Replace any existing courier item with the new one
        foreach (WC()->cart->get_cart() as $key => $cart_item) {
            if (isset($cart_item['wwc_quote_data']) && $cart_item['wwc_quote_data']['is_courier_service']) {
                WC()->cart->remove_cart_item($key);
            }
        }
        
        // Validate quantity (only 1 allowed)
        if ($quantity > 1) {
            wc_add_notice(__('Only one courier service booking per order is allowed.', 'wright-courier'), 'error');
            return false;
        }
        
        return $passed;
    }
    
    /**
     * Handle AJAX add to cart for courier products
     */
    public function ajax_add_to_cart() {
        // Verify this is for our product
        $product_id = absint($_POST['product_id'] ?? 0);
        
        if (!$this->is_courier_product($product_id)) {
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['wwc_nonce'] ?? '', 'wwc_quote')) {
            wp_die('Security check failed');
        }
        
        try {
            $result = WC()->cart->add_to_cart($product_id, 1);
            
            if ($result) {
                // Return checkout URL for redirect (better UX for courier services)
                wp_die(wc_get_checkout_url());
            } else {
                wp_die('Failed to add to cart');
            }
            
        } catch (Exception $e) {
            wwc_debug_log('AJAX add to cart failed', $e->getMessage());
            wp_die('Add to cart failed');
        }
    }
    
    /**
     * Check if product is a courier service product
     */
    private function is_courier_product($product_id) {
        $target_id = get_option('wwc_target_product_id', 177);
        
        // Check by product ID
        if ($product_id == $target_id) {
            return true;
        }
        
        // Check by product tag
        $tags = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'slugs']);
        if (in_array('courier-service', $tags)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get cart item courier data
     */
    public static function get_cart_item_courier_data($cart_item) {
        return isset($cart_item['wwc_quote_data']) ? $cart_item['wwc_quote_data'] : null;
    }
    
    /**
     * Calculate total courier distance in cart
     */
    public static function get_cart_total_distance() {
        $total_distance = 0;
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['wwc_quote_data']) && $cart_item['wwc_quote_data']['is_courier_service']) {
                $total_distance += $cart_item['wwc_quote_data']['miles'];
            }
        }
        
        return $total_distance;
    }
    
    /**
     * Check if cart contains courier services
     */
    public static function cart_has_courier_service() {
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['wwc_quote_data']) && $cart_item['wwc_quote_data']['is_courier_service']) {
                return true;
            }
        }
        
        return false;
    }
}
