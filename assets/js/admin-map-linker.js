/**
 * Admin Map Linker JavaScript
 */
jQuery(document).ready(function($) {
  const svgWrapper = $('#rep-map-svg-wrapper');
  const mapTypeSelector = $('#rep-map-type-selector');
  const areaServedSelector = $('#rep-map-area-served-selector');
  const colorPickerInput = $('#rep-map-region-color');
  const selectedRegionIdDisplay = $('#selected-svg-region-id');
  const assignButton = $('#assign-term-to-region-button');
  const removeButton = $('#remove-term-from-region-button');
  const currentMappingsDisplay = $('.mappings-list');
  
  let currentMapType = mapTypeSelector.val();
  let currentSelectedSvgElement = null;
  let currentSelectedSvgId = null;
  let currentMapLinks = {}; // Stores { svg_id: { term_id: tid, color: #hex } }
  let termIdToNameMap = {};

  // Initialize Color Picker
  colorPickerInput.wpColorPicker({
      change: function(event, ui) {
          if (currentSelectedSvgElement && currentMapLinks[currentSelectedSvgId]) {
              // Live update color on SVG if a region is selected and mapped
              // currentSelectedSvgElement.css('fill', ui.color.toString());
              // Debounce or make this an explicit action if it causes performance issues
          }
      },
      clear: function() {
          // Handle clear if needed
      }
  });

  // Populate termIdToNameMap
  areaServedSelector.find('option').each(function() {
      const termId = $(this).val();
      const termName = $(this).text();
      if (termId) {
          termIdToNameMap[termId] = termName;
      }
  });

  function applyStylesToSvgElement(svgElement, svgId) {
      svgElement.removeClass('mapped-region selected-region'); // Clear previous states
      svgElement.css('fill', ''); // Reset fill to default SVG fill

      if (currentMapLinks[svgId]) {
          svgElement.addClass('mapped-region');
          if (currentMapLinks[svgId].color) {
              svgElement.css('fill', currentMapLinks[svgId].color);
          }
      }
      if (currentSelectedSvgId === svgId) {
          svgElement.addClass('selected-region');
          // Selected region style might override mapped color, or combine them via CSS
      }
  }

  function loadSvgMap(mapType) {
      currentMapType = mapType;
      currentSelectedSvgElement = null;
      currentSelectedSvgId = null;
      selectedRegionIdDisplay.html('<em>' + RepMapLinkerData.text.loading_map + '</em>');
      svgWrapper.html('<p class="loading-message">' + RepMapLinkerData.text.loading_map + '</p>');
      assignButton.prop('disabled', true);
      removeButton.prop('disabled', true);
      areaServedSelector.prop('disabled', true).val('');
      colorPickerInput.wpColorPicker('color', RepMapLinkerData.default_color).prop('disabled', true);

      const svgUrl = RepMapLinkerData.svg_urls[mapType];
      if (!svgUrl) {
          svgWrapper.html('<p class="error-message">' + RepMapLinkerData.text.map_not_configured + '</p>');
          selectedRegionIdDisplay.html('<em>N/A</em>');
          currentMapLinks = {};
          displayCurrentMappings(); 
          return;
      }

      $.post(RepMapLinkerData.ajax_url, {
          action: 'get_rep_map_links',
          nonce: RepMapLinkerData.nonce,
          map_type: mapType
      }).done(function(response) {
          if (response.success) {
              currentMapLinks = response.data || {};
          } else {
              currentMapLinks = {};
              // Error fetching map links, message: response.data.message
          }
          displayCurrentMappings();

          const objectTag = $('<object type="image/svg+xml" id="interactive-map-svg"></object>');
          objectTag.attr('data', svgUrl);
          
          objectTag.on('load', function() {
              try {
                  const svgDoc = this.contentDocument;
                  if (!svgDoc) throw new Error('SVG contentDocument is null.');
                  
                  const svgElements = $(svgDoc).find('path, g, rect, circle, polygon');
                  svgElements.each(function() {
                      const el = $(this);
                      const elId = el.attr('id');
                      if (elId) {
                          applyStylesToSvgElement(el, elId);                            
                          el.on('click', function(e) {
                              e.stopPropagation();
                              handleSvgRegionClick(el, elId);
                          });
                          el.hover(
                              function() { $(this).addClass('hover-region'); },
                              function() { $(this).removeClass('hover-region'); }
                          );
                      }
                  });
                  selectedRegionIdDisplay.html('<em>None</em>');
                  svgWrapper.find('.loading-message').remove();
              } catch (e) {
                  // Error processing SVG, error: e
                  svgWrapper.html('<p class="error-message">' + RepMapLinkerData.text.error_loading_svg + '</p>');
                  selectedRegionIdDisplay.html('<em>Error</em>');
              }
          });
          svgWrapper.html(objectTag);
      }).fail(function() {
          // AJAX error fetching map links.
          svgWrapper.html('<p class="error-message">Error fetching map data.</p>');
          currentMapLinks = {};
          displayCurrentMappings();
      });
  }

  function handleSvgRegionClick(element, id) {
      if (currentSelectedSvgElement && currentSelectedSvgElement[0] !== element[0]) {
           applyStylesToSvgElement(currentSelectedSvgElement, currentSelectedSvgId); // Reset old
      }

      currentSelectedSvgElement = element;
      currentSelectedSvgId = id;
      applyStylesToSvgElement(element, id); // Apply selected style
      element.addClass('selected-region'); // Ensure selected style is prominent

      selectedRegionIdDisplay.text(id);
      areaServedSelector.prop('disabled', false);
      colorPickerInput.prop('disabled', false);
      assignButton.prop('disabled', false);

      if (currentMapLinks[id]) {
          areaServedSelector.val(currentMapLinks[id].term_id);
          colorPickerInput.wpColorPicker('color', currentMapLinks[id].color || RepMapLinkerData.default_color);
          removeButton.prop('disabled', false);
      } else {
          areaServedSelector.val('');
          colorPickerInput.wpColorPicker('color', RepMapLinkerData.default_color);
          removeButton.prop('disabled', true);
      }
  }

  mapTypeSelector.on('change', function() {
      loadSvgMap($(this).val());
  });

  assignButton.on('click', function() {
      const termId = areaServedSelector.val();
      const color = colorPickerInput.val();
      if (!currentSelectedSvgId || !termId) {
          alert('Please select an SVG region and an Area Served term.');
          return;
      }

      $.post(RepMapLinkerData.ajax_url, {
          action: 'save_rep_map_link',
          nonce: RepMapLinkerData.nonce,
          map_type: currentMapType,
          svg_region_id: currentSelectedSvgId,
          term_id: termId,
          color: color
      }).done(function(response) {
          if (response.success) {
              currentMapLinks = response.data.links;
              if (currentSelectedSvgElement) {
                  applyStylesToSvgElement(currentSelectedSvgElement, currentSelectedSvgId);
                  currentSelectedSvgElement.addClass('selected-region'); // Re-apply selected style
              }
              removeButton.prop('disabled', false);
              displayCurrentMappings();
              alert(response.data.message || 'Mapping saved!');
          } else {
              alert('Error saving mapping: ' + (response.data.message || 'Unknown error'));
          }
      }).fail(function() {
          alert('AJAX error saving mapping.');
      });
  });

  removeButton.on('click', function() {
      if (!currentSelectedSvgId) {
          alert('Please select an SVG region to remove its link.');
          return;
      }
      if (!confirm('Are you sure you want to remove the link for region ' + currentSelectedSvgId + '?')) {
          return;
      }
      $.post(RepMapLinkerData.ajax_url, {
          action: 'delete_rep_map_link',
          nonce: RepMapLinkerData.nonce,
          map_type: currentMapType,
          svg_region_id: currentSelectedSvgId
      }).done(function(response) {
          if (response.success) {
              currentMapLinks = response.data.links;
              if (currentSelectedSvgElement) {
                  applyStylesToSvgElement(currentSelectedSvgElement, currentSelectedSvgId);
                   currentSelectedSvgElement.removeClass('selected-region'); // Explicitly remove if needed
              }
              areaServedSelector.val('').prop('disabled', true);
              colorPickerInput.wpColorPicker('color', RepMapLinkerData.default_color).prop('disabled', true);
              selectedRegionIdDisplay.html('<em>None</em>');
              assignButton.prop('disabled', true);
              removeButton.prop('disabled', true);
              currentSelectedSvgId = null;
              currentSelectedSvgElement = null;
              displayCurrentMappings();
              alert(response.data.message || 'Mapping deleted!');
          } else {
              alert('Error deleting mapping: ' + (response.data.message || 'Unknown error'));
          }
      }).fail(function() {
          alert('AJAX error deleting mapping.');
      });
  });

  function displayCurrentMappings() {
      currentMappingsDisplay.empty();
      if (Object.keys(currentMapLinks).length === 0) {
          currentMappingsDisplay.append('<p><em>No mappings yet for this map.</em></p>');
          return;
      }
      const ul = $('<ul></ul>');
      for (const svgId in currentMapLinks) {
          if (currentMapLinks.hasOwnProperty(svgId)) {
              const mappingData = currentMapLinks[svgId];
              const termId = mappingData.term_id;
              const color = mappingData.color || RepMapLinkerData.default_color;
              const termName = termIdToNameMap[termId] || 'Unknown Term (ID: ' + termId + ')';
              ul.append('<li><strong>' + svgId + '</strong> &rarr; ' + termName + 
                        ' <span style="display:inline-block;width:15px;height:15px;background-color:' + color + ';border:1px solid #777;margin-left:5px;"></span>' +
                        '</li>');
          }
      }
      currentMappingsDisplay.append(ul);
  }

  // Initial load
  loadSvgMap(currentMapType);
}); 