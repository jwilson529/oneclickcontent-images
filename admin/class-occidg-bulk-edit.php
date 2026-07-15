<?php
/**
 * Bulk edit functionality for the AI image metadata plugin.
 *
 * Handles the bulk edit tab and related AJAX actions for editing and generating image metadata.
 *
 * @package    One_Click_Images
 * @subpackage One_Click_Images/admin
 * @author     James Wilson
 * @since      1.0.0
 * @copyright  2025 OneClickContent
 * @license    GPL-2.0+
 * @link       https://github.com/jwilson529/oneclickcontent-images
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Occidg_Bulk_Edit
 *
 * Manages bulk editing and metadata generation in the Media Library.
 *
 * @since 1.0.0
 */
class Occidg_Bulk_Edit {
	/**
	 * Database service used to load saved suggestions.
	 *
	 * @var Occidg_Database|null
	 */
	private $database;

	/**
	 * Construct the bulk editor.
	 *
	 * @param Occidg_Database|null $database Database service.
	 */
	public function __construct( $database = null ) {
		$this->database = $database;
	}

	/**
	 * Render the editable table inside the unified Image Library.
	 *
	 * @param array $filters       Sanitized Image Library filters.
	 * @param bool  $filter_active Whether the filters should be applied.
	 * @return void
	 */
	public function render_library_table( $filters = array(), $filter_active = false ) {
		$filters = is_array( $filters ) ? $filters : array();
		?>
		<div id="occidg_bulk_edit" class="occidg-library-editor" data-filter-active="<?php echo $filter_active ? '1' : '0'; ?>" data-library-filters="<?php echo esc_attr( wp_json_encode( $filters ) ); ?>">
			<div class="occidg-bulk-selection-toolbar" aria-label="<?php esc_attr_e( 'Bulk image generation', 'occidg' ); ?>">
				<div class="occidg-bulk-selection-summary">
					<strong id="occidg-selected-count"><?php esc_html_e( '0 selected', 'occidg' ); ?></strong>
					<span><?php esc_html_e( 'Select images here, then queue a safe background batch.', 'occidg' ); ?></span>
				</div>
				<div class="occidg-bulk-selection-actions">
					<button type="button" id="occidg-select-all-matching" class="button"><?php esc_html_e( 'Select all matching', 'occidg' ); ?></button>
					<button type="button" id="occidg-clear-selection" class="button" disabled><?php esc_html_e( 'Clear selection', 'occidg' ); ?></button>
					<button type="button" class="button button-primary occidg-queue-selected" data-mode="fill_missing" disabled><?php esc_html_e( 'Fill missing', 'occidg' ); ?></button>
					<button type="button" class="button occidg-queue-selected" data-mode="suggestion" disabled><?php esc_html_e( 'Create review suggestions', 'occidg' ); ?></button>
				</div>
				<p id="occidg-bulk-selection-status" class="occidg-bulk-selection-status" aria-live="polite"></p>
			</div>
			<section id="occidg-selected-batch-progress" class="occidg-selected-batch-progress" aria-labelledby="occidg-selected-batch-heading" tabindex="-1" hidden>
				<div class="occidg-selected-batch-progress__header">
					<div>
						<p class="occidg-eyebrow"><?php esc_html_e( 'Background batch', 'occidg' ); ?></p>
						<h3 id="occidg-selected-batch-heading"><?php esc_html_e( 'Batch progress', 'occidg' ); ?></h3>
					</div>
					<span id="occidg-selected-batch-state" class="occidg-selected-batch-state"><?php esc_html_e( 'Queued', 'occidg' ); ?></span>
				</div>
				<p id="occidg-selected-batch-message" class="occidg-selected-batch-message" aria-live="polite"></p>
				<div id="occidg-selected-batch-progress-track" class="occidg-selected-batch-progress-track" role="progressbar" aria-label="<?php esc_attr_e( 'Batch completion', 'occidg' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
					<span id="occidg-selected-batch-progress-bar" class="occidg-selected-batch-progress-bar"></span>
				</div>
				<dl class="occidg-selected-batch-stats">
					<div><dt><?php esc_html_e( 'Processed', 'occidg' ); ?></dt><dd id="occidg-selected-batch-processed">0 / 0</dd></div>
					<div><dt><?php esc_html_e( 'Succeeded', 'occidg' ); ?></dt><dd id="occidg-selected-batch-succeeded">0</dd></div>
					<div><dt><?php esc_html_e( 'Failed', 'occidg' ); ?></dt><dd id="occidg-selected-batch-failed">0</dd></div>
					<div><dt><?php esc_html_e( 'Skipped', 'occidg' ); ?></dt><dd id="occidg-selected-batch-skipped">0</dd></div>
				</dl>
				<p id="occidg-selected-batch-refresh-status" class="occidg-selected-batch-refresh-status" aria-live="polite" hidden></p>
				<div class="occidg-selected-batch-progress__actions">
					<a id="occidg-selected-batch-details" class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=occ-idg-batches' ) ); ?>"><?php esc_html_e( 'View batch details', 'occidg' ); ?></a>
					<button type="button" id="occidg-dismiss-selected-batch" class="button button-link" hidden><?php esc_html_e( 'Dismiss', 'occidg' ); ?></button>
				</div>
			</section>

			<div id="occidg-bulk-confirm-modal" class="occidg-bulk-confirm-modal" hidden>
				<div class="occidg-bulk-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="occidg-bulk-confirm-title" aria-describedby="occidg-bulk-confirm-description" tabindex="-1">
					<button type="button" id="occidg-bulk-confirm-close" class="occidg-bulk-confirm-close" aria-label="<?php esc_attr_e( 'Close dialog', 'occidg' ); ?>">
						<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					</button>
					<div class="occidg-bulk-confirm-icon" aria-hidden="true"><span class="dashicons dashicons-images-alt2"></span></div>
					<h2 id="occidg-bulk-confirm-title"><?php esc_html_e( 'Start a background batch?', 'occidg' ); ?></h2>
					<p id="occidg-bulk-confirm-description"></p>
					<p class="occidg-bulk-confirm-note"><?php esc_html_e( 'You can stay on this page. Progress will update here automatically.', 'occidg' ); ?></p>
					<div class="occidg-bulk-confirm-actions">
						<button type="button" id="occidg-bulk-confirm-cancel" class="button"><?php esc_html_e( 'Go back', 'occidg' ); ?></button>
						<button type="button" id="occidg-bulk-confirm-submit" class="button button-primary"><?php esc_html_e( 'Start batch', 'occidg' ); ?></button>
					</div>
				</div>
			</div>
			<table id="image-metadata-table" class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th class="occidg-select-column"><input type="checkbox" id="occidg-select-page" aria-label="<?php esc_attr_e( 'Select all eligible images on this page', 'occidg' ); ?>"></th>
						<th><?php esc_html_e( 'Thumbnail', 'occidg' ); ?></th>
						<th><?php esc_html_e( 'Title', 'occidg' ); ?></th>
						<th><?php esc_html_e( 'Alt Text', 'occidg' ); ?></th>
						<th><?php esc_html_e( 'Description', 'occidg' ); ?></th>
						<th><?php esc_html_e( 'Caption', 'occidg' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'occidg' ); ?></th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Renders the bulk edit tab interface in the admin area.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_bulk_edit_tab() {
		$gate_state     = Occidg_Admin_Settings::get_generation_gate_state();
		$generate_attrs = $gate_state['has_selected_provider_key']
			? ''
			: sprintf(
				' disabled="disabled" aria-disabled="true" title="%s"',
				esc_attr( $gate_state['missing_key_message'] )
			);

		wp_localize_script(
			'occidg-bulk-edit',
			'occidg_bulk_vars',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'occidg_bulk_edit' ),
			)
		);
		?>
		<div id="occidg_bulk_edit" class="wrap">
			<h2><?php esc_html_e( 'Bulk Edit Image Metadata', 'occidg' ); ?></h2>
			
			<div class="usage-info-section">
				<div class="bulk-edit-header">
					<button id="generate-all-metadata" class="button button-primary button-hero"<?php echo wp_kses_post( $generate_attrs ); ?>>
						<?php esc_html_e( 'Generate All Metadata', 'occidg' ); ?>
					</button>
					<p class="description"><?php esc_html_e( 'Click to generate metadata for all your images using your configured provider.', 'occidg' ); ?></p>
					<?php if ( ! $gate_state['has_selected_provider_key'] ) : ?>
						<p class="occidg-generation-gate-message">
							<?php echo esc_html( $gate_state['missing_key_message'] ); ?>
						</p>
					<?php endif; ?>
				</div>

				<div id="bulk-generate-status" class="bulk-generate-status" style="display: none;">
					<h3><?php esc_html_e( 'Bulk Generation Progress', 'occidg' ); ?></h3>
					<div id="bulk-generate-progress-container" class="bulk-generate-progress-container">
						<div id="bulk-generate-progress-bar" class="bulk-generate-progress-bar"></div>
					</div>
					<div class="occidg-job-toolbar">
						<div id="bulk-generate-message" class="bulk-generate-message" aria-live="polite"></div>
						<div class="occidg-job-actions">
							<button id="pause-bulk-generation" class="button button-secondary" style="display:none;">
								<?php esc_html_e( 'Pause', 'occidg' ); ?>
							</button>
							<button id="resume-bulk-generation" class="button button-secondary" style="display:none;">
								<?php esc_html_e( 'Resume', 'occidg' ); ?>
							</button>
							<button id="cancel-bulk-generation" class="button button-link-delete" style="display:none;">
								<?php esc_html_e( 'Cancel', 'occidg' ); ?>
							</button>
							<button id="retry-bulk-generation" class="button button-secondary" style="display:none;">
								<?php esc_html_e( 'Retry Failed', 'occidg' ); ?>
							</button>
						</div>
					</div>
					<div id="bulk-generate-summary" class="occidg-job-summary" aria-live="polite"></div>
					<div id="bulk-generate-failures" class="occidg-job-failures"></div>
				</div>
			</div>

			<div id="bulk-generate-modal" class="modal" style="display:none;">
				<div class="modal-content">
					<h2><?php esc_html_e( 'Confirm Bulk Metadata Generation', 'occidg' ); ?></h2>
					<p><?php esc_html_e( 'Generate metadata for all images in your library? This may take some time.', 'occidg' ); ?></p>
					<div id="bulk-generate-warning" class="warning" style="display:none;">
						<p><strong><?php esc_html_e( 'Warning:', 'occidg' ); ?></strong> 
							<?php esc_html_e( 'This will overwrite any existing image metadata.', 'occidg' ); ?>
						</p>
					</div>
					<?php if ( ! $gate_state['has_selected_provider_key'] ) : ?>
						<p class="occidg-generation-gate-message occidg-generation-gate-message-modal">
							<?php echo esc_html( $gate_state['missing_key_message'] ); ?>
						</p>
					<?php endif; ?>
					<div class="modal-buttons">
						<button id="confirm-bulk-generate" class="button button-primary"<?php echo wp_kses_post( $generate_attrs ); ?>>
							<?php esc_html_e( 'Yes, Generate', 'occidg' ); ?>
						</button>
						<button id="cancel-bulk-generate" class="button button-secondary">
							<?php esc_html_e( 'Cancel', 'occidg' ); ?>
						</button>
					</div>
				</div>
			</div>

			<table id="image-metadata-table" class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Thumbnail', 'occidg' ); ?></th>
						<th><?php esc_html_e( 'Title', 'occidg' ); ?></th>
						<th><?php esc_html_e( 'Alt Text', 'occidg' ); ?></th>
						<th><?php esc_html_e( 'Description', 'occidg' ); ?></th>
						<th><?php esc_html_e( 'Caption', 'occidg' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'occidg' ); ?></th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Fetch image metadata for DataTables via AJAX with server-side pagination, search, and sorting.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function get_image_metadata() {
		check_ajax_referer( 'occidg_bulk_edit', 'nonce' );
		if ( ! current_user_can( 'occ_idg_view_dashboard' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'occidg' ) );
		}

		$draw         = isset( $_REQUEST['draw'] ) ? intval( $_REQUEST['draw'] ) : 1;
		$start        = isset( $_REQUEST['start'] ) ? intval( $_REQUEST['start'] ) : 0;
		$length       = isset( $_REQUEST['length'] ) ? intval( $_REQUEST['length'] ) : 10;
		$search_value = isset( $_REQUEST['search']['value'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search']['value'] ) ) : '';

		$length        = min( 100, max( 1, $length ) );
		$page          = (int) floor( $start / $length ) + 1;
		$filter_active = ! empty( $_REQUEST['filter_active'] );
		$filters       = $this->get_library_filters_from_request();
		$filtered_ids  = $filter_active ? ( new Occidg_Preflight() )->query_ids( $filters, 10000 ) : array();

		if ( $filter_active && empty( $filtered_ids ) ) {
			wp_send_json(
				array(
					'draw'            => $draw,
					'recordsTotal'    => 0,
					'recordsFiltered' => 0,
					'data'            => array(),
				)
			);
		}

		// Base query arguments.
		$args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => $length,
			'paged'          => $page,
		);
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$args['author'] = get_current_user_id();
		}
		if ( $filter_active ) {
			$args['post__in'] = array_slice( $filtered_ids, 0, 10000 );
		}

		if ( '' !== $search_value ) {
			$args['s'] = $search_value;
		}

		// Handle ordering parameters sent by DataTables.
		// DataTables sends order[0][column] and order[0][dir].
		$order_column_index = isset( $_REQUEST['order'][0]['column'] ) ? intval( $_REQUEST['order'][0]['column'] ) : 2;
		$order_dir          = isset( $_REQUEST['order'][0]['dir'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'][0]['dir'] ) ) : 'asc';

		// Map DataTables column index to WP_Query keys.
		// Column indices: 0 = select, 1 = thumbnail, 2 = title, 3 = alt_text, 4 = description, 5 = caption, 6 = actions.
		if ( 3 === $order_column_index ) {
			// For alt_text, sort by the meta value.
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Sorting by alt text is required, and performance will be monitored.
			$args['meta_key'] = '_wp_attachment_image_alt';
			$args['orderby']  = 'meta_value';
			$args['order']    = $order_dir;
		} elseif ( in_array( $order_column_index, array( 2, 4, 5 ), true ) ) {
			$column_map      = array(
				2 => 'post_title',
				4 => 'post_content',
				5 => 'post_excerpt',
			);
			$args['orderby'] = $column_map[ $order_column_index ];
			$args['order']   = $order_dir;
		}
		// Selection, thumbnail, and action columns keep the default ordering.

		// Get total record count.
		$total_query   = new WP_Query(
			array_merge(
				$args,
				array(
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			)
		);
		$records_total = $total_query->found_posts;

		$query            = new WP_Query( $args );
		$records_filtered = $query->found_posts;

		$data = array();
		foreach ( $query->posts as $post ) {
			$row = $this->prepare_metadata_row(
				$post->ID,
				wp_get_attachment_image( $post->ID, 'thumbnail', false, array( 'style' => 'max-width: 50px;' ) ),
				array(
					'title'       => get_the_title( $post->ID ),
					'alt_text'    => get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
					'description' => $post->post_content,
					'caption'     => $post->post_excerpt,
				),
				$post->post_mime_type
			);

			$row['pending_suggestions'] = $this->get_pending_suggestions( $post->ID );
			$row['pending_count']       = count( $row['pending_suggestions'] );
			$row['edit_url']            = get_edit_post_link( $post->ID, 'raw' );
			$row['history_url']         = admin_url( 'admin.php?page=occ-idg-history&attachment_id=' . $post->ID );
			$data[]                     = $row;
		}

		wp_send_json(
			array(
				'draw'            => $draw,
				'recordsTotal'    => $records_total,
				'recordsFiltered' => $records_filtered,
				'data'            => $data,
			)
		);
	}

	/**
	 * Return every eligible attachment ID matching the current Image Library view.
	 *
	 * The endpoint only resolves IDs. Provider work remains in the durable queue.
	 *
	 * @since 2.0.2
	 * @return void
	 */
	public function get_bulk_selection_ids() {
		check_ajax_referer( 'occidg_bulk_edit', 'nonce' );
		if ( ! current_user_can( 'occ_idg_generate_metadata' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to generate metadata.', 'occidg' ) ) );
			return;
		}

		$search_value  = isset( $_REQUEST['search_value'] ) && ! is_array( $_REQUEST['search_value'] )
			? sanitize_text_field( wp_unslash( $_REQUEST['search_value'] ) )
			: '';
		$filter_active = ! empty( $_REQUEST['filter_active'] );
		$filters       = $this->get_library_filters_from_request();
		$filtered_ids  = $filter_active ? ( new Occidg_Preflight() )->query_ids( $filters, 10000 ) : array();
		$max_selection = max( 1, absint( get_option( 'occ_idg_max_batch_size', 1000 ) ) );

		if ( $filter_active && empty( $filtered_ids ) ) {
			wp_send_json_success(
				array(
					'image_ids' => array(),
					'count'     => 0,
				)
			);
			return;
		}

		$args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => $max_selection + 1,
			'fields'         => 'ids',
		);
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$args['author'] = get_current_user_id();
		}
		if ( $filter_active ) {
			$args['post__in'] = array_slice( $filtered_ids, 0, 10000 );
		}
		if ( '' !== $search_value ) {
			$args['s'] = $search_value;
		}

		$query = new WP_Query( $args );
		if ( (int) $query->found_posts > $max_selection ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: 1: matching image count, 2: maximum batch size. */
						__( '%1$s images match, which is above the %2$s-image batch limit. Narrow the filters or raise the maximum batch size.', 'occidg' ),
						number_format_i18n( (int) $query->found_posts ),
						number_format_i18n( $max_selection )
					),
				)
			);
			return;
		}

		$image_ids = array();
		foreach ( $query->posts as $attachment_id ) {
			$attachment_id = absint( $attachment_id );
			if ( ! $attachment_id || Occidg_Image_Support::is_svg_attachment( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
				continue;
			}
			$image_ids[] = $attachment_id;
		}

		wp_send_json_success(
			array(
				'image_ids' => array_values( array_unique( $image_ids ) ),
				'count'     => count( $image_ids ),
			)
		);
	}

	/**
	 * Decode and recursively sanitize Image Library filters from the request.
	 *
	 * @return array Sanitized filters.
	 */
	private function get_library_filters_from_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Both callers verify the bulk-edit AJAX nonce before parsing these filters.
		$filters = isset( $_REQUEST['library_filters'] ) && ! is_array( $_REQUEST['library_filters'] )
			? json_decode( wp_unslash( $_REQUEST['library_filters'] ), true ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is recursively sanitized below.
			: array();
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return is_array( $filters ) ? map_deep( $filters, 'sanitize_text_field' ) : array();
	}

	/**
	 * Return pending suggestions in a safe JSON shape for inline review.
	 *
	 * @param int $image_id Attachment ID.
	 * @return array
	 */
	private function get_pending_suggestions( $image_id ) {
		if ( ! $this->database || ! current_user_can( 'occ_idg_review_suggestions' ) ) {
			return array();
		}

		$rows = $this->database->get_suggestions(
			array(
				'status'        => 'pending',
				'attachment_id' => $image_id,
				'limit'         => 20,
			)
		);

		return array_map(
			function ( $row ) {
				return array(
					'id'                => (int) $row['id'],
					'field_name'        => sanitize_key( $row['field_name'] ),
					'current_value'     => (string) $row['current_value'],
					'suggested_value'   => (string) $row['suggested_value'],
					'confidence'        => sanitize_key( $row['confidence'] ),
					'confidence_reason' => sanitize_text_field( $row['confidence_reason'] ),
					'provider'          => sanitize_key( $row['provider'] ),
					'model'             => sanitize_text_field( $row['model'] ),
					'generated_at'      => sanitize_text_field( $row['generated_at'] ),
					'nonce'             => wp_create_nonce( 'occ_idg_suggestion_' . (int) $row['id'] ),
				);
			},
			$rows
		);
	}

	/**
	 * Prepare an attachment row for JSON transport.
	 *
	 * Metadata must remain unescaped in the JSON response. The bulk-edit script
	 * escapes each value for its HTML context when it renders the form controls.
	 * Escaping here as well would expose entities such as &#039; to users.
	 *
	 * @since 1.2.5
	 * @param int    $image_id Attachment ID.
	 * @param string $thumbnail Rendered attachment thumbnail HTML.
	 * @param array  $metadata Raw attachment metadata.
	 * @param string $mime_type Attachment MIME type.
	 * @return array<string, array|bool|int|string> DataTables row data.
	 */
	private function prepare_metadata_row( $image_id, $thumbnail, $metadata, $mime_type = '' ) {
		$svg_unsupported = Occidg_Image_Support::is_svg_mime_type( $mime_type ) || Occidg_Image_Support::is_svg_attachment( $image_id );
		$empty_fields    = array();
		foreach ( array_keys( Occidg_Metadata::FIELDS ) as $field ) {
			$empty_fields[ $field ] = Occidg_Metadata::is_empty( isset( $metadata[ $field ] ) ? $metadata[ $field ] : '' );
		}

		return array(
			'thumbnail'            => $thumbnail,
			'title'                => $metadata['title'],
			'alt_text'             => $metadata['alt_text'],
			'description'          => $metadata['description'],
			'caption'              => $metadata['caption'],
			'id'                   => $image_id,
			'mime_type'            => sanitize_text_field( $mime_type ),
			'generation_supported' => ! $svg_unsupported,
			'generation_message'   => $svg_unsupported ? Occidg_Image_Support::get_svg_generation_message() : '',
			'empty_fields'         => $empty_fields,
		);
	}

	/**
	 * Save edited metadata via AJAX.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function save_bulk_metadata() {
		check_ajax_referer( 'occidg_bulk_edit', 'nonce' );
		$image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;

		// Explicitly check for empty strings using strict comparison.
		$title       = ( isset( $_POST['title'] ) && '' !== $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$alt_text    = ( isset( $_POST['alt_text'] ) && '' !== $_POST['alt_text'] ) ? sanitize_text_field( wp_unslash( $_POST['alt_text'] ) ) : '';
		$description = ( isset( $_POST['description'] ) && '' !== $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$caption     = ( isset( $_POST['caption'] ) && '' !== $_POST['caption'] ) ? sanitize_textarea_field( wp_unslash( $_POST['caption'] ) ) : '';

		if ( 0 === $image_id || 'attachment' !== get_post_type( $image_id ) || 0 !== strpos( (string) get_post_mime_type( $image_id ), 'image/' ) || ! current_user_can( 'edit_post', $image_id ) ) {
			wp_send_json_error( __( 'Invalid image or insufficient permission.', 'occidg' ) );
			return;
		}

		if ( class_exists( 'Occidg_Metadata' ) && class_exists( 'Occidg_Database' ) ) {
			$metadata_service = new Occidg_Metadata( new Occidg_Database() );
			foreach ( array(
				'title'       => $title,
				'alt_text'    => $alt_text,
				'description' => $description,
				'caption'     => $caption,
			) as $field => $value ) {
				$metadata_service->update_field(
					$image_id,
					$field,
					$value,
					array(
						'action_type'     => 'manual_edit',
						'processing_mode' => 'bulk_edit',
						'approved_by'     => get_current_user_id(),
					)
				);
			}
		} else {
			update_post_meta( $image_id, '_wp_attachment_image_alt', $alt_text );
			wp_update_post(
				array(
					'ID'           => $image_id,
					'post_title'   => $title,
					'post_content' => $description,
					'post_excerpt' => $caption,
				),
				true
			);
		}

		// Return the updated metadata.
		$updated_data = $this->prepare_metadata_row(
			$image_id,
			wp_get_attachment_image( $image_id, 'thumbnail', false, array( 'class' => 'thumbnail-preview' ) ),
			array(
				'title'       => $title,
				'alt_text'    => $alt_text,
				'description' => $description,
				'caption'     => $caption,
			),
			get_post_mime_type( $image_id )
		);
		wp_send_json_success( $updated_data );
	}

	/**
	 * Apply one explicitly approved AI suggestion from the Bulk Edit preview.
	 *
	 * This is a manual per-field decision, so it is intentionally independent
	 * from the automatic overwrite setting. The approved change is still stored
	 * in the immutable history ledger.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function apply_bulk_suggestion() {
		check_ajax_referer( 'occidg_bulk_edit', 'nonce' );

		$image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;
		$field    = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		if ( ! $image_id || ! isset( Occidg_Metadata::FIELDS[ $field ] ) || ! current_user_can( 'occ_idg_generate_metadata' ) || ! current_user_can( 'edit_post', $image_id ) ) {
			wp_send_json_error( __( 'Invalid image field or insufficient permission.', 'occidg' ) );
			return;
		}

		if ( in_array( $field, array( 'description', 'caption' ), true ) ) {
			$value           = isset( $_POST['value'] ) && ! is_array( $_POST['value'] ) ? sanitize_textarea_field( wp_unslash( $_POST['value'] ) ) : '';
			$suggested_value = isset( $_POST['suggested_value'] ) && ! is_array( $_POST['suggested_value'] ) ? sanitize_textarea_field( wp_unslash( $_POST['suggested_value'] ) ) : $value;
		} else {
			$value           = isset( $_POST['value'] ) && ! is_array( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
			$suggested_value = isset( $_POST['suggested_value'] ) && ! is_array( $_POST['suggested_value'] ) ? sanitize_text_field( wp_unslash( $_POST['suggested_value'] ) ) : $value;
		}
		$provider = get_option( 'occidg_provider', 'openai' );
		$model    = get_option( 'gemini' === $provider ? 'occidg_gemini_model' : 'occidg_openai_model', '' );
		$metadata = new Occidg_Metadata( new Occidg_Database() );
		$result   = $metadata->update_field(
			$image_id,
			$field,
			$value,
			array(
				'provider'        => $provider,
				'model'           => $model,
				'confidence'      => 'medium',
				'action_type'     => 'suggestion_approved',
				'processing_mode' => 'bulk_preview',
				'initiated_by'    => get_current_user_id(),
				'approved_by'     => get_current_user_id(),
				'suggested_value' => $suggested_value,
				'was_edited'      => $value !== $suggested_value,
				'prompt_version'  => Occidg_Workflow::PROMPT_VERSION,
			)
		);

		if ( empty( $result['success'] ) ) {
			wp_send_json_error( isset( $result['error'] ) ? $result['error'] : __( 'Unable to apply this suggestion.', 'occidg' ) );
			return;
		}

		$current = $metadata->get_all( $image_id );
		$row     = $this->prepare_metadata_row(
			$image_id,
			wp_get_attachment_image( $image_id, 'thumbnail', false, array( 'class' => 'thumbnail-preview' ) ),
			$current,
			get_post_mime_type( $image_id )
		);

		wp_send_json_success(
			array(
				'field'    => $field,
				'value'    => $result['new_value'],
				'is_empty' => Occidg_Metadata::is_empty( $result['new_value'] ),
				'row'      => $row,
				'message'  => __( 'Suggestion applied.', 'occidg' ),
			)
		);
	}
}
