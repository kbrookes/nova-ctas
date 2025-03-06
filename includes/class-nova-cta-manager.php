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
        add_action('admin_menu', array($this, 'add_cta_admin_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
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

    public function add_cta_admin_page() {
        add_submenu_page(
            'edit.php?post_type=nova_cta',
            __('Edit CTA', 'nova-ctas'),
            __('Edit CTA', 'nova-ctas'),
            'edit_posts',
            'edit-nova-cta',
            array($this, 'render_cta_admin_page')
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook === 'nova_cta_page_edit-nova-cta' || 
            ($hook === 'post.php' && get_post_type() === 'nova_cta') ||
            ($hook === 'post-new.php' && get_post_type() === 'nova_cta')) {
            
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

    public function render_cta_admin_page() {
        $post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;
        $post = get_post($post_id);
        
        $settings = get_post_meta($post_id, '_nova_cta_settings', true);
        $design = get_post_meta($post_id, '_nova_cta_design', true);
        $display = get_post_meta($post_id, '_nova_cta_display', true);
        
        // Default values
        $heading = $post ? $post->post_title : '';
        $content = $post ? $post->post_content : '';
        $button_text = isset($settings['button_text']) ? $settings['button_text'] : '';
        $button_url = isset($settings['button_url']) ? $settings['button_url'] : '';
        $button_target = isset($settings['button_target']) ? $settings['button_target'] : '_self';
        
        ?>
        <div class="wrap nova-cta-editor">
            <h1><?php echo $post_id ? __('Edit CTA', 'nova-ctas') : __('Add New CTA', 'nova-ctas'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('nova_cta_editor', 'nova_cta_editor_nonce'); ?>
                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                
                <div class="nova-tabs">
                    <button type="button" class="nova-tab-button active" data-tab="content"><?php _e('Content', 'nova-ctas'); ?></button>
                    <button type="button" class="nova-tab-button" data-tab="design"><?php _e('Design', 'nova-ctas'); ?></button>
                    <button type="button" class="nova-tab-button" data-tab="display"><?php _e('Display', 'nova-ctas'); ?></button>
                </div>

                <div class="nova-tab-content active" data-tab="content">
                    <div class="nova-field-group">
                        <label for="nova_cta_heading"><?php _e('Heading:', 'nova-ctas'); ?></label>
                        <input type="text" id="nova_cta_heading" name="post_title" value="<?php echo esc_attr($heading); ?>" class="widefat">
                    </div>
                    
                    <div class="nova-field-group">
                        <label for="nova_cta_content"><?php _e('Content:', 'nova-ctas'); ?></label>
                        <?php 
                        wp_editor($content, 'nova_cta_content', array(
                            'textarea_name' => 'post_content',
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

                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save CTA', 'nova-ctas'); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    private function render_design_tab($design) {
        $bg_color = isset($design['bg_color']) ? $design['bg_color'] : '#f8f9fa';
        $text_color = isset($design['text_color']) ? $design['text_color'] : '#212529';
        $button_style = isset($design['button_style']) ? $design['button_style'] : 'default';
        $layout = isset($design['layout']) ? $design['layout'] : 'standard';
        ?>
        <div class="nova-field-group">
            <label for="nova_cta_bg_color"><?php _e('Background Color:', 'nova-ctas'); ?></label>
            <input type="text" id="nova_cta_bg_color" name="nova_cta_design[bg_color]" value="<?php echo esc_attr($bg_color); ?>" class="nova-color-picker">
        </div>

        <div class="nova-field-group">
            <label for="nova_cta_text_color"><?php _e('Text Color:', 'nova-ctas'); ?></label>
            <input type="text" id="nova_cta_text_color" name="nova_cta_design[text_color]" value="<?php echo esc_attr($text_color); ?>" class="nova-color-picker">
        </div>

        <div class="nova-field-group">
            <label for="nova_cta_button_style"><?php _e('Button Style:', 'nova-ctas'); ?></label>
            <select id="nova_cta_button_style" name="nova_cta_design[button_style]">
                <option value="default" <?php selected($button_style, 'default'); ?>><?php _e('Default', 'nova-ctas'); ?></option>
                <option value="outline" <?php selected($button_style, 'outline'); ?>><?php _e('Outline', 'nova-ctas'); ?></option>
                <option value="minimal" <?php selected($button_style, 'minimal'); ?>><?php _e('Minimal', 'nova-ctas'); ?></option>
            </select>
        </div>

        <div class="nova-field-group">
            <label for="nova_cta_layout"><?php _e('Layout:', 'nova-ctas'); ?></label>
            <select id="nova_cta_layout" name="nova_cta_design[layout]">
                <option value="standard" <?php selected($layout, 'standard'); ?>><?php _e('Standard', 'nova-ctas'); ?></option>
                <option value="centered" <?php selected($layout, 'centered'); ?>><?php _e('Centered', 'nova-ctas'); ?></option>
                <option value="split" <?php selected($layout, 'split'); ?>><?php _e('Split', 'nova-ctas'); ?></option>
            </select>
        </div>
        <?php
    }

    private function render_display_tab($display) {
        $position = isset($display['position']) ? $display['position'] : 'after_content';
        $conditions = isset($display['conditions']) ? $display['conditions'] : array();
        ?>
        <div class="nova-field-group">
            <label for="nova_cta_position"><?php _e('Display Position:', 'nova-ctas'); ?></label>
            <select id="nova_cta_position" name="nova_cta_display[position]">
                <option value="before_content" <?php selected($position, 'before_content'); ?>><?php _e('Before Content', 'nova-ctas'); ?></option>
                <option value="after_content" <?php selected($position, 'after_content'); ?>><?php _e('After Content', 'nova-ctas'); ?></option>
                <option value="after_first_paragraph" <?php selected($position, 'after_first_paragraph'); ?>><?php _e('After First Paragraph', 'nova-ctas'); ?></option>
            </select>
        </div>

        <div class="nova-field-group">
            <label><?php _e('Display Conditions:', 'nova-ctas'); ?></label>
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

    public function save_cta_data() {
        if (!isset($_POST['nova_cta_editor_nonce']) || 
            !wp_verify_nonce($_POST['nova_cta_editor_nonce'], 'nova_cta_editor')) {
            return;
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        // Create or update post
        $post_data = array(
            'post_title' => sanitize_text_field($_POST['post_title']),
            'post_content' => wp_kses_post($_POST['post_content']),
            'post_type' => 'nova_cta',
            'post_status' => 'publish'
        );

        if ($post_id) {
            $post_data['ID'] = $post_id;
            $post_id = wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data);
        }

        if ($post_id) {
            // Save settings
            if (isset($_POST['nova_cta_settings'])) {
                update_post_meta($post_id, '_nova_cta_settings', $_POST['nova_cta_settings']);
            }

            // Save design
            if (isset($_POST['nova_cta_design'])) {
                update_post_meta($post_id, '_nova_cta_design', $_POST['nova_cta_design']);
            }

            // Save display
            if (isset($_POST['nova_cta_display'])) {
                update_post_meta($post_id, '_nova_cta_display', $_POST['nova_cta_display']);
            }

            wp_redirect(admin_url('edit.php?post_type=nova_cta&page=edit-nova-cta&post=' . $post_id . '&updated=true'));
            exit;
        }
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
        $related_ctas = get_post_meta($post->ID, '_related_ctas', true);
        ?>
        <p>
            <label>
                <input type="checkbox" name="is_pillar_page" value="1" <?php checked($is_pillar, '1'); ?>>
                <?php _e('This is a pillar page', 'nova-ctas'); ?>
            </label>
        </p>
        <p>
            <label for="related_ctas"><?php _e('Related CTAs:', 'nova-ctas'); ?></label>
            <?php
            $ctas = get_posts(array(
                'post_type' => 'nova_cta',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC'
            ));

            if ($ctas) {
                echo '<select name="related_ctas[]" id="related_ctas" multiple style="width: 100%;">';
                foreach ($ctas as $cta) {
                    $selected = is_array($related_ctas) && in_array($cta->ID, $related_ctas) ? 'selected' : '';
                    echo '<option value="' . esc_attr($cta->ID) . '" ' . $selected . '>' . esc_html($cta->post_title) . '</option>';
                }
                echo '</select>';
            } else {
                _e('No CTAs available', 'nova-ctas');
            }
            ?>
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

        // Save related CTAs
        $related_ctas = isset($_POST['related_ctas']) ? array_map('intval', $_POST['related_ctas']) : array();
        update_post_meta($post_id, '_related_ctas', $related_ctas);
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
        // Only process main content of singular pages
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $post_id = get_the_ID();
        $post_type = get_post_type();

        // Get display settings
        $options = get_option('nova_ctas_settings', array());
        $auto_insert = isset($options['auto_insert']) ? $options['auto_insert'] : 'disabled';

        if ($auto_insert === 'disabled') {
            return $content;
        }

        // Get all CTAs that should be displayed
        $ctas = $this->get_ctas_for_post($post_id, $post_type);
        if (empty($ctas)) {
            return $content;
        }

        // Process each CTA
        foreach ($ctas as $cta) {
            $display = get_post_meta($cta->ID, '_nova_cta_display', true);
            $position = isset($display['position']) ? $display['position'] : 'after_content';

            $cta_html = $this->build_cta_html($cta);

            switch ($position) {
                case 'before_content':
                    $content = $cta_html . $content;
                    break;

                case 'after_first_paragraph':
                    $pos = strpos($content, '</p>');
                    if ($pos !== false) {
                        $content = substr_replace($content, '</p>' . $cta_html, $pos, 4);
                    } else {
                        $content .= $cta_html;
                    }
                    break;

                case 'after_content':
                default:
                    $content .= $cta_html;
                    break;
            }
        }

        return $content;
    }

    private function get_ctas_for_post($post_id, $post_type) {
        $ctas = array();

        // Check if this is a pillar page
        $is_pillar = get_post_meta($post_id, '_is_pillar_page', true);
        if ($is_pillar) {
            $related_ctas = get_post_meta($post_id, '_related_ctas', true);
            if (!empty($related_ctas)) {
                $ctas = get_posts(array(
                    'post_type' => 'nova_cta',
                    'post__in' => $related_ctas,
                    'posts_per_page' => -1,
                    'orderby' => 'menu_order',
                    'order' => 'ASC'
                ));
            }
        } else {
            // Get CTAs based on display conditions
            $args = array(
                'post_type' => 'nova_cta',
                'posts_per_page' => -1,
                'orderby' => 'menu_order',
                'order' => 'ASC',
                'meta_query' => array(
                    array(
                        'key' => '_nova_cta_display',
                        'value' => $post_type,
                        'compare' => 'LIKE'
                    )
                )
            );
            $ctas = get_posts($args);
        }

        return $ctas;
    }
} 