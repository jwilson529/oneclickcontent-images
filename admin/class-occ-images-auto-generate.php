<?php
/**
 * Admin-specific functionality of the plugin.
 *
 * This class runs the automatic generation of metadata as the files are uploaded.
 *
 * @link       https://oneclickcontent.com
 * @since      1.0.0
 * @package    Occ_Images
 * @subpackage Occ_Images/admin
 */

/**
 * Class Occ_Images_Auto_Generate
 *
 * Handles automatic metadata generation when an image is uploaded.
 *
 * @since 1.0.0
 * @package Occ_Images
 */
class Occ_Images_Auto_Generate {

	/**
	 * Hook into the media upload process to generate metadata.
	 */
	public function __construct() {
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'auto_generate_metadata' ), 10, 2 );
	}

	/**
	 * Automatically generate metadata for an image if the setting is enabled.
	 *
	 * @param array $metadata The current attachment metadata.
	 * @param int   $attachment_id The attachment ID.
	 * @return array The metadata (unmodified).
	 */
	public function auto_generate_metadata( $metadata, $attachment_id ) {
		// Check if the "Auto Add Details on Upload" option is enabled.
		$auto_add = get_option( 'occ_images_auto_add_details', false );

		// Only run the API call if the option is enabled.
		if ( $auto_add ) {
			// Get the existing instance of the admin settings class or call the metadata generation function.
			$occ_images_admin = new Occ_Images_Admin_Settings();

			// Run the metadata generation function.
			$occ_images_admin->occ_images_generate_metadata( $attachment_id );
		}

		// Return the metadata (unmodified).
		return $metadata;
	}

	// Add this in the same class where you handle AJAX
	public function occ_images_get_all_media_ids() {
		// Verify the nonce
		check_ajax_referer( 'occ_images_ajax_nonce', 'nonce' );

		// Ensure the user has permission to upload files
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		// Query all images in the media library
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'post_mime_type' => 'image',
		);

		$query     = new WP_Query( $args );
		$image_ids = wp_list_pluck( $query->posts, 'ID' );

		// Return the list of image IDs
		wp_send_json_success( array( 'ids' => $image_ids ) );
	}
}
