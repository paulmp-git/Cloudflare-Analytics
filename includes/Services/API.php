<?php
namespace CloudflareAnalytics\Services;

use WP_Error;

/**
 * API service class
 */
class API {
    private Cache $cache;
    private Security $security;
    
    /**
     * Allowed time ranges
     */
    private const ALLOWED_TIME_RANGES = ['24', '7', '30'];
    
    /**
     * Time range intervals mapping
     */
    private const TIME_INTERVALS = [
        '24' => '-24 hours',
        '7' => '-7 days',
        '30' => '-30 days'
    ];
    
    public function __construct(Cache $cache, Security $security) {
        $this->cache = $cache;
        $this->security = $security;
    }
    
    /**
     * Fetch Cloudflare analytics data
     * 
     * @param string $time_range Time range: '24', '7', or '30'
     * @return array|WP_Error Analytics data or error
     */
    public function fetch_analytics(string $time_range): array|WP_Error {
        try {
            // Validate time range
            $time_range = $this->validate_time_range($time_range);
            
            // Validate zone ID before making request
            $zone_id = get_option('cloudflare_zone_id');
            if (!$this->security->validate_zone_id($zone_id)) {
                return new WP_Error('invalid_zone_id', 'Invalid Cloudflare Zone ID format');
            }
            
            // Implement stale-while-revalidate caching
            $cache_key = "cloudflare_data_{$time_range}";
            $cached_data = $this->cache->get($cache_key);
            
            if ($cached_data !== false) {
                // Asynchronously refresh cache if it's getting stale
                if (isset($cached_data['timestamp']) && (time() - $cached_data['timestamp']) > 240) {
                    wp_schedule_single_event(time(), 'cloudflare_refresh_cache', [$time_range]);
                }
                return $cached_data['data'];
            }
            
            if (!$this->security->check_rate_limit()) {
                $this->security->log_error('Rate limit exceeded for analytics fetch');
                return new WP_Error('rate_limit', 'Rate limit exceeded. Please try again later.');
            }
            
            $response = $this->make_api_request($time_range);
            $processed_data = $this->process_api_response($response);
            
            if (!is_wp_error($processed_data)) {
                $this->cache->set($cache_key, [
                    'data' => $processed_data,
                    'timestamp' => time()
                ]);
            }
            
            return $processed_data;
            
        } catch (\Exception $e) {
            $this->security->log_error('API Error: ' . $e->getMessage());
            return new WP_Error('api_error', $e->getMessage());
        }
    }
    
    /**
     * Validate and sanitize time range
     */
    private function validate_time_range(string $time_range): string {
        if (!in_array($time_range, self::ALLOWED_TIME_RANGES, true)) {
            return '24'; // Default fallback
        }
        return $time_range;
    }
    
    /**
     * Make API request with retry logic
     */
    private function make_api_request($time_range) {
        $max_retries = 3;
        $retry_count = 0;
        
        while ($retry_count < $max_retries) {
            $response = wp_remote_post('https://api.cloudflare.com/client/v4/graphql', [
                'headers' => $this->get_api_headers(),
                'body' => wp_json_encode([
                    'query' => $this->build_graphql_query($this->get_date_filter($time_range))
                ]),
                'timeout' => 15,
                'sslverify' => true,
            ]);
            
            if (!is_wp_error($response)) {
                return $response;
            }
            
            $retry_count++;
            if ($retry_count < $max_retries) {
                sleep(pow(2, $retry_count));
            }
        }
        
        return $response;
    }
    
    /**
     * Get API headers
     */
    private function get_api_headers() {
        return [
            'Authorization' => 'Bearer ' . $this->security->decrypt_data(get_option('cloudflare_api_token')),
            'X-Auth-Email' => get_option('cloudflare_account_email'),
            'Content-Type' => 'application/json',
        ];
    }
    
    /**
     * Build GraphQL query with extended metrics
     */
    private function build_graphql_query(string $date_filter): string {
        $zone_id = get_option('cloudflare_zone_id');
        return <<<GRAPHQL
        {
          viewer {
            zones(filter: {zoneTag: "$zone_id"}) {
              httpRequests1dGroups(
                limit: 1,
                filter: { date_geq: "$date_filter" }
              ) {
                sum {
                  requests
                  pageViews
                  bytes
                  cachedBytes
                  threats
                  encryptedRequests
                }
                uniq {
                  uniques
                }
              }
            }
          }
        }
        GRAPHQL;
    }
    
    /**
     * Process API response
     */
    private function process_api_response($response): array|WP_Error {
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['data']['viewer']['zones'][0]['httpRequests1dGroups'][0])) {
            $error_message = 'Invalid response from Cloudflare API';
            if (isset($data['errors'])) {
                $error_message .= ': ' . json_encode($data['errors']);
            }
            return new WP_Error('invalid_response', $error_message);
        }
        
        $stats = $data['data']['viewer']['zones'][0]['httpRequests1dGroups'][0];
        $sum = $stats['sum'] ?? [];
        $uniq = $stats['uniq'] ?? [];
        
        // Calculate cache hit ratio
        $total_bytes = $sum['bytes'] ?? 0;
        $cached_bytes = $sum['cachedBytes'] ?? 0;
        $cache_ratio = $total_bytes > 0 ? round(($cached_bytes / $total_bytes) * 100, 1) : 0;
        
        // Calculate HTTPS percentage
        $total_requests = $sum['requests'] ?? 0;
        $encrypted_requests = $sum['encryptedRequests'] ?? 0;
        $https_percentage = $total_requests > 0 ? round(($encrypted_requests / $total_requests) * 100, 1) : 0;
        
        return [
            'total_visitors' => number_format_i18n($uniq['uniques'] ?? 0),
            'total_requests' => number_format_i18n($total_requests),
            'pageviews' => number_format_i18n($sum['pageViews'] ?? 0),
            'bandwidth' => $this->format_bytes($total_bytes),
            'cached_bandwidth' => $this->format_bytes($cached_bytes),
            'cache_ratio' => $cache_ratio . '%',
            'threats_blocked' => number_format_i18n($sum['threats'] ?? 0),
            'https_percentage' => $https_percentage . '%',
        ];
    }
    
    /**
     * Format bytes to human readable format
     */
    private function format_bytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Get date filter
     */
    private function get_date_filter(string $time_range): string {
        $time_range = $this->validate_time_range($time_range);
        return date('Y-m-d', strtotime(self::TIME_INTERVALS[$time_range]));
    }
    
    /**
     * Test API connection with current credentials
     * 
     * @return array|WP_Error Connection test result
     */
    public function test_connection(): array|WP_Error {
        $zone_id = get_option('cloudflare_zone_id');
        if (!$this->security->validate_zone_id($zone_id)) {
            return new WP_Error('invalid_zone_id', 'Invalid Zone ID format. Must be 32 hexadecimal characters.');
        }
        
        $api_token = get_option('cloudflare_api_token');
        if (empty($api_token)) {
            return new WP_Error('missing_token', 'API Token is not configured.');
        }
        
        $email = get_option('cloudflare_account_email');
        if (empty($email) || !is_email($email)) {
            return new WP_Error('invalid_email', 'Invalid account email address.');
        }
        
        // Make a simple API request to verify credentials
        $response = wp_remote_post('https://api.cloudflare.com/client/v4/graphql', [
            'headers' => $this->get_api_headers(),
            'body' => wp_json_encode([
                'query' => '{ viewer { user { email } } }'
            ]),
            'timeout' => 10,
            'sslverify' => true,
        ]);
        
        if (is_wp_error($response)) {
            return new WP_Error('connection_failed', 'Failed to connect to Cloudflare API: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['errors']) && !empty($data['errors'])) {
            $error_msg = $data['errors'][0]['message'] ?? 'Unknown API error';
            return new WP_Error('api_error', 'Cloudflare API error: ' . $error_msg);
        }
        
        if (isset($data['data']['viewer']['user']['email'])) {
            return [
                'success' => true,
                'message' => 'Connection successful!',
                'email' => $data['data']['viewer']['user']['email']
            ];
        }
        
        return new WP_Error('unknown_error', 'Unexpected response from Cloudflare API');
    }
}
