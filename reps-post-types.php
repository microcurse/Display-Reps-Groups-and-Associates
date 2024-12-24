<?php
/**
 * Plugin Name: Rep Group Shortcode
 * Description: A plugin to display Rep Group information using a shortcode.
 * Version: 2.0.0
 * Author: Marc Maninang
 * Update URI: https://github.com/yourusername/rep-group-plugin
 * GitHub Plugin URI: https://github.com/yourusername/rep-group-plugin
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('REP_GROUP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('REP_GROUP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('REP_GROUP_VERSION', '2.0.0');

// Activation/Deactivation hooks
register_activation_hook(__FILE__, function() {
    // Get old version if it exists
    $old_version = get_option('rep_group_version', '0');
    
    // If this is a new installation or update
    if (version_compare($old_version, REP_GROUP_VERSION, '<')) {
        // Update the version in the database
        update_option('rep_group_version', REP_GROUP_VERSION);
        
        // Clear any caches
        flush_rewrite_rules();
    }
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

// Require files
require_once REP_GROUP_PLUGIN_PATH . 'includes/class-import-export.php';
require_once REP_GROUP_PLUGIN_PATH . 'includes/class-shortcode.php';
require_once REP_GROUP_PLUGIN_PATH . 'includes/class-renderer.php';
require_once REP_GROUP_PLUGIN_PATH . 'includes/class-post-type.php';

// Initialize plugin
function init_rep_group_plugin() {
    new RepGroup\Import_Export();
    new RepGroup\Shortcode();
    new RepGroup\Post_Type();
}
add_action('plugins_loaded', 'init_rep_group_plugin');