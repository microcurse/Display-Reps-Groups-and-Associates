<?php
/**
 * Template for displaying the list of all rep groups for the map's default panel.
 *
 * Expected variables:
 * $all_rep_groups (array) - Array of WP_Post objects for rep groups.
 * $map_instance_id (string) - The unique ID for the current map instance.
 */

if (!isset($map_instance_id)) {
    // Fallback or error, though it should always be provided by the calling function
    $map_instance_id = 'map-instance-fallback-' . wp_generate_uuid4(); 
}
?>
<div class="rep-group-list-container">
    <h2>Rep Groups</h2>
    <p>All Forbes Reps Groups and Associates.</p>
    <ul class="rep-group-list-map-default default-rep-group-list">
        <?php if (empty($all_rep_groups)) : ?>
            <li>No rep groups found.</li>
    <?php else : ?>
        <?php foreach ($all_rep_groups as $rep_group) : ?>
            <li data-rep-group-id="<?php echo esc_attr($rep_group->ID); ?>">
                <a href="#" class="rep-group-list-item-link" data-map-instance-id="<?php echo esc_attr($map_instance_id); ?>">
                    <?php echo esc_html(get_the_title($rep_group->ID)); ?>
                </a>
            </li>
        <?php endforeach; ?>
    <?php endif; ?>
</ul> 
</div>