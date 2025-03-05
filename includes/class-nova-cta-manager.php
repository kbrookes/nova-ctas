<?php
if (!defined('ABSPATH')) {
    exit;
}

class Nova_CTA_Manager {
    public function register_shortcodes() {
        add_shortcode('nova_cta', array($this, 'render_cta_shortcode'));
    }

    public function enqueue_frontend_styles() {
        wp_enqueue_style(
            'nova-ctas-frontend',
            NOVA_CTAS_PLUGIN_URL . 'public/css/frontend.css',
            array(),
            NOVA_CTAS_VERSION
        );
    }

    public function __construct() {
        // Register post type immediately if not already registered
        if (!post_type_exists('nova_cta')) {
            $this->register_cta_post_type();
        }

        // Add actions and filters
        add_action('init', array($this, 'register_shortcodes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
        add_action('add_meta_boxes', array($this, 'add_cta_meta_boxes'));
        add_action('save_post', array($this, 'save_cta_meta'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_color_picker'));
        add_filter('the_content', array($this, 'maybe_insert_cta'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . plugin_basename(NOVA_CTAS_PLUGIN_FILE), array($this, 'add_settings_link'));
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="edit.php?post_type=nova_cta">' . __('Settings', 'nova-ctas') . '</a>';
        array_push($links, $settings_link);
        return $links;
    }

    public function register_cta_post_type() {
        $labels = array(
            'name'               => __('CTAs', 'nova-ctas'),
            'singular_name'      => __('CTA', 'nova-ctas'),
            'menu_name'          => __('Nova CTAs', 'nova-ctas'),
            'add_new'           => __('Add New', 'nova-ctas'),
            'add_new_item'      => __('Add New CTA', 'nova-ctas'),
            'edit_item'         => __('Edit CTA', 'nova-ctas'),
            'new_item'          => __('New CTA', 'nova-ctas'),
            'view_item'         => __('View CTA', 'nova-ctas'),
            'search_items'      => __('Search CTAs', 'nova-ctas'),
            'not_found'         => __('No CTAs found', 'nova-ctas'),
            'not_found_in_trash'=> __('No CTAs found in trash', 'nova-ctas'),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'rewrite'             => array('slug' => 'nova-cta'),
            'supports'            => array('title'),
            'menu_icon'           => 'dashicons-megaphone',
            'show_in_rest'        => true,
        );

        register_post_type('nova_cta', $args);
    }

    // ... existing code with updated naming ...
    // Note: The rest of the class methods will remain functionally the same,
    // just with updated naming conventions from 'ibcta' to 'nova_cta'
} 