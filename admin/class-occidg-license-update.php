<?php
/**
 * License Verification for the OneClickContent Image Details Plugin
 *
 * Handles license validation and usage tracking via the OneClickContent API.
 * Update checking functionality has been removed as updates are managed by the WordPress Plugin Directory.
 *
 * @package    One_Click_Images
 * @subpackage One_Click_Images/admin
 * @author     OneClickContent <support@oneclickcontent.com>
 * @since      1.0.0
 * @copyright  2025 OneClickContent
 * @license    GPL-2.0+
 * @link       https://oneclickcontent.com
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Occidg_License_Update
 *
 * Manages license validation and usage tracking for the OneClickContent Image Details plugin.
 * Update checking has been removed to comply with WordPress Plugin Directory guidelines.
 *
 * @since 1.0.0
 */
class Occidg_License_Update {

	/**
	 * The License Validation API URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $auth_url;

	/**
	 * The plugin slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Constructor.
	 *
	 * Initializes the class with the license validation API URL and plugin slug.
	 * Update URL is removed as update checking is no longer supported.
	 *
	 * @since 1.0.0
	 * @param string $auth_url    The License Validation API URL.
	 * @param string $plugin_slug The plugin slug.
	 */
	public function __construct( $auth_url, $plugin_slug ) {
		$this->auth_url    = esc_url_raw( $auth_url );
		$this->plugin_slug = sanitize_text_field( $plugin_slug );

		// Register AJAX handlers for license and usage management.
		add_action( 'wp_ajax_occidg_validate_license', array( $this, 'ajax_validate_license' ) );
		add_action( 'wp_ajax_occidg_get_license_status', array( $this, 'ajax_get_license_status' ) );
		add_action( 'wp_ajax_occidg_check_usage', array( $this, 'occidg_ajax_check_usage' ) );
	}

	/**
	 * Validate the plugin license on demand via AJAX.
	 *
	 * Verifies the license key with the OneClickContent API and updates the license status.
	 *
	 * @since 1.0.0
	 * @return void Outputs JSON response with validation result.
	 */
	public function ajax_validate_license() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'occidg' ) ) );
			return;
		}

		$license_key = get_option( 'occidg_license_key', '' );
		if ( empty( $license_key ) ) {
			update_option( 'occidg_license_status', 'inactive' );
			wp_send_json_error(
				array(
					'status'  => 'inactive',
					'message' => __( 'License key is missing.', 'occidg' ),
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
			update_option( 'occidg_license_status', 'inactive' );
			wp_send_json_error(
				array(
					'status'  => 'inactive',
					'message' => __( 'Unable to validate license.', 'occidg' ),
				)
			);
			return;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! isset( $data['status'] ) || 'success' !== $data['status'] ) {
			update_option( 'occidg_license_status', 'inactive' );
			wp_send_json_success(
				array(
					'status'  => 'inactive',
					'message' => __( 'License is inactive or invalid.', 'occidg' ),
				)
			);
			return;
		}

		update_option( 'occidg_license_status', 'active' );
		delete_option( 'occidg_trial_expired' );
		wp_send_json_success(
			array(
				'status'  => 'active',
				'message' => __( 'License validated successfully.', 'occidg' ),
			)
		);
	}

	/**
	 * Get the current license status via AJAX.
	 *
	 * Retrieves and verifies the license status with the OneClickContent API.
	 *
	 * @since 1.0.0
	 * @return void Outputs JSON response with license status.
	 */
	public function ajax_get_license_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'occidg' ) ) );
			return;
		}

		$license_key = get_option( 'occidg_license_key', '' );
		if ( empty( $license_key ) ) {
			update_option( 'occidg_license_status', 'inactive' );
			wp_send_json_error(
				array(
					'status'  => 'inactive',
					'message' => __( 'License key is missing.', 'occidg' ),
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
					'message' => __( 'Unable to verify license status.', 'occidg' ),
				)
			);
			return;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! isset( $data['status'] ) || 'success' !== $data['status'] ) {
			update_option( 'occidg_license_status', 'inactive' );
			wp_send_json_success(
				array(
					'status'  => 'inactive',
					'message' => __( 'License is inactive or invalid.', 'occidg' ),
				)
			);
			return;
		}

		update_option( 'occidg_license_status', 'active' );
		delete_option( 'occidg_trial_expired' );
		wp_send_json_success(
			array(
				'status'  => 'active',
				'message' => __( 'License is active and valid.', 'occidg' ),
			)
		);
	}

	/**
	 * Fetch plugin usage details via AJAX.
	 *
	 * Retrieves usage data from the OneClickContent API to enforce the 15-image limit for free users.
	 *
	 * @since 1.0.0
	 * @return void Outputs JSON response with usage data or an error message.
	 */
	public function occidg_ajax_check_usage() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'occidg_ajax_nonce' ) ) {
			wp_send_json_error( array( 'error' => __( 'Invalid nonce.', 'occidg' ) ) );
			return;
		}

		$license_key = get_option( 'occidg_license_key', '' );
		$origin_url  = esc_url_raw( home_url() );

		if ( '' === $license_key ) {
			wp_send_json_error( array( 'error' => __( 'License key is missing.', 'occidg' ) ) );
			return;
		}

		$request_data = array(
			'license_key'  => sanitize_text_field( $license_key ),
			'origin_url'   => $origin_url,
			'product_slug' => defined( 'OCCIDG_PRODUCT_SLUG' ) ? OCCIDG_PRODUCT_SLUG : 'demo',
		);

		$response = wp_remote_post(
			'https://oneclickcontent.com/wp-json/subscriber/v1/check-usage',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $request_data ),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'error' => $response->get_error_message() ) );
			return;
		}

		$decoded_response = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			wp_send_json_error( array( 'error' => __( 'Invalid response from server.', 'occidg' ) ) );
			return;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) || isset( $decoded_response['error'] ) ) {
			$error_message = isset( $decoded_response['error'] ) ? $decoded_response['error'] : __( 'Unknown error occurred.', 'occidg' );
			wp_send_json_error( array( 'error' => $error_message ) );
			return;
		}

		wp_send_json_success( $decoded_response );
	}
}
