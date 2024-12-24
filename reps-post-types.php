<?php
/**
 * Plugin Name: Rep Group Shortcode
 * Description: A plugin to display Rep Group information using a shortcode.
 * Version: 2.0
 * Author: Marc Maninang
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('REP_GROUP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('REP_GROUP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('REP_GROUP_VERSION', '1.0.0');

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