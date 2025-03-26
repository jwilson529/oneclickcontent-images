<?php
/**
 * Fired during plugin deactivation
 *
 * @link       https://oneclickcontent.com
 * @since      1.0.0
 *
 * @package    Occidg
 * @subpackage Occidg/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Occidg
 * @subpackage Occidg/includes
 * @author     James Wilson <james@oneclickcontent.com>
 */
class Occidg_Deactivator {

	/**
	 * Fired during plugin deactivation.
	 *
	 * Performs cleanup or other actions when the plugin is deactivated.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function deactivate() {
		delete_option( 'occidg_first_time' );
	}
}
