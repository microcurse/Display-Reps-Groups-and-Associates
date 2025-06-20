<?php
/**
 * Template for displaying a single Rep Group.
 *
 * Expected variables:
 * $post_id (int) - The ID of the rep group post.
 * $shortcode_instance (RepGroup\\Shortcode) - Instance of the Shortcode class to call helper methods.
 */

// Ensure $post_id is available.
if (empty($post_id) || !is_numeric($post_id)) {
    echo '<p>Error: Rep Group ID not provided for single display template.</p>';
    return;
}

// Ensure $shortcode_instance is available.
if (empty($shortcode_instance) || !is_a($shortcode_instance, 'RepGroup\\Shortcode')) {
    echo '<p>Error: Shortcode instance not available for single display template.</p>';
    return;
}

// Fetch Rep Group specific fields
$rg_website = get_field('rg_website', $post_id);

?>
<section class="rep-group rep-group-single-display">
    <?php 
    // Get Area Served from taxonomy
    $areas = get_the_terms($post_id, 'area-served');
    if ($areas && !is_wp_error($areas)) :
    ?>
        <div class="area-served">
            <strong>Area Served:</strong> 
            <span>
                <?php
                $area_names = array_map(function($term) {
                    return esc_html($term->name);
                }, $areas);
                echo implode(', ', $area_names);
                ?>
            </span>
        </div>
    <?php endif; ?>

    <?php 
    // Address Container
    $address = get_field('rg_address_container', $post_id);
    if ($address && is_array($address)) :
    ?>
        <div class="address">
            <strong>Address:</strong>
            <div class="address-content">
                <?php if (!empty($address['rg_address_1'])) : ?>
                    <p><?php echo esc_html($address['rg_address_1']); ?></p>
                <?php endif; ?>
                <?php if (!empty($address['rg_address_2'])) : ?>
                    <p><?php echo esc_html($address['rg_address_2']); ?></p>
                <?php endif; ?>
                <?php 
                $city_state_zip_parts = [];
                if (!empty($address['rg_city'])) $city_state_zip_parts[] = esc_html($address['rg_city']);
                if (!empty($address['rg_state'])) $city_state_zip_parts[] = esc_html($address['rg_state']);
                if (!empty($address['rg_zip_code'])) $city_state_zip_parts[] = esc_html($address['rg_zip_code']);
                
                if (!empty($city_state_zip_parts)) :
                ?>                    
                    <p><?php echo implode(', ', $city_state_zip_parts); // Join with comma and space ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php // Website Section ?>
    <?php if ($rg_website) : ?>
        <div class="rep-group-website">
            <strong>Website:</strong>
            <p>
                <a href="<?php echo esc_url($rg_website); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html($rg_website); ?>
                </a>
            </p>
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
            <div class="satellite-office">
                <?php if ($office_name) : ?>
                    <h4><?php echo esc_html($office_name); ?></h4>
                <?php endif; ?>

                <?php if ($address && is_array($address)) : ?>
                    <div class="address">
                        <strong>Address:</strong>
                        <div class="address-content">
                            <?php if (!empty($address['address_1'])) : ?>
                                <p><?php echo esc_html($address['address_1']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($address['address_2'])) : ?>
                                <p><?php echo esc_html($address['address_2']); ?></p>
                            <?php endif; ?>
                            <?php
                            $city_state_zip_parts = [];
                            if (!empty($address['city'])) $city_state_zip_parts[] = esc_html($address['city']);
                            if (!empty($address['state'])) $city_state_zip_parts[] = esc_html($address['state']);
                            if (!empty($address['zip_code'])) $city_state_zip_parts[] = esc_html($address['zip_code']);

                            if (!empty($city_state_zip_parts)) :
                            ?>
                                <p><?php echo implode(', ', $city_state_zip_parts); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($phones) : ?>
                    <div class="phone-numbers">
                        <strong>Phone:</strong>
                        <?php foreach ($phones as $phone) : ?>
                            <p>
                                <?php if ($phone['phone_type']) : ?>
                                    <em><?php echo esc_html($phone['phone_type']); ?>:</em>
                                <?php endif; ?>
                                <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $phone['phone_number'])); ?>">
                                    <?php echo esc_html($phone['phone_number']); ?>
                                </a>
                            </p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        endwhile;
    endif;
    ?>

    <?php 
    // Rep Associates
    if (have_rows('rep_associates', $post_id)) :
    ?>
        <div class="rep-associates-section">
            <h4>Team Members</h4>
            <div class="rep-card-grid">
            <?php 
            while (have_rows('rep_associates', $post_id)) : the_row();
                $associate_type = get_sub_field('associate_type');
                $associate_specific_areas_text = get_sub_field('rep_specific_areas_text');

                $associate_name = '';
                $rep_title = '';
                $contact_info_html = '';

                if ($associate_type === 'wp_user') {
                    $user_id = get_sub_field('rep_user');
                    if ($user_id) {
                        $user_data = get_userdata($user_id);
                        if ($user_data) {
                            $associate_name = $user_data->display_name;
                            $rep_title = get_field('rep_title', 'user_' . $user_id);

                            $email_override = get_sub_field('rep_contact_email_override');
                            $phone_override = get_sub_field('rep_contact_phone_override');

                            $phone_to_display = !empty($phone_override) ? $phone_override : get_field('rep_primary_phone', 'user_' . $user_data->ID);
                            if (!empty($phone_to_display)) {
                                $contact_info_html .= sprintf(
                                    '<p class="rep-phone"><ion-icon name="call" role="img" class="hydrated" aria-label="call"></ion-icon> <strong>Phone:</strong> <a href="tel:%s">%s</a></p>',
                                    esc_attr(preg_replace('/[^0-9+]/', '', $phone_to_display)),
                                    esc_html($phone_to_display)
                                );
                            }

                            $email_to_display = !empty($email_override) ? $email_override : ($user_data ? $user_data->user_email : '');
                            if (!empty($email_to_display) && is_email($email_to_display)) {
                                $contact_info_html .= sprintf(
                                    '<p class="rep-email"><ion-icon name="mail" role="img" class="hydrated" aria-label="mail"></ion-icon> <strong>Email:</strong> <a href="mailto:%s">%s</a></p>',
                                    esc_attr($email_to_display),
                                    esc_html($email_to_display)
                                );
                            }
                        } else {
                            $associate_name = 'User Not Found';
                        }
                    }
                } elseif ($associate_type === 'manual') {
                    $associate_name = get_sub_field('manual_rep_name');
                    $rep_title = get_sub_field('manual_rep_title');
                    $associate_email = get_sub_field('manual_rep_email');
                    $associate_phone = get_sub_field('manual_rep_phone');

                    if (!empty($associate_phone)) {
                        $contact_info_html .= sprintf(
                            '<p class="rep-phone"><ion-icon name="call" role="img" class="hydrated" aria-label="call"></ion-icon> <strong>Phone:</strong> <a href="tel:%s">%s</a></p>',
                            esc_attr(preg_replace('/[^0-9+]/', '', $associate_phone)),
                            esc_html($associate_phone)
                        );
                    }
                    if (!empty($associate_email) && is_email($associate_email)) {
                        $contact_info_html .= sprintf(
                            '<p class="rep-email"><ion-icon name="mail" role="img" class="hydrated" aria-label="mail"></ion-icon> <strong>Email:</strong> <a href="mailto:%s">%s</a></p>',
                            esc_attr($associate_email),
                            esc_html($associate_email)
                        );
                    }
                }

                if (empty(trim($associate_name))) continue;
            ?>
                <div class="rep-card">
                    <h3 class="rep-name"><?php echo esc_html($associate_name); ?></h3>
                    <?php if ($rep_title) : ?>
                        <p class="rep-title"><em><?php echo esc_html($rep_title); ?></em></p>
                    <?php endif; ?>
                    <?php if (!empty($associate_specific_areas_text)) : ?>
                        <p class="rep-territory"><strong>Specific Areas:</strong> <?php echo esc_html($associate_specific_areas_text); ?></p>
                    <?php endif; ?>
                    <div class="rep-contact-info">
                        <?php // This HTML is pre-escaped in the logic above ?>
                        <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php echo $contact_info_html; ?>
                    </div>
                </div>
            <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>
</section> 