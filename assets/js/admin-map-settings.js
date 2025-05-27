jQuery(document).ready(function($) {
    function handleMediaUpload(buttonId, inputId) {
        $('#' + buttonId).on('click', function(e) {
            e.preventDefault();
            var button = $(this);
            var input = $('#' + inputId);

            var frame = wp.media({
                title: 'Select or Upload SVG',
                button: {
                    text: 'Use this SVG'
                },
                library: {
                    type: 'image/svg+xml' // Only allow SVG files
                },
                multiple: false
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                input.val(attachment.url);
            });

            frame.open();
        });
    }

    handleMediaUpload('upload_local_svg_button', 'rep_group_local_svg');
    handleMediaUpload('upload_international_svg_button', 'rep_group_international_svg');
});
