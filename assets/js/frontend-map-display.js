/**
 * Frontend Map Display JavaScript
 */
(function($) {
  function initRepMap(mapData) {
      const mapContainer = $('#' + mapData.map_id);
      if (!mapContainer.length) {
          return;
      }

      const objectTag = mapContainer.find('object.rep-group-map-svg-object');
      if (!objectTag.length) {
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
      // If it's a group, try to apply to direct visual children
      if (element.is('g')) {
          element.children('path, rect, circle, polygon, ellipse').each(function() {
              const child = $(this);
              // Only fill children if they aren't also mapped regions themselves with a *different* ID.
              // If child has same ID as parent group, or no ID, it should inherit the group's color.
              if (!child.attr('id') || child.attr('id') === element.attr('id')) { 
                   child.css('fill', color);
              }
          });
      }
  }

  function processSvg(svgObjectElement, mapData) {
      try {
          const svgDoc = svgObjectElement.contentDocument;
          if (!svgDoc) {
              // console.error('RepMap: SVG contentDocument not found for', svgObjectElement);
              return;
          }
          const svgRoot = $(svgDoc).find('svg');
          if (!svgRoot.length) {
              // console.error('RepMap: SVG root element not found in', svgObjectElement);
              return;
          }
          
          // console.log('RepMap: Processing SVG. Map Data for areas:', mapData.area_data);

          // Iterate over all potential clickable elements, including groups
          svgRoot.find('path, g, rect, circle, polygon, ellipse').each(function() {
              const el = $(this);
              const elId = el.attr('id');

              if (elId) {
                  //// console.log('RepMap: Found SVG element with ID:', elId);
                  if (mapData.area_data && mapData.area_data[elId]) {
                      const areaInfo = mapData.area_data[elId];
                      const color = areaInfo.color || mapData.default_region_color;
                      // console.log('RepMap: MATCH! Applying color', color, 'to SVG ID:', elId, 'Data:', areaInfo);
                      applyFillToElementAndChildren(el, color);
                      el.addClass('mapped-region-frontend'); 
                      if (mapData.is_interactive) {
                          el.css('cursor', 'pointer');
                          el.on('click', function(e) {
                              e.preventDefault();
                              e.stopPropagation(); // Prevent event from bubbling to parent SVG elements if nested
                              // console.log('RepMap: Clicked on region:', elId, 'Color:', color);
                              displayRepInfoForArea(elId, color, mapData.map_id, mapData.nonce, mapData.ajax_url);
                          });
                      }
                       el.hover(
                          function() { $(this).addClass('hover-region-frontend'); },
                          function() { $(this).removeClass('hover-region-frontend'); }
                      );
                  } else {
                      //// console.log('RepMap: SVG ID:', elId, 'NOT found in area_data.');
                  }
              }
          });
      } catch (e) {
          // console.error('RepMap: Error processing frontend SVG:', e);
      }
  }
  
  function displayRepInfoForArea(areaSlug, areaColor, mapInstanceId, nonce, ajaxUrl) {
      const mapInteractiveArea = $('#' + mapInstanceId);
      const infoColumn = mapInteractiveArea.find('.rep-map-info-column');
      const defaultContent = infoColumn.find('.rep-map-default-content');
      const detailsContent = infoColumn.find('.rep-map-details-content');
      const infoTarget = detailsContent.find('.rep-group-info-target');

      // Prepare for animation
      infoTarget.html('<p><em>Loading rep group...</em></p>'); // Placeholder

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
          area_slug: areaSlug, // Send term slug
          // area_color: areaColor // Not strictly needed by backend if backend determines color
      }).done(function(response) {
          if (response.success) {
              infoTarget.html(response.data.html);
              // Optionally, re-apply color to the clicked SVG element if backend sends a definitive color
              if (response.data.color && response.data.term_name) { // Ensure term_name (slug) is also part of response if needed to find element
                  const svgObject = mapInteractiveArea.find('object.rep-group-map-svg');
                  if (svgObject.length && svgObject[0].contentDocument) {
                      const svgDoc = svgObject[0].contentDocument;
                      // areaSlug is the ID of the clicked element
                      const clickedElement = $(svgDoc).find('#' + areaSlug);
                      if (clickedElement.length) {
                          applyFillToElementAndChildren(clickedElement, response.data.color);
                      }
                  }
              }
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

  function displayRepGroupDetailsById(repGroupId, mapInstanceId, nonce, ajaxUrl) {
      const mapInteractiveArea = $('#' + mapInstanceId);
      const infoColumn = mapInteractiveArea.find('.rep-map-info-column');
      const defaultContent = infoColumn.find('.rep-map-default-content');
      const detailsContent = infoColumn.find('.rep-map-details-content');
      const infoTarget = detailsContent.find('.rep-group-info-target');

      infoTarget.html('<p><em>Loading Rep Group details...</em></p>');

      if (defaultContent.hasClass('panel-active')) {
          defaultContent.removeClass('panel-active').addClass('slide-out');
          detailsContent.removeClass('panel-hidden').addClass('panel-active slide-in');
      } else {
          detailsContent.removeClass('panel-hidden').addClass('panel-active');
      }
      infoColumn.scrollTop(0);

      $.post(ajaxUrl, {
          action: 'get_rep_group_details_by_id',
          nonce: nonce,
          rep_group_id: repGroupId
      }).done(function(response) {
          if (response.success) {
              infoTarget.html(response.data.html);
          } else {
              infoTarget.html('<p class="error-message">' + (response.data.message || 'Could not load Rep Group details.') + '</p>');
          }
      }).fail(function() {
          infoTarget.html('<p class="error-message">AJAX error loading Rep Group details.</p>');
      }).always(function() {
          setTimeout(function() {
              infoColumn.find('.slide-out').removeClass('slide-out').addClass('panel-hidden');
              infoColumn.find('.slide-in').removeClass('slide-in');
          }, 300);
      });
  }

  // Initialize all maps on the page
  $(function() {
      // Click handler for the details panel close button (now "Back to Overview" link)
      $(document).on('click', '.rep-map-details-content .back-to-map-default', function(e) {
          e.preventDefault();
          const mapInteractiveArea = $(this).closest('.rep-group-map-interactive-area');
          const infoColumn = mapInteractiveArea.find('.rep-map-info-column');
          const defaultContent = infoColumn.find('.rep-map-default-content');
          const detailsContent = infoColumn.find('.rep-map-details-content');
          
          if (detailsContent.hasClass('panel-active')) {
              detailsContent.removeClass('panel-active').addClass('slide-out');
              defaultContent.removeClass('panel-hidden').addClass('panel-active slide-in');
          }
          
          infoColumn.scrollTop(0);

          setTimeout(function() {
              infoColumn.find('.slide-out').removeClass('slide-out').addClass('panel-hidden');
              infoColumn.find('.slide-in').removeClass('slide-in');
          }, 300);
      });

      // New click handler for rep group list items in the default view
      $(document).on('click', '.rep-map-default-content .rep-group-list-item-link', function(e) {
          e.preventDefault();
          const listItem = $(this).closest('li');
          const repGroupId = listItem.data('rep-group-id');
          const mapInteractiveArea = listItem.closest('.rep-group-map-interactive-area');
          const mapInstanceId = mapInteractiveArea.attr('id');
          
          // We need nonce and ajax_url, which are part of the localized RepMapData_ for this specific map instance
          const mapData = window['RepMapData_' + mapInstanceId.replace(/-/g, '_')]; // map_instance_id has hyphens
          if (repGroupId && mapInstanceId && mapData && mapData.nonce && mapData.ajax_url) {
              displayRepGroupDetailsById(repGroupId, mapInstanceId, mapData.nonce, mapData.ajax_url);
          } else {
              // Could not retrieve rep group ID or map data for AJAX call.
          }
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