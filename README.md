# Display Reps, Groups, and Associates

A WordPress plugin to manage and display sales representative groups, their associates, and their territories on an interactive map.

## Description

This plugin provides a comprehensive solution for managing sales representatives and their associated groups. It allows for the creation of "Rep Group" custom posts, which can be detailed with contact information, office locations, and team members. A key feature is the interactive SVG map, which visually displays sales territories and provides details on the corresponding rep groups.

## Features

*   **Rep Group Management:** Custom post type for creating and managing Rep Groups.
*   **Rep Associate Management:** Link WordPress users with a "Rep" role to Rep Groups, or manually enter associate details for those without a user account.
*   **Multiple Office Locations:** Define a main office and add any number of satellite offices for each Rep Group.
*   **Interactive SVG Map:** Use the `[rep_map]` shortcode to display an interactive map of your sales territories.
    *   Clickable regions that display corresponding Rep Group information.
    *   Pan and zoom functionality.
    *   Dynamic side panel with list views for both Rep Groups and Areas Served, with A-Z/Z-A sorting.
*   **"Areas Served" Taxonomy:** A dedicated taxonomy to define sales territories and link them to Rep Groups and specific regions on the SVG map.
*   **Import/Export Tool:** A built-in tool to export all plugin data (Rep Groups, Areas, settings) to a JSON file and import it into another site, making migrations simple.
*   **Developer Friendly:** Includes WP-CLI support for some operations and is built with standard WordPress hooks and filters for extensibility.

## Installation

1.  Upload the plugin files to the `/wp-content/plugins/Display-Reps-Groups-and-Associates` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Ensure that Advanced Custom Fields (ACF) Pro is installed and activated, as this plugin depends on it.

## Usage

### Creating Rep Groups

1.  Navigate to **Rep Groups > Add New** in the WordPress admin menu.
2.  Fill in the Rep Group's name and details.
3.  **Main Office Address:** Enter the primary address for the group.
4.  **Phone Number(s) & Email:** Add primary contact methods for the group.
5.  **Rep Associates:** Add team members.
    *   **WP User:** Select a user from the dropdown. Their details will be pulled from their user profile.
    *   **Manual Entry:** Manually type in the associate's full name, title, email, and phone.
6.  **Satellite Offices:** If the group has additional locations, add them using the "Add Satellite Office" button.
7.  **Areas Served:** In the right-hand sidebar, select the territories this Rep Group is responsible for from the "Areas Served" taxonomy box.
8.  Publish the Rep Group.

### Setting up the Map

1.  Navigate to **Rep Groups > Map Settings**.
2.  Upload your Local (e.g., North America) and/or International map SVG files.
3.  Navigate to **Rep Groups > Areas Served**. Here you can manage your sales territories.
4.  For each "Area Served" term, edit it and find the "SVG Target ID" field. Enter the corresponding ID of the path/shape from your SVG file (e.g., `wa` for Washington State). This links the territory to the map region.

### Displaying the Map

Use the following shortcode in your pages or posts:
`[rep_map type="local"]` for the local map.
`[rep_map type="international"]` for the international map.

### Import & Export

1.  Navigate to **Rep Groups > Import / Export**.
2.  **To Export:** Click the "Export All Data" button. A JSON file will be downloaded.
3.  **To Import:** Click "Choose File," select the JSON file you previously exported, and click "Import Data."

## Frequently Asked Questions

*   **What is required for this plugin to work?**
    *   Advanced Custom Fields (ACF) Pro must be installed and active.

## Changelog

Please see `CHANGELOG.md` for a detailed history of changes.