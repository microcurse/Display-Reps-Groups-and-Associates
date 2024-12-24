<?php
if (!defined('ABSPATH')) {
    exit;
}

$shortcode = sprintf('[rep_group id="%d"]', $post->ID);
?>
<div class="rep-group-shortcode" title="Click to copy shortcode">
    <?php echo esc_html($shortcode); ?>
</div>
<div class="shortcode-copied">Shortcode copied to clipboard!</div> 