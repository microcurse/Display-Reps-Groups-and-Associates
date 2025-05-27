<?php
namespace RepGroup;

class Shortcode {
    const DEFAULT_REGION_COLOR = '#CCCCCC'; // Default color for regions on frontend map

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
        // Debug output
        error_log('Rendering rep group: ' . $post_id);
        
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
        $output .= '<h2 class="rep-section-title">Rep Associates</h2>';
        $output .= '<div class="rep-card-grid">';
        
        while (have_rows('rep_associates', $post_id)) {
            the_row();
            $output .= $this->render_rep_card();
        }
        
        $output .= '</div></div>';
        return $output;
    }

    /**
     * Helper method to render individual rep card
     */
    private function render_rep_card() {
        $output = '<div class="rep-card">';

        $name = get_sub_field('name');
        if ($name) {
            $output .= sprintf('<h3 class="rep-name">%s</h3>', esc_html($name));
        }

        $areas_served_field = get_sub_field('areas_served'); // Assuming this is the new field name for taxonomy
        if ($areas_served_field) {
            $territory_name = is_array($areas_served_field) ? $areas_served_field['name'] : 
                             (is_object($areas_served_field) ? $areas_served_field->name : $areas_served_field);
            $output .= sprintf('<p class="rep-territory"><strong>Area Served:</strong> %s</p>', 
                esc_html($territory_name));
        }

        // Rep Associate Address
        $address = get_sub_field('address');
        if ($address) {
            $address_text = is_array($address) ? implode(', ', array_filter($address)) : $address;
            $output .= '<div class="rep-address">';
            $output .= '<strong>Address:</strong>';
            $output .= sprintf('<p>%s</p>', esc_html($address_text));
            $output .= '</div>';
        }

        $output .= '<div class="rep-contact-info">';
        $output .= $this->render_rep_contact_info();
        $output .= '</div>';

        $output .= '</div>';
        return $output;
    }

    /**
     * Helper method to render rep contact info
     */
    private function render_rep_contact_info() {
        $output = '';

        if (have_rows('rep_phone_numbers')) {
            while (have_rows('rep_phone_numbers')) {
                the_row();
                $phone_type = get_sub_field('rep_phone_type');
                $phone_number = get_sub_field('rep_phone_number');
                
                $phone_type_text = is_array($phone_type) ? $phone_type['label'] : $phone_type;
                
                if ($phone_type && $phone_number) {
                    $output .= sprintf(
                        '<p class="rep-phone"><strong>%s:</strong> <a href="tel:%s">%s</a></p>',
                        esc_html($phone_type_text),
                        esc_attr($phone_number),
                        esc_html($phone_number)
                    );
                }
            }
        }

        $email = get_sub_field('email');
        if ($email) {
            $output .= sprintf(
                '<p class="rep-email"><strong>Email:</strong> <a href="mailto:%s">%s</a></p>',
                esc_attr($email),
                esc_html($email)
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
                $map_links[$svg_id] = ['term_id' => $data, 'color' => self::DEFAULT_REGION_COLOR];
            }
            if (!isset($data['color'])){
                 $map_links[$svg_id]['color'] = self::DEFAULT_REGION_COLOR;
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
                'default_color' => self::DEFAULT_REGION_COLOR,
                'ajax_url'      => admin_url('admin-ajax.php'),
                'nonce'         => wp_create_nonce('rep_group_frontend_map_nonce') // Nonce for frontend AJAX
            ];
            // Ensure a unique JS object name for each map instance data
            wp_localize_script('rep-group-frontend-map-js', 'RepMapData_' . str_replace('-', '_', $map_instance_id), $localized_data);
        }

        return $output;
    }
}