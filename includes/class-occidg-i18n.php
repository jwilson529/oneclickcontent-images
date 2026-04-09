<?php
/**
 * Internationalization support for the plugin.
 *
 * @package    Occidg
 * @subpackage Occidg/includes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads the plugin text domain.
 */
class Occidg_I18n {

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'occidg',
			false,
			dirname( plugin_basename( OCCIDG_PLUGIN_FILE ) ) . '/languages/'
		);
	}
}
