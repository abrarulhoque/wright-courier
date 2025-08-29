<?php
defined('ABSPATH') or die('Direct access not allowed');

$tiers = apply_filters('wwc_rates_tiers', WWC_TIERS);
$addons = apply_filters('wwc_rates_addons', WWC_ADDONS);
$test_mode = get_option('wwc_test_mode', 'yes') === 'yes';
?>

<div class="wwc-courier-calculator" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
    
    <?php if ($test_mode): ?>
        <div class="wwc-test-mode-notice">
            <strong><?php _e('Test Mode Active', 'wright-courier'); ?></strong>
            <span><?php _e('Using mock data for testing. No charges will occur.', 'wright-courier'); ?></span>
        </div>
    <?php endif; ?>
    
    <div class="wwc-form-section">
        <h3><?php _e('Courier Service Calculator', 'wright-courier'); ?></h3>
        
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
                                <span class="addon-name"><?php echo esc_html($addon_data['label']); ?></span>
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
                    class="button alt wwc-add-to-cart-button"
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
    </div>
    
</div>

<style>
/* Inline critical styles to prevent FOUC */
.wwc-courier-calculator {
    margin: 20px 0;
    padding: 0;
}

.wwc-test-mode-notice {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
    padding: 10px 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.wwc-addresses {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .wwc-addresses {
        grid-template-columns: 1fr;
        gap: 15px;
    }
}

.wwc-field-group {
    margin-bottom: 20px;
}

.wwc-field-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.required {
    color: #e74c3c;
}

.wwc-address-input {
    width: 100%;
    padding: 12px;
    border: 2px solid #ddd;
    border-radius: 6px;
    font-size: 16px;
    transition: border-color 0.3s ease;
}

.wwc-address-input:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.1);
}

.wwc-calculate-button {
    background: #007cba;
    color: white;
    border: none;
    padding: 15px 30px;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    max-width: 300px;
}

.wwc-calculate-button:hover:not(:disabled) {
    background: #005a87;
    transform: translateY(-1px);
}

.wwc-calculate-button:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
}

.wwc-results {
    margin-top: 25px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #28a745;
}

.wwc-total-price {
    text-align: center;
    margin-bottom: 20px;
}

.price-amount {
    font-size: 2em;
    font-weight: bold;
    color: #28a745;
}

.wwc-error {
    margin-top: 20px;
    padding: 15px;
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
    border-radius: 6px;
}
</style>