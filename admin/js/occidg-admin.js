/**
 * Admin JavaScript functionality for OneClickContent Image Details plugin.
 * Reduced version retaining license functions and unique non-duplicated features.
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

    $(document).ready(function($) {

        $('#close-first-time-modal').on('click', function() {
            $.ajax({
                url: occidg_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'occidg_dismiss_first_time',
                    dismiss_first_time_nonce: occidg_admin_vars.dismiss_first_time_nonce
                },
                success: function(response) {
                    $('#occidg-first-time-modal').fadeOut();
                }
            });
        });

        /**
         * Handles metadata generation for a specific image when the button is clicked.
         */
        $(document).on('click', '#generate_metadata_button', function (e) {
            e.preventDefault();

            const button = $(this);
            const imageId = button.data('image-id');

            if (!imageId) {
                showGeneralErrorModal('An unexpected error occurred. Please try again.');
                button.attr('disabled', false).text('Generate Metadata');
                return;
            }

            // Base UTM parameters for tracking
            const utmBase = {
                utm_source: 'occidg_plugin',
                utm_medium: 'modal',
                utm_campaign: 'single_image_generation'
            };
            const addUTMParams = (baseUrl) => {
                const params = new URLSearchParams(utmBase);
                return `${baseUrl}?${params.toString()}`;
            };
            const baseSubscriptionUrl = "https://oneclickcontent.com/image-detail-generator/";

            // Check trial/license status upfront
            if (occidg_admin_vars.trial_expired) {
                showSubscriptionPrompt(
                    'Free Trial Expired',
                    'Your free trial has ended. Enter a license key to continue generating metadata.',
                    addUTMParams(baseSubscriptionUrl)
                );
                button.attr('disabled', false).text('Generate Metadata');
                return;
            }
            if (occidg_admin_vars.is_valid_license && occidg_admin_vars.usage.remaining_count <= 0) {
                showSubscriptionPrompt(
                    'Usage Limit Reached',
                    'You’ve used all your credits. Purchase more or enter a new license key.',
                    addUTMParams(baseSubscriptionUrl)
                );
                button.attr('disabled', false).text('Generate Metadata');
                return;
            }

            // Disable the button and show processing text
            button.attr('disabled', true).text('Generating...');

            $.ajax({
                url: occidg_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'occidg_generate_metadata',
                    nonce: occidg_admin_vars.occidg_ajax_nonce,
                    image_id: imageId,
                },
                success: function (response) {
                    if (typeof response !== 'object') {
                        showGeneralErrorModal('An unexpected error occurred. Please try again.');
                        button.attr('disabled', false).text('Generate Metadata');
                        return;
                    }

                    if (response.success && response.data && response.data.metadata) {
                        // Successful metadata generation
                        const metadata = response.data.metadata;
                        updateMetadataFields(metadata);

                        // Removed trial usage update so the button is always re-enabled.
                        button.attr('disabled', false).text('Generate Metadata');
                    } else if (!response.success && response.data && response.data.error) {
                        // Handle errors
                        const error = response.data.error;
                        if (error.includes('Free trial limit reached')) {
                            showSubscriptionPrompt(
                                'Free Trial Limit Reached',
                                response.data.message || 'Upgrade your subscription to access unlimited features.',
                                response.data.ad_url ? addUTMParams(response.data.ad_url) : addUTMParams(baseSubscriptionUrl)
                            );
                            occidg_admin_vars.trial_expired = true;
                            // Button stays disabled when trial expires
                        } else if (error.includes('Usage limit reached')) {
                            showSubscriptionPrompt(
                                'Usage Limit Reached',
                                response.data.message || 'Purchase more credits or enter a new license key.',
                                response.data.ad_url ? addUTMParams(response.data.ad_url) : addUTMParams(baseSubscriptionUrl)
                            );
                            button.attr('disabled', false).text('Generate Metadata');
                        } else if (error.startsWith('Image validation failed')) {
                            showImageRejectionModal(error);
                            button.attr('disabled', false).text('Generate Metadata');
                        } else if (error.startsWith('No metadata fields require generation')) {
                            showGeneralErrorModal('The image already has all metadata fields filled, and "Override Metadata" is disabled.');
                            button.attr('disabled', false).text('Generate Metadata');
                        } else if (error.includes('license')) {
                            showSubscriptionPrompt(
                                'Invalid License',
                                error || 'Please enter a valid license key to continue.',
                                addUTMParams(baseSubscriptionUrl)
                            );
                            button.attr('disabled', false).text('Generate Metadata');
                        } else {
                            showGeneralErrorModal(error || 'An unexpected error occurred.');
                            button.attr('disabled', false).text('Generate Metadata');
                        }
                    } else {
                        showGeneralErrorModal('An unexpected error occurred.');
                        button.attr('disabled', false).text('Generate Metadata');
                    }
                },
                error: function () {
                    showGeneralErrorModal('An error occurred while processing the request. Please try again.');
                    button.attr('disabled', false).text('Generate Metadata');
                }
            });
        });

        /**
         * Updates metadata fields in the UI.
         *
         * @param {Object} metadata The metadata object containing title, description, alt_text, and caption.
         */
        function updateMetadataFields(metadata) {
            try {
                const selectedFields = occidg_admin_vars.selected_fields || {
                    alt_text: true,
                    title: true,
                    caption: true,
                    description: true,
                };

                // Update alt text field.
                const altInputLibrary = $('#attachment-details-two-column-alt-text');
                const altInputSingle = $('#attachment-details-alt-text');
                if (metadata.alt_text && selectedFields.alt_text) {
                    if (altInputLibrary.length) {
                        altInputLibrary.val(metadata.alt_text).trigger('change');
                    }
                    if (altInputSingle.length) {
                        altInputSingle.val(metadata.alt_text).trigger('change');
                    }
                }

                // Update title field.
                const titleInputLibrary = $('#attachment-details-two-column-title');
                const titleInputSingle = $('#attachment-details-title');
                if (metadata.title && selectedFields.title) {
                    if (titleInputLibrary.length) {
                        titleInputLibrary.val(metadata.title).trigger('change');
                    }
                    if (titleInputSingle.length) {
                        titleInputSingle.val(metadata.title).trigger('change');
                    }
                }

                // Update caption field.
                const captionInputLibrary = $('#attachment-details-two-column-caption');
                const captionInputSingle = $('#attachment-details-caption');
                if (metadata.caption && selectedFields.caption) {
                    if (captionInputLibrary.length) {
                        captionInputLibrary.val(metadata.caption).trigger('change');
                    }
                    if (captionInputSingle.length) {
                        captionInputSingle.val(metadata.caption).trigger('change');
                    }
                }

                // Update description field.
                const descriptionInputLibrary = $('#attachment-details-two-column-description');
                const descriptionInputSingle = $('#attachment-details-description');
                if (metadata.description && selectedFields.description) {
                    if (descriptionInputLibrary.length) {
                        descriptionInputLibrary.val(metadata.description).trigger('change');
                    }
                    if (descriptionInputSingle.length) {
                        descriptionInputSingle.val(metadata.description).trigger('change');
                    }
                }

                $('input, textarea').trigger('change').trigger('input');
            } catch (err) {
                // Silent catch for robustness; errors are not displayed to user.
            }
        }

        // Expose updateMetadataFields globally.
        window.updateMetadataFields = updateMetadataFields;


        /**
         * Displays a subscription prompt modal for usage limits.
         *
         * @param {string} error   The error message from the server.
         * @param {string} message Additional message from the server.
         * @param {string} url     The base URL for subscription plans.
         */
        function showSubscriptionPrompt(error, message, url) {
            const licenseStatus = occidg_admin_vars.license_status;
            let subscriptionOptionsHtml = '';

            // Base UTM parameters
            const utmBase = {
                utm_source: 'occidg_plugin',
                utm_medium: 'modal',
                utm_campaign: error.includes('Free trial') ? 'free_trial_limit' : 'usage_limit'
            };

            // Build URL with UTM parameters
            const addUTMParams = (baseUrl, plan) => {
                const params = new URLSearchParams({
                    ...utmBase,
                    utm_content: plan ? `${plan}_plan` : 'additional_credits'
                });
                return `${baseUrl}?${params.toString()}`;
            };

            if ('active' === licenseStatus) {
                subscriptionOptionsHtml = `
                    <div class="occ-subscription-extra">
                        <p>You've reached your image limit. Need more? Buy additional credits:</p>
                        <a href="${addUTMParams('https://oneclickcontent.com/buy-more-image-credits/', '100_credits')}" target="_blank" class="occ-subscription-button">Get 100 More Images for $6.99</a>
                    </div>
                `;
            } else {
                subscriptionOptionsHtml = `
                    <div class="occ-subscription-options">
                        <div class="occ-subscription-tier">
                            <h3>Growth Plan</h3>
                            <p>100 Images</p>
                            <p class="plan-description">Suitable for small projects and blogs.</p>
                            <strong>$4.99/month</strong>
                            <a href="${addUTMParams(url, 'growth')}" target="_blank" class="occ-subscription-button">Choose Growth</a>
                        </div>
                        <div class="occ-subscription-tier">
                            <h3>Business Plan</h3>
                            <p>500 Images</p>
                            <p class="plan-description">Ideal for small to medium businesses.</p>
                            <strong>$19.99/month</strong>
                            <a href="${addUTMParams(url, 'business')}" target="_blank" class="occ-subscription-button">Choose Business</a>
                        </div>
                        <div class="occ-subscription-tier most-popular">
                            <h3>Pro Plan <span class="badge">Most Popular</span></h3>
                            <p>1,000 Images</p>
                            <p class="plan-description">Most popular choice for professionals.</p>
                            <strong>$29.99/month</strong>
                            <a href="${addUTMParams(url, 'pro')}" target="_blank" class="occ-subscription-button primary">Choose Pro</a>
                        </div>
                        <div class="occ-subscription-tier">
                            <h3>Premium Plan</h3>
                            <p>3,000 Images</p>
                            <p class="plan-description">High-volume users and agencies.</p>
                            <strong>$89.99/month</strong>
                            <a href="${addUTMParams(url, 'premium')}" target="_blank" class="occ-subscription-button">Choose Premium</a>
                        </div>
                        <div class="occ-subscription-tier">
                            <h3>Elite Plan</h3>
                            <p>5,000 Images</p>
                            <p class="plan-description">Enterprise-level usage.</p>
                            <strong>$129.00/month</strong>
                            <a href="${addUTMParams(url, 'elite')}" target="_blank" class="occ-subscription-button">Choose Elite</a>
                        </div>
                    </div>
                `;
            }

            const modalHtml = `
                <div id="occ-subscription-modal" class="occ-subscription-modal" role="dialog" aria-labelledby="subscription-modal-title">
                    <div class="occ-subscription-modal-overlay"></div>
                    <div class="occ-subscription-modal-content" tabindex="0">
                        <span class="occ-subscription-modal-close dashicons dashicons-no" aria-label="Close"></span>
                        <h2 id="subscription-modal-title"><span class="dashicons dashicons-lock"></span> ${error.includes('Free trial') ? 'Your Free Trial Has Ended' : 'Usage Limit Reached'}</h2>
                        <p>${$( '<div/>' ).text(message).html()}</p>
                        ${subscriptionOptionsHtml}
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
                if ('Escape' === e.key) {
                    modal.fadeOut(function() {
                        $(this).remove();
                    });
                }
            });
        }

        /**
         * Display a saving message near the input label.
         *
         * @param {HTMLElement} element The input element triggering the message.
         * @param {string}      message The message to display.
         * @param {string}      type    The type of message ('success', 'error', or 'info').
         */
        function showSavingMessage(element, message, type) {
            let $label = null;

            if ($(element).hasClass('metadata-field-checkbox')) {
                $label = $(element).next('label');
            } else if ($(element).next('label').length) {
                $label = $(element).next('label');
            } else if ($(element).closest('tr').length) {
                $label = $(element).closest('tr').find('th label');
            } else if ($(element).closest('th').length) {
                $label = $(element).closest('th').find('label');
            } else if ($(element).attr('id')) {
                $label = $(`label[for="${$( element ).attr( 'id' )}"]`);
            }

            if (!$label || !$label.length) {
                return;
            }

            const messageClass = 'success' === type ? 'saving-success' : 'saving-error';
            $label.find('.saving-message').remove();
            $label.append(`<span class="saving-message ${messageClass}">${message}</span>`);

            if ('Saving...' !== message) {
                setTimeout(() => {
                    $label.find('.saving-message').fadeOut(400, function() {
                        $(this).remove();
                    });
                }, 2000);
            }
        }

        /**
         * Save settings asynchronously via AJAX and return a Promise.
         *
         * @param {HTMLElement} element The input element triggering the save.
         * @return {Promise} A Promise resolving on success or rejecting on failure.
         */
        function promiseSaveSettings(element) {
            return new Promise((resolve, reject) => {
                // Explicitly collect all checkbox states
                const metadataFields = {};
                $('.metadata-field-checkbox').each(function() {
                    const key = $(this).attr('name').replace('occidg_metadata_fields[', '').replace(']', '');
                    metadataFields[key] = $(this).is(':checked') ? '1' : '0';
                });

                // Log the collected checkbox states
                console.log('[OCCIDG] Checkbox states:', metadataFields);

                // Collect other form fields
                const formData = {
                    'occidg_auto_add_details': $('#occidg_auto_add_details').is(':checked') ? '1' : '0',
                    'occidg_override_metadata': $('#occidg_override_metadata').is(':checked') ? '1' : '0',
                    'occidg_language': $('#occidg_language').val(),
                    'occidg_license_key': $('#occidg_license_key').val(),
                    'occidg_metadata_fields': metadataFields
                };

                // Convert to URL-encoded string
                const serializedData = $.param(formData);

                // Log the serialized data being sent
                console.log('[OCCIDG] Serialized data:', serializedData);

                showSavingMessage(element, ' Saving...', 'info');

                $.ajax({
                    url: occidg_admin_vars.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'occidg_save_settings',
                        _ajax_nonce: occidg_admin_vars.occidg_ajax_nonce,
                        settings: serializedData
                    },
                    success: function(response) {
                        if (response.success) {
                            showSavingMessage(element, ' Saved!', 'success');
                            resolve();
                        } else {
                            showSavingMessage(element, 'Failed to Save!', 'error');
                            reject(new Error('Failed to save settings'));
                        }
                    },
                    error: function(xhr, status, error) {
                        showSavingMessage(element, 'Error occurred!', 'error');
                        reject(error);
                    }
                });
            });
        }
        
        /**
         * Update the license status UI based on the response.
         *
         * @param {string} status  The license status ('active', 'invalid', etc.).
         * @param {string} message The message to display.
         */
        function updateLicenseStatusUI(status, message) {
            const $statusLabel = $('#license_status_label');
            const $statusMessage = $('#license_status_message');
            const statusColor = 'active' === status ? 'green' : 'validating' === status ? 'orange' : 'red';
            const statusText = 'active' === status ? 'Valid' : 'invalid' === status ? 'Invalid' : 'Checking...';

            $statusLabel.text(statusText).css('color', statusColor);
            $statusMessage.text(message).css('color', statusColor);
        }

        /**
         * Fetch the current license status via AJAX.
         */
        function fetchLicenseStatus() {
            updateLicenseStatusUI('validating', 'Checking license status...');

            $.ajax({
                url: occidg_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'occidg_get_license_status',
                    nonce: occidg_admin_vars.occidg_ajax_nonce,
                },
                success: function(response) {
                    if (response.success) {
                        const status = 'active' === response.data.status ? 'active' : 'invalid';
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

        /**
         * Display a modal with server-provided HTML content for usage limits.
         *
         * @param {string} htmlContent The HTML content to display in the modal.
         */
        function showLimitPrompt(htmlContent) {
            const modalHtml = `
                <div id="occ-limit-modal" class="occ-modal" role="dialog" aria-labelledby="limit-modal-title">
                    <div class="occ-modal-overlay"></div>
                    <div class="occ-modal-content" tabindex="0">
                        <span class="occ-modal-close dashicons dashicons-no" aria-label="Close"></span>
                        <div class="occ-modal-body">${htmlContent}</div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);

            const modal = $('#occ-limit-modal');
            modal.removeAttr('aria-hidden');
            modal.find('.occ-modal-content').focus();
            modal.fadeIn();

            modal.find('.occ-modal-close, .occ-modal-overlay').on('click', function() {
                modal.fadeOut(function() {
                    $(this).remove();
                });
            });

            $(document).on('keydown', function(e) {
                if ('Escape' === e.key) {
                    modal.fadeOut(function() {
                        $(this).remove();
                    });
                }
            });
        }

        /**
         * Display an image rejection modal with a specified message.
         *
         * @param {string} message The error message to display.
         */
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

            modal.find('.occ-modal-close, .occ-modal-overlay, .occ-close-modal').on('click', function() {
                modal.fadeOut(function() {
                    $(this).remove();
                });
            });

            $(document).on('keydown', function(e) {
                if ('Escape' === e.key) {
                    modal.fadeOut(function() {
                        $(this).remove();
                    });
                }
            });
        }

        /**
         * Display a general error modal with a specified message.
         *
         * @param {string} message The error message to display.
         */
        function showGeneralErrorModal(message) {
            const modalHtml = `
                <div id="occ-general-error-modal" class="occ-modal" role="dialog" aria-labelledby="general-error-modal-title">
                    <div class="occ-modal-overlay"></div>
                    <div class="occ-modal-content" tabindex="0">
                        <span class="occ-modal-close dashicons dashicons-no" aria-label="Close"></span>
                        <h2 id="general-error-modal-title"><span class="dashicons dashicons-warning"></span> Action Skipped</h2>
                        <p>${message}</p>
                        <p>
                            <a href="/wp-admin/options-general.php?page=occidg-settings" class="occ-modal-link">
                                Click here to update your settings.
                            </a>
                        </p>
                        <button class="occ-modal-button occ-close-modal">OK</button>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);
            const modal = $('#occ-general-error-modal');

            modal.fadeIn();

            modal.find('.occ-modal-close, .occ-modal-overlay, .occ-close-modal').on('click', function() {
                modal.fadeOut(() => modal.remove());
            });

            $(document).on('keydown', function(e) {
                if ('Escape' === e.key) {
                    modal.fadeOut(() => modal.remove());
                }
            });
        }

        // Expose modal functions globally.
        window.showImageRejectionModal = showImageRejectionModal;
        window.showGeneralErrorModal = showGeneralErrorModal;
        window.showLimitPrompt = showLimitPrompt;
        window.showSubscriptionPrompt = showSubscriptionPrompt;

        // Event listeners for settings changes
        $('#occidg_settings_form input, #occidg_settings_form select').on('change', function() {
            const element = this;
            promiseSaveSettings(element).catch(() => {
                // Silent catch for robustness
            });
        });

        $('#occidg_license_key').on('keyup', function() {
            const element = this;
            promiseSaveSettings(element)
                .then(() => fetchLicenseStatus())
                .catch(() => {
                    // Silent catch for robustness.
                });
        });

        // Trigger license validation on button click.
        $('#validate_license_button').on('click', function() {
            fetchLicenseStatus();
        });
    });
})(jQuery);