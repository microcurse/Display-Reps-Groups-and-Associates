<?php
/**
 * Plugin Name: Display Rep Groups and Associates
 * Description: A plugin to display Rep Group information.
 * Version: 2.0.0
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
 define('REP_GROUP_VERSION', '1.0.0');
 define('REP_GROUP_PATH', plugin_dir_path(__FILE__));
 define('REP_GROUP_URL', plugin_dir_url(__FILE__));
 
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

     // For Map_Settings, the path is different
     if ($class_name === 'Map_Settings') {
        $file_path = REP_GROUP_PATH . 'includes/class-map-settings.php';
     }

     // For Shortcode class
     if ($class_name === 'Shortcode') {
        $file_path = REP_GROUP_PATH . 'includes/class-shortcode.php';
     }

     // For Asset_Manager class
     if ($class_name === 'Asset_Manager') {
        $file_path = REP_GROUP_PATH . 'includes/class-asset-manager.php';
     }
     
     // Include file if it exists
     if (file_exists($file_path)) {
         require_once $file_path;
     }
 });
 
 // Initialize plugin
 function init_rep_group_plugin() {
     new Rep_Group(); // This should already exist
     new Map_Settings(); // Add this line
     new Shortcode(); // Add this line
     new Asset_Manager(); // Add this line
     new Map_Editor_Page(); // Add this line
 }
 
 add_action('plugins_loaded', 'RepGroup\\init_rep_group_plugin');