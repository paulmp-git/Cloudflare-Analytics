<?php
namespace CloudflareAnalytics\Services;

use WP_Error;

/**
 * API service class
 */
class API {
    private $cache;
    private $security;
    
    public function __construct(Cache $cache, Security $security) {
        $this->cache = $cache;
        $this->security = $security;
    }
    
    /**
     * Fetch Cloudflare analytics data
     */
    public function fetch_analytics($time_range) {
        try {
            // Implement stale-while-revalidate caching
            $cache_key = "cloudflare_data_{$time_range}";
            $cached_data = $this->cache->get($cache_key);
            
            if ($cached_data !== false) {
                // Asynchronously refresh cache if it's getting stale
                if (time() - $cached_data['timestamp'] > 240) { // 4 minutes
                    wp_schedule_single_event(time(), 'cloudflare_refresh_cache', [$time_range]);
                }
                return $cached_data['data'];
            }
            
            if (!$this->security->check_rate_limit()) {
                throw new \Exception('Rate limit exceeded');
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
            return new WP_Error('api_error', $e->getMessage());
        }
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
     * Build GraphQL query
     */
    private function build_graphql_query($date_filter) {
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
                  uniqueVisitors
                  pageViews
                }
                max {
                  uniqueVisitors
                }
                min {
                  uniqueVisitors
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
    private function process_api_response($response) {
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
        return [
            'total_visitors' => number_format_i18n($stats['sum']['uniqueVisitors']),
            'max_visitors' => number_format_i18n($stats['max']['uniqueVisitors']),
            'min_visitors' => number_format_i18n($stats['min']['uniqueVisitors']),
            'pageviews' => number_format_i18n($stats['sum']['pageViews'])
        ];
    }
    
    /**
     * Get date filter
     */
    private function get_date_filter($time_range) {
        $intervals = [
            '24' => '-24 hours',
            '7' => '-7 days',
            '30' => '-30 days'
        ];
        
        return date('Y-m-d', strtotime($intervals[$time_range]));
    }
}
