<?php
defined('ABSPATH') or die('Direct access not allowed');

class WWC_Google {
    
    private $api_key;
    private $test_mode;
    
    public function __construct() {
        $this->api_key = get_option('wwc_google_api_key', '');
        $this->test_mode = get_option('wwc_test_mode', 'yes') === 'yes';
    }
    
    public function get_distance($pickup_place_id, $dropoff_place_id, $pickup_text = '', $dropoff_text = '', $pickup_lat = null, $pickup_lng = null, $dropoff_lat = null, $dropoff_lng = null) {
        // In test mode, return mock data
        if ($this->test_mode) {
            return $this->get_test_distance($pickup_text, $dropoff_text);
        }
        
        // Check if we have API key
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'error_code' => 'NO_API_KEY',
                'message' => __('Google API key not configured.', 'wright-courier')
            ];
        }
        
        // Try cache first
        $cache_key = 'wwc_dm_' . md5($pickup_place_id . '_' . $dropoff_place_id);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return [
                'success' => true,
                'miles' => $cached_result,
                'cached' => true
            ];
        }
        
        // Make API request
        $distance = $this->make_distance_request($pickup_place_id, $dropoff_place_id);

        if ($distance['success']) {
            // Cache for 12 hours
            set_transient($cache_key, $distance['miles'], 12 * HOUR_IN_SECONDS);
        } else {
            // Fallback: if API fails but we have coordinates, compute haversine with road factor
            if (!is_null($pickup_lat) && !is_null($pickup_lng) && !is_null($dropoff_lat) && !is_null($dropoff_lng)) {
                $miles = $this->haversine_distance($pickup_lat, $pickup_lng, $dropoff_lat, $dropoff_lng);
                // Apply simple road factor to approximate real driving distance
                $road_factor = apply_filters('wwc_road_distance_factor', 1.25);
                $approx_miles = round($miles * $road_factor, 2);
                return [
                    'success' => true,
                    'miles' => $approx_miles,
                    'fallback' => 'haversine'
                ];
            }
        }

        return $distance;
    }
    
    private function get_test_distance($pickup, $dropoff) {
        // Generate realistic test distances based on text similarity
        $pickup_hash = crc32($pickup);
        $dropoff_hash = crc32($dropoff);
        $combined = abs($pickup_hash - $dropoff_hash);
        
        // Generate distance between 1 and 95 miles (within service radius)
        $miles = 1 + ($combined % 95);
        
        // Add some decimal precision
        $miles += (($combined % 100) / 100);
        
        return [
            'success' => true,
            'miles' => round($miles, 2),
            'test_mode' => true
        ];
    }
    
    private function make_distance_request($pickup_place_id, $dropoff_place_id) {
        $url = 'https://maps.googleapis.com/maps/api/distancematrix/json';
        
        $params = [
            'origins' => 'place_id:' . $pickup_place_id,
            'destinations' => 'place_id:' . $dropoff_place_id,
            'mode' => 'driving',
            'units' => 'imperial',
            'key' => $this->api_key
        ];
        
        $url .= '?' . http_build_query($params);
        
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json'
            ]
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error_code' => 'HTTP_ERROR',
                'message' => __('Network error occurred.', 'wright-courier')
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data) || $data['status'] !== 'OK') {
            return [
                'success' => false,
                'error_code' => 'API_ERROR',
                'message' => __('Google API error: ', 'wright-courier') . ($data['status'] ?? 'Unknown error')
            ];
        }
        
        if (empty($data['rows'][0]['elements'][0]) || $data['rows'][0]['elements'][0]['status'] !== 'OK') {
            return [
                'success' => false,
                'error_code' => 'ROUTE_ERROR',
                'message' => __('Unable to calculate route between addresses.', 'wright-courier')
            ];
        }
        
        $element = $data['rows'][0]['elements'][0];
        $distance_meters = $element['distance']['value'];
        $miles = $distance_meters / 1609.344; // Convert to miles
        
        return [
            'success' => true,
            'miles' => round($miles, 2),
            'google_data' => $element
        ];
    }
    
    public function geocode_address($address) {
        if ($this->test_mode) {
            return $this->get_test_geocode($address);
        }
        
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'error_code' => 'NO_API_KEY',
                'message' => __('Google API key not configured.', 'wright-courier')
            ];
        }
        
        $url = 'https://maps.googleapis.com/maps/api/geocode/json';
        
        $params = [
            'address' => $address,
            'key' => $this->api_key
        ];
        
        $url .= '?' . http_build_query($params);
        
        $response = wp_remote_get($url, [
            'timeout' => 10
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error_code' => 'HTTP_ERROR',
                'message' => __('Network error occurred.', 'wright-courier')
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data) || $data['status'] !== 'OK') {
            return [
                'success' => false,
                'error_code' => 'GEOCODE_ERROR',
                'message' => __('Unable to geocode address.', 'wright-courier')
            ];
        }
        
        $result = $data['results'][0];
        
        return [
            'success' => true,
            'place_id' => $result['place_id'],
            'formatted_address' => $result['formatted_address'],
            'location' => $result['geometry']['location']
        ];
    }
    
    private function get_test_geocode($address) {
        // Generate consistent mock data for test addresses
        $hash = crc32($address);
        
        return [
            'success' => true,
            'place_id' => 'test_place_id_' . abs($hash),
            'formatted_address' => $address . ', Atlanta, GA, USA',
            'location' => [
                'lat' => 33.7490 + (($hash % 1000) / 10000), // Around Atlanta
                'lng' => -84.3880 + (($hash % 1000) / 10000)
            ],
            'test_mode' => true
        ];
    }
    
    public function check_service_area($lat, $lng) {
        $service_center = apply_filters('wwc_service_center', WWC_SERVICE_CENTER);
        $max_radius = apply_filters('wwc_service_radius_miles', WWC_SERVICE_RADIUS_MILES);
        
        // Calculate distance from service center
        $distance = $this->haversine_distance(
            $service_center['lat'], 
            $service_center['lng'], 
            $lat, 
            $lng
        );
        
        return [
            'within_area' => $distance <= $max_radius,
            'distance_from_center' => $distance,
            'max_radius' => $max_radius
        ];
    }
    
    private function haversine_distance($lat1, $lon1, $lat2, $lon2) {
        $earth_radius = 3959; // Earth's radius in miles
        
        $lat_delta = deg2rad($lat2 - $lat1);
        $lon_delta = deg2rad($lon2 - $lon1);
        
        $a = sin($lat_delta / 2) * sin($lat_delta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lon_delta / 2) * sin($lon_delta / 2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earth_radius * $c;
    }
    
    public function clear_cache() {
        global $wpdb;
        
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wwc_dm_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wwc_dm_%'");
        
        return true;
    }
}
