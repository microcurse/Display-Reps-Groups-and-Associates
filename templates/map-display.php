<?php
/**
 * Template for displaying the dynamic map.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get SVG URLs from options
$local_svg_url = get_option('rep_group_local_svg');
$international_svg_url = get_option('rep_group_international_svg');

// Get all terms with their SVG IDs
$taxonomy_manager = new RepGroup\Taxonomy_Manager();
$areas_data = $taxonomy_manager->get_all_terms_with_svg_ids();

// Get all rep groups (you might want to optimize this later)
$rep_groups_query = new WP_Query([
    'post_type' => 'rep-group',
    'posts_per_page' => -1,
]);
$rep_groups_data = [];
if ($rep_groups_query->have_posts()) {
    while($rep_groups_query->have_posts()) {
        $rep_groups_query->the_post();
        $post_id = get_the_ID();
        $terms = $taxonomy_manager->get_areas_served_by_rep_group($post_id);
        $associates = get_field('associates', $post_id); // Assuming ACF for associates

        $rep_groups_data[] = [
            'id' => $post_id,
            'name' => get_the_title(),
            'logo' => get_field('logo', $post_id), // Assuming ACF
            'website' => get_field('website', $post_id), // Assuming ACF
            'description' => get_field('description', $post_id), // Assuming ACF
            'contact_info' => get_field('contact_info', $post_id), // Assuming ACF
            'areas_served' => wp_list_pluck($terms, 'term_id'),
            'associates' => $associates ?: []
        ];
    }
    wp_reset_postdata();
}

?>

<div id="rep-map-container">
    <div class="map-controls">
        <div class="map-filters">
            <label for="filter-rep-group">Filter by Rep Group:</label>
            <select id="filter-rep-group">
                <option value="">All Rep Groups</option>
                <?php foreach ($rep_groups_data as $group): ?>
                    <option value="<?php echo esc_attr($group['id']); ?>"><?php echo esc_html($group['name']); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="filter-area">Filter by Area:</label>
            <select id="filter-area">
                <option value="">All Areas</option>
                <?php
                $all_areas_sorted = [];
                foreach ($areas_data as $svg_id => $area) {
                    $all_areas_sorted[$area['term_id']] = $area['name'];
                }
                asort($all_areas_sorted); // Sort areas by name
                foreach ($all_areas_sorted as $term_id => $name): ?>
                    <option value="<?php echo esc_attr($term_id); ?>"><?php echo esc_html($name); ?></option>
                <?php endforeach; ?>
            </select>
            
            <?php // Note: Country/Region filter might be complex if not part of existing data taxonomy.
                  // For now, this example assumes 'Areas Served' can be hierarchical for regions/countries,
                  // or this filter might be added later if a separate 'Country' taxonomy exists.
                  // We will focus on Rep Group and Area filters first.
            ?>
        </div>
        <button id="toggle-map-type">Switch to International Map</button>
        <button id="toggle-view-type">Switch to View by Rep Group</button>
    </div>

    <div id="map-local" class="map-active">
        <?php
        if ($local_svg_url) {
            $svg_content_local = @file_get_contents($local_svg_url);
            if ($svg_content_local) {
                echo $svg_content_local;
            } else {
                echo '<p>Error: Could not load local SVG map. Please check the URL in Map Settings.</p>';
            }
        } else {
            echo '<p>Local SVG map not configured. Please set it in Map Settings.</p>';
        }
        ?>
    </div>
    <div id="map-international" style="display: none;">
        <?php
        if ($international_svg_url) {
            $svg_content_international = @file_get_contents($international_svg_url);
            if ($svg_content_international) {
                echo $svg_content_international;
            } else {
                echo '<p>Error: Could not load international SVG map. Please check the URL in Map Settings.</p>';
            }
        } else {
            echo '<p>International SVG map not configured. Please set it in Map Settings.</p>';
        }
        ?>
    </div>

    <div id="map-sidebar" style="display: none;">
        <h3 id="sidebar-title"></h3>
        <div id="sidebar-content"></div>
        <button id="close-sidebar">Close</button>
    </div>
</div>

<script type="text/javascript">
    const repMapAreasData = <?php echo json_encode($areas_data); ?>;
    const repMapRepGroupsData = <?php echo json_encode($rep_groups_data); ?>;
    const repMapLocalSvgUrl = <?php echo json_encode($local_svg_url); ?>;
    const repMapInternationalSvgUrl = <?php echo json_encode($international_svg_url); ?>;
</script>
