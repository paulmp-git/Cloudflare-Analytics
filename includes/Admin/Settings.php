<?php
namespace CloudflareAnalytics\Admin;

use CloudflareAnalytics\Services\Security;
use CloudflareAnalytics\Services\API;

/**
 * Settings page class
 */
class Settings {
    private Security $security;
    private API $api;
    private string $option_group = 'cloudflare_analytics_settings';
    private string $page = 'cloudflare-analytics';
    
    public function __construct(Security $security, API $api) {
        $this->security = $security;
        $this->api = $api;
        
        add_action('admin_menu', [$this, 'add_settings_menu']);
        add_action('admin_init', [$this, 'initialize_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_settings_assets']);
        add_action('wp_ajax_cloudflare_test_connection', [$this, 'handle_test_connection']);
    }
    
    /**
     * Add settings menu
     */
    public function add_settings_menu() {
        add_options_page(
            __('Cloudflare Analytics Settings', 'cloudflare-analytics'),
            __('Cloudflare Analytics', 'cloudflare-analytics'),
            'manage_options',
            $this->page,
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Initialize settings
     */
    public function initialize_settings() {
        register_setting($this->option_group, 'cloudflare_api_token', [
            'type' => 'string',
            'sanitize_callback' => [$this, 'encrypt_api_token'],
            'show_in_rest' => false,
        ]);
        
        register_setting($this->option_group, 'cloudflare_zone_id', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => false,
        ]);
        
        register_setting($this->option_group, 'cloudflare_account_email', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
            'show_in_rest' => false,
        ]);
        
        add_settings_section(
            'cloudflare_analytics_main',
            __('API Settings', 'cloudflare-analytics'),
            [$this, 'render_section_description'],
            $this->page
        );
        
        $this->add_settings_fields();
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields($this->option_group);
                do_settings_sections($this->page);
                ?>
                <p class="submit">
                    <?php submit_button(esc_html__('Save Settings', 'cloudflare-analytics'), 'primary', 'submit', false); ?>
                    <button type="button" id="cloudflare-test-connection" class="button button-secondary">
                        <?php esc_html_e('Test Connection', 'cloudflare-analytics'); ?>
                    </button>
                    <span id="connection-test-result" style="margin-left: 10px;"></span>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render section description
     */
    public function render_section_description(): void {
        ?>
        <p>
            <?php esc_html_e('Enter your Cloudflare API credentials below. You can find these in your Cloudflare dashboard.', 'cloudflare-analytics'); ?>
        </p>
        <p>
            <strong><?php esc_html_e('Required Permissions:', 'cloudflare-analytics'); ?></strong>
            <?php esc_html_e('Your API token needs Zone:Read and Analytics:Read permissions.', 'cloudflare-analytics'); ?>
        </p>
        <?php
    }
    
    /**
     * Add settings fields
     */
    private function add_settings_fields() {
        add_settings_field(
            'cloudflare_api_token',
            __('API Token', 'cloudflare-analytics'),
            [$this, 'render_api_token_field'],
            $this->page,
            'cloudflare_analytics_main'
        );
        
        add_settings_field(
            'cloudflare_zone_id',
            __('Zone ID', 'cloudflare-analytics'),
            [$this, 'render_zone_id_field'],
            $this->page,
            'cloudflare_analytics_main'
        );
        
        add_settings_field(
            'cloudflare_account_email',
            __('Account Email', 'cloudflare-analytics'),
            [$this, 'render_email_field'],
            $this->page,
            'cloudflare_analytics_main'
        );
    }
    
    /**
     * Render API token field
     */
    public function render_api_token_field() {
        $value = get_option('cloudflare_api_token');
        ?>
        <input type="password" 
               name="cloudflare_api_token" 
               value="<?php echo esc_attr($value ? str_repeat('•', 32) : ''); ?>" 
               class="regular-text" 
               autocomplete="off">
        <?php
    }
    
    /**
     * Render zone ID field
     */
    public function render_zone_id_field() {
        $value = get_option('cloudflare_zone_id');
        ?>
        <input type="text" 
               name="cloudflare_zone_id" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" 
               autocomplete="off">
        <?php
    }
    
    /**
     * Render email field
     */
    public function render_email_field() {
        $value = get_option('cloudflare_account_email');
        ?>
        <input type="email" 
               name="cloudflare_account_email" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text">
        <?php
    }
    
    /**
     * Encrypt API token
     */
    public function encrypt_api_token($token): string {
        if (empty($token) || $token === str_repeat('•', 32)) {
            return get_option('cloudflare_api_token', '');
        }
        return $this->security->encrypt_data($token);
    }
    
    /**
     * Handle AJAX connection test
     */
    public function handle_test_connection(): void {
        check_ajax_referer('cloudflare_test_connection_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('Insufficient permissions', 'cloudflare-analytics'));
        }
        
        $result = $this->api->test_connection();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Enqueue settings page assets
     */
    public function enqueue_settings_assets(string $hook): void {
        if ('settings_page_cloudflare-analytics' !== $hook) {
            return;
        }
        
        wp_enqueue_script(
            'cloudflare-analytics-settings',
            CLOUDFLARE_ANALYTICS_PLUGIN_URL . 'assets/js/settings.js',
            ['jquery'],
            CLOUDFLARE_ANALYTICS_VERSION,
            true
        );
        
        wp_localize_script('cloudflare-analytics-settings', 'cloudflareSettings', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cloudflare_test_connection_nonce'),
            'i18n' => [
                'testing' => esc_html__('Testing connection...', 'cloudflare-analytics'),
                'success' => esc_html__('Connection successful!', 'cloudflare-analytics'),
                'error' => esc_html__('Connection failed', 'cloudflare-analytics')
            ]
        ]);
    }
}
