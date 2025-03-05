<?php
if (!defined('ABSPATH')) {
    exit;
}

class Nova_Taxonomy {
    public function __construct() {
        add_action('init', array($this, 'register_taxonomies'));
    }

    public function register_taxonomies() {
        $labels = array(
            'name'              => __('CTA Categories', 'nova-ctas'),
            'singular_name'     => __('CTA Category', 'nova-ctas'),
            'search_items'      => __('Search CTA Categories', 'nova-ctas'),
            'all_items'         => __('All CTA Categories', 'nova-ctas'),
            'parent_item'       => __('Parent CTA Category', 'nova-ctas'),
            'parent_item_colon' => __('Parent CTA Category:', 'nova-ctas'),
            'edit_item'         => __('Edit CTA Category', 'nova-ctas'),
            'update_item'       => __('Update CTA Category', 'nova-ctas'),
            'add_new_item'      => __('Add New CTA Category', 'nova-ctas'),
            'new_item_name'     => __('New CTA Category Name', 'nova-ctas'),
            'menu_name'         => __('CTA Categories', 'nova-ctas'),
        );

        $args = array(
            'hierarchical'      => true,
            'labels'           => $labels,
            'show_ui'          => true,
            'show_admin_column' => true,
            'query_var'        => true,
            'rewrite'          => array('slug' => 'cta-category'),
            'show_in_rest'     => true,
        );

        register_taxonomy('nova_cta_category', array('nova_cta'), $args);
    }
} 