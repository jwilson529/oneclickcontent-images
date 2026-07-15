<?php
/**
 * Background jobs admin orchestration for OCCIDG.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles background metadata job orchestration in the admin.
 */
class Occidg_Background_Jobs_Admin {

	/**
	 * Job store instance.
	 *
	 * @since 1.2.0
	 * @var Occidg_Background_Jobs
	 */
	private $jobs;

	/**
	 * Background worker instance.
	 *
	 * @since 1.2.0
	 * @var Occidg_Background_Worker
	 */
	private $worker;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 * @param Occidg_Background_Jobs|null   $jobs   Job store.
	 * @param Occidg_Background_Worker|null $worker Worker instance.
	 */
	public function __construct( $jobs = null, $worker = null ) {
		$this->jobs   = $jobs instanceof Occidg_Background_Jobs ? $jobs : new Occidg_Background_Jobs();
		$this->worker = $worker instanceof Occidg_Background_Worker ? $worker : new Occidg_Background_Worker( $this->jobs );
	}

	/**
	 * Create a queued job from image IDs and the current settings snapshot.
	 *
	 * @since 1.2.0
	 * @param array  $image_ids Image IDs.
	 * @param string $label     Optional job label.
	 * @param array  $overrides Optional job setting overrides.
	 * @return array|false
	 */
	public function create_job_from_image_ids( $image_ids, $label = '', $overrides = array() ) {
		$image_ids = $this->normalize_image_ids( $image_ids );
		if ( empty( $image_ids ) ) {
			return false;
		}

		$gate_state = Occidg_Admin_Settings::get_generation_gate_state();
		if ( empty( $gate_state['has_selected_provider_key'] ) ) {
			return false;
		}

		$provider       = $gate_state['provider'];
		$provider_label = $gate_state['provider_label'];
		$model_option   = 'gemini' === $provider ? 'occidg_gemini_model' : 'occidg_openai_model';
		$default_model  = 'gemini' === $provider ? 'gemini-1.5-flash' : 'gpt-4o-mini';
		$job_args       = array(
			'label'             => '' !== $label ? $label : __( 'Bulk metadata generation', 'occidg' ),
			'provider'          => $provider,
			'provider_label'    => $provider_label,
			'model'             => get_option( $model_option, $default_model ),
			'language'          => get_option( 'occidg_language', 'en' ),
			'selected_fields'   => get_option( 'occidg_metadata_fields', array() ),
			'override_metadata' => get_option( 'occidg_override_metadata', false ),
			'image_ids'         => $image_ids,
			'mode'              => 'fill_missing',
			'initiated_by'      => function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0,
		);
		$overrides      = is_array( $overrides ) ? $overrides : array();
		$allowed        = array( 'provider', 'provider_label', 'model', 'language', 'selected_fields', 'override_metadata', 'mode', 'batch_id', 'initiated_by', 'overwrite_confirmed', 'caption_review_confirmed' );
		$job            = $this->jobs->create_job( array_merge( $job_args, array_intersect_key( $overrides, array_flip( $allowed ) ) ) );

		if ( false === $job ) {
			return false;
		}

		$job = $this->worker->start_job( $job['id'] );
		if ( false === $job ) {
			return false;
		}

		return $this->build_job_payload( $job );
	}

	/**
	 * Return a normalized job payload for UI rendering.
	 *
	 * @since 1.2.0
	 * @param array $job Job payload.
	 * @return array|false
	 */
	public function build_job_payload( $job ) {
		if ( ! is_array( $job ) || empty( $job['id'] ) ) {
			return false;
		}

		$total            = isset( $job['total'] ) ? max( 0, (int) $job['total'] ) : 0;
		$processed        = isset( $job['processed'] ) ? max( 0, (int) $job['processed'] ) : 0;
		$failed           = isset( $job['failed'] ) ? max( 0, (int) $job['failed'] ) : 0;
		$percent_complete = $total > 0 ? (int) round( ( $processed / $total ) * 100 ) : 0;
		$status           = isset( $job['status'] ) ? (string) $job['status'] : 'queued';
		$retry_image_ids  = $this->jobs->get_retry_image_ids( $job['id'] );

		return array(
			'id'                  => (string) $job['id'],
			'label'               => isset( $job['label'] ) ? (string) $job['label'] : '',
			'status'              => $status,
			'status_label'        => $this->get_status_label( $status ),
			'provider'            => isset( $job['provider'] ) ? (string) $job['provider'] : '',
			'provider_label'      => isset( $job['provider_label'] ) ? (string) $job['provider_label'] : '',
			'model'               => isset( $job['model'] ) ? (string) $job['model'] : '',
			'mode'                => isset( $job['mode'] ) ? (string) $job['mode'] : 'fill_missing',
			'batch_id'            => isset( $job['batch_id'] ) ? (int) $job['batch_id'] : 0,
			'language'            => isset( $job['language'] ) ? (string) $job['language'] : '',
			'total'               => $total,
			'processed'           => $processed,
			'succeeded'           => isset( $job['succeeded'] ) ? max( 0, (int) $job['succeeded'] ) : 0,
			'failed'              => $failed,
			'skipped'             => isset( $job['skipped'] ) ? max( 0, (int) $job['skipped'] ) : 0,
			'retry_image_count'   => count( $retry_image_ids ),
			'retried_from_job_id' => isset( $job['retried_from_job_id'] ) ? (string) $job['retried_from_job_id'] : '',
			'next_index'          => isset( $job['next_index'] ) ? max( 0, (int) $job['next_index'] ) : 0,
			'percent_complete'    => min( 100, $percent_complete ),
			'is_active'           => in_array( $status, array( 'queued', 'running' ), true ),
			'can_pause'           => in_array( $status, array( 'queued', 'running' ), true ),
			'can_resume'          => 'paused' === $status,
			'can_cancel'          => in_array( $status, array( 'queued', 'running', 'paused' ), true ),
			'can_retry'           => count( $retry_image_ids ) > 0,
			'recent_failures'     => isset( $job['recent_failures'] ) && is_array( $job['recent_failures'] ) ? $job['recent_failures'] : array(),
			'last_error'          => isset( $job['last_error'] ) ? (string) $job['last_error'] : '',
			'created_at'          => isset( $job['created_at'] ) ? (string) $job['created_at'] : '',
			'updated_at'          => isset( $job['updated_at'] ) ? (string) $job['updated_at'] : '',
			'completed_at'        => isset( $job['completed_at'] ) ? (string) $job['completed_at'] : '',
		);
	}

	/**
	 * Return a normalized payload for a single job or the latest active job.
	 *
	 * @since 1.2.0
	 * @param string $job_id Optional job ID.
	 * @return array|false
	 */
	public function get_job_payload( $job_id = '' ) {
		if ( '' !== $job_id ) {
			$job = $this->jobs->get_job( $job_id );
		} else {
			$job = $this->jobs->get_latest_job( array( 'queued', 'running', 'paused' ) );
			if ( false === $job ) {
				$job = $this->jobs->get_latest_job( array( 'completed_with_errors', 'cancelled', 'failed' ) );
			}
		}

		if ( false === $job ) {
			return false;
		}

		return $this->build_job_payload( $job );
	}

	/**
	 * Return a job owned by a specific user, or that user's latest relevant job.
	 *
	 * Generators without batch-management access use this path so they can poll
	 * work they started without gaining visibility into other users' jobs.
	 *
	 * @since 2.0.2
	 * @param string $job_id Job ID, or an empty string for the latest job.
	 * @param int    $user_id WordPress user ID.
	 * @return array|false
	 */
	public function get_user_job_payload( $job_id, $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id < 1 ) {
			return false;
		}

		if ( '' !== $job_id ) {
			$job = $this->jobs->get_job( $job_id );
			if ( false === $job || $user_id !== (int) $job['initiated_by'] ) {
				return false;
			}

			return $this->build_job_payload( $job );
		}

		$status_groups = array(
			array( 'queued', 'running', 'paused' ),
			array( 'completed_with_errors', 'cancelled', 'failed' ),
		);
		foreach ( $status_groups as $statuses ) {
			foreach ( $this->jobs->get_jobs() as $job ) {
				if ( $user_id === (int) $job['initiated_by'] && in_array( $job['status'], $statuses, true ) ) {
					return $this->build_job_payload( $job );
				}
			}
		}

		return false;
	}

	/**
	 * Pause a job and return the normalized payload.
	 *
	 * @since 1.2.0
	 * @param string $job_id Job ID.
	 * @return array|false
	 */
	public function pause_job_payload( $job_id ) {
		$job = $this->worker->pause_job( $job_id );

		return false === $job ? false : $this->build_job_payload( $job );
	}

	/**
	 * Resume a job and return the normalized payload.
	 *
	 * @since 1.2.0
	 * @param string $job_id Job ID.
	 * @return array|false
	 */
	public function resume_job_payload( $job_id ) {
		$job = $this->worker->resume_job( $job_id );

		return false === $job ? false : $this->build_job_payload( $job );
	}

	/**
	 * Cancel a job and return the normalized payload.
	 *
	 * @since 1.2.0
	 * @param string $job_id Job ID.
	 * @return array|false
	 */
	public function cancel_job_payload( $job_id ) {
		$job = $this->worker->cancel_job( $job_id );

		return false === $job ? false : $this->build_job_payload( $job );
	}

	/**
	 * Retry a finished job and return the normalized payload.
	 *
	 * @since 1.2.0
	 * @param string $job_id Job ID.
	 * @return array|false
	 */
	public function retry_job_payload( $job_id ) {
		$job = $this->jobs->create_retry_job( $job_id );
		if ( false === $job ) {
			return false;
		}

		$job = $this->worker->start_job( $job['id'] );

		return false === $job ? false : $this->build_job_payload( $job );
	}

	/**
	 * AJAX handler for creating a background job.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function ajax_create_job() {
		check_ajax_referer( 'occidg_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'occ_idg_generate_metadata' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'occidg' ) ) );
			return;
		}

		$image_ids = isset( $_POST['image_ids'] )
			? $this->normalize_image_ids( map_deep( wp_unslash( $_POST['image_ids'] ), 'sanitize_text_field' ) )
			: $this->get_all_image_ids();

		$job = $this->create_job_from_image_ids( $image_ids );

		if ( false === $job ) {
			wp_send_json_error( array( 'message' => __( 'Unable to create a background job for the selected images.', 'occidg' ) ) );
			return;
		}

		wp_send_json_success( $job );
	}

	/**
	 * AJAX handler for fetching job status.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function ajax_get_job_status() {
		check_ajax_referer( 'occidg_ajax_nonce', 'nonce' );

		$can_manage   = current_user_can( 'occ_idg_manage_batches' );
		$can_generate = current_user_can( 'occ_idg_generate_metadata' );
		if ( ! $can_manage && ! $can_generate ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'occidg' ) ) );
			return;
		}

		$job_id = isset( $_REQUEST['job_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['job_id'] ) ) : '';
		$job    = $can_manage
			? $this->get_job_payload( $job_id )
			: $this->get_user_job_payload( $job_id, get_current_user_id() );

		if ( false === $job ) {
			wp_send_json_error( array( 'message' => __( 'Background job not found.', 'occidg' ) ) );
			return;
		}

		wp_send_json_success( $job );
	}

	/**
	 * AJAX handler for pausing a job.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function ajax_pause_job() {
		$this->ajax_update_job_state( 'pause' );
	}

	/**
	 * AJAX handler for resuming a job.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function ajax_resume_job() {
		$this->ajax_update_job_state( 'resume' );
	}

	/**
	 * AJAX handler for cancelling a job.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function ajax_cancel_job() {
		$this->ajax_update_job_state( 'cancel' );
	}

	/**
	 * AJAX handler for retrying a job.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function ajax_retry_job() {
		$this->ajax_update_job_state( 'retry' );
	}

	/**
	 * Shared AJAX state-update handler.
	 *
	 * @since 1.2.0
	 * @param string $action_name Action name.
	 * @return void
	 */
	private function ajax_update_job_state( $action_name ) {
		check_ajax_referer( 'occidg_ajax_nonce', 'nonce' );

		if ( ! current_user_can( 'occ_idg_manage_batches' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'occidg' ) ) );
			return;
		}

		$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : '';
		$job    = false;

		if ( 'pause' === $action_name ) {
			$job = $this->pause_job_payload( $job_id );
		} elseif ( 'resume' === $action_name ) {
			$job = $this->resume_job_payload( $job_id );
		} elseif ( 'cancel' === $action_name ) {
			$job = $this->cancel_job_payload( $job_id );
		} elseif ( 'retry' === $action_name ) {
			$job = $this->retry_job_payload( $job_id );
		}

		if ( false === $job ) {
			wp_send_json_error( array( 'message' => __( 'Unable to update the selected background job.', 'occidg' ) ) );
			return;
		}

		wp_send_json_success( $job );
	}

	/**
	 * Return a status label for UI rendering.
	 *
	 * @since 1.2.0
	 * @param string $status Job status.
	 * @return string
	 */
	private function get_status_label( $status ) {
		$labels = array(
			'queued'                => __( 'Queued', 'occidg' ),
			'running'               => __( 'Running', 'occidg' ),
			'paused'                => __( 'Paused', 'occidg' ),
			'completed'             => __( 'Completed', 'occidg' ),
			'completed_with_errors' => __( 'Completed with Errors', 'occidg' ),
			'cancelled'             => __( 'Cancelled', 'occidg' ),
			'failed'                => __( 'Failed', 'occidg' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Queued', 'occidg' );
	}

	/**
	 * Return all image attachment IDs.
	 *
	 * @since 1.2.0
	 * @return array
	 */
	private function get_all_image_ids() {
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'post_mime_type' => 'image',
				'fields'         => 'ids',
			)
		);

		return $this->normalize_image_ids( $query->posts );
	}

	/**
	 * Normalize image IDs from request input.
	 *
	 * @since 1.2.0
	 * @param mixed $image_ids Raw image IDs.
	 * @return array
	 */
	private function normalize_image_ids( $image_ids ) {
		if ( is_string( $image_ids ) ) {
			$decoded = json_decode( $image_ids, true );
			if ( is_array( $decoded ) ) {
				$image_ids = $decoded;
			}
		}

		if ( ! is_array( $image_ids ) ) {
			return array();
		}

		$normalized_ids = array();

		foreach ( $image_ids as $image_id ) {
			$image_id = is_numeric( $image_id ) ? (int) $image_id : 0;
			if ( $image_id < 1 ) {
				continue;
			}

			$normalized_ids[] = $image_id;
		}

		return array_values( array_unique( $normalized_ids ) );
	}
}
