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
        $output = '<section class="rep-group">';
        
        // Get Area Served from taxonomy
        $areas = get_the_terms($post_id, 'area-served');
        if ($areas && !is_wp_error($areas)) {
            $output .= '<div class="area-served">';
            $output .= '<strong>Area Served:</strong> ';
            $area_names = array_map(function($term) {
                return esc_html($term->name);
            }, $areas);
            $output .= sprintf('<span>%s</span>', implode(', ', $area_names));
            $output .= '</div>';
        }

        // Address Container
        $address = get_field('rg_address_container', $post_id);
        if ($address && is_array($address)) {
            $output .= $this->render_address($address);
        }

        // Rep Associates
        if (have_rows('rep_associates', $post_id)) {
            $output .= $this->render_rep_associates($post_id);
        }

        $output .= '</section>';
        return $output;
    }

    /**
     * Helper method to render address
     */
    private function render_address($address) {
        $output = '<div class="address">';
        $output .= '<strong>Address:</strong>';
        $output .= '<div class="address-content">';
        
        if (!empty($address['rg_address_1'])) {
            $output .= sprintf('<p>%s</p>', esc_html($address['rg_address_1']));
        }
        if (!empty($address['rg_address_2'])) {
            $output .= sprintf('<p>%s</p>', esc_html($address['rg_address_2']));
        }
        if (!empty($address['rg_city']) || !empty($address['rg_state']) || !empty($address['rg_zip_code'])) {
            $output .= sprintf(
                '<p>%s%s%s</p>',
                !empty($address['rg_city']) ? esc_html($address['rg_city']) . ', ' : '',
                !empty($address['rg_state']) ? esc_html($address['rg_state']) . ' ' : '',
                !empty($address['rg_zip_code']) ? esc_html($address['rg_zip_code']) : ''
            );
        }
        
        $output .= '</div></div>';
        return $output;
    }

    /**
     * Helper method to render rep associates
     */
    private function render_rep_associates($post_id) {
        $output = '<div class="rep-associates-section">';

        $output .= '<div class="rep-card-grid">';
        
        if (have_rows('rep_associates', $post_id)) { // Check before looping
            while (have_rows('rep_associates', $post_id)) {
                the_row();
                $user_id = get_sub_field('rep_user');
                $user_data = $user_id ? get_userdata($user_id) : null;
                $email_override = get_sub_field('rep_contact_email_override');
                $phone_override = get_sub_field('rep_contact_phone_override');
                $associate_specific_areas = get_sub_field('associate_specific_areas_text'); // New text field

                $output .= $this->render_rep_card($user_data, $email_override, $phone_override, $associate_specific_areas);
            }
        }
        
        $output .= '</div></div>';
        return $output;
    }

    /**
     * Helper method to render individual rep card
     */
    private function render_rep_card($user_data, $email_override, $phone_override, $associate_specific_areas_text) {
        $output = '<div class="rep-card">';

        if ($user_data) {
            $output .= sprintf('<h3 class="rep-name">%s</h3>', esc_html($user_data->display_name));
        } else {
            $output .= '<h3 class="rep-name">Associate Name Not Found</h3>';
        }

        // Display associate specific areas text
        if (!empty($associate_specific_areas_text)) {
            $output .= sprintf('<p class="rep-territory"><strong>Specific Areas:</strong> %s</p>', esc_html($associate_specific_areas_text));
        }

        $output .= '<div class="rep-contact-info">';
        $output .= $this->render_rep_contact_info($user_data, $email_override, $phone_override);
        $output .= '</div>';

        $output .= '</div>';
        return $output;
    }

    /**
     * Helper method to render rep contact info
     * Now accepts user_data and overrides
     */
    private function render_rep_contact_info($user_data, $email_override, $phone_override) {
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
        $output = sprintf(
            '<div id="%s" class="rep-group-map-interactive-area %s-map-interactive-area">',
            esc_attr($map_instance_id),
            esc_attr($map_type)
        );
        // Two-column layout: Info Panel (Left), Map (Right)
        $output .= '<div class="rep-map-info-column">';
        // Default content for the info column (e.g., list of all rep groups)
        $output .= '<div class="rep-map-default-content panel-active">';
        $output .= '<h4>All Rep Groups</h4>';
        $output .= $this->get_all_rep_groups_list_html($map_instance_id); // Pass map_instance_id
        $output .= '</div>';
        // Placeholder for detailed rep group info when an area is clicked
        $output .= '<div class="rep-map-details-content panel-hidden">';
        $output .= '<a href="#" class="back-to-map-default" role="button">&laquo; Back to Overview</a>';
        $output .= '<div class="rep-group-info-target"></div>';
        $output .= '</div>'; // End rep-map-details-content
        $output .= '</div>'; // End rep-map-info-column

        $output .= '<div class="rep-map-svg-column">';
        $output .= sprintf(
            '<object id="rep-map-svg-%s" class="rep-group-map-svg-object" type="image/svg+xml" data="%s" aria-label="%s map"></object>',
            esc_attr($map_type),
            esc_url($svg_url),
            esc_attr(ucfirst($map_type))
        );
        $output .= '</div>'; // End rep-map-svg-column
        $output .= '</div>'; // End rep-group-map-interactive-area

        // Prepare data for frontend JavaScript
        $area_colors_and_terms = [];
        $rep_groups_query_args = [
            'post_type' => 'rep-group',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ];
        $rep_groups = get_posts($rep_groups_query_args);

        foreach ($rep_groups as $rep_group) {

            $color = get_field('rep_group_map_color', $rep_group->ID);
            if (empty($color)) {
                $color = REP_GROUP_DEFAULT_REGION_COLOR; // Use defined default color
            }
            $terms = get_the_terms($rep_group->ID, 'area-served');

            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {

                    // USE THE CUSTOM META FIELD FOR SVG ID
                    $svg_id_meta = get_term_meta($term->term_id, '_rep_svg_target_id', true);
                    

                    if (!empty($svg_id_meta)) {
                        $svg_id_key = ltrim($svg_id_meta, '#');
                        $area_colors_and_terms[$svg_id_key] = [
                            'color' => $color,
                            'term_id' => $term->term_id,
                            'term_name' => $term->name,
                            'term_slug' => $term->slug
                        ];
                    } else {
                        // Fallback or skip if SVG ID meta is not set for a term
                    }
                }
            }
        }
        
        wp_enqueue_style('rep-group-frontend-map', REP_GROUP_URL . 'assets/css/frontend.css', [], REP_GROUP_VERSION);
        wp_enqueue_script('rep-group-frontend-map', REP_GROUP_URL . 'assets/js/frontend-map-display.js', ['jquery', 'wp-util'], REP_GROUP_VERSION, true);
        wp_localize_script('rep-group-frontend-map', 'RepMapData_' . str_replace('-', '_', $map_instance_id), [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rep_map_nonce'),
            'svg_url' => $svg_url,
            'area_data' => $area_colors_and_terms, // New: term slug -> {color, term_id, term_name}
            'map_id' => esc_attr($map_instance_id),
            'default_region_color' => REP_GROUP_DEFAULT_REGION_COLOR,
            'is_interactive' => $is_interactive,
        ]);

        return $output;
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
     * AJAX handler to get rep group details by ID (for list item clicks).
     */
    public function ajax_get_rep_group_details_by_id() {
        check_ajax_referer('rep_map_nonce', 'nonce');

        $rep_group_id = isset($_POST['rep_group_id']) ? absint($_POST['rep_group_id']) : 0;

        if (empty($rep_group_id) || get_post_type($rep_group_id) !== 'rep-group') {
            wp_send_json_error(['message' => 'Invalid Rep Group ID.']);
            return;
        }
        
        // For display purposes, we might not have a single "clicked area" term name here.
        // We can fetch all areas served by this rep group.
        $area_terms = get_the_terms($rep_group_id, 'area-served');
        $area_names_display = 'Multiple Areas'; // Default
        if ($area_terms && !is_wp_error($area_terms)) {
            $area_names = wp_list_pluck($area_terms, 'name');
            if (count($area_names) === 1) {
                 $area_names_display = $area_names[0];   
            } elseif (count($area_names) > 1) {
                 $area_names_display = implode(', ', array_slice($area_names, 0, 2)) . (count($area_names) > 2 ? '...' : '');
            }
        }


        $rep_group_color = get_field('rep_group_map_color', $rep_group_id) ?: REP_GROUP_DEFAULT_REGION_COLOR;
        $html = $this->render_rep_group_details_html($rep_group_id, $area_names_display, $rep_group_color);
        wp_send_json_success(['html' => $html, 'term_name' => $area_names_display, 'color' => $rep_group_color]);
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
        $output = '';
        $title = get_the_title($post_id);
        $logo_url = get_the_post_thumbnail_url($post_id, 'medium');

        // Header with background color
        $output .= sprintf(
            '<div class="rep-group-details-header" style="background-color: %s;">',
            esc_attr($area_color)
        );
        if ($logo_url) {
            $output .= sprintf('<img src="%s" alt="%s logo" class="rep-group-logo-map">', esc_url($logo_url), esc_attr($title));
        }
        // Rep Group Title and Area Context - Using h3 for Rep Group title, p for context
        $output .= sprintf(
            '<h3 class="rep-group-title-map">%s</h3><p class="area-context-map">Areas Served: <span class="area-name-highlighted">%s</span></p>',
            esc_html($title),
            esc_html($area_name_context)
        );
        $output .= '</div>'; // End rep-group-details-header

        $output .= '<div class="rep-group-details-body">';

        // Address
        $address_data = get_field('rg_address_container', $post_id);
        if ($address_data && is_array($address_data)) {
            $output .= '<div class="rep-group-contact-section address-details">';
            $output .= '<h4><ion-icon name="location-outline"></ion-icon> Address</h4>';
            $full_address = '';
            if (!empty($address_data['rg_address_1'])) $full_address .= esc_html($address_data['rg_address_1']) . '<br>';
            if (!empty($address_data['rg_address_2'])) $full_address .= esc_html($address_data['rg_address_2']) . '<br>';
            $city_state_zip = [];
            if (!empty($address_data['rg_city'])) $city_state_zip[] = esc_html($address_data['rg_city']);
            if (!empty($address_data['rg_state'])) $city_state_zip[] = esc_html($address_data['rg_state']);
            if (!empty($address_data['rg_zip_code'])) $city_state_zip[] = esc_html($address_data['rg_zip_code']);
            if (!empty($city_state_zip)) $full_address .= implode(', ', $city_state_zip);
            
            if (!empty($full_address)) {
                $output .= sprintf('<p>%s</p>', $full_address);
            } else {
                $output .= '<p>Address not available.</p>';
            }
            $output .= '</div>';
        }
        
        // Phone Numbers (Repeater: rg_phone_numbers)
        if (have_rows('rg_phone_numbers', $post_id)) {
            $output .= '<div class="rep-group-contact-section phone-details">';
            $output .= '<h4><ion-icon name="call-outline"></ion-icon> Phone Numbers</h4>';
            while (have_rows('rg_phone_numbers', $post_id)) {
                the_row();
                $phone_type = get_sub_field('rep_phone_type');
                $phone_number = get_sub_field('rep_phone_number');
                if ($phone_number) {
                    $output .= sprintf(
                        '<p><strong>%s:</strong> <a href="tel:%s">%s</a></p>',
                        esc_html($phone_type ?: 'Phone'),
                        esc_attr(preg_replace('/[^0-9+]/', '', $phone_number)),
                        esc_html($phone_number)
                    );
                }
            }
            $output .= '</div>';
        }

        // Email (Single field: rg_email)
        $email = get_field('rg_email', $post_id);
        if ($email && is_email($email)) {
            $output .= '<div class="rep-group-contact-section email-details">';
            $output .= '<h4><ion-icon name="mail-outline"></ion-icon> Email</h4>';
            $output .= sprintf(
                '<p><a href="mailto:%s">%s</a></p>',
                esc_attr($email),
                esc_html($email)
            );
            $output .= '</div>';
        }

        // Website (Single field: rg_website)
        $website = get_field('rg_website', $post_id);
        if ($website) {
            $output .= '<div class="rep-group-contact-section website-details">';
            $output .= '<h4><ion-icon name="globe-outline"></ion-icon> Website</h4>';
            $output .= sprintf(
                '<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
                esc_url($website),
                esc_html($website) // Or a more friendly display like the domain name
            );
            $output .= '</div>';
        }

        // Rep Associates
        if (have_rows('rep_associates', $post_id)) {
            $output .= '<div class="rep-associates-map-display rep-group-contact-section">'; // Added rep-group-contact-section for consistent styling
            $output .= '<h4><ion-icon name="people-outline"></ion-icon> Team</h4>';
            while (have_rows('rep_associates', $post_id)) {
                the_row();
                $user_id = get_sub_field('rep_user');
                $user_data = $user_id ? get_userdata($user_id) : null;
                $output .= '<div class="associate-card">'; // Start associate card
                
                if ($user_data) {
                    $output .= sprintf('<h5>%s</h5>', esc_html($user_data->display_name));
                } else {
                    $output .= '<h5>Associate</h5>';
                }

                // Associate Specific Areas Text
                $associate_specific_areas = get_sub_field('associate_specific_areas_text');
                if (!empty($associate_specific_areas)) {
                    $output .= sprintf('<p class="associate-areas"><em>%s</em></p>', esc_html($associate_specific_areas));
                }

                // Contact Info for Associate
                $email_override = get_sub_field('rep_contact_email_override');
                $phone_override = get_sub_field('rep_contact_phone_override');
                
                $contact_info_html = $this->render_rep_contact_info($user_data, $email_override, $phone_override);
                if (!empty(trim($contact_info_html))) {
                     $output .= '<div class="associate-contact-map">' . $contact_info_html . '</div>';
                }

                $output .= '</div>'; // End associate card
            }
            $output .= '</div>'; // End rep-associates-map-display
        }
        $output .= '</div>'; // End rep-group-details-body

        return $output;
    }
    
    /**
     * Generates HTML list of all rep groups for the default panel in map view.
     * Includes data attributes for AJAX loading.
     */
    private function get_all_rep_groups_list_html($map_instance_id) {
        $output = '<ul class="rep-group-list-map-default default-rep-group-list">'; // Added default-rep-group-list class
        $rep_groups_query_args = [
            'post_type' => 'rep-group',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ];
        $all_rep_groups = get_posts($rep_groups_query_args);

        if (empty($all_rep_groups)) {
            $output .= '<li>No rep groups found.</li>';
        } else {
            foreach ($all_rep_groups as $rep_group) {
                $output .= sprintf(
                    '<li data-rep-group-id="%d">', // Data attribute for JS
                    esc_attr($rep_group->ID)
                );
                $output .= sprintf(
                    '<a href="#" class="rep-group-list-item-link" data-map-instance-id="%s">',
                    esc_attr($map_instance_id) 
                );
                $output .= esc_html(get_the_title($rep_group->ID));
                $output .= '</a>';
                $output .= '</li>';
            }
        }
        $output .= '</ul>';
        return $output;
    }

} // End Class