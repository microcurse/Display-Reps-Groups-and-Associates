<?php
namespace RepGroup;

class Import_Export {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_import_export_page']);
        add_action('admin_init', [$this, 'handle_export_action']);
        add_action('admin_init', [$this, 'handle_import_action']);
        add_action('admin_notices', [$this, 'display_import_notices']);
    }

    public function add_import_export_page() {
        add_submenu_page(
            'edit.php?post_type=rep-group',
            __('Import / Export', 'rep-group'),
            __('Import / Export', 'rep-group'),
            'manage_options',
            'rep-group-import-export',
            [$this, 'render_import_export_page']
        );
    }

    public function render_import_export_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $template_path = REP_GROUP_PATH . 'templates/admin/import-export-page.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="wrap"><h1>' . esc_html__('Import / Export', 'rep-group') . '</h1><p>Error: Template file not found.</p></div>';
        }
    }

    public function handle_export_action() {
        if (isset($_POST['rep_group_export_nonce']) && wp_verify_nonce($_POST['rep_group_export_nonce'], 'rep_group_export_action')) {
            if (isset($_POST['export_rep_data'])) {
                $this->export_data();
            }
        }
    }

    public function handle_import_action() {
        if (isset($_POST['import_rep_data']) && isset($_FILES['import_file'])) {
            if (!isset($_POST['rep_group_import_nonce']) || !wp_verify_nonce($_POST['rep_group_import_nonce'], 'rep_group_import_action')) {
                $this->add_notice('error', 'Security check failed.');
                return;
            }

            if (!current_user_can('manage_options')) {
                $this->add_notice('error', 'You do not have sufficient permissions to import data.');
                return;
            }

            if ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
                $this->add_notice('error', 'File upload error. Code: ' . $_FILES['import_file']['error']);
                return;
            }

            $file_path = $_FILES['import_file']['tmp_name'];
            $file_content = file_get_contents($file_path);
            if ($file_content === false) {
                 $this->add_notice('error', 'Could not read import file.');
                 return;
            }
            $data = json_decode($file_content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->add_notice('error', 'Invalid JSON file. Error: ' . json_last_error_msg());
                return;
            }
            
            $this->import_data($data);
        }
    }
    
    public function display_import_notices() {
        $notices = get_transient('rep_group_import_notices');
        if (!empty($notices) && is_array($notices)) {
            foreach ($notices as $notice) {
                printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                    esc_attr($notice['type']),
                    esc_html($notice['message'])
                );
            }
            delete_transient('rep_group_import_notices');
        }
    }
    
    private function add_notice($type, $message) {
        $notices = get_transient('rep_group_import_notices') ?: [];
        $notices[] = ['type' => $type, 'message' => $message];
        set_transient('rep_group_import_notices', $notices, 60);
    }

    private function export_data() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $export_data = [
            'version' => defined('REP_GROUP_VERSION') ? REP_GROUP_VERSION : '1.0.0',
            'timestamp' => current_time('mysql'),
            'source_url' => site_url(),
            'posts' => [],
            'terms' => [],
            'options' => []
        ];

        $posts_query = new \WP_Query([
            'post_type' => 'rep-group',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ]);

        if ($posts_query->have_posts()) {
            while ($posts_query->have_posts()) {
                $posts_query->the_post();
                $post_id = get_the_ID();
                $post_data = get_post($post_id, ARRAY_A);
                
                $acf_fields = get_fields($post_id);
                if ($acf_fields) {
                    $post_data['acf_fields'] = $acf_fields;
                }

                $assigned_terms = wp_get_object_terms($post_id, 'area-served', ['fields' => 'slugs']);
                if (!is_wp_error($assigned_terms) && !empty($assigned_terms)) {
                    $post_data['assigned_terms_area_served'] = $assigned_terms;
                }

                $export_data['posts'][] = $post_data;
            }
            wp_reset_postdata();
        }

        $terms = get_terms([
            'taxonomy' => 'area-served',
            'hide_empty' => false,
        ]);

        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                $term_data = (array) $term;
                $term_meta = get_term_meta($term->term_id);
                if ($term_meta) {
                    $term_data['meta'] = $term_meta;
                }
                $export_data['terms'][] = $term_data;
            }
        }

        $options_to_export = [
            'rep_group_local_svg',
            'rep_group_international_svg',
        ];

        foreach ($options_to_export as $option_name) {
            $export_data['options'][$option_name] = get_option($option_name);
        }
        
        $filename = 'rep-group-export-' . date('Y-m-d') . '.json';
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');
        echo wp_json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }

    private function import_data($data) {
        $stats = [
            'posts_created' => 0, 'posts_updated' => 0, 'posts_skipped' => 0,
            'terms_created' => 0, 'terms_updated' => 0, 'terms_skipped' => 0,
            'options_updated' => 0,
        ];

        // 1. Import Terms first
        if (!empty($data['terms']) && is_array($data['terms'])) {
            foreach ($data['terms'] as $term_item) {
                 if (!isset($term_item['slug']) || !isset($term_item['name'])) continue;
                $term = term_exists($term_item['slug'], 'area-served');
                if ($term) {
                    $term_id = $term['term_id'];
                    wp_update_term($term_id, 'area-served', ['name' => $term_item['name'], 'slug' => $term_item['slug']]);
                    $stats['terms_updated']++;
                } else {
                    $new_term = wp_insert_term($term_item['name'], 'area-served', ['slug' => $term_item['slug']]);
                    if (!is_wp_error($new_term)) {
                        $term_id = $new_term['term_id'];
                        $stats['terms_created']++;
                    } else {
                        $stats['terms_skipped']++;
                        continue;
                    }
                }
                
                if (isset($term_id) && !empty($term_item['meta']) && is_array($term_item['meta'])) {
                    foreach ($term_item['meta'] as $meta_key => $meta_value) {
                         if (isset($meta_value[0])) {
                            update_term_meta($term_id, $meta_key, $meta_value[0]);
                         }
                    }
                }
            }
        }

        // 2. Import Posts and store term relationships for later
        $post_term_map = [];
        if (!empty($data['posts']) && is_array($data['posts'])) {
            foreach ($data['posts'] as $post_item) {
                if (!isset($post_item['post_title'])) continue;

                $post_args = [
                    'post_title' => $post_item['post_title'],
                    'post_content' => isset($post_item['post_content']) ? $post_item['post_content'] : '',
                    'post_type' => 'rep-group',
                    'post_status' => isset($post_item['post_status']) ? $post_item['post_status'] : 'publish',
                ];

                $existing_post = get_page_by_title($post_item['post_title'], OBJECT, 'rep-group');

                if ($existing_post instanceof \WP_Post) {
                    $post_args['ID'] = $existing_post->ID;
                    $post_id = wp_update_post($post_args);
                    if (!is_wp_error($post_id)) {
                        $stats['posts_updated']++;
                    } else {
                        $stats['posts_skipped']++;
                        continue;
                    }
                } else {
                    $post_id = wp_insert_post($post_args);
                    if (!is_wp_error($post_id)) {
                        $stats['posts_created']++;
                    } else {
                        $stats['posts_skipped']++;
                        continue;
                    }
                }

                if (isset($post_item['acf_fields']) && is_array($post_item['acf_fields'])) {
                    foreach ($post_item['acf_fields'] as $key => $value) {
                        update_field($key, $value, $post_id);
                    }
                }

                if (isset($post_item['assigned_terms_area_served']) && is_array($post_item['assigned_terms_area_served'])) {
                    $post_term_map[$post_id] = $post_item['assigned_terms_area_served'];
                }
            }
        }
        
        // 3. Assign terms to posts now that posts and terms are imported
        if (!empty($post_term_map)) {
            foreach($post_term_map as $post_id => $term_slugs) {
                wp_set_object_terms($post_id, $term_slugs, 'area-served');
            }
        }

        // 4. Import Options (with URL replacement)
        if (!empty($data['options']) && is_array($data['options'])) {
            $source_url = isset($data['source_url']) ? rtrim($data['source_url'], '/') : '';
            $destination_url = rtrim(site_url(), '/');

            foreach ($data['options'] as $option_name => $option_value) {
                if (!empty($source_url) && !empty($option_value) && is_string($option_value) && $source_url !== $destination_url) {
                    $option_value = str_replace($source_url, $destination_url, $option_value);
                }
                update_option($option_name, $option_value);
                $stats['options_updated']++;
            }
        }

        $this->add_notice('success', 'Import complete. ' . implode(' | ', array_map(
            function ($k, $v) { return ucwords(str_replace('_', ' ', $k)) . ": $v"; },
            array_keys($stats), $stats
        )));
    }
} 