(function($) {
    'use strict';

    jQuery(document).ready(function ($) {

        /**
         * Function to show a "Saving..." or "Saved!" message near the input label.
         *
         * @param {HTMLElement} element - The input element triggering the message.
         * @param {string} message - The message to display.
         * @param {string} type - The type of message ('success', 'error', or 'info').
         */
        function showSavingMessage(element, message, type) {
            let $label;

            // 1. Check if it's a metadata field checkbox.
            if ($(element).hasClass('metadata-field-checkbox')) {
                $label = $(element).next('label'); 
            }
            // 2. Check if the element has a label immediately following it.
            else if ($(element).next('label').length) {
              $label = $(element).next('label');
            }
            // 3. Fallback: Find a label within the closest table row (for other inputs in tables).
            else if ($(element).closest('tr').length) {
                $label = $(element).closest('tr').find('th label');
            }
            //4. Fallback if the element is not in a table row but has a label in a th.
            else if ($(element).closest('th').length){
              $label = $(element).closest('th').find('label');
            }
            // 5. Last resort: Find a label associated by for attribute.
            else if ($(element).attr('id')){
              $label = $(`label[for="${$(element).attr('id')}"]`);
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

        // Update event listeners for saving settings and fetching status.
        $('#oneclick_images_settings_form input[type="checkbox"], #oneclick_images_settings_form select').on('change', function() {
            const element = this;
            promiseSaveSettings(element).catch((error) => {
                console.error('[OneClickContent] Error during saveSettings:', error);
            });
        });

        $('#oneclick_images_settings_form input, #oneclick_images_settings_form select').on('change', function() {
            const element = this;
            promiseSaveSettings(element).catch((error) => {
                console.error('[OneClickContent] Error during saveSettings:', error);
            });
        });

        $('#oneclick_images_license_key').on('keyup', function() {
            const element = this;
            promiseSaveSettings(element)
                .then(() => promiseFetchLicenseStatus())
                .then(() => promiseFetchUsageStatus())
                .catch((error) => {
                    console.error('[OneClickContent] Error during sequential execution:', error);
                });
        });

        function promiseSaveSettings(element) {
            return new Promise((resolve, reject) => {
                const formData = $('#oneclick_images_settings_form').serialize();
                showSavingMessage(element, ' Saving...', 'info');

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
                            showSavingMessage(element, ' Saved!', 'success');
                            resolve();
                        } else {
                            showSavingMessage(element, ' Failed to Save!', 'error');
                            reject('Failed to save settings');
                        }
                    },
                    error: function(xhr, status, error) {
                        showSavingMessage(element, ' Error occurred!', 'error');
                        reject(error);
                    },
                });
            });
        }

        function promiseFetchLicenseStatus() {
            return new Promise((resolve) => {
                fetchLicenseStatus();
                resolve();
            });
        }

        function promiseFetchUsageStatus() {
            return new Promise((resolve) => {
                fetchUsageStatus();
                resolve();
            });
        }

        function saveSettings(element) {            
            const formData = $('#oneclick_images_settings_form').serialize();
            showSavingMessage(element, ' Saving...', 'info');

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
                        showSavingMessage(element, ' Saved!', 'success');
                    } else {
                        showSavingMessage(element, ' Failed to Save!', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    showSavingMessage(element, ' Error occurred!', 'error');
                },
            });
        }

        /**
         * Handle bulk metadata generation when the button is clicked.
         */
        $(document).on('click', '#bulk_generate_metadata_button', function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to generate metadata for all images in the media library? This might take some time.')) {
                return;
            }
            const button = $(this);
            button.attr('disabled', true).text('Generating...');
            $('#bulk_generate_status').empty();

            $.ajax({
                url: oneclick_images_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'oneclick_images_get_all_media_ids',
                    nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                },
                success: function(response) {
                    if (response.success && response.data.ids.length > 0) {
                        processNextImage(response.data.ids, 0);
                    } else {
                        $('#bulk_generate_status').append('<p>No media items found.</p>');
                        button.attr('disabled', false).text('Generate Metadata for Media Library');
                    }
                },
                error: function(xhr, status, error) {
                    $('#bulk_generate_status').append('<p>Error fetching media IDs. Please try again later.</p>');
                    button.attr('disabled', false).text('Generate Metadata for Media Library');
                },
            });
        });

        let updateCounter = 0;

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
                   image_id: imageId,
               },
               success: function (response) {
                   if (typeof response !== 'object') {
                       $('#bulk_generate_status').append('<p>Error: Invalid response format for ID ' + imageId + '.</p>');
                       processNextImage(ids, index + 1);
                       return;
                   }

                   if (response.success && response.data && response.data.metadata) {
                       const metadataResponse = response.data.metadata;
                       if (
                           metadataResponse.error === 'Usage limit reached. Please purchase more blocks to continue.' ||
                           metadataResponse.error === 'Free trial limit reached. Please subscribe to continue.'
                       ) {
                           console.log('[Bulk Metadata] Usage limit reached for image ID ' + imageId + '. Triggering subscription prompt.');
                           showSubscriptionPrompt(metadataResponse.error, metadataResponse.message, metadataResponse.ad_url);
                           $('#bulk_generate_metadata_button').attr('disabled', false).text('Generate Metadata for Media Library');
                           return;
                       }

                       if (metadataResponse.error && metadataResponse.error.startsWith('Image validation failed')) {
                           showImageRejectionModal(metadataResponse.error);
                           $('#bulk_generate_status').append(
                               `<p>${imageId} - <span style="color:orange;">Rejected: </span>${metadataResponse.error}</p>`
                           );
                           processNextImage(ids, index + 1);
                           return;
                       }

                       if (metadataResponse.success) {
                           const mediaLibraryUrl = `/wp-admin/post.php?post=${imageId}&action=edit`;
                           const newData = metadataResponse.metadata || {};
                           $.ajax({
                               url: `/wp-admin/admin-ajax.php?action=get_thumbnail&image_id=${imageId}`,
                               type: 'GET',
                               success: function (thumbnailResponse) {
                                   const thumbnailUrl = thumbnailResponse.success && thumbnailResponse.data?.thumbnail 
                                       ? thumbnailResponse.data.thumbnail 
                                       : '/path/to/placeholder-image.jpg';
                                   renderMetadataUI(mediaLibraryUrl, thumbnailUrl, newData, imageId);
                                   processNextImage(ids, index + 1);
                               },
                               error: function () {
                                   const thumbnailUrl = '/path/to/placeholder-image.jpg';
                                   renderMetadataUI(mediaLibraryUrl, thumbnailUrl, newData, imageId);
                                   processNextImage(ids, index + 1);
                               }
                           });
                           return;
                       }

                       $('#bulk_generate_status').append(
                           `<p>${imageId} - <span style="color:gray;">Skipped (Already has details).</span></p>`
                       );
                   } else {
                       $('#bulk_generate_status').append(
                           `<p>${imageId} - <span style="color:gray;">Skipped (Unexpected response).</span></p>`
                       );
                   }
                   processNextImage(ids, index + 1);
               },
               error: function (xhr, status, error) {
                   $('#bulk_generate_status').append('<p>Error processing ID ' + imageId + ': ' + error + '</p>');
                   processNextImage(ids, index + 1);
               },
           });

           if (++updateCounter % 5 === 0) {
               fetchUsageStatus();
           }
       }

       function renderMetadataUI(mediaLibraryUrl, thumbnailUrl, metadata, imageId) {
           const metadataRows = Object.entries(metadata).length > 0
               ? Object.entries(metadata)
                   .map(([key, value]) => `<tr><td>${key}</td><td>${value}</td></tr>`)
                   .join('')
               : '<tr><td colspan="2">No metadata available</td></tr>';

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
                           src="${thumbnailUrl}" 
                           alt="Thumbnail for ${imageId}" 
                           class="thumbnail-preview" 
                           style="width: 150px; height: auto; border: 1px solid #ccc; border-radius: 3px;" 
                           onerror="this.src='/path/to/placeholder-image.jpg';" 
                       />
                   </div>
                   <div class="metadata-container" style="flex: 1;">
                       <p>
                           <a href="${mediaLibraryUrl}" target="_blank" style="text-decoration: none; color: #0073aa; font-weight: normal; font-size: 14px;">
                               ${imageId} - Done
                               <span class="dashicons dashicons-external" style="margin-left: 5px;"></span>
                           </a>
                       </p>
                       ${metadataTable}
                   </div>
               </div>
           `;
           $('#bulk_generate_status').append(content);
       }

        /**
         * Handles metadata generation for a specific image when the button is clicked.
         */
        $(document).on('click', '#generate_metadata_button', function(e) {
            e.preventDefault();
            const button = $(this);
            const imageId = button.data('image-id');
            console.log('[Metadata] Generate button clicked for image ID:', imageId);
            if (!imageId) {
                console.error('[Metadata] No image ID found.');
                return;
            }
            button.attr('disabled', true).text('Generating...');
            console.log('[Metadata] Button disabled and loading state set.');

            $.ajax({
                url: oneclick_images_admin_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'oneclick_images_generate_metadata',
                    nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                    image_id: imageId,
                },
                success: function(response) {
                    console.log('[Metadata] AJAX request succeeded with response:', response);
                    if (typeof response !== 'object') {
                        console.error('[Metadata] Invalid response format:', response);
                        showGeneralErrorModal('An unexpected error occurred. Please try again.');
                        return;
                    }

                    if (response.success && response.data && response.data.metadata) {
                        const metadata = response.data.metadata;

                        // Log any error coming back from the metadata generation.
                        if (metadata.error) {
                            console.error('[Metadata] Metadata error:', metadata.error);
                            // Use a substring check for usage limit errors.
                            if (
                                metadata.error.includes('Usage limit reached') ||
                                metadata.error.includes('Free trial limit reached')
                            ) {
                                console.log('[Metadata] Triggering subscription prompt due to usage limit.');
                                showSubscriptionPrompt(metadata.error, metadata.message, metadata.ad_url);
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

                        // Handle successful metadata generation.
                        if (metadata.success) {
                            console.log('[Metadata] Metadata generated successfully:', metadata.metadata);
                            updateMetadataFields(metadata.metadata); // Update metadata fields in the UI.
                        } else {
                            console.error('[Metadata] Metadata generation skipped or unexpected issue:', metadata);
                            showGeneralErrorModal('Metadata generation was skipped or an unexpected issue occurred.');
                        }
                    } else {
                        console.error('[Metadata] Unexpected response structure:', response);
                        showGeneralErrorModal('An unexpected error occurred.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[Metadata] AJAX request error:', status, error);
                    showGeneralErrorModal('An error occurred while processing the request. Please try again.');
                },
                complete: function() {
                    console.log('[Metadata] AJAX request complete. Re-enabling button.');
                    button.attr('disabled', false).text('Generate Metadata');
                },
            });
        });

        /**
         * Function to show the image rejection modal.
         */
        function showImageRejectionModal(message) {
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
         * Function to close the modal.
         */
        function closeModal(modal) {
            modal.fadeOut(function() {
                modal.remove();
            });
        }

        /**
         * Displays a general error modal with a specified message.
         */
        function showGeneralErrorModal(message) {
            console.log('[Modal] Showing general error modal with message:', message);
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
         * Displays a subscription prompt modal for when usage limits are reached.
         */
        function showSubscriptionPrompt(error, message, url) {
            console.log('[Modal] Showing subscription prompt modal with error:', error, 'message:', message, 'url:', url);
            const licenseStatus = oneclick_images_admin_vars.license_status;
            let subscriptionOptionsHtml = '';
            if (licenseStatus === 'active') {
                subscriptionOptionsHtml = `
                    <div class="occ-subscription-extra">
                        <p>You have an active subscription. Buy additional credits if needed:</p>
                        <a href="https://oneclickcontent.com/buy-more-image-credits/" target="_blank" class="occ-subscription-button">Buy Image Credits</a>
                    </div>
                `;
            } else {
                subscriptionOptionsHtml = `
                    <div class="occ-subscription-options">
                        <div class="occ-subscription-tier">
                            <h3>Growth Plan</h3>
                            <p>100 Images</p>
                            <strong>$4.99/month</strong>
                            <a href="${url}?plan=growth" target="_blank" class="occ-subscription-button">Choose Growth</a>
                        </div>
                        <div class="occ-subscription-tier">
                            <h3>Business Plan</h3>
                            <p>500 Images</p>
                            <strong>$19.99/month</strong>
                            <a href="${url}?plan=business" target="_blank" class="occ-subscription-button">Choose Business</a>
                        </div>
                        <div class="occ-subscription-tier most-popular">
                            <h3>Pro Plan <span class="badge">Most Popular</span></h3>
                            <p>1,000 Images</p>
                            <strong>$29.99/month</strong>
                            <a href="${url}?plan=pro" target="_blank" class="occ-subscription-button primary">Choose Pro</a>
                        </div>
                        <div class="occ-subscription-tier">
                            <h3>Premium Plan</h3>
                            <p>3,000 Images</p>
                            <strong>$89.99/month</strong>
                            <a href="${url}?plan=premium" target="_blank" class="occ-subscription-button">Choose Premium</a>
                        </div>
                        <div class="occ-subscription-tier">
                            <h3>Elite Plan</h3>
                            <p>5,000 Images</p>
                            <strong>$129.00/month</strong>
                            <a href="${url}?plan=elite" target="_blank" class="occ-subscription-button">Choose Elite</a>
                        </div>
                    </div>
                    <div class="occ-subscription-extra">
                        <p>Need more images? Buy additional credits:</p>
                        <a href="https://oneclickcontent.com/buy-more-image-credits/" target="_blank" class="occ-subscription-button">Buy Image Credits</a>
                    </div>
                `;
            }

            const modalHtml = `
                <div id="occ-subscription-modal" class="occ-subscription-modal" role="dialog" aria-labelledby="subscription-modal-title">
                    <div class="occ-subscription-modal-overlay"></div>
                    <div class="occ-subscription-modal-content" tabindex="0">
                        <span class="occ-subscription-modal-close dashicons dashicons-no" aria-label="Close"></span>
                        <h2 id="subscription-modal-title"><span class="dashicons dashicons-lock"></span> ${error}</h2>
                        <p>${message}</p>
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
            $('.occ-subscription-modal-close, .occ-subscription-modal-overlay').on('click', function () {
                modal.fadeOut(function () {
                    $(this).remove();
                });
            });
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    modal.fadeOut(function () {
                        $(this).remove();
                    });
                }
            });
        }

        function showSubscriberLimitPrompt(error, limit) {
            console.log('[Modal] Showing subscriber limit prompt with error:', error, 'limit:', limit);
            const modalHtml = `
                <div id="occ-subscriber-limit-modal" class="occ-subscriber-limit-modal">
                    <div class="occ-subscriber-limit-modal-content">
                        <span class="occ-subscriber-limit-modal-close dashicons dashicons-no"></span>
                        <h2><span class="dashicons dashicons-warning"></span> ${error}</h2>
                        <p>You have reached your usage limit of ${limit} images this month. Buy additional blocks to continue generating metadata.</p>
                        <div class="occ-subscriber-upgrade">
                            <a href="https://oneclickcontent.com/image-detail-generator/" target="_blank" class="occ-upgrade-button">Upgrade Now</a>
                        </div>
                        <p>Contact <a href="https://oneclickcontent.com/contact/">support</a> for assistance.</p>
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
            console.log('[UI] Updating license status UI to', status, 'with message:', message);
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

        fetchLicenseStatus();
        fetchUsageStatus();

        $('#validate_license_button').on('click', function() {
            fetchLicenseStatus();
        });

        // Update usage status UI
        function updateUsageStatusUI(usedCount, totalAllowed, remainingCount) {
            const usageCount = $('#usage_count');
            const usageProgress = $('#usage_progress');
            usageCount.html(`
                <strong>Used:</strong> ${usedCount} of ${totalAllowed} images 
                (${remainingCount} remaining)
            `);
            const percentageUsed = (usedCount / totalAllowed) * 100;
            usageProgress.css('width', `${Math.min(percentageUsed, 100)}%`);
            usageProgress.attr('aria-valuenow', Math.min(percentageUsed, 100));
            usageProgress.text(`${Math.round(percentageUsed)}% Used`);
            if (percentageUsed >= 90) {
                usageProgress.removeClass('bg-success bg-warning').addClass('bg-danger');
            } else if (percentageUsed >= 70) {
                usageProgress.removeClass('bg-success bg-danger').addClass('bg-warning');
            } else {
                usageProgress.removeClass('bg-warning bg-danger').addClass('bg-success');
            }
        }

        /**
         * Fetches usage information via AJAX and updates the UI, with retry logic.
         */
        function fetchUsageStatus(retryCount = 3) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'check_usage',
                    nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                },
                success: function(response) {
                    if (response.success) {
                        const { used_count, usage_limit, addon_count, remaining_count } = response.data;
                        const totalAllowed = parseInt(usage_limit, 10) + parseInt(addon_count, 10);
                        updateUsageStatusUI(used_count, totalAllowed, remaining_count);
                        console.log('[Usage] Fetched usage status:', response.data);
                    } else {
                        if (retryCount > 0) {
                            setTimeout(() => fetchUsageStatus(retryCount - 1), 1000);
                        } else {
                            $('#usage_count').html('<strong>Error:</strong> Unable to fetch usage information.');
                            $('#usage_progress').css('width', '0%').text('0%');
                        }
                    }
                },
                error: function(xhr, status, error) {
                    if (retryCount > 0) {
                        setTimeout(() => fetchUsageStatus(retryCount - 1), 1000);
                    } else {
                        $('#usage_count').html('<strong>Error:</strong> An error occurred while fetching usage information.');
                        $('#usage_progress').css('width', '0%').text('0%');
                    }
                },
            });
        }

        // Expose functions to the global scope.
        window.showImageRejectionModal = showImageRejectionModal;
        window.showGeneralErrorModal = showGeneralErrorModal;
        window.showSubscriptionPrompt = showSubscriptionPrompt;
        window.showSubscriberLimitPrompt = showSubscriberLimitPrompt;
        window.updateMetadataFields = updateMetadataFields;

        /**
         * Updates metadata fields in the UI.
         */
        function updateMetadataFields(metadata) {
            try {
                const selectedFields = oneclick_images_admin_vars.selected_fields || {
                    alt_text: true,
                    title: true,
                    caption: true,
                    description: true,
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
                console.log('[Metadata UI] Updated metadata fields.');
            } catch (err) {
                console.error('[Metadata UI] Error updating metadata fields:', err);
            }
        }
    });
})(jQuery);