<?php
/**
 * Template for displaying Rep Group details.
 *
 * Expected variables:
 * $post_id (int) - The ID of the rep group post.
 * $area_name_context (string) - The name of the area/context for the title.
 * $area_color (string) - The color associated with this area/rep group for styling.
 * $shortcode_instance (RepGroup\\Shortcode) - Instance of the Shortcode class to call helper methods.
 *
 * Other variables are derived from $post_id within this template using ACF functions.
 */

// Ensure $post_id is available, otherwise, nothing to render.
if (empty($post_id) || !is_numeric($post_id)) {
    echo '<p>Error: Rep Group ID not provided for details template.</p>';
    return;
}

// Ensure $shortcode_instance is available for helper methods.
if (empty($shortcode_instance) || !is_a($shortcode_instance, 'RepGroup\\Shortcode')) {
    echo '<p>Error: Shortcode instance not available for details template.</p>';
    return;
}

// Fetch data using $post_id
$title = get_the_title($post_id);
$logo_url = get_the_post_thumbnail_url($post_id, 'medium');
$address_data = get_field('rg_address_container', $post_id);
$phone = get_field('rg_phone_number', $post_id);
$rg_phone_numbers = get_field('rg_phone_numbers', $post_id);
$email = get_field('rg_email', $post_id);
$website = get_field('rg_website', $post_id);
$team_members = get_field('rep_associates', $post_id);

// Default area color if not provided or invalid
$default_detail_header_color = defined('REP_GROUP_DEFAULT_REGION_COLOR') ? REP_GROUP_DEFAULT_REGION_COLOR : '#CCCCCC';
$header_color = !empty($area_color) ? $area_color : $default_detail_header_color;

?>
<div class="rep-group-details-header">
    <?php 
    // Show Area Served if context is specific and not one of the default/error messages from ajax_get_rep_group_details_by_id
    $is_generic_context = in_array($area_name_context, ['Details for this Rep Group', 'Not specified', 'Error fetching areas'], true);
    if (!empty($area_name_context) && !$is_generic_context) : 
    ?>
        <div class="area-served-info" style="border-left-color: <?php echo esc_attr($header_color); ?>;">
            <span class="area-label">Area Served:</span>
            <span class="area-values"><?php echo esc_html($area_name_context); ?></span>
        </div>
    <?php endif; ?>
    <?php if ($logo_url) : ?>
        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($title); ?> logo" class="rep-group-logo-map">
    <?php endif; ?>
    <h3 class="rep-group-title-map"><?php echo esc_html($title); ?></h3>
</div>

<div class="rep-group-details-body">
    <?php // Website Section - shows URL as text, which is fine. ?>
    <?php if ($website) : ?>
        <div class="contact-item rep-group-contact-item website-details">
            <ion-icon name="globe" aria-hidden="true"></ion-icon>
            <a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener noreferrer" aria-label="Website: <?php echo esc_attr(str_replace(['http://', 'https://'], '', $website)); ?>">
                <?php echo esc_html(str_replace(['http://', 'https://'], '', $website)); // Display without http(s):// ?>
            </a>
        </div>
    <?php endif; ?>

    <?php // Address Section ?>
    <?php if ($address_data && (!empty($address_data['rg_address_1']) || !empty($address_data['rg_city']))) : ?>
        <div class="contact-item rep-group-contact-item address-details">
            <ion-icon name="location" aria-label="Address"></ion-icon>
            <span class="contact-text">
                <?php 
                $address_lines = [];
                if (!empty($address_data['rg_address_1'])) $address_lines[] = esc_html($address_data['rg_address_1']);
                if (!empty($address_data['rg_address_2'])) $address_lines[] = esc_html($address_data['rg_address_2']);
                $city_state_zip = [];
                if (!empty($address_data['rg_city'])) $city_state_zip[] = esc_html($address_data['rg_city']);
                if (!empty($address_data['rg_state'])) $city_state_zip[] = esc_html($address_data['rg_state']);
                if (!empty($address_data['rg_zip_code'])) $city_state_zip[] = esc_html($address_data['rg_zip_code']);
                if (!empty($city_state_zip)) $address_lines[] = implode(', ', array_filter($city_state_zip, 'strlen'));
                echo implode('<br>', array_filter($address_lines, 'strlen'));
                ?>
            </span>
        </div>
    <?php endif; ?>

    <?php // Phone Section - Updated for repeater ?>
    <?php if (!empty($rg_phone_numbers)) : ?>
        <?php foreach ($rg_phone_numbers as $phone_entry) :
            $phone_type = !empty($phone_entry['rg_phone_type']) ? esc_html($phone_entry['rg_phone_type']) : 'Phone';
            $phone_number = !empty($phone_entry['rg_phone_number']) ? esc_html($phone_entry['rg_phone_number']) : '';
            if ($phone_number) :
                $icon_name = 'call'; // Default icon
                if (strtolower($phone_type) === 'fax') {
                    $icon_name = 'print';
                }
        ?>
            <div class="contact-item rep-group-contact-item phone-details">
                <ion-icon name="<?php echo $icon_name; ?>" aria-hidden="true"></ion-icon>
                <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone_number)); ?>" 
                   aria-label="<?php echo $phone_type . ': ' . $phone_number; ?>">
                    <?php echo $phone_number; // Display only the number as visible link text ?>
                </a>
            </div>
        <?php 
            endif;
        endforeach; ?>
    <?php elseif ($phone) : // Fallback for old single phone field, can be removed later if data is migrated ?>
        <div class="contact-item rep-group-contact-item phone-details">
            <ion-icon name="call" aria-hidden="true"></ion-icon>
            <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone)); ?>" 
               aria-label="Phone: <?php echo esc_attr($phone); ?>">
                 <?php echo esc_html($phone); // Display only the number as visible link text ?>
            </a>
        </div>
    <?php endif; ?>

    <?php // Email Section ?>
    <?php if ($email) : ?>
        <div class="contact-item rep-group-contact-item email-details">
            <ion-icon name="mail" aria-hidden="true"></ion-icon>
            <a href="mailto:<?php echo esc_attr($email); ?>" aria-label="Email: <?php echo esc_attr($email); ?>">
                <span class="contact-text-hidden">Email: </span><?php echo esc_html($email); ?>
            </a>
        </div>
    <?php endif; ?>

    <?php
    // Satellite Offices
    if (have_rows('satellite_offices', $post_id)) :
        while (have_rows('satellite_offices', $post_id)) : the_row();
            $office_name = get_sub_field('office_name');
            $address = get_sub_field('office_address');
            $phones = get_sub_field('office_phone_numbers');
            ?>
            <div class="satellite-office-details">
                <?php if ($office_name) : ?>
                    <h5 class="satellite-office-title"><?php echo esc_html($office_name); ?></h5>
                <?php endif; ?>

                <?php if ($address && is_array($address) && !empty(array_filter($address))) : ?>
                    <div class="contact-item address-details">
                        <ion-icon name="location" aria-label="Address"></ion-icon>
                        <span class="contact-text">
                            <?php
                            $address_lines = [];
                            if (!empty($address['address_1'])) $address_lines[] = esc_html($address['address_1']);
                            if (!empty($address['address_2'])) $address_lines[] = esc_html($address['address_2']);
                            $city_state_zip = [];
                            if (!empty($address['city'])) $city_state_zip[] = esc_html($address['city']);
                            if (!empty($address['state'])) $city_state_zip[] = esc_html($address['state']);
                            if (!empty($address['zip_code'])) $city_state_zip[] = esc_html($address['zip_code']);
                            if (!empty($city_state_zip)) $address_lines[] = implode(', ', array_filter($city_state_zip));
                            echo implode('<br>', array_filter($address_lines));
                            ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if ($phones) : ?>
                    <?php foreach ($phones as $phone) :
                        $phone_type = !empty($phone['phone_type']) ? esc_html($phone['phone_type']) : 'Phone';
                        $phone_number = !empty($phone['phone_number']) ? esc_html($phone['phone_number']) : '';
                        if ($phone_number) :
                            $icon_name = 'call';
                            if (strtolower($phone_type) === 'fax') {
                                $icon_name = 'print';
                            }
                    ?>
                        <div class="contact-item phone-details">
                            <ion-icon name="<?php echo $icon_name; ?>" aria-hidden="true"></ion-icon>
                            <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone_number)); ?>" aria-label="<?php echo $phone_type . ': ' . $phone_number; ?>">
                                <?php echo $phone_number; ?>
                            </a>
                        </div>
                    <?php 
                        endif;
                    endforeach; ?>
                <?php endif; ?>
            </div>
            <?php
        endwhile;
    endif;
    ?>

    <?php // Team Members Section ?>
    <?php if (have_rows('rep_associates', $post_id)) : ?>
        <div class="team-members-section">
            <h4 class="team-section-title">Team</h4>
            <?php
            while (have_rows('rep_associates', $post_id)) : the_row();
                $associate_type = get_sub_field('associate_type');
                $associate_areas_served = esc_html(get_sub_field('rep_specific_areas_text'));
                
                $associate_name = '';
                $associate_title = '';
                $associate_email = '';
                $associate_phone = '';

                if ($associate_type === 'wp_user') {
                    $user_id = get_sub_field('rep_user');
                    if ($user_id) {
                        $user_info = get_userdata($user_id);
                        if ($user_info) {
                            $associate_name = esc_html($user_info->display_name);
                            $associate_title = get_field('rep_title', 'user_' . $user_id);
                            
                            $email_override = get_sub_field('rep_contact_email_override');
                            $associate_email = !empty($email_override) ? $email_override : $user_info->user_email;
                            
                            $phone_override = get_sub_field('rep_contact_phone_override');
                            $associate_phone = !empty($phone_override) ? esc_html($phone_override) : esc_html(get_field('rep_primary_phone', 'user_' . $user_id));
                        }
                    }
                } elseif ($associate_type === 'manual') {
                    $associate_name = esc_html(get_sub_field('manual_rep_name'));
                    $associate_title = esc_html(get_sub_field('manual_rep_title'));
                    $associate_email = get_sub_field('manual_rep_email');
                    $associate_phone = get_sub_field('manual_rep_phone');
                }

                if (empty(trim($associate_name))) continue;
                ?>
                <div class="rep-associate-item">
                    <h5 class="rep-associate-name"><?php echo $associate_name; ?></h5>
                    <?php if (!empty($associate_title)) : ?>
                        <p class="rep-associate-title"><?php echo esc_html($associate_title); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($associate_areas_served)) : ?>
                        <div class="area-served-info rep-associate-areas-served">
                            <span class="area-label">Area Served:</span>
                            <span class="area-values"><?php echo $associate_areas_served; ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($associate_phone) : ?>
                        <div class="contact-item rep-associate-contact-item phone-details">
                            <ion-icon name="call" aria-hidden="true"></ion-icon>
                            <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $associate_phone)); ?>" 
                               aria-label="Call <?php echo $associate_name; ?> at <?php echo $associate_phone; ?>">
                                <span class="contact-text-hidden">Phone: </span><?php echo esc_html($associate_phone); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                    <?php if ($associate_email) : ?>
                        <div class="contact-item rep-associate-contact-item email-details">
                            <ion-icon name="mail" aria-hidden="true"></ion-icon>
                            <a href="mailto:<?php echo esc_attr($associate_email); ?>" 
                               aria-label="Email <?php echo $associate_name; ?> at <?php echo esc_attr($associate_email); ?>">
                                <span class="contact-text-hidden">Email: </span><?php echo esc_html($associate_email); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php 
                if (get_row_index() < count(get_field('rep_associates', $post_id))) :
                ?>
                <hr class="team-divider">
                <?php endif; ?>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div> 