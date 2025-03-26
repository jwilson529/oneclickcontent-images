<?php
/**
 * Fired during plugin activation
 *
 * @link       https://oneclickcontent.com
 * @since      1.0.0
 *
 * @package    Occidg
 * @subpackage Occidg/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Occidg
 * @subpackage Occidg/includes
 * @author     James Wilson <james@oneclickcontent.com>
 */
class Occidg_Activator {

	/**
	 * Fired during plugin activation.
	 *
	 * Sets a transient flag for redirection to the settings page after activation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function activate() {
		add_option( 'occidg_activation_redirect', true );
		if ( false === get_option( 'occidg_first_time' ) ) {
			add_option( 'occidg_first_time', true );
		}
	}
}
