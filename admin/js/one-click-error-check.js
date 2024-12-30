(function($) {
    'use strict';

    $(document).ready(function() {

        /**
         * Event handler for the close button in the subscription modal.
         */
        $(document).on('click', '.occ-subscription-modal-close', function() {
            closeModalAndRemoveTransient();
        });

        /**
         * Event handler for clicking on the modal overlay to close the modal.
         */
        $(document).on('click', '.occ-subscription-modal-overlay', function() {
            closeModalAndRemoveTransient();
        });

        /**
         * Function to close the subscription modal and remove the transient on the server.
         */
        function closeModalAndRemoveTransient() {
            $('#occ-subscription-modal').fadeOut(); // Hide the modal with fade-out animation.

            // Send an AJAX request to remove the transient on the server.
            $.ajax({
                url: oneclick_images_admin_vars.ajax_url,
                method: 'POST',
                data: {
                    action: 'remove_image_error_transient',
                    nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                },
                success: function(response) {
                    if (response.success) {
                        console.log('Transient removed successfully.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error removing transient:', error);
                }
            });
        }

        /**
         * Polling mechanism to check for image errors periodically.
         * If an error is detected, it displays the subscription prompt modal.
         */
        let pollingInterval = setInterval(function() {
            $.ajax({
                url: oneclick_images_admin_vars.ajax_url,
                method: 'POST',
                data: {
                    action: 'check_image_error',
                    nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                },
                success: function(response) {
                    if (response.success) {
                        showSubscriptionPrompt(
                            'API Limit Reached',          // Title of the modal.
                            response.data.message,       // Message content.
                            response.data.ad_url         // URL for upgrade options.
                        );

                        // Stop polling once the modal is displayed.
                        clearInterval(pollingInterval);
                    }
                },
                error: function(xhr, status, error) {
                    // Optional: Log errors if needed for debugging.
                    console.error('Error checking image error:', error);
                }
            });
        }, 5000); // Poll every 5 seconds.
    });
})(jQuery);