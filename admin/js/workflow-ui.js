/**
 * Progressive disclosure and table behavior for OCCIDG workflow screens.
 *
 * @package Occidg
 */
(function($) {
    'use strict';

    $(function() {
        setupWorkflowTables();
        setupBatchForm();
    });

    function setupWorkflowTables() {
        if (!$.fn.DataTable) {
            return;
        }

        $('.occidg-data-table').each(function() {
            const $table = $(this);

            if ($.fn.dataTable.isDataTable(this)) {
                return;
            }

            $table.DataTable({
                autoWidth: false,
                order: [],
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100],
                language: {
                    emptyTable: 'Nothing to show yet.',
                    search: 'Search this list:',
                },
            });
        });
    }

    function setupBatchForm() {
        const $form = $('.occidg-batch-form');
        if (!$form.length) {
            return;
        }

        const $limit = $form.find('[name="limit"]');
        const $customLimit = $form.find('[name="custom_limit"]');
        const $mode = $form.find('[name="mode"]');
        const $overwriteConfirmation = $form.find('.occ-idg-overwrite-confirm');
        const $submit = $form.find('[name="submit"]');

        function syncCustomLimit() {
            const isCustom = 'custom' === $limit.val();
            $customLimit.prop('hidden', !isCustom).prop('disabled', !isCustom);
        }

        function syncModeUi() {
            const mode = String($mode.val() || 'dry_run');
            const label = $submit.attr(`data-${mode.replace('_', '-')}-label`);

            $overwriteConfirmation.prop('hidden', 'overwrite' !== mode);
            if (label) {
                $submit.val(label);
            }
        }

        $limit.on('change', syncCustomLimit);
        $mode.on('change', syncModeUi);
        syncCustomLimit();
        syncModeUi();
    }
})(jQuery);
