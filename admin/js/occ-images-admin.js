(function($) {
    'use strict';

    jQuery(document).ready(function($) {

        // Listen for clicks on the 'Generate Metadata' button
        $(document).on('click', '#generate_metadata_button', function(e) {
            e.preventDefault();

            // Store button and image ID for later use
            var button = $(this);
            var imageId = button.data('image-id');

            // Disable the button and change the text to indicate processing
            button.attr('disabled', true).text('Generating...');

            // Send an AJAX request to generate metadata for the image
            $.ajax({
                url: occ_images_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'occ_images_generate_metadata', // WordPress action hook
                    nonce: occ_images_admin_vars.occ_images_ajax_nonce, // Security nonce
                    image_id: imageId // ID of the image for which metadata is generated
                },
                success: function(response) {
                    // Check if the request was successful
                    if (response.success) {
                        // Update the media modal fields with the generated metadata
                        $('#attachment-details-two-column-alt-text').val(response.data.metadata.alt_text);
                        $('#attachment-details-two-column-title').val(response.data.metadata.title);
                        $('#attachment-details-two-column-caption').val(response.data.metadata.caption);
                        $('#attachment-details-two-column-description').val(response.data.metadata.description);
                    } 
                },
                complete: function() {
                    // Re-enable the button after the request completes
                    button.attr('disabled', false).text('Generate Metadata');
                }
            });
        });
    });

})(jQuery);