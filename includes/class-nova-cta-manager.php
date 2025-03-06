<?php
if (!defined('ABSPATH')) {
    exit;
}

class Nova_CTA_Manager {
    public function __construct() {
        // Register post type immediately if not already registered
        if (!post_type_exists('nova_cta')) {
            $this->register_cta_post_type();
        }

        // Add actions and filters
        add_action('init', array($this, 'register_shortcodes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('save_post', array($this, 'save_cta_meta'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_filter('the_content', array($this, 'maybe_insert_cta'));
    }

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

    public function add_settings_page() {
        add_menu_page(
            __('Nova CTAs', 'nova-ctas'),
            __('Nova CTAs', 'nova-ctas'),
            'manage_options',
            'nova-ctas',
            array($this, 'render_settings_page'),
            'dashicons-megaphone',
            30
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap nova-settings-page">
            <h1><?php _e('Nova CTAs Settings', 'nova-ctas'); ?></h1>
            
            <div class="nova-tabs">
                <button class="nova-tab-button active" data-tab="general"><?php _e('General', 'nova-ctas'); ?></button>
                <button class="nova-tab-button" data-tab="design"><?php _e('Design', 'nova-ctas'); ?></button>
                <button class="nova-tab-button" data-tab="display"><?php _e('Display', 'nova-ctas'); ?></button>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('nova_ctas_options');
                do_settings_sections('nova_ctas');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting('nova_ctas_options', 'nova_ctas_settings');

        // General Settings
        add_settings_section(
            'nova_ctas_general',
            __('General Settings', 'nova-ctas'),
            null,
            'nova_ctas'
        );

        // Design Settings
        add_settings_section(
            'nova_ctas_design',
            __('Design Settings', 'nova-ctas'),
            null,
            'nova_ctas'
        );

        // Button Colors
        add_settings_field(
            'button_colors',
            __('Button Colors', 'nova-ctas'),
            array($this, 'render_button_colors_field'),
            'nova_ctas',
            'nova_ctas_design'
        );

        // Button Style
        add_settings_field(
            'button_style',
            __('Button Style', 'nova-ctas'),
            array($this, 'render_button_style_field'),
            'nova_ctas',
            'nova_ctas_design'
        );

        // Container Style
        add_settings_field(
            'container_style',
            __('Container Style', 'nova-ctas'),
            array($this, 'render_container_style_field'),
            'nova_ctas',
            'nova_ctas_design'
        );

        // Display Settings
        add_settings_section(
            'nova_ctas_display',
            __('Display Settings', 'nova-ctas'),
            null,
            'nova_ctas'
        );

        // Auto Insert
        add_settings_field(
            'auto_insert',
            __('Auto Insert', 'nova-ctas'),
            array($this, 'render_auto_insert_field'),
            'nova_ctas',
            'nova_ctas_display'
        );
    }

    public function render_button_colors_field() {
        $options = get_option('nova_ctas_settings');
        $bg_color = isset($options['button_bg_color']) ? $options['button_bg_color'] : '#0073aa';
        $text_color = isset($options['button_text_color']) ? $options['button_text_color'] : '#ffffff';
        $hover_bg_color = isset($options['button_hover_bg_color']) ? $options['button_hover_bg_color'] : '#005177';
        $hover_text_color = isset($options['button_hover_text_color']) ? $options['button_hover_text_color'] : '#ffffff';
        ?>
        <div class="nova-color-fields">
            <p>
                <label><?php _e('Background Color:', 'nova-ctas'); ?></label>
                <input type="text" name="nova_ctas_settings[button_bg_color]" value="<?php echo esc_attr($bg_color); ?>" class="nova-color-picker">
            </p>
            <p>
                <label><?php _e('Text Color:', 'nova-ctas'); ?></label>
                <input type="text" name="nova_ctas_settings[button_text_color]" value="<?php echo esc_attr($text_color); ?>" class="nova-color-picker">
            </p>
            <p>
                <label><?php _e('Hover Background:', 'nova-ctas'); ?></label>
                <input type="text" name="nova_ctas_settings[button_hover_bg_color]" value="<?php echo esc_attr($hover_bg_color); ?>" class="nova-color-picker">
            </p>
            <p>
                <label><?php _e('Hover Text:', 'nova-ctas'); ?></label>
                <input type="text" name="nova_ctas_settings[button_hover_text_color]" value="<?php echo esc_attr($hover_text_color); ?>" class="nova-color-picker">
            </p>
        </div>
        <?php
    }

    public function render_button_style_field() {
        $options = get_option('nova_ctas_settings');
        $padding = isset($options['button_padding']) ? $options['button_padding'] : '0.8em 1.5em';
        $border_radius = isset($options['button_border_radius']) ? $options['button_border_radius'] : '3px';
        ?>
        <div class="nova-style-fields">
            <p>
                <label><?php _e('Padding:', 'nova-ctas'); ?></label>
                <input type="text" name="nova_ctas_settings[button_padding]" value="<?php echo esc_attr($padding); ?>" placeholder="0.8em 1.5em">
            </p>
            <p>
                <label><?php _e('Border Radius:', 'nova-ctas'); ?></label>
                <input type="text" name="nova_ctas_settings[button_border_radius]" value="<?php echo esc_attr($border_radius); ?>" placeholder="3px">
            </p>
        </div>
        <?php
    }

    public function render_container_style_field() {
        $options = get_option('nova_ctas_settings');
        $bg_color = isset($options['container_bg_color']) ? $options['container_bg_color'] : '#f8f9fa';
        $padding = isset($options['container_padding']) ? $options['container_padding'] : '2em';
        $border_radius = isset($options['container_border_radius']) ? $options['container_border_radius'] : '4px';
        ?>
        <div class="nova-style-fields">
            <p>
                <label><?php _e('Background Color:', 'nova-ctas'); ?></label>
                <input type="text" name="nova_ctas_settings[container_bg_color]" value="<?php echo esc_attr($bg_color); ?>" class="nova-color-picker">
            </p>
            <p>
                <label><?php _e('Padding:', 'nova-ctas'); ?></label>
                <input type="text" name="nova_ctas_settings[container_padding]" value="<?php echo esc_attr($padding); ?>" placeholder="2em">
            </p>
            <p>
                <label><?php _e('Border Radius:', 'nova-ctas'); ?></label>
                <input type="text" name="nova_ctas_settings[container_border_radius]" value="<?php echo esc_attr($border_radius); ?>" placeholder="4px">
            </p>
        </div>
        <?php
    }

    public function render_auto_insert_field() {
        $options = get_option('nova_ctas_settings');
        $auto_insert = isset($options['auto_insert']) ? $options['auto_insert'] : 'disabled';
        $position = isset($options['insert_position']) ? $options['insert_position'] : 'after_first_paragraph';
        ?>
        <div class="nova-display-fields">
            <p>
                <label>
                    <input type="radio" name="nova_ctas_settings[auto_insert]" value="enabled" <?php checked($auto_insert, 'enabled'); ?>>
                    <?php _e('Enable', 'nova-ctas'); ?>
                </label>
                <label>
                    <input type="radio" name="nova_ctas_settings[auto_insert]" value="disabled" <?php checked($auto_insert, 'disabled'); ?>>
                    <?php _e('Disable', 'nova-ctas'); ?>
                </label>
            </p>
            <p>
                <label><?php _e('Insert Position:', 'nova-ctas'); ?></label>
                <select name="nova_ctas_settings[insert_position]">
                    <option value="after_first_paragraph" <?php selected($position, 'after_first_paragraph'); ?>><?php _e('After First Paragraph', 'nova-ctas'); ?></option>
                    <option value="after_content" <?php selected($position, 'after_content'); ?>><?php _e('After Content', 'nova-ctas'); ?></option>
                    <option value="before_content" <?php selected($position, 'before_content'); ?>><?php _e('Before Content', 'nova-ctas'); ?></option>
                </select>
            </p>
        </div>
        <?php
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
            'show_in_menu'        => false, // We're using a custom menu page
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'rewrite'             => array('slug' => 'nova-cta'),
            'supports'            => array('title', 'editor'),
            'show_in_rest'        => true,
        );

        register_post_type('nova_cta', $args);
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook === 'toplevel_page_nova-ctas') {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_media();
            
            wp_enqueue_style(
                'nova-ctas-admin',
                NOVA_CTAS_PLUGIN_URL . 'admin/css/admin.css',
                array('wp-color-picker'),
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

    public function maybe_insert_cta($content) {
        $options = get_option('nova_ctas_settings');
        
        if (!isset($options['auto_insert']) || $options['auto_insert'] !== 'enabled') {
            return $content;
        }

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
        
        $position = isset($options['insert_position']) ? $options['insert_position'] : 'after_first_paragraph';
        
        switch ($position) {
            case 'before_content':
                return $cta_html . $content;
            case 'after_content':
                return $content . $cta_html;
            case 'after_first_paragraph':
            default:
                $paragraphs = explode('</p>', $content);
                if (count($paragraphs) > 1) {
                    $paragraphs[0] .= '</p>' . $cta_html;
                    return implode('</p>', $paragraphs);
                }
                return $content . $cta_html;
        }
    }

    private function build_cta_html($cta) {
        $options = get_option('nova_ctas_settings');
        $button_text = get_post_meta($cta->ID, '_nova_cta_button_text', true);
        $button_url = get_post_meta($cta->ID, '_nova_cta_button_url', true);
        $custom_style = get_post_meta($cta->ID, '_nova_cta_custom_style', true);

        if (empty($button_text) || empty($button_url)) {
            return '';
        }

        $container_style = array();
        $button_style = array();

        // Apply global styles unless custom style is enabled
        if (!$custom_style) {
            if (!empty($options['container_bg_color'])) {
                $container_style[] = "background-color: " . esc_attr($options['container_bg_color']);
            }
            if (!empty($options['container_padding'])) {
                $container_style[] = "padding: " . esc_attr($options['container_padding']);
            }
            if (!empty($options['container_border_radius'])) {
                $container_style[] = "border-radius: " . esc_attr($options['container_border_radius']);
            }
            if (!empty($options['button_bg_color'])) {
                $button_style[] = "background-color: " . esc_attr($options['button_bg_color']);
            }
            if (!empty($options['button_padding'])) {
                $button_style[] = "padding: " . esc_attr($options['button_padding']);
            }
            if (!empty($options['button_border_radius'])) {
                $button_style[] = "border-radius: " . esc_attr($options['button_border_radius']);
            }
        }

        $container_style_attr = !empty($container_style) ? ' style="' . esc_attr(implode('; ', $container_style)) . '"' : '';
        $button_style_attr = !empty($button_style) ? ' style="' . esc_attr(implode('; ', $button_style)) . '"' : '';

        return sprintf(
            '<div class="nova-cta-container"%s>
                <div class="nova-cta-content">%s</div>
                <a href="%s" class="nova-cta-button"%s>%s</a>
            </div>',
            $container_style_attr,
            wp_kses_post($cta->post_content),
            esc_url($button_url),
            $button_style_attr,
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