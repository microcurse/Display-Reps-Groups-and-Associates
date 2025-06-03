<?php
/**
 * Template for displaying the list of "Rep Groups" for the map's default panel.
 *
 * Expected variables:
 * $rep_groups_data (array) - Array of rep group data, each with ['id', 'title'].
 * $map_instance_id (string) - The unique ID for the current map instance.
 */

if (!isset($map_instance_id)) {
    // Fallback or error, though it should always be provided by the calling function
    $map_instance_id = 'map-instance-fallback-' . wp_generate_uuid4(); 
}
?>
<ul class="rep-group-list default-list-view" data-list-type="rep_groups">
    <?php if (empty($rep_groups_data)) : ?>
        <li>No Rep Groups found.</li>
    <?php else : ?>
        <?php foreach ($rep_groups_data as $group) : ?>
            <li>
                <a href="#" 
                   class="rep-group-list-item-link" 
                   data-rep-group-id="<?php echo esc_attr($group['id']); ?>" 
                   data-map-instance-id="<?php echo esc_attr($map_instance_id); ?>"
                   data-area-color="<?php echo esc_attr($group['color']); ?>">
                    <span class="area-color-indicator" style="background-color:<?php echo esc_attr($group['color']); ?>;"></span>
                    <span class="list-item-text"><?php echo esc_html($group['title']); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    <?php endif; ?>
</ul> 