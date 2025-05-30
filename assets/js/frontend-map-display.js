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

      const handleLoad = function() {
        processSvg(this, mapData); // 'this' is the objectTag element
        if (mapData.is_interactive) {
            initPanZoomForMap(mapData.map_id, this, mapData);
        }
      };

      if (objectTag[0].contentDocument && objectTag[0].contentDocument.readyState === 'complete') {
        // Call handleLoad with objectTag[0] as context for 'this'
        handleLoad.call(objectTag[0]); 
      } else {
          objectTag.on('load', handleLoad);
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
                              displayRepInfoForArea(elId, color, mapData.map_id, mapData.nonce, mapData.ajax_url, mapData.default_region_color);
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
  
  function displayRepInfoForArea(areaSlug, areaColor, mapInstanceId, nonce, ajaxUrl, defaultRegionColorParam) {
      const mapInteractiveArea = $('#' + mapInstanceId);
      const infoColumn = mapInteractiveArea.find('.rep-map-info-column');
      const defaultContent = infoColumn.find('.rep-map-default-content');
      const detailsContent = infoColumn.find('.rep-map-details-content');
      const infoTarget = detailsContent.find('.rep-group-info-target');

      // Prepare for animation
      infoTarget.html('<p><em>Loading rep group...</em></p>');

      // If default is currently active, slide it out
      if (defaultContent.hasClass('panel-active')) {
          defaultContent.removeClass('panel-active').addClass('slide-out');
          detailsContent.removeClass('panel-hidden').addClass('panel-active slide-in'); 
      } else {
          detailsContent.removeClass('panel-hidden').addClass('panel-active'); 
      }
      
      infoColumn.scrollTop(0);

      // Clear previous border color and class
      detailsContent.removeClass('has-left-border').css('--area-specific-color', '');

      $.post(ajaxUrl, {
          action: 'get_rep_group_info_for_area',
          nonce: nonce,
          area_slug: areaSlug,
      }).done(function(response) {
          if (response.success) {
              infoTarget.html(response.data.html);
              const effectiveColor = response.data.color || areaColor || defaultRegionColorParam;
              if (effectiveColor) {
                  detailsContent.addClass('has-left-border').css('--area-specific-color', effectiveColor);
                  requestAnimationFrame(() => {
                      detailsContent.addClass('animate-border-in');
                  });
              }

              // Re-apply fill to SVG if backend determined a specific color for the region
              if (response.data.color && response.data.term_name) { 
                  const svgObject = mapInteractiveArea.find('object.rep-group-map-svg-object');
                  if (svgObject.length && svgObject[0].contentDocument) {
                      const svgDoc = svgObject[0].contentDocument;
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
          setTimeout(function() {
              infoColumn.find('.slide-out').removeClass('slide-out').addClass('panel-hidden');
              infoColumn.find('.slide-in').removeClass('slide-in');
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

      // Clear previous border color and class
      detailsContent.removeClass('has-left-border').css('--area-specific-color', '');

      $.post(ajaxUrl, {
          action: 'get_rep_group_details_by_id',
          nonce: nonce,
          rep_group_id: repGroupId
      }).done(function(response) {
          if (response.success) {
              infoTarget.html(response.data.html);
              // Expect response.data.color to be sent from backend
              if (response.data.color) { 
                  detailsContent.addClass('has-left-border').css('--area-specific-color', response.data.color);
                  requestAnimationFrame(() => {
                      detailsContent.addClass('animate-border-in');
                  });
              } else {
                  // Fallback if color not provided by this specific endpoint response
                  // This case might need a default color from mapData or a fixed fallback.
                  const mapData = window['RepMapData_' + mapInstanceId.replace(/-/g, '_')];
                  const fallbackColor = mapData ? mapData.default_region_color : '#CCCCCC';
                  detailsContent.addClass('has-left-border').css('--area-specific-color', fallbackColor);
                  requestAnimationFrame(() => {
                      detailsContent.addClass('animate-border-in');
                  });
              }
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
              detailsContent.removeClass('panel-active slide-out has-left-border animate-border-in').addClass('slide-out');
              detailsContent.css('--area-specific-color', '');
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

  // --- New Pan and Zoom Functionality ---
  const mapStates = {}; // Store state per mapInstanceId

  function applyTransform(state) {
      if (state && state.svgObject && state.svgObject.length) {
          console.log('Applying transform:', `translate(${state.panX}px, ${state.panY}px) scale(${state.scale})`, state.svgObject);
          state.svgObject.css('transform', `translate(${state.panX}px, ${state.panY}px) scale(${state.scale})`);
      }
  }

  function handlePanMouseDown(event, state, mapInstanceId) {
      console.log('PanMouseDown on map:', mapInstanceId, 'Target:', event.target, 'State:', state);
      event.preventDefault(); // Re-enable to prevent default browser drag on SVG content
      state.isPanning = true;
      state.isDragging = false; 
      state.startX = event.clientX;
      state.startY = event.clientY;
      state.lastMouseX = event.clientX;
      state.lastMouseY = event.clientY;
      if (state.viewport) state.viewport.css('cursor', 'grabbing');
  }

  function handlePanMouseMove(event, state, mapInstanceId) {
      if (!state.isPanning) return;
      // console.log('PanMouseMove on map:', mapInstanceId); // Can be noisy

      const dx = event.clientX - state.lastMouseX;
      const dy = event.clientY - state.lastMouseY;

      if (!state.isDragging) {
          const moveX = Math.abs(event.clientX - state.startX);
          const moveY = Math.abs(event.clientY - state.startY);
          if (moveX > state.dragThreshold || moveY > state.dragThreshold) {
              console.log('Drag threshold exceeded, starting drag for map:', mapInstanceId);
              state.isDragging = true;
              // If we decide to prevent path clicks only after drag starts, this might be a place
              // to temporarily set pointer-events: none on the svgObject if needed for reliability
              // though stopping propagation on mouseup is preferred.
          }
      }

      if (state.isDragging) {
        state.panX += dx;
        state.panY += dy;
        applyTransform(state);
      }
      
      state.lastMouseX = event.clientX;
      state.lastMouseY = event.clientY;
  }

  function handlePanMouseUp(event, state, mapInstanceId) {
      console.log('PanMouseUp on map:', mapInstanceId, 'isDragging:', state.isDragging);
      state.isPanning = false;
      if (state.viewport) state.viewport.css('cursor', 'grab');

      if (state.isDragging) {
          console.log('Drag ended, preventing click for map:', mapInstanceId);
          event.preventDefault(); // Prevent default actions if it was a drag
          event.stopImmediatePropagation(); // Crucial: stop click on underlying SVG path
      }
      // If it wasn't a drag, the click on the SVG path (if any) should proceed normally.
      state.isDragging = false; // Reset for next interaction
  }
  
  function handleWheelZoom(event, state, mapInstanceId) {
      console.log('WheelZoom on map:', mapInstanceId, 'DeltaY:', event.deltaY);
      event.preventDefault(); // Always prevent default for wheel to stop page scroll
      event.stopImmediatePropagation(); // Also stop propagation to prevent path clicks during zoom

      const oldScale = state.scale;
      const rect = state.viewport[0].getBoundingClientRect();
      const mouseXViewport = event.clientX - rect.left;
      const mouseYViewport = event.clientY - rect.top;

      // Determine zoom direction
      if (event.deltaY < 0) { // Zooming In (typically scroll down/forward)
          state.scale *= state.zoomFactor;
      } else { // Zooming Out (typically scroll up/backward)
          state.scale /= state.zoomFactor;
      }

      // Clamp scale
      state.scale = Math.max(state.minScale, Math.min(state.maxScale, state.scale));

      // Adjust pan to zoom towards the mouse pointer
      // Calculations from: https://stackoverflow.com/a/29162408/126353
      // (and similar pan/zoom libraries)
      state.panX = mouseXViewport - (mouseXViewport - state.panX) * (state.scale / oldScale);
      state.panY = mouseYViewport - (mouseYViewport - state.panY) * (state.scale / oldScale);

      applyTransform(state);
  }

  function initPanZoomForMap(mapInstanceId, svgObjectElement, mapData) {
      console.log('initPanZoomForMap called for:', mapInstanceId, 'SVG Element:', svgObjectElement);
      const viewport = $(svgObjectElement).parent('.svg-viewport');
      console.log('Viewport found:', viewport);

      if (!viewport.length) {
          console.error('SVG viewport not found for map:', mapInstanceId);
          return;
      }

      mapStates[mapInstanceId] = {
          scale: 1,
          panX: 0,
          panY: 0,
          isPanning: false,
          isDragging: false,      
          dragThreshold: 5,       
          startX: 0,              
          startY: 0,              
          lastMouseX: 0,
          lastMouseY: 0,
          svgObject: $(svgObjectElement),
          viewport: viewport,
          minScale: 0.5, 
          maxScale: 5,   
          zoomFactor: 1.1 
      };

      const state = mapStates[mapInstanceId];
      applyTransform(state); 

      viewport.on('mousedown', function(event) { // This is jQuery event object
          console.log('Viewport mousedown event. Target:', event.target, ' map:', mapInstanceId);
          handlePanMouseDown(event.originalEvent, state, mapInstanceId); // Pass native event
      });

      $(document).on('mousemove.panzoom.' + mapInstanceId, function(event) { // jQuery event object
          if (state.isPanning) {
              handlePanMouseMove(event.originalEvent, state, mapInstanceId); // Pass native event
          }
      });

      $(document).on('mouseup.panzoom.' + mapInstanceId, function(event) { // jQuery event object
          if (state.isPanning) {
              handlePanMouseUp(event.originalEvent, state, mapInstanceId); // Pass native event
          }
      });
      
      viewport.on('wheel', function(event) { // jQuery event object
          handleWheelZoom(event.originalEvent, state, mapInstanceId); // Pass native event
      });
  }
  // --- End of New Pan and Zoom Functionality ---

})(jQuery); 