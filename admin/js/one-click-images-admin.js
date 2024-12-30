(function($) {
    'use strict';

    jQuery(document).ready(function($) {


        // Function to show the "Saving..." or "Saved!" message near the input label
        function showSavingMessage(element, message, type) {

            let $label;

            // Check if the input is in the Metadata Fields section (within a td)
            if ($(element).closest('td').length) {
                // Find the label directly after the input
                $label = $(element).next('label');
            }

            // Fallback for inputs where labels are in th
            if (!$label || $label.length === 0) {
                $label = $(element).closest('tr').find('th label');
            }

            const messageClass = type === 'success' ? 'saving-success' : 'saving-error';

            if ($label.length === 0) {
                return;
            }

            // Remove existing messages
            $label.find('.saving-message').remove();

            // Add the new message
            $label.append(
                `<span class="saving-message ${messageClass}" style="margin-left: 10px;">${message}</span>`
            );

            // Remove the message after 3 seconds if it's not "Saving..."
            if (message !== 'Saving...') {
                setTimeout(() => {
                    $label.find('.saving-message').fadeOut(400, function() {
                        $(this).remove();
                    });
                }, 3000);
            }
        }

        // Auto-save on input or select change
        $('#oneclick_images_settings_form input, #oneclick_images_settings_form select').on('change', function() {
            const element = this;
            saveSettings(element);
        });

        // Auto-save for license key on keyup
        $('#oneclick_images_license_key').on('keyup', function() {
            const element = this;
            saveSettings(element);
            fetchLicenseStatus();
        });

        // Save settings via AJAX
        function saveSettings(element) {

            const formData = $('#oneclick_images_settings_form').serialize();

            // Show "Saving..." message
            showSavingMessage(element, 'Saving...', 'info');

            $.ajax({
                url: oneclick_images_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'oneclick_images_save_settings',
                    _ajax_nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                    settings: formData,
                },
                success: function(response) {
                    if (response.success) {
                        showSavingMessage(element, 'Saved!', 'success');
                    } else {
                        showSavingMessage(element, 'Failed to Save!', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showSavingMessage(element, 'Error occurred!', 'error');
                },
            });
        }


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
                            $('#bulk_generate_status').append('<p>Skipped ' + imageId + ' (Already has details).</p>');
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

        /**
         * Handles metadata generation for a specific image when the button is clicked.
         */
        $(document).on('click', '#generate_metadata_button', function(e) {
            e.preventDefault();

            // Initialize button and image ID variables
            var button = $(this);
            var imageId = button.data('image-id');

            if (!imageId) {
                // Exit early if no image ID is found
                return;
            }

            // Disable button and show loading state
            button.attr('disabled', true).text('Generating...');

            // Send AJAX request to generate metadata
            $.ajax({
                url: oneclick_images_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'oneclick_images_generate_metadata',
                    nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                    image_id: imageId,
                },
                success: function(response) {
                    if (typeof response !== 'object') {
                        // Handle invalid response format
                        showGeneralErrorModal('An unexpected error occurred. Please try again.');
                        return;
                    }

                    if (response.success === true && response.data && response.data.metadata) {
                        const metadata = response.data.metadata;

                        // Handle specific errors
                        if (metadata.error === 'Usage limit reached. Please upgrade your subscription or purchase more blocks.') {
                            showSubscriptionPrompt(metadata.error, metadata.message, metadata.ad_url);
                            return;
                        }

                        if (metadata.error && metadata.error.startsWith('Image validation failed')) {
                            showImageRejectionModal(metadata.error);
                            return;
                        }

                        if (metadata.error && metadata.error.startsWith('No metadata fields require generation')) {
                            showGeneralErrorModal('The image already has all metadata fields filled, and "Override Metadata" is disabled.');
                            return;
                        }

                        if (metadata.success) {
                            updateMetadataFields(metadata.metadata); // Update metadata fields
                        } else {
                            showGeneralErrorModal('Metadata generation was skipped or an unexpected issue occurred.');
                        }
                    } else {
                        // Handle unexpected response structure or failure
                        showGeneralErrorModal('An unexpected error occurred.');
                    }
                },
                error: function() {
                    // Handle AJAX errors
                    showGeneralErrorModal('An error occurred while processing the request. Please try again.');
                },
                complete: function() {
                    // Re-enable the button after the request is complete
                    button.attr('disabled', false).text('Generate Metadata');
                },
            });
        });

        /**
         * Function to show the image rejection modal.
         * @param {string} message - The error message to display in the modal.
         */
        function showImageRejectionModal(message) {

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
            modal.find('.occ-modal-close, .occ-modal-overlay, .occ-close-modal').on('click', function() {
                closeModal(modal);
            });

            // Close modal on Escape key press
            $(document).on('keydown', function(e) {
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
            modal.fadeOut(function() {
                modal.remove();
            });
        }

        // Function to show the general error modal (if needed)
        function showGeneralErrorModal(message) {
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
            modal.find('.occ-modal-close, .occ-modal-overlay, .occ-close-modal').on('click', function() {
                modal.fadeOut(() => modal.remove());
            });

            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    modal.fadeOut(() => modal.remove());
                }
            });
        }

        // Ensure these functions are globally accessible
        window.showImageRejectionModal = showImageRejectionModal;
        window.showGeneralErrorModal = showGeneralErrorModal;
        window.showSubscriptionPrompt = showSubscriptionPrompt;

        /**
         * Updates metadata fields in the media library.
         *
         * @param {Object} metadata The metadata to update (alt_text, title, caption, description).
         */
        function updateMetadataFields(metadata) {
            try {
                // Safely get selected fields with fallback to enable all fields if undefined
                const selectedFields = oneclick_images_admin_vars.selected_fields || {
                    alt_text: true,
                    title: true,
                    caption: true,
                    description: true,
                };

                // Update alt text if field exists and is selected
                const altInput = $('#attachment-details-two-column-alt-text');
                if (altInput.length && metadata.alt_text && selectedFields.alt_text) {
                    altInput.val(metadata.alt_text).trigger('change');
                }

                // Update title if field exists and is selected
                const titleInput = $('#attachment-details-two-column-title');
                if (titleInput.length && metadata.title && selectedFields.title) {
                    titleInput.val(metadata.title).trigger('change');
                }

                // Update caption if field exists and is selected
                const captionInput = $('#attachment-details-two-column-caption');
                if (captionInput.length && metadata.caption && selectedFields.caption) {
                    captionInput.val(metadata.caption).trigger('change');
                }

                // Update description if field exists and is selected
                const descriptionInput = $('#attachment-details-two-column-description');
                if (descriptionInput.length && metadata.description && selectedFields.description) {
                    descriptionInput.val(metadata.description).trigger('change');
                }

                // Trigger change/input events for WordPress to recognize the updates
                $('input, textarea').trigger('change').trigger('input');
            } catch (err) {
                // Handle errors gracefully without logging
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
            modal.find('.occ-modal-close, .occ-modal-overlay, .occ-close-modal').on('click', function() {
                closeModal(modal);
            });

            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal(modal);
                }
            });
        }

        // Function to close and remove the modal
        function closeModal(modal) {
            modal.fadeOut(function() {
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

        /**
         * Fetches usage information via AJAX and updates the UI.
         */
        function fetchUsageStatus() {
            $.ajax({
                url: ajaxurl, // Provided by WordPress
                type: 'POST',
                data: {
                    action: 'check_usage',
                    nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                },
                success: function(response) {
                    if (response.success) {
                        const { used_count, usage_limit, addon_count, remaining_count } = response.data;
                        const totalAllowed = parseInt(usage_limit, 10) + parseInt(addon_count, 10);

                        // Update the UI with the usage data
                        updateUsageStatusUI(used_count, totalAllowed, remaining_count);
                    } else {
                        $('#usage_count').html('<strong>Error:</strong> Unable to fetch usage information.');
                        $('#usage_progress').css('width', '0%').text('0%');
                    }
                },
                error: function() {
                    $('#usage_count').html('<strong>Error:</strong> An error occurred while fetching usage information.');
                    $('#usage_progress').css('width', '0%').text('0%');
                },
            });
        }

    });
})(jQuery);