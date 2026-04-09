<?php
/**
 * Tests for plugin activation and deactivation helpers.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-occidg-activator.php';
require_once dirname( __DIR__ ) . '/includes/class-occidg-deactivator.php';

/**
 * Covers activation and deactivation side effects.
 */
final class Test_Occidg_Lifecycle extends TestCase {

	/**
	 * Reset test doubles between lifecycle assertions.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['occidg_options']         = array();
		$GLOBALS['occidg_runtime_filters'] = array();
	}

	/**
	 * Ensure activation sets expected options and logs the event.
	 *
	 * @return void
	 */
	public function test_activate_sets_options_and_logs_event() {
		$log_file = tempnam( sys_get_temp_dir(), 'occidg-activate-' );

		add_filter(
			'occidg_log_file',
			static function () use ( $log_file ) {
				return $log_file;
			}
		);

		Occidg_Activator::activate();

		$this->assertTrue( get_option( 'occidg_activation_redirect' ) );
		$this->assertTrue( get_option( 'occidg_first_time' ) );
		$this->assertStringContainsString( 'Plugin activated.', $this->read_file_contents( $log_file ) );
	}

	/**
	 * Ensure deactivation clears transient options and logs the event.
	 *
	 * @return void
	 */
	public function test_deactivate_removes_options_and_logs_event() {
		$log_file = tempnam( sys_get_temp_dir(), 'occidg-deactivate-' );

		$GLOBALS['occidg_options'] = array(
			'occidg_first_time'   => true,
			'occidg_trial_expired' => true,
		);

		add_filter(
			'occidg_log_file',
			static function () use ( $log_file ) {
				return $log_file;
			}
		);

		Occidg_Deactivator::deactivate();

		$this->assertFalse( get_option( 'occidg_first_time' ) );
		$this->assertFalse( get_option( 'occidg_trial_expired' ) );
		$this->assertStringContainsString( 'Plugin deactivated.', $this->read_file_contents( $log_file ) );
	}

	/**
	 * Read a log file without using direct filesystem helpers that WPCS flags.
	 *
	 * @param string $file_path Log file path.
	 * @return string
	 */
	private function read_file_contents( $file_path ) {
		$file = new SplFileObject( $file_path, 'r' );
		$text = '';

		while ( ! $file->eof() ) {
			$text .= $file->fgets();
		}

		return $text;
	}
}
