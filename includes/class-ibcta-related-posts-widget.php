<?php
if (!defined('ABSPATH')) {
    exit;
}

class IBCTA_Related_Posts_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'ibcta_related_posts',
            __('IBCTA Related Posts', 'internal-blog-cta'),
            array('description' => __('Displays related blog posts based on current page categories.', 'internal-blog-cta'))
        );
    }

    public function widget($args, $instance) {
        $related_posts = new IBCTA_Related_Posts();
        
        echo $args['before_widget'];
        
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }
        
        echo $related_posts->render_related_posts(array(
            'layout' => $instance['layout'] ?? 'list',
            'postsToShow' => $instance['posts_to_show'] ?? 3,
            'orderBy' => $instance['order_by'] ?? 'date'
        ));
        
        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = isset($instance['title']) ? $instance['title'] : '';
        $layout = isset($instance['layout']) ? $instance['layout'] : 'list';
        $posts_to_show = isset($instance['posts_to_show']) ? $instance['posts_to_show'] : 3;
        $order_by = isset($instance['order_by']) ? $instance['order_by'] : 'date';
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'internal-blog-cta'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('layout'); ?>"><?php _e('Layout:', 'internal-blog-cta'); ?></label>
            <select class="widefat" id="<?php echo $this->get_field_id('layout'); ?>" name="<?php echo $this->get_field_name('layout'); ?>">
                <option value="list" <?php selected($layout, 'list'); ?>><?php _e('List', 'internal-blog-cta'); ?></option>
                <option value="grid" <?php selected($layout, 'grid'); ?>><?php _e('Grid', 'internal-blog-cta'); ?></option>
            </select>
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('posts_to_show'); ?>"><?php _e('Number of posts to show:', 'internal-blog-cta'); ?></label>
            <input class="tiny-text" id="<?php echo $this->get_field_id('posts_to_show'); ?>" name="<?php echo $this->get_field_name('posts_to_show'); ?>" type="number" step="1" min="1" value="<?php echo esc_attr($posts_to_show); ?>" size="3">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('order_by'); ?>"><?php _e('Order by:', 'internal-blog-cta'); ?></label>
            <select class="widefat" id="<?php echo $this->get_field_id('order_by'); ?>" name="<?php echo $this->get_field_name('order_by'); ?>">
                <option value="date" <?php selected($order_by, 'date'); ?>><?php _e('Date', 'internal-blog-cta'); ?></option>
                <option value="title" <?php selected($order_by, 'title'); ?>><?php _e('Title', 'internal-blog-cta'); ?></option>
                <option value="comment_count" <?php selected($order_by, 'comment_count'); ?>><?php _e('Comment Count', 'internal-blog-cta'); ?></option>
            </select>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        $instance['layout'] = (!empty($new_instance['layout'])) ? strip_tags($new_instance['layout']) : 'list';
        $instance['posts_to_show'] = (!empty($new_instance['posts_to_show'])) ? (int) $new_instance['posts_to_show'] : 3;
        $instance['order_by'] = (!empty($new_instance['order_by'])) ? strip_tags($new_instance['order_by']) : 'date';
        return $instance;
    }
}