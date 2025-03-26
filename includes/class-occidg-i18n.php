<?php
/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for the OneClickContent Image Details plugin
 * so that it is ready for translation.
 *
 * @link       https://oneclickcontent.com
 * @since      1.0.0
 *
 * @package    Occidg
 * @subpackage Occidg/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for the OneClickContent Image Details plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Occidg
 * @subpackage Occidg/includes
 * @author     James Wilson <james@oneclickcontent.com>
 */
class Occidg_I18n {

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'occidg',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}
