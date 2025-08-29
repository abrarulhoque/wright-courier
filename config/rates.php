<?php
/**
 * Wright Courier Calculator - Rate Configuration
 * All rates and settings are defined here and can be modified via WordPress filters
 */

defined('ABSPATH') or die('Direct access not allowed');

// Service Center (Atlanta coordinates)
if (!defined('WWC_SERVICE_CENTER')) {
    define('WWC_SERVICE_CENTER', [
        'lat' => 33.7490,
        'lng' => -84.3880
    ]);
}

// Service radius in miles
if (!defined('WWC_SERVICE_RADIUS_MILES')) {
    define('WWC_SERVICE_RADIUS_MILES', 100);
}

// Service tier configuration
if (!defined('WWC_TIERS')) {
    define('WWC_TIERS', [
        'standard' => [
            'label' => 'Standard Delivery',
            'description' => 'Regular delivery service with 4-6 hour window',
            'base' => 15.00,
            'per_mile' => 1.50,
            'first_miles_free' => 5,
            'estimated_time' => '4-6 hours'
        ],
        'express' => [
            'label' => 'Express Delivery',
            'description' => 'Faster delivery service with 2-3 hour window',
            'base' => 25.00,
            'per_mile' => 2.00,
            'first_miles_free' => 5,
            'estimated_time' => '2-3 hours'
        ],
        'premium' => [
            'label' => 'Premium Delivery',
            'description' => 'Priority delivery service with 1-2 hour window',
            'base' => 40.00,
            'per_mile' => 4.00,
            'first_miles_free' => 5,
            'estimated_time' => '1-2 hours'
        ]
    ]);
}

// Add-on services configuration
if (!defined('WWC_ADDONS')) {
    define('WWC_ADDONS', [
        'signature' => [
            'label' => 'Signature Required',
            'description' => 'Recipient signature required for delivery',
            'type' => 'flat',
            'value' => 5.00,
            'category' => 'delivery'
        ],
        'photo_share' => [
            'label' => 'Photo Confirmation',
            'description' => 'Photo proof of delivery shared with sender',
            'type' => 'flat',
            'value' => 3.00,
            'category' => 'confirmation'
        ],
        'expedite' => [
            'label' => 'Expedite Service',
            'description' => 'Rush handling for urgent deliveries',
            'type' => 'mult',
            'value' => 1.25, // 25% increase
            'category' => 'speed'
        ]
    ]);
}

// Fuel surcharge percentage (applied to subtotal)
if (!defined('WWC_FUEL_SURCHARGE')) {
    define('WWC_FUEL_SURCHARGE', 0.05); // 5%
}

// Business hours configuration (for future after-hours billing)
if (!defined('WWC_BUSINESS_HOURS')) {
    define('WWC_BUSINESS_HOURS', [
        'monday' => ['start' => '08:00', 'end' => '18:00'],
        'tuesday' => ['start' => '08:00', 'end' => '18:00'],
        'wednesday' => ['start' => '08:00', 'end' => '18:00'],
        'thursday' => ['start' => '08:00', 'end' => '18:00'],
        'friday' => ['start' => '08:00', 'end' => '18:00'],
        'saturday' => ['start' => '09:00', 'end' => '15:00'],
        'sunday' => ['start' => null, 'end' => null] // Closed
    ]);
}

// After-hours surcharge configuration (for future implementation)
if (!defined('WWC_AFTER_HOURS_SURCHARGE')) {
    define('WWC_AFTER_HOURS_SURCHARGE', [
        'weekday_evening' => 15.00, // Flat fee for after 6 PM weekdays
        'weekend' => 25.00,         // Flat fee for weekends
        'holiday' => 35.00          // Flat fee for holidays
    ]);
}

// Weight/size limits (for future expansion)
if (!defined('WWC_PACKAGE_LIMITS')) {
    define('WWC_PACKAGE_LIMITS', [
        'max_weight_lbs' => 50,
        'max_dimensions' => [
            'length' => 36, // inches
            'width' => 24,
            'height' => 24
        ],
        'oversized_surcharge' => 20.00
    ]);
}

// Minimum order amounts by tier
if (!defined('WWC_MINIMUM_ORDERS')) {
    define('WWC_MINIMUM_ORDERS', [
        'standard' => 0.00,  // No minimum
        'express' => 0.00,   // No minimum
        'premium' => 0.00    // No minimum
    ]);
}

// Geographic restrictions (ZIP codes or area codes for future use)
if (!defined('WWC_SERVICE_AREAS')) {
    define('WWC_SERVICE_AREAS', [
        'primary_zips' => [
            '30301', '30302', '30303', '30304', '30305', '30306', '30307', '30308', '30309', '30310',
            '30311', '30312', '30313', '30314', '30315', '30316', '30317', '30318', '30319', '30320',
            '30321', '30322', '30324', '30325', '30326', '30327', '30328', '30329', '30330', '30331',
            '30332', '30333', '30334', '30336', '30337', '30338', '30339', '30340', '30341', '30342',
            '30343', '30344', '30345', '30346', '30347', '30348', '30349', '30350', '30353', '30354',
            '30355', '30356', '30357', '30358', '30359', '30360', '30361', '30362', '30363', '30364',
            '30366', '30368', '30369', '30370', '30371', '30374', '30375', '30377', '30378', '30380',
            '30384', '30385', '30388', '30392', '30394', '30396', '30398'
        ],
        'extended_zips' => [
            // Surrounding metro areas within 100-mile radius
        ]
    ]);
}

// Discount configuration (for future promotional features)
if (!defined('WWC_DISCOUNTS')) {
    define('WWC_DISCOUNTS', [
        'first_time_customer' => [
            'type' => 'percentage',
            'value' => 0.10, // 10% off
            'max_discount' => 25.00
        ],
        'bulk_monthly' => [
            'type' => 'tiered',
            'tiers' => [
                5 => 0.05,   // 5% off for 5+ deliveries/month
                10 => 0.10,  // 10% off for 10+ deliveries/month
                20 => 0.15   // 15% off for 20+ deliveries/month
            ]
        ]
    ]);
}

// Tax configuration (inherit from WooCommerce)
if (!defined('WWC_TAX_SETTINGS')) {
    define('WWC_TAX_SETTINGS', [
        'taxable' => true,
        'tax_class' => '', // Use default WooCommerce tax class
        'tax_status' => 'taxable'
    ]);
}

/**
 * Filter hooks to allow customization without editing this file
 */

// Service center location
add_filter('wwc_service_center', function($default) {
    return apply_filters('wright_courier_service_center', $default);
});

// Service radius
add_filter('wwc_service_radius_miles', function($default) {
    return apply_filters('wright_courier_service_radius', $default);
});

// Rate tiers
add_filter('wwc_rates_tiers', function($default) {
    return apply_filters('wright_courier_tiers', $default);
});

// Add-on services
add_filter('wwc_rates_addons', function($default) {
    return apply_filters('wright_courier_addons', $default);
});

// Fuel surcharge
add_filter('wwc_fuel_surcharge', function($default) {
    return apply_filters('wright_courier_fuel_surcharge', $default);
});

/**
 * Helper function to get tier by key
 */
function wwc_get_tier($tier_key) {
    $tiers = apply_filters('wwc_rates_tiers', WWC_TIERS);
    return isset($tiers[$tier_key]) ? $tiers[$tier_key] : null;
}

/**
 * Helper function to get addon by key
 */
function wwc_get_addon($addon_key) {
    $addons = apply_filters('wwc_rates_addons', WWC_ADDONS);
    return isset($addons[$addon_key]) ? $addons[$addon_key] : null;
}

/**
 * Helper function to check if service is available in area
 */
function wwc_is_service_available($lat, $lng) {
    $center = apply_filters('wwc_service_center', WWC_SERVICE_CENTER);
    $radius = apply_filters('wwc_service_radius_miles', WWC_SERVICE_RADIUS_MILES);
    
    // Calculate distance using Haversine formula
    $earth_radius = 3959; // Earth radius in miles
    
    $lat_delta = deg2rad($lat - $center['lat']);
    $lng_delta = deg2rad($lng - $center['lng']);
    
    $a = sin($lat_delta / 2) * sin($lat_delta / 2) +
         cos(deg2rad($center['lat'])) * cos(deg2rad($lat)) *
         sin($lng_delta / 2) * sin($lng_delta / 2);
         
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $distance = $earth_radius * $c;
    
    return $distance <= $radius;
}

/**
 * Helper function to get business hours for a specific day
 */
function wwc_get_business_hours($day = null) {
    $hours = apply_filters('wwc_business_hours', WWC_BUSINESS_HOURS);
    
    if ($day === null) {
        $day = strtolower(date('l'));
    }
    
    return isset($hours[$day]) ? $hours[$day] : null;
}

/**
 * Helper function to check if current time is after hours
 */
function wwc_is_after_hours($timestamp = null) {
    if ($timestamp === null) {
        $timestamp = current_time('timestamp');
    }
    
    $day = strtolower(date('l', $timestamp));
    $time = date('H:i', $timestamp);
    $hours = wwc_get_business_hours($day);
    
    if (!$hours || !$hours['start'] || !$hours['end']) {
        return true; // Closed day
    }
    
    return $time < $hours['start'] || $time > $hours['end'];
}