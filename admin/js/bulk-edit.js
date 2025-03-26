/**
 * OneClickContent Bulk Metadata Management.
 *
 * @package OneClickContent
 */

jQuery(document).ready(function ($) {
    'use strict';

    // =============================================================================
    // Utility Functions
    // =============================================================================

    /**
     * Debounces a function to prevent it from being called too frequently.
     *
     * @param {Function} func The function to debounce.
     * @param {number}   wait The delay in milliseconds before the function is executed.
     * @returns {Function} A debounced version of the input function.
     */
    function debounce(func, wait) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                func.apply(this, args);
            }, wait);
        };
    }

    /**
     * Decodes HTML entities in a string.
     *
     * @param {string} text The text containing HTML entities.
     * @returns {string} The decoded string.
     */
    function decodeHTMLEntities(text) {
        const parser = new DOMParser();
        const dom = parser.parseFromString('<!doctype html><body>' + text, 'text/html');
        return dom.body.textContent;
    }

    /**
     * Displays a modal with customizable content and buttons.
     *
     * @param {string} title   The title of the modal.
     * @param {string} message The message or HTML content to display in the modal.
     * @param {string} adUrl   Optional URL for a call-to-action link.
     */
    function showModal(title, message, adUrl) {
        $('#bulk-generate-modal .modal-content h2').text(title);
        $('#bulk-generate-modal .modal-content p').html(message); // Supports HTML content.
        $('#bulk-generate-warning').hide();

        // Setup modal buttons based on whether an ad URL is provided.
        const buttonText = title.includes('Credits') ? 'Get More Credits' : 'Activate License Now';
        $('#bulk-generate-modal .modal-buttons').html(
            adUrl
                ? `<a href="${adUrl}" target="_blank" class="button button-primary">${buttonText}</a>
                   <button id="cancel-bulk-generate" class="button button-secondary">Close</button>`
                : '<button id="cancel-bulk-generate" class="button button-secondary">Close</button>'
        );

        $('#bulk-generate-modal').show();

        // Handle modal close button.
        $('#cancel-bulk-generate').off('click').on('click', function () {
            $('#bulk-generate-modal').hide();
        });
    }

    // =============================================================================
    // DataTable Initialization
    // =============================================================================

    /**
     * Initializes or reuses a DataTable to display and manage image metadata.
     *
     * @type {DataTable}
     */
    let table;
    if ($.fn.DataTable.isDataTable('#image-metadata-table')) {
        table = $('#image-metadata-table').DataTable();
    } else {
        table = $('#image-metadata-table').DataTable({
            serverSide: true,
            processing: true,
            searching: true,
            ordering: false,
            autoWidth: false,
            deferRender: true,
            ajax: {
                url: oneclick_images_bulk_vars.ajax_url,
                type: 'POST',
                data: function (d) {
                    d.action = 'oneclick_images_get_image_metadata';
                    d.nonce  = oneclick_images_bulk_vars.nonce;
                },
            },
            columns: [
                { data: 'thumbnail', orderable: false, searchable: false },
                {
                    data: 'title',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return `<div class="input-wrapper">
                                <input type="text" value="${$('<div/>').text(data || '').html()}" data-field="title" data-image-id="${row.id}" style="width: 100%;">
                                <span class="save-status"></span>
                            </div>`;
                        }
                        return data;
                    },
                    orderable: false,
                },
                {
                    data: 'alt_text',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return `<div class="input-wrapper">
                                <input type="text" value="${$('<div/>').text(data || '').html()}" data-field="alt_text" data-image-id="${row.id}" style="width: 100%;">
                                <span class="save-status"></span>
                            </div>`;
                        }
                        return data;
                    },
                    orderable: false,
                },
                {
                    data: 'description',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return `<div class="input-wrapper">
                                <textarea data-field="description" data-image-id="${row.id}" style="width: 100%; height: 60px;">${$('<div/>').text(data || '').html()}</textarea>
                                <span class="save-status"></span>
                            </div>`;
                        }
                        return data;
                    },
                    orderable: false,
                },
                {
                    data: 'caption',
                    render: function (data, type, row) {
                        if (type === 'display') {
                            return `<div class="input-wrapper">
                                <textarea data-field="caption" data-image-id="${row.id}" style="width: 100%; height: 60px;">${$('<div/>').text(data || '').html()}</textarea>
                                <span class="save-status"></span>
                            </div>`;
                        }
                        return data;
                    },
                    orderable: false,
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function (data, type, row) {
                        if (type === 'display') {
                            const isOverLimit = oneclick_images_bulk_vars.is_valid_license && oneclick_images_bulk_vars.usage.remaining_count <= 0;
                            const isDisabled  = oneclick_images_bulk_vars.trial_expired || isOverLimit;
                            return `<div class="action-wrapper">
                                <button class="generate-metadata button" data-image-id="${row.id}" ${isDisabled ? 'disabled' : ''} title="${isDisabled ? (oneclick_images_bulk_vars.trial_expired ? 'Trial expired' : 'Out of credits') : ''}">Generate</button>
                                <span class="action-status"></span>
                            </div>`;
                        }
                        return null;
                    },
                },
            ],
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            drawCallback: function () {
                updateUsageDisplay();
                if (window.activeElementInfo) {
                    setTimeout(restoreFocus, 10);
                }
            },
            createdRow: function (row) {
                $(row).find('input, textarea').each(function () {
                    $(this).data('dt-no-redraw', true);
                });
            },
            preDrawCallback: function () {
                const $focused = $('input:focus, textarea:focus', this);
                if ($focused.length) {
                    $focused.closest('td').addClass('no-redraw');
                }
            },
            rowCallback: function (row) {
                $(row).find('td.no-redraw').each(function () {
                    $(this).removeClass('no-redraw');
                    return false;
                });
                // Preserve input values during redraw.
                $(row).find('input, textarea').each(function () {
                    const $input = $(this);
                    const field = $input.data('field');
                    const currentVal = $input.val();
                    if (currentVal !== table.row(row).data()[field]) {
                        table.row(row).data()[field] = currentVal;
                    }
                });
            },
        });
    }

    // =============================================================================
    // Event Handlers
    // =============================================================================

    /** Tracks the next element to receive focus after a blur event. */
    let nextFocusedElement = null;

    $(document).on('focus', '#image-metadata-table input, #image-metadata-table textarea', function () {
        $(this).closest('td').addClass('editing');
    });

    $(document).on('blur', '#image-metadata-table input, #image-metadata-table textarea', function () {
        $(this).closest('td').removeClass('editing');
    });

    table.on('focus', 'input, textarea', function () {
        $(this).data('original-value', $(this).val());
    });

    table.on('blur', 'input, textarea', function () {
        const $input = $(this);
        nextFocusedElement = document.activeElement === this ? null : document.activeElement;
        if ($input.val() !== $input.data('original-value')) {
            saveField($input, function () {
                if (nextFocusedElement) {
                    setTimeout(() => $(nextFocusedElement).focus(), 100);
                }
            });
        }
    });

    $('#image-metadata-table').on('click', 'td', function (e) {
        const $target = $(e.target);
        if (!$target.is('input') && !$target.is('textarea')) {
            $(this).find('input:visible, textarea:visible').first().focus();
        }
    });

    /**
     * Handles the click event for generating metadata.
     */
    table.on('click', '.generate-metadata', function () {
        const $button = $(this);
        const imageId = $button.data('image-id');
        const $status = $button.siblings('.action-status');
        const $row = $button.closest('tr');
        const rowIndex = table.row($row).index();

        // Check usage limits before proceeding.
        if (oneclick_images_bulk_vars.trial_expired) {
            window.showSubscriptionPrompt(
                'Free Trial Expired',
                'Your free trial has ended. Subscribe to continue generating metadata.',
                oneclick_images_bulk_vars.settings_url || 'https://oneclickcontent.com/image-detail-generator/'
            );
            return;
        }
        if (oneclick_images_bulk_vars.is_valid_license && oneclick_images_bulk_vars.usage.remaining_count <= 0) {
            window.showSubscriptionPrompt(
                'Usage Limit Reached',
                'You’ve used all your credits. Purchase more to continue.',
                oneclick_images_bulk_vars.settings_url || 'https://oneclickcontent.com/image-detail-generator/'
            );
            return;
        }

        $button.prop('disabled', true).text('Generating...');

        $.ajax({
            url: oneclick_images_admin_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'oneclick_images_generate_metadata',
                nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                image_id: imageId,
            },
            success: function (response) {
                // Inside the success callback for the generate-metadata AJAX request:
                if (response.success && response.data && response.data.metadata) {
                    // Successful metadata generation.
                    const metadata = response.data.metadata;
                    // Decode HTML entities for each field.
                    metadata.title = decodeHTMLEntities(metadata.title || '');
                    metadata.alt_text = decodeHTMLEntities(metadata.alt_text || '');
                    metadata.description = decodeHTMLEntities(metadata.description || '');
                    metadata.caption = decodeHTMLEntities(metadata.caption || '');

                    const rowData = table.row(rowIndex).data();
                    if (rowData) {
                        rowData.title = metadata.title || rowData.title;
                        rowData.alt_text = metadata.alt_text || rowData.alt_text;
                        rowData.description = metadata.description || rowData.description;
                        rowData.caption = metadata.caption || rowData.caption;

                        table.row(rowIndex).data(rowData).invalidate();
                        table.draw(false); // Redraw table without resetting paging.

                        $row.find('input[data-field="title"]').val(rowData.title);
                        $row.find('input[data-field="alt_text"]').val(rowData.alt_text);
                        $row.find('textarea[data-field="description"]').val(rowData.description);
                        $row.find('textarea[data-field="caption"]').val(rowData.caption);

                        $status.text('Done!').addClass('action-status-success').fadeIn();
                        $button.prop('disabled', false).text('Generate');
                        setTimeout(() => $status.fadeOut(), 2000);

                        // Instead of manually updating usage, fetch updated usage from the server.
                        fetchUsageStatus(3);
                    } else {
                        table.ajax.reload(null, false);
                    }
                } else if (!response.success && response.data && response.data.error) {
                    // Handle errors.
                    const error = response.data.error;
                    if (error.includes('Free trial limit reached')) {
                        window.showSubscriptionPrompt(
                            'Free Trial Limit Reached',
                            response.data.message || 'Upgrade your subscription to access unlimited features.',
                            response.data.ad_url || oneclick_images_bulk_vars.settings_url || 'https://oneclickcontent.com/image-detail-generator/'
                        );
                        oneclick_images_bulk_vars.trial_expired = true;
                        $('.generate-metadata').prop('disabled', true).attr('title', 'Trial expired');
                        $('#generate-all-metadata').prop('disabled', true);
                    } else if (error.includes('Usage limit reached')) {
                        const htmlContent = `
                            <p><strong>Error:</strong> ${$('<div/>').text(error).html()}</p>
                            <p>${$('<div/>').text(response.data.message || 'Purchase additional image credits to continue.').html()}</p>
                            <p><a href="${$('<div/>').text(response.data.ad_url || oneclick_images_bulk_vars.settings_url || 'https://oneclickcontent.com/image-detail-generator/').html()}" target="_blank">Purchase more credits</a> to continue generating metadata.</p>
                        `;
                        window.showLimitPrompt(htmlContent);
                        oneclick_images_bulk_vars.usage.remaining_count = 0;
                        $('.generate-metadata').prop('disabled', true).attr('title', 'Out of credits');
                        $('#generate-all-metadata').prop('disabled', true);
                    } else if (error.includes('Image validation failed')) {
                        window.showImageRejectionModal(error);
                    } else if (error.includes('license')) {
                        window.showSubscriptionPrompt(
                            'Invalid License',
                            error || 'Please enter a valid license key to continue.',
                            oneclick_images_bulk_vars.settings_url || 'https://oneclickcontent.com/image-detail-generator/'
                        );
                    } else {
                        window.showGeneralErrorModal(error || 'An unexpected error occurred.');
                    }
                    $button.prop('disabled', false).text('Generate');
                    $status.text('Error: ' + error).addClass('action-status-error').fadeIn();
                    setTimeout(() => $status.fadeOut(), 2000);
                } else {
                    // Handle unexpected response format.
                    $status.text('Error: Failed').addClass('action-status-error').fadeIn();
                    $button.prop('disabled', false).text('Generate');
                    setTimeout(() => $status.fadeOut(), 2000);
                    window.showGeneralErrorModal('Unexpected response format from server.');
                }
            },
            error: function (xhr) {
                $status.text('Error: ' + xhr.responseText).addClass('action-status-error').fadeIn();
                $button.prop('disabled', false).text('Generate');
                setTimeout(() => $status.fadeOut(), 2000);
                window.showGeneralErrorModal('An error occurred while processing the request: ' + xhr.responseText);
            },
        });
    });

    // =============================================================================
    // Field Saving and Focus Management
    // =============================================================================

    /**
     * Saves a field value to the server with debouncing to limit request frequency.
     *
     * @param {jQuery}   $input    The input or textarea element being edited.
     * @param {Function} callback  Optional callback to execute after saving.
     */
    const saveField = debounce(function ($input, callback) {
        const imageId = $input.data('image-id');
        const field = $input.data('field');
        const newValue = $input.val();
        const originalValue = $input.data('original-value') || '';
        const $status = $input.closest('.input-wrapper').find('.save-status');
        const $row = $input.closest('tr');
        const rowData = table.row($row).data();

        console.log(`saveField called for imageId: ${imageId}, field: ${field}`);
        console.log(`newValue: '${newValue}', originalValue: '${originalValue}'`);

        // Store active element info for focus restoration.
        window.activeElementInfo = {
            field: document.activeElement.getAttribute('data-field'),
            id: document.activeElement.getAttribute('data-image-id'),
            tag: document.activeElement.tagName.toLowerCase(),
            caret: document.activeElement.selectionStart || 0,
            rowIndex: $(document.activeElement).closest('tr').index(),
        };
        console.log('Active element info stored:', window.activeElementInfo);

        if (!rowData) {
            console.log('No rowData found, reloading table');
            $status.text('Error: Row data lost').addClass('save-status-error').fadeIn();
            table.ajax.reload(function () {
                $status.text('Table reloaded').addClass('save-status-saved').fadeIn();
                setTimeout(() => $status.fadeOut(), 2000);
                restoreFocus();
            }, false);
            if (callback) {
                callback();
            }
            return;
        }
        console.log('rowData exists:', rowData);

        // Check if the value changed (allowing empty values).
        if (newValue === originalValue) {
            console.log('No change detected, skipping save');
            $status.text('No changes').addClass('save-status-nochange').fadeIn();
            setTimeout(() => $status.fadeOut(), 2000);
            if (callback) {
                callback();
            }
            return;
        }
        console.log('Change detected, proceeding with save');

        $status.text(' Saving...').addClass('save-status-saving').css('display', 'inline-block');

        // Construct all fields object.
        const allFields = {};
        ['title', 'alt_text', 'description', 'caption'].forEach(function (f) {
            if (f === field) {
                allFields[f] = newValue;
            } else {
                const $fieldInput = $row.find(`[data-field="${f}"]`);
                allFields[f] = $fieldInput.length ? $fieldInput.val() : (rowData[f] || '');
            }
        });
        console.log('Fields to save:', allFields);

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
                updated_field: field,
            },
            success: function (response) {
                console.log('AJAX success response:', response);
                if (response.success && response.data) {
                    $status.text('Saved!').removeClass('save-status-saving').addClass('save-status-saved').fadeIn();
                    $input.data('original-value', newValue);
                    console.log(`Updated original-value to: '${newValue}'`);

                    rowData[field] = newValue;
                    console.log(`Updated rowData[${field}] to: '${newValue}'`);
                    table.row($row).data(rowData).invalidate();
                    console.log('DataTable invalidated with updated rowData');

                    // Update the input field with the new value.
                    $row.find(`[data-field="${field}"]`).val(newValue);
                    console.log(`Set input value to: '${newValue}'`);

                    setTimeout(() => $status.fadeOut(), 2000);
                    restoreFocus();
                    if (callback) {
                        callback();
                    }
                } else {
                    console.log('AJAX success but invalid response:', response);
                    $status.text('Error: ' + (response.data || 'Invalid response'))
                        .removeClass('save-status-saving').addClass('save-status-error').fadeIn();
                    setTimeout(() => $status.fadeOut(), 2000);
                    restoreFocus();
                    if (callback) {
                        callback();
                    }
                }
            },
            error: function (xhr) {
                console.log('AJAX error:', xhr.responseText);
                $status.text('Error: ' + xhr.responseText)
                    .removeClass('save-status-saving').addClass('save-status-error').fadeIn();
                setTimeout(() => $status.fadeOut(), 2000);
                restoreFocus();
                if (callback) {
                    callback();
                }
            },
        });
    }, 500);

    /**
     * Restores focus to the previously active element after table operations.
     */
    function restoreFocus() {
        if (!window.activeElementInfo) {
            return;
        }
        attemptFocusRestore();
        setTimeout(attemptFocusRestore, 10);
        setTimeout(attemptFocusRestore, 50);
        setTimeout(attemptFocusRestore, 100);
        setTimeout(attemptFocusRestore, 200);
    }

    /**
     * Attempts to restore focus and cursor position to the stored active element.
     */
    function attemptFocusRestore() {
        if (!window.activeElementInfo) {
            return;
        }
        const info = window.activeElementInfo;
        let selector = `${info.tag}[data-field="${info.field}"]`;
        if (info.id) {
            selector += `[data-image-id="${info.id}"]`;
        }

        const $elements = $(selector);
        let $element = $elements.length > 1 && info.rowIndex !== undefined ?
            $elements.filter(function () {
                return $(this).closest('tr').index() === info.rowIndex;
            }).first() :
            $elements.first();

        if ($element.length) {
            $element.focus();
            if (info.caret !== undefined && ($element[0].tagName === 'INPUT' || $element[0].tagName === 'TEXTAREA')) {
                try {
                    $element[0].setSelectionRange(info.caret, info.caret);
                } catch (e) {
                    // Ignore selection range errors.
                }
            }
        }
    }

    // =============================================================================
    // Usage Display
    // =============================================================================

    /**
     * Updates the UI to reflect current usage statistics for credits or trial limits.
     */
    function updateUsageDisplay() {
        if (!oneclick_images_bulk_vars.is_valid_license && !oneclick_images_bulk_vars.is_trial) {
            return;
        }

        const used = oneclick_images_bulk_vars.usage.used_count;
        const total = oneclick_images_bulk_vars.usage.total_allowed;
        const remaining = oneclick_images_bulk_vars.usage.remaining_count;
        const percentage = total > 0 ? (used / total) * 100 : 0;

        $('#usage_count').text(
            oneclick_images_bulk_vars.is_valid_license
                ? `Used ${used} of ${total} credits`
                : `Free Trial: Used ${used} of ${total} credits`
        );
        $('#usage_progress')
            .css('width', `${percentage}%`)
            .text(`${Math.round(percentage)}%`)
            .attr('aria-valuenow', used);

        if (oneclick_images_bulk_vars.is_valid_license && remaining <= 0) {
            $('.generate-metadata').prop('disabled', true).attr('title', 'Out of credits');
            $('#generate-all-metadata').prop('disabled', true);
        }
    }

    // =============================================================================
    // Initialization
    // =============================================================================

    if (oneclick_images_bulk_vars.is_valid_license || oneclick_images_bulk_vars.is_trial) {
        updateUsageDisplay();
    }
});