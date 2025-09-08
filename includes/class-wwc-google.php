<?php
defined('ABSPATH') or die('Direct access not allowed');

class WWC_Google {
    
    private $api_key;
    private $test_mode;
    
    public function __construct() {
        $this->api_key = get_option('wwc_google_api_key', '');
        $this->test_mode = get_option('wwc_test_mode', 'yes') === 'yes';
    }

    /**
     * Calculate total miles for origin -> multiple stops by shortest driving route
     * $origin: ['place_id','label','lat','lng']
     * $stops: array of same objects; order will be optimized when using Directions API
     */
    public function get_multi_distance($origin, $stops = []) {
        // Normalize inputs
        $stops = array_values(array_filter($stops, function($s){ return !empty($s['label']); }));
        if (empty($origin) || empty($stops)) {
            return [
                'success' => false,
                'error_code' => 'INVALID_INPUT',
                'message' => __('Origin and at least one stop are required.', 'wright-courier')
            ];
        }

        // Check if any place IDs are fake test IDs
        $has_test_place_ids = false;
        if (strpos($origin['place_id'] ?? '', 'test_') === 0) {
            $has_test_place_ids = true;
        }
        foreach ($stops as $s) {
            if (strpos($s['place_id'] ?? '', 'test_') === 0) {
                $has_test_place_ids = true;
                break;
            }
        }
        
        // Test mode: approximate using haversine + simple nearest-neighbor route
        if ($this->test_mode || empty($this->api_key) || $has_test_place_ids) {
            // Build coordinate list; if lat/lng missing, mock geocode based on label
            $points = [];
            $points[] = $this->ensure_coords($origin);
            foreach ($stops as $s) { $points[] = $this->ensure_coords($s); }

            // Nearest neighbor from origin
            $n = count($points);
            $visited = array_fill(0, $n, false);
            $visited[0] = true; // origin
            $current = 0;
            $total = 0.0;
            for ($step = 1; $step < $n; $step++) {
                $bestDist = PHP_FLOAT_MAX;
                $bestIdx = -1;
                for ($i = 1; $i < $n; $i++) {
                    if ($visited[$i]) continue;
                    $d = $this->haversine_distance($points[$current]['lat'], $points[$current]['lng'], $points[$i]['lat'], $points[$i]['lng']);
                    if ($d < $bestDist) { $bestDist = $d; $bestIdx = $i; }
                }
                if ($bestIdx === -1) break;
                $total += $bestDist;
                $visited[$bestIdx] = true;
                $current = $bestIdx;
            }
            // Apply road factor to approximate real driving distance
            $road_factor = apply_filters('wwc_road_distance_factor', 1.25);
            return [
                'success' => true,
                'miles' => round($total * $road_factor, 2),
                'fallback' => 'haversine_multi'
            ];
        }

        // Build cache key from origin + stops place_ids
        $key_parts = [ $origin['place_id'] ?? $origin['label'] ];
        foreach ($stops as $s) { $key_parts[] = $s['place_id'] ?? $s['label']; }
        $cache_key = 'wwc_dir_' . md5(implode('|', $key_parts));
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return [ 'success' => true, 'miles' => $cached, 'cached' => true ];
        }

        // Prepare Directions API request with optimize:true waypoints
        $origin_param = 'place_id:' . sanitize_text_field($origin['place_id'] ?? '');
        // Use last stop as destination; waypoints contain the rest with optimize:true
        $dest = array_pop($stops);
        $destination_param = 'place_id:' . sanitize_text_field($dest['place_id'] ?? '');
        $waypoints = [];
        foreach ($stops as $s) {
            $waypoints[] = 'place_id:' . sanitize_text_field($s['place_id'] ?? '');
        }
        $waypoints_param = '';
        if (!empty($waypoints)) {
            $waypoints_param = 'optimize:true|' . implode('|', $waypoints);
        }

        $url = 'https://maps.googleapis.com/maps/api/directions/json';
        $params = [
            'origin' => $origin_param,
            'destination' => $destination_param,
            'mode' => 'driving',
            'units' => 'imperial',
            'key' => $this->api_key
        ];
        if ($waypoints_param) { $params['waypoints'] = $waypoints_param; }
        $url .= '?' . http_build_query($params);

        $response = wp_remote_get($url, [ 'timeout' => 20, 'headers' => [ 'Accept' => 'application/json' ] ]);
        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            error_log("WWC Multi-Distance HTTP Error: " . $error_msg . " | URL: " . $url);
            return [ 'success' => false, 'error_code' => 'HTTP_ERROR', 'message' => __('Network error occurred.', 'wright-courier'), 'debug' => $error_msg ];
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Log detailed API response for debugging
        error_log("WWC Multi-Distance API Response: HTTP " . $http_code . " | Status: " . ($data['status'] ?? 'N/A') . " | Body: " . substr($body, 0, 500));
        
        if (empty($data)) {
            return [ 'success' => false, 'error_code' => 'PARSE_ERROR', 'message' => __('Invalid API response format.', 'wright-courier'), 'debug' => 'Empty JSON response' ];
        }
        
        if (($data['status'] ?? '') !== 'OK') {
            $status = $data['status'] ?? 'UNKNOWN';
            $error_message = $data['error_message'] ?? 'No error message provided';
            error_log("WWC Multi-Distance API Error: Status=" . $status . " | Message=" . $error_message);
            return [ 
                'success' => false, 
                'error_code' => 'API_ERROR_' . $status, 
                'message' => __('Google API error: ', 'wright-courier') . $status . ' - ' . $error_message,
                'debug' => ['status' => $status, 'error_message' => $error_message, 'full_response' => $data]
            ];
        }
        
        if (empty($data['routes'][0]['legs'])) {
            error_log("WWC Multi-Distance Route Error: No route legs found | Routes count: " . count($data['routes'] ?? []) . " | Full response: " . $body);
            return [ 
                'success' => false, 
                'error_code' => 'NO_ROUTES', 
                'message' => __('No valid routes found for multi-stop calculation.', 'wright-courier'),
                'debug' => ['routes_count' => count($data['routes'] ?? []), 'full_response' => $data]
            ];
        }
        $legs = $data['routes'][0]['legs'];
        $meters = 0;
        foreach ($legs as $leg) {
            $meters += (int)($leg['distance']['value'] ?? 0);
        }
        $miles = round($meters / 1609.344, 2);
        set_transient($cache_key, $miles, 12 * HOUR_IN_SECONDS);
        return [ 'success' => true, 'miles' => $miles, 'google_data' => [ 'route_legs' => count($legs) ] ];
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
            $error_msg = $response->get_error_message();
            error_log("WWC Distance HTTP Error: " . $error_msg . " | URL: " . $url);
            return [
                'success' => false,
                'error_code' => 'HTTP_ERROR',
                'message' => __('Network error occurred.', 'wright-courier'),
                'debug' => $error_msg
            ];
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Log detailed API response for debugging
        error_log("WWC Distance API Response: HTTP " . $http_code . " | Status: " . ($data['status'] ?? 'N/A') . " | Body: " . substr($body, 0, 500));
        
        if (empty($data) || $data['status'] !== 'OK') {
            $status = $data['status'] ?? 'UNKNOWN';
            $error_message = $data['error_message'] ?? 'No error message provided';
            error_log("WWC Distance API Error: Status=" . $status . " | Message=" . $error_message);
            return [
                'success' => false,
                'error_code' => 'API_ERROR_' . $status,
                'message' => __('Google API error: ', 'wright-courier') . $status . ' - ' . $error_message,
                'debug' => ['status' => $status, 'error_message' => $error_message, 'full_response' => $data]
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

    private function ensure_coords($point) {
        $lat = $point['lat'] ?? null;
        $lng = $point['lng'] ?? null;
        if (is_numeric($lat) && is_numeric($lng)) {
            return [ 'lat' => (float)$lat, 'lng' => (float)$lng ];
        }
        $label = $point['label'] ?? ($point['place_id'] ?? '');
        $geo = $this->get_test_geocode($label);
        return [ 'lat' => (float)$geo['location']['lat'], 'lng' => (float)$geo['location']['lng'] ];
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
