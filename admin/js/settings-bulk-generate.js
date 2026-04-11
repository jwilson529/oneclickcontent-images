/**
 * Bulk generation JavaScript functionality for OCCIDG.
 *
 * @package One_Click_Images
 */
jQuery(document).ready(function($) {
    'use strict';

    let stopBulkGeneration = false;

    $('#generate-all-metadata-settings, #generate-all-metadata').on('click', function() {
        const $button = $(this);
        const isSettingsTab = 'generate-all-metadata-settings' === $button.attr('id');
        const statusContainer = isSettingsTab ? '#bulk-generate-status-settings' : '#bulk-generate-status';
        const stopButton = isSettingsTab ? '#stop-bulk-generation-settings' : '#stop-bulk-generation';
        const progressBar = isSettingsTab ? '#bulk-generate-progress-bar-settings' : '#bulk-generate-progress-bar';
        const messageContainer = isSettingsTab ? '#bulk-generate-message-settings' : '#bulk-generate-message';

        $('#bulk-generate-modal').show();

        $.ajax({
            url: occidg_admin_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'occidg_check_override_metadata',
                nonce: occidg_admin_vars.occidg_ajax_nonce,
            },
        }).done(function(response) {
            if (response.success && (true === response.data.override || '1' === response.data.override)) {
                $('#bulk-generate-warning').show();
            } else {
                $('#bulk-generate-warning').hide();
            }
        }).fail(function() {
            $('#bulk-generate-warning').hide();
        });

        $('#confirm-bulk-generate').off('click').on('click', function() {
            $('#bulk-generate-modal').hide();
            startBulkGeneration($button, statusContainer, stopButton, progressBar, messageContainer);
        });

        $('#cancel-bulk-generate').off('click').on('click', function() {
            $('#bulk-generate-modal').hide();
        });
    });

    $('#stop-bulk-generation-settings, #stop-bulk-generation').on('click', function() {
        stopBulkGeneration = true;
        const isSettingsTab = 'stop-bulk-generation-settings' === $(this).attr('id');
        const messageContainer = isSettingsTab ? '#bulk-generate-message-settings' : '#bulk-generate-message';
        const generateButton = isSettingsTab ? '#generate-all-metadata-settings' : '#generate-all-metadata';

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
            url: occidg_admin_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'occidg_get_all_media_ids',
                nonce: occidg_admin_vars.occidg_ajax_nonce,
            },
        }).done(function(response) {
            if (response.success && response.data.ids.length > 0) {
                processBulkGeneration(response.data.ids, 0, $button, response.data.ids.length, statusContainer, stopButton, progressBar, messageContainer);
            } else {
                $(messageContainer).text('No media items found.');
                $button.prop('disabled', false).text('Generate All Metadata');
                $(stopButton).hide();
            }
        }).fail(function(xhr) {
            $(messageContainer).text('Error fetching media IDs: ' + xhr.responseText);
            $button.prop('disabled', false).text('Generate All Metadata');
            $(stopButton).hide();
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
            $(messageContainer).text('All metadata generation complete.');
            $(progressBar).css('width', '100%');
            $button.prop('disabled', false).text('Generate All Metadata');
            $(stopButton).hide();
            return;
        }

        const imageId = ids[index];
        const percent = Math.round(((index + 1) / total) * 100);
        $(messageContainer).text(`Processing image ${index + 1} of ${total} (ID: ${imageId})`);
        $(progressBar).css('width', percent + '%');

        $.ajax({
            url: occidg_admin_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'occidg_generate_metadata',
                nonce: occidg_admin_vars.occidg_ajax_nonce,
                image_id: imageId,
            },
        }).done(function(response) {
            if (response.success && response.data && response.data.metadata) {
                renderMetadataUI(imageId, response.data.metadata, statusContainer);
            } else {
                const errorMessage = response && response.data && response.data.error ? response.data.error : 'Unknown error';
                $(messageContainer).text(`Image ${imageId} - Error: ${errorMessage}`);
            }

            processBulkGeneration(ids, index + 1, $button, total, statusContainer, stopButton, progressBar, messageContainer);
        }).fail(function(xhr) {
            $(messageContainer).text(`Image ${imageId} - AJAX error: ${xhr.responseText}`);
            processBulkGeneration(ids, index + 1, $button, total, statusContainer, stopButton, progressBar, messageContainer);
        });
    }

    function renderMetadataUI(imageId, metadata, statusContainer) {
        const mediaLibraryUrl = `/wp-admin/post.php?post=${imageId}&action=edit`;

        $.ajax({
            url: occidg_admin_vars.ajax_url,
            type: 'GET',
            data: {
                action: 'get_thumbnail',
                image_id: imageId,
                occidg_ajax_nonce: occidg_admin_vars.occidg_ajax_nonce,
            },
        }).done(function(thumbnailResponse) {
            const thumbnailUrl = thumbnailResponse.success && thumbnailResponse.data && thumbnailResponse.data.thumbnail ? thumbnailResponse.data.thumbnail : occidg_admin_vars.fallback_image_url;
            buildMetadataDisplay(mediaLibraryUrl, thumbnailUrl, metadata, imageId, statusContainer);
        }).fail(function() {
            buildMetadataDisplay(mediaLibraryUrl, occidg_admin_vars.fallback_image_url, metadata, imageId, statusContainer);
        });
    }

    function buildMetadataDisplay(mediaLibraryUrl, thumbnailUrl, metadata, imageId, statusContainer) {
        const safeThumbnailUrl = $('<div/>').text(thumbnailUrl).html();
        const safeMediaLibraryUrl = $('<div/>').text(mediaLibraryUrl).html();
        const safeImageId = parseInt(imageId, 10);
        const displayMetadata = metadata && metadata.metadata ? metadata.metadata : metadata || {};

        let metadataRows = '<tr><td colspan="2">No metadata available</td></tr>';
        if (displayMetadata && typeof displayMetadata === 'object' && !Array.isArray(displayMetadata) && Object.keys(displayMetadata).length > 0) {
            metadataRows = Object.entries(displayMetadata).map(function(entry) {
                const key = entry[0];
                let value = entry[1];
                let displayKey = 'alt_text' === key ? 'Alt Tag' : key.charAt(0).toUpperCase() + key.slice(1);

                if (null === value || undefined === value) {
                    value = '';
                } else if (typeof value === 'object') {
                    value = JSON.stringify(value);
                }

                if ('title' === key) {
                    value = `<a href="${safeMediaLibraryUrl}" target="_blank">${$('<div/>').text(value).html()} <span class="dashicons dashicons-external"></span></a>`;
                } else {
                    value = $('<div/>').text(value).html();
                }

                return `<tr><td>${displayKey}</td><td>${value}</td></tr>`;
            }).join('');
        }

        $(statusContainer).append(`
            <div class="status-item">
                <div class="thumbnail-container">
                    <img src="${safeThumbnailUrl}" alt="Thumbnail for ${safeImageId}" class="thumbnail-preview attachment-thumbnail size-thumbnail" onerror="this.src='${occidg_admin_vars.fallback_image_url}';" />
                </div>
                <div class="metadata-container">
                    <table id="image-metadata-table" class="metadata-table">
                        ${metadataRows}
                    </table>
                </div>
            </div>
        `);
    }
});
