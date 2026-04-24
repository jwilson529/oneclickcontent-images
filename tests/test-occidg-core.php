<?php
/**
 * Tests for the core plugin class.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-occidg.php';

/**
 * Covers core plugin wiring.
 */
final class Test_Occidg_Core extends TestCase {

	/**
	 * Define plugin constants once for the test suite.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		if ( ! defined( 'OCCIDG_VERSION' ) ) {
			define( 'OCCIDG_VERSION', '1.2.1' );
		}

		if ( ! defined( 'OCCIDG_PLUGIN_FILE' ) ) {
			define( 'OCCIDG_PLUGIN_FILE', dirname( __DIR__ ) . '/occidg.php' );
		}
	}

	/**
	 * Reset captured hooks between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['occidg_registered_actions'] = array();
		$GLOBALS['occidg_registered_filters'] = array();
	}

	/**
	 * Ensure the core plugin class wires the expected hooks.
	 *
	 * @return void
	 */
	public function test_constructor_sets_core_properties_and_registers_hooks() {
		$plugin = new Occidg();

		$this->assertSame( 'occidg', $plugin->get_occidg_images() );
		$this->assertSame( '1.2.1', $plugin->get_version() );
		$this->assertInstanceOf( Occidg_Loader::class, $plugin->get_loader() );

		$plugin->run();

		$action_hooks = array_column( $GLOBALS['occidg_registered_actions'], 'hook' );
		$filter_hooks = array_column( $GLOBALS['occidg_registered_filters'], 'hook' );

		$this->assertContains( 'plugins_loaded', $action_hooks );
		$this->assertContains( 'admin_menu', $action_hooks );
		$this->assertContains( Occidg_Background_Worker::CRON_HOOK, $action_hooks );
		$this->assertContains( 'wp_ajax_occidg_create_background_job', $action_hooks );
		$this->assertContains( 'wp_ajax_occidg_get_background_job_status', $action_hooks );
		$this->assertContains( 'wp_ajax_occidg_pause_background_job', $action_hooks );
		$this->assertContains( 'wp_ajax_occidg_resume_background_job', $action_hooks );
		$this->assertContains( 'wp_ajax_occidg_cancel_background_job', $action_hooks );
		$this->assertContains( 'wp_ajax_occidg_retry_background_job', $action_hooks );
		$this->assertContains( 'wp_enqueue_scripts', $action_hooks );
		$this->assertNotContains( 'wp_ajax_occidg_validate_license', $action_hooks );
		$this->assertNotContains( 'wp_ajax_occidg_get_license_status', $action_hooks );
		$this->assertNotContains( 'wp_ajax_occidg_check_usage', $action_hooks );
		$this->assertContains( 'attachment_fields_to_edit', $filter_hooks );
		$this->assertContains( 'wp_generate_attachment_metadata', $filter_hooks );
	}
}
