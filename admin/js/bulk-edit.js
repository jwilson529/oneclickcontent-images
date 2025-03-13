jQuery(document).ready(function($) {
    console.log('[bulk-edit.js] Script loaded and document.ready executed');

    // Debounce function to prevent rapid saves.
    function debounce(func, wait) {
        var timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Check if DataTable is already initialized.
    var table;
    if ($.fn.DataTable.isDataTable('#image-metadata-table')) {
        console.log('[bulk-edit.js] DataTable already initialized, reusing instance');
        table = $('#image-metadata-table').DataTable();
    } else {
        console.log('[bulk-edit.js] Initializing new DataTable instance');
        table = $('#image-metadata-table').DataTable({
            serverSide: true,
            processing: true,
            ajax: {
                url: oneclick_images_bulk_vars.ajax_url,
                type: 'POST',
                data: function(d) {
                    d.action = 'oneclick_images_get_image_metadata';
                    d.nonce = oneclick_images_bulk_vars.nonce;
                }
            },
            columns: [
                { data: 'thumbnail', orderable: false, searchable: false },
                { 
                    data: 'title',
                    render: function(data, type, row) {
                        return `<div class="input-wrapper"><input type="text" value="${$('<div/>').text(data || '').html()}" data-field="title" data-image-id="${row.id}" style="width: 100%;"><span class="save-status"></span></div>`;
                    }
                },
                { 
                    data: 'alt_text',
                    render: function(data, type, row) {
                        return `<div class="input-wrapper"><input type="text" value="${$('<div/>').text(data || '').html()}" data-field="alt_text" data-image-id="${row.id}" style="width: 100%;"><span class="save-status"></span></div>`;
                    }
                },
                { 
                    data: 'description',
                    render: function(data, type, row) {
                        return `<div class="input-wrapper"><textarea data-field="description" data-image-id="${row.id}" style="width: 100%; height: 60px;">${$('<div/>').text(data || '').html()}</textarea><span class="save-status"></span></div>`;
                    }
                },
                { 
                    data: 'caption',
                    render: function(data, type, row) {
                        return `<div class="input-wrapper"><textarea data-field="caption" data-image-id="${row.id}" style="width: 100%; height: 60px;">${$('<div/>').text(data || '').html()}</textarea><span class="save-status"></span></div>`;
                    }
                },
                { 
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return `<div class="action-wrapper"><button class="generate-metadata button" data-image-id="${row.id}">Generate</button><span class="action-status"></span></div>`;
                    }
                }
            ],
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            dom: 'Bfrtip',
            buttons: ['csv'],
            order: [[5, 'asc']] // Default sort by ID
        });
    }

    var saveField = debounce(function($input) {
        var imageId = $input.data('image-id');
        var field = $input.data('field');
        var newValue = $input.val();
        var originalValue = $input.data('original-value') || '';
        var $status = $input.siblings('.save-status');
        var $row = $input.closest('tr');
        var rowIndex = table.row($row).index(); // Store row index for reliability
        var rowData = table.row($row).data();

        console.log('[saveField] Before Save - Row Data:', rowData);
        console.log('[saveField] Field:', field, 'New Value:', newValue);

        if (!rowData) {
            console.error('[saveField] Row data is undefined before save. Reloading table.');
            $status.text('Error: Row data lost').addClass('save-status-error').fadeIn();
            table.ajax.reload(function() {
                $status.text('Table reloaded').addClass('save-status-saved').fadeIn();
                setTimeout(() => $status.fadeOut(), 2000);
            }, false);
            return;
        }

        if (newValue === originalValue) {
            $status.text('No changes').addClass('save-status-nochange').fadeIn();
            setTimeout(() => $status.fadeOut(), 2000);
            return;
        }

        $status.text('Saving...').addClass('save-status-saving').fadeIn();

        var allFields = {
            title: $row.find('input[data-field="title"]').val() || rowData.title || '',
            alt_text: $row.find('input[data-field="alt_text"]').val() || rowData.alt_text || '',
            description: $row.find('textarea[data-field="description"]').val() || rowData.description || '',
            caption: $row.find('textarea[data-field="caption"]').val() || rowData.caption || ''
        };

        console.log('[saveField] Sending to Server:', allFields);

        $.ajax({
            url: oneclick_images_bulk_vars.ajax_url,
            method: 'POST',
            data: {
                action: 'oneclick_images_save_bulk_metadata',
                nonce: oneclick_images_bulk_vars.nonce,
                image_id: imageId,
                title: allFields.title,
                alt_text: allFields.alt_text,
                description: allFields.description,
                caption: allFields.caption,
                updated_field: field
            },
            success: function(response) {
                console.log('[saveField] Server Response:', response);
                if (response.success && response.data) {
                    $status.text('Saved!').addClass('save-status-saved').fadeIn();
                    $input.data('original-value', newValue);

                    // Use server-returned data to update the row
                    var updatedData = response.data;
                    var currentData = table.row(rowIndex).data(); // Use row index for consistency

                    if (currentData) {
                        currentData.thumbnail = currentData.thumbnail || updatedData.thumbnail;
                        currentData.id = updatedData.id || currentData.id;
                        currentData.title = updatedData.title;
                        currentData.alt_text = updatedData.alt_text;
                        currentData.description = updatedData.description;
                        currentData.caption = updatedData.caption;

                        console.log('[saveField] Updated Data:', currentData);

                        // Update the row without triggering a full server fetch
                        table.row(rowIndex).data(currentData).draw(false);
                        console.log('[saveField] After Draw - Row Data:', table.row(rowIndex).data());
                    } else {
                        console.error('[saveField] Row data is undefined after save. Reloading table.');
                        table.ajax.reload(null, false);
                    }
                } else {
                    $status.text('Error: ' + (response.data || 'Invalid response')).addClass('save-status-error').fadeIn();
                }
                setTimeout(() => $status.fadeOut(), 2000);
            },
            error: function(xhr) {
                console.log('[saveField] AJAX Error:', xhr.responseText);
                $status.text('Error: ' + xhr.responseText).addClass('save-status-error').fadeIn();
                setTimeout(() => $status.fadeOut(), 2000);
            }
        });
    }, 500);

    table.on('focus', 'input, textarea', function() {
        var $input = $(this);
        $input.data('original-value', $input.val());
    }).on('blur', 'input, textarea', function() {
        var $input = $(this);
        saveField($input);
    });

    // Generate metadata for one image.
    table.on('click', '.generate-metadata', function() {
        var $button = $(this);
        var imageId = $button.data('image-id');
        var $status = $button.siblings('.action-status');
        $button.prop('disabled', true).text('Generating...');

        $.ajax({
            url: oneclick_images_admin_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'oneclick_images_generate_metadata',
                nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                image_id: imageId
            },
            success: function(response) {
                console.log('[Metadata Gen] Response:', response);

                // Check if outer request was OK
                if (response.success && response.data && response.data.metadata) {
                    var metadata = response.data.metadata;

                    // 🛑 Check if the inner metadata response has an over-limit error
                    if (metadata.success === false && metadata.error && metadata.error.includes('Free trial limit')) {
                        console.log('[Metadata Gen] Metadata over limit detected – showing modal');
                        showSubscriptionPrompt(
                            'over_limit',
                            metadata.message || metadata.error,
                            metadata.ad_url || oneclick_images_admin_vars.subscription_url
                        );
                        $button.prop('disabled', false).text('Generate');
                        return;
                    }

                    // ✅ Continue with success logic if metadata is good
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
                            const thumbnailUrl = thumbnailResponse.success && thumbnailResponse.data?.thumbnail ?
                                thumbnailResponse.data.thumbnail :
                                oneclick_images_admin_vars.fallback_image_url;
                            renderMetadataUI(mediaLibraryUrl, thumbnailUrl, metadata, imageId);
                        },
                        error: function() {
                            const thumbnailUrl = oneclick_images_admin_vars.fallback_image_url;
                            renderMetadataUI(mediaLibraryUrl, thumbnailUrl, metadata, imageId);
                        }
                    });

                    var row = table.row($button.closest('tr')).data();
                    table.row($button.closest('tr')).data({
                        thumbnail: row.thumbnail,
                        title: metadata.title || row.title,
                        alt_text: metadata.alt_text || row.alt_text,
                        description: metadata.description || row.description,
                        caption: metadata.caption || row.caption,
                        id: imageId
                    }).draw(false);

                    $status.text('Done!').addClass('action-status-success').fadeIn();
                    $button.prop('disabled', false).text('Generate');
                    setTimeout(() => $status.fadeOut(), 2000);

                } else {
                    console.log('[Metadata Gen] Top-level error:', response);
                    $status.text('Error: ' + (response.message || response.error || 'Failed'))
                        .addClass('action-status-error').fadeIn();
                    $button.prop('disabled', false).text('Generate');
                    setTimeout(() => $status.fadeOut(), 2000);
                }
            },
            error: function(xhr) {
                console.log('[Metadata Gen] AJAX error:', xhr);
                $status.text('Error: ' + xhr.responseText).addClass('action-status-error').fadeIn();
                $button.prop('disabled', false).text('Generate');
                setTimeout(() => $status.fadeOut(), 2000);
            }
        });
    });

    // Generate metadata for all images with progress indicators.
    // Remove existing handlers to prevent duplicates.
    $('#generate-all-metadata').off('click').on('click', function() {
        if (!confirm('Generate metadata for all images in your library? This may take some time.')) return;
        var $button = $(this);
        $button.prop('disabled', true).text('Generating...');
        $('#bulk-generate-status').empty();
        $('#image-metadata-table').addClass('loading');

        $.ajax({
            url: oneclick_images_admin_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'oneclick_images_get_all_media_ids',
                nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce
            },
            success: function(response) {
                if (response.success && response.data.ids.length > 0) {
                    processBulkGeneration(response.data.ids, 0, $button, response.data.ids.length);
                } else {
                    $('#bulk-generate-status').append('<p>No media items found.</p>');
                    $button.prop('disabled', false).text('Generate All Metadata');
                    $('#image-metadata-table').removeClass('loading');
                }
            },
            error: function(xhr) {
                $('#bulk-generate-status').append('<p>Error fetching media IDs: ' + xhr.responseText + '</p>');
                $button.prop('disabled', false).text('Generate All Metadata');
                $('#image-metadata-table').removeClass('loading');
            }
        });
    });

    function processBulkGeneration(ids, index, $button, total) {
        if (index >= ids.length) {
            $('#bulk-generate-status').append('<p>All metadata generation complete!</p>');
            $button.prop('disabled', false).text('Generate All Metadata');
            $('#image-metadata-table').removeClass('loading');
            table.ajax.reload(null, false); // Reload without resetting paging.
            return;
        }

        var imageId = ids[index];
        var progress = ((index + 1) / total) * 100;
        $('#bulk-generate-status').append(`<p>Processing ${imageId} (${Math.round(progress)}% complete)</p>`);

        // Find row in current page using DataTables API.
        var rowIndex = null;
        table.rows().every(function(index) {
            var data = this.data();
            if (data.id === imageId) {
                rowIndex = index;
                return false; // Break the loop.
            }
        });

        if (rowIndex !== null) {
            var $row = table.row(rowIndex).node();
            var $status = $($row).find('.action-status');
            var $generateButton = $($row).find('.generate-metadata');
            $generateButton.prop('disabled', true).text('Generating...');
            $status.text('Generating...').addClass('action-status-working').fadeIn();
        }

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
                    var metadata = response.data.metadata;
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
                            const thumbnailUrl = thumbnailResponse.success && thumbnailResponse.data?.thumbnail ?
                                thumbnailResponse.data.thumbnail :
                                oneclick_images_admin_vars.fallback_image_url;
                            renderMetadataUI(mediaLibraryUrl, thumbnailUrl, metadata, imageId);
                        },
                        error: function() {
                            const thumbnailUrl = oneclick_images_admin_vars.fallback_image_url;
                            renderMetadataUI(mediaLibraryUrl, thumbnailUrl, metadata, imageId);
                        }
                    });

                    if (rowIndex !== null) {
                        var rowData = table.row(rowIndex).data();
                        table.row(rowIndex).data({
                            thumbnail: rowData.thumbnail,
                            title: metadata.title || rowData.title,
                            alt_text: metadata.alt_text || rowData.alt_text,
                            description: metadata.description || rowData.description,
                            caption: metadata.caption || rowData.caption,
                            id: imageId
                        }).draw(false);
                        $status.text('Done!').addClass('action-status-success').fadeIn();
                        $generateButton.prop('disabled', false).text('Generate');
                        setTimeout(() => $status.fadeOut(), 2000);
                    }
                } else {
                    if (rowIndex !== null) {
                        $status.text('Error: ' + (response.data.message || 'Failed')).addClass('action-status-error').fadeIn();
                        $generateButton.prop('disabled', false).text('Generate');
                        setTimeout(() => $status.fadeOut(), 2000);
                    }
                }
                processBulkGeneration(ids, index + 1, $button, total);
            },
            error: function(xhr) {
                $('#bulk-generate-status').append(`<p>${imageId} - AJAX error: ${xhr.responseText}</p>`);
                if (rowIndex !== null) {
                    $status.text('Error: ' + xhr.responseText).addClass('action-status-error').fadeIn();
                    $generateButton.prop('disabled', false).text('Generate');
                    setTimeout(() => $status.fadeOut(), 2000);
                }
                processBulkGeneration(ids, index + 1, $button, total);
            }
        });
    }

    function renderMetadataUI(mediaLibraryUrl, thumbnailUrl, metadata, imageId) {
            console.log(`[Bulk Metadata] renderMetadataUI called - ID: ${imageId}, Thumbnail: ${thumbnailUrl}, Media URL: ${mediaLibraryUrl}, Metadata:`, metadata);

            const safeThumbnailUrl = $('<div/>').text(thumbnailUrl).html();
            const safeMediaLibraryUrl = $('<div/>').text(mediaLibraryUrl).html();
            const safeImageId = parseInt(imageId, 10);

            // Handle nested metadata structure
            let displayMetadata = metadata;
            if (metadata && typeof metadata === 'object' && metadata.metadata && typeof metadata.metadata === 'string') {
                try {
                    displayMetadata = JSON.parse(metadata.metadata); // Parse the stringified inner metadata
                } catch (e) {
                    console.error(`[Bulk Metadata] Failed to parse metadata string for ID ${imageId}:`, e);
                    displayMetadata = {}; // Fallback to empty object if parsing fails
                }
            } else if (metadata && typeof metadata === 'object' && metadata.metadata && typeof metadata.metadata === 'object') {
                displayMetadata = metadata.metadata; // Use the nested object directly
            }

            // Generate metadata rows
            let metadataRows = '<tr><td colspan="2">No metadata available</td></tr>';
            if (displayMetadata && typeof displayMetadata === 'object' && !Array.isArray(displayMetadata) && Object.keys(displayMetadata).length > 0) {
                metadataRows = Object.entries(displayMetadata)
                    .map(([key, value]) => {
                        let displayValue = value;
                        if (value === null || value === undefined) {
                            displayValue = '';
                        } else if (typeof value === 'object') {
                            displayValue = JSON.stringify(value); // Fallback for nested objects
                        }
                        return `<tr><td>${$('<div/>').text(key).html()}</td><td>${$('<div/>').text(displayValue).html()}</td></tr>`;
                    })
                    .join('');
            }

            const metadataTable = `
                <table class="metadata-table">
                    <tr><th>Key</th><th>Value</th></tr>
                    ${metadataRows}
                </table>
            `;

            const content = `
                <div class="status-item">
                    <div class="thumbnail-container">
                        <img
                            src="${safeThumbnailUrl}"
                            alt="Thumbnail for ${safeImageId}"
                            class="thumbnail-preview"
                            onerror="this.src='${oneclick_images_admin_vars.fallback_image_url}';"
                        />
                    </div>
                    <div class="metadata-container">
                        <p>
                            <a href="${safeMediaLibraryUrl}" target="_blank">
                                ${safeImageId} - Done
                                <span class="dashicons dashicons-external"></span>
                            </a>
                        </p>
                        ${metadataTable}
                    </div>
                </div>
            `;

            $('#bulk-generate-status').append(content);
            $('#bulk-generate-status').show(); // Show the parent container
            console.log(`[Bulk Metadata] UI rendered for ID: ${imageId}`);
        }

    // Initialize usage status.
    function fetchUsageStatus(retryCount = 3) {
        console.log('[bulk-edit.js] Fetching usage status');
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

    function updateUsageStatusUI(usedCount, totalAllowed, remainingCount) {
        const usageCount = $('#usage_count');
        const usageProgress = $('#usage_progress');

        if (typeof usedCount === 'undefined' || typeof totalAllowed === 'undefined' || typeof remainingCount === 'undefined') {
            usageCount.html('<strong>Error:</strong> Invalid usage data.');
            usageProgress.css('width', '0%').text('0%');
            return;
        }

        const percentageUsed = totalAllowed > 0 ? (usedCount / totalAllowed) * 100 : 0;
        usageCount.html(
            `<strong>Used:</strong> ${usedCount} of ${totalAllowed} images (${remainingCount} remaining)`
        );
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

    fetchUsageStatus();
});