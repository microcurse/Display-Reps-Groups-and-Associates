/**
 * Frontend Map Display JavaScript
 */
(function($) {
    function initRepMap(mapData) {
        const mapContainer = $('#' + mapData.map_id);
        if (!mapContainer.length) {
            // console.log('Map container not found for:', mapData.map_id);
            return;
        }

        const objectTag = mapContainer.find('object.rep-group-map-svg');
        if (!objectTag.length) {
            // console.log('SVG object tag not found in:', mapData.map_id);
            return;
        }

        // Check if SVG is already loaded (e.g., by browser cache)
        if (objectTag[0].contentDocument && objectTag[0].contentDocument.readyState === 'complete') {
            processSvg(objectTag[0], mapData);
        } else {
            objectTag.on('load', function() {
                processSvg(this, mapData);
            });
        }
    }

    function applyFillToElementAndChildren(element, color) {
        // Apply to the element itself
        element.css('fill', color);
        // If it's a group, try to apply to direct visual children that don't have their own explicit ID
        // (as those might be separate clickable regions or have their own styling)
        if (element.is('g')) {
            element.children('path, rect, circle, polygon, ellipse').each(function() {
                const child = $(this);
                // Only fill children if they aren't also mapped regions themselves
                // or if you want the group color to override specific child styling from map_links.
                // For now, let's assume group color should try to fill its direct, non-ID'd children.
                if (!child.attr('id')) { // Simple check: if child has no ID, it's likely part of the parent group's visual
                     child.css('fill', color);
                }
            });
        }
    }

    function processSvg(svgObjectElement, mapData) {
        try {
            const svgDoc = svgObjectElement.contentDocument;
            if (!svgDoc) return;
            const svgElements = $(svgDoc).find('path, g, rect, circle, polygon, ellipse');
            
            svgElements.each(function() {
                const el = $(this);
                const elId = el.attr('id');
                if (elId && mapData.map_links && mapData.map_links[elId]) {
                    const linkData = mapData.map_links[elId];
                    const color = linkData.color || mapData.default_color;
                    const termId = linkData.term_id;
                    applyFillToElementAndChildren(el, color);
                    el.addClass('mapped-region-frontend'); 
                    if (termId) {
                        el.css('cursor', 'pointer');
                        el.on('click', function(e) {
                            e.preventDefault();
                            displayRepInfoForArea(termId, color, mapData.map_id, mapData.nonce, mapData.ajax_url);
                        });
                    }
                     el.hover(
                        function() { $(this).addClass('hover-region-frontend'); },
                        function() { $(this).removeClass('hover-region-frontend'); }
                    );
                }
            });
        } catch (e) {
            console.error('Error processing frontend SVG for map:', mapData.map_id, e);
        }
    }
    
    function displayRepInfoForArea(termId, areaColor, mapInstanceId, nonce, ajaxUrl) {
        const mapInteractiveArea = $('#' + mapInstanceId);
        const infoColumn = mapInteractiveArea.find('.rep-map-info-column');
        const defaultContent = infoColumn.find('.rep-map-default-content');
        const detailsContent = infoColumn.find('.rep-map-details-content');
        const infoTarget = detailsContent.find('.rep-group-info-target');

        // Prepare for animation
        infoTarget.html('<p><em>Loading details...</em></p>'); // Placeholder

        // If default is currently active, slide it out
        if (defaultContent.hasClass('panel-active')) {
            defaultContent.removeClass('panel-active').addClass('slide-out');
            detailsContent.removeClass('panel-hidden').addClass('panel-active slide-in'); 
        } else {
            // Details content is already active, just update its content (no new slide animation needed here, but ensure it's visible)
            detailsContent.removeClass('panel-hidden').addClass('panel-active'); 
            // Potentially add a subtle fade or quick refresh animation if desired for content update
        }
        
        // Scroll info column to top when new content is loaded/displayed
        infoColumn.scrollTop(0);

        $.post(ajaxUrl, {
            action: 'get_rep_group_info_for_area',
            nonce: nonce,
            term_id: termId,
            area_color: areaColor
        }).done(function(response) {
            if (response.success) {
                infoTarget.html(response.data.html);
            } else {
                infoTarget.html('<p class="error-message">' + (response.data.message || 'Could not load rep details.') + '</p>');
            }
        }).fail(function() {
            infoTarget.html('<p class="error-message">AJAX error loading rep details.</p>');
        }).always(function() {
            // Clean up animation classes after animation duration (300ms)
            setTimeout(function() {
                infoColumn.find('.slide-out').removeClass('slide-out').addClass('panel-hidden');
                infoColumn.find('.slide-in').removeClass('slide-in'); // Now it's just panel-active, position:static
            }, 300);
        });
    }

    // Initialize all maps on the page
    $(function() {
        $(document).on('click', '.rep-map-details-content .back-to-map-default', function(e) {
            e.preventDefault(); // It's an <a> tag now
            const infoColumn = $(this).closest('.rep-map-info-column');
            const defaultContent = infoColumn.find('.rep-map-default-content');
            const detailsContent = infoColumn.find('.rep-map-details-content');
            
            if (detailsContent.hasClass('panel-active')) {
                detailsContent.removeClass('panel-active').addClass('slide-out');
                defaultContent.removeClass('panel-hidden').addClass('panel-active slide-in');
            }
            
            // Scroll info column to top
            infoColumn.scrollTop(0);

            // Clean up animation classes after animation duration
            setTimeout(function() {
                infoColumn.find('.slide-out').removeClass('slide-out').addClass('panel-hidden');
                infoColumn.find('.slide-in').removeClass('slide-in');
            }, 300);
        });

        for (const key in window) {
            if (window.hasOwnProperty(key) && key.startsWith('RepMapData_')) {
                initRepMap(window[key]);
            }
        }
    });

    // We also need some general RepGroupData for term links if possible.
    // This would be localized once by the plugin, not per map instance.
    // Example: if (typeof RepGroupData === 'undefined') { const RepGroupData = {}; }

})(jQuery); 