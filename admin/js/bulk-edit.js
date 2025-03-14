jQuery(document).ready(function($) {
    console.log('[bulk-edit.js] Script loaded and document.ready executed');

    // Debounce function to prevent rapid saves
    function debounce(func, wait) {
        var timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Check if DataTable is already initialized
    var table;
    if ($.fn.DataTable.isDataTable('#image-metadata-table')) {
        console.log('[bulk-edit.js] DataTable already initialized, reusing instance');
        table = $('#image-metadata-table').DataTable();
    } else {
        console.log('[bulk-edit.js] Initializing new DataTable instance');
        table = $('#image-metadata-table').DataTable({
            serverSide: true,
            processing: true,
            searching: true,
            ordering: false,
            ajax: {
                url: oneclick_images_bulk_vars.ajax_url,
                type: 'POST',
                data: function(d) {
                    d.action = 'oneclick_images_get_image_metadata';
                    d.nonce = oneclick_images_bulk_vars.nonce;
                }
            },
            columns: [
                { 
                    data: 'thumbnail', 
                    orderable: false, 
                    searchable: false 
                },
                { 
                    data: 'title',
                    render: function(data, type, row) {
                        return `<div class="input-wrapper"><input type="text" value="${$('<div/>').text(data || '').html()}" data-field="title" data-image-id="${row.id}" style="width: 100%;"><span class="save-status"></span></div>`;
                    },
                    orderable: false
                },
                { 
                    data: 'alt_text',
                    render: function(data, type, row) {
                        return `<div class="input-wrapper"><input type="text" value="${$('<div/>').text(data || '').html()}" data-field="alt_text" data-image-id="${row.id}" style="width: 100%;"><span class="save-status"></span></div>`;
                    },
                    orderable: false
                },
                { 
                    data: 'description',
                    render: function(data, type, row) {
                        return `<div class="input-wrapper"><textarea data-field="description" data-image-id="${row.id}" style="width: 100%; height: 60px;">${$('<div/>').text(data || '').html()}</textarea><span class="save-status"></span></div>`;
                    },
                    orderable: false
                },
                { 
                    data: 'caption',
                    render: function(data, type, row) {
                        return `<div class="input-wrapper"><textarea data-field="caption" data-image-id="${row.id}" style="width: 100%; height: 60px;">${$('<div/>').text(data || '').html()}</textarea><span class="save-status"></span></div>`;
                    },
                    orderable: false
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
            lengthMenu: [10, 25, 50, 100]
        });
    }

    var saveField = debounce(function($input, callback) {
        var imageId = $input.data('image-id');
        var field = $input.data('field');
        var newValue = $input.val();
        var originalValue = $input.data('original-value') || '';
        var $status = $input.siblings('.save-status');
        var $row = $input.closest('tr');
        var rowIndex = table.row($row).index();
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
            if (callback) callback();
            return;
        }

        if (newValue === originalValue) {
            $status.text('No changes').addClass('save-status-nochange').fadeIn();
            setTimeout(() => $status.fadeOut(), 2000);
            if (callback) callback();
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

                    var updatedData = response.data;
                    var currentData = table.row(rowIndex).data();

                    if (currentData) {
                        currentData.thumbnail = currentData.thumbnail || updatedData.thumbnail;
                        currentData.id = updatedData.id || currentData.id;
                        currentData.title = updatedData.title;
                        currentData.alt_text = updatedData.alt_text;
                        currentData.description = updatedData.description;
                        currentData.caption = updatedData.caption;

                        console.log('[saveField] Updated Data:', currentData);

                        table.row(rowIndex).data(currentData);
                        $input.val(updatedData[field]);
                    } else {
                        console.error('[saveField] Row data is undefined after save. Reloading table.');
                        table.ajax.reload(null, false);
                    }
                } else {
                    $status.text('Error: ' + (response.data || 'Invalid response')).addClass('save-status-error').fadeIn();
                }
                setTimeout(() => $status.fadeOut(), 2000);
                if (callback) callback();
            },
            error: function(xhr) {
                console.log('[saveField] AJAX Error:', xhr.responseText);
                $status.text('Error: ' + xhr.responseText).addClass('save-status-error').fadeIn();
                setTimeout(() => $status.fadeOut(), 2000);
                if (callback) callback();
            }
        });
    }, 500);

    table.on('focus', 'input, textarea', function() {
        var $input = $(this);
        $input.data('original-value', $input.val());
    });

    table.on('blur', 'input, textarea', function() {
        var $input = $(this);
        saveField($input);
    });

    table.on('keydown', 'input, textarea', function(e) {
        if (e.key === 'Tab') {
            e.preventDefault();
            var $input = $(this);
            var $row = $input.closest('tr');
            var $inputs = $row.find('input, textarea');
            var currentIndex = $inputs.index($input);
            var nextIndex = (currentIndex + 1) % $inputs.length;
            var $nextInput = nextIndex < $inputs.length ? $inputs.eq(nextIndex) : null;

            // If no next input in this row, move to the next row
            if (!$nextInput) {
                var nextRowIndex = table.row($row).index() + 1;
                if (nextRowIndex < table.rows().count()) {
                    var $nextRowInputs = table.row(nextRowIndex).node().querySelectorAll('input, textarea');
                    $nextInput = $nextRowInputs.length ? $($nextRowInputs[0]) : null;
                }
            }

            if ($nextInput) {
                // Save current input and move focus after save completes
                saveField($input, function() {
                    // Restore cursor position and move focus
                    var cursorPos = $input[0].selectionStart;
                    $input.val($input.val()); // Ensure DOM reflects latest value
                    $nextInput.focus();
                    // For textareas, try to maintain a reasonable cursor position
                    if ($nextInput.is('textarea')) {
                        $nextInput[0].selectionStart = 0;
                        $nextInput[0].selectionEnd = 0;
                    }
                });
            }
        }
    });

    table.on('click', '.generate-metadata', function() {
        var $button = $(this);
        var imageId = $button.data('image-id');
        var $status = $button.siblings('.action-status');
        var $row = $button.closest('tr');
        var rowIndex = table.row($row).index();
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

                if (response.success && response.data && response.data.metadata) {
                    var metadata = response.data.metadata;
                    if (typeof metadata === 'object' && metadata.metadata) {
                        metadata = metadata.metadata;
                    }

                    if (metadata.success === false) {
                        if (metadata.error && metadata.error.includes('Free trial limit')) {
                            console.log('[Metadata Gen] Metadata over limit detected – showing modal');
                            showModal('Free Trial Limit Reached', metadata.message || metadata.error, metadata.ad_url || oneclick_images_admin_vars.subscription_url);
                        } else if (metadata.error && metadata.error.includes('Image validation failed')) {
                            console.log('[Metadata Gen] Image validation failed – showing modal');
                            showModal('Image Validation Failed', metadata.error, metadata.ad_url || '');
                        }
                        $button.prop('disabled', false).text('Generate');
                        return;
                    }

                    var rowData = table.row(rowIndex).data();
                    if (rowData) {
                        rowData.title = metadata.title || rowData.title;
                        rowData.alt_text = metadata.alt_text || rowData.alt_text;
                        rowData.description = metadata.description || rowData.description;
                        rowData.caption = metadata.caption || rowData.caption;

                        console.log('[Metadata Gen] Updated Row Data:', rowData);

                        table.row(rowIndex).data(rowData);

                        $row.find('input[data-field="title"]').val(rowData.title);
                        $row.find('input[data-field="alt_text"]').val(rowData.alt_text);
                        $row.find('textarea[data-field="description"]').val(rowData.description);
                        $row.find('textarea[data-field="caption"]').val(rowData.caption);

                        $status.text('Done!').addClass('action-status-success').fadeIn();
                        $button.prop('disabled', false).text('Generate');
                        setTimeout(() => $status.fadeOut(), 2000);
                    } else {
                        console.error('[Metadata Gen] Row data is undefined. Reloading table.');
                        table.ajax.reload(null, false);
                    }
                } else {
                    console.log('[Metadata Gen] Top-level error:', response);
                    $status.text('Error: ' + (response.message || response.error || 'Failed'))
                        .addClass('action-status-error').fadeIn();
                    $button.prop('disabled', false).text('Generate');
                    setTimeout(() => $status.fadeOut(), 2000);
                }
            },
            error: function(xhr) {
                console.log('[Metadata Gen] AJAX error:', xhr.responseText);
                $status.text('Error: ' + xhr.responseText).addClass('action-status-error').fadeIn();
                $button.prop('disabled', false).text('Generate');
                setTimeout(() => $status.fadeOut(), 2000);
            }
        });
    });

    // Function to show the modal with dynamic content
    function showModal(title, message, adUrl) {
        $('#bulk-generate-modal .modal-content h2').text(title);
        $('#bulk-generate-modal .modal-content p').text(message);
        $('#bulk-generate-warning').hide();
        if (adUrl) {
            $('#bulk-generate-modal .modal-buttons').html(
                '<a href="' + adUrl + '" target="_blank" class="button button-primary">Upgrade Now</a>' +
                '<button id="cancel-bulk-generate" class="button button-secondary">Close</button>'
            );
        } else {
            $('#bulk-generate-modal .modal-buttons').html(
                '<button id="cancel-bulk-generate" class="button button-secondary">Close</button>'
            );
        }
        $('#bulk-generate-modal').show();

        $('#cancel-bulk-generate').off('click').on('click', function() {
            $('#bulk-generate-modal').hide();
        });
    }
});