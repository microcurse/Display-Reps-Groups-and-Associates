<?php
/**
 * Template for displaying rep-group archive pages (Fallback)
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
    
    <!-- Navigation Notice -->
    <div class="rep-group-notice">
        <p><strong>Note:</strong> This is a direct link to rep listings. For the best experience, <a href="/forbes-reps/">view the interactive map</a>.</p>
    </div>

    <?php if (have_posts()) : ?>

        <header class="page-header">
            <h1 class="page-title">Rep Groups</h1>
            <div class="archive-description">
                <p>Browse all rep groups and distributors. Use the interactive map for a better experience.</p>
            </div>
        </header>

        <div class="rep-groups-listing">
            <?php while (have_posts()) : the_post(); ?>

                <article id="post-<?php the_ID(); ?>" <?php post_class('rep-group-item'); ?>>
                    
                    <h2 class="rep-group-title">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h2>

                    <div class="rep-group-summary">
                        <?php
                        // Basic contact info
                        $email = get_field('rg_email');
                        $phone_numbers = get_field('rg_phone_numbers');
                        $address_container = get_field('rg_address_container');
                        $area_served_terms = get_the_terms(get_the_ID(), 'area-served');
                        ?>

                        <?php if ($area_served_terms && !is_wp_error($area_served_terms)) : ?>
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

                        <?php if ($email) : ?>
                            <p><strong>Email:</strong> <a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></p>
                        <?php endif; ?>

                        <?php if ($phone_numbers && is_array($phone_numbers)) : ?>
                            <p><strong>Phone:</strong> <?php echo esc_html($phone_numbers[0]['rg_phone_number']); ?></p>
                        <?php endif; ?>

                        <?php if ($address_container) : ?>
                            <p><strong>Location:</strong> 
                            <?php
                            $location_parts = array();
                            if ($address_container['rg_city']) $location_parts[] = $address_container['rg_city'];
                            if ($address_container['rg_state']) $location_parts[] = $address_container['rg_state'];
                            echo esc_html(implode(', ', $location_parts));
                            ?>
                            </p>
                        <?php endif; ?>

                        <p><a href="<?php the_permalink(); ?>">View Details →</a></p>
                    </div>

                </article>

            <?php endwhile; ?>
        </div>

        <?php the_posts_navigation(); ?>

        <!-- Back to Map -->
        <div class="rep-group-navigation">
            <p><a href="/forbes-reps/" class="button">← Back to Interactive Map</a></p>
        </div>

    <?php else : ?>
        
        <section class="no-results">
            <header class="page-header">
                <h1 class="page-title">No Rep Groups Found</h1>
            </header>

            <div class="page-content">
                <p>No rep groups are currently available. Try the <a href="/forbes-reps/">interactive map</a> instead.</p>
            </div>
        </section>

    <?php endif; ?>

</main><!-- #primary -->

<?php get_footer(); 