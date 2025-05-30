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
            <?php
            // Fetch and display SVG content inline
            if (!empty($svg_url)) {
                $svg_path = str_replace(content_url(), WP_CONTENT_DIR, $svg_url);

                $svg_content = false; // Initialize

                if (filter_var($svg_url, FILTER_VALIDATE_URL) && strpos($svg_path, WP_CONTENT_DIR) !== 0) {
                    if (ini_get('allow_url_fopen')) {
                        $svg_content = @file_get_contents($svg_url);
                    } else {
                        // error_log('[RepMap Template] Cannot fetch remote SVG: allow_url_fopen is disabled.');
                    }
                } elseif (file_exists($svg_path)) {
                    $svg_content = @file_get_contents($svg_path);
                } else {
                    $svg_content = false;
                }

                if ($svg_content) {
                    $original_trimmed_content = trim($svg_content);
                    
                    $content_to_check = $original_trimmed_content;
                    $content_to_check = preg_replace('/^<\\?xml[^>]*\\?>\\s*/is', '', $content_to_check);
                    $content_to_check = preg_replace('/^<!--.*?-->\\s*/is', '', $content_to_check);
                    $content_to_check = preg_replace('/^<!--.*?-->\\s*/is', '', $content_to_check);

                    if (stripos($content_to_check, '<svg') === 0) {
                        $svg_id = 'rep-map-svg-' . esc_attr($map_type);
                        $svg_class = 'rep-group-map-svg-object';
                        
                        $modified_svg_content = preg_replace(
                            '/<svg/', 
                            sprintf('<svg id="%s" class="%s"', esc_attr($svg_id), esc_attr($svg_class)), 
                            $svg_content,
                            1 
                        );
                        // error_log('[RepMap Template] SVG content (original with prefixes) modified with ID/class and will be echoed.');
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        echo $modified_svg_content;
                    } else {
                        echo '<!-- Rep Map: Loaded content is not a valid SVG or has unexpected prefixes. URL: ' . esc_html($svg_url) . ' -->';
                    }
                } else {
                    echo '<!-- Rep Map: Unable to load SVG from URL: ' . esc_html($svg_url) . ' -->';
                }
            } else {
                echo '<!-- Rep Map: SVG URL not provided. -->';
            }
            ?>
        </div>
    </div>
</div> 