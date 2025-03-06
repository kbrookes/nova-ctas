<?php
if (!defined('ABSPATH')) {
    exit;
}

class Nova_CTA_Manager {
    private $is_processing_cta = false;

    public function __construct() {
        // Register post type immediately if not already registered
        if (!post_type_exists('nova_cta')) {
            $this->register_cta_post_type();
        }

        add_action('init', array($this, 'register_cta_post_type'), 0);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('edit_form_after_title', array($this, 'render_cta_admin_page'));
        add_action('save_post_nova_cta', array($this, 'save_cta_data'));
        
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

        // Initialize processing flag
        $this->is_processing_cta = false;
    }

    public function register_shortcodes() {
        add_shortcode('nova_cta', array($this, 'render_cta_shortcode'));
    }

    public function enqueue_frontend_assets() {
        if (!is_admin()) {
            wp_enqueue_style(
                'nova-ctas-frontend',
                plugin_dir_url(dirname(__FILE__)) . 'public/css/frontend.css',
                array(),
                NOVA_CTAS_VERSION
            );
        }
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
        $button_target = isset($settings['button_target']) ? $settings['button_target'] : '_self';
        $display_categories = isset($settings['display_categories']) ? (array)$settings['display_categories'] : array();
        $pillar_page = isset($settings['pillar_page']) ? $settings['pillar_page'] : '';
        
        wp_nonce_field('nova_cta_editor', 'nova_cta_editor_nonce');
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
                <div class="nova-field-group">
                    <h3><?php _e('Display in Categories', 'nova-ctas'); ?></h3>
                    <p class="description"><?php _e('Select which post categories this CTA should appear in:', 'nova-ctas'); ?></p>
                    <?php
                    $categories = get_categories(array('hide_empty' => false));
                    if ($categories) {
                        echo '<div class="nova-categories-list">';
                        foreach ($categories as $category) {
                            printf(
                                '<label><input type="checkbox" name="nova_cta_settings[display_categories][]" value="%d" %s> %s</label><br>',
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
            </div>
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
        
        // Layout Settings
        $inline_image = isset($design['inline_image']) ? $design['inline_image'] : '';
        $image_position = isset($design['image_position']) ? $design['image_position'] : 'right';
        $content_width = isset($design['content_width']) ? $design['content_width'] : '50';
        $content_alignment = isset($design['content_alignment']) ? $design['content_alignment'] : 'left';
        $element_gap = isset($design['element_gap']) ? $design['element_gap'] : '20';
        
        // Start Design Tab Content
        ?>
        <div class="nova-design-section">
            <h3><?php _e('Layout', 'nova-ctas'); ?></h3>
            
            <div class="nova-field-group">
                <label><?php _e('Inline Image:', 'nova-ctas'); ?></label>
                <div class="nova-media-wrapper">
                    <input type="hidden" name="nova_cta_design[inline_image]" value="<?php echo esc_attr($inline_image); ?>">
                    <button type="button" class="button nova-media-upload"><?php _e('Choose Image', 'nova-ctas'); ?></button>
                    <button type="button" class="button nova-remove-image" <?php echo empty($inline_image) ? 'style="display:none;"' : ''; ?>>
                        <?php _e('Remove Image', 'nova-ctas'); ?>
                    </button>
                    <div class="nova-media-preview">
                        <?php if ($inline_image): ?>
                            <?php echo wp_get_attachment_image($inline_image, 'medium'); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="nova-field-group">
                <label><?php _e('Image Position:', 'nova-ctas'); ?></label>
                <select name="nova_cta_design[image_position]">
                    <option value="right" <?php selected($image_position, 'right'); ?>><?php _e('Right', 'nova-ctas'); ?></option>
                    <option value="left" <?php selected($image_position, 'left'); ?>><?php _e('Left', 'nova-ctas'); ?></option>
                </select>
            </div>

            <div class="nova-field-group">
                <label><?php _e('Content Width (%):', 'nova-ctas'); ?></label>
                <input type="range" name="nova_cta_design[content_width]" value="<?php echo esc_attr($content_width); ?>" min="30" max="70" step="5">
                <span class="range-value"><?php echo esc_html($content_width); ?>%</span>
            </div>

            <div class="nova-field-group">
                <label><?php _e('Content Alignment:', 'nova-ctas'); ?></label>
                <div class="nova-alignment-controls">
                    <label class="nova-alignment-option">
                        <input type="radio" name="nova_cta_design[content_alignment]" value="left" <?php checked($content_alignment, 'left'); ?>>
                        <img src="<?php echo NOVA_CTAS_PLUGIN_URL; ?>assets/icons/text-align-left.svg" alt="<?php _e('Left', 'nova-ctas'); ?>">
                    </label>
                    <label class="nova-alignment-option">
                        <input type="radio" name="nova_cta_design[content_alignment]" value="center" <?php checked($content_alignment, 'center'); ?>>
                        <img src="<?php echo NOVA_CTAS_PLUGIN_URL; ?>assets/icons/text-align-center.svg" alt="<?php _e('Center', 'nova-ctas'); ?>">
                    </label>
                    <label class="nova-alignment-option">
                        <input type="radio" name="nova_cta_design[content_alignment]" value="right" <?php checked($content_alignment, 'right'); ?>>
                        <img src="<?php echo NOVA_CTAS_PLUGIN_URL; ?>assets/icons/text-align-right.svg" alt="<?php _e('Right', 'nova-ctas'); ?>">
                    </label>
                </div>
            </div>

            <div class="nova-field-group">
                <label><?php _e('Element Gap (px):', 'nova-ctas'); ?></label>
                <input type="range" name="nova_cta_design[element_gap]" value="<?php echo esc_attr($element_gap); ?>" min="10" max="60" step="5">
                <span class="range-value"><?php echo esc_html($element_gap); ?>px</span>
            </div>
        </div>

        <!-- Original Box Design Section -->
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
        // Early return if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Early return if this is not our post type
        if (get_post_type($post_id) !== 'nova_cta') {
            return;
        }

        // Check permissions first
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Verify nonce - moved after permission check
        if (!isset($_POST['nova_cta_editor_nonce']) || 
            !wp_verify_nonce($_POST['nova_cta_editor_nonce'], 'nova_cta_editor')) {
            return;
        }

        // Save content if it exists
        if (isset($_POST['content'])) {
            remove_action('save_post_nova_cta', array($this, 'save_cta_data'));
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => wp_kses_post($_POST['content'])
            ));
            add_action('save_post_nova_cta', array($this, 'save_cta_data'));
        }

        // Save settings
        if (isset($_POST['nova_cta_settings'])) {
            $settings = $_POST['nova_cta_settings'];
            
            // Initialize display_categories as empty array if not set
            if (!isset($settings['display_categories']) || !is_array($settings['display_categories'])) {
                $settings['display_categories'] = array();
            }
            
            $sanitized_settings = array(
                'button_text' => isset($settings['button_text']) ? sanitize_text_field($settings['button_text']) : '',
                'button_target' => isset($settings['button_target']) ? sanitize_text_field($settings['button_target']) : '_self',
                'display_categories' => array_map('absint', (array)$settings['display_categories']),
                'pillar_page' => isset($settings['pillar_page']) ? absint($settings['pillar_page']) : ''
            );
            
            update_post_meta($post_id, '_nova_cta_settings', $sanitized_settings);
        }

        // Save design settings
        if (isset($_POST['nova_cta_design'])) {
            $sanitized_design = $this->sanitize_design_settings($_POST['nova_cta_design']);
            update_post_meta($post_id, '_nova_cta_design', $sanitized_design);
        }

        // Save display settings
        if (isset($_POST['nova_cta_display'])) {
            $display = $_POST['nova_cta_display'];
            
            $display_settings = array(
                'first_position' => isset($display['first_position']) ? absint($display['first_position']) : 30,
                'show_end' => isset($display['show_end']),
                'conditions' => isset($display['conditions']) && is_array($display['conditions']) 
                    ? array_map('sanitize_text_field', $display['conditions']) 
                    : array()
            );
            
            update_post_meta($post_id, '_nova_cta_display', $display_settings);
        }
    }

    private function sanitize_design_settings($design) {
        $sanitized = array();
        
        // Box Design Settings
        $sanitized['border_radius'] = isset($design['border_radius']) ? sanitize_text_field($design['border_radius']) : '0';
        
        // Padding
        $sanitized['padding_top'] = isset($design['padding_top']) ? absint($design['padding_top']) : 30;
        $sanitized['padding_right'] = isset($design['padding_right']) ? absint($design['padding_right']) : 30;
        $sanitized['padding_bottom'] = isset($design['padding_bottom']) ? absint($design['padding_bottom']) : 30;
        $sanitized['padding_left'] = isset($design['padding_left']) ? absint($design['padding_left']) : 30;
        
        // Margin
        $sanitized['margin_top'] = isset($design['margin_top']) ? absint($design['margin_top']) : 60;
        $sanitized['margin_right'] = isset($design['margin_right']) ? absint($design['margin_right']) : 0;
        $sanitized['margin_bottom'] = isset($design['margin_bottom']) ? absint($design['margin_bottom']) : 60;
        $sanitized['margin_left'] = isset($design['margin_left']) ? absint($design['margin_left']) : 0;
        
        // Shadow
        $sanitized['shadow_x'] = isset($design['shadow_x']) ? absint($design['shadow_x']) : 0;
        $sanitized['shadow_y'] = isset($design['shadow_y']) ? absint($design['shadow_y']) : 0;
        $sanitized['shadow_blur'] = isset($design['shadow_blur']) ? absint($design['shadow_blur']) : 0;
        $sanitized['shadow_spread'] = isset($design['shadow_spread']) ? absint($design['shadow_spread']) : 0;
        $sanitized['shadow_color'] = isset($design['shadow_color']) ? sanitize_text_field($design['shadow_color']) : 'rgba(0,0,0,0.1)';
        
        // Background Settings
        $sanitized['bg_color'] = isset($design['bg_color']) ? sanitize_text_field($design['bg_color']) : '#f8f9fa';
        $sanitized['bg_image'] = isset($design['bg_image']) ? absint($design['bg_image']) : '';
        $sanitized['bg_position'] = isset($design['bg_position']) ? sanitize_text_field($design['bg_position']) : 'center center';
        $sanitized['bg_size'] = isset($design['bg_size']) ? sanitize_text_field($design['bg_size']) : 'cover';
        $sanitized['overlay_color'] = isset($design['overlay_color']) ? sanitize_text_field($design['overlay_color']) : '';
        $sanitized['overlay_opacity'] = isset($design['overlay_opacity']) ? absint($design['overlay_opacity']) : 50;
        
        // Typography Settings
        $sanitized['title_color'] = isset($design['title_color']) ? sanitize_text_field($design['title_color']) : '';
        $sanitized['title_font_size'] = isset($design['title_font_size']) ? sanitize_text_field($design['title_font_size']) : '2rem';
        $sanitized['title_font_weight'] = isset($design['title_font_weight']) ? sanitize_text_field($design['title_font_weight']) : '700';
        $sanitized['body_color'] = isset($design['body_color']) ? sanitize_text_field($design['body_color']) : '';
        $sanitized['body_font_size'] = isset($design['body_font_size']) ? sanitize_text_field($design['body_font_size']) : '1rem';
        $sanitized['body_font_weight'] = isset($design['body_font_weight']) ? sanitize_text_field($design['body_font_weight']) : '400';
        
        return $sanitized;
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
        try {
            error_log('Nova CTAs: Starting to build CTA HTML for ID: ' . $cta->ID);

            // Validate CTA object
            if (!$cta || !is_object($cta) || $cta->post_type !== 'nova_cta') {
                error_log('Nova CTAs: Invalid CTA object');
                return '';
            }

            // Get and validate settings
            $settings = get_post_meta($cta->ID, '_nova_cta_settings', true);
            if (!is_array($settings)) {
                error_log('Nova CTAs: Invalid settings for CTA ID: ' . $cta->ID);
                $settings = array();
            }

            // Get and validate design settings
            $design = get_post_meta($cta->ID, '_nova_cta_design', true);
            if (!is_array($design)) {
                error_log('Nova CTAs: Invalid design settings for CTA ID: ' . $cta->ID);
                $design = array();
            }
            
            // Merge shortcode attributes with saved settings
            $design = wp_parse_args($atts, $design);
            
            // Get button settings with defaults
            $button_text = isset($settings['button_text']) ? $settings['button_text'] : '';
            
            // Always get URL from pillar page
            $button_url = '';
            if (!empty($settings['pillar_page'])) {
                $button_url = get_permalink($settings['pillar_page']);
                error_log('Nova CTAs: Using pillar page URL: ' . $button_url);
            }
            
            if (empty($button_url)) {
                $button_url = '#';
                error_log('Nova CTAs: No pillar page set, using fallback: #');
            }
            
            $button_target = isset($settings['button_target']) ? $settings['button_target'] : '_self';
            
            // Layout settings
            $inline_image = isset($design['inline_image']) ? $design['inline_image'] : '';
            $image_position = isset($design['image_position']) ? $design['image_position'] : 'right';
            $content_width = isset($design['content_width']) ? absint($design['content_width']) : 50;
            $content_alignment = isset($design['content_alignment']) ? $design['content_alignment'] : 'left';
            $element_gap = isset($design['element_gap']) ? absint($design['element_gap']) : 20;
            
            // Build CSS classes
            $classes = array(
                'nova-cta',
                'nova-cta-' . $cta->ID,
                'nova-cta-layout-' . ($inline_image ? 'with-image' : 'text-only'),
                'nova-cta-image-' . $image_position,
                'nova-cta-align-' . $content_alignment
            );

            // Build inline styles array for main container
            $styles = array();
            
            // Background styles
            if (!empty($design['bg_color'])) {
                $styles[] = 'background-color: ' . esc_attr($design['bg_color']);
            }
            
            // Background image with validation
            if (!empty($design['bg_image'])) {
                $bg_url = wp_get_attachment_url($design['bg_image']);
                if ($bg_url) {
                    $styles[] = 'background-image: url(' . esc_url($bg_url) . ')';
                    $styles[] = 'background-position: ' . esc_attr($design['bg_position'] ?? 'center center');
                    $styles[] = 'background-size: ' . esc_attr($design['bg_size'] ?? 'cover');
                }
            }

            // Box design with validation
            if (!empty($design['border_radius'])) {
                $styles[] = 'border-radius: ' . absint($design['border_radius']) . 'px';
            }
            
            // Padding with validation
            $padding = array(
                'top' => isset($design['padding_top']) ? absint($design['padding_top']) : 30,
                'right' => isset($design['padding_right']) ? absint($design['padding_right']) : 30,
                'bottom' => isset($design['padding_bottom']) ? absint($design['padding_bottom']) : 30,
                'left' => isset($design['padding_left']) ? absint($design['padding_left']) : 30
            );
            $styles[] = sprintf('padding: %dpx %dpx %dpx %dpx', 
                $padding['top'], 
                $padding['right'], 
                $padding['bottom'], 
                $padding['left']
            );

            // Build HTML with proper escaping
            $html = sprintf(
                '<div class="%s" style="%s">',
                esc_attr(implode(' ', array_filter($classes))),
                esc_attr(implode('; ', array_filter($styles)))
            );

            // Start flex container for image and content
            $html .= '<div class="nova-cta-container">';
            
            // Content wrapper with width and alignment
            $content_styles = array(
                'width: ' . $content_width . '%',
                'text-align: ' . $content_alignment,
                '--element-gap: ' . $element_gap . 'px'
            );
            
            if (!empty($design['body_color'])) {
                $content_styles[] = 'color: ' . esc_attr($design['body_color']);
            }
            
            $html .= sprintf(
                '<div class="nova-cta-content" style="%s">',
                esc_attr(implode('; ', $content_styles))
            );

            // Add title with typography settings
            $title_styles = array();
            if (!empty($design['title_color'])) {
                $title_styles[] = 'color: ' . esc_attr($design['title_color']);
            }
            if (!empty($design['title_font_size'])) {
                $title_styles[] = 'font-size: ' . esc_attr($design['title_font_size']);
            }
            if (!empty($design['title_font_weight'])) {
                $title_styles[] = 'font-weight: ' . esc_attr($design['title_font_weight']);
            }

            $html .= sprintf(
                '<h3 class="nova-cta-title" style="%s">%s</h3>',
                esc_attr(implode('; ', array_filter($title_styles))),
                esc_html($cta->post_title)
            );
            
            // Add content with proper filtering
            $content = $cta->post_content;
            if (!empty($content)) {
                $allowed_html = array(
                    'p' => array(),
                    'br' => array(),
                    'strong' => array(),
                    'em' => array(),
                    'span' => array('class' => array()),
                    'h1' => array(),
                    'h2' => array(),
                    'h3' => array(),
                    'h4' => array(),
                    'h5' => array(),
                    'h6' => array(),
                    'ul' => array(),
                    'ol' => array(),
                    'li' => array(),
                    'a' => array(
                        'href' => array(),
                        'title' => array(),
                        'target' => array(),
                        'class' => array()
                    )
                );
                $html .= sprintf(
                    '<div class="nova-cta-text">%s</div>',
                    wp_kses($content, $allowed_html)
                );
            }

            // Add button if we have text
            if (!empty($button_text)) {
                $html .= sprintf(
                    '<div class="nova-cta-button-wrapper"><a href="%s" target="%s" class="nova-button">%s</a></div>',
                    esc_url($button_url),
                    esc_attr($button_target),
                    esc_html($button_text)
                );
            }

            $html .= '</div>'; // Close content wrapper

            // Add inline image if set
            if (!empty($inline_image)) {
                $image_styles = array(
                    'width: ' . (100 - $content_width) . '%'
                );
                
                $html .= sprintf(
                    '<div class="nova-cta-image" style="%s">%s</div>',
                    esc_attr(implode('; ', $image_styles)),
                    wp_get_attachment_image($inline_image, 'large')
                );
            }

            $html .= '</div>'; // Close flex container
            $html .= '</div>'; // Close main CTA wrapper

            error_log('Nova CTAs: Successfully built CTA HTML');
            return $html;
            
        } catch (Exception $e) {
            error_log('Nova CTAs Error in build_cta_html: ' . $e->getMessage());
            error_log('Nova CTAs Stack Trace: ' . $e->getTraceAsString());
            return '';
        }
    }

    public function maybe_insert_cta($content) {
        // Prevent recursive calls
        if ($this->is_processing_cta) {
            return $content;
        }

        try {
            $this->is_processing_cta = true;

            // Don't modify content in admin or if not in the main query
            if (is_admin() || !in_the_loop() || !is_main_query()) {
                $this->is_processing_cta = false;
                return $content;
            }

            // Get post categories
            $post_id = get_the_ID();
            if (!$post_id) {
                $this->is_processing_cta = false;
                return $content;
            }

            $post_categories = wp_get_post_categories($post_id);
            if (empty($post_categories)) {
                $this->is_processing_cta = false;
                return $content;
            }

            // Get all CTAs
            $ctas = get_posts(array(
                'post_type' => 'nova_cta',
                'posts_per_page' => -1,
                'post_status' => 'publish'
            ));

            if (empty($ctas)) {
                $this->is_processing_cta = false;
                return $content;
            }

            // Split content into paragraphs and clean up
            $paragraphs = preg_split('/<\/p>/', $content);
            $paragraphs = array_map('trim', $paragraphs);
            $paragraphs = array_filter($paragraphs, function($p) {
                return !empty($p);
            });
            
            // Reset array keys to ensure sequential numbering
            $paragraphs = array_values($paragraphs);
            $total_paragraphs = count($paragraphs);

            if ($total_paragraphs === 0) {
                $this->is_processing_cta = false;
                return $content;
            }

            $modified_content = '';
            $inserted_ctas = array();

            // Process each CTA and determine insertion points
            foreach ($ctas as $cta) {
                $settings = get_post_meta($cta->ID, '_nova_cta_settings', true);
                if (!is_array($settings) || empty($settings['display_categories'])) {
                    continue;
                }

                $display_categories = (array)$settings['display_categories'];
                if (!array_intersect($post_categories, $display_categories)) {
                    continue;
                }

                $display = get_post_meta($cta->ID, '_nova_cta_display', true);
                $display = is_array($display) ? $display : array();
                
                $first_position = isset($display['first_position']) ? absint($display['first_position']) : 30;
                $show_end = isset($display['show_end']) ? (bool)$display['show_end'] : true;

                // Calculate insert position (ensure it's within bounds)
                $insert_position = min($total_paragraphs - 1, max(1, floor(($total_paragraphs * $first_position) / 100)));
                
                $cta_html = $this->build_cta_html($cta);
                if (empty($cta_html)) {
                    continue;
                }

                // Store CTAs for insertion
                if (!isset($inserted_ctas[$insert_position])) {
                    $inserted_ctas[$insert_position] = array();
                }
                $inserted_ctas[$insert_position][] = $cta_html;

                // Add to end if enabled
                if ($show_end) {
                    if (!isset($inserted_ctas[$total_paragraphs])) {
                        $inserted_ctas[$total_paragraphs] = array();
                    }
                    $inserted_ctas[$total_paragraphs][] = $cta_html;
                }
            }

            // Rebuild content with CTAs
            for ($i = 0; $i < $total_paragraphs; $i++) {
                // Add paragraph content if it exists
                if (isset($paragraphs[$i]) && !empty($paragraphs[$i])) {
                    $modified_content .= $paragraphs[$i];
                    if (!preg_match('/<\/p>$/', $paragraphs[$i])) {
                        $modified_content .= '</p>';
                    }
                }

                // Add any CTAs that should be inserted at this position
                if (isset($inserted_ctas[$i])) {
                    foreach ($inserted_ctas[$i] as $cta_html) {
                        $modified_content .= $cta_html;
                    }
                }
            }

            // Add any remaining CTAs that should appear at the end
            if (isset($inserted_ctas[$total_paragraphs])) {
                foreach ($inserted_ctas[$total_paragraphs] as $cta_html) {
                    $modified_content .= $cta_html;
                }
            }

            $this->is_processing_cta = false;
            return $modified_content;

        } catch (Exception $e) {
            error_log('Nova CTAs Critical Error: ' . $e->getMessage());
            error_log('Nova CTAs Stack Trace: ' . $e->getTraceAsString());
            $this->is_processing_cta = false;
            return $content;
        }
    }

    public function render_relationship_settings($post) {
        // This function is no longer needed as we've moved the settings into the main editor
        return;
    }

    public function output_dynamic_styles() {
        // Only output styles if we're not in admin
        if (is_admin()) {
            return;
        }
        
        // Get all published CTAs
        $ctas = get_posts(array(
            'post_type' => 'nova_cta',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        if (empty($ctas)) {
            return;
        }
        
        $styles = '<style id="nova-ctas-dynamic-styles">';
        
        foreach ($ctas as $cta) {
            $design = get_post_meta($cta->ID, '_nova_cta_design', true);
            if (!is_array($design)) {
                continue;
            }
            
            $cta_id = $cta->ID;
            
            // Base CTA container styles
            $styles .= ".nova-cta-{$cta_id} {";
            
            // Background styles
            if (!empty($design['bg_color'])) {
                $styles .= "background-color: " . esc_attr($design['bg_color']) . ";";
            }
            
            if (!empty($design['bg_image'])) {
                $bg_url = wp_get_attachment_url($design['bg_image']);
                if ($bg_url) {
                    $styles .= "background-image: url(" . esc_url($bg_url) . ");";
                    $styles .= "background-position: " . esc_attr($design['bg_position'] ?? 'center center') . ";";
                    $styles .= "background-size: " . esc_attr($design['bg_size'] ?? 'cover') . ";";
                }
            }
            
            // Box design
            if (!empty($design['border_radius'])) {
                $styles .= "border-radius: " . esc_attr($design['border_radius']) . "px;";
            }
            
            // Padding
            $padding = array(
                'top' => isset($design['padding_top']) ? absint($design['padding_top']) : 30,
                'right' => isset($design['padding_right']) ? absint($design['padding_right']) : 30,
                'bottom' => isset($design['padding_bottom']) ? absint($design['padding_bottom']) : 30,
                'left' => isset($design['padding_left']) ? absint($design['padding_left']) : 30
            );
            $styles .= sprintf('padding: %dpx %dpx %dpx %dpx;', 
                $padding['top'], 
                $padding['right'], 
                $padding['bottom'], 
                $padding['left']
            );
            
            // Margin
            $margin = array(
                'top' => isset($design['margin_top']) ? absint($design['margin_top']) : 60,
                'right' => isset($design['margin_right']) ? absint($design['margin_right']) : 0,
                'bottom' => isset($design['margin_bottom']) ? absint($design['margin_bottom']) : 60,
                'left' => isset($design['margin_left']) ? absint($design['margin_left']) : 0
            );
            $styles .= sprintf('margin: %dpx %dpx %dpx %dpx;', 
                $margin['top'], 
                $margin['right'], 
                $margin['bottom'], 
                $margin['left']
            );
            
            // Box shadow
            if (!empty($design['shadow_x']) || !empty($design['shadow_y']) || !empty($design['shadow_blur']) || !empty($design['shadow_spread'])) {
                $shadow = sprintf('box-shadow: %dpx %dpx %dpx %dpx %s;',
                    absint($design['shadow_x'] ?? 0),
                    absint($design['shadow_y'] ?? 0),
                    absint($design['shadow_blur'] ?? 0),
                    absint($design['shadow_spread'] ?? 0),
                    esc_attr($design['shadow_color'] ?? 'rgba(0,0,0,0.1)')
                );
                $styles .= $shadow;
            }
            
            $styles .= "}";
            
            // Title styles
            $styles .= ".nova-cta-{$cta_id} .nova-cta-title {";
            if (!empty($design['title_color'])) {
                $styles .= "color: " . esc_attr($design['title_color']) . ";";
            }
            if (!empty($design['title_font_size'])) {
                $styles .= "font-size: " . esc_attr($design['title_font_size']) . ";";
            }
            if (!empty($design['title_font_weight'])) {
                $styles .= "font-weight: " . esc_attr($design['title_font_weight']) . ";";
            }
            $styles .= "}";
            
            // Content styles
            $styles .= ".nova-cta-{$cta_id} .nova-cta-content {";
            if (!empty($design['body_color'])) {
                $styles .= "color: " . esc_attr($design['body_color']) . ";";
            }
            if (!empty($design['body_font_size'])) {
                $styles .= "font-size: " . esc_attr($design['body_font_size']) . ";";
            }
            if (!empty($design['body_font_weight'])) {
                $styles .= "font-weight: " . esc_attr($design['body_font_weight']) . ";";
            }
            $styles .= "}";
            
            // Button styles
            if (!empty($design['button_bg_color'])) {
                $styles .= ".nova-cta-{$cta_id} .nova-button {";
                $styles .= "background-color: " . esc_attr($design['button_bg_color']) . ";";
                if (!empty($design['button_text_color'])) {
                    $styles .= "color: " . esc_attr($design['button_text_color']) . ";";
                }
                $styles .= "}";
                
                // Button hover state
                $styles .= ".nova-cta-{$cta_id} .nova-button:hover {";
                $styles .= "background-color: " . esc_attr($this->adjust_brightness($design['button_bg_color'], -20)) . ";";
                if (!empty($design['button_text_color'])) {
                    $styles .= "color: " . esc_attr($design['button_text_color']) . ";";
                }
                $styles .= "}";
            }
            
            // Button style variations
            if (!empty($design['button_style'])) {
                if ($design['button_style'] === 'outline') {
                    $styles .= ".nova-cta-{$cta_id} .nova-button {";
                    $styles .= "background-color: transparent;";
                    $styles .= "border: 2px solid " . esc_attr($design['button_bg_color']) . ";";
                    $styles .= "color: " . esc_attr($design['button_bg_color']) . ";";
                    $styles .= "}";
                    
                    $styles .= ".nova-cta-{$cta_id} .nova-button:hover {";
                    $styles .= "background-color: " . esc_attr($design['button_bg_color']) . ";";
                    $styles .= "color: " . esc_attr($design['button_text_color']) . ";";
                    $styles .= "}";
                } elseif ($design['button_style'] === 'ghost') {
                    $styles .= ".nova-cta-{$cta_id} .nova-button {";
                    $styles .= "background-color: transparent;";
                    $styles .= "border: none;";
                    $styles .= "color: " . esc_attr($design['button_bg_color']) . ";";
                    $styles .= "padding: 0.5rem 0;";
                    $styles .= "position: relative;";
                    $styles .= "}";
                    
                    $styles .= ".nova-cta-{$cta_id} .nova-button::after {";
                    $styles .= "content: '';";
                    $styles .= "position: absolute;";
                    $styles .= "bottom: 0;";
                    $styles .= "left: 0;";
                    $styles .= "width: 100%;";
                    $styles .= "height: 2px;";
                    $styles .= "background-color: " . esc_attr($design['button_bg_color']) . ";";
                    $styles .= "transform: scaleX(0);";
                    $styles .= "transition: transform 0.2s ease;";
                    $styles .= "}";
                    
                    $styles .= ".nova-cta-{$cta_id} .nova-button:hover::after {";
                    $styles .= "transform: scaleX(1);";
                    $styles .= "}";
                }
            }
            
            // Responsive styles
            $styles .= "@media (max-width: 768px) {";
            $styles .= ".nova-cta-{$cta_id} {";
            $styles .= "padding: " . absint($padding['top']/2) . "px " . absint($padding['right']/2) . "px " . 
                      absint($padding['bottom']/2) . "px " . absint($padding['left']/2) . "px;";
            $styles .= "margin: " . absint($margin['top']/2) . "px " . absint($margin['right']/2) . "px " . 
                      absint($margin['bottom']/2) . "px " . absint($margin['left']/2) . "px;";
            $styles .= "}";
            
            if (!empty($design['title_font_size'])) {
                $styles .= ".nova-cta-{$cta_id} .nova-cta-title {";
                $styles .= "font-size: calc(" . esc_attr($design['title_font_size']) . " * 0.85);";
                $styles .= "}";
            }
            
            if (!empty($design['body_font_size'])) {
                $styles .= ".nova-cta-{$cta_id} .nova-cta-content {";
                $styles .= "font-size: calc(" . esc_attr($design['body_font_size']) . " * 0.9);";
                $styles .= "}";
            }
            $styles .= "}";
        }
        
        $styles .= '</style>';
        echo $styles;
    }
    
    private function adjust_brightness($hex, $steps) {
        // Convert hex to rgb
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        // Adjust brightness
        $r = max(0, min(255, $r + $steps));
        $g = max(0, min(255, $g + $steps));
        $b = max(0, min(255, $b + $steps));
        
        // Convert back to hex
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
} 