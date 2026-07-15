<?php
/**
 * Fired during plugin activation
 *
 * @link       https://github.com/jwilson529/oneclickcontent-images
 * @since      1.0.0
 *
 * @package    Occidg
 * @subpackage Occidg/includes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Occidg
 * @subpackage Occidg/includes
 * @author     James Wilson
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
		if ( class_exists( 'Occidg_Database' ) ) {
			Occidg_Database::install();
		}
		if ( class_exists( 'Occidg_Capabilities' ) && function_exists( 'get_role' ) ) {
			Occidg_Capabilities::install();
		}
		add_option( 'occ_idg_allow_overwrite', false );
		add_option( 'occ_idg_require_overwrite_confirmation', true );
		add_option( 'occ_idg_require_caption_review', true );
		add_option( 'occ_idg_require_low_confidence_review', true );
		add_option( 'occ_idg_preserve_human_metadata', true );
		add_option( 'occ_idg_remove_data_on_uninstall', false );

		Occidg_Logger::info( 'Plugin activated.' );
	}
}
