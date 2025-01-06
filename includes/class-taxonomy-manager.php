<?php
namespace RepGroup;

class Taxonomy_Manager {
    public function __construct() {
        add_action('area-served_add_form_fields', [$this, 'add_svg_id_field']);
        add_action('area-served_edit_form_fields', [$this, 'edit_svg_id_field']);
        add_action('created_area-served', [$this, 'save_svg_id_field']);
        add_action('edited_area-served', [$this, 'save_svg_id_field']);
    }

    /**
     * Add SVG ID field to the add term form
     */
    public function add_svg_id_field() {
        ?>
        <div class="form-field">
            <label for="svg_id">SVG ID</label>
            <input type="text" name="svg_id" id="svg_id" value="">
            <p class="description">Enter the SVG ID for this area (e.g., US-CA for California)</p>
        </div>
        <?php
    }

    /**
     * Add SVG ID field to the edit term form
     */
    public function edit_svg_id_field($term) {
        $svg_id = get_term_meta($term->term_id, 'svg_id', true);
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="svg_id">SVG ID</label>
            </th>
            <td>
                <input type="text" name="svg_id" id="svg_id" value="<?php echo esc_attr($svg_id); ?>">
                <p class="description">Enter the SVG ID for this area (e.g., US-CA for California)</p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save the SVG ID field
     */
    public function save_svg_id_field($term_id) {
        if (isset($_POST['svg_id'])) {
            $svg_id = sanitize_text_field($_POST['svg_id']);
            update_term_meta($term_id, 'svg_id', $svg_id);
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
                    'key' => 'svg_id',
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
            $svg_id = get_term_meta($term->term_id, 'svg_id', true);
            if ($svg_id) {
                $terms_with_svg[$svg_id] = [
                    'term_id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug
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