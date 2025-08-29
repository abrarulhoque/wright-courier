<?php
/**
 * Shortcode template for Wright Courier Calculator
 * This template is completely isolated and designed to work independently
 * 
 * Available variables:
 * $atts - shortcode attributes
 * $product - WooCommerce product object
 */

defined('ABSPATH') or die('Direct access not allowed');

$tiers = apply_filters('wwc_rates_tiers', WWC_TIERS);
$addons = apply_filters('wwc_rates_addons', WWC_ADDONS);
$test_mode = get_option('wwc_test_mode', 'yes') === 'yes';
$container_class = !empty($atts['container_class']) ? ' ' . esc_attr($atts['container_class']) : '';
?>

<!-- Wright Courier Calculator - Isolated Shortcode Version -->
<div class="wwc-calculator-container<?php echo $container_class; ?>" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
    
    <!-- Inline Critical CSS for Immediate Styling -->
    <style>
    /* Critical CSS to prevent FOUC and theme conflicts */
    .wwc-calculator-container * {
        box-sizing: border-box !important;
    }
    .wwc-calculator-container {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif !important;
        line-height: 1.6 !important;
        color: #1e293b !important;
        margin: 0 !important;
        padding: 0 !important;
        clear: both !important;
        isolation: isolate !important;
    }
    .wwc-calculator-container .wwc-loading-placeholder {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 200px;
        background: #f8fafc;
        border-radius: 12px;
        margin: 20px 0;
    }
    .wwc-calculator-container .wwc-loading-spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #e2e8f0;
        border-top: 4px solid #3b82f6;
        border-radius: 50%;
        animation: wwc-spin 1s linear infinite;
    }
    @keyframes wwc-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    </style>
    
    <!-- Loading Placeholder (replaced by JavaScript) -->
    <div class="wwc-loading-placeholder">
        <div style="text-align: center;">
            <div class="wwc-loading-spinner"></div>
            <p style="margin: 15px 0 0 0; color: #64748b; font-size: 14px;">
                <?php _e('Loading courier calculator...', 'wright-courier'); ?>
            </p>
        </div>
    </div>
    
    <!-- Main Calculator (hidden initially, shown by JavaScript) -->
    <div class="wwc-courier-calculator" style="display: none;">
        
        <?php if ($test_mode): ?>
            <div class="wwc-test-mode-notice">
                <strong><?php _e('Test Mode Active', 'wright-courier'); ?></strong>
                <span><?php _e('Using mock data for testing. No charges will occur.', 'wright-courier'); ?></span>
            </div>
        <?php endif; ?>
        
        <div class="wwc-form-section">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            
            <form id="wwc-quote-form" class="wwc-quote-form">
                
                <!-- Address Fields -->
                <div class="wwc-addresses">
                    <div class="wwc-field-group wwc-pickup">
                        <label for="wwc_pickup"><?php _e('Pickup Address', 'wright-courier'); ?> <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="wwc_pickup" 
                            name="pickup" 
                            class="wwc-address-input" 
                            placeholder="<?php esc_attr_e('Enter pickup address...', 'wright-courier'); ?>"
                            required
                            autocomplete="off"
                        >
                        <input type="hidden" id="wwc_pickup_place_id" name="pickup_place_id" value="">
                    </div>
                    
                    <div class="wwc-field-group wwc-dropoff">
                        <label for="wwc_dropoff"><?php _e('Drop-off Address', 'wright-courier'); ?> <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="wwc_dropoff" 
                            name="dropoff" 
                            class="wwc-address-input" 
                            placeholder="<?php esc_attr_e('Enter drop-off address...', 'wright-courier'); ?>"
                            required
                            autocomplete="off"
                        >
                        <input type="hidden" id="wwc_dropoff_place_id" name="dropoff_place_id" value="">
                    </div>
                </div>
                
                <!-- Service Tier Selection -->
                <div class="wwc-field-group wwc-service-tiers">
                    <label><?php _e('Service Tier', 'wright-courier'); ?> <span class="required">*</span></label>
                    <div class="wwc-tier-options">
                        <?php foreach ($tiers as $tier_key => $tier_data): ?>
                            <div class="wwc-tier-option">
                                <input 
                                    type="radio" 
                                    id="wwc_tier_<?php echo esc_attr($tier_key); ?>" 
                                    name="tier" 
                                    value="<?php echo esc_attr($tier_key); ?>"
                                    <?php checked($tier_key, 'standard'); ?>
                                >
                                <label for="wwc_tier_<?php echo esc_attr($tier_key); ?>">
                                    <span class="tier-name"><?php echo esc_html($tier_data['label']); ?></span>
                                    <span class="tier-details">
                                        <?php printf(
                                            __('Base: %s | Per mile: %s | First %d miles free', 'wright-courier'),
                                            wwc_format_money($tier_data['base']),
                                            wwc_format_money($tier_data['per_mile']),
                                            $tier_data['first_miles_free']
                                        ); ?>
                                        <?php if (isset($tier_data['estimated_time'])): ?>
                                            <br><small><?php printf(__('Est. time: %s', 'wright-courier'), $tier_data['estimated_time']); ?></small>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Add-ons -->
                <div class="wwc-field-group wwc-addons">
                    <label><?php _e('Add-ons', 'wright-courier'); ?></label>
                    <div class="wwc-addon-options">
                        <?php foreach ($addons as $addon_key => $addon_data): ?>
                            <div class="wwc-addon-option">
                                <input 
                                    type="checkbox" 
                                    id="wwc_addon_<?php echo esc_attr($addon_key); ?>" 
                                    name="addons[]" 
                                    value="<?php echo esc_attr($addon_key); ?>"
                                >
                                <label for="wwc_addon_<?php echo esc_attr($addon_key); ?>">
                                    <span class="addon-details">
                                        <span class="addon-name"><?php echo esc_html($addon_data['label']); ?></span>
                                        <?php if (isset($addon_data['description'])): ?>
                                            <small class="addon-description"><?php echo esc_html($addon_data['description']); ?></small>
                                        <?php endif; ?>
                                    </span>
                                    <span class="addon-price">
                                        <?php if ($addon_data['type'] === 'flat'): ?>
                                            +<?php echo wwc_format_money($addon_data['value']); ?>
                                        <?php else: ?>
                                            +<?php echo number_format(($addon_data['value'] - 1) * 100); ?>%
                                        <?php endif; ?>
                                    </span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Calculate Button -->
                <div class="wwc-actions">
                    <button 
                        type="submit" 
                        id="wwc-calculate-btn" 
                        class="wwc-calculate-button" 
                        disabled
                    >
                        <span class="button-text"><?php _e('Calculate Price', 'wright-courier'); ?></span>
                        <span class="button-loading" style="display: none;"><?php _e('Calculating...', 'wright-courier'); ?></span>
                    </button>
                </div>
                
            </form>
            
            <!-- Results Section -->
            <div id="wwc-results" class="wwc-results" style="display: none;">
                <div class="wwc-price-display">
                    <div class="wwc-total-price">
                        <span class="price-label"><?php _e('Total Price:', 'wright-courier'); ?></span>
                        <span class="price-amount" id="wwc-total-amount">$0.00</span>
                    </div>
                </div>
                
                <div class="wwc-breakdown" id="wwc-breakdown-content">
                    <!-- Breakdown will be inserted here via JavaScript -->
                </div>
                
                <!-- Add to Cart Button -->
                <div class="wwc-cart-actions">
                    <button 
                        type="button" 
                        id="wwc-add-to-cart" 
                        class="wwc-add-to-cart-button"
                    >
                        <?php _e('Add to Cart', 'wright-courier'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Error Display -->
            <div id="wwc-error" class="wwc-error" style="display: none;">
                <p class="error-message"></p>
                <button type="button" class="wwc-retry-button"><?php _e('Try Again', 'wright-courier'); ?></button>
            </div>
            
        </div>
        
        <!-- Hidden fields for cart data -->
        <div style="display: none;">
            <input type="hidden" id="wwc-quote-data" name="wwc_quote_data" value="">
            <input type="hidden" id="wwc-nonce" name="wwc_nonce" value="<?php echo wp_create_nonce('wwc_quote'); ?>">
            <input type="hidden" id="wwc-product-id" name="wwc_product_id" value="<?php echo esc_attr($product->get_id()); ?>">
        </div>
        
    </div>
    
    <!-- Fallback for when JavaScript is disabled -->
    <noscript>
        <div style="padding: 20px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #7f1d1d; margin: 20px 0;">
            <h4 style="margin: 0 0 10px 0;"><?php _e('JavaScript Required', 'wright-courier'); ?></h4>
            <p style="margin: 0;"><?php _e('The courier calculator requires JavaScript to function properly. Please enable JavaScript in your browser and refresh the page.', 'wright-courier'); ?></p>
        </div>
    </noscript>
    
</div>

<!-- Initialize calculator when assets are loaded -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show calculator and hide loading placeholder
    const container = document.querySelector('.wwc-calculator-container');
    if (container) {
        const placeholder = container.querySelector('.wwc-loading-placeholder');
        const calculator = container.querySelector('.wwc-courier-calculator');
        
        if (placeholder && calculator) {
            placeholder.style.display = 'none';
            calculator.style.display = 'block';
        }
    }
});
</script>