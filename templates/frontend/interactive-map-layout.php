<?php
/**
 * Template for the interactive map layout.
 *
 * Expected variables:
 * $map_instance_id (string) - Unique ID for the map instance.
 * $map_type (string) - 'local' or 'international'.
 * $svg_url (string) - URL of the SVG file.
 * $default_panel_content (string) - HTML content for the default info panel (list of rep groups).
 */

if (empty($map_instance_id) || empty($map_type) || empty($svg_url) || !isset($default_panel_content)) {
    echo '<!-- Rep map layout template: Missing required variables. -->';
    return;
}
?>
<div id="<?php echo esc_attr($map_instance_id); ?>" class="rep-group-map-interactive-area <?php echo esc_attr($map_type); ?>-map-interactive-area">
    <div class="rep-map-info-column">
        <div class="rep-map-default-content panel-active">
            <?php 
            // Outputting the pre-rendered default panel content
            // This content is already escaped by its generating function/template
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $default_panel_content; 
            ?>
        </div>
        <div class="rep-map-details-content panel-hidden">
            <a href="#" class="back-to-map-default" role="button">&laquo; Back to Overview</a>
            <div class="rep-group-info-target"></div>
        </div>
    </div>

    <div class="rep-map-svg-column">
        <div class="svg-viewport"> 
            <object id="rep-map-svg-<?php echo esc_attr($map_type); ?>" class="rep-group-map-svg-object" type="image/svg+xml" data="<?php echo esc_url($svg_url); ?>" aria-label="<?php echo esc_attr(ucfirst($map_type)); ?> map"></object>
        </div>
    </div>
</div> 