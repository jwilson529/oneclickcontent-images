<?php
/**
 * Tests for background metadata job persistence.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

use PHPUnit\Framework\TestCase;

/**
 * Covers queue creation, progress tracking, and pruning.
 */
final class Test_Occidg_Background_Jobs extends TestCase {

	/**
	 * Reset option state between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['occidg_options'] = array();
	}

	/**
	 * Jobs should persist image IDs, totals, and provider snapshots on creation.
	 *
	 * @return void
	 */
	public function test_create_job_persists_expected_shape() {
		$jobs = new Occidg_Background_Jobs();
		$job  = $jobs->create_job(
			array(
				'label'                    => 'Library backfill',
				'provider'                 => 'openai',
				'provider_label'           => 'OpenAI',
				'model'                    => 'gpt-4o-mini',
				'override_metadata'        => true,
				'caption_review_confirmed' => true,
				'selected_fields'          => array(
					'title'       => '1',
					'description' => '0',
					'alt_text'    => '1',
					'caption'     => '0',
				),
				'image_ids'                => array( 42, '73', 42, 0, -1 ),
			)
		);

		$this->assertIsArray( $job );
		$this->assertSame( 'queued', $job['status'] );
		$this->assertSame( 'Library backfill', $job['label'] );
		$this->assertSame( 'openai', $job['provider'] );
		$this->assertSame( 'OpenAI', $job['provider_label'] );
		$this->assertSame( 'gpt-4o-mini', $job['model'] );
		$this->assertTrue( $job['override_metadata'] );
		$this->assertTrue( $job['caption_review_confirmed'] );
		$this->assertSame( array( 42, 73 ), $job['image_ids'] );
		$this->assertSame( 2, $job['total'] );
		$this->assertSame( 0, $job['processed'] );
		$this->assertSame( '1', $job['selected_fields']['title'] );
		$this->assertSame( '1', $job['selected_fields']['alt_text'] );
		$this->assertSame( '0', $job['selected_fields']['description'] );
		$this->assertNotSame( '', $job['id'] );
	}

	/**
	 * Progress updates should advance counters and bound recent failures.
	 *
	 * @return void
	 */
	public function test_record_job_progress_updates_counters_and_failures() {
		$jobs = new Occidg_Background_Jobs();
		$job  = $jobs->create_job(
			array(
				'image_ids'       => array( 11, 12, 13 ),
				'selected_fields' => array(
					'title'       => '1',
					'description' => '1',
					'alt_text'    => '1',
					'caption'     => '1',
				),
			)
		);

		$updated_job = $jobs->record_job_progress(
			$job['id'],
			array(
				'processed'        => 2,
				'succeeded'        => 1,
				'failed'           => 1,
				'next_index'       => 2,
				'failed_image_ids' => array( 12 ),
				'recent_failures'  => array(
					array(
						'image_id' => 12,
						'message'  => 'Provider timeout',
					),
				),
				'last_error'       => 'Provider timeout',
				'status'           => 'running',
			)
		);

		$this->assertSame( 'running', $updated_job['status'] );
		$this->assertSame( 2, $updated_job['processed'] );
		$this->assertSame( 1, $updated_job['succeeded'] );
		$this->assertSame( 1, $updated_job['failed'] );
		$this->assertSame( 2, $updated_job['next_index'] );
		$this->assertSame( array( 12 ), $updated_job['failed_image_ids'] );
		$this->assertCount( 1, $updated_job['recent_failures'] );
		$this->assertSame( 'Provider timeout', $updated_job['last_error'] );

		$completed_job = $jobs->record_job_progress(
			$job['id'],
			array(
				'processed'  => 1,
				'succeeded'  => 1,
				'next_index' => 3,
			)
		);

		$this->assertSame( 'completed_with_errors', $completed_job['status'] );
		$this->assertSame( 3, $completed_job['processed'] );
		$this->assertSame( 2, $completed_job['succeeded'] );
		$this->assertSame( 1, $completed_job['failed'] );
		$this->assertNotSame( '', $completed_job['completed_at'] );
	}

	/**
	 * Pruning should keep active jobs plus the most recent terminal jobs only.
	 *
	 * @return void
	 */
	public function test_get_pruned_jobs_keeps_active_and_recent_terminal_jobs() {
		$jobs_service = new Occidg_Background_Jobs();
		$jobs         = array();

		for ( $index = 0; $index < 12; $index++ ) {
			$job    = $jobs_service->create_job(
				array(
					'label'           => 'Job ' . $index,
					'image_ids'       => array( $index + 1 ),
					'selected_fields' => array(
						'title'       => '1',
						'description' => '0',
						'alt_text'    => '1',
						'caption'     => '0',
					),
				)
			);
			$jobs[] = $jobs_service->update_job(
				$job['id'],
				array(
					'status'       => 'completed',
					'updated_at'   => sprintf( '2026-04-19T00:%02d:00+00:00', $index ),
					'completed_at' => sprintf( '2026-04-19T00:%02d:00+00:00', $index ),
				)
			);
		}

		$active_job = $jobs_service->create_job(
			array(
				'label'           => 'Active job',
				'image_ids'       => array( 999 ),
				'selected_fields' => array(
					'title'       => '1',
					'description' => '1',
					'alt_text'    => '1',
					'caption'     => '1',
				),
			)
		);

		$pruned_jobs = $jobs_service->get_pruned_jobs( $jobs_service->get_jobs() );
		$job_ids     = array_column( $pruned_jobs, 'id' );

		$this->assertCount( 11, $pruned_jobs );
		$this->assertContains( $active_job['id'], $job_ids );
		$this->assertNotContains( $jobs[0]['id'], $job_ids );
		$this->assertNotContains( $jobs[1]['id'], $job_ids );
		$this->assertContains( $jobs[11]['id'], $job_ids );
	}

	/**
	 * Retry jobs should only include failed and unprocessed image IDs.
	 *
	 * @return void
	 */
	public function test_create_retry_job_clones_remaining_and_failed_work_only() {
		$jobs_service = new Occidg_Background_Jobs();
		$job          = $jobs_service->create_job(
			array(
				'label'           => 'Library backfill',
				'provider'        => 'openai',
				'provider_label'  => 'OpenAI',
				'model'           => 'gpt-4o-mini',
				'language'        => 'en',
				'image_ids'       => array( 41, 42, 43, 44 ),
				'selected_fields' => array(
					'title'       => '1',
					'description' => '1',
					'alt_text'    => '1',
					'caption'     => '1',
				),
			)
		);

		$job = $jobs_service->update_job(
			$job['id'],
			array(
				'status'           => 'cancelled',
				'processed'        => 3,
				'succeeded'        => 2,
				'failed'           => 1,
				'next_index'       => 3,
				'failed_image_ids' => array( 42 ),
				'recent_failures'  => array(
					array(
						'image_id' => 42,
						'message'  => 'Provider timeout',
					),
				),
			)
		);

		$this->assertSame( array( 42, 44 ), $jobs_service->get_retry_image_ids( $job['id'] ) );

		$retry_job = $jobs_service->create_retry_job( $job['id'] );

		$this->assertIsArray( $retry_job );
		$this->assertSame( 'queued', $retry_job['status'] );
		$this->assertSame( array( 42, 44 ), $retry_job['image_ids'] );
		$this->assertSame( 2, $retry_job['total'] );
		$this->assertSame( $job['id'], $retry_job['retried_from_job_id'] );
		$this->assertStringStartsWith( 'Retry: ', $retry_job['label'] );
	}
}
