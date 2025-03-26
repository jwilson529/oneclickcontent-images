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
                url: occidg_admin_vars.ajax_url,
                method: 'POST',
                data: {
                    action: 'occidg_remove_image_error_transient',
                    nonce: occidg_admin_vars.occidg_ajax_nonce
                }
            });
        }
    });

})(jQuery);