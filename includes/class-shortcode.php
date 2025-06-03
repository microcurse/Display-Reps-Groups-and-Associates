<?php
namespace RepGroup;

class Shortcode {
    public function __construct() {
        add_shortcode('rep_group_display', [$this, 'render_rep_group_display']);
        add_shortcode('rep_map', [$this, 'render_rep_map']);

        // AJAX handler for fetching rep group info when an area is clicked on the map
        add_action('wp_ajax_get_rep_group_info_for_area', [$this, 'ajax_get_rep_group_info_for_area']);
        add_action('wp_ajax_nopriv_get_rep_group_info_for_area', [$this, 'ajax_get_rep_group_info_for_area']);

        // AJAX handler for fetching rep group details when a list item is clicked
        add_action('wp_ajax_get_rep_group_details_by_id', [$this, 'ajax_get_rep_group_details_by_id']);
        add_action('wp_ajax_nopriv_get_rep_group_details_by_id', [$this, 'ajax_get_rep_group_details_by_id']);
    }

    /**
     * Render the rep group display shortcode
     */
    public function render_rep_group_display($atts) {
        $attributes = shortcode_atts([
            'id' => null,
            'limit' => -1
        ], $atts);

        // If ID is provided, render single rep group
        if ($attributes['id']) {
            return $this->render_single_rep_group($attributes['id']);
        }

        // Otherwise, query rep groups
        $query_args = [
            'post_type' => 'rep-group',
            'posts_per_page' => $attributes['limit']
        ];

        $rep_groups = new \WP_Query($query_args);
        
        ob_start();
        include REP_GROUP_PATH . 'templates/frontend/archive-rep-group.php';
        return ob_get_clean();
    }

    /**
     * Render a single rep group
     */
    private function render_single_rep_group($post_id) {
        ob_start();
        
        // Pass $post_id and $this (as $shortcode_instance) to the template
        $shortcode_instance = $this;
        $template_path = REP_GROUP_PATH . 'templates/frontend/single-rep-group.php';

        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<p>Error: Single rep group template not found.</p>';
        }
        
        return ob_get_clean();
    }

    /**
     * Helper method to render rep contact info
     * Now accepts user_data and overrides
     */
    public function render_rep_contact_info($user_data, $email_override, $phone_override) {
        $output = '';

        // Display Phone (Override first, then potentially from user profile if we add that later)
        $phone_to_display = ''; // Default to empty
        $phone_type_text = 'Phone'; // Generic label, can be improved if we store phone type

        if (!empty($phone_override)) {
            $phone_to_display = $phone_override;
            // Optionally, if you add a phone_type_override field:
            // $phone_type_text = get_sub_field('rep_contact_phone_type_override') ?: 'Phone'; 
        } elseif ($user_data) {
            // Fallback to user profile field if override is not set
            $user_profile_phone = get_field('rep_primary_phone', 'user_' . $user_data->ID);
            if (!empty($user_profile_phone)) {
                $phone_to_display = $user_profile_phone;
                // Optionally, you might have a separate field for phone type on user profile too
            }
        }

        if (!empty($phone_to_display)) {
            $output .= sprintf(
                '<p class="rep-phone"><ion-icon name="call" role="img" class="hydrated" aria-label="call"></ion-icon> <strong>%s:</strong> <a href="tel:%s">%s</a></p>',
                esc_html($phone_type_text),
                esc_attr(preg_replace('/[^0-9+ ]/', '', $phone_to_display)),
                esc_html($phone_to_display)
            );
        }

        // Display Email (Override first, then from user profile)
        $email_to_display = '';
        if (!empty($email_override)) {
            $email_to_display = $email_override;
        } elseif ($user_data && !empty($user_data->user_email)) {
            $email_to_display = $user_data->user_email;
        }

        if (!empty($email_to_display) && is_email($email_to_display)) {
            $output .= sprintf(
                '<p class="rep-email"><ion-icon name="mail" role="img" class="hydrated" aria-label="mail"></ion-icon> <strong>Email:</strong> <a href="mailto:%s">%s</a></p>',
                esc_attr($email_to_display),
                esc_html($email_to_display)
            );
        }

        return $output;
    }

    /**
     * Render the rep map shortcode
     * [rep_map type="local"] or [rep_map type="international"]
     */
    public function render_rep_map($atts) {
        $attributes = shortcode_atts([
            'type' => 'local', 
            'interactive' => 'true'
        ], $atts);

        $map_type = strtolower($attributes['type']);
        $is_interactive = filter_var($attributes['interactive'], FILTER_VALIDATE_BOOLEAN);
        $svg_url = '';

        if ($map_type === 'local') {
            $svg_url = get_option('rep_group_local_svg');
        } elseif ($map_type === 'international') {
            $svg_url = get_option('rep_group_international_svg');
        }

        if (empty($svg_url)) {
            return '<!-- Rep map SVG URL not configured or invalid type -->';
        }

        $map_instance_id = 'rep-map-instance-' . esc_attr($map_type) . '-' . wp_generate_uuid4();
        
        // Data for map links (area_data)
        $map_region_data = []; // Renamed from $map_links_with_term_slugs
        $rep_groups_query_args_for_map_links = [
            'post_type' => 'rep-group',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];
        $rep_groups_for_map_links = get_posts($rep_groups_query_args_for_map_links);

        foreach ($rep_groups_for_map_links as $rep_group) {
            $terms = get_the_terms($rep_group->ID, 'area-served');
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $svg_id_meta = get_term_meta($term->term_id, '_rep_svg_target_id', true);
                    if (!empty($svg_id_meta)) {
                        $color = get_field('rep_group_map_color', $rep_group->ID);
                        if (empty($color)) {
                            $color = REP_GROUP_DEFAULT_REGION_COLOR;
                        }
                        $svg_id_key = ltrim($svg_id_meta, '#');
                        $map_region_data[$svg_id_key] = [
                            'color' => $color,
                            'term_id' => $term->term_id,
                            'term_name' => $term->name,
                            'term_slug' => $term->slug
                        ];
                    }
                }
            }
        }

        // Data for the default "Rep Groups" list view
        $all_rep_groups_data = [];
        $all_rep_groups_query_args = [
            'post_type' => 'rep-group',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ];
        $all_rep_groups_posts = get_posts($all_rep_groups_query_args);
        foreach ($all_rep_groups_posts as $group_post) {
            $all_rep_groups_data[] = [
                'id' => $group_post->ID,
                'title' => get_the_title($group_post->ID),
            ];
        }

        // Data for the "Areas Served" list view
        $used_areas_served_terms_data = [];
        $rep_group_post_ids_for_terms = wp_list_pluck($all_rep_groups_posts, 'ID'); 

        if (!empty($rep_group_post_ids_for_terms)) {
            $terms_args = [
                'orderby' => 'name',
                'order'   => 'ASC',
                'object_ids' => $rep_group_post_ids_for_terms // Ensure terms are associated with these posts
            ];
            $area_terms = wp_get_object_terms($rep_group_post_ids_for_terms, 'area-served', $terms_args);
            if (!is_wp_error($area_terms) && !empty($area_terms)) {
                $unique_terms_by_id = [];
                foreach($area_terms as $term){
                    if(!isset($unique_terms_by_id[$term->term_id])){ 
                        $svg_id_meta = get_term_meta($term->term_id, '_rep_svg_target_id', true);
                        $unique_terms_by_id[$term->term_id] = [
                            'id'   => $term->term_id,
                            'name' => $term->name,
                            'slug' => $term->slug,
                            'svg_id' => $svg_id_meta ?: '' // Add svg_id, fallback to empty string if not set
                        ];
                    }
                }
                $used_areas_served_terms_data = array_values($unique_terms_by_id);
                usort($used_areas_served_terms_data, function($a, $b) {
                    return strcmp(strtolower($a['name']), strtolower($b['name']));
                });
            }
        }
        
        // Generate HTML for the Rep Groups list using the partial
        ob_start();
        $template_vars_rep_groups = [
            'rep_groups_data' => $all_rep_groups_data, // This is already an array of [id, title]
            'map_instance_id' => $map_instance_id,
        ];
        extract($template_vars_rep_groups);
        include REP_GROUP_PATH . 'templates/frontend/partials/default-rep-groups-list.php';
        $rep_groups_list_html = ob_get_clean();

        // Generate HTML for the Areas Served list using the partial
        ob_start();
        $template_vars_areas_served = [
            'terms_data' => $used_areas_served_terms_data, // Array of [id, name, slug, svg_id]
            'map_instance_id' => $map_instance_id,
            'map_links_data' => $map_region_data, // Pass renamed variable
            'default_region_color' => REP_GROUP_DEFAULT_REGION_COLOR,
        ];
        extract($template_vars_areas_served);
        include REP_GROUP_PATH . 'templates/frontend/partials/default-areas-served-list.php';
        $areas_served_list_html = ob_get_clean();
        
        wp_enqueue_style('rep-group-frontend-map', REP_GROUP_URL . 'assets/css/frontend.css', [], REP_GROUP_VERSION);
        wp_enqueue_script('rep-group-frontend-map-display', REP_GROUP_URL . 'assets/js/frontend-map-display.js', ['jquery', 'wp-util'], REP_GROUP_VERSION, true);
        wp_localize_script('rep-group-frontend-map-display', 'RepMapData_' . str_replace('-', '_', $map_instance_id), [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rep_map_nonce'), 
            'svg_url' => $svg_url, 
            'map_links_data' => $map_region_data, // Use renamed variable
            'map_id' => esc_attr($map_instance_id),
            'default_region_color' => REP_GROUP_DEFAULT_REGION_COLOR,
            'is_interactive' => $is_interactive,
            'rep_groups_list_html' => $rep_groups_list_html, // NEW - Pre-rendered HTML
            'areas_served_list_html' => $areas_served_list_html, // NEW - Pre-rendered HTML
            'default_view_type' => 'rep_groups', 
        ]);

        // Use output buffering to capture the main layout template output
        ob_start();
        $template_path = REP_GROUP_PATH . 'templates/frontend/interactive-map-layout.php';
        if (file_exists($template_path)) {
            // Variables $map_instance_id, $map_type, $svg_url will be available in the template's scope.
            // The $svg_content for inline SVG is also prepared here.
            $svg_content = $this->get_svg_content_from_url($svg_url, $map_instance_id, $map_type);
            include $template_path;
        } else {
            echo '<!-- Rep map layout template not found. -->';
        }
        return ob_get_clean();
    }

    /**
     * AJAX handler to get rep group information for a clicked area (SVG region).
     * Expects 'area_slug' (which is the SVG region ID and term slug).
     */
    public function ajax_get_rep_group_info_for_area() {
        check_ajax_referer('rep_map_nonce', 'nonce');

        $svg_id_clicked = isset($_POST['area_slug']) ? sanitize_text_field($_POST['area_slug']) : ''; // Parameter from JS is svg_id
        // error_log("AJAX: Received svg_id_clicked: " . $svg_id_clicked);

        if (empty($svg_id_clicked)) {
            // error_log("AJAX ERROR: svg_id_clicked is empty.");
            wp_send_json_error(['message' => 'Area SVG identifier not provided.']);
            return;
        }

        // Find the term by its SVG ID meta field
        $term_args = [
            'taxonomy' => 'area-served',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => '_rep_svg_target_id',
                    'value' => $svg_id_clicked,
                    'compare' => '='
                ]
            ],
            'number' => 1 // We expect only one term for a given SVG ID
        ];
        $terms_found = get_terms($term_args);
        // error_log("AJAX: Terms found based on _rep_svg_target_id = " . $svg_id_clicked . ": " . print_r($terms_found, true));

        if (empty($terms_found) || is_wp_error($terms_found)) {
            // if (is_wp_error($terms_found)) error_log("AJAX ERROR: WP_Error finding term: " . $terms_found->get_error_message());
            // else error_log("AJAX ERROR: No terms found for svg_id_clicked: " . $svg_id_clicked);
            wp_send_json_error(['message' => 'Area not found for SVG identifier: ' . esc_html($svg_id_clicked)]);
            return;
        }
        
        $term = $terms_found[0]; // Get the first (and should be only) term
        // error_log("AJAX: Using term: " . print_r($term, true));

        $args = [
            'post_type' => 'rep-group',
            'posts_per_page' => 1, // Expecting only one due to validation
            'tax_query' => [
                [
                    'taxonomy' => 'area-served',
                    'field'    => 'term_id', // Query by the term_id we just found
                    'terms'    => $term->term_id,
                ],
            ],
        ];
        $rep_groups_query = new \WP_Query($args);
        // error_log("AJAX: Rep groups query args: " . print_r($args, true));
        // error_log("AJAX: Rep groups found: " . $rep_groups_query->found_posts);

        if ($rep_groups_query->have_posts()) {
            $rep_groups_query->the_post();
            $post_id = get_the_ID();
            // error_log("AJAX: Found Rep Group Post ID: " . $post_id . " Title: " . get_the_title());
            
            $rep_group_color = get_field('rep_group_map_color', $post_id);
            if (empty($rep_group_color)) {
                $rep_group_color = REP_GROUP_DEFAULT_REGION_COLOR;
            }
            // error_log("AJAX: Rep Group Color: " . $rep_group_color);

            $html = $this->render_rep_group_details_html($post_id, $term->name, $rep_group_color);
            // error_log("AJAX: HTML generated, sending success.");
            wp_send_json_success(['html' => $html, 'term_name' => $term->name, 'color' => $rep_group_color]);
        } else {
            // error_log("AJAX ERROR: No Rep Group found for term_id: " . $term->term_id . " (Term name: " . $term->name . ")");
            wp_send_json_error(['message' => 'No Rep Group found for area: ' . esc_html($term->name)]);
        }
        wp_reset_postdata(); // Should be called after custom WP_Query loops
    }
    
    /**
     * AJAX handler to get rep group details when a list item is clicked.
     * Expects 'rep_group_id'.
     */
    public function ajax_get_rep_group_details_by_id() {
        check_ajax_referer('rep_map_nonce', 'nonce');

        $rep_group_id = isset($_POST['rep_group_id']) ? intval($_POST['rep_group_id']) : 0;

        if (empty($rep_group_id)) {
            wp_send_json_error(['message' => 'Rep Group ID not provided.']);
            return;
        }

        $post = get_post($rep_group_id);
        if (!$post || $post->post_type !== 'rep-group') {
            wp_send_json_error(['message' => 'Invalid Rep Group ID.']);
            return;
        }

        $rep_group_color = get_field('rep_group_map_color', $rep_group_id);
        if (empty($rep_group_color)) {
            $rep_group_color = defined('REP_GROUP_DEFAULT_REGION_COLOR') ? REP_GROUP_DEFAULT_REGION_COLOR : '#CCCCCC';
        }

        // Fetch Area Served terms for this Rep Group
        $areas_served_terms = wp_get_object_terms($rep_group_id, 'area-served');
        $area_name_context = 'Not specified'; // Default if no areas
        if (!empty($areas_served_terms) && !is_wp_error($areas_served_terms)) {
            $area_names = wp_list_pluck($areas_served_terms, 'name');
            $area_name_context = implode(', ', $area_names);
        } else if (is_wp_error($areas_served_terms)) {
            // Optionally log the error: error_log('Error fetching terms for rep group ' . $rep_group_id . ': ' . $areas_served_terms->get_error_message());
            $area_name_context = 'Error fetching areas';
        }

        $html_content = $this->render_rep_group_details_html($rep_group_id, $area_name_context, $rep_group_color);

        if (empty($html_content)) {
            wp_send_json_error(['message' => 'Could not generate rep group details.']);
            return;
        }

        wp_send_json_success([
            'html' => $html_content,
            'color' => $rep_group_color // Add the color to the response
        ]);
    }

    /**
     * Helper function to render the HTML for a single Rep Group's details.
     * Used by AJAX handlers.
     *
     * @param int    $post_id The Rep Group post ID.
     * @param string $area_name_context The name of the area/context for the title.
     * @param string $area_color The color associated with this area/rep group for styling.
     * @return string HTML content.
     */
    private function render_rep_group_details_html($post_id, $area_name_context, $area_color) {
        // Prepare variables for the template
        // $post_id, $area_name_context, and $area_color are passed as arguments.
        // $shortcode_instance will be $this within the template.

        ob_start();
        
        // Make variables available to the template file
        // Note: WordPress's get_template_part or similar functions often handle variable scoping implicitly.
        // For a direct include, explicitly setting them or ensuring they are in the current scope is needed.
        // However, PHP's include will inherit the current variable scope.
        
        // We need to ensure the template can call $this->render_rep_contact_info
        // So, we'll pass $this as $shortcode_instance to the template.
        $shortcode_instance = $this; 

        $template_path = REP_GROUP_PATH . 'templates/frontend/partials/rep-group-details.php';

        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Fallback or error message if template is not found
            echo '<p>Error: Rep group details template not found.</p>';
        }

        return ob_get_clean();
    }
    
    /**
     * Generates HTML list of all rep groups for the default panel in map view.
     * Includes data attributes for AJAX loading.
     */
    // private function get_all_rep_groups_list_html($map_instance_id) { ... } // REMOVED as JS will handle this

    private function get_svg_content_from_url($url, $map_instance_id, $map_type) {
        if (empty($url)) {
            return '<!-- Rep Map: SVG URL not provided to get_svg_content_from_url. -->';
        }

        $svg_content_raw = '';
        // Try to get path from URL if it's a local file
        $svg_path = str_replace(content_url(), WP_CONTENT_DIR, $url);

        if (filter_var($url, FILTER_VALIDATE_URL) && strpos($svg_path, WP_CONTENT_DIR) !== 0) {
            // It's a remote URL, or a local URL not in WP_CONTENT_DIR (less common for theme/plugin assets)
            if (ini_get('allow_url_fopen')) {
                $context = stream_context_create(["ssl" => ["verify_peer" => false, "verify_peer_name" => false]]);
                $svg_content_raw = @file_get_contents($url, false, $context);
            } else {
                error_log('[RepGroup Plugin] Cannot fetch remote SVG: allow_url_fopen is disabled. URL: ' . $url);
                return '<!-- Rep Map: allow_url_fopen is disabled, cannot fetch remote SVG. -->';
            }
        } elseif (file_exists($svg_path)) {
            // It's a local file path within the WordPress installation
            $svg_content_raw = @file_get_contents($svg_path);
        } else {
            error_log('[RepGroup Plugin] SVG file not found at path: ' . $svg_path . ' (derived from URL: ' . $url . ')');
            return '<!-- Rep Map: SVG file not found at path: ' . esc_html($svg_path) . ' -->';
        }

        if (empty($svg_content_raw)) {
            error_log('[RepGroup Plugin] Failed to retrieve SVG content from URL/path: ' . $url);
            return '<!-- Rep Map: Unable to load SVG content from ' . esc_html($url) . ' -->';
        }

        // Clean and modify SVG content
        $trimmed_content = trim($svg_content_raw);
        $content_to_check = preg_replace('/^<\?xml[^>]*\?>\s*/is', '', $trimmed_content);
        $content_to_check = preg_replace('/^<!DOCTYPE[^>]*>\s*/is', '', $content_to_check); // Also remove DOCTYPE
        $content_to_check = preg_replace('/^<!--.*?-->\s*/is', '', $content_to_check);
        $content_to_check = preg_replace('/^<!--.*?-->\s*/is', '', $content_to_check); // Second pass for adjacent comments
        $content_to_check = trim($content_to_check);

        if (stripos($content_to_check, '<svg') === 0) {
            // Use map_instance_id for the main SVG element ID to ensure uniqueness if multiple maps are on a page
            // The JS finds it by class anyway, but unique IDs are good practice.
            $svg_id_attr = 'svg-object-' . esc_attr($map_instance_id); 
            $svg_class_attr = 'rep-group-map-svg-object';
            
            $modified_svg = preg_replace(
                '/<svg/',
                sprintf('<svg id="%s" class="%s"', esc_attr($svg_id_attr), esc_attr($svg_class_attr)),
                $trimmed_content, // Use $trimmed_content which has prefixes, apply to the first <svg>
                1
            );
            return $modified_svg;
        } else {
            error_log('[RepGroup Plugin] Loaded content does not appear to be a valid SVG. URL: ' . $url);
            return '<!-- Rep Map: Loaded content is not a valid SVG or has unexpected prefixes. URL: ' . esc_html($url) . ' -->';
        }
    }

} // End Class