/**
 * Bulk metadata table management for OCCIDG.
 *
 * @package OneClickContent
 */

jQuery(document).ready(function($) {
    'use strict';

    if (!$.fn.DataTable) {
        return;
    }

    const table = $('#image-metadata-table').DataTable({
        serverSide: true,
        processing: true,
        searching: true,
        ordering: true,
        autoWidth: false,
        deferRender: true,
        ajax: {
            url: occidg_bulk_vars.ajax_url,
            type: 'POST',
            data: function(request) {
                request.action = 'occidg_get_image_metadata';
                request.nonce = occidg_bulk_vars.nonce;
            },
        },
        columns: [
            { data: 'thumbnail', orderable: false, searchable: false },
            {
                data: 'title',
                render: function(data, type, row) {
                    if ('display' !== type) {
                        return data;
                    }

                    return renderEditableInput('title', data, row.id);
                },
            },
            {
                data: 'alt_text',
                render: function(data, type, row) {
                    if ('display' !== type) {
                        return data;
                    }

                    return renderEditableInput('alt_text', data, row.id);
                },
            },
            {
                data: 'description',
                render: function(data, type, row) {
                    if ('display' !== type) {
                        return data;
                    }

                    return renderEditableTextarea('description', data, row.id);
                },
            },
            {
                data: 'caption',
                render: function(data, type, row) {
                    if ('display' !== type) {
                        return data;
                    }

                    return renderEditableTextarea('caption', data, row.id);
                },
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    if ('display' !== type) {
                        return '';
                    }

                    return `<div class="action-wrapper">
                        <button class="generate-metadata button" data-image-id="${parseInt(row.id, 10)}">Generate</button>
                        <span class="action-status"></span>
                    </div>`;
                },
            },
        ],
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
    });

    function renderEditableInput(field, value, imageId) {
        return `<div class="input-wrapper">
            <input type="text" value="${escapeHtml(value || '')}" data-field="${field}" data-image-id="${parseInt(imageId, 10)}" style="width: 100%;">
            <span class="save-status"></span>
        </div>`;
    }

    function renderEditableTextarea(field, value, imageId) {
        return `<div class="input-wrapper">
            <textarea data-field="${field}" data-image-id="${parseInt(imageId, 10)}" style="width: 100%; height: 60px;">${escapeHtml(value || '')}</textarea>
            <span class="save-status"></span>
        </div>`;
    }

    function escapeHtml(value) {
        return $('<div/>').text(value).html();
    }

    $('#image-metadata-table').on('focus', 'input, textarea', function() {
        $(this).data('original-value', $(this).val());
    });

    $('#image-metadata-table').on('blur', 'input, textarea', function() {
        const $input = $(this);

        if ($input.val() === $input.data('original-value')) {
            return;
        }

        saveRowField($input);
    });

    $('#image-metadata-table').on('click', '.generate-metadata', function() {
        const $button = $(this);
        const imageId = $button.data('image-id');
        const $status = $button.siblings('.action-status');
        const row = table.row($button.closest('tr'));

        $button.prop('disabled', true).text('Generating...');
        $status.text('');

        $.ajax({
            url: occidg_bulk_vars.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'occidg_generate_metadata',
                nonce: occidg_admin_vars.occidg_ajax_nonce,
                image_id: imageId,
            },
        }).done(function(response) {
            if (response.success && response.data && response.data.metadata) {
                const metadata = response.data.metadata.metadata ? response.data.metadata.metadata : response.data.metadata;
                const rowData = row.data();

                rowData.title = metadata.title || rowData.title;
                rowData.alt_text = metadata.alt_text || rowData.alt_text;
                rowData.description = metadata.description || rowData.description;
                rowData.caption = metadata.caption || rowData.caption;

                row.data(rowData).invalidate().draw(false);
                $status.text('Generated');
                return;
            }

            $status.text(response && response.data && response.data.error ? response.data.error : 'Error');
        }).fail(function() {
            $status.text('Error');
        }).always(function() {
            $button.prop('disabled', false).text('Generate');
        });
    });

    function saveRowField($input) {
        const imageId = $input.data('image-id');
        const field = $input.data('field');
        const $status = $input.siblings('.save-status');
        const row = table.row($input.closest('tr'));
        const rowData = row.data();

        rowData[field] = $input.val();
        $status.text('Saving...');

        $.ajax({
            url: occidg_bulk_vars.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'occidg_save_bulk_metadata',
                nonce: occidg_bulk_vars.nonce,
                image_id: imageId,
                title: rowData.title || '',
                alt_text: rowData.alt_text || '',
                description: rowData.description || '',
                caption: rowData.caption || '',
            },
        }).done(function(response) {
            if (response.success && response.data) {
                row.data(response.data).invalidate().draw(false);
                $status.text('Saved');
                return;
            }

            $status.text('Error');
        }).fail(function() {
            $status.text('Error');
        });
    }
});
