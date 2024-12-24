<?php
namespace RepGroup;

class Renderer {
    public static function render_rep_group($post_id) {
        $output = '<section class="rep-group">';
        
        $area_served = get_field('rg_area_served', $post_id);
        if ($area_served) {
            $output .= '<div class="area-served">';
            $output .= '<strong>Area Served:</strong> ';
            $output .= sprintf('<span>%s</span>', esc_html($area_served));
            $output .= '</div>';
        }

        $address = get_field('rg_address_container', $post_id);
        if ($address) {
            $output .= '<div class="address">';
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
            $output .= '</div>'; // Close address-content
            $output .= '</div>'; // Close address
        }

        if (have_rows('rep_associates', $post_id)) {
            $output .= '<div class="rep-associates-section">';
            $output .= '<h2 class="rep-section-title">Rep Associates</h2>';
            $output .= '<div class="rep-card-grid">';
            
            while (have_rows('rep_associates', $post_id)) {
                the_row();
                $output .= '<div class="rep-card">';

                $name = get_sub_field('name');
                if ($name) {
                    $output .= sprintf('<h3 class="rep-name">%s</h3>', esc_html($name));
                }

                $territory = get_sub_field('territory_served');
                if ($territory) {
                    $output .= sprintf('<p class="rep-territory"><strong>Territory:</strong> %s</p>', esc_html($territory));
                }

                $output .= '<div class="rep-contact-info">';
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
                $output .= '</div>'; // Close rep-contact-info

                $output .= '</div>'; // Close rep-card
            }
            $output .= '</div>'; // Close rep-card-grid
            $output .= '</div>'; // Close rep-associates-section
        }

        $output .= '</section>';
        return $output;
    }
} 