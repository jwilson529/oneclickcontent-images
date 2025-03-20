<?php
/**
 * Automatic metadata generation for the OneClickContent Image Details plugin.
 *
 * Handles the automatic generation of metadata when images are uploaded.
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
 * Class OneClickContent_Images_Auto_Generate
 *
 * Handles automatic metadata generation for images during upload in the OneClickContent Image Details plugin.
 *
 * @since 1.0.0
 */
class OneClickContent_Images_Auto_Generate {

	/**
	 * Constructor.
	 *
	 * Hooks into WordPress to trigger automatic metadata generation on image upload.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'auto_generate_metadata' ), 10, 2 );
		add_action( 'wp_ajax_check_image_error', array( $this, 'check_image_error' ) );
		add_action( 'wp_ajax_oneclick_remove_image_error_transient', array( $this, 'oneclick_remove_image_error_transient' ) );
		add_action( 'wp_ajax_oneclick_images_get_all_media_ids', array( $this, 'oneclick_images_get_all_media_ids' ) );
	}

	/**
	 * Automatically generate metadata for an image if the setting is enabled.
	 *
	 * Triggers metadata generation when an image is uploaded, based on the plugin's settings.
	 *
	 * @since 1.0.0
	 * @param array $metadata      The current attachment metadata.
	 * @param int   $attachment_id The attachment ID.
	 * @return array The unmodified metadata.
	 */
	public function auto_generate_metadata( $metadata, $attachment_id ) {
		$auto_add = get_option( 'oneclick_images_auto_add_details', false );

		if ( $auto_add ) {
			if ( ! class_exists( 'OneClickContent_Images_Admin_Settings' ) ) {
				return $metadata; // Fail gracefully if the class is not loaded.
			}

			$oneclick_images_admin = new OneClickContent_Images_Admin_Settings();
			$result                = $oneclick_images_admin->oneclick_images_generate_metadata( $attachment_id );

			if ( isset( $result['error'] ) && false !== strpos( $result['error'], 'Usage limit reached' ) ) {
				set_transient(
					'oneclick_image_error',
					array(
						'message' => sanitize_text_field( $result['message'] ),
						'ad_url'  => esc_url_raw( $result['ad_url'] ),
					),
					5 * MINUTE_IN_SECONDS // 5 minutes.
				);
			}
		}

		return $metadata;
	}

	/**
	 * Handle AJAX request to check for image upload API errors.
	 *
	 * Retrieves transient data about API errors, such as usage limit reached.
	 *
	 * @since 1.0.0
	 * @return void Outputs JSON response with error data or an error message.
	 */
	public function check_image_error() {
		check_ajax_referer( 'oneclick_images_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'oneclickcontent-image-detail-generator' ) ) );
			return;
		}

		$error_data = get_transient( 'oneclick_image_error' );

		if ( $error_data ) {
			wp_send_json_success( $error_data );
		} else {
			wp_send_json_error( array( 'message' => __( 'No error data found.', 'oneclickcontent-image-detail-generator' ) ) );
		}
	}

	/**
	 * Handle AJAX request to remove the error transient when the modal is closed.
	 *
	 * Deletes the transient storing API error data.
	 *
	 * @since 1.0.0
	 * @return void Outputs JSON response confirming the transient removal.
	 */
	public function oneclick_remove_image_error_transient() {
		check_ajax_referer( 'oneclick_images_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'oneclickcontent-image-detail-generator' ) ) );
			return;
		}

		delete_transient( 'oneclick_image_error' );

		wp_send_json_success( array( 'message' => __( 'Transient removed successfully.', 'oneclickcontent-image-detail-generator' ) ) );
	}

	/**
	 * Get all media IDs in the Media Library via AJAX.
	 *
	 * Retrieves all image IDs in the media library for bulk processing.
	 *
	 * @since 1.0.0
	 * @return void Outputs JSON response with the list of image IDs or an error message.
	 */
	public function oneclick_images_get_all_media_ids() {
		check_ajax_referer( 'oneclick_images_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'oneclickcontent-image-detail-generator' ) ) );
			return;
		}

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'post_mime_type' => 'image',
		);

		$query     = new WP_Query( $args );
		$image_ids = wp_list_pluck( $query->posts, 'ID' );

		wp_send_json_success( array( 'ids' => $image_ids ) );
	}
}
