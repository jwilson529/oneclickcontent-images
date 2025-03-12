<?php
/**
 * Admin-specific functionality for the OneClickContent Image Details plugin.
 *
 * Defines admin-specific hooks and functions to enqueue styles, scripts, and add
 * custom functionality to the Media Library.
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
 * Class OneClickContent_Images_Admin
 *
 * Handles admin-specific functionality for the OneClickContent Image Details plugin,
 * including enqueuing scripts/styles and adding Media Library enhancements.
 *
 * @since 1.0.0
 */
class OneClickContent_Images_Admin {

	/**
	 * The name of the plugin.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The version of the plugin.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $version;

	/**
	 * Constructor for the admin class.
	 *
	 * Initializes the plugin name and version.
	 *
	 * @since 1.0.0
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Enqueue admin-specific stylesheets for the plugin.
	 *
	 * Loads CSS files on relevant admin screens (e.g., Media Library, plugin settings).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_styles() {
		$screen = get_current_screen();

		if ( ! $screen instanceof WP_Screen ) {
			return; // Exit early if screen is not available.
		}

		$allowed_screens = array( 'upload', 'post', 'post-new' );
		if ( in_array( $screen->base, $allowed_screens, true ) || 'settings_page_oneclickcontent-images-settings' === $screen->id ) {
			wp_enqueue_style(
				$this->plugin_name,
				plugin_dir_url( __FILE__ ) . 'css/oneclickcontent-images-admin.css',
				array(),
				$this->version,
				'all'
			);
		}
	}

	/**
	 * Enqueue admin-specific JavaScript files for the plugin.
	 *
	 * Loads JavaScript files on relevant admin screens, including jQuery and media library dependencies.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();

		if ( ! $screen instanceof WP_Screen ) {
			return; // Exit early if screen is not available.
		}

		$allowed_screens = array( 'upload', 'post', 'post-new' );
		if ( in_array( $screen->base, $allowed_screens, true ) || 'settings_page_oneclickcontent-images-settings' === $screen->id ) {
			wp_enqueue_script(
				$this->plugin_name,
				plugin_dir_url( __FILE__ ) . 'js/oneclickcontent-images-admin.js',
				array( 'jquery' ),
				$this->version,
				true
			);

			wp_enqueue_script(
				$this->plugin_name . '-error-check',
				plugin_dir_url( __FILE__ ) . 'js/one-click-error-check.js',
				array( 'jquery' ),
				$this->version,
				true
			);

			wp_enqueue_media();

			$selected_fields = get_option( 'oneclick_images_metadata_fields', array() );
			$selected_fields = wp_parse_args(
				$selected_fields,
				array(
					'title'       => false,
					'description' => false,
					'alt_text'    => false,
					'caption'     => false,
				)
			);

			$license_status = get_option( 'oneclick_images_license_status', 'unknown' );

			wp_localize_script(
				$this->plugin_name,
				'oneclick_images_admin_vars',
				array(
					'ajax_url'                   => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
					'oneclick_images_ajax_nonce' => wp_create_nonce( 'oneclick_images_ajax_nonce' ),
					'selected_fields'            => $selected_fields,
					'license_status'             => sanitize_text_field( $license_status ),
				)
			);

			wp_localize_script(
				$this->plugin_name . '-error-check',
				'oneclick_images_error_vars',
				array(
					'ajax_url'                   => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
					'oneclick_images_ajax_nonce' => wp_create_nonce( 'oneclick_images_ajax_nonce' ),
				)
			);
		}
	}

	/**
	 * Add a "Generate Metadata" button to the Media Library attachment details.
	 *
	 * Modifies the Media Library form to include a button for generating metadata.
	 *
	 * @since 1.0.0
	 * @param array   $form_fields An array of attachment form fields.
	 * @param WP_Post $post        The attachment post object.
	 * @return array Modified form fields with the custom button.
	 */
	public function add_generate_metadata_button( $form_fields, $post ) {
		if ( ! preg_match( '/^image\//', $post->post_mime_type ) ) {
			return $form_fields;
		}

		$form_fields['generate_metadata'] = array(
			'label' => __( 'Generate Metadata', 'oneclickcontent-images' ),
			'input' => 'html',
			'html'  => sprintf(
				'<button type="button" class="button" id="generate_metadata_button" data-image-id="%s">%s</button>',
				esc_attr( $post->ID ),
				esc_html__( 'Generate Metadata', 'oneclickcontent-images' )
			),
		);

		return $form_fields;
	}

	/**
	 * Add "Generate Details" bulk action to the Media Library.
	 *
	 * @since 1.0.0
	 * @param array $bulk_actions Existing bulk actions.
	 * @return array Modified bulk actions with "Generate Details" added.
	 */
	public function add_generate_details_bulk_action( $bulk_actions ) {
		$bulk_actions['generate_details'] = __( 'Generate Details', 'oneclickcontent-images' );
		return $bulk_actions;
	}

	/**
	 * Handle the "Generate Details" bulk action in the Media Library.
	 *
	 * Processes selected media items to generate metadata and redirects with a success message.
	 *
	 * @since 1.0.0
	 * @param string $redirect_to The URL to redirect to after processing.
	 * @param string $action      The bulk action being processed.
	 * @param array  $post_ids    Array of selected media item IDs.
	 * @return string Modified redirect URL with success query parameter.
	 */
	public function handle_generate_details_bulk_action( $redirect_to, $action, $post_ids ) {
		if ( 'generate_details' !== $action ) {
			return $redirect_to;
		}

		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'bulk-media' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'oneclickcontent-images' ) );
		}

		if ( ! class_exists( 'OneClickContent_Images_Admin_Settings' ) ) {
			return $redirect_to;
		}

		$admin_settings = new OneClickContent_Images_Admin_Settings();

		foreach ( $post_ids as $post_id ) {
			$admin_settings->oneclick_images_generate_metadata( intval( $post_id ) );
		}

		// Append both the count and a nonce for the generated details.
		$generated_details_nonce = wp_create_nonce( 'generated_details_nonce' );
		$args                    = array(
			'generated_details'       => count( $post_ids ),
			'generated_details_nonce' => $generated_details_nonce,
		);
		return add_query_arg( $args, $redirect_to );
	}

	/**
	 * Display an admin notice after processing the "Generate Details" bulk action.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function generate_details_bulk_action_admin_notice() {
		// Check that both parameters exist.
		if ( ! isset( $_REQUEST['generated_details'], $_REQUEST['generated_details_nonce'] ) ) {
			return;
		}

		// Sanitize and verify the nonce.
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['generated_details_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'generated_details_nonce' ) ) {
			return;
		}

		$count = intval( wp_unslash( $_REQUEST['generated_details'] ) );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d is the number of media items processed */
					__( 'Metadata generated for %d media items.', 'oneclickcontent-images' ),
					$count
				)
			)
		);
	}

	/**
	 * Register a custom image size for the plugin.
	 *
	 * Adds a 500x500 pixel cropped image size for API usage.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function oneclick_register_custom_image_size() {
		add_image_size( 'one-click-image-api', 500, 500, true );
	}

	/**
	 * Add custom image size to the Media Library image size dropdown.
	 *
	 * Allows users to select the 'OCC Image' size when inserting images.
	 *
	 * @since 1.0.0
	 * @param array $sizes Existing image sizes.
	 * @return array Modified list of image sizes.
	 */
	public function oneclick_add_custom_image_sizes( $sizes ) {
		return array_merge(
			$sizes,
			array(
				'one-click-image-api' => __( 'OCC Image', 'oneclickcontent-images' ),
			)
		);
	}

	/**
	 * Fetch the thumbnail URL for a given image ID via AJAX.
	 *
	 * @since 1.0.0
	 * @return void Outputs JSON response with the thumbnail URL or an error message.
	 */
	public function get_thumbnail() {
		// Verify nonce for security. The AJAX request should include the 'oneclick_images_ajax_nonce' parameter.
		$nonce = isset( $_GET['oneclick_images_ajax_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['oneclick_images_ajax_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'oneclick_images_ajax_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'oneclickcontent-images' ) ) );
			return;
		}

		if ( ! isset( $_GET['image_id'] ) || ! is_numeric( $_GET['image_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid image ID.', 'oneclickcontent-images' ) ) );
			return;
		}

		$image_id      = intval( $_GET['image_id'] );
		$thumbnail_url = wp_get_attachment_thumb_url( $image_id );

		if ( $thumbnail_url ) {
			wp_send_json_success( array( 'thumbnail' => esc_url_raw( $thumbnail_url ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Thumbnail not found.', 'oneclickcontent-images' ) ) );
		}
	}

	/**
	 * Redirect to the settings page after plugin activation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function activation_redirect() {
		if ( ! get_option( 'oneclick_images_activation_redirect', false ) ) {
			return;
		}

		delete_option( 'oneclick_images_activation_redirect' );

		if ( ! is_network_admin() && ! wp_doing_ajax() && ! wp_doing_cron() && ! defined( 'REST_REQUEST' ) ) {
			wp_safe_redirect( admin_url( 'options-general.php?page=oneclickcontent-images-settings' ) );
			exit;
		}
	}
}
