<?php
namespace RepGroup;

class Post_Type {
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_menu', [$this, 'remove_author_metabox']);
        add_filter('post_row_actions', [$this, 'modify_list_row_actions'], 10, 2);
        add_filter('archive_template', [$this, 'load_archive_template']);
    }

    public function register_post_type() {
        register_post_type('rep-group', [
            'labels' => [
                'name' => 'Rep Groups',
                'singular_name' => 'Rep Group',
                'add_new' => 'Add New',
                'add_new_item' => 'Add New Rep Group',
                'edit_item' => 'Edit Rep Group',
                'new_item' => 'New Rep Group',
                'view_item' => 'View Rep Group',
                'search_items' => 'Search Rep Groups',
                'not_found' => 'No rep groups found',
                'not_found_in_trash' => 'No rep groups found in trash',
                'all_items' => 'All Rep Groups',
                'menu_name' => 'Rep Groups'
            ],
            'public' => true,
            'has_archive' => true,
            'show_in_menu' => true,
            'supports' => ['title', 'thumbnail'],
            'menu_icon' => 'dashicons-groups',
            'show_in_rest' => false,
        ]);
    }

    public function remove_author_metabox() {
        remove_meta_box('authordiv', 'rep-group', 'normal');
    }

    public function modify_list_row_actions($actions, $post) {
        if ($post->post_type === 'rep-group') {
            unset($actions['inline hide-if-no-js']);
        }
        return $actions;
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

    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'rep-group-frontend',
            REP_GROUP_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            REP_GROUP_VERSION
        );
    }

    public function load_archive_template($template) {
        if (is_post_type_archive('rep-group')) {
            $custom_template = REP_GROUP_PLUGIN_PATH . 'templates/archive-rep-group.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        return $template;
    }
} 