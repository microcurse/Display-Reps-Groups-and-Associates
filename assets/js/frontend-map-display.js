/**
 * Frontend Map Display JavaScript
 */
(function($) {
  const mapInstanceStates = {}; // To store last active view type per map instance
  let isPanelTransitioning = false; // Flag to prevent concurrent panel transitions

  function initRepMap(mapData) {
      const mapContainer = $('#' + mapData.map_id);
      if (!mapContainer.length) {
          return;
      }

      // --- Populate Default View ---
      createToggleButtons(mapData, mapContainer);
      updateColumnTitle(mapData, mapContainer, mapData.default_view_type); // Set initial column title
      
      mapInstanceStates[mapData.map_id] = { 
          lastActiveDefaultView: mapData.default_view_type 
      };

      // Initial list display
      if (mapData.default_view_type === 'rep_groups') {
          // createRepGroupList(mapData, mapContainer);
          mapContainer.find('.rep-map-list-container').html(mapData.rep_groups_list_html || '<li>Error loading Rep Groups list.</li>');
      } else if (mapData.default_view_type === 'areas_served') {
          // createAreasServedList(mapData, mapContainer);
          mapContainer.find('.rep-map-list-container').html(mapData.areas_served_list_html || '<li>Error loading Areas Served list.</li>');
      }
      // --- End Populate Default View ---

      const svgElement = mapContainer.find('svg.rep-group-map-svg-object'); 
      if (!svgElement.length) {
          return;
      }

      const panZoomGroup = processSvg(svgElement, mapData);
      if (mapData.is_interactive) {
          initPanZoomForMap(mapData.map_id, svgElement, panZoomGroup, mapData);
      }
  }

  // --- New: Functions to create HTML elements for default view ---
  function createToggleButtons(mapData, mapContainer) {
    const togglePlaceholder = mapContainer.find('.rep-map-default-toggle');
    if (!togglePlaceholder.length) return;

    const repGroupsButton = $('<button class="toggle-button" data-view="rep_groups" aria-pressed="false">Rep Groups</button>');
    const areasServedButton = $('<button class="toggle-button" data-view="areas_served" aria-pressed="false">Areas Served</button>');

    if (mapData.default_view_type === 'rep_groups') {
        repGroupsButton.addClass('active').attr('aria-pressed', 'true');
    } else {
        areasServedButton.addClass('active').attr('aria-pressed', 'true');
    }

    repGroupsButton.on('click', function() {
        $(this).addClass('active').attr('aria-pressed', 'true');
        areasServedButton.removeClass('active').attr('aria-pressed', 'false');
        mapInstanceStates[mapData.map_id].lastActiveDefaultView = 'rep_groups';
        updateColumnTitle(mapData, mapContainer, 'rep_groups');
        // createRepGroupList(mapData, mapContainer); 
        mapContainer.find('.rep-map-list-container').html(mapData.rep_groups_list_html || '<li>Error loading Rep Groups list.</li>');
    });

    areasServedButton.on('click', function() {
        $(this).addClass('active').attr('aria-pressed', 'true');
        repGroupsButton.removeClass('active').attr('aria-pressed', 'false');
        mapInstanceStates[mapData.map_id].lastActiveDefaultView = 'areas_served';
        updateColumnTitle(mapData, mapContainer, 'areas_served');
        // createAreasServedList(mapData, mapContainer);
        mapContainer.find('.rep-map-list-container').html(mapData.areas_served_list_html || '<li>Error loading Areas Served list.</li>');
    });

    togglePlaceholder.empty().append(repGroupsButton).append(areasServedButton);
  }

  function updateColumnTitle(mapData, mapContainer, currentView) {
    const columnTitleArea = mapContainer.find('.rep-map-column-title-area');
    if (!columnTitleArea.length) return;

    let titleText = '';
    // Always set the title based on map type, regardless of currentView (rep_groups or areas_served)
    if (mapData.map_id && mapData.map_id.includes('-local-')) {
        titleText = "North American Reps";
    } else if (mapData.map_id && mapData.map_id.includes('-international-')) {
        titleText = "International Reps";
    } else {
        titleText = "Representatives"; // More generic fallback if needed
    }

    if (titleText) {
        columnTitleArea.html('<h3 class="rep-map-list-title">' + titleText + '</h3>');
    } else {
        columnTitleArea.empty(); // Should not happen if map_id is always present
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
      let panZoomGroup = $(); // Initialize as an empty jQuery object
      try {
          const svgRoot = svgObjectElement;

          if (!svgRoot.length) {
              console.error('RepMap: SVG root element not found (this should be the passed element).', svgObjectElement[0]);
              return panZoomGroup; // Return empty group on error
          }

          panZoomGroup = svgRoot.find('> g.rep-map-pan-zoom-group');
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
                  if (mapData.map_links_data && mapData.map_links_data[elId]) {
                      processedRegionsCount++;
                      if (!firstProcessedId) firstProcessedId = elId;

                      const areaInfo = mapData.map_links_data[elId];
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
          // If an error occurred, panZoomGroup might be the last valid one or the initial empty one.
          // It will be returned as is.
      }
      return panZoomGroup; // Always return the panZoomGroup
  }
  
  function displayRepInfoForArea(areaSlug, areaColor, mapInstanceId, nonce, ajaxUrl, defaultRegionColorParam) {
      if (isPanelTransitioning) {
          return;
      }
      isPanelTransitioning = true;

      const mapInteractiveArea = $('#' + mapInstanceId);
      const infoColumn = mapInteractiveArea.find('.rep-map-info-column');
      const defaultContent = infoColumn.find('.rep-map-default-content');
      const detailsContent = infoColumn.find('.rep-map-details-content');
      const infoTarget = detailsContent.find('.rep-group-info-target');

      // Determine which panel is currently active to slide it out.
      let panelToSlideOut;
      if (detailsContent.hasClass('panel-active')) {
          panelToSlideOut = detailsContent;
      } else { // Assume defaultContent is active or should be the one sliding out
          panelToSlideOut = defaultContent;
          // If defaultContent is sliding out, its list needs to be cleared (if it wasn't already).
          // This also helps prevent briefly seeing old list items if the panel somehow wasn't fully hidden or cleared before.
          defaultContent.find('.rep-map-list-container').empty();
      }
      
      // Common preparation for detailsContent (which will eventually slide in with area info)
      infoTarget.html('<p><em>Loading area information...</em></p>'); // Updated loading message
      detailsContent.removeClass('has-left-border animate-border-in').css('--area-specific-color', '');

      // Ensure no previous animationend handlers are lingering from a rapid re-entry on the panel to slide out
      panelToSlideOut.off('animationend webkitAnimationEnd oAnimationEnd MSAnimationEnd');
      panelToSlideOut.removeClass('panel-active').addClass('slide-out');

      panelToSlideOut.one('animationend webkitAnimationEnd oAnimationEnd MSAnimationEnd', function() {
          $(this).removeClass('slide-out').addClass('panel-hidden');

          // AJAX call is made AFTER the outgoing panel is hidden
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
                  zoomToArea(mapInstanceId, areaSlug);
              } else {
                  infoTarget.html('<p class="error-message">' + (response.data.message || 'Could not load rep details.') + '</p>');
              }
          }).fail(function() {
              infoTarget.html('<p class="error-message">AJAX error loading rep details.</p>');
          }).always(function() {
              detailsContent.removeClass('panel-hidden').addClass('panel-active slide-in');
              infoColumn.scrollTop(0);
              // Ensure no previous animationend handlers are lingering on detailsContent from other operations
              detailsContent.off('animationend webkitAnimationEnd oAnimationEnd MSAnimationEnd');
              detailsContent.one('animationend webkitAnimationEnd oAnimationEnd MSAnimationEnd', function() {
                  $(this).removeClass('slide-in');
                  isPanelTransitioning = false; // Reset flag
              });
          });
      });
  }

  function displayRepGroupDetailsById(repGroupId, mapInstanceId, nonce, ajaxUrl) {
      if (isPanelTransitioning) {
          return;
      }
      isPanelTransitioning = true;

      // Restore part of the original code
      const mapInteractiveArea = $('#' + mapInstanceId);
      const infoColumn = mapInteractiveArea.find('.rep-map-info-column');
      const defaultContent = infoColumn.find('.rep-map-default-content');
      const detailsContent = infoColumn.find('.rep-map-details-content');
      const infoTarget = detailsContent.find('.rep-group-info-target');

      defaultContent.find('.rep-map-list-container').empty();
      infoTarget.html('<p><em>Loading Rep Group details...</em></p>');
      detailsContent.removeClass('has-left-border animate-border-in').css('--area-specific-color', '');


      // Restore the animation trigger line
      defaultContent.off('animationend webkitAnimationEnd oAnimationEnd MSAnimationEnd'); // Keep this to be safe for when we restore .one()
      defaultContent.removeClass('panel-active').addClass('slide-out');

      // Restore the .one('animationend') handler but with an empty callback for now
      defaultContent.one('animationend webkitAnimationEnd oAnimationEnd MSAnimationEnd', function() {
          $(this).removeClass('slide-out').addClass('panel-hidden');

          // Restore the AJAX call, but with empty promise handlers for now
          $.post(ajaxUrl, {
              action: 'get_rep_group_details_by_id',
              nonce: nonce,
              rep_group_id: repGroupId
          }).done(function(response) {
              // Restore original .done() logic
              if (response.success) {
                  infoTarget.html(response.data.html);
                  if (response.data.color) { 
                      detailsContent.addClass('has-left-border').css('--area-specific-color', response.data.color);
                      requestAnimationFrame(() => {
                          detailsContent.addClass('animate-border-in');
                      });
                  } else {
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
          }).fail(function(jqXHR, textStatus, errorThrown) {
              console.error('RepMap DEBUG: (RepGroup) AJAX .fail() - Restoring full logic. Status:', textStatus, 'Error:', errorThrown); // Keep this error for actual failures
              // Restore original .fail() logic
              infoTarget.html('<p class="error-message">AJAX error loading Rep Group details. Check console.</p>');
          }).always(function() {
              // Restore full original .always() logic
              detailsContent.removeClass('panel-hidden').addClass('panel-active slide-in');
              infoColumn.scrollTop(0);
              
              // Ensure no previous animationend handlers are lingering from a rapid re-entry on detailsContent too
              detailsContent.off('animationend webkitAnimationEnd oAnimationEnd MSAnimationEnd');
              detailsContent.one('animationend webkitAnimationEnd oAnimationEnd MSAnimationEnd', function() {
                  $(this).removeClass('slide-in');
                  isPanelTransitioning = false; // Reset flag
              });
          });

      });
  }

  // Initialize all maps on the page
  $(function() {
      // Click handler for the details panel close button (now "Back to Overview" link)
      $(document).on('click', '.rep-map-details-content .back-to-map-default', function(e) {
          e.preventDefault();

          if (isPanelTransitioning) {
              // console.log('RepMap DEBUG: Panel transition already in progress. Ignoring Back to Overview call.');
              return;
          }
          isPanelTransitioning = true;
          // console.log('RepMap DEBUG: Back to Overview clicked.');

          const mapInteractiveArea = $(this).closest('.rep-group-map-interactive-area');
          const mapInstanceId = mapInteractiveArea.attr('id');
          const mapData = window['RepMapData_' + mapInstanceId.replace(/-/g, '_')];
          const currentMapState = mapInstanceStates[mapInstanceId];

          const infoColumn = mapInteractiveArea.find('.rep-map-info-column');
          const defaultContent = infoColumn.find('.rep-map-default-content');
          const detailsContent = infoColumn.find('.rep-map-details-content');
          
          restorePreviousZoom(mapInteractiveArea.attr('id'));

          // 1. Clear content of the outgoing panel (detailsContent) & reset its appearance
          detailsContent.find('.rep-group-info-target').empty();
          detailsContent.removeClass('has-left-border animate-border-in').css('--area-specific-color', '');

          // 2. Start animation for outgoing panel (detailsContent)
          detailsContent.removeClass('panel-active').addClass('slide-out');
          
          detailsContent.one('animationend webkitAnimationEnd oAnimationEnd MSAnimationEnd', function() {
              // 3. Outgoing animation ended: fully hide it and clean up
              $(this).removeClass('slide-out').addClass('panel-hidden'); // Remove panel-active from previous step is implicit
              
              // 4. Now, prepare and animate in the incoming panel (defaultContent)
              // Ensure defaultContent is ready to be shown (not panel-hidden) and make it active
              defaultContent.removeClass('panel-hidden').addClass('panel-active');
              
              // Repopulate list. "View by" and toggles are static children of defaultContent and should appear
              // when defaultContent becomes panel-active (assuming CSS is set up for this).
              if (currentMapState && mapData) {
                  // updateColumnTitle is mainly for the main title, which is persistent.
                  // The "View By" and toggles are static HTML within defaultContent.
                  // updateColumnTitle(mapData, mapInteractiveArea, currentMapState.lastActiveDefaultView);
                  if (currentMapState.lastActiveDefaultView === 'rep_groups') {
                      defaultContent.find('.rep-map-list-container').html(mapData.rep_groups_list_html || '<li>Error loading Rep Groups list.</li>');
                  } else if (currentMapState.lastActiveDefaultView === 'areas_served') {
                      defaultContent.find('.rep-map-list-container').html(mapData.areas_served_list_html || '<li>Error loading Areas Served list.</li>');
                  }
              }
              infoColumn.scrollTop(0);

              // 5. Add slide-in class to start the animation for defaultContent
              defaultContent.addClass('slide-in');
              defaultContent.one('animationend webkitAnimationEnd oAnimationEnd MSAnimationEnd', function() {
                  // console.log('RepMap DEBUG: defaultContent slide-in animation ended (Back to Overview).');
                  $(this).removeClass('slide-in');
                  isPanelTransitioning = false; // Reset flag
              });
          });
      });

      // New click handler for rep group list items in the default view
      // This needs to be delegated now as the list is dynamically created
      $(document).on('click', '.rep-map-list-container .rep-group-list-item-link', function(e) {
          e.preventDefault();
          e.stopImmediatePropagation(); 

          const listItem = $(this).closest('li');
          const repGroupId = listItem.data('rep-group-id');
          // console.log('RepMap DEBUG: .rep-group-list-item-link actual CLICK event. ID:', repGroupId, 'Target class:', $(e.target).attr('class'));

          // Restore the call to displayRepGroupDetailsById
          const mapInteractiveArea = listItem.closest('.rep-group-map-interactive-area');
          const mapInstanceId = mapInteractiveArea.attr('id');
          const mapData = window['RepMapData_' + mapInstanceId.replace(/-/g, '_')]; 
          if (repGroupId && mapInstanceId && mapData && mapData.nonce && mapData.ajax_url) {
              displayRepGroupDetailsById(repGroupId, mapInstanceId, mapData.nonce, mapData.ajax_url);
          } else {
              console.error('RepMap DEBUG: Could not retrieve rep group ID or map data for AJAX call from rep group list.', {repGroupId, mapInstanceId, mapData}); // Keep this error
          }
      });

      // New click handler for areas served list items in the default view
      $(document).on('click', '.rep-map-list-container .area-served-list-item-link', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        const listItem = $(this).closest('li');
        const svgId = listItem.data('svg-id');
        const areaColor = listItem.data('area-color');
        const mapInteractiveArea = listItem.closest('.rep-group-map-interactive-area');
        const mapInstanceId = mapInteractiveArea.attr('id');
        const mapData = window['RepMapData_' + mapInstanceId.replace(/-/g, '_')];
        
        const defaultContentContext = mapInteractiveArea.find('.rep-map-default-content'); 
        const detailsContentContext = mapInteractiveArea.find('.rep-map-details-content');

        if (!svgId) {
            console.warn('RepMap: Clicked Area Served list item is missing data-svg-id. Cannot link to map region.', listItem);
            const infoTarget = detailsContentContext.find('.rep-group-info-target');
            if (infoTarget.length) {
                // Transition to show error in details panel
                defaultContentContext.find('.rep-map-list-container').empty();
                defaultContentContext.removeClass('panel-active').addClass('slide-out');
                defaultContentContext.one('animationend webkitAnimationEnd oAnimationEnd MSAnimationEnd', function() {
                    $(this).removeClass('slide-out').addClass('panel-hidden');
                    infoTarget.html('<p class="error-message">This area is not directly linked to a map region. Please select a Rep Group or another area.</p>');
                    detailsContentContext.removeClass('panel-hidden').addClass('panel-active slide-in');
                    detailsContentContext.one('animationend webkitAnimationEnd oAnimationEnd MSAnimationEnd', function(){
                        $(this).removeClass('slide-in');
                    });
                });
            }
            return; 
        }

        if (svgId && mapInstanceId && mapData && mapData.nonce && mapData.ajax_url) {
            displayRepInfoForArea(svgId, areaColor, mapInstanceId, mapData.nonce, mapData.ajax_url, mapData.default_region_color);
        } else {
            console.error('RepMap: Could not retrieve svgId or map data for AJAX call from areas served list.', {svgId, mapInstanceId, mapData}); // Keep this error
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

      if (state.svgRoot && state.svgRoot.length) {
          state.svgRoot.css('pointer-events', 'auto');
      }

      const wasDragging = state.isDragging;

      state.isPanning = false;
      state.isDragging = false; 

      if (state.viewport) state.viewport.css('cursor', 'grab');

      if (wasDragging) {
          event.preventDefault(); 
          event.stopImmediatePropagation(); 
      }
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

  function initPanZoomForMap(mapInstanceId, svgElement, panZoomGroupElement, mapData) {
      const viewport = $(svgElement).parent('.svg-viewport');

      if (!viewport.length) {
          console.error('RepMap: SVG viewport not found for map:', mapInstanceId);
          return;
      }

      const svgRootElement = svgElement; 

      if (!panZoomGroupElement || !panZoomGroupElement.length) {
          console.error('RepMap: panZoomGroupElement is invalid or not received in initPanZoomForMap for:', mapInstanceId + ". Pan/zoom will not be initialized.");
          return; // Critical error, cannot proceed with pan/zoom
      }

      // Ensure mapStates[mapInstanceId] is initialized before accessing its properties
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
          svgRoot: svgRootElement,       
          panZoomGroup: panZoomGroupElement, // Use the passed element
          viewport: viewport,
          minScale: 0.5, 
          maxScale: 5,   
          zoomFactor: 1.1,
          // New properties for zoom-to-area
          previousScale: null,
          previousPanX: null,
          previousPanY: null,
          isZoomedToArea: false
      };

      const state = mapStates[mapInstanceId];
      const mapId = mapInstanceId; 

      // Defer the dimension calculation and initial transform to the next animation frame
      requestAnimationFrame(() => {
          const viewportWidth = state.viewport.width();
          const viewportHeight = state.viewport.height();
          // Removed console.log for Viewport dimensions

          if (!svgElement || !svgElement.length || typeof svgElement[0].getBBox !== 'function') {
              console.warn(`RepMap (rAF): svgElement is not a valid SVG element or getBBox is not available for ${mapId}.`);
              return; // Exit if we can't get dimensions
          }
          const svgBox = svgElement[0].getBBox();
          // Removed console.log for SVG original dimensions

          if (svgBox.width === 0 || svgBox.height === 0) {
              console.warn(`RepMap: SVG for ${mapId} has zero dimensions, skipping initial auto-zoom.`);
          } else {
              let targetInitialScale = 1.25;

              // Clamp this desired scale to min/max limits
              state.scale = Math.max(state.minScale, Math.min(state.maxScale, targetInitialScale));

              // Calculate pan to center the SVG (using its bbox) with this new scale
              state.panX = (viewportWidth - svgBox.width * state.scale) / 2 + 200; // Added 200px offset to shift right
              state.panY = (viewportHeight - svgBox.height * state.scale) / 2 + 100; // Added 100px offset to shift down

              // Apply the transform using the updated state
              if (state.panZoomGroup && state.panZoomGroup.length) {
                  applyTransform(state);
              } else {
                  console.warn(`RepMap (rAF): panZoomGroup not found in state for ${mapId} when trying to apply initial transform.`);
              }
          }
      });

      viewport.on('mousedown', function(event) {
          handlePanMouseDown(event.originalEvent, state, mapInstanceId);
      });

      viewport.on('wheel', function(event) {
          handleWheelZoom(event.originalEvent, state, mapInstanceId);
      });
  }
  // --- End of New Pan and Zoom Functionality ---

  // --- Animation Helper ---
  function easeInOutQuad(t) {
    return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
  }

  function animateView(mapInstanceId, targetScale, targetPanX, targetPanY, duration = 350) {
    const state = mapStates[mapInstanceId];
    if (!state || !state.panZoomGroup || !state.panZoomGroup.length) {
        return;
    }

    const startScale = state.scale;
    const startPanX = state.panX;
    const startPanY = state.panY;
    const startTime = performance.now();

    function animationStep(currentTime) {
        const elapsedTime = currentTime - startTime;
        const progress = Math.min(elapsedTime / duration, 1);
        const easedProgress = easeInOutQuad(progress);

        state.scale = startScale + (targetScale - startScale) * easedProgress;
        state.panX = startPanX + (targetPanX - startPanX) * easedProgress;
        state.panY = startPanY + (targetPanY - startPanY) * easedProgress;
        
        applyTransform(state);

        if (progress < 1) {
            requestAnimationFrame(animationStep);
        } else {
            // Ensure final state is set precisely
            state.scale = targetScale;
            state.panX = targetPanX;
            state.panY = targetPanY;
            applyTransform(state);
        }
    }
    requestAnimationFrame(animationStep);
  }

  // --- Zoom to Area and Restore ---
  function zoomToArea(mapInstanceId, areaElementId) {
    const state = mapStates[mapInstanceId];
    if (!state || !state.svgRoot || !state.viewport || !state.panZoomGroup) {
        return;
    }

    const clickedElement = state.svgRoot.find('#' + areaElementId);
    if (!clickedElement.length || typeof clickedElement[0].getBBox !== 'function') {
        return;
    }

    requestAnimationFrame(() => { // Ensure BBox is accurate
        const elementBBox = clickedElement[0].getBBox();
        if (elementBBox.width === 0 || elementBBox.height === 0) {
            return;
        }

        const viewportWidth = state.viewport.width();
        const viewportHeight = state.viewport.height();

        const zoomPaddingFactor = 0.8; // e.g., 80% of viewport
        let targetScaleX = (viewportWidth * zoomPaddingFactor) / elementBBox.width;
        let targetScaleY = (viewportHeight * zoomPaddingFactor) / elementBBox.height;
        let targetScale = Math.min(targetScaleX, targetScaleY);

        targetScale = Math.max(state.minScale, Math.min(state.maxScale, targetScale));

        // Calculate pan to center the element
        // Element's center in its own coordinate system (relative to svgRoot's <g> content)
        const elementCenterX = elementBBox.x + elementBBox.width / 2;
        const elementCenterY = elementBBox.y + elementBBox.height / 2;
        
        // Target pan for the panZoomGroup
        let targetPanX = (viewportWidth / 2) - (elementCenterX * targetScale);
        let targetPanY = (viewportHeight / 2) - (elementCenterY * targetScale);
        
        if (!state.isZoomedToArea) { // Only store if not already zoomed (i.e., this is the first area zoom)
            state.previousScale = state.scale;
            state.previousPanX = state.panX;
            state.previousPanY = state.panY;
        }
        state.isZoomedToArea = true;

        animateView(mapInstanceId, targetScale, targetPanX, targetPanY);
    });
  }

  function restorePreviousZoom(mapInstanceId) {
    const state = mapStates[mapInstanceId];
    if (!state) return;

    if (state.isZoomedToArea && state.previousScale !== null && typeof state.previousPanX === 'number' && typeof state.previousPanY === 'number') {
        animateView(mapInstanceId, state.previousScale, state.previousPanX, state.previousPanY);
        state.isZoomedToArea = false; 
    }
  }

})(jQuery); 