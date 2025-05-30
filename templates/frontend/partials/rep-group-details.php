<?php
/**
 * Template for displaying Rep Group details.
 *
 * Expected variables:
 * $post_id (int) - The ID of the rep group post.
 * $area_name_context (string) - The name of the area/context for the title.
 * $area_color (string) - The color associated with this area/rep group for styling.
 * $shortcode_instance (RepGroup\Shortcode) - Instance of the Shortcode class to call helper methods.
 *
 * Other variables are derived from $post_id within this template using ACF functions.
 */

// Ensure $post_id is available, otherwise, nothing to render.
if (empty($post_id) || !is_numeric($post_id)) {
    echo '<p>Error: Rep Group ID not provided for details template.</p>';
    return;
}

// Ensure $shortcode_instance is available for helper methods.
if (empty($shortcode_instance) || !is_a($shortcode_instance, 'RepGroup\Shortcode')) {
    echo '<p>Error: Shortcode instance not available for details template.</p>';
    return;
}

// Fetch data using $post_id
$title = get_the_title($post_id);
$logo_url = get_the_post_thumbnail_url($post_id, 'medium');
$address_data = get_field('rg_address_container', $post_id);
$email = get_field('rg_email', $post_id);
$website = get_field('rg_website', $post_id);

// Default area color if not provided or invalid
$default_detail_header_color = defined('REP_GROUP_DEFAULT_REGION_COLOR') ? REP_GROUP_DEFAULT_REGION_COLOR : '#CCCCCC';
$header_color = !empty($area_color) ? $area_color : $default_detail_header_color;

?>
<div class="rep-group-details-header">
    <?php if ($logo_url) : ?>
        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($title); ?> logo" class="rep-group-logo-map">
    <?php endif; ?>
    <h3 class="rep-group-title-map"><?php echo esc_html($title); ?></h3>
    <?php if (!empty($area_name_context)) : ?>
        <p class="area-context-map">Areas Served: <span class="area-name-highlighted"><?php echo esc_html($area_name_context); ?></span></p>
    <?php endif; ?>
</div>

<div class="rep-group-details-body">
    <?php // Website Section ?>
    <?php if ($website) : ?>
        <div class="rep-group-contact-section website-details">
            <p>
                <a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html($website); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <?php // Address Section ?>
    <?php if ($address_data && is_array($address_data) && (!empty($address_data['rg_address_1']) || !empty($address_data['rg_city']) || !empty($address_data['rg_state']) || !empty($address_data['rg_zip_code']))) : ?>
        <div class="rep-group-contact-section address-details">
            <h4><ion-icon name="location-outline"></ion-icon> Address</h4>
            <?php
            $full_address_parts = [];
            if (!empty($address_data['rg_address_1'])) $full_address_parts[] = esc_html($address_data['rg_address_1']);
            if (!empty($address_data['rg_address_2'])) $full_address_parts[] = esc_html($address_data['rg_address_2']);
            
            $city_state_zip_parts = [];
            if (!empty($address_data['rg_city'])) $city_state_zip_parts[] = esc_html($address_data['rg_city']);
            if (!empty($address_data['rg_state'])) $city_state_zip_parts[] = esc_html($address_data['rg_state']);
            if (!empty($address_data['rg_zip_code'])) $city_state_zip_parts[] = esc_html($address_data['rg_zip_code']);
            
            if (!empty($city_state_zip_parts)) {
                 $full_address_parts[] = implode(', ', $city_state_zip_parts); // Join city, state, zip with comma and space
            }

            if (!empty($full_address_parts)) :
                echo '<p>' . implode('<br>', $full_address_parts) . '</p>';
            else :
                echo '<p>Address not available.</p>';
            endif;
            ?>
        </div>
    <?php endif; ?>

    <?php // Phone Numbers Section ?>
    <?php if (have_rows('rg_phone_numbers', $post_id)) : ?>
        <div class="rep-group-contact-section phone-details">
            <h4><ion-icon name="call-outline"></ion-icon> Phone Numbers</h4>
            <?php while (have_rows('rg_phone_numbers', $post_id)) : the_row(); ?>
                <?php
                $phone_type = get_sub_field('rep_phone_type');
                $phone_number = get_sub_field('rep_phone_number');
                ?>
                <?php if ($phone_number) : ?>
                    <p>
                        <strong><?php echo esc_html($phone_type ?: 'Phone'); ?>:</strong>
                        <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone_number)); ?>">
                            <?php echo esc_html($phone_number); ?>
                        </a>
                    </p>
                <?php endif; ?>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>

    <?php // Email Section ?>
    <?php if ($email && is_email($email)) : ?>
        <div class="rep-group-contact-section email-details">
            <h4><ion-icon name="mail-outline"></ion-icon> Email</h4>
            <p>
                <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a>
            </p>
        </div>
    <?php endif; ?>

    <?php // Rep Associates Section ?>
    <?php if (have_rows('rep_associates', $post_id)) : ?>
        <div class="rep-associates-map-display rep-group-contact-section">
            <h4><ion-icon name="people-outline"></ion-icon> Team</h4>
            <?php while (have_rows('rep_associates', $post_id)) : the_row(); ?>
                <?php
                $user_id = get_sub_field('rep_user');
                $user_data = $user_id ? get_userdata($user_id) : null;
                $associate_name = $user_data ? $user_data->display_name : 'Associate';
                $rep_title = $user_id ? get_field('rep_title', 'user_' . $user_id) : '';
                ?>
                <div class="associate-card">
                    <h5><?php echo esc_html($associate_name); ?></h5>
                    <?php if ($rep_title) : ?>
                        <p class="associate-title"><em><?php echo esc_html($rep_title); ?></em></p>
                    <?php endif; ?>
                    <?php
                    $associate_specific_areas = get_sub_field('associate_specific_areas_text');
                    if (!empty($associate_specific_areas)) :
                        echo sprintf('<p class="associate-areas"><em>%s</em></p>', esc_html($associate_specific_areas));
                    endif;
                    
                    $email_override = get_sub_field('rep_contact_email_override');
                    $phone_override = get_sub_field('rep_contact_phone_override');
                    
                    // Use the passed $shortcode_instance to call the render_rep_contact_info method
                    // This is a public method, so it's okay to call it from the template if $shortcode_instance is passed.
                    $contact_info_html = $shortcode_instance->render_rep_contact_info($user_data, $email_override, $phone_override);
                    if (!empty(trim($contact_info_html))) :
                         echo '<div class="associate-contact-map">' . $contact_info_html . '</div>';
                    endif;
                    ?>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div> 