/**
 * Bulk edit JavaScript functionality for OneClickContent Image Details plugin.
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

    /**
     * Debounce function to prevent rapid saves.
     *
     * @param {Function} func The function to debounce.
     * @param {number}   wait The delay in milliseconds.
     * @return {Function} The debounced function.
     */
    function debounce( func, wait ) {
        let timeout;
        return function( ...args ) {
            clearTimeout( timeout );
            timeout = setTimeout( () => func.apply( this, args ), wait );
        };
    }

    // Initialize or reuse DataTable.
    let table;
    if ( $.fn.DataTable.isDataTable( '#image-metadata-table' ) ) {
        table = $( '#image-metadata-table' ).DataTable();
    } else {
        table = $( '#image-metadata-table' ).DataTable( {
            serverSide: true,
            processing: true,
            searching: true,
            ordering: false,
            ajax: {
                url: oneclick_images_bulk_vars.ajax_url,
                type: 'POST',
                data: function( d ) {
                    d.action = 'oneclick_images_get_image_metadata';
                    d.nonce  = oneclick_images_bulk_vars.nonce;
                },
            },
            columns: [
                {
                    data: 'thumbnail',
                    orderable: false,
                    searchable: false,
                },
                {
                    data: 'title',
                    render: function( data, type, row ) {
                        return `<div class="input-wrapper"><input type="text" value="${$( '<div/>' ).text( data || '' ).html()}" data-field="title" data-image-id="${row.id}" style="width: 100%;"><span class="save-status"></span></div>`;
                    },
                    orderable: false,
                },
                {
                    data: 'alt_text',
                    render: function( data, type, row ) {
                        return `<div class="input-wrapper"><input type="text" value="${$( '<div/>' ).text( data || '' ).html()}" data-field="alt_text" data-image-id="${row.id}" style="width: 100%;"><span class="save-status"></span></div>`;
                    },
                    orderable: false,
                },
                {
                    data: 'description',
                    render: function( data, type, row ) {
                        return `<div class="input-wrapper"><textarea data-field="description" data-image-id="${row.id}" style="width: 100%; height: 60px;">${$( '<div/>' ).text( data || '' ).html()}</textarea><span class="save-status"></span></div>`;
                    },
                    orderable: false,
                },
                {
                    data: 'caption',
                    render: function( data, type, row ) {
                        return `<div class="input-wrapper"><textarea data-field="caption" data-image-id="${row.id}" style="width: 100%; height: 60px;">${$( '<div/>' ).text( data || '' ).html()}</textarea><span class="save-status"></span></div>`;
                    },
                    orderable: false,
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function( data, type, row ) {
                        return `<div class="action-wrapper"><button class="generate-metadata button" data-image-id="${row.id}">Generate</button><span class="action-status"></span></div>`;
                    },
                },
            ],
            pageLength: 10,
            lengthMenu: [ 10, 25, 50, 100 ],
        } );
    }

    /**
     * Saves a field value with debounce.
     *
     * @param {jQuery} $input   The input element.
     * @param {Function} callback Optional callback after save.
     */
    const saveField = debounce( function( $input, callback ) {
        const imageId       = $input.data( 'image-id' );
        const field         = $input.data( 'field' );
        const newValue      = $input.val();
        const originalValue = $input.data( 'original-value' ) || '';
        const $status       = $input.siblings( '.save-status' );
        const $row          = $input.closest( 'tr' );
        const rowIndex      = table.row( $row ).index();
        const rowData       = table.row( $row ).data();

        if ( ! rowData ) {
            $status.text( 'Error: Row data lost' ).addClass( 'save-status-error' ).fadeIn();
            table.ajax.reload( function() {
                $status.text( 'Table reloaded' ).addClass( 'save-status-saved' ).fadeIn();
                setTimeout( () => $status.fadeOut(), 2000 );
            }, false );
            if ( callback ) callback();
            return;
        }

        if ( newValue === originalValue ) {
            $status.text( 'No changes' ).addClass( 'save-status-nochange' ).fadeIn();
            setTimeout( () => $status.fadeOut(), 2000 );
            if ( callback ) callback();
            return;
        }

        $status.text( 'Saving...' ).addClass( 'save-status-saving' ).fadeIn();

        const allFields = {
            title: $row.find( 'input[data-field="title"]' ).val() || rowData.title || '',
            alt_text: $row.find( 'input[data-field="alt_text"]' ).val() || rowData.alt_text || '',
            description: $row.find( 'textarea[data-field="description"]' ).val() || rowData.description || '',
            caption: $row.find( 'textarea[data-field="caption"]' ).val() || rowData.caption || '',
        };

        $.ajax( {
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
            success: function( response ) {
                if ( response.success && response.data ) {
                    $status.text( 'Saved!' ).addClass( 'save-status-saved' ).fadeIn();
                    $input.data( 'original-value', newValue );

                    const updatedData = response.data;
                    const currentData = table.row( rowIndex ).data();

                    if ( currentData ) {
                        currentData.thumbnail = currentData.thumbnail || updatedData.thumbnail;
                        currentData.id        = updatedData.id || currentData.id;
                        currentData.title     = updatedData.title;
                        currentData.alt_text  = updatedData.alt_text;
                        currentData.description = updatedData.description;
                        currentData.caption   = updatedData.caption;

                        table.row( rowIndex ).data( currentData );
                        $input.val( updatedData[ field ] );
                    } else {
                        table.ajax.reload( null, false );
                    }
                } else {
                    $status.text( 'Error: ' + ( response.data || 'Invalid response' ) )
                        .addClass( 'save-status-error' ).fadeIn();
                }
                setTimeout( () => $status.fadeOut(), 2000 );
                if ( callback ) callback();
            },
            error: function( xhr ) {
                $status.text( 'Error: ' + xhr.responseText ).addClass( 'save-status-error' ).fadeIn();
                setTimeout( () => $status.fadeOut(), 2000 );
                if ( callback ) callback();
            },
        } );
    }, 500 );

    // Store original value on focus.
    table.on( 'focus', 'input, textarea', function() {
        const $input = $( this );
        $input.data( 'original-value', $input.val() );
    } );

    // Save on blur.
    table.on( 'blur', 'input, textarea', function() {
        const $input = $( this );
        saveField( $input );
    } );

    // Handle Tab key navigation and save.
    table.on( 'keydown', 'input, textarea', function( e ) {
        if ( 'Tab' === e.key ) {
            e.preventDefault();
            const $input      = $( this );
            const $row        = $input.closest( 'tr' );
            const $inputs     = $row.find( 'input, textarea' );
            const currentIndex = $inputs.index( $input );
            const nextIndex   = ( currentIndex + 1 ) % $inputs.length;
            let $nextInput    = nextIndex < $inputs.length ? $inputs.eq( nextIndex ) : null;

            if ( ! $nextInput ) {
                const nextRowIndex = table.row( $row ).index() + 1;
                if ( nextRowIndex < table.rows().count() ) {
                    const $nextRowInputs = table.row( nextRowIndex ).node().querySelectorAll( 'input, textarea' );
                    $nextInput = $nextRowInputs.length ? $( $nextRowInputs[0] ) : null;
                }
            }

            if ( $nextInput ) {
                saveField( $input, function() {
                    const cursorPos = $input[0].selectionStart;
                    $input.val( $input.val() );
                    $nextInput.focus();
                    if ( $nextInput.is( 'textarea' ) ) {
                        $nextInput[0].selectionStart = 0;
                        $nextInput[0].selectionEnd   = 0;
                    }
                } );
            }
        }
    } );

    // Handle metadata generation button click.
    table.on( 'click', '.generate-metadata', function() {
        const $button  = $( this );
        const imageId  = $button.data( 'image-id' );
        const $status  = $button.siblings( '.action-status' );
        const $row     = $button.closest( 'tr' );
        const rowIndex = table.row( $row ).index();

        $button.prop( 'disabled', true ).text( 'Generating...' );

        $.ajax( {
            url: oneclick_images_admin_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'oneclick_images_generate_metadata',
                nonce: oneclick_images_admin_vars.oneclick_images_ajax_nonce,
                image_id: imageId,
            },
            success: function( response ) {
                if ( response.success && response.data && response.data.metadata ) {
                    let metadata = response.data.metadata;
                    if ( typeof metadata === 'object' && metadata.metadata ) {
                        metadata = metadata.metadata;
                    }

                    if ( metadata.success === false ) {
                        if ( metadata.error && metadata.error.includes( 'Free trial limit' ) ) {
                            showModal( 'Free Trial Limit Reached', metadata.message || metadata.error, metadata.ad_url || oneclick_images_admin_vars.subscription_url );
                        } else if ( metadata.error && metadata.error.includes( 'Image validation failed' ) ) {
                            showModal( 'Image Validation Failed', metadata.error, metadata.ad_url || '' );
                        }
                        $button.prop( 'disabled', false ).text( 'Generate' );
                        return;
                    }

                    const rowData = table.row( rowIndex ).data();
                    if ( rowData ) {
                        rowData.title       = metadata.title || rowData.title;
                        rowData.alt_text    = metadata.alt_text || rowData.alt_text;
                        rowData.description = metadata.description || rowData.description;
                        rowData.caption     = metadata.caption || rowData.caption;

                        table.row( rowIndex ).data( rowData );

                        $row.find( 'input[data-field="title"]' ).val( rowData.title );
                        $row.find( 'input[data-field="alt_text"]' ).val( rowData.alt_text );
                        $row.find( 'textarea[data-field="description"]' ).val( rowData.description );
                        $row.find( 'textarea[data-field="caption"]' ).val( rowData.caption );

                        $status.text( 'Done!' ).addClass( 'action-status-success' ).fadeIn();
                        $button.prop( 'disabled', false ).text( 'Generate' );
                        setTimeout( () => $status.fadeOut(), 2000 );
                    } else {
                        table.ajax.reload( null, false );
                    }
                } else {
                    $status.text( 'Error: ' + ( response.message || response.error || 'Failed' ) )
                        .addClass( 'action-status-error' ).fadeIn();
                    $button.prop( 'disabled', false ).text( 'Generate' );
                    setTimeout( () => $status.fadeOut(), 2000 );
                }
            },
            error: function( xhr ) {
                $status.text( 'Error: ' + xhr.responseText ).addClass( 'action-status-error' ).fadeIn();
                $button.prop( 'disabled', false ).text( 'Generate' );
                setTimeout( () => $status.fadeOut(), 2000 );
            },
        } );
    } );

    /**
     * Shows a modal with dynamic content.
     *
     * @param {string} title  The modal title.
     * @param {string} message The modal message.
     * @param {string} adUrl   The URL for the upgrade link, if applicable.
     */
    function showModal( title, message, adUrl ) {
        $( '#bulk-generate-modal .modal-content h2' ).text( title );
        $( '#bulk-generate-modal .modal-content p' ).text( message );
        $( '#bulk-generate-warning' ).hide();

        if ( adUrl ) {
            $( '#bulk-generate-modal .modal-buttons' ).html(
                `<a href="${adUrl}" target="_blank" class="button button-primary">Upgrade Now</a>` +
                '<button id="cancel-bulk-generate" class="button button-secondary">Close</button>'
            );
        } else {
            $( '#bulk-generate-modal .modal-buttons' ).html(
                '<button id="cancel-bulk-generate" class="button button-secondary">Close</button>'
            );
        }

        $( '#bulk-generate-modal' ).show();

        $( '#cancel-bulk-generate' ).off( 'click' ).on( 'click', function() {
            $( '#bulk-generate-modal' ).hide();
        } );
    }
} );