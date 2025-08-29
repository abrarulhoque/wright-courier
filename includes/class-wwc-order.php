<?php
defined('ABSPATH') or die('Direct access not allowed');

class WWC_Order {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Order line item meta
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_order_line_item_meta'], 10, 4);
        
        // Admin order display
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'display_order_courier_details']);
        add_filter('woocommerce_order_item_display_meta_key', [$this, 'customize_meta_key_display'], 10, 3);
        add_filter('woocommerce_order_item_display_meta_value', [$this, 'customize_meta_value_display'], 10, 3);
        
        // Order emails
        add_action('woocommerce_email_order_meta', [$this, 'add_courier_details_to_emails'], 10, 4);
        
        // Order status changes
        add_action('woocommerce_order_status_changed', [$this, 'handle_order_status_change'], 10, 4);
    }
    
    /**
     * Add courier service data to order line items
     */
    public function add_order_line_item_meta($item, $cart_item_key, $values, $order) {
        if (!isset($values['wwc_quote_data'])) {
            return;
        }
        
        $quote_data = $values['wwc_quote_data'];
        
        // Add all the detailed breakdown data
        $item->add_meta_data('_wwc_is_courier_service', 'yes', true);
        $item->add_meta_data('_wwc_pickup_address', $quote_data['pickup']['label'], true);
        $item->add_meta_data('_wwc_pickup_place_id', $quote_data['pickup']['place_id'], true);
        $item->add_meta_data('_wwc_dropoff_address', $quote_data['dropoff']['label'], true);
        $item->add_meta_data('_wwc_dropoff_place_id', $quote_data['dropoff']['place_id'], true);
        $item->add_meta_data('_wwc_service_tier', $quote_data['tier'], true);
        $item->add_meta_data('_wwc_distance_miles', $quote_data['miles'], true);
        $item->add_meta_data('_wwc_calculated_at', $quote_data['calculated_at'], true);
        
        // Add pricing breakdown
        if (isset($quote_data['pricing'])) {
            $pricing = $quote_data['pricing'];
            $item->add_meta_data('_wwc_base_price', $pricing['base'], true);
            $item->add_meta_data('_wwc_per_mile_rate', $pricing['per_mile'], true);
            $item->add_meta_data('_wwc_extra_miles', $pricing['extra_miles'], true);
            $item->add_meta_data('_wwc_flat_addons', $pricing['flat_addons'], true);
            $item->add_meta_data('_wwc_mult_addons', $pricing['mult_addons'], true);
            $item->add_meta_data('_wwc_fuel_surcharge', $pricing['fuel'], true);
            $item->add_meta_data('_wwc_total_price', $pricing['total'], true);
        }
        
        // Add add-ons
        if (!empty($quote_data['addons'])) {
            $item->add_meta_data('_wwc_addons', json_encode($quote_data['addons']), true);
        }
        
        // Add visible display data
        $item->add_meta_data(__('Pickup Address', 'wright-courier'), $quote_data['pickup']['label']);
        $item->add_meta_data(__('Drop-off Address', 'wright-courier'), $quote_data['dropoff']['label']);
        
        // Service tier display
        $tiers = apply_filters('wwc_rates_tiers', WWC_TIERS);
        $tier_label = isset($tiers[$quote_data['tier']]) ? $tiers[$quote_data['tier']]['label'] : ucfirst($quote_data['tier']);
        $item->add_meta_data(__('Service Tier', 'wright-courier'), $tier_label);
        
        // Distance display
        $item->add_meta_data(__('Distance', 'wright-courier'), number_format($quote_data['miles'], 1) . ' miles');
        
        // Add-ons display
        if (!empty($quote_data['addons'])) {
            $addons_config = apply_filters('wwc_rates_addons', WWC_ADDONS);
            $addon_labels = [];
            
            foreach ($quote_data['addons'] as $addon_key) {
                if (isset($addons_config[$addon_key])) {
                    $addon_labels[] = $addons_config[$addon_key]['label'];
                }
            }
            
            if (!empty($addon_labels)) {
                $item->add_meta_data(__('Add-ons', 'wright-courier'), implode(', ', $addon_labels));
            }
        }
    }
    
    /**
     * Display courier service details in admin order view
     */
    public function display_order_courier_details($order) {
        $courier_items = $this->get_courier_items($order);
        
        if (empty($courier_items)) {
            return;
        }
        
        echo '<div class="wwc-order-details">';
        echo '<h3>' . __('Courier Service Details', 'wright-courier') . '</h3>';
        
        foreach ($courier_items as $item) {
            $this->display_courier_item_details($item, $order);
        }
        
        echo '</div>';
        
        // Add some CSS for better display
        echo '<style>
        .wwc-order-details {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-left: 4px solid #007cba;
            border-radius: 4px;
        }
        .wwc-courier-item {
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .wwc-courier-breakdown {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
        }
        .wwc-courier-breakdown dl {
            margin: 0;
        }
        .wwc-courier-breakdown dt {
            font-weight: bold;
            color: #555;
        }
        .wwc-courier-breakdown dd {
            margin: 0 0 10px 0;
            color: #333;
        }
        .wwc-map-link {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 12px;
            background: #007cba;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
        }
        .wwc-map-link:hover {
            background: #005a87;
            color: white;
        }
        </style>';
    }
    
    /**
     * Display individual courier item details
     */
    private function display_courier_item_details($item, $order) {
        $pickup = $item->get_meta('_wwc_pickup_address');
        $dropoff = $item->get_meta('_wwc_dropoff_address');
        $tier = $item->get_meta('_wwc_service_tier');
        $miles = $item->get_meta('_wwc_distance_miles');
        $calculated_at = $item->get_meta('_wwc_calculated_at');
        
        // Pricing details
        $base_price = $item->get_meta('_wwc_base_price');
        $per_mile = $item->get_meta('_wwc_per_mile_rate');
        $extra_miles = $item->get_meta('_wwc_extra_miles');
        $fuel = $item->get_meta('_wwc_fuel_surcharge');
        $total = $item->get_meta('_wwc_total_price');
        $addons_json = $item->get_meta('_wwc_addons');
        
        echo '<div class="wwc-courier-item">';
        echo '<h4>' . $item->get_name() . '</h4>';
        
        // Route information
        echo '<div class="wwc-route-info">';
        echo '<p><strong>' . __('Route:', 'wright-courier') . '</strong><br>';
        echo '<strong>' . __('From:', 'wright-courier') . '</strong> ' . esc_html($pickup) . '<br>';
        echo '<strong>' . __('To:', 'wright-courier') . '</strong> ' . esc_html($dropoff) . '</p>';
        
        // Generate Google Maps link
        $maps_url = 'https://www.google.com/maps/dir/' . urlencode($pickup) . '/' . urlencode($dropoff);
        echo '<a href="' . esc_url($maps_url) . '" target="_blank" class="wwc-map-link">' . __('View Route on Google Maps', 'wright-courier') . '</a>';
        echo '</div>';
        
        // Service details and pricing breakdown
        echo '<div class="wwc-courier-breakdown">';
        
        // Service details
        echo '<div>';
        echo '<h5>' . __('Service Details', 'wright-courier') . '</h5>';
        echo '<dl>';
        echo '<dt>' . __('Service Tier:', 'wright-courier') . '</dt>';
        echo '<dd>' . esc_html(ucfirst($tier)) . '</dd>';
        echo '<dt>' . __('Distance:', 'wright-courier') . '</dt>';
        echo '<dd>' . number_format($miles, 1) . ' miles</dd>';
        
        // Add-ons
        if (!empty($addons_json)) {
            $addons = json_decode($addons_json, true);
            if (is_array($addons) && !empty($addons)) {
                $addons_config = apply_filters('wwc_rates_addons', WWC_ADDONS);
                $addon_labels = [];
                
                foreach ($addons as $addon_key) {
                    if (isset($addons_config[$addon_key])) {
                        $addon_labels[] = $addons_config[$addon_key]['label'];
                    }
                }
                
                if (!empty($addon_labels)) {
                    echo '<dt>' . __('Add-ons:', 'wright-courier') . '</dt>';
                    echo '<dd>' . implode(', ', $addon_labels) . '</dd>';
                }
            }
        }
        
        if ($calculated_at) {
            echo '<dt>' . __('Calculated:', 'wright-courier') . '</dt>';
            echo '<dd>' . date('Y-m-d H:i:s', $calculated_at) . '</dd>';
        }
        
        echo '</dl>';
        echo '</div>';
        
        // Pricing breakdown
        echo '<div>';
        echo '<h5>' . __('Price Breakdown', 'wright-courier') . '</h5>';
        echo '<dl>';
        
        if ($base_price) {
            echo '<dt>' . __('Base Price:', 'wright-courier') . '</dt>';
            echo '<dd>' . wwc_format_money($base_price) . '</dd>';
        }
        
        if ($extra_miles > 0) {
            echo '<dt>' . sprintf(__('Extra Miles (%s × %s):', 'wright-courier'), number_format($extra_miles, 1), wwc_format_money($per_mile)) . '</dt>';
            echo '<dd>' . wwc_format_money($extra_miles * $per_mile) . '</dd>';
        }
        
        if ($fuel) {
            echo '<dt>' . __('Fuel Surcharge (5%):', 'wright-courier') . '</dt>';
            echo '<dd>' . wwc_format_money($fuel) . '</dd>';
        }
        
        if ($total) {
            echo '<dt><strong>' . __('Total:', 'wright-courier') . '</strong></dt>';
            echo '<dd><strong>' . wwc_format_money($total) . '</strong></dd>';
        }
        
        echo '</dl>';
        echo '</div>';
        
        echo '</div>'; // End breakdown
        echo '</div>'; // End courier item
    }
    
    /**
     * Customize meta key display names
     */
    public function customize_meta_key_display($display_key, $meta, $item) {
        // Hide internal meta keys from customer view
        if (strpos($meta->key, '_wwc_') === 0) {
            return '';
        }
        
        return $display_key;
    }
    
    /**
     * Customize meta value display
     */
    public function customize_meta_value_display($display_value, $meta, $item) {
        // Format specific meta values
        if ($meta->key === __('Distance', 'wright-courier') && is_numeric($display_value)) {
            return number_format($display_value, 1) . ' miles';
        }
        
        return $display_value;
    }
    
    /**
     * Add courier details to order emails
     */
    public function add_courier_details_to_emails($order, $sent_to_admin, $plain_text, $email) {
        $courier_items = $this->get_courier_items($order);
        
        if (empty($courier_items)) {
            return;
        }
        
        if ($plain_text) {
            $this->add_courier_details_plain_text($courier_items);
        } else {
            $this->add_courier_details_html($courier_items);
        }
    }
    
    /**
     * Add courier details to plain text emails
     */
    private function add_courier_details_plain_text($courier_items) {
        echo "\n" . __('COURIER SERVICE DETAILS', 'wright-courier') . "\n";
        echo str_repeat('=', 50) . "\n";
        
        foreach ($courier_items as $item) {
            $pickup = $item->get_meta('_wwc_pickup_address');
            $dropoff = $item->get_meta('_wwc_dropoff_address');
            $tier = $item->get_meta('_wwc_service_tier');
            $miles = $item->get_meta('_wwc_distance_miles');
            
            echo "\n" . $item->get_name() . "\n";
            echo __('From:', 'wright-courier') . ' ' . $pickup . "\n";
            echo __('To:', 'wright-courier') . ' ' . $dropoff . "\n";
            echo __('Service:', 'wright-courier') . ' ' . ucfirst($tier) . "\n";
            echo __('Distance:', 'wright-courier') . ' ' . number_format($miles, 1) . " miles\n";
        }
        
        echo "\n";
    }
    
    /**
     * Add courier details to HTML emails
     */
    private function add_courier_details_html($courier_items) {
        echo '<h2 style="color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px;">' . __('Courier Service Details', 'wright-courier') . '</h2>';
        
        foreach ($courier_items as $item) {
            $pickup = $item->get_meta('_wwc_pickup_address');
            $dropoff = $item->get_meta('_wwc_dropoff_address');
            $tier = $item->get_meta('_wwc_service_tier');
            $miles = $item->get_meta('_wwc_distance_miles');
            
            echo '<div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #007cba;">';
            echo '<h3 style="margin: 0 0 10px 0; color: #333;">' . esc_html($item->get_name()) . '</h3>';
            echo '<p style="margin: 5px 0;"><strong>' . __('From:', 'wright-courier') . '</strong> ' . esc_html($pickup) . '</p>';
            echo '<p style="margin: 5px 0;"><strong>' . __('To:', 'wright-courier') . '</strong> ' . esc_html($dropoff) . '</p>';
            echo '<p style="margin: 5px 0;"><strong>' . __('Service:', 'wright-courier') . '</strong> ' . esc_html(ucfirst($tier)) . '</p>';
            echo '<p style="margin: 5px 0;"><strong>' . __('Distance:', 'wright-courier') . '</strong> ' . number_format($miles, 1) . ' miles</p>';
            echo '</div>';
        }
    }
    
    /**
     * Handle order status changes for courier services
     */
    public function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        $courier_items = $this->get_courier_items($order);
        
        if (empty($courier_items)) {
            return;
        }
        
        // Log status change for courier orders
        wwc_debug_log("Courier order #{$order_id} status changed from {$old_status} to {$new_status}");
        
        // Future: This is where you would integrate with Onfleet or other dispatch systems
        switch ($new_status) {
            case 'processing':
                // Order is ready for dispatch
                $this->handle_order_processing($order, $courier_items);
                break;
                
            case 'completed':
                // Courier service completed
                $this->handle_order_completed($order, $courier_items);
                break;
                
            case 'cancelled':
                // Cancel courier service
                $this->handle_order_cancelled($order, $courier_items);
                break;
        }
    }
    
    /**
     * Handle processing status
     */
    private function handle_order_processing($order, $courier_items) {
        // Add order note
        $order->add_order_note(__('Courier service is being prepared for dispatch.', 'wright-courier'));
        
        // Future: Create Onfleet tasks here
    }
    
    /**
     * Handle completed status
     */
    private function handle_order_completed($order, $courier_items) {
        // Add order note
        $order->add_order_note(__('Courier service has been completed.', 'wright-courier'));
    }
    
    /**
     * Handle cancelled status
     */
    private function handle_order_cancelled($order, $courier_items) {
        // Add order note
        $order->add_order_note(__('Courier service has been cancelled.', 'wright-courier'));
    }
    
    /**
     * Get courier service items from order
     */
    private function get_courier_items($order) {
        $courier_items = [];
        
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_wwc_is_courier_service') === 'yes') {
                $courier_items[] = $item;
            }
        }
        
        return $courier_items;
    }
    
    /**
     * Get courier service data from order
     */
    public static function get_order_courier_data($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return null;
        }
        
        $courier_data = [];
        
        foreach ($order->get_items() as $item_id => $item) {
            if ($item->get_meta('_wwc_is_courier_service') === 'yes') {
                $courier_data[] = [
                    'item_id' => $item_id,
                    'pickup' => [
                        'address' => $item->get_meta('_wwc_pickup_address'),
                        'place_id' => $item->get_meta('_wwc_pickup_place_id')
                    ],
                    'dropoff' => [
                        'address' => $item->get_meta('_wwc_dropoff_address'),
                        'place_id' => $item->get_meta('_wwc_dropoff_place_id')
                    ],
                    'tier' => $item->get_meta('_wwc_service_tier'),
                    'miles' => $item->get_meta('_wwc_distance_miles'),
                    'total_price' => $item->get_meta('_wwc_total_price'),
                    'calculated_at' => $item->get_meta('_wwc_calculated_at')
                ];
            }
        }
        
        return !empty($courier_data) ? $courier_data : null;
    }
}