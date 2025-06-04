<?php
namespace RepGroup;

class Taxonomy_Manager {
    public function __construct() {
        add_action('init', [$this, 'register_taxonomies']);
        add_action('area-served_add_form_fields', [$this, 'add_custom_fields']);
        add_action('area-served_edit_form_fields', [$this, 'edit_custom_fields']);
        add_action('created_area-served', [$this, 'save_custom_fields']);
        add_action('edited_area-served', [$this, 'save_custom_fields']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_color_picker']);

        // Add column to term list table
        add_filter('manage_edit-area-served_columns', [$this, 'add_svg_id_column_header']);
        add_action('manage_area-served_custom_column', [$this, 'render_svg_id_column_content'], 10, 3);

        // Add to Quick Edit
        add_action('quick_edit_custom_box', [$this, 'add_svg_id_to_quick_edit_form'], 10, 3); // Changed from 2 to 3 for WP 5.5+
        // Use 'save_term' action as it fires for both create and edit, including quick edit.
        add_action('save_term', [$this, 'save_quick_edit_svg_id'], 10, 3);
    }

    public function register_taxonomies() {
        register_taxonomy('area-served', 'rep-group', [
            'labels' => [
                'name' => 'Areas Served',
                'singular_name' => 'Area Served',
                'menu_name' => 'Areas Served',
                'all_items' => 'All Areas',
                'edit_item' => 'Edit Area',
                'view_item' => 'View Area',
                'update_item' => 'Update Area',
                'add_new_item' => 'Add New Area',
                'new_item_name' => 'New Area Name',
                'search_items' => 'Search Areas',
                'popular_items' => 'Popular Areas',
                'not_found' => 'No areas found'
            ],
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => [
                'slug' => 'area',
                'hierarchical' => false
            ],
            'show_in_rest' => true,
        ]);
    }

    public function enqueue_color_picker($hook) {
        if ('edit-tags.php' === $hook || 'term.php' === $hook) {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            wp_add_inline_script('wp-color-picker', '
                jQuery(document).ready(function($){
                    $(".color-picker").wpColorPicker();
                });
            ');
        }
    }

    public function add_custom_fields() {
        ?>
        <div class="form-field">
            <label for="area_color">Area Color</label>
            <input type="text" name="area_color" id="area_color" class="color-picker" value="#2271b1">
            <p class="description">Choose a color for this area on the map.</p>
        </div>
        <div class="form-field">
            <label for="_rep_svg_target_id">SVG ID/Class</label>
            <input type="text" name="_rep_svg_target_id" id="_rep_svg_target_id" value="">
            <p class="description">Enter the SVG ID or class selector for this area (e.g., #US-CA or .california). This is used to link the area to the map.</p>
        </div>
        <?php
    }

    public function edit_custom_fields($term) {
        $color = get_term_meta($term->term_id, 'area_color', true) ?: '#2271b1';
        $_rep_svg_target_id = get_term_meta($term->term_id, '_rep_svg_target_id', true);
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="area_color">Area Color</label>
            </th>
            <td>
                <input type="text" name="area_color" id="area_color" class="color-picker" value="<?php echo esc_attr($color); ?>">
                <p class="description">Choose a color for this area on the map.</p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row">
                <label for="_rep_svg_target_id">SVG ID/Class</label>
            </th>
            <td>
                <input type="text" name="_rep_svg_target_id" id="_rep_svg_target_id" value="<?php echo esc_attr($_rep_svg_target_id); ?>">
                <p class="description">Enter the SVG ID or class selector for this area (e.g., #US-CA or .california). This is used to link the area to the map.</p>
                <p class="description">Current term slug (can be used as SVG ID if appropriate): <?php echo esc_html($term->slug); ?></p>
            </td>
        </tr>
        <?php
    }

    public function save_custom_fields($term_id) {
        if (isset($_POST['_rep_svg_target_id'])) {
            $svg_id_value = sanitize_text_field($_POST['_rep_svg_target_id']);
            // Remove leading # before saving to keep data clean
            $cleaned_svg_id = ltrim($svg_id_value, '#');
            update_term_meta($term_id, '_rep_svg_target_id', $cleaned_svg_id);
        }
        if (isset($_POST['area_color'])) {
            update_term_meta($term_id, 'area_color', sanitize_hex_color($_POST['area_color']));
        }
    }

    /**
     * Get term by SVG ID
     */
    public function get_term_by_svg_id($svg_id) {
        $terms = get_terms([
            'taxonomy' => 'area-served',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => '_rep_svg_target_id',
                    'value' => $svg_id,
                    'compare' => '='
                ]
            ]
        ]);

        return !empty($terms) ? $terms[0] : null;
    }

    /**
     * Get all terms with their SVG IDs
     */
    public function get_all_terms_with_svg_ids() {
        $terms = get_terms([
            'taxonomy' => 'area-served',
            'hide_empty' => false
        ]);

        $terms_with_svg = [];
        foreach ($terms as $term) {
            $svg_id = get_term_meta($term->term_id, '_rep_svg_target_id', true);
            $color = get_term_meta($term->term_id, 'area_color', true); // Get the color
            if ($svg_id) {
                $terms_with_svg[$svg_id] = [
                    'term_id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'color' => $color ?: null // Add color here
                ];
            }
        }
        return $terms_with_svg;
    }

    /**
     * Get rep groups by area served
     */
    public function get_rep_groups_by_area($term_id) {
        return get_posts([
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
    }

    /**
     * Get areas served by rep group
     */
    public function get_areas_served_by_rep_group($post_id) {
        return wp_get_object_terms($post_id, 'area-served');
    }

    /**
     * Adds the SVG ID/Class column header to the terms list table.
     */
    public function add_svg_id_column_header($columns) {
        // Ensure 'slug' column exists before trying to insert after it
        if (!isset($columns['slug'])) {
            $columns['_rep_svg_target_id'] = __('SVG ID/Class', 'rep-group');
            return $columns;
        }

        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'slug') {
                $new_columns['_rep_svg_target_id'] = __('SVG ID/Class', 'rep-group');
            }
        }
        return $new_columns;
    }

    /**
     * Renders the content for the SVG ID/Class column.
     */
    public function render_svg_id_column_content($content, $column_name, $term_id) {
        if ($column_name === '_rep_svg_target_id') {
            $svg_id = get_term_meta($term_id, '_rep_svg_target_id', true);
            $content = esc_html($svg_id);
            // Add hidden span for Quick Edit JavaScript
            $content .= sprintf('<span class="hidden-svg-id" style="display:none;">%s</span>', esc_attr($svg_id));
        }
        return $content;
    }

    /**
     * Adds the SVG ID/Class field to the Quick Edit form.
     */
    public function add_svg_id_to_quick_edit_form($column_name, $screen, $taxonomy_slug = '') {
        // $screen is 'edit-tags' for term list tables, $taxonomy_slug is available in WP 5.5+
        // For older WP, $screen might be the taxonomy slug itself when on edit-tags.php
        // We'll check $column_name and explicitly check $taxonomy_slug if available, or infer from $screen if not.
        
        $current_taxonomy = '';
        if (!empty($taxonomy_slug)) {
            $current_taxonomy = $taxonomy_slug;
        } elseif (is_object($screen) && isset($screen->taxonomy)) { // $screen can be WP_Screen object
            $current_taxonomy = $screen->taxonomy;
        }
        
        if ($column_name !== '_rep_svg_target_id' || $current_taxonomy !== 'area-served') {
            return;
        }
        
        wp_nonce_field('rep_group_save_svg_id_quick_edit', '_rep_svg_id_quick_edit_nonce', false); // false for not echoing
        ?>
        <fieldset>
            <div class="inline-edit-col">
                <label>
                    <span class="title"><?php _e('SVG ID/Class', 'rep-group'); ?></span>
                    <span class="input-text-wrap">
                        <input type="text" name="_rep_svg_target_id" class="ptitle" value="" />
                    </span>
                </label>
            </div>
        </fieldset>
        <?php
    }

    /**
     * Saves the SVG ID/Class from Quick Edit.
     */
    public function save_quick_edit_svg_id($term_id, $tt_id, $taxonomy) {
        if ($taxonomy !== 'area-served') {
            return;
        }

        // Check if our quick edit nonce was submitted (it might not be if other quick edit actions are happening)
        if (!isset($_POST['_rep_svg_id_quick_edit_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['_rep_svg_id_quick_edit_nonce'], 'rep_group_save_svg_id_quick_edit')) {
            // Potentially log this or add an admin notice for debugging if nonce fails
            return;
        }

        if (!current_user_can('edit_term', $term_id)) {
            return;
        }

        if (isset($_POST['_rep_svg_target_id'])) {
            $svg_id_value = sanitize_text_field($_POST['_rep_svg_target_id']);
            $cleaned_svg_id = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $svg_id_value);
            update_term_meta($term_id, '_rep_svg_target_id', $cleaned_svg_id);
        }
    }
} 