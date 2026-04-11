<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://github.com/jwilson529/oneclickcontent-images
 * @since      1.0.0
 *
 * @package    One_Click_Images
 */

defined( 'ABSPATH' ) || exit;
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$occidg_options = array(
	'occidg_activation_redirect',
	'occidg_auto_add_details',
	'occidg_first_time',
	'occidg_gemini_api_key',
	'occidg_gemini_model',
	'occidg_language',
	'occidg_metadata_fields',
	'occidg_openai_api_key',
	'occidg_openai_model',
	'occidg_override_metadata',
	'occidg_provider',
);

foreach ( $occidg_options as $occidg_option ) {
	delete_option( $occidg_option );
}

delete_transient( 'occidg_image_error' );

$occidg_log_file = plugin_dir_path( __FILE__ ) . 'plugin-error.log';
if ( function_exists( 'wp_delete_file' ) && file_exists( $occidg_log_file ) ) {
	wp_delete_file( $occidg_log_file );
}
