jQuery(document).ready(function($) {
    'use strict';
    var stopBulkGeneration = false;

    // Click handler for both "Generate All Metadata" buttons
    $('#generate-all-metadata-settings, #generate-all-metadata').on('click', function() {
        var $button = $(this);
        var isSettingsTab = $button.attr('id') === 'generate-all-metadata-settings';
        var statusContainer = isSettingsTab ? '#bulk-generate-status-settings' : '#bulk-generate-status';
        var stopButton = isSettingsTab ? '#stop-bulk-generation-settings' : '#stop-bulk-generation';
        var progressBar = isSettingsTab ? '#bulk-generate-progress-bar-settings' : '#bulk-generate-progress-bar';
        var messageContainer = isSettingsTab ? '#bulk-generate-message-settings' : '#bulk-generate-message';

        // Show the modal
        $('#bulk-generate-modal').show();

        // Check the override_metadata option
        $.ajax({
            url: oneclick_images_admin_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'oneclick_images_check_override_metadata',
                nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce
            },
            success: function(response) {
                if (response.success && (response.data.override === true || response.data.override === "1")) {
                    $('#bulk-generate-warning').show();
                } else {
                    $('#bulk-generate-warning').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr.responseText);
                $('#bulk-generate-warning').hide();
            }
        });

        // Handle modal buttons
        $('#confirm-bulk-generate').off('click').on('click', function() {
            $('#bulk-generate-modal').hide();
            startBulkGeneration($button, statusContainer, stopButton, progressBar, messageContainer);
        });

        $('#cancel-bulk-generate').off('click').on('click', function() {
            $('#bulk-generate-modal').hide();
        });
    });

    // Click handler for the Stop buttons
    $('#stop-bulk-generation-settings, #stop-bulk-generation').on('click', function() {
        stopBulkGeneration = true;
        var isSettingsTab = $(this).attr('id') === 'stop-bulk-generation-settings';
        var messageContainer = isSettingsTab ? '#bulk-generate-message-settings' : '#bulk-generate-message';
        var generateButton = isSettingsTab ? '#generate-all-metadata-settings' : '#generate-all-metadata';
        
        $(messageContainer).text('Generation stopped.');
        $(this).hide();
        $(generateButton).prop('disabled', false).text('Generate All Metadata');
    });

    function startBulkGeneration($button, statusContainer, stopButton, progressBar, messageContainer) {
        stopBulkGeneration = false;
        $(stopButton).show();
        $button.prop('disabled', true).html('<span class="generate-spinner"></span> Generating...');
        $(statusContainer).show();
        $(progressBar).css('width', '0%');
        $(messageContainer).text('');

        $.ajax({
            url: oneclick_images_admin_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'oneclick_images_get_all_media_ids',
                nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce
            },
            success: function(response) {
                if (response.success && response.data.ids.length > 0) {
                    processBulkGeneration(response.data.ids, 0, $button, response.data.ids.length, statusContainer, stopButton, progressBar, messageContainer);
                } else {
                    $(messageContainer).text('No media items found.');
                    $button.prop('disabled', false).text('Generate All Metadata');
                    $(stopButton).hide();
                }
            },
            error: function(xhr) {
                $(messageContainer).text('Error fetching media IDs: ' + xhr.responseText);
                $button.prop('disabled', false).text('Generate All Metadata');
                $(stopButton).hide();
            }
        });
    }

    function processBulkGeneration(ids, index, $button, total, statusContainer, stopButton, progressBar, messageContainer) {
        if (stopBulkGeneration) {
            $(messageContainer).text('Generation stopped.');
            $(stopButton).hide();
            $button.prop('disabled', false).text('Generate All Metadata');
            return;
        }

        if (index >= ids.length) {
            $(messageContainer).text('All metadata generation complete!');
            $(progressBar).css('width', '100%');
            $button.prop('disabled', false).text('Generate All Metadata');
            $(stopButton).hide();
            fetchUsageStatus();
            return;
        }

        var imageId = ids[index];
        var percent = Math.round(((index + 1) / total) * 100);
        $(messageContainer).text(`Processing image ${index + 1} of ${total} (ID: ${imageId})`);
        $(progressBar).css('width', percent + '%');

        $.ajax({
            url: oneclick_images_admin_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'oneclick_images_generate_metadata',
                nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                image_id: imageId
            },
            success: function(response) {
                if (response.success && response.data && response.data.metadata) {
                    renderMetadataUI(imageId, response.data.metadata, statusContainer);
                }
                fetchUsageStatus();
                processBulkGeneration(ids, index + 1, $button, total, statusContainer, stopButton, progressBar, messageContainer);
            },
            error: function(xhr) {
                $(messageContainer).text(`Image ${imageId} - AJAX error: ${xhr.responseText}`);
                fetchUsageStatus();
                processBulkGeneration(ids, index + 1, $button, total, statusContainer, stopButton, progressBar, messageContainer);
            }
        });
    }

    function renderMetadataUI(imageId, metadata, statusContainer) {
        var mediaLibraryUrl = `/wp-admin/post.php?post=${imageId}&action=edit`;

        $.ajax({
            url: oneclick_images_admin_vars.ajax_url,
            type: 'GET',
            data: {
                action: 'get_thumbnail',
                image_id: imageId,
                oneclick_images_ajax_nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce
            },
            success: function(thumbnailResponse) {
                console.log('Thumbnail Response:', thumbnailResponse);
                const thumbnailUrl = thumbnailResponse.success && thumbnailResponse.data?.thumbnail ?
                    thumbnailResponse.data.thumbnail :
                    oneclick_images_admin_vars.fallback_image_url;
                buildMetadataDisplay(mediaLibraryUrl, thumbnailUrl, metadata, imageId, statusContainer);
            },
            error: function(xhr, status, error) {
                console.error('Thumbnail Fetch Error:', status, error, xhr.responseText);
                const thumbnailUrl = oneclick_images_admin_vars.fallback_image_url;
                buildMetadataDisplay(mediaLibraryUrl, thumbnailUrl, metadata, imageId, statusContainer);
            }
        });
    }

    function buildMetadataDisplay(mediaLibraryUrl, thumbnailUrl, metadata, imageId, statusContainer) {
        const safeThumbnailUrl = $('<div/>').text(thumbnailUrl).html();
        const safeMediaLibraryUrl = $('<div/>').text(mediaLibraryUrl).html();
        const safeImageId = parseInt(imageId, 10);

        let displayMetadata = metadata;
        if (metadata && typeof metadata === 'object' && metadata.metadata && typeof metadata.metadata === 'string') {
            try {
                displayMetadata = JSON.parse(metadata.metadata);
            } catch (e) {
                console.error(`[Bulk Metadata] Failed to parse metadata string for ID ${imageId}:`, e);
                displayMetadata = {};
            }
        } else if (metadata && typeof metadata === 'object' && metadata.metadata && typeof metadata.metadata === 'object') {
            displayMetadata = metadata.metadata;
        }

        let metadataRows = '<tr><td colspan="2">No metadata available</td></tr>';
        if (displayMetadata && typeof displayMetadata === 'object' && !Array.isArray(displayMetadata) && Object.keys(displayMetadata).length > 0) {
            metadataRows = Object.entries(displayMetadata)
                .map(([key, value]) => {
                    let displayKey = key;
                    let displayValue = value;

                    if (key === 'alt_text') {
                        displayKey = 'Alt Tag';
                    } else {
                        displayKey = key.charAt(0).toUpperCase() + key.slice(1);
                    }

                    if (value === null || value === undefined) {
                        displayValue = '';
                    } else if (typeof value === 'object') {
                        displayValue = JSON.stringify(value);
                    }

                    if (key === 'title') {
                        displayValue = `<a href="${safeMediaLibraryUrl}" target="_blank">${$('<div/>').text(displayValue).html()} <span class="dashicons dashicons-external"></span></a>`;
                    } else {
                        displayValue = $('<div/>').text(displayValue).html();
                    }

                    return `<tr><td>${displayKey}</td><td>${displayValue}</td></tr>`;
                })
                .join('');
        }

        const content = `
            <div class="status-item">
                <div class="thumbnail-container">
                    <img
                        src="${safeThumbnailUrl}"
                        alt="Thumbnail for ${safeImageId}"
                        class="thumbnail-preview attachment-thumbnail size-thumbnail"
                        onerror="this.src='${oneclick_images_admin_vars.fallback_image_url}';"
                    />
                </div>
                <div class="metadata-container">
                    <table id="image-metadata-table" class="metadata-table">
                        ${metadataRows}
                    </table>
                </div>
            </div>
        `;

        $(statusContainer).append(content);
    }

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
                } else if (retryCount > 0) {
                    setTimeout(() => fetchUsageStatus(retryCount - 1), 1000);
                } else {
                    $('#usage_count').html('Error: Unable to fetch usage information.');
                    $('#usage_progress').css('width', '0%').text('0%');
                }
            },
            error: function() {
                if (retryCount > 0) {
                    setTimeout(() => fetchUsageStatus(retryCount - 1), 1000);
                } else {
                    $('#usage_count').html('Error: An error occurred.');
                    $('#usage_progress').css('width', '0%').text('0%');
                }
            }
        });
    }

    function updateUsageStatusUI(usedCount, totalAllowed, remainingCount) {
        const percentageUsed = totalAllowed > 0 ? (usedCount / totalAllowed) * 100 : 0;
        $('#usage_count').html(
            `Used: ${usedCount} of ${totalAllowed} images (${remainingCount} remaining)`
        );
        $('#usage_progress')
            .css('width', `${Math.min(percentageUsed, 100)}%`)
            .attr('aria-valuenow', Math.min(percentageUsed, 100))
            .text(`${Math.round(percentageUsed)}% Used`);

        if (percentageUsed >= 90) {
            $('#usage_progress').removeClass('bg-success bg-warning').addClass('bg-danger');
        } else if (percentageUsed >= 70) {
            $('#usage_progress').removeClass('bg-success bg-danger').addClass('bg-warning');
        } else {
            $('#usage_progress').removeClass('bg-warning bg-danger').addClass('bg-success');
        }
    }

    fetchUsageStatus();
});