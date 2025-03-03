<?php
if (!defined('ABSPATH')) {
    exit;
}

class IBCTA_Admin {
    private $settings_page = 'ibcta-settings';
    private $option_group = 'ibcta_options';
    private $option_name = 'ibcta_settings';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Add settings link to plugins page
        $plugin_basename = plugin_basename(IBCTA_PLUGIN_DIR . 'internal-blog-cta.php');
        add_filter('plugin_action_links_' . $plugin_basename, array($this, 'add_settings_link'));
    }

    public function enqueue_admin_assets() {
        // Enqueue editor assets
        if (get_current_screen()->is_block_editor()) {
            wp_enqueue_script(
                'ibcta-blocks',
                IBCTA_PLUGIN_URL . 'assets/js/blocks/related-posts.js',
                array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
                IBCTA_VERSION,
                true
            );

            wp_enqueue_style(
                'ibcta-editor-styles',
                IBCTA_PLUGIN_URL . 'assets/css/related-posts.css',
                array(),
                IBCTA_VERSION
            );
        }
    }

    public function register_settings() {
        register_setting(
            $this->option_group,
            $this->option_name,
            array(
                'sanitize_callback' => array($this, 'sanitize_settings')
            )
        );
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('edit.php?post_type=ibcta&page=' . $this->settings_page) . '">' . __('Settings', 'internal-blog-cta') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function add_settings_page() {
        // Add settings as submenu under CTAs
        add_submenu_page(
            'edit.php?post_type=ibcta',
            __('Global Settings', 'internal-blog-cta'),
            __('Global Settings', 'internal-blog-cta'),
            'edit_posts',  // Changed from 'manage_options' to 'edit_posts'
            $this->settings_page,
            array($this, 'render_settings_page')
        );
    }

    public function render_settings_page() {
        if (!current_user_can('edit_posts')) {  // Changed from 'manage_options' to 'edit_posts'
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields($this->option_group);
                do_settings_sections($this->settings_page);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_cta_section() {
        echo '<p>' . esc_html__('Configure the default settings for your CTAs.', 'internal-blog-cta') . '</p>';
    }

    public function render_text_field($args) {
        $options = get_option($this->option_name);
        $field = $args['field'];
        $value = isset($options[$field]) ? $options[$field] : '';
        ?>
        <input type="text" 
               name="<?php echo esc_attr($this->option_name . '[' . $field . ']'); ?>"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text">
        <?php
    }

    public function render_textarea_field($args) {
        $options = get_option($this->option_name);
        $field = $args['field'];
        $value = isset($options[$field]) ? $options[$field] : '';
        ?>
        <textarea name="<?php echo esc_attr($this->option_name . '[' . $field . ']'); ?>"
                  class="large-text"
                  rows="4"><?php echo esc_textarea($value); ?></textarea>
        <?php
    }

    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['cta_title'])) {
            $sanitized['cta_title'] = sanitize_text_field($input['cta_title']);
        }
        
        if (isset($input['cta_description'])) {
            $sanitized['cta_description'] = wp_kses_post($input['cta_description']);
        }
        
        if (isset($input['button_text'])) {
            $sanitized['button_text'] = sanitize_text_field($input['button_text']);
        }
        
        if (isset($input['fallback_cta'])) {
            $sanitized['fallback_cta'] = wp_kses_post($input['fallback_cta']);
        }
        
        return $sanitized;
    }
}