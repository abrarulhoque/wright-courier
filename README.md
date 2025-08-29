# Wright Courier Calculator for WooCommerce

A custom WooCommerce plugin for selling **A→B courier services** with real-time, distance-based pricing at checkout. This plugin focuses on the **Courier Delivery** service with Google Address Autocomplete, server-side price calculation, and automatic line-item pricing in cart/checkout.

## Features

- **🎯 Shortcode Integration**: Use `[wright_courier_calculator]` anywhere - pages, posts, widgets
- **🎨 Theme-Independent**: Completely isolated CSS prevents conflicts with any theme
- **💻 Real-time Distance Calculation**: Uses Google Distance Matrix API for accurate driving distances
- **🧪 Test Mode**: Complete functionality with mock data for testing without API costs
- **📱 Modern Responsive UI**: Beautiful design works on all devices with Google Places Autocomplete
- **🔒 Server-side Validation**: All pricing calculations happen server-side for security
- **🚀 Three Service Tiers**: Standard, Express, and Premium delivery options
- **⭐ Add-on Services**: Signature required, photo confirmation, and expedite options
- **⛽ Fuel Surcharge**: Automatic 5% fuel surcharge calculation
- **👨‍💼 Admin Management**: Complete order details and pricing breakdown in admin
- **⚡ Cache System**: Efficient distance caching to reduce API costs
- **📧 Email Integration**: Courier details included in order confirmation emails
- **🔧 No Theme Conflicts**: Fully isolated styling with `!important` declarations

## Installation

1. Upload the `wright-courier-calculator` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Settings → Wright Courier** to configure the plugin
4. Create or assign a product with ID `177` (or configure your target product ID)

## Configuration

### Settings Page

Navigate to **Settings → Wright Courier** in your WordPress admin:

- **Test Mode**: Enable to use mock data instead of Google API (recommended for development)
- **Google API Key**: Your Google API key with Places and Distance Matrix API enabled
- **Target Product ID**: The WooCommerce product ID to apply courier calculator to (default: 177)

### Google API Setup

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable the following APIs:
   - Places API
   - Distance Matrix API
4. Create an API key with appropriate restrictions:
   - **HTTP referrers** for Places API (frontend)
   - **IP addresses** for Distance Matrix API (server)

### Service Configuration

All rates and settings can be customized via WordPress filters in your theme's `functions.php`:

```php
// Customize service center location
add_filter('wwc_service_center', function($default) {
    return ['lat' => 33.7490, 'lng' => -84.3880]; // Atlanta
});

// Modify service radius
add_filter('wwc_service_radius_miles', function($default) {
    return 100; // 100 mile radius
});

// Customize pricing tiers
add_filter('wwc_rates_tiers', function($tiers) {
    $tiers['standard']['base'] = 20.00; // Increase base price
    return $tiers;
});

// Modify fuel surcharge
add_filter('wwc_fuel_surcharge', function($default) {
    return 0.08; // 8% instead of 5%
});
```

## Usage

### Using the Shortcode

The plugin now works via shortcode, making it completely flexible and theme-independent:

**Basic Usage:**
```
[wright_courier_calculator]
```

**With Custom Product ID:**
```
[wright_courier_calculator product_id="177"]
```

**With Custom Title:**
```
[wright_courier_calculator title="Get Your Delivery Quote" product_id="177"]
```

**With Custom Container Class:**
```
[wright_courier_calculator container_class="my-custom-wrapper"]
```

**Available Shortcode Parameters:**
- `product_id` - WooCommerce product ID (default: 177)
- `title` - Calculator title (default: "Courier Service Calculator")
- `theme` - Theme variation (default: "default" - for future use)
- `container_class` - Additional CSS classes for container

### For Customers

1. Fill in pickup and drop-off addresses (autocomplete helps in production mode)
2. Select service tier (Standard/Express/Premium)
3. Choose any add-on services (Signature, Photo, Expedite)
4. Click "Calculate Price" to get instant quote with breakdown
5. Review pricing details and click "Add to Cart"
6. Complete checkout normally

### Page/Post Integration

You can add the calculator to any page, post, or widget area:

1. **Pages/Posts**: Add the shortcode `[wright_courier_calculator]` in the content editor
2. **Widgets**: Use a Text widget with the shortcode
3. **Theme Integration**: Use `do_shortcode('[wright_courier_calculator]')` in PHP
4. **Custom Fields**: Add shortcode in ACF or custom field content

### For Store Managers

- View detailed courier information in **WooCommerce → Orders**
- Each order shows complete route details, pricing breakdown, and Google Maps links
- Order emails automatically include courier service details
- All pricing calculations are logged for transparency

## Service Tiers

| Tier | Base Price | Per Mile | Free Miles | Estimated Time |
|------|------------|----------|------------|----------------|
| Standard | $15.00 | $1.50 | 5 miles | 4-6 hours |
| Express | $25.00 | $2.00 | 5 miles | 2-3 hours |
| Premium | $40.00 | $4.00 | 5 miles | 1-2 hours |

## Add-on Services

- **Signature Required**: +$5.00 flat fee
- **Photo Confirmation**: +$3.00 flat fee  
- **Expedite Service**: +25% of subtotal

## Pricing Formula

```
Base Price = Tier base price
Distance Charge = (Miles - Free Miles) × Per Mile Rate
Subtotal = Base Price + Distance Charge
Apply Multiplier Add-ons (Expedite)
Add Flat Add-ons (Signature, Photo)
Fuel Surcharge = Subtotal × 5%
TOTAL = Subtotal + Fuel Surcharge
```

## Test Mode

Test mode allows full functionality without Google API:

- **Mock Distance Calculation**: Generates realistic distances (1-95 miles)
- **Address Validation**: Simple validation for testing
- **Full Cart/Checkout Flow**: Complete order process with test data
- **No API Costs**: Perfect for development and testing

Enable test mode in plugin settings or via constant:
```php
define('WWC_TEST_MODE', true);
```

## File Structure

```
wright-courier-calculator/
├── wright-courier-calculator.php    # Main plugin file
├── README.md                       # This documentation
├── /includes/
│   ├── class-wwc-plugin.php        # Core plugin class
│   ├── class-wwc-frontend.php      # Frontend form handling
│   ├── class-wwc-rest.php          # REST API endpoints
│   ├── class-wwc-calculator.php    # Pricing calculations
│   ├── class-wwc-google.php        # Google API integration
│   ├── class-wwc-cart.php          # Cart integration
│   ├── class-wwc-order.php         # Order management
│   └── helpers.php                 # Utility functions
├── /assets/
│   ├── /js/
│   │   └── product.js              # Frontend JavaScript
│   └── /css/
│       └── product.css             # Styling
├── /templates/
│   └── product-fields.php          # Form template
└── /config/
    └── rates.php                   # Rate configuration
```

## REST API

### Calculate Quote
**Endpoint**: `POST /wp-json/wright/v1/quote`

**Request**:
```json
{
  "pickup": {
    "place_id": "ChIJD7fiBh9u",
    "label": "123 Main St, Atlanta, GA"
  },
  "dropoff": {
    "place_id": "ChIJm7_4JiZu",
    "label": "456 Pine Ave, Atlanta, GA"
  },
  "tier": "standard",
  "addons": ["signature", "photo_share"]
}
```

**Response**:
```json
{
  "ok": true,
  "miles": 12.4,
  "pricing": {
    "base": 15.0,
    "extra_miles": 7.4,
    "per_mile": 1.5,
    "distance_subtotal": 26.1,
    "flat_addons": 8.0,
    "mult_addons": 1.0,
    "fuel": 1.71,
    "total": 35.81
  },
  "breakdown_html": "<div class='wwc-price-breakdown'>...</div>"
}
```

### Health Check
**Endpoint**: `GET /wp-json/wright/v1/health`

Returns plugin status and configuration info.

## Security Features

- **Nonce Verification**: All AJAX requests verified
- **Server-side Calculation**: Never trust client-provided totals
- **Input Sanitization**: All user inputs properly sanitized
- **Rate Limiting**: API abuse prevention
- **Google Place ID Validation**: Secure address handling

## Caching

- **Distance Caching**: 12-hour cache for API responses
- **Transient Storage**: WordPress transients for performance
- **Cache Cleanup**: Automatic cleanup of expired cache entries

## Troubleshooting

### Common Issues

**Shortcode not displaying calculator:**
- Ensure you're using the correct shortcode: `[wright_courier_calculator]`
- Check that WooCommerce is active and properly configured
- Verify the product ID exists (default: 177) in WooCommerce
- Check browser console for JavaScript errors

**"Google API key not configured" error:**
- Add Google API key in plugin settings
- Ensure APIs are enabled in Google Cloud Console
- Check API key restrictions

**Distance calculation failing:**
- Verify internet connection
- Check Google API quotas and billing
- Enable test mode to bypass API temporarily

**Prices not updating in cart:**
- Clear browser cache
- Check for plugin conflicts
- Verify WooCommerce version compatibility

### Debug Mode

Enable WordPress debug mode to see detailed logs:
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Plugin logs will appear in `/wp-content/debug.log`

## Requirements

- **WordPress**: 5.0+
- **WooCommerce**: 5.0+
- **PHP**: 7.4+
- **Google API Key**: For production use
- **HTTPS**: Required for Google Places API

## Future Enhancements

- **Onfleet Integration**: Automated dispatch and tracking
- **Mobile Notary Module**: Specialized notary services
- **Admin Settings UI**: Visual rate configuration
- **Multi-job Orders**: Multiple stops per order
- **After-hours Billing**: Automatic surcharge calculation

## Support

For support and customization:
- Review this documentation
- Check WordPress error logs
- Test in isolated environment
- Contact development team

## License

This plugin is proprietary software developed for Wright Courier services.

---

**Version**: 1.0.0  
**Last Updated**: 2024  
**Tested up to**: WordPress 6.4, WooCommerce 8.0