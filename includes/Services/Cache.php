<?php
namespace CloudflareAnalytics\Services;

/**
 * Cache service class
 */
class Cache {
    private $cache_group = 'cloudflare_analytics';
    
    /**
     * Get cached data
     */
    public function get($key) {
        // Try WordPress object cache first
        $cache_key = 'cloudflare_' . md5($key);
        $cached_data = wp_cache_get($cache_key, $this->cache_group);
        if (false !== $cached_data) {
            return $cached_data;
        }
        
        // Fall back to database cache
        global $wpdb;
        $table_name = $wpdb->prefix . 'cloudflare_analytics_cache';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT cache_value, expiration FROM $table_name 
            WHERE cache_key = %s AND expiration > NOW()",
            $key
        ));
        
        if ($result) {
            $data = json_decode($result->cache_value, true);
            wp_cache_set($cache_key, $data, $this->cache_group, 300);
            return $data;
        }
        
        return false;
    }
    
    /**
     * Set cached data
     */
    public function set($key, $value, $expiration = 300) {
        // Set WordPress object cache
        $cache_key = 'cloudflare_' . md5($key);
        wp_cache_set($cache_key, $value, $this->cache_group, $expiration);
        
        // Set database cache
        global $wpdb;
        $table_name = $wpdb->prefix . 'cloudflare_analytics_cache';
        
        $wpdb->replace(
            $table_name,
            [
                'cache_key' => $key,
                'cache_value' => json_encode($value),
                'expiration' => date('Y-m-d H:i:s', time() + $expiration)
            ],
            ['%s', '%s', '%s']
        );
    }
    
    /**
     * Clean up expired cache entries
     */
    public function cleanup() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cloudflare_analytics_cache';
        
        // Batch processing for large cache tables
        $batch_size = 1000;
        do {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name 
                WHERE expiration < NOW() 
                LIMIT %d",
                $batch_size
            ));
        } while ($deleted >= $batch_size);
        
        // Optimize table periodically
        if (rand(1, 100) <= 5) { // 5% chance
            $wpdb->query("OPTIMIZE TABLE $table_name");
        }
    }
}
