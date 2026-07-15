<?php
/**
 * Background metadata job worker for OCCIDG.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

/**
 * Processes queued metadata jobs in small background batches.
 */
class Occidg_Background_Worker {

	/**
	 * Cron hook used to process queued jobs.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	const CRON_HOOK = 'occidg_process_background_job';

	/**
	 * Lock key used to prevent overlapping runs.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	const LOCK_KEY = 'occidg_background_job_lock';

	/**
	 * Default batch size for each worker pass.
	 *
	 * @since 1.2.0
	 * @var int
	 */
	const DEFAULT_BATCH_SIZE = 5;

	/**
	 * Delay in seconds before the next queued batch is scheduled.
	 *
	 * @since 1.2.0
	 * @var int
	 */
	const RESCHEDULE_DELAY = 5;

	/**
	 * Job store instance.
	 *
	 * @since 1.2.0
	 * @var Occidg_Background_Jobs
	 */
	private $jobs;

	/**
	 * Callable metadata generator.
	 *
	 * @since 1.2.0
	 * @var callable|null
	 */
	private $metadata_generator;

	/**
	 * Number of images to process per pass.
	 *
	 * @since 1.2.0
	 * @var int
	 */
	private $batch_size;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 * @param Occidg_Background_Jobs|null $jobs               Job store.
	 * @param callable|null               $metadata_generator Metadata generator callback.
	 * @param int                         $batch_size         Batch size.
	 */
	public function __construct( $jobs = null, $metadata_generator = null, $batch_size = self::DEFAULT_BATCH_SIZE ) {
		$this->jobs               = $jobs instanceof Occidg_Background_Jobs ? $jobs : new Occidg_Background_Jobs();
		$this->metadata_generator = is_callable( $metadata_generator ) ? $metadata_generator : null;
		$this->batch_size         = max( 1, (int) $batch_size );
	}

	/**
	 * Queue a job for background processing.
	 *
	 * @since 1.2.0
	 * @param string $job_id Job ID.
	 * @return array|false
	 */
	public function start_job( $job_id ) {
		$job = $this->jobs->update_job(
			$job_id,
			array(
				'status'       => 'queued',
				'completed_at' => '',
				'last_error'   => '',
			)
		);

		if ( false === $job ) {
			return false;
		}

		$this->schedule_job( $job_id );

		return $job;
	}

	/**
	 * Pause an active job.
	 *
	 * @since 1.2.0
	 * @param string $job_id Job ID.
	 * @return array|false
	 */
	public function pause_job( $job_id ) {
		return $this->jobs->update_job( $job_id, array( 'status' => 'paused' ) );
	}

	/**
	 * Resume a paused job.
	 *
	 * @since 1.2.0
	 * @param string $job_id Job ID.
	 * @return array|false
	 */
	public function resume_job( $job_id ) {
		$job = $this->jobs->update_job(
			$job_id,
			array(
				'status'       => 'queued',
				'completed_at' => '',
			)
		);

		if ( false === $job ) {
			return false;
		}

		$this->schedule_job( $job_id );

		return $job;
	}

	/**
	 * Cancel a job.
	 *
	 * @since 1.2.0
	 * @param string $job_id Job ID.
	 * @return array|false
	 */
	public function cancel_job( $job_id ) {
		return $this->jobs->update_job( $job_id, array( 'status' => 'cancelled' ) );
	}

	/**
	 * Schedule the next worker pass for a job.
	 *
	 * @since 1.2.0
	 * @param string $job_id Job ID.
	 * @return bool
	 */
	public function schedule_job( $job_id ) {
		$job_id = is_scalar( $job_id ) ? trim( (string) $job_id ) : '';
		if ( '' === $job_id ) {
			return false;
		}

		if ( false !== wp_next_scheduled( self::CRON_HOOK, array( $job_id ) ) ) {
			return true;
		}

		$delay = max( self::RESCHEDULE_DELAY, (int) get_option( 'occ_idg_delay_between_requests', self::RESCHEDULE_DELAY ) );
		return wp_schedule_single_event( time() + $delay, self::CRON_HOOK, array( $job_id ) );
	}

	/**
	 * Process the next batch for a queued job.
	 *
	 * @since 1.2.0
	 * @param string $job_id Job ID.
	 * @return array|false
	 */
	public function process_job( $job_id ) {
		$job = $this->jobs->get_job( $job_id );
		if ( false === $job ) {
			return false;
		}

		if ( in_array( $job['status'], array( 'paused', 'cancelled', 'completed', 'completed_with_errors', 'failed' ), true ) ) {
			return $job;
		}

		if ( ! $this->acquire_lock() ) {
			return $job;
		}

		try {
			if ( ! is_callable( $this->metadata_generator ) ) {
				return $this->jobs->update_job(
					$job_id,
					array(
						'status'     => 'failed',
						'last_error' => __( 'Background metadata generator is not configured.', 'occidg' ),
					)
				);
			}

			$job = $this->jobs->update_job( $job_id, array( 'status' => 'running' ) );
			if ( false === $job ) {
				return false;
			}

			$batch_results = array(
				'processed'        => 0,
				'succeeded'        => 0,
				'failed'           => 0,
				'skipped'          => 0,
				'next_index'       => $job['next_index'],
				'failed_image_ids' => array(),
				'recent_failures'  => array(),
				'last_error'       => '',
				'status'           => 'running',
			);

			$batch_limit = min( $job['total'], $job['next_index'] + $this->batch_size );

			for ( $index = $job['next_index']; $index < $batch_limit; $index++ ) {
				$image_id    = $job['image_ids'][ $index ];
				$result      = call_user_func( $this->metadata_generator, $image_id, $this->build_generation_context( $job ) );
				$max_retries = max( 0, (int) get_option( 'occ_idg_retry_count', 3 ) );
				if ( is_array( $result ) && empty( $result['success'] ) && ! empty( $result['temporary'] ) && $job['current_retry_count'] < $max_retries ) {
					$retry_count = $job['current_retry_count'] + 1;
					$job         = $this->jobs->update_job(
						$job_id,
						array(
							'status'              => 'queued',
							'current_retry_count' => $retry_count,
							'last_error'          => isset( $result['error'] ) ? sanitize_text_field( $result['error'] ) : __( 'Temporary provider error.', 'occidg' ),
						)
					);
					$delay       = min( HOUR_IN_SECONDS, max( 5, (int) pow( 2, $retry_count ) * 5 ) );
					wp_schedule_single_event( time() + $delay, self::CRON_HOOK, array( $job_id ) );
					return $job;
				}

				$this->merge_result_outcome( $batch_results, $image_id, $result );
				$batch_results['next_index'] = $index + 1;
				$job['current_retry_count']  = 0;
			}

			$job = $this->jobs->record_job_progress( $job_id, $batch_results );
			if ( false !== $job && 0 !== $job['current_retry_count'] ) {
				$job = $this->jobs->update_job( $job_id, array( 'current_retry_count' => 0 ) );
			}
			if ( false === $job ) {
				return false;
			}

			if ( $job['processed'] < $job['total'] && in_array( $job['status'], array( 'queued', 'running' ), true ) ) {
				$this->schedule_job( $job_id );
			} elseif ( in_array( $job['status'], array( 'completed', 'completed_with_errors' ), true ) ) {
				$batch_id = isset( $job['batch_id'] ) ? (int) $job['batch_id'] : 0;
				do_action( 'occ_idg_batch_completed', $batch_id );
				do_action( 'occidg_batch_completed', $batch_id );
			}

			return $job;
		} finally {
			$this->release_lock();
		}
	}

	/**
	 * Merge a single image processing result into a batch accumulator.
	 *
	 * @since 1.2.0
	 * @param array $batch_results Batch accumulator.
	 * @param int   $image_id      Image ID.
	 * @param mixed $result        Generation result.
	 * @return void
	 */
	private function merge_result_outcome( &$batch_results, $image_id, $result ) {
		++$batch_results['processed'];

		if ( is_array( $result ) && ! empty( $result['success'] ) && ! empty( $result['skipped'] ) ) {
			++$batch_results['skipped'];
			return;
		}

		if ( is_array( $result ) && ! empty( $result['success'] ) ) {
			++$batch_results['succeeded'];
			return;
		}

		$error_message = is_array( $result ) && isset( $result['error'] )
			? sanitize_text_field( $result['error'] )
			: __( 'Unknown metadata generation error.', 'occidg' );

		if ( false !== strpos( $error_message, 'No metadata fields require generation' ) ) {
			++$batch_results['skipped'];
			return;
		}

		++$batch_results['failed'];
		$batch_results['last_error']         = $error_message;
		$batch_results['failed_image_ids'][] = (int) $image_id;
		$batch_results['recent_failures'][]  = array(
			'image_id' => (int) $image_id,
			'message'  => $error_message,
		);
	}

	/**
	 * Build the generation context snapshot for a queued job.
	 *
	 * @since 1.2.0
	 * @param array $job Job payload.
	 * @return array
	 */
	private function build_generation_context( $job ) {
		return array(
			'provider'                 => isset( $job['provider'] ) ? $job['provider'] : '',
			'model'                    => isset( $job['model'] ) ? $job['model'] : '',
			'language'                 => isset( $job['language'] ) ? $job['language'] : '',
			'selected_fields'          => isset( $job['selected_fields'] ) ? $job['selected_fields'] : array(),
			'override_metadata'        => ! empty( $job['override_metadata'] ),
			'mode'                     => isset( $job['mode'] ) ? $job['mode'] : 'fill_missing',
			'batch_id'                 => isset( $job['batch_id'] ) ? (int) $job['batch_id'] : 0,
			'initiated_by'             => isset( $job['initiated_by'] ) ? (int) $job['initiated_by'] : 0,
			'overwrite_confirmed'      => ! empty( $job['overwrite_confirmed'] ),
			'caption_review_confirmed' => ! empty( $job['caption_review_confirmed'] ),
			'current_retry_count'      => isset( $job['current_retry_count'] ) ? (int) $job['current_retry_count'] : 0,
		);
	}

	/**
	 * Attempt to acquire the global worker lock.
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	private function acquire_lock() {
		if ( get_transient( self::LOCK_KEY ) ) {
			return false;
		}

		return set_transient( self::LOCK_KEY, 1, 60 );
	}

	/**
	 * Release the global worker lock.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function release_lock() {
		delete_transient( self::LOCK_KEY );
	}
}
