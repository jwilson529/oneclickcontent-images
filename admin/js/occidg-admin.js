/**
 * Admin JavaScript functionality for OCCIDG.
 *
 * @package One_Click_Images
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        setupSelectableCards();
        setupProviderVisibility();
        setupProviderSettings();

        $('#close-first-time-modal').on('click', function() {
            $.post(occidg_admin_vars.ajax_url, {
                action: 'occidg_dismiss_first_time',
                dismiss_first_time_nonce: occidg_admin_vars.dismiss_first_time_nonce,
            }).always(function() {
                $('#occidg-first-time-modal').fadeOut();
            });
        });

        $(document).on('click', '.generate-metadata', function(e) {
            const button = $(this);

            if (button.closest('#image-metadata-table').length) {
                return;
            }

            if (button.is(':disabled') || !isSelectedProviderReady()) {
                e.preventDefault();
                return;
            }

            e.preventDefault();
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

                    const errorMessage = getAjaxErrorMessage(response, 'Unable to generate metadata.');
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

    function setupSelectableCards() {
        syncSelectableCardState();

        $(document).on('change', '.occidg-choice-card input, .occidg-toggle-card input', function() {
            $(this)
                .closest('.occidg-choice-card, .occidg-toggle-card')
                .toggleClass('is-checked', $(this).is(':checked'));
        });
    }

    function syncSelectableCardState() {
        $('.occidg-choice-card, .occidg-toggle-card').each(function() {
            const isChecked = $(this).find('input[type="checkbox"]').is(':checked');
            $(this).toggleClass('is-checked', isChecked);
        });
    }

    function setupProviderVisibility() {
        const providerSelect = $('#occidg_provider');
        if (!providerSelect.length) {
            return;
        }

        const providerRows = {
            openai: ['#occidg_openai_api_key', '#occidg_openai_model'],
            gemini: ['#occidg_gemini_api_key', '#occidg_gemini_model'],
        };

        Object.entries(providerRows).forEach(function(entry) {
            const provider = entry[0];
            const selectors = entry[1];

            selectors.forEach(function(selector) {
                $(selector)
                    .closest('tr')
                    .addClass('occidg-provider-row')
                    .attr('data-occidg-provider', provider);
            });
        });

        function applyProviderState() {
            const activeProvider = 'gemini' === providerSelect.val() ? 'gemini' : 'openai';

            $('.occidg-provider-row').each(function() {
                const isActive = $(this).attr('data-occidg-provider') === activeProvider;
                $(this)
                    .toggleClass('is-active', isActive)
                    .toggleClass('is-inactive', !isActive);
            });

            updateGenerationGateState(activeProvider);
            updateSidebarProviderSummary(activeProvider);
        }

        providerSelect.on('change', applyProviderState);
        applyProviderState();
    }

    function setupProviderSettings() {
        $('.occidg-api-key-field').each(function() {
            $(this).data('saved-value', $(this).val());
        });

        $('.occidg-model-select').each(function() {
            $(this).data('saved-value', $(this).val());
        });

        hydrateSavedProviderModels();

        $(document).on('change', '.occidg-api-key-field', function() {
            syncProviderApiKey($(this));
        });

        $(document).on('change', '.occidg-model-select', function() {
            syncProviderModel($(this));
        });

        $('#occidg_settings_form').on('submit', function(event) {
            let shouldBlockSubmit = false;

            $('.occidg-api-key-field').each(function() {
                const $field = $(this);
                const provider = $field.data('provider');
                const currentValue = $.trim($field.val());
                const savedValue = $.trim($field.data('saved-value') || '');

                if ($field.is(':disabled') || currentValue !== savedValue) {
                    shouldBlockSubmit = true;

                    if (!$field.is(':disabled')) {
                        $field.trigger('change');
                    }

                    setProviderFieldStatus(
                        getProviderKeyStatus(provider),
                        'working',
                        occidg_admin_vars.wait_for_validation_message || 'Finish API key validation before saving settings.'
                    );
                } else {
                    $field.val(savedValue);
                }
            });

            if (shouldBlockSubmit) {
                event.preventDefault();
            }
        });
    }

    function hydrateSavedProviderModels() {
        $('.occidg-api-key-field').each(function() {
            const $field = $(this);
            const provider = $field.data('provider');
            const $modelSelect = getProviderModelSelect(provider);
            const savedValue = $.trim($field.data('saved-value') || '');
            const modelCount = parseInt($modelSelect.attr('data-model-count') || '0', 10);

            if (!savedValue || modelCount > 1) {
                return;
            }

            syncProviderApiKey($field, { force: true });
        });
    }

    function updateGenerationGateState(activeProvider) {
        if (!window.occidg_admin_vars) {
            return;
        }

        const providerLabel = getProviderLabel(activeProvider);
        const hasKey = providerHasKey(activeProvider);
        const message = hasKey ? '' : getMissingKeyMessage(providerLabel);

        occidg_admin_vars.provider = activeProvider;
        occidg_admin_vars.selected_provider_ready = hasKey;
        occidg_admin_vars.missing_key_message = message;

        syncGenerationButtonState($('#generate-all-metadata-settings'), hasKey, message);
        syncGenerationButtonState($('#confirm-bulk-generate'), hasKey, message);
        syncGenerationGateMessage($('.bulk-edit-header').first(), hasKey, message);
        syncGenerationGateMessage($('#bulk-generate-modal .modal-content').first(), hasKey, message, 'occidg-generation-gate-message occidg-generation-gate-message-modal');
    }

    function syncProviderApiKey($field, options) {
        const settings = options || {};
        const provider = $field.data('provider');
        const apiKey = $.trim($field.val());
        const savedValue = $.trim($field.data('saved-value') || '');
        const $keyStatus = getProviderKeyStatus(provider);
        const $modelStatus = getProviderModelStatus(provider);
        const $modelSelect = getProviderModelSelect(provider);
        const isClearing = '' === apiKey;

        if (!settings.force && apiKey === savedValue) {
            return;
        }

        $field.prop('disabled', true);
        $modelSelect.prop('disabled', true).attr('aria-disabled', 'true');

        setProviderFieldStatus(
            $keyStatus,
            'working',
            isClearing
                ? (occidg_admin_vars.clearing_key_message || 'Clearing saved API key...')
                : (occidg_admin_vars.checking_key_message || 'Checking API key...')
        );
        setProviderFieldStatus(
            $modelStatus,
            'working',
            isClearing
                ? (occidg_admin_vars.model_placeholder_message || 'Add and save a key to load available models.')
                : (occidg_admin_vars.loading_models_message || 'Loading available models...')
        );

        $.ajax({
            url: occidg_admin_vars.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'occidg_validate_provider_key',
                nonce: occidg_admin_vars.occidg_ajax_nonce,
                provider: provider,
                api_key: apiKey,
            },
        }).done(function(response) {
            if (!(response.success && response.data)) {
                handleProviderFieldError($field, $keyStatus, response);
                return;
            }

            $field.val(apiKey);
            $field.data('saved-value', apiKey);
            setProviderKeyAvailability(provider, response.data.has_key);
            syncSidebarProviderKeyData(provider, response.data.has_key);
            renderProviderModelOptions($modelSelect, response.data.models || [], response.data.selected_model || $modelSelect.val(), response.data.has_key);
            $modelSelect.data('saved-value', response.data.selected_model || $modelSelect.val());

            setProviderFieldStatus($keyStatus, 'success', response.data.message || '');
            setProviderFieldStatus(
                $modelStatus,
                response.data.has_key ? 'success' : 'idle',
                response.data.has_key
                    ? (occidg_admin_vars.models_updated_message || 'Available models updated.')
                    : (occidg_admin_vars.model_placeholder_message || 'Add and save a key to load available models.')
            );

            updateGenerationGateState(getCurrentProvider());
            updateSidebarProviderSummary(getCurrentProvider());
        }).fail(function() {
            handleProviderFieldError($field, $keyStatus);
        }).always(function() {
            $field.prop('disabled', false);
            $modelSelect.prop('disabled', !providerHasKey(provider)).attr('aria-disabled', providerHasKey(provider) ? null : 'true');
        });
    }

    function syncProviderModel($select) {
        const provider = $select.data('provider');
        const selectedModel = $select.val();
        const savedValue = $select.data('saved-value') || '';
        const $status = getProviderModelStatus(provider);

        if (!selectedModel || selectedModel === savedValue || $select.is(':disabled')) {
            return;
        }

        $select.prop('disabled', true).attr('aria-disabled', 'true');
        setProviderFieldStatus($status, 'working', occidg_admin_vars.saving_model_message || 'Saving model...');

        $.ajax({
            url: occidg_admin_vars.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'occidg_save_provider_model',
                nonce: occidg_admin_vars.occidg_ajax_nonce,
                provider: provider,
                model: selectedModel,
            },
        }).done(function(response) {
            if (!(response.success && response.data)) {
                $select.val(savedValue);
                setProviderFieldStatus($status, 'error', getAjaxErrorMessage(response, occidg_admin_vars.model_save_error || 'Unable to save the selected model right now.'));
                return;
            }

            $select.data('saved-value', response.data.selected_model || selectedModel);
            setProviderFieldStatus($status, 'success', response.data.message || '');
        }).fail(function() {
            $select.val(savedValue);
            setProviderFieldStatus($status, 'error', occidg_admin_vars.model_save_error || 'Unable to save the selected model right now.');
        }).always(function() {
            $select.prop('disabled', !providerHasKey(provider)).attr('aria-disabled', providerHasKey(provider) ? null : 'true');
        });
    }

    function updateSidebarProviderSummary(activeProvider) {
        const providerLabel = 'gemini' === activeProvider ? 'Gemini' : 'OpenAI';
        const providerPill = $('#occidg-sidebar-provider-pill');
        const keyStatus = $('#occidg-sidebar-key-status');

        if (providerPill.length) {
            providerPill
                .text(providerLabel)
                .removeClass('is-openai is-gemini')
                .addClass(`is-${activeProvider}`);
        }

        if (keyStatus.length) {
            const hasKey = 'gemini' === activeProvider
                ? '1' === keyStatus.attr('data-gemini-ready')
                : '1' === keyStatus.attr('data-openai-ready');
            const message = hasKey
                ? `${providerLabel} API key saved in WordPress.`
                : `${providerLabel} API key still needs to be added.`;

            keyStatus
                .text(message)
                .toggleClass('is-ready', hasKey)
                .toggleClass('is-missing', !hasKey);
        }
    }

    function renderProviderModelOptions($select, models, selectedModel, hasKey) {
        const normalizedModels = Array.isArray(models) ? models : [];

        $select.empty();

        if (!normalizedModels.length) {
            const fallbackValue = selectedModel || '';
            const fallbackLabel = fallbackValue || occidg_admin_vars.model_placeholder_message || 'Add and save a key to load available models.';

            $select.append(
                $('<option />', {
                    value: fallbackValue,
                    text: fallbackLabel,
                    selected: true,
                })
            );
            $select.attr('data-model-count', 0);
            $select.prop('disabled', !hasKey).attr('aria-disabled', hasKey ? null : 'true');
            return;
        }

        normalizedModels.forEach(function(model) {
            if (!model || !model.value) {
                return;
            }

            $select.append(
                $('<option />', {
                    value: model.value,
                    text: model.label || model.value,
                })
            );
        });

        $select.val(selectedModel || normalizedModels[0].value);
        $select.attr('data-model-count', normalizedModels.length);
        $select.prop('disabled', !hasKey).attr('aria-disabled', hasKey ? null : 'true');
    }

    function setProviderKeyAvailability(provider, hasKey) {
        if ('gemini' === provider) {
            occidg_admin_vars.has_gemini_key = hasKey;
            return;
        }

        occidg_admin_vars.has_openai_key = hasKey;
    }

    function syncSidebarProviderKeyData(provider, hasKey) {
        const $keyStatus = $('#occidg-sidebar-key-status');

        if (!$keyStatus.length) {
            return;
        }

        if ('gemini' === provider) {
            $keyStatus.attr('data-gemini-ready', hasKey ? '1' : '0');
            return;
        }

        $keyStatus.attr('data-openai-ready', hasKey ? '1' : '0');
    }

    function getProviderKeyStatus(provider) {
        return $(`#occidg_${provider}_api_key_status`);
    }

    function getProviderModelStatus(provider) {
        return $(`#occidg_${provider}_model_status`);
    }

    function getProviderModelSelect(provider) {
        return $(`#occidg_${provider}_model`);
    }

    function getCurrentProvider() {
        return 'gemini' === $('#occidg_provider').val() ? 'gemini' : 'openai';
    }

    function setProviderFieldStatus($status, state, message) {
        if (!$status.length) {
            return;
        }

        $status
            .removeClass('is-working is-success is-error is-idle')
            .toggleClass('is-working', 'working' === state)
            .toggleClass('is-success', 'success' === state)
            .toggleClass('is-error', 'error' === state)
            .toggleClass('is-idle', 'idle' === state)
            .text(message || '');
    }

    function handleProviderFieldError($field, $status, response) {
        const provider = $field.data('provider');

        setProviderFieldStatus(
            $status,
            'error',
            getAjaxErrorMessage(response, occidg_admin_vars.provider_request_error || 'Unable to validate the provider key right now.')
        );
        getProviderModelSelect(provider).val(getProviderModelSelect(provider).data('saved-value') || getProviderModelSelect(provider).val());
        setProviderFieldStatus(getProviderModelStatus(provider), 'idle', '');
    }

    function getAjaxErrorMessage(response, fallbackMessage) {
        if (response && response.data) {
            if (response.data.message || response.data.error) {
                const baseMessage = response.data.message || response.data.error;
                return response.data.details
                    ? `${baseMessage} ${response.data.details}`
                    : baseMessage;
            }

            if ('string' === typeof response.data) {
                return response.data;
            }
        }

        return fallbackMessage;
    }

    function syncGenerationButtonState($button, hasKey, message) {
        if (!$button.length) {
            return;
        }

        if (hasKey) {
            if ('true' === $button.attr('data-occidg-gated') || ($button.is(':disabled') && 'true' === $button.attr('aria-disabled'))) {
                $button
                    .prop('disabled', false)
                    .removeAttr('aria-disabled')
                    .removeAttr('title')
                    .attr('data-occidg-gated', 'false');
            }

            return;
        }

        $button
            .prop('disabled', true)
            .attr('aria-disabled', 'true')
            .attr('title', message)
            .attr('data-occidg-gated', 'true');
    }

    function syncGenerationGateMessage($container, hasKey, message, className) {
        if (!$container.length) {
            return;
        }

        let $message = $container.find('.occidg-generation-gate-message').first();

        if (hasKey) {
            $message.remove();
            return;
        }

        if (!$message.length) {
            $message = $('<p />', {
                class: className || 'occidg-generation-gate-message',
            });
            $container.append($message);
        }

        $message.text(message);
    }

    function getMissingKeyMessage(providerLabel) {
        const template = occidg_admin_vars.missing_key_message_template || 'Add and save a %s API key in Settings to enable metadata generation.';
        return template.replace('%s', providerLabel);
    }

    function getProviderLabel(provider) {
        return 'gemini' === provider
            ? (occidg_admin_vars.gemini_label || 'Gemini')
            : (occidg_admin_vars.openai_label || 'OpenAI');
    }

    function providerHasKey(provider) {
        return 'gemini' === provider
            ? isTruthy(occidg_admin_vars.has_gemini_key)
            : isTruthy(occidg_admin_vars.has_openai_key);
    }

    function isSelectedProviderReady() {
        return isTruthy(occidg_admin_vars.selected_provider_ready);
    }

    function isTruthy(value) {
        return true === value || 1 === value || '1' === value;
    }

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
