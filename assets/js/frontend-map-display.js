/**
 * Frontend Map Display JavaScript
 */
(function($) {
  const mapInstanceStates = {}; // To store view type, sort order, and pan/zoom per map instance
  let isPanelTransitioning = false;

  function initRepMap(mapData) {
      const mapContainer = $('#' + mapData.map_id);
      if (!mapContainer.length) {
          console.error('RepMap: Map container not found for ID:', mapData.map_id);
          return;
      }

      // Initialize state for this map instance
      mapInstanceStates[mapData.map_id] = { 
          currentViewBy: 'rep_groups', // Default to 'rep_groups'
          currentSortOrder: 'asc',   // Default to 'asc'
          // pan/zoom state will be added by initPanZoomForMap
      };

      updateColumnTitle(mapData, mapContainer); // Set initial column title based on map type
      updateDefaultListView(mapData, mapContainer); // Populate initial list
      setupViewControls(mapData, mapContainer);   // Setup listeners for select and sort button
      setupListItemClickDelegation(mapData, mapContainer); // Setup delegated event listeners for list items

      const svgElement = mapContainer.find('svg.rep-group-map-svg-object'); 
      if (!svgElement.length) {
          console.error('RepMap: SVG element not found in container:', mapData.map_id);
          return;
      }

      const panZoomGroup = processSvg(svgElement, mapData);
      // mapData.is_interactive is no longer passed, assume true for now or add back if needed
      initPanZoomForMap(mapData.map_id, svgElement, panZoomGroup, mapData);

      // Update sort button icon to initial state (A-Z, arrow-down)
      const sortButton = mapContainer.find('.sort-toggle-button');
      sortButton.find('ion-icon').attr('name', 'arrow-down-outline');
  }

  function updateColumnTitle(mapData, mapContainer) {
    const columnTitleArea = mapContainer.find('.rep-map-column-title-area');
    if (!columnTitleArea.length) return;

    // Title is now passed directly in mapData as map_type_title
    const titleText = mapData.map_type_title || "Representatives"; 

    if (titleText) {
        columnTitleArea.html('<h3 class="rep-map-list-title">' + titleText + '</h3>');
    } else {
        columnTitleArea.empty(); 
    }
  }

  function updateDefaultListView(mapData, mapContainer) {
    const mapId = mapData.map_id;
    if (!mapInstanceStates[mapId]) {
        console.error('RepMap: State not found for mapId:', mapId, 'in updateDefaultListView');
        return;
    }
    const currentView = mapInstanceStates[mapId].currentViewBy;
    const currentSort = mapInstanceStates[mapId].currentSortOrder;
    const listContainer = mapContainer.find('.rep-map-list-container');

    let listHtml = '';
    if (currentView === 'rep_groups') {
        listHtml = currentSort === 'asc' ? mapData.rep_groups_list_html_asc : mapData.rep_groups_list_html_desc;
    } else if (currentView === 'areas_served') {
        listHtml = currentSort === 'asc' ? mapData.areas_served_list_html_asc : mapData.areas_served_list_html_desc;
    }

    listContainer.html(listHtml || '<li>Error loading list or no items found.</li>');
    // Event listeners are now handled by delegation, no need to re-bind here.
  }

  function setupViewControls(mapData, mapContainer) {
    const mapId = mapData.map_id;

    mapContainer.find('.view-by-select').on('change', function() {
        if (!mapInstanceStates[mapId]) return;
        mapInstanceStates[mapId].currentViewBy = $(this).val();
        updateDefaultListView(mapData, mapContainer);
    });

    mapContainer.find('.sort-toggle-button').on('click', function() {
        if (!mapInstanceStates[mapId]) return;
        const sortButton = $(this);
        const currentOrder = mapInstanceStates[mapId].currentSortOrder;
        const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
        mapInstanceStates[mapId].currentSortOrder = newOrder;

        sortButton.data('sort-order', newOrder);
        sortButton.attr('aria-label', newOrder === 'asc' ? 'Sort Ascending' : 'Sort Descending');
        sortButton.find('.sort-text').text(newOrder === 'asc' ? 'A-Z' : 'Z-A');
        sortButton.find('ion-icon').attr('name', newOrder === 'asc' ? 'arrow-down-outline' : 'arrow-up-outline');
        
        updateDefaultListView(mapData, mapContainer);
    });
  }

  function setupListItemClickDelegation(mapData, mapContainer) {
    const mapId = mapData.map_id;
    const listContainer = mapContainer.find('.rep-map-list-container');

    // Click on a Rep Group in the list
    listContainer.on('click', '.rep-group-list-item-link', function(e) {
        e.preventDefault();
        const repGroupId = $(this).data('rep-group-id');
        if (repGroupId) {
            displayRepGroupDetailsById(repGroupId, mapId, mapData.nonce, mapData.ajax_url);
        }
        return false;
    });

    // Click on an Area Served in the list
    listContainer.on('click', '.area-served-list-item-link', function(e) {
        e.preventDefault();
        const svgId = $(this).data('svg-id');
        const areaName = $(this).data('area-name');
        const areaColor = $(this).data('area-color') || mapData.default_region_color;
        if (svgId) {
            displayRepInfoForArea(svgId, areaColor, mapId, mapData.nonce, mapData.ajax_url, mapData.default_region_color, areaName);
        }
        return false;
    });
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
      let panZoomGroup = $(); 
      try {
          const svgRoot = svgObjectElement;

          if (!svgRoot.length) {
              console.error('RepMap: SVG root element not found.');
              return panZoomGroup;
          }

          panZoomGroup = svgRoot.find('> g.rep-map-pan-zoom-group');
          if (!panZoomGroup.length) {
              const newG = document.createElementNS('http://www.w3.org/2000/svg', 'g');
              panZoomGroup = $(newG).addClass('rep-map-pan-zoom-group');
              svgRoot.children().appendTo(panZoomGroup);
              svgRoot.append(panZoomGroup);
          }
          
          svgRoot.find('path, g, rect, circle, polygon, ellipse').each(function() {
              const el = $(this);
              const elId = el.attr('id');

              if (elId && mapData.map_links_data && mapData.map_links_data[elId]) {
                  const areaInfo = mapData.map_links_data[elId];
                  const color = areaInfo.color || mapData.default_region_color;
                  applyFillToElementAndChildren(el, color);
                  el.addClass('mapped-region-frontend'); 
                  // mapData.is_interactive check removed, assumed interactive for now
                  el.css('cursor', 'pointer');
                  el.on('click', function(e) {
                      e.preventDefault();
                      e.stopPropagation();
                      // Pass term_name from map_links_data for title context
                      const areaNameForTitle = areaInfo.term_name || elId;
                      displayRepInfoForArea(elId, color, mapData.map_id, mapData.nonce, mapData.ajax_url, mapData.default_region_color, areaNameForTitle);
                  });
                  el.hover(
                      function() { $(this).addClass('hover-region-frontend'); },
                      function() { $(this).removeClass('hover-region-frontend'); }
                  );
              }
          });
      } catch (e) {
          console.error('RepMap: Error processing frontend SVG:', e);
      }
      return panZoomGroup;
  }
  
  function displayRepInfoForArea(areaSlug, areaColor, mapInstanceId, nonce, ajaxUrl, defaultRegionColorParam, areaNameContext) {
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
              area_name_context: areaNameContext
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

      const mapInteractiveArea = $('#' + mapInstanceId);
      const infoColumn = mapInteractiveArea.find('.rep-map-info-column');
      const defaultContent = infoColumn.find('.rep-map-default-content');
      const detailsContent = infoColumn.find('.rep-map-details-content');
      const infoTarget = detailsContent.find('.rep-group-info-target');

      let panelToSlideOut = defaultContent.hasClass('panel-active') ? defaultContent : detailsContent;
      if (panelToSlideOut.is(detailsContent) && !defaultContent.hasClass('panel-active')){
          // If details is active but default is not, we might be in a state where only details is shown.
          // This logic branch ensures we are correctly sliding out *something*.
      } else if (!panelToSlideOut.hasClass('panel-active')) {
         // If the determined panelToSlideOut is not active, it might mean both are hidden
         // or we're in an unexpected state. Forcing defaultContent to be the one ensures a path forward.
         panelToSlideOut = defaultContent;
      }

      infoTarget.html('<p><em>Loading Rep Group details...</em></p>');
      detailsContent.removeClass('has-left-border animate-border-in').css('--area-specific-color', '');
      
      panelToSlideOut.off('animationend webkitAnimationEnd oAnimationEnd MSAnimationEnd');
      panelToSlideOut.removeClass('panel-active').addClass('slide-out');

      panelToSlideOut.one('animationend webkitAnimationEnd oAnimationEnd MSAnimationEnd', function() {
          $(this).removeClass('slide-out').addClass('panel-hidden');

          $.post(ajaxUrl, {
              action: 'get_rep_group_details_by_id',
              nonce: nonce,
              rep_group_id: repGroupId
          }).done(function(response) {
              if (response.success) {
                  infoTarget.html(response.data.html);
                  // Fix: Correctly access mapData for fallback color
                  const mapDataForColor = window['RepMapData_' + mapInstanceId.replace(/-/g, '_')];
                  const fallbackColor = mapDataForColor ? mapDataForColor.default_region_color : '#CCCCCC';
                  const effectiveColor = response.data.color || fallbackColor; 
                  if (effectiveColor) { 
                      detailsContent.addClass('has-left-border').css('--area-specific-color', effectiveColor);
                      requestAnimationFrame(() => {
                          detailsContent.addClass('animate-border-in');
                      });
                  }
              } else {
                  infoTarget.html('<p class="error-message">' + (response.data.message || 'Could not load rep group details.') + '</p>');
              }
          }).fail(function() {
              infoTarget.html('<p class="error-message">AJAX error loading rep group details.</p>');
          }).always(function() {
              detailsContent.removeClass('panel-hidden').addClass('panel-active slide-in');
              infoColumn.scrollTop(0); // Scroll to top of info column
              detailsContent.off('animationend webkitAnimationEnd oAnimationEnd MSAnimationEnd');
              detailsContent.one('animationend webkitAnimationEnd oAnimationEnd MSAnimationEnd', function() {
                  $(this).removeClass('slide-in');
                  isPanelTransitioning = false;
              });
          });
      });
  }

  // Back to Overview - 수정
  $(document).on('click', '.back-to-map-default', function(e) {
      e.preventDefault();
      if (isPanelTransitioning) return;
      isPanelTransitioning = true;

      const mapInteractiveArea = $(this).closest('.rep-group-map-interactive-area');
      const mapId = mapInteractiveArea.attr('id');
      
      // Retrieve mapData associated with this mapId.
      // This assumes RepMapData_{mapId} is globally available and correctly structured.
      const mapData = window['RepMapData_' + mapId.replace(/-/g, '_')]; 

      if (!mapData) {
          console.error('RepMap: mapData not found for', mapId, 'in back-to-map-default handler');
          isPanelTransitioning = false;
          return;
      }

      const infoColumn = mapInteractiveArea.find('.rep-map-info-column');
      const defaultContent = infoColumn.find('.rep-map-default-content');
      const detailsContent = infoColumn.find('.rep-map-details-content');
      const infoTarget = detailsContent.find('.rep-group-info-target');

      detailsContent.removeClass('panel-active').addClass('slide-out');
      detailsContent.one('animationend webkitAnimationEnd oAnimationEnd MSAnimationEnd', function() {
          $(this).removeClass('slide-out').addClass('panel-hidden');
          infoTarget.empty(); // Clear details
          detailsContent.removeClass('has-left-border animate-border-in').css('--area-specific-color', '');

          // Restore and show default content panel with animation
          // updateDefaultListView will populate the list based on current state
          updateDefaultListView(mapData, mapInteractiveArea); 
          defaultContent.removeClass('panel-hidden').addClass('panel-active slide-in');
          infoColumn.scrollTop(0); // Scroll to top

          defaultContent.one('animationend webkitAnimationEnd oAnimationEnd MSAnimationEnd', function() {
              $(this).removeClass('slide-in');
              isPanelTransitioning = false;
              restorePreviousZoom(mapId); 
          });
      });
  });

  // Utility function to find the mapData object based on a mapId
  // Not strictly necessary if RepMapData_{mapId} is reliably global
  function getMapDataById(mapId) {
      const mapDataKey = 'RepMapData_' + mapId.replace(/-/g, '_');
      if (window[mapDataKey]) {
          return window[mapDataKey];
      }
      console.warn('RepMap: Map data object not found for key:', mapDataKey);
      return null;
  }

  $(document).ready(function() {
      // Initialize all maps on the page
      $('[id^="rep-map-instance-"]').each(function() {
          const mapId = $(this).attr('id');
          const mapDataKey = 'RepMapData_' + mapId.replace(/-/g, '_');
          if (window[mapDataKey]) {
              initRepMap(window[mapDataKey]);
          } else {
              console.error('RepMap: No map data found for ', mapId);
          }
      });
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