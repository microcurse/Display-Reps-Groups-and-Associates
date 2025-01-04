jQuery(document).ready(function($) {
    console.log('Admin map JS loaded');

    // Configuration object
    const config = {
        colors: {
            default: '#D8D8D8',
            selected: '#0073aa'
        },
        selectors: {
            svg: 'svg',
            mapControls: '#map-controls',
            selectedState: '#selected-state',
            repGroupSelect: '#rep-group-select',
            uploadButton: '#upload-map-svg',
            removeButton: '#remove-map-svg'
        }
    };

    // State management
    const state = {
        selectedState: null,
        mediaUploader: null
    };

    // Map functionality
    const MapManager = {
        init() {
            this.resetColors();
            this.initializeRegions();
        },

        resetColors() {
            $(config.selectors.svg + ' path').css('fill', config.colors.default);
        },

        initializeRegions() {
            const $paths = this.getAllRegionPaths();
            $paths.each((_, path) => this.setupRegion($(path)));
        },

        getAllRegionPaths() {
            const $individualPaths = $('svg path[id^="US-"], svg path[id^="CA-"]');
            const $groupedPaths = $('svg g[id^="US-"] path, svg g[id^="CA-"] path');
            return $individualPaths.add($groupedPaths);
        },

        setupRegion($path) {
            const $parent = $path.parent('g');
            const pathId = $parent.is('g[id^="US-"], g[id^="CA-"]') ? $parent.attr('id') : $path.attr('id');

            if (!this.isValidRegion(pathId)) return;

            $path.css('cursor', 'pointer')
                .on('mouseenter', (e) => this.handleHover(e, $path, $parent, pathId, true))
                .on('mouseleave', (e) => this.handleHover(e, $path, $parent, pathId, false))
                .on('click', (e) => this.handleClick(e, $path, $parent, pathId));
        },

        isValidRegion(pathId) {
            return pathId && 
                   !pathId.toLowerCase().includes('separator') && 
                   !pathId.toLowerCase().includes('landmarks');
        },

        handleHover(e, $path, $parent, pathId, isEntering) {
            e.stopPropagation();
            if (pathId !== state.selectedState) {
                const color = isEntering ? config.colors.selected : config.colors.default;
                this.updateRegionColor($path, $parent, color);
            }
        },

        handleClick(e, $path, $parent, pathId) {
            e.preventDefault();
            e.stopPropagation();

            // Reset previous selection
            if (state.selectedState) {
                this.resetRegion($(`#${state.selectedState}`));
            }

            // Update selection
            state.selectedState = pathId;
            this.updateRegionColor($path, $parent, config.colors.selected);
            
            // We'll get the proper name after fetching assignments
            this.fetchStateAssignments(pathId);
        },

        updateRegionColor($path, $parent, color) {
            if ($parent.is('g[id^="US-"], g[id^="CA-"]')) {
                $parent.find('path').css('fill', color);
            } else {
                $path.css('fill', color);
            }
        },

        resetRegion($region) {
            if ($region.is('g')) {
                $region.find('path').css('fill', config.colors.default);
            } else {
                $region.css('fill', config.colors.default);
            }
        },

        fetchStateAssignments(stateId) {
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
                        // Update the select with rep groups
                        $(config.selectors.repGroupSelect)
                            .val(response.data.rep_groups)
                            .trigger('change');
                        
                        // Update UI with the term name from the response
                        $(config.selectors.selectedState).text(response.data.state_name);
                    }
                }
            });
        }
    };

    // Media management
    const MediaManager = {
        init() {
            this.bindEvents();
        },

        bindEvents() {
            $(config.selectors.uploadButton).on('click', (e) => this.handleUpload(e));
            $(config.selectors.removeButton).on('click', (e) => this.handleRemove(e));
        },

        handleUpload(e) {
            e.preventDefault();
            
            if (!this.isMediaLibraryAvailable()) return;
            
            if (state.mediaUploader) {
                state.mediaUploader.open();
                return;
            }

            this.createMediaUploader();
        },

        isMediaLibraryAvailable() {
            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                console.error('WordPress Media Library not available');
                return false;
            }
            return true;
        },

        createMediaUploader() {
            state.mediaUploader = wp.media({
                title: 'Select SVG Map',
                button: { text: 'Use this map' },
                multiple: false,
                library: { type: ['image/svg+xml'] }
            });

            state.mediaUploader.on('select', () => this.handleMediaSelection());
            state.mediaUploader.open();
        },

        handleMediaSelection() {
            const attachment = state.mediaUploader.state().get('selection').first().toJSON();
            this.updateMapSvg(attachment.id);
        },

        updateMapSvg(attachmentId) {
            $.ajax({
                url: repGroupsAdmin.ajaxurl,
                method: 'POST',
                data: {
                    action: 'update_map_svg',
                    nonce: repGroupsAdmin.nonce,
                    attachment_id: attachmentId
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
        },

        handleRemove(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to remove the current map?')) {
                this.removeMapSvg();
            }
        },

        removeMapSvg() {
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
    };

    // Initialize Select2
    $(config.selectors.repGroupSelect).select2({
        width: '100%',
        placeholder: 'Select rep groups',
        allowClear: true,
        closeOnSelect: false
    });

    // Initialize everything
    MapManager.init();
    MediaManager.init();
}); 