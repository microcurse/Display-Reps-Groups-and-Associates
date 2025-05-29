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
        ?>
        <div class="wrap">
            <h1>Import/Export Rep Groups</h1>

            <!-- Export Section -->
            <div class="card">
                <h2>Export</h2>
                <p>Download all rep groups as an Excel file.</p>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('export_rep_groups', 'export_nonce'); ?>
                    <input type="hidden" name="action" value="export_rep_groups">
                    <button type="submit" class="button button-primary">Export Rep Groups</button>
                </form>
            </div>

            <!-- Template Download Section -->
            <div class="card">
                <h2>Download Template</h2>
                <p>Download an Excel template for importing rep groups.</p>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('download_rep_group_template', 'template_nonce'); ?>
                    <input type="hidden" name="action" value="download_rep_group_template">
                    <button type="submit" class="button button-secondary">Download Template</button>
                </form>
            </div>

            <!-- Import Section -->
            <div class="card">
                <h2>Import</h2>
                <p>Import rep groups from an Excel file. Please use the template provided above.</p>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field('import_rep_groups', 'import_nonce'); ?>
                    <input type="hidden" name="action" value="import_rep_groups">
                    <input type="file" name="import_file" accept=".xlsx,.xls" required>
                    <p class="submit">
                        <button type="submit" class="button button-primary">Import Rep Groups</button>
                    </p>
                </form>
            </div>
        </div>

        <style>
            .card {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                margin-top: 20px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .card h2 {
                margin-top: 0;
            }
            form {
                margin: 15px 0;
            }
        </style>
        <?php
    }

    public function handle_export() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Ensure no prior output interferes
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start(); // Start a clean buffer specifically for this export

        require_once REP_GROUP_PATH . 'vendor/autoload.php';
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $headers = [
            'Post ID', 'Rep Group Title', 'Rep Group Area Served (Taxonomy)', 
            'Street Address', 'City', 'State', 'Zip Code', 'Country',
            'Associate User Email', 'Associate User Display Name', 
            'Associate Areas Served (for this group)', 
            'Associate Contact Email Override', 'Associate Contact Phone Override'
        ];
        $sheet->fromArray([$headers], NULL, 'A1');

        // Get all rep groups
        $rep_groups = get_posts([
            'post_type' => 'rep-group',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);

        if (is_wp_error($rep_groups)) {
            error_log('Error fetching rep groups for export: ' . $rep_groups->get_error_message());
            wp_die('Error fetching rep groups. Please check the logs.');
        }
        
        // Additional check to satisfy linter and ensure we have WP_Post objects
        if (!empty($rep_groups) && !($rep_groups[0] instanceof \WP_Post)) {
            // This case should ideally not happen if get_posts behaves as expected
            // and returns WP_Post objects or an empty array.
            error_log('Rep groups fetched, but are not WP_Post objects. Aborting export.');
            wp_die('Unexpected data type for rep groups. Please check the logs.');
        }
        
        $row = 2;
        foreach ($rep_groups as $rep_group) {
            if (!($rep_group instanceof \WP_Post)) {
                error_log("Skipping an item in rep_groups as it is not a WP_Post object.");
                continue; // Skip to the next item if it's not a WP_Post object
            }

            // Correctly get 'Area Served' taxonomy terms for the Rep Group
            $rep_group_areas_served_terms = get_the_terms($rep_group->ID, 'area-served');
            $rep_group_area_served_names = [];
            if ($rep_group_areas_served_terms && !is_wp_error($rep_group_areas_served_terms)) {
                foreach ($rep_group_areas_served_terms as $term) {
                    $rep_group_area_served_names[] = $term->name;
                }
            }
            $area_served_display = implode(', ', $rep_group_area_served_names);

            $address_container = get_field('rg_address_container', $rep_group->ID);
            $street_address = $address_container['rg_street_address'] ?? '';
            $city = $address_container['rg_city'] ?? '';
            $state = $address_container['rg_state'] ?? '';
            $zip_code = $address_container['rg_zip_code'] ?? '';
            $country_data = $address_container['rg_country'] ?? [];
            $country = is_array($country_data) && isset($country_data['label']) ? $country_data['label'] : ($country_data ?: '');

            if (have_rows('rep_associates', $rep_group->ID)) {
                while (have_rows('rep_associates', $rep_group->ID)) {
                    the_row();
                    $user_id = get_sub_field('rep_user'); 
                    $user_data = $user_id ? get_userdata($user_id) : false;
                    if (!$user_data && $user_id) { // Log if user_id was present but user not found
                        error_log(
                            sprintf(
                                'Export Debug: For Rep Group ID %s, Associate User ID %s was found in ACF, but no corresponding WP User exists.\'',
                                $rep_group->ID,
                                $user_id
                            )
                        );
                    } elseif (!$user_id) {
                        error_log(
                            sprintf(
                                'Export Debug: For Rep Group ID %s, an associate row was found with no User ID in rep_user field.\'',
                                $rep_group->ID
                            )
                        );
                    }
                    $associate_user_email = $user_data ? $user_data->user_email : '';
                    $associate_user_display_name = $user_data ? $user_data->display_name : 'User not found';

                    // Get 'areas_served_for_group' taxonomy term IDs
                    // The third parameter `false` tells ACF to return term IDs directly
                    $assoc_term_ids = get_sub_field('areas_served_for_group', false, false); 
                    $assoc_area_names = [];
                    if (is_array($assoc_term_ids) && !empty($assoc_term_ids)) {
                        foreach ($assoc_term_ids as $term_id) {
                            $term = get_term(intval($term_id), 'area-served');
                            if ($term instanceof \WP_Term && !is_wp_error($term)) {
                                $assoc_area_names[] = $term->name;
                            }
                        }
                    }
                    $associate_territory_display = implode(', ', $assoc_area_names);
                    
                    $associate_email_override = get_sub_field('rep_contact_email_override');
                    $associate_phone_override = get_sub_field('rep_contact_phone_override');

                    $data_row = [
                        $rep_group->ID,
                        $rep_group->post_title,
                        $area_served_display,
                        $street_address,
                        $city,
                        $state,
                        $zip_code,
                        $country,
                        $associate_user_email,
                        $associate_user_display_name,
                        $associate_territory_display,
                        $associate_email_override,
                        $associate_phone_override
                    ];
                    $sheet->fromArray([$data_row], NULL, "A{$row}");
                    $row++;
                }
            } else {
                // If a rep group has no associates, still list the rep group info
                $data_row = [
                    $rep_group->ID,
                    $rep_group->post_title,
                    $area_served_display,
                    $street_address,
                    $city,
                    $state,
                    $zip_code,
                    $country,
                    '', // No associate user email
                    '', // No associate user display name
                    '', // No associate areas served
                    '', // No associate email override
                    ''  // No associate phone override
                ];
                $sheet->fromArray([$data_row], NULL, "A{$row}");
                $row++;
            }
        }

        $filename = 'rep_groups_export_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        // Check if headers already sent before attempting to send our own
        if (headers_sent($file, $line)) {
            error_log("Export Error: Headers already sent in {$file} on line {$line}");
            ob_end_clean(); // Clean the buffer we started
            wp_die("Cannot initiate file download. Headers already sent by {$file} at line {$line}. Please check for stray output in your PHP files or enable output buffering in php.ini.");
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1'); // Define Cache-Control for IE9
        // If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // Always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        // Temporarily suppress PHP errors from interfering with output stream
        $old_error_reporting = error_reporting(0);
        @ini_set('display_errors', '0');

        try {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        } catch (\PhpOffice\PhpSpreadsheet\Writer\Exception $e) {
            error_log("Excel writer exception: " . $e->getMessage());
        } catch (\Throwable $t) {
            error_log("General error during Excel export: " . $t->getMessage() . "\nStack Trace:\n" . $t->getTraceAsString());
        }

        // Restore error reporting
        error_reporting($old_error_reporting);
        @ini_set('display_errors', ini_get('display_errors')); // Restore to previous display_errors state
        
        ob_end_flush(); // Send the buffer content (Excel file)
        exit;        
    }

    public function handle_import() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (!isset($_FILES['import_file']) || empty($_FILES['import_file']['tmp_name'])) {
            wp_die('No file uploaded or file is empty.');
        }

        require_once REP_GROUP_PATH . 'vendor/autoload.php';

        $inputFileName = $_FILES['import_file']['tmp_name'];
        $processed_count = 0;
        $created_count = 0;
        $updated_count = 0;
        $skipped_rows = 0;
        $errors = [];
        $current_post_id_being_processed = null;
        $associates_data_for_current_post = [];

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            $header_row = array_shift($rows); // Remove and store header row for reference
            $expected_headers = [
                'Rep Group Post ID', 
                'Rep Group Title',
                'Rep Group Area Served (comma-separated)',
                'Rep Group Address 1',
                'Rep Group Address 2', 
                'Rep Group City',
                'Rep Group State',
                'Rep Group Zip Code',
                'Associate User Email', 
                'Associate Areas Served (for this group, comma-separated)', 
                'Associate Email Override',
                'Associate Phone Override'
            ];
            // Basic header check
            if (count($header_row) < count($expected_headers)) { // Check if we have at least enough columns
                 $errors[] = "Import file has too few columns. Expected at least " . count($expected_headers) . " columns based on defined headers.";
                 throw new \Exception("Header column count mismatch.");
            }

            // Function to process/save the collected associates for the current Rep Group
            $save_current_rep_group_associates = function() use (&$current_post_id_being_processed, &$associates_data_for_current_post, &$errors) {
                if ($current_post_id_being_processed && !empty($associates_data_for_current_post)) {
                    // Re-index numerically before saving to ACF
                    update_field('rep_associates', array_values($associates_data_for_current_post), $current_post_id_being_processed);
                }
                $associates_data_for_current_post = []; // Reset for next group
            };
            
            $row_number = 1; // Start from 1 after header

            foreach ($rows as $row_data) {
                $row_number++;
                // Pad row with empty strings if it has fewer columns than expected to avoid undefined offset errors
                $row = array_pad($row_data, count($expected_headers), '');

                $post_id_from_csv = !empty($row[0]) ? intval($row[0]) : 0;
                $title_from_csv = trim($row[1]);

                if (empty($title_from_csv)) {
                    $errors[] = "Row {$row_number}: Title is missing. Skipping row.";
                    $skipped_rows++;
                    continue;
                }
                
                $target_post_id = 0;
                $is_new_post = false;

                if ($post_id_from_csv > 0) {
                    $existing_post = get_post($post_id_from_csv);
                    if ($existing_post && $existing_post->post_type === 'rep-group') {
                        $target_post_id = $post_id_from_csv;
                    } else {
                        $errors[] = "Row {$row_number}: Post ID {$post_id_from_csv} not found or is not a Rep Group. Attempting to match by title '{$title_from_csv}'.";
                        // Fall through to title matching
                    }
                }

                if ($target_post_id === 0) { // No valid Post ID from CSV, or lookup failed
                    $existing_post_by_title = get_page_by_title($title_from_csv, OBJECT, 'rep-group');
                    if ($existing_post_by_title) {
                        $target_post_id = $existing_post_by_title->ID;
                        if($post_id_from_csv > 0) { // CSV had an ID, but it was wrong. Inform user.
                             $errors[] = "Row {$row_number}: Rep Group '{$title_from_csv}' found with ID {$target_post_id} instead of CSV Post ID {$post_id_from_csv}. Using ID {$target_post_id}.";
                        }
                    } else {
                        $is_new_post = true;
                    }
                }
                
                // If the Rep Group changes (based on Post ID or detection of a new one by title for the first row)
                if ($target_post_id !== $current_post_id_being_processed && $current_post_id_being_processed !== null) {
                    $save_current_rep_group_associates(); // Save previous group's associates
                }
                
                // If it's a new Rep Group (either different from current or first one)
                if ($target_post_id !== $current_post_id_being_processed || $is_new_post) {
                    if ($is_new_post) {
                         $post_data_args = [
                            'post_title'   => $title_from_csv,
                            'post_type'    => 'rep-group',
                            'post_status'  => 'publish',
                        ];
                        $new_post_id = wp_insert_post($post_data_args);

                        if (is_wp_error($new_post_id)) {
                            $errors[] = "Row {$row_number}: Error creating new Rep Group '{$title_from_csv}': " . $new_post_id->get_error_message();
                            $skipped_rows++;
                            $current_post_id_being_processed = null; // Ensure we don't try to process associates for failed post
                            continue; 
                        }
                        $target_post_id = $new_post_id;
                        $created_count++;
                        $current_post_id_being_processed = $target_post_id;
                    } else {
                        // Existing Rep Group, just update its title if different from CSV
                        if (get_the_title($target_post_id) !== $title_from_csv) {
                             wp_update_post(['ID' => $target_post_id, 'post_title' => $title_from_csv]);
                        }
                        $updated_count++;
                        $current_post_id_being_processed = $target_post_id;
                    }
                    $processed_count++;

                    // Update Rep Group's own fields (Address, Area Served)
                    // These are processed once per Rep Group
                    $address_data = [
                        'rg_address_1' => trim($row[3]), // Rep Group Address 1
                        'rg_address_2' => trim($row[4]), // Rep Group Address 2
                        'rg_city'      => trim($row[5]), // Rep Group City
                        'rg_state'     => trim($row[6]), // Rep Group State
                        'rg_zip_code'  => trim($row[7]), // Rep Group Zip Code
                    ];
                    update_field('rg_address_container', $address_data, $current_post_id_being_processed);

                    $rep_group_areas_served_str = trim($row[2]); // Rep Group Area Served
                    $rep_group_term_ids = [];
                    if (!empty($rep_group_areas_served_str)) {
                        $term_names = array_map('trim', explode(',', $rep_group_areas_served_str));
                        foreach ($term_names as $term_name) {
                            if (empty($term_name)) continue;
                            $term = get_term_by('name', $term_name, 'area-served');
                            if ($term) {
                                $rep_group_term_ids[] = $term->term_id;
                            } else {
                                // Optionally create term if it doesn't exist
                                // $new_term = wp_insert_term($term_name, 'area-served');
                                // if (!is_wp_error($new_term)) $rep_group_term_ids[] = $new_term['term_id'];
                                // else $errors[] = "Row {$row_number}: Could not find or create term '{$term_name}' for Rep Group Area Served.";
                                $errors[] = "Row {$row_number}: Term '{$term_name}' for Rep Group Area Served not found. Please create it first.";
                            }
                        }
                    }
                    update_field('area-served', $rep_group_term_ids, $current_post_id_being_processed); // Use correct field key for taxonomy
                }

                // Process Associate for the current Rep Group
                $associate_user_email_from_csv = trim($row[8]); // Associate User Email

                if (!empty($associate_user_email_from_csv) && $current_post_id_being_processed) {
                    if (!is_email($associate_user_email_from_csv)){
                        $errors[] = "Row {$row_number}: Invalid email format '{$associate_user_email_from_csv}' for Associate User Email. Skipping associate.";
                    } else {
                        $user = get_user_by('email', $associate_user_email_from_csv);
                        $user_id_for_associate = 0;

                        if ($user) {
                            $user_id_for_associate = $user->ID;
                        } else {
                            // Option: Create user if not found. For now, we'll error.
                            // To create: $user_id_for_associate = wp_create_user($associate_user_email_from_csv, wp_generate_password(), $associate_user_email_from_csv);
                            // if (is_wp_error($user_id_for_associate)) { ... error handling ... }
                            // else { wp_update_user(['ID' => $user_id_for_associate, 'role' => 'rep']); /* Add to Rep role */ }
                            $errors[] = "Row {$row_number}: User with email '{$associate_user_email_from_csv}' not found. Please create the user with 'Rep' role first. Skipping associate.";
                        }

                        if ($user_id_for_associate > 0) {
                            // Use email as a temporary key for associate data during this import session for THIS rep group
                            // to prevent duplicate entries if the same user is listed multiple times for the same rep group in the CSV (which shouldn't happen with this new structure)
                            $associate_session_key = $associate_user_email_from_csv; 

                            if (!isset($associates_data_for_current_post[$associate_session_key])) {
                                $associates_data_for_current_post[$associate_session_key] = [
                                    'rep_user' => $user_id_for_associate, // Field key for ACF User field
                                    'areas_served_for_group' => [],      // Field key for ACF Taxonomy field
                                    'rep_contact_email_override' => trim($row[10]), // Associate Email Override
                                    'rep_contact_phone_override' => trim($row[11]), // Associate Phone Override
                                    // 'acf_fc_layout' => 'your_repeater_row_layout_name' // if applicable
                                ];

                                $assoc_areas_served_str = trim($row[9]); // Associate Areas Served
                                $assoc_term_ids = [];
                                if (!empty($assoc_areas_served_str)) {
                                    $term_names = array_map('trim', explode(',', $assoc_areas_served_str));
                                    foreach ($term_names as $term_name) {
                                        if (empty($term_name)) continue;
                                        $term = get_term_by('name', $term_name, 'area-served');
                                        if ($term) {
                                            $assoc_term_ids[] = $term->term_id;
                                        } else {
                                            $errors[] = "Row {$row_number}: Term '{$term_name}' for Associate Areas Served not found. Please create it first.";
                                        }
                                    }
                                }
                                $associates_data_for_current_post[$associate_session_key]['areas_served_for_group'] = $assoc_term_ids;
                            } else {
                                // User already listed for this Rep Group in this import batch. This row might be redundant.
                                // Or, if we decided to allow multiple rows to *add* data (e.g. more areas served), this logic would change.
                                // For now, first entry for user wins for areas/overrides for this rep group.
                                $errors[] = "Row {$row_number}: Associate with email '{$associate_user_email_from_csv}' already processed for this Rep Group in this import. Additional data on this row for this associate was ignored.";
                            }
                        }
                    } // end is_email check
                } elseif (empty($associate_user_email_from_csv) && $current_post_id_being_processed && $is_new_post) {
                    // This is a new rep group with no associates listed on its first row.
                    // This is fine, associates_data_for_current_post will be empty.
                } elseif (empty($associate_user_email_from_csv) && $current_post_id_being_processed && !$is_new_post) {
                    // This is an existing rep group, and this row has no associate.
                    // This usually implies the main rep group data was on this row, and was processed.
                    // If all associates for this rep group were on previous rows, they'd be saved when Post ID changes.
                    // If this is the *only* row for an existing rep group, and it has no associates,
                    // it might clear existing associates if $save_current_rep_group_associates runs next
                    // due to a Post ID change. This is intended: empty associates in sheet means empty associates on post.
                }

            } // End foreach $row

            // Save associates for the very last Rep Group in the file
            $save_current_rep_group_associates();
            
            $success_message = sprintf(
                'Import completed. Processed %d unique Rep Groups (%d created, %d updated). %d rows were skipped.',
                $processed_count, $created_count, ($processed_count - $created_count), $skipped_rows
            );
            if (!empty($errors)) {
                $success_message .= ' Errors encountered: <br>- ' . implode('<br>- ', array_unique($errors));
                 set_transient('rep_group_import_message', ['type' => 'warning', 'message' => $success_message], 45);
            } else {
                 set_transient('rep_group_import_message', ['type' => 'success', 'message' => $success_message], 45);
            }

        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            set_transient('rep_group_import_message', ['type' => 'error', 'message' => 'Error reading Excel file: ' . $e->getMessage()], 45);
        } catch (\Exception $e) {
            $general_error_message = 'Error during import: ' . $e->getMessage();
            if(!empty($errors)){
                 $general_error_message .= ' Additional errors: <br>- ' . implode('<br>- ', array_unique($errors));
            }
            set_transient('rep_group_import_message', ['type' => 'error', 'message' => $general_error_message], 45);
        }

        // Redirect back
        wp_redirect(add_query_arg(
            ['page' => 'rep-group-import-export', 'post_type' => 'rep-group'],
            admin_url('edit.php')
        ));
        exit;
    }

    public function handle_template_download() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Ensure no prior output interferes
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start(); // Start a clean buffer specifically for this template download

        require_once REP_GROUP_PATH . 'vendor/autoload.php';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $headers = [
            'Rep Group Post ID', 
            'Rep Group Title',
            'Rep Group Area Served (comma-separated)',
            'Rep Group Address 1',
            'Rep Group Address 2',
            'Rep Group City',
            'Rep Group State',
            'Rep Group Zip Code',
            'Associate User Email', // Email of the WP User (Rep) to link/create
            'Associate Areas Served (for this group, comma-separated)',
            'Associate Email Override',
            'Associate Phone Override'
        ];
        $sheet->fromArray([$headers], NULL, 'A1');

        // Add example row
        $example = [
            '',  // Rep Group Post ID (leave blank for new, or ID to update)
            'Global Reps Inc.',
            'North America, Europe',
            '100 Corp Drive',
            'Building A',
            'New York',
            'NY',
            '10001',
            'jane.doe@example.com', // WP User email for the associate
            'USA - Northeast, Canada - East',
            'jane.group@example.com', // Optional: email override for this group
            '212-555-0100'  // Optional: phone override for this group
        ];
        $sheet->fromArray([$example], NULL, 'A2');
        
        $example_2 = [
            '',  // Rep Group Post ID (if Global Reps Inc. was created and got ID 150, put 150 here to add another associate)
            'Global Reps Inc.', // Or, if Post ID is blank, use same title to group associates under same Rep Group
            'North America, Europe', // Repeated for clarity, but only processed once per Rep Group
            '100 Corp Drive',
            'Building A',
            'New York',
            'NY',
            '10001',
            'john.smith@example.com', // Second associate for the same Rep Group
            'USA - West Coast',
            '', // No email override for John
            ''  // No phone override for John
        ];
        $sheet->fromArray([$example_2], NULL, 'A3');

        // Auto-size columns
        foreach (range('A', 'L') as $col) { // Adjusted to L for 12 columns
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Create Excel file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        // Clean any output buffers
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Start with a clean buffer
        if (ob_get_length()) {
            ob_end_clean();
        }
        ob_start();
        
        // Ensure no previous output has broken the stream
        if (headers_sent($file, $line)) {
            error_log("Headers already sent in $file on line $line");
            wp_die("Headers already sent. Cannot generate Excel file.");
        }
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"{$filename}\"");
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