<?php
if (!defined('ABSPATH')) {
    exit;
}
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