/**
 * Frontend Map Display JavaScript
 */
(function($) {
  function initRepMap(mapData) {
      const mapContainer = $('#' + mapData.map_id);
      if (!mapContainer.length) {
          console.log('RepMap: Map container not found:', mapData.map_id);
          return;
      }

      const svgElement = mapContainer.find('svg.rep-group-map-svg-object'); 
      if (!svgElement.length) {
          console.log('RepMap: Inline SVG element (.rep-group-map-svg-object) not found in:', mapData.map_id);
          return;
      }

      processSvg(svgElement, mapData);
      if (mapData.is_interactive) {
          initPanZoomForMap(mapData.map_id, svgElement, mapData);
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
          const svgRoot = svgObjectElement;

          if (!svgRoot.length) {
              console.error('RepMap: SVG root element not found (this should be the passed element).', svgObjectElement[0]);
              return;
          }

          let panZoomGroup = svgRoot.find('> g.rep-map-pan-zoom-group');
          if (!panZoomGroup.length) {
              const newG = document.createElementNS('http://www.w3.org/2000/svg', 'g');
              panZoomGroup = $(newG).addClass('rep-map-pan-zoom-group');
              svgRoot.children().appendTo(panZoomGroup);
              svgRoot.append(panZoomGroup);
          }
          
          let foundElementsCount = 0;
          let processedRegionsCount = 0;
          let firstProcessedId = null;

          svgRoot.find('path, g, rect, circle, polygon, ellipse').each(function(index) {
              foundElementsCount++;
              const el = $(this);
              const elId = el.attr('id');

              if (elId) {
                  if (mapData.area_data && mapData.area_data[elId]) {
                      processedRegionsCount++;
                      if (!firstProcessedId) firstProcessedId = elId;

                      const areaInfo = mapData.area_data[elId];
                      const color = areaInfo.color || mapData.default_region_color;
                      applyFillToElementAndChildren(el, color);
                      el.addClass('mapped-region-frontend'); 
                      if (mapData.is_interactive) {
                          el.css('cursor', 'pointer');
                          el.on('click', function(e) {
                              e.preventDefault();
                              e.stopPropagation();
                              displayRepInfoForArea(elId, color, mapData.map_id, mapData.nonce, mapData.ajax_url, mapData.default_region_color);
                          });
                      }
                       el.hover(
                          function() { $(this).addClass('hover-region-frontend'); },
                          function() { $(this).removeClass('hover-region-frontend'); }
                      );
                  }
              }
          });
      } catch (e) {
          console.error('RepMap: Error processing frontend SVG:', e);
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
                  const svgObject = mapInteractiveArea.find('svg.rep-group-map-svg-object');
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
      if (state && state.panZoomGroup && state.panZoomGroup.length) {
          const transformString = `translate(${state.panX} ${state.panY}) scale(${state.scale})`;
          state.panZoomGroup.attr('transform', transformString);
      } else if (state && state.svgObject && state.svgObject.length) {
          console.warn('Applying CSS transform to svgObject as panZoomGroup not found. State:', state);
          state.svgObject.css('transform', `translate(${state.panX}px, ${state.panY}px) scale(${state.scale})`);
      }
  }

  function handlePanMouseDown(event, state, mapInstanceId) {
      event.preventDefault(); 
      
      state.isPanning = true;
      state.isDragging = false; 
      state.startX = event.clientX;
      state.startY = event.clientY;
      state.lastMouseX = event.clientX;
      state.lastMouseY = event.clientY;
      if (state.viewport) state.viewport.css('cursor', 'grabbing');

      // Attach mousemove and mouseup to the viewport itself
      state.viewport.on('mousemove.panzoom.' + mapInstanceId, function(e_move) {
          handlePanMouseMove(e_move.originalEvent, state, mapInstanceId);
      });
      state.viewport.on('mouseup.panzoom.' + mapInstanceId + ', mouseleave.panzoom.' + mapInstanceId, function(e_up) {
          // mouseleave is added to handle cases where mouse is released outside viewport
          handlePanMouseUp(e_up.originalEvent, state, mapInstanceId);
          state.viewport.off('.panzoom.' + mapInstanceId);
      });
  }

  function handlePanMouseMove(event, state, mapInstanceId) {
      if (!state.isPanning) return;

      const dx = event.clientX - state.lastMouseX;
      const dy = event.clientY - state.lastMouseY;

      if (!state.isDragging) {
          const moveX = Math.abs(event.clientX - state.startX);
          const moveY = Math.abs(event.clientY - state.startY);
          if (moveX > state.dragThreshold || moveY > state.dragThreshold) {
              state.isDragging = true;
              if (state.svgRoot && state.svgRoot.length) {
                  state.svgRoot.css('pointer-events', 'none');
              }
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
      if (state.viewport) {
          state.viewport.off('.panzoom.' + mapInstanceId);
      }

      // Restore pointer events on the root SVG element
      if (state.svgRoot && state.svgRoot.length) {
          state.svgRoot.css('pointer-events', 'auto');
      }

      const wasDragging = state.isDragging; // Capture before reset

      state.isPanning = false;
      // Reset dragging flag AFTER checking it, so click prevention logic works
      state.isDragging = false; 

      if (state.viewport) state.viewport.css('cursor', 'grab');

      if (wasDragging) {
          event.preventDefault(); 
          event.stopImmediatePropagation(); 
      }
      // If it wasn't a drag, the click on the SVG path (if any) should proceed normally.
  }
  
  function handleWheelZoom(event, state, mapInstanceId) {
      event.preventDefault();
      event.stopImmediatePropagation();

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

  function initPanZoomForMap(mapInstanceId, svgElement, mapData) {
      const viewport = $(svgElement).parent('.svg-viewport');

      if (!viewport.length) {
          console.error('SVG viewport not found for map:', mapInstanceId);
          return;
      }

      const svgRootElement = svgElement; 
      let panZoomGroupElement = null;

      if (svgRootElement && svgRootElement.length) {
          panZoomGroupElement = svgRootElement.find('> g.rep-map-pan-zoom-group');
          if (!panZoomGroupElement.length) {
              console.error('RepMap: panZoomGroup not found inside SVG for map by initPanZoomForMap:', mapInstanceId);
          }
      } else {
          console.error('RepMap: svgRootElement (the inline <svg>) not found for map by initPanZoomForMap:', mapInstanceId);
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
          svgRoot: svgRootElement,       // The inline <svg> element (jQuery object)
          panZoomGroup: panZoomGroupElement, // The <g> inside the SVG
          viewport: viewport,
          minScale: 0.5, 
          maxScale: 5,   
          zoomFactor: 1.1 
      };

      const state = mapStates[mapInstanceId];

      // Defer the dimension calculation and initial transform to the next animation frame
      requestAnimationFrame(() => {
          const viewportWidth = state.viewport.width();
          const viewportHeight = state.viewport.height();

          if (state.svgRoot && state.svgRoot.length && typeof state.svgRoot[0].getBBox === 'function') {
              const svgBoundingBox = state.svgRoot[0].getBBox();
              let svgWidth = svgBoundingBox.width;
              let svgHeight = svgBoundingBox.height;

              console.log(`RepMap (rAF): Viewport dimensions for ${mapInstanceId}: ${viewportWidth}x${viewportHeight}`);
              console.log(`RepMap (rAF): SVG original dimensions for ${mapInstanceId}: ${svgWidth}x${svgHeight}`);

              if (svgWidth > 0 && svgHeight > 0) {
                  const viewportAspect = viewportWidth / viewportHeight;
                  const svgAspect = svgWidth / svgHeight;
                  let calculatedScale;

                  if (viewportAspect < svgAspect) { // Viewport is "taller" or less wide than SVG
                      calculatedScale = viewportHeight / svgHeight;
                      console.log(`RepMap (rAF): Initial scale for ${mapInstanceId}: fitting to height. Scale: ${calculatedScale}`);
                  } else { // Viewport is "wider" or less tall
                      calculatedScale = viewportWidth / svgWidth;
                      console.log(`RepMap (rAF): Initial scale for ${mapInstanceId}: fitting to width. Scale: ${calculatedScale}`);
                  }

                  if (calculatedScale > 0 && isFinite(calculatedScale)) {
                      state.scale = calculatedScale;
                      state.scale = Math.max(state.minScale, Math.min(state.maxScale, state.scale)); // Clamp scale
                  } else {
                      console.warn(`RepMap (rAF): Calculated scale ${calculatedScale} is invalid for ${mapInstanceId}. Using default state.scale: ${state.scale}`);
                      // state.scale remains its default (1)
                  }

                  state.panX = (viewportWidth - svgWidth * state.scale) / 2;
                  state.panY = (viewportHeight - svgHeight * state.scale) / 2;

              } else {
                  console.warn(`RepMap (rAF): SVG dimensions are zero or invalid for ${mapInstanceId} (w:${svgWidth}, h:${svgHeight}). Using default scale and pan.`);
                  // state.scale, state.panX, state.panY remain their defaults
              }
          } else {
              console.warn(`RepMap (rAF): svgRoot not available or getBBox not supported for ${mapInstanceId}. Using default scale and pan.`);
              // state.scale, state.panX, state.panY remain their defaults
          }

          console.log(`RepMap (rAF): Applying initial transform for ${mapInstanceId}:`, JSON.parse(JSON.stringify(state)));
          applyTransform(state);
      });

      viewport.on('mousedown', function(event) {
          handlePanMouseDown(event.originalEvent, state, mapInstanceId);
      });

      viewport.on('wheel', function(event) {
          handleWheelZoom(event.originalEvent, state, mapInstanceId);
      });
  }
  // --- End of New Pan and Zoom Functionality ---

})(jQuery); 