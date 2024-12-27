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
                        console.error('Response is not a valid JSON object:', response);
                        processNextImage(ids, index + 1);
                        return;
                    }

                    if (response.success === true && response.data.metadata && response.data.metadata.error) {
                        showSubscriptionPrompt(
                            response.data.metadata.error,
                            response.data.metadata.message,
                            response.data.metadata.ad_url
                        );
                        return; 
                    } else if (response.success === false && response.error === 'Usage limit reached. Please upgrade your subscription.') {
                        showSubscriberLimitPrompt(response.error, response.limit);
                        $('#bulk_generate_metadata_button').attr('disabled', false).text('Generate Metadata for Media Library');
                        return;
                    } else if (response.success === true && response.data && response.data.metadata) {
                        $('#bulk_generate_status').append(
                            '<p>' + response.data.metadata.title + ' - <span style="color:green;">Done</span></p>'
                        );
                    } else {
                        $('#bulk_generate_status').append('<p>Skipped ' + imageId + ' (Already has metadata or error).</p>');
                    }

                    processNextImage(ids, index + 1);
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    $('#bulk_generate_status').append('<p>Error processing ID ' + imageId + ': ' + error + '</p>');
                    processNextImage(ids, index + 1);
                }
            });
        }

        // Handle metadata generation for a single image
        $(document).on('click', '#generate_metadata_button', function(e) {
            e.preventDefault();

            const selectedFields = oneclick_images_admin_vars.selected_fields;
            const button = $(this);
            const imageId = button.data('image-id');

            if (!imageId) {
                console.error('No Image ID found. Aborting metadata generation.');
                return;
            }

            button.attr('disabled', true).text('Generating...');

            $.ajax({
                url: oneclick_images_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'oneclick_images_generate_metadata',
                    nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                    image_id: imageId,
                },
                beforeSend: function() {
                },
                success: function(response) {
                    if (response.success) {
                        const metadataWrapper = response.data.metadata;

                        if (metadataWrapper.success === true) {
                            updateMetadataFields(metadataWrapper.metadata, selectedFields);
                        } else {
                            showSubscriptionPrompt(
                                metadataWrapper.error,
                                metadataWrapper.message,
                                metadataWrapper.ad_url
                            );
                        }
                    } else {
                        console.error('Response indicates failure:', response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    console.debug('XHR Object:', xhr);
                },
                complete: function() {
                    button.attr('disabled', false).text('Generate Metadata');
                },
            });
        });

        function updateMetadataFields(metadata, selectedFields) {
            try {
                if (selectedFields.alt_text) {
                    const altInput = $('#attachment-details-two-column-alt-text');
                    altInput.val(metadata.alt_text || '').trigger('change');
                }

                if (selectedFields.title) {
                    const titleInput = $('#attachment-details-two-column-title');
                    titleInput.val(metadata.title || '').trigger('change');
                }

                if (selectedFields.caption) {
                    const captionInput = $('#attachment-details-two-column-caption');
                    captionInput.val(metadata.caption || '').trigger('change');
                }

                if (selectedFields.description) {
                    const descriptionInput = $('#attachment-details-two-column-description');
                    descriptionInput.val(metadata.description || '').trigger('change');
                }
            } catch (err) {
                console.error('Error updating metadata fields:', err);
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

        window.showSubscriptionPrompt = showSubscriptionPrompt;

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

        $('#validate_license_button').on('click', function() {
            validateLicense();
        });
    });
})(jQuery);