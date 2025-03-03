<?php
if (!defined('ABSPATH')) {
    exit;
}

class IBCTA_Taxonomy {
    private $meta_key = 'ibcta_product_page';

    public function __construct() {
        add_action('category_add_form_fields', array($this, 'add_category_fields'));
        add_action('category_edit_form_fields', array($this, 'edit_category_fields'));
        add_action('created_category', array($this, 'save_category_fields'));
        add_action('edited_category', array($this, 'save_category_fields'));
    }

    public function add_category_fields() {
        ?>
        <div class="form-field">
            <label for="ibcta_product_page"><?php _e('Associated Product/Service Page', 'internal-blog-cta'); ?></label>
            <?php
            wp_dropdown_pages(array(
                'name' => 'ibcta_product_page',
                'show_option_none' => __('Select a page', 'internal-blog-cta'),
                'option_none_value' => '',
            ));
            ?>
            <p class="description"><?php _e('Select the product or service page associated with this category.', 'internal-blog-cta'); ?></p>
        </div>
        <?php
    }

    public function edit_category_fields($term) {
        $product_page_id = get_term_meta($term->term_id, $this->meta_key, true);
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="ibcta_product_page"><?php _e('Associated Product/Service Page', 'internal-blog-cta'); ?></label>
            </th>
            <td>
                <?php
                wp_dropdown_pages(array(
                    'name' => 'ibcta_product_page',
                    'show_option_none' => __('Select a page', 'internal-blog-cta'),
                    'option_none_value' => '',
                    'selected' => $product_page_id,
                ));
                ?>
                <p class="description"><?php _e('Select the product or service page associated with this category.', 'internal-blog-cta'); ?></p>
            </td>
        </tr>
        <?php
    }

    public function save_category_fields($term_id) {
        if (isset($_POST['ibcta_product_page'])) {
            update_term_meta(
                $term_id,
                $this->meta_key,
                sanitize_text_field($_POST['ibcta_product_page'])
            );
        }
    }

    public function get_associated_product_page($category_id) {
        $product_page_id = get_term_meta($category_id, $this->meta_key, true);
        return $product_page_id ? get_permalink($product_page_id) : false;
    }

    public function auto_associate_categories() {
        $categories = get_categories(array('hide_empty' => false));
        $pages = get_pages();

        foreach ($categories as $category) {
            // Skip if already has association
            if (get_term_meta($category->term_id, $this->meta_key, true)) {
                continue;
            }

            // Try to find matching page by name or slug
            foreach ($pages as $page) {
                if (strtolower($category->name) === strtolower($page->post_title) ||
                    $category->slug === $page->post_name) {
                    update_term_meta($category->term_id, $this->meta_key, $page->ID);
                    break;
                }
            }
        }
    }
}