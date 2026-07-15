<?php
/**
 * WP-CLI commands for large-library operations.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

/** Implements `wp occ-idg ...` commands. */
class Occidg_CLI {
	/**
	 * Workflow service.
	 *
	 * @var Occidg_Workflow
	 */
	private $workflow;

	/**
	 * Preflight service.
	 *
	 * @var Occidg_Preflight
	 */
	private $preflight;

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
	 * Background jobs controller.
	 *
	 * @var Occidg_Background_Jobs_Admin
	 */
	private $jobs_admin;

	/**
	 * Construct the CLI command group.
	 *
	 * @param Occidg_Workflow              $workflow   Workflow service.
	 * @param Occidg_Preflight             $preflight  Preflight service.
	 * @param Occidg_Database              $database   Database service.
	 * @param Occidg_Metadata              $metadata   Metadata service.
	 * @param Occidg_Background_Jobs_Admin $jobs_admin Background jobs controller.
	 */
	public function __construct( $workflow, $preflight, $database, $metadata, $jobs_admin ) {
		$this->workflow   = $workflow;
		$this->preflight  = $preflight;
		$this->database   = $database;
		$this->metadata   = $metadata;
		$this->jobs_admin = $jobs_admin;
	}

	/**
	 * Show media-library condition and optional missing-field scope.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function preflight( $args, $assoc_args ) {
		unset( $args );
		$field   = isset( $assoc_args['field'] ) ? sanitize_key( $assoc_args['field'] ) : '';
		$metrics = $this->preflight->get_metrics( $field ? array( $field ) : get_option( 'occidg_metadata_fields', array() ) );
		WP_CLI\Utils\format_items( 'table', array( $metrics ), array_keys( $metrics ) );
	}

	/**
	 * Queue fill-missing or explicit overwrite generation.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function generate( $args, $assoc_args ) {
		unset( $args );
		$this->queue_from_cli( 'fill_missing', $assoc_args );
	}

	/**
	 * Queue suggestions without modifying attachment metadata.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function suggest( $args, $assoc_args ) {
		unset( $args );
		$this->queue_from_cli( 'suggestion', $assoc_args );
	}

	/** List persisted operational batches. */
	public function batches() {
		$rows = $this->database->get_batches( 200 );
		if ( empty( $rows ) ) {
			WP_CLI::log( 'No batches found.' );
			return; }
		WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'name', 'mode', 'provider', 'model', 'status', 'total_items', 'created_at' ) );
	}

	/**
	 * Retry failed and unprocessed items from a job key.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function retry( $args, $assoc_args ) {
		unset( $args );
		$job_id = isset( $assoc_args['job'] ) ? sanitize_text_field( $assoc_args['job'] ) : '';
		if ( ! $job_id ) {
			WP_CLI::error( 'Use --job=<job-key>.' ); }
		$job = $this->jobs_admin->retry_job_payload( $job_id );
		if ( ! $job ) {
			WP_CLI::error( 'No retryable items were found.' ); }
		WP_CLI::success( 'Queued retry job ' . $job['id'] . '.' );
	}

	/**
	 * Restore a field event or all compatible events from a batch.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function restore( $args, $assoc_args ) {
		unset( $args );
		$force = ! empty( $assoc_args['force'] );
		if ( isset( $assoc_args['history'] ) ) {
			$result = $this->metadata->restore_history( absint( $assoc_args['history'] ), $force );
			if ( empty( $result['success'] ) ) {
				WP_CLI::error( $result['error'] ); }
			WP_CLI::success( 'History value restored and a new restoration event recorded.' );
			return;
		}
		if ( isset( $assoc_args['batch'] ) ) {
			$result = $this->metadata->restore_batch( absint( $assoc_args['batch'] ), $force );
			WP_CLI\Utils\format_items( 'table', array( $result ), array_keys( $result ) );
			return;
		}
		WP_CLI::error( 'Use --history=<id> or --batch=<id>. Add --force only after reviewing conflicts.' );
	}

	/**
	 * Export operational records as CSV to stdout.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function export( $args, $assoc_args ) {
		unset( $args );
		$type = isset( $assoc_args['type'] ) ? sanitize_key( $assoc_args['type'] ) : 'history';
		if ( 'suggestions' === $type ) {
			$rows = $this->database->get_suggestions(
				array(
					'status' => '',
					'limit'  => 500,
				)
			);
		} elseif ( 'batches' === $type ) {
			$rows = $this->database->get_batches( 500 );
		} else {
			$rows = $this->database->get_history_rows( array( 'limit' => 1000 ) );
		}
		if ( empty( $rows ) ) {
			return;
		}
		WP_CLI\Utils\format_items( 'csv', $rows, array_keys( $rows[0] ) );
	}

	/**
	 * Run a dry preview or create a durable queue from CLI arguments.
	 *
	 * @param string $default_mode Default processing mode.
	 * @param array  $assoc_args   Named arguments.
	 */
	private function queue_from_cli( $default_mode, $assoc_args ) {
		$requested_mode = isset( $assoc_args['mode'] ) ? sanitize_key( $assoc_args['mode'] ) : '';
		$mode           = in_array( $requested_mode, array( 'dry_run', 'fill_missing', 'suggestion', 'overwrite' ), true ) ? $requested_mode : $default_mode;
		$mode           = ! empty( $assoc_args['overwrite'] ) ? 'overwrite' : $mode;
		if ( 'overwrite' === $mode && ( ! get_option( 'occ_idg_allow_overwrite', false ) || empty( $assoc_args['confirm-overwrite'] ) ) ) {
			WP_CLI::error( 'Overwrite requires the setting plus both --overwrite and --confirm-overwrite.' );
		}
		$fields = isset( $assoc_args['fields'] ) ? explode( ',', $assoc_args['fields'] ) : array( 'alt_text' );
		$fields = Occidg_Metadata::normalize_fields( $fields );
		$limit  = isset( $assoc_args['limit'] ) ? min( absint( $assoc_args['limit'] ), absint( get_option( 'occ_idg_max_batch_size', 1000 ) ) ) : 100;
		if ( isset( $assoc_args['attachment_ids'] ) ) {
			$ids = array_map( 'absint', explode( ',', $assoc_args['attachment_ids'] ) );
		} else {
			$ids = $this->preflight->query_ids(
				array(
					'missing_field' => isset( $assoc_args['field'] ) ? sanitize_key( $assoc_args['field'] ) : '',
					'order'         => 'oldest',
				),
				$limit
			);
		}
		if ( 'dry_run' === $mode || ! empty( $assoc_args['dry-run'] ) ) {
			$preview_mode = ! empty( $assoc_args['overwrite'] ) ? 'overwrite' : $default_mode;
			WP_CLI\Utils\format_items( 'table', array( $this->workflow->dry_run( $ids, $fields, $preview_mode ) ), array( 'total_evaluated', 'eligible_images', 'skipped_images', 'estimated_requests', 'estimated_cost' ) );
			return;
		}
		$provider = get_option( 'occidg_provider', 'openai' );
		$model    = get_option( 'gemini' === $provider ? 'occidg_gemini_model' : 'occidg_openai_model', '' );
		$estimate = $this->workflow->dry_run( $ids, $fields, $mode, $provider );
		$batch_id = $this->database->create_batch(
			array(
				'name'               => 'WP-CLI ' . $mode,
				'mode'               => $mode,
				'provider'           => $provider,
				'model'              => $model,
				'requested_fields'   => $fields,
				'estimated_requests' => $estimate['estimated_requests'],
				'estimated_cost'     => $estimate['estimated_cost'],
			),
			$ids
		);
		if ( false === $batch_id ) {
			WP_CLI::error( 'Unable to save the batch. Verify that eligible images and metadata fields are selected.' );
		}
		$job = $this->jobs_admin->create_job_from_image_ids(
			$ids,
			'WP-CLI batch #' . $batch_id,
			array(
				'mode'                => $mode,
				'batch_id'            => $batch_id,
				'selected_fields'     => array_fill_keys( $fields, '1' ),
				'override_metadata'   => 'overwrite' === $mode,
				'overwrite_confirmed' => ! empty( $assoc_args['confirm-overwrite'] ),
				'initiated_by'        => get_current_user_id(),
			)
		);
		if ( ! $job ) {
			WP_CLI::error( 'Unable to queue the batch. Verify provider credentials and attachment IDs.' );
		}
		$this->database->update_batch( $batch_id, array( 'job_key' => $job['id'] ) );
		WP_CLI::success( sprintf( 'Queued batch %d as job %s (%d images, estimated cost $%s).', $batch_id, $job['id'], count( $ids ), $estimate['estimated_cost'] ) );
	}
}
