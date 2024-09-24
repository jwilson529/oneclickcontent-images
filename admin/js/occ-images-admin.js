(function($) {
    'use strict';

    jQuery(document).ready(function($) {

        // Handle metadata generation when the button is clicked inside the Media Library modal
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
                        // Get user-selected metadata fields
                        var selectedFields = occ_images_admin_vars.selected_fields;

                        // Conditionally update the media modal fields based on user settings
                        if (selectedFields.alt_text) {
                            $('#attachment-details-two-column-alt-text').val(response.data.metadata.alt_text);
                        }
                        if (selectedFields.title) {
                            $('#attachment-details-two-column-title').val(response.data.metadata.title);
                        }
                        if (selectedFields.caption) {
                            $('#attachment-details-two-column-caption').val(response.data.metadata.caption);
                        }
                        if (selectedFields.description) {
                            $('#attachment-details-two-column-description').val(response.data.metadata.description);
                        }
                    }
                },
                complete: function() {
                    // Re-enable the button after the request completes
                    button.attr('disabled', false).text('Generate Metadata');
                }
            });
        });

        // Handle bulk metadata generation when the button is clicked
        $(document).on('click', '#bulk_generate_metadata_button', function(e) {
            e.preventDefault();

            // Confirm the action
            if (!confirm('Are you sure you want to generate metadata for all images in the media library? This might take some time.')) {
                return;
            }

            // Disable the button to prevent duplicate clicks
            var button = $(this);
            button.attr('disabled', true).text('Generating...');

            // Clear the status div
            $('#bulk_generate_status').empty();

            // Fetch all attachment IDs via AJAX (we will loop over them)
            $.ajax({
                url: occ_images_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'occ_images_get_all_media_ids', // This action retrieves all media IDs
                    nonce: occ_images_admin_vars.occ_images_ajax_nonce
                },
                success: function(response) {
                    if (response.success && response.data.ids.length > 0) {
                        // Process each image one by one
                        processNextImage(response.data.ids, 0);
                    } else {
                        $('#bulk_generate_status').append('<p>No media items found.</p>');
                        button.attr('disabled', false).text('Generate Metadata for Media Library');
                    }
                }
            });
        });

        // Process each image one by one
        function processNextImage(ids, index) {
            if (index >= ids.length) {
                // All images processed
                $('#bulk_generate_status').append('<p>All media items processed.</p>');
                $('#bulk_generate_metadata_button').attr('disabled', false).text('Generate Metadata for Media Library');
                return;
            }

            var imageId = ids[index];

            // Send an AJAX request to generate metadata for this image
            $.ajax({
                url: occ_images_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'occ_images_generate_metadata', // Use your existing action
                    nonce: occ_images_admin_vars.occ_images_ajax_nonce,
                    image_id: imageId
                },
                success: function(response) {
                    if (response.success) {
                        // Display status for this image
                        $('#bulk_generate_status').append('<p>' + response.data.metadata.title + ' - <span style="color:green;">Done</span></p>');
                    } else {
                        $('#bulk_generate_status').append('<p>Skipped ' + imageId + ' (Already has metadata)</p>');
                    }

                    // Process the next image
                    processNextImage(ids, index + 1);
                }
            });
        }
    });

})(jQuery);
