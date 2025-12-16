<?php
namespace CloudflareAnalytics\Services;

/**
 * Security service class
 */
class Security {
    /**
     * Encrypt sensitive data using AES-256-CBC with random IV
     */
    public function encrypt_data(string $data): string {
        $method = 'AES-256-CBC';
        $key = hash('sha256', wp_salt('auth'), true);
        $iv = openssl_random_pseudo_bytes(16);
        
        $encrypted = openssl_encrypt($data, $method, $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive data
     */
    public function decrypt_data(string $data): string {
        $method = 'AES-256-CBC';
        $key = hash('sha256', wp_salt('auth'), true);
        
        $decoded = base64_decode($data);
        if (strlen($decoded) < 17) {
            // Invalid data or legacy format
            return '';
        }
        
        // Check for legacy format (with :: separator)
        if (strpos($decoded, '::') !== false) {
            return $this->decrypt_legacy_data($data);
        }
        
        $iv = substr($decoded, 0, 16);
        $encrypted_data = substr($decoded, 16);
        
        $decrypted = openssl_decrypt($encrypted_data, $method, $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Decrypt legacy data format (for backwards compatibility)
     */
    private function decrypt_legacy_data(string $data): string {
        $method = 'AES-256-CBC';
        $key = wp_salt('auth');
        
        $decoded = base64_decode($data);
        if (strpos($decoded, '::') === false) {
            return '';
        }
        
        list($encrypted_data, $iv) = explode('::', $decoded, 2);
        $decrypted = openssl_decrypt($encrypted_data, $method, $key, 0, $iv);
        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Add security headers
     */
    public function add_security_headers() {
        if (headers_sent() || !is_admin()) {
            return;
        }
        
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header("Content-Security-Policy: default-src 'self' https://api.cloudflare.com; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';");
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }

    /**
     * Get client IP address with proxy support
     */
    public function get_client_ip(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // Trust Cloudflare's CF-Connecting-IP header if configured
        if (defined('CLOUDFLARE_ANALYTICS_TRUST_PROXY') && CLOUDFLARE_ANALYTICS_TRUST_PROXY) {
            if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                // Take the first IP from X-Forwarded-For
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ip = trim($ips[0]);
            }
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';
    }

    /**
     * Validate Cloudflare Zone ID format (32-character hex)
     */
    public function validate_zone_id(string $zone_id): bool {
        return (bool) preg_match('/^[a-f0-9]{32}$/i', $zone_id);
    }

    /**
     * Validate API token format
     */
    public function validate_api_token(string $token): bool {
        // Cloudflare API tokens are 40 characters, alphanumeric with underscores and hyphens
        return (bool) preg_match('/^[a-zA-Z0-9_-]{40}$/', $token);
    }

    /**
     * Rate limiting check
     */
    public function check_rate_limit(): bool {
        $ip = $this->get_client_ip();
        $transient_key = 'cf_rate_limit_' . md5($ip);
        $rate_data = get_transient($transient_key);
        
        if (false === $rate_data) {
            set_transient($transient_key, ['count' => 1, 'timestamp' => time()], HOUR_IN_SECONDS);
            return true;
        }
        
        // Implement exponential backoff
        if ($rate_data['count'] > get_option('cloudflare_analytics_rate_limit_requests', 100)) {
            $backoff = min(pow(2, $rate_data['count'] - 100), 3600);
            return false;
        }
        
        $rate_data['count']++;
        set_transient($transient_key, $rate_data, HOUR_IN_SECONDS);
        return true;
    }

    /**
     * Log security events
     */
    public function log_security_event(string $event, string $details = ''): void {
        if (!get_option('cloudflare_analytics_error_logging', true)) {
            return;
        }
        
        $log_dir = WP_CONTENT_DIR . '/cloudflare-analytics-logs';
        $log_file = $log_dir . '/security.log';
        
        if (!file_exists($log_dir)) {
            return;
        }
        
        $timestamp = current_time('mysql');
        $message = sprintf("[%s] [%s] %s\n", $timestamp, $event, $details);
        file_put_contents($log_file, $message, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log general errors
     */
    public function log_error(string $message, string $level = 'error'): void {
        if (!get_option('cloudflare_analytics_error_logging', true)) {
            return;
        }
        
        $log_dir = WP_CONTENT_DIR . '/cloudflare-analytics-logs';
        $log_file = $log_dir . '/debug.log';
        
        if (!file_exists($log_dir)) {
            return;
        }
        
        $timestamp = current_time('mysql');
        $entry = sprintf("[%s] [%s] %s\n", $timestamp, strtoupper($level), $message);
        file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Validate and sanitize input
     */
    public function sanitize_input($input, string $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($input);
            case 'url':
                return esc_url_raw($input);
            case 'int':
                return intval($input);
            case 'float':
                return floatval($input);
            case 'array':
                return array_map([$this, 'sanitize_input'], (array) $input);
            case 'zone_id':
                $sanitized = sanitize_text_field($input);
                return $this->validate_zone_id($sanitized) ? $sanitized : '';
            case 'api_token':
                $sanitized = sanitize_text_field($input);
                return $this->validate_api_token($sanitized) ? $sanitized : '';
            default:
                return sanitize_text_field($input);
        }
    }

    /**
     * Escape output for safe display
     */
    public function escape_output(string $output, string $context = 'html'): string {
        switch ($context) {
            case 'attr':
                return esc_attr($output);
            case 'url':
                return esc_url($output);
            case 'js':
                return esc_js($output);
            default:
                return esc_html($output);
        }
    }
}
