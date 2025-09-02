<?php
defined('ABSPATH') or die('Direct access not allowed');

class WWC_Frontend {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Register shortcode
        add_shortcode('wright_courier_calculator', [$this, 'render_shortcode']);
        
        // Enqueue assets when shortcode is present
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets']);
        
        // Handle AJAX for non-logged users
        add_action('wp_ajax_wwc_calculate_quote', [$this, 'handle_ajax_quote']);
        add_action('wp_ajax_nopriv_wwc_calculate_quote', [$this, 'handle_ajax_quote']);
    }
    
    /**
     * Render the courier calculator shortcode
     */
    public function render_shortcode($atts) {
        // Parse shortcode attributes
        $atts = shortcode_atts([
            'product_id' => get_option('wwc_target_product_id', 177),
            'theme' => 'default', // For future theme variations
            'title' => __('Courier Service Calculator', 'wright-courier'),
            'container_class' => ''
        ], $atts, 'wright_courier_calculator');
        
        // Validate product exists
        $product = wc_get_product($atts['product_id']);
        if (!$product) {
            return '<div class="wwc-error-message">' . __('Invalid product ID specified for courier calculator.', 'wright-courier') . '</div>';
        }
        
        // Start output buffering
        ob_start();
        
        // Set global product for template
        global $wwc_current_product;
        $wwc_current_product = $product;
        
        // Load the calculator template
        $this->load_calculator_template($atts);
        
        // Clean up global
        $wwc_current_product = null;
        
        return ob_get_clean();
    }
    
    /**
     * Load the calculator template with proper isolation
     */
    private function load_calculator_template($atts) {
        global $wwc_current_product;
        $product = $wwc_current_product;
        
        // Load template with attributes
        include WWC_PLUGIN_PATH . 'templates/shortcode-calculator.php';
    }
    
    /**
     * Conditionally enqueue assets only when shortcode is present
     */
    public function maybe_enqueue_assets() {
        global $post;
        
        // Check if shortcode is present in content or widgets
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'wright_courier_calculator')) {
            $this->enqueue_calculator_assets();
        }
        
        // Also check widgets and other dynamic content
        if (is_active_widget(false, false, 'text') || is_customize_preview()) {
            $this->enqueue_calculator_assets();
        }
    }
    
    /**
     * Enqueue calculator assets with proper namespacing
     */
    private function enqueue_calculator_assets() {
        wp_enqueue_script(
            'wwc-calculator',
            WWC_PLUGIN_URL . 'assets/js/calculator.js',
            ['jquery'],
            WWC_PLUGIN_VERSION,
            true
        );
        
        wp_enqueue_style(
            'wwc-calculator',
            WWC_PLUGIN_URL . 'assets/css/calculator.css',
            [],
            WWC_PLUGIN_VERSION
        );
        
        // Localize script with enhanced data
        wp_localize_script('wwc-calculator', 'wwcCalculator', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'resturl' => rest_url('wright/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'testMode' => get_option('wwc_test_mode', 'yes'),
            'googleApiKey' => get_option('wwc_google_api_key', ''),
            'pluginUrl' => WWC_PLUGIN_URL,
            'checkoutUrl' => wc_get_checkout_url(),
            'wcAjaxAddToCart' => method_exists('WC_AJAX', 'get_endpoint') ? WC_AJAX::get_endpoint('add_to_cart') : add_query_arg('wc-ajax', 'add_to_cart', home_url('/')),
            'i18n' => [
                'calculating' => __('Calculating...', 'wright-courier'),
                'error' => __('Error calculating price. Please try again.', 'wright-courier'),
                'outOfRadius' => __('Service not available for this distance (over 100 miles).', 'wright-courier'),
                'invalidAddress' => __('Please enter valid pickup and drop-off addresses.', 'wright-courier'),
                'apiError' => __('Unable to calculate distance. Please try again later.', 'wright-courier'),
                'addingToCart' => __('Adding to Cart...', 'wright-courier'),
                'addToCart' => __('Add to Cart', 'wright-courier'),
                'proceedToPayment' => __('Proceed to Payment', 'wright-courier')
            ]
        ]);
        
        // Google Places API (only if not in test mode and API key exists)
        if (get_option('wwc_test_mode') !== 'yes' && !empty(get_option('wwc_google_api_key'))) {
            wp_enqueue_script(
                'google-places',
                'https://maps.googleapis.com/maps/api/js?key=' . get_option('wwc_google_api_key') . '&libraries=places&callback=wwcInitGoogleMaps',
                [],
                null,
                true
            );
        }
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
