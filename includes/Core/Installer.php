<?php
namespace CloudflareAnalytics\Core;

/**
 * Plugin installer class
 */
class Installer {
    /**
     * Plugin activation
     */
    public function activate() {
        $this->create_cache_tables();
        $this->set_default_options();
        
        // Clear any existing rate limit data
        delete_option('cloudflare_analytics_rate_limit');
        
        // Add capabilities
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_cloudflare_analytics');
        }
        
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        wp_clear_scheduled_hook('cloudflare_analytics_cache_cleanup');
        flush_rewrite_rules();
    }
    
    /**
     * Plugin uninstall
     */
    public function uninstall() {
        global $wpdb;
        
        // Remove options
        $options = [
            'cloudflare_api_token',
            'cloudflare_zone_id',
            'cloudflare_account_email',
            'cloudflare_analytics_cache_duration',
            'cloudflare_analytics_rate_limit_requests',
            'cloudflare_analytics_error_logging',
            'cloudflare_analytics_rate_limit'
        ];
        
        foreach ($options as $option) {
            delete_option($option);
        }
        
        // Remove cache table
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cloudflare_analytics_cache");
        
        // Remove capabilities
        $role = get_role('administrator');
        if ($role) {
            $role->remove_cap('manage_cloudflare_analytics');
        }
        
        // Remove log directory
        $log_dir = WP_CONTENT_DIR . '/cloudflare-analytics-logs';
        if (is_dir($log_dir)) {
            $this->remove_directory($log_dir);
        }
    }
    
    /**
     * Create cache tables
     */
    private function create_cache_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'cloudflare_analytics_cache';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cache_key varchar(255) NOT NULL,
            cache_value longtext NOT NULL,
            expiration datetime NOT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY cache_key (cache_key),
            KEY expiration (expiration)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = [
            'cloudflare_analytics_cache_duration' => 300, // 5 minutes
            'cloudflare_analytics_rate_limit_requests' => 100, // requests per hour
            'cloudflare_analytics_error_logging' => true
        ];
        
        foreach ($defaults as $key => $value) {
            if (false === get_option($key)) {
                update_option($key, $value);
            }
        }
    }
    
    /**
     * Remove directory recursively
     */
    private function remove_directory($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->remove_directory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
