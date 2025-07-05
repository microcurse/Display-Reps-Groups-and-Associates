<?php
/**
 * Template for displaying a single rep-group post (Fallback)
 *
 * @package Display_Reps_Groups_and_Associates
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<?php get_sidebar('shop'); ?>

<main id="primary" class="site-main">
    <?php while (have_posts()) : the_post(); ?>
        
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            
            <!-- Navigation Notice -->
            <div class="rep-group-notice">
                <p><strong>Note:</strong> This is a direct link to rep information. For the best experience, <a href="/forbes-reps/">view the interactive map</a>.</p>
            </div>

            <header class="entry-header">
                <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
            </header>

            <div class="entry-content">
                
                <!-- Contact Information -->
                <section class="rep-contact-section">
                    <h2>Contact Information</h2>
                    
                    <?php
                    // Website
                    $website = get_field('rg_website');
                    if ($website) : ?>
                        <p><strong>Website:</strong> <a href="<?php echo esc_url($website); ?>" target="_blank"><?php echo esc_html($website); ?></a></p>
                    <?php endif; ?>

                    <?php
                    // Email
                    $email = get_field('rg_email');
                    if ($email) : ?>
                        <p><strong>Email:</strong> <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></p>
                    <?php endif; ?>

                    <?php
                    // Phone Numbers
                    $phone_numbers = get_field('rg_phone_numbers');
                    if ($phone_numbers) : ?>
                        <div class="phone-numbers">
                            <strong>Phone:</strong>
                            <ul>
                                <?php foreach ($phone_numbers as $phone) : ?>
                                    <li><?php echo esc_html($phone['rg_phone_type']); ?>: <?php echo esc_html($phone['rg_phone_number']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Area Served
                    $area_served_terms = get_the_terms(get_the_ID(), 'area-served');
                    if ($area_served_terms && !is_wp_error($area_served_terms)) : ?>
                        <p><strong>Area Served:</strong> 
                        <?php
                        $area_names = array();
                        foreach ($area_served_terms as $term) {
                            $area_names[] = $term->name;
                        }
                        echo esc_html(implode(', ', $area_names));
                        ?>
                        </p>
                    <?php endif; ?>
                </section>

                <!-- Addresses -->
                <?php
                $address_container = get_field('rg_address_container');
                if ($address_container) : ?>
                    <section class="rep-address-section">
                        <h2>Main Office</h2>
                        <?php
                        $address_parts = array();
                        if ($address_container['rg_address_1']) $address_parts[] = $address_container['rg_address_1'];
                        if ($address_container['rg_address_2']) $address_parts[] = $address_container['rg_address_2'];
                        
                        $location_parts = array();
                        if ($address_container['rg_city']) $location_parts[] = $address_container['rg_city'];
                        if ($address_container['rg_state']) $location_parts[] = $address_container['rg_state'];
                        if ($address_container['rg_zip_code']) $location_parts[] = $address_container['rg_zip_code'];
                        
                        if (!empty($location_parts)) {
                            $address_parts[] = implode(', ', $location_parts);
                        }
                        
                        if (!empty($address_parts)) {
                            echo '<p>' . esc_html(implode('<br>', $address_parts)) . '</p>';
                        }
                        ?>
                    </section>
                <?php endif; ?>

                <?php
                // Satellite Offices
                $satellite_offices = get_field('satellite_offices');
                if ($satellite_offices) : ?>
                    <section class="rep-satellite-section">
                        <h2>Additional Offices</h2>
                        <?php foreach ($satellite_offices as $office) : ?>
                            <div class="satellite-office">
                                <h3><?php echo esc_html($office['office_name']); ?></h3>
                                <?php if ($office['office_address']) : ?>
                                    <?php
                                    $office_address = $office['office_address'];
                                    $office_parts = array();
                                    if ($office_address['address_1']) $office_parts[] = $office_address['address_1'];
                                    if ($office_address['address_2']) $office_parts[] = $office_address['address_2'];
                                    
                                    $office_location = array();
                                    if ($office_address['city']) $office_location[] = $office_address['city'];
                                    if ($office_address['state']) $office_location[] = $office_address['state'];
                                    if ($office_address['zip_code']) $office_location[] = $office_address['zip_code'];
                                    
                                    if (!empty($office_location)) {
                                        $office_parts[] = implode(', ', $office_location);
                                    }
                                    
                                    if (!empty($office_parts)) {
                                        echo '<p>' . esc_html(implode('<br>', $office_parts)) . '</p>';
                                    }
                                    ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                <!-- Associates -->
                <?php
                $associates = get_field('rep_associates');
                if ($associates) : ?>
                    <section class="rep-associates-section">
                        <h2>Team Members</h2>
                        
                        <?php foreach ($associates as $associate) : ?>
                            <div class="associate-item">
                                <?php
                                $associate_type = $associate['associate_type'];
                                
                                if ($associate_type === 'wp_user') {
                                    // WordPress User
                                    $user_id = $associate['rep_user'];
                                    if ($user_id) {
                                        $user_data = get_userdata($user_id);
                                        if ($user_data) {
                                            echo '<h3>' . esc_html($user_data->display_name) . '</h3>';
                                            
                                            $user_title = get_field('rep_title', 'user_' . $user_id);
                                            if ($user_title) {
                                                echo '<p><strong>Title:</strong> ' . esc_html($user_title) . '</p>';
                                            }
                                            
                                            $user_company = get_field('rep_company_name', 'user_' . $user_id);
                                            if ($user_company) {
                                                echo '<p><strong>Company:</strong> ' . esc_html($user_company) . '</p>';
                                            }
                                            
                                            $user_phone = get_field('rep_primary_phone', 'user_' . $user_id);
                                            if ($user_phone) {
                                                echo '<p><strong>Phone:</strong> ' . esc_html($user_phone) . '</p>';
                                            }
                                            
                                            if ($user_data->user_email) {
                                                echo '<p><strong>Email:</strong> <a href="mailto:' . esc_attr($user_data->user_email) . '">' . esc_html($user_data->user_email) . '</a></p>';
                                            }
                                        }
                                    }
                                } else {
                                    // Manual Entry
                                    $manual_name = $associate['manual_rep_name'];
                                    if ($manual_name) {
                                        echo '<h3>' . esc_html($manual_name) . '</h3>';
                                        
                                        $manual_title = $associate['manual_rep_title'];
                                        if ($manual_title) {
                                            echo '<p><strong>Title:</strong> ' . esc_html($manual_title) . '</p>';
                                        }
                                        
                                        $manual_company = $associate['manual_rep_company'];
                                        if ($manual_company) {
                                            echo '<p><strong>Company:</strong> ' . esc_html($manual_company) . '</p>';
                                        }
                                        
                                        $manual_email = $associate['manual_rep_email'];
                                        if ($manual_email) {
                                            echo '<p><strong>Email:</strong> <a href="mailto:' . esc_attr($manual_email) . '">' . esc_html($manual_email) . '</a></p>';
                                        }
                                        
                                        $manual_phone = $associate['manual_rep_phone'];
                                        if ($manual_phone) {
                                            echo '<p><strong>Phone:</strong> ' . esc_html($manual_phone) . '</p>';
                                        }
                                    }
                                }
                                ?>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                <!-- Back to Map -->
                <div class="rep-group-navigation">
                    <p><a href="/forbes-reps/" class="button">← Back to Interactive Map</a></p>
                </div>

            </div><!-- .entry-content -->
        </article><!-- #post-## -->

    <?php endwhile; ?>
</main><!-- #primary -->

<?php
get_footer(); 