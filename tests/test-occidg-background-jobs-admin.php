<?php
/**
 * Tests for background jobs admin orchestration.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/admin/class-occidg-admin-settings.php';

/**
 * Covers normalized queue payloads and state transitions.
 */
final class Test_Occidg_Background_Jobs_Admin extends TestCase {

	/**
	 * Reset shared state between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['occidg_options']          = array(
			'occidg_provider'          => 'openai',
			'occidg_openai_api_key'    => 'sk-test',
			'occidg_openai_model'      => 'gpt-4o-mini',
			'occidg_language'          => 'en',
			'occidg_override_metadata' => false,
			'occidg_metadata_fields'   => array(
				'title'       => '1',
				'description' => '1',
				'alt_text'    => '1',
				'caption'     => '1',
			),
		);
		$GLOBALS['occidg_transients']       = array();
		$GLOBALS['occidg_scheduled_events'] = array();
	}

	/**
	 * Creating a job should return a stable payload and schedule the worker.
	 *
	 * @return void
	 */
	public function test_create_job_from_image_ids_returns_stable_payload() {
		$jobs   = new Occidg_Background_Jobs();
		$worker = new Occidg_Background_Worker(
			$jobs,
			static function () {
				return array( 'success' => true );
			}
		);
		$admin  = new Occidg_Background_Jobs_Admin( $jobs, $worker );

		$payload = $admin->create_job_from_image_ids( array( 101, 102 ), 'Library backfill' );

		$this->assertIsArray( $payload );
		$this->assertSame( 'Library backfill', $payload['label'] );
		$this->assertSame( 'queued', $payload['status'] );
		$this->assertSame( 'Queued', $payload['status_label'] );
		$this->assertSame( 'openai', $payload['provider'] );
		$this->assertSame( 'OpenAI', $payload['provider_label'] );
		$this->assertSame( 'gpt-4o-mini', $payload['model'] );
		$this->assertSame( 2, $payload['total'] );
		$this->assertSame( 0, $payload['processed'] );
		$this->assertSame( 0, $payload['percent_complete'] );
		$this->assertTrue( $payload['can_pause'] );
		$this->assertCount( 1, $GLOBALS['occidg_scheduled_events'] );
	}

	/**
	 * Status lookup should return the latest active job when no ID is provided.
	 *
	 * @return void
	 */
	public function test_get_job_payload_returns_latest_active_job() {
		$jobs   = new Occidg_Background_Jobs();
		$worker = new Occidg_Background_Worker(
			$jobs,
			static function () {
				return array( 'success' => true );
			}
		);
		$admin  = new Occidg_Background_Jobs_Admin( $jobs, $worker );

		$created_job = $admin->create_job_from_image_ids( array( 201, 202 ) );
		$latest_job  = $admin->get_job_payload();

		$this->assertIsArray( $latest_job );
		$this->assertSame( $created_job['id'], $latest_job['id'] );
	}

	/**
	 * Status lookup should fall back to the latest retryable job when no active job exists.
	 *
	 * @return void
	 */
	public function test_get_job_payload_returns_latest_retryable_job_when_idle() {
		$jobs   = new Occidg_Background_Jobs();
		$worker = new Occidg_Background_Worker(
			$jobs,
			static function () {
				return array( 'success' => true );
			}
		);
		$admin  = new Occidg_Background_Jobs_Admin( $jobs, $worker );

		$created_job = $admin->create_job_from_image_ids( array( 211, 212, 213 ) );
		$jobs->update_job(
			$created_job['id'],
			array(
				'status'           => 'completed_with_errors',
				'processed'        => 3,
				'succeeded'        => 2,
				'failed'           => 1,
				'next_index'       => 3,
				'failed_image_ids' => array( 212 ),
				'recent_failures'  => array(
					array(
						'image_id' => 212,
						'message'  => 'Provider timeout',
					),
				),
			)
		);

		$latest_job = $admin->get_job_payload();

		$this->assertIsArray( $latest_job );
		$this->assertSame( $created_job['id'], $latest_job['id'] );
		$this->assertTrue( $latest_job['can_retry'] );
		$this->assertSame( 1, $latest_job['retry_image_count'] );
	}

	/**
	 * Pause, resume, and cancel should return normalized job payloads.
	 *
	 * @return void
	 */
	public function test_pause_resume_and_cancel_return_normalized_payloads() {
		$jobs   = new Occidg_Background_Jobs();
		$worker = new Occidg_Background_Worker(
			$jobs,
			static function () {
				return array( 'success' => true );
			}
		);
		$admin  = new Occidg_Background_Jobs_Admin( $jobs, $worker );

		$job           = $admin->create_job_from_image_ids( array( 301, 302 ) );
		$paused_job    = $admin->pause_job_payload( $job['id'] );
		$resumed_job   = $admin->resume_job_payload( $job['id'] );
		$cancelled_job = $admin->cancel_job_payload( $job['id'] );

		$this->assertSame( 'paused', $paused_job['status'] );
		$this->assertTrue( $paused_job['can_resume'] );
		$this->assertSame( 'queued', $resumed_job['status'] );
		$this->assertTrue( $resumed_job['can_pause'] );
		$this->assertSame( 'cancelled', $cancelled_job['status'] );
		$this->assertFalse( $cancelled_job['can_cancel'] );
	}

	/**
	 * Retrying a finished job should create a fresh queued job for remaining work only.
	 *
	 * @return void
	 */
	public function test_retry_job_payload_creates_fresh_job_for_remaining_work() {
		$jobs   = new Occidg_Background_Jobs();
		$worker = new Occidg_Background_Worker(
			$jobs,
			static function () {
				return array( 'success' => true );
			}
		);
		$admin  = new Occidg_Background_Jobs_Admin( $jobs, $worker );

		$job = $admin->create_job_from_image_ids( array( 401, 402, 403 ) );
		$jobs->update_job(
			$job['id'],
			array(
				'status'           => 'cancelled',
				'processed'        => 2,
				'succeeded'        => 1,
				'failed'           => 1,
				'next_index'       => 2,
				'failed_image_ids' => array( 402 ),
				'recent_failures'  => array(
					array(
						'image_id' => 402,
						'message'  => 'Provider timeout',
					),
				),
			)
		);

		$retry_job = $admin->retry_job_payload( $job['id'] );

		$this->assertIsArray( $retry_job );
		$this->assertSame( 'queued', $retry_job['status'] );
		$this->assertSame( 2, $retry_job['total'] );
		$this->assertSame( 0, $retry_job['processed'] );
		$this->assertSame( $job['id'], $retry_job['retried_from_job_id'] );
		$this->assertSame( 0, $retry_job['retry_image_count'] );
		$this->assertCount( 2, $GLOBALS['occidg_scheduled_events'] );
	}

	/**
	 * Invalid job state transitions should fail cleanly.
	 *
	 * @return void
	 */
	public function test_invalid_job_updates_fail_cleanly() {
		$admin = new Occidg_Background_Jobs_Admin( new Occidg_Background_Jobs(), new Occidg_Background_Worker() );

		$this->assertFalse( $admin->pause_job_payload( 'missing-job' ) );
		$this->assertFalse( $admin->resume_job_payload( 'missing-job' ) );
		$this->assertFalse( $admin->cancel_job_payload( 'missing-job' ) );
		$this->assertFalse( $admin->retry_job_payload( 'missing-job' ) );
	}
}
