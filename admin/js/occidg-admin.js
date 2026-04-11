/**
 * Admin JavaScript functionality for OCCIDG.
 *
 * @package One_Click_Images
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        $('#close-first-time-modal').on('click', function() {
            $.post(occidg_admin_vars.ajax_url, {
                action: 'occidg_dismiss_first_time',
                dismiss_first_time_nonce: occidg_admin_vars.dismiss_first_time_nonce,
            }).always(function() {
                $('#occidg-first-time-modal').fadeOut();
            });
        });

        $(document).on('click', '.generate-metadata', function(e) {
            e.preventDefault();

            const button = $(this);
            const imageId = button.data('image-id');

            if (!imageId) {
                window.alert('Missing image ID.');
                return;
            }

            button.prop('disabled', true).text('Generating...');

            $.ajax({
                url: occidg_admin_vars.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'occidg_generate_metadata',
                    nonce: occidg_admin_vars.occidg_ajax_nonce,
                    image_id: imageId,
                },
            })
                .done(function(response) {
                    if (response.success && response.data && response.data.metadata) {
                        updateMetadataFields(response.data.metadata);
                        return;
                    }

                    const errorMessage = response && response.data && response.data.error ? response.data.error : 'Unable to generate metadata.';
                    window.alert(errorMessage);
                })
                .fail(function() {
                    window.alert('An error occurred while generating metadata.');
                })
                .always(function() {
                    button.prop('disabled', false).text('Generate Metadata');
                });
        });
    });

    function updateMetadataFields(metadata) {
        const generatedMetadata = metadata && metadata.metadata ? metadata.metadata : metadata || {};
        const selectedFields = {
            alt_text: true,
            title: true,
            caption: true,
            description: true,
        };

        const altInput = $('#attachment-details-two-column-alt-text, #attachment-details-alt-text, #attachment_alt');
        if (generatedMetadata.alt_text !== undefined && selectedFields.alt_text && altInput.length) {
            altInput.val(generatedMetadata.alt_text).trigger('change').trigger('input');
        }

        const titleInput = $('#attachment-details-two-column-title, #attachment-details-title');
        if (generatedMetadata.title !== undefined && selectedFields.title && titleInput.length) {
            titleInput.val(generatedMetadata.title).trigger('change').trigger('input');
        }

        const captionInput = $('#attachment-details-two-column-caption, #attachment-details-caption, #attachment_caption');
        if (generatedMetadata.caption !== undefined && selectedFields.caption && captionInput.length) {
            captionInput.val(generatedMetadata.caption).trigger('change').trigger('input');
        }

        const descriptionInput = $('#attachment-details-two-column-description, #attachment-details-description, #attachment_content');
        if (generatedMetadata.description !== undefined && selectedFields.description && descriptionInput.length) {
            descriptionInput.val(generatedMetadata.description).trigger('change').trigger('input');
        }

        if ($('body').hasClass('post-type-attachment') && $('body').hasClass('post-php')) {
            $('form#post').trigger('change');
        } else {
            $(document).trigger('attachmentUpdate');
        }
    }

    window.updateMetadataFields = updateMetadataFields;
})(jQuery);
