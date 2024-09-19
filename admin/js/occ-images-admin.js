(function($) {
    'use strict';
    jQuery(document).ready(function($) {
        $(document).on('click', '#generate_metadata_button', function(e) {
            e.preventDefault();
            var button = $(this);
            var imageId = button.data('image-id');

            // Disable the button to prevent multiple clicks.
            button.attr('disabled', true).text('Generating...');

            $.ajax({
                url: occ_images_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'occ_images_generate_metadata',
                    nonce: occ_images_admin_vars.occ_images_ajax_nonce,
                    image_id: imageId
                },
                success: function(response) {
                    if (response.success) {
                        alert('Metadata generated successfully.');

                        // Get the attachment model
                        var attachment = wp.media.attachment.get(imageId);

                        // Fetch the updated data from the server
                        attachment.fetch({
                            success: function() {
                                // Re-select the attachment to update the details view
                                var selection = wp.media.frame.state().get('selection');
                                if (selection) {
                                    selection.reset(attachment);
                                }
                            }
                        });

                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('An unexpected error occurred.');
                },
                complete: function() {
                    // Re-enable the button.
                    button.attr('disabled', false).text('Generate Metadata');
                }
            });
        });
    });
})(jQuery);
