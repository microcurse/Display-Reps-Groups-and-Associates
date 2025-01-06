<?php
namespace RepGroup;

class Post_Type {
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('admin_menu', [$this, 'remove_author_metabox']);
        add_filter('post_row_actions', [$this, 'modify_list_row_actions'], 10, 2);
    }

    public function register_post_type() {
        $labels = [
            'name'                  => 'Rep Groups',
            'singular_name'         => 'Rep Group',
            'add_new'              => 'Add New',
            'add_new_item'         => 'Add New Rep Group',
            'edit_item'            => 'Edit Rep Group',
            'new_item'             => 'New Rep Group',
            'view_item'            => 'View Rep Group',
            'search_items'         => 'Search Rep Groups',
            'not_found'            => 'No rep groups found',
            'not_found_in_trash'   => 'No rep groups found in trash',
            'all_items'            => 'All Rep Groups',
            'menu_name'            => 'Rep Groups',
            // Additional helpful labels
            'featured_image'       => 'Rep Group Image',
            'set_featured_image'   => 'Set rep group image',
            'remove_featured_image'=> 'Remove rep group image',
            'archives'             => 'Rep Group Archives',
        ];

        $args = [
            'labels'              => $labels,
            'public'              => true,
            'has_archive'         => true,
            'show_in_menu'        => true,
            'supports'            => [
                'title',
                'thumbnail',
                'custom-fields',  // Add if using ACF fields
                'excerpt',        // Optional: for short descriptions
                'revisions',      // Optional: for change tracking
            ],
            'menu_icon'          => 'dashicons-groups',
            'show_in_rest'       => true,  // Enable Gutenberg if desired
            'menu_position'      => 5,     // Position in admin menu
            'publicly_queryable' => true,
            'hierarchical'       => false,
            'rewrite'           => [
                'slug' => 'rep-group',
                'with_front' => true
            ],
            'capability_type'    => 'post',
            'show_in_admin_bar'  => true,
        ];

        register_post_type('rep-group', $args);
    }

    public function register_taxonomies() {
        $labels = [
            'name'              => 'Areas Served',
            'singular_name'     => 'Area Served',
            'search_items'      => 'Search Areas',
            'all_items'         => 'All Areas',
            'parent_item'       => 'Parent Area',
            'parent_item_colon' => 'Parent Area:',
            'edit_item'         => 'Edit Area',
            'update_item'       => 'Update Area',
            'add_new_item'      => 'Add New Area',
            'new_item_name'     => 'New Area Name',
            'menu_name'         => 'Areas Served'
        ];

        $args = [
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'show_in_rest'      => true,
            'rewrite'           => [
                'slug' => 'area-served',
                'with_front' => true
            ],
        ];

        register_taxonomy('area-served', ['rep-group'], $args);
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

    public function render_shortcode_meta_box($post) {
        require REP_GROUP_PATH . 'templates/admin/shortcode-box.php';
    }
}