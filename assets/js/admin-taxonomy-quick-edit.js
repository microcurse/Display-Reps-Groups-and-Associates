jQuery(document).ready(function($) {
    // Ensure inlineEditTax is available
    if (typeof inlineEditTax === 'undefined') {
        return;
    }

    // Store the original WordPress function
    var wpInlineEditTax = inlineEditTax.edit;

    // Override the function
    inlineEditTax.edit = function(id) {
        // Call the original WordPress edit function
        wpInlineEditTax.apply(this, arguments);

        // Get the term ID
        var term_id = 0;
        if (typeof(id) === 'object') { // If id is the form element
            term_id = parseInt(this.getId(id));
        } else { // If id is just the term_id
            term_id = parseInt(id);
        }

        if (term_id > 0) {
            // Find the table row for this term
            var $row = $('#tag-' + term_id);
            
            // Get the SVG ID value from our hidden span in the custom column
            // WordPress adds 'column-' prefix to the class of the <td> based on the column key.
            var svg_id = $row.find('.column-_rep_svg_target_id .hidden-svg-id').text();

            // Find the input field in the Quick Edit form and set its value
            // The Quick Edit form for a term has an ID like 'edit-TERM_ID'
            var $quick_edit_row = $('#edit-' + term_id);
            $quick_edit_row.find('input[name="_rep_svg_target_id"]').val(svg_id);
        }
        
        // WordPress recommends returning false when overriding inlineEditTax.edit
        return false; 
    };
}); 