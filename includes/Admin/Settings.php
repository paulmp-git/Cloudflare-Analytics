<?php
namespace CloudflareAnalytics\Admin;

use CloudflareAnalytics\Services\Security;

/**
 * Settings page class
 */
class Settings {
    private $security;
    private $option_group = 'cloudflare_analytics_settings';
    private $page = 'cloudflare-analytics';
    
    public function __construct(Security $security) {
        $this->security = $security;
        
        add_action('admin_menu', [$this, 'add_settings_menu']);
        add_action('admin_init', [$this, 'initialize_settings']);
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
                submit_button(__('Save Settings', 'cloudflare-analytics'));
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render section description
     */
    public function render_section_description() {
        ?>
        <p>
            <?php _e('Enter your Cloudflare API credentials below. You can find these in your Cloudflare dashboard.', 'cloudflare-analytics'); ?>
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
    public function encrypt_api_token($token) {
        if (empty($token) || $token === str_repeat('•', 32)) {
            return get_option('cloudflare_api_token');
        }
        return $this->security->encrypt_data($token);
    }
}
