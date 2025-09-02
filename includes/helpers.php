<?php
/**
 * Wright Courier Calculator - Helper Functions
 * Utility functions for sanitization, formatting, and common operations
 */

defined('ABSPATH') or die('Direct access not allowed');

/**
 * Round money values to 2 decimal places
 */
function wwc_round_money($amount) {
    return round(floatval($amount), 2);
}

/**
 * Format money amount for display
 */
function wwc_format_money($amount, $currency = null) {
    if ($currency === null) {
        $currency = get_woocommerce_currency();
    }
    
    $amount = wwc_round_money($amount);
    
    // Use WooCommerce formatting if available
    if (function_exists('wc_price')) {
        return strip_tags(wc_price($amount));
    }
    
    // Fallback formatting
    $currency_symbol = wwc_get_currency_symbol($currency);
    return $currency_symbol . number_format($amount, 2);
}

/**
 * Get currency symbol
 */
function wwc_get_currency_symbol($currency = null) {
    if ($currency === null) {
        $currency = get_woocommerce_currency();
    }
    
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'CAD' => 'C$',
        'AUD' => 'A$',
        'JPY' => '¥'
    ];
    
    return isset($symbols[$currency]) ? $symbols[$currency] : $currency . ' ';
}

/**
 * Sanitize address string
 */
function wwc_sanitize_address($address) {
    $address = sanitize_text_field($address);
    $address = trim($address);
    
    // Remove multiple spaces
    $address = preg_replace('/\s+/', ' ', $address);
    
    return $address;
}

/**
 * Sanitize place ID
 */
function wwc_sanitize_place_id($place_id) {
    // Google Place IDs are alphanumeric with some special characters
    $place_id = sanitize_text_field($place_id);
    $place_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $place_id);
    
    return $place_id;
}

/**
 * Validate tier selection
 */
function wwc_validate_tier($tier) {
    $valid_tiers = array_keys(apply_filters('wwc_rates_tiers', WWC_TIERS));
    return in_array($tier, $valid_tiers) ? $tier : 'standard';
}

/**
 * Validate addon selection
 */
function wwc_validate_addons($addons) {
    if (!is_array($addons)) {
        return [];
    }
    
    $valid_addons = array_keys(apply_filters('wwc_rates_addons', WWC_ADDONS));
    return array_intersect($addons, $valid_addons);
}

/**
 * Generate a secure random token
 */
function wwc_generate_token($length = 32) {
    if (function_exists('wp_generate_password')) {
        return wp_generate_password($length, false, false);
    }
    
    // Fallback
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $token = '';
    
    for ($i = 0; $i < $length; $i++) {
        $token .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $token;
}

/**
 * Log debug information
 */
function wwc_debug_log($message, $data = null) {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    $log_message = '[Wright Courier] ' . $message;
    
    if ($data !== null) {
        $log_message .= ' | Data: ' . print_r($data, true);
    }
    
    if (function_exists('error_log')) {
        error_log($log_message);
    }
}

/**
 * Check if request is AJAX
 */
function wwc_is_ajax_request() {
    return defined('DOING_AJAX') && DOING_AJAX;
}

/**
 * Check if user is admin
 */
function wwc_is_admin_user() {
    return current_user_can('manage_options');
}

/**
 * Get plugin option with default
 */
function wwc_get_option($option_name, $default = null) {
    return get_option($option_name, $default);
}

/**
 * Update plugin option
 */
function wwc_update_option($option_name, $value) {
    return update_option($option_name, $value);
}

/**
 * Delete plugin option
 */
function wwc_delete_option($option_name) {
    return delete_option($option_name);
}

/**
 * Validate and sanitize quote data
 */
function wwc_sanitize_quote_data($data) {
    if (!is_array($data)) {
        return false;
    }
    
    $sanitized = [];
    
    // Helper to sanitize float coordinates
    $sanitize_float = function($val) {
        return is_numeric($val) ? floatval($val) : null;
    };

    // Sanitize pickup data
    if (isset($data['pickup']) && is_array($data['pickup'])) {
        $sanitized['pickup'] = [
            'place_id' => wwc_sanitize_place_id($data['pickup']['place_id'] ?? ''),
            'label' => wwc_sanitize_address($data['pickup']['label'] ?? '')
        ];
        // Optional coordinates for fallback distance calculation
        if (isset($data['pickup']['lat'])) {
            $sanitized['pickup']['lat'] = $sanitize_float($data['pickup']['lat']);
        }
        if (isset($data['pickup']['lng'])) {
            $sanitized['pickup']['lng'] = $sanitize_float($data['pickup']['lng']);
        }
    }
    
    // Sanitize dropoff data
    if (isset($data['dropoff']) && is_array($data['dropoff'])) {
        $sanitized['dropoff'] = [
            'place_id' => wwc_sanitize_place_id($data['dropoff']['place_id'] ?? ''),
            'label' => wwc_sanitize_address($data['dropoff']['label'] ?? '')
        ];
        // Optional coordinates for fallback distance calculation
        if (isset($data['dropoff']['lat'])) {
            $sanitized['dropoff']['lat'] = $sanitize_float($data['dropoff']['lat']);
        }
        if (isset($data['dropoff']['lng'])) {
            $sanitized['dropoff']['lng'] = $sanitize_float($data['dropoff']['lng']);
        }
    }
    
    // Sanitize tier
    $sanitized['tier'] = wwc_validate_tier($data['tier'] ?? 'standard');
    
    // Sanitize addons
    $sanitized['addons'] = wwc_validate_addons($data['addons'] ?? []);
    
    // Add timestamp
    $sanitized['timestamp'] = time();
    
    return $sanitized;
}

/**
 * Format distance for display
 */
function wwc_format_distance($miles, $precision = 1) {
    return number_format($miles, $precision) . ' miles';
}

/**
 * Convert meters to miles
 */
function wwc_meters_to_miles($meters) {
    return $meters / 1609.344;
}

/**
 * Convert miles to meters
 */
function wwc_miles_to_meters($miles) {
    return $miles * 1609.344;
}

/**
 * Calculate estimated delivery time
 */
function wwc_calculate_delivery_time($tier, $miles) {
    $tiers = apply_filters('wwc_rates_tiers', WWC_TIERS);
    
    if (!isset($tiers[$tier])) {
        return null;
    }
    
    $tier_data = $tiers[$tier];
    
    if (isset($tier_data['estimated_time'])) {
        return $tier_data['estimated_time'];
    }
    
    // Fallback calculation based on tier
    switch ($tier) {
        case 'premium':
            return '1-2 hours';
        case 'express':
            return '2-3 hours';
        case 'standard':
        default:
            return '4-6 hours';
    }
}

/**
 * Check if coordinates are within service area
 */
function wwc_check_service_area($lat, $lng) {
    $center = apply_filters('wwc_service_center', WWC_SERVICE_CENTER);
    $radius = apply_filters('wwc_service_radius_miles', WWC_SERVICE_RADIUS_MILES);
    
    $distance = wwc_calculate_distance($center['lat'], $center['lng'], $lat, $lng);
    
    return $distance <= $radius;
}

/**
 * Calculate distance between two coordinates using Haversine formula
 */
function wwc_calculate_distance($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 3959; // Earth's radius in miles
    
    $lat_delta = deg2rad($lat2 - $lat1);
    $lng_delta = deg2rad($lng2 - $lng1);
    
    $a = sin($lat_delta / 2) * sin($lat_delta / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lng_delta / 2) * sin($lng_delta / 2);
         
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earth_radius * $c;
}

/**
 * Generate cache key for distance calculations
 */
function wwc_generate_cache_key($pickup_place_id, $dropoff_place_id) {
    return 'wwc_dm_' . md5($pickup_place_id . '_' . $dropoff_place_id);
}

/**
 * Get cached distance
 */
function wwc_get_cached_distance($pickup_place_id, $dropoff_place_id) {
    $cache_key = wwc_generate_cache_key($pickup_place_id, $dropoff_place_id);
    return get_transient($cache_key);
}

/**
 * Cache distance calculation
 */
function wwc_cache_distance($pickup_place_id, $dropoff_place_id, $miles, $expiration = null) {
    if ($expiration === null) {
        $expiration = 12 * HOUR_IN_SECONDS; // 12 hours default
    }
    
    $cache_key = wwc_generate_cache_key($pickup_place_id, $dropoff_place_id);
    return set_transient($cache_key, $miles, $expiration);
}

/**
 * Clear all cached distances
 */
function wwc_clear_distance_cache() {
    global $wpdb;
    
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_wwc_dm_%' 
         OR option_name LIKE '_transient_timeout_wwc_dm_%'"
    );
}

/**
 * Rate limit checker for API calls
 */
function wwc_check_rate_limit($identifier, $limit = 60, $window = 3600) {
    $cache_key = 'wwc_rate_limit_' . md5($identifier);
    $current_requests = get_transient($cache_key);
    
    if ($current_requests === false) {
        // First request in window
        set_transient($cache_key, 1, $window);
        return true;
    }
    
    if ($current_requests >= $limit) {
        return false;
    }
    
    // Increment counter
    set_transient($cache_key, $current_requests + 1, $window);
    return true;
}

/**
 * Get client IP address for rate limiting
 */
function wwc_get_client_ip() {
    $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            
            // Handle comma-separated IPs (from proxies)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            
            // Validate IP
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return '0.0.0.0'; // Fallback
}

/**
 * Validate nonce with custom action
 */
function wwc_verify_nonce($nonce, $action = 'wwc_nonce') {
    return wp_verify_nonce($nonce, $action);
}

/**
 * Create nonce with custom action
 */
function wwc_create_nonce($action = 'wwc_nonce') {
    return wp_create_nonce($action);
}

/**
 * Clean up old transients and cache entries
 */
function wwc_cleanup_cache() {
    global $wpdb;
    
    // Clean up expired transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_timeout_wwc_%' 
         AND option_value < " . time()
    );
    
    // Clean up corresponding transient data
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_wwc_%' 
         AND option_name NOT IN (
             SELECT REPLACE(option_name, '_transient_timeout_', '_transient_') 
             FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_timeout_wwc_%'
         )"
    );
}

/**
 * Schedule cache cleanup (hook into WordPress cron)
 */
function wwc_schedule_cache_cleanup() {
    if (!wp_next_scheduled('wwc_cache_cleanup')) {
        wp_schedule_event(time(), 'daily', 'wwc_cache_cleanup');
    }
}

/**
 * Unschedule cache cleanup
 */
function wwc_unschedule_cache_cleanup() {
    wp_clear_scheduled_hook('wwc_cache_cleanup');
}

// Hook the cache cleanup function
add_action('wwc_cache_cleanup', 'wwc_cleanup_cache');

/**
 * Get WooCommerce currency
 */
function wwc_get_currency() {
    if (function_exists('get_woocommerce_currency')) {
        return get_woocommerce_currency();
    }
    
    return 'USD'; // Fallback
}
