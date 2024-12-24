<?php
namespace RepGroup;

class Import_Export {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_import_export_page']);
        add_action('admin_post_export_rep_groups', [$this, 'handle_export']);
        add_action('admin_post_import_rep_groups', [$this, 'handle_import']);
        add_action('admin_post_download_rep_group_template', [$this, 'handle_template_download']);
        add_action('admin_notices', [$this, 'display_import_notices']);
    }

    public function add_import_export_page() {
        add_submenu_page(
            'edit.php?post_type=rep-group',
            'Import/Export Rep Groups',
            'Import/Export',
            'manage_options',
            'rep-group-import-export',
            [$this, 'render_import_export_page']
        );
    }

    public function render_import_export_page() {
        require_once REP_GROUP_PLUGIN_PATH . 'templates/import-export-page.php';
    }

    public function handle_export() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        require_once REP_GROUP_PLUGIN_PATH . 'vendor/autoload.php';

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
    }

    public function handle_import() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (!isset($_FILES['import_file'])) {
            wp_die('No file uploaded');
        }

        require_once REP_GROUP_PLUGIN_PATH . 'vendor/autoload.php';

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

                    // Get unique identifier for this row (combine post ID and rep name)
                    $row_identifier = $post_id . '-' . strtolower(trim($row[8]));

                    // Track which reps we've seen in this import
                    static $processed_reps = [];
                    
                    if (!isset($processed_reps[$post_id])) {
                        $processed_reps[$post_id] = [];
                    }
                    
                    $processed_reps[$post_id][] = $row_identifier;

                    // If this is the first rep we're processing for this post,
                    // clear the existing associates
                    if (count($processed_reps[$post_id]) === 1) {
                        $existing_associates = [];
                    }

                    // Add the new/updated rep data
                    $existing_associates[] = $new_rep;

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
    }

    public function handle_template_download() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        require_once REP_GROUP_PLUGIN_PATH . 'vendor/autoload.php';

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
            '',  // Post ID (leave blank for new entries)
            'Example Rep Group',
            'Northeast Region',
            '123 Main St',
            'Suite 100',
            'Boston',
            'MA',
            '02108',
            'John Smith',
            'Greater Boston Area',
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
        
        // Clean any output buffers
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers for download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="rep-groups-template.xlsx"');
        header('Cache-Control: max-age=0');
        
        // Save to output
        $writer->save('php://output');
        exit;
    }

    public function display_import_notices() {
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
    }
} 