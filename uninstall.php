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

// Production-safe default: retain configuration and audit data unless an
// administrator explicitly opted into destructive uninstall cleanup.
if ( ! get_option( 'occ_idg_remove_data_on_uninstall', false ) ) {
	return;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-occidg-database.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-occidg-capabilities.php';

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
	'occidg_background_jobs',
	'occidg_openai_models_cache',
	'occidg_gemini_models_cache',
	'occ_idg_default_mode',
	'occ_idg_default_batch_size',
	'occ_idg_max_batch_size',
	'occ_idg_retry_count',
	'occ_idg_request_timeout',
	'occ_idg_images_per_queue_action',
	'occ_idg_concurrent_requests',
	'occ_idg_delay_between_requests',
	'occ_idg_max_response_tokens',
	'occ_idg_history_retention_days',
	'occ_idg_remove_data_on_uninstall',
	'occ_idg_organization_name',
	'occ_idg_site_description',
	'occ_idg_editorial_tone',
	'occ_idg_preferred_terminology',
	'occ_idg_prohibited_terminology',
	'occ_idg_title_capitalization',
	'occ_idg_max_alt_length',
	'occ_idg_custom_prompt_instructions',
	'occ_idg_allow_unpublished_context',
	'occ_idg_allow_private_context',
	'occ_idg_allow_overwrite',
	'occ_idg_require_overwrite_confirmation',
	'occ_idg_require_caption_review',
	'occ_idg_require_low_confidence_review',
	'occ_idg_preserve_human_metadata',
	'occ_idg_daily_request_ceiling',
	'occ_idg_max_estimated_batch_cost',
	'occ_idg_openai_cost_per_request',
	'occ_idg_gemini_cost_per_request',
	'occ_idg_daily_usage',
	'occ_idg_lock_ttl',
	'occ_idg_debug_logging',
	'occ_idg_grant_editor_capabilities',
);

foreach ( $occidg_options as $occidg_option ) {
	delete_option( $occidg_option );
}

Occidg_Database::uninstall();
Occidg_Capabilities::uninstall();

// Remove per-user batch planner choices only when destructive cleanup is on.
delete_metadata( 'user', 0, '_occ_idg_batch_planner_preferences', '', true );

delete_transient( 'occidg_image_error' );

$occidg_log_file = plugin_dir_path( __FILE__ ) . 'plugin-error.log';
if ( function_exists( 'wp_delete_file' ) && file_exists( $occidg_log_file ) ) {
	wp_delete_file( $occidg_log_file );
}
