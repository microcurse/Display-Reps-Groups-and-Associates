<?php
/**
 * Plugin Name: Rep Group Shortcode
 * Description: A plugin to display Rep Group information using a shortcode.
 * Version: 1.0
 * Author: Marc Maninang
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Register the shortcode
add_shortcode('rep_group', 'render_rep_group_shortcode');

/**
 * Render the rep group shortcode
 */
function render_rep_group_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => 0,
    ], $atts);

    if (empty($atts['id'])) {
        return '';
    }

    return render_rep_group($atts['id']);
}

/**
 * Render the rep group content
 */
function render_rep_group($post_id) {
    $output = '<section class="mapplic-lightbox__contact-section">';
    
    // Area served
    $area_served = get_field('rg_area_served', $post_id);
    if ($area_served) {
        $output .= sprintf(
            '<h2 class="mapplic-lightbox__area-served">Area Served: %s</h2>',
            esc_html($area_served)
        );
    }

    // Featured image
    if (has_post_thumbnail($post_id)) {
        $featured_image = get_the_post_thumbnail_url($post_id, 'full');
        $title = get_the_title($post_id);
        $output .= sprintf(
            '<figure><img src="%s" alt="%s" width="217" height="auto" /></figure>',
            esc_url($featured_image),
            esc_attr($title)
        );
    }

    // Company name and address
    $output .= sprintf(
        '<h3 class="mapplic-lightbox__company-name">%s</h3>',
        esc_html(get_the_title($post_id))
    );
    
    $output .= '<address class="mapplic-lightbox__address">';
    
    // Address Container
    $address_container = get_field('rg_address_container', $post_id);
    if ($address_container) {
        if (!empty($address_container['rg_address_1'])) {
            $output .= sprintf('<p>%s</p>', esc_html($address_container['rg_address_1']));
        }
        if (!empty($address_container['rg_address_2'])) {
            $output .= sprintf('<p>%s</p>', esc_html($address_container['rg_address_2']));
        }

        $location_parts = array_filter([
            $address_container['rg_city'] ?? '',
            $address_container['rg_state'] ?? '',
            $address_container['rg_zip_code'] ?? ''
        ]);
        if (!empty($location_parts)) {
            $output .= sprintf('<p>%s</p>', esc_html(implode(', ', $location_parts)));
        }
    }

    $output .= '</address>';

    // Rep Associates
    if (have_rows('rep_associates', $post_id)) {
        $output .= '<ul class="mapplic-lightbox__contact-details">';
        while (have_rows('rep_associates', $post_id)) {
            the_row();
            $output .= '<li class="mapplic-lightbox__contact-details-item">';
            
            $rep_name = get_sub_field('name');
            if ($rep_name) {
                $output .= sprintf('<p>%s</p>', esc_html($rep_name));
            }

            $territory = get_sub_field('territory_served');
            if ($territory) {
                $output .= sprintf(
                    '<p><strong>Territory:</strong> %s</p>',
                    esc_html($territory)
                );
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

// Add filter to display rep group content on single post
add_filter('the_content', function($content) {
    if (!is_singular('rep-group') || !in_the_loop()) {
        return $content;
    }
    return $content . render_rep_group(get_the_ID());
});