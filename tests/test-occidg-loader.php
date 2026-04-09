<?php
/**
 * Tests for the loader class.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

use PHPUnit\Framework\TestCase;

/**
 * Covers hook registration through the loader.
 */
final class Test_Occidg_Loader extends TestCase {

	/**
	 * Reset registered hooks between tests.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['occidg_registered_actions'] = array();
		$GLOBALS['occidg_registered_filters'] = array();
	}

	/**
	 * Ensure run() registers queued actions and filters.
	 *
	 * @return void
	 */
	public function test_run_registers_actions_and_filters() {
		$loader    = new Occidg_Loader();
		$component = new stdClass();

		$loader->add_action( 'init', $component, 'boot', 20, 2 );
		$loader->add_filter( 'the_content', $component, 'filter_content', 15, 1 );
		$loader->run();

		$this->assertCount( 1, $GLOBALS['occidg_registered_actions'] );
		$this->assertCount( 1, $GLOBALS['occidg_registered_filters'] );
		$this->assertSame( 'init', $GLOBALS['occidg_registered_actions'][0]['hook'] );
		$this->assertSame( 'the_content', $GLOBALS['occidg_registered_filters'][0]['hook'] );
		$this->assertSame( 20, $GLOBALS['occidg_registered_actions'][0]['priority'] );
		$this->assertSame( 2, $GLOBALS['occidg_registered_actions'][0]['accepted_args'] );
	}
}
