/**
 * Error checking and subscription modal handling for OneClickContent Image Details plugin.
 *
 * Handles periodic checks for API errors (e.g., usage limit reached) and displays a subscription
 * modal to prompt the user to upgrade. Also manages closing the modal and removing server-side transients.
 *
 * @package    One_Click_Images
 * @subpackage One_Click_Images/admin/js
 * @author     OneClickContent <support@oneclickcontent.com>
 * @since      1.0.0
 * @copyright  2025 OneClickContent
 * @license    GPL-2.0+
 * @link       https://oneclickcontent.com
 */
(function($) {
    'use strict';

    /**
     * Initialize the error checking and modal functionality when the document is ready.
     */
    $(document).ready(function() {
        // Event delegation for closing the subscription modal via the close button.
        $(document).on('click', '.occ-subscription-modal-close', closeModalAndRemoveTransient);

        // Event delegation for closing the subscription modal by clicking the overlay.
        $(document).on('click', '.occ-subscription-modal-overlay', closeModalAndRemoveTransient);

        /**
         * Close the subscription modal and remove the server-side transient.
         *
         * Fades out the modal and sends an AJAX request to remove the transient on the server.
         */
        function closeModalAndRemoveTransient() {
            $('#occ-subscription-modal').fadeOut();

            $.ajax({
                url: oneclick_images_admin_vars.ajax_url,
                method: 'POST',
                data: {
                    action: 'oneclick_remove_image_error_transient', // Corrected action name to match PHP handler.
                    nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce
                },
                success: function(response) {
                    if (response.success) {
                        // eslint-disable-next-line no-console
                        console.log('Transient removed successfully.');
                    }
                },
                error: function(xhr, status, error) {
                    // eslint-disable-next-line no-console
                    console.error('Error removing transient:', error);
                }
            });
        }

        /**
         * Display a subscription prompt modal with a given title, message, and URL.
         *
         * @param {string} title  The title of the modal.
         * @param {string} message The message content to display in the modal.
         * @param {string} adUrl   The URL linking to upgrade options.
         */
        function showSubscriptionPrompt(title, message, adUrl) {
            // Remove any existing modal to prevent duplicates.
            $('#occ-subscription-modal').remove();

            // Create and append the modal HTML.
            const modalHtml = `
                <div id="occ-subscription-modal" class="occ-subscription-modal">
                    <div class="occ-subscription-modal-overlay"></div>
                    <div class="occ-subscription-modal-content">
                        <span class="occ-subscription-modal-close">&times;</span>
                        <h2>${title}</h2>
                        <p>${message}</p>
                        <a href="${adUrl}" target="_blank" class="button button-primary">
                            Upgrade Now
                        </a>
                    </div>
                </div>
            `;
            $('body').append(modalHtml);

            // Show the modal with a fade-in effect.
            $('#occ-subscription-modal').fadeIn();
        }

        /**
         * Polling mechanism to periodically check for image upload API errors.
         *
         * If an error (e.g., usage limit reached) is detected, displays a subscription prompt modal
         * and stops polling.
         */
        const pollingInterval = setInterval(function() {
            $.ajax({
                url: oneclick_images_admin_vars.ajax_url,
                method: 'POST',
                data: {
                    action: 'check_image_error',
                    nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce
                },
                success: function(response) {
                    if (response.success) {
                        showSubscriptionPrompt(
                            'API Limit Reached',
                            response.data.message,
                            response.data.ad_url
                        );
                        clearInterval(pollingInterval);
                    }
                },
                error: function(xhr, status, error) {
                    // eslint-disable-next-line no-console
                    console.error('Error checking image error:', error);
                }
            });
        }, 5000); // Poll every 5 seconds.
    });

})(jQuery);