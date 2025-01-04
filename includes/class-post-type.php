<?php
namespace RepGroup;

class Post_Type {
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomies']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_menu', [$this, 'remove_author_metabox']);
        add_filter('post_row_actions', [$this, 'modify_list_row_actions'], 10, 2);
        add_filter('archive_template', [$this, 'load_archive_template']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_map_assets']);
        add_action('wp_ajax_get_rep_groups_by_state', [$this, 'ajax_get_rep_groups_by_state']);
        add_action('wp_ajax_nopriv_get_rep_groups_by_state', [$this, 'ajax_get_rep_groups_by_state']);
        add_action('admin_menu', [$this, 'add_map_manager_page']);
        add_action('wp_ajax_save_state_rep_groups', [$this, 'save_state_rep_groups']);
        add_action('wp_ajax_get_state_rep_groups_admin', [$this, 'get_state_rep_groups_admin']);
        add_action('wp_ajax_update_map_svg', [$this, 'update_map_svg']);
        add_action('wp_ajax_remove_map_svg', [$this, 'remove_map_svg']);
        add_action('area-served_add_form_fields', [$this, 'add_svg_id_field']);
        add_action('area-served_edit_form_fields', [$this, 'edit_svg_id_field']);
        add_action('created_area-served', [$this, 'save_svg_id_field']);
        add_action('edited_area-served', [$this, 'save_svg_id_field']);
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
        if ('rep-group_page_rep-group-map-manager' !== $hook) {
            return;
        }

        // Enqueue WordPress media scripts
        wp_enqueue_media();

        wp_enqueue_style(
            'rep-group-admin-map',
            REP_GROUP_PLUGIN_URL . 'assets/css/admin-map.css',
            [],
            REP_GROUP_VERSION
        );

        // Make sure we load all required dependencies
        wp_enqueue_script(
            'rep-group-admin-map',
            REP_GROUP_PLUGIN_URL . 'assets/js/admin-map.js',
            ['jquery', 'select2', 'media-upload', 'wp-media-utils', 'wp-plupload'],
            REP_GROUP_VERSION,
            true
        );

        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery']);

        wp_localize_script('rep-group-admin-map', 'repGroupsAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rep_group_map_admin'),
            'currentMapId' => get_option('rep_group_map_svg_id')
        ]);
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

    public function enqueue_map_assets() {
        wp_enqueue_style(
            'rep-group-map',
            REP_GROUP_PLUGIN_URL . 'assets/css/map.css',
            [],
            REP_GROUP_VERSION
        );

        wp_enqueue_script(
            'rep-group-map',
            REP_GROUP_PLUGIN_URL . 'assets/js/map.js',
            ['jquery'],
            REP_GROUP_VERSION,
            true
        );

        wp_localize_script('rep-group-map', 'repGroupsData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rep_group_map')
        ]);
    }

    public function ajax_get_rep_groups_by_state() {
        check_ajax_referer('rep_group_map', 'nonce');
        
        $state = sanitize_text_field($_POST['state']);
        // Remove 'US-' prefix from SVG ID to match taxonomy slug
        $state_code = str_replace('US-', '', $state);
        
        $args = [
            'post_type' => 'rep-group',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => 'area-served',
                    'field' => 'slug',
                    'terms' => $state_code
                ]
            ]
        ];
        
        $rep_groups = get_posts($args);
        $html = '';
        
        if ($rep_groups) {
            foreach ($rep_groups as $group) {
                $html .= '<div class="rep-group-item">';
                $html .= '<h3>' . esc_html($group->post_title) . '</h3>';
                if (has_post_thumbnail($group->ID)) {
                    $html .= get_the_post_thumbnail($group->ID, 'thumbnail');
                }
                $html .= '<div class="rep-group-excerpt">' . get_the_excerpt($group) . '</div>';
                $html .= '<a href="' . get_permalink($group->ID) . '" class="rep-group-link">View Details</a>';
                $html .= '</div>';
            }
        } else {
            $html = '<p class="no-results">No representatives found for this area.</p>';
        }
        
        wp_send_json_success(['html' => $html]);
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

    public function add_map_manager_page() {
        add_submenu_page(
            'edit.php?post_type=rep-group',
            'Map Manager',
            'Map Manager',
            'manage_options',
            'rep-group-map-manager',
            [$this, 'render_map_manager_page']
        );
    }

    public function render_map_manager_page() {
        // Get the saved SVG attachment ID
        $map_svg_id = get_option('rep_group_map_svg_id');
        ?>
        <div class="wrap">
            <h1>Rep Group Map Manager</h1>
            
            <div class="map-upload-section">
                <?php if (!$map_svg_id): ?>
                    <div class="no-map-message">
                        <p>No map SVG uploaded. Please upload an SVG map with state paths using IDs in the format "US-XX".</p>
                    </div>
                <?php endif; ?>
                
                <button type="button" id="upload-map-svg" class="button">
                    <?php echo $map_svg_id ? 'Change Map SVG' : 'Upload Map SVG'; ?>
                </button>
                
                <?php if ($map_svg_id): ?>
                    <button type="button" id="remove-map-svg" class="button">Remove Map</button>
                <?php endif; ?>
            </div>

            <?php if ($map_svg_id): ?>
                <div class="map-manager-container">
                    <div class="map-preview">
                        <?php 
                        $svg_url = wp_get_attachment_url($map_svg_id);
                        $svg_path = get_attached_file($map_svg_id);
                        
                        if ($svg_path && file_exists($svg_path)) {
                            echo file_get_contents($svg_path);
                        } else {
                            echo '<p class="error">Error loading SVG file.</p>';
                        }
                        ?>
                    </div>
                    <div class="map-controls">
                        <div id="state-info">
                            <h3>Selected State: <span id="selected-state">None</span></h3>
                            <div id="rep-group-selector">
                                <?php
                                $rep_groups = get_posts([
                                    'post_type' => 'rep-group',
                                    'posts_per_page' => -1,
                                    'orderby' => 'title',
                                    'order' => 'ASC'
                                ]);
                                
                                if ($rep_groups): ?>
                                    <select id="rep-group-select" multiple>
                                        <?php foreach ($rep_groups as $group): ?>
                                            <option value="<?php echo esc_attr($group->ID); ?>">
                                                <?php echo esc_html($group->post_title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button id="save-state-mapping" class="button button-primary">Save Assignment</button>
                                <?php else: ?>
                                    <p>No rep groups found. Please create some first.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function save_state_rep_groups() {
        if (!check_ajax_referer('rep_group_map_admin', 'nonce', false)) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        if (!isset($_POST['state'])) {
            wp_send_json_error('No state/region provided');
            return;
        }
        
        $svg_id = sanitize_text_field($_POST['state']); // e.g., "US-NFL"
        $rep_groups = isset($_POST['rep_groups']) ? array_map('intval', $_POST['rep_groups']) : [];
        
        // Get existing taxonomy term based on the SVG ID mapping
        $term = $this->get_term_by_svg_id($svg_id);
        
        if (!$term) {
            wp_send_json_error("No matching taxonomy term found for region: {$svg_id}");
            return;
        }
        
        // Remove existing assignments for this term
        $existing_posts = get_posts([
            'post_type' => 'rep-group',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => 'area-served',
                    'field' => 'term_id',
                    'terms' => $term->term_id
                ]
            ]
        ]);

        foreach ($existing_posts as $post) {
            wp_remove_object_terms($post->ID, $term->term_id, 'area-served');
        }
        
        // Add new assignments
        $errors = [];
        foreach ($rep_groups as $group_id) {
            $result = wp_set_object_terms($group_id, $term->term_id, 'area-served', true);
            if (is_wp_error($result)) {
                $errors[] = "Error assigning group {$group_id}: " . $result->get_error_message();
            }
        }
        
        if (!empty($errors)) {
            wp_send_json_error(['errors' => $errors]);
            return;
        }
        
        wp_send_json_success(['message' => 'Assignments saved successfully']);
    }

    /**
     * Get taxonomy term by SVG ID
     */
    private function get_term_by_svg_id($svg_id) {
        // Remove 'US-' prefix
        $region_code = str_replace('US-', '', $svg_id);
        
        // First try to find the term by exact slug match
        $term = get_term_by('slug', $region_code, 'area-served');
        
        if (!$term) {
            // If no exact match, try to find by SVG ID stored in term meta
            $terms = get_terms([
                'taxonomy' => 'area-served',
                'hide_empty' => false,
                'meta_query' => [
                    [
                        'key' => 'svg_id',
                        'value' => $svg_id,
                        'compare' => '='
                    ]
                ]
            ]);
            
            if (!empty($terms)) {
                $term = $terms[0];
            }
        }
        
        return $term;
    }

    /**
     * Add SVG ID field to taxonomy term edit page
     */
    public function add_svg_id_field() {
        ?>
        <div class="form-field">
            <label for="svg_id">SVG ID</label>
            <input type="text" name="svg_id" id="svg_id" value="" />
            <p class="description">Enter the SVG ID for this region (e.g., US-NFL for Florida Panhandle)</p>
        </div>
        <?php
    }

    public function edit_svg_id_field($term) {
        $svg_id = get_term_meta($term->term_id, 'svg_id', true);
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="svg_id">SVG ID</label>
            </th>
            <td>
                <input type="text" name="svg_id" id="svg_id" value="<?php echo esc_attr($svg_id); ?>" />
                <p class="description">Enter the SVG ID for this region (e.g., US-NFL for Florida Panhandle)</p>
            </td>
        </tr>
        <?php
    }

    public function save_svg_id_field($term_id) {
        if (isset($_POST['svg_id'])) {
            $svg_id = sanitize_text_field($_POST['svg_id']);
            update_term_meta($term_id, 'svg_id', $svg_id);
        }
    }

    public function get_state_rep_groups_admin() {
        check_ajax_referer('rep_group_map_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $state = sanitize_text_field($_POST['state']);
        $state_code = str_replace('US-', '', $state);
        
        $args = [
            'post_type' => 'rep-group',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => 'area-served',
                    'field' => 'slug',
                    'terms' => $state_code
                ]
            ]
        ];
        
        $rep_groups = get_posts($args);
        $group_ids = array_map(function($group) {
            return $group->ID;
        }, $rep_groups);
        
        wp_send_json_success(['rep_groups' => $group_ids]);
    }

    public function update_map_svg() {
        check_ajax_referer('rep_group_map_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        
        if ($attachment_id) {
            update_option('rep_group_map_svg_id', $attachment_id);
            wp_send_json_success(['message' => 'Map updated successfully']);
        }
        
        wp_send_json_error('Invalid attachment ID');
    }

    public function remove_map_svg() {
        check_ajax_referer('rep_group_map_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        delete_option('rep_group_map_svg_id');
        wp_send_json_success(['message' => 'Map removed successfully']);
    }
} 