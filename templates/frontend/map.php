<div class="rep-group-map-container">
    <div class="map-wrapper">
        <?php 
        $svg_path = get_attached_file($map_svg_id);
        if ($svg_path && file_exists($svg_path)) {
            echo file_get_contents($svg_path);
        }
        ?>
    </div>
    <div id="rep-groups-results" class="rep-groups-results"></div>
</div> 