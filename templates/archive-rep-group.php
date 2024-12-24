<?php get_header(); ?>

<div class="post-type-archive-rep-group">
    <header class="archive-header">
        <h1 class="archive-title">Rep Groups</h1>
        <div class="archive-description">
            <p>Browse our network of representative groups across different regions. Each group provides dedicated support and service to their respective territories.</p>
        </div>
    </header>

    <div class="rep-groups-grid">
        <?php 
        if (have_posts()) :
            while (have_posts()) : the_post();
                $area_served = get_field('rg_area_served');
                ?>
                <article class="rep-group-card">
                    <div class="rep-group-image">
                        <?php 
                        if (has_post_thumbnail()) {
                            the_post_thumbnail('medium');
                        }
                        ?>
                    </div>
                    <div class="rep-group-content">
                        <h2 class="rep-group-title"><?php the_title(); ?></h2>
                        <?php if ($area_served) : ?>
                            <div class="rep-group-area">
                                <strong>Area Served:</strong> <?php echo esc_html($area_served); ?>
                            </div>
                        <?php endif; ?>
                        <a href="<?php the_permalink(); ?>" class="rep-group-link">View this Rep Group</a>
                    </div>
                </article>
                <?php
            endwhile;
            
            // Pagination if needed
            the_posts_pagination();
        else :
            echo '<p>No rep groups found.</p>';
        endif;
        ?>
    </div>
</div>

<?php get_footer(); ?> 