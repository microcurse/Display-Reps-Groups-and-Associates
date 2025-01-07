<?php
/**
 * Rep Group Archive Template
 */
?>

<div class="rep-groups-archive">
    <?php if ($rep_groups->have_posts()) : ?>
        <div class="rep-group-grid">
            <?php while ($rep_groups->have_posts()) : $rep_groups->the_post(); ?>
                <article class="rep-group-card">
                    <div class="rep-group-logo">
                        <?php if (has_post_thumbnail()) : ?>
                            <?php the_post_thumbnail('medium', array('class' => 'rep-group-image')); ?>
                        <?php else : ?>
                            <div class="rep-group-placeholder">
                                <span class="dashicons dashicons-building"></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="rep-group-content">
                        <h2 class="rep-group-title">
                            <a href="<?php the_permalink(); ?>">
                                <?php the_title(); ?>
                            </a>
                        </h2>
                        
                        <?php
                        $areas = get_the_terms(get_the_ID(), 'area-served');
                        if ($areas && !is_wp_error($areas)) : ?>
                            <div class="rep-group-areas">
                                <strong>Areas Served:</strong> 
                                <?php echo esc_html(join(', ', wp_list_pluck($areas, 'name'))); ?>
                            </div>
                        <?php endif; ?>

                        <a href="<?php the_permalink(); ?>" class="rep-group-link button">
                            View Details
                        </a>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
        
        <?php wp_reset_postdata(); ?>
        
    <?php else : ?>
        <p class="no-rep-groups">No rep groups found.</p>
    <?php endif; ?>
</div> 