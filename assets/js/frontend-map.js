document.addEventListener('DOMContentLoaded', function () {
    const mapContainer = document.getElementById('rep-map-container');
    if (!mapContainer) return;

    const localMapDiv = document.getElementById('map-local');
    const internationalMapDiv = document.getElementById('map-international');
    const sidebar = document.getElementById('map-sidebar');
    const sidebarTitle = document.getElementById('sidebar-title');
    const sidebarContent = document.getElementById('sidebar-content');
    const closeSidebarButton = document.getElementById('close-sidebar');
    const toggleMapTypeButton = document.getElementById('toggle-map-type');
    const toggleViewTypeButton = document.getElementById('toggle-view-type');
    const filterRepGroupSelect = document.getElementById('filter-rep-group');
    const filterAreaSelect = document.getElementById('filter-area');

    let currentMap = 'local'; // 'local' or 'international'
    let currentView = 'area'; // 'area' or 'rep_group'
    let currentFilters = {
        repGroup: null, // Selected Rep Group ID
        area: null      // Selected Area Term ID
    };

    // --- Data from PHP (already available from map-display.php) ---
    // repMapAreasData: object mapping svg_id to term details {term_id, name, slug}
    // repMapRepGroupsData: array of rep group objects {id, name, logo, website, ..., areas_served (array of term_ids), associates}
    // repMapLocalSvgUrl, repMapInternationalSvgUrl

    function getRepGroupsForArea(termId) {
        return repMapRepGroupsData.filter(rg => rg.areas_served.includes(termId));
    }

    function getAreaName(termId) {
        for (const svgId in repMapAreasData) {
            if (repMapAreasData[svgId].term_id === termId) {
                return repMapAreasData[svgId].name;
            }
        }
        return 'Unknown Area';
    }
    
    function getAreaBySvgId(svgId) {
        return repMapAreasData[svgId] || null;
    }

    function displaySidebarForArea(svgTargetId) {
        const areaInfo = getAreaBySvgId(svgTargetId);
        if (!areaInfo) {
            sidebarTitle.textContent = 'Unknown Area';
            sidebarContent.innerHTML = '<p>No information available for this area.</p>';
            sidebar.style.display = 'block';
            return;
        }

        const termId = areaInfo.term_id;
        const repGroups = getRepGroupsForArea(termId);

        sidebarTitle.textContent = areaInfo.name;
        let contentHtml = '';

        if (repGroups.length > 0) {
            repGroups.forEach(rg => {
                contentHtml += `<h4>${rg.name}</h4>`;
                if (rg.logo) contentHtml += `<img src="${rg.logo.url || rg.logo}" alt="${rg.name} Logo" style="max-width: 100px; height: auto;">`; // Handle ACF image object or URL
                if (rg.website) contentHtml += `<p><a href="${rg.website}" target="_blank">Website</a></p>`;
                if (rg.description) contentHtml += `<p>${rg.description}</p>`;
                if (rg.contact_info) contentHtml += `<div><strong>Contact:</strong> ${rg.contact_info}</div>`;
                
                if (rg.associates && rg.associates.length > 0) {
                    contentHtml += '<h5>Associates:</h5><ul>';
                    rg.associates.forEach(assoc => {
                        contentHtml += `<li>${assoc.name} - ${assoc.title} (${assoc.email}, ${assoc.phone})</li>`;
                    });
                    contentHtml += '</ul>';
                }
                contentHtml += '<hr>';
            });
        } else {
            contentHtml += '<p>No representative groups assigned to this area.</p>';
        }
        sidebarContent.innerHTML = contentHtml;
        sidebar.style.display = 'block';
    }
    
    function displaySidebarForRepGroup(repGroupId) {
        const repGroup = repMapRepGroupsData.find(rg => rg.id === repGroupId);
        if (!repGroup) return;

        sidebarTitle.textContent = repGroup.name;
        let contentHtml = '';
        if (repGroup.logo) contentHtml += `<img src="${repGroup.logo.url || repGroup.logo}" alt="${repGroup.name} Logo" style="max-width: 100px; height: auto;">`;
        if (repGroup.website) contentHtml += `<p><a href="${repGroup.website}" target="_blank">Website</a></p>`;
        if (repGroup.description) contentHtml += `<p>${repGroup.description}</p>`;
        if (repGroup.contact_info) contentHtml += `<div><strong>Contact:</strong> ${repGroup.contact_info}</div>`;

        if (repGroup.associates && repGroup.associates.length > 0) {
            contentHtml += '<h5>Associates:</h5><ul>';
            repGroup.associates.forEach(assoc => {
                contentHtml += `<li>${assoc.name} - ${assoc.title} (${assoc.email}, ${assoc.phone})</li>`;
            });
            contentHtml += '</ul>';
        }

        contentHtml += '<h5>Areas Served:</h5><ul>';
        repGroup.areas_served.forEach(termId => {
            contentHtml += `<li>${getAreaName(termId)}</li>`;
        });
        contentHtml += '</ul>';

        sidebarContent.innerHTML = contentHtml;
        sidebar.style.display = 'block';
    }


    function handleSvgElementInteraction(svgElement, svgTargetId) {
        svgElement.classList.add('map-region'); // General class for styling
        
        // Apply highlight color from term meta if available
        const areaInfo = getAreaBySvgId(svgTargetId);
        if (areaInfo && areaInfo.color) {
            // svgElement.style.fill = areaInfo.color; // Apply color directly
            // Or add a class and define colors in CSS if preferred for specific states
        }
        // The .highlight class added later by updateMapHighlights will handle the main "active" color


        svgElement.addEventListener('mouseenter', () => {
            svgElement.classList.add('hovered');
        });
        svgElement.addEventListener('mouseleave', () => {
            svgElement.classList.remove('hovered');
        });
        svgElement.addEventListener('click', () => {
            if (currentView === 'area') {
                displaySidebarForArea(svgTargetId);
            } else {
                // If view is by rep group, clicking an area should still show area info,
                // or potentially highlight reps serving this area.
                // For now, keep it simple:
                displaySidebarForArea(svgTargetId);
            }
        });
    }
    
    function setupMapInteractivity(mapDiv) {
        if (!mapDiv || !mapDiv.querySelector('svg')) return;
        const svg = mapDiv.querySelector('svg');

        // Iterate over areas_data which contains the SVG IDs/classes
        for (const svgTargetId in repMapAreasData) {
            if (repMapAreasData.hasOwnProperty(svgTargetId)) {
                const areaInfo = repMapAreasData[svgTargetId];
                let elements;
                // Check if svgTargetId is a class (starts with .) or an ID (starts with # or is plain)
                if (svgTargetId.startsWith('.')) {
                    elements = svg.querySelectorAll(svgTargetId);
                } else {
                    const id = svgTargetId.startsWith('#') ? svgTargetId.substring(1) : svgTargetId;
                    const elementById = svg.getElementById(id);
                    elements = elementById ? [elementById] : [];
                }
                
                elements.forEach(el => {
                     handleSvgElementInteraction(el, svgTargetId);
                     // Highlight areas that have reps
                     const repGroupsInArea = getRepGroupsForArea(areaInfo.term_id);
                     if (repGroupsInArea.length > 0) {
                        el.classList.add('highlight'); // General highlight for active areas
                     }
                });
            }
        }
    }


    if (closeSidebarButton) {
        closeSidebarButton.addEventListener('click', () => {
            sidebar.style.display = 'none';
        });
    }

    if (toggleMapTypeButton) {
        toggleMapTypeButton.addEventListener('click', () => {
            if (currentMap === 'local') {
                currentMap = 'international';
                localMapDiv.style.display = 'none';
                localMapDiv.classList.remove('map-active');
                internationalMapDiv.style.display = 'block';
                internationalMapDiv.classList.add('map-active');
                toggleMapTypeButton.textContent = 'Switch to Local Map';
                setupMapInteractivity(internationalMapDiv); 
            } else {
                currentMap = 'local';
                internationalMapDiv.style.display = 'none';
                internationalMapDiv.classList.remove('map-active');
                localMapDiv.style.display = 'block';
                localMapDiv.classList.add('map-active');
                toggleMapTypeButton.textContent = 'Switch to International Map';
                setupMapInteractivity(localMapDiv);
            }
            sidebar.style.display = 'none'; // Close sidebar on map switch
        });
    }

    if (toggleViewTypeButton) {
        // This button's functionality is more complex and depends on how "View by Rep Group"
        // should visually alter the map (e.g., coloring by rep group, or just changing click behavior).
        // For now, it will just toggle a state and could influence the sidebar.
        toggleViewTypeButton.addEventListener('click', () => {
            if (currentView === 'area') {
                currentView = 'rep_group';
                toggleViewTypeButton.textContent = 'Switch to View by Area';
                // Potentially re-render map highlights or available click targets
                // For now, sidebar will show Rep Group info if a rep group is clicked (not implemented yet)
                // or if an area is clicked, it could list rep groups.
            } else {
                currentView = 'area';
                toggleViewTypeButton.textContent = 'Switch to View by Rep Group';
            }
            sidebar.style.display = 'none'; // Close sidebar on view switch
            // Redraw or update highlights based on the new view
            updateMapHighlights();
        });
    }
    
    function updateMapHighlights() {
        const activeMapDiv = (currentMap === 'local') ? localMapDiv : internationalMapDiv;
        if (!activeMapDiv || !activeMapDiv.querySelector('svg')) return;
        const svg = activeMapDiv.querySelector('svg');

        // Clear all existing highlights first
        svg.querySelectorAll('.highlight, .dimmed, .rep-group-highlight-specific').forEach(el => {
            el.classList.remove('highlight', 'dimmed', 'rep-group-highlight-specific');
            // Reset any inline styles if they were used for coloring
            // el.style.fill = ''; 
        });

        const selectedRepGroupId = currentFilters.repGroup;
        const selectedAreaTermId = currentFilters.area;

        // If no filters, default behavior (highlight areas with any reps)
        if (!selectedRepGroupId && !selectedAreaTermId) {
            for (const svgTargetId in repMapAreasData) {
                if (repMapAreasData.hasOwnProperty(svgTargetId)) {
                    const areaInfo = repMapAreasData[svgTargetId];
                    const repGroupsInArea = getRepGroupsForArea(areaInfo.term_id);
                    if (repGroupsInArea.length > 0) {
                        applyClassToSvgTarget(svg, svgTargetId, 'highlight');
                    }
                }
            }
            return; // Exit after default highlighting
        }
        
        // Apply filters
        // All regions start dimmed if a filter is active
        svg.querySelectorAll('path, g, rect, circle, polygon').forEach(el => el.classList.add('dimmed'));


        if (selectedRepGroupId) {
            // Highlight areas served by the selected rep group
            const repGroup = repMapRepGroupsData.find(rg => rg.id === selectedRepGroupId);
            if (repGroup) {
                repGroup.areas_served.forEach(termId => {
                    const svgTargetId = findSvgIdByTermId(termId);
                    if (svgTargetId) {
                        applyClassToSvgTarget(svg, svgTargetId, 'highlight', true); // true to remove .dimmed
                    }
                });
            }
        } else if (selectedAreaTermId) {
            // Highlight the selected area
            const svgTargetId = findSvgIdByTermId(selectedAreaTermId);
            if (svgTargetId) {
                 applyClassToSvgTarget(svg, svgTargetId, 'highlight', true); // true to remove .dimmed
            }
        }
    }

    function findSvgIdByTermId(termId) {
        for (const svgId in repMapAreasData) {
            if (repMapAreasData[svgId].term_id === termId) {
                return svgId;
            }
        }
        return null;
    }
    
    function applyClassToSvgTarget(svgContainer, svgTargetId, className, removeDimmed = false) {
        let elements;
        if (svgTargetId.startsWith('.')) {
            elements = svgContainer.querySelectorAll(svgTargetId);
        } else {
            const id = svgTargetId.startsWith('#') ? svgTargetId.substring(1) : svgTargetId;
            const elementById = svgContainer.getElementById(id);
            elements = elementById ? [elementById] : [];
        }
        elements.forEach(el => {
            el.classList.add(className);
            if (removeDimmed) {
                el.classList.remove('dimmed');
            }
        });
    }

    // Event Listeners for filters
    if (filterRepGroupSelect) {
        filterRepGroupSelect.addEventListener('change', function() {
            currentFilters.repGroup = this.value ? parseInt(this.value) : null;
            currentFilters.area = null; // Reset area filter when rep group changes
            if(filterAreaSelect) filterAreaSelect.value = "";
            updateMapHighlights();
            if (sidebar.style.display === 'block' && currentFilters.repGroup) {
               // Optionally, if a rep group is selected and sidebar is open, update sidebar for this group
               // displaySidebarForRepGroup(currentFilters.repGroup); 
            } else if (!currentFilters.repGroup && !currentFilters.area) {
                sidebar.style.display = 'none';
            }
        });
    }

    if (filterAreaSelect) {
        filterAreaSelect.addEventListener('change', function() {
            currentFilters.area = this.value ? parseInt(this.value) : null;
            currentFilters.repGroup = null; // Reset rep group filter when area changes
            if(filterRepGroupSelect) filterRepGroupSelect.value = "";
            updateMapHighlights();
            if (sidebar.style.display === 'block' && currentFilters.area) {
                // To display sidebar for area, we need SVG ID. 
                // This requires finding the SVG ID for the selected term_id.
                // For now, simply applying filter and user can click map.
                // Alternatively, if an area is selected, we could try to find its SVG target and show sidebar.
                // displaySidebarForArea(findSvgIdByTermId(currentFilters.area));
            } else if (!currentFilters.repGroup && !currentFilters.area) {
                 sidebar.style.display = 'none';
            }
        });
    }

    // Initial setup
    setupMapInteractivity(localMapDiv); // Setup for the initially visible map
    updateMapHighlights(); // Initial highlights
});
