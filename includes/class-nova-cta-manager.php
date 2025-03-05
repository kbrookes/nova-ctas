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
            'supports'            => array('title', 'editor'),
            'menu_icon'           => 'dashicons-megaphone',
            'show_in_rest'        => true,
        );

        register_post_type('nova_cta', $args);
    }

    public function add_cta_meta_boxes() {
        add_meta_box(
            'nova_cta_settings',
            __('CTA Settings', 'nova-ctas'),
            array($this, 'render_cta_settings'),
            'nova_cta',
            'normal',
            'high'
        );
    }

    public function render_cta_settings($post) {
        // Add nonce for security
        wp_nonce_field('nova_cta_settings', 'nova_cta_nonce');

        // Get saved values
        $button_text = get_post_meta($post->ID, '_nova_cta_button_text', true);
        $button_url = get_post_meta($post->ID, '_nova_cta_button_url', true);
        $button_color = get_post_meta($post->ID, '_nova_cta_button_color', true);
        
        // Default color if none set
        if (empty($button_color)) {
            $button_color = '#0073aa';
        }

        ?>
        <p>
            <label for="nova_cta_button_text"><?php _e('Button Text:', 'nova-ctas'); ?></label><br>
            <input type="text" id="nova_cta_button_text" name="nova_cta_button_text" value="<?php echo esc_attr($button_text); ?>" class="widefat">
        </p>
        <p>
            <label for="nova_cta_button_url"><?php _e('Button URL:', 'nova-ctas'); ?></label><br>
            <input type="url" id="nova_cta_button_url" name="nova_cta_button_url" value="<?php echo esc_url($button_url); ?>" class="widefat">
        </p>
        <p>
            <label for="nova_cta_button_color"><?php _e('Button Color:', 'nova-ctas'); ?></label><br>
            <input type="text" id="nova_cta_button_color" name="nova_cta_button_color" value="<?php echo esc_attr($button_color); ?>" class="nova-color-picker">
        </p>
        <?php
    }

    public function save_cta_meta($post_id) {
        // Check if our nonce is set and verify it
        if (!isset($_POST['nova_cta_nonce']) || !wp_verify_nonce($_POST['nova_cta_nonce'], 'nova_cta_settings')) {
            return;
        }

        // If this is an autosave, don't do anything
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the user's permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save the meta fields
        if (isset($_POST['nova_cta_button_text'])) {
            update_post_meta($post_id, '_nova_cta_button_text', sanitize_text_field($_POST['nova_cta_button_text']));
        }
        if (isset($_POST['nova_cta_button_url'])) {
            update_post_meta($post_id, '_nova_cta_button_url', esc_url_raw($_POST['nova_cta_button_url']));
        }
        if (isset($_POST['nova_cta_button_color'])) {
            update_post_meta($post_id, '_nova_cta_button_color', sanitize_hex_color($_POST['nova_cta_button_color']));
        }
    }

    public function enqueue_admin_scripts($hook) {
        global $post;

        // Only enqueue on CTA edit screens
        if ($hook == 'post-new.php' || $hook == 'post.php') {
            if (isset($post) && $post->post_type === 'nova_cta') {
                wp_enqueue_style('wp-color-picker');
                wp_enqueue_script('wp-color-picker');
                wp_enqueue_script(
                    'nova-ctas-admin',
                    NOVA_CTAS_PLUGIN_URL . 'admin/js/admin.js',
                    array('wp-color-picker'),
                    NOVA_CTAS_VERSION,
                    true
                );
            }
        }
    }

    public function enqueue_color_picker() {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }

    public function maybe_insert_cta($content) {
        // Only process main content of single posts
        if (!is_single() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        // Get all CTAs
        $ctas = get_posts(array(
            'post_type' => 'nova_cta',
            'posts_per_page' => -1,
            'orderby' => 'rand',
            'post_status' => 'publish'
        ));

        if (empty($ctas)) {
            return $content;
        }

        // Get a random CTA
        $cta = $ctas[array_rand($ctas)];
        
        // Build the CTA HTML
        $cta_html = $this->build_cta_html($cta);
        
        // Insert after the first paragraph
        $paragraphs = explode('</p>', $content);
        if (count($paragraphs) > 1) {
            $paragraphs[0] .= '</p>' . $cta_html;
            return implode('</p>', $paragraphs);
        }
        
        // If no paragraphs found, append to the content
        return $content . $cta_html;
    }

    private function build_cta_html($cta) {
        $button_text = get_post_meta($cta->ID, '_nova_cta_button_text', true);
        $button_url = get_post_meta($cta->ID, '_nova_cta_button_url', true);
        $button_color = get_post_meta($cta->ID, '_nova_cta_button_color', true);

        if (empty($button_text) || empty($button_url)) {
            return '';
        }

        $style = '';
        if (!empty($button_color)) {
            $style = 'style="background-color: ' . esc_attr($button_color) . ';"';
        }

        return sprintf(
            '<div class="nova-cta-container">
                <div class="nova-cta-content">%s</div>
                <a href="%s" class="nova-cta-button" %s>%s</a>
            </div>',
            wp_kses_post($cta->post_content),
            esc_url($button_url),
            $style,
            esc_html($button_text)
        );
    }

    public function render_cta_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0
        ), $atts, 'nova_cta');

        if (empty($atts['id'])) {
            return '';
        }

        $cta = get_post($atts['id']);
        if (!$cta || $cta->post_type !== 'nova_cta' || $cta->post_status !== 'publish') {
            return '';
        }

        return $this->build_cta_html($cta);
    }
} 