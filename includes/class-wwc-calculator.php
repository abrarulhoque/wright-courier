<?php
defined('ABSPATH') or die('Direct access not allowed');

class WWC_Calculator {
    
    public function calculate_price($miles, $tier, $addons = []) {
        $tiers = apply_filters('wwc_rates_tiers', WWC_TIERS);
        $addon_config = apply_filters('wwc_rates_addons', WWC_ADDONS);
        $fuel_surcharge = apply_filters('wwc_fuel_surcharge', WWC_FUEL_SURCHARGE);
        
        if (!isset($tiers[$tier])) {
            throw new Exception('Invalid tier');
        }
        
        $tier_data = $tiers[$tier];
        
        // Calculate base pricing
        $free_miles = $tier_data['first_miles_free'];
        $paid_miles = max(0, $miles - $free_miles);
        $base = $tier_data['base'];
        $per_mile = $tier_data['per_mile'];
        
        // Distance subtotal
        $distance_subtotal = $base + ($paid_miles * $per_mile);
        
        // Apply multiplier addons first (like expedite)
        $mult_addons = 1.0;
        $flat_addons = 0.0;
        
        foreach ($addons as $addon) {
            if (isset($addon_config[$addon])) {
                $addon_data = $addon_config[$addon];
                if ($addon_data['type'] === 'mult') {
                    $mult_addons *= $addon_data['value'];
                } elseif ($addon_data['type'] === 'flat') {
                    $flat_addons += $addon_data['value'];
                }
            }
        }
        
        // Apply multiplier to distance subtotal
        $distance_subtotal *= $mult_addons;
        
        // Add flat addons
        $subtotal = $distance_subtotal + $flat_addons;
        
        // Calculate fuel surcharge
        $fuel = wwc_round_money($subtotal * $fuel_surcharge);
        
        // Final total
        $total = wwc_round_money($subtotal + $fuel);
        
        return [
            'base' => wwc_round_money($base),
            'extra_miles' => wwc_round_money($paid_miles),
            'per_mile' => wwc_round_money($per_mile),
            'distance_subtotal' => wwc_round_money($base + ($paid_miles * $per_mile)),
            'flat_addons' => wwc_round_money($flat_addons),
            'mult_addons' => $mult_addons,
            'fuel' => $fuel,
            'total' => $total,
            'subtotal' => wwc_round_money($subtotal)
        ];
    }
    
    public function generate_breakdown_html($pricing, $miles, $tier, $addons = []) {
        $tiers = apply_filters('wwc_rates_tiers', WWC_TIERS);
        $addon_config = apply_filters('wwc_rates_addons', WWC_ADDONS);
        
        $tier_data = $tiers[$tier];
        $free_miles = $tier_data['first_miles_free'];
        
        $html = '<div class="wwc-price-breakdown">';
        $html .= '<h4>' . __('Price Breakdown', 'wright-courier') . '</h4>';
        $html .= '<ul class="wwc-breakdown-list">';
        
        // Distance info
        $html .= '<li><strong>' . __('Distance:', 'wright-courier') . '</strong> ' . number_format($miles, 1) . ' miles</li>';
        $html .= '<li><strong>' . __('Service Tier:', 'wright-courier') . '</strong> ' . esc_html($tier_data['label']) . '</li>';
        
        // Base price
        $html .= '<li>' . __('Base price:', 'wright-courier') . ' ' . wwc_format_money($pricing['base']) . '</li>';
        
        // Distance pricing
        if ($pricing['extra_miles'] > 0) {
            $html .= '<li>' . sprintf(
                __('Distance (%s miles × %s):', 'wright-courier'), 
                number_format($pricing['extra_miles'], 1),
                wwc_format_money($pricing['per_mile'])
            ) . ' ' . wwc_format_money($pricing['extra_miles'] * $pricing['per_mile']) . '</li>';
        } else {
            $html .= '<li>' . sprintf(__('Distance (within %d free miles)', 'wright-courier'), $free_miles) . '</li>';
        }
        
        // Addons
        foreach ($addons as $addon) {
            if (isset($addon_config[$addon])) {
                $addon_data = $addon_config[$addon];
                if ($addon_data['type'] === 'flat') {
                    $html .= '<li>' . esc_html($addon_data['label']) . ': ' . wwc_format_money($addon_data['value']) . '</li>';
                } elseif ($addon_data['type'] === 'mult') {
                    $percentage = ($addon_data['value'] - 1) * 100;
                    $html .= '<li>' . esc_html($addon_data['label']) . ' (+' . number_format($percentage) . '%)</li>';
                }
            }
        }
        
        // Fuel surcharge
        $html .= '<li>' . __('Fuel surcharge (5%):', 'wright-courier') . ' ' . wwc_format_money($pricing['fuel']) . '</li>';
        
        // Total
        $html .= '<li class="wwc-total"><strong>' . __('Total:', 'wright-courier') . ' ' . wwc_format_money($pricing['total']) . '</strong></li>';
        
        $html .= '</ul>';
        $html .= '<p class="wwc-notice">' . __('After-hours/weekend surcharge may be billed post-fulfillment per policy.', 'wright-courier') . '</p>';
        $html .= '</div>';
        
        return $html;
    }
    
    public function validate_quote_data($data) {
        $required_fields = ['pickup', 'dropoff', 'tier'];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return [
                    'valid' => false,
                    'error' => sprintf(__('Missing required field: %s', 'wright-courier'), $field)
                ];
            }
        }
        
        // Validate tier
        $tiers = apply_filters('wwc_rates_tiers', WWC_TIERS);
        if (!isset($tiers[$data['tier']])) {
            return [
                'valid' => false,
                'error' => __('Invalid service tier', 'wright-courier')
            ];
        }
        
        // Validate addons
        if (!empty($data['addons'])) {
            $addon_config = apply_filters('wwc_rates_addons', WWC_ADDONS);
            foreach ($data['addons'] as $addon) {
                if (!isset($addon_config[$addon])) {
                    return [
                        'valid' => false,
                        'error' => sprintf(__('Invalid addon: %s', 'wright-courier'), $addon)
                    ];
                }
            }
        }
        
        return ['valid' => true];
    }
}