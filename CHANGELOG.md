# Changelog

### 2.1.0 (Current Version)
*   **Added Interactive SVG Map:** New `[rep_map]` shortcode for local/international territory display.
*   Features clickable regions, pan/zoom, and a dynamic info panel with Rep Group and Associate details.
*   Implemented "Areas Served" taxonomy and linked it to SVG regions via term meta.
*   Added `rg_map_scope` ACF field to Rep Groups for map-specific filtering.
*   Side panel includes "View By" (Rep Groups/Areas) and A-Z/Z-A sorting.
*   Map region colors now primarily managed via WP option populated by the new WP-CLI bulk import script.
*   Rep Group list items in map panel show color indicators from `rep_group_map_color` ACF field.
*   WP-CLI script `bulk_import_areas.php` for managing "Areas Served" terms, SVG IDs, and map colors.
*   Enhanced styling with theme CSS variable support for light/dark modes.
*   Resolved various bugs related to settings, export, and UI interactions.

### 2.0.0 - 2.0.2
*   Added card-based layout for rep associates.
*   Improved import/export functionality for Rep Group CPT.
*   Added proper styling and organization.
*   Removed author display from CPT templates.
*   Added GitHub integration for plugin updates.
*   Fixed duplicate "Map Settings" menu.
*   Added "Settings saved." notice for map settings updates.
*   Refined ACF field types for better data integrity (e.g., "Territory Served" to Taxonomy).

### 1.0.0
*   Initial release.
*   Basic Rep Group CPT and display functionality.
*   Initial Import/Export capabilities for Rep Group CPT.