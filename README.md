# Display Rep Groups and Associates
Shortcode Generator to display the rep group custom post type and associated reps. Custom Post type is built in ACF along with Field Groups for for inputs.

## Inputs for user
Rep Group
- Area Served
- Rep Group Name
- Rep Group Address
- Rep Group Phone Number (if available)
- Rep Group Email (if available)

Rep Associates (multiple)
- Rep's Name
- Territory served (if available)
- Rep's Phone Number(s) 
-- More numbers can be added/removed
-- Selection drop down for: "Mobile", "Office", "Fax", "Other"
-- When displayed on the front end, display the type of number it is before the number.
- Rep's Email

## Usage

### Shortcode
Use the shortcode with a rep group ID to display information: [rep_group id="123"]

### Import/Export
1. Navigate to Rep Groups → Import/Export
2. Use the Export button to download current rep groups
3. Use the Import feature to update or add new rep groups

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Advanced Custom Fields Pro plugin

## Changelog

### 2.1.0
- Added archive page template
- Added card-based layout for rep associates
- Added image featured image support for rep groups
- Added proper styling and organization
- Removed author display

### 2.0.0
- Added card-based layout for rep associates
- Improved import/export functionality
- Added proper styling and organization
- Removed author display
- Added GitHub integration

### 1.0.0
- Initial release
- Basic rep group functionality
- Import/Export capabilities