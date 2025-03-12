/**
 * Admin JavaScript functionality for OneClickContent Image Details plugin.
 *
 * Handles settings saving, bulk metadata generation, modal displays, and UI updates
 * for the admin interface of the plugin.
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
     * Initialize the admin JavaScript functionality when the document is ready.
     */
    $(document).ready(function($) {
        /**
         * Display a saving message near the input label.
         *
         * @param {HTMLElement} element The input element triggering the message.
         * @param {string}      message The message to display.
         * @param {string}      type    The type of message ('success', 'error', or 'info').
         */
        function showSavingMessage(element, message, type) {
            let $label = null;

            // 1. Check if it's a metadata field checkbox.
            if ($(element).hasClass('metadata-field-checkbox')) {
                $label = $(element).next('label');
            }
            // 2. Check if the element has a label immediately following it.
            else if ($(element).next('label').length) {
                $label = $(element).next('label');
            }
            // 3. Fallback: Find a label within the closest table row (for inputs in tables).
            else if ($(element).closest('tr').length) {
                $label = $(element).closest('tr').find('th label');
            }
            // 4. Fallback if the element is in a table header with a label.
            else if ($(element).closest('th').length) {
                $label = $(element).closest('th').find('label');
            }
            // 5. Last resort: Find a label by 'for' attribute matching the element's ID.
            else if ($(element).attr('id')) {
                $label = $(`label[for="${$(element).attr('id')}"]`);
            }

            if (!$label || !$label.length) {
                return; // Exit if no label is found.
            }

            const messageClass = type === 'success' ? 'saving-success' : 'saving-error';
            $label.find('.saving-message').remove();
            $label.append(`<span class="saving-message ${messageClass}">${message}</span>`);

            if (message !== 'Saving...') {
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
                const formData = $('#oneclick_images_settings_form').serialize();
                showSavingMessage(element, 'Saving...', 'info');

                $.ajax({
                    url: oneclick_images_admin_vars.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'oneclick_images_save_settings',
                        _ajax_nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                        settings: formData
                    },
                    success: function(response) {
                        if (response.success) {
                            showSavingMessage(element, 'Saved!', 'success');
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
         * Fetch license status asynchronously via AJAX and return a Promise.
         *
         * @return {Promise} A Promise resolving after fetching the status.
         */
        function promiseFetchLicenseStatus() {
            return new Promise((resolve) => {
                fetchLicenseStatus();
                resolve();
            });
        }

        /**
         * Fetch usage status asynchronously via AJAX and return a Promise.
         *
         * @return {Promise} A Promise resolving after fetching the status.
         */
        function promiseFetchUsageStatus() {
            return new Promise((resolve) => {
                fetchUsageStatus();
                resolve();
            });
        }

        /**
         * Save settings synchronously via AJAX (legacy function, kept for compatibility).
         *
         * @param {HTMLElement} element The input element triggering the save.
         */
        function saveSettings(element) {
            const formData = $('#oneclick_images_settings_form').serialize();
            showSavingMessage(element, 'Saving...', 'info');

            $.ajax({
                url: oneclick_images_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'oneclick_images_save_settings',
                    _ajax_nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                    settings: formData
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
                }
            });
        }

        /**
         * Handle bulk metadata generation when the button is clicked.
         *
         * @param {Event} e The click event.
         */
        $(document).on('click', '#bulk_generate_metadata_button', function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to generate metadata for all images in the media library? This might take some time.')) {
                return;
            }

            const button = $(this);
            button.prop('disabled', true).text('Generating...');
            $('#bulk_generate_status').empty();

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
                        button.prop('disabled', false).text('Generate Metadata for Media Library');
                    }
                },
                error: function(xhr, status, error) {
                    $('#bulk_generate_status').append('<p>Error fetching media IDs. Please try again later.</p>');
                    button.prop('disabled', false).text('Generate Metadata for Media Library');
                }
            });
        });

        let updateCounter = 0;

        /**
         * Process the next image in the bulk generation queue.
         *
         * @param {Array}  ids   Array of image IDs to process.
         * @param {number} index Current index in the array.
         * @param {string} nonce The nonce to use for AJAX requests.
         */
        function processNextImage(ids, index, nonce) {
            console.log(`[Bulk Metadata] Starting processNextImage - Index: ${index}, Total: ${ids.length}`);

            if (index >= ids.length) {
                console.log('[Bulk Metadata] All images processed');
                $('#bulk_generate_status').append('<p>All media items processed.</p>');
                $('#bulk_generate_metadata_button').prop('disabled', false).text('Generate Metadata for Media Library');
                return;
            }

            const imageId = ids[index];
            console.log(`[Bulk Metadata] Processing image ID: ${imageId}`);

            $.ajax({
                url: oneclick_images_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'oneclick_images_generate_metadata',
                    nonce: nonce || oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                    image_id: imageId
                },
                success: function(response) {
                    console.log(`[Bulk Metadata] AJAX success for ID ${imageId}`, response);

                    if (typeof response !== 'object') {
                        console.error(`[Bulk Metadata] Invalid response format for ID ${imageId}`, response);
                        $('#bulk_generate_status').append('<p>Error: Invalid response format for ID ' + imageId + '.</p>');
                        processNextImage(ids, index + 1, nonce);
                        return;
                    }

                    if (response.success && response.data && response.data.metadata) {
                        const metadata = response.data.metadata;

                        if (metadata.error && (metadata.error.includes('Usage limit reached') || metadata.error.includes('Free trial limit reached'))) {
                            console.log(`[Bulk Metadata] Usage limit reached for ID ${imageId}`, metadata);
                            if (metadata.cta_payload && metadata.cta_payload.html) {
                                showLimitPrompt(metadata.cta_payload.html);
                            } else {
                                console.error('[Bulk Metadata] CTA payload missing in response:', response);
                                $('#bulk_generate_status').append('<p>Error: Unable to display usage limit prompt.</p>');
                            }
                            $('#bulk_generate_metadata_button').prop('disabled', false).text('Generate Metadata for Media Library');
                            return;
                        }

                        if (metadata.error && metadata.error.startsWith('Image validation failed')) {
                            console.log(`[Bulk Metadata] Image rejected for ID ${imageId}: ${metadata.error}`);
                            showImageRejectionModal(metadata.error);
                            $('#bulk_generate_status').append(
                                `<p>${imageId} - <span style="color: orange;">Rejected: </span>${metadata.error}</p>`
                            );
                            processNextImage(ids, index + 1, nonce);
                            return;
                        }

                        if (metadata.success) {
                            const mediaLibraryUrl = `/wp-admin/post.php?post=${imageId}&action=edit`;
                            const newData = metadata.metadata || {};
                            console.log(`[Bulk Metadata] Metadata generated for ID ${imageId}`, newData);

                            $.ajax({
                                url: oneclick_images_admin_vars.ajax_url,
                                type: 'GET',
                                data: {
                                    action: 'get_thumbnail',
                                    image_id: imageId,
                                    oneclick_images_ajax_nonce: nonce || oneclick_images_admin_vars.oneclick_images_ajax_nonce
                                },
                                success: function(thumbnailResponse) {
                                    console.log(`[Bulk Metadata] Thumbnail fetch success for ID ${imageId}`, thumbnailResponse);
                                    const thumbnailUrl = thumbnailResponse.success && thumbnailResponse.data?.thumbnail ?
                                        thumbnailResponse.data.thumbnail :
                                        '/path/to/placeholder-image.jpg';
                                    console.log(`[Bulk Metadata] Passing to renderMetadataUI - ID: ${imageId}, Thumbnail: ${thumbnailUrl}`);
                                    renderMetadataUI(mediaLibraryUrl, thumbnailUrl, newData, imageId);
                                    processNextImage(ids, index + 1, nonce);
                                },
                                error: function(xhr, status, error) {
                                    console.error(`[Bulk Metadata] Thumbnail fetch failed for ID ${imageId}`, { status, error });
                                    const thumbnailUrl = '/path/to/placeholder-image.jpg';
                                    console.log(`[Bulk Metadata] Passing to renderMetadataUI (error case) - ID: ${imageId}, Thumbnail: ${thumbnailUrl}`);
                                    renderMetadataUI(mediaLibraryUrl, thumbnailUrl, newData, imageId);
                                    processNextImage(ids, index + 1, nonce);
                                }
                            });
                            return;
                        }

                        console.log(`[Bulk Metadata] Skipped ID ${imageId} - Already has details`);
                        $('#bulk_generate_status').append(
                            `<p>${imageId} - <span style="color: gray;">Skipped (Already has details).</span></p>`
                        );
                    } else {
                        console.log(`[Bulk Metadata] Skipped ID ${imageId} - Unexpected response`, response);
                        $('#bulk_generate_status').append(
                            `<p>${imageId} - <span style="color: gray;">Skipped (Unexpected response).</span></p>`
                        );
                    }
                    processNextImage(ids, index + 1, nonce);
                },
                error: function(xhr, status, error) {
                    console.error(`[Bulk Metadata] AJAX error for ID ${imageId}`, { status, error, xhr });
                    if (xhr.responseJSON && xhr.responseJSON.data === 'Nonce verification failed.') {
                        console.log('[Bulk Metadata] Nonce failed - Attempting refresh');
                        refreshNonce().then(newNonce => {
                            if (newNonce) {
                                processNextImage(ids, index, newNonce);
                            } else {
                                $('#bulk_generate_status').append('<p>Error: Unable to refresh nonce for ID ' + imageId + '</p>');
                                processNextImage(ids, index + 1, nonce);
                            }
                        });
                    } else {
                        $('#bulk_generate_status').append('<p>Error processing ID ' + imageId + ': ' + error + '</p>');
                        processNextImage(ids, index + 1, nonce);
                    }
                }
            });

            if (++updateCounter % 5 === 0) {
                console.log('[Bulk Metadata] Fetching usage status at counter:', updateCounter);
                fetchUsageStatus();
            }
        }

        // Function to refresh nonce
        function refreshNonce() {
            return $.ajax({
                url: oneclick_images_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'oneclick_images_refresh_nonce',
                    nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce
                },
                success: function(response) {
                    if (response.success && response.data.nonce) {
                        console.log('[Bulk Metadata] Nonce refreshed:', response.data.nonce);
                        oneclick_images_admin_vars.oneclick_images_ajax_nonce = response.data.nonce;
                        return response.data.nonce;
                    }
                    console.error('[Bulk Metadata] Failed to refresh nonce:', response);
                    return null;
                },
                error: function(xhr, status, error) {
                    console.error('[Bulk Metadata] Nonce refresh AJAX error:', { status, error });
                    return null;
                }
            });
        }

        /**
         * Render metadata UI for a processed image.
         *
         * @param {string} mediaLibraryUrl URL to the media library edit page.
         * @param {string} thumbnailUrl    URL of the image thumbnail.
         * @param {Object} metadata       Metadata object for the image.
         * @param {number} imageId        The ID of the image.
         */
        function renderMetadataUI(mediaLibraryUrl, thumbnailUrl, metadata, imageId) {
            console.log(`[Bulk Metadata] renderMetadataUI called - ID: ${imageId}, Thumbnail: ${thumbnailUrl}, Media URL: ${mediaLibraryUrl}`);

            const metadataRows = Object.entries(metadata).length > 0 ?
                Object.entries(metadata)
                    .map(([key, value]) => `<tr><td>${key}</td><td>${value}</td></tr>`)
                    .join('') :
                '<tr><td colspan="2">No metadata available</td></tr>';

            const metadataTable = `
                <table class="metadata-table">
                    <tr><th>Key</th><th>Value</th></tr>
                    ${metadataRows}
                </table>
            `;

            const content = `
                <div class="status-item" style="display: flex; align-items: flex-start; margin-bottom: 20px; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                    <div class="thumbnail-container" style="flex: 0 0 150px; margin-right: 20px;">
                        <img
                            src="${thumbnailUrl}"  <!-- Removed esc_url -->
                            alt="Thumbnail for ${imageId}"
                            class="thumbnail-preview"
                            style="width: 150px; height: auto; border: 1px solid #ccc; border-radius: 3px;"
                            onerror="this.src='/path/to/placeholder-image.jpg';"
                        />
                    </div>
                    <div class="metadata-container" style="flex: 1;">
                        <p>
                            <a href="${mediaLibraryUrl}" target="_blank" style="text-decoration: none; color: #0073aa; font-weight: normal; font-size: 14px;">  <!-- Removed esc_url -->
                                ${imageId} - Done
                                <span class="dashicons dashicons-external" style="margin-left: 5px;"></span>
                            </a>
                        </p>
                        ${metadataTable}
                    </div>
                </div>
            `;
            $('#bulk_generate_status').append(content);
            console.log(`[Bulk Metadata] UI rendered for ID: ${imageId}`);
        }

        /**
         * Handle metadata generation for a specific image when the button is clicked.
         *
         * @param {Event} e The click event.
         */
        $(document).on('click', '#generate_metadata_button', function(e) {
            e.preventDefault();
            const button = $(this);
            const imageId = button.data('image-id');

            if (!imageId) {
                // eslint-disable-next-line no-console
                console.error('[Metadata] No image ID found.');
                return;
            }

            // eslint-disable-next-line no-console
            console.log('[Metadata] Generate button clicked for image ID:', imageId);
            button.prop('disabled', true).text('Generating...');
            // eslint-disable-next-line no-console
            console.log('[Metadata] Button disabled and loading state set.');

            $.ajax({
                url: oneclick_images_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'oneclick_images_generate_metadata',
                    nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                    image_id: imageId
                },
                success: function(response) {
                    // eslint-disable-next-line no-console
                    console.log('[Metadata] AJAX request succeeded with response:', response);
                    if (typeof response !== 'object') {
                        // eslint-disable-next-line no-console
                        console.error('[Metadata] Invalid response format:', response);
                        showGeneralErrorModal('An unexpected error occurred. Please try again.');
                        return;
                    }

                    if (response.success && response.data && response.data.metadata) {
                        const metadata = response.data.metadata;

                        if (metadata.error) {
                            // eslint-disable-next-line no-console
                            console.error('[Metadata] Metadata error:', metadata.error);
                            if (metadata.error.includes('Usage limit reached') || metadata.error.includes('Free trial limit reached')) {
                                // eslint-disable-next-line no-console
                                console.log('[Metadata] Usage limit reached. Displaying limit prompt.');
                                if (metadata.cta_payload && metadata.cta_payload.html) {
                                    showLimitPrompt(metadata.cta_payload.html);
                                } else {
                                    console.error('[Metadata] CTA payload missing in response:', response);
                                    showGeneralErrorModal('Error: Unable to display usage limit prompt.');
                                }
                                return;
                            }

                            if (metadata.error.startsWith('Image validation failed')) {
                                showImageRejectionModal(metadata.error);
                                return;
                            }

                            if (metadata.error.startsWith('No metadata fields require generation')) {
                                showGeneralErrorModal('The image already has all metadata fields filled, and "Override Metadata" is disabled.');
                                return;
                            }
                        }

                        if (metadata.success) {
                            // eslint-disable-next-line no-console
                            console.log('[Metadata] Metadata generated successfully:', metadata.metadata);
                            updateMetadataFields(metadata.metadata);
                        } else {
                            // eslint-disable-next-line no-console
                            console.error('[Metadata] Metadata generation skipped or unexpected issue:', metadata);
                            showGeneralErrorModal('Metadata generation was skipped or an unexpected issue occurred.');
                        }
                    } else {
                        // eslint-disable-next-line no-console
                        console.error('[Metadata] Unexpected response structure:', response);
                        showGeneralErrorModal('An unexpected error occurred.');
                    }
                },
                error: function(xhr, status, error) {
                    // eslint-disable-next-line no-console
                    console.error('[Metadata] AJAX request error:', status, error);
                    showGeneralErrorModal('An error occurred while processing the request. Please try again.');
                },
                complete: function() {
                    // eslint-disable-next-line no-console
                    console.log('[Metadata] AJAX request complete. Re-enabling button.');
                    button.prop('disabled', false).text('Generate Metadata');
                }
            });
        });

        /**
         * Display a modal with server-provided HTML content for usage limits.
         *
         * @param {string} htmlContent The HTML content to display in the modal.
         */
        function showLimitPrompt(htmlContent) {
            // eslint-disable-next-line no-console
            console.log('[Modal] Showing limit prompt modal');
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
                if (e.key === 'Escape') {
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
            // eslint-disable-next-line no-console
            console.log('[Modal] Showing image rejection modal with message:', message);
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
                closeModal(modal);
            });
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal(modal);
                }
            });
        }

        /**
         * Close the specified modal.
         *
         * @param {jQuery} modal The modal jQuery object to close.
         */
        function closeModal(modal) {
            modal.fadeOut(function() {
                $(this).remove();
            });
        }

        /**
         * Display a general error modal with a specified message.
         *
         * @param {string} message The error message to display.
         */
        function showGeneralErrorModal(message) {
            // eslint-disable-next-line no-console
            console.log('[Modal] Showing general error modal with message:', message);
            const modalHtml = `
                <div id="occ-general-error-modal" class="occ-modal" role="dialog" aria-labelledby="general-error-modal-title">
                    <div class="occ-modal-overlay"></div>
                    <div class="occ-modal-content" tabindex="0">
                        <span class="occ-modal-close dashicons dashicons-no" aria-label="Close"></span>
                        <h2 id="general-error-modal-title"><span class="dashicons dashicons-warning"></span> Action Skipped</h2>
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
            $('body').append(modalHtml);
            const modal = $('#occ-general-error-modal');
            modal.fadeIn();
            modal.find('.occ-modal-close, .occ-modal-overlay, .occ-close-modal').on('click', function() {
                modal.fadeOut(() => modal.remove());
            });
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    modal.fadeOut(() => modal.remove());
                }
            });
        }

        /**
         * Update the license status UI based on the response.
         *
         * @param {string} status  The license status ('active', 'invalid', etc.).
         * @param {string} message The message to display.
         */
        function updateLicenseStatusUI(status, message) {
            // eslint-disable-next-line no-console
            console.log('[UI] Updating license status UI to', status, 'with message:', message);
            const $statusLabel = $('#license_status_label');
            const $statusMessage = $('#license_status_message');
            const statusColor = status === 'active' ? 'green' : status === 'validating' ? 'orange' : 'red';
            const statusText = status === 'active' ? 'Valid' : status === 'invalid' ? 'Invalid' : 'Checking...';
            $statusLabel.text(statusText).css('color', statusColor);
            $statusMessage.text(message).css('color', statusColor);
        }

        /**
         * Fetch the current license status via AJAX.
         */
        function fetchLicenseStatus() {
            updateLicenseStatusUI('validating', 'Checking license status...');
            $.ajax({
                url: oneclick_images_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'oneclick_images_get_license_status',
                    nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce
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
                }
            });
        }

        /**
         * Fetch usage information via AJAX with retry logic.
         *
         * @param {number} retryCount Number of retry attempts (default: 3).
         */
        function fetchUsageStatus(retryCount = 3) {
            $.ajax({
                url: oneclick_images_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'oneclick_images_check_usage',
                    nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce
                },
                success: function(response) {
                    if (response.success) {
                        const { used_count, usage_limit, addon_count, remaining_count } = response.data;
                        const totalAllowed = parseInt(usage_limit, 10) + parseInt(addon_count || 0, 10);
                        updateUsageStatusUI(used_count, totalAllowed, remaining_count);
                        console.log('[Usage] Fetched usage status:', response.data);
                    } else if (retryCount > 0) {
                        setTimeout(() => fetchUsageStatus(retryCount - 1), 1000);
                    } else {
                        $('#usage_count').html('<strong>Error:</strong> Unable to fetch usage information.');
                        $('#usage_progress').css('width', '0%').text('0%');
                    }
                },
                error: function(xhr, status, error) {
                    if (retryCount > 0) {
                        setTimeout(() => fetchUsageStatus(retryCount - 1), 1000);
                    } else {
                        $('#usage_count').html('<strong>Error:</strong> An error occurred while fetching usage information.');
                        $('#usage_progress').css('width', '0%').text('0%');
                    }
                }
            });
        }

        /**
         * Update the usage status UI with the provided counts.
         *
         * @param {number} usedCount      The number of images used.
         * @param {number} totalAllowed   The total number of images allowed.
         * @param {number} remainingCount The number of images remaining.
         */
        function updateUsageStatusUI(usedCount, totalAllowed, remainingCount) {
            const usageCount = $('#usage_count'); // Element to display usage text
            const usageProgress = $('#usage_progress'); // Progress bar element

            // Check for invalid data
            if (typeof usedCount === 'undefined' || typeof totalAllowed === 'undefined' || typeof remainingCount === 'undefined') {
                usageCount.html('<strong>Error:</strong> Invalid usage data.');
                usageProgress.css('width', '0%').text('0%');
                return;
            }

            // Calculate percentage used
            const percentageUsed = totalAllowed > 0 ? (usedCount / totalAllowed) * 100 : 0;

            // Update usage count text
            usageCount.html(
                `<strong>Used:</strong> ${usedCount} of ${totalAllowed} images (${remainingCount} remaining)`
            );

            // Update progress bar width and text
            usageProgress.css('width', `${Math.min(percentageUsed, 100)}%`);
            usageProgress.attr('aria-valuenow', Math.min(percentageUsed, 100));
            usageProgress.text(`${Math.round(percentageUsed)}% Used`);

            // Adjust progress bar color based on usage level
            if (percentageUsed >= 90) {
                usageProgress.removeClass('bg-success bg-warning').addClass('bg-danger');
            } else if (percentageUsed >= 70) {
                usageProgress.removeClass('bg-success bg-danger').addClass('bg-warning');
            } else {
                usageProgress.removeClass('bg-warning bg-danger').addClass('bg-success');
            }
        }

        // Expose functions to the global scope for external use (e.g., other scripts).
        window.showImageRejectionModal = showImageRejectionModal;
        window.showGeneralErrorModal = showGeneralErrorModal;
        window.showLimitPrompt = showLimitPrompt;
        window.updateMetadataFields = updateMetadataFields;

        /**
         * Update metadata fields in the UI based on the generated metadata.
         *
         * @param {Object} metadata The metadata object to update in the UI.
         */
        function updateMetadataFields(metadata) {
            try {
                const selectedFields = oneclick_images_admin_vars.selected_fields || {
                    alt_text: true,
                    title: true,
                    caption: true,
                    description: true
                };

                const altInputLibrary = $('#attachment-details-two-column-alt-text');
                const altInputSingle = $('#attachment_alt');
                if (metadata.alt_text && selectedFields.alt_text) {
                    if (altInputLibrary.length) {
                        altInputLibrary.val(metadata.alt_text).trigger('change');
                    }
                    if (altInputSingle.length) {
                        altInputSingle.val(metadata.alt_text).trigger('change');
                    }
                }

                const titleInputLibrary = $('#attachment-details-two-column-title');
                const titleInputSingle = $('#title');
                if (metadata.title && selectedFields.title) {
                    if (titleInputLibrary.length) {
                        titleInputLibrary.val(metadata.title).trigger('change');
                    }
                    if (titleInputSingle.length) {
                        titleInputSingle.val(metadata.title).trigger('change');
                    }
                }

                const captionInputLibrary = $('#attachment-details-two-column-caption');
                const captionInputSingle = $('#attachment_caption');
                if (metadata.caption && selectedFields.caption) {
                    if (captionInputLibrary.length) {
                        captionInputLibrary.val(metadata.caption).trigger('change');
                    }
                    if (captionInputSingle.length) {
                        captionInputSingle.val(metadata.caption).trigger('change');
                    }
                }

                const descriptionInputLibrary = $('#attachment-details-two-column-description');
                const descriptionInputSingle = $('#attachment_content');
                if (metadata.description && selectedFields.description) {
                    if (descriptionInputLibrary.length) {
                        descriptionInputLibrary.val(metadata.description).trigger('change');
                    }
                    if (descriptionInputSingle.length) {
                        descriptionInputSingle.val(metadata.description).trigger('change');
                    }
                }

                $('input, textarea').trigger('change').trigger('input');
                // eslint-disable-next-line no-console
                console.log('[Metadata UI] Updated metadata fields.');
            } catch (err) {
                // eslint-disable-next-line no-console
                console.error('[Metadata UI] Error updating metadata fields:', err);
            }
        }

        // Initialize status fetching.
        fetchLicenseStatus();
        fetchUsageStatus();

        // Trigger license validation on button click.
        $('#validate_license_button').on('click', function() {
            fetchLicenseStatus();
        });

        // Event listeners for settings changes.
        $('#oneclick_images_settings_form input[type="checkbox"], #oneclick_images_settings_form select').on('change', function() {
            const element = this;
            promiseSaveSettings(element).catch((error) => {
                // eslint-disable-next-line no-console
                console.error('[OneClickContent] Error during saveSettings:', error);
            });
        });

        $('#oneclick_images_settings_form input, #oneclick_images_settings_form select').on('change', function() {
            const element = this;
            promiseSaveSettings(element).catch((error) => {
                // eslint-disable-next-line no-console
                console.error('[OneClickContent] Error during saveSettings:', error);
            });
        });

        $('#oneclick_images_license_key').on('keyup', function() {
            const element = this;
            promiseSaveSettings(element)
                .then(() => promiseFetchLicenseStatus())
                .then(() => promiseFetchUsageStatus())
                .catch((error) => {
                    // eslint-disable-next-line no-console
                    console.error('[OneClickContent] Error during sequential execution:', error);
                });
        });
    });

})(jQuery);