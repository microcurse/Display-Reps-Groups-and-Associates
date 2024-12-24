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
            $output .= '<ul class="rep-list">';
            while (have_rows('rep_associates', $post_id)) {
                the_row();
                $output .= '<li class="rep-item">';

                $name = get_sub_field('name');
                if ($name) {
                    $output .= sprintf('<h3>%s</h3>', esc_html($name));
                }

                $territory = get_sub_field('territory_served');
                if ($territory) {
                    $output .= sprintf('<p>Territory: %s</p>', esc_html($territory));
                }

                if (have_rows('rep_phone_numbers')) {
                    while (have_rows('rep_phone_numbers')) {
                        the_row();
                        $phone_type = get_sub_field('rep_phone_type');
                        $phone_number = get_sub_field('rep_phone_number');
                        if ($phone_type && $phone_number) {
                            $output .= sprintf(
                                '<p>%s: <a href="tel:%s">%s</a></p>',
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
                        '<p><a href="mailto:%s">%s</a></p>',
                        esc_attr($email),
                        esc_html($email)
                    );
                }

                $output .= '</li>';
            }
            $output .= '</ul>';
        }

        $output .= '</section>';
        return $output;
    }
} 