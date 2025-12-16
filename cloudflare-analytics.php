<?php
/*
Plugin Name: Cloudflare Analytics
Plugin URI: https://lucentdigital.net
Description: A secure and optimized plugin to display Cloudflare traffic analytics in the WordPress dashboard.
Version: 1.2
Author: Paul Pichugin
Author URI: https://lucentdigital.net
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants
define('CLOUDFLARE_ANALYTICS_VERSION', '1.3');
define('CLOUDFLARE_ANALYTICS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CLOUDFLARE_ANALYTICS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'CloudflareAnalytics\\';
    $base_dir = CLOUDFLARE_ANALYTICS_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Activation Hook
register_activation_hook(__FILE__, function() {
    $installer = new \CloudflareAnalytics\Core\Installer();
    $installer->activate();
});

// Deactivation Hook
register_deactivation_hook(__FILE__, function() {
    $installer = new \CloudflareAnalytics\Core\Installer();
    $installer->deactivate();
});

// Uninstall Hook - uses a static method for proper cleanup
register_uninstall_hook(__FILE__, ['CloudflareAnalytics\Core\Installer', 'uninstall_static']);

// Initialize Plugin
add_action('plugins_loaded', function () {
    // Load text domain for translations
    load_plugin_textdomain(
        'cloudflare-analytics',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
    
    $plugin = \CloudflareAnalytics\Core\Plugin::get_instance();
    $plugin->init();
});