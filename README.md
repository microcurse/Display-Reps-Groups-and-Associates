# Display Rep Groups and Associates Plugin

**Version: 2.1.0**

This plugin provides a comprehensive system for managing and displaying "Rep Groups" (a custom post type), their "Rep Associates" (via ACF repeater fields), and the "Areas Served" (a custom taxonomy). It features an interactive SVG map display to visually represent sales territories and associated representative information.

## Key Features

*   **Custom Post Types & Fields:**
    *   **Rep Group (Custom Post Type):** Manages main representative group information.
        *   ACF Fields: Rep Group Name, Address, Phone, Email, Logo, Map Scope (`local`/`international`/`both`), and a custom Map Color (`rep_group_map_color`) for list indicators.
    *   **Rep Associates (ACF Repeater Field on Rep Group):** Manages individual representatives within a group.
        *   ACF Fields: Rep's Name, Title (from User Profile), Phone(s) with type, Email, and "Territory Served".
    *   **Areas Served (Custom Taxonomy `area-served`):** Manages distinct geographical regions.
        *   Each term has an "SVG Target CSS ID/Class" field to link it to a specific region in an SVG map.
*   **Interactive SVG Map Display:**
    *   Shortcode `[rep_map type="local|international"]` renders an interactive map.
    *   Uses scalable SVG files for maps (URLs configured in "Map Settings").
    *   Clickable map regions highlight and display information for the selected "Area Served".
    *   Pan and zoom functionality for easy map navigation.
    *   Dynamic side panel for displaying information:
        *   **Default View:** Lists all Rep Groups or all "Areas Served" relevant to the current map scope.
        *   **View By Dropdown:** Allows users to toggle between "Rep Groups" and "Areas Served" lists.
        *   **Sort Toggle:** Sorts the current list A-Z or Z-A by name/title.
        *   **Details View:** Activated by clicking a map region or a list item. Displays:
            *   Contextual "Area Served" name and its map color.
            *   Details of Rep Group(s) serving that area, including logo, contact info, and a list of their Rep Associates.
            *   Rep Associates' details include their title, specific territories they cover within the selected area context, and contact information.
        *   Smooth slide-in/out animations for the details panel.
*   **Color Management:**
    *   **Map Region Colors:** Defined via a WordPress option (`rep_group_map_links_local` or `_international`) that maps SVG region IDs to specific colors, term details. This option is best managed via the included WP-CLI bulk import script.
    *   **Rep Group List Indicators:** Colors for dots next to Rep Group names in the side panel list are sourced from the `rep_group_map_color` ACF field on each Rep Group post.
*   **Data Filtering:**
    *   Rep Groups and their associated "Areas Served" are automatically filtered on the map based on the `rg_map_scope` field (`local`, `international`, or `both`) matching the map type being displayed.
*   **Styling & Compatibility:**
    *   Frontend CSS uses theme-defined CSS variables for improved light/dark mode compatibility.
*   **Administrative Tools:**
    *   **Map Settings Page:** (Under "Rep Groups" > "Map Settings") Allows configuration of Local and International SVG map URLs.
    *   **Import/Export:** (Under "Rep Groups" > "Import/Export") Facilitates bulk import/export of Rep Group CPT data via CSV.
    *   **WP-CLI Bulk Import for Areas Served:**
        *   A powerful WP-CLI script (`bulk_import_areas.php` located in the WordPress root) allows for bulk creation and updating of "Areas Served" taxonomy terms.
        *   The script uses a CSV-like text file (`areas_to_import.txt` by default) with columns for: `Area Name,SVG_ID,RGB_Color`.
        *   Automatically links terms to SVG IDs and populates the map region color data in the corresponding WordPress option.

## Usage

### Shortcodes

1.  **Displaying Rep Group Archives or Single Entries:**
    *   `[rep_group_display]` - Renders an archive/grid of all Rep Groups.
    *   `[rep_group_display id="123"]` - Renders the details for a single Rep Group with the specified post ID.
    *   `[rep_group_display limit="5"]` - Limits the number of rep groups shown in the archive view.

2.  **Displaying the Interactive Rep Map:**
    *   `[rep_map type="local"]` - Renders the interactive map using the "Local Rep Map SVG URL" configured in settings and shows Rep Groups/Areas scoped for "local" or "both".
    *   `[rep_map type="international"]` - Renders the interactive map using the "International Rep Map SVG URL" and shows Rep Groups/Areas scoped for "international" or "both".

### Managing Map Data

1.  **Configure SVG Map URLs:**
    *   Go to WordPress Admin > Rep Groups > Map Settings.
    *   Enter the full URLs for your local and international SVG map files. Save changes.

2.  **Prepare "Areas Served" and Link to SVG:**
    *   **Manually:**
        *   Go to WordPress Admin > Rep Groups > Areas Served.
        *   Create each "Area Served" term (e.g., "California," "Western Region").
        *   For each term, edit it and fill in the "SVG Target CSS ID/Class" field with the exact ID of the corresponding clickable path/element in your SVG file (e.g., `US-CA`, `region-west`).
    *   **Bulk Import (Recommended):**
        *   Prepare a text file (e.g., `areas_to_import.txt` in your WordPress root). Each line should contain: `Area Name,SVG_ID,rgb(R,G,B_Color_for_Map_Region)`. Example: `California,US-CA,rgb(255,0,0)`
        *   Via WP-CLI, navigate to your WordPress root and run: `wp eval-file bulk_import_areas.php areas_to_import.txt` (assuming `bulk_import_areas.php` is also in the root).
        *   This will create the "Areas Served" terms, set their SVG IDs, and populate the necessary WordPress option with the specified map region colors.

3.  **Assign "Areas Served" to Rep Groups/Associates:**
    *   Edit a Rep Group post.
    *   In the "Rep Associates" repeater field, for each associate, select the relevant "Territory Served" (which are your "Areas Served" terms).

4.  **Set Rep Group Scope & List Colors:**
    *   Edit a Rep Group post.
    *   Set the "Map Scope" (Local, International, Both).
    *   Choose a "Rep Group Map Color" to be used as an indicator in the map's side panel list.

## Requirements

*   WordPress 5.0 or higher
*   PHP 7.4 or higher
*   Advanced Custom Fields Pro plugin