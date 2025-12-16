<?php
namespace CloudflareAnalytics\Services;

/**
 * Cache service class
 */
class Cache {
    private string $cache_group = 'cloudflare_analytics';
    private const CACHE_PREFIX = 'cf_';
    private const DEFAULT_EXPIRATION = 300; // 5 minutes
    
    /**
     * Get cached data
     * 
     * @param string $key Cache key
     * @return mixed|false Cached data or false if not found
     */
    public function get(string $key) {
        // Try WordPress object cache first
        $cache_key = $this->build_cache_key($key);
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
     * 
     * @param string $key Cache key
     * @param mixed $value Data to cache
     * @param int $expiration Expiration time in seconds
     */
    public function set(string $key, $value, int $expiration = self::DEFAULT_EXPIRATION): void {
        // Set WordPress object cache
        $cache_key = $this->build_cache_key($key);
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
     * Delete cached data
     * 
     * @param string $key Cache key
     */
    public function delete(string $key): void {
        $cache_key = $this->build_cache_key($key);
        wp_cache_delete($cache_key, $this->cache_group);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cloudflare_analytics_cache';
        $wpdb->delete($table_name, ['cache_key' => $key], ['%s']);
    }
    
    /**
     * Flush all cache
     */
    public function flush(): void {
        wp_cache_flush();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cloudflare_analytics_cache';
        $wpdb->query("TRUNCATE TABLE $table_name");
    }
    
    /**
     * Build cache key with prefix
     */
    private function build_cache_key(string $key): string {
        return self::CACHE_PREFIX . sanitize_key($key);
    }
    
    /**
     * Clean up expired cache entries
     */
    public function cleanup(): void {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cloudflare_analytics_cache';
        
        // Batch processing for large cache tables
        $batch_size = 1000;
        $total_deleted = 0;
        
        do {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name 
                WHERE expiration < NOW() 
                LIMIT %d",
                $batch_size
            ));
            $total_deleted += $deleted;
        } while ($deleted >= $batch_size);
        
        // Optimize table if we deleted a significant amount or on schedule
        $this->maybe_optimize_table($total_deleted);
    }
    
    /**
     * Conditionally optimize the cache table
     */
    private function maybe_optimize_table(int $deleted_count): void {
        $last_optimize = get_option('cloudflare_analytics_last_optimize', 0);
        $week_ago = time() - WEEK_IN_SECONDS;
        
        // Optimize if we deleted more than 100 rows or it's been over a week
        if ($deleted_count > 100 || $last_optimize < $week_ago) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'cloudflare_analytics_cache';
            $wpdb->query("OPTIMIZE TABLE $table_name");
            update_option('cloudflare_analytics_last_optimize', time());
        }
    }
}
