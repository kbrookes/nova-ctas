<?php
if (!defined('ABSPATH')) {
    exit;
}

class Nova_Related_Posts_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'nova_related_posts_widget',
            __('Nova Related Posts', 'nova-ctas'),
            array('description' => __('Display related posts for the current post', 'nova-ctas'))
        );
    }

    public function widget($args, $instance) {
        if (!is_single()) {
            return;
        }

        $title = !empty($instance['title']) ? $instance['title'] : __('Related Posts', 'nova-ctas');
        $title = apply_filters('widget_title', $title, $instance, $this->id_base);

        $related_posts = new Nova_Related_Posts(get_the_ID());
        $content = $related_posts->render();

        if (!empty($content)) {
            echo $args['before_widget'];
            if ($title) {
                echo $args['before_title'] . $title . $args['after_title'];
            }
            echo $content;
            echo $args['after_widget'];
        }
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Related Posts', 'nova-ctas');
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'nova-ctas'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        return $instance;
    }
} 