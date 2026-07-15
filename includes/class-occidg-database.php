<?php
/**
 * Database schema and operational record access.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

/**
 * Owns the custom tables used by suggestions, history, and batch processing.
 */
class Occidg_Database {

	/** Schema version stored in the options table. */
	const SCHEMA_VERSION = '2.0.0';

	/** Schema option name. */
	const SCHEMA_OPTION = 'occidg_schema_version';

	/**
	 * Return a prefixed table name.
	 *
	 * @param string $table Logical table name.
	 * @return string
	 */
	public static function table( $table ) {
		global $wpdb;

		$allowed = array( 'suggestions', 'history', 'batches', 'batch_items' );
		if ( ! isset( $wpdb ) || ! in_array( $table, $allowed, true ) ) {
			return '';
		}

		return $wpdb->prefix . 'occ_idg_' . $table;
	}

	/**
	 * Create or upgrade plugin tables.
	 *
	 * @return bool
	 */
	public static function install() {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! method_exists( $wpdb, 'get_charset_collate' ) ) {
			return false;
		}

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$charset = $wpdb->get_charset_collate();
		$sql     = array(
			'CREATE TABLE ' . self::table( 'suggestions' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				attachment_id bigint(20) unsigned NOT NULL,
				field_name varchar(32) NOT NULL,
				current_value longtext NOT NULL,
				suggested_value longtext NOT NULL,
				approved_value longtext NULL,
				confidence varchar(12) NOT NULL DEFAULT 'medium',
				confidence_reason text NULL,
				context_snapshot longtext NULL,
				provider varchar(32) NOT NULL,
				model varchar(191) NOT NULL,
				prompt_version varchar(64) NOT NULL DEFAULT '',
				status varchar(32) NOT NULL DEFAULT 'pending',
				batch_id bigint(20) unsigned NULL,
				generated_at datetime NOT NULL,
				reviewed_at datetime NULL,
				reviewed_by bigint(20) unsigned NULL,
				PRIMARY KEY  (id),
				KEY attachment_status (attachment_id,status),
				KEY batch_status (batch_id,status),
				KEY confidence (confidence)
			) $charset;",
			'CREATE TABLE ' . self::table( 'history' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				attachment_id bigint(20) unsigned NOT NULL,
				field_name varchar(32) NOT NULL,
				old_value longtext NOT NULL,
				new_value longtext NOT NULL,
				suggested_value longtext NULL,
				final_approved_value longtext NULL,
				provider varchar(32) NOT NULL DEFAULT '',
				model varchar(191) NOT NULL DEFAULT '',
				confidence varchar(12) NOT NULL DEFAULT '',
				action_type varchar(32) NOT NULL,
				processing_mode varchar(32) NOT NULL DEFAULT '',
				batch_id bigint(20) unsigned NULL,
				initiated_by bigint(20) unsigned NULL,
				approved_by bigint(20) unsigned NULL,
				was_edited tinyint(1) NOT NULL DEFAULT 0,
				prompt_version varchar(64) NOT NULL DEFAULT '',
				restored_from_id bigint(20) unsigned NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY attachment_field (attachment_id,field_name),
				KEY batch_id (batch_id),
				KEY action_type (action_type)
			) $charset;",
			'CREATE TABLE ' . self::table( 'batches' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				job_key varchar(191) NOT NULL DEFAULT '',
				name varchar(191) NOT NULL,
				mode varchar(32) NOT NULL,
				provider varchar(32) NOT NULL,
				model varchar(191) NOT NULL,
				requested_fields text NOT NULL,
				filters longtext NULL,
				status varchar(32) NOT NULL DEFAULT 'queued',
				total_items bigint(20) unsigned NOT NULL DEFAULT 0,
				completed_items bigint(20) unsigned NOT NULL DEFAULT 0,
				failed_items bigint(20) unsigned NOT NULL DEFAULT 0,
				skipped_items bigint(20) unsigned NOT NULL DEFAULT 0,
				estimated_requests bigint(20) unsigned NOT NULL DEFAULT 0,
				actual_requests bigint(20) unsigned NOT NULL DEFAULT 0,
				estimated_cost decimal(18,6) NOT NULL DEFAULT 0,
				actual_cost decimal(18,6) NOT NULL DEFAULT 0,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				started_at datetime NULL,
				completed_at datetime NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY job_key (job_key),
				KEY status (status),
				KEY created_by (created_by)
			) $charset;",
			'CREATE TABLE ' . self::table( 'batch_items' ) . " (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				batch_id bigint(20) unsigned NOT NULL,
				attachment_id bigint(20) unsigned NOT NULL,
				requested_fields text NOT NULL,
				status varchar(32) NOT NULL DEFAULT 'queued',
				attempts smallint(5) unsigned NOT NULL DEFAULT 0,
				error_code varchar(64) NOT NULL DEFAULT '',
				error_message text NULL,
				next_attempt_at datetime NULL,
				started_at datetime NULL,
				completed_at datetime NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY batch_attachment (batch_id,attachment_id),
				KEY status_retry (status,next_attempt_at),
				KEY attachment_id (attachment_id)
			) $charset;",
		);

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );

		return true;
	}

	/**
	 * Upgrade lazily after a plugin update.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( self::SCHEMA_VERSION !== get_option( self::SCHEMA_OPTION, '' ) ) {
			self::install();
		}
	}

	/**
	 * Insert a suggestion.
	 *
	 * @param array $data Suggestion fields.
	 * @return int|false
	 */
	public function insert_suggestion( $data ) {
		global $wpdb;

		$defaults = array(
			'attachment_id'     => 0,
			'field_name'        => '',
			'current_value'     => '',
			'suggested_value'   => '',
			'approved_value'    => null,
			'confidence'        => 'medium',
			'confidence_reason' => '',
			'context_snapshot'  => '',
			'provider'          => '',
			'model'             => '',
			'prompt_version'    => '',
			'status'            => 'pending',
			'batch_id'          => null,
			'generated_at'      => current_time( 'mysql', true ),
		);
		$row      = wp_parse_args( $data, $defaults );
		$result   = $wpdb->insert( self::table( 'suggestions' ), $row );

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Retrieve one suggestion.
	 *
	 * @param int $suggestion_id Suggestion ID.
	 * @return array|false
	 */
	public function get_suggestion( $suggestion_id ) {
		global $wpdb;

		$sql = $wpdb->prepare( 'SELECT * FROM ' . self::table( 'suggestions' ) . ' WHERE id = %d', absint( $suggestion_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $row ) ? $row : false;
	}

	/**
	 * Retrieve suggestions for review.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_suggestions( $args = array() ) {
		global $wpdb;

		$args   = wp_parse_args(
			$args,
			array(
				'status'        => 'pending',
				'attachment_id' => 0,
				'batch_id'      => 0,
				'limit'         => 100,
			)
		);
		$where  = array( '1=1' );
		$params = array();
		if ( '' !== $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( $args['status'] );
		}
		if ( ! empty( $args['attachment_id'] ) ) {
			$where[]  = 'attachment_id = %d';
			$params[] = absint( $args['attachment_id'] );
		}
		if ( ! empty( $args['batch_id'] ) ) {
			$where[]  = 'batch_id = %d';
			$params[] = absint( $args['batch_id'] );
		}
		$params[] = min( 500, max( 1, absint( $args['limit'] ) ) );
		$query    = 'SELECT * FROM ' . self::table( 'suggestions' ) . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY generated_at ASC, id ASC LIMIT %d';
		$sql      = $wpdb->prepare( $query, ...$params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Update a suggestion.
	 *
	 * @param int   $suggestion_id Suggestion ID.
	 * @param array $updates       Fields to update.
	 * @return bool
	 */
	public function update_suggestion( $suggestion_id, $updates ) {
		global $wpdb;

		$allowed = array( 'approved_value', 'status', 'reviewed_at', 'reviewed_by', 'confidence', 'confidence_reason' );
		$row     = array_intersect_key( (array) $updates, array_flip( $allowed ) );

		return ! empty( $row ) && false !== $wpdb->update( self::table( 'suggestions' ), $row, array( 'id' => absint( $suggestion_id ) ) );
	}

	/**
	 * Add an immutable history event.
	 *
	 * @param array $data Event fields.
	 * @return int|false
	 */
	public function insert_history( $data ) {
		global $wpdb;

		$defaults = array(
			'attachment_id'        => 0,
			'field_name'           => '',
			'old_value'            => '',
			'new_value'            => '',
			'suggested_value'      => '',
			'final_approved_value' => '',
			'provider'             => '',
			'model'                => '',
			'confidence'           => '',
			'action_type'          => 'update',
			'processing_mode'      => '',
			'batch_id'             => null,
			'initiated_by'         => get_current_user_id(),
			'approved_by'          => null,
			'was_edited'           => 0,
			'prompt_version'       => '',
			'restored_from_id'     => null,
			'created_at'           => current_time( 'mysql', true ),
		);
		$result   = $wpdb->insert( self::table( 'history' ), wp_parse_args( $data, $defaults ) );

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Retrieve a history event.
	 *
	 * @param int $history_id History ID.
	 * @return array|false
	 */
	public function get_history( $history_id ) {
		global $wpdb;

		$sql = $wpdb->prepare( 'SELECT * FROM ' . self::table( 'history' ) . ' WHERE id = %d', absint( $history_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $row ) ? $row : false;
	}

	/**
	 * Retrieve history rows.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_history_rows( $args = array() ) {
		global $wpdb;

		$args   = wp_parse_args(
			$args,
			array(
				'attachment_id' => 0,
				'batch_id'      => 0,
				'limit'         => 200,
			)
		);
		$where  = array( '1=1' );
		$params = array();
		if ( $args['attachment_id'] ) {
			$where[]  = 'attachment_id = %d';
			$params[] = absint( $args['attachment_id'] );
		}
		if ( $args['batch_id'] ) {
			$where[]  = 'batch_id = %d';
			$params[] = absint( $args['batch_id'] );
		}
		$params[] = min( 1000, max( 1, absint( $args['limit'] ) ) );
		$query    = 'SELECT * FROM ' . self::table( 'history' ) . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY created_at DESC, id DESC LIMIT %d';
		$sql      = $wpdb->prepare( $query, ...$params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** Delete history older than the explicitly configured retention period. */
	public function prune_history() {
		global $wpdb;
		$days = max( 0, (int) get_option( 'occ_idg_history_retention_days', 0 ) );
		if ( 0 === $days ) {
			return 0;
		}
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$sql    = $wpdb->prepare( 'DELETE FROM ' . self::table( 'history' ) . ' WHERE created_at < %s', $cutoff ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Create a persisted batch and its item rows.
	 *
	 * @param array $data           Batch data.
	 * @param array $attachment_ids Attachment IDs.
	 * @return int|false
	 */
	public function create_batch( $data, $attachment_ids ) {
		global $wpdb;

		$data           = is_array( $data ) ? $data : array();
		$attachment_ids = array_values( array_filter( array_unique( array_map( 'absint', (array) $attachment_ids ) ) ) );
		$fields         = array();
		foreach ( isset( $data['requested_fields'] ) ? (array) $data['requested_fields'] : array() as $field ) {
			$field = sanitize_key( $field );
			if ( in_array( $field, array( 'alt_text', 'title', 'caption', 'description' ), true ) ) {
				$fields[] = $field;
			}
		}
		$fields = array_values( array_unique( $fields ) );
		if ( empty( $attachment_ids ) || empty( $fields ) ) {
			return false;
		}

		$data['requested_fields'] = wp_json_encode( $fields );
		if ( isset( $data['filters'] ) && ! is_scalar( $data['filters'] ) ) {
			$data['filters'] = wp_json_encode( $data['filters'] );
		}
		if ( empty( $data['job_key'] ) ) {
			$data['job_key'] = 'pending-' . wp_generate_uuid4();
		}

		$defaults                = array(
			'job_key'            => $data['job_key'],
			'name'               => __( 'Metadata batch', 'occidg' ),
			'mode'               => 'fill_missing',
			'provider'           => '',
			'model'              => '',
			'requested_fields'   => $data['requested_fields'],
			'filters'            => '',
			'status'             => 'queued',
			'total_items'        => count( $attachment_ids ),
			'estimated_requests' => count( $attachment_ids ),
			'estimated_cost'     => 0,
			'created_by'         => get_current_user_id(),
			'created_at'         => current_time( 'mysql', true ),
		);
		$row                     = wp_parse_args( $data, $defaults );
		$row['job_key']          = sanitize_text_field( $row['job_key'] );
		$row['name']             = sanitize_text_field( $row['name'] );
		$row['mode']             = sanitize_key( $row['mode'] );
		$row['provider']         = sanitize_key( $row['provider'] );
		$row['model']            = sanitize_text_field( $row['model'] );
		$row['requested_fields'] = $data['requested_fields'];
		$row['total_items']      = count( $attachment_ids );

		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		if ( false === $wpdb->insert( self::table( 'batches' ), $row ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return false;
		}

		$batch_id = (int) $wpdb->insert_id;
		foreach ( $attachment_ids as $attachment_id ) {
			$result = $wpdb->insert(
				self::table( 'batch_items' ),
				array(
					'batch_id'         => $batch_id,
					'attachment_id'    => $attachment_id,
					'requested_fields' => $data['requested_fields'],
					'status'           => 'queued',
				)
			);
			if ( false === $result ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				return false;
			}
		}

		if ( false === $wpdb->query( 'COMMIT' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return false;
		}

		return $batch_id;
	}

	/**
	 * Update a batch row.
	 *
	 * @param int   $batch_id Batch ID.
	 * @param array $updates  Allowed updates.
	 * @return bool Whether the row was updated.
	 */
	public function update_batch( $batch_id, $updates ) {
		global $wpdb;
		$allowed = array( 'job_key', 'status', 'completed_items', 'failed_items', 'skipped_items', 'actual_requests', 'actual_cost', 'started_at', 'completed_at' );
		$row     = array_intersect_key( (array) $updates, array_flip( $allowed ) );
		return ! empty( $row ) && false !== $wpdb->update( self::table( 'batches' ), $row, array( 'id' => absint( $batch_id ) ) );
	}

	/**
	 * Update a batch item by attachment.
	 *
	 * @param int   $batch_id      Batch ID.
	 * @param int   $attachment_id Attachment ID.
	 * @param array $updates       Allowed updates.
	 * @return bool Whether the row was updated.
	 */
	public function update_batch_item( $batch_id, $attachment_id, $updates ) {
		global $wpdb;
		$allowed = array( 'status', 'attempts', 'error_code', 'error_message', 'next_attempt_at', 'started_at', 'completed_at' );
		$row     = array_intersect_key( (array) $updates, array_flip( $allowed ) );
		return ! empty( $row ) && false !== $wpdb->update(
			self::table( 'batch_items' ),
			$row,
			array(
				'batch_id'      => absint( $batch_id ),
				'attachment_id' => absint( $attachment_id ),
			)
		);
	}

	/**
	 * Atomically mark an item processing and increment its attempt count.
	 *
	 * @param int $batch_id      Batch ID.
	 * @param int $attachment_id Attachment ID.
	 * @return bool Whether the row was updated.
	 */
	public function start_batch_item( $batch_id, $attachment_id ) {
		global $wpdb;
		$table = self::table( 'batch_items' );
		$sql   = $wpdb->prepare(
			"UPDATE $table SET status='processing', attempts=attempts+1, started_at=%s WHERE batch_id=%d AND attachment_id=%d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			current_time( 'mysql', true ),
			absint( $batch_id ),
			absint( $attachment_id )
		);
		return false !== $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Recalculate durable batch counters from item-level state.
	 *
	 * @param int $batch_id Batch ID.
	 * @return bool Whether the batch was updated.
	 */
	public function sync_batch_counts( $batch_id ) {
		global $wpdb;
		$batch_id = absint( $batch_id );
		if ( ! $batch_id ) {
			return false;
		}
		$table  = self::table( 'batch_items' );
		$sql    = $wpdb->prepare( "SELECT status, COUNT(*) AS item_count FROM $table WHERE batch_id=%d GROUP BY status", $batch_id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows   = (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$counts = array(
			'completed'  => 0,
			'failed'     => 0,
			'skipped'    => 0,
			'queued'     => 0,
			'processing' => 0,
			'retrying'   => 0,
		);
		foreach ( $rows as $row ) {
			if ( isset( $counts[ $row['status'] ] ) ) {
				$counts[ $row['status'] ] = (int) $row['item_count'];
			}
		}
		$status = ( $counts['queued'] + $counts['processing'] + $counts['retrying'] ) > 0 ? 'running' : ( $counts['failed'] > 0 ? 'completed_with_errors' : 'completed' );
		return $this->update_batch(
			$batch_id,
			array(
				'status'          => $status,
				'completed_items' => $counts['completed'],
				'failed_items'    => $counts['failed'],
				'skipped_items'   => $counts['skipped'],
				'actual_requests' => $counts['completed'] + $counts['failed'],
				'completed_at'    => 'running' === $status ? null : current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * Retrieve batch rows.
	 *
	 * @param int $limit Maximum rows.
	 * @return array Batch rows.
	 */
	public function get_batches( $limit = 100 ) {
		global $wpdb;
		$sql = $wpdb->prepare( 'SELECT * FROM ' . self::table( 'batches' ) . ' ORDER BY created_at DESC, id DESC LIMIT %d', min( 500, max( 1, absint( $limit ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** Drop all custom tables. Only called after explicit uninstall opt-in. */
	public static function uninstall() {
		global $wpdb;
		if ( ! isset( $wpdb ) ) {
			return;
		}
		foreach ( array( 'batch_items', 'suggestions', 'history', 'batches' ) as $table ) {
			$name = self::table( $table );
			$wpdb->query( "DROP TABLE IF EXISTS `$name`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		delete_option( self::SCHEMA_OPTION );
	}
}
