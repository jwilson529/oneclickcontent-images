<?php
/**
 * Tests for the internationalization class.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

use PHPUnit\Framework\TestCase;

/**
 * Covers textdomain loading.
 */
final class Test_Occidg_I18n extends TestCase {

	/**
	 * Reset captured textdomain state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['occidg_loaded_textdomain'] = array();
	}

	/**
	 * Ensure the plugin textdomain is loaded from the languages directory.
	 *
	 * @return void
	 */
	public function test_load_plugin_textdomain_uses_expected_values() {
		if ( ! defined( 'OCCIDG_PLUGIN_FILE' ) ) {
			define( 'OCCIDG_PLUGIN_FILE', dirname( __DIR__ ) . '/occidg.php' );
		}

		$i18n = new Occidg_I18n();
		$i18n->load_plugin_textdomain();

		$this->assertSame( 'occidg', $GLOBALS['occidg_loaded_textdomain']['domain'] );
		$this->assertSame( 'oneclickcontent-images/languages/', $GLOBALS['occidg_loaded_textdomain']['plugin_rel_path'] );
	}
}
