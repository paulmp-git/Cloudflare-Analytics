<?php
namespace CloudflareAnalytics\Admin;

use CloudflareAnalytics\Services\API;

/**
 * Dashboard widget class
 */
class Dashboard {
    private $api;
    
    public function __construct(API $api) {
        $this->api = $api;
        
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_fetch_cloudflare_data', [$this, 'handle_ajax_request']);
    }
    
    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget(
                'cloudflare_traffic',
                __('Cloudflare Traffic Analytics', 'cloudflare-analytics'),
                [$this, 'render_widget']
            );
        }
    }
    
    /**
     * Render dashboard widget
     */
    public function render_widget() {
        if (!$this->verify_api_credentials()) {
            echo '<div class="notice notice-warning inline"><p>';
            echo esc_html__('Please configure your Cloudflare API credentials in the settings.', 'cloudflare-analytics');
            echo '</p></div>';
            return;
        }
        
        ?>
        <div class="cloudflare-analytics-widget">
            <select id="cloudflare-time-range" class="widefat">
                <option value="24"><?php esc_html_e('Last 24 Hours', 'cloudflare-analytics'); ?></option>
                <option value="7"><?php esc_html_e('Last Week', 'cloudflare-analytics'); ?></option>
                <option value="30"><?php esc_html_e('Last Month', 'cloudflare-analytics'); ?></option>
            </select>
            <div id="cloudflare-data" class="analytics-grid"></div>
        </div>
        <?php
    }
    
    /**
     * Handle AJAX request
     */
    public function handle_ajax_request() {
        check_ajax_referer('cloudflare_analytics_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'cloudflare-analytics'));
        }
        
        $time_range = isset($_POST['time_range']) ? sanitize_text_field($_POST['time_range']) : '24';
        $analytics_data = $this->api->fetch_analytics($time_range);
        
        if (is_wp_error($analytics_data)) {
            wp_send_json_error($analytics_data->get_error_message());
        }
        
        wp_send_json_success($analytics_data);
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook) {
        if ('index.php' !== $hook) {
            return;
        }
        
        // Register and enqueue styles
        wp_enqueue_style(
            'cloudflare-analytics',
            CLOUDFLARE_ANALYTICS_PLUGIN_URL . 'assets/css/analytics.css',
            [],
            CLOUDFLARE_ANALYTICS_VERSION
        );
        
        // Register and enqueue scripts
        wp_enqueue_script(
            'cloudflare-analytics',
            CLOUDFLARE_ANALYTICS_PLUGIN_URL . 'assets/js/analytics.js',
            ['jquery'],
            CLOUDFLARE_ANALYTICS_VERSION,
            true
        );
        
        wp_localize_script('cloudflare-analytics', 'cloudflareAnalytics', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cloudflare_analytics_nonce'),
            'i18n' => [
                'error' => __('Error loading analytics data', 'cloudflare-analytics'),
                'loading' => __('Loading...', 'cloudflare-analytics')
            ]
        ]);
    }
    
    /**
     * Verify API credentials
     */
    private function verify_api_credentials() {
        $required_options = [
            'cloudflare_api_token',
            'cloudflare_zone_id',
            'cloudflare_account_email'
        ];
        
        foreach ($required_options as $option) {
            if (empty(get_option($option))) {
                return false;
            }
        }
        
        return true;
    }
}
