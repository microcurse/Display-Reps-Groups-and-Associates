jQuery(document).ready(function($) {
    $('.rep-group-map-container path').on('click', function() {
        const regionId = $(this).attr('id');
        const $infoDiv = $('.rep-group-info');
        
        $.ajax({
            url: repGroupMap.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_region_info',
                region_id: regionId
            },
            success: function(response) {
                $infoDiv.html(response);
            }
        });
    });
}); 