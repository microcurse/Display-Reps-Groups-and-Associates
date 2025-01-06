<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="map-manager-layout">
        <div class="map-section">
            <div class="postbox">
                <div class="postbox-header">
                    <h2>Map SVG</h2>
                </div>
                <div class="inside">
                    <?php if (!$map_svg_id): ?>
                        <div class="notice notice-warning inline">
                            <p>No map SVG uploaded. Please upload an SVG map with state paths using IDs in the format "US-XX".</p>
                        </div>
                    <?php endif; ?>
                    
                    <p>
                        <button type="button" class="button upload-map">
                            <?php echo $map_svg_id ? 'Change Map SVG' : 'Upload Map SVG'; ?>
                        </button>
                        
                        <?php if ($map_svg_id): ?>
                            <button type="button" class="button remove-map">Remove Map</button>
                        <?php endif; ?>
                    </p>

                    <?php if ($map_svg_id): ?>
                        <div class="current-map">
                            <?php 
                            $svg_path = get_attached_file($map_svg_id);
                            if ($svg_path && file_exists($svg_path)) {
                                echo file_get_contents($svg_path);
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($map_svg_id): ?>
            <div class="assignments-section">
                <div class="postbox">
                    <div class="postbox-header">
                        <h2>State Assignments</h2>
                    </div>
                    <div class="inside">
                        <div class="state-selector">
                            <h3>Selected State: <span id="selected-state">None</span></h3>
                            
                            <div class="rep-group-selector">
                                <label for="rep-group-select">Assign Representatives:</label>
                                <select multiple="multiple" id="rep-group-select" class="regular-text">
                                    <?php
                                    $rep_groups = get_posts([
                                        'post_type' => 'rep-group',
                                        'posts_per_page' => -1,
                                        'orderby' => 'title',
                                        'order' => 'ASC'
                                    ]);
                                    
                                    foreach ($rep_groups as $group) {
                                        printf(
                                            '<option value="%d">%s</option>',
                                            $group->ID,
                                            esc_html($group->post_title)
                                        );
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <p class="submit">
                                <button type="button" class="button button-primary save-assignments">Save Assignments</button>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div> 