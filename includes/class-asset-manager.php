<?php
namespace RepGroup;

class Asset_Manager {
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_map_assets']);
    }

    public function enqueue_admin_assets($hook) {
        if ($hook === 'rep-group_page_rep-group-map-manager') {
            wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
            wp_enqueue_style('rep-group-admin', REP_GROUP_URL . 'assets/css/admin.css', [], REP_GROUP_VERSION);
            
            wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
            wp_enqueue_script('rep-group-admin-map', REP_GROUP_URL . 'assets/js/admin-map.js', ['jquery', 'select2'], REP_GROUP_VERSION, true);
            
            wp_localize_script('rep-group-admin-map', 'repGroupsAdmin', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rep_groups_nonce')
            ]);
        }
    }

    public function enqueue_frontend_assets() {
        global $post;
        
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $has_map_shortcode = has_shortcode($post->post_content, 'rep_group_map');
        $has_display_shortcode = has_shortcode($post->post_content, 'rep_group_display');
        
        if ($has_map_shortcode || $has_display_shortcode || is_singular('rep-group')) {
            wp_enqueue_style(
                'rep-group-frontend',
                REP_GROUP_URL . 'assets/css/frontend.css',
                [],
                REP_GROUP_VERSION
            );
        }

        if ($has_map_shortcode) {
            wp_enqueue_script(
                'rep-group-map',
                REP_GROUP_URL . 'assets/js/frontend-map.js',
                ['jquery'],
                REP_GROUP_VERSION,
                true
            );

            wp_localize_script('rep-group-map', 'repGroupsData', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rep_groups_nonce')
            ]);
        }
    }

    public function enqueue_map_assets() {
        // Only enqueue map assets when needed
        if (is_singular('rep-group') || has_shortcode(get_the_content(), 'rep_group_map')) {
            wp_enqueue_style(
                'rep-group-map',
                REP_GROUP_URL . 'assets/css/map.css',
                [],
                REP_GROUP_VERSION
            );

            wp_enqueue_script(
                'rep-group-map',
                REP_GROUP_URL . 'assets/js/map.js',
                ['jquery'],
                REP_GROUP_VERSION,
                true
            );

            wp_localize_script('rep-group-map', 'repGroupMapData', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rep_group_map')
            ]);
        }
    }
} 