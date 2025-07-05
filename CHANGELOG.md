# Changelog

## 2.3.0 - 2025-01-02

### Added
- **Fallback Templates:** Created simplified fallback page templates for single and archive rep-group pages with clear navigation back to the interactive map
- **Template Loader:** Implemented plugin-based template loading system for portability across themes
- **CSS Scoping:** Added proper CSS scoping to prevent conflicts with existing map functionality

### Changed
- **Simplified Layout:** Replaced complex card-based layouts with clean, minimal fallback pages focused on essential information
- **Sidebar Integration:** Fixed sidebar loading to properly use theme's standard `get_sidebar('shop')` approach
- **Map Navigation:** Updated links to point to correct `/forbes-reps/` path for interactive map
- **CSS Optimization:** Streamlined CSS from complex two-column layouts to simple single-column fallback styling

### Fixed
- **Associates Display:** Fixed field name from `rg_associates` to `rep_associates` to properly display associate information
- **Sidebar Missing:** Resolved sidebar not appearing by implementing proper WordPress template structure
- **CSS Conflicts:** Eliminated conflicts between rep-group.css and frontend.css by adding specific page-type prefixes

## 2.2.1

### Fixed
- **Import/Export Taxonomy Assignment:** Corrected the import logic to ensure "Areas Served" taxonomies are always created before being assigned to their Rep Group posts, fixing a critical bug where relationships were not being saved.
- **Import/Export Map URLs:** The import process now automatically replaces the source site's URL with the destination site's URL in the SVG map settings, ensuring maps work correctly after migration.

## 2.2.0 - 2025-06-20

### Added
- **Manual Rep Associate Entry:** Added a fallback system to allow for manual entry of Rep Associates who do not have a WordPress user account. Includes fields for Full Name, Title, Email, and Phone.
- **Satellite Offices:** Implemented a repeater field for Rep Groups to add multiple satellite office locations, each with its own address and phone numbers.
- **Import/Export Functionality:** Added a dedicated admin page to import and export all plugin data (Rep Groups, Areas Served, and map settings) as a JSON file, facilitating migration between sites.

### Changed
- Updated the Rep Associate repeater to display the associate's name in the collapsed view for easier identification.
- Relabeled the primary address field for Rep Groups to "Main Office Address" for clarity.

## 2.1.0

### Added
- Added a `rep_title` field to user profiles for Reps.
- Override fields for email and phone on a per-associate basis within a Rep Group.
- SVG map color is now managed on the Rep Group post.

### Changed
- Re-styled the map info panel for a cleaner, more modern look.
- Improved AJAX handling and data loading for the interactive map.

## 2.0.0
- Initial major release with interactive SVG map functionality.

### 2.1.0 (Current Version)
*   **Added Interactive SVG Map:** New `[rep_map]`