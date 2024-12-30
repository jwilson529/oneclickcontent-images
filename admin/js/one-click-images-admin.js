(function($) {
    'use strict';

    jQuery(document).ready(function($) {

        // Handle bulk metadata generation when the button is clicked
        $(document).on('click', '#bulk_generate_metadata_button', function(e) {
            e.preventDefault();

            // Confirm before proceeding
            if (!confirm('Are you sure you want to generate metadata for all images in the media library? This might take some time.')) {
                return;
            }

            const button = $(this);
            button.attr('disabled', true).text('Generating...');
            $('#bulk_generate_status').empty();

            // Fetch all attachment IDs
            $.ajax({
                url: oneclick_images_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'oneclick_images_get_all_media_ids',
                    nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce
                },
                success: function(response) {
                    if (response.success && response.data.ids.length > 0) {
                        processNextImage(response.data.ids, 0);
                    } else {
                        $('#bulk_generate_status').append('<p>No media items found.</p>');
                        button.attr('disabled', false).text('Generate Metadata for Media Library');
                    }
                }
            });
        });

        function processNextImage(ids, index) {
            if (index >= ids.length) {
                $('#bulk_generate_status').append('<p>All media items processed.</p>');
                $('#bulk_generate_metadata_button').attr('disabled', false).text('Generate Metadata for Media Library');
                return;
            }

            const imageId = ids[index];

            $.ajax({
                url: oneclick_images_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'oneclick_images_generate_metadata',
                    nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                    image_id: imageId
                },
                success: function(response) {
                    if (typeof response !== 'object') {
                        $('#bulk_generate_status').append('<p>Error: Invalid response format for ID ' + imageId + '.</p>');
                        processNextImage(ids, index + 1);
                        return;
                    }

                    if (response.success === true && response.data && response.data.metadata) {
                        const metadata = response.data.metadata;

                        if (metadata.error === 'Usage limit reached. Please upgrade your subscription or purchase more blocks.') {
                            showSubscriptionPrompt(
                                metadata.error,
                                metadata.message,
                                metadata.ad_url
                            );

                            $('#bulk_generate_metadata_button').attr('disabled', false).text('Generate Metadata for Media Library');
                            return;
                        }

                        if (metadata.error && metadata.error.startsWith('Image validation failed')) {
                            showImageRejectionModal(metadata.error);
                            $('#bulk_generate_status').append(
                                '<p>' + imageId + ' - <span style="color:orange;">Rejected: </span>' + metadata.error + '</p>'
                            );
                            processNextImage(ids, index + 1);
                            return;
                        }

                        if (metadata.success) {
                            $('#bulk_generate_status').append(
                                '<p>' + imageId + ' - <span style="color:green;">Done</span></p>'
                            );
                        } else {
                            $('#bulk_generate_status').append('<p>Skipped ' + imageId + ' (Already has metadata or error).</p>');
                        }
                    } else {
                        $('#bulk_generate_status').append('<p>Skipped ' + imageId + ' (Unexpected response).</p>');
                    }

                    processNextImage(ids, index + 1);
                },
                error: function(xhr, status, error) {
                    $('#bulk_generate_status').append('<p>Error processing ID ' + imageId + ': ' + error + '</p>');
                    processNextImage(ids, index + 1);
                }
            });
        }

        // Handle metadata generation for a specific image
        $(document).on('click', '#generate_metadata_button', function (e) {
            e.preventDefault();

            console.log('Generate Metadata button clicked.');

            // Initialize button and image ID variables
            var button = $(this);
            var imageId = button.data('image-id');
            console.log('Image ID retrieved from data attribute:', imageId);

            if (!imageId) {
                console.error('No Image ID found. Aborting metadata generation.');
                return;
            }

            // Disable button and show loading state
            button.attr('disabled', true).text('Generating...');
            console.log('Button disabled and loading state shown.');

            // Send AJAX request to generate metadata
            $.ajax({
                url: oneclick_images_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'oneclick_images_generate_metadata',
                    nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                    image_id: imageId,
                },
                beforeSend: function () {
                    console.log('AJAX request is about to be sent with data:', {
                        action: 'oneclick_images_generate_metadata',
                        nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                        image_id: imageId,
                    });
                },
                success: function (response) {
                    if (typeof response !== 'object') {
                        console.error('Invalid response format:', response);
                        showGeneralErrorModal('An unexpected error occurred. Please try again.');
                        return;
                    }

                    if (response.success === true && response.data && response.data.metadata) {
                        const metadata = response.data.metadata;

                        // Handle specific errors
                        if (metadata.error === 'Usage limit reached. Please upgrade your subscription or purchase more blocks.') {
                            console.log('Triggering subscription prompt...');
                            showSubscriptionPrompt(
                                metadata.error,
                                metadata.message,
                                metadata.ad_url
                            );
                            return;
                        }

                        if (metadata.error && metadata.error.startsWith('Image validation failed')) {
                            console.log('Triggering image rejection modal...');
                            showImageRejectionModal(metadata.error);
                            return;
                        }

                        if (metadata.error && metadata.error.startsWith('No metadata fields require generation')) {
                            console.log('Triggering general error modal for skipped metadata generation...');
                            showGeneralErrorModal('The image already has all metadata fields filled, and "Override Metadata" is disabled.');
                            return;
                        }

                        if (metadata.success) {
                            console.log('Metadata successfully generated:', metadata.metadata);
                            updateMetadataFields(metadata.metadata); // Update metadata fields
                        } else {
                            console.warn('Metadata generation skipped or unexpected issue occurred.');
                            showGeneralErrorModal('Metadata generation was skipped or an unexpected issue occurred.');
                        }
                    } else {
                        console.error('Unexpected response structure or failure:', response);
                        showGeneralErrorModal('An unexpected error occurred.');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX error:', error);
                    showGeneralErrorModal('An error occurred while processing the request. Please try again.');
                },
                complete: function () {
                    button.attr('disabled', false).text('Generate Metadata');
                    console.log('Metadata generation request complete. Button re-enabled.');
                },
            });
        });

        /**
         * Function to show the image rejection modal.
         * @param {string} message - The error message to display in the modal.
         */
        function showImageRejectionModal(message) {
            console.log('Displaying image rejection modal with message:', message);

            // Construct the modal HTML
            const modalHtml = `
                <div id="occ-image-rejection-modal" class="occ-modal" role="dialog" aria-labelledby="image-rejection-modal-title">
                    <div class="occ-modal-overlay"></div>
                    <div class="occ-modal-content" tabindex="0">
                        <span class="occ-modal-close dashicons dashicons-no" aria-label="Close"></span>
                        <h2 id="image-rejection-modal-title">
                            <span class="dashicons dashicons-warning"></span> Image Rejected
                        </h2>
                        <p>${message}</p>
                        <button class="occ-modal-button occ-close-modal">OK</button>
                    </div>
                </div>
            `;

            // Append modal to body
            $('body').append(modalHtml);

            const modal = $('#occ-image-rejection-modal');
            modal.fadeIn();

            // Attach close handlers
            modal.find('.occ-modal-close, .occ-modal-overlay, .occ-close-modal').on('click', function () {
                closeModal(modal);
            });

            // Close modal on Escape key press
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeModal(modal);
                }
            });
        }

        /**
         * Function to close the modal.
         * @param {object} modal - The modal element to close.
         */
        function closeModal(modal) {
            console.log('Closing modal...');
            modal.fadeOut(function () {
                modal.remove();
                console.log('Modal removed from DOM.');
            });
        }

        // Function to show the general error modal (if needed)
        function showGeneralErrorModal(message) {
            console.log('Displaying general error modal with message:', message);

            const modalHtml = `
                <div id="occ-general-error-modal" class="occ-modal" role="dialog" aria-labelledby="general-error-modal-title">
                    <div class="occ-modal-overlay"></div>
                    <div class="occ-modal-content" tabindex="0">
                        <span class="occ-modal-close dashicons dashicons-no" aria-label="Close"></span>
                        <h2 id="general-error-modal-title">
                            <span class="dashicons dashicons-warning"></span> Action Skipped
                        </h2>
                        <p>${message}</p>
                        <p>
                            <a href="/wp-admin/options-general.php?page=oneclickcontent-images-settings" class="occ-modal-link">
                                Click here to update your settings.
                            </a>
                        </p>
                        <button class="occ-modal-button occ-close-modal">OK</button>
                    </div>
                </div>
            `;

            // Append modal to body
            $('body').append(modalHtml);

            const modal = $('#occ-general-error-modal');
            modal.fadeIn();

            // Attach close handlers
            modal.find('.occ-modal-close, .occ-modal-overlay, .occ-close-modal').on('click', function () {
                modal.fadeOut(() => modal.remove());
            });

            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    modal.fadeOut(() => modal.remove());
                }
            });
        }

        // Ensure these functions are globally accessible
        window.showImageRejectionModal = showImageRejectionModal;
        window.showGeneralErrorModal = showGeneralErrorModal;
        window.showSubscriptionPrompt = showSubscriptionPrompt;

        function updateMetadataFields(metadata) {
            try {
                console.log('Inside updateMetadataFields. Metadata received:', metadata);
                
                // Safely get selected fields with fallback to enable all fields if undefined
                const selectedFields = oneclick_images_admin_vars.selected_fields || {
                    alt_text: true,
                    title: true,
                    caption: true,
                    description: true
                };
                
                console.log('Selected fields configuration:', selectedFields);

                // Update alt text if field exists
                const altInput = $('#attachment-details-two-column-alt-text');
                if (altInput.length && metadata.alt_text) {
                    console.log('Setting alt text to:', metadata.alt_text);
                    altInput.val(metadata.alt_text).trigger('change');
                }

                // Update title if field exists
                const titleInput = $('#attachment-details-two-column-title');
                if (titleInput.length && metadata.title) {
                    console.log('Setting title to:', metadata.title);
                    titleInput.val(metadata.title).trigger('change');
                }

                // Update caption if field exists
                const captionInput = $('#attachment-details-two-column-caption');
                if (captionInput.length && metadata.caption) {
                    console.log('Setting caption to:', metadata.caption);
                    captionInput.val(metadata.caption).trigger('change');
                }

                // Update description if field exists
                const descriptionInput = $('#attachment-details-two-column-description');
                if (descriptionInput.length && metadata.description) {
                    console.log('Setting description to:', metadata.description);
                    descriptionInput.val(metadata.description).trigger('change');
                }

                // Force WordPress to recognize the changes
                $('input, textarea').trigger('change').trigger('input');
                
            } catch (err) {
                console.error('Error updating metadata fields:', err);
                console.error('Metadata at time of error:', metadata);
            }
        }

        function showSubscriptionPrompt(error, message, url) {
            const modalHtml = `
                <div id="occ-subscription-modal" class="occ-subscription-modal" role="dialog" aria-labelledby="subscription-modal-title">
                    <div class="occ-subscription-modal-overlay"></div>
                    <div class="occ-subscription-modal-content" tabindex="0">
                        <span class="occ-subscription-modal-close dashicons dashicons-no" aria-label="Close"></span>
                        <h2 id="subscription-modal-title"><span class="dashicons dashicons-lock"></span> ${error}</h2>
                        <p>${message}</p>
                        <div class="occ-subscription-options">
                            <div class="occ-subscription-tier">
                                <h3>Small Plan</h3>
                                <p>Perfect for personal use and small projects.</p>
                                <strong>$9/month</strong>
                                <a href="${url}?plan=small" target="_blank" class="occ-subscription-button">Get Started</a>
                            </div>
                            <div class="occ-subscription-tier">
                                <h3>Medium Plan</h3>
                                <p>Ideal for growing businesses and teams.</p>
                                <strong>$19/month</strong>
                                <a href="${url}?plan=medium" target="_blank" class="occ-subscription-button">Choose Medium</a>
                            </div>
                            <div class="occ-subscription-tier">
                                <h3>Large Plan</h3>
                                <p>Best for agencies and high-volume users.</p>
                                <strong>$49/month</strong>
                                <a href="${url}?plan=large" target="_blank" class="occ-subscription-button">Go Large</a>
                            </div>
                        </div>
                        <a href="${url}" target="_blank" class="occ-subscription-modal-link">View All Plans</a>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);

            const modal = $('#occ-subscription-modal');
            const modalContent = modal.find('.occ-subscription-modal-content');
            modal.removeAttr('aria-hidden');
            modalContent.focus();
            modal.fadeIn();

            $('.occ-subscription-modal-close, .occ-subscription-modal-overlay').on('click', function() {
                modal.fadeOut(function() {
                    $(this).remove();
                });
            });

            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    modal.fadeOut(function() {
                        $(this).remove();
                    });
                }
            });
        }

        // Ensure these functions are globally accessible
        window.showImageRejectionModal = showImageRejectionModal;
        window.showSubscriptionPrompt = showSubscriptionPrompt;


        // Function to show the image rejection modal
        function showImageRejectionModal(message) {
            const modalHtml = `
                <div id="occ-image-rejection-modal" class="occ-modal" role="dialog" aria-labelledby="image-rejection-modal-title">
                    <div class="occ-modal-overlay"></div>
                    <div class="occ-modal-content" tabindex="0">
                        <span class="occ-modal-close dashicons dashicons-no" aria-label="Close"></span>
                        <h2 id="image-rejection-modal-title"><span class="dashicons dashicons-warning"></span> Image Rejected</h2>
                        <p>${message}</p>
                        <button class="occ-modal-button occ-close-modal">OK</button>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);

            const modal = $('#occ-image-rejection-modal');
            modal.removeAttr('aria-hidden');
            modal.find('.occ-modal-content').focus();
            modal.fadeIn();

            // Close modal on clicking close button, overlay, or pressing Escape
            modal.find('.occ-modal-close, .occ-modal-overlay, .occ-close-modal').on('click', function () {
                closeModal(modal);
            });

            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    closeModal(modal);
                }
            });
        }

        // Function to close and remove the modal
        function closeModal(modal) {
            modal.fadeOut(function () {
                $(this).remove();
            });
        }

        function showSubscriberLimitPrompt(error, limit) {
            const modalHtml = `
                <div id="occ-subscriber-limit-modal" class="occ-subscriber-limit-modal">
                    <div class="occ-subscriber-limit-modal-content">
                        <span class="occ-subscriber-limit-modal-close dashicons dashicons-no"></span>
                        <h2><span class="dashicons dashicons-warning"></span> ${error}</h2>
                        <p>You have reached your usage limit of ${limit} images this month. Upgrade your subscription to continue generating metadata.</p>
                        <div class="occ-subscriber-upgrade">
                            <a href="https://oneclickcontent.com/subscriber-upgrade" target="_blank" class="occ-upgrade-button">Upgrade Now</a>
                        </div>
                        <p>Contact <a href="mailto:support@oneclickcontent.com">support@oneclickcontent.com</a> for assistance.</p>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);
            $('#occ-subscriber-limit-modal').fadeIn();

            $('.occ-subscriber-limit-modal-close, #occ-subscriber-limit-modal').on('click', function(e) {
                if (e.target.id === 'occ-subscriber-limit-modal' || $(e.target).hasClass('occ-subscriber-limit-modal-close')) {
                    $('#occ-subscriber-limit-modal').fadeOut(function() {
                        $(this).remove();
                    });
                }
            });
        }

        // Function to update license status UI
        function updateLicenseStatusUI(status, message) {
            const $statusLabel = $('#license_status_label');
            const $statusMessage = $('#license_status_message');

            const statusColor = status === 'active' ? 'green' : status === 'validating' ? 'orange' : 'red';
            const statusText = status === 'active' ? 'Valid' : status === 'invalid' ? 'Invalid' : 'Checking...';

            $statusLabel.text(statusText).css('color', statusColor);
            $statusMessage.text(message).css('color', statusColor);
        }

        // Fetch current license status
        function fetchLicenseStatus() {
            updateLicenseStatusUI('validating', 'Checking license status...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_license_status',
                },
                success: function(response) {
                    if (response.success) {
                        const status = response.data.status === 'active' ? 'active' : 'invalid';
                        updateLicenseStatusUI(status, response.data.message);
                    } else {
                        updateLicenseStatusUI('invalid', response.data.message || 'Unable to fetch license status.');
                    }
                },
                error: function() {
                    updateLicenseStatusUI('error', 'An error occurred while fetching the license status.');
                },
            });
        }

        // Handle license validation
        function validateLicense() {
            updateLicenseStatusUI('validating', 'Validating license...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'validate_license',
                },
                success: function(response) {
                    if (response.success) {
                        const status = response.data.status === 'active' ? 'active' : 'invalid';
                        updateLicenseStatusUI(status, response.data.message);
                    } else {
                        updateLicenseStatusUI('invalid', response.data.message || 'License validation failed.');
                    }
                },
                error: function() {
                    updateLicenseStatusUI('error', 'An error occurred during license validation.');
                },
            });
        }

        fetchLicenseStatus();
        fetchUsageStatus();

        $('#validate_license_button').on('click', function() {
            validateLicense();
        });

        // Function to update the usage status UI
        function updateUsageStatusUI(usedCount, totalAllowed, remainingCount) {
            const usageCount = $('#usage_count');
            const usageProgress = $('#usage_progress');

            // Update the usage text
            usageCount.html(`
                <strong>Used:</strong> ${usedCount} of ${totalAllowed} images 
                (${remainingCount} remaining)
            `);

            // Update the progress bar
            const percentageUsed = (usedCount / totalAllowed) * 100;
            usageProgress.css('width', `${Math.min(percentageUsed, 100)}%`);
            usageProgress.attr('aria-valuenow', Math.min(percentageUsed, 100));
            usageProgress.text(`${Math.round(percentageUsed)}% Used`);

            // Change progress bar color based on usage
            if (percentageUsed >= 90) {
                usageProgress.removeClass('bg-success bg-warning').addClass('bg-danger'); // Danger when >90%
            } else if (percentageUsed >= 70) {
                usageProgress.removeClass('bg-success bg-danger').addClass('bg-warning'); // Warning when >70%
            } else {
                usageProgress.removeClass('bg-warning bg-danger').addClass('bg-success'); // Success when <70%
            }
        }

        // Fetch usage information via AJAX
        function fetchUsageStatus() {
            console.log('Starting fetchUsageStatus()...');

            $.ajax({
                url: ajaxurl, // Provided by WordPress
                type: 'POST',
                data: {
                    action: 'check_usage',
                    nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                },
                beforeSend: function () {
                    console.log(
                        'Sending AJAX request to check_usage with nonce:',
                        oneclick_images_admin_vars.oneclick_images_ajax_nonce
                    );
                },
                success: function (response) {
                    console.log('AJAX response received:', response);

                    if (response.success) {
                        const { used_count, usage_limit, addon_count, remaining_count } = response.data;
                        const totalAllowed = parseInt(usage_limit) + parseInt(addon_count);
                        console.log(`Usage data: Used ${used_count}, Allowed ${totalAllowed}, Remaining ${remaining_count}`);
                        updateUsageStatusUI(used_count, totalAllowed, remaining_count);
                    } else {
                        console.error('Error in AJAX response:', response.error || 'Unknown error');
                        $('#usage_count').html('<strong>Error:</strong> Unable to fetch usage information.');
                        $('#usage_progress').css('width', '0%').text('0%');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX request failed:', status, error);
                    console.debug('XHR object:', xhr);
                    $('#usage_count').html('<strong>Error:</strong> An error occurred while fetching usage information.');
                    $('#usage_progress').css('width', '0%').text('0%');
                },
                complete: function () {
                    console.log('fetchUsageStatus() completed.');
                },
            });
        }

    });
})(jQuery);