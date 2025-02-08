<?php
/**
 * One_Click_Images_License_Update Class File
 *
 * Handles retrieval of plugin updates and license verification for the One Click Images plugin.
 * Communicates with the OneClickContent API endpoints for license validation and update checking.
 *
 * @since    1.0.0
 * @package  PostVoice
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class One_Click_Images_License_Update
 *
 * This class verifies the plugin license and checks for updates.
 * It uses separate API endpoints:
 * - License validation: /oneclick/v1/auth/validate-license
 * - Update checking: /oneclick/v1/updates/check-update
 *
 * The class also handles integration with WordPress transients and AJAX responses.
 *
 * @since    1.0.0
 * @package  PostVoice
 */
class One_Click_Images_License_Update {

	/**
	 * The License Validation API URL.
	 *
	 * @var string
	 */
	private $auth_url;

	/**
	 * The Update Check API URL.
	 *
	 * @var string
	 */
	private $update_url;

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
	 * Constructor.
	 *
	 * Initializes the class with separate API URLs for license validation and update checking,
	 * along with the plugin slug and version.
	 *
	 * @param string $auth_url     The License Validation API URL.
	 * @param string $update_url   The Update Check API URL.
	 * @param string $plugin_slug  The plugin slug.
	 * @param string $version      The plugin version.
	 */
	public function __construct( $auth_url, $update_url, $plugin_slug, $version ) {
		$this->auth_url    = esc_url_raw( $auth_url );
		$this->update_url  = esc_url_raw( $update_url );
		$this->plugin_slug = sanitize_text_field( $plugin_slug );
		$this->version     = sanitize_text_field( $version );
	}

	/**
	 * Validate the plugin license on initialization.
	 *
	 * Uses a transient to limit the frequency of license validation requests.
	 *
	 * @return void
	 */
	public function validate_license_on_init() {
		$license_key = get_option( 'oneclick_images_license_key' );
		if ( empty( $license_key ) ) {
			return; // No license key saved, skip validation.
		}

		// Use a transient to avoid frequent API calls.
		if ( false !== get_transient( 'oneclick_images_license_validation' ) ) {
			return;
		}

		$request_data = array(
			'license_key' => sanitize_text_field( $license_key ),
			'site_url'    => esc_url_raw( home_url() ),
		);

		$response = wp_remote_post(
			$this->auth_url,
			array(
				'body'    => wp_json_encode( $request_data ),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return; // Network or other error; fail gracefully.
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$data          = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( json_last_error() !== JSON_ERROR_NONE || 200 !== $response_code ) {
			return; // Failed to validate license.
		}

		if ( isset( $data['status'] ) && 'success' === $data['status'] ) {
			update_option( 'oneclick_images_license_status', 'active' );
			set_transient( 'oneclick_images_license_validation', 'active', DAY_IN_SECONDS );
		} else {
			update_option( 'oneclick_images_license_status', 'inactive' );
			set_transient( 'oneclick_images_license_validation', 'inactive', HOUR_IN_SECONDS );
		}
	}

	/**
	 * Check for plugin updates using transients.
	 *
	 * Sends a request to the update endpoint and caches the result.
	 *
	 * @return void
	 */
	public function check_for_update() {
		// Use cached update data if available.
		$cached_response = get_transient( 'oneclick_images_update_check' );
		if ( false !== $cached_response ) {
			$this->apply_update_to_transient( $cached_response );
			if ( isset( $cached_response['is_updated'] ) && $cached_response['is_updated'] ) {
				delete_transient( 'oneclick_images_update_check' );
			}
			return;
		}

		$license_key = get_option( 'oneclick_images_license_key' );
		if ( empty( $license_key ) ) {
			return; // No license key; abort update check.
		}

		$request_data = array(
			'product_key'     => 'product_6799599b70411',
			'license_key'     => sanitize_text_field( $license_key ),
			'website_url'     => esc_url_raw( home_url() ),
			'current_version' => sanitize_text_field( $this->version ),
		);

		$response = wp_remote_post(
			$this->update_url,
			array(
				'body'    => wp_json_encode( $request_data ),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			// Cache the update response for 24 hours.
			set_transient( 'oneclick_images_update_check', $data, DAY_IN_SECONDS );
			$this->apply_update_to_transient( $data );
			if ( isset( $data['is_updated'] ) && $data['is_updated'] ) {
				delete_transient( 'oneclick_images_update_check' );
			}
		}
	}

	/**
	 * Apply update data to the WordPress plugin update transient.
	 *
	 * @param array $data Update data from the API.
	 * @return void
	 */
	public function apply_update_to_transient( $data ) {
		if ( isset( $data['update_available'] ) && true === $data['update_available'] ) {
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

					// Sanitize sections.
					$sanitized_sections = array();
					if ( ! empty( $plugin_details['sections'] ) && is_array( $plugin_details['sections'] ) ) {
						foreach ( $plugin_details['sections'] as $key => $value ) {
							$sanitized_sections[ $key ] = wp_kses_post( $value );
						}
					}

					// Sanitize banners.
					$sanitized_banners = array();
					if ( ! empty( $plugin_details['banners'] ) && is_array( $plugin_details['banners'] ) ) {
						foreach ( $plugin_details['banners'] as $key => $value ) {
							$sanitized_banners[ $key ] = esc_url_raw( $value );
						}
					}

					$transient->response[ "{$this->plugin_slug}/{$this->plugin_slug}.php" ] = (object) array(
						'slug'         => sanitize_text_field( $plugin_details['slug'] ?? $this->plugin_slug ),
						'plugin'       => sanitize_text_field( "{$this->plugin_slug}/{$this->plugin_slug}.php" ),
						'new_version'  => sanitize_text_field( $data['latest_version'] ?? '1.0.0' ),
						'package'      => esc_url_raw( $data['download_url'] ?? '' ),
						'name'         => sanitize_text_field( $plugin_details['name'] ?? 'One Click Images' ),
						'author'       => sanitize_text_field( $plugin_details['author'] ?? '' ),
						'homepage'     => esc_url_raw( $plugin_details['homepage'] ?? '' ),
						'requires'     => sanitize_text_field( $plugin_details['requires'] ?? '' ),
						'tested'       => sanitize_text_field( $plugin_details['tested'] ?? '' ),
						'requires_php' => sanitize_text_field( $plugin_details['requires_php'] ?? '' ),
						'sections'     => $sanitized_sections,
						'banners'      => $sanitized_banners,
					);

					return $transient;
				}
			);

			// Clear the cached update transient to avoid stale data.
			delete_transient( 'oneclick_images_update_check' );
		}
	}

	/**
	 * Adds a custom icon to the plugin update notification.
	 *
	 * @param object $transient The update transient object.
	 * @return object The modified transient object.
	 */
	public function one_click_images_add_update_icon( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		if ( isset( $transient->response[ "{$this->plugin_slug}/{$this->plugin_slug}.php" ] ) ) {
			$transient->response[ "{$this->plugin_slug}/{$this->plugin_slug}.php" ]->icons = array(
				'default' => esc_url( plugins_url( 'assets/icon.png', __FILE__ ) ),
				'1x'      => esc_url( plugins_url( 'assets/icon.png', __FILE__ ) ),
				'2x'      => esc_url( plugins_url( 'assets/icon.png', __FILE__ ) ),
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
			'site_url'    => esc_url_raw( home_url() ),
			'check_type'  => 'status_check',
		);

		$response = wp_remote_post(
			$this->auth_url,
			array(
				'body'    => wp_json_encode( $request_data ),
				'headers' => array( 'Content-Type' => 'application/json' ),
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

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! isset( $data['status'] ) || 'success' !== $data['status'] ) {
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
	 * AJAX handler to validate the license explicitly.
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
			'site_url'    => esc_url_raw( home_url() ),
			'check_type'  => 'explicit_validation',
		);

		$response = wp_remote_post(
			$this->auth_url,
			array(
				'body'    => wp_json_encode( $request_data ),
				'headers' => array( 'Content-Type' => 'application/json' ),
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

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! isset( $data['status'] ) || 'success' !== $data['status'] ) {
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
	 * AJAX handler to fetch plugin usage details.
	 *
	 * @return void
	 */
	public function oneclick_images_ajax_check_usage() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'oneclick_images_ajax_nonce' ) ) {
			wp_send_json_error( array( 'error' => __( 'Invalid nonce.', 'oneclickcontent-images' ) ) );
		}

		$license_key = get_option( 'oneclick_images_license_key', '' );
		$origin_url  = esc_url_raw( home_url() );

		if ( '' === $license_key ) {
			wp_send_json_error( array( 'error' => __( 'License key is missing.', 'oneclickcontent-images' ) ) );
		}

		$response = wp_remote_post(
			'https://oneclickcontent.local/wp-json/subscriber/v1/check-usage',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'license_key' => sanitize_text_field( $license_key ),
						'origin_url'  => $origin_url,
					)
				),
				'timeout' => 20,
			)
		);
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'error' => $response->get_error_message() ) );
		}

		$decoded_response = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			wp_send_json_error( array( 'error' => __( 'Invalid response from server.', 'oneclickcontent-images' ) ) );
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) || isset( $decoded_response['error'] ) ) {
			$error_message = isset( $decoded_response['error'] ) ? $decoded_response['error'] : __( 'Unknown error occurred.', 'oneclickcontent-images' );
			wp_send_json_error( array( 'error' => $error_message ) );
		}

		wp_send_json_success( $decoded_response );
	}

	/**
	 * AJAX handler to serve plugin update information in the plugin info popup.
	 *
	 * @param object $res    Existing plugin information object.
	 * @param string $action Current action (e.g., 'plugin_information').
	 * @param array  $args   Arguments passed to the API call.
	 * @return object Modified plugin information object.
	 */
	public function oneclickcontent_plugin_popup_info( $res, $action, $args ) {
		if ( 'plugin_information' !== $action || $this->plugin_slug !== $args->slug ) {
			return $res;
		}

		$transient = get_site_transient( 'update_plugins' );
		if ( isset( $transient->response[ "{$this->plugin_slug}/{$this->plugin_slug}.php" ] ) ) {
			$plugin_data = $transient->response[ "{$this->plugin_slug}/{$this->plugin_slug}.php" ];
			$res         = (object) array(
				'name'          => sanitize_text_field( $plugin_data->name ?? 'One Click Images' ),
				'slug'          => sanitize_text_field( $plugin_data->slug ?? 'oneclickcontent-images' ),
				'version'       => sanitize_text_field( $plugin_data->new_version ?? '1.0.0' ),
				'author'        => sanitize_text_field( $plugin_data->author ?? __( 'Unknown Author', 'oneclickcontent-images' ) ),
				'homepage'      => esc_url_raw( $plugin_data->homepage ?? 'https://oneclickcontent.com' ),
				'requires'      => sanitize_text_field( $plugin_data->requires ?? '7.4' ),
				'tested'        => sanitize_text_field( $plugin_data->tested ?? '7.4' ),
				'requires_php'  => sanitize_text_field( $plugin_data->requires_php ?? '7.4' ),
				'sections'      => wp_kses_post( $plugin_data->sections ?? array() ),
				'banners'       => wp_kses_data( $plugin_data->banners ?? array() ),
				'download_link' => esc_url_raw( $plugin_data->package ?? '' ),
				'last_updated'  => sanitize_text_field( date( 'Y-m-d' ) ),
			);
		} else {
			$res = (object) array(
				'name'        => 'One Click Images',
				'slug'        => 'oneclickcontent-images',
				'description' => '<p>' . esc_html__( 'Plugin information is currently unavailable.', 'oneclickcontent-images' ) . '</p>',
			);
		}
		return $res;
	}
}
