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

	const $libraryEditor = $('#occidg_bulk_edit');
	const $metadataTable = $('#image-metadata-table');
	if (!$metadataTable.length) {
		return;
	}

	let libraryFilters = {};
	try {
		libraryFilters = JSON.parse($libraryEditor.attr('data-library-filters') || '{}');
	} catch (error) {
		libraryFilters = {};
	}
	const filterActive = '1' === String($libraryEditor.attr('data-filter-active') || '0');
	const selectedImageIds = new Set();
	const $selectedCount = $('#occidg-selected-count');
	const $selectPage = $('#occidg-select-page');
	const $selectAllMatching = $('#occidg-select-all-matching');
	const $clearSelection = $('#occidg-clear-selection');
	const $queueSelected = $('.occidg-queue-selected');
	const $bulkSelectionStatus = $('#occidg-bulk-selection-status');
	const $bulkConfirmModal = $('#occidg-bulk-confirm-modal');
	const $bulkConfirmDialog = $bulkConfirmModal.find('.occidg-bulk-confirm-dialog');
	const $bulkConfirmTitle = $('#occidg-bulk-confirm-title');
	const $bulkConfirmDescription = $('#occidg-bulk-confirm-description');
	const $bulkConfirmSubmit = $('#occidg-bulk-confirm-submit');
	const $selectedBatchProgress = $('#occidg-selected-batch-progress');
	const selectedBatchStorageKey = 'occidgSelectedBatchJobId';
	let selectionBusy = false;
	let lastSearchValue = '';
	let pendingQueueMode = '';
	let lastModalTrigger = null;
	let selectedBatchPollTimer = null;
	let currentSelectedBatchJobId = '';
	let refreshedTerminalJobId = '';

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
				request.filter_active = filterActive ? '1' : '';
				request.library_filters = JSON.stringify(libraryFilters);
            },
	        },
	        columns: [
			{
				data: null,
				orderable: false,
				searchable: false,
				className: 'occidg-select-column',
				width: '44px',
				render: function(data, type, row) {
					if ('display' !== type) {
						return '';
					}

					const imageId = parseInt(row.id, 10);
					const unsupported = isGenerationUnsupported(row);
					const checked = selectedImageIds.has(imageId) ? ' checked' : '';
					const disabled = unsupported
						? ` disabled aria-disabled="true" title="${escapeAttribute(getUnsupportedSvgMessage(row))}"`
						: '';

					return `<input type="checkbox" class="occidg-row-select" value="${imageId}" aria-label="${escapeAttribute(getUiString('select_image_label', 'Select image').replace('%d', imageId))}"${checked}${disabled}>`;
				},
			},
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

                    const unsupportedMessage = isGenerationUnsupported(row)
                        ? `<span class="occidg-generation-unsupported">${escapeHtml(getUnsupportedSvgMessage(row))}</span>`
                        : '';
					const reviewButton = parseInt(row.pending_count || 0, 10) > 0
						? `<button type="button" class="review-pending-metadata button" data-image-id="${parseInt(row.id, 10)}">${escapeHtml(`${getUiString('review_label', 'Review')} (${parseInt(row.pending_count, 10)})`)}</button>`
						: '';
					const editLink = row.edit_url
						? `<a href="${escapeAttribute(row.edit_url)}">${escapeHtml(getUiString('edit_attachment_label', 'Edit attachment'))}</a>`
						: '';
					const historyLink = row.history_url
						? `<a href="${escapeAttribute(row.history_url)}">${escapeHtml(getUiString('history_label', 'History'))}</a>`
						: '';
					const utilityLinks = [editLink, historyLink].filter(Boolean).join('<span aria-hidden="true"> · </span>');

                    return `<div class="action-wrapper">
                        <div class="occidg-row-actions">
							<button type="button" class="preview-metadata button" data-image-id="${parseInt(row.id, 10)}"${getActionButtonAttributes(row, getUiString('preview_button_title', 'Compare AI suggestions before applying them.'))}>${escapeHtml(getUiString('preview_ai_label', 'Preview'))}</button>
							<button type="button" class="generate-metadata button button-primary" data-image-id="${parseInt(row.id, 10)}"${getActionButtonAttributes(row, getUiString('generate_button_title', 'Apply metadata automatically using your current settings.'))}>${escapeHtml(getUiString('generate_ai_label', 'Generate'))}</button>
							${reviewButton}
                        </div>
						<div class="occidg-row-utility-links">${utilityLinks}</div>
                        <span class="action-status" aria-live="polite"></span>
                        ${unsupportedMessage}
                    </div>`;
                },
            },
	        ],
		order: [[2, 'asc']],
	        pageLength: 10,
	        lengthMenu: [10, 25, 50, 100],
		drawCallback: function() {
			window.setTimeout(syncSelectionControls, 0);
		},
	    });

	function syncSelectionControls() {
		$metadataTable.find('tbody .occidg-row-select').each(function() {
			const imageId = parseInt($(this).val(), 10);
			$(this).prop('checked', selectedImageIds.has(imageId));
		});

		const $eligiblePageCheckboxes = $metadataTable.find('tbody .occidg-row-select:not(:disabled)');
		const selectedOnPage = $eligiblePageCheckboxes.filter(':checked').length;
		$selectPage
			.prop('checked', $eligiblePageCheckboxes.length > 0 && selectedOnPage === $eligiblePageCheckboxes.length)
			.prop('indeterminate', selectedOnPage > 0 && selectedOnPage < $eligiblePageCheckboxes.length)
			.prop('disabled', selectionBusy || 0 === $eligiblePageCheckboxes.length);

		updateSelectionButtons();
	}

	function updateSelectionButtons() {
		const count = selectedImageIds.size;
		const countTemplate = 1 === count
			? getUiString('selected_count_singular', '%d selected')
			: getUiString('selected_count_plural', '%d selected');
		$selectedCount.text(countTemplate.replace('%d', count));
		$clearSelection.prop('disabled', selectionBusy || 0 === count);
		$selectAllMatching.prop('disabled', selectionBusy);
		$queueSelected
			.prop('disabled', selectionBusy || 0 === count || !isGenerationAllowed())
			.attr('title', !isGenerationAllowed() ? getGenerationGateMessage() : null);
	}

	function clearSelectedImages(clearStatus) {
		selectedImageIds.clear();
		$metadataTable.find('tbody .occidg-row-select').prop('checked', false);
		if (clearStatus) {
			setBulkSelectionStatus('', '');
		}
		syncSelectionControls();
	}

	function setSelectionBusy(isBusy) {
		selectionBusy = !!isBusy;
		syncSelectionControls();
	}

	function setBulkSelectionStatus(state, message, batchesUrl) {
		$bulkSelectionStatus
			.removeClass('is-working is-success is-error')
			.toggleClass(`is-${state}`, !!state)
			.empty();

		if (!message) {
			return;
		}

		$bulkSelectionStatus.append($('<span />', { text: message }));
		if (batchesUrl) {
			$bulkSelectionStatus
				.append(document.createTextNode(' '))
				.append($('<a />', {
					href: batchesUrl,
					text: getUiString('view_batches_label', 'View batches'),
				}));
		}
	}

	$metadataTable.on('change', '.occidg-row-select', function() {
		const imageId = parseInt($(this).val(), 10);
		if ($(this).is(':checked')) {
			selectedImageIds.add(imageId);
		} else {
			selectedImageIds.delete(imageId);
		}
		syncSelectionControls();
	});

	$selectPage.on('change', function() {
		const shouldSelect = $(this).is(':checked');
		$metadataTable.find('tbody .occidg-row-select:not(:disabled)').each(function() {
			const imageId = parseInt($(this).val(), 10);
			if (shouldSelect) {
				selectedImageIds.add(imageId);
			} else {
				selectedImageIds.delete(imageId);
			}
			$(this).prop('checked', shouldSelect);
		});
		syncSelectionControls();
	});

	$clearSelection.on('click', function() {
		clearSelectedImages(true);
	});

	$metadataTable.on('search.dt', function() {
		const currentSearchValue = table.search();
		if (currentSearchValue === lastSearchValue) {
			return;
		}

		lastSearchValue = currentSearchValue;
		clearSelectedImages(true);
	});

	$selectAllMatching.on('click', function() {
		setSelectionBusy(true);
		setBulkSelectionStatus('working', getUiString('selecting_matching_message', 'Selecting matching images...'));

		$.ajax({
			url: occidg_bulk_vars.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'occidg_get_bulk_selection_ids',
				nonce: occidg_bulk_vars.nonce,
				search_value: table.search(),
				filter_active: filterActive ? '1' : '',
				library_filters: JSON.stringify(libraryFilters),
			},
		}).done(function(response) {
			if (!(response.success && response.data && Array.isArray(response.data.image_ids))) {
				setBulkSelectionStatus('error', getAjaxErrorMessage(response, getUiString('select_matching_error', 'Unable to select the matching images.')));
				return;
			}

			response.data.image_ids.forEach(function(imageId) {
				selectedImageIds.add(parseInt(imageId, 10));
			});
			const count = parseInt(response.data.count || 0, 10);
			const message = 0 === count
				? getUiString('no_matching_images_message', 'No eligible images match this view.')
				: getUiString('selected_matching_message', 'Selected %d matching images.').replace('%d', count);
			setBulkSelectionStatus(0 === count ? '' : 'success', message);
		}).fail(function() {
			setBulkSelectionStatus('error', getUiString('select_matching_error', 'Unable to select the matching images.'));
		}).always(function() {
			setSelectionBusy(false);
		});
	});

	$queueSelected.on('click', function() {
		if (0 === selectedImageIds.size) {
			return;
		}

		openBulkConfirm(String($(this).data('mode') || ''), this);
	});

	$('#occidg-bulk-confirm-cancel, #occidg-bulk-confirm-close').on('click', closeBulkConfirm);

	$bulkConfirmModal.on('click', function(event) {
		if (event.target === this) {
			closeBulkConfirm();
		}
	});

	$bulkConfirmSubmit.on('click', function() {
		const mode = pendingQueueMode;
		if (!mode || 0 === selectedImageIds.size) {
			closeBulkConfirm();
			return;
		}

		closeBulkConfirm();
		queueSelectedImages(mode);
	});

	$('#occidg-dismiss-selected-batch').on('click', dismissSelectedBatchProgress);

	function openBulkConfirm(mode, trigger) {
		const count = selectedImageIds.size;
		const isSuggestion = 'suggestion' === mode;
		const confirmationTemplate = isSuggestion
			? (1 === count
				? getUiString('queue_suggestions_confirmation_one', 'Queue review suggestions for 1 selected image? Nothing changes until you approve it.')
				: getUiString('queue_suggestions_confirmation', 'Queue review suggestions for %d selected images? Nothing changes until you approve them.'))
			: (1 === count
				? getUiString('queue_fill_missing_confirmation_one', 'Fill missing metadata for 1 selected image? Existing values stay unchanged.')
				: getUiString('queue_fill_missing_confirmation', 'Fill missing metadata for %d selected images? Existing values stay unchanged.'));

		pendingQueueMode = mode;
		lastModalTrigger = trigger || null;
		$bulkConfirmTitle.text(isSuggestion
			? getUiString('queue_suggestions_title', 'Create review suggestions?')
			: getUiString('queue_fill_missing_title', 'Fill missing metadata?'));
		$bulkConfirmDescription.text(confirmationTemplate.replace('%d', count));
		$bulkConfirmSubmit.text(isSuggestion
			? getUiString('queue_suggestions_submit', 'Start review batch')
			: getUiString('queue_fill_missing_submit', 'Start fill missing batch'));
		$bulkConfirmModal.prop('hidden', false);
		$('body').addClass('occidg-modal-open');
		$(document).on('keydown.occidgBulkConfirm', handleBulkConfirmKeydown);
		window.setTimeout(function() {
			$bulkConfirmSubmit.trigger('focus');
		}, 0);
	}

	function closeBulkConfirm() {
		if ($bulkConfirmModal.prop('hidden')) {
			return;
		}

		$bulkConfirmModal.prop('hidden', true);
		$('body').removeClass('occidg-modal-open');
		$(document).off('keydown.occidgBulkConfirm');
		pendingQueueMode = '';
		if (lastModalTrigger) {
			$(lastModalTrigger).trigger('focus');
		}
		lastModalTrigger = null;
	}

	function handleBulkConfirmKeydown(event) {
		if ('Escape' === event.key) {
			event.preventDefault();
			closeBulkConfirm();
			return;
		}

		if ('Tab' !== event.key) {
			return;
		}

		const $focusable = $bulkConfirmDialog.find('button:not(:disabled), a[href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled)').filter(':visible');
		if (!$focusable.length) {
			event.preventDefault();
			$bulkConfirmDialog.trigger('focus');
			return;
		}

		const first = $focusable.get(0);
		const last = $focusable.get($focusable.length - 1);
		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			$(last).trigger('focus');
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			$(first).trigger('focus');
		}
	}

	function queueSelectedImages(mode) {
		const queuedImageIds = Array.from(selectedImageIds);
		setSelectionBusy(true);
		setBulkSelectionStatus('working', getUiString('queueing_selected_message', 'Queueing the selected images...'));

		$.ajax({
			url: occidg_bulk_vars.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'occidg_create_selected_batch',
				nonce: occidg_bulk_vars.nonce,
				mode: mode,
				image_ids: queuedImageIds,
			},
		}).done(function(response) {
			if (!(response.success && response.data)) {
				setBulkSelectionStatus('error', getAjaxErrorMessage(response, getUiString('queue_selected_error', 'Unable to queue the selected images.')));
				return;
			}

			clearSelectedImages(false);
			setBulkSelectionStatus('success', response.data.message || getUiString('queue_selected_success', 'The selected images were queued.'));
			startSelectedBatchProgress(response.data);
		}).fail(function() {
			setBulkSelectionStatus('error', getUiString('queue_selected_error', 'Unable to queue the selected images.'));
		}).always(function() {
			setSelectionBusy(false);
		});
	}

	function startSelectedBatchProgress(batchData) {
		const jobId = String(batchData.job_id || '');
		if (!jobId) {
			return;
		}

		currentSelectedBatchJobId = jobId;
		refreshedTerminalJobId = '';
		persistSelectedBatchJobId(jobId);
		if (batchData.batches_url) {
			$('#occidg-selected-batch-details').attr('href', batchData.batches_url);
		}
		renderSelectedBatchJob({
			id: jobId,
			batch_id: parseInt(batchData.batch_id || 0, 10),
			status: 'queued',
			status_label: getUiString('background_job_queued_label', 'Queued'),
			total: parseInt(batchData.total || 0, 10),
			processed: 0,
			succeeded: 0,
			failed: 0,
			skipped: 0,
			percent_complete: 0,
		});
		$selectedBatchProgress.trigger('focus');
		if ($selectedBatchProgress.get(0) && 'function' === typeof $selectedBatchProgress.get(0).scrollIntoView) {
			$selectedBatchProgress.get(0).scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		}
		scheduleSelectedBatchPoll(jobId, 500);
	}

	function restoreSelectedBatchProgress() {
		let jobId = '';
		try {
			jobId = String(window.sessionStorage.getItem(selectedBatchStorageKey) || '');
		} catch (error) {
			jobId = '';
		}

		if (!jobId) {
			return;
		}

		currentSelectedBatchJobId = jobId;
		pollSelectedBatch(jobId);
	}

	function persistSelectedBatchJobId(jobId) {
		try {
			window.sessionStorage.setItem(selectedBatchStorageKey, jobId);
		} catch (error) {
			// Progress still works for this page view when storage is unavailable.
		}
	}

	function dismissSelectedBatchProgress() {
		if (selectedBatchPollTimer) {
			window.clearTimeout(selectedBatchPollTimer);
			selectedBatchPollTimer = null;
		}
		currentSelectedBatchJobId = '';
		$selectedBatchProgress.prop('hidden', true);
		try {
			window.sessionStorage.removeItem(selectedBatchStorageKey);
		} catch (error) {
			// There is nothing else to clean up when storage is unavailable.
		}
	}

	function scheduleSelectedBatchPoll(jobId, delay) {
		if (selectedBatchPollTimer) {
			window.clearTimeout(selectedBatchPollTimer);
		}
		selectedBatchPollTimer = window.setTimeout(function() {
			pollSelectedBatch(jobId);
		}, delay);
	}

	function pollSelectedBatch(jobId) {
		if (!jobId || jobId !== currentSelectedBatchJobId) {
			return;
		}

		$.ajax({
			url: occidg_bulk_vars.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'occidg_get_background_job_status',
				nonce: window.occidg_admin_vars ? occidg_admin_vars.occidg_ajax_nonce : '',
				job_id: jobId,
			},
		}).done(function(response) {
			if (jobId !== currentSelectedBatchJobId) {
				return;
			}
			if (!(response.success && response.data)) {
				showSelectedBatchRefreshError(getAjaxErrorMessage(response, getUiString('background_job_poll_error', 'Unable to refresh the background job status right now.')));
				scheduleSelectedBatchPoll(jobId, 5000);
				return;
			}

			$('#occidg-selected-batch-refresh-status').prop('hidden', true).text('');
			renderSelectedBatchJob(response.data);
			if (isSelectedBatchPollable(response.data)) {
				scheduleSelectedBatchPoll(jobId, document.hidden ? 5000 : 2500);
			}
		}).fail(function() {
			if (jobId !== currentSelectedBatchJobId) {
				return;
			}
			showSelectedBatchRefreshError(getUiString('background_job_poll_error', 'Unable to refresh the background job status right now.'));
			scheduleSelectedBatchPoll(jobId, 5000);
		});
	}

	function renderSelectedBatchJob(job) {
		const status = String(job.status || 'queued');
		const total = Math.max(0, parseInt(job.total || 0, 10));
		const processed = Math.max(0, parseInt(job.processed || 0, 10));
		const succeeded = Math.max(0, parseInt(job.succeeded || 0, 10));
		const failed = Math.max(0, parseInt(job.failed || 0, 10));
		const skipped = Math.max(0, parseInt(job.skipped || 0, 10));
		const percent = Math.min(100, Math.max(0, parseInt(job.percent_complete || 0, 10)));
		const statusLabel = String(job.status_label || status);
		const message = getSelectedBatchMessage(job, statusLabel, processed, total);
		const terminal = !isSelectedBatchPollable(job);

		$selectedBatchProgress
			.prop('hidden', false)
			.toggleClass('is-active', 'queued' === status || 'running' === status);
		$('#occidg-selected-batch-state')
			.removeClass('is-queued is-running is-paused is-completed is-completed_with_errors is-cancelled is-failed')
			.addClass(`is-${status}`)
			.text(statusLabel);
		$('#occidg-selected-batch-message').text(message);
		$('#occidg-selected-batch-progress-track')
			.attr('aria-valuenow', percent)
			.attr('aria-valuetext', message);
		$('#occidg-selected-batch-progress-bar').css('width', `${percent}%`);
		$('#occidg-selected-batch-processed').text(`${processed} / ${total}`);
		$('#occidg-selected-batch-succeeded').text(succeeded);
		$('#occidg-selected-batch-failed').text(failed);
		$('#occidg-selected-batch-skipped').text(skipped);
		$('#occidg-dismiss-selected-batch').prop('hidden', !terminal);

		if (terminal && job.id && refreshedTerminalJobId !== String(job.id)) {
			refreshedTerminalJobId = String(job.id);
			table.ajax.reload(null, false);
		}
	}

	function getSelectedBatchMessage(job, statusLabel, processed, total) {
		const status = String(job.status || 'queued');
		if ('completed' === status) {
			return `${getUiString('background_job_complete', 'All metadata generation complete.')} ${formatJobSummary(job)}`;
		}
		if ('completed_with_errors' === status) {
			return `${getUiString('background_job_complete_with_errors', 'Metadata generation finished with some errors.')} ${formatJobSummary(job)}`;
		}
		if ('paused' === status) {
			return getUiString('background_job_paused', 'Metadata generation is paused.');
		}
		if ('cancelled' === status) {
			return getUiString('background_job_cancelled', 'Metadata generation was cancelled.');
		}
		if ('failed' === status) {
			return String(job.last_error || getUiString('background_job_complete_with_errors', 'Metadata generation finished with some errors.'));
		}

		return getUiString('background_job_progress', '%1$s: %2$d of %3$d images processed.')
			.replace('%1$s', statusLabel)
			.replace('%2$d', processed)
			.replace('%3$d', total);
	}

	function formatJobSummary(job) {
		return getUiString('background_job_summary', '%1$d succeeded, %2$d failed, %3$d skipped.')
			.replace('%1$d', Math.max(0, parseInt(job.succeeded || 0, 10)))
			.replace('%2$d', Math.max(0, parseInt(job.failed || 0, 10)))
			.replace('%3$d', Math.max(0, parseInt(job.skipped || 0, 10)));
	}

	function isSelectedBatchPollable(job) {
		return ['queued', 'running', 'paused'].includes(String(job.status || ''));
	}

	function showSelectedBatchRefreshError(message) {
		$selectedBatchProgress.prop('hidden', false);
		$('#occidg-selected-batch-refresh-status').prop('hidden', false).text(message);
	}

	restoreSelectedBatchProgress();

    function renderEditableInput(field, value, imageId) {
        return `<div class="input-wrapper">
            <input type="text" value="${escapeHtml(value || '')}" data-field="${field}" data-image-id="${parseInt(imageId, 10)}" style="width: 100%;">
            <span class="save-status" aria-live="polite"></span>
        </div>`;
    }

    function renderEditableTextarea(field, value, imageId) {
        return `<div class="input-wrapper">
            <textarea data-field="${field}" data-image-id="${parseInt(imageId, 10)}" style="width: 100%; height: 60px;">${escapeHtml(value || '')}</textarea>
            <span class="save-status" aria-live="polite"></span>
        </div>`;
    }

    function escapeHtml(value) {
        const entities = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        };

        return String(value).replace(/[&<>"']/g, function(character) {
            return entities[character];
        });
    }

    function escapeAttribute(value) {
        return escapeHtml(value);
    }

    function isGenerationAllowed() {
        return !!(window.occidg_admin_vars && (
            true === occidg_admin_vars.selected_provider_ready ||
            1 === occidg_admin_vars.selected_provider_ready ||
            '1' === occidg_admin_vars.selected_provider_ready
        ));
    }

    function getGenerationGateMessage() {
        return window.occidg_admin_vars && occidg_admin_vars.missing_key_message
            ? occidg_admin_vars.missing_key_message
            : 'Add and save an API key in Settings to enable metadata generation.';
    }

    function getUnsupportedSvgMessage(rowData) {
        if (rowData && rowData.generation_message) {
            return rowData.generation_message;
        }

        return window.occidg_admin_vars && occidg_admin_vars.unsupported_svg_message
            ? occidg_admin_vars.unsupported_svg_message
            : 'AI metadata generation is not available for SVG files. You can edit this file\'s metadata manually.';
    }

    function getUiString(key, fallback) {
        return window.occidg_admin_vars && occidg_admin_vars[key]
            ? occidg_admin_vars[key]
            : fallback;
    }

    function isGenerationUnsupported(rowData) {
        return !!rowData && (
            false === rowData.generation_supported ||
            '0' === String(rowData.generation_supported) ||
            'image/svg+xml' === String(rowData.mime_type || '').toLowerCase()
        );
    }

    function getActionButtonAttributes(rowData, enabledTitle) {
        if (isGenerationUnsupported(rowData)) {
            return ` disabled="disabled" aria-disabled="true" title="${escapeAttribute(getUnsupportedSvgMessage(rowData))}"`;
        }

        if (isGenerationAllowed()) {
            return ` title="${escapeAttribute(enabledTitle)}"`;
        }

        return ` disabled="disabled" aria-disabled="true" title="${escapeAttribute(getGenerationGateMessage())}"`;
    }

	    $('#image-metadata-table').on('focus', '.input-wrapper input, .input-wrapper textarea', function() {
	        $(this).data('original-value', $(this).val());
	    });

	    $('#image-metadata-table').on('blur', '.input-wrapper input, .input-wrapper textarea', function() {
        const $input = $(this);

        if ($input.val() === $input.data('original-value')) {
            return;
        }

        saveRowField($input);
    });

    $('#image-metadata-table').on('click', '.preview-metadata', function() {
        const $button = $(this);
        const imageId = $button.data('image-id');
        const $wrapper = $button.closest('.action-wrapper');
        const $status = $wrapper.find('.action-status');
        const row = table.row($button.closest('tr'));
        const rowData = row.data();

		if (row.child.isShown() && 'preview' === rowData.child_view) {
			row.child.hide();
			delete rowData.child_view;
			resetPreviewButton($button, rowData, false);
			return;
        }

        if (isGenerationUnsupported(rowData)) {
            setInlineStatus($status, 'action', 'error', getUnsupportedSvgMessage(rowData), true);
            return;
        }

        if ($button.is(':disabled') || !isGenerationAllowed()) {
            setInlineStatus($status, 'action', 'error', getGenerationGateMessage(), true);
            return;
        }

        if (rowData.ai_preview) {
            showMetadataPreview(row, rowData);
            resetPreviewButton($button, rowData, true);
            return;
        }

		$wrapper.find('.preview-metadata, .generate-metadata').prop('disabled', true);
		$button.text(getUiString('previewing_ai_label', 'Creating preview...'));
		setInlineStatus($status, 'action', 'working', getUiString('previewing_ai_label', 'Creating preview...'), true);

        $.ajax({
            url: occidg_bulk_vars.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
				action: 'occidg_preview_metadata',
                nonce: occidg_admin_vars.occidg_ajax_nonce,
                image_id: imageId,
            },
        }).done(function(response) {
			if (response.success && response.data && response.data.fields) {
				rowData.ai_preview = response.data;
				showMetadataPreview(row, rowData);
				setInlineStatus($status, 'action', 'success', getUiString('preview_ready_message', 'Preview ready'));
				return;
			}

			setInlineStatus($status, 'action', 'error', getAjaxErrorMessage(response, getUiString('preview_error_message', 'Unable to create a metadata preview.')), true);
		}).fail(function() {
			setInlineStatus($status, 'action', 'error', getUiString('preview_error_message', 'Unable to create a metadata preview.'), true);
		}).always(function() {
			resetRowActionButtons($wrapper, rowData, row.child.isShown());
		});
    });

	$('#image-metadata-table').on('click', '.generate-metadata', function() {
		const $button = $(this);
		const imageId = $button.data('image-id');
		const $wrapper = $button.closest('.action-wrapper');
		const $status = $wrapper.find('.action-status');
		const $tableRow = $button.closest('tr');
		const row = table.row($tableRow);
		const rowData = row.data();

		if (isGenerationUnsupported(rowData)) {
			setInlineStatus($status, 'action', 'error', getUnsupportedSvgMessage(rowData), true);
			return;
		}

		if ($button.is(':disabled') || !isGenerationAllowed()) {
			setInlineStatus($status, 'action', 'error', getGenerationGateMessage(), true);
			return;
		}

			$wrapper.find('.preview-metadata, .generate-metadata').prop('disabled', true);
		$button.text(getUiString('generating_ai_label', 'Generating...'));
		setInlineStatus($status, 'action', 'working', getUiString('generating_ai_label', 'Generating...'), true);

		$.ajax({
			url: occidg_bulk_vars.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'occidg_generate_metadata',
				nonce: occidg_admin_vars.occidg_ajax_nonce,
				image_id: imageId,
				apply_configured_rules: '1',
			},
		}).done(function(response) {
			if (response.success && response.data) {
				if (response.data.current_metadata) {
					updateRowFromCurrentMetadata(row, $tableRow, rowData, response.data.current_metadata);
				}
					if (row.child.isShown()) {
						row.child.hide();
					}
					delete rowData.child_view;
				delete rowData.ai_preview;
				setInlineStatus($status, 'action', 'success', response.data.message || getUiString('generation_ready_message', 'Metadata generated using current rules.'));
				return;
			}

			setInlineStatus($status, 'action', 'error', getAjaxErrorMessage(response, getUiString('generation_error_message', 'Unable to generate metadata.')), true);
		}).fail(function() {
			setInlineStatus($status, 'action', 'error', getUiString('generation_error_message', 'Unable to generate metadata.'), true);
		}).always(function() {
			resetRowActionButtons($wrapper, rowData, false);
		});
	});

	function resetPreviewButton($button, rowData, previewIsOpen) {
		const svgUnsupported = isGenerationUnsupported(rowData);
		const disabled = svgUnsupported || !isGenerationAllowed();
		const title = svgUnsupported ? getUnsupportedSvgMessage(rowData) : getGenerationGateMessage();

        $button
			.prop('disabled', disabled)
			.attr('aria-disabled', disabled ? 'true' : null)
			.attr('title', disabled ? title : getUiString('preview_button_title', 'Compare AI suggestions before applying them.'))
			.text(previewIsOpen
				? getUiString('hide_preview_label', 'Hide preview')
				: getUiString('preview_ai_label', 'Preview'));
    }

	function resetRowActionButtons($wrapper, rowData, previewIsOpen) {
		const disabled = isGenerationUnsupported(rowData) || !isGenerationAllowed();
		const disabledTitle = isGenerationUnsupported(rowData) ? getUnsupportedSvgMessage(rowData) : getGenerationGateMessage();
		const $previewButton = $wrapper.find('.preview-metadata');
		const $generateButton = $wrapper.find('.generate-metadata');

		resetPreviewButton($previewButton, rowData, previewIsOpen);
		$generateButton
			.prop('disabled', disabled)
			.attr('aria-disabled', disabled ? 'true' : null)
			.attr('title', disabled ? disabledTitle : getUiString('generate_button_title', 'Apply metadata automatically using your current settings.'))
			.text(getUiString('generate_ai_label', 'Generate'));
	}

	function updateRowFromCurrentMetadata(row, $tableRow, rowData, currentMetadata) {
		['title', 'alt_text', 'description', 'caption'].forEach(function(field) {
			if (!Object.prototype.hasOwnProperty.call(currentMetadata, field)) {
				return;
			}

			rowData[field] = currentMetadata[field];
			rowData.empty_fields = rowData.empty_fields || {};
			rowData.empty_fields[field] = isEffectivelyEmpty(currentMetadata[field]);
			$tableRow.find(`[data-field="${field}"]`).val(currentMetadata[field]);
		});

		row.invalidate('data');
	}

	function showMetadataPreview(row, rowData) {
		row.child(renderMetadataPreview(rowData), 'occidg-ai-preview-row').show();
		rowData.child_view = 'preview';
	}

	$('#image-metadata-table').on('click', '.review-pending-metadata', function() {
		const $button = $(this);
		const row = table.row($button.closest('tr'));
		const rowData = row.data();
		const $previewButton = $button.closest('.action-wrapper').find('.preview-metadata');

		if (row.child.isShown() && 'review' === rowData.child_view) {
			row.child.hide();
			delete rowData.child_view;
			$button.text(`${getUiString('review_label', 'Review')} (${parseInt(rowData.pending_count || 0, 10)})`);
			return;
		}

		row.child(renderPendingReview(rowData), 'occidg-ai-preview-row').show();
		rowData.child_view = 'review';
		resetPreviewButton($previewButton, rowData, false);
		$button.text(getUiString('hide_review_label', 'Hide review'));
	});

	function renderPendingReview(rowData) {
		const suggestions = Array.isArray(rowData.pending_suggestions) ? rowData.pending_suggestions : [];
		const cards = suggestions.map(renderPendingSuggestionCard).join('');

		return `<div class="occidg-ai-preview occidg-pending-review" data-image-id="${parseInt(rowData.id, 10)}">
			<div class="occidg-ai-preview__header">
				<div>
					<p class="occidg-ai-preview__eyebrow">${escapeHtml(getUiString('saved_suggestions_heading', 'Saved suggestions'))}</p>
					<h3>${escapeHtml(getUiString('saved_suggestions_help', 'Approve, edit, reject, or decide later without leaving the Image Library.'))}</h3>
				</div>
				<span class="occidg-status-pill">${escapeHtml(getUiString('awaiting_review_count', '%d awaiting review').replace('%d', parseInt(rowData.pending_count || 0, 10)))}</span>
			</div>
			<div class="occidg-ai-preview__grid">${cards}</div>
		</div>`;
	}

	function renderPendingSuggestionCard(suggestion) {
		const field = String(suggestion.field_name || '');
		const currentValue = String(suggestion.current_value || '');
		const confidence = String(suggestion.confidence || 'medium');
		const currentMarkup = isEffectivelyEmpty(currentValue)
			? `<span class="occidg-empty-value">${escapeHtml(getUiString('empty_value_label', 'Empty'))}</span>`
			: `<p>${escapeHtml(currentValue)}</p>`;

		return `<form class="occidg-suggestion-card occidg-pending-suggestion" method="post" action="${escapeAttribute(occidg_bulk_vars.admin_post_url)}">
			<input type="hidden" name="action" value="occ_idg_review_suggestion">
			<input type="hidden" name="suggestion_id" value="${parseInt(suggestion.id, 10)}">
			<input type="hidden" name="_wpnonce" value="${escapeAttribute(suggestion.nonce || '')}">
			<div class="occidg-pending-suggestion__heading"><h4>${escapeHtml(getFieldLabel(field))}</h4><span class="occidg-confidence is-${escapeAttribute(confidence)}">${escapeHtml(getUiString('confidence_label_template', '%s confidence').replace('%s', confidence))}</span></div>
			<div class="occidg-suggestion-current"><span>${escapeHtml(getUiString('current_value_label', 'Current value'))}</span><div class="occidg-suggestion-current__value">${currentMarkup}</div></div>
			<label class="occidg-suggestion-proposed"><span>${escapeHtml(getUiString('suggestion_editable_label', 'Suggested value — you can edit this'))}</span><textarea rows="4" name="approved_value">${escapeHtml(suggestion.suggested_value || '')}</textarea></label>
			${suggestion.confidence_reason ? `<p class="occidg-review-reason">${escapeHtml(suggestion.confidence_reason)}</p>` : ''}
			<div class="occidg-review-actions">
				<button class="button button-primary" name="review_action" value="approve">${escapeHtml(getUiString('approve_label', 'Approve'))}</button>
				<button class="button" name="review_action" value="reject">${escapeHtml(getUiString('reject_label', 'Reject'))}</button>
				<button class="button" name="review_action" value="defer">${escapeHtml(getUiString('decide_later_label', 'Decide later'))}</button>
				<button class="button button-link" name="review_action" value="manual">${escapeHtml(getUiString('flag_manual_review_label', 'Flag for manual review'))}</button>
			</div>
			<p class="occidg-suggestion-source">${escapeHtml(`${suggestion.provider || ''} / ${suggestion.model || ''} · ${suggestion.generated_at || ''}`)}</p>
		</form>`;
	}

	function renderMetadataPreview(rowData) {
		const preview = rowData.ai_preview || {};
		const fields = preview.fields || {};
		const cards = ['title', 'alt_text', 'description', 'caption']
			.filter(function(field) {
				return Object.prototype.hasOwnProperty.call(fields, field);
			})
			.map(function(field) {
				return renderSuggestionCard(field, fields[field]);
			})
			.join('');

		return `<div class="occidg-ai-preview" data-image-id="${parseInt(rowData.id, 10)}">
			<div class="occidg-ai-preview__header">
				<div>
					<p class="occidg-ai-preview__eyebrow">${escapeHtml(getUiString('preview_heading', 'Review AI suggestions'))}</p>
					<h3>${escapeHtml(getUiString('preview_safety_message', 'Nothing changes until you approve a suggestion below.'))}</h3>
				</div>
				<span class="occidg-status-pill is-success">#${parseInt(rowData.id, 10)}</span>
			</div>
			<div class="occidg-ai-preview__grid">${cards}</div>
		</div>`;
	}

	function renderSuggestionCard(field, fieldData) {
		const currentValue = fieldData && null != fieldData.current ? String(fieldData.current) : '';
		const suggestedValue = fieldData && null != fieldData.suggested ? String(fieldData.suggested) : '';
		const currentIsEmpty = !fieldData || true === fieldData.current_empty;
		const suggestionIsEmpty = isEffectivelyEmpty(suggestedValue);
		const textareaRows = 'description' === field || 'caption' === field ? 4 : 2;
		const currentMarkup = currentIsEmpty
			? `<span class="occidg-empty-value">${escapeHtml(getUiString('empty_value_label', 'Empty'))}</span>`
			: `<p>${escapeHtml(currentValue)}</p>`;
		const suggestionMarkup = suggestionIsEmpty
			? `<p class="occidg-empty-suggestion">${escapeHtml(getUiString('empty_suggestion_message', 'The provider did not return a value for this field.'))}</p>`
			: `<textarea rows="${textareaRows}" data-suggestion-field="${field}">${escapeHtml(suggestedValue)}</textarea>`;
		const buttonMarkup = suggestionIsEmpty
			? ''
			: `<button type="button" class="button button-primary occidg-use-suggestion" data-field="${field}">${escapeHtml(getUiString('use_suggestion_label', 'Use this suggestion'))}</button>`;

		return `<section class="occidg-suggestion-card" data-field-card="${field}">
			<h4>${escapeHtml(getFieldLabel(field))}</h4>
			<div class="occidg-suggestion-current">
				<span>${escapeHtml(getUiString('current_value_label', 'Current value'))}</span>
				<div class="occidg-suggestion-current__value">${currentMarkup}</div>
			</div>
			<label class="occidg-suggestion-proposed">
				<span>${escapeHtml(getUiString('suggested_value_label', 'Suggested value'))}</span>
				${suggestionMarkup}
			</label>
			<div class="occidg-suggestion-actions">
				${buttonMarkup}
				<span class="occidg-suggestion-status" aria-live="polite"></span>
			</div>
		</section>`;
	}

	function getFieldLabel(field) {
		const labels = {
			title: getUiString('field_title_label', 'Title'),
			alt_text: getUiString('field_alt_text_label', 'Alt Text'),
			description: getUiString('field_description_label', 'Description'),
			caption: getUiString('field_caption_label', 'Caption'),
		};

		return labels[field] || field;
	}

	function isEffectivelyEmpty(value) {
		return '' === String(value || '').replace(/[\s\u00a0\u200b\ufeff]+/g, '');
	}

	$('#image-metadata-table').on('click', '.occidg-use-suggestion', function() {
		const $button = $(this);
		const field = String($button.data('field') || '');
		const $card = $button.closest('.occidg-suggestion-card');
		const $childRow = $button.closest('tr');
		const row = table.row($childRow.prev('tr'));
		const rowData = row.data();
		const $textarea = $card.find(`[data-suggestion-field="${field}"]`);
		const value = $textarea.val();
		const originalSuggestion = rowData.ai_preview.fields[field].suggested || '';
		const $status = $card.find('.occidg-suggestion-status');

		$button.prop('disabled', true).text(getUiString('applying_suggestion_label', 'Applying...'));
		$status.removeClass('is-success is-error').text('');

		$.ajax({
			url: occidg_bulk_vars.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'occidg_apply_bulk_suggestion',
				nonce: occidg_bulk_vars.nonce,
				image_id: rowData.id,
				field: field,
				value: value,
				suggested_value: originalSuggestion,
			},
		}).done(function(response) {
			if (!(response.success && response.data)) {
				$button.prop('disabled', false).text(getUiString('use_suggestion_label', 'Use this suggestion'));
				$status.addClass('is-error').text(getAjaxErrorMessage(response, getUiString('apply_suggestion_error', 'Unable to apply this suggestion.')));
				return;
			}

			rowData[field] = response.data.value;
			rowData.empty_fields = rowData.empty_fields || {};
			rowData.empty_fields[field] = response.data.is_empty;
			rowData.ai_preview.fields[field].current = response.data.value;
			rowData.ai_preview.fields[field].current_empty = response.data.is_empty;
			$childRow.prev('tr').find(`[data-field="${field}"]`).val(response.data.value);
			$card.addClass('is-applied');
			$card.find('.occidg-suggestion-current__value')
				.empty()
				.append(response.data.is_empty
					? $('<span />', { class: 'occidg-empty-value', text: getUiString('empty_value_label', 'Empty') })
					: $('<p />', { text: response.data.value }));
			$button.text(getUiString('applied_suggestion_label', 'Applied'));
			$status.addClass('is-success').text(response.data.message || getUiString('applied_suggestion_label', 'Applied'));
		}).fail(function() {
			$button.prop('disabled', false).text(getUiString('use_suggestion_label', 'Use this suggestion'));
			$status.addClass('is-error').text(getUiString('apply_suggestion_error', 'Unable to apply this suggestion.'));
		});
	});

    function saveRowField($input) {
        const imageId = $input.data('image-id');
        const field = $input.data('field');
        const $status = $input.siblings('.save-status');
        const row = table.row($input.closest('tr'));
        const rowData = row.data();

        rowData[field] = $input.val();
        setInlineStatus($status, 'save', 'saving', 'Saving...', true);

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
				response.data.pending_suggestions = rowData.pending_suggestions || [];
				response.data.pending_count = rowData.pending_count || 0;
				response.data.edit_url = rowData.edit_url || '';
				response.data.history_url = rowData.history_url || '';
                row.data(response.data).invalidate().draw(false);
                setInlineStatus($status, 'save', 'saved', 'Saved');
                return;
            }

            setInlineStatus($status, 'save', 'error', 'Error', true);
        }).fail(function() {
            setInlineStatus($status, 'save', 'error', 'Error', true);
        });
    }

    function setInlineStatus($status, type, state, message, persist) {
        const classes = 'save-status-saving save-status-saved save-status-error save-status-nochange action-status-working action-status-success action-status-error';
        const stateClass = `${type}-status-${state}`;
        const timeoutId = $status.data('occidgTimeoutId');

        if (timeoutId) {
            window.clearTimeout(timeoutId);
        }

        $status
            .stop(true, true)
            .removeClass(classes)
            .addClass(stateClass)
            .text(message)
            .css('display', 'inline-flex');

        if (persist) {
            return;
        }

        const newTimeoutId = window.setTimeout(function() {
            $status.fadeOut(200, function() {
                $status.removeClass(classes).text('');
            });
        }, 'error' === state ? 4500 : 1800);

        $status.data('occidgTimeoutId', newTimeoutId);
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
});
