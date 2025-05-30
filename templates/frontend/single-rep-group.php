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
    // Rep Associates
    if (have_rows('rep_associates', $post_id)) :
    ?>
        <div class="rep-associates-section">
            <h4>Team Members</h4> <?php // Or a more generic title like "Contacts" or "Associates" ?>
            <div class="rep-card-grid">
            <?php 
            while (have_rows('rep_associates', $post_id)) : the_row();
                $user_id = get_sub_field('rep_user');
                $user_data = $user_id ? get_userdata($user_id) : null;
                $email_override = get_sub_field('rep_contact_email_override');
                $phone_override = get_sub_field('rep_contact_phone_override');
                $associate_specific_areas_text = get_sub_field('associate_specific_areas_text');
                $associate_name = $user_data ? $user_data->display_name : 'Associate Name Not Found';
                $rep_title = $user_id ? get_field('rep_title', 'user_' . $user_id) : '';
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
                        <?php 
                        // Call public method on the $shortcode_instance
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo $shortcode_instance->render_rep_contact_info($user_data, $email_override, $phone_override);
                        ?>
                    </div>
                </div>
            <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>
</section> 