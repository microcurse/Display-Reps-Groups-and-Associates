<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <p><?php esc_html_e('Use the tools below to export or import your Rep Group data.', 'rep-group'); ?></p>

    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-2">
            <!-- Main Content -->
            <div id="post-body-content">
                <div class="meta-box-sortables ui-sortable">
                    <!-- Export Card -->
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e('Export Data', 'rep-group'); ?></span></h2>
                        <div class="inside">
                            <p><?php esc_html_e('Click the button below to export all Rep Groups, Areas Served, and map settings into a single JSON file. This file can be used to import the data to another website.', 'rep-group'); ?></p>
                            <form method="post">
                                <?php wp_nonce_field('rep_group_export_action', 'rep_group_export_nonce'); ?>
                                <p>
                                    <input type="submit" name="export_rep_data" class="button button-primary" value="<?php esc_attr_e('Export All Data', 'rep-group'); ?>">
                                </p>
                            </form>
                        </div>
                    </div>

                    <!-- Import Card -->
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e('Import Data', 'rep-group'); ?></span></h2>
                        <div class="inside">
                            <p><?php esc_html_e('Select the JSON file you want to import. This will overwrite existing data.', 'rep-group'); ?></p>
                            <form method="post" enctype="multipart/form-data">
                                <?php wp_nonce_field('rep_group_import_action', 'rep_group_import_nonce'); ?>
                                <p>
                                    <label for="import_file"><?php esc_html_e('Select JSON file to upload:', 'rep-group'); ?></label>
                                    <input type="file" id="import_file" name="import_file" accept=".json" required>
                                </p>
                                <p>
                                    <input type="submit" name="import_rep_data" class="button button-secondary" value="<?php esc_attr_e('Import Data', 'rep-group'); ?>">
                                </p>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div id="postbox-container-1" class="postbox-container">
                <div class="meta-box-sortables">
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e('Instructions', 'rep-group'); ?></span></h2>
                        <div class="inside">
                            <p><strong><?php esc_html_e('Exporting:', 'rep-group'); ?></strong></p>
                            <ol>
                                <li><?php esc_html_e('Click "Export All Data".', 'rep-group'); ?></li>
                                <li><?php esc_html_e('Your browser will download a .json file.', 'rep-group'); ?></li>
                            </ol>
                            <hr>
                            <p><strong><?php esc_html_e('Importing:', 'rep-group'); ?></strong></p>
                            <ol>
                                <li><?php esc_html_e('Click "Choose File" and select your exported .json file.', 'rep-group'); ?></li>
                                <li><?php esc_html_e('Click "Import Data".', 'rep-group'); ?></li>
                            </ol>
                            <p><em><strong><?php esc_html_e('Warning:', 'rep-group'); ?></strong> <?php esc_html_e('Importing will overwrite any existing Rep Group data with matching identifiers. It is recommended to backup your database before importing.', 'rep-group'); ?></em></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <br class="clear">
    </div>
</div> 