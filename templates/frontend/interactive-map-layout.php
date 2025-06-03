<?php
/**
 * Template for the interactive map layout.
 *
 * Expected variables:
 * $map_instance_id (string) - Unique ID for the map instance.
 * $map_type (string) - 'local' or 'international'.
 * $svg_url (string) - URL of the SVG file.
 * $svg_content (string) - The actual SVG content to inline.
 */

if (empty($map_instance_id) || empty($map_type) || !isset($svg_content)) { // svg_url is still useful for context, svg_content is critical
    echo '<!-- Rep map layout template: Missing required variables. -->';
    return;
}
?>
<div id="<?php echo esc_attr($map_instance_id); ?>" class="rep-group-map-interactive-area <?php echo esc_attr($map_type); ?>-map-interactive-area">
    <div class="rep-map-info-column">
        <div class="rep-map-column-title-area"></div>
        <div class="rep-map-default-content panel-active">
            <div class="rep-map-view-controls">
                <h4 class="rep-map-view-by-title">
                    <span>View by:</span>
                </h4>
                <select class="view-by-select">
                    <option value="rep_groups" selected>Rep Groups</option>
                    <option value="areas_served">Areas Served</option>
                </select>
                <button class="sort-toggle-button" data-sort-order="asc" aria-label="Sort Ascending">
                    <span class="sort-text"></span>
                    <ion-icon name="arrow-down"></ion-icon>
                </button>
            </div>
            <div class="rep-map-list-container">
                <!-- List content (Rep Groups or Areas Served) will be injected here by JS -->
            </div>
        </div>
        <div class="rep-map-details-content panel-hidden">
            <a href="#" class="back-to-map-default" role="button">&laquo; Back to Overview</a>
            <div class="rep-group-info-target">
                <!-- Rep group details will be loaded here via AJAX -->
            </div>
        </div>
    </div>

    <div class="rep-map-svg-column">
        <div class="svg-viewport">
            <?php
            // SVG content is now passed directly as $svg_content from the shortcode render method
            if (!empty($svg_content)) {
                // The SVG content should already have ID and class attributes from the shortcode method
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo $svg_content; 
            } else {
                echo '<!-- Rep Map: SVG content not provided or empty. -->';
            }
            ?>
        </div>
    </div>
</div> 