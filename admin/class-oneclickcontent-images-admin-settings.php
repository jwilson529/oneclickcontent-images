<?php
/**
 * Admin Settings for OneClickContent Image Details Plugin
 *
 * Handles the admin settings page, including generating image metadata using the OneClickContent API.
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
 * Class OneClickContent_Images_Admin_Settings
 *
 * Manages the admin settings page for the OneClickContent Image Details plugin,
 * including metadata generation via the OneClickContent API.
 *
 * @since 1.0.0
 */
class OneClickContent_Images_Admin_Settings {

	/**
	 * Register the plugin settings page in the WordPress admin menu.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function oneclick_images_register_options_page() {
		add_options_page(
			__( 'OneClickContent Image Details Settings', 'oneclickcontent-images' ),
			__( 'OneClickContent Image Details', 'oneclickcontent-images' ),
			'manage_options',
			'oneclickcontent-images-settings',
			array( $this, 'oneclick_images_options_page' )
		);
	}

	/**
	 * Display the plugin options page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function oneclick_images_options_page() {
		$license_status   = get_option( 'oneclick_images_license_status', 'unknown' );
		$header_image_url = plugin_dir_url( __FILE__ ) . 'assets/header-image.webp';

		?>
		<div id="oneclick_images" class="wrap">
			<!-- Jumbotron Header -->
			<div class="jumbotron-wrapper">
				<picture>
					<source srcset="<?php echo esc_url( $header_image_url ); ?>" type="image/webp">
					<img src="<?php echo esc_url( $header_image_url ); ?>" alt="<?php esc_attr_e( 'OneClickContent Image Details', 'oneclickcontent-images' ); ?>">
				</picture>
				<h1 class="jumbotron-title"><?php esc_html_e( 'OneClickContent Image Details Settings', 'oneclickcontent-images' ); ?></h1>
			</div>

			<!-- Settings Form -->
			<form method="post" action="options.php" id="oneclick_images_settings_form">
				<?php
				settings_fields( 'oneclick_images_settings' );
				wp_nonce_field( 'oneclick_images_settings_update', '_wpnonce' );
				do_settings_sections( 'oneclick_images_settings' );
				?>
			</form>

			<!-- Divider -->
			<hr class="settings-divider" />

			<!-- Usage Information Section -->
			<div class="usage-info-section">
				<h2><?php esc_html_e( 'Usage Information', 'oneclickcontent-images' ); ?></h2>

				<?php if ( 'active' !== $license_status ) : ?>
					<p class="free-trial-message">
						<?php esc_html_e( 'You are currently using the free trial version of OneClickContent Images. To unlock full features, please subscribe.', 'oneclickcontent-images' ); ?>
					</p>
					<a href="https://oneclickcontent.com/image-detail-generator/" target="_blank" class="button button-primary">
						<?php esc_html_e( 'Subscribe Now', 'oneclickcontent-images' ); ?>
					</a>
				<?php else : ?>
					<p><?php esc_html_e( 'Track your current usage and remaining image generation allowance.', 'oneclickcontent-images' ); ?></p>
					<div id="usage_status" class="usage-summary">
						<strong id="usage_count"><?php esc_html_e( 'Loading usage data...', 'oneclickcontent-images' ); ?></strong>
						<div class="progress">
							<div 
								id="usage_progress" 
								class="progress-bar bg-success" 
								role="progressbar" 
								aria-valuenow="0" 
								aria-valuemin="0" 
								aria-valuemax="100" 
								style="width: 0%;"
							>
								0%
							</div>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<!-- Divider -->
			<hr class="settings-divider" />

			<!-- Bulk Generate Details Section -->
			<div class="bulk-generate-section">
				<h2><?php esc_html_e( 'Bulk Generate Details for Media Library', 'oneclickcontent-images' ); ?></h2>
				<p><?php esc_html_e( 'Automatically generate details for images in your media library based on your settings.', 'oneclickcontent-images' ); ?></p>
				<button id="bulk_generate_metadata_button" class="button button-primary">
					<?php esc_html_e( 'Generate Details for Media Library', 'oneclickcontent-images' ); ?>
				</button>
				<div id="bulk_generate_status" class="bulk-generate-status">
					<!-- Status messages will appear here -->
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Display admin notices for settings errors or updates.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_admin_notices() {
		$settings_updated = isset( $_GET['settings-updated'] ) ? sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) : '';
		$nonce            = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( current_user_can( 'manage_options' ) && $settings_updated && 'true' === $settings_updated ) {
			// Verify the nonce using the localized nonce action.
			if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'oneclick_images_ajax_nonce' ) ) {
				add_settings_error(
					'oneclick_images_messages',
					'oneclick_images_message',
					__( 'Settings saved.', 'oneclickcontent-images' ),
					'updated'
				);
			}
		}

		settings_errors( 'oneclick_images_messages' );
	}

	/**
	 * Register plugin settings and add settings fields.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function oneclick_images_register_settings() {
		register_setting(
			'oneclick_images_settings',
			'oneclick_images_license_key',
			array(
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'oneclick_images_settings',
			'oneclick_images_ai_model',
			array(
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'oneclick_images_settings',
			'oneclick_images_auto_add_details',
			array(
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);

		register_setting(
			'oneclick_images_settings',
			'oneclick_images_metadata_fields',
			array(
				'sanitize_callback' => array( $this, 'oneclick_images_sanitize_metadata_fields' ),
			)
		);

		register_setting(
			'oneclick_images_settings',
			'oneclick_images_override_metadata',
			array(
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);

		register_setting(
			'oneclick_images_settings',
			'oneclick_images_language',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'en',
			)
		);

		$this->add_settings_sections_and_fields();
	}

	/**
	 * Adds settings sections and fields to the settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function add_settings_sections_and_fields() {
		add_settings_section(
			'oneclick_images_metadata_section',
			__( 'Select Metadata Fields to Replace', 'oneclickcontent-images' ),
			array( $this, 'oneclick_images_metadata_section_callback' ),
			'oneclick_images_settings'
		);

		add_settings_field(
			'oneclick_images_metadata_fields',
			__( 'Metadata Fields', 'oneclickcontent-images' ),
			array( $this, 'oneclick_images_metadata_fields_callback' ),
			'oneclick_images_settings',
			'oneclick_images_metadata_section'
		);

		add_settings_section(
			'oneclick_images_settings_section',
			__( 'OneClickContent Image Details Settings', 'oneclickcontent-images' ),
			array( $this, 'oneclick_images_settings_section_callback' ),
			'oneclick_images_settings'
		);

		add_settings_field(
			'oneclick_images_auto_add_details',
			__( 'Auto Add Details on Upload', 'oneclickcontent-images' ),
			array( $this, 'oneclick_images_auto_add_details_callback' ),
			'oneclick_images_settings',
			'oneclick_images_settings_section',
			array( 'label_for' => 'oneclick_images_auto_add_details' )
		);

		add_settings_field(
			'oneclick_images_override_metadata',
			__( 'Override Existing Details', 'oneclickcontent-images' ),
			array( $this, 'oneclick_images_override_metadata_callback' ),
			'oneclick_images_settings',
			'oneclick_images_settings_section',
			array( 'label_for' => 'oneclick_images_override_metadata' )
		);

		add_settings_field(
			'oneclick_images_language',
			__( 'Language', 'oneclickcontent-images' ),
			array( $this, 'oneclick_images_language_callback' ),
			'oneclick_images_settings',
			'oneclick_images_settings_section',
			array( 'label_for' => 'oneclick_images_language' )
		);

		add_settings_field(
			'oneclick_images_license_key',
			__( 'OneClickContent License Key', 'oneclickcontent-images' ),
			array( $this, 'oneclick_images_license_key_callback' ),
			'oneclick_images_settings',
			'oneclick_images_settings_section',
			array( 'label_for' => 'oneclick_images_license_key' )
		);
	}

	/**
	 * Callback for the Language dropdown field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function oneclick_images_language_callback() {
		$languages = array(
			'en' => __( 'English', 'oneclickcontent-images' ),
			'es' => __( 'Spanish', 'oneclickcontent-images' ),
			'fr' => __( 'French', 'oneclickcontent-images' ),
			'de' => __( 'German', 'oneclickcontent-images' ),
			'it' => __( 'Italian', 'oneclickcontent-images' ),
			'zh' => __( 'Chinese', 'oneclickcontent-images' ),
			'ja' => __( 'Japanese', 'oneclickcontent-images' ),
		);

		$selected_language = get_option( 'oneclick_images_language', 'en' );

		echo '<select id="oneclick_images_language" name="oneclick_images_language">';
		foreach ( $languages as $key => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $key ),
				selected( $selected_language, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Get the label for a language code.
	 *
	 * @since 1.0.0
	 * @param string $language_code The language code.
	 * @return string The human-readable label.
	 */
	private function get_language_label( $language_code ) {
		$languages = array(
			'en' => 'English',
			'es' => 'Spanish',
			'fr' => 'French',
			'de' => 'German',
			'it' => 'Italian',
			'zh' => 'Chinese',
			'ja' => 'Japanese',
		);

		return isset( $languages[ $language_code ] ) ? $languages[ $language_code ] : 'English';
	}

	/**
	 * Callback for the Metadata Fields section description.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function oneclick_images_metadata_section_callback() {
		echo '<p>' . esc_html__( 'Select which metadata fields you want to automatically generate and replace for images.', 'oneclickcontent-images' ) . '</p>';
	}

	/**
	 * Callback to render the Override Existing Metadata checkbox.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function oneclick_images_override_metadata_callback() {
		$checked       = get_option( 'oneclick_images_override_metadata', false );
		$checked_value = $checked ? 1 : 0; // Sanitize to ensure it's a strict 1 or 0.
		?>
		<input type="checkbox" id="oneclick_images_override_metadata" name="oneclick_images_override_metadata" value="1" <?php checked( 1, esc_attr( $checked_value ) ); ?> />
		<p class="description"><?php esc_html_e( 'Check this box if you want to override existing metadata details when generating new metadata.', 'oneclickcontent-images' ); ?></p>
		<?php
	}

	/**
	 * Callback to display checkboxes for metadata fields selection.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function oneclick_images_metadata_fields_callback() {
		$options = get_option( 'oneclick_images_metadata_fields', array() );
		$fields  = array(
			'title'       => __( 'Title', 'oneclickcontent-images' ),
			'description' => __( 'Description', 'oneclickcontent-images' ),
			'alt_text'    => __( 'Alt Text', 'oneclickcontent-images' ),
			'caption'     => __( 'Caption', 'oneclickcontent-images' ),
		);

		foreach ( $fields as $key => $label ) {
			// Retrieve the output of checked() without escaping first.
			$checked = isset( $options[ $key ] ) ? checked( 1, 1, false ) : '';
			// Then explicitly escape it when outputting.
			printf(
				'<input type="checkbox" class="metadata-field-checkbox" id="oneclick_images_metadata_fields_%s" name="oneclick_images_metadata_fields[%s]" value="1" %s />',
				esc_attr( $key ),
				esc_attr( $key ),
				esc_attr( $checked )
			);
			printf(
				'<label for="oneclick_images_metadata_fields_%s"> %s</label><br>',
				esc_attr( $key ),
				esc_html( $label )
			);
		}
	}

	/**
	 * Sanitize the metadata fields array.
	 *
	 * @since 1.0.0
	 * @param array $input The input array.
	 * @return array $valid The sanitized fields array.
	 */
	public function oneclick_images_sanitize_metadata_fields( $input ) {
		$valid  = array();
		$fields = array( 'title', 'description', 'alt_text', 'caption' );

		foreach ( $fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$valid[ $field ] = 1;
			}
		}

		return $valid;
	}

	/**
	 * Callback for the settings section description.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function oneclick_images_settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure the settings for the OneClickContent Image Details plugin.', 'oneclickcontent-images' ) . '</p>';
	}

	/**
	 * Callback for the Auto Add Details on Upload checkbox.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function oneclick_images_auto_add_details_callback() {
		$checked = get_option( 'oneclick_images_auto_add_details', false );
		?>
		<input type="checkbox" id="oneclick_images_auto_add_details" name="oneclick_images_auto_add_details" value="1" <?php checked( 1, $checked ); ?> />
		<p class="description"><?php esc_html_e( 'Automatically generate and add metadata details when images are uploaded.', 'oneclickcontent-images' ); ?></p>
		<?php
	}

	/**
	 * Callback for the License Key field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function oneclick_images_license_key_callback() {
		$value = get_option( 'oneclick_images_license_key', '' );
		?>
		<input type="password" id="oneclick_images_license_key" name="oneclick_images_license_key" value="<?php echo esc_attr( $value ); ?>" />
		<button type="button" id="validate_license_button" class="button button-secondary" style="margin-left: 10px;">
			<?php esc_html_e( 'Validate License', 'oneclickcontent-images' ); ?>
		</button>
		<span id="license_status_label" style="margin-left: 10px; font-weight: bold;"></span>
		<div id="license_status_message" style="margin-top: 10px;"></div>
		<p class="description"><?php echo wp_kses_post( __( 'Get your OneClickContent License Key <a href="https://oneclickcontent.com/" target="_blank">here</a>.', 'oneclickcontent-images' ) ); ?></p>
		<?php
	}

	/**
	 * Handles saving settings for the OneClickContent Images plugin via AJAX.
	 *
	 * @since 1.0.0
	 * @return void Responds with a JSON success or error message.
	 */
	public function oneclick_images_save_settings() {
		// Sanitize and verify nonce.
		$ajax_nonce = isset( $_POST['_ajax_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) ) : '';
		if ( ! $ajax_nonce || ! wp_verify_nonce( $ajax_nonce, 'oneclick_images_ajax_nonce' ) ) {
			wp_send_json_error( __( 'Invalid nonce.', 'oneclickcontent-images' ) );
			return;
		}

		if ( ! isset( $_POST['settings'] ) ) {
			wp_send_json_error( __( 'Settings data missing.', 'oneclickcontent-images' ) );
			return;
		}

		// Sanitize the settings input.
		$settings_input = sanitize_text_field( wp_unslash( $_POST['settings'] ) );
		parse_str( $settings_input, $settings );

		// Sanitize and update options.
		if ( isset( $settings['oneclick_images_metadata_fields'] ) && is_array( $settings['oneclick_images_metadata_fields'] ) ) {
			$sanitized_fields = array_map( 'sanitize_text_field', $settings['oneclick_images_metadata_fields'] );
			update_option( 'oneclick_images_metadata_fields', $sanitized_fields );
		}

		update_option( 'oneclick_images_auto_add_details', isset( $settings['oneclick_images_auto_add_details'] ) ? 1 : 0 );
		update_option( 'oneclick_images_override_metadata', isset( $settings['oneclick_images_override_metadata'] ) ? 1 : 0 );
		update_option( 'oneclick_images_language', sanitize_text_field( $settings['oneclick_images_language'] ) );
		update_option( 'oneclick_images_license_key', sanitize_text_field( $settings['oneclick_images_license_key'] ) );

		wp_send_json_success();
	}

	/**
	 * Generate metadata for an image using the OneClickContent API.
	 *
	 * @since 1.0.0
	 * @param int $image_id The ID of the image attachment.
	 * @return array|false The generated metadata on success, or false/an error array on failure.
	 */
	public function oneclick_images_generate_metadata( $image_id ) {
		$api_key    = get_option( 'oneclick_images_license_key' );
		$remote_url = empty( $api_key )
			? 'https://oneclickcontent.com/wp-json/free-trial/v1/generate-meta'
			: 'https://oneclickcontent.com/wp-json/subscriber/v1/generate-meta';

		$selected_fields   = get_option( 'oneclick_images_metadata_fields', array() );
		$override_metadata = get_option( 'oneclick_images_override_metadata', false );

		$image_path = $this->get_custom_image_size_path( $image_id, 'one-click-image-api' );
		if ( ! $image_path || ! file_exists( $image_path ) ) {
			$image_path = get_attached_file( $image_id );
		}

		if ( ! $image_path || ! file_exists( $image_path ) ) {
			return false;
		}

		$generate_metadata = $this->determine_metadata_to_generate( $image_id, $selected_fields, $override_metadata );
		if ( empty( $generate_metadata ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No metadata fields require generation, and "Override Metadata" is disabled.', 'oneclickcontent-images' ),
			);
		}

		$image_data = file_get_contents( $image_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $image_data ) {
			return false;
		}
		// The following line encodes the image data to base64 for safe transmission.
		// This usage is benign and intentional.
		$image_base64 = base64_encode( $image_data );
		$image_type   = wp_check_filetype( $image_path )['ext'];

		$messages = $this->prepare_messages_payload( $image_base64, $image_type );

		$body = array(
			'messages'      => $messages,
			'functions'     => array( $this->get_function_definition() ),
			'function_call' => array( 'name' => 'generate_image_metadata' ),
			'max_tokens'    => 500,
			'origin_url'    => esc_url_raw( home_url() ),
			'license_key'   => $api_key,
			'product_slug'  => defined( 'OCC_IMAGES_PRODUCT_SLUG' ) ? OCC_IMAGES_PRODUCT_SLUG : 'demo',
		);

		$response = wp_remote_post(
			$remote_url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'api-key'      => $api_key,
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 30, // Reduced from 120 to comply with performance standards.
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			return array(
				'success' => false,
				'error'   => __( 'Failed to communicate with the metadata service.', 'oneclickcontent-images' ),
				'details' => $error_message,
			);
		}

		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$json_error = json_last_error_msg();
			return array(
				'success' => false,
				'error'   => __( 'Invalid response from metadata service.', 'oneclickcontent-images' ),
				'details' => $json_error,
			);
		}

		if ( isset( $data['error'] ) ) {
			return array(
				'success' => false,
				'error'   => $data['error'],
				'limit'   => $data['limit'] ?? null,
				'message' => $data['message'] ?? '',
				'ad_url'  => $data['ad_url'] ?? '',
			);
		}

		$processed_metadata = $this->process_and_save_metadata( $image_id, $data, $generate_metadata );
		if ( $processed_metadata ) {
			return array(
				'success'  => true,
				'metadata' => $processed_metadata,
			);
		}

		return array(
			'success' => false,
			'error'   => __( 'Metadata processing failed.', 'oneclickcontent-images' ),
		);
	}

	/**
	 * Determine which metadata fields need generation.
	 *
	 * @since 1.0.0
	 * @param int   $image_id         The ID of the image attachment.
	 * @param array $selected_fields  The metadata fields selected for generation.
	 * @param bool  $override_metadata Whether to override existing metadata.
	 * @return array Metadata fields that need to be generated.
	 */
	private function determine_metadata_to_generate( $image_id, $selected_fields, $override_metadata ) {
		$generate_metadata = array();

		if ( isset( $selected_fields['alt_text'] ) ) {
			if ( $override_metadata || ! get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ) {
				$generate_metadata['alt_text'] = true;
			}
		}

		if ( isset( $selected_fields['title'] ) ) {
			if ( $override_metadata || ! get_the_title( $image_id ) ) {
				$generate_metadata['title'] = true;
			}
		}

		if ( isset( $selected_fields['description'] ) ) {
			if ( $override_metadata || ! get_post_field( 'post_content', $image_id ) ) {
				$generate_metadata['description'] = true;
			}
		}

		if ( isset( $selected_fields['caption'] ) ) {
			if ( $override_metadata || empty( get_post_field( 'post_excerpt', $image_id ) ) ) {
				$generate_metadata['caption'] = true;
			}
		}

		return $generate_metadata;
	}

	/**
	 * Prepare the messages payload for the API request.
	 *
	 * @since 1.0.0
	 * @param string $image_base64 The base64-encoded image data.
	 * @param string $image_type   The image file type.
	 * @return array The messages payload.
	 */
	private function prepare_messages_payload( $image_base64, $image_type ) {
		$selected_language    = get_option( 'oneclick_images_language', 'en' );
		$language_instruction = sprintf(
			'Generate image metadata including title, description, alt text, and caption for the provided image in %s.',
			$this->get_language_label( $selected_language )
		);

		return array(
			array(
				'role'    => 'user',
				'content' => array(
					array(
						'type' => 'text',
						'text' => $language_instruction,
					),
					array(
						'type'      => 'image_url',
						'image_url' => array(
							'url' => 'data:image/' . $image_type . ';base64,' . $image_base64,
						),
					),
				),
			),
		);
	}

	/**
	 * Get the function definition for metadata generation.
	 *
	 * @since 1.0.0
	 * @return array The function definition.
	 */
	private function get_function_definition() {
		return array(
			'name'        => 'generate_image_metadata',
			'description' => 'Generate image metadata including title, description, alt text, and caption.',
			'parameters'  => array(
				'type'       => 'object',
				'properties' => array(
					'title'       => array(
						'type'        => 'string',
						'description' => 'A concise and descriptive title for the image.',
					),
					'description' => array(
						'type'        => 'string',
						'description' => 'A detailed description of the image content.',
					),
					'alt_text'    => array(
						'type'        => 'string',
						'description' => 'Alt text for accessibility.',
					),
					'caption'     => array(
						'type'        => 'string',
						'description' => 'A caption to display alongside the image.',
					),
				),
				'required'   => array( 'title', 'description', 'alt_text', 'caption' ),
			),
		);
	}

	/**
	 * Process and save the generated metadata.
	 *
	 * @since 1.0.0
	 * @param int   $image_id          The ID of the image attachment.
	 * @param array $data              The API response data.
	 * @param array $generate_metadata The metadata fields to save.
	 * @return array|false The saved metadata or false on failure.
	 */
	private function process_and_save_metadata( $image_id, $data, $generate_metadata ) {
		if ( isset( $data['choices'][0]['message']['function_call']['arguments'] ) ) {
			$metadata_json = $data['choices'][0]['message']['function_call']['arguments'];
			$metadata      = json_decode( $metadata_json, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return false;
			}

			$sanitized_metadata = array_map( 'sanitize_text_field', $metadata ); // Added sanitization for security.

			if ( isset( $generate_metadata['alt_text'] ) ) {
				update_post_meta( $image_id, '_wp_attachment_image_alt', $sanitized_metadata['alt_text'] );
			}

			if ( isset( $generate_metadata['title'] ) ) {
				wp_update_post(
					array(
						'ID'         => $image_id,
						'post_title' => $sanitized_metadata['title'],
					)
				);
			}

			if ( isset( $generate_metadata['description'] ) ) {
				wp_update_post(
					array(
						'ID'           => $image_id,
						'post_content' => $sanitized_metadata['description'],
					)
				);
			}

			if ( isset( $generate_metadata['caption'] ) ) {
				wp_update_post(
					array(
						'ID'           => $image_id,
						'post_excerpt' => $sanitized_metadata['caption'],
					)
				);
			}

			return $sanitized_metadata;
		}

		return false;
	}

	/**
	 * Retrieve the path of the specified image size or generate it in WebP format if missing.
	 *
	 * @since 1.0.0
	 * @param int    $image_id The image ID.
	 * @param string $size     The image size to retrieve.
	 * @return string|false The path to the WebP image, or false if generation fails.
	 */
	private function get_custom_image_size_path( $image_id, $size ) {
		$image_info = wp_get_attachment_image_src( $image_id, $size );

		if ( $image_info && isset( $image_info[0] ) ) {
			$image_path = get_attached_file( $image_id );

			$resized_path = str_replace(
				wp_basename( $image_path ),
				wp_basename( $image_info[0], pathinfo( $image_info[0], PATHINFO_EXTENSION ) ) . 'webp',
				$image_path
			);

			if ( ! file_exists( $resized_path ) ) {
				$generated = $this->generate_image_size_as_webp( $image_id, $size, $resized_path );
				if ( $generated ) {
					return $resized_path;
				}
				return false;
			}

			return $resized_path;
		}

		return false;
	}

	/**
	 * Generate the specified image size in WebP format.
	 *
	 * @since 1.0.0
	 * @param int    $image_id     The image ID.
	 * @param string $size         The image size to generate.
	 * @param string $output_path  The output path for the generated WebP image.
	 * @return bool True on success, false on failure.
	 */
	private function generate_image_size_as_webp( $image_id, $size, $output_path ) {
		$image_path = get_attached_file( $image_id );
		if ( ! file_exists( $image_path ) ) {
			return false;
		}

		$image = wp_get_image_editor( $image_path );
		if ( is_wp_error( $image ) ) {
			return false;
		}

		$resized = $image->resize( 500, 500, true );
		if ( is_wp_error( $resized ) ) {
			return false;
		}

		$image->set_quality( 85 );
		$saved = $image->save( $output_path, 'image/webp' );

		return ! is_wp_error( $saved );
	}

	/**
	 * AJAX handler to generate metadata for an image.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function oneclick_images_ajax_generate_metadata() {
		if ( ! check_ajax_referer( 'oneclick_images_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Nonce verification failed.', 'oneclickcontent-images' ) );
			return;
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'oneclickcontent-images' ) );
			return;
		}

		$image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;
		if ( ! $image_id ) {
			wp_send_json_error( __( 'Invalid image ID.', 'oneclickcontent-images' ) );
			return;
		}

		$metadata = $this->oneclick_images_generate_metadata( $image_id );
		if ( $metadata ) {
			wp_send_json_success(
				array(
					'message'  => __( 'Metadata generated successfully.', 'oneclickcontent-images' ),
					'metadata' => $metadata,
				)
			);
		} else {
			wp_send_json_error( __( 'Failed to generate metadata.', 'oneclickcontent-images' ) );
		}
	}
}