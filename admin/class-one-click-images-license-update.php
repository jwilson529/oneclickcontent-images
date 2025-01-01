<?php
/**
 * One_Click_Images_License_Update Class File
 *
 * This file contains the definition of the One_Click_Images_License_Update class,
 * which handles the retrieval of updates and license verification for the
 * Post to Voice plugin. It communicates with the OneClickContent API to
 * manage license keys, check for plugin updates, and process API responses.
 *
 * This class ensures that the plugin remains up-to-date and licensed properly,
 * providing seamless integration with WordPress options for persistent storage
 * of license and update data.
 *
 * @since 1.0.0
 * @package PostVoice
 */

/**
 * Class One_Click_Images_License_Update
 *
 * Handles the retrieval of updates from the OneClickContent API and stores
 * them as WordPress options for usage within Post to Voice. This class
 * includes methods to verify license keys, check for updates, and process
 * responses from the API.
 *
 * @since 1.0.0
 * @package PostVoice
 */
class One_Click_Images_License_Update {

	/**
	 * The API URL.
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * The plugin slug.
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * The plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor for the One_Click_Images_License_Update class.
	 *
	 * @param string $api_url The API URL.
	 * @param string $plugin_slug The plugin slug.
	 * @param string $version The plugin version.
	 */
	public function __construct( $api_url, $plugin_slug, $version ) {
		$this->api_url     = $api_url;
		$this->plugin_slug = $plugin_slug;
		$this->version     = $version;
	}

	/**
	 * Validate the plugin license on initialization.
	 *
	 * This function uses a transient to limit the frequency of license validation requests.
	 * A manual trigger can be used to bypass the transient and force validation.
	 *
	 * @return void
	 */
	public function validate_license_on_init() {
		$license_key = get_option( 'oneclick_images_license_key' );

		if ( empty( $license_key ) ) {
			return; // No license key saved, skip validation.
		}

		// Check if a transient exists to avoid frequent API calls.
		$cached_validation = get_transient( 'oneclick_images_license_validation' );
		if ( false !== $cached_validation ) {
			return; // License has already been validated recently.
		}

		$request_data = array(
			'license_key' => sanitize_text_field( $license_key ),
			'site_url'    => home_url(),
		);

		$response = wp_remote_post(
			"{$this->api_url}validate-license",
			array(
				'body'    => wp_json_encode( $request_data ),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return; // Handle error gracefully (e.g., network issues).
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		$data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE || 200 !== $response_code ) {
			return; // Failed to validate license.
		}

		if ( isset( $data['status'] ) && 'success' === $data['status'] ) {
			update_option( 'oneclick_images_license_status', 'active' );
			set_transient( 'oneclick_images_license_validation', 'active', DAY_IN_SECONDS ); // Cache the active status for 24 hours.
		} else {
			update_option( 'oneclick_images_license_status', 'inactive' );
			set_transient( 'oneclick_images_license_validation', 'inactive', HOUR_IN_SECONDS ); // Cache the inactive status for 1 hour.
		}
	}


	/**
	 * Check for plugin updates using transients.
	 */
	public function check_for_update() {
		// Check if the transient exists and has not expired.
		$cached_response = get_transient( 'oneclick_images_update_check' );
		if ( false !== $cached_response ) {
			$this->apply_update_to_transient( $cached_response );
			return; // Skip the API call if the transient is still valid.
		}

		$license_key = get_option( 'oneclick_images_license_key' );

		if ( empty( $license_key ) ) {
			return;
		}

		$request_data = array(
			'product_key'     => 'product_6758e21c683bc',
			'license_key'     => sanitize_text_field( $license_key ),
			'website_url'     => home_url(),
			'current_version' => sanitize_text_field( $this->version ),
		);

		$response = wp_remote_post(
			"{$this->api_url}check-update",
			array(
				'body'    => wp_json_encode( $request_data ),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return;
		}

		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( json_last_error() === JSON_ERROR_NONE ) {
			// Cache the result for 24 hours.
			set_transient( 'oneclick_images_update_check', $data, DAY_IN_SECONDS );
			$this->apply_update_to_transient( $data );
		}
	}

	/**
	 * Apply update data to the `site_transient_update_plugins`.
	 *
	 * @param array $data The update data.
	 */
	private function apply_update_to_transient( $data ) {
		if ( isset( $data['update_available'] ) && $data['update_available'] ) {
			add_filter(
				'site_transient_update_plugins',
				function ( $transient ) use ( $data ) {
					if ( ! is_object( $transient ) ) {
						$transient = new stdClass();
					}

					if ( ! isset( $transient->response ) ) {
						$transient->response = array();
					}

					$plugin_details = $data['plugin_details'] ?? array();

					$transient->response['one-click-images/one-click-images.php'] = (object) array(
						'slug'         => 'one-click-images',
						'plugin'       => 'one-click-images/one-click-images.php',
						'new_version'  => sanitize_text_field( $data['latest_version'] ?? '1.0.0' ),
						'package'      => esc_url_raw( $data['download_url'] ?? '' ),
						'name'         => sanitize_text_field( $plugin_details['name'] ?? 'Unknown Plugin' ),
						'author'       => $plugin_details['author'] ?? '',
						'homepage'     => esc_url_raw( $plugin_details['homepage'] ?? '' ),
						'requires'     => $plugin_details['requires'] ?? '',
						'tested'       => $plugin_details['tested'] ?? '',
						'requires_php' => $plugin_details['requires_php'] ?? '',
						'sections'     => $plugin_details['sections'] ?? array(),
						'banners'      => $plugin_details['banners'] ?? array(),
					);

					return $transient;
				}
			);
		}
	}

	/**
	 * Adds a custom icon to the plugin update notification.
	 *
	 * @param object $transient The update transient object.
	 * @return object The modified transient object.
	 */
	public function one_click_images_add_update_icon( $transient ) {
		// Ensure we have a transient object to work with.
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		if ( isset( $transient->response['one-click-images/one-click-images.php'] ) ) {
			// Add the custom icon URL to the plugin data.
			$transient->response['one-click-images/one-click-images.php']->icons = array(
				'default' => plugins_url( 'assets/icon.png', __FILE__ ),
				'1x'      => plugins_url( 'assets/icon.png', __FILE__ ),
				'2x'      => plugins_url( 'assets/icon.png', __FILE__ ),
			);
		}

		return $transient;
	}

	/**
	 * AJAX handler to get the license status.
	 *
	 * @return void
	 */
	public function ajax_get_license_status() {

		// Check for user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'oneclickcontent-images' ) ) );
			return;
		}

		// Get the license key.
		$license_key = get_option( 'oneclick_images_license_key' );

		if ( empty( $license_key ) ) {
			update_option( 'oneclick_images_license_status', 'inactive' );
			wp_send_json_error(
				array(
					'status'  => 'inactive',
					'message' => __( 'License key is missing.', 'oneclickcontent-images' ),
				)
			);
			return;
		}

		// Prepare the request data.
		$request_data = array(
			'license_key' => sanitize_text_field( $license_key ),
			'site_url'    => home_url(),
			'check_type'  => 'status_check',  // Add this to differentiate from validation.
		);

		// Send the request to the API.
		$response = wp_remote_post(
			"{$this->api_url}validate-license",
			array(
				'body'    => wp_json_encode( $request_data ),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array(
					'status'  => 'inactive',
					'message' => __( 'Unable to verify license status.', 'oneclickcontent-images' ),
				)
			);
			return;
		}

		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		// Important: Always treat non-success as inactive.
		if ( JSON_ERROR_NONE !== json_last_error()
			|| ! isset( $data['status'] )
			|| 'success' !== $data['status'] ) {
			update_option( 'oneclick_images_license_status', 'inactive' );
			wp_send_json_success(
				array(
					'status'  => 'inactive',
					'message' => __( 'License is inactive or invalid.', 'oneclickcontent-images' ),
				)
			);
			return;
		}

		update_option( 'oneclick_images_license_status', 'active' );
		wp_send_json_success(
			array(
				'status'  => 'active',
				'message' => __( 'License is active and valid.', 'oneclickcontent-images' ),
			)
		);
	}

	/**
	 * AJAX handler to validate the license.
	 *
	 * @return void
	 */
	public function ajax_validate_license() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'oneclickcontent-images' ) ) );
			return;
		}

		$license_key = get_option( 'oneclick_images_license_key' );
		if ( empty( $license_key ) ) {
			update_option( 'oneclick_images_license_status', 'inactive' );
			wp_send_json_error(
				array(
					'status'  => 'inactive',
					'message' => __( 'License key is missing.', 'oneclickcontent-images' ),
				)
			);
			return;
		}

		$request_data = array(
			'license_key' => sanitize_text_field( $license_key ),
			'site_url'    => home_url(),
			'check_type'  => 'explicit_validation',  // Add this to differentiate from status check.
		);

		$response = wp_remote_post(
			"{$this->api_url}validate-license",
			array(
				'body'    => wp_json_encode( $request_data ),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			update_option( 'oneclick_images_license_status', 'inactive' );
			wp_send_json_error(
				array(
					'status'  => 'inactive',
					'message' => __( 'Unable to validate license.', 'oneclickcontent-images' ),
				)
			);
			return;
		}

		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		// Important: Always treat non-success as inactive.
		if ( JSON_ERROR_NONE !== json_last_error()
			|| ! isset( $data['status'] )
			|| 'success' !== $data['status'] ) {
			update_option( 'oneclick_images_license_status', 'inactive' );
			wp_send_json_success(
				array(
					'status'  => 'inactive',
					'message' => __( 'License is inactive or invalid.', 'oneclickcontent-images' ),
				)
			);
			return;
		}

		update_option( 'oneclick_images_license_status', 'active' );
		wp_send_json_success(
			array(
				'status'  => 'active',
				'message' => __( 'License validated successfully.', 'oneclickcontent-images' ),
			)
		);
	}

	/**
	 * Handles the AJAX request to fetch usage details.
	 */
	public function oneclick_images_ajax_check_usage() {
		// Verify nonce for security.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'oneclick_images_ajax_nonce' ) ) {
			wp_send_json_error( array( 'error' => 'Invalid nonce.' ) );
		}

		// Get license key and origin URL.
		$license_key = get_option( 'oneclick_images_license_key', '' );
		$origin_url  = home_url();

		if ( '' === $license_key ) {
			wp_send_json_error( array( 'error' => 'License key is missing.' ) );
		}

		// Prepare the API request.
		$response = wp_remote_post(
			'https://oneclickcontent.com/wp-json/subscriber/v1/check-usage',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'license_key' => $license_key,
						'origin_url'  => $origin_url,
					)
				),
				'timeout' => 20,
			)
		);

		// Handle errors from the API request.
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'error' => $response->get_error_message() ) );
		}

		$response_body = wp_remote_retrieve_body( $response );
		$response_code = wp_remote_retrieve_response_code( $response );

		// Decode the JSON response.
		$decoded_response = json_decode( $response_body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			wp_send_json_error( array( 'error' => 'Invalid response from server.' ) );
		}

		// Check for errors in the response.
		if ( 200 !== $response_code || isset( $decoded_response['error'] ) ) {
			$error_message = isset( $decoded_response['error'] ) ? $decoded_response['error'] : 'Unknown error occurred.';
			wp_send_json_error( array( 'error' => $error_message ) );
		}

		// Send back the successful response.
		wp_send_json_success( $decoded_response );
	}

	/**
	 * Add custom plugin data to the plugin information popup.
	 *
	 * @param object $res Existing plugin information object.
	 * @param string $action Current action (e.g., 'plugin_information').
	 * @param array  $args Arguments passed to the API call.
	 * @return object Modified plugin information object.
	 */
	public function oneclickcontent_plugin_popup_info( $res, $action, $args ) {
		// Check if the plugin slug matches.
		if ( 'plugin_information' !== $action || 'one-click-images' !== $args->slug ) {
			return $res;
		}

		// Fetch transient data.
		$transient = get_site_transient( 'update_plugins' );

		if ( isset( $transient->response['one-click-images/one-click-images.php'] ) ) {
			$plugin_data = $transient->response['one-click-images/one-click-images.php'];

			// Populate the plugin details from the transient.
			$res = (object) array(
				'name'          => $plugin_data->name ?? 'One Click Images',
				'slug'          => $plugin_data->slug ?? 'one-click-images',
				'version'       => $plugin_data->new_version ?? '1.0.0',
				'author'        => $plugin_data->author ?? '<a href="https://oneclickcontent.com">OneClickContent</a>',
				'homepage'      => $plugin_data->homepage ?? 'https://oneclickcontent.com',
				'requires'      => $plugin_data->requires ?? '7.4',
				'tested'        => $plugin_data->tested ?? '7.4',
				'requires_php'  => $plugin_data->requires_php ?? '7.4',
				'sections'      => $plugin_data->sections ?? array(),
				'banners'       => $plugin_data->banners ?? array(),
				'download_link' => $plugin_data->package ?? '',
				'last_updated'  => '2024-12-31', // Optional, add last updated manually or dynamically.
			);
		} else {
			// Handle case where no transient data is available.
			$res = (object) array(
				'name'        => 'One Click Images',
				'slug'        => 'one-click-images',
				'description' => '<p>Plugin information is currently unavailable.</p>',
			);
		}

		return $res;
	}
}
