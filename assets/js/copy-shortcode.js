jQuery(document).ready(function($) {
    // Initialize clipboard.js
    new ClipboardJS('.copy-shortcode');
    
    // Add click handler for feedback
    $('.copy-shortcode').on('click', function() {
        const $button = $(this);
        const originalText = $button.text();
        
        // Show feedback
        $button.text('Copied!');
        
        // Reset button text after 2 seconds
        setTimeout(function() {
            $button.text(originalText);
        }, 2000);
    });
}); 