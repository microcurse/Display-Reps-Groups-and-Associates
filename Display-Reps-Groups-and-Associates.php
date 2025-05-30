<?php
/**
 * Plugin Name: Display Rep Groups and Associates
 * Description: A plugin to display Rep Group information.
 * Version: 2.0.1
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
 define('REP_GROUP_VERSION', '2.0.1');
 define('REP_GROUP_PATH', plugin_dir_path(__FILE__));
 define('REP_GROUP_URL', plugin_dir_url(__FILE__));
 define('REP_GROUP_DEFAULT_REGION_COLOR', '#CCCCCC');
 
 // ACF JSON Save and Load points - using Asset_Manager static methods
 add_filter('acf/settings/save_json', ['RepGroup\\Asset_Manager', 'acf_json_save_point']);
 add_filter('acf/settings/load_json', ['RepGroup\\Asset_Manager', 'acf_json_load_point']);
 
 /**
  * Validate that an "Area Served" term is not already assigned to another Rep Group.
  *
  * @param mixed $valid Whether the value is valid (boolean) or a custom error message (string).
  * @param mixed $value The value of the field.
  * @param array $field The ACF field array.
  * @param string $input_name The input name of the field (e.g., acf[field_xxxxxxxxxxxxx]).
  * @return mixed True if valid, or a string error message if invalid.
  */
 function validate_unique_area_served_assignment($valid, $value, $field, $input_name) {
     // If the value is already marked invalid by another validation, or if it's empty, don't proceed.
     if (!$valid || empty($value)) {
         return $valid;
     }

     // Ensure this validation runs only for the 'rep-group' post type editor screen.
     // Check if global $post is set and is a WP_Post object
     global $post;
     if (!isset($post) || !is_a($post, 'WP_Post') || $post->post_type !== 'rep-group') {
         // If $post is not available (e.g. during some AJAX operations or REST API calls without post context),
         // try to get post_id from the input name if possible, or from $_POST.
         // This part might need adjustment based on where ACF validation runs.
         $current_post_id = 0;
         if (isset($_POST['post_ID'])) {
             $current_post_id = absint($_POST['post_ID']);
         } elseif (preg_match('/acf\\\\[(post_id|post_ID)\\\]/', $input_name, $matches)) {
             // Fallback for some contexts, less reliable
             // This regex is hypothetical and may not work for all ACF input name structures
             // A more robust way is to check the screen context if possible.
             // For now, if $post global isn't set, we might skip stricter context check or rely on field name.
         }
         // If we are on a rep-group edit page, $post should be set.
         // If creating a new post, $post_id might be 0 or not set yet in this hook.
     } else {
         $current_post_id = $post->ID;
     }

     $term_ids_being_assigned = (array) $value; 

     foreach ($term_ids_being_assigned as $term_id) {
         $term_id = absint($term_id);
         if (!$term_id) continue;

         $term = get_term($term_id, 'area-served');
         if (!$term || is_wp_error($term)) {
             continue; 
         }

         $args = [
             'post_type' => 'rep-group',
             'posts_per_page' => 1, 
             'post_status' => 'publish', 
             'fields' => 'ids', 
             'tax_query' => [
                 [
                     'taxonomy' => 'area-served',
                     'field'    => 'term_id',
                     'terms'    => $term_id,
                 ],
             ],
         ];

         if ($current_post_id > 0) {
             $args['post__not_in'] = [$current_post_id]; // Exclude the current post being saved
         }

         $conflicting_posts = get_posts($args);

         if (!empty($conflicting_posts)) {
             $conflicting_post_id = $conflicting_posts[0];
             $conflicting_post_title = get_the_title($conflicting_post_id);
             return sprintf(
                 __('Error: The area "%1$s" is already assigned to another Rep Group (%2$s - ID: %3$s). Each area can only be assigned to one Rep Group.', 'rep-group'),
                 esc_html($term->name),
                 esc_html($conflicting_post_title),
                 esc_html($conflicting_post_id)
             );
         }
     }

     return $valid; // If no conflicts, the value is valid
 }
 // The field name for the taxonomy field on Rep Group CPT is 'area-served'
 add_filter('acf/validate_value/name=area-served', 'RepGroup\\validate_unique_area_served_assignment', 10, 4);
 
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
     new Map_Settings(); 
     new Shortcode(); 
     new Asset_Manager(); 
     new Post_Type();
     new Import_Export();
     new Taxonomy_Manager();
 }
 
 add_action('plugins_loaded', 'RepGroup\\init_rep_group_plugin');