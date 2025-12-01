<?php
namespace CloudflareAnalytics\Services;

/**
 * Security service class
 */
class Security {
    /**
     * Encrypt sensitive data
     */
    public function encrypt_data($data) {
        $method = 'AES-256-CBC';
        $key = wp_salt('auth');
        $iv = substr(wp_salt('secure_auth'), 0, 16);
        
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }

    /**
     * Decrypt sensitive data
     */
    public function decrypt_data($data) {
        $method = 'AES-256-CBC';
        $key = wp_salt('auth');
        
        $decoded = base64_decode($data);
        if (strpos($decoded, '::') === false) {
            // Fallback for legacy base64 encoded data
            return $decoded;
        }
        
        list($encrypted_data, $iv) = explode('::', $decoded, 2);
        return openssl_decrypt($encrypted_data, $method, $key, 0, $iv);
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
     * Rate limiting check
     */
    public function check_rate_limit() {
        $ip = $_SERVER['REMOTE_ADDR'];
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
     * Validate and sanitize input
     */
    public function sanitize_input($input, $type = 'text') {
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
            default:
                return sanitize_text_field($input);
        }
    }
}
