<?php
/**
 * Bulk edit functionality for the OneClickContent Image Details plugin.
 *
 * Handles the bulk edit tab and related AJAX actions for editing and generating image metadata.
 *
 * @package    One_Click_Images
 * @subpackage One_Click_Images/admin
 * @author     OneClickContent <support@oneclickcontent.com>
 * @since      1.0.0
 * @copyright  2025 OneClickContent
 * @license    GPL-2.0+
 * @link       https://oneclickcontent.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Occidg_Bulk_Edit
 *
 * Manages bulk editing and metadata generation in the Media Library.
 *
 * @since 1.0.0
 */
class Occidg_Bulk_Edit {

	/**
	 * Renders the bulk edit tab interface in the admin area.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_bulk_edit_tab() {
		$license_status   = get_option( 'occidg_license_status', 'unknown' );
		$is_valid_license = ( 'active' === $license_status );

		$fallback_image_url = plugin_dir_url( __FILE__ ) . 'assets/icon.png';
		$license_cta_html   = '<div class="bulk-edit-license-warning compact">
	        <div class="cta-left">
	            <img src="' . esc_url( $fallback_image_url ) . '" alt="One Click Content Icon" style="float: left; margin-right: 10px; width: 50px; height: auto;">
	            <h2>' . esc_html__( 'Never Forget an Alt Tag Again!', 'occidg' ) . '</h2>
	            <p>' . esc_html__( 'Upgrade now to automatically generate metadata for your images. Save time and boost your site’s SEO, accessibility, and image searchability.', 'occidg' ) . '</p>
	            <ul class="benefits-list">
	                <li>' . esc_html__( 'Save time with automated metadata generation', 'occidg' ) . '</li>
	                <li>' . esc_html__( 'Ensure every image has a descriptive alt tag', 'occidg' ) . '</li>
	                <li>' . esc_html__( 'Improve SEO, ADA compliance, and user experience', 'occidg' ) . '</li>
	            </ul>
	        </div>
	        <div class="cta-right">
	            <a href="https://oneclickcontent.com/image-detail-generator/" target="_blank" class="btn-license">' . esc_html__( 'Activate License Now', 'occidg' ) . '</a>
	        </div>
	    </div>';

		$usage_data = array(
			'used_count'      => 0,
			'total_allowed'   => 10,
			'remaining_count' => 10,
			'cta_html'        => '',
		);

		wp_localize_script(
			'occidg-bulk-edit',
			'occidg_bulk_vars',
			array(
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'occidg_bulk_edit' ),
				'is_valid_license' => $is_valid_license,
				'license_cta_html' => $license_cta_html,
				'usage'            => $usage_data,
			)
		);
		?>
		<div id="occidg_bulk_edit" class="wrap">
			<h2><?php esc_html_e( 'Bulk Edit Image Metadata', 'occidg' ); ?></h2>
			
			<div class="usage-info-section">
				<h2><?php esc_html_e( 'Your Usage', 'occidg' ); ?></h2>
				<div id="usage_status" class="usage-summary">
					<?php if ( $is_valid_license ) : ?>
						<strong id="usage_count"><?php esc_html_e( 'Loading usage data...', 'occidg' ); ?></strong>
						<div class="progress">
							<div 
								id="usage_progress" 
								class="progress-bar bg-success" 
								role="progressbar" 
								aria-valuenow="0" 
								aria-valuemin="0" 
								aria-valuemax="100" 
								style="width: 0%;"
							>
								0%
							</div>
						</div>
					<?php else : ?>
						<?php echo wp_kses_post( $license_cta_html ); ?>
					<?php endif; ?>
				</div>

				<div class="bulk-edit-header">
					<button id="generate-all-metadata" class="button button-primary button-hero" <?php echo $is_valid_license ? '' : 'disabled'; ?>>
						<?php esc_html_e( 'Generate All Metadata', 'occidg' ); ?>
					</button>
					<button id="stop-bulk-generation" class="button button-secondary" style="display:none;">
						<?php esc_html_e( 'Stop Generation', 'occidg' ); ?>
					</button>
					<p class="description"><?php esc_html_e( 'Click to generate metadata for all your images.', 'occidg' ); ?></p>
				</div>

				<div id="bulk-generate-status" class="bulk-generate-status" style="display: none;">
					<h3><?php esc_html_e( 'Bulk Generation Progress', 'occidg' ); ?></h3>
					<div id="bulk-generate-progress-container" class="bulk-generate-progress-container">
						<div id="bulk-generate-progress-bar" class="bulk-generate-progress-bar"></div>
					</div>
					<div id="bulk-generate-message" class="bulk-generate-message"></div>
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
					<div class="modal-buttons">
						<button id="confirm-bulk-generate" class="button button-primary">
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
	 * Fetch image metadata for DataTables via AJAX with server-side pagination and search.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function get_image_metadata() {
		check_ajax_referer( 'occidg_bulk_edit', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$draw         = isset( $_REQUEST['draw'] ) ? intval( $_REQUEST['draw'] ) : 1;
		$start        = isset( $_REQUEST['start'] ) ? intval( $_REQUEST['start'] ) : 0;
		$length       = isset( $_REQUEST['length'] ) ? intval( $_REQUEST['length'] ) : 10;
		$search_value = isset( $_REQUEST['search']['value'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['search']['value'] ) ) : '';

		$page = $start / $length + 1;

		$args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => $length,
			'paged'          => $page,
		);

		if ( ! empty( $search_value ) ) {
			$args['s'] = $search_value;
		}

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
			$thumbnail = wp_get_attachment_image( $post->ID, 'thumbnail', false, array( 'style' => 'max-width: 50px;' ) );
			$data[]    = array(
				'thumbnail'   => $thumbnail,
				'title'       => esc_html( get_the_title( $post->ID ) ),
				'alt_text'    => esc_attr( get_post_meta( $post->ID, '_wp_attachment_image_alt', true ) ),
				'description' => esc_textarea( $post->post_content ),
				'caption'     => esc_textarea( $post->post_excerpt ),
				'id'          => $post->ID,
			);
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
	 * Save edited metadata via AJAX.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function save_bulk_metadata() {
		check_ajax_referer( 'occidg_bulk_edit', 'nonce' );
		if ( false === current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}
		$image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;

		// Explicitly check for empty strings using strict comparison.
		$title       = ( isset( $_POST['title'] ) && '' !== $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$alt_text    = ( isset( $_POST['alt_text'] ) && '' !== $_POST['alt_text'] ) ? sanitize_text_field( wp_unslash( $_POST['alt_text'] ) ) : '';
		$description = ( isset( $_POST['description'] ) && '' !== $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$caption     = ( isset( $_POST['caption'] ) && '' !== $_POST['caption'] ) ? sanitize_textarea_field( wp_unslash( $_POST['caption'] ) ) : '';

		if ( 0 === $image_id ) {
			wp_send_json_error( 'Invalid image ID.' );
		}

		// Update the metadata.
		update_post_meta( $image_id, '_wp_attachment_image_alt', $alt_text );

		// Force update with empty values.
		$post_update_args = array(
			'ID'           => $image_id,
			'post_title'   => $title,
			'post_content' => $description,
			'post_excerpt' => $caption,
		);

		// Use wp_update_post with the 'force_update' parameter.
		wp_update_post( $post_update_args, true );

		// Return the updated metadata.
		$updated_data = array(
			'id'          => $image_id,
			'title'       => $title,
			'alt_text'    => $alt_text,
			'description' => $description,
			'caption'     => $caption,
			'thumbnail'   => wp_get_attachment_image( $image_id, 'thumbnail', false, array( 'class' => 'thumbnail-preview' ) ),
		);
		wp_send_json_success( $updated_data );
	}
}