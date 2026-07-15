<?php
/**
 * Safe attachment metadata reads, writes, locks, and rollback.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides one auditable path for every plugin-initiated metadata change.
 */
class Occidg_Metadata {

	/** Supported fields and their WordPress storage locations. */
	const FIELDS = array(
		'alt_text'    => '_wp_attachment_image_alt',
		'title'       => 'post_title',
		'caption'     => 'post_excerpt',
		'description' => 'post_content',
	);

	/**
	 * Database gateway.
	 *
	 * @var Occidg_Database
	 */
	private $database;

	/**
	 * Constructor.
	 *
	 * @param Occidg_Database|null $database Database gateway.
	 */
	public function __construct( $database = null ) {
		$this->database = $database instanceof Occidg_Database ? $database : new Occidg_Database();
	}

	/**
	 * Normalize enabled fields from lists or settings maps.
	 *
	 * @param array $fields Field list or settings map.
	 * @return array Supported enabled fields.
	 */
	public static function normalize_fields( $fields ) {
		$normalized = array();
		foreach ( (array) $fields as $key => $value ) {
			$field = is_int( $key ) ? $value : $key;
			if ( isset( self::FIELDS[ $field ] ) && ( is_int( $key ) || '1' === (string) $value || true === $value ) ) {
				$normalized[] = $field;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Determine whether a value is effectively empty.
	 *
	 * @param mixed $value Candidate value.
	 * @return bool Whether the value is empty.
	 */
	public static function is_empty( $value ) {
		$value      = html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$normalized = preg_replace( '/[\s\p{Z}\x{200B}\x{FEFF}]+/u', '', $value );

		return '' === ( null === $normalized ? trim( $value ) : $normalized );
	}

	/**
	 * Get one current field value.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $field         Field name.
	 * @return string Current value.
	 */
	public function get_value( $attachment_id, $field ) {
		if ( ! isset( self::FIELDS[ $field ] ) ) {
			return '';
		}

		if ( 'alt_text' === $field ) {
			return (string) get_post_meta( $attachment_id, self::FIELDS[ $field ], true );
		}

		return (string) get_post_field( self::FIELDS[ $field ], $attachment_id, 'raw' );
	}

	/**
	 * Return all supported current values.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Current values keyed by field.
	 */
	public function get_all( $attachment_id ) {
		$values = array();
		foreach ( array_keys( self::FIELDS ) as $field ) {
			$values[ $field ] = $this->get_value( $attachment_id, $field );
		}

		return $values;
	}

	/**
	 * Safely update one field and create an immutable history event.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $field         Field key.
	 * @param string $new_value     New value.
	 * @param array  $context       Audit context.
	 * @return array
	 */
	public function update_field( $attachment_id, $field, $new_value, $context = array() ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || ! isset( self::FIELDS[ $field ] ) || 'attachment' !== get_post_type( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid image attachment or metadata field.', 'occidg' ),
			);
		}

		if ( ! $this->acquire_lock( $attachment_id, $field ) ) {
			return array(
				'success'   => false,
				'temporary' => true,
				'error'     => __( 'This image field is already being processed.', 'occidg' ),
			);
		}

		try {
			$old_value       = $this->get_value( $attachment_id, $field );
			$new_value       = is_scalar( $new_value ) ? (string) $new_value : '';
			$new_value       = in_array( $field, array( 'description', 'caption' ), true ) ? sanitize_textarea_field( $new_value ) : sanitize_text_field( $new_value );
			$suggested_value = isset( $context['suggested_value'] ) && is_scalar( $context['suggested_value'] ) ? (string) $context['suggested_value'] : $new_value;
			$suggested_value = in_array( $field, array( 'description', 'caption' ), true ) ? sanitize_textarea_field( $suggested_value ) : sanitize_text_field( $suggested_value );
			if ( $old_value === $new_value ) {
				return array(
					'success'   => true,
					'changed'   => false,
					'old_value' => $old_value,
					'new_value' => $new_value,
				);
			}

			if ( 'alt_text' === $field ) {
				$result = update_post_meta( $attachment_id, self::FIELDS[ $field ], $new_value );
			} else {
				$result = wp_update_post(
					array(
						'ID'                   => $attachment_id,
						self::FIELDS[ $field ] => $new_value,
					),
					true
				);
			}

			if ( is_wp_error( $result ) ) {
				return array(
					'success' => false,
					'error'   => $result->get_error_message(),
				);
			}

			$context    = wp_parse_args(
				$context,
				array(
					'action_type'     => 'update',
					'processing_mode' => 'fill_missing',
				)
			);
			$history_id = $this->database->insert_history(
				array(
					'attachment_id'        => $attachment_id,
					'field_name'           => $field,
					'old_value'            => $old_value,
					'new_value'            => $new_value,
					'suggested_value'      => $suggested_value,
					'final_approved_value' => $new_value,
					'provider'             => isset( $context['provider'] ) ? sanitize_key( $context['provider'] ) : '',
					'model'                => isset( $context['model'] ) ? sanitize_text_field( $context['model'] ) : '',
					'confidence'           => isset( $context['confidence'] ) ? sanitize_key( $context['confidence'] ) : '',
					'action_type'          => sanitize_key( $context['action_type'] ),
					'processing_mode'      => sanitize_key( $context['processing_mode'] ),
					'batch_id'             => isset( $context['batch_id'] ) ? absint( $context['batch_id'] ) : null,
					'initiated_by'         => isset( $context['initiated_by'] ) ? absint( $context['initiated_by'] ) : get_current_user_id(),
					'approved_by'          => isset( $context['approved_by'] ) ? absint( $context['approved_by'] ) : null,
					'was_edited'           => ! empty( $context['was_edited'] ) ? 1 : 0,
					'prompt_version'       => isset( $context['prompt_version'] ) ? sanitize_text_field( $context['prompt_version'] ) : '',
					'restored_from_id'     => isset( $context['restored_from_id'] ) ? absint( $context['restored_from_id'] ) : null,
				)
			);

			update_post_meta( $attachment_id, '_occ_idg_last_processed', current_time( 'mysql', true ) );
			update_post_meta( $attachment_id, '_occ_idg_review_status', 'approved' );
			if ( ! empty( $context['provider'] ) ) {
				update_post_meta( $attachment_id, '_occ_idg_last_provider', sanitize_key( $context['provider'] ) );
			}
			if ( ! empty( $context['model'] ) ) {
				update_post_meta( $attachment_id, '_occ_idg_last_model', sanitize_text_field( $context['model'] ) );
			}

			do_action( 'occ_idg_metadata_updated', $attachment_id, $field, $old_value, $new_value );
			// Backward-compatible hook spelling used in early project notes.
			do_action( 'occidg_metadata_updated', $attachment_id, $field, $old_value, $new_value );

			return array(
				'success'    => true,
				'changed'    => true,
				'history_id' => $history_id,
				'old_value'  => $old_value,
				'new_value'  => $new_value,
			);
		} finally {
			$this->release_lock( $attachment_id, $field );
		}
	}

	/**
	 * Restore a specific history event without erasing history.
	 *
	 * @param int  $history_id History event ID.
	 * @param bool $force      Whether to overwrite a newer edit.
	 * @return array
	 */
	public function restore_history( $history_id, $force = false ) {
		$row = $this->database->get_history( $history_id );
		if ( false === $row ) {
			return array(
				'success' => false,
				'error'   => __( 'History event not found.', 'occidg' ),
			);
		}

		$current = $this->get_value( (int) $row['attachment_id'], $row['field_name'] );
		if ( ! $force && $current !== $row['new_value'] ) {
			return array(
				'success'        => false,
				'conflict'       => true,
				'current_value'  => $current,
				'expected_value' => $row['new_value'],
				'restore_value'  => $row['old_value'],
				'error'          => __( 'The current value has changed since this plugin update. Confirm before replacing the newer edit.', 'occidg' ),
			);
		}

		return $this->update_field(
			(int) $row['attachment_id'],
			$row['field_name'],
			$row['old_value'],
			array(
				'action_type'      => 'restore',
				'processing_mode'  => 'restore',
				'batch_id'         => $row['batch_id'],
				'restored_from_id' => (int) $row['id'],
				'approved_by'      => get_current_user_id(),
			)
		);
	}

	/**
	 * Restore each latest applicable event from a batch in reverse order.
	 *
	 * @param int  $batch_id Batch ID.
	 * @param bool $force    Whether to replace newer values.
	 * @return array Restore counts.
	 */
	public function restore_batch( $batch_id, $force = false ) {
		$rows    = $this->database->get_history_rows(
			array(
				'batch_id' => absint( $batch_id ),
				'limit'    => 10000,
			)
		);
		$results = array(
			'restored'  => 0,
			'conflicts' => 0,
			'failed'    => 0,
		);
		foreach ( $rows as $row ) {
			if ( 'restore' === $row['action_type'] ) {
				continue;
			}
			$result = $this->restore_history( $row['id'], $force );
			if ( ! empty( $result['success'] ) ) {
				++$results['restored'];
			} elseif ( ! empty( $result['conflict'] ) ) {
				++$results['conflicts'];
			} else {
				++$results['failed'];
			}
		}

		return $results;
	}

	/**
	 * Mark or unmark an image as intentionally decorative.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param bool   $decorative    Decorative state.
	 * @param string $reason        Optional human rationale.
	 * @return bool Whether the state was saved.
	 */
	public function set_decorative( $attachment_id, $decorative, $reason = '' ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}

		if ( $decorative ) {
			update_post_meta( $attachment_id, '_occ_idg_decorative', '1' );
			update_post_meta( $attachment_id, '_occ_idg_decorative_reason', sanitize_textarea_field( $reason ) );
			update_post_meta( $attachment_id, '_occ_idg_decorative_by', get_current_user_id() );
			update_post_meta( $attachment_id, '_occ_idg_decorative_at', current_time( 'mysql', true ) );
			update_post_meta( $attachment_id, '_occ_idg_review_status', 'intentionally_decorative' );
			return true;
		}

		foreach ( array( '_occ_idg_decorative', '_occ_idg_decorative_reason', '_occ_idg_decorative_by', '_occ_idg_decorative_at' ) as $key ) {
			delete_post_meta( $attachment_id, $key );
		}
		update_post_meta( $attachment_id, '_occ_idg_review_status', 'not_reviewed' );
		return true;
	}

	/**
	 * Acquire an expiring field-level lock using atomic option creation.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $field         Field name.
	 * @return bool Whether the lock was acquired.
	 */
	private function acquire_lock( $attachment_id, $field ) {
		$key     = 'occ_idg_lock_' . md5( $attachment_id . ':' . $field );
		$expires = time() + max( 60, absint( get_option( 'occ_idg_lock_ttl', 300 ) ) );
		if ( add_option( $key, $expires, '', false ) ) {
			return true;
		}
		if ( (int) get_option( $key, 0 ) < time() ) {
			delete_option( $key );
			return add_option( $key, $expires, '', false );
		}
		return false;
	}

	/**
	 * Release a field-level lock.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $field         Field name.
	 */
	private function release_lock( $attachment_id, $field ) {
		delete_option( 'occ_idg_lock_' . md5( $attachment_id . ':' . $field ) );
	}
}
