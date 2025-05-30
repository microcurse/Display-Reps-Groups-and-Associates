<?php
namespace RepGroup;

class Post_Type {
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes_rep-group', [$this, 'add_custom_meta_boxes']);
        add_action('admin_menu', [$this, 'remove_author_metabox']);
        add_filter('post_row_actions', [$this, 'modify_list_row_actions'], 10, 2);
        add_filter('acf/validate_value/name=area-served', [$this, 'validate_unique_area_served_assignment'], 10, 4);
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

    public function add_custom_meta_boxes() {
        add_meta_box(
            'rep_group_shortcode_metabox', // Unique ID
            'Shortcode',                  // Box title
            [$this, 'render_shortcode_meta_box'],  // Content callback
            'rep-group',                  // Post type
            'side',                       // Context (normal, side, advanced)
            'low'                         // Priority (high, core, default, low)
        );
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
        $template_path = REP_GROUP_PATH . 'templates/admin/shortcode-box.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<p>Error: Shortcode display template not found.</p>';
        }
    }

    /**
     * Validate that an "Area Served" term is not already assigned to another Rep Group.
     *
     * @param mixed $valid Whether the value is valid (boolean) or a custom error message (string).
     * @param mixed $value The value of the field.
     * @param array $field The ACF field array.
     * @param string $input_name The input name of the field (e.g., acf[field_xxxxxxxxxxxxx]).
     * @return mixed True if valid, or a string error message if invalid.
     */
    public function validate_unique_area_served_assignment($valid, $value, $field, $input_name) {
        // If the value is already marked invalid by another validation, or if it's empty, don't proceed.
        if (!$valid || empty($value)) {
            return $valid;
        }

        global $post;
        $current_post_id = 0;

        if (isset($_POST['post_ID'])) { // Standard post edit screen
            $current_post_id = absint($_POST['post_ID']);
        } elseif (is_admin() && function_exists('get_current_screen')) { // Check if on admin screen
            $screen = get_current_screen();
            if ($screen && $screen->post_type === 'rep-group' && $screen->base === 'post' && isset($post->ID)) {
                 // When creating a new post, $post might be set but ID might be 0 before first save.
                 // For existing posts, $post->ID should be reliable here.
                $current_post_id = $post->ID;
            }
        }       
        // Note: For AJAX or REST API context where global $post or $_POST['post_ID'] isn't available,
        // this validation might need a more robust way to get the current post ID if the validation is triggered there.
        // ACF usually provides context, but it can vary.

        $term_ids_being_assigned = (array) $value; 

        foreach ($term_ids_being_assigned as $term_id) {
            $term_id = absint($term_id);
            if (!$term_id) continue;

            $term = get_term($term_id, 'area-served');
            if (!$term || is_wp_error($term)) {
                continue; 
            }

            $args = [
                'post_type' => 'rep-group',
                'posts_per_page' => 1, 
                'post_status' => 'publish', 
                'fields' => 'ids', 
                'tax_query' => [
                    [
                        'taxonomy' => 'area-served',
                        'field'    => 'term_id',
                        'terms'    => $term_id,
                    ],
                ],
            ];

            if ($current_post_id > 0) {
                $args['post__not_in'] = [$current_post_id]; // Exclude the current post being saved
            }

            $conflicting_posts = get_posts($args);

            if (!empty($conflicting_posts)) {
                $conflicting_post_id = $conflicting_posts[0];
                $conflicting_post_title = get_the_title($conflicting_post_id);
                // Ensure __() is available. It should be as this is WordPress context.
                return sprintf(
                    __('Error: The area "%1$s" is already assigned to another Rep Group (%2$s - ID: %3$s). Each area can only be assigned to one Rep Group.', 'rep-group'),
                    esc_html($term->name),
                    esc_html($conflicting_post_title),
                    esc_html($conflicting_post_id)
                );
            }
        }
        return $valid; // If no conflicts, the value is valid
    }
}