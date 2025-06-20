<?php
/**
 * Plugin Name: Display Rep Groups and Associates
 * Description: A plugin to display Rep Group information.
 * Version: 2.2.1
 * Author: Marc Maninang
 * Plugin URI: https://github.com/microcurse/Display-Reps-Groups-and-Associates
 * GitHub Plugin URI: https://github.com/microcurse/Display-Reps-Groups-and-Associates
 * Primary Branch: main
 * Release Branch: main
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Update URI: https://github.com/microcurse/Display-Reps-Groups-and-Associates
 */

namespace RepGroup;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('REP_GROUP_VERSION', '2.2.1');
define('REP_GROUP_PATH', plugin_dir_path(__FILE__));
define('REP_GROUP_URL', plugin_dir_url(__FILE__));
define('REP_GROUP_DEFAULT_REGION_COLOR', '#CCCCCC');

/**
 * Check if ACF Pro is active. Used to gate plugin functionality.
 */
function is_acf_pro_active() {
    $is_acf_pro = defined('ACF_PRO') ? constant('ACF_PRO') : false;
    return class_exists('ACF') && 
           function_exists('get_field') && 
           $is_acf_pro;
}

/**
 * Admin notice if ACF Pro is not active.
 */
function acf_pro_inactive_notice() {
    ?>
    <div class="error notice">
        <p>
            <?php 
            printf(
                esc_html__('The "%s" plugin requires Advanced Custom Fields Pro to be active. Please install and activate ACF Pro.', 'rep-group'),
                'Display Rep Groups and Associates'
            );
            ?>
        </p>
    </div>
    <?php
}

// ACF JSON Save and Load points
/**
 * Sets the save path for ACF JSON local fields.
 */
function acf_json_save_point_callback( $path ) {
    return REP_GROUP_PATH . 'acf-json';
}
add_filter('acf/settings/save_json', 'RepGroup\acf_json_save_point_callback');

/**
 * Sets the load path for ACF JSON local fields.
 */
function acf_json_load_point_callback( $paths ) {
    unset($paths[0]); // Remove the original path (usually theme's acf-json folder)
    $paths[] = REP_GROUP_PATH . 'acf-json'; // Add our plugin's path
    return $paths;
}
add_filter('acf/settings/load_json', 'RepGroup\acf_json_load_point_callback');

// Autoload classes
spl_autoload_register(function ($class) {
    // Check if the class is in our namespace
    if (strpos($class, 'RepGroup\\') !== 0) {
        return;
    }

    // Remove namespace from class name
    $class_name = str_replace('RepGroup\\', '', $class);
    
    // Convert class name format to file name format
    $file_name = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
    
    // Build file path
    $file_path = REP_GROUP_PATH . 'includes/' . $file_name;
    
    // Include file if it exists
    if (file_exists($file_path)) {
        require_once $file_path;
    }
});

// Initialize plugin
function init_rep_group_plugin() {
    // Perform the ACF Pro check here, inside plugins_loaded action
    if (!is_acf_pro_active()) {
        add_action('admin_notices', 'RepGroup\acf_pro_inactive_notice');
        return; // Do not initialize the plugin classes if ACF Pro is not active
    }

    // Proceed with plugin class initializations
    new Map_Settings(); 
    new Shortcode(); 
    new Asset_Manager(); 
    new Post_Type();
    new Import_Export();
    new Taxonomy_Manager();
}
add_action('plugins_loaded', 'RepGroup\init_rep_group_plugin', 20); // Added priority 20 to load later