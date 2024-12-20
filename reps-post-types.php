<?php
/*
Plugin Name: Rep Group Shortcode
Description: A plugin to display Rep Group information using a shortcode.
Version: 1.0
Author: Marc Maninang
*/


function get_rep_group_post_type(): string {
    return 'rep-group';
}

// Register the meta box for your ACF post type
function add_rep_group_shortcode_meta_box(): void {
    add_meta_box(
        id: 'rep-group-shortcode',           
        title: 'Rep Group Shortcode',           
        callback: 'display_shortcode_meta_box',    
        screen: get_rep_group_post_type(),       // Using the post type from ACF
        context: 'side',                          
        priority: 'high'                           
    );
}
add_action(hook_name: 'add_meta_boxes', callback: 'add_rep_group_shortcode_meta_box');

// The rest remains the same as before
function display_shortcode_meta_box($post): void {
    $shortcode = '[rep_group id="' . $post->ID . '"]';
    ?>
    <div class="shortcode-container">
        <p>Copy this shortcode to display this Rep Group:</p>
        <input 
            type="text" 
            value="<?php echo esc_attr(text: $shortcode); ?>" 
            class="widefat" 
            readonly 
            onclick="this.select();"
        />
        <p class="description">
            Click the shortcode to select it, then copy (Ctrl/Cmd + C) to use it in your content.
        </p>
    </div>
    <?php
}

// Update the styling check to use your ACF post type
function add_shortcode_meta_box_styles(): void {
    $screen = get_current_screen();
    if ($screen->post_type === get_rep_group_post_type()) {
        ?>
        <style>
            #rep-group-shortcode .shortcode-container {
                padding: 10px 0;
            }
            #rep-group-shortcode input {
                background: #f0f0f1;
                padding: 8px;
                margin: 5px 0;
                font-family: monospace;
            }
            #rep-group-shortcode .description {
                font-size: 12px;
                font-style: italic;
            }
        </style>
        <?php
    }
}
add_action(hook_name: 'admin_head', callback: 'add_shortcode_meta_box_styles');

// Update the column handlers to use your ACF post type
function add_rep_group_shortcode_column($columns): array {
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['shortcode'] = 'Shortcode';
        }
    }
    return $new_columns;
}
add_filter(hook_name: 'manage_' . get_rep_group_post_type() . '_posts_columns', callback: 'add_rep_group_shortcode_column');

function display_rep_group_shortcode_column($column, $post_id): void {
    if ($column === 'shortcode') {
        $shortcode = '[rep_group id="' . $post_id . '"]';
        echo '<input type="text" value="' . esc_attr(text: $shortcode) . '" readonly onclick="this.select();" style="width: 200px;">';
    }
}
add_action(hook_name: 'manage_' . get_rep_group_post_type() . '_posts_custom_column', callback: 'display_rep_group_shortcode_column', priority: 10, accepted_args: 2);

function display_rep_group($atts): string {
    $atts = shortcode_atts(pairs: [
        'id' => '',
    ], atts: $atts, shortcode: 'rep_group');

    $post_id = $atts['id'];
    if (!$post_id) return '';

    $output = '<section class="mapplic-lightbox__contact-section">';
    
    $area_served = get_field('rep_group_area_served', $post_id);
    if ($area_served) {
        $output .= '<h2 class="mapplic-lightbox__area-served">Area Served: ' . $area_served . '</h2>';
    }

    if (has_post_thumbnail(post: $post_id)) {
        $featured_image = get_the_post_thumbnail_url(post: $post_id, size: 'full');
        $output .= '<figure>';
        $output .= '<img class="wp-image-157995" src="' . $featured_image . '" alt="' . get_the_title(post: $post_id) . '" width="217" height="auto" />';
        $output .= '<figcaption class="visually-hidden">' . get_the_title(post: $post_id) . ' Logo</figcaption>';
        $output .= '</figure>';
    }

    $output .= '<h3 class="mapplic-lightbox__company-name">' . get_the_title(post: $post_id) . '</h3>';
    $output .= '<address class="mapplic-lightbox__address">';
    
    $address_line_1 = get_field('rep_group_address_line_1', $post_id);
    if ($address_line_1) {
        $output .= '<p>' . $address_line_1 . '</p>';
    }

    $address_line_2 = get_field('rep_group_address_line_2', $post_id);
    if ($address_line_2) {
        $output .= '<p>' . $address_line_2 . '</p>';
    }

    $city = get_field('rep_group_city', $post_id);
    $state = get_field('rep_group_state', $post_id);
    $zip_code = get_field('rep_group_zip_code', $post_id);
    if ($city || $state || $zip_code) {
        $location_parts = array_filter(array: [$city, $state, $zip_code]);
        $output .= '<p>' . implode(separator: ', ', array: $location_parts) . '</p>';
    }

    if (have_rows('rep_group_phone_numbers', $post_id)) {
        while (have_rows('rep_group_phone_numbers', $post_id)) {
            the_row();
            $phone_type = get_sub_field('phone_type');
            $phone_number = get_sub_field('phone_number');
            if ($phone_type && $phone_number) {
                $output .= '<p>' . $phone_type . ': <a href="tel:' . $phone_number . '">' . $phone_number . '</a></p>';
            }
        }
    }

    $output .= '</address>';

    if (have_rows('rep_associates', $post_id)) {
        $output .= '<ul class="mapplic-lightbox__contact-details">';
        while (have_rows('rep_associates', $post_id)) {
            the_row();
            $output .= '<li class="mapplic-lightbox__contact-details-item">';
            
            $rep_name = get_sub_field('rep_name');
            if ($rep_name) {
                $output .= '<p>' . $rep_name . '</p>';
            }

            $territory_served = get_sub_field('territory_served');
            if ($territory_served) {
                $output .= '<p><strong>Territory:</strong> ' . $territory_served . '</p>';
            }

            if (have_rows('rep_phone_numbers')) {
                while (have_rows('rep_phone_numbers')) {
                    the_row();
                    $phone_type = get_sub_field('phone_type');
                    $phone_number = get_sub_field('phone_number');
                    if ($phone_type && $phone_number) {
                        $output .= '<p>' . $phone_type . ': <a href="tel:' . $phone_number . '">' . $phone_number . '</a></p>';
                    }
                }
            }

            $rep_email = get_sub_field('rep_email');
            if ($rep_email) {
                $output .= '<p><a href="mailto:' . $rep_email . '">' . $rep_email . '</a></p>';
            }

            $output .= '</li>';
        }
        $output .= '</ul>';
    }

    $output .= '</section>';
    return $output;
}
add_shortcode(tag: 'rep_group', callback: 'display_rep_group');
