<?php
if (!defined('ABSPATH')) {
    exit;
}

class IBCTA_Shortcode {
    public function __construct() {
        add_shortcode('contextual_cta', array($this, 'render_cta'));
        add_filter('the_content', array($this, 'auto_insert_cta'));
    }

    public function render_cta($atts) {
        $defaults = array(
            'category' => '',
            'style' => 'default'
        );
        
        $atts = shortcode_atts($defaults, $atts, 'contextual_cta');
        
        // Get current post categories
        $categories = get_the_category();
        
        // Get associated product/service page
        $product_page = $this->get_associated_product_page($categories);
        
        if (!$product_page) {
            return $this->get_fallback_cta();
        }
        
        return $this->generate_cta_html($product_page);
    }

    private function get_associated_product_page($categories) {
        if (empty($categories)) {
            return false;
        }
    
        global $ibcta_taxonomy;
        if (!isset($ibcta_taxonomy) || !is_object($ibcta_taxonomy)) {
            return false;
        }
        
        foreach ($categories as $category) {
            $product_page = $ibcta_taxonomy->get_associated_product_page($category->term_id);
            if ($product_page) {
                return $product_page;
            }
        }
        
        return false;
    }

    private function generate_cta_html($product_page) {
        $options = get_option('ibcta_settings', array());
        $defaults = array(
            'cta_title' => __('Learn More', 'internal-blog-cta'),
            'cta_description' => __('Discover related products and services.', 'internal-blog-cta'),
            'button_text' => __('Read More', 'internal-blog-cta')
        );
        
        $options = wp_parse_args($options, $defaults);
        
        ob_start();
        ?>
        <div class="ibcta-wrapper">
            <div class="ibcta-content">
                <h3><?php echo esc_html($options['cta_title']); ?></h3>
                <p><?php echo esc_html($options['cta_description']); ?></p>
                <a href="<?php echo esc_url($product_page); ?>" class="ibcta-button">
                    <?php echo esc_html($options['button_text']); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function auto_insert_cta($content) {
        // Only proceed if we're in a single post view and in the main loop
        if (!is_single() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $cta = $this->render_cta(array());
        if (empty($cta)) {
            return $content;
        }

        // Split content into paragraphs
        $parts = explode('</p>', $content);
        
        // If we have at least 2 paragraphs, insert after the second one
        if (count($parts) >= 2) {
            $parts[1] .= $cta;
        }
        
        // Add CTA at the end
        $parts[count($parts) - 1] .= $cta;
        
        return implode('</p>', $parts);
    }
}