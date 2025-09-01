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
                <strong><?php _e('Test Mode', 'wright-courier'); ?></strong>
                <span><?php _e('— Using mock data. No charges will occur.', 'wright-courier'); ?></span>
            </div>
        <?php endif; ?>
        
        <!-- Trust & Service Info Bar -->
        <div class="wwc-trust-bar">
            <div class="wwc-trust-item">
                <span>📞</span>
                <span><?php _e('Available 24/7', 'wright-courier'); ?></span>
            </div>
            <div class="wwc-trust-item">
                <span>🗺️</span>
                <a href="#" class="wwc-coverage-link"><?php _e('See coverage map', 'wright-courier'); ?></a>
            </div>
            <div class="wwc-trust-item">
                <span>🔒</span>
                <span><?php _e('Secure checkout', 'wright-courier'); ?></span>
            </div>
        </div>
        
        <div class="wwc-form-section">
            <h3><?php echo !empty($atts['title']) ? esc_html($atts['title']) : __('Book a Local Courier', 'wright-courier'); ?></h3>
            <div class="subtitle"><?php _e('Live distance-based pricing. Photo proof on every delivery.', 'wright-courier'); ?></div>
            
            <form id="wwc-quote-form" class="wwc-quote-form">
                
                <!-- Section 1: Addresses -->
                <div class="wwc-addresses">
                    <div class="wwc-field-group wwc-pickup">
                        <label for="wwc_pickup"><?php _e('Where should we collect?', 'wright-courier'); ?> <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="wwc_pickup" 
                            name="pickup" 
                            class="wwc-address-input" 
                            placeholder="<?php esc_attr_e('Start typing... \'123 Peachtree St NE\'', 'wright-courier'); ?>"
                            required
                            autocomplete="off"
                            aria-describedby="wwc_pickup_hint"
                        >
                        <div id="wwc_pickup_hint" class="wwc-address-hint"><?php _e('Start typing an address…', 'wright-courier'); ?></div>
                        <input type="hidden" id="wwc_pickup_place_id" name="pickup_place_id" value="">
                    </div>
                    
                    <!-- Swap Button -->
                    <button type="button" class="wwc-swap-button" aria-label="<?php esc_attr_e('Swap pickup and delivery addresses', 'wright-courier'); ?>" title="<?php esc_attr_e('Swap addresses', 'wright-courier'); ?>">
                        ⟷
                    </button>
                    
                    <div class="wwc-field-group wwc-dropoff">
                        <label for="wwc_dropoff"><?php _e('Where should we deliver?', 'wright-courier'); ?> <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="wwc_dropoff" 
                            name="dropoff" 
                            class="wwc-address-input" 
                            placeholder="<?php esc_attr_e('Start typing... \'456 Spring St NW\'', 'wright-courier'); ?>"
                            required
                            autocomplete="off"
                            aria-describedby="wwc_dropoff_hint"
                        >
                        <div id="wwc_dropoff_hint" class="wwc-address-hint"><?php _e('Start typing an address…', 'wright-courier'); ?></div>
                        <input type="hidden" id="wwc_dropoff_place_id" name="dropoff_place_id" value="">
                    </div>
                </div>
                
                <!-- Mini Map Preview (optional) -->
                <div class="wwc-map-preview" id="wwc-map-preview" style="display: none;">
                    <!-- Static map or route line will be inserted here -->
                </div>
                
                <!-- Section 2: Service Tier (Segmented Control) -->
                <div class="wwc-field-group wwc-service-tiers">
                    <label class="section-title"><?php _e('Service Tier', 'wright-courier'); ?> <span class="required">*</span></label>
                    <div class="wwc-tier-segmented" role="radiogroup" aria-labelledby="tier-label">
                        <?php foreach ($tiers as $tier_key => $tier_data): 
                            $tier_time = $tier_data['estimated_time'] ?? '';
                            $tier_base = wwc_format_money($tier_data['base']);
                            $tier_per_mile = wwc_format_money($tier_data['per_mile']);
                            $tier_free_miles = $tier_data['first_miles_free'] ?? 5;
                        ?>
                            <div class="wwc-tier-segment">
                                <input 
                                    type="radio" 
                                    id="wwc_tier_<?php echo esc_attr($tier_key); ?>" 
                                    name="tier" 
                                    value="<?php echo esc_attr($tier_key); ?>"
                                    <?php checked($tier_key, 'standard'); ?>
                                    aria-describedby="tier-<?php echo esc_attr($tier_key); ?>-desc"
                                >
                                <label for="wwc_tier_<?php echo esc_attr($tier_key); ?>" class="tier-button">
                                    <span class="tier-name"><?php echo esc_html($tier_data['label']); ?></span>
                                    <span class="tier-time"><?php echo esc_html($tier_time); ?></span>
                                    <span class="tier-pricing"><?php echo $tier_base; ?> base • <?php echo $tier_per_mile; ?>/mi after <?php echo $tier_free_miles; ?></span>
                                </label>
                                <div id="tier-<?php echo esc_attr($tier_key); ?>-desc" class="tier-expanded" style="display: none;">
                                    <p class="tier-explanation">
                                        <?php 
                                        switch($tier_key) {
                                            case 'standard':
                                                _e('Reliable service for regular deliveries', 'wright-courier');
                                                break;
                                            case 'express':
                                                _e('Faster service when you need it sooner', 'wright-courier');
                                                break;
                                            case 'premium':
                                                _e('Priority handling with dedicated courier', 'wright-courier');
                                                break;
                                            default:
                                                echo esc_html($tier_data['description'] ?? '');
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="wwc-pricing-info" aria-expanded="false">
                        <span><?php _e('Why these prices?', 'wright-courier'); ?></span>
                    </button>
                    <div class="wwc-pricing-breakdown" style="display: none;" role="region" aria-label="<?php esc_attr_e('Pricing breakdown', 'wright-courier'); ?>">
                        <p><?php _e('Our distance-based pricing includes fuel, insurance, and professional handling. First miles are free to keep short trips affordable.', 'wright-courier'); ?></p>
                    </div>
                </div>
                
                <!-- Section 3: Add-ons (Pill Toggles) -->
                <div class="wwc-field-group wwc-addons">
                    <label class="section-title"><?php _e('Add-ons', 'wright-courier'); ?></label>
                    <div class="wwc-addon-pills">
                        <?php foreach ($addons as $addon_key => $addon_data): 
                            $addon_price = $addon_data['type'] === 'flat' 
                                ? '+' . wwc_format_money($addon_data['value'])
                                : '+' . number_format(($addon_data['value'] - 1) * 100) . '%';
                            $addon_description = '';
                            switch($addon_key) {
                                case 'signature':
                                    $addon_description = __('Proof shared with sender', 'wright-courier');
                                    break;
                                case 'photo':
                                    $addon_description = __('Visual confirmation of delivery', 'wright-courier');
                                    break;
                                case 'expedite':
                                    $addon_description = __('Rush handling and priority routing', 'wright-courier');
                                    break;
                                default:
                                    $addon_description = $addon_data['description'] ?? '';
                            }
                        ?>
                            <div class="wwc-addon-pill">
                                <input 
                                    type="checkbox" 
                                    id="wwc_addon_<?php echo esc_attr($addon_key); ?>" 
                                    name="addons[]" 
                                    value="<?php echo esc_attr($addon_key); ?>"
                                    aria-describedby="addon-<?php echo esc_attr($addon_key); ?>-desc"
                                >
                                <label for="wwc_addon_<?php echo esc_attr($addon_key); ?>" class="pill-toggle">
                                    <span class="addon-name"><?php echo esc_html($addon_data['label']); ?></span>
                                    <span class="addon-price"><?php echo $addon_price; ?></span>
                                </label>
                                <div id="addon-<?php echo esc_attr($addon_key); ?>-desc" class="addon-tooltip" role="tooltip">
                                    <?php echo esc_html($addon_description); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Primary CTA -->
                <div class="wwc-actions">
                    <button 
                        type="submit" 
                        id="wwc-calculate-btn" 
                        class="wwc-calculate-button" 
                        disabled
                    >
                        <span class="button-text"><?php _e('Get Live Price', 'wright-courier'); ?></span>
                        <span class="button-loading" style="display: none;"><?php _e('Calculating route…', 'wright-courier'); ?></span>
                    </button>
                </div>
                
            </form>
            
            <!-- Results Section -->
            <div id="wwc-results" class="wwc-results" style="display: none;">
                <div class="wwc-price-display">
                    <div class="wwc-total-price">
                        <span class="price-label"><?php _e('Total today:', 'wright-courier'); ?></span>
                        <span class="price-amount" id="wwc-total-amount">$0.00</span>
                    </div>
                    <div class="wwc-route-info">
                        <span class="route-distance" id="wwc-route-distance">—</span>
                        <span class="route-eta" id="wwc-route-eta">—</span>
                    </div>
                </div>
                
                <!-- Collapsible Breakdown -->
                <details class="wwc-breakdown-details">
                    <summary class="wwc-breakdown-toggle">
                        <span><?php _e('View breakdown', 'wright-courier'); ?></span>
                    </summary>
                    <div class="wwc-breakdown" id="wwc-breakdown-content">
                        <!-- Breakdown will be inserted here via JavaScript -->
                    </div>
                </details>
                
                <!-- After-hours notice -->
                <div class="wwc-after-hours-notice" style="display: none;">
                    <small><?php _e('After-hours/weekend surcharge may be billed post-delivery per policy.', 'wright-courier'); ?></small>
                </div>
            </div>
            
            <!-- Error Display -->
            <div id="wwc-error" class="wwc-error" style="display: none;">
                <p class="error-message"></p>
                <button type="button" class="wwc-retry-button"><?php _e('Try Again', 'wright-courier'); ?></button>
            </div>
            
        </div>
        
        <!-- Sticky Summary Bar -->
        <div class="wwc-sticky-summary" id="wwc-sticky-summary" style="display: none;">
            <div class="summary-content">
                <div class="summary-info">
                    <span class="summary-distance" id="summary-distance">—</span>
                    <span class="summary-eta" id="summary-eta">—</span>
                </div>
                <div class="summary-total" id="summary-total">$0.00</div>
                <button type="button" id="wwc-add-to-cart" class="wwc-sticky-cta">
                    <span class="cta-text" id="cta-text"><?php _e('Add to Cart', 'wright-courier'); ?></span>
                    <span class="cta-price" id="cta-price" style="display: none;">$0.00</span>
                </button>
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
    // Also guard against duplicate address inputs rendered by some themes/builders
    document.querySelectorAll('.wwc-calculator-container').forEach(function(container) {
        const placeholder = container.querySelector('.wwc-loading-placeholder');
        const calculator = container.querySelector('.wwc-courier-calculator');

        if (placeholder && calculator) {
            placeholder.style.display = 'none';
            calculator.style.display = 'block';
        }

        // Safety: remove any accidental duplicate pickup/dropoff inputs inside this instance
        ['.wwc-pickup', '.wwc-dropoff'].forEach(function(groupSelector) {
            const inputs = container.querySelectorAll(groupSelector + ' input.wwc-address-input');
            if (inputs.length > 1) {
                // Keep the first input and remove the rest
                for (let i = 1; i < inputs.length; i++) {
                    inputs[i].parentNode && inputs[i].parentNode.removeChild(inputs[i]);
                }
            }
        });
    });
});
</script>
