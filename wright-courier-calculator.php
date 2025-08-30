<?php
/**
 * Plugin Name: Wright Courier Calculator
 * Description: Custom WooCommerce plugin for A→B courier services with real-time, distance-based pricing
 * Version: 1.0.7
 * Author: Wright Courier
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * Text Domain: wright-courier
 * Domain Path: /languages
 */

defined('ABSPATH') or die('Direct access not allowed');

// Plugin constants
define('WWC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WWC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WWC_PLUGIN_VERSION', '1.0.7');

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>Wright Courier Calculator</strong> requires WooCommerce to be installed and active.</p></div>';
    });
    return;
}

// Include required files
require_once WWC_PLUGIN_PATH . 'includes/helpers.php';
require_once WWC_PLUGIN_PATH . 'config/rates.php';
require_once WWC_PLUGIN_PATH . 'includes/class-wwc-plugin.php';

// Initialize plugin
add_action('plugins_loaded', function() {
    WWC_Plugin::get_instance();
});

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create necessary options
    if (!get_option('wwc_test_mode')) {
        add_option('wwc_test_mode', 'yes');
    }
    if (!get_option('wwc_google_api_key')) {
        add_option('wwc_google_api_key', '');
    }
    if (!get_option('wwc_target_product_id')) {
        add_option('wwc_target_product_id', 177);
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clean up transients
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wwc_dm_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wwc_dm_%'");
});