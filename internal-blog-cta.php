<?php
/**
 * Plugin Name: Internal Blog CTA
 * Description: A plugin to create and manage Call-to-Action blocks for internal blog posts
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: internal-blog-cta
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('IBCTA_VERSION', '1.0.0');
define('IBCTA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IBCTA_PLUGIN_URL', plugin_dir_url(__FILE__));

if (!defined('IBCTA_PLUGIN_FILE')) {
    define('IBCTA_PLUGIN_FILE', __FILE__);
}

// Include required files
require_once IBCTA_PLUGIN_DIR . 'includes/class-ibcta-cta-manager.php';

// Initialize plugin components
function ibcta_setup() {
    global $ibcta_cta_manager;
    
    // Initialize CTA manager
    $ibcta_cta_manager = new IBCTA_CTA_Manager();
    
    // Load text domain for internationalization
    load_plugin_textdomain('internal-blog-cta', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'ibcta_setup', 0);

// Activation hook
register_activation_hook(__FILE__, function() {
    $cta_manager = new IBCTA_CTA_Manager();
    $cta_manager->register_cta_post_type();
    flush_rewrite_rules();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});