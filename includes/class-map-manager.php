<?php
namespace RepGroup;

class Map_Manager {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_map_manager_page']);
        add_action('wp_ajax_save_state_rep_groups', [$this, 'save_state_rep_groups']);
        add_action('wp_ajax_get_state_rep_groups_admin', [$this, 'get_state_rep_groups_admin']);
        add_action('wp_ajax_get_all_state_assignments', [$this, 'get_all_state_assignments']);
        add_action('wp_ajax_update_map_svg', [$this, 'update_map_svg']);
        add_action('wp_ajax_remove_map_svg', [$this, 'remove_map_svg']);
    }

    public function add_map_manager_page() {
        add_submenu_page(
            'edit.php?post_type=rep-group',
            'Rep Group Map Manager',
            'Map Manager',
            'manage_options',
            'rep-group-map-manager',
            [$this, 'render_map_manager_page']
        );
    }

    public function render_map_manager_page() {
        $map_svg_id = get_option('rep_group_map_svg_id');
        require_once REP_GROUP_PATH . 'templates/admin/map-manager.php';
    }

    public function save_state_rep_groups() {
        check_ajax_referer('rep_groups_nonce', 'nonce');
        
        $state_id = sanitize_text_field($_POST['state']);
        $rep_groups = json_decode(stripslashes($_POST['rep_groups']));
        
        // Get the term based on the SVG ID
        $term = get_terms([
            'taxonomy' => 'area-served',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => 'svg_id',
                    'value' => $state_id,
                    'compare' => '='
                ]
            ]
        ]);
        
        // If term doesn't exist, create it with proper name
        if (empty($term)) {
            // Get state name from a mapping function or array
            $state_name = $this->get_state_name_from_id($state_id);
            $term = wp_insert_term($state_name, 'area-served');
            if (!is_wp_error($term)) {
                update_term_meta($term['term_id'], 'svg_id', $state_id);
            }
        } else {
            $term = $term[0];
        }
        
        if (is_wp_error($term)) {
            wp_send_json_error(['message' => 'Failed to create/get term']);
            return;
        }
        
        $term_id = is_object($term) ? $term->term_id : $term['term_id'];
        
        // Remove existing assignments for this state
        $existing_posts = get_posts([
            'post_type' => 'rep-group',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => 'area-served',
                    'field' => 'term_id',
                    'terms' => $term_id
                ]
            ]
        ]);
        
        foreach ($existing_posts as $post) {
            wp_remove_object_terms($post->ID, $term_id, 'area-served');
        }
        
        // Add new assignments
        if (!empty($rep_groups)) {
            foreach ($rep_groups as $group_id) {
                wp_set_object_terms($group_id, $term_id, 'area-served', true);
            }
        }
        
        wp_send_json_success(['message' => 'Assignments saved successfully']);
    }

    private function get_state_name_from_id($state_id) {
        // Add a mapping of SVG IDs to proper state names
        $state_names = [
            'US-AL' => 'Alabama',
            'US-AK' => 'Alaska',
            'US-AZ' => 'Arizona',
            // ... add all states ...
        ];
        
        return isset($state_names[$state_id]) ? $state_names[$state_id] : $state_id;
    }

    public function get_state_rep_groups_admin() {
        check_ajax_referer('rep_groups_nonce', 'nonce');
        
        $state_id = sanitize_text_field($_POST['state']);
        
        // Get term by SVG ID
        $terms = get_terms([
            'taxonomy' => 'area-served',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => 'svg_id',
                    'value' => $state_id,
                    'compare' => '='
                ]
            ]
        ]);
        
        if (empty($terms)) {
            wp_send_json_success([
                'rep_groups' => [],
                'state_name' => $this->get_state_name_from_id($state_id)
            ]);
            return;
        }
        
        $term = $terms[0];
        
        $rep_groups = get_posts([
            'post_type' => 'rep-group',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => 'area-served',
                    'field' => 'term_id',
                    'terms' => $term->term_id
                ]
            ]
        ]);
        
        wp_send_json_success([
            'rep_groups' => wp_list_pluck($rep_groups, 'ID'),
            'state_name' => $term->name
        ]);
    }

    public function get_all_state_assignments() {
        check_ajax_referer('rep_groups_nonce', 'nonce');
        
        $terms = get_terms([
            'taxonomy' => 'area-served',
            'hide_empty' => true
        ]);
        
        $assignments = [];
        foreach ($terms as $term) {
            $assignments[$term->slug] = true;
        }
        
        wp_send_json_success(['assignments' => $assignments]);
    }

    public function update_map_svg() {
        check_ajax_referer('rep_groups_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        
        if ($attachment_id) {
            update_option('rep_group_map_svg_id', $attachment_id);
            wp_send_json_success(['message' => 'Map updated successfully']);
        }
        
        wp_send_json_error('Invalid attachment ID');
    }

    public function remove_map_svg() {
        check_ajax_referer('rep_groups_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        delete_option('rep_group_map_svg_id');
        wp_send_json_success(['message' => 'Map removed successfully']);
    }
} 