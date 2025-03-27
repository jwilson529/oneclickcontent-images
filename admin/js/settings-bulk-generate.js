/**
 * Bulk generation JavaScript functionality for OneClickContent Image Details plugin.
 *
 * @package    One_Click_Images
 * @subpackage One_Click_Images/admin/js
 * @author     OneClickContent <support@oneclickcontent.com>
 * @since      1.0.0
 * @copyright  2025 OneClickContent
 * @license    GPL-2.0+
 * @link       https://oneclickcontent.com
 */
jQuery( document ).ready( function( $ ) {
    'use strict';

    let stopBulkGeneration = false;

    /**
     * Handle click events for "Generate All Metadata" buttons.
     */
    $( '#generate-all-metadata-settings, #generate-all-metadata' ).on( 'click', function() {
        const $button        = $( this );
        const isSettingsTab  = 'generate-all-metadata-settings' === $button.attr( 'id' );
        const statusContainer = isSettingsTab ? '#bulk-generate-status-settings' : '#bulk-generate-status';
        const stopButton      = isSettingsTab ? '#stop-bulk-generation-settings' : '#stop-bulk-generation';
        const progressBar     = isSettingsTab ? '#bulk-generate-progress-bar-settings' : '#bulk-generate-progress-bar';
        const messageContainer = isSettingsTab ? '#bulk-generate-message-settings' : '#bulk-generate-message';

        $( '#bulk-generate-modal' ).show();

        // Check override_metadata option.
        $.ajax( {
            url: occidg_admin_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'occidg_check_override_metadata',
                nonce: occidg_admin_vars.occidg_ajax_nonce,
            },
            success: function( response ) {
                if ( response.success && ( true === response.data.override || '1' === response.data.override ) ) {
                    $( '#bulk-generate-warning' ).show();
                } else {
                    $( '#bulk-generate-warning' ).hide();
                }
            },
            error: function() {
                $( '#bulk-generate-warning' ).hide();
            },
        } );

        // Handle modal buttons.
        $( '#confirm-bulk-generate' ).off( 'click' ).on( 'click', function() {
            $( '#bulk-generate-modal' ).hide();
            startBulkGeneration( $button, statusContainer, stopButton, progressBar, messageContainer );
        } );

        $( '#cancel-bulk-generate' ).off( 'click' ).on( 'click', function() {
            $( '#bulk-generate-modal' ).hide();
        } );
    } );

    /**
     * Handle click events for "Stop" buttons.
     */
    $( '#stop-bulk-generation-settings, #stop-bulk-generation' ).on( 'click', function() {
        stopBulkGeneration = true;
        const isSettingsTab   = 'stop-bulk-generation-settings' === $( this ).attr( 'id' );
        const messageContainer = isSettingsTab ? '#bulk-generate-message-settings' : '#bulk-generate-message';
        const generateButton  = isSettingsTab ? '#generate-all-metadata-settings' : '#generate-all-metadata';

        $( messageContainer ).text( 'Generation stopped.' );
        $( this ).hide();
        $( generateButton ).prop( 'disabled', false ).text( 'Generate All Metadata' );
    } );

    /**
     * Start the bulk metadata generation process.
     *
     * @param {jQuery} $button         The button element.
     * @param {string} statusContainer The status container selector.
     * @param {string} stopButton      The stop button selector.
     * @param {string} progressBar     The progress bar selector.
     * @param {string} messageContainer The message container selector.
     */
    function startBulkGeneration( $button, statusContainer, stopButton, progressBar, messageContainer ) {
        stopBulkGeneration = false;
        $( stopButton ).show();
        $( $button ).prop( 'disabled', true ).html( '<span class="generate-spinner"></span> Generating...' );
        $( statusContainer ).show();
        $( progressBar ).css( 'width', '0%' );
        $( messageContainer ).text( '' );

        $.ajax( {
            url: occidg_admin_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'occidg_get_all_media_ids',
                nonce: occidg_admin_vars.occidg_ajax_nonce,
            },
            success: function( response ) {
                if ( response.success && response.data.ids.length > 0 ) {
                    processBulkGeneration( response.data.ids, 0, $button, response.data.ids.length, statusContainer, stopButton, progressBar, messageContainer );
                } else {
                    $( messageContainer ).text( 'No media items found.' );
                    $( $button ).prop( 'disabled', false ).text( 'Generate All Metadata' );
                    $( stopButton ).hide();
                }
            },
            error: function( xhr ) {
                $( messageContainer ).text( 'Error fetching media IDs: ' + xhr.responseText );
                $( $button ).prop( 'disabled', false ).text( 'Generate All Metadata' );
                $( stopButton ).hide();
            },
        } );
    }

    /**
     * Process bulk metadata generation recursively.
     *
     * @param {Array}  ids             Array of media IDs.
     * @param {number} index           Current index in the array.
     * @param {jQuery} $button         The button element.
     * @param {number} total           Total number of items.
     * @param {string} statusContainer The status container selector.
     * @param {string} stopButton      The stop button selector.
     * @param {string} progressBar     The progress bar selector.
     * @param {string} messageContainer The message container selector.
     */
    function processBulkGeneration(ids, index, $button, total, statusContainer, stopButton, progressBar, messageContainer) {
        if (stopBulkGeneration) {
            $(messageContainer).text('Generation stopped.');
            $(stopButton).hide();
            $( $button ).prop( 'disabled', false ).text( 'Generate All Metadata' );
            return;
        }

        if (index >= ids.length) {
            $(messageContainer).text('All metadata generation complete!');
            $(progressBar).css('width', '100%');
            $( $button ).prop( 'disabled', false ).text( 'Generate All Metadata' );
            $(stopButton).hide();
            fetchUsageStatus();
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
            success: function(response) {
                // Log the raw response to debug its structure
                console.log('Raw response:', response);

                // Handle potential nested response (e.g., WP REST API)
                const data = response.data && typeof response.data === 'object' ? response.data : response;

                if (data.success === true && data.data && data.data.metadata) {
                    // Success case: metadata generated
                    renderMetadataUI(imageId, data.data.metadata, statusContainer);
                    fetchUsageStatus();
                    processBulkGeneration(ids, index + 1, $button, total, statusContainer, stopButton, progressBar, messageContainer);
                } else if (data.success === false && data.error) {
                    if (data.error.includes('Free trial limit reached')) {
                        // Free trial limit reached
                        $(messageContainer).text(data.error);
                        $(stopButton).hide();
                        $( $button ).prop( 'disabled', false ).text( 'Generate All Metadata' );
                        showSubscriptionPrompt(
                            data.error,
                            data.message || 'Upgrade your subscription to access unlimited features.',
                            data.ad_url || 'https://oneclickcontent.com/image-detail-generator/'
                        );
                        // Explicitly stop recursion
                        return;
                    } else if (data.error.includes('Usage limit reached')) {
                        // Subscription usage limit reached
                        $(messageContainer).text(data.error);
                        $(stopButton).hide();
                        $( $button ).prop( 'disabled', false ).text( 'Generate All Metadata' );
                        const htmlContent = `
                            <p><strong>Error:</strong> ${$( '<div/>' ).text(data.error).html()}</p>
                            <p>${$( '<div/>' ).text(data.message).html()}</p>
                            <p><a href="${$( '<div/>' ).text(data.ad_url).html()}" target="_blank">Purchase more credits</a> to continue generating metadata.</p>
                        `;
                        showLimitPrompt(htmlContent);
                        // Explicitly stop recursion
                        return;
                    } else {
                        // Other error case
                        $(messageContainer).text(`Image ${imageId} - Error: ${data.error || 'Unknown error'}`);
                        fetchUsageStatus();
                        processBulkGeneration(ids, index + 1, $button, total, statusContainer, stopButton, progressBar, messageContainer);
                    }
                } else {
                    // Unexpected response format
                    $(messageContainer).text(`Image ${imageId} - Unexpected response format`);
                    fetchUsageStatus();
                    processBulkGeneration(ids, index + 1, $button, total, statusContainer, stopButton, progressBar, messageContainer);
                }
            },
            error: function(xhr) {
                $(messageContainer).text(`Image ${imageId} - AJAX error: ${xhr.responseText}`);
                fetchUsageStatus();
                processBulkGeneration(ids, index + 1, $button, total, statusContainer, stopButton, progressBar, messageContainer);
            },
        });
    }

    /**
     * Render metadata UI for a processed image.
     *
     * @param {number} imageId        The image ID.
     * @param {Object} metadata       The metadata object.
     * @param {string} statusContainer The status container selector.
     */
    function renderMetadataUI( imageId, metadata, statusContainer ) {
        const mediaLibraryUrl = `/wp-admin/post.php?post=${imageId}&action=edit`;

        $.ajax( {
            url: occidg_admin_vars.ajax_url,
            type: 'GET',
            data: {
                action: 'get_thumbnail',
                image_id: imageId,
                occidg_ajax_nonce: occidg_admin_vars.occidg_ajax_nonce,
            },
            success: function( thumbnailResponse ) {
                const thumbnailUrl = thumbnailResponse.success && thumbnailResponse.data?.thumbnail ?
                    thumbnailResponse.data.thumbnail :
                    occidg_admin_vars.fallback_image_url;
                buildMetadataDisplay( mediaLibraryUrl, thumbnailUrl, metadata, imageId, statusContainer );
            },
            error: function() {
                const thumbnailUrl = occidg_admin_vars.fallback_image_url;
                buildMetadataDisplay( mediaLibraryUrl, thumbnailUrl, metadata, imageId, statusContainer );
            },
        } );
    }

    /**
     * Build and append metadata display HTML.
     *
     * @param {string} mediaLibraryUrl The media library URL.
     * @param {string} thumbnailUrl   The thumbnail URL.
     * @param {Object} metadata       The metadata object.
     * @param {number} imageId        The image ID.
     * @param {string} statusContainer The status container selector.
     */
    function buildMetadataDisplay( mediaLibraryUrl, thumbnailUrl, metadata, imageId, statusContainer ) {
        const safeThumbnailUrl    = $( '<div/>' ).text( thumbnailUrl ).html();
        const safeMediaLibraryUrl = $( '<div/>' ).text( mediaLibraryUrl ).html();
        const safeImageId         = parseInt( imageId, 10 );

        let displayMetadata = metadata;
        if ( metadata && typeof metadata === 'object' && metadata.metadata && typeof metadata.metadata === 'string' ) {
            try {
                displayMetadata = JSON.parse( metadata.metadata );
            } catch ( e ) {
                displayMetadata = {};
            }
        } else if ( metadata && typeof metadata === 'object' && metadata.metadata && typeof metadata.metadata === 'object' ) {
            displayMetadata = metadata.metadata;
        }

        let metadataRows = '<tr><td colspan="2">No metadata available</td></tr>';
        if ( displayMetadata && typeof displayMetadata === 'object' && ! Array.isArray( displayMetadata ) && Object.keys( displayMetadata ).length > 0 ) {
            metadataRows = Object.entries( displayMetadata )
                .map( ( [ key, value ] ) => {
                    let displayKey   = key;
                    let displayValue = value;

                    if ( 'alt_text' === key ) {
                        displayKey = 'Alt Tag';
                    } else {
                        displayKey = key.charAt( 0 ).toUpperCase() + key.slice( 1 );
                    }

                    if ( null === value || undefined === value ) {
                        displayValue = '';
                    } else if ( typeof value === 'object' ) {
                        displayValue = JSON.stringify( value );
                    }

                    if ( 'title' === key ) {
                        displayValue = `<a href="${safeMediaLibraryUrl}" target="_blank">${$( '<div/>' ).text( displayValue ).html()} <span class="dashicons dashicons-external"></span></a>`;
                    } else {
                        displayValue = $( '<div/>' ).text( displayValue ).html();
                    }

                    return `<tr><td>${displayKey}</td><td>${displayValue}</td></tr>`;
                } )
                .join( '' );
        }

        const content = `
            <div class="status-item">
                <div class="thumbnail-container">
                    <img
                        src="${safeThumbnailUrl}"
                        alt="Thumbnail for ${safeImageId}"
                        class="thumbnail-preview attachment-thumbnail size-thumbnail"
                        onerror="this.src='${occidg_admin_vars.fallback_image_url}';"
                    />
                </div>
                <div class="metadata-container">
                    <table id="image-metadata-table" class="metadata-table">
                        ${metadataRows}
                    </table>
                </div>
            </div>
        `;

        $( statusContainer ).append( content );
    }

    /**
     * Fetch usage status from the server with retry logic.
     *
     * @param {number} retryCount Number of retries remaining.
     */
    function fetchUsageStatus( retryCount = 3 ) {
        $.ajax( {
            url: occidg_admin_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'occidg_check_usage',
                nonce: occidg_admin_vars.occidg_ajax_nonce,
            },
            success: function( response ) {
                if ( response.success ) {
                    const { used_count, usage_limit, addon_count, remaining_count } = response.data;
                    const totalAllowed = parseInt( usage_limit, 10 ) + parseInt( addon_count || 0, 10 );
                    updateUsageStatusUI( used_count, totalAllowed, remaining_count );
                } else if ( retryCount > 0 ) {
                    setTimeout( () => fetchUsageStatus( retryCount - 1 ), 1000 );
                } else {
                    $( '#usage_count' ).html( 'Error: Unable to fetch usage information.' );
                    $( '#usage_progress' ).css( 'width', '0%' ).text( '0%' );
                }
            },
            error: function() {
                if ( retryCount > 0 ) {
                    setTimeout( () => fetchUsageStatus( retryCount - 1 ), 1000 );
                } else {
                    $( '#usage_count' ).html( 'Error: An error occurred.' );
                    $( '#usage_progress' ).css( 'width', '0%' ).text( '0%' );
                }
            },
        } );
    }

    window.fetchUsageStatus = fetchUsageStatus;

    /**
     * Update the usage status UI.
     *
     * @param {number} usedCount     Number of used items.
     * @param {number} totalAllowed  Total allowed items.
     * @param {number} remainingCount Number of remaining items.
     */
    function updateUsageStatusUI( usedCount, totalAllowed, remainingCount ) {
        const percentageUsed = totalAllowed > 0 ? ( usedCount / totalAllowed ) * 100 : 0;
        $( '#usage_count' ).html(
            `Used: ${usedCount} of ${totalAllowed} images (${remainingCount} remaining)`
        );
        $( '#usage_progress' )
            .css( 'width', `${Math.min( percentageUsed, 100 )}%` )
            .attr( 'aria-valuenow', Math.min( percentageUsed, 100 ) )
            .text( `${Math.round( percentageUsed )}% Used` );

        if ( percentageUsed >= 90 ) {
            $( '#usage_progress' ).removeClass( 'bg-success bg-warning' ).addClass( 'bg-danger' );
        } else if ( percentageUsed >= 70 ) {
            $( '#usage_progress' ).removeClass( 'bg-success bg-danger' ).addClass( 'bg-warning' );
        } else {
            $( '#usage_progress' ).removeClass( 'bg-warning bg-danger' ).addClass( 'bg-success' );
        }
    }

    // Initial fetch of usage status.
    fetchUsageStatus();
} );