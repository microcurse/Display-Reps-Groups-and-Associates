<?php
namespace RepGroup;

class Post_Type {
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function register_post_type() {
        register_post_type('rep-group', [
            'labels' => [
                'name' => 'Rep Groups',
                'singular_name' => 'Rep Group',
            ],
            'public' => true,
            'has_archive' => true,
            'show_in_menu' => true,
            'supports' => ['title'],
            'menu_icon' => 'dashicons-groups',
        ]);
    }

    public function enqueue_admin_assets($hook) {
        $screen = get_current_screen();
        
        if ($screen && ($screen->post_type === 'rep-group')) {
            wp_enqueue_style(
                'rep-group-admin',
                REP_GROUP_PLUGIN_URL . 'assets/css/admin.css',
                [],
                REP_GROUP_VERSION
            );
            
            wp_enqueue_script(
                'rep-group-admin',
                REP_GROUP_PLUGIN_URL . 'assets/js/admin.js',
                [],
                REP_GROUP_VERSION,
                true
            );
        }
    }
} 