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
            update_term_meta($term_id, '_rep_svg_target_id', sanitize_text_field($_POST['_rep_svg_target_id']));
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
} 