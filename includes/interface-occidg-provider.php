<?php
/**
 * AI provider contract.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

/** Provider implementations can be replaced without changing workflow code. */
interface OCC_IDG_Provider_Interface {
	/**
	 * Determine whether provider credentials are available.
	 *
	 * @return bool Whether provider credentials are available.
	 */
	public function validate_credentials();

	/**
	 * Generate metadata for requested fields.
	 *
	 * @param int   $attachment_id   Attachment ID.
	 * @param array $requested_fields Requested fields.
	 * @param array $context          Provider context.
	 * @return array Provider result.
	 */
	public function generate_metadata( $attachment_id, $requested_fields, $context );

	/**
	 * Estimate request and cost usage.
	 *
	 * @param array $attachments     Attachment IDs.
	 * @param array $requested_fields Requested fields.
	 * @return array Estimate data.
	 */
	public function estimate_cost( $attachments, $requested_fields );
}
