<?php
/**
 * Template for displaying the list of "Areas Served" terms for the map's default panel.
 *
 * Expected variables:
 * $terms_data (array) - Array of term data, each with ['id', 'name', 'slug', 'svg_id'].
 * $map_instance_id (string) - The unique ID for the current map instance.
 * $map_links_data (array) - SVG ID keyed array with color and other link info.
 * $default_region_color (string) - Default color for regions.
 */

if (!isset($map_instance_id)) {
    $map_instance_id = 'map-instance-fallback-' . wp_generate_uuid4(); 
}
if (!isset($default_region_color)) {
    $default_region_color = defined('REP_GROUP_DEFAULT_REGION_COLOR') ? REP_GROUP_DEFAULT_REGION_COLOR : '#CCCCCC';
}

?>
<ul class="areas-served-list default-list-view" data-list-type="areas_served">
    <?php if (empty($terms_data)) : ?>
        <li>No Areas Served are currently assigned to Rep Groups or linked to map regions.</li>
    <?php else : ?>
        <?php foreach ($terms_data as $term) : ?>
            <?php
            $area_color = $default_region_color;
            // Try to find the color for this area from map_links_data using the term's svg_id
            // The keys in map_links_data are the svg_ids (without #)
            $svg_id_key = !empty($term['svg_id']) ? ltrim($term['svg_id'], '#') : null;

            if ($svg_id_key && isset($map_links_data[$svg_id_key]) && !empty($map_links_data[$svg_id_key]['color'])) {
                $area_color = $map_links_data[$svg_id_key]['color'];
            }
            ?>
            <li data-area-slug="<?php echo esc_attr($term['slug']); ?>" 
                data-term-id="<?php echo esc_attr($term['id']); ?>">
                <a href="#" 
                   class="area-served-list-item-link" 
                   data-map-instance-id="<?php echo esc_attr($map_instance_id); ?>"
                   data-svg-id="<?php echo esc_attr($svg_id_key); ?>" 
                   data-area-color="<?php echo esc_attr($area_color); ?>"
                   data-area-name="<?php echo esc_attr($term['name']); ?>">
                    <span class="area-color-indicator" style="background-color:<?php echo esc_attr($area_color); ?>;"></span>
                    <span class="list-item-text"><?php echo esc_html($term['name']); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    <?php endif; ?>
</ul> 