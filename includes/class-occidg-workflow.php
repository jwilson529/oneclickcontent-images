<?php
/**
 * Safe generation, suggestion, review, and dry-run workflows.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

/** Coordinates providers, metadata safety rules, and operational records. */
class Occidg_Workflow {
	const PROMPT_VERSION = 'centerstone-2.0';

	/**
	 * Database service.
	 *
	 * @var Occidg_Database
	 */
	private $database;

	/**
	 * Metadata service.
	 *
	 * @var Occidg_Metadata
	 */
	private $metadata;

	/**
	 * Provider registry.
	 *
	 * @var Occidg_Provider_Registry
	 */
	private $providers;

	/**
	 * Construct the workflow service.
	 *
	 * @param Occidg_Database          $database  Database service.
	 * @param Occidg_Metadata          $metadata  Metadata service.
	 * @param Occidg_Provider_Registry $providers Provider registry.
	 */
	public function __construct( $database, $metadata, $providers ) {
		$this->database  = $database;
		$this->metadata  = $metadata;
		$this->providers = $providers;
	}

	/**
	 * Process one queue item independently.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $context       Batch snapshot.
	 * @return array
	 */
	public function process_attachment( $attachment_id, $context = array() ) {
		$context = wp_parse_args(
			$context,
			array(
				'mode'                     => 'fill_missing',
				'provider'                 => get_option( 'occidg_provider', 'openai' ),
				'model'                    => '',
				'selected_fields'          => get_option( 'occidg_metadata_fields', array() ),
				'batch_id'                 => 0,
				'initiated_by'             => 0,
				'overwrite_confirmed'      => false,
				'caption_review_confirmed' => false,
				'current_retry_count'      => 0,
			)
		);
		$mode    = $this->sanitize_mode( $context['mode'] );
		if ( empty( $context['model'] ) ) {
			$context['model'] = get_option( 'gemini' === $context['provider'] ? 'occidg_gemini_model' : 'occidg_openai_model', 'gemini' === $context['provider'] ? 'gemini-1.5-flash' : 'gpt-4o-mini' );
		}
		$fields = Occidg_Metadata::normalize_fields( apply_filters( 'occ_idg_requested_fields', $context['selected_fields'], $attachment_id ) );

		if ( Occidg_Image_Support::is_svg_attachment( $attachment_id ) ) {
			$result = Occidg_Image_Support::get_svg_skipped_result();
			$this->record_batch_result( $context, $attachment_id, 'skipped', $result['message'] );
			return $result;
		}

		if ( ! wp_attachment_is_image( $attachment_id ) || empty( $fields ) ) {
			return $this->failure( __( 'Unsupported attachment or no metadata fields were selected.', 'occidg' ), false );
		}
		if ( get_post_meta( $attachment_id, '_occ_idg_decorative', true ) && ! empty( array_intersect( $fields, array( 'alt_text' ) ) ) ) {
			$fields = array_values( array_diff( $fields, array( 'alt_text' ) ) );
		}
		if ( empty( $fields ) ) {
			$result = array(
				'success' => true,
				'skipped' => true,
				'reason'  => 'decorative',
			);
			$this->record_batch_result( $context, $attachment_id, 'skipped' );
			return $result;
		}
		if ( ! apply_filters( 'occ_idg_should_process_attachment', true, $attachment_id ) ) {
			return array(
				'success' => true,
				'skipped' => true,
				'reason'  => 'filtered',
			);
		}

		$eligible = $this->eligible_fields( $attachment_id, $fields, $mode );
		if ( empty( $eligible ) ) {
			$result = array(
				'success' => true,
				'skipped' => true,
				'reason'  => 'no_eligible_fields',
			);
			$this->record_batch_result( $context, $attachment_id, 'skipped' );
			return $result;
		}

		if ( 'dry_run' === $mode ) {
			return array(
				'success'          => true,
				'dry_run'          => true,
				'eligible_fields'  => $eligible,
				'preserved_fields' => array_values( array_diff( $fields, $eligible ) ),
			);
		}

		if ( 'overwrite' === $mode && ( ! get_option( 'occ_idg_allow_overwrite', false ) || empty( $context['overwrite_confirmed'] ) ) ) {
			$this->record_batch_result( $context, $attachment_id, 'failed', __( 'Overwrite was not authorized.', 'occidg' ) );
			return $this->failure( __( 'Overwrite mode is disabled or was not confirmed immediately before this batch.', 'occidg' ), false );
		}
		$generation_locks = $this->acquire_generation_locks( $attachment_id, $eligible );
		if ( false === $generation_locks ) {
			$this->record_batch_result( $context, $attachment_id, 'retrying', __( 'Attachment generation lock is held.', 'occidg' ) );
			return $this->failure( __( 'This attachment is already being generated or reviewed.', 'occidg' ), true );
		}
		try {
			if ( ! empty( $context['batch_id'] ) ) {
				$this->database->start_batch_item( $context['batch_id'], $attachment_id );
			}

			$provider = $this->providers->get( $context['provider'] );
			if ( false === $provider || ! $provider->validate_credentials() ) {
				$this->record_batch_result( $context, $attachment_id, 'failed', __( 'Provider credentials are unavailable.', 'occidg' ) );
				return $this->failure( __( 'The selected provider is unavailable or has no configured API key.', 'occidg' ), false );
			}
			if ( ! $this->reserve_request_slot() ) {
				$retry_status = (int) $context['current_retry_count'] < (int) get_option( 'occ_idg_retry_count', 3 ) ? 'retrying' : 'failed';
				$this->record_batch_result( $context, $attachment_id, $retry_status, __( 'Daily request ceiling reached.', 'occidg' ) );
				return $this->failure( __( 'The configured daily provider request ceiling has been reached.', 'occidg' ), true );
			}

			$generation_context = apply_filters( 'occ_idg_generation_context', $this->build_generation_context( $attachment_id, $context ), $attachment_id );
			$response           = $provider->generate_metadata( $attachment_id, $eligible, $generation_context );
			if ( empty( $response['success'] ) || empty( $response['metadata'] ) ) {
				$error = isset( $response['error'] ) ? sanitize_text_field( $response['error'] ) : __( 'Provider generation failed.', 'occidg' );
				update_post_meta( $attachment_id, '_occ_idg_review_status', 'failed' );
				$can_retry = ! empty( $response['temporary'] ) && (int) $context['current_retry_count'] < (int) get_option( 'occ_idg_retry_count', 3 );
				$this->record_batch_result( $context, $attachment_id, $can_retry ? 'retrying' : 'failed', $error );
				return $this->failure( $error, ! empty( $response['temporary'] ) );
			}

			$metadata    = $response['metadata'];
			$confidences = isset( $response['confidence'] ) && is_array( $response['confidence'] ) ? $response['confidence'] : array();
			$reasons     = isset( $response['confidence_reason'] ) && is_array( $response['confidence_reason'] ) ? $response['confidence_reason'] : array();
			$results     = array(
				'success'   => true,
				'applied'   => array(),
				'suggested' => array(),
				'skipped'   => array(),
			);

			foreach ( $eligible as $field ) {
				if ( ! array_key_exists( $field, $metadata ) ) {
					$results['skipped'][ $field ] = 'missing_provider_value';
					continue;
				}
				$value = apply_filters( 'occ_idg_generated_value', $metadata[ $field ], $field, $attachment_id, $response );
				$value = $this->enforce_field_rules( $field, $value );
				if ( Occidg_Metadata::is_empty( $value ) ) {
					$results['skipped'][ $field ] = 'empty_provider_value';
					continue;
				}
				$confidence = $this->normalize_confidence( isset( $confidences[ $field ] ) ? $confidences[ $field ] : 'medium' );
				$reason     = isset( $reasons[ $field ] ) ? sanitize_text_field( $reasons[ $field ] ) : '';
				$prohibited = array_filter( array_map( 'trim', explode( ',', (string) get_option( 'occ_idg_prohibited_terminology', '' ) ) ) );
				foreach ( $prohibited as $term ) {
					if ( false !== stripos( $value, $term ) ) {
						$confidence = 'low';
						$reason     = __( 'The suggestion contains configured prohibited terminology.', 'occidg' );
						break;
					}
				}
				$must_review = 'suggestion' === $mode
					|| 'low' === $confidence
					|| ( 'caption' === $field && get_option( 'occ_idg_require_caption_review', true ) && empty( $context['caption_review_confirmed'] ) );

				if ( $must_review ) {
					$suggestion_id                  = $this->store_suggestion( $attachment_id, $field, $value, $confidence, $reason, $context, $generation_context );
					$results['suggested'][ $field ] = $suggestion_id;
					continue;
				}

				$update = $this->metadata->update_field(
					$attachment_id,
					$field,
					$value,
					array(
						'provider'        => $context['provider'],
						'model'           => $context['model'],
						'confidence'      => $confidence,
						'processing_mode' => $mode,
						'batch_id'        => $context['batch_id'],
						'initiated_by'    => $context['initiated_by'],
						'prompt_version'  => self::PROMPT_VERSION,
					)
				);
				if ( ! empty( $update['success'] ) ) {
					$results['applied'][ $field ] = $update;
				} else {
					$results['skipped'][ $field ] = isset( $update['error'] ) ? $update['error'] : 'update_failed';
				}
			}

			update_post_meta( $attachment_id, '_occ_idg_last_processed', current_time( 'mysql', true ) );
			if ( ! empty( $results['suggested'] ) ) {
				update_post_meta( $attachment_id, '_occ_idg_review_status', 'suggestion_ready' );
			}
			$this->record_batch_result( $context, $attachment_id, 'completed' );
			return $results;
		} finally {
			foreach ( $generation_locks as $generation_lock ) {
				delete_option( $generation_lock );
			}
		}
	}

	/**
	 * Analyze a set without changing data or making provider calls.
	 *
	 * @param array  $attachment_ids Attachment IDs.
	 * @param array  $fields         Requested metadata fields.
	 * @param string $mode           Processing mode to preview.
	 * @param string $provider_slug  Provider slug.
	 * @return array Dry-run report.
	 */
	public function dry_run( $attachment_ids, $fields, $mode = 'fill_missing', $provider_slug = '' ) {
		$fields        = Occidg_Metadata::normalize_fields( $fields );
		$provider_slug = $provider_slug ? sanitize_key( $provider_slug ) : sanitize_key( get_option( 'occidg_provider', 'openai' ) );
		$report        = array(
			'total_evaluated'     => 0,
			'eligible_images'     => 0,
			'skipped_images'      => 0,
			'fields_to_fill'      => array_fill_keys( $fields, 0 ),
			'fields_preserved'    => array_fill_keys( $fields, 0 ),
			'fields_to_overwrite' => array_fill_keys( $fields, 0 ),
			'unsupported_files'   => 0,
			'already_processed'   => 0,
			'decorative'          => 0,
			'potential_errors'    => array(),
		);
		$eligible_ids  = array();
		foreach ( array_unique( array_map( 'absint', (array) $attachment_ids ) ) as $attachment_id ) {
			++$report['total_evaluated'];
			if ( Occidg_Image_Support::is_svg_attachment( $attachment_id ) ) {
				++$report['unsupported_files'];
				++$report['skipped_images'];
				continue;
			}
			if ( ! wp_attachment_is_image( $attachment_id ) ) {
				++$report['unsupported_files'];
				++$report['skipped_images'];
				continue;
			}
			if ( get_post_meta( $attachment_id, '_occ_idg_last_processed', true ) ) {
				++$report['already_processed'];
			}
			if ( get_post_meta( $attachment_id, '_occ_idg_decorative', true ) ) {
				++$report['decorative'];
			}
			$eligible = $this->eligible_fields( $attachment_id, $fields, $mode );
			if ( empty( $eligible ) ) {
				++$report['skipped_images'];
				continue;
			}
			++$report['eligible_images'];
			$eligible_ids[] = $attachment_id;
			foreach ( $fields as $field ) {
				$is_empty = Occidg_Metadata::is_empty( $this->metadata->get_value( $attachment_id, $field ) );
				if ( in_array( $field, $eligible, true ) && $is_empty ) {
					++$report['fields_to_fill'][ $field ];
				} elseif ( in_array( $field, $eligible, true ) ) {
					++$report['fields_to_overwrite'][ $field ];
				} else {
					++$report['fields_preserved'][ $field ];
				}
			}
		}
		$provider = $this->providers->get( $provider_slug );
		if ( false === $provider ) {
			$report['potential_errors'][] = __( 'The selected provider is not registered.', 'occidg' );
		} elseif ( ! $provider->validate_credentials() ) {
			$report['potential_errors'][] = __( 'The selected provider does not have a configured API key.', 'occidg' );
		}
		$estimate = $provider ? $provider->estimate_cost( $eligible_ids, $fields ) : array(
			'estimated_requests' => count( $eligible_ids ),
			'estimated_cost'     => 0,
		);
		return array_merge( $report, $estimate );
	}

	/**
	 * Approve an individual field, optionally after editing.
	 *
	 * @param int         $suggestion_id Suggestion ID.
	 * @param string|null $approved_value Edited approved value, or null to accept as-is.
	 * @return array Update result.
	 */
	public function approve_suggestion( $suggestion_id, $approved_value = null ) {
		$row = $this->database->get_suggestion( $suggestion_id );
		if ( false === $row || ! in_array( $row['status'], array( 'pending', 'deferred' ), true ) ) {
			return $this->failure( __( 'Suggestion is unavailable for approval.', 'occidg' ), false );
		}
		$value  = null === $approved_value ? $row['suggested_value'] : $approved_value;
		$update = $this->metadata->update_field(
			(int) $row['attachment_id'],
			$row['field_name'],
			$value,
			array(
				'provider'        => $row['provider'],
				'model'           => $row['model'],
				'confidence'      => $row['confidence'],
				'processing_mode' => 'suggestion',
				'batch_id'        => $row['batch_id'],
				'approved_by'     => get_current_user_id(),
				'suggested_value' => $row['suggested_value'],
				'was_edited'      => $value !== $row['suggested_value'],
				'prompt_version'  => $row['prompt_version'],
			)
		);
		if ( empty( $update['success'] ) ) {
			return $update;
		}
		$this->database->update_suggestion(
			$suggestion_id,
			array(
				'approved_value' => $value,
				'status'         => 'approved',
				'reviewed_at'    => current_time( 'mysql', true ),
				'reviewed_by'    => get_current_user_id(),
			)
		);
		update_post_meta( $row['attachment_id'], '_occ_idg_last_reviewed', current_time( 'mysql', true ) );
		return $update;
	}

	/**
	 * Reject or defer an individual field.
	 *
	 * @param int    $suggestion_id Suggestion ID.
	 * @param string $status        New review status.
	 * @return bool Whether the record was updated.
	 */
	public function set_suggestion_status( $suggestion_id, $status ) {
		$status = in_array( $status, array( 'rejected', 'deferred', 'needs_manual_review' ), true ) ? $status : 'rejected';
		$row    = $this->database->get_suggestion( $suggestion_id );
		if ( false === $row ) {
			return false;
		}
		$result = $this->database->update_suggestion(
			$suggestion_id,
			array(
				'status'      => $status,
				'reviewed_at' => current_time( 'mysql', true ),
				'reviewed_by' => get_current_user_id(),
			)
		);
		update_post_meta( $row['attachment_id'], '_occ_idg_last_reviewed', current_time( 'mysql', true ) );
		update_post_meta( $row['attachment_id'], '_occ_idg_review_status', $status );
		return $result;
	}

	/**
	 * Select fields eligible for the requested mode.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param array  $fields        Requested fields.
	 * @param string $mode          Processing mode.
	 * @return array Eligible fields.
	 */
	private function eligible_fields( $attachment_id, $fields, $mode ) {
		if ( 'suggestion' === $mode || 'overwrite' === $mode ) {
			return $fields;
		}
		$eligible = array();
		foreach ( $fields as $field ) {
			if ( Occidg_Metadata::is_empty( $this->metadata->get_value( $attachment_id, $field ) ) ) {
				$eligible[] = $field;
			}
		}
		return $eligible;
	}

	/**
	 * Store one field-level suggestion.
	 *
	 * @param int    $attachment_id     Attachment ID.
	 * @param string $field             Field name.
	 * @param string $value             Suggested value.
	 * @param string $confidence        Confidence level.
	 * @param string $reason            Confidence rationale.
	 * @param array  $context           Batch context.
	 * @param array  $generation_context Provider context snapshot.
	 * @return int|false Suggestion ID or false.
	 */
	private function store_suggestion( $attachment_id, $field, $value, $confidence, $reason, $context, $generation_context ) {
		$suggestion_id = $this->database->insert_suggestion(
			array(
				'attachment_id'     => $attachment_id,
				'field_name'        => $field,
				'current_value'     => $this->metadata->get_value( $attachment_id, $field ),
				'suggested_value'   => $value,
				'confidence'        => $confidence,
				'confidence_reason' => $reason,
				'context_snapshot'  => wp_json_encode( $this->redact_context( $generation_context ) ),
				'provider'          => $context['provider'],
				'model'             => $context['model'],
				'prompt_version'    => self::PROMPT_VERSION,
				'status'            => 'pending',
				'batch_id'          => $context['batch_id'] ? absint( $context['batch_id'] ) : null,
			)
		);
		do_action( 'occ_idg_suggestion_generated', $attachment_id, $field, $suggestion_id );
		return $suggestion_id;
	}

	/**
	 * Build the minimal trusted provider context.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $context       Batch context.
	 * @return array Provider context.
	 */
	private function build_generation_context( $attachment_id, $context ) {
		$post          = get_post( $attachment_id );
		$parent        = $post && $post->post_parent ? get_post( $post->post_parent ) : null;
		$allow_draft   = (bool) get_option( 'occ_idg_allow_unpublished_context', false );
		$allow_private = (bool) get_option( 'occ_idg_allow_private_context', false );
		$parent_ok     = $parent && ( 'publish' === $parent->post_status || ( 'private' === $parent->post_status ? $allow_private : $allow_draft ) );
		return array(
			'provider'           => $context['provider'],
			'model'              => $context['model'],
			'language'           => isset( $context['language'] ) ? $context['language'] : get_option( 'occidg_language', 'en' ),
			'filename'           => wp_basename( get_attached_file( $attachment_id ) ),
			'current_metadata'   => $this->metadata->get_all( $attachment_id ),
			'parent_title'       => $parent_ok ? get_the_title( $parent ) : '',
			'parent_excerpt'     => $parent_ok ? wp_trim_words( wp_strip_all_tags( $parent->post_excerpt ), 55 ) : '',
			'parent_post_type'   => $parent_ok ? $parent->post_type : '',
			'site_name'          => get_bloginfo( 'name' ),
			'organization_name'  => get_option( 'occ_idg_organization_name', '' ),
			'site_description'   => get_option( 'occ_idg_site_description', '' ),
			'editorial_tone'     => get_option( 'occ_idg_editorial_tone', '' ),
			'editorial_guidance' => get_option( 'occ_idg_custom_prompt_instructions', '' ),
			'preferred_terms'    => get_option( 'occ_idg_preferred_terminology', '' ),
			'prohibited_terms'   => get_option( 'occ_idg_prohibited_terminology', '' ),
			'request_timeout'    => max( 5, min( 300, (int) get_option( 'occ_idg_request_timeout', 60 ) ) ),
			'privacy_notice'     => true,
		);
	}

	/**
	 * Remove credential-like values from stored context.
	 *
	 * @param array $context Provider context.
	 * @return array Redacted context.
	 */
	private function redact_context( $context ) {
		foreach ( array_keys( $context ) as $key ) {
			if ( false !== strpos( strtolower( $key ), 'key' ) || false !== strpos( strtolower( $key ), 'authorization' ) ) {
				unset( $context[ $key ] );
			}
		}
		return $context;
	}

	/**
	 * Normalize a workflow mode.
	 *
	 * @param string $mode Candidate mode.
	 * @return string Supported mode.
	 */
	private function sanitize_mode( $mode ) {
		$mode = sanitize_key( $mode );
		return in_array( $mode, array( 'fill_missing', 'suggestion', 'overwrite', 'dry_run' ), true ) ? $mode : 'fill_missing';
	}

	/**
	 * Normalize a provider confidence value.
	 *
	 * @param string $confidence Candidate confidence.
	 * @return string Supported confidence level.
	 */
	private function normalize_confidence( $confidence ) {
		$confidence = strtolower( sanitize_text_field( $confidence ) );
		return in_array( $confidence, array( 'high', 'medium', 'low' ), true ) ? $confidence : 'medium';
	}

	/**
	 * Apply deterministic field limits after provider generation.
	 *
	 * @param string $field Field name.
	 * @param string $value Generated value.
	 * @return string Normalized value.
	 */
	private function enforce_field_rules( $field, $value ) {
		$value = in_array( $field, array( 'caption', 'description' ), true ) ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
		if ( 'alt_text' === $field ) {
			$limit = max( 1, (int) get_option( 'occ_idg_max_alt_length', 160 ) );
			if ( function_exists( 'mb_substr' ) ) {
				$value = mb_substr( $value, 0, $limit );
			} else {
				$value = substr( $value, 0, $limit );
			}
		}
		if ( 'title' === $field ) {
			$value = preg_replace( '/\.(?:jpe?g|png|gif|webp|avif|heic|svg)$/i', '', $value );
		}
		return trim( $value );
	}

	/**
	 * Build a normalized failure result.
	 *
	 * @param string $message   Error message.
	 * @param bool   $temporary Whether the failure may be retried.
	 * @return array Failure result.
	 */
	private function failure( $message, $temporary ) {
		return array(
			'success'   => false,
			'temporary' => (bool) $temporary,
			'error'     => $message,
		);
	}

	/** Reserve one request against the UTC daily ceiling. */
	private function reserve_request_slot() {
		$ceiling = max( 0, (int) get_option( 'occ_idg_daily_request_ceiling', 500 ) );
		if ( 0 === $ceiling ) {
			return true;
		}
		$today = gmdate( 'Y-m-d' );
		$usage = get_option( 'occ_idg_daily_usage', array() );
		if ( ! is_array( $usage ) || ! isset( $usage['date'] ) || $today !== $usage['date'] ) {
			$usage = array(
				'date'     => $today,
				'requests' => 0,
			);
		}
		if ( (int) $usage['requests'] >= $ceiling ) {
			return false;
		}
		++$usage['requests'];
		update_option( 'occ_idg_daily_usage', $usage, false );
		return true;
	}

	/**
	 * Persist item-level status and refresh batch counters.
	 *
	 * @param array  $context       Batch context.
	 * @param int    $attachment_id Attachment ID.
	 * @param string $status        Item status.
	 * @param string $error         Optional error message.
	 */
	private function record_batch_result( $context, $attachment_id, $status, $error = '' ) {
		$batch_id = isset( $context['batch_id'] ) ? absint( $context['batch_id'] ) : 0;
		if ( ! $batch_id ) {
			return;
		}
		$this->database->update_batch_item(
			$batch_id,
			$attachment_id,
			array(
				'status'        => $status,
				'error_message' => sanitize_text_field( $error ),
				'completed_at'  => in_array( $status, array( 'completed', 'failed', 'skipped' ), true ) ? current_time( 'mysql', true ) : null,
			)
		);
		$this->database->sync_batch_counts( $batch_id );
	}

	/**
	 * Acquire expiring locks for every attachment-and-field pair.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $fields        Field names.
	 * @return array|false Lock option keys, or false when any lock is held.
	 */
	private function acquire_generation_locks( $attachment_id, $fields ) {
		$locks   = array();
		$expires = time() + max( 60, (int) get_option( 'occ_idg_lock_ttl', 300 ) );
		foreach ( array_unique( $fields ) as $field ) {
			$key = 'occ_idg_generation_lock_' . md5( absint( $attachment_id ) . ':' . sanitize_key( $field ) );
			if ( ! add_option( $key, $expires, '', false ) ) {
				if ( (int) get_option( $key, 0 ) < time() ) {
					delete_option( $key );
				}
				if ( ! add_option( $key, $expires, '', false ) ) {
					foreach ( $locks as $acquired_lock ) {
						delete_option( $acquired_lock );
					}
					return false;
				}
			}
			$locks[] = $key;
		}
		return $locks;
	}
}
