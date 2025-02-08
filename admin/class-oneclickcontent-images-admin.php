<?php
/**
 * Admin-specific functionality of the plugin.
 *
 * This class defines all the admin-specific hooks and functions that handle
 * enqueueing admin styles, scripts, and adding custom functionality to the Media Library.
 *
 * @link       https://oneclickcontent.com
 * @since      1.0.0
 * @package    One_Click_Images
 * @subpackage One_Click_Images/admin
 */

/**
 * Class One_Click_Images_Admin
 *
 * Admin-specific functionality of the OCC Images plugin.
 *
 * @since 1.0.0
 */
class One_Click_Images_Admin {

	/**
	 * The name of the plugin.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The version of the plugin.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor for the admin class.
	 *
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}


	/**
	 * Enqueue the admin-specific stylesheets for the plugin.
	 *
	 * This method ensures the admin area has the necessary CSS files
	 * loaded for any custom styles used by the plugin.
	 */
	public function enqueue_styles() {
		$screen = get_current_screen();

		// Load styles only on relevant screens or the plugin's settings page.
		if (
			in_array( $screen->base, array( 'upload', 'post', 'post-new' ), true ) ||
			( 'settings_page_oneclickcontent-images-settings' === $screen->id )
		) {
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
	 * Enqueue the admin-specific JavaScript files for the plugin.
	 *
	 * This method ensures the admin area has the necessary JavaScript files loaded,
	 * including any necessary libraries like jQuery and the WordPress media library.
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();

		// Load scripts only on relevant screens or the plugin's settings page.
		if (
			in_array( $screen->base, array( 'upload', 'post', 'post-new' ), true ) ||
			( 'settings_page_oneclickcontent-images-settings' === $screen->id )
		) {
			// Enqueue the plugin's admin JavaScript file.
			wp_enqueue_script(
				$this->plugin_name,
				plugin_dir_url( __FILE__ ) . 'js/oneclickcontent-images-admin.js',
				array( 'jquery' ),
				$this->version,
				true
			);

			// Enqueue the error-check script.
			wp_enqueue_script(
				$this->plugin_name . '-error-check',
				plugin_dir_url( __FILE__ ) . 'js/one-click-error-check.js',
				array( 'jquery' ),
				$this->version,
				true
			);

			// Enqueue the WordPress media library.
			wp_enqueue_media();

			// Get the user-selected metadata fields from plugin settings.
			$selected_fields = get_option( 'oneclick_images_metadata_fields', array() );

			// Ensure that the selected fields always exist in the expected format.
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

			// Localize scripts with necessary data.
			wp_localize_script(
				$this->plugin_name,
				'oneclick_images_admin_vars',
				array(
					'ajax_url'                   => admin_url( 'admin-ajax.php' ),
					'oneclick_images_ajax_nonce' => wp_create_nonce( 'oneclick_images_ajax_nonce' ),
					'selected_fields'            => $selected_fields,
					'license_status'             => $license_status,
				)
			);

			wp_localize_script(
				$this->plugin_name . '-error-check',
				'oneclick_images_error_vars',
				array(
					'ajax_url'                   => admin_url( 'admin-ajax.php' ),
					'oneclick_images_ajax_nonce' => wp_create_nonce( 'oneclick_images_ajax_nonce' ),
				)
			);
		}
	}

	/**
	 * Add a custom "Generate Metadata" button to the Media Library's attachment details.
	 *
	 * This method modifies the Media Library form to include a button that allows
	 * users to generate metadata for the selected image.
	 *
	 * @param array   $form_fields An array of attachment form fields.
	 * @param WP_Post $post        The WP_Post attachment object.
	 * @return array Modified form fields including the custom button.
	 */
	public function add_generate_metadata_button( $form_fields, $post ) {
		// Only show the "Generate Metadata" button for images.
		if ( ! preg_match( '/^image\//', $post->post_mime_type ) ) {
			return $form_fields;
		}

		// Add the custom "Generate Metadata" button to the Media Library.
		$form_fields['generate_metadata'] = array(
			'label' => __( 'Generate Metadata', 'oneclickcontent-images' ),
			'input' => 'html',
			'html'  => '<button type="button" class="button" id="generate_metadata_button" data-image-id="' . esc_attr( $post->ID ) . '">' . esc_html__( 'Generate Metadata', 'oneclickcontent-images' ) . '</button>',
		);

		return $form_fields;
	}


	/**
	 * Add the "Generate Details" bulk action to the Media Library.
	 *
	 * @param array $bulk_actions Existing bulk actions.
	 * @return array Modified bulk actions.
	 */
	public function add_generate_details_bulk_action( $bulk_actions ) {
		$bulk_actions['generate_details'] = __( 'Generate Details', 'oneclickcontent-images' );
		return $bulk_actions;
	}

	/**
	 * Handle the "Generate Details" bulk action.
	 *
	 * @param string $redirect_to URL to redirect to after processing.
	 * @param string $action The bulk action being processed.
	 * @param array  $post_ids Array of selected media item IDs.
	 * @return string Modified redirect URL with query parameters.
	 */
	public function handle_generate_details_bulk_action( $redirect_to, $action, $post_ids ) {
		if ( 'generate_details' !== $action ) {
			return $redirect_to;
		}

		// Check if the nonce exists and is valid.
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'bulk-media' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'oneclickcontent-images' ) );
		}

		// Ensure the class exists.
		if ( ! class_exists( 'One_Click_Images_Admin_Settings' ) ) {
			return $redirect_to; // Fail gracefully if the class is not loaded.
		}

		$admin_settings = new One_Click_Images_Admin_Settings();

		// Process each selected media item.
		foreach ( $post_ids as $post_id ) {
			// Sanitize post ID and call the existing method to generate metadata.
			$admin_settings->oneclick_images_generate_metadata( intval( $post_id ) );
		}

		// Add a query parameter to the redirect URL to indicate success.
		$redirect_to = add_query_arg( 'generated_details', count( $post_ids ), $redirect_to );
		return $redirect_to;
	}

	/**
	 * Display an admin notice after processing the bulk action.
	 */
	public function generate_details_bulk_action_admin_notice() {
		// Check if the 'generated_details' parameter exists and is valid.
		if ( isset( $_REQUEST['generated_details'] ) ) {
			// Sanitize the value to ensure it's a safe integer.
			$count = intval( wp_unslash( $_REQUEST['generated_details'] ) );

			// Display the success notice.
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d is the number of media items processed. */
						__( 'Metadata generated for %d media items.', 'oneclickcontent-images' ),
						$count
					)
				)
			);
		}
	}

	/**
	 * Register a custom image size for the OCC plugin.
	 *
	 * This function registers a custom image size with the dimensions 500x500 pixels.
	 *
	 * @return void
	 */
	public function oneclick_register_custom_image_size() {
		add_image_size( 'one-click-image-api', 500, 500, true ); // 500x500 pixels, cropped.
	}

	/**
	 * Add custom image size to the Media Library image size dropdown.
	 *
	 * This function adds the custom image size to the dropdown menu in the Media Library,
	 * allowing users to select the 'OCC Image' size when inserting images into content.
	 *
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
	 * Handles fetching the thumbnail URL for a given image ID via AJAX.
	 *
	 * @return void Outputs JSON response with the thumbnail URL or an error message.
	 */
	public function get_thumbnail() {
		// Check if 'image_id' is set and is a valid integer.
		if ( isset( $_GET['image_id'] ) && is_numeric( $_GET['image_id'] ) ) {
			$image_id      = intval( $_GET['image_id'] );
			$thumbnail_url = wp_get_attachment_thumb_url( $image_id );

			if ( $thumbnail_url ) {
				wp_send_json_success( array( 'thumbnail' => $thumbnail_url ) );
			} else {
				wp_send_json_error( array( 'message' => 'Thumbnail not found.' ) );
			}
		} else {
			// If 'image_id' is not set or invalid, return an error.
			wp_send_json_error( array( 'message' => 'Invalid image ID.' ) );
		}
	}

	/**
	 * Redirect to settings page after plugin activation.
	 *
	 * @since 1.0.0
	 */
	public function activation_redirect() {
		if ( get_option( 'oneclick_images_activation_redirect', false ) ) {
			delete_option( 'oneclick_images_activation_redirect' );
			if ( ! is_network_admin() && ! wp_doing_ajax() && ! wp_doing_cron() && ! defined( 'REST_REQUEST' ) ) {
				wp_safe_redirect( admin_url( 'options-general.php?page=oneclickcontent-images-settings' ) );
				exit;
			}
		}
	}
}
