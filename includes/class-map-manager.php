<?php
namespace RepGroup;

class Map_Manager {
    public function __construct() {
        add_action('init', array($this, 'register_map_region_post_type'));
        add_action('add_meta_boxes', array($this, 'add_region_meta_boxes'));
        add_action('save_post', array($this, 'save_region_meta'));
        add_shortcode('rep_group_map', array($this, 'render_map'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_map_scripts'));
    }

    public function register_map_region_post_type(): void {
        register_post_type('rep_map_region', [
            'labels' => [
                'name' => 'Map Regions',
                'singular_name' => 'Map Region',
            ],
            'public' => true,
            'show_in_menu' => 'edit.php?post_type=rep_group',
            'supports' => ['title'],
        ]);
    }

    public function add_region_meta_boxes(): void {
        add_meta_box(
            'region_details',
            'Region Details',
            array($this, 'render_region_meta_box'),
            'rep_map_region'
        );
    }

    public function render_region_meta_box($post): void {
        $region_id = get_post_meta($post->ID, 'region_svg_id', true);
        $rep_group = get_post_meta($post->ID, 'region_rep_group', true);
        $map_type = get_post_meta($post->ID, 'region_map_type', true);
        wp_nonce_field('region_meta_box', 'region_meta_box_nonce');
        ?>
        <p>
            <label>SVG Region ID:</label>
            <input type="text" name="region_svg_id" value="<?php echo esc_attr($region_id); ?>">
        </p>
        <p>
            <label>Rep Group Shortcode:</label>
            <input type="text" name="region_rep_group" value="<?php echo esc_attr($rep_group); ?>">
        </p>
        <p>
            <label>Map Type:</label>
            <select name="region_map_type">
                <option value="local" <?php selected($map_type, 'local'); ?>>Local</option>
                <option value="international" <?php selected($map_type, 'international'); ?>>International</option>
            </select>
        </p>
        <?php
    }

    public function save_region_meta($post_id): void {
        if (!isset($_POST['region_meta_box_nonce'])) return;
        if (!wp_verify_nonce($_POST['region_meta_box_nonce'], 'region_meta_box')) return;
        if (defined(constant_name: 'DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        if (isset($_POST['region_svg_id'])) {
            update_post_meta($post_id, 'region_svg_id', sanitize_text_field($_POST['region_svg_id']));
        }
        if (isset($_POST['region_rep_group'])) {
            update_post_meta($post_id, 'region_rep_group', sanitize_text_field($_POST['region_rep_group']));
        }
        if (isset($_POST['region_map_type'])) {
            update_post_meta($post_id, 'region_map_type', sanitize_text_field($_POST['region_map_type']));
        }
    }

    public function render_map($atts): string {
        $atts = shortcode_atts([
            'type' => 'local' // default to local map
        ], $atts);

        // Load the appropriate SVG map file based on type
        $map_filename = $atts['type'] === 'international' ? 'world-map.svg' : 'usa-map.svg';
        $svg_path = REP_GROUP_PLUGIN_PATH . 'assets/' . $map_filename;
        
        if (!file_exists($svg_path)) {
            return 'Map file not found: ' . $map_filename;
        }

        $svg_content = file_get_contents($svg_path);
        
        // Get regions for the specific map type
        $regions = get_posts([
            'post_type' => 'rep_map_region',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'region_map_type',
                    'value' => $atts['type'],
                    'compare' => '='
                ]
            ]
        ]);

        $output = '<div class="rep-group-map-container" data-map-type="' . esc_attr($atts['type']) . '">';
        $output .= $svg_content;
        $output .= '<div class="rep-group-info"></div>';
        $output .= '</div>';

        return $output;
    }

    public function enqueue_map_scripts(): void {
        wp_enqueue_style(
            'rep-group-map',
            REP_GROUP_PLUGIN_URL . 'assets/css/map.css',
            [],
            REP_GROUP_VERSION
        );

        wp_enqueue_script(
            'rep-group-map',
            REP_GROUP_PLUGIN_URL . 'assets/js/map.js',
            ['jquery'],
            REP_GROUP_VERSION,
            true
        );

        wp_localize_script('rep-group-map', 'repGroupMap', [
            'ajaxurl' => admin_url('admin-ajax.php'),
        ]);
    }
} 