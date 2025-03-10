<?php
/**
 * Plugin Name: Nova CTAs
 * Plugin URI: https://github.com/kbrookes/nova-ctas
 * Description: A WordPress plugin for creating and managing Call To Action buttons
 * Version: 1.1.25
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Author: Kelsey Brookes
 * Author URI: https://github.com/kbrookes
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: nova-ctas
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/kbrookes/nova-ctas
 * Primary Branch: main
 * Release Asset: true
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NOVA_CTAS_VERSION', '1.1.25');
define('NOVA_CTAS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NOVA_CTAS_PLUGIN_URL', plugin_dir_url(__FILE__));

if (!defined('NOVA_CTAS_PLUGIN_FILE')) {
    define('NOVA_CTAS_PLUGIN_FILE', __FILE__);
}

// Include required files
require_once NOVA_CTAS_PLUGIN_DIR . 'includes/class-nova-taxonomy.php';
require_once NOVA_CTAS_PLUGIN_DIR . 'includes/class-nova-related-posts.php';
require_once NOVA_CTAS_PLUGIN_DIR . 'includes/class-nova-related-posts-widget.php';
require_once NOVA_CTAS_PLUGIN_DIR . 'includes/class-nova-shortcode.php';
require_once NOVA_CTAS_PLUGIN_DIR . 'includes/class-nova-cta-manager.php';

// Initialize plugin components
function nova_ctas_setup() {
    global $nova_ctas_manager;
    
    // Initialize CTA manager
    $nova_ctas_manager = new Nova_CTA_Manager();
    
    // Register the widget
    add_action('widgets_init', function() {
        register_widget('Nova_Related_Posts_Widget');
    });
    
    // Initialize taxonomy
    new Nova_Taxonomy();
    
    // Load text domain for internationalization
    load_plugin_textdomain('nova-ctas', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'nova_ctas_setup', 0);

// Activation hook
register_activation_hook(__FILE__, function() {
    $cta_manager = new Nova_CTA_Manager();
    $cta_manager->register_cta_post_type();
    
    // Also register the taxonomy on activation
    $taxonomy = new Nova_Taxonomy();
    $taxonomy->register_taxonomies();
    
    flush_rewrite_rules();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
}); 