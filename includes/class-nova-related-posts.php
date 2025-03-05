<?php
if (!defined('ABSPATH')) {
    exit;
}

class Nova_Related_Posts {
    private $post_id;
    private $related_posts;

    public function __construct($post_id) {
        $this->post_id = $post_id;
        $this->related_posts = $this->get_related_posts();
    }

    public function get_related_posts() {
        $categories = wp_get_post_categories($this->post_id);
        
        if (empty($categories)) {
            return array();
        }

        $args = array(
            'category__in'   => $categories,
            'post__not_in'   => array($this->post_id),
            'posts_per_page' => 3,
            'orderby'        => 'rand'
        );

        $related_query = new WP_Query($args);
        return $related_query->posts;
    }

    public function render() {
        if (empty($this->related_posts)) {
            return '';
        }

        $output = '<div class="nova-related-posts">';
        $output .= '<h3>' . __('Related Posts', 'nova-ctas') . '</h3>';
        $output .= '<ul>';

        foreach ($this->related_posts as $post) {
            $output .= sprintf(
                '<li><a href="%s">%s</a></li>',
                get_permalink($post->ID),
                esc_html($post->post_title)
            );
        }

        $output .= '</ul></div>';
        return $output;
    }
} 