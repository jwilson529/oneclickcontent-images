<?php
/**
 * Tests for the main plugin bootstrap file.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

use PHPUnit\Framework\TestCase;

/**
 * Covers the main plugin bootstrap wiring.
 */
final class Test_Occidg_Bootstrap extends TestCase {

	/**
	 * Load the plugin bootstrap once for this test class.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		$GLOBALS['occidg_registered_actions'] = array();
		$GLOBALS['occidg_registered_filters'] = array();
		$GLOBALS['occidg_activation_hooks']   = array();
		$GLOBALS['occidg_deactivation_hooks'] = array();

		require dirname( __DIR__ ) . '/occidg.php';
	}

	/**
	 * Ensure the bootstrap registers lifecycle and text-domain hooks.
	 *
	 * @return void
	 */
	public function test_main_bootstrap_registers_expected_hooks() {
		$this->assertNotEmpty( $GLOBALS['occidg_activation_hooks'] );
		$this->assertNotEmpty( $GLOBALS['occidg_deactivation_hooks'] );

		$this->assertSame( dirname( __DIR__ ) . '/occidg.php', $GLOBALS['occidg_activation_hooks'][0]['file'] );
		$this->assertSame( 'occidg_activate', $GLOBALS['occidg_activation_hooks'][0]['callback'] );
		$this->assertSame( dirname( __DIR__ ) . '/occidg.php', $GLOBALS['occidg_deactivation_hooks'][0]['file'] );
		$this->assertSame( 'occidg_deactivate', $GLOBALS['occidg_deactivation_hooks'][0]['callback'] );

		$plugins_loaded_hooks = array_filter(
			$GLOBALS['occidg_registered_actions'],
			static function ( $hook ) {
				return 'plugins_loaded' === $hook['hook'] && 'occidg_load_textdomain' === $hook['callback'];
			}
		);

		$this->assertNotEmpty( $plugins_loaded_hooks );
		$this->assertTrue( function_exists( 'occidg_run' ) );
	}
}
