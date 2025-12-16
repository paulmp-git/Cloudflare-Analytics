<?php
namespace CloudflareAnalytics\Core;

use CloudflareAnalytics\Services\Cache;
use CloudflareAnalytics\Services\API;
use CloudflareAnalytics\Services\Security;
use CloudflareAnalytics\Admin\Dashboard;
use CloudflareAnalytics\Admin\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Main plugin class
 */
class Plugin {
    /**
     * @var Plugin|null Plugin instance
     */
    private static ?Plugin $instance = null;

    /**
     * @var Cache Cache service
     */
    private Cache $cache;

    /**
     * @var API API service
     */
    private API $api;

    /**
     * @var Security Security service
     */
    private Security $security;

    /**
     * @var Dashboard|null Dashboard widget
     */
    private ?Dashboard $dashboard = null;

    /**
     * @var Settings|null Settings page
     */
    private ?Settings $settings = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance(): Plugin {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor for singleton
     */
    private function __construct() {
        // Prevent direct instantiation
    }

    /**
     * Initialize plugin components
     */
    public function init(): void {
        // Initialize services
        $this->security = new Security();
        $this->cache = new Cache();
        $this->api = new API($this->cache, $this->security);
        
        // Initialize admin components
        if (is_admin()) {
            $this->dashboard = new Dashboard($this->api, $this->security);
            $this->settings = new Settings($this->security, $this->api);
        }

        // Add security headers
        add_action('send_headers', [$this->security, 'add_security_headers']);

        // Schedule cache cleanup
        if (!wp_next_scheduled('cloudflare_analytics_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'cloudflare_analytics_cache_cleanup');
        }
        add_action('cloudflare_analytics_cache_cleanup', [$this->cache, 'cleanup']);

        // Initialize error logging
        add_action('init', [$this, 'initialize_error_logging']);
        
        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // Register cache refresh action
        add_action('cloudflare_refresh_cache', [$this, 'refresh_cache_async']);
    }

    /**
     * Initialize error logging
     */
    public function initialize_error_logging(): void {
        if (get_option('cloudflare_analytics_error_logging', true)) {
            $log_dir = WP_CONTENT_DIR . '/cloudflare-analytics-logs';
            
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
                file_put_contents($log_dir . '/.htaccess', 'Deny from all');
                file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
            }
        }
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes(): void {
        register_rest_route('cloudflare-analytics/v1', '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_stats'],
            'permission_callback' => [$this, 'rest_permission_check'],
            'args' => [
                'time_range' => [
                    'default' => '24',
                    'validate_callback' => function($param) {
                        return in_array($param, ['24', '7', '30'], true);
                    }
                ]
            ]
        ]);
        
        register_rest_route('cloudflare-analytics/v1', '/test-connection', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_test_connection'],
            'permission_callback' => [$this, 'rest_permission_check'],
        ]);
    }
    
    /**
     * REST API permission check
     */
    public function rest_permission_check(): bool {
        return current_user_can('manage_cloudflare_analytics') || current_user_can('manage_options');
    }
    
    /**
     * REST API: Get analytics stats
     */
    public function rest_get_stats(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $time_range = $request->get_param('time_range');
        $data = $this->api->fetch_analytics($time_range);
        
        if (is_wp_error($data)) {
            return $data;
        }
        
        return new WP_REST_Response($data, 200);
    }
    
    /**
     * REST API: Test connection
     */
    public function rest_test_connection(): WP_REST_Response|WP_Error {
        $result = $this->api->test_connection();
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new WP_REST_Response($result, 200);
    }
    
    /**
     * Async cache refresh handler
     */
    public function refresh_cache_async(string $time_range): void {
        $this->api->fetch_analytics($time_range);
    }
    
    /**
     * Get API instance
     */
    public function get_api(): API {
        return $this->api;
    }
    
    /**
     * Get Security instance
     */
    public function get_security(): Security {
        return $this->security;
    }
}
