jQuery(document).ready(function($) {
    console.log('Admin map JS loaded');

    // Core variables
    const defaultColor = '#D8D8D8';
    const selectedColor = '#0073aa';
    let selectedState = null;
    let mediaUploader = null;

    // Initialize map
    function initializeMap() {
        // Reset all paths to default color
        $('svg path').css('fill', defaultColor);

        // Find individual paths first
        const $individualPaths = $('svg path[id^="US-"], svg path[id^="CA-"]');
        
        // Find grouped paths
        const $groupedPaths = $('svg g[id^="US-"] path, svg g[id^="CA-"] path');
        
        // Combine all paths
        const $allPaths = $individualPaths.add($groupedPaths);

        $allPaths.each(function() {
            const $path = $(this);
            const $parent = $path.parent('g');
            const pathId = $parent.is('g[id^="US-"], g[id^="CA-"]') ? $parent.attr('id') : $path.attr('id');

            // Skip if no valid ID found
            if (!pathId) return;

            // Skip separators and landmarks
            if (pathId.toLowerCase().includes('separator') || 
                pathId.toLowerCase().includes('landmarks')) {
                return;
            }

            // Make path clickable
            $path.css('cursor', 'pointer');

            // Hover handlers
            $path.on('mouseenter', function(e) {
                e.stopPropagation();
                if (pathId !== selectedState) {
                    if ($parent.is('g[id^="US-"], g[id^="CA-"]')) {
                        $parent.find('path').css('fill', selectedColor);
                    } else {
                        $path.css('fill', selectedColor);
                    }
                }
            }).on('mouseleave', function(e) {
                e.stopPropagation();
                if (pathId !== selectedState) {
                    if ($parent.is('g[id^="US-"], g[id^="CA-"]')) {
                        $parent.find('path').css('fill', defaultColor);
                    } else {
                        $path.css('fill', defaultColor);
                    }
                }
            });

            // Click handler
            $path.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Reset previous selection
                if (selectedState) {
                    const $prevRegion = $(`#${selectedState}`);
                    if ($prevRegion.is('g')) {
                        $prevRegion.find('path').css('fill', defaultColor);
                    } else {
                        $prevRegion.css('fill', defaultColor);
                    }
                }

                // Update selection
                selectedState = pathId;
                if ($parent.is('g[id^="US-"], g[id^="CA-"]')) {
                    $parent.find('path').css('fill', selectedColor);
                } else {
                    $path.css('fill', selectedColor);
                }

                // Update UI
                $('#selected-state').text(pathId);
                fetchStateAssignments(pathId);
            });
        });
    }

    // Media uploader button
    $('#upload-map-svg').on('click', function(e) {
        e.preventDefault();
        
        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            console.error('WordPress Media Library not available');
            return;
        }
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media({
            title: 'Select SVG Map',
            button: { text: 'Use this map' },
            multiple: false,
            library: { type: ['image/svg+xml'] }
        });

        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            
            $.ajax({
                url: repGroupsAdmin.ajaxurl,
                method: 'POST',
                data: {
                    action: 'update_map_svg',
                    nonce: repGroupsAdmin.nonce,
                    attachment_id: attachment.id
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error updating map: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error updating map: ' + error);
                }
            });
        });

        mediaUploader.open();
    });

    // Remove map button
    $('#remove-map-svg').on('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to remove the current map?')) {
            $.ajax({
                url: repGroupsAdmin.ajaxurl,
                method: 'POST',
                data: {
                    action: 'remove_map_svg',
                    nonce: repGroupsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error removing map');
                    }
                }
            });
        }
    });

    function fetchStateAssignments(stateId) {
        $.ajax({
            url: repGroupsAdmin.ajaxurl,
            method: 'POST',
            data: {
                action: 'get_state_rep_groups_admin',
                nonce: repGroupsAdmin.nonce,
                state: stateId
            },
            success: function(response) {
                if (response.success) {
                    $('#rep-group-select').val(response.data.rep_groups).trigger('change');
                }
            }
        });
    }

    // Initialize the map
    initializeMap();
}); 