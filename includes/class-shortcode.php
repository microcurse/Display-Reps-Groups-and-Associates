<?php
namespace RepGroup;

class Shortcode {
    public function __construct() {
        add_shortcode('rep_group', [$this, 'render_shortcode']);
        add_filter('the_content', [$this, 'modify_rep_group_content']);
        add_action('add_meta_boxes', [$this, 'add_shortcode_meta_box']);
        add_filter('manage_rep-group_posts_columns', [$this, 'add_shortcode_column']);
        add_action('manage_rep-group_posts_custom_column', [$this, 'render_shortcode_column'], 10, 2);
    }

    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts);

        if (empty($atts['id'])) {
            return '';
        }

        return Renderer::render_rep_group($atts['id']);
    }

    public function modify_rep_group_content($content) {
        if (!is_singular('rep-group') || !in_the_loop()) {
            return $content;
        }
        return $content . Renderer::render_rep_group(get_the_ID());
    }

    public function add_shortcode_meta_box() {
        add_meta_box(
            'rep-group-shortcode',
            'Rep Group Shortcode',
            [$this, 'render_shortcode_meta_box'],
            'rep-group',
            'side',
            'high'
        );
    }

    public function render_shortcode_meta_box($post) {
        $shortcode = sprintf('[rep_group id="%d"]', $post->ID);
        ?>
        <div class="rep-group-shortcode" title="Click to copy shortcode">
            <?php echo esc_html($shortcode); ?>
        </div>
        <div class="shortcode-copied">Shortcode copied to clipboard!</div>
        <?php
    }

    public function add_shortcode_column($columns) {
        $columns['shortcode'] = 'Shortcode';
        return $columns;
    }

    public function render_shortcode_column($column, $post_id) {
        if ($column === 'shortcode') {
            $shortcode = sprintf('[rep_group id="%d"]', $post_id);
            printf(
                '<div class="rep-group-shortcode" title="Click to copy shortcode">%s</div>
                <div class="shortcode-copied">Shortcode copied to clipboard!</div>',
                esc_html($shortcode)
            );
        }
    }
}