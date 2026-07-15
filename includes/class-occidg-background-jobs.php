<?php
/**
 * Background metadata job persistence for OCCIDG.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stores and normalizes queued metadata generation jobs.
 */
class Occidg_Background_Jobs {

	/**
	 * Option name used to persist background jobs.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	const JOBS_OPTION = 'occidg_background_jobs';

	/**
	 * Maximum number of terminal jobs to retain.
	 *
	 * @since 1.2.0
	 * @var int
	 */
	const MAX_RETAINED_TERMINAL_JOBS = 10;

	/**
	 * Maximum number of recent failure rows to retain per job.
	 *
	 * @since 1.2.0
	 * @var int
	 */
	const MAX_RECENT_FAILURES = 10;

	/**
	 * Return all stored jobs, ordered by updated timestamp descending.
	 *
	 * @since 1.2.0
	 * @return array
	 */
	public function get_jobs() {
		$jobs = get_option( self::JOBS_OPTION, array() );
		if ( ! is_array( $jobs ) ) {
			return array();
		}

		$normalized_jobs = array();

		foreach ( $jobs as $job ) {
			$normalized_job = $this->normalize_job( $job );
			if ( false === $normalized_job ) {
				continue;
			}

			$normalized_jobs[] = $normalized_job;
		}

		usort(
			$normalized_jobs,
			static function ( $left, $right ) {
				return strcmp( (string) $right['updated_at'], (string) $left['updated_at'] );
			}
		);

		return $normalized_jobs;
	}

	/**
	 * Return a single job by ID.
	 *
	 * @since 1.2.0
	 * @param string $job_id Job ID.
	 * @return array|false
	 */
	public function get_job( $job_id ) {
		$job_id = $this->sanitize_job_id( $job_id );
		if ( '' === $job_id ) {
			return false;
		}

		foreach ( $this->get_jobs() as $job ) {
			if ( $job_id === $job['id'] ) {
				return $job;
			}
		}

		return false;
	}

	/**
	 * Return the newest job, optionally filtered by statuses.
	 *
	 * @since 1.2.0
	 * @param array $statuses Allowed statuses.
	 * @return array|false
	 */
	public function get_latest_job( $statuses = array() ) {
		$statuses = is_array( $statuses ) ? array_values( array_filter( $statuses, 'is_string' ) ) : array();

		foreach ( $this->get_jobs() as $job ) {
			if ( empty( $statuses ) || in_array( $job['status'], $statuses, true ) ) {
				return $job;
			}
		}

		return false;
	}

	/**
	 * Create and persist a new background job.
	 *
	 * @since 1.2.0
	 * @param array $args Job configuration.
	 * @return array|false
	 */
	public function create_job( $args ) {
		$image_ids = isset( $args['image_ids'] ) ? $this->normalize_image_ids( $args['image_ids'] ) : array();
		if ( empty( $image_ids ) ) {
			return false;
		}

		$timestamp = $this->get_current_timestamp();
		$job       = $this->normalize_job(
			array(
				'id'                  => $this->generate_job_id(),
				'label'               => isset( $args['label'] ) ? $args['label'] : __( 'Bulk metadata generation', 'occidg' ),
				'status'              => 'queued',
				'created_at'          => $timestamp,
				'updated_at'          => $timestamp,
				'completed_at'        => '',
				'provider'            => isset( $args['provider'] ) ? $args['provider'] : '',
				'provider_label'      => isset( $args['provider_label'] ) ? $args['provider_label'] : '',
				'model'               => isset( $args['model'] ) ? $args['model'] : '',
				'language'            => isset( $args['language'] ) ? $args['language'] : '',
				'selected_fields'     => isset( $args['selected_fields'] ) ? $args['selected_fields'] : array(),
				'override_metadata'   => ! empty( $args['override_metadata'] ),
				'mode'                => isset( $args['mode'] ) ? $args['mode'] : 'fill_missing',
				'batch_id'            => isset( $args['batch_id'] ) ? $args['batch_id'] : 0,
				'initiated_by'        => isset( $args['initiated_by'] ) ? $args['initiated_by'] : 0,
				'overwrite_confirmed' => ! empty( $args['overwrite_confirmed'] ),
				'retried_from_job_id' => isset( $args['retried_from_job_id'] ) ? $args['retried_from_job_id'] : '',
				'image_ids'           => $image_ids,
				'next_index'          => 0,
				'total'               => count( $image_ids ),
				'processed'           => 0,
				'succeeded'           => 0,
				'failed'              => 0,
				'skipped'             => 0,
				'failed_image_ids'    => array(),
				'recent_failures'     => array(),
				'last_error'          => '',
				'current_retry_count' => 0,
			)
		);

		if ( false === $job ) {
			return false;
		}

		$jobs   = $this->get_jobs();
		$jobs[] = $job;

		$this->persist_jobs( $jobs );

		return $this->get_job( $job['id'] );
	}

	/**
	 * Return the image IDs that should be retried for a finished job.
	 *
	 * @since 1.2.0
	 * @param string $job_id Job ID.
	 * @return array
	 */
	public function get_retry_image_ids( $job_id ) {
		$job = $this->get_job( $job_id );
		if ( false === $job || ! $this->is_retryable_status( $job['status'] ) ) {
			return array();
		}

		$remaining_image_ids = array_slice( $job['image_ids'], $job['next_index'] );
		$retry_image_ids     = array_merge( $job['failed_image_ids'], $remaining_image_ids );

		return array_values( array_unique( $retry_image_ids ) );
	}

	/**
	 * Create a new retry job from a finished job.
	 *
	 * @since 1.2.0
	 * @param string $job_id    Source job ID.
	 * @param array  $overrides Optional retry overrides.
	 * @return array|false
	 */
	public function create_retry_job( $job_id, $overrides = array() ) {
		$job = $this->get_job( $job_id );
		if ( false === $job || ! $this->is_retryable_status( $job['status'] ) ) {
			return false;
		}

		$retry_image_ids = $this->get_retry_image_ids( $job_id );
		if ( empty( $retry_image_ids ) ) {
			return false;
		}

		$overrides = is_array( $overrides ) ? $overrides : array();

		return $this->create_job(
			array_merge(
				array(
					/* translators: %s: original background job label. */
					'label'               => sprintf( __( 'Retry: %s', 'occidg' ), $job['label'] ),
					'provider'            => $job['provider'],
					'provider_label'      => $job['provider_label'],
					'model'               => $job['model'],
					'language'            => $job['language'],
					'selected_fields'     => $job['selected_fields'],
					'override_metadata'   => $job['override_metadata'],
					'mode'                => $job['mode'],
					'batch_id'            => $job['batch_id'],
					'initiated_by'        => $job['initiated_by'],
					'overwrite_confirmed' => $job['overwrite_confirmed'],
					'retried_from_job_id' => $job['id'],
					'image_ids'           => $retry_image_ids,
				),
				$overrides
			)
		);
	}

	/**
	 * Update a stored job.
	 *
	 * @since 1.2.0
	 * @param string $job_id  Job ID.
	 * @param array  $updates Updated fields.
	 * @return array|false
	 */
	public function update_job( $job_id, $updates ) {
		$job_id = $this->sanitize_job_id( $job_id );
		if ( '' === $job_id || ! is_array( $updates ) ) {
			return false;
		}

		$jobs    = $this->get_jobs();
		$updated = false;

		foreach ( $jobs as $index => $job ) {
			if ( $job_id !== $job['id'] ) {
				continue;
			}

			$updated_job = $this->normalize_job( array_merge( $job, $updates ) );
			if ( false === $updated_job ) {
				return false;
			}

			$updated_job['updated_at'] = isset( $updates['updated_at'] ) && is_string( $updates['updated_at'] )
				? $updates['updated_at']
				: $this->get_current_timestamp();

			if ( $this->is_terminal_status( $updated_job['status'] ) && '' === $updated_job['completed_at'] ) {
				$updated_job['completed_at'] = $updated_job['updated_at'];
			}

			if ( ! $this->is_terminal_status( $updated_job['status'] ) ) {
				$updated_job['completed_at'] = '';
			}

			$jobs[ $index ] = $updated_job;
			$updated        = $updated_job;
			break;
		}

		if ( false === $updated ) {
			return false;
		}

		$this->persist_jobs( $jobs );

		return $this->get_job( $job_id );
	}

	/**
	 * Record a batch of per-image outcomes for a job.
	 *
	 * @since 1.2.0
	 * @param string $job_id  Job ID.
	 * @param array  $results Batch results.
	 * @return array|false
	 */
	public function record_job_progress( $job_id, $results ) {
		$job = $this->get_job( $job_id );
		if ( false === $job || ! is_array( $results ) ) {
			return false;
		}

		$processed  = $job['processed'] + $this->normalize_non_negative_int( isset( $results['processed'] ) ? $results['processed'] : 0 );
		$succeeded  = $job['succeeded'] + $this->normalize_non_negative_int( isset( $results['succeeded'] ) ? $results['succeeded'] : 0 );
		$failed     = $job['failed'] + $this->normalize_non_negative_int( isset( $results['failed'] ) ? $results['failed'] : 0 );
		$skipped    = $job['skipped'] + $this->normalize_non_negative_int( isset( $results['skipped'] ) ? $results['skipped'] : 0 );
		$next_index = $this->normalize_non_negative_int(
			isset( $results['next_index'] ) ? $results['next_index'] : $processed
		);

		$failed_image_ids = array_merge(
			$job['failed_image_ids'],
			$this->normalize_image_ids( isset( $results['failed_image_ids'] ) ? $results['failed_image_ids'] : array() )
		);
		$recent_failures  = array_merge(
			$job['recent_failures'],
			$this->normalize_recent_failures( isset( $results['recent_failures'] ) ? $results['recent_failures'] : array() )
		);

		$recent_failures = array_slice( $recent_failures, -1 * self::MAX_RECENT_FAILURES );
		$status          = isset( $results['status'] ) ? $this->sanitize_status( $results['status'] ) : $job['status'];
		$last_error      = isset( $results['last_error'] ) ? sanitize_text_field( $results['last_error'] ) : $job['last_error'];

		if ( $processed >= $job['total'] && 'failed' !== $status && 'cancelled' !== $status ) {
			$status = $failed > 0 ? 'completed_with_errors' : 'completed';
		}

		return $this->update_job(
			$job_id,
			array(
				'processed'        => min( $processed, $job['total'] ),
				'succeeded'        => min( $succeeded, $job['total'] ),
				'failed'           => min( $failed, $job['total'] ),
				'skipped'          => min( $skipped, $job['total'] ),
				'next_index'       => min( $next_index, $job['total'] ),
				'failed_image_ids' => array_values( array_unique( $failed_image_ids ) ),
				'recent_failures'  => $recent_failures,
				'last_error'       => $last_error,
				'status'           => $status,
			)
		);
	}

	/**
	 * Prune stored jobs and persist the retained set.
	 *
	 * @since 1.2.0
	 * @return array
	 */
	public function prune_jobs() {
		$pruned_jobs = $this->get_pruned_jobs( $this->get_jobs() );

		$this->persist_jobs( $pruned_jobs );

		return $pruned_jobs;
	}

	/**
	 * Return the pruned job set without persisting it.
	 *
	 * @since 1.2.0
	 * @param array $jobs Jobs to prune.
	 * @return array
	 */
	public function get_pruned_jobs( $jobs ) {
		if ( ! is_array( $jobs ) ) {
			return array();
		}

		$active_jobs   = array();
		$terminal_jobs = array();

		foreach ( $jobs as $job ) {
			$normalized_job = $this->normalize_job( $job );
			if ( false === $normalized_job ) {
				continue;
			}

			if ( $this->is_terminal_status( $normalized_job['status'] ) ) {
				$terminal_jobs[] = $normalized_job;
				continue;
			}

			$active_jobs[] = $normalized_job;
		}

		usort(
			$terminal_jobs,
			static function ( $left, $right ) {
				return strcmp( (string) $right['updated_at'], (string) $left['updated_at'] );
			}
		);

		$terminal_jobs = array_slice( $terminal_jobs, 0, self::MAX_RETAINED_TERMINAL_JOBS );
		$jobs          = array_merge( $active_jobs, $terminal_jobs );

		usort(
			$jobs,
			static function ( $left, $right ) {
				return strcmp( (string) $right['updated_at'], (string) $left['updated_at'] );
			}
		);

		return $jobs;
	}

	/**
	 * Persist normalized jobs to the options table.
	 *
	 * @since 1.2.0
	 * @param array $jobs Jobs to persist.
	 * @return void
	 */
	private function persist_jobs( $jobs ) {
		update_option( self::JOBS_OPTION, $this->get_pruned_jobs( $jobs ) );
	}

	/**
	 * Normalize a stored job payload.
	 *
	 * @since 1.2.0
	 * @param mixed $job Raw job payload.
	 * @return array|false
	 */
	private function normalize_job( $job ) {
		if ( ! is_array( $job ) ) {
			return false;
		}

		$job_id = isset( $job['id'] ) ? $this->sanitize_job_id( $job['id'] ) : '';
		if ( '' === $job_id ) {
			return false;
		}

		$image_ids = $this->normalize_image_ids( isset( $job['image_ids'] ) ? $job['image_ids'] : array() );
		$total     = isset( $job['total'] ) ? $this->normalize_non_negative_int( $job['total'] ) : count( $image_ids );

		return array(
			'id'                  => $job_id,
			'label'               => isset( $job['label'] ) ? sanitize_text_field( $job['label'] ) : __( 'Bulk metadata generation', 'occidg' ),
			'status'              => $this->sanitize_status( isset( $job['status'] ) ? $job['status'] : 'queued' ),
			'created_at'          => isset( $job['created_at'] ) && is_string( $job['created_at'] ) ? $job['created_at'] : $this->get_current_timestamp(),
			'updated_at'          => isset( $job['updated_at'] ) && is_string( $job['updated_at'] ) ? $job['updated_at'] : $this->get_current_timestamp(),
			'completed_at'        => isset( $job['completed_at'] ) && is_string( $job['completed_at'] ) ? $job['completed_at'] : '',
			'provider'            => isset( $job['provider'] ) ? sanitize_text_field( $job['provider'] ) : '',
			'provider_label'      => isset( $job['provider_label'] ) ? sanitize_text_field( $job['provider_label'] ) : '',
			'model'               => isset( $job['model'] ) ? sanitize_text_field( $job['model'] ) : '',
			'language'            => isset( $job['language'] ) ? sanitize_text_field( $job['language'] ) : '',
			'selected_fields'     => $this->normalize_selected_fields( isset( $job['selected_fields'] ) ? $job['selected_fields'] : array() ),
			'override_metadata'   => ! empty( $job['override_metadata'] ),
			'mode'                => $this->sanitize_mode( isset( $job['mode'] ) ? $job['mode'] : ( ! empty( $job['override_metadata'] ) ? 'overwrite' : 'fill_missing' ) ),
			'batch_id'            => isset( $job['batch_id'] ) ? $this->normalize_non_negative_int( $job['batch_id'] ) : 0,
			'initiated_by'        => isset( $job['initiated_by'] ) ? $this->normalize_non_negative_int( $job['initiated_by'] ) : 0,
			'overwrite_confirmed' => ! empty( $job['overwrite_confirmed'] ),
			'retried_from_job_id' => isset( $job['retried_from_job_id'] ) ? $this->sanitize_job_id( $job['retried_from_job_id'] ) : '',
			'image_ids'           => $image_ids,
			'next_index'          => min(
				isset( $job['next_index'] ) ? $this->normalize_non_negative_int( $job['next_index'] ) : 0,
				$total
			),
			'total'               => $total,
			'processed'           => min(
				isset( $job['processed'] ) ? $this->normalize_non_negative_int( $job['processed'] ) : 0,
				$total
			),
			'succeeded'           => min(
				isset( $job['succeeded'] ) ? $this->normalize_non_negative_int( $job['succeeded'] ) : 0,
				$total
			),
			'failed'              => min(
				isset( $job['failed'] ) ? $this->normalize_non_negative_int( $job['failed'] ) : 0,
				$total
			),
			'skipped'             => min(
				isset( $job['skipped'] ) ? $this->normalize_non_negative_int( $job['skipped'] ) : 0,
				$total
			),
			'failed_image_ids'    => $this->normalize_image_ids( isset( $job['failed_image_ids'] ) ? $job['failed_image_ids'] : array() ),
			'recent_failures'     => $this->normalize_recent_failures( isset( $job['recent_failures'] ) ? $job['recent_failures'] : array() ),
			'last_error'          => isset( $job['last_error'] ) ? sanitize_text_field( $job['last_error'] ) : '',
			'current_retry_count' => isset( $job['current_retry_count'] ) ? $this->normalize_non_negative_int( $job['current_retry_count'] ) : 0,
		);
	}

	/**
	 * Normalize selected metadata fields.
	 *
	 * @since 1.2.0
	 * @param mixed $selected_fields Raw field selection.
	 * @return array
	 */
	private function normalize_selected_fields( $selected_fields ) {
		$normalized = array(
			'title'       => '0',
			'description' => '0',
			'alt_text'    => '0',
			'caption'     => '0',
		);

		if ( ! is_array( $selected_fields ) ) {
			return $normalized;
		}

		foreach ( array_keys( $normalized ) as $field_key ) {
			if ( isset( $selected_fields[ $field_key ] ) && '1' === (string) $selected_fields[ $field_key ] ) {
				$normalized[ $field_key ] = '1';
			}
		}

		return $normalized;
	}

	/**
	 * Normalize a list of image IDs.
	 *
	 * @since 1.2.0
	 * @param mixed $image_ids Raw image IDs.
	 * @return array
	 */
	private function normalize_image_ids( $image_ids ) {
		if ( ! is_array( $image_ids ) ) {
			return array();
		}

		$normalized_ids = array();

		foreach ( $image_ids as $image_id ) {
			$image_id = $this->normalize_non_negative_int( $image_id );
			if ( $image_id < 1 ) {
				continue;
			}

			$normalized_ids[] = $image_id;
		}

		return array_values( array_unique( $normalized_ids ) );
	}

	/**
	 * Normalize recent failure rows.
	 *
	 * @since 1.2.0
	 * @param mixed $recent_failures Raw failure rows.
	 * @return array
	 */
	private function normalize_recent_failures( $recent_failures ) {
		if ( ! is_array( $recent_failures ) ) {
			return array();
		}

		$normalized_failures = array();

		foreach ( $recent_failures as $failure ) {
			if ( ! is_array( $failure ) ) {
				continue;
			}

			$image_id = isset( $failure['image_id'] ) ? $this->normalize_non_negative_int( $failure['image_id'] ) : 0;
			$message  = isset( $failure['message'] ) ? sanitize_text_field( $failure['message'] ) : '';

			if ( $image_id < 1 || '' === $message ) {
				continue;
			}

			$normalized_failures[] = array(
				'image_id' => $image_id,
				'message'  => $message,
			);
		}

		return $normalized_failures;
	}

	/**
	 * Sanitize a job status value.
	 *
	 * @since 1.2.0
	 * @param string $status Raw status.
	 * @return string
	 */
	private function sanitize_status( $status ) {
		$allowed_statuses = array(
			'queued',
			'running',
			'paused',
			'completed',
			'completed_with_errors',
			'cancelled',
			'failed',
		);

		$status = is_scalar( $status ) ? trim( (string) $status ) : '';

		return in_array( $status, $allowed_statuses, true ) ? $status : 'queued';
	}

	/**
	 * Sanitize a processing mode.
	 *
	 * @param string $mode Candidate processing mode.
	 * @return string Supported processing mode.
	 */
	private function sanitize_mode( $mode ) {
		$mode = is_scalar( $mode ) ? strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $mode ) ) : '';
		return in_array( $mode, array( 'fill_missing', 'suggestion', 'overwrite', 'dry_run' ), true ) ? $mode : 'fill_missing';
	}

	/**
	 * Determine whether a status is terminal.
	 *
	 * @since 1.2.0
	 * @param string $status Job status.
	 * @return bool
	 */
	private function is_terminal_status( $status ) {
		return in_array( $status, array( 'completed', 'completed_with_errors', 'cancelled', 'failed' ), true );
	}

	/**
	 * Determine whether a terminal job can be retried.
	 *
	 * @since 1.2.0
	 * @param string $status Job status.
	 * @return bool
	 */
	private function is_retryable_status( $status ) {
		return in_array( $status, array( 'completed_with_errors', 'cancelled', 'failed' ), true );
	}

	/**
	 * Return a timestamp string for stored job rows.
	 *
	 * @since 1.2.0
	 * @return string
	 */
	private function get_current_timestamp() {
		return gmdate( 'c' );
	}

	/**
	 * Generate a unique job ID.
	 *
	 * @since 1.2.0
	 * @return string
	 */
	private function generate_job_id() {
		return 'occidg_job_' . str_replace( '.', '', uniqid( '', true ) );
	}

	/**
	 * Sanitize a job ID.
	 *
	 * @since 1.2.0
	 * @param string $job_id Raw job ID.
	 * @return string
	 */
	private function sanitize_job_id( $job_id ) {
		$job_id = is_scalar( $job_id ) ? trim( (string) $job_id ) : '';
		if ( '' === $job_id ) {
			return '';
		}

		return preg_replace( '/[^A-Za-z0-9_\-]/', '', $job_id );
	}

	/**
	 * Normalize a value to a non-negative integer.
	 *
	 * @since 1.2.0
	 * @param mixed $value Raw value.
	 * @return int
	 */
	private function normalize_non_negative_int( $value ) {
		$value = is_numeric( $value ) ? (int) $value : 0;

		return max( 0, $value );
	}
}
