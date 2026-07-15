<?php
/**
 * Adapts the existing OpenAI/Gemini request implementation to the provider API.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

/** Provider adapter used while keeping the mature response decoding code. */
class Occidg_Provider_Adapter implements OCC_IDG_Provider_Interface {
	/**
	 * Provider slug.
	 *
	 * @var string
	 */
	private $provider;

	/**
	 * Legacy provider generator callback.
	 *
	 * @var callable|null
	 */
	private $generator;

	/**
	 * Construct a provider adapter.
	 *
	 * @param string   $provider  Provider slug.
	 * @param callable $generator Provider generator callback.
	 */
	public function __construct( $provider, $generator ) {
		$this->provider  = 'gemini' === $provider ? 'gemini' : 'openai';
		$this->generator = is_callable( $generator ) ? $generator : null;
	}

	/**
	 * Determine whether provider credentials are available.
	 *
	 * @return bool Whether provider credentials are available.
	 */
	public function validate_credentials() {
		$constant = 'openai' === $this->provider ? 'OCC_IDG_OPENAI_API_KEY' : 'OCC_IDG_GEMINI_API_KEY';
		$option   = 'occidg_' . $this->provider . '_api_key';
		return ( defined( $constant ) && constant( $constant ) ) || (string) get_option( $option, '' ) !== '';
	}

	/**
	 * Generate metadata through the adapted provider callback.
	 *
	 * @param int   $attachment_id   Attachment ID.
	 * @param array $requested_fields Requested metadata fields.
	 * @param array $context          Provider context.
	 * @return array Provider result.
	 */
	public function generate_metadata( $attachment_id, $requested_fields, $context ) {
		if ( ! is_callable( $this->generator ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Provider generator is unavailable.', 'occidg' ),
			);
		}
		$field_map = array_fill_keys( Occidg_Metadata::normalize_fields( $requested_fields ), '1' );
		$context   = array_merge(
			(array) $context,
			array(
				'provider'          => $this->provider,
				'selected_fields'   => $field_map,
				'override_metadata' => true,
				'persist'           => false,
			)
		);

		return call_user_func( $this->generator, absint( $attachment_id ), $context );
	}

	/**
	 * Estimate request, token, and configured cost usage.
	 *
	 * @param array $attachments     Attachment IDs.
	 * @param array $requested_fields Requested metadata fields.
	 * @return array Estimate data.
	 */
	public function estimate_cost( $attachments, $requested_fields ) {
		$count            = count( (array) $attachments );
		$enabled_fields   = count( Occidg_Metadata::normalize_fields( $requested_fields ) );
		$cost_per_request = (float) get_option( 'occ_idg_' . $this->provider . '_cost_per_request', 0 );
		return array(
			'provider'           => $this->provider,
			'estimated_requests' => $count,
			'estimated_images'   => $count,
			'estimated_tokens'   => $count * max( 1, $enabled_fields ) * 150,
			'estimated_cost'     => round( $count * $cost_per_request, 6 ),
			'currency'           => 'USD',
		);
	}
}
