<?php
if (!defined('ABSPATH')) {
    exit;
}

class IBCTA_Related_Posts {
    public function __construct() {
        add_action('init', array($this, 'register_block'));
        add_action('widgets_init', array($this, 'register_widget'));
    }

    public function register_block() {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type('internal-blog-cta/related-posts', array(
            'editor_script' => 'ibcta-blocks',
            'render_callback' => array($this, 'render_related_posts'),
            'attributes' => array(
                'layout' => array(
                    'type' => 'string',
                    'default' => 'list'
                ),
                'postsToShow' => array(
                    'type' => 'number',
                    'default' => 3
                ),
                'orderBy' => array(
                    'type' => 'string',
                    'default' => 'date'
                )
            )
        ));
    }

    public function register_widget() {
        register_widget('IBCTA_Related_Posts_Widget');
    }

    public function render_related_posts($attributes) {
        global $post;

        if (!is_object($post)) {
            return '';
        }

        $defaults = array(
            'layout' => 'list',
            'postsToShow' => 3,
            'orderBy' => 'date'
        );

        $attributes = wp_parse_args($attributes, $defaults);

        // Get current page categories
        $categories = get_the_category($post->ID);
        if (empty($categories)) {
            return '';
        }

        $category_ids = wp_list_pluck($categories, 'term_id');

        $query_args = array(
            'post_type' => 'post',
            'posts_per_page' => $attributes['postsToShow'],
            'post__not_in' => array($post->ID),
            'category__in' => $category_ids,
            'orderby' => $attributes['orderBy'],
            'order' => 'DESC'
        );

        $related_posts = new WP_Query($query_args);

        if (!$related_posts->have_posts()) {
            return '';
        }

        ob_start();
        ?>
        <div class="ibcta-related-posts ibcta-layout-<?php echo esc_attr($attributes['layout']); ?>">
            <ul class="ibcta-posts-list">
                <?php while ($related_posts->have_posts()) : $related_posts->the_post(); ?>
                    <li class="ibcta-post-item">
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="ibcta-post-thumbnail">
                                <?php the_post_thumbnail('thumbnail'); ?>
                            </div>
                        <?php endif; ?>
                        <div class="ibcta-post-content">
                            <h4 class="ibcta-post-title">
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            </h4>
                            <div class="ibcta-post-excerpt">
                                <?php the_excerpt(); ?>
                            </div>
                        </div>
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }
}