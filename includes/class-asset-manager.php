<?php
namespace RepGroup;

class Asset_Manager {
    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    public function enqueue_admin_assets($hook) {
        // Only load on our plugin's admin pages
        if (!$this->is_plugin_admin_page($hook)) {
            return;
        }

        wp_enqueue_style('rep-group-admin', REP_GROUP_URL . 'assets/css/admin.css', [], REP_GROUP_VERSION);

        // Get current screen
        $screen = get_current_screen();

        // Enqueue scripts only on specific admin pages
        // Example: For the Rep Group CPT edit screen or your plugin's settings page
        if ($screen) {
            if ($screen->post_type === 'rep-group' || strpos($screen->id, 'rep-group-map-settings') !== false) {
                // Enqueue scripts specific to your plugin's admin pages
                // wp_enqueue_script('my-plugin-admin-script', REP_GROUP_URL . 'assets/js/admin.js', ['jquery'], REP_GROUP_VERSION, true);
            }

            // Enqueue the new taxonomy quick edit script only on the 'area-served' term list table page
            if ($screen->base === 'edit-tags' && $screen->taxonomy === 'area-served') {
                wp_enqueue_script(
                    'rep-group-admin-taxonomy-quick-edit',
                    REP_GROUP_URL . 'assets/js/admin-taxonomy-quick-edit.js',
                    ['jquery', 'inline-edit-tax'], // Dependencies: jQuery and WordPress's inline-edit-tax script
                    REP_GROUP_VERSION,
                    true // Load in footer
                );
            }
        }

        // Enqueue color picker for taxonomy edit screens if needed
        if ( (isset($_GET['taxonomy']) && $_GET['taxonomy'] == 'area-served') || 
             (isset($_GET['post_type']) && $_GET['post_type'] == 'rep-group') || 
             (strpos($hook, 'rep-group-map-settings') !== false) ) {
            // WordPress Color Picker
            // wp_enqueue_style( 'wp-color-picker' ); // Already enqueued by ACF Pro if it's an ACF color field.
            // wp_enqueue_script( 'wp-color-picker-alpha', REP_GROUP_URL . 'assets/js/wp-color-picker-alpha.min.js', ['wp-color-picker'], REP_GROUP_VERSION, true );
        }
    }

    private function is_plugin_admin_page($hook_suffix) { // hook_suffix is $hook passed to admin_enqueue_scripts
        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }
        // Target 'rep-group' CPT list, add new, and edit screens
        if ($screen->post_type === 'rep-group') {
            if ($screen->base === 'edit' || $screen->base === 'post') {
                return true;
            }
        }
        // This function is specifically for the CPT screens. Other admin pages (Map Settings, Map Linker)
        // enqueue their own assets. Asset_Manager is for general admin assets for the CPT screens.
        return false;
    }

    public function enqueue_frontend_assets() {
        global $post;
        
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $has_rep_group_display_shortcode = has_shortcode($post->post_content, 'rep_group_display');
        $has_rep_map_shortcode = has_shortcode($post->post_content, 'rep_map');
        
        $should_enqueue_assets = $has_rep_group_display_shortcode || 
                                 $has_rep_map_shortcode || 
                                 is_singular('rep-group') || 
                                 is_post_type_archive('rep-group');

        if ($should_enqueue_assets) {
            wp_enqueue_style(
                'rep-group-frontend',
                REP_GROUP_URL . 'assets/css/frontend.css',
                [],
                REP_GROUP_VERSION
            );

            wp_enqueue_script(
                'ionicons', // Changed handle to just 'ionicons'
                'https://cdn.jsdelivr.net/npm/ionicons@7/dist/ionicons/ionicons.js',
                [],
                '7.0.0', // Use a specific version 
                true // In footer
            );

            $general_plugin_data = [];
            $all_terms = get_terms(['taxonomy' => 'area-served', 'hide_empty' => false]);
            $term_links = [];
            if (!is_wp_error($all_terms)) {
                foreach ($all_terms as $term) {
                    $link = get_term_link($term);
                    if (!is_wp_error($link)) {
                        $term_links[$term->term_id] = $link;
                    }
                }
            }
            $general_plugin_data['term_links'] = $term_links;
            $general_plugin_data['site_url'] = site_url();

            if (!wp_script_is('rep-group-frontend-base', 'enqueued')) {
                 wp_register_script('rep-group-frontend-base', false, [], REP_GROUP_VERSION, true);
                 wp_enqueue_script('rep-group-frontend-base');
            }
            wp_localize_script('rep-group-frontend-base', 'RepGroupData', $general_plugin_data);
        }
    }
} 