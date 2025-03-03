<?php
if (!defined('ABSPATH')) {
    exit;
}

class IBCTA_CTA_Manager {
    public function register_shortcodes() {
        add_shortcode('internal_blog_cta', array($this, 'render_cta_shortcode'));
    }

    public function enqueue_frontend_styles() {
        wp_enqueue_style(
            'ibcta-frontend',
            IBCTA_PLUGIN_URL . 'public/css/frontend.css',
            array(),
            IBCTA_VERSION
        );
    }

    public function __construct() {
        // Debug message
        add_action('admin_notices', function() {
            echo '<div class="notice notice-info"><p>CTA Manager Constructor Called. Post Type Registration Status: ' . 
                 (post_type_exists('ibcta') ? 'Exists' : 'Not Registered') . '</p></div>';
        });

        // Register post type immediately if not already registered
        if (!post_type_exists('ibcta')) {
            $this->register_cta_post_type();
        }

        add_action('init', array($this, 'register_cta_post_type'), 0);
        add_action('add_meta_boxes', array($this, 'add_cta_meta_boxes'));
        add_action('save_post', array($this, 'save_cta_meta'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
        
        // Register shortcodes
        $this->register_shortcodes();
        
        // Add settings link to plugins page
        $plugin_basename = plugin_basename(dirname(__DIR__) . '/internal-blog-cta.php');
        add_filter('plugin_action_links_' . $plugin_basename, array($this, 'add_settings_link'));

        // Add pillar page meta box to pages
        add_action('add_meta_boxes', array($this, 'add_pillar_page_meta_box'));
        add_action('save_post_page', array($this, 'save_pillar_page_meta'));

        // Add content filter for automatic CTA insertion
        add_filter('the_content', array($this, 'maybe_insert_cta'));

        // Add color picker assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_color_picker'));
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('edit.php?post_type=ibcta') . '">' . __('Settings', 'internal-blog-cta') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function register_cta_post_type() {
        // Debug message
        add_action('admin_notices', function() {
            echo '<div class="notice notice-info"><p>Register CTA Post Type Called</p></div>';
        });

        $labels = array(
            'name'               => __('CTAs', 'internal-blog-cta'),
            'singular_name'      => __('CTA', 'internal-blog-cta'),
            'menu_name'          => __('CTAs', 'internal-blog-cta'),
            'add_new'           => __('Add New', 'internal-blog-cta'),
            'add_new_item'      => __('Add New CTA', 'internal-blog-cta'),
            'edit_item'         => __('Edit CTA', 'internal-blog-cta'),
            'new_item'          => __('New CTA', 'internal-blog-cta'),
            'view_item'         => __('View CTA', 'internal-blog-cta'),
            'search_items'      => __('Search CTAs', 'internal-blog-cta'),
            'not_found'         => __('No CTAs found', 'internal-blog-cta'),
            'not_found_in_trash'=> __('No CTAs found in trash', 'internal-blog-cta')
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'capability_type'     => 'post',
            'capabilities'        => array(
                'edit_post'          => 'edit_post',
                'read_post'          => 'read_post',
                'delete_post'        => 'delete_post',
                'edit_posts'         => 'edit_posts',
                'edit_others_posts'  => 'edit_others_posts',
                'publish_posts'      => 'publish_posts',
                'read_private_posts' => 'read_private_posts'
            ),
            'map_meta_cap'       => true,
            'hierarchical'        => false,
            'rewrite'            => false,
            'supports'            => array('title', 'editor'),
            'menu_icon'           => 'dashicons-megaphone',
            'show_in_rest'        => true
        );

        register_post_type('ibcta', $args);
    }

    public function add_cta_meta_boxes() {
        // Single meta box with all tabs
        add_meta_box(
            'ibcta_settings',
            __('CTA Settings', 'internal-blog-cta'),
            array($this, 'render_cta_settings'),
            'ibcta',
            'normal',
            'high'
        );

        // Display rules (side)
        add_meta_box(
            'ibcta_relationships',
            __('CTA Display Rules', 'internal-blog-cta'),
            array($this, 'render_relationship_settings'),
            'ibcta',
            'side',
            'default'
        );
    }

    public function render_cta_settings($post) {
        $settings = $this->get_cta_settings($post->ID);
        wp_nonce_field('ibcta_save_meta', 'ibcta_meta_nonce');
        ?>
        <div class="ibcta-tabs">
            <!-- Tab Navigation -->
            <div class="ibcta-tab-nav">
                <button type="button" class="ibcta-tab-button active" data-tab="content">
                    <?php _e('Content', 'internal-blog-cta'); ?>
                </button>
                <button type="button" class="ibcta-tab-button" data-tab="layout">
                    <?php _e('Layout', 'internal-blog-cta'); ?>
                </button>
                <button type="button" class="ibcta-tab-button" data-tab="design">
                    <?php _e('Design', 'internal-blog-cta'); ?>
                </button>
            </div>

            <!-- Content Tab -->
            <div class="ibcta-tab-content active" data-tab="content">
                <?php $this->render_content_tab($post); ?>
            </div>

            <!-- Layout Tab -->
            <div class="ibcta-tab-content" data-tab="layout">
                <?php $this->render_layout_tab($post); ?>
            </div>

            <!-- Design Tab -->
            <div class="ibcta-tab-content" data-tab="design">
                <?php $this->render_design_tab($post); ?>
            </div>
        </div>

        <style>
        .ibcta-tab-nav {
            border-bottom: 1px solid #ccc;
            margin-bottom: 20px;
            padding-bottom: 0;
        }
        .ibcta-tab-button {
            background: #f1f1f1;
            border: 1px solid #ccc;
            border-bottom: none;
            padding: 10px 15px;
            margin: 0 5px -1px 0;
            cursor: pointer;
            font-size: 14px;
        }
        .ibcta-tab-button.active {
            background: #fff;
            border-bottom: 1px solid #fff;
        }
        .ibcta-tab-content {
            display: none;
            padding: 20px 0;
        }
        .ibcta-tab-content.active {
            display: block;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.ibcta-tab-button').on('click', function() {
                var tab = $(this).data('tab');
                
                // Update buttons
                $('.ibcta-tab-button').removeClass('active');
                $(this).addClass('active');
                
                // Update content
                $('.ibcta-tab-content').removeClass('active');
                $('.ibcta-tab-content[data-tab="' + tab + '"]').addClass('active');
            });

            // Show/hide column settings when image is added/removed
            function toggleColumnSettings() {
                var hasImage = $('input[name="ibcta_settings[inline_image]"]').val() !== '';
                $('.ibcta-column-settings, .image-alignment')[hasImage ? 'show' : 'hide']();
            }

            // Watch for image changes
            $('input[name="ibcta_settings[inline_image]"]').on('change', toggleColumnSettings);
            
            // Initial check
            toggleColumnSettings();
        });
        </script>
        <?php
    }

    public function render_content_tab($post) {
        $settings = $this->get_cta_settings($post->ID);
        $this->render_content_settings($settings);
    }

    public function render_layout_tab($post) {
        $settings = $this->get_cta_settings($post->ID);
        ?>
        <!-- Position Settings Section -->
        <?php $this->render_position_settings($settings); ?>

        <!-- Column Layout Section -->
        <div class="ibcta-settings-section">
            <h3><?php _e('Column Layout', 'internal-blog-cta'); ?></h3>
            
            <?php 
            // Only show column settings if an image is added
            $has_image = !empty($settings['inline_image']); 
            ?>
            
            <div class="ibcta-column-settings <?php echo $has_image ? '' : 'hidden'; ?>">
                <table class="form-table">
                    <tr>
                        <th><?php _e('Column Layout', 'internal-blog-cta'); ?></th>
                        <td>
                            <select name="ibcta_settings[layout][column_order]">
                                <option value="content-image" <?php selected($settings['layout']['column_order'] ?? 'content-image', 'content-image'); ?>>
                                    <?php _e('Content Left, Image Right', 'internal-blog-cta'); ?>
                                </option>
                                <option value="image-content" <?php selected($settings['layout']['column_order'] ?? 'content-image', 'image-content'); ?>>
                                    <?php _e('Image Left, Content Right', 'internal-blog-cta'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Content Column Width', 'internal-blog-cta'); ?></th>
                        <td>
                            <input type="number" 
                                   name="ibcta_settings[layout][content_width]"
                                   value="<?php echo esc_attr($settings['layout']['content_width'] ?? 60); ?>"
                                   min="20"
                                   max="80"
                                   step="5"
                            > %
                            <p class="description">
                                <?php _e('Image column will automatically use remaining width', 'internal-blog-cta'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Alignment Settings -->
            <table class="form-table">
                <tr>
                    <th><?php _e('Content Alignment', 'internal-blog-cta'); ?></th>
                    <td>
                        <select name="ibcta_settings[layout][content_alignment]">
                            <option value="left" <?php selected($settings['layout']['content_alignment'] ?? 'left', 'left'); ?>>
                                <?php _e('Left', 'internal-blog-cta'); ?>
                            </option>
                            <option value="center" <?php selected($settings['layout']['content_alignment'] ?? 'left', 'center'); ?>>
                                <?php _e('Center', 'internal-blog-cta'); ?>
                            </option>
                            <option value="right" <?php selected($settings['layout']['content_alignment'] ?? 'left', 'right'); ?>>
                                <?php _e('Right', 'internal-blog-cta'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                <tr class="image-alignment <?php echo $has_image ? '' : 'hidden'; ?>">
                    <th><?php _e('Image Alignment', 'internal-blog-cta'); ?></th>
                    <td>
                        <select name="ibcta_settings[layout][image_alignment]">
                            <option value="left" <?php selected($settings['layout']['image_alignment'] ?? 'left', 'left'); ?>>
                                <?php _e('Left', 'internal-blog-cta'); ?>
                            </option>
                            <option value="center" <?php selected($settings['layout']['image_alignment'] ?? 'left', 'center'); ?>>
                                <?php _e('Center', 'internal-blog-cta'); ?>
                            </option>
                            <option value="right" <?php selected($settings['layout']['image_alignment'] ?? 'left', 'right'); ?>>
                                <?php _e('Right', 'internal-blog-cta'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    public function render_design_tab($post) {
        $settings = $this->get_cta_settings($post->ID);
        $this->render_design_settings($settings);
    }

    public function render_relationship_settings($post) {
        $settings = $this->get_cta_settings($post->ID);
        $selected_cats = isset($settings['display_categories']) ? $settings['display_categories'] : array();
        ?>
        <div class="ibcta-relationship-settings">
            <p><strong><?php _e('Pillar Page', 'internal-blog-cta'); ?></strong></p>
            <div class="ibcta-pillar-page">
                <?php
                // Get all pages
                $pages = get_pages(array(
                    'post_status' => 'publish',
                    'meta_key' => '_is_pillar_page', // Optional: you could add a meta field to mark pillar pages
                    'meta_value' => '1'
                ));
                ?>
                <select name="ibcta_settings[pillar_page]" id="ibcta_pillar_page">
                    <option value=""><?php _e('Select a Pillar Page', 'internal-blog-cta'); ?></option>
                    <?php foreach ($pages as $page) : ?>
                        <option value="<?php echo esc_attr($page->ID); ?>" 
                                <?php selected($settings['pillar_page'], $page->ID); ?>
                                data-url="<?php echo esc_url(get_permalink($page->ID)); ?>">
                            <?php echo esc_html($page->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <p><strong><?php _e('Associated Categories', 'internal-blog-cta'); ?></strong></p>
            <p class="description"><?php _e('Select categories that are relevant to this pillar page. The CTA will automatically appear in posts from these categories.', 'internal-blog-cta'); ?></p>
            
            <div class="ibcta-categories">
                <?php
                $categories = get_categories(array('hide_empty' => false));
                foreach ($categories as $category) {
                    ?>
                    <p>
                        <label>
                            <input type="checkbox" name="ibcta_settings[display_categories][]" 
                                   value="<?php echo esc_attr($category->term_id); ?>"
                                   <?php checked(in_array($category->term_id, $selected_cats)); ?>>
                            <?php echo esc_html($category->name); ?>
                        </label>
                    </p>
                    <?php
                }
                ?>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Auto-fill button URL when pillar page is selected
            $('#ibcta_pillar_page').on('change', function() {
                var selectedOption = $(this).find('option:selected');
                var url = selectedOption.data('url');
                $('input[name="ibcta_settings[button_url]"]').val(url);
            });
        });
        </script>
        <?php
    }

    public function enqueue_admin_scripts($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        wp_enqueue_media();
        
        // Enqueue color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Enqueue admin styles
        wp_enqueue_style(
            'ibcta-admin-styles',
            IBCTA_PLUGIN_URL . 'admin/css/admin-styles.css',
            array('wp-color-picker'),
            IBCTA_VERSION
        );
        
        // Enqueue admin scripts with color picker initialization
        wp_enqueue_script(
            'ibcta-admin',
            IBCTA_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery', 'wp-color-picker'),
            IBCTA_VERSION,
            true
        );

        // Pass color palette to JavaScript
        wp_localize_script('ibcta-admin', 'ibctaColors', array(
            'palette' => $this->get_available_colors()
        ));
    }

    private function get_available_colors() {
        $colors = array(
            'default' => array(
                'primary' => '#0073aa',
                'secondary' => '#23282d',
                'success' => '#46b450',
                'info' => '#00a0d2',
                'warning' => '#ffb900',
                'danger' => '#dc3232',
            )
        );

        // Check if AutomaticCSS is active
        if (class_exists('AutomaticCSS')) {
            $acss_colors = get_option('automatic_css_colors', array());
            if (!empty($acss_colors)) {
                $colors['automatic_css'] = $acss_colors;
            }
        }

        // Check if CoreFramework is active
        if (class_exists('CoreFramework')) {
            $cf_colors = get_option('core_framework_colors', array());
            if (!empty($cf_colors)) {
                $colors['core_framework'] = $cf_colors;
            }
        }

        // Get theme colors if theme.json exists
        $theme_json_file = get_template_directory() . '/theme.json';
        if (file_exists($theme_json_file)) {
            $theme_json = json_decode(file_get_contents($theme_json_file), true);
            if (isset($theme_json['settings']['color']['palette'])) {
                $colors['theme'] = array();
                foreach ($theme_json['settings']['color']['palette'] as $color) {
                    $colors['theme'][$color['slug']] = $color['color'];
                }
            }
        }

        return apply_filters('ibcta_available_colors', $colors);
    }

    private function get_cta_settings($post_id) {
        return wp_parse_args(get_post_meta($post_id, '_ibcta_settings', true), array(
            // Existing settings
            'button_text' => '',
            'button_url' => '',
            'bg_image' => '',
            'bg_position' => 'center center',
            'bg_size' => 'cover',
            'bg_color' => '',
            'overlay_color' => '',
            'overlay_opacity' => '50',
            'inline_image' => '',
            'display_all' => false,
            'display_categories' => array(),
            
            // Typography settings
            'element_gap' => '1.5',
            'title_line_height' => '1.2',
            'body_line_height' => '1.5',
            
            // Box Design settings
            'border_radius' => '0',
            'padding_top' => '30',
            'padding_right' => '30',
            'padding_bottom' => '30',
            'padding_left' => '30',
            'margin_top' => '60',
            'margin_right' => '0',
            'margin_bottom' => '60',
            'margin_left' => '0',
            'shadow_x' => '0',
            'shadow_y' => '0',
            'shadow_blur' => '0',
            'shadow_spread' => '0',
            'shadow_color' => 'rgba(0,0,0,0)',
            'pillar_page' => '',
            'position_settings' => array(
                'first_position' => '30', // Percentage through content
                'show_end' => true,       // Whether to show at end
            ),
            'layout' => array(
                'column_order' => 'content-image',
                'content_width' => 60,
                'content_alignment' => 'left',
                'image_alignment' => 'left',
            ),
        ));
    }

    public function save_cta_meta($post_id) {
        if (!isset($_POST['ibcta_meta_nonce']) || 
            !wp_verify_nonce($_POST['ibcta_meta_nonce'], 'ibcta_save_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['ibcta_settings'])) {
            update_post_meta($post_id, '_ibcta_settings', $this->sanitize_settings($_POST['ibcta_settings']));
        }
    }

    private function sanitize_settings($settings) {
        $sanitized = array(
            // Existing settings
            'button_text' => sanitize_text_field($settings['button_text']),
            'button_url' => esc_url_raw($settings['button_url']),
            'bg_image' => absint($settings['bg_image']),
            'bg_position' => sanitize_text_field($settings['bg_position']),
            'bg_size' => sanitize_text_field($settings['bg_size']),
            'bg_color' => sanitize_hex_color($settings['bg_color']),
            'overlay_color' => sanitize_hex_color($settings['overlay_color']),
            'overlay_opacity' => absint($settings['overlay_opacity']),
            'inline_image' => absint($settings['inline_image']),
            'display_all' => isset($settings['display_all']) ? true : false,
            'display_categories' => isset($settings['display_categories']) ? array_map('absint', $settings['display_categories']) : array(),
            
            // Typography settings
            'title_color' => isset($settings['title_color']) ? sanitize_hex_color($settings['title_color']) : '',
            'title_font_size' => isset($settings['title_font_size']) ? sanitize_text_field($settings['title_font_size']) : '2rem',
            'title_font_weight' => isset($settings['title_font_weight']) ? sanitize_text_field($settings['title_font_weight']) : '700',
            'title_alignment' => isset($settings['title_alignment']) ? sanitize_text_field($settings['title_alignment']) : 'left',
            'body_color' => isset($settings['body_color']) ? sanitize_hex_color($settings['body_color']) : '',
            'body_font_size' => isset($settings['body_font_size']) ? sanitize_text_field($settings['body_font_size']) : '1rem',
            'body_font_weight' => isset($settings['body_font_weight']) ? sanitize_text_field($settings['body_font_weight']) : '400',
            'body_alignment' => isset($settings['body_alignment']) ? sanitize_text_field($settings['body_alignment']) : 'left',
            
            // Box design settings
            'border_radius' => isset($settings['border_radius']) ? sanitize_text_field($settings['border_radius']) : '0',
            'padding_top' => isset($settings['padding_top']) ? sanitize_text_field($settings['padding_top']) : '30',
            'padding_right' => isset($settings['padding_right']) ? sanitize_text_field($settings['padding_right']) : '30',
            'padding_bottom' => isset($settings['padding_bottom']) ? sanitize_text_field($settings['padding_bottom']) : '30',
            'padding_left' => isset($settings['padding_left']) ? sanitize_text_field($settings['padding_left']) : '30',
            'margin_top' => isset($settings['margin_top']) ? sanitize_text_field($settings['margin_top']) : '60',
            'margin_right' => isset($settings['margin_right']) ? sanitize_text_field($settings['margin_right']) : '0',
            'margin_bottom' => isset($settings['margin_bottom']) ? sanitize_text_field($settings['margin_bottom']) : '60',
            'margin_left' => isset($settings['margin_left']) ? sanitize_text_field($settings['margin_left']) : '0',
            'shadow_x' => isset($settings['shadow_x']) ? sanitize_text_field($settings['shadow_x']) : '0',
            'shadow_y' => isset($settings['shadow_y']) ? sanitize_text_field($settings['shadow_y']) : '0',
            'shadow_blur' => isset($settings['shadow_blur']) ? sanitize_text_field($settings['shadow_blur']) : '0',
            'shadow_spread' => isset($settings['shadow_spread']) ? sanitize_text_field($settings['shadow_spread']) : '0',
            'shadow_color' => isset($settings['shadow_color']) ? sanitize_hex_color($settings['shadow_color']) : 'rgba(0,0,0,0)',
            'pillar_page' => isset($settings['pillar_page']) ? sanitize_text_field($settings['pillar_page']) : '',
            'position_settings' => array(
                'first_position' => isset($settings['position_settings']['first_position']) ? 
                    absint($settings['position_settings']['first_position']) : 30,
                'show_end' => isset($settings['position_settings']['show_end']) ? 
                    (bool)$settings['position_settings']['show_end'] : true,
            ),
            'layout' => array(
                'column_order' => isset($settings['layout']['column_order']) ? sanitize_text_field($settings['layout']['column_order']) : 'content-image',
                'content_width' => isset($settings['layout']['content_width']) ? absint($settings['layout']['content_width']) : 60,
                'content_alignment' => isset($settings['layout']['content_alignment']) ? sanitize_text_field($settings['layout']['content_alignment']) : 'left',
                'image_alignment' => isset($settings['layout']['image_alignment']) ? sanitize_text_field($settings['layout']['image_alignment']) : 'left',
            ),
        );
        
        return $sanitized;
    }

    public function render_color_field($name, $value, $label) {
        $colors = $this->get_available_colors();
        ?>
        <div class="ibcta-color-field">
            <input type="text" 
                   name="ibcta_settings[<?php echo esc_attr($name); ?>]" 
                   value="<?php echo esc_attr($value); ?>"
                   class="ibcta-color-picker"
                   data-alpha-enabled="true"
                   data-default-color="<?php echo esc_attr($colors['default']['primary']); ?>">
            
            <?php if (!empty($colors)): ?>
            <div class="ibcta-color-presets">
                <label><?php _e('Color Presets:', 'internal-blog-cta'); ?></label>
                <div class="ibcta-color-groups">
                    <?php foreach ($colors as $group => $group_colors): ?>
                        <div class="ibcta-color-group">
                            <h4><?php echo esc_html(ucfirst($group)); ?></h4>
                            <div class="ibcta-color-swatches">
                                <?php foreach ($group_colors as $color_name => $color_value): ?>
                                    <button type="button" 
                                            class="ibcta-color-swatch" 
                                            data-color="<?php echo esc_attr($color_value); ?>"
                                            style="background-color: <?php echo esc_attr($color_value); ?>"
                                            title="<?php echo esc_attr(ucfirst($color_name)); ?>">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <style>
        .wp-picker-container .wp-picker-input-wrap {
            display: inline-flex !important;
        }
        .wp-picker-container input[type="text"].wp-color-picker {
            width: 100px;
        }
        .wp-picker-container .wp-picker-clear {
            margin-left: 6px;
        }
        .ibcta-color-field {
            position: relative;
        }
        .ibcta-color-presets {
            margin-top: 10px;
        }
        .ibcta-color-groups {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 10px;
        }
        .ibcta-color-group h4 {
            margin: 0 0 5px 0;
            font-size: 12px;
        }
        .ibcta-color-swatches {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .ibcta-color-swatch {
            width: 25px;
            height: 25px;
            border: 1px solid #ccc;
            border-radius: 3px;
            cursor: pointer;
            padding: 0;
            background-image: linear-gradient(45deg, #ccc 25%, transparent 25%),
                             linear-gradient(-45deg, #ccc 25%, transparent 25%),
                             linear-gradient(45deg, transparent 75%, #ccc 75%),
                             linear-gradient(-45deg, transparent 75%, #ccc 75%);
            background-size: 10px 10px;
            background-position: 0 0, 0 5px, 5px -5px, -5px 0px;
        }
        .ibcta-color-swatch:hover {
            transform: scale(1.1);
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Initialize color picker with alpha channel support
            $('.ibcta-color-picker').wpColorPicker({
                defaultColor: false,
                change: function(event, ui) {
                    // Handle color changes
                    $(this).val(ui.color.toString()).trigger('change');
                },
                clear: function() {
                    // Handle clear button
                    $(this).val('').trigger('change');
                },
                palettes: false // Disable default palette
            });

            // Handle color swatch clicks
            $('.ibcta-color-swatch').click(function() {
                var color = $(this).data('color');
                var $picker = $(this).closest('.ibcta-color-field').find('.ibcta-color-picker');
                $picker.val(color).trigger('change');
                $picker.wpColorPicker('color', color);
            });
        });
        </script>
        <?php
    }

    public function get_cta($id) {
        $cta = get_post($id);
        
        if (!$cta) {
            return new WP_Error('cta_not_found', __('CTA not found', 'internal-blog-cta'));
        }
        
        if ($cta->post_type !== 'ibcta') {
            return new WP_Error('invalid_cta_type', __('Invalid CTA type', 'internal-blog-cta'));
        }

        $settings = $this->get_cta_settings($id);
        $styles = $this->generate_cta_styles($id, $settings);

        return array(
            'id' => $id,
            'title' => $cta->post_title,
            'content' => $cta->post_content,
            'settings' => $settings,
            'styles' => $styles,
            'button_text' => $settings['button_text'],
            'button_url' => $settings['button_url']
        );
    }

    public function generate_cta_styles($id, $settings) {
        $styles = array(
            'container' => array(
                // Box design
                'background-color' => esc_attr($settings['bg_color']),
                'border-radius' => esc_attr($settings['border_radius']) . 'px',
                'padding' => sprintf('%spx %spx %spx %spx',
                    esc_attr($settings['padding_top']),
                    esc_attr($settings['padding_right']),
                    esc_attr($settings['padding_bottom']),
                    esc_attr($settings['padding_left'])
                ),
                'margin' => sprintf('%spx %spx %spx %spx',
                    esc_attr($settings['margin_top']),
                    esc_attr($settings['margin_right']),
                    esc_attr($settings['margin_bottom']),
                    esc_attr($settings['margin_left'])
                ),
                'box-shadow' => sprintf('%spx %spx %spx %spx %s',
                    esc_attr($settings['shadow_x']),
                    esc_attr($settings['shadow_y']),
                    esc_attr($settings['shadow_blur']),
                    esc_attr($settings['shadow_spread']),
                    esc_attr($settings['shadow_color'])
                ),
                '--ibcta-element-gap' => esc_attr($settings['element_gap']) . 'rem',
            ),
            'title' => array(
                'color' => esc_attr($settings['title_color']),
                'font-size' => esc_attr($settings['title_font_size']),
                'font-weight' => esc_attr($settings['title_font_weight']),
                'text-align' => esc_attr($settings['title_alignment']),
                'line-height' => esc_attr($settings['title_line_height']),
                'margin-bottom' => 'var(--ibcta-element-gap)',
            ),
            'body' => array(
                'color' => esc_attr($settings['body_color']),
                'font-size' => esc_attr($settings['body_font_size']),
                'font-weight' => esc_attr($settings['body_font_weight']),
                'text-align' => esc_attr($settings['body_alignment']),
                'line-height' => esc_attr($settings['body_line_height']),
                'margin-bottom' => 'var(--ibcta-element-gap)',
            ),
            'background' => array(
                'background-position' => esc_attr($settings['bg_position']),
                'background-size' => esc_attr($settings['bg_size']),
            ),
            'overlay' => array(
                'background-color' => $this->adjust_color_opacity($settings['overlay_color'], $settings['overlay_opacity']),
            ),
        );

        return apply_filters('ibcta_generated_styles', $styles, $id, $settings);
    }

    private function adjust_color_opacity($color, $opacity) {
        // Convert hex to rgba if needed
        if (strpos($color, '#') === 0) {
            $color = sscanf($color, "#%02x%02x%02x");
            return sprintf('rgba(%d, %d, %d, %s)', 
                $color[0], 
                $color[1], 
                $color[2], 
                $opacity / 100
            );
        }
        return $color;
    }

    private function sanitize_color($color) {
        if (strpos($color, 'rgba') === 0 || strpos($color, 'hsla') === 0) {
            // Implement rgba/hsla sanitization
            return $this->sanitize_rgba_color($color);
        }
        return sanitize_hex_color($color);
    }

    public function render_cta_shortcode($atts) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'id' => 0,
        ), $atts);

        // Get CTA data
        $cta = $this->get_cta($atts['id']);
        
        // Check for errors
        if (is_wp_error($cta)) {
            return ''; // Return empty if CTA not found or invalid
        }

        // Check if this CTA should be displayed on current post
        if (!$this->should_display_cta($cta['id'])) {
            return '';
        }

        // Start output buffering
        ob_start();
        ?>
        <div class="ibcta-container" id="ibcta-<?php echo esc_attr($cta['id']); ?>">
            <?php if (!empty($cta['settings']['bg_image'])) : ?>
                <div class="ibcta-background" style="background-image: url(<?php echo esc_url(wp_get_attachment_url($cta['settings']['bg_image'])); ?>);"></div>
            <?php endif; ?>
            
            <?php if (!empty($cta['settings']['overlay_color'])) : ?>
                <div class="ibcta-overlay"></div>
            <?php endif; ?>
            
            <div class="ibcta-content">
                <?php if (!empty($cta['settings']['inline_image'])) : ?>
                    <div class="ibcta-image">
                        <?php echo wp_get_attachment_image($cta['settings']['inline_image'], 'full'); ?>
                    </div>
                <?php endif; ?>
                
                <div class="ibcta-text">
                    <h3 class="ibcta-title"><?php echo esc_html($cta['title']); ?></h3>
                    <div class="ibcta-body">
                        <?php echo wp_kses_post($cta['content']); ?>
                    </div>
                    
                    <?php if (!empty($cta['button_text']) && !empty($cta['button_url'])) : ?>
                        <div class="ibcta-button-wrapper">
                            <a href="<?php echo esc_url($cta['button_url']); ?>" class="ibcta-button">
                                <?php echo esc_html($cta['button_text']); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <style>
            #ibcta-<?php echo esc_attr($cta['id']); ?> {
                position: relative;
                <?php foreach ($cta['styles']['container'] as $property => $value) : ?>
                    <?php echo esc_attr($property); ?>: <?php echo esc_attr($value); ?>;
                <?php endforeach; ?>
            }

            #ibcta-<?php echo esc_attr($cta['id']); ?> .ibcta-title {
                <?php foreach ($cta['styles']['title'] as $property => $value) : ?>
                    <?php echo esc_attr($property); ?>: <?php echo esc_attr($value); ?>;
                <?php endforeach; ?>
            }

            #ibcta-<?php echo esc_attr($cta['id']); ?> .ibcta-body {
                <?php foreach ($cta['styles']['body'] as $property => $value) : ?>
                    <?php echo esc_attr($property); ?>: <?php echo esc_attr($value); ?>;
                <?php endforeach; ?>
            }

            #ibcta-<?php echo esc_attr($cta['id']); ?> .ibcta-background {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 1;
                <?php foreach ($cta['styles']['background'] as $property => $value) : ?>
                    <?php echo esc_attr($property); ?>: <?php echo esc_attr($value); ?>;
                <?php endforeach; ?>
            }

            #ibcta-<?php echo esc_attr($cta['id']); ?> .ibcta-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 2;
                <?php foreach ($cta['styles']['overlay'] as $property => $value) : ?>
                    <?php echo esc_attr($property); ?>: <?php echo esc_attr($value); ?>;
                <?php endforeach; ?>
            }

            #ibcta-<?php echo esc_attr($cta['id']); ?> .ibcta-content {
                position: relative;
                z-index: 3;
            }
        </style>
        <?php
        return ob_get_clean();
    }

    private function should_display_cta($cta_id) {
        $settings = $this->get_cta_settings($cta_id);
        
        // If set to display on all posts, return true
        if (!empty($settings['display_all'])) {
            return true;
        }
        
        // If no specific categories are set, return false
        if (empty($settings['display_categories'])) {
            return false;
        }
        
        // Get current post categories
        $post_categories = wp_get_post_categories(get_the_ID());
        
        // Check if any of the post's categories match the CTA's target categories
        return array_intersect($post_categories, $settings['display_categories']);
    }

    public function mark_as_pillar_page($post_id) {
        update_post_meta($post_id, '_is_pillar_page', '1');
    }

    public function unmark_as_pillar_page($post_id) {
        delete_post_meta($post_id, '_is_pillar_page');
    }

    public function add_pillar_page_meta_box() {
        add_meta_box(
            'ibcta_pillar_page_settings',
            __('Pillar Page Settings', 'internal-blog-cta'),
            array($this, 'render_pillar_page_meta_box'),
            'page',
            'side',
            'high'
        );
    }

    public function render_pillar_page_meta_box($post) {
        wp_nonce_field('ibcta_pillar_page_meta', 'ibcta_pillar_page_nonce');
        $is_pillar = get_post_meta($post->ID, '_is_pillar_page', true);
        ?>
        <p>
            <label>
                <input type="checkbox" name="is_pillar_page" value="1" <?php checked($is_pillar, '1'); ?>>
                <?php _e('This is a pillar page', 'internal-blog-cta'); ?>
            </label>
        </p>
        <p class="description">
            <?php _e('Marking this as a pillar page allows it to be selected as a destination for CTAs.', 'internal-blog-cta'); ?>
        </p>
        <?php
    }

    public function save_pillar_page_meta($post_id) {
        // Skip autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['ibcta_pillar_page_nonce']) || 
            !wp_verify_nonce($_POST['ibcta_pillar_page_nonce'], 'ibcta_pillar_page_meta')) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_page', $post_id)) {
            return;
        }

        // Save pillar page status
        if (isset($_POST['is_pillar_page'])) {
            update_post_meta($post_id, '_is_pillar_page', '1');
        } else {
            delete_post_meta($post_id, '_is_pillar_page');
        }
    }

    public function maybe_insert_cta($content) {
        if (!is_single()) {
            return $content;
        }

        $post_categories = wp_get_post_categories(get_the_ID());
        if (empty($post_categories)) {
            error_log('IBCTA Debug - No categories found for post');
            return $content;
        }

        $matching_ctas = $this->get_matching_ctas($post_categories);
        if (empty($matching_ctas)) {
            error_log('IBCTA Debug - No matching CTAs found');
            return $content;
        }

        // Get actual paragraphs (not just HTML tags)
        $paragraphs = explode("\n\n", $content);
        $total_paragraphs = count($paragraphs);
        
        error_log('IBCTA Debug - Total actual paragraphs: ' . $total_paragraphs);

        // Calculate all CTA positions first
        $cta_positions = array();
        foreach ($matching_ctas as $cta) {
            $settings = $this->get_cta_settings($cta);
            $first_position = isset($settings['position_settings']['first_position']) ? 
                $settings['position_settings']['first_position'] : 30;
            
            $insert_at = max(1, floor($total_paragraphs * ($first_position / 100)));
            
            error_log(sprintf(
                'IBCTA Debug - CTA %d: Will insert at paragraph %d of %d (%.1f%%)',
                $cta,
                $insert_at,
                $total_paragraphs,
                $first_position
            ));

            // Store both mid-content and end positions
            $cta_positions[$insert_at][] = $cta;
            if ($settings['position_settings']['show_end']) {
                $cta_positions['end'][] = $cta;
            }
        }

        // Build new content with CTAs
        $new_content = array();
        foreach ($paragraphs as $index => $paragraph) {
            $new_content[] = $paragraph;
            
            // Check if any CTAs should be inserted after this paragraph
            if (isset($cta_positions[$index + 1])) {
                foreach ($cta_positions[$index + 1] as $cta_id) {
                    error_log(sprintf('IBCTA Debug - Inserting CTA %d after paragraph %d', $cta_id, $index + 1));
                    $new_content[] = sprintf('[internal_blog_cta id="%d"]', $cta_id);
                }
            }
        }

        // Add end-of-content CTAs
        if (isset($cta_positions['end'])) {
            foreach ($cta_positions['end'] as $cta_id) {
                error_log(sprintf('IBCTA Debug - Adding CTA %d at end', $cta_id));
                $new_content[] = sprintf('[internal_blog_cta id="%d"]', $cta_id);
            }
        }

        $final_content = implode("\n\n", $new_content);
        error_log('IBCTA Debug - Final content length: ' . strlen($final_content));

        return $final_content;
    }

    private function get_matching_ctas($post_categories) {
        $matching_ctas = array();
        
        // Query CTAs
        $ctas = get_posts(array(
            'post_type' => 'ibcta',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));

        foreach ($ctas as $cta) {
            $settings = $this->get_cta_settings($cta->ID);
            
            // Skip if no categories set
            if (empty($settings['display_categories'])) {
                continue;
            }
            
            // Check for category match
            if (array_intersect($post_categories, $settings['display_categories'])) {
                $matching_ctas[] = $cta->ID;
            }
        }

        return $matching_ctas;
    }

    private function render_position_settings($settings) {
        ?>
        <div class="ibcta-settings-section">
            <h3><?php _e('Position Settings', 'internal-blog-cta'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label for="ibcta_first_position"><?php _e('First CTA Position', 'internal-blog-cta'); ?></label></th>
                    <td>
                        <input type="number" 
                               id="ibcta_first_position"
                               name="ibcta_settings[position_settings][first_position]"
                               value="<?php echo esc_attr($settings['position_settings']['first_position']); ?>"
                               min="0"
                               max="100"
                               step="1"
                        > %
                        <p class="description">
                            <?php _e('Position of first CTA insertion (percentage through content)', 'internal-blog-cta'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ibcta_show_end"><?php _e('Show at End', 'internal-blog-cta'); ?></label></th>
                    <td>
                        <input type="checkbox"
                               id="ibcta_show_end"
                               name="ibcta_settings[position_settings][show_end]"
                               value="1"
                               <?php checked($settings['position_settings']['show_end']); ?>
                        >
                        <span class="description">
                            <?php _e('Also show this CTA at the end of content', 'internal-blog-cta'); ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    public function render_design_settings($settings) {
        ?>
        <!-- Typography & Spacing Section -->
        <div class="ibcta-settings-section">
            <h3><?php _e('Typography & Spacing', 'internal-blog-cta'); ?></h3>
            
            <div class="ibcta-typography-controls">
                <!-- Element Spacing -->
                <div class="ibcta-type-group">
                    <h4><?php _e('Element Spacing', 'internal-blog-cta'); ?></h4>
                    <div class="ibcta-type-row">
                        <label class="ibcta-label"><?php _e('Gap between elements', 'internal-blog-cta'); ?></label>
                        <div class="ibcta-range-with-value">
                            <input type="range" 
                                   name="ibcta_settings[element_gap]" 
                                   value="<?php echo esc_attr($settings['element_gap'] ?? '1.5'); ?>"
                                   min="0"
                                   max="5"
                                   step="0.25"
                                   class="ibcta-range-slider">
                            <input type="text" 
                                   class="ibcta-range-value"
                                   value="<?php echo esc_attr($settings['element_gap'] ?? '1.5'); ?>">
                            <span class="ibcta-unit">rem</span>
                        </div>
                    </div>
                </div>

                <!-- Title Typography -->
                <div class="ibcta-type-group">
                    <h4><?php _e('Title', 'internal-blog-cta'); ?></h4>
                    <div class="ibcta-type-row">
                        <div class="ibcta-type-color">
                            <?php $this->render_color_field('title_color', $settings['title_color'], __('Color', 'internal-blog-cta')); ?>
                        </div>
                        <div class="ibcta-type-size">
                            <input type="text" 
                                   name="ibcta_settings[title_font_size]" 
                                   value="<?php echo esc_attr($settings['title_font_size']); ?>"
                                   placeholder="e.g., 3rem, clamp(2rem, 5vw, 3rem)"
                                   class="regular-text">
                            <p class="description"><?php _e('Supports any CSS size value including clamp()', 'internal-blog-cta'); ?></p>
                        </div>
                        <div class="ibcta-type-weight">
                            <select name="ibcta_settings[title_font_weight]">
                                <?php
                                $weights = array(
                                    '300' => __('Light', 'internal-blog-cta'),
                                    '400' => __('Regular', 'internal-blog-cta'),
                                    '500' => __('Medium', 'internal-blog-cta'),
                                    '600' => __('Semi-Bold', 'internal-blog-cta'),
                                    '700' => __('Bold', 'internal-blog-cta')
                                );
                                foreach ($weights as $value => $label) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($value),
                                        selected($settings['title_font_weight'], $value, false),
                                        esc_html($label)
                                    );
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="ibcta-type-row">
                        <label class="ibcta-label"><?php _e('Line Height', 'internal-blog-cta'); ?></label>
                        <div class="ibcta-range-with-value">
                            <input type="range" 
                                   name="ibcta_settings[title_line_height]" 
                                   value="<?php echo esc_attr($settings['title_line_height'] ?? '1.2'); ?>"
                                   min="1"
                                   max="2"
                                   step="0.1"
                                   class="ibcta-range-slider">
                            <input type="text" 
                                   class="ibcta-range-value"
                                   value="<?php echo esc_attr($settings['title_line_height'] ?? '1.2'); ?>">
                        </div>
                    </div>
                </div>

                <!-- Body Typography -->
                <div class="ibcta-type-group">
                    <h4><?php _e('Body Text', 'internal-blog-cta'); ?></h4>
                    <div class="ibcta-type-row">
                        <div class="ibcta-type-color">
                            <?php $this->render_color_field('body_color', $settings['body_color'], __('Color', 'internal-blog-cta')); ?>
                        </div>
                        <div class="ibcta-type-size">
                            <input type="text" 
                                   name="ibcta_settings[body_font_size]" 
                                   value="<?php echo esc_attr($settings['body_font_size']); ?>"
                                   placeholder="e.g., 1rem, clamp(1rem, 3vw, 1.25rem)"
                                   class="regular-text">
                            <p class="description"><?php _e('Supports any CSS size value including clamp()', 'internal-blog-cta'); ?></p>
                        </div>
                        <div class="ibcta-type-weight">
                            <select name="ibcta_settings[body_font_weight]">
                                <?php
                                foreach ($weights as $value => $label) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($value),
                                        selected($settings['body_font_weight'], $value, false),
                                        esc_html($label)
                                    );
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="ibcta-type-row">
                        <label class="ibcta-label"><?php _e('Line Height', 'internal-blog-cta'); ?></label>
                        <div class="ibcta-range-with-value">
                            <input type="range" 
                                   name="ibcta_settings[body_line_height]" 
                                   value="<?php echo esc_attr($settings['body_line_height'] ?? '1.5'); ?>"
                                   min="1"
                                   max="2"
                                   step="0.1"
                                   class="ibcta-range-slider">
                            <input type="text" 
                                   class="ibcta-range-value"
                                   value="<?php echo esc_attr($settings['body_line_height'] ?? '1.5'); ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Box Design Section -->
        <div class="ibcta-settings-section">
            <h3><?php _e('Box Design', 'internal-blog-cta'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><?php _e('Background Color', 'internal-blog-cta'); ?></th>
                    <td><?php $this->render_color_field('bg_color', $settings['bg_color'], __('Background Color', 'internal-blog-cta')); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Border Radius', 'internal-blog-cta'); ?></th>
                    <td>
                        <input type="number" 
                               name="ibcta_settings[border_radius]" 
                               value="<?php echo esc_attr($settings['border_radius']); ?>"
                               min="0"
                               step="1"> px
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Padding', 'internal-blog-cta'); ?></th>
                    <td class="ibcta-spacing-inputs">
                        <label>
                            <?php _e('Top', 'internal-blog-cta'); ?>
                            <input type="number" name="ibcta_settings[padding_top]" 
                                   value="<?php echo esc_attr($settings['padding_top']); ?>" min="0">
                        </label>
                        <label>
                            <?php _e('Right', 'internal-blog-cta'); ?>
                            <input type="number" name="ibcta_settings[padding_right]" 
                                   value="<?php echo esc_attr($settings['padding_right']); ?>" min="0">
                        </label>
                        <label>
                            <?php _e('Bottom', 'internal-blog-cta'); ?>
                            <input type="number" name="ibcta_settings[padding_bottom]" 
                                   value="<?php echo esc_attr($settings['padding_bottom']); ?>" min="0">
                        </label>
                        <label>
                            <?php _e('Left', 'internal-blog-cta'); ?>
                            <input type="number" name="ibcta_settings[padding_left]" 
                                   value="<?php echo esc_attr($settings['padding_left']); ?>" min="0">
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Margin', 'internal-blog-cta'); ?></th>
                    <td class="ibcta-spacing-inputs">
                        <label>
                            <?php _e('Top', 'internal-blog-cta'); ?>
                            <input type="number" name="ibcta_settings[margin_top]" 
                                   value="<?php echo esc_attr($settings['margin_top']); ?>">
                        </label>
                        <label>
                            <?php _e('Right', 'internal-blog-cta'); ?>
                            <input type="number" name="ibcta_settings[margin_right]" 
                                   value="<?php echo esc_attr($settings['margin_right']); ?>">
                        </label>
                        <label>
                            <?php _e('Bottom', 'internal-blog-cta'); ?>
                            <input type="number" name="ibcta_settings[margin_bottom]" 
                                   value="<?php echo esc_attr($settings['margin_bottom']); ?>">
                        </label>
                        <label>
                            <?php _e('Left', 'internal-blog-cta'); ?>
                            <input type="number" name="ibcta_settings[margin_left]" 
                                   value="<?php echo esc_attr($settings['margin_left']); ?>">
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Box Shadow Section -->
        <div class="ibcta-settings-section">
            <h3><?php _e('Box Shadow', 'internal-blog-cta'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><?php _e('Shadow Settings', 'internal-blog-cta'); ?></th>
                    <td class="ibcta-shadow-inputs">
                        <label>
                            <?php _e('X Offset', 'internal-blog-cta'); ?>
                            <input type="number" name="ibcta_settings[shadow_x]" 
                                   value="<?php echo esc_attr($settings['shadow_x']); ?>">
                        </label>
                        <label>
                            <?php _e('Y Offset', 'internal-blog-cta'); ?>
                            <input type="number" name="ibcta_settings[shadow_y]" 
                                   value="<?php echo esc_attr($settings['shadow_y']); ?>">
                        </label>
                        <label>
                            <?php _e('Blur', 'internal-blog-cta'); ?>
                            <input type="number" name="ibcta_settings[shadow_blur]" 
                                   value="<?php echo esc_attr($settings['shadow_blur']); ?>" min="0">
                        </label>
                        <label>
                            <?php _e('Spread', 'internal-blog-cta'); ?>
                            <input type="number" name="ibcta_settings[shadow_spread]" 
                                   value="<?php echo esc_attr($settings['shadow_spread']); ?>">
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Shadow Color', 'internal-blog-cta'); ?></th>
                    <td><?php $this->render_color_field('shadow_color', $settings['shadow_color'], __('Shadow Color', 'internal-blog-cta')); ?></td>
                </tr>
            </table>
        </div>

        <style>
        .ibcta-typography-controls {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
        }

        .ibcta-type-group {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .ibcta-type-group:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .ibcta-type-group h4 {
            margin: 0 0 15px;
            font-size: 14px;
            color: #1d2327;
        }

        .ibcta-type-row {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .ibcta-type-row:last-child {
            margin-bottom: 0;
        }

        .ibcta-label {
            min-width: 120px;
            font-weight: normal;
        }

        .ibcta-type-color {
            min-width: 120px;
        }

        .ibcta-type-size {
            flex: 1;
        }

        .ibcta-type-size input {
            width: 100%;
        }

        .ibcta-type-size .description {
            margin: 5px 0 0;
            font-style: italic;
            color: #666;
            font-size: 12px;
        }

        .ibcta-type-weight {
            width: 120px;
        }

        .ibcta-type-weight select {
            width: 100%;
        }

        .ibcta-range-with-value {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }

        .ibcta-range-slider {
            flex: 1;
            margin: 0;
        }

        .ibcta-range-value {
            width: 50px;
            text-align: right;
        }

        .ibcta-unit {
            color: #666;
            margin-left: 2px;
        }

        /* Color picker adjustments */
        .wp-picker-container {
            display: inline-block;
        }

        .wp-picker-container .wp-picker-input-wrap {
            display: inline-flex !important;
            gap: 6px;
        }

        /* Responsive adjustments */
        @media screen and (max-width: 782px) {
            .ibcta-type-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .ibcta-label {
                margin-bottom: 5px;
            }
            
            .ibcta-type-color,
            .ibcta-type-weight {
                width: 100%;
            }
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Handle range slider updates
            $('.ibcta-range-slider').on('input', function() {
                $(this).siblings('.ibcta-range-value').val($(this).val());
            });

            // Handle manual value updates
            $('.ibcta-range-value').on('input', function() {
                $(this).siblings('.ibcta-range-slider').val($(this).val());
            });
        });
        </script>
        <?php
    }

    // Add this method to handle content settings
    public function render_content_settings($settings) {
        ?>
        <div class="ibcta-settings-section">
            <h3><?php _e('Inline Image', 'internal-blog-cta'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label><?php _e('Image', 'internal-blog-cta'); ?></label></th>
                    <td>
                        <div class="ibcta-media-wrapper">
                            <input type="hidden" name="ibcta_settings[inline_image]" id="ibcta_inline_image" 
                                   value="<?php echo esc_attr($settings['inline_image']); ?>">
                            <div class="ibcta-preview-image">
                                <?php if ($settings['inline_image']) : ?>
                                    <img src="<?php echo esc_url(wp_get_attachment_url($settings['inline_image'])); ?>">
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button ibcta-upload-image">
                                <?php _e('Select Image', 'internal-blog-cta'); ?>
                            </button>
                            <button type="button" class="button ibcta-remove-image">
                                <?php _e('Remove Image', 'internal-blog-cta'); ?>
                            </button>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="ibcta-settings-section">
            <h3><?php _e('Button Settings', 'internal-blog-cta'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label for="ibcta_button_text"><?php _e('Button Text', 'internal-blog-cta'); ?></label></th>
                    <td>
                        <input type="text" 
                               id="ibcta_button_text"
                               name="ibcta_settings[button_text]"
                               value="<?php echo esc_attr($settings['button_text']); ?>"
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th><label for="ibcta_button_url"><?php _e('Button URL', 'internal-blog-cta'); ?></label></th>
                    <td>
                        <input type="url" 
                               id="ibcta_button_url"
                               name="ibcta_settings[button_url]"
                               value="<?php echo esc_url($settings['button_url']); ?>"
                               class="regular-text">
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    // Add this method to enqueue color picker
    public function enqueue_color_picker($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker-alpha',
            IBCTA_PLUGIN_URL . 'admin/js/wp-color-picker-alpha.min.js',
            array('wp-color-picker'),
            IBCTA_VERSION,
            true
        );
        
        // Add inline script to ensure alpha color picker works
        wp_add_inline_script('wp-color-picker-alpha', 
            'jQuery(document).ready(function($) { $(".ibcta-color-picker").wpColorPicker(); });'
        );
    }
}

