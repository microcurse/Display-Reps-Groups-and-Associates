<?php
/**
 * Plugin Name: Rep Group Shortcode
 * Description: A plugin to display Rep Group information using a shortcode.
 * Version: 2.0
 * Author: Marc Maninang
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Register the shortcode
add_shortcode('rep_group', 'render_rep_group_shortcode');

/**
 * Render the rep group shortcode
 */
function render_rep_group_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => 0,
    ], $atts);

    if (empty($atts['id'])) {
        return '';
    }

    return render_rep_group($atts['id']);
}

/**
 * Render the rep group content
 */
function render_rep_group($post_id) {
    $output = '<section class="mapplic-lightbox__contact-section">';
    
    // Area served
    $area_served = get_field('rg_area_served', $post_id);
    if ($area_served) {
        $output .= sprintf(
            '<h2 class="mapplic-lightbox__area-served">Area Served: %s</h2>',
            esc_html($area_served)
        );
    }

    // Featured image
    if (has_post_thumbnail($post_id)) {
        $featured_image = get_the_post_thumbnail_url($post_id, 'full');
        $title = get_the_title($post_id);
        $output .= sprintf(
            '<figure><img src="%s" alt="%s" width="217" height="auto" /></figure>',
            esc_url($featured_image),
            esc_attr($title)
        );
    }

    // Company name and address
    $output .= sprintf(
        '<h3 class="mapplic-lightbox__company-name">%s</h3>',
        esc_html(get_the_title($post_id))
    );
    
    $output .= '<address class="mapplic-lightbox__address">';
    
    // Address Container
    $address_container = get_field('rg_address_container', $post_id);
    if ($address_container) {
        if (!empty($address_container['rg_address_1'])) {
            $output .= sprintf('<p>%s</p>', esc_html($address_container['rg_address_1']));
        }
        if (!empty($address_container['rg_address_2'])) {
            $output .= sprintf('<p>%s</p>', esc_html($address_container['rg_address_2']));
        }

        $location_parts = array_filter([
            $address_container['rg_city'] ?? '',
            $address_container['rg_state'] ?? '',
            $address_container['rg_zip_code'] ?? ''
        ]);
        if (!empty($location_parts)) {
            $output .= sprintf('<p>%s</p>', esc_html(implode(', ', $location_parts)));
        }
    }

    $output .= '</address>';

    // Rep Associates
    if (have_rows('rep_associates', $post_id)) {
        $output .= '<ul class="mapplic-lightbox__contact-details">';
        while (have_rows('rep_associates', $post_id)) {
            the_row();
            $output .= '<li class="mapplic-lightbox__contact-details-item">';
            
            $rep_name = get_sub_field('name');
            if ($rep_name) {
                $output .= sprintf('<p>%s</p>', esc_html($rep_name));
            }

            $territory = get_sub_field('territory_served');
            if ($territory) {
                $output .= sprintf(
                    '<p><strong>Territory:</strong> %s</p>',
                    esc_html($territory)
                );
            }

            if (have_rows('rep_phone_numbers')) {
                while (have_rows('rep_phone_numbers')) {
                    the_row();
                    $phone_type = get_sub_field('rep_phone_type');
                    $phone_number = get_sub_field('rep_phone_number');
                    if ($phone_type && $phone_number) {
                        $output .= sprintf(
                            '<p>%s: <a href="tel:%s">%s</a></p>',
                            esc_html($phone_type),
                            esc_attr($phone_number),
                            esc_html($phone_number)
                        );
                    }
                }
            }

            $email = get_sub_field('email');
            if ($email) {
                $output .= sprintf(
                    '<p><a href="mailto:%s">%s</a></p>',
                    esc_attr($email),
                    esc_html($email)
                );
            }

            $output .= '</li>';
        }
        $output .= '</ul>';
    }

    $output .= '</section>';
    return $output;
}

// Add filter to display rep group content on single post
add_filter('the_content', function($content) {
    if (!is_singular('rep-group') || !in_the_loop()) {
        return $content;
    }
    return $content . render_rep_group(get_the_ID());
});

// Add the shortcode meta box
add_action('add_meta_boxes', function() {
    add_meta_box(
        'rep-group-shortcode',
        'Rep Group Shortcode',
        'render_shortcode_meta_box',
        'rep-group',
        'side',
        'high'
    );
});

/**
 * Render the shortcode meta box
 */
function render_shortcode_meta_box($post) {
    $shortcode = sprintf('[rep_group id="%d"]', $post->ID);
    ?>
    <div class="rep-group-shortcode" title="Click to copy shortcode">
        <?php echo esc_html($shortcode); ?>
    </div>
    <div class="shortcode-copied">Shortcode copied to clipboard!</div>
    <?php
}

// Add shortcode column to admin list
add_filter('manage_rep-group_posts_columns', function($columns) {
    $columns['shortcode'] = 'Shortcode';
    return $columns;
});

// Display shortcode in admin column
add_action('manage_rep-group_posts_custom_column', function($column, $post_id) {
    if ($column === 'shortcode') {
        $shortcode = sprintf('[rep_group id="%d"]', $post_id);
        printf(
            '<div class="rep-group-shortcode" title="Click to copy shortcode">%s</div>
            <div class="shortcode-copied">Shortcode copied to clipboard!</div>',
            esc_html($shortcode)
        );
    }
}, 10, 2);

// Enqueue admin assets
add_action('admin_enqueue_scripts', function($hook) {
    $screen = get_current_screen();
    
    // Only load on rep-group post type screens
    if ($screen && ($screen->post_type === 'rep-group')) {
        // Enqueue CSS
        wp_enqueue_style(
            'rep-group-admin',
            plugins_url('assets/css/admin.css', __FILE__),
            [],
            '1.0.0'
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'rep-group-admin',
            plugins_url('assets/js/admin.js', __FILE__),
            [],
            '1.0.0',
            true
        );
    }
});

// Add the import/export page to the admin menu
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=rep-group',
        'Import/Export Rep Groups',
        'Import/Export',
        'manage_options',
        'rep-group-import-export',
        'render_import_export_page'
    );
});

/**
 * Render the import/export page
 */
function render_import_export_page() {
    ?>
    <div class="wrap">
        <h1>Import/Export Rep Groups</h1>

        <div class="card">
            <h2>Export Rep Groups</h2>
            <p>Download all rep groups as an Excel file.</p>
            <a href="<?php echo admin_url('admin-post.php?action=export_rep_groups'); ?>" class="button button-primary">Export to Excel</a>
        </div>

        <div class="card">
            <h2>Import Rep Groups</h2>
            <p>Upload an Excel file to import or update rep groups.</p>
            <p><a href="<?php echo admin_url('admin-post.php?action=download_rep_group_template'); ?>" class="button">Download Template</a></p>
            <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('rep_group_import', 'rep_group_import_nonce'); ?>
                <input type="hidden" name="action" value="import_rep_groups">
                <input type="file" name="import_file" accept=".xlsx,.xls" required>
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Import from Excel">
                </p>
            </form>
        </div>
    </div>
    <?php
}

// Add the import action handler
add_action('admin_post_import_rep_groups', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    if (!isset($_FILES['import_file'])) {
        wp_die('No file uploaded');
    }

    require_once __DIR__ . '/vendor/autoload.php';

    $inputFileName = $_FILES['import_file']['tmp_name'];
    $updates = 0;
    $errors = [];
    
    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        // Remove header row
        array_shift($rows);
        
        foreach ($rows as $row) {
            $post_id = intval($row[0]);
            $title = $row[1];
            $area_served = $row[2];
            
            // If post ID is provided, update existing post
            if ($post_id > 0) {
                $existing_post = get_post($post_id);
                if (!$existing_post || $existing_post->post_type !== 'rep-group') {
                    $errors[] = "Invalid post ID: $post_id";
                    continue;
                }

                $post_data = [
                    'ID' => $post_id,
                    'post_title' => $title,
                    'post_type' => 'rep-group'
                ];
                wp_update_post($post_data);
            } else {
                // Create new post
                $post_data = [
                    'post_title' => $title,
                    'post_type' => 'rep-group',
                    'post_status' => 'publish'
                ];
                $post_id = wp_insert_post($post_data);
            }

            if (is_wp_error($post_id)) {
                $errors[] = "Error saving post: " . $post_id->get_error_message();
                continue;
            }

            // Update ACF fields
            update_field('rg_area_served', $area_served, $post_id);
            
            // Update address container
            $address_data = [
                'rg_address_1' => $row[3],
                'rg_address_2' => $row[4],
                'rg_city' => $row[5],
                'rg_state' => $row[6],
                'rg_zip_code' => $row[7]
            ];
            update_field('rg_address_container', $address_data, $post_id);

            // Update rep associates
            if (!empty($row[8])) { // Only if there's a rep name
                // Get existing rep associates
                $existing_associates = get_field('rep_associates', $post_id) ?: [];
                
                // Create new rep data
                $new_rep = [
                    'name' => $row[8],
                    'territory_served' => $row[9],
                    'rep_phone_numbers' => [
                        [
                            'rep_phone_type' => $row[10],
                            'rep_phone_number' => $row[11]
                        ]
                    ],
                    'email' => $row[12]
                ];

                // Check if this rep already exists (by name)
                $rep_exists = false;
                foreach ($existing_associates as $key => $associate) {
                    if ($associate['name'] === $new_rep['name']) {
                        // Update existing rep
                        $existing_associates[$key] = $new_rep;
                        $rep_exists = true;
                        break;
                    }
                }

                // If rep doesn't exist, add them to the array
                if (!$rep_exists) {
                    $existing_associates[] = $new_rep;
                }

                // Update the field with all associates
                update_field('rep_associates', $existing_associates, $post_id);
            }

            $updates++;
        }

        // Set admin notice
        set_transient('rep_group_import_message', [
            'type' => 'success',
            'message' => sprintf(
                'Import completed successfully. Updated %d rep groups. %s',
                $updates,
                !empty($errors) ? ' Errors: ' . implode(', ', $errors) : ''
            )
        ], 45);

    } catch (\Exception $e) {
        set_transient('rep_group_import_message', [
            'type' => 'error',
            'message' => 'Error importing file: ' . $e->getMessage()
        ], 45);
    }

    // Redirect back to the import page
    wp_redirect(add_query_arg(
        ['page' => 'rep-group-import-export'],
        admin_url('edit.php?post_type=rep-group')
    ));
    exit;
});

// Add admin notice for import results
add_action('admin_notices', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'rep-group-import-export') {
        $message = get_transient('rep_group_import_message');
        if ($message) {
            $class = ($message['type'] === 'success') ? 'notice-success' : 'notice-error';
            printf(
                '<div class="notice %s is-dismissible"><p>%s</p></div>',
                esc_attr($class),
                esc_html($message['message'])
            );
            delete_transient('rep_group_import_message');
        }
    }
});

// Add download template action
add_action('admin_post_download_rep_group_template', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    require_once __DIR__ . '/vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set headers
    $headers = [
        'Post ID',
        'Title',
        'Area Served',
        'Address 1',
        'Address 2',
        'City',
        'State',
        'Zip Code',
        'Rep Name',
        'Territory',
        'Phone Type',
        'Phone Number',
        'Email'
    ];
    $sheet->fromArray([$headers], NULL, 'A1');

    // Add example row
    $example = [
        '',  // Leave Post ID empty for new entries
        'Example Rep Group',
        'Northeast Region',
        '123 Main St',
        'Suite 100',
        'Boston',
        'MA',
        '02108',
        'John Smith',
        'Massachusetts',
        'Office',
        '555-123-4567',
        'john@example.com'
    ];
    $sheet->fromArray([$example], NULL, 'A2');

    // Auto-size columns
    foreach (range('A', 'M') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Create Excel file
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="rep-groups-template.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit;
});

// Add some basic styles for the import/export page
add_action('admin_head', function() {
    ?>
    <style>
        .wrap .card {
            max-width: 600px;
            padding: 20px;
            margin-top: 20px;
        }
        .wrap .card h2 {
            margin-top: 0;
        }
        .wrap .card p:last-child {
            margin-bottom: 0;
        }
    </style>
    <?php
});

// Add export action handler
add_action('admin_post_export_rep_groups', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    require_once __DIR__ . '/vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set headers
    $headers = [
        'Post ID',
        'Title',
        'Area Served',
        'Address 1',
        'Address 2',
        'City',
        'State',
        'Zip Code',
        'Rep Name',
        'Territory',
        'Phone Type',
        'Phone Number',
        'Email'
    ];
    $sheet->fromArray([$headers], NULL, 'A1');

    // Get all rep groups
    $rep_groups = get_posts([
        'post_type' => 'rep-group',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ]);

    $row = 2;
    foreach ($rep_groups as $rep_group) {
        $area_served = get_field('rg_area_served', $rep_group->ID);
        $address = get_field('rg_address_container', $rep_group->ID);

        if (have_rows('rep_associates', $rep_group->ID)) {
            while (have_rows('rep_associates', $rep_group->ID)) {
                the_row();
                $rep_name = get_sub_field('name');
                $territory = get_sub_field('territory_served');
                $email = get_sub_field('email');

                if (have_rows('rep_phone_numbers')) {
                    while (have_rows('rep_phone_numbers')) {
                        the_row();
                        $phone_type = get_sub_field('rep_phone_type');
                        $phone_number = get_sub_field('rep_phone_number');

                        $data = [
                            $rep_group->ID,
                            $rep_group->post_title,
                            $area_served,
                            $address['rg_address_1'] ?? '',
                            $address['rg_address_2'] ?? '',
                            $address['rg_city'] ?? '',
                            $address['rg_state'] ?? '',
                            $address['rg_zip_code'] ?? '',
                            $rep_name,
                            $territory,
                            $phone_type,
                            $phone_number,
                            $email
                        ];
                        $sheet->fromArray([$data], NULL, "A{$row}");
                        $row++;
                    }
                } else {
                    // No phone numbers, still add the row
                    $data = [
                        $rep_group->ID,
                        $rep_group->post_title,
                        $area_served,
                        $address['rg_address_1'] ?? '',
                        $address['rg_address_2'] ?? '',
                        $address['rg_city'] ?? '',
                        $address['rg_state'] ?? '',
                        $address['rg_zip_code'] ?? '',
                        $rep_name,
                        $territory,
                        '',
                        '',
                        $email
                    ];
                    $sheet->fromArray([$data], NULL, "A{$row}");
                    $row++;
                }
            }
        } else {
            // No associates, add basic info
            $data = [
                $rep_group->ID,
                $rep_group->post_title,
                $area_served,
                $address['rg_address_1'] ?? '',
                $address['rg_address_2'] ?? '',
                $address['rg_city'] ?? '',
                $address['rg_state'] ?? '',
                $address['rg_zip_code'] ?? '',
                '',
                '',
                '',
                '',
                ''
            ];
            $sheet->fromArray([$data], NULL, "A{$row}");
            $row++;
        }
    }

    // Auto-size columns
    foreach (range('A', 'M') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Create Excel file
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    
    // Clean any output buffers
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="rep-groups-export.xlsx"');
    header('Cache-Control: max-age=0');
    
    // Save to output
    $writer->save('php://output');
    exit;
});