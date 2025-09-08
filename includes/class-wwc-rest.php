<?php
defined('ABSPATH') or die('Direct access not allowed');

class WWC_REST {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    public function register_routes() {
        register_rest_route('wright/v1', '/quote', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_quote'],
            'permission_callback' => [$this, 'permissions_check'],
            'args' => [
                'pickup' => [
                    'required' => true,
                    'type' => 'object',
                    'properties' => [
                        'place_id' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'lat' => ['type' => 'number'],
                        'lng' => ['type' => 'number']
                    ]
                ],
                'dropoff' => [
                    'required' => true,
                    'type' => 'object',
                    'properties' => [
                        'place_id' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'lat' => ['type' => 'number'],
                        'lng' => ['type' => 'number']
                    ]
                ],
                'stops' => [
                    'required' => false,
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'place_id' => ['type' => 'string'],
                            'label' => ['type' => 'string'],
                            'lat' => ['type' => 'number'],
                            'lng' => ['type' => 'number']
                        ]
                    ]
                ],
                'tier' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['standard', 'express', 'premium']
                ],
                'addons' => [
                    'type' => 'array',
                    'items' => ['type' => 'string']
                ]
            ]
        ]);
        
        // Health check endpoint
        register_rest_route('wright/v1', '/health', [
            'methods' => 'GET',
            'callback' => [$this, 'health_check'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    public function permissions_check($request) {
        // Allow all public requests without nonce requirement
        // This makes the endpoint accessible for non-logged in users
        return true;
    }
    
    public function handle_quote(WP_REST_Request $request) {
        try {
            $data = [
                'pickup' => $request->get_param('pickup'),
                'dropoff' => $request->get_param('dropoff'),
                'stops' => $request->get_param('stops') ?? [],
                'tier' => $request->get_param('tier'),
                'addons' => $request->get_param('addons') ?? []
            ];
            
            // Validate data
            $calculator = new WWC_Calculator();
            $validation = $calculator->validate_quote_data($data);
            
            if (!$validation['valid']) {
                return new WP_Error('invalid_data', $validation['error'], ['status' => 400]);
            }
            
            // Get distance (single or multi-stop)
            $google = new WWC_Google();
            $distance_result = null;
            $stops = is_array($data['stops']) ? array_values(array_filter($data['stops'] ?? [], function($s){ return !empty($s['label']); })) : [];
            if (!empty($stops)) {
                // Combine the primary dropoff as the first stop
                array_unshift($stops, $data['dropoff']);
                $distance_result = $google->get_multi_distance($data['pickup'], $stops);
            } else {
                $distance_result = $google->get_distance(
                    $data['pickup']['place_id'],
                    $data['dropoff']['place_id'],
                    $data['pickup']['label'],
                    $data['dropoff']['label'],
                    $data['pickup']['lat'] ?? null,
                    $data['pickup']['lng'] ?? null,
                    $data['dropoff']['lat'] ?? null,
                    $data['dropoff']['lng'] ?? null
                );
            }
            
            if (!$distance_result['success']) {
                return new WP_Error(
                    $distance_result['error_code'],
                    $distance_result['message'],
                    ['status' => 422]
                );
            }
            
            $miles = $distance_result['miles'];
            
            // Check service radius
            if ($miles > WWC_SERVICE_RADIUS_MILES) {
                return new WP_Error(
                    'out_of_radius',
                    sprintf(__('Service not available for distances over %d miles.', 'wright-courier'), WWC_SERVICE_RADIUS_MILES),
                    ['status' => 422]
                );
            }
            
            // Calculate pricing
            $pricing = $calculator->calculate_price($miles, $data['tier'], $data['addons']);
            
            // Generate breakdown HTML
            $breakdown_html = $calculator->generate_breakdown_html(
                $pricing, 
                $miles, 
                $data['tier'], 
                $data['addons']
            );
            
            return rest_ensure_response([
                'ok' => true,
                'miles' => $miles,
                'pricing' => $pricing,
                'breakdown_html' => $breakdown_html,
                'quote_data' => [
                    'pickup' => $data['pickup'],
                    'dropoff' => $data['dropoff'],
                    'stops' => $stops,
                    'tier' => $data['tier'],
                    'addons' => $data['addons'],
                    'timestamp' => time()
                ]
            ]);
            
        } catch (Exception $e) {
            return new WP_Error(
                'calculation_error',
                __('Unable to calculate quote. Please try again.', 'wright-courier'),
                ['status' => 500]
            );
        }
    }
    
    public function health_check(WP_REST_Request $request) {
        $test_mode = get_option('wwc_test_mode', 'yes') === 'yes';
        $api_key_set = !empty(get_option('wwc_google_api_key', ''));
        
        return rest_ensure_response([
            'status' => 'ok',
            'test_mode' => $test_mode,
            'api_configured' => $api_key_set,
            'version' => WWC_PLUGIN_VERSION,
            'timestamp' => time()
        ]);
    }
}
