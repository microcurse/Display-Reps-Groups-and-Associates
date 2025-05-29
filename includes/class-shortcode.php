<?php
namespace RepGroup;

class Shortcode {
    public function __construct() {
        add_shortcode('rep_group_display', [$this, 'render_rep_group_display']);
        add_shortcode('rep_map', [$this, 'render_rep_map']);
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
        
        while (have_rows('rep_associates', $post_id)) { // ACF's have_rows() can be called multiple times
            the_row();
            // Get user data and overrides for the current associate row
            $user_id = get_sub_field('rep_user');
            $user_data = $user_id ? get_userdata($user_id) : null;
            $email_override = get_sub_field('rep_contact_email_override');
            $phone_override = get_sub_field('rep_contact_phone_override');
            $areas_served = get_sub_field('areas_served');

            $output .= $this->render_rep_card($user_data, $email_override, $phone_override, $areas_served);
        }
        
        $output .= '</div></div>';
        return $output;
    }

    /**
     * Helper method to render individual rep card
     */
    private function render_rep_card($user_data, $email_override, $phone_override, $areas_served) {
        $output = '<div class="rep-card">';

        if ($user_data) {
            $output .= sprintf('<h3 class="rep-name">%s</h3>', esc_html($user_data->display_name));
        } else {
            $output .= '<h3 class="rep-name">Associate Name Not Found</h3>';
        }

        $area_names_to_display = [];
        // Ensure $areas_served is an array of term IDs or objects as configured in ACF
        if (is_array($areas_served) && !empty($areas_served)) {
            foreach ($areas_served as $term_id_or_object) {
                $term = null;
                if (is_object($term_id_or_object) && isset($term_id_or_object->term_id)) {
                     $term = $term_id_or_object; // It's already a WP_Term object
                } elseif (is_numeric($term_id_or_object)) {
                    $term = get_term(intval($term_id_or_object), 'area-served');
                }

                if ($term instanceof \WP_Term && !is_wp_error($term)) {
                    $area_names_to_display[] = esc_html($term->name);
                }
            }
        }
        if (!empty($area_names_to_display)) {
            $output .= sprintf('<p class="rep-territory"><strong>Serves:</strong> %s</p>', implode(', ', $area_names_to_display));
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
        $map_links_option_name = ($map_type === 'local') ? 'rep_group_local_map_links' : 'rep_group_international_map_links';
        $map_links = get_option($map_links_option_name, []);

        foreach ($map_links as $svg_id => $data) {
            if (!is_array($data)) {
                $map_links[$svg_id] = ['term_id' => $data, 'color' => REP_GROUP_DEFAULT_REGION_COLOR];
            }
            if (!isset($map_links[$svg_id]['color'])){
                 $map_links[$svg_id]['color'] = REP_GROUP_DEFAULT_REGION_COLOR;
            }
        }

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
            $map_instance_id,
            esc_attr($map_type)
        );

        // --- Default content for info column: List of all rep groups ---
        $default_content_html = '<p>' . __('No Rep Groups found.', 'rep-group') . '</p>';
        $all_rep_groups_args = [
            'post_type' => 'rep-group',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ];
        $all_rep_groups_query = new \WP_Query($all_rep_groups_args);
        if ($all_rep_groups_query->have_posts()) {
            $default_content_html = '<h3 class="default-list-title">' . __('All Rep Groups', 'rep-group') . '</h3>';
            $default_content_html .= '<ul class="default-rep-group-list">';
            while ($all_rep_groups_query->have_posts()) {
                $all_rep_groups_query->the_post();
                $rep_group_id = get_the_ID();
                $default_content_html .= sprintf('<li data-rep-group-id="%s"><a href="#" class="rep-group-list-item-link">%s</a></li>', 
                    esc_attr($rep_group_id), 
                    get_the_title()
                );
            }
            $default_content_html .= '</ul>';
            wp_reset_postdata();
        }
        // --- End default content generation ---

        $output .= '<div class="rep-map-info-column">';
        $output .= '  <div class="rep-map-default-content panel-active">';
        $output .= $default_content_html;
        $output .= '  </div>';
        $output .= '  <div class="rep-map-details-content panel-hidden">';
        $output .= '    <a href="#" class="back-to-map-default" role="button">&laquo; ' . __('Back to Overview', 'rep-group') . '</a>';
        $output .= '    <div class="rep-group-info-target"></div>';
        $output .= '  </div>';
        $output .= '</div>';

        $output .= '<div class="rep-map-svg-column">';
        $output .= sprintf(
            '<object type="image/svg+xml" data="%s" class="rep-group-map-svg"></object>',
            esc_url($svg_url)
        );
        $output .= '</div>';

        $output .= '</div>'; // End #map_instance_id

        if ($is_interactive) {
            if (!wp_script_is('rep-group-frontend-map-js', 'enqueued')) {
                wp_enqueue_script(
                    'rep-group-frontend-map-js',
                    REP_GROUP_URL . 'assets/js/frontend-map-display.js',
                    ['jquery'],
                    REP_GROUP_VERSION,
                    true
                );
            }
            $localized_data = [
                'map_id'        => $map_instance_id, // Pass the main container ID
                'svg_url'       => esc_url($svg_url),
                'map_links'     => $map_links,
                'default_color' => REP_GROUP_DEFAULT_REGION_COLOR,
                'ajax_url'      => admin_url('admin-ajax.php'),
                'nonce'         => wp_create_nonce('rep_group_frontend_map_nonce') // Nonce for frontend AJAX
            ];
            // Ensure a unique JS object name for each map instance data
            wp_localize_script('rep-group-frontend-map-js', 'RepMapData_' . str_replace('-', '_', $map_instance_id), $localized_data);
        }

        return $output;
    }
}