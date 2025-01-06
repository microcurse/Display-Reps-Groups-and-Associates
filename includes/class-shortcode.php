<?php
namespace RepGroup;

class Shortcode {
    private $map_manager;

    public function __construct() {
        $this->map_manager = new Map_Manager();
        add_shortcode('rep_group_map', [$this, 'render_map_shortcode']);
        add_shortcode('rep_group_display', [$this, 'render_rep_group_display']);
    }

    /**
     * Render the map shortcode
     */
    public function render_map_shortcode($atts) {
        $map_svg_id = get_option('rep_group_map_svg_id');
        
        if (!$map_svg_id) {
            return '<p>Map not configured. Please upload a map in the admin panel.</p>';
        }
        
        ob_start();
        require REP_GROUP_PATH . 'templates/frontend/map.php';
        return ob_get_clean();
    }

    /**
     * Render the rep group display shortcode
     */
    public function render_rep_group_display($atts) {
        $attributes = shortcode_atts([
            'state' => '',
            'limit' => -1,
            'id' => null // Added support for single rep group display
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

        if (!empty($attributes['state'])) {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => 'area-served',
                    'field' => 'slug',
                    'terms' => strtolower($attributes['state'])
                ]
            ];
        }

        $rep_groups = new \WP_Query($query_args);
        
        ob_start();
        include REP_GROUP_PATH . 'templates/frontend/archive-rep-group.php';
        return ob_get_clean();
    }

    /**
     * Render a single rep group (moved from Renderer class)
     */
    private function render_single_rep_group($post_id) {
        $output = '<section class="rep-group">';
        
        // Area Served
        $area_served = get_field('rg_area_served', $post_id);
        if ($area_served) {
            $output .= '<div class="area-served">';
            $output .= '<strong>Area Served:</strong> ';
            $output .= sprintf('<span>%s</span>', esc_html($area_served));
            $output .= '</div>';
        }

        // Address
        $address = get_field('rg_address_container', $post_id);
        if ($address) {
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

        $territory = get_sub_field('territory_served');
        if ($territory) {
            $output .= sprintf('<p class="rep-territory"><strong>Territory:</strong> %s</p>', esc_html($territory));
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
                if ($phone_type && $phone_number) {
                    $output .= sprintf(
                        '<p class="rep-phone"><strong>%s:</strong> <a href="tel:%s">%s</a></p>',
                        esc_html($phone_type),
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
}