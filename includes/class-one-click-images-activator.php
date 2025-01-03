<?php
/**
 * Fired during plugin activation
 *
 * @link       https://oneclickcontent.com
 * @since      1.0.0
 *
 * @package    One_Click_Images
 * @subpackage One_Click_Images/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    One_Click_Images
 * @subpackage One_Click_Images/includes
 * @author     James Wilson <james@oneclickcontent.com>
 */
class One_Click_Images_Activator {

	/**
	 * Fired during plugin activation.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		// Set a transient flag for redirection.
		add_option( 'oneclick_images_activation_redirect', true );
	}
}
