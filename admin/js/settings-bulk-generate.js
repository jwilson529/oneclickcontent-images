/**
 * Background bulk generation JavaScript functionality for OCCIDG.
 *
 * @package One_Click_Images
 */
jQuery(document).ready(function($) {
    'use strict';

    const contexts = buildContexts();
    let currentJob = null;
    let pollTimer = null;
    let pendingContextKey = '';

    if (!Object.keys(contexts).length) {
        return;
    }

    syncBulkGenerateControls();
    restoreLatestJob();

    Object.keys(contexts).forEach(function(contextKey) {
        const context = contexts[contextKey];

        context.generateButton.on('click', function() {
            if (shouldBlockGeneration(context)) {
                return;
            }

            pendingContextKey = contextKey;
            openBulkGenerateModal();
        });

        context.pauseButton.on('click', function() {
            updateBackgroundJobState('pause', contextKey);
        });

        context.resumeButton.on('click', function() {
            updateBackgroundJobState('resume', contextKey);
        });

        context.cancelButton.on('click', function() {
            updateBackgroundJobState('cancel', contextKey);
        });

        context.retryButton.on('click', function() {
            updateBackgroundJobState('retry', contextKey);
        });
    });

    $('#confirm-bulk-generate').on('click', function() {
        if (!pendingContextKey || !contexts[pendingContextKey]) {
            closeBulkGenerateModal();
            return;
        }

        const contextKey = pendingContextKey;
        const context = contexts[contextKey];
        if (shouldBlockGeneration(context)) {
            closeBulkGenerateModal();
            return;
        }

        closeBulkGenerateModal();
        queueBackgroundJob(contextKey);
    });

    $('#cancel-bulk-generate').on('click', closeBulkGenerateModal);

    function buildContexts() {
        const availableContexts = {};

        if ($('#generate-all-metadata-settings').length) {
            availableContexts.settings = {
                key: 'settings',
                generateButton: $('#generate-all-metadata-settings'),
                header: $('.bulk-edit-header').first(),
                statusContainer: $('#bulk-generate-status-settings'),
                progressBar: $('#bulk-generate-progress-bar-settings'),
                message: $('#bulk-generate-message-settings'),
                summary: $('#bulk-generate-summary-settings'),
                failures: $('#bulk-generate-failures-settings'),
                pauseButton: $('#pause-bulk-generation-settings'),
                resumeButton: $('#resume-bulk-generation-settings'),
                cancelButton: $('#cancel-bulk-generation-settings'),
                retryButton: $('#retry-bulk-generation-settings'),
            };
        }

        if ($('#generate-all-metadata').length) {
            availableContexts.bulk = {
                key: 'bulk',
                generateButton: $('#generate-all-metadata'),
                header: $('.bulk-edit-header').first(),
                statusContainer: $('#bulk-generate-status'),
                progressBar: $('#bulk-generate-progress-bar'),
                message: $('#bulk-generate-message'),
                summary: $('#bulk-generate-summary'),
                failures: $('#bulk-generate-failures'),
                pauseButton: $('#pause-bulk-generation'),
                resumeButton: $('#resume-bulk-generation'),
                cancelButton: $('#cancel-bulk-generation'),
                retryButton: $('#retry-bulk-generation'),
            };
        }

        return availableContexts;
    }

    function openBulkGenerateModal() {
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
    }

    function closeBulkGenerateModal() {
        $('#bulk-generate-modal').hide();
        pendingContextKey = '';
    }

    function queueBackgroundJob(contextKey) {
        const context = contexts[contextKey];
        const queueingLabel = occidg_admin_vars.background_job_queueing_label || 'Queueing...';

        setCurrentJob(null);
        context.statusContainer.show();
        context.generateButton.prop('disabled', true).text(queueingLabel);
        context.progressBar.css('width', '0%');
        context.message.text(occidg_admin_vars.creating_background_job || 'Creating background job...');
        context.summary.empty();
        context.failures.empty();
        context.pauseButton.hide();
        context.resumeButton.hide();
        context.cancelButton.hide();
        context.retryButton.hide();

        $.ajax({
            url: occidg_admin_vars.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'occidg_create_background_job',
                nonce: occidg_admin_vars.occidg_ajax_nonce,
            },
        }).done(function(response) {
            if (response.success && response.data) {
                setCurrentJob(response.data);
                applyJobToContexts(response.data);
                startPolling();
                return;
            }

            context.message.text(getAjaxErrorMessage(response, occidg_admin_vars.background_job_create_error || 'Unable to create a background job right now.'));
            context.generateButton.text('Generate All Metadata');
            syncBulkGenerateControls();
        }).fail(function(xhr) {
            context.message.text(getAjaxErrorMessage(xhr, occidg_admin_vars.background_job_create_error || 'Unable to create a background job right now.'));
            context.generateButton.text('Generate All Metadata');
            syncBulkGenerateControls();
        });
    }

    function updateBackgroundJobState(actionName, contextKey) {
        const context = contexts[contextKey];

        if (!currentJob || !currentJob.id) {
            return;
        }

        context.message.text(getBusyMessageForAction(actionName));

        $.ajax({
            url: occidg_admin_vars.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: `occidg_${actionName}_background_job`,
                nonce: occidg_admin_vars.occidg_ajax_nonce,
                job_id: currentJob.id,
            },
        }).done(function(response) {
            if (response.success && response.data) {
                setCurrentJob(response.data);
                applyJobToContexts(response.data);
                if (isJobPollable(response.data)) {
                    startPolling();
                } else {
                    stopPolling();
                }
                return;
            }

            context.message.text(getAjaxErrorMessage(response, getActionErrorMessage(actionName)));
        }).fail(function(xhr) {
            context.message.text(getAjaxErrorMessage(xhr, getActionErrorMessage(actionName)));
        });
    }

    function restoreLatestJob() {
        $.ajax({
            url: occidg_admin_vars.ajax_url,
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'occidg_get_background_job_status',
                nonce: occidg_admin_vars.occidg_ajax_nonce,
            },
        }).done(function(response) {
            if (response.success && response.data) {
                setCurrentJob(response.data);
                applyJobToContexts(response.data);
                startPolling();
                return;
            }

            syncBulkGenerateControls();
        }).fail(function() {
            syncBulkGenerateControls();
        });
    }

    function startPolling() {
        stopPolling();

        if (!currentJob || !currentJob.id || !isJobPollable(currentJob)) {
            return;
        }

        pollTimer = window.setTimeout(function() {
            pollJobStatus(currentJob.id);
        }, 3000);
    }

    function stopPolling() {
        if (!pollTimer) {
            return;
        }

        window.clearTimeout(pollTimer);
        pollTimer = null;
    }

    function pollJobStatus(jobId) {
        if (!jobId) {
            return;
        }

        $.ajax({
            url: occidg_admin_vars.ajax_url,
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'occidg_get_background_job_status',
                nonce: occidg_admin_vars.occidg_ajax_nonce,
                job_id: jobId,
            },
        }).done(function(response) {
            if (response.success && response.data) {
                setCurrentJob(response.data);
                applyJobToContexts(response.data);

                if (isJobPollable(response.data)) {
                    startPolling();
                } else {
                    stopPolling();
                }

                return;
            }

            setJobMessageAcrossContexts(getAjaxErrorMessage(response, occidg_admin_vars.background_job_poll_error || 'Unable to refresh the background job status right now.'));
            startPolling();
        }).fail(function(xhr) {
            setJobMessageAcrossContexts(getAjaxErrorMessage(xhr, occidg_admin_vars.background_job_poll_error || 'Unable to refresh the background job status right now.'));
            startPolling();
        });
    }

    function applyJobToContexts(job) {
        Object.keys(contexts).forEach(function(contextKey) {
            renderJob(contexts[contextKey], job);
        });

        syncBulkGenerateControls();
    }

    function renderJob(context, job) {
        const percent = Math.max(0, Math.min(100, parseInt(job.percent_complete || 0, 10)));

        context.statusContainer.show();
        context.progressBar.css('width', `${percent}%`);
        context.message.text(getJobMessage(job));
        context.summary.html(buildSummaryMarkup(job));
        context.failures.html(buildFailuresMarkup(job));

        context.pauseButton.toggle(isPauseAvailable(job));
        context.resumeButton.toggle(isResumeAvailable(job));
        context.cancelButton.toggle(isCancelAvailable(job));
        context.retryButton.toggle(isRetryAvailable(job));
        context.generateButton.text('Generate All Metadata');
    }

    function buildSummaryMarkup(job) {
        const counts = formatTemplate(
            occidg_admin_vars.background_job_summary || '%1$d succeeded, %2$d failed, %3$d skipped.',
            [job.succeeded || 0, job.failed || 0, job.skipped || 0]
        );

        return `
            <div class="occidg-job-summary-grid">
                <div class="occidg-job-summary-card">
                    <span class="occidg-status-pill ${getStatusPillClass(job.status)}">${escapeHtml(job.status_label || job.status || '')}</span>
                    <strong>${escapeHtml(job.provider_label || '')}</strong>
                    <span>${escapeHtml(job.model || '')}</span>
                </div>
                <div class="occidg-job-summary-card">
                    <strong>${escapeHtml(counts)}</strong>
                    <span>${escapeHtml(job.label || '')}</span>
                </div>
            </div>
        `;
    }

    function buildFailuresMarkup(job) {
        const failures = Array.isArray(job.recent_failures) ? job.recent_failures : [];

        if (!failures.length) {
            if (parseInt(job.processed || 0, 10) < 1 && isJobPollable(job)) {
                return '';
            }

            return `
                <div class="status-item occidg-job-failure-card occidg-job-empty-state">
                    <p>${escapeHtml(occidg_admin_vars.background_job_no_failures || 'No recent failures recorded.')}</p>
                </div>
            `;
        }

        const items = failures.map(function(failure) {
            const imageId = parseInt(failure.image_id || 0, 10);
            const imageLabel = formatTemplate(
                occidg_admin_vars.background_job_image_label || 'Image %d',
                [imageId]
            );
            const imageUrl = `/wp-admin/post.php?post=${imageId}&action=edit`;

            return `
                <li class="occidg-job-failure-item">
                    <a href="${escapeHtml(imageUrl)}" class="occidg-job-failure-link" target="_blank" rel="noopener noreferrer">${escapeHtml(imageLabel)}</a>
                    <span>${escapeHtml(failure.message || '')}</span>
                </li>
            `;
        }).join('');

        return `
            <div class="status-item occidg-job-failure-card">
                <div class="status-item__header">
                    <span class="occidg-status-pill is-error">${escapeHtml(occidg_admin_vars.background_job_failures || 'Recent failures')}</span>
                </div>
                <ul class="occidg-job-failure-list">${items}</ul>
            </div>
        `;
    }

    function syncBulkGenerateControls() {
        const isBusy = hasBlockingJob();
        const message = isBusy
            ? (occidg_admin_vars.background_job_active || 'Background job active')
            : getGenerationGateMessage();
        const isReady = !isBusy && isGenerationReady();

        Object.keys(contexts).forEach(function(contextKey) {
            syncGenerateButton(contexts[contextKey].generateButton, isReady, message);
        });

        syncGenerateButton($('#confirm-bulk-generate'), isReady, message);
    }

    function syncGenerateButton($button, isReady, message) {
        if (!$button.length) {
            return;
        }

        if (isReady) {
            $button.prop('disabled', false).removeAttr('aria-disabled').removeAttr('title');
            return;
        }

        $button
            .prop('disabled', true)
            .attr('aria-disabled', 'true')
            .attr('title', message);
    }

    function shouldBlockGeneration(context) {
        if (hasBlockingJob()) {
            context.statusContainer.show();
            context.message.text(occidg_admin_vars.background_job_active || 'Background job active');
            return true;
        }

        if (!context.generateButton.is(':disabled') && isGenerationReady()) {
            return false;
        }

        const message = getGenerationGateMessage();
        const $modalContent = $('#bulk-generate-modal .modal-content');

        syncGenerateButton(context.generateButton, false, message);
        syncGenerationGateMessage(context.header, message, 'occidg-generation-gate-message');
        syncGenerationGateMessage($modalContent, message, 'occidg-generation-gate-message occidg-generation-gate-message-modal');

        return true;
    }

    function syncGenerationGateMessage($container, message, className) {
        if (!$container.length) {
            return;
        }

        let $message = $container.find('.occidg-generation-gate-message').first();

        if (!$message.length) {
            $message = $('<p />', { class: className });
            $container.append($message);
        }

        $message.text(message);
    }

    function setCurrentJob(job) {
        currentJob = job && job.id ? job : null;
    }

    function setJobMessageAcrossContexts(message) {
        Object.keys(contexts).forEach(function(contextKey) {
            contexts[contextKey].message.text(message);
        });
    }

    function isPauseAvailable(job) {
        return !!(job && job.can_pause);
    }

    function isResumeAvailable(job) {
        return !!(job && job.can_resume);
    }

    function isCancelAvailable(job) {
        return !!(job && job.can_cancel);
    }

    function isRetryAvailable(job) {
        return !!(job && job.can_retry);
    }

    function isJobPollable(job) {
        return !!(job && (job.status === 'queued' || job.status === 'running' || job.status === 'paused'));
    }

    function hasBlockingJob() {
        return !!(currentJob && (currentJob.status === 'queued' || currentJob.status === 'running' || currentJob.status === 'paused'));
    }

    function getJobMessage(job) {
        const progressMessage = formatTemplate(
            occidg_admin_vars.background_job_progress || '%1$s: %2$d of %3$d images processed.',
            [job.status_label || job.status || '', job.processed || 0, job.total || 0]
        );

        if ('completed' === job.status) {
            return occidg_admin_vars.background_job_complete || 'All metadata generation complete.';
        }

        if ('completed_with_errors' === job.status) {
            return occidg_admin_vars.background_job_complete_with_errors || 'Metadata generation finished with some errors.';
        }

        if ('cancelled' === job.status) {
            return occidg_admin_vars.background_job_cancelled || 'Metadata generation was cancelled.';
        }

        if ('paused' === job.status) {
            return occidg_admin_vars.background_job_paused || 'Metadata generation is paused.';
        }

        return progressMessage;
    }

    function getBusyMessageForAction(actionName) {
        if ('pause' === actionName) {
            return occidg_admin_vars.background_job_paused || 'Metadata generation is paused.';
        }

        if ('resume' === actionName) {
            return occidg_admin_vars.background_job_active || 'Background job active';
        }

        if ('retry' === actionName) {
            return occidg_admin_vars.background_job_retrying || 'Creating retry job...';
        }

        return occidg_admin_vars.background_job_cancelled || 'Metadata generation was cancelled.';
    }

    function getActionErrorMessage(actionName) {
        if ('pause' === actionName) {
            return occidg_admin_vars.background_job_pause_error || 'Unable to pause the background job right now.';
        }

        if ('resume' === actionName) {
            return occidg_admin_vars.background_job_resume_error || 'Unable to resume the background job right now.';
        }

        if ('retry' === actionName) {
            return occidg_admin_vars.background_job_retry_error || 'Unable to retry the background job right now.';
        }

        return occidg_admin_vars.background_job_cancel_error || 'Unable to cancel the background job right now.';
    }

    function getStatusPillClass(status) {
        if ('completed' === status) {
            return 'is-success';
        }

        if ('completed_with_errors' === status || 'failed' === status || 'cancelled' === status) {
            return 'is-error';
        }

        if ('paused' === status) {
            return 'is-paused';
        }

        return 'is-openai';
    }

    function isGenerationReady() {
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

    function getAjaxErrorMessage(response, fallbackMessage) {
        if (response && response.responseJSON && response.responseJSON.data) {
            return getAjaxErrorMessage(response.responseJSON, fallbackMessage);
        }

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

    function formatTemplate(template, values) {
        let output = template;

        values.forEach(function(value, index) {
            const placeholder = new RegExp(`%${index + 1}\\$[sd]`, 'g');
            output = output.replace(placeholder, value);
        });

        output = output.replace(/%d|%s/g, function() {
            return values.length ? values.shift() : '';
        });

        return output;
    }

    function escapeHtml(value) {
        return $('<div/>').text(value || '').html();
    }
});
