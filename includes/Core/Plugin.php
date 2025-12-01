<?php
namespace CloudflareAnalytics\Core;

use CloudflareAnalytics\Services\Cache;
use CloudflareAnalytics\Services\API;
use CloudflareAnalytics\Services\Security;
use CloudflareAnalytics\Admin\Dashboard;
use CloudflareAnalytics\Admin\Settings;

/**
 * Main plugin class
 */
class Plugin {
    /**
     * @var Plugin|null Plugin instance
     */
    private static $instance = null;

    /**
     * @var Cache Cache service
     */
    private $cache;

    /**
     * @var API API service
     */
    private $api;

    /**
     * @var Security Security service
     */
    private $security;

    /**
     * @var Dashboard Dashboard widget
     */
    private $dashboard;

    /**
     * @var Settings Settings page
     */
    private $settings;

    /**
     * Initialize plugin components
     */
    public function init() {
        // Initialize services
        $this->security = new Security();
        $this->cache = new Cache();
        $this->api = new API($this->cache, $this->security);
        
        // Initialize admin components
        if (is_admin()) {
            $this->dashboard = new Dashboard($this->api);
            $this->settings = new Settings($this->security);
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
    }

    /**
     * Initialize error logging
     */
    public function initialize_error_logging() {
        if (get_option('cloudflare_analytics_error_logging', true)) {
            $log_dir = WP_CONTENT_DIR . '/cloudflare-analytics-logs';
            
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
                file_put_contents($log_dir . '/.htaccess', 'Deny from all');
                file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
            }
        }
    }
}
