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

        add_action('init', array($this, 'register_cta_post_type'), 0);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
        add_action('edit_form_after_title', array($this, 'render_cta_admin_page'));
        add_action('save_post_nova_cta', array($this, 'save_cta_data'));
        add_action('add_meta_boxes', array($this, 'add_cta_meta_boxes'));
        
        // Register shortcodes
        $this->register_shortcodes();
        
        // Add settings link to plugins page
        $plugin_basename = plugin_basename(NOVA_CTAS_PLUGIN_FILE);
        add_filter('plugin_action_links_' . $plugin_basename, array($this, 'add_settings_link'));

        // Add pillar page meta box to pages
        add_action('add_meta_boxes', array($this, 'add_pillar_page_meta_box'));
        add_action('save_post_page', array($this, 'save_pillar_page_meta'));

        // Add content filter for automatic CTA insertion
        add_filter('the_content', array($this, 'maybe_insert_cta'));

        // Remove default editor
        add_action('admin_init', function() {
            remove_post_type_support('nova_cta', 'editor');
        });
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

    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('edit.php?post_type=nova_cta') . '">' . __('Settings', 'nova-ctas') . '</a>';
        array_unshift($links, $settings_link);
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
            'enter_title_here'  => __('CTA Heading', 'nova-ctas'),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_icon'           => 'dashicons-megaphone',
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'supports'            => array('title'),  // Only support title, we'll handle content ourselves
            'menu_position'       => 30,
            'show_in_rest'        => false, // Disable Gutenberg
        );

        register_post_type('nova_cta', $args);
    }

    public function enqueue_admin_scripts($hook) {
        global $post;
        
        if (($hook === 'post.php' || $hook === 'post-new.php') && get_post_type() === 'nova_cta') {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_media();
            wp_enqueue_editor();
            
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

    public function render_cta_admin_page($post) {
        if ($post->post_type !== 'nova_cta') {
            return;
        }
        
        $settings = get_post_meta($post->ID, '_nova_cta_settings', true);
        $design = get_post_meta($post->ID, '_nova_cta_design', true);
        $display = get_post_meta($post->ID, '_nova_cta_display', true);
        
        // Default values
        $content = $post->post_content;
        $button_text = isset($settings['button_text']) ? $settings['button_text'] : '';
        $button_url = isset($settings['button_url']) ? $settings['button_url'] : '';
        $button_target = isset($settings['button_target']) ? $settings['button_target'] : '_self';
        
        ?>
        <div class="nova-cta-editor">
            <div class="nova-tabs">
                <button type="button" class="nova-tab-button active" data-tab="content"><?php _e('Content', 'nova-ctas'); ?></button>
                <button type="button" class="nova-tab-button" data-tab="design"><?php _e('Design', 'nova-ctas'); ?></button>
                <button type="button" class="nova-tab-button" data-tab="display"><?php _e('Display', 'nova-ctas'); ?></button>
                <button type="button" class="nova-tab-button" data-tab="relationships"><?php _e('Relationships', 'nova-ctas'); ?></button>
            </div>

            <div class="nova-tab-content active" data-tab="content">
                <div class="nova-field-group">
                    <label for="nova_cta_content"><?php _e('Content:', 'nova-ctas'); ?></label>
                    <?php 
                    wp_editor($content, 'content', array(
                        'textarea_name' => 'content',
                        'media_buttons' => true,
                        'textarea_rows' => 10,
                        'teeny' => false
                    ));
                    ?>
                </div>

                <div class="nova-field-group">
                    <label for="nova_cta_button_text"><?php _e('Button Text:', 'nova-ctas'); ?></label>
                    <input type="text" id="nova_cta_button_text" name="nova_cta_settings[button_text]" value="<?php echo esc_attr($button_text); ?>" class="widefat">
                </div>

                <div class="nova-field-group">
                    <label for="nova_cta_button_url"><?php _e('Button URL:', 'nova-ctas'); ?></label>
                    <input type="url" id="nova_cta_button_url" name="nova_cta_settings[button_url]" value="<?php echo esc_url($button_url); ?>" class="widefat">
                </div>

                <div class="nova-field-group">
                    <label for="nova_cta_button_target"><?php _e('Open in:', 'nova-ctas'); ?></label>
                    <select id="nova_cta_button_target" name="nova_cta_settings[button_target]">
                        <option value="_self" <?php selected($button_target, '_self'); ?>><?php _e('Same Window', 'nova-ctas'); ?></option>
                        <option value="_blank" <?php selected($button_target, '_blank'); ?>><?php _e('New Window', 'nova-ctas'); ?></option>
                    </select>
                </div>
            </div>

            <div class="nova-tab-content" data-tab="design">
                <?php $this->render_design_tab($design); ?>
            </div>

            <div class="nova-tab-content" data-tab="display">
                <?php $this->render_display_tab($display); ?>
            </div>

            <div class="nova-tab-content" data-tab="relationships">
                <?php $this->render_relationship_settings($post); ?>
            </div>

            <?php wp_nonce_field('nova_cta_editor', 'nova_cta_editor_nonce'); ?>
        </div>
        <?php
    }

    private function render_design_tab($design) {
        // Box Design Settings
        $border_radius = isset($design['border_radius']) ? $design['border_radius'] : '0';
        $padding = array(
            'top' => isset($design['padding_top']) ? $design['padding_top'] : '30',
            'right' => isset($design['padding_right']) ? $design['padding_right'] : '30',
            'bottom' => isset($design['padding_bottom']) ? $design['padding_bottom'] : '30',
            'left' => isset($design['padding_left']) ? $design['padding_left'] : '30'
        );
        $margin = array(
            'top' => isset($design['margin_top']) ? $design['margin_top'] : '60',
            'right' => isset($design['margin_right']) ? $design['margin_right'] : '0',
            'bottom' => isset($design['margin_bottom']) ? $design['margin_bottom'] : '60',
            'left' => isset($design['margin_left']) ? $design['margin_left'] : '0'
        );
        $shadow = array(
            'x' => isset($design['shadow_x']) ? $design['shadow_x'] : '0',
            'y' => isset($design['shadow_y']) ? $design['shadow_y'] : '0',
            'blur' => isset($design['shadow_blur']) ? $design['shadow_blur'] : '0',
            'spread' => isset($design['shadow_spread']) ? $design['shadow_spread'] : '0',
            'color' => isset($design['shadow_color']) ? $design['shadow_color'] : 'rgba(0,0,0,0)'
        );

        // Background Settings
        $bg_color = isset($design['bg_color']) ? $design['bg_color'] : '#f8f9fa';
        $bg_image = isset($design['bg_image']) ? $design['bg_image'] : '';
        $bg_position = isset($design['bg_position']) ? $design['bg_position'] : 'center center';
        $bg_size = isset($design['bg_size']) ? $design['bg_size'] : 'cover';
        $overlay_color = isset($design['overlay_color']) ? $design['overlay_color'] : '';
        $overlay_opacity = isset($design['overlay_opacity']) ? $design['overlay_opacity'] : '50';
        
        // Typography settings
        $title_color = isset($design['title_color']) ? $design['title_color'] : '';
        $title_font_size = isset($design['title_font_size']) ? $design['title_font_size'] : '2rem';
        $title_font_weight = isset($design['title_font_weight']) ? $design['title_font_weight'] : '700';
        $body_color = isset($design['body_color']) ? $design['body_color'] : '';
        $body_font_size = isset($design['body_font_size']) ? $design['body_font_size'] : '1rem';
        $body_font_weight = isset($design['body_font_weight']) ? $design['body_font_weight'] : '400';
        ?>

        <!-- Box Design Section -->
        <div class="nova-design-section">
            <h3><?php _e('Box Design', 'nova-ctas'); ?></h3>
            
            <div class="nova-field-group">
                <label for="nova_cta_border_radius"><?php _e('Border Radius:', 'nova-ctas'); ?></label>
                <input type="text" id="nova_cta_border_radius" name="nova_cta_design[border_radius]" value="<?php echo esc_attr($border_radius); ?>" class="small-text"> px
            </div>

            <div class="nova-field-group">
                <h4><?php _e('Padding', 'nova-ctas'); ?></h4>
                <div class="nova-spacing-inputs">
                    <label data-position="top">
                        <?php _e('Top', 'nova-ctas'); ?>
                        <input type="number" name="nova_cta_design[padding_top]" value="<?php echo esc_attr($padding['top']); ?>" class="tiny-text">
                    </label>
                    <label data-position="right">
                        <?php _e('Right', 'nova-ctas'); ?>
                        <input type="number" name="nova_cta_design[padding_right]" value="<?php echo esc_attr($padding['right']); ?>" class="tiny-text">
                    </label>
                    <label data-position="bottom">
                        <?php _e('Bottom', 'nova-ctas'); ?>
                        <input type="number" name="nova_cta_design[padding_bottom]" value="<?php echo esc_attr($padding['bottom']); ?>" class="tiny-text">
                    </label>
                    <label data-position="left">
                        <?php _e('Left', 'nova-ctas'); ?>
                        <input type="number" name="nova_cta_design[padding_left]" value="<?php echo esc_attr($padding['left']); ?>" class="tiny-text">
                    </label>
                    <label data-position="center"></label>
                </div>
            </div>

            <div class="nova-field-group">
                <h4><?php _e('Margin', 'nova-ctas'); ?></h4>
                <div class="nova-spacing-inputs">
                    <label data-position="top">
                        <?php _e('Top', 'nova-ctas'); ?>
                        <input type="number" name="nova_cta_design[margin_top]" value="<?php echo esc_attr($margin['top']); ?>" class="tiny-text">
                    </label>
                    <label data-position="right">
                        <?php _e('Right', 'nova-ctas'); ?>
                        <input type="number" name="nova_cta_design[margin_right]" value="<?php echo esc_attr($margin['right']); ?>" class="tiny-text">
                    </label>
                    <label data-position="bottom">
                        <?php _e('Bottom', 'nova-ctas'); ?>
                        <input type="number" name="nova_cta_design[margin_bottom]" value="<?php echo esc_attr($margin['bottom']); ?>" class="tiny-text">
                    </label>
                    <label data-position="left">
                        <?php _e('Left', 'nova-ctas'); ?>
                        <input type="number" name="nova_cta_design[margin_left]" value="<?php echo esc_attr($margin['left']); ?>" class="tiny-text">
                    </label>
                    <label data-position="center"></label>
                </div>
            </div>

            <div class="nova-field-group">
                <h4><?php _e('Box Shadow', 'nova-ctas'); ?></h4>
                <div class="nova-shadow-inputs">
                    <label>
                        <?php _e('X Offset', 'nova-ctas'); ?>
                        <input type="number" name="nova_cta_design[shadow_x]" value="<?php echo esc_attr($shadow['x']); ?>" class="tiny-text">
                    </label>
                    <label>
                        <?php _e('Y Offset', 'nova-ctas'); ?>
                        <input type="number" name="nova_cta_design[shadow_y]" value="<?php echo esc_attr($shadow['y']); ?>" class="tiny-text">
                    </label>
                    <label>
                        <?php _e('Blur', 'nova-ctas'); ?>
                        <input type="number" name="nova_cta_design[shadow_blur]" value="<?php echo esc_attr($shadow['blur']); ?>" class="tiny-text">
                    </label>
                    <label>
                        <?php _e('Spread', 'nova-ctas'); ?>
                        <input type="number" name="nova_cta_design[shadow_spread]" value="<?php echo esc_attr($shadow['spread']); ?>" class="tiny-text">
                    </label>
                </div>
                <div class="nova-field-group">
                    <label for="nova_cta_shadow_color"><?php _e('Shadow Color:', 'nova-ctas'); ?></label>
                    <input type="text" id="nova_cta_shadow_color" name="nova_cta_design[shadow_color]" value="<?php echo esc_attr($shadow['color']); ?>" class="nova-color-picker">
                </div>
            </div>
        </div>

        <!-- Background Settings Section -->
        <div class="nova-design-section">
            <h3><?php _e('Background', 'nova-ctas'); ?></h3>
            
            <div class="nova-field-group">
                <label for="nova_cta_bg_color"><?php _e('Background Color:', 'nova-ctas'); ?></label>
                <input type="text" id="nova_cta_bg_color" name="nova_cta_design[bg_color]" value="<?php echo esc_attr($bg_color); ?>" class="nova-color-picker">
            </div>

            <div class="nova-field-group">
                <label for="nova_cta_bg_image"><?php _e('Background Image:', 'nova-ctas'); ?></label>
                <div class="nova-media-wrapper">
                    <input type="hidden" name="nova_cta_design[bg_image]" value="<?php echo esc_attr($bg_image); ?>">
                    <button type="button" class="button nova-media-upload"><?php _e('Choose Image', 'nova-ctas'); ?></button>
                    <button type="button" class="button nova-remove-image" <?php echo empty($bg_image) ? 'style="display:none;"' : ''; ?>>
                        <?php _e('Remove Image', 'nova-ctas'); ?>
                    </button>
                    <div class="nova-media-preview">
                        <?php if ($bg_image): ?>
                            <?php echo wp_get_attachment_image($bg_image, 'medium'); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="nova-field-group">
                <label for="nova_cta_bg_position"><?php _e('Background Position:', 'nova-ctas'); ?></label>
                <select id="nova_cta_bg_position" name="nova_cta_design[bg_position]">
                    <option value="center center" <?php selected($bg_position, 'center center'); ?>><?php _e('Center', 'nova-ctas'); ?></option>
                    <option value="top center" <?php selected($bg_position, 'top center'); ?>><?php _e('Top', 'nova-ctas'); ?></option>
                    <option value="bottom center" <?php selected($bg_position, 'bottom center'); ?>><?php _e('Bottom', 'nova-ctas'); ?></option>
                </select>
            </div>

            <div class="nova-field-group">
                <label for="nova_cta_bg_size"><?php _e('Background Size:', 'nova-ctas'); ?></label>
                <select id="nova_cta_bg_size" name="nova_cta_design[bg_size]">
                    <option value="cover" <?php selected($bg_size, 'cover'); ?>><?php _e('Cover', 'nova-ctas'); ?></option>
                    <option value="contain" <?php selected($bg_size, 'contain'); ?>><?php _e('Contain', 'nova-ctas'); ?></option>
                    <option value="auto" <?php selected($bg_size, 'auto'); ?>><?php _e('Auto', 'nova-ctas'); ?></option>
                </select>
            </div>

            <div class="nova-field-group">
                <label for="nova_cta_overlay_color"><?php _e('Overlay Color:', 'nova-ctas'); ?></label>
                <input type="text" id="nova_cta_overlay_color" name="nova_cta_design[overlay_color]" value="<?php echo esc_attr($overlay_color); ?>" class="nova-color-picker">
            </div>

            <div class="nova-field-group">
                <label for="nova_cta_overlay_opacity"><?php _e('Overlay Opacity:', 'nova-ctas'); ?></label>
                <input type="range" id="nova_cta_overlay_opacity" name="nova_cta_design[overlay_opacity]" value="<?php echo esc_attr($overlay_opacity); ?>" min="0" max="100" step="1">
                <span class="opacity-value"><?php echo esc_html($overlay_opacity); ?>%</span>
            </div>
        </div>

        <!-- Typography Section -->
        <div class="nova-design-section">
            <h3><?php _e('Typography', 'nova-ctas'); ?></h3>
            
            <div class="nova-field-group">
                <h4><?php _e('Title', 'nova-ctas'); ?></h4>
                <div class="nova-field-group">
                    <label for="nova_cta_title_color"><?php _e('Color:', 'nova-ctas'); ?></label>
                    <input type="text" id="nova_cta_title_color" name="nova_cta_design[title_color]" value="<?php echo esc_attr($title_color); ?>" class="nova-color-picker">
                </div>

                <div class="nova-field-group">
                    <label for="nova_cta_title_font_size"><?php _e('Font Size:', 'nova-ctas'); ?></label>
                    <input type="text" id="nova_cta_title_font_size" name="nova_cta_design[title_font_size]" value="<?php echo esc_attr($title_font_size); ?>" class="regular-text">
                </div>

                <div class="nova-field-group">
                    <label for="nova_cta_title_font_weight"><?php _e('Font Weight:', 'nova-ctas'); ?></label>
                    <select id="nova_cta_title_font_weight" name="nova_cta_design[title_font_weight]">
                        <option value="400" <?php selected($title_font_weight, '400'); ?>><?php _e('Normal', 'nova-ctas'); ?></option>
                        <option value="700" <?php selected($title_font_weight, '700'); ?>><?php _e('Bold', 'nova-ctas'); ?></option>
                    </select>
                </div>
            </div>

            <div class="nova-field-group">
                <h4><?php _e('Body Text', 'nova-ctas'); ?></h4>
                <div class="nova-field-group">
                    <label for="nova_cta_body_color"><?php _e('Color:', 'nova-ctas'); ?></label>
                    <input type="text" id="nova_cta_body_color" name="nova_cta_design[body_color]" value="<?php echo esc_attr($body_color); ?>" class="nova-color-picker">
                </div>

                <div class="nova-field-group">
                    <label for="nova_cta_body_font_size"><?php _e('Font Size:', 'nova-ctas'); ?></label>
                    <input type="text" id="nova_cta_body_font_size" name="nova_cta_design[body_font_size]" value="<?php echo esc_attr($body_font_size); ?>" class="regular-text">
                </div>

                <div class="nova-field-group">
                    <label for="nova_cta_body_font_weight"><?php _e('Font Weight:', 'nova-ctas'); ?></label>
                    <select id="nova_cta_body_font_weight" name="nova_cta_design[body_font_weight]">
                        <option value="400" <?php selected($body_font_weight, '400'); ?>><?php _e('Normal', 'nova-ctas'); ?></option>
                        <option value="700" <?php selected($body_font_weight, '700'); ?>><?php _e('Bold', 'nova-ctas'); ?></option>
                    </select>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_display_tab($display) {
        $position = isset($display['position']) ? $display['position'] : 'after_content';
        $conditions = isset($display['conditions']) ? $display['conditions'] : array();
        $first_position = isset($display['first_position']) ? $display['first_position'] : 30;
        $show_end = isset($display['show_end']) ? $display['show_end'] : true;
        ?>
        <div class="nova-field-group">
            <h3><?php _e('Position Settings', 'nova-ctas'); ?></h3>
            
            <div class="nova-field-group">
                <label for="nova_cta_first_position"><?php _e('First Position (% through content):', 'nova-ctas'); ?></label>
                <input type="range" id="nova_cta_first_position" name="nova_cta_display[first_position]" value="<?php echo esc_attr($first_position); ?>" min="0" max="100" step="5">
                <span class="position-value"><?php echo esc_html($first_position); ?>%</span>
            </div>

            <div class="nova-field-group">
                <label>
                    <input type="checkbox" name="nova_cta_display[show_end]" value="1" <?php checked($show_end, true); ?>>
                    <?php _e('Show at end of content', 'nova-ctas'); ?>
                </label>
            </div>
        </div>

        <div class="nova-field-group">
            <h3><?php _e('Display Conditions', 'nova-ctas'); ?></h3>
            <div class="nova-display-conditions">
                <label>
                    <input type="checkbox" name="nova_cta_display[conditions][]" value="posts" <?php checked(in_array('posts', $conditions)); ?>>
                    <?php _e('Posts', 'nova-ctas'); ?>
                </label>
                <label>
                    <input type="checkbox" name="nova_cta_display[conditions][]" value="pages" <?php checked(in_array('pages', $conditions)); ?>>
                    <?php _e('Pages', 'nova-ctas'); ?>
                </label>
                <label>
                    <input type="checkbox" name="nova_cta_display[conditions][]" value="pillar_pages" <?php checked(in_array('pillar_pages', $conditions)); ?>>
                    <?php _e('Pillar Pages', 'nova-ctas'); ?>
                </label>
            </div>
        </div>
        <?php
    }

    public function save_cta_data($post_id) {
        // Verify nonce
        if (!isset($_POST['nova_cta_editor_nonce']) || 
            !wp_verify_nonce($_POST['nova_cta_editor_nonce'], 'nova_cta_editor')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save content
        if (isset($_POST['content'])) {
            $content = wp_kses_post($_POST['content']);
            $post_data = array(
                'ID' => $post_id,
                'post_content' => $content
            );
            wp_update_post($post_data);
        }

        // Save settings (including categories and pillar page)
        if (isset($_POST['nova_cta_settings'])) {
            $settings = $_POST['nova_cta_settings'];
            
            // Initialize arrays if not set
            if (!isset($settings['display_categories'])) {
                $settings['display_categories'] = array();
            }

            // Ensure display_categories is always an array
            $settings['display_categories'] = (array)$settings['display_categories'];
            
            // Sanitize settings
            $sanitized_settings = array(
                'button_text' => isset($settings['button_text']) ? sanitize_text_field($settings['button_text']) : '',
                'button_url' => isset($settings['button_url']) ? esc_url_raw($settings['button_url']) : '',
                'button_target' => isset($settings['button_target']) ? sanitize_text_field($settings['button_target']) : '_self',
                'display_categories' => array_map('absint', $settings['display_categories']),
                'pillar_page' => isset($settings['pillar_page']) ? absint($settings['pillar_page']) : ''
            );
            
            update_post_meta($post_id, '_nova_cta_settings', $sanitized_settings);
        } else {
            // If no settings were posted, clear the categories
            $settings = get_post_meta($post_id, '_nova_cta_settings', true);
            if ($settings) {
                $settings['display_categories'] = array();
                update_post_meta($post_id, '_nova_cta_settings', $settings);
            }
        }

        // Save design settings
        if (isset($_POST['nova_cta_design'])) {
            $design = $_POST['nova_cta_design'];
            update_post_meta($post_id, '_nova_cta_design', $this->sanitize_design_settings($design));
        }

        // Save display settings
        if (isset($_POST['nova_cta_display'])) {
            $display = $_POST['nova_cta_display'];
            
            $display_settings = array(
                'first_position' => isset($display['first_position']) ? absint($display['first_position']) : 30,
                'show_end' => isset($display['show_end']) && $display['show_end'] == '1',
                'conditions' => isset($display['conditions']) ? array_map('sanitize_text_field', (array)$display['conditions']) : array()
            );
            
            update_post_meta($post_id, '_nova_cta_display', $display_settings);
        }
    }

    private function sanitize_design_settings($design) {
        // ... existing sanitize_design_settings code ...
    }

    public function add_pillar_page_meta_box() {
        add_meta_box(
            'nova_pillar_page',
            __('Pillar Page Settings', 'nova-ctas'),
            array($this, 'render_pillar_page_meta_box'),
            'page',
            'side',
            'default'
        );
    }

    public function render_pillar_page_meta_box($post) {
        wp_nonce_field('nova_pillar_page', 'nova_pillar_page_nonce');
        $is_pillar = get_post_meta($post->ID, '_is_pillar_page', true);
        ?>
        <p>
            <label>
                <input type="checkbox" name="is_pillar_page" value="1" <?php checked($is_pillar, '1'); ?>>
                <?php _e('Mark this as a pillar page', 'nova-ctas'); ?>
            </label>
        </p>
        <p class="description">
            <?php _e('Pillar pages are key content pages that CTAs can link to.', 'nova-ctas'); ?>
        </p>
        <?php
    }

    public function save_pillar_page_meta($post_id) {
        // Check if our nonce is set
        if (!isset($_POST['nova_pillar_page_nonce'])) {
            return;
        }

        // Verify the nonce
        if (!wp_verify_nonce($_POST['nova_pillar_page_nonce'], 'nova_pillar_page')) {
            return;
        }

        // If this is an autosave, our form has not been submitted, so we don't want to do anything
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check the user's permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save pillar page status
        $is_pillar = isset($_POST['is_pillar_page']) ? '1' : '0';
        update_post_meta($post_id, '_is_pillar_page', $is_pillar);
    }

    public function render_cta_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'style' => '',
            'position' => '',
        ), $atts, 'nova_cta');

        if (empty($atts['id'])) {
            return '';
        }

        $cta = get_post($atts['id']);
        if (!$cta || $cta->post_type !== 'nova_cta') {
            return '';
        }

        return $this->build_cta_html($cta, $atts);
    }

    private function build_cta_html($cta, $atts = array()) {
        $settings = get_post_meta($cta->ID, '_nova_cta_settings', true);
        $design = get_post_meta($cta->ID, '_nova_cta_design', true);
        
        // Merge shortcode attributes with saved settings
        $design = wp_parse_args($atts, $design);
        
        // Get button settings
        $button_text = isset($settings['button_text']) ? $settings['button_text'] : '';
        $button_url = isset($settings['button_url']) ? $settings['button_url'] : '';
        $button_target = isset($settings['button_target']) ? $settings['button_target'] : '_self';
        
        // Get design settings
        $bg_color = isset($design['bg_color']) ? $design['bg_color'] : '#f8f9fa';
        $text_color = isset($design['text_color']) ? $design['text_color'] : '#212529';
        $button_style = isset($design['button_style']) ? $design['button_style'] : 'default';
        $layout = isset($design['layout']) ? $design['layout'] : 'standard';
        
        // Build CSS classes
        $classes = array(
            'nova-cta',
            'nova-cta-' . $layout,
            'nova-cta-button-' . $button_style
        );
        
        // Build inline styles
        $styles = array(
            'background-color: ' . esc_attr($bg_color),
            'color: ' . esc_attr($text_color)
        );
        
        // Start building HTML
        $html = sprintf(
            '<div class="%s" style="%s">',
            esc_attr(implode(' ', $classes)),
            esc_attr(implode('; ', $styles))
        );
        
        // Add content based on layout
        switch ($layout) {
            case 'split':
                $html .= '<div class="nova-cta-content">';
                $html .= apply_filters('the_content', $cta->post_content);
                $html .= '</div>';
                $html .= '<div class="nova-cta-button">';
                if ($button_text && $button_url) {
                    $html .= sprintf(
                        '<a href="%s" target="%s" class="nova-button">%s</a>',
                        esc_url($button_url),
                        esc_attr($button_target),
                        esc_html($button_text)
                    );
                }
                $html .= '</div>';
                break;
                
            case 'centered':
                $html .= '<div class="nova-cta-content">';
                $html .= apply_filters('the_content', $cta->post_content);
                if ($button_text && $button_url) {
                    $html .= sprintf(
                        '<a href="%s" target="%s" class="nova-button">%s</a>',
                        esc_url($button_url),
                        esc_attr($button_target),
                        esc_html($button_text)
                    );
                }
                $html .= '</div>';
                break;
                
            default: // standard
                $html .= apply_filters('the_content', $cta->post_content);
                if ($button_text && $button_url) {
                    $html .= sprintf(
                        '<a href="%s" target="%s" class="nova-button">%s</a>',
                        esc_url($button_url),
                        esc_attr($button_target),
                        esc_html($button_text)
                    );
                }
                break;
        }
        
        $html .= '</div>';
        
        return $html;
    }

    public function maybe_insert_cta($content) {
        // Don't modify content in admin or if not in the main query
        if (is_admin() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        // Get post categories
        $post_categories = wp_get_post_categories(get_the_ID());
        if (empty($post_categories)) {
            return $content;
        }

        // Get all CTAs
        $ctas = get_posts(array(
            'post_type' => 'nova_cta',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));

        if (empty($ctas)) {
            return $content;
        }

        $matching_ctas = array();
        foreach ($ctas as $cta) {
            $settings = get_post_meta($cta->ID, '_nova_cta_settings', true);
            $display_categories = isset($settings['display_categories']) ? (array)$settings['display_categories'] : array();
            
            // Check if this CTA should be displayed in any of the post's categories
            $matching_categories = array_intersect($post_categories, $display_categories);
            if (!empty($matching_categories)) {
                $matching_ctas[] = $cta;
            }
        }

        if (empty($matching_ctas)) {
            return $content;
        }

        // Split content into paragraphs
        $paragraphs = explode('</p>', $content);
        $total_paragraphs = count($paragraphs);

        foreach ($matching_ctas as $cta) {
            $display = get_post_meta($cta->ID, '_nova_cta_display', true);
            
            // Get position settings
            $first_position = isset($display['first_position']) ? absint($display['first_position']) : 30;
            $show_end = isset($display['show_end']) ? (bool)$display['show_end'] : true;

            // Calculate position to insert CTA
            $insert_position = floor(($total_paragraphs * $first_position) / 100);
            
            // Ensure we don't insert too close to the end if showing at end
            if ($show_end && $insert_position > ($total_paragraphs - 3)) {
                $insert_position = floor($total_paragraphs / 2);
            }

            // Insert CTA at calculated position
            if ($insert_position > 0 && $insert_position < $total_paragraphs) {
                $paragraphs[$insert_position] .= $this->build_cta_html($cta);
            }

            // Add CTA at the end if enabled
            if ($show_end) {
                $paragraphs[] = $this->build_cta_html($cta);
            }
        }

        return implode('</p>', $paragraphs);
    }

    public function add_cta_meta_boxes() {
        // Display rules (side)
        add_meta_box(
            'nova_cta_relationships',
            __('CTA Display Rules', 'nova-ctas'),
            array($this, 'render_relationship_settings'),
            'nova_cta',
            'side',
            'default'
        );
    }

    public function render_relationship_settings($post) {
        wp_nonce_field('nova_cta_editor', 'nova_cta_editor_nonce');
        $settings = get_post_meta($post->ID, '_nova_cta_settings', true);
        $display_categories = isset($settings['display_categories']) ? (array)$settings['display_categories'] : array();
        $pillar_page = isset($settings['pillar_page']) ? $settings['pillar_page'] : '';
        ?>
        <div class="nova-field-group">
            <h3><?php _e('Display in Categories', 'nova-ctas'); ?></h3>
            <p class="description"><?php _e('Select which post categories this CTA should appear in:', 'nova-ctas'); ?></p>
            <?php
            $categories = get_categories(array('hide_empty' => false));
            if ($categories) {
                echo '<div class="nova-categories-list">';
                foreach ($categories as $category) {
                    printf(
                        '<label><input type="checkbox" name="nova_cta_settings[display_categories][]" value="%d" %s> %s</label>',
                        $category->term_id,
                        checked(in_array($category->term_id, $display_categories), true, false),
                        esc_html($category->name)
                    );
                }
                echo '</div>';
            }
            ?>
        </div>

        <div class="nova-field-group">
            <h3><?php _e('Link to Page', 'nova-ctas'); ?></h3>
            <p class="description"><?php _e('Select which page this CTA should link to:', 'nova-ctas'); ?></p>
            <?php
            $pages = get_pages(array('sort_column' => 'menu_order,post_title'));
            if ($pages) {
                echo '<select name="nova_cta_settings[pillar_page]" class="widefat">';
                echo '<option value="">' . __('Select a page...', 'nova-ctas') . '</option>';
                foreach ($pages as $page) {
                    $is_pillar = get_post_meta($page->ID, '_is_pillar_page', true);
                    $title = $page->post_title;
                    if ($is_pillar) {
                        $title .= ' ' . __('(Pillar Page)', 'nova-ctas');
                    }
                    printf(
                        '<option value="%d" %s>%s</option>',
                        $page->ID,
                        selected($pillar_page, $page->ID, false),
                        esc_html($title)
                    );
                }
                echo '</select>';
            }
            ?>
        </div>
        <?php
    }
} 
} 