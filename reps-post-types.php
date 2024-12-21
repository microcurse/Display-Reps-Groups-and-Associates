<?php
/*
Plugin Name: Rep Group Shortcode
Description: A plugin to display Rep Group information using a shortcode. Custom Post Type is built in ACF along with Field Groups for for inputs.
Version: 1.0
Author: Marc Maninang
*/


function get_rep_group_post_type(): string {
    return 'rep-group';
}

function add_rep_group_shortcode_meta_box(): void {
    add_meta_box(
        id: 'rep-group-shortcode',           
        title: 'Rep Group Shortcode',           
        callback: 'display_shortcode_meta_box',    
        screen: get_rep_group_post_type(),
        context: 'side',                          
        priority: 'high'                           
    );
}
add_action(hook_name: 'add_meta_boxes', callback: 'add_rep_group_shortcode_meta_box');

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
    // Validate and sanitize input
    $atts = shortcode_atts([
        'id' => '',
    ], $atts, 'rep_group');

    $post_id = absint($atts['id']); // Convert to positive integer
    if (!$post_id || get_post_type($post_id) !== get_rep_group_post_type()) {
        return ''; // Early return if invalid ID or wrong post type
    }

    // Start building output using heredoc for better readability
    $output = <<<HTML
    <section class="mapplic-lightbox__contact-section">
    HTML;

    // Extract method for better organization
    $output .= render_rep_group_header($post_id);
    $output .= render_rep_group_address($post_id);
    $output .= render_rep_associates($post_id);

    $output .= '</section>';
    return $output;
}

function render_rep_group_header(int $post_id): string {
    $output = '';
    
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
            '<figure>
                <img class="wp-image-157995" src="%s" alt="%s" width="217" height="auto" />
                <figcaption class="visually-hidden">%s Logo</figcaption>
            </figure>',
            esc_url($featured_image),
            esc_attr($title),
            esc_html($title)
        );
    }

    return $output;
}

function render_rep_group_address(int $post_id): string {
    $output = sprintf(
        '<h3 class="mapplic-lightbox__company-name">%s</h3>',
        esc_html(get_the_title($post_id))
    );
    
    $output .= '<address class="mapplic-lightbox__address">';

    // Address fields
    $address_fields = [
        get_field('rg_address_1', $post_id),
        get_field('rg_address_2', $post_id),
    ];
    
    foreach ($address_fields as $field) {
        if ($field) {
            $output .= sprintf('<p>%s</p>', esc_html($field));
        }
    }

    // City, State, Zip
    $location_parts = array_filter([
        get_field('rg_city', $post_id),
        get_field('rg_state', $post_id),
        get_field('rg_zip_code', $post_id)
    ]);
    
    if (!empty($location_parts)) {
        $output .= sprintf('<p>%s</p>', esc_html(implode(', ', $location_parts)));
    }

    // Phone numbers
    $output .= render_phone_numbers($post_id);

    $output .= '</address>';
    return $output;
}

function render_phone_numbers(int $post_id): string {
    $output = '';
    
    if (have_rows('rg_phone_numbers', $post_id)) {
        while (have_rows('rg_phone_numbers', $post_id)) {
            the_row();
            $phone_type = get_sub_field('rg_phone_type');
            $phone_number = get_sub_field('rg_phone_number');
            
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
    
    return $output;
}

function render_rep_associates(int $post_id): string {
    if (!have_rows('rep_associates', $post_id)) {
        return '';
    }

    $output = '<ul class="mapplic-lightbox__contact-details">';
    
    while (have_rows('rep_associates', $post_id)) {
        the_row();
        $output .= render_single_rep();
    }
    
    $output .= '</ul>';
    return $output;
}

function render_single_rep(): string {
    $output = '<li class="mapplic-lightbox__contact-details-item">';
    
    // Rep name
    $rep_name = get_sub_field('name');
    if ($rep_name) {
        $output .= sprintf('<p>%s</p>', esc_html($rep_name));
    }

    // Territory
    $territory = get_sub_field('territory_served');
    if ($territory) {
        $output .= sprintf(
            '<p><strong>Territory:</strong> %s</p>',
            esc_html($territory)
        );
    }

    // Phone numbers
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

    // Email
    $email = get_sub_field('email');
    if ($email) {
        $output .= sprintf(
            '<p><a href="mailto:%s">%s</a></p>',
            esc_attr($email),
            esc_html($email)
        );
    }

    $output .= '</li>';
    return $output;
}

add_shortcode(tag: 'rep_group', callback: 'display_rep_group');