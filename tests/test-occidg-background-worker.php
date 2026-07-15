<?php
/**
 * Tests for background metadata job worker behavior.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

use PHPUnit\Framework\TestCase;

/**
 * Covers queue scheduling and worker batching.
 */
final class Test_Occidg_Background_Worker extends TestCase {

	/**
	 * Reset shared state between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['occidg_options']          = array();
		$GLOBALS['occidg_transients']       = array();
		$GLOBALS['occidg_scheduled_events'] = array();
	}

	/**
	 * Starting a job should schedule one background event without duplication.
	 *
	 * @return void
	 */
	public function test_start_job_schedules_single_event() {
		$jobs   = new Occidg_Background_Jobs();
		$job    = $jobs->create_job(
			array(
				'image_ids'       => array( 10, 11 ),
				'selected_fields' => array(
					'title'       => '1',
					'description' => '1',
					'alt_text'    => '1',
					'caption'     => '1',
				),
			)
		);
		$worker = new Occidg_Background_Worker(
			$jobs,
			static function () {
				return array( 'success' => true );
			}
		);

		$worker->start_job( $job['id'] );
		$worker->schedule_job( $job['id'] );

		$this->assertCount( 1, $GLOBALS['occidg_scheduled_events'] );
		$this->assertSame( Occidg_Background_Worker::CRON_HOOK, $GLOBALS['occidg_scheduled_events'][0]['hook'] );
		$this->assertSame( array( $job['id'] ), $GLOBALS['occidg_scheduled_events'][0]['args'] );
	}

	/**
	 * The worker should process one batch and reschedule unfinished jobs.
	 *
	 * @return void
	 */
	public function test_process_job_batches_and_reschedules() {
		$contexts = array();
		$jobs     = new Occidg_Background_Jobs();
		$job      = $jobs->create_job(
			array(
				'image_ids'                => array( 21, 22, 23 ),
				'provider'                 => 'openai',
				'model'                    => 'gpt-4o-mini',
				'language'                 => 'en',
				'caption_review_confirmed' => true,
				'selected_fields'          => array(
					'title'       => '1',
					'description' => '1',
					'alt_text'    => '1',
					'caption'     => '1',
				),
			)
		);
		$worker   = new Occidg_Background_Worker(
			$jobs,
			static function ( $image_id, $context ) use ( &$contexts ) {
				$contexts[] = $context;
				return array(
					'success'  => true,
					'image_id' => $image_id,
					'context'  => $context,
				);
			},
			2
		);

		$updated_job = $worker->process_job( $job['id'] );

		$this->assertSame( 'running', $updated_job['status'] );
		$this->assertSame( 2, $updated_job['processed'] );
		$this->assertSame( 2, $updated_job['succeeded'] );
		$this->assertSame( 2, $updated_job['next_index'] );
		$this->assertCount( 2, $contexts );
		$this->assertTrue( $contexts[0]['caption_review_confirmed'] );
		$this->assertCount( 1, $GLOBALS['occidg_scheduled_events'] );
		$this->assertSame( array( $job['id'] ), $GLOBALS['occidg_scheduled_events'][0]['args'] );
	}

	/**
	 * A held lock should prevent background progress.
	 *
	 * @return void
	 */
	public function test_process_job_bails_when_lock_is_held() {
		$jobs   = new Occidg_Background_Jobs();
		$job    = $jobs->create_job(
			array(
				'image_ids'       => array( 31 ),
				'selected_fields' => array(
					'title'       => '1',
					'description' => '1',
					'alt_text'    => '1',
					'caption'     => '1',
				),
			)
		);
		$worker = new Occidg_Background_Worker(
			$jobs,
			static function () {
				return array( 'success' => true );
			}
		);

		set_transient( Occidg_Background_Worker::LOCK_KEY, 1, 60 );
		$unchanged_job = $worker->process_job( $job['id'] );

		$this->assertSame( 'queued', $unchanged_job['status'] );
		$this->assertSame( 0, $unchanged_job['processed'] );
		$this->assertCount( 0, $GLOBALS['occidg_scheduled_events'] );
	}

	/**
	 * Mixed batch outcomes should complete the job with error details preserved.
	 *
	 * @return void
	 */
	public function test_process_job_records_mixed_outcomes() {
		$jobs   = new Occidg_Background_Jobs();
		$job    = $jobs->create_job(
			array(
				'image_ids'       => array( 41, 42, 43 ),
				'selected_fields' => array(
					'title'       => '1',
					'description' => '1',
					'alt_text'    => '1',
					'caption'     => '1',
				),
			)
		);
		$worker = new Occidg_Background_Worker(
			$jobs,
			static function ( $image_id ) {
				if ( 42 === $image_id ) {
					return array(
						'success' => false,
						'error'   => 'Provider timeout',
					);
				}

				if ( 43 === $image_id ) {
					return array(
						'success' => true,
						'skipped' => true,
						'reason'  => 'unsupported_svg',
					);
				}

				return array( 'success' => true );
			},
			5
		);

		$completed_job = $worker->process_job( $job['id'] );

		$this->assertSame( 'completed_with_errors', $completed_job['status'] );
		$this->assertSame( 3, $completed_job['processed'] );
		$this->assertSame( 1, $completed_job['succeeded'] );
		$this->assertSame( 1, $completed_job['failed'] );
		$this->assertSame( 1, $completed_job['skipped'] );
		$this->assertSame( array( 42 ), $completed_job['failed_image_ids'] );
		$this->assertCount( 1, $completed_job['recent_failures'] );
		$this->assertSame( 'Provider timeout', $completed_job['recent_failures'][0]['message'] );
		$this->assertSame( 0, count( $GLOBALS['occidg_scheduled_events'] ) );
	}
}
