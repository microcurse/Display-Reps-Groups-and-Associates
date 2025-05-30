<?php
namespace RepGroup;

class Map_Settings {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function enqueue_admin_scripts($hook) {
        // Check if we are on the plugin's settings page
        if ('rep-group_page_rep-group-map-settings' !== $hook) {
            return;
        }
        wp_enqueue_media(); // Required for the media uploader
        wp_enqueue_script(
            'rep-group-admin-map-settings',
            REP_GROUP_URL . 'assets/js/admin-map-settings.js',
            ['jquery'],
            REP_GROUP_VERSION,
            true
        );

        // Enqueue admin.js for the copy shortcode functionality
        wp_enqueue_script(
            'rep-group-admin-js-copier', // Use a distinct handle if admin.js is enqueued elsewhere
            REP_GROUP_URL . 'assets/js/admin.js',
            [], // admin.js appears to be vanilla JS, no specific dependencies like jQuery listed for its core function
            REP_GROUP_VERSION,
            true
        );

        // Enqueue admin.css for the shortcode display styles
        wp_enqueue_style(
            'rep-group-admin-css', // Use a distinct handle
            REP_GROUP_URL . 'assets/css/admin.css',
            [],
            REP_GROUP_VERSION
        );
    }

    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=rep-group', // Parent slug: Rep Groups CPT
            'Map Settings',                  // Page title
            'Map Settings',                  // Menu title
            'manage_options',                // Capability
            'rep-group-map-settings',        // Menu slug
            [$this, 'render_settings_page']   // Callback function
        );
    }

    public function register_settings() {
        register_setting('rep_group_map_options', 'rep_group_local_svg', ['sanitize_callback' => 'esc_url_raw']);
        register_setting('rep_group_map_options', 'rep_group_international_svg', ['sanitize_callback' => 'esc_url_raw']);

        add_settings_section(
            'rep_group_map_section',
            'SVG Map Files',
            null,
            'rep-group-map-settings'
        );

        add_settings_field(
            'rep_group_local_svg',
            'Local Rep Map SVG URL',
            [$this, 'render_local_svg_field'],
            'rep-group-map-settings',
            'rep_group_map_section'
        );

        add_settings_field(
            'rep_group_international_svg',
            'International Rep Map SVG URL',
            [$this, 'render_international_svg_field'],
            'rep-group-map-settings',
            'rep_group_map_section'
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']) : ?>
                <div id="message" class="updated notice is-dismissible">
                    <p><strong><?php _e('Settings saved.', 'rep-group'); ?></strong></p>
                </div>
            <?php endif; ?>
            <h1>Map Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('rep_group_map_options');
                do_settings_sections('rep-group-map-settings');
                submit_button();
                ?>
            </form>

        </div>
        <?php
    }

    public function render_local_svg_field() {
        $option = get_option('rep_group_local_svg');
        ?>
        <input type="text" id="rep_group_local_svg" name="rep_group_local_svg" value="<?php echo esc_attr($option); ?>" class="regular-text">
        <input type="button" id="upload_local_svg_button" class="button" value="Upload SVG">
        <p class="description">Upload or enter the URL for the Local Rep Map SVG.</p>
        <p class="description">Shortcode: 
            <span class="rep-group-shortcode" title="Click to copy shortcode" style="display: inline-block; padding: 2px 5px; background-color: #f0f0f1; border: 1px solid #dcdcde; border-radius: 3px; cursor: pointer;">
                [rep_map type="local"]
            </span>
            <span class="shortcode-copied" style="display: none; margin-left: 5px; color: green;">Copied!</span>
        </p>
        <?php
    }

    public function render_international_svg_field() {
        $option = get_option('rep_group_international_svg');
        ?>
        <input type="text" id="rep_group_international_svg" name="rep_group_international_svg" value="<?php echo esc_attr($option); ?>" class="regular-text">
        <input type="button" id="upload_international_svg_button" class="button" value="Upload SVG">
        <p class="description">Upload or enter the URL for the International Rep Map SVG.</p>
        <p class="description">Shortcode: 
            <span class="rep-group-shortcode" title="Click to copy shortcode" style="display: inline-block; padding: 2px 5px; background-color: #f0f0f1; border: 1px solid #dcdcde; border-radius: 3px; cursor: pointer;">
                [rep_map type="international"]
            </span>
            <span class="shortcode-copied" style="display: none; margin-left: 5px; color: green;">Copied!</span>
        </p>
        <?php
    }
}
?>

