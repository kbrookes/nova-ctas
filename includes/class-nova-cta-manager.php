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

        add_meta_box(
            'nova_cta_layout',
            __('Layout Settings', 'nova-ctas'),
            array($this, 'render_layout_settings'),
            'nova_cta',
            'normal',
            'high'
        );

        add_meta_box(
            'nova_cta_image',
            __('CTA Image', 'nova-ctas'),
            array($this, 'render_image_settings'),
            'nova_cta',
            'side',
            'default'
        );
    }

    public function render_cta_settings($post) {
        wp_nonce_field('nova_cta_settings', 'nova_cta_nonce');

        // Get saved values
        $button_text = get_post_meta($post->ID, '_nova_cta_button_text', true);
        $button_url = get_post_meta($post->ID, '_nova_cta_button_url', true);
        $button_color = get_post_meta($post->ID, '_nova_cta_button_color', true);
        $button_opacity = get_post_meta($post->ID, '_nova_cta_button_opacity', true) ?: '100';
        
        if (empty($button_color)) {
            $button_color = '#0073aa';
        }

        ?>
        <div class="nova-settings-section">
            <div class="nova-tab-nav">
                <button class="nova-tab-button active" data-tab="button"><?php _e('Button', 'nova-ctas'); ?></button>
                <button class="nova-tab-button" data-tab="style"><?php _e('Style', 'nova-ctas'); ?></button>
            </div>

            <div class="nova-tab-content active" data-tab="button">
                <p>
                    <label for="nova_cta_button_text"><?php _e('Button Text:', 'nova-ctas'); ?></label><br>
                    <input type="text" id="nova_cta_button_text" name="nova_cta_button_text" value="<?php echo esc_attr($button_text); ?>" class="widefat">
                </p>
                <p>
                    <label for="nova_cta_button_url"><?php _e('Button URL:', 'nova-ctas'); ?></label><br>
                    <input type="url" id="nova_cta_button_url" name="nova_cta_button_url" value="<?php echo esc_url($button_url); ?>" class="widefat">
                </p>
            </div>

            <div class="nova-tab-content" data-tab="style">
                <p>
                    <label for="nova_cta_button_color"><?php _e('Button Color:', 'nova-ctas'); ?></label><br>
                    <input type="text" id="nova_cta_button_color" name="nova_cta_button_color" value="<?php echo esc_attr($button_color); ?>" class="nova-color-picker">
                    <div class="nova-color-swatches">
                        <span class="nova-color-swatch" data-color="#0073aa" style="background-color: #0073aa;"></span>
                        <span class="nova-color-swatch" data-color="#00a0d2" style="background-color: #00a0d2;"></span>
                        <span class="nova-color-swatch" data-color="#dc3232" style="background-color: #dc3232;"></span>
                    </div>
                </p>
                <p>
                    <label for="nova_cta_button_opacity"><?php _e('Button Opacity:', 'nova-ctas'); ?></label><br>
                    <input type="range" id="nova_cta_button_opacity" name="nova_cta_button_opacity" min="0" max="100" value="<?php echo esc_attr($button_opacity); ?>">
                    <span class="opacity-value"><?php echo esc_html($button_opacity); ?>%</span>
                </p>
            </div>
        </div>
        <?php
    }

    public function render_layout_settings($post) {
        $alignment = get_post_meta($post->ID, '_nova_cta_alignment', true) ?: 'left';
        $column_order = get_post_meta($post->ID, '_nova_cta_column_order', true) ?: 'content-image';
        $content_width = get_post_meta($post->ID, '_nova_cta_content_width', true) ?: '60';
        ?>
        <div class="nova-settings-section">
            <p>
                <label><?php _e('Content Alignment:', 'nova-ctas'); ?></label><br>
                <div class="nova-button-group">
                    <button type="button" class="nova-alignment-button <?php echo $alignment === 'left' ? 'active' : ''; ?>" data-align="left">
                        <?php _e('Left', 'nova-ctas'); ?>
                    </button>
                    <button type="button" class="nova-alignment-button <?php echo $alignment === 'center' ? 'active' : ''; ?>" data-align="center">
                        <?php _e('Center', 'nova-ctas'); ?>
                    </button>
                    <button type="button" class="nova-alignment-button <?php echo $alignment === 'right' ? 'active' : ''; ?>" data-align="right">
                        <?php _e('Right', 'nova-ctas'); ?>
                    </button>
                    <input type="hidden" name="nova_cta_alignment" value="<?php echo esc_attr($alignment); ?>">
                </div>
            </p>
            <p>
                <label><?php _e('Column Order:', 'nova-ctas'); ?></label><br>
                <select name="nova_cta_column_order">
                    <option value="content-image" <?php selected($column_order, 'content-image'); ?>><?php _e('Content → Image', 'nova-ctas'); ?></option>
                    <option value="image-content" <?php selected($column_order, 'image-content'); ?>><?php _e('Image → Content', 'nova-ctas'); ?></option>
                </select>
            </p>
            <p>
                <label><?php _e('Content Width (%):', 'nova-ctas'); ?></label><br>
                <input type="number" name="nova_cta_content_width" value="<?php echo esc_attr($content_width); ?>" min="10" max="90" step="5">
            </p>
        </div>
        <?php
    }

    public function render_image_settings($post) {
        $image_id = get_post_meta($post->ID, '_nova_cta_image', true);
        ?>
        <div class="nova-media-wrapper">
            <div class="nova-preview-image">
                <?php if ($image_id): ?>
                    <?php echo wp_get_attachment_image($image_id, 'medium'); ?>
                <?php endif; ?>
            </div>
            <p>
                <button type="button" class="button nova-upload-image"><?php _e('Upload Image', 'nova-ctas'); ?></button>
                <button type="button" class="button nova-remove-image" <?php echo empty($image_id) ? 'style="display:none;"' : ''; ?>>
                    <?php _e('Remove Image', 'nova-ctas'); ?>
                </button>
                <input type="hidden" name="nova_cta_image" value="<?php echo esc_attr($image_id); ?>">
            </p>
        </div>
        <?php
    }

    public function save_cta_meta($post_id) {
        if (!isset($_POST['nova_cta_nonce']) || !wp_verify_nonce($_POST['nova_cta_nonce'], 'nova_cta_settings')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = array(
            '_nova_cta_button_text' => 'sanitize_text_field',
            '_nova_cta_button_url' => 'esc_url_raw',
            '_nova_cta_button_color' => 'sanitize_hex_color',
            '_nova_cta_button_opacity' => 'intval',
            '_nova_cta_alignment' => 'sanitize_text_field',
            '_nova_cta_column_order' => 'sanitize_text_field',
            '_nova_cta_content_width' => 'intval',
            '_nova_cta_image' => 'intval'
        );

        foreach ($fields as $key => $sanitize_callback) {
            $post_key = str_replace('_', '', substr($key, 1));
            if (isset($_POST[$post_key])) {
                update_post_meta($post_id, $key, $sanitize_callback($_POST[$post_key]));
            }
        }
    }

    public function enqueue_admin_scripts($hook) {
        global $post;

        if ($hook == 'post-new.php' || $hook == 'post.php') {
            if (isset($post) && $post->post_type === 'nova_cta') {
                wp_enqueue_media();
                wp_enqueue_style('wp-color-picker');
                wp_enqueue_script('wp-color-picker');
                wp_enqueue_style(
                    'nova-ctas-admin',
                    NOVA_CTAS_PLUGIN_URL . 'admin/css/admin.css',
                    array(),
                    NOVA_CTAS_VERSION
                );
                wp_enqueue_script(
                    'nova-ctas-admin',
                    NOVA_CTAS_PLUGIN_URL . 'admin/js/admin.js',
                    array('jquery', 'wp-color-picker', 'media-upload'),
                    NOVA_CTAS_VERSION,
                    true
                );
            }
        }
    }

    public function maybe_insert_cta($content) {
        if (!is_single() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $ctas = get_posts(array(
            'post_type' => 'nova_cta',
            'posts_per_page' => -1,
            'orderby' => 'rand',
            'post_status' => 'publish'
        ));

        if (empty($ctas)) {
            return $content;
        }

        $cta = $ctas[array_rand($ctas)];
        $cta_html = $this->build_cta_html($cta);
        
        $paragraphs = explode('</p>', $content);
        if (count($paragraphs) > 1) {
            $paragraphs[0] .= '</p>' . $cta_html;
            return implode('</p>', $paragraphs);
        }
        
        return $content . $cta_html;
    }

    private function build_cta_html($cta) {
        // Get all meta values
        $meta = array(
            'button_text' => get_post_meta($cta->ID, '_nova_cta_button_text', true),
            'button_url' => get_post_meta($cta->ID, '_nova_cta_button_url', true),
            'button_color' => get_post_meta($cta->ID, '_nova_cta_button_color', true),
            'button_opacity' => get_post_meta($cta->ID, '_nova_cta_button_opacity', true),
            'alignment' => get_post_meta($cta->ID, '_nova_cta_alignment', true),
            'column_order' => get_post_meta($cta->ID, '_nova_cta_column_order', true),
            'content_width' => get_post_meta($cta->ID, '_nova_cta_content_width', true),
            'image_id' => get_post_meta($cta->ID, '_nova_cta_image', true)
        );

        if (empty($meta['button_text']) || empty($meta['button_url'])) {
            return '';
        }

        // Build button style
        $button_style = array();
        if (!empty($meta['button_color'])) {
            $opacity = !empty($meta['button_opacity']) ? $meta['button_opacity'] / 100 : 1;
            $button_style[] = "background-color: " . $this->hex2rgba($meta['button_color'], $opacity);
        }

        // Determine container classes
        $container_classes = array('nova-cta-container');
        if (!empty($meta['alignment'])) {
            $container_classes[] = 'nova-align-' . $meta['alignment'];
        }
        if (!empty($meta['image_id'])) {
            $container_classes[] = 'nova-has-image';
            $container_classes[] = 'nova-column-order-' . $meta['column_order'];
        }

        // Start building the HTML
        $html = sprintf('<div class="%s">', esc_attr(implode(' ', $container_classes)));

        if (!empty($meta['image_id'])) {
            $image_width = 100 - intval($meta['content_width']);
            $html .= '<div class="nova-columns" style="--nova-content-width: ' . esc_attr($meta['content_width']) . '%; --nova-image-width: ' . esc_attr($image_width) . '%;">';
            
            // Content column
            $html .= '<div class="nova-content-column">';
            $html .= '<div class="nova-cta-content">' . wp_kses_post($cta->post_content) . '</div>';
            $html .= sprintf(
                '<a href="%s" class="nova-cta-button" style="%s">%s</a>',
                esc_url($meta['button_url']),
                esc_attr(implode('; ', $button_style)),
                esc_html($meta['button_text'])
            );
            $html .= '</div>';

            // Image column
            $html .= '<div class="nova-image-column">';
            $html .= wp_get_attachment_image($meta['image_id'], 'large');
            $html .= '</div>';

            $html .= '</div>'; // Close columns
        } else {
            $html .= '<div class="nova-cta-content">' . wp_kses_post($cta->post_content) . '</div>';
            $html .= sprintf(
                '<a href="%s" class="nova-cta-button" style="%s">%s</a>',
                esc_url($meta['button_url']),
                esc_attr(implode('; ', $button_style)),
                esc_html($meta['button_text'])
            );
        }

        $html .= '</div>'; // Close container

        return $html;
    }

    private function hex2rgba($color, $opacity = 1) {
        $default = 'rgba(0,0,0,1)';
 
        if (empty($color)) {
            return $default;
        }
 
        if ($color[0] == '#') {
            $color = substr($color, 1);
        }
 
        if (strlen($color) == 6) {
            $hex = array($color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5]);
        } elseif (strlen($color) == 3) {
            $hex = array($color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2]);
        } else {
            return $default;
        }
 
        $rgb = array_map('hexdec', $hex);
 
        return 'rgba(' . implode(',', $rgb) . ',' . $opacity . ')';
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