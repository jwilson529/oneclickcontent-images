<?php
/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://oneclickcontent.com
 * @since      1.0.0
 *
 * @package    One_Click_Images
 * @subpackage One_Click_Images/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    One_Click_Images
 * @subpackage One_Click_Images/includes
 * @author     James Wilson <james@oneclickcontent.com>
 */
class One_Click_Images_I18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'oneclickcontent-images',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}
