<?php
namespace RepGroup;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Map_Editor_Page {

    private $option_names = [
        'local' => 'rep_group_local_map_links',
        'international' => 'rep_group_international_map_links'
    ];
    const DEFAULT_REGION_COLOR = '#CCCCCC'; // Default color for regions

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'wp_ajax_get_rep_map_links', [ $this, 'ajax_get_rep_map_links' ] );
        add_action( 'wp_ajax_save_rep_map_link', [ $this, 'ajax_save_rep_map_link' ] );
        add_action( 'wp_ajax_delete_rep_map_link', [ $this, 'ajax_delete_rep_map_link' ] );
        add_action( 'wp_ajax_get_rep_group_info_for_area', [ $this, 'ajax_get_rep_group_info_for_area' ] );
        add_action( 'wp_ajax_nopriv_get_rep_group_info_for_area', [ $this, 'ajax_get_rep_group_info_for_area' ] );
        // New AJAX actions for getting rep group by ID
        add_action( 'wp_ajax_get_rep_group_details_by_id', [ $this, 'ajax_get_rep_group_details_by_id' ] );
        add_action( 'wp_ajax_nopriv_get_rep_group_details_by_id', [ $this, 'ajax_get_rep_group_details_by_id' ] );
    }

    private function get_formatted_address_html($address_fields, $prefix = '') {
        $output = '';
        if (empty($address_fields)) return $output;

        $address_1 = isset($address_fields[$prefix . 'address_1']) ? $address_fields[$prefix . 'address_1'] : '';
        $address_2 = isset($address_fields[$prefix . 'address_2']) ? $address_fields[$prefix . 'address_2'] : '';
        $city      = isset($address_fields[$prefix . 'city']) ? $address_fields[$prefix . 'city'] : '';
        $state     = isset($address_fields[$prefix . 'state']) ? $address_fields[$prefix . 'state'] : '';
        $zip_code  = isset($address_fields[$prefix . 'zip_code']) ? $address_fields[$prefix . 'zip_code'] : '';

        if ($address_1 || $city || $state || $zip_code) { // Only output if there's something to show
            $output .= '<div class="address-details">';
            if ($address_1) {
                $output .= '<p class="address-line">' . esc_html($address_1) . '</p>';
            }
            if ($address_2) {
                $output .= '<p class="address-line">' . esc_html($address_2) . '</p>';
            }
            $city_state_zip = [];
            if ($city) $city_state_zip[] = esc_html($city);
            if ($state) $city_state_zip[] = esc_html($state);
            if ($zip_code) $city_state_zip[] = esc_html($zip_code);
            
            if (!empty($city_state_zip)) {
                 $output .= '<p class="address-line">' . implode(', ', array_filter([esc_html($city), esc_html($state)], 'strlen')) . ($zip_code ? ' ' . esc_html($zip_code) : '') . '</p>';
            }
            $output .= '</div>';
        }
        return $output;
    }

    private function get_formatted_phone_numbers_html($phone_repeater_field_value, $is_sub_field = false) {
        $output = '';
        if (empty($phone_repeater_field_value)) return $output;
        
        // If it's from have_rows context, $phone_repeater_field_value is not needed as argument.
        // This helper assumes the array of rows is passed directly.

        $phones_html_list = '';
        foreach ($phone_repeater_field_value as $row) {
            $phone_type_field = $is_sub_field ? $row['rep_phone_type'] : $row['rg_phone_type']; // ACF field names differ
            $phone_number     = $is_sub_field ? $row['rep_phone_number'] : $row['rg_phone_number'];
            $phone_type_label = is_array($phone_type_field) ? $phone_type_field['label'] : $phone_type_field;

            if ($phone_number) {
                $phones_html_list .= '<p class="contact-item phone"><ion-icon name="call" aria-hidden="true"></ion-icon> ';
                if ($phone_type_label) {
                    $phones_html_list .= '<strong>' . esc_html($phone_type_label) . ':</strong> ';
                }
                $clean_phone_number = preg_replace('/[^\\d+]/ ', '', $phone_number);
                $phones_html_list .= '<a href="tel:' . esc_attr($clean_phone_number) . '">' . esc_html($phone_number) . '</a></p>';
            }
        }
        if ($phones_html_list) {
            $output = '<div class="phone-numbers-list">' . $phones_html_list . '</div>';
        }
        return $output;
    }
    
    // Specific helper for sub_field repeater for associates
    private function get_formatted_associate_phones_html() {
        $output = '';
        $phones_html_list = '';
        if (have_rows('rep_phone_numbers')) { // This is the sub-field repeater for associates
            while (have_rows('rep_phone_numbers')) {
                the_row();
                $phone_type_field = get_sub_field('rep_phone_type');
                $phone_number = get_sub_field('rep_phone_number');
                $phone_type_label = is_array($phone_type_field) ? $phone_type_field['label'] : $phone_type_field;

                if ($phone_number) {
                    $phones_html_list .= '<p class="contact-item phone"><ion-icon name="call" aria-hidden="true"></ion-icon> ';
                    if ($phone_type_label) {
                        $phones_html_list .= '<strong>' . esc_html($phone_type_label) . ':</strong> ';
                    }
                    $clean_phone_number = preg_replace('/[^\\d+]/ ', '', $phone_number);
                    $phones_html_list .= '<a href="tel:' . esc_attr($clean_phone_number) . '">' . esc_html($phone_number) . '</a></p>';
                }
            }
        }
        if ($phones_html_list) {
            $output = '<div class="phone-numbers-list associate-phones">' . $phones_html_list . '</div>';
        }
        return $output;
    }


    private function get_formatted_email_html($email_address) {
        $output = '';
        if ($email_address && is_email($email_address)) {
            $output .= '<p class="contact-item email"><ion-icon name="mail" aria-hidden="true"></ion-icon> <a href="mailto:' . esc_attr($email_address) . '">' . esc_html($email_address) . '</a></p>';
        }
        return $output;
    }

    public function enqueue_admin_scripts( $hook_suffix ) {
        // Only load on our admin page
        if ( 'rep-group_page_rep-group-map-linker' !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style(
            'rep-group-map-linker-admin-css',
            REP_GROUP_URL . 'assets/css/admin-map-linker.css',
            [ 'wp-color-picker' ], // Add wp-color-picker dependency for styles
            REP_GROUP_VERSION
        );

        wp_enqueue_script(
            'rep-group-map-linker-admin-js',
            REP_GROUP_URL . 'assets/js/admin-map-linker.js',
            [ 'jquery', 'wp-color-picker' ], // Add wp-color-picker dependency for scripts
            REP_GROUP_VERSION,
            true
        );

        // Pass data to JavaScript
        $localized_data = [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'rep_map_linker_nonce' ),
            'svg_urls' => [
                'local' => get_option( 'rep_group_local_svg' ),
                'international' => get_option( 'rep_group_international_svg' ),
            ],
            'text' => [
                'loading_map' => __( 'Loading map...', 'rep-group' ),
                'map_not_configured' => __( 'SVG Map URL for this type is not configured in Map Settings.', 'rep-group' ),
                'error_loading_svg' => __( 'Error loading SVG. Please check the console.', 'rep-group' ),
            ],
            'default_color' => self::DEFAULT_REGION_COLOR
        ];
        wp_localize_script( 'rep-group-map-linker-admin-js', 'RepMapLinkerData', $localized_data );
    }

    public function add_admin_menu_page() {
        add_submenu_page(
            'edit.php?post_type=rep-group',
            __( 'Map Linker', 'rep-group' ),
            __( 'Map Linker', 'rep-group' ),
            'manage_options',
            'rep-group-map-linker',
            [ $this, 'render_page_content' ]
        );
    }

    public function render_page_content() {
        ?>
        <div class="wrap rep-group-map-linker-wrap">
            <h1><?php _e( 'Link SVG Map Regions to Areas Served', 'rep-group' ); ?></h1>
            
            <div id="map-linker-controls">
                <div class="control-group">
                    <label for="rep-map-type-selector"><?php _e( 'Select Map:', 'rep-group' ); ?></label>
                    <select id="rep-map-type-selector">
                        <option value="local" selected><?php _e( 'Local Map', 'rep-group' ); ?></option>
                        <option value="international"><?php _e( 'International Map', 'rep-group' ); ?></option>
                    </select>
                </div>

                <div class="control-group">
                    <label for="rep-map-area-served-selector"><?php _e( 'Area Served Term:', 'rep-group' ); ?></label>
                    <select id="rep-map-area-served-selector" disabled>
                        <option value=""><?php _e( '-- Select Area Served --', 'rep-group' ); ?></option>
                        <?php
                        $terms = get_terms( [ 'taxonomy' => 'area-served', 'hide_empty' => false, 'orderby' => 'name' ] );
                        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                            foreach ( $terms as $term ) {
                                printf(
                                    '<option value="%s">%s</option>',
                                    esc_attr( $term->term_id ),
                                    esc_html( $term->name )
                                );
                            }
                        }
                        ?>
                    </select>
                </div>

                <div class="control-group">
                    <label for="rep-map-region-color"><?php _e( 'Region Color:', 'rep-group' ); ?></label>
                    <input type="text" id="rep-map-region-color" class="rep-map-color-picker" value="<?php echo esc_attr(self::DEFAULT_REGION_COLOR); ?>" disabled />
                </div>
                
                <div class="control-group actions">
                     <button id="assign-term-to-region-button" class="button button-primary" disabled><?php _e( 'Assign Term & Color to Selected Region', 'rep-group' ); ?></button>
                     <button id="remove-term-from-region-button" class="button" disabled><?php _e( 'Remove Link from Selected Region', 'rep-group' ); ?></button>
                </div>
                 <div id="rep-map-selected-region-info">
                    <?php _e( 'Selected SVG Region ID:', 'rep-group' ); ?> <strong id="selected-svg-region-id"><em><?php _e( 'None', 'rep-group' ); ?></em></strong>
                </div>
            </div>

            <div id="map-linker-main-area">
                <div id="current-mappings-display">
                    <h2><?php _e( 'Current Mappings', 'rep-group' ); ?></h2>
                    <div class="mappings-list">
                        <p><em><?php _e( 'Select a map to see current mappings.', 'rep-group' ); ?></em></p>
                    </div>
                </div>
                <div id="rep-map-svg-wrapper">
                    <p class="loading-message"><?php _e( 'Select a map type to begin.', 'rep-group' ); ?></p> 
                    <!-- SVG will be loaded here by JavaScript -->
                </div>
            </div>
            
        </div>
        <?php
    }

    public function ajax_get_rep_map_links() {
        check_ajax_referer( 'rep_map_linker_nonce', 'nonce' );
        $map_type = isset( $_POST['map_type'] ) ? sanitize_key( $_POST['map_type'] ) : 'local';
        
        if ( ! array_key_exists( $map_type, $this->option_names ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid map type.', 'rep-group' ) ] );
        }
        
        $links = get_option( $this->option_names[$map_type], [] );
        // Ensure defaults for old data structure
        foreach ($links as $svg_id => $data) {
            if (!is_array($data)) {
                $links[$svg_id] = ['term_id' => $data, 'color' => self::DEFAULT_REGION_COLOR];
            }
            if (!isset($data['color'])){
                 $links[$svg_id]['color'] = self::DEFAULT_REGION_COLOR;
            }
        }
        wp_send_json_success( $links );
    }

    public function ajax_save_rep_map_link() {
        check_ajax_referer( 'rep_map_linker_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'rep-group' ) ] );
        }

        $map_type       = isset( $_POST['map_type'] ) ? sanitize_key( $_POST['map_type'] ) : null;
        $svg_region_id  = isset( $_POST['svg_region_id'] ) ? sanitize_text_field( $_POST['svg_region_id'] ) : null;
        $term_id        = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : null;
        $color          = isset( $_POST['color'] ) ? sanitize_hex_color( $_POST['color'] ) : self::DEFAULT_REGION_COLOR;

        if ( ! $map_type || ! $svg_region_id || ! $term_id || ! array_key_exists( $map_type, $this->option_names ) ) {
            wp_send_json_error( [ 'message' => __( 'Missing or invalid data.', 'rep-group' ) ] );
        }
        if ( ! $color ) { // sanitize_hex_color returns empty for invalid
            $color = self::DEFAULT_REGION_COLOR;
        }

        $option_name = $this->option_names[$map_type];
        $links = get_option( $option_name, [] );
        $links[ $svg_region_id ] = [ // Store as an array with term_id and color
            'term_id' => $term_id,
            'color'   => $color
        ];
        update_option( $option_name, $links );

        wp_send_json_success( [ 'message' => __( 'Mapping saved.', 'rep-group' ), 'links' => $links ] );
    }

    public function ajax_delete_rep_map_link() {
        check_ajax_referer( 'rep_map_linker_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'rep-group' ) ] );
        }

        $map_type       = isset( $_POST['map_type'] ) ? sanitize_key( $_POST['map_type'] ) : null;
        $svg_region_id  = isset( $_POST['svg_region_id'] ) ? sanitize_text_field( $_POST['svg_region_id'] ) : null;

        if ( ! $map_type || ! $svg_region_id || ! array_key_exists( $map_type, $this->option_names ) ) {
            wp_send_json_error( [ 'message' => __( 'Missing or invalid data for deletion.', 'rep-group' ) ] );
        }

        $option_name = $this->option_names[$map_type];
        $links = get_option( $option_name, [] );
        if ( isset( $links[ $svg_region_id ] ) ) {
            unset( $links[ $svg_region_id ] );
            update_option( $option_name, $links );
            wp_send_json_success( [ 'message' => __( 'Mapping deleted.', 'rep-group' ), 'links' => $links ] );
        } else {
            wp_send_json_error( [ 'message' => __( 'Mapping not found for deletion.', 'rep-group' ) ] );
        }
    }

    public function ajax_get_rep_group_info_for_area() {
        check_ajax_referer( 'rep_group_frontend_map_nonce', 'nonce' );

        $term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
        $area_color = isset( $_POST['area_color'] ) ? sanitize_hex_color( $_POST['area_color'] ) : '#ccc';

        if ( ! $term_id ) {
            wp_send_json_error( [ 'message' => __( 'Area ID not provided.', 'rep-group' ) ] );
        }

        $term = get_term( $term_id, 'area-served' );
        if ( is_wp_error( $term ) || ! $term ) {
            wp_send_json_error( [ 'message' => __( 'Invalid area.', 'rep-group' ) ] );
        }

        $args = [
            'post_type' => 'rep-group',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => 'area-served',
                    'field'    => 'term_id',
                    'terms'    => $term_id,
                ],
            ],
            'orderby' => 'title',
            'order'   => 'ASC',
        ];

        $rep_groups_query = new \WP_Query( $args );
        $output_html = '';

        if ( $rep_groups_query->have_posts() ) {
            $output_html .= '<h3 style="--area-highlight-color:' . esc_attr($area_color) . ';"><span class="area-name-highlighted">' . esc_html( $term->name ) . '</span></h3>';
            
            while ( $rep_groups_query->have_posts() ) {
                $rep_groups_query->the_post();
                $rep_group_id = get_the_ID();
                $output_html .= '<div class="rep-group-item">';

                // --- Rep Group's Own Details ---
                $output_html .= '<div class="rep-group-main-details">';
                $output_html .= '<h4 class="rep-group-title">' . get_the_title() . '</h4>';

                $rg_address_data = get_field('rg_address_container', $rep_group_id);
                if ($rg_address_data) {
                    $output_html .= $this->get_formatted_address_html($rg_address_data, 'rg_');
                }
                
                $rg_phones_data = get_field('rg_phone_numbers', $rep_group_id); // Repeater field
                if ($rg_phones_data) { // This will be an array of rows
                    $output_html .= $this->get_formatted_phone_numbers_html($rg_phones_data, false); // false for not a sub_field
                }

                $rg_email_data = get_field('rg_email', $rep_group_id);
                if ($rg_email_data) {
                    $output_html .= $this->get_formatted_email_html($rg_email_data);
                }
                $output_html .= '</div>'; // end .rep-group-main-details

                // --- Rep Associates ---
                if ( have_rows( 'rep_associates', $rep_group_id ) ) {
                    $output_html .= '<h5 class="rep-associates-section-title">' . esc_html__('Rep Associates', 'rep-group') . '</h5>';
                    $output_html .= '<div class="rep-associates-list">';
                    
                    $associates_items = []; // For sorting
                    while ( have_rows( 'rep_associates', $rep_group_id ) ) {
                        the_row();
                        $associate_item_html = '<div class="rep-associate-item">';
                        $associate_name = get_sub_field('name');

                        if ($associate_name) {
                            $associate_item_html .= '<h6 class="rep-associate-name">' . esc_html($associate_name) . '</h6>';
                        }

                        // Debugging line for areas_served (can be removed after fix)
                        // error_log(\'[DEBUG] Associate: \' . $associate_name . \' - areas_served raw value: \' . print_r($assoc_areas_served_value, true));

                        $assoc_areas_served_value = get_sub_field('areas_served');
                        $area_names_to_display = [];

                        if (is_array($assoc_areas_served_value) && !empty($assoc_areas_served_value)) {
                            foreach ($assoc_areas_served_value as $term_id) {
                                $term = get_term($term_id, 'area-served'); // Specify taxonomy
                                if ($term instanceof \WP_Term && !is_wp_error($term)) {
                                    $area_names_to_display[] = esc_html($term->name);
                                }
                            }
                        } elseif ($assoc_areas_served_value instanceof \WP_Term && !empty($assoc_areas_served_value->name)) {
                            // Fallback if it somehow returns a single Term object (though debug shows array)
                            $area_names_to_display[] = esc_html($assoc_areas_served_value->name);
                        }

                        if (!empty($area_names_to_display)) {
                             $associate_item_html .= '<p class="rep-associate-area"><strong>' . esc_html__('Serves:', 'rep-group') . '</strong> ' . implode(', ', $area_names_to_display) . '</p>';
                        }
                        
                        $assoc_address_field_value = get_sub_field('address');
                        if (is_array($assoc_address_field_value) && !empty(array_filter($assoc_address_field_value))) { 
                            // If it's an array (like an ACF address group), pass it to the formatter.
                            // The formatter get_formatted_address_html expects keys like 'address_1', 'city', etc. without a prefix if $prefix is empty.
                            $associate_item_html .= $this->get_formatted_address_html($assoc_address_field_value, ''); // Pass empty prefix
                        } elseif (is_string($assoc_address_field_value) && !empty(trim($assoc_address_field_value))) {
                            // Fallback if it's just a simple text string and not empty
                            $associate_item_html .= '<div class="address-details associate-address"><p>' . nl2br(esc_html($assoc_address_field_value)) . '</p></div>';
                        } else {
                            // Address is empty or not in a recognized format, skip.
                        }

                        $associate_item_html .= $this->get_formatted_associate_phones_html();

                        $assoc_email_text = get_sub_field('email');
                        if ($assoc_email_text) {
                            $associate_item_html .= $this->get_formatted_email_html($assoc_email_text);
                        }
                        $associate_item_html .= '</div>'; // end .rep-associate-item
                        $associates_items[] = ['name' => $associate_name ? strtolower($associate_name) : '', 'html' => $associate_item_html];
                    }

                    // Sort associates by name
                    usort($associates_items, function($a, $b) {
                        return strcmp($a['name'], $b['name']);
                    });

                    foreach ($associates_items as $item) {
                        $output_html .= $item['html'];
                    }
                    $output_html .= '</div>'; // end .rep-associates-list
                } else {
                    // $output_html .= '<p><em>' . __('No associates listed for this group.', 'rep-group') . '</em></p>'; // Optional: message if no associates
                }
                $output_html .= '</div>'; // .rep-group-item
            }
            wp_reset_postdata();
        } else {
            $output_html = '<h3><span class="area-name-highlighted">' . esc_html( $term->name ) . '</span></h3>';
            $output_html .= '<p>' . __( 'No rep groups found serving this area.', 'rep-group' ) . '</p>';
        }

        wp_send_json_success( [ 'html' => $output_html ] );
    }

    public function ajax_get_rep_group_details_by_id() {
        check_ajax_referer( 'rep_group_frontend_map_nonce', 'nonce' ); // Reuse existing nonce for simplicity

        $rep_group_id = isset( $_POST['rep_group_id'] ) ? absint( $_POST['rep_group_id'] ) : 0;

        if ( ! $rep_group_id || get_post_type($rep_group_id) !== 'rep-group' ) {
            wp_send_json_error( [ 'message' => __( 'Invalid Rep Group ID provided.', 'rep-group' ) ] );
        }

        $output_html = '';
        $post = get_post($rep_group_id);
        if (!$post) {
            wp_send_json_error( [ 'message' => __( 'Rep Group not found.', 'rep-group' ) ] );
        }

        // Setup post data for ACF functions like have_rows, get_field, get_sub_field
        // global $post; // Already global, but ensure it\'s set for the current context of ACF
        // $original_post = $post;
        // $post = get_post($rep_group_id);
        // setup_postdata($post); // This might not be strictly necessary if using ID with get_field/have_rows

        // No area color to pass here for the h3 highlight, so we use a default or no highlight.
        // Or, we could try to find one area this group serves and use its color - too complex for now.
        $output_html .= '<h3><span class="area-name-highlighted">' . esc_html( get_the_title($rep_group_id) ) . '</span></h3>'; // No specific area color for this context
        
        $output_html .= '<div class="rep-group-item"> <!-- Re-use class for consistent styling -->';
        // --- Rep Group's Own Details ---
        $output_html .= '<div class="rep-group-main-details">' ; // Removed h4 title as it's already in h3

        $rg_address_data = get_field('rg_address_container', $rep_group_id);
        if ($rg_address_data) {
            $output_html .= $this->get_formatted_address_html($rg_address_data, 'rg_');
        }
        
        $rg_phones_data = get_field('rg_phone_numbers', $rep_group_id);
        if ($rg_phones_data) {
            $output_html .= $this->get_formatted_phone_numbers_html($rg_phones_data, false);
        }

        $rg_email_data = get_field('rg_email', $rep_group_id);
        if ($rg_email_data) {
            $output_html .= $this->get_formatted_email_html($rg_email_data);
        }
        $output_html .= '</div>'; // end .rep-group-main-details

        // --- Rep Associates ---
        if ( have_rows( 'rep_associates', $rep_group_id ) ) {
            $output_html .= '<h5 class="rep-associates-section-title">' . esc_html__('Rep Associates', 'rep-group') . '</h5>';
            $output_html .= '<div class="rep-associates-list">' ;
            
            $associates_items = [];
            while ( have_rows( 'rep_associates', $rep_group_id ) ) {
                the_row(); // Sets up sub-field context
                $associate_item_html = '<div class="rep-associate-item">' ;
                $associate_name = get_sub_field('name');

                if ($associate_name) {
                    $associate_item_html .= '<h6 class="rep-associate-name">' . esc_html($associate_name) . '</h6>';
                }

                // Debugging line for areas_served (can be removed after fix)
                // error_log(\'[DEBUG] Associate: \' . $associate_name . \' - areas_served raw value: \' . print_r($assoc_areas_served_value, true));

                $assoc_areas_served_value = get_sub_field('areas_served');
                $area_names_to_display = [];

                if (is_array($assoc_areas_served_value) && !empty($assoc_areas_served_value)) {
                    foreach ($assoc_areas_served_value as $term_id) {
                        $term = get_term($term_id, 'area-served'); // Specify taxonomy
                        if ($term instanceof \WP_Term && !is_wp_error($term)) {
                            $area_names_to_display[] = esc_html($term->name);
                        }
                    }
                } elseif ($assoc_areas_served_value instanceof \WP_Term && !empty($assoc_areas_served_value->name)) {
                    // Fallback if it somehow returns a single Term object (though debug shows array)
                    $area_names_to_display[] = esc_html($assoc_areas_served_value->name);
                }

                if (!empty($area_names_to_display)) {
                     $associate_item_html .= '<p class="rep-associate-area"><strong>' . esc_html__('Serves:', 'rep-group') . '</strong> ' . implode(', ', $area_names_to_display) . '</p>';
                }
                
                $assoc_address_field_value = get_sub_field('address');
                if (is_array($assoc_address_field_value) && !empty(array_filter($assoc_address_field_value))) { 
                    // If it's an array (like an ACF address group), pass it to the formatter.
                    // The formatter get_formatted_address_html expects keys like 'address_1', 'city', etc. without a prefix if $prefix is empty.
                    $associate_item_html .= $this->get_formatted_address_html($assoc_address_field_value, ''); // Pass empty prefix
                } elseif (is_string($assoc_address_field_value) && !empty(trim($assoc_address_field_value))) {
                    // Fallback if it's just a simple text string and not empty
                    $associate_item_html .= '<div class="address-details associate-address"><p>' . nl2br(esc_html($assoc_address_field_value)) . '</p></div>';
                } else {
                    // Address is empty or not in a recognized format, skip.
                }

                $associate_item_html .= $this->get_formatted_associate_phones_html();

                $assoc_email_text = get_sub_field('email');
                if ($assoc_email_text) {
                    $associate_item_html .= $this->get_formatted_email_html($assoc_email_text);
                }
                $associate_item_html .= '</div>'; // end .rep-associate-item
                $associates_items[] = ['name' => $associate_name ? strtolower($associate_name) : '', 'html' => $associate_item_html];
            }

            usort($associates_items, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });

            foreach ($associates_items as $item) {
                $output_html .= $item['html'];
            }
            $output_html .= '</div>'; // end .rep-associates-list
        }
        $output_html .= '</div>'; // end .rep-group-item
        
        // wp_reset_postdata(); // Reset if setup_postdata was used
        // $post = $original_post; // Restore global post object if changed

        wp_send_json_success( [ 'html' => $output_html ] );
    }
}

// Instantiate the class if it's not already done by the main plugin loader
// For now, let's assume the main plugin file will initialize it.
// If not, we might need: new Map_Editor_Page(); 