<?php
/**
 * Tests for the logger class.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

use PHPUnit\Framework\TestCase;

/**
 * Covers file logging.
 */
final class Test_Occidg_Logger extends TestCase {

	/**
	 * Reset runtime filters between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['occidg_runtime_filters'] = array();
	}

	/**
	 * Ensure log entries are written to the filtered log path.
	 *
	 * @return void
	 */
	public function test_logger_writes_to_filtered_log_file() {
		$log_file = tempnam( sys_get_temp_dir(), 'occidg-log-' );

		add_filter(
			'occidg_log_file',
			static function () use ( $log_file ) {
				return $log_file;
			}
		);

		$this->assertTrue( Occidg_Logger::info( 'Logger ready', array( 'image_id' => 42 ) ) );
		$this->assertFileExists( $log_file );

		$file = new SplFileObject( $log_file, 'r' );
		$line = '';

		while ( ! $file->eof() ) {
			$line .= $file->fgets();
		}

		$this->assertStringContainsString( 'Logger ready', $line );
		$this->assertStringContainsString( '"image_id":42', $line );
	}
}
