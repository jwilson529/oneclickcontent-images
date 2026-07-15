<?php
/**
 * Database double for batch persistence tests.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

/** Minimal wpdb double for batch inserts and transaction assertions. */
final class Occidg_Database_Wpdb_Double {
	/**
	 * WordPress table prefix.
	 *
	 * @var string
	 */
	public $prefix = 'wp_';

	/**
	 * Most recent insert ID.
	 *
	 * @var int
	 */
	public $insert_id = 0;

	/**
	 * Most recent database error.
	 *
	 * @var string
	 */
	public $last_error = '';

	/**
	 * Inserted batch rows keyed by ID.
	 *
	 * @var array
	 */
	public $batches = array();

	/**
	 * Inserted batch item rows.
	 *
	 * @var array
	 */
	public $batch_items = array();

	/**
	 * Transaction statements.
	 *
	 * @var array
	 */
	public $queries = array();

	/**
	 * Insert one row.
	 *
	 * @param string $table Table name.
	 * @param array  $row   Row data.
	 * @return int|false
	 */
	public function insert( $table, $row ) {
		if ( 'wp_occ_idg_batches' === $table ) {
			foreach ( $this->batches as $batch ) {
				if ( $batch['job_key'] === $row['job_key'] ) {
					$this->last_error = 'Duplicate job key';
					return false;
				}
			}
			$this->insert_id                   = count( $this->batches ) + 1;
			$row['id']                         = $this->insert_id;
			$this->batches[ $this->insert_id ] = $row;
			return 1;
		}

		if ( 'wp_occ_idg_batch_items' === $table ) {
			$this->batch_items[] = $row;
			return 1;
		}

		return false;
	}

	/**
	 * Record a transaction statement.
	 *
	 * @param string $query SQL statement.
	 * @return int
	 */
	public function query( $query ) {
		$this->queries[] = $query;
		return 1;
	}
}
