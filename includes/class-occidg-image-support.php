<?php
/**
 * Image-format support rules for metadata generation.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

/**
 * Centralizes attachment-format decisions used by every generation entry point.
 */
final class Occidg_Image_Support {

	/**
	 * Determine whether a MIME type represents an SVG image.
	 *
	 * @param string $mime_type Attachment MIME type.
	 * @return bool
	 */
	public static function is_svg_mime_type( $mime_type ) {
		return 'image/svg+xml' === strtolower( trim( (string) $mime_type ) );
	}

	/**
	 * Determine whether an attachment is an SVG by MIME type or file extension.
	 *
	 * The extension fallback handles libraries where an SVG was imported with a
	 * generic MIME type.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function is_svg_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return false;
		}

		if ( self::is_svg_mime_type( get_post_mime_type( $attachment_id ) ) ) {
			return true;
		}

		$file_path = (string) get_attached_file( $attachment_id );
		if ( '' === $file_path ) {
			return false;
		}

		$parsed_path = wp_parse_url( $file_path, PHP_URL_PATH );
		$parsed_path = is_string( $parsed_path ) && '' !== $parsed_path ? $parsed_path : $file_path;

		return 'svg' === strtolower( (string) pathinfo( $parsed_path, PATHINFO_EXTENSION ) );
	}

	/**
	 * Return the user-facing explanation for unsupported SVG generation.
	 *
	 * @return string
	 */
	public static function get_svg_generation_message() {
		return __( 'AI metadata generation is not available for SVG files. You can edit this file\'s metadata manually.', 'occidg' );
	}

	/**
	 * Return the normalized skipped result used by generation workflows.
	 *
	 * @return array
	 */
	public static function get_svg_skipped_result() {
		return array(
			'success'   => true,
			'skipped'   => true,
			'reason'    => 'unsupported_svg',
			'temporary' => false,
			'message'   => self::get_svg_generation_message(),
		);
	}
}
