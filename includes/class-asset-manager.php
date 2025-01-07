<?php
namespace RepGroup;

class Asset_Manager {
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    public function enqueue_admin_assets($hook) {
        // Only load on our plugin's admin pages
        if (!$this->is_plugin_admin_page($hook)) {
            return;
        }

        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_style('rep-group-admin', REP_GROUP_URL . 'assets/css/admin.css', [], REP_GROUP_VERSION);
        
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
    }

    private function is_plugin_admin_page($hook) {
        $plugin_pages = [
            'edit.php?post_type=rep-group',
            'post-new.php?post_type=rep-group',
            'post.php?post_type=rep-group'
        ];

        return in_array($hook, $plugin_pages);
    }

    public function enqueue_frontend_assets() {
        global $post;
        
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $has_display_shortcode = has_shortcode($post->post_content, 'rep_group_display');
        
        if ($has_display_shortcode || is_singular('rep-group') || is_post_type_archive('rep-group')) {
            wp_enqueue_style('dashicons');
            wp_enqueue_style(
                'rep-group-frontend',
                REP_GROUP_URL . 'assets/css/frontend.css',
                [],
                REP_GROUP_VERSION
            );
        }
    }
} 