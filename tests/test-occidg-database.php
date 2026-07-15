<?php
/**
 * Tests for durable batch persistence.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/class-occidg-database-wpdb-double.php';

/** Covers batch field serialization and repeat creation. */
final class Test_Occidg_Database extends TestCase {
	/**
	 * Previous global database object.
	 *
	 * @var mixed
	 */
	private $previous_wpdb;

	/** Install the isolated wpdb double. */
	protected function setUp(): void {
		parent::setUp();
		$this->previous_wpdb = isset( $GLOBALS['wpdb'] ) ? $GLOBALS['wpdb'] : null;
		$GLOBALS['wpdb']     = new Occidg_Database_Wpdb_Double();
	}

	/** Restore the previous database object. */
	protected function tearDown(): void {
		$GLOBALS['wpdb'] = $this->previous_wpdb;
		parent::tearDown();
	}

	/** Requested fields and attachment IDs should be normalized before insertion. */
	public function test_create_batch_serializes_fields_and_deduplicates_items() {
		$database = new Occidg_Database();
		$batch_id = $database->create_batch(
			array(
				'name'             => 'Release batch',
				'requested_fields' => array( 'title', 'alt_text', 'unsupported', 'title' ),
			),
			array( 12, 12, 0, 9 )
		);

		$this->assertSame( 1, $batch_id );
		$this->assertSame( '["title","alt_text"]', $GLOBALS['wpdb']->batches[1]['requested_fields'] );
		$this->assertSame( 2, $GLOBALS['wpdb']->batches[1]['total_items'] );
		$this->assertStringStartsWith( 'pending-', $GLOBALS['wpdb']->batches[1]['job_key'] );
		$this->assertSame( array( 12, 9 ), array_column( $GLOBALS['wpdb']->batch_items, 'attachment_id' ) );
		$this->assertSame( array( 'START TRANSACTION', 'COMMIT' ), $GLOBALS['wpdb']->queries );
	}

	/** Provisional job keys should allow multiple batches before jobs are attached. */
	public function test_create_batch_uses_a_unique_provisional_job_key() {
		$database  = new Occidg_Database();
		$first_id  = $database->create_batch( array( 'requested_fields' => array( 'title' ) ), array( 1 ) );
		$second_id = $database->create_batch( array( 'requested_fields' => array( 'caption' ) ), array( 2 ) );

		$this->assertSame( 1, $first_id );
		$this->assertSame( 2, $second_id );
		$this->assertNotSame( $GLOBALS['wpdb']->batches[1]['job_key'], $GLOBALS['wpdb']->batches[2]['job_key'] );
	}

	/** Empty image or field scopes should not create unusable batches. */
	public function test_create_batch_rejects_empty_work() {
		$database = new Occidg_Database();

		$this->assertFalse( $database->create_batch( array( 'requested_fields' => array( 'title' ) ), array() ) );
		$this->assertFalse( $database->create_batch( array( 'requested_fields' => array() ), array( 1 ) ) );
		$this->assertSame( array(), $GLOBALS['wpdb']->batches );
	}
}
