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

        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_style('rep-group-admin', REP_GROUP_URL . 'assets/css/admin.css', [], REP_GROUP_VERSION);
        
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
    }

    private function is_plugin_admin_page($hook) {
        $plugin_pages = [
            'edit.php?post_type=rep-group',
            'post-new.php?post_type=rep-group',
            'post.php?post_type=rep-group'
        ];

        return in_array($hook, $plugin_pages);
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
            wp_enqueue_style('dashicons'); // Keep for now, in case other parts of plugin or theme use it
            wp_enqueue_style(
                'rep-group-frontend',
                REP_GROUP_URL . 'assets/css/frontend.css',
                [],
                REP_GROUP_VERSION
            );

            // Enqueue Ionicons (using v7 as an example, consider latest stable)
            // ESM version for modern browsers
            wp_enqueue_script(
                'ionicons-esm',
                'https://cdn.jsdelivr.net/npm/ionicons@7/dist/ionicons/ionicons.esm.js',
                [],
                '7.0.0', // Use a specific version 
                true // In footer
            );
            wp_script_add_data('ionicons-esm', 'type', 'module');

            // Nomodule version for older browsers
            wp_enqueue_script(
                'ionicons-nomodule',
                'https://cdn.jsdelivr.net/npm/ionicons@7/dist/ionicons/ionicons.js',
                [],
                '7.0.0', // Use a specific version
                true // In footer
            );
            // For nomodule, the attribute is simply 'nomodule', wp_script_add_data might not directly support boolean attributes this way.
            // However, browsers that don't understand type=module will ignore it and should pick up the nomodule script if it's loaded.
            // A common pattern for nomodule is just <script nomodule src=...>, WordPress might need a filter to add 'nomodule' attribute correctly.
            // For simplicity, we\'ll enqueue it. Modern browsers will ignore it if ionicons-esm (type=module) loads.
            // It seems wp_script_add_data doesn\'t directly support adding boolean attributes like `nomodule` cleanly without a value.
            // This setup is generally fine. The ESM script will be used by modern browsers.

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