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

defined( 'ABSPATH' ) || exit;

/**
 * Class Occidg_Admin_Settings
 *
 * Manages the admin settings page for the OneClickContent Image Details plugin,
 * including metadata generation via the OneClickContent API.
 *
 * @since 1.0.0
 */
class Occidg_Admin_Settings {

	/**
	 * Display admin notices for settings errors or updates.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Display any queued settings errors or notices.
		settings_errors( 'occidg_messages' );
	}

	/**
	 * Register plugin settings and add settings fields.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function occidg_register_settings() {
		$option_group = 'occidg_settings';

		register_setting(
			$option_group,
			'occidg_provider',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_provider' ),
				'default'           => 'openai',
			)
		);

		register_setting(
			$option_group,
			'occidg_openai_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			$option_group,
			'occidg_openai_model',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'gpt-4o-mini',
			)
		);

		register_setting(
			$option_group,
			'occidg_gemini_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			$option_group,
			'occidg_gemini_model',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'gemini-1.5-flash',
			)
		);

		register_setting(
			$option_group,
			'occidg_auto_add_details',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			$option_group,
			'occidg_metadata_fields',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'occidg_sanitize_metadata_fields' ),
				'default'           => array(
					'title'       => '0',
					'description' => '0',
					'alt_text'    => '0',
					'caption'     => '0',
				),
			)
		);

		register_setting(
			$option_group,
			'occidg_override_metadata',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);

		register_setting(
			$option_group,
			'occidg_language',
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
			'occidg_metadata_section',
			__( 'Select Metadata Fields to Replace', 'occidg' ),
			array( $this, 'occidg_metadata_section_callback' ),
			'occidg_settings'
		);

		add_settings_field(
			'occidg_metadata_fields',
			__( 'Metadata Fields', 'occidg' ),
			array( $this, 'occidg_metadata_fields_callback' ),
			'occidg_settings',
			'occidg_metadata_section'
		);

		add_settings_section(
			'occidg_provider_section',
			__( 'AI Provider Settings', 'occidg' ),
			array( $this, 'occidg_provider_section_callback' ),
			'occidg_settings'
		);

		add_settings_field(
			'occidg_provider',
			__( 'Provider', 'occidg' ),
			array( $this, 'occidg_provider_callback' ),
			'occidg_settings',
			'occidg_provider_section',
			array( 'label_for' => 'occidg_provider' )
		);

		add_settings_field(
			'occidg_openai_api_key',
			__( 'OpenAI API Key', 'occidg' ),
			array( $this, 'occidg_openai_api_key_callback' ),
			'occidg_settings',
			'occidg_provider_section',
			array( 'label_for' => 'occidg_openai_api_key' )
		);

		add_settings_field(
			'occidg_openai_model',
			__( 'OpenAI Model', 'occidg' ),
			array( $this, 'occidg_openai_model_callback' ),
			'occidg_settings',
			'occidg_provider_section',
			array( 'label_for' => 'occidg_openai_model' )
		);

		add_settings_field(
			'occidg_gemini_api_key',
			__( 'Gemini API Key', 'occidg' ),
			array( $this, 'occidg_gemini_api_key_callback' ),
			'occidg_settings',
			'occidg_provider_section',
			array( 'label_for' => 'occidg_gemini_api_key' )
		);

		add_settings_field(
			'occidg_gemini_model',
			__( 'Gemini Model', 'occidg' ),
			array( $this, 'occidg_gemini_model_callback' ),
			'occidg_settings',
			'occidg_provider_section',
			array( 'label_for' => 'occidg_gemini_model' )
		);

		add_settings_section(
			'occidg_settings_section',
			__( 'Metadata Settings', 'occidg' ),
			array( $this, 'occidg_settings_section_callback' ),
			'occidg_settings'
		);

		add_settings_field(
			'occidg_auto_add_details',
			__( 'Auto Add Details on Upload', 'occidg' ),
			array( $this, 'occidg_auto_add_details_callback' ),
			'occidg_settings',
			'occidg_settings_section',
			array( 'label_for' => 'occidg_auto_add_details' )
		);

		add_settings_field(
			'occidg_override_metadata',
			__( 'Override Existing Details', 'occidg' ),
			array( $this, 'occidg_override_metadata_callback' ),
			'occidg_settings',
			'occidg_settings_section',
			array( 'label_for' => 'occidg_override_metadata' )
		);

		add_settings_field(
			'occidg_language',
			__( 'Language', 'occidg' ),
			array( $this, 'occidg_language_callback' ),
			'occidg_settings',
			'occidg_settings_section',
			array( 'label_for' => 'occidg_language' )
		);
	}

	/**
	 * Callback for the Language dropdown field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function occidg_language_callback() {
		$languages = array(
			'en' => __( 'English', 'occidg' ),
			'es' => __( 'Spanish', 'occidg' ),
			'fr' => __( 'French', 'occidg' ),
			'de' => __( 'German', 'occidg' ),
			'it' => __( 'Italian', 'occidg' ),
			'zh' => __( 'Chinese', 'occidg' ),
			'ja' => __( 'Japanese', 'occidg' ),
			'pt' => __( 'Portuguese', 'occidg' ),
			'ru' => __( 'Russian', 'occidg' ),
			'ar' => __( 'Arabic', 'occidg' ),
			'ko' => __( 'Korean', 'occidg' ),
			'nl' => __( 'Dutch', 'occidg' ),
			'tr' => __( 'Turkish', 'occidg' ),
			'pl' => __( 'Polish', 'occidg' ),
			'sv' => __( 'Swedish', 'occidg' ),
			'hi' => __( 'Hindi', 'occidg' ),
		);

		$selected_language = get_option( 'occidg_language', 'en' );

		echo '<select id="occidg_language" name="occidg_language">';
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
	public function occidg_metadata_section_callback() {
		echo '<p>' . esc_html__( 'Select which metadata fields you want to automatically generate and replace for images.', 'occidg' ) . '</p>';
	}

	/**
	 * Callback to render the Override Existing Metadata checkbox.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function occidg_override_metadata_callback() {
		$checked       = get_option( 'occidg_override_metadata', false );
		$checked_value = $checked ? 1 : 0;
		?>
		<input type="checkbox" id="occidg_override_metadata" name="occidg_override_metadata" value="1" <?php checked( 1, esc_attr( $checked_value ) ); ?> />
		<p class="description"><?php esc_html_e( 'Check this box if you want to override existing metadata details when generating new metadata.', 'occidg' ); ?></p>
		<?php
	}

	/**
	 * Callback to display checkboxes for metadata fields selection.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function occidg_metadata_fields_callback() {
		$options = get_option( 'occidg_metadata_fields', array() );
		$fields  = array(
			'title'       => __( 'Title', 'occidg' ),
			'description' => __( 'Description', 'occidg' ),
			'alt_text'    => __( 'Alt Text', 'occidg' ),
			'caption'     => __( 'Caption', 'occidg' ),
		);

		foreach ( $fields as $key => $label ) {
			$checked = ( isset( $options[ $key ] ) && '1' === $options[ $key ] ) ? 'checked="checked"' : '';
			printf(
				'<input type="checkbox" class="metadata-field-checkbox" id="occidg_metadata_fields_%s" name="occidg_metadata_fields[%s]" value="1" %s>',
				esc_attr( $key ),
				esc_attr( $key ),
				esc_attr( $checked )
			);
			printf(
				'<label for="occidg_metadata_fields_%s"> %s</label><br>',
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
	public function occidg_sanitize_metadata_fields( $input ) {
		$fields = array( 'title', 'description', 'alt_text', 'caption' );
		$valid  = array();

		foreach ( $fields as $field ) {
			$valid[ $field ] = isset( $input[ $field ] ) && '1' === $input[ $field ] ? '1' : '0';
		}

		return $valid;
	}

	/**
	 * Callback for the settings section description.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function occidg_settings_section_callback() {
		echo '<p>' . esc_html__( 'Choose how metadata should be generated and applied inside your Media Library.', 'occidg' ) . '</p>';
	}

	/**
	 * Callback for the provider settings section description.
	 *
	 * @since 1.1.16
	 * @return void
	 */
	public function occidg_provider_section_callback() {
		echo '<p>' . esc_html__( 'Use your own API credentials. This plugin supports OpenAI and Gemini and stores your keys locally in WordPress settings.', 'occidg' ) . '</p>';
	}

	/**
	 * Sanitize the selected provider.
	 *
	 * @since 1.1.16
	 * @param string $provider Raw provider value.
	 * @return string
	 */
	public function sanitize_provider( $provider ) {
		$provider = sanitize_text_field( $provider );

		return in_array( $provider, array( 'openai', 'gemini' ), true ) ? $provider : 'openai';
	}

	/**
	 * Render the provider dropdown.
	 *
	 * @since 1.1.16
	 * @return void
	 */
	public function occidg_provider_callback() {
		$provider = get_option( 'occidg_provider', 'openai' );
		?>
		<select id="occidg_provider" name="occidg_provider">
			<option value="openai" <?php selected( $provider, 'openai' ); ?>><?php esc_html_e( 'OpenAI', 'occidg' ); ?></option>
			<option value="gemini" <?php selected( $provider, 'gemini' ); ?>><?php esc_html_e( 'Gemini', 'occidg' ); ?></option>
		</select>
		<p class="description"><?php esc_html_e( 'Choose which provider OCCIDG should use when generating metadata.', 'occidg' ); ?></p>
		<?php
	}

	/**
	 * Callback for the Auto Add Details on Upload checkbox.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function occidg_auto_add_details_callback() {
		$checked = get_option( 'occidg_auto_add_details', false );
		?>
		<input type="checkbox" id="occidg_auto_add_details" name="occidg_auto_add_details" value="1" <?php checked( 1, $checked ); ?> />
		<p class="description"><?php esc_html_e( 'Automatically generate and add metadata details when images are added to the Media Library.', 'occidg' ); ?></p>
		<?php
	}

	/**
	 * Render the OpenAI API key field.
	 *
	 * @since 1.1.16
	 * @return void
	 */
	public function occidg_openai_api_key_callback() {
		$value = get_option( 'occidg_openai_api_key', '' );
		?>
		<input type="password" id="occidg_openai_api_key" name="occidg_openai_api_key" value="<?php echo esc_attr( $value ); ?>" class="regular-text" autocomplete="off" />
		<p class="description"><?php esc_html_e( 'Paste your OpenAI API key. It is used only for metadata generation requests from this site.', 'occidg' ); ?></p>
		<?php
	}

	/**
	 * Render the OpenAI model field.
	 *
	 * @since 1.1.16
	 * @return void
	 */
	public function occidg_openai_model_callback() {
		$value = get_option( 'occidg_openai_model', 'gpt-4o-mini' );
		?>
		<input type="text" id="occidg_openai_model" name="occidg_openai_model" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
		<p class="description"><?php esc_html_e( 'Recommended: gpt-4o-mini or another multimodal model that can return structured JSON.', 'occidg' ); ?></p>
		<?php
	}

	/**
	 * Render the Gemini API key field.
	 *
	 * @since 1.1.16
	 * @return void
	 */
	public function occidg_gemini_api_key_callback() {
		$value = get_option( 'occidg_gemini_api_key', '' );
		?>
		<input type="password" id="occidg_gemini_api_key" name="occidg_gemini_api_key" value="<?php echo esc_attr( $value ); ?>" class="regular-text" autocomplete="off" />
		<p class="description"><?php esc_html_e( 'Paste your Gemini API key. OCCIDG will call Gemini directly when Gemini is the selected provider.', 'occidg' ); ?></p>
		<?php
	}

	/**
	 * Render the Gemini model field.
	 *
	 * @since 1.1.16
	 * @return void
	 */
	public function occidg_gemini_model_callback() {
		$value = get_option( 'occidg_gemini_model', 'gemini-1.5-flash' );
		?>
		<input type="text" id="occidg_gemini_model" name="occidg_gemini_model" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
		<p class="description"><?php esc_html_e( 'Recommended: gemini-1.5-flash or another image-capable Gemini model that supports JSON output.', 'occidg' ); ?></p>
		<?php
	}

	/**
	 * Handles saving settings for the OneClickContent Images plugin via AJAX.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function occidg_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'occidg' ) );
			return;
		}

		$nonce = isset( $_POST['_ajax_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ajax_nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'occidg_ajax_nonce' ) ) {
			wp_send_json_error( __( 'Invalid nonce.', 'occidg' ) );
			return;
		}

		if ( ! isset( $_POST['settings'] ) || ! is_array( $_POST['settings'] ) ) {
			wp_send_json_error( __( 'Settings data missing or invalid.', 'occidg' ) );
			return;
		}

		$settings = map_deep( wp_unslash( $_POST['settings'] ), 'sanitize_text_field' );

		$fields          = array( 'title', 'description', 'alt_text', 'caption' );
		$metadata_fields = array_fill_keys( $fields, '0' );

		if ( isset( $settings['occidg_metadata_fields'] ) && is_array( $settings['occidg_metadata_fields'] ) ) {
			foreach ( $fields as $field ) {
				$metadata_fields[ $field ] = isset( $settings['occidg_metadata_fields'][ $field ] ) && '1' === $settings['occidg_metadata_fields'][ $field ] ? '1' : '0';
			}
		}
		update_option( 'occidg_metadata_fields', $metadata_fields );

		$auto_add = isset( $settings['occidg_auto_add_details'] ) && '1' === $settings['occidg_auto_add_details'] ? '1' : '0';
		update_option( 'occidg_auto_add_details', $auto_add );

		$override = isset( $settings['occidg_override_metadata'] ) && '1' === $settings['occidg_override_metadata'] ? '1' : '0';
		update_option( 'occidg_override_metadata', $override );

		$language = isset( $settings['occidg_language'] ) ? $settings['occidg_language'] : 'en';
		update_option( 'occidg_language', $language );

		$provider = isset( $settings['occidg_provider'] ) ? $this->sanitize_provider( $settings['occidg_provider'] ) : 'openai';
		update_option( 'occidg_provider', $provider );

		$openai_api_key = isset( $settings['occidg_openai_api_key'] ) ? $settings['occidg_openai_api_key'] : '';
		update_option( 'occidg_openai_api_key', $openai_api_key );

		$openai_model = isset( $settings['occidg_openai_model'] ) ? $settings['occidg_openai_model'] : 'gpt-4o-mini';
		update_option( 'occidg_openai_model', $openai_model );

		$gemini_api_key = isset( $settings['occidg_gemini_api_key'] ) ? $settings['occidg_gemini_api_key'] : '';
		update_option( 'occidg_gemini_api_key', $gemini_api_key );

		$gemini_model = isset( $settings['occidg_gemini_model'] ) ? $settings['occidg_gemini_model'] : 'gemini-1.5-flash';
		update_option( 'occidg_gemini_model', $gemini_model );

		wp_send_json_success();
	}

	/**
	 * Generate metadata for an image using the OneClickContent API.
	 *
	 * @since 1.0.0
	 * @param int $image_id The ID of the image attachment.
	 * @return array|false The generated metadata on success, or false/an error array on failure.
	 */
	public function occidg_generate_metadata( $image_id ) {
		$selected_fields   = get_option( 'occidg_metadata_fields', array() );
		$override_metadata = get_option( 'occidg_override_metadata', false );

		$image_path = $this->get_custom_image_size_path( $image_id, 'one-click-image-api' );
		if ( ! $image_path || ! file_exists( $image_path ) ) {
			$image_path = get_attached_file( $image_id );
		}

		if ( ! $image_path || ! file_exists( $image_path ) ) {
			Occidg_Logger::warning(
				'Metadata generation skipped because the image file could not be resolved.',
				array(
					'image_id' => $image_id,
				)
			);
			return false;
		}

		$generate_metadata = $this->determine_metadata_to_generate( $image_id, $selected_fields, $override_metadata );
		if ( empty( $generate_metadata ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No metadata fields require generation, and "Override Metadata" is disabled.', 'occidg' ),
			);
		}

		$image_data = file_get_contents( $image_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $image_data ) {
			Occidg_Logger::warning(
				'Metadata generation skipped because the image file could not be read.',
				array(
					'image_id'   => $image_id,
					'image_path' => $image_path,
				)
			);
			return false;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Provider APIs accept the image payload encoded as base64.
		$image_base64 = base64_encode( $image_data );
		$image_type   = wp_check_filetype( $image_path )['ext'];

		$provider_metadata = $this->request_provider_metadata( $image_base64, $image_type );
		if ( isset( $provider_metadata['success'] ) && ! $provider_metadata['success'] ) {
			return $provider_metadata;
		}

		$processed_metadata = $this->process_and_save_metadata( $image_id, $provider_metadata, $generate_metadata );
		if ( $processed_metadata ) {
			return array(
				'success'  => true,
				'metadata' => $processed_metadata,
			);
		}

		Occidg_Logger::warning(
			'Metadata processing failed after a successful API response.',
			array(
				'image_id' => $image_id,
			)
		);

		return array(
			'success' => false,
			'error'   => __( 'Metadata processing failed.', 'occidg' ),
		);
	}

	/**
	 * Request metadata from the configured AI provider.
	 *
	 * @since 1.1.16
	 * @param string $image_base64 Base64 encoded image.
	 * @param string $image_type   Detected image extension.
	 * @return array
	 */
	private function request_provider_metadata( $image_base64, $image_type ) {
		$provider = get_option( 'occidg_provider', 'openai' );

		if ( 'gemini' === $provider ) {
			return $this->request_gemini_metadata( $image_base64, $image_type );
		}

		return $this->request_openai_metadata( $image_base64, $image_type );
	}

	/**
	 * Request metadata from OpenAI.
	 *
	 * @since 1.1.16
	 * @param string $image_base64 Base64 encoded image.
	 * @param string $image_type   Detected image extension.
	 * @return array
	 */
	private function request_openai_metadata( $image_base64, $image_type ) {
		$api_key = get_option( 'occidg_openai_api_key', '' );
		$model   = get_option( 'occidg_openai_model', 'gpt-4o-mini' );

		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'error'   => __( 'OpenAI is selected, but no OpenAI API key is configured.', 'occidg' ),
			);
		}

		$messages = $this->prepare_messages_payload( $image_base64, $image_type );
		$body     = array(
			'model'         => $model,
			'messages'      => $messages,
			'functions'     => array( $this->get_function_definition() ),
			'function_call' => array( 'name' => 'generate_image_metadata' ),
			'max_tokens'    => 500,
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 60,
			)
		);

		return $this->decode_provider_response( $response, 'openai' );
	}

	/**
	 * Request metadata from Gemini.
	 *
	 * @since 1.1.16
	 * @param string $image_base64 Base64 encoded image.
	 * @param string $image_type   Detected image extension.
	 * @return array
	 */
	private function request_gemini_metadata( $image_base64, $image_type ) {
		$api_key = get_option( 'occidg_gemini_api_key', '' );
		$model   = get_option( 'occidg_gemini_model', 'gemini-1.5-flash' );

		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Gemini is selected, but no Gemini API key is configured.', 'occidg' ),
			);
		}

		$language_instruction = sprintf(
			'Generate image metadata including title, description, alt_text, and caption for the provided image in %s. Return valid JSON only.',
			$this->get_language_label( get_option( 'occidg_language', 'en' ) )
		);

		$body = array(
			'contents'         => array(
				array(
					'parts' => array(
						array(
							'text' => $language_instruction,
						),
						array(
							'inline_data' => array(
								'mime_type' => 'image/' . $image_type,
								'data'      => $image_base64,
							),
						),
					),
				),
			),
			'generationConfig' => array(
				'temperature'      => 0.4,
				'responseMimeType' => 'application/json',
				'responseSchema'   => array(
					'type'       => 'OBJECT',
					'properties' => array(
						'title'       => array( 'type' => 'STRING' ),
						'description' => array( 'type' => 'STRING' ),
						'alt_text'    => array( 'type' => 'STRING' ),
						'caption'     => array( 'type' => 'STRING' ),
					),
					'required'   => array( 'title', 'description', 'alt_text', 'caption' ),
				),
			),
		);

		$response = wp_remote_post(
			sprintf(
				'https://generativelanguage.googleapis.com/v1beta/models/%1$s:generateContent?key=%2$s',
				rawurlencode( $model ),
				rawurlencode( $api_key )
			),
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 60,
			)
		);

		return $this->decode_provider_response( $response, 'gemini' );
	}

	/**
	 * Decode and normalize a provider response.
	 *
	 * @since 1.1.16
	 * @param array|WP_Error $response Provider HTTP response.
	 * @param string         $provider Provider slug.
	 * @return array
	 */
	private function decode_provider_response( $response, $provider ) {
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: provider name */
					__( 'Failed to communicate with %s.', 'occidg' ),
					ucfirst( $provider )
				),
				'details' => $response->get_error_message(),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: provider name */
					__( 'Invalid response from %s.', 'occidg' ),
					ucfirst( $provider )
				),
				'details' => json_last_error_msg(),
			);
		}

		$metadata = $this->extract_metadata_from_provider_response( $body, $provider );
		if ( false === $metadata ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: provider name */
					__( 'Unable to extract metadata from the %s response.', 'occidg' ),
					ucfirst( $provider )
				),
			);
		}

		return $metadata;
	}

	/**
	 * Extract normalized metadata from a provider response.
	 *
	 * @since 1.1.16
	 * @param array  $data     Provider response body.
	 * @param string $provider Provider slug.
	 * @return array|false
	 */
	private function extract_metadata_from_provider_response( $data, $provider ) {
		$metadata = false;

		if ( 'gemini' === $provider ) {
			$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
			if ( is_string( $text ) && '' !== $text ) {
				$metadata = json_decode( trim( $text ), true );
			}
		} else {
			$arguments = $data['choices'][0]['message']['function_call']['arguments'] ?? '';
			if ( is_string( $arguments ) && '' !== $arguments ) {
				$metadata = json_decode( $arguments, true );
			}
		}

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $metadata ) ) {
			return false;
		}

		return $this->normalize_generated_metadata( $metadata );
	}

	/**
	 * Normalize generated metadata to the expected OCCIDG shape.
	 *
	 * @since 1.1.16
	 * @param array $metadata Raw metadata.
	 * @return array|false
	 */
	private function normalize_generated_metadata( $metadata ) {
		$normalized = array(
			'title'       => isset( $metadata['title'] ) ? sanitize_text_field( $metadata['title'] ) : '',
			'description' => isset( $metadata['description'] ) ? sanitize_textarea_field( $metadata['description'] ) : '',
			'alt_text'    => isset( $metadata['alt_text'] ) ? sanitize_text_field( $metadata['alt_text'] ) : '',
			'caption'     => isset( $metadata['caption'] ) ? sanitize_textarea_field( $metadata['caption'] ) : '',
		);

		foreach ( $normalized as $value ) {
			if ( '' !== $value ) {
				return $normalized;
			}
		}

		return false;
	}

	/**
	 * Fetch (and cache) the free‑trial HMAC salt from our server.
	 *
	 * @return string
	 */
	private function get_trial_salt() {
		$salt = get_transient( 'occidg_trial_salt' );
		if ( $salt ) {
			return $salt;
		}

		$response = wp_remote_post(
			'https://oneclickcontent.com/wp-json/free-trial/v1/get-trial-salt',
			array(
				'timeout' => 10,
				'body'    => array(
					'site_url'    => home_url(),
					'plugin_slug' => 'occidg',
				),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $body['salt'] ) && is_string( $body['salt'] ) ) {
				$salt = sanitize_text_field( $body['salt'] );
				set_transient( 'occidg_trial_salt', $salt, 6 * HOUR_IN_SECONDS );
				return $salt;
			}
		}

		Occidg_Logger::warning( 'Falling back to the local trial salt constant.' );

		// 3) Fallback to whatever constant you defined (or empty)
		return defined( 'OCCIDG_HMAC_SALT' ) ? OCCIDG_HMAC_SALT : '';
	}

	/**
	 * AJAX handler for generating metadata.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_generate_metadata() {
		check_ajax_referer( 'occidg_ajax_nonce', 'nonce' );

		$image_ids = isset( $_POST['image_ids'] )
			? sanitize_text_field( wp_unslash( $_POST['image_ids'] ) )
			: ( isset( $_POST['image_id'] )
				? absint( wp_unslash( $_POST['image_id'] ) )
				: 0 );

		$image_ids = is_string( $image_ids ) ? json_decode( $image_ids, true ) : (int) $image_ids;
		$image_ids = is_array( $image_ids ) ? array_map( 'intval', $image_ids ) : array( (int) $image_ids );

		if ( empty( $image_ids ) || ! is_array( $image_ids ) || $image_ids[0] <= 0 ) {
			wp_send_json_error( __( 'Invalid image ID(s).', 'occidg' ) );
			return;
		}

		$result = $this->occidg_generate_metadata( $image_ids[0] );

		wp_send_json( $result );
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
		$selected_language    = get_option( 'occidg_language', 'en' );
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
	 * @param array $data              The normalized metadata data.
	 * @param array $generate_metadata The metadata fields to save.
	 * @return array|false The saved metadata or false on failure.
	 */
	private function process_and_save_metadata( $image_id, $data, $generate_metadata ) {
		$sanitized_metadata = $this->normalize_generated_metadata( $data );

		if ( false === $sanitized_metadata ) {
			return false;
		}

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
	 * Generate the specified image size in WebP format and ensure it fits
	 * Azure AI Content Safety's 4 MB (Base-64) limit.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $image_id     Attachment ID.
	 * @param string $size         Image size label (kept for API parity).
	 * @param string $output_path  Destination file path.
	 * @return bool  True on success, false on failure.
	 */
	private function generate_image_size_as_webp( $image_id, $size, $output_path ) {

		// ---- SETTINGS --------------------------------------------------------- //
		$target_bytes = 3 * 1024 * 1024; // 3 MB on disk  → <4 MB after Base-64.
		$min_quality  = 60;              // Do not go below this.
		$quality_step = 5;               // Decrease quality in 5-point steps.
		$scale_factor = 0.9;             // When quality floor reached, shrink width by 10 %.

		// ---- LOAD ORIGINAL ---------------------------------------------------- //
		$image_path = get_attached_file( $image_id );
		if ( ! file_exists( $image_path ) ) {
			return false;
		}

		$orig_editor = wp_get_image_editor( $image_path );
		if ( is_wp_error( $orig_editor ) ) {
			return false;
		}

		// ---- START VALUES ----------------------------------------------------- //
		$current_size = $orig_editor->get_size();
		$width        = $current_size['width'];
		$height       = $current_size['height'];
		$quality      = 90;

		if ( $width > 1024 ) {
			$width  = 1024;
			$height = null; // Preserve aspect ratio.
		}

		// ---- TRY, MEASURE, ADJUST -------------------------------------------- //
		while ( true ) {

			// Always clone the pristine editor to avoid double compression.
			$editor = clone $orig_editor;
			$editor->resize( $width, $height, false );
			$editor->set_quality( $quality );

			$saved = $editor->save( $output_path, 'image/webp' );
			if ( is_wp_error( $saved ) ) {
				return false;
			}

			$filesize = filesize( $output_path );

			// Success.
			if ( $filesize <= $target_bytes ) {
				return true;
			}

			// First, lower quality.
			if ( $quality - $quality_step >= $min_quality ) {
				$quality -= $quality_step;
				continue;
			}

			// Then, reduce width.
			$width   = (int) round( $width * $scale_factor );
			$height  = null;
			$quality = 90; // Reset quality after each down-scale.

			// Fail-safe: stop if the image would get absurdly small.
			if ( $width < 512 ) {
				return false;
			}
		}
	}

	/**
	 * AJAX handler to generate metadata for an image.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function occidg_ajax_generate_metadata() {
		if ( ! check_ajax_referer( 'occidg_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Nonce verification failed.', 'occidg' ) );
			return;
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'occidg' ) );
			return;
		}

		$image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;

		if ( ! $image_id ) {
			wp_send_json_error( __( 'Invalid image ID.', 'occidg' ) );
			return;
		}

		$metadata = $this->occidg_generate_metadata( $image_id );

		if ( is_array( $metadata ) && isset( $metadata['success'] ) ) {
			if ( $metadata['success'] ) {
				wp_send_json_success(
					array(
						'message'  => __( 'Metadata generated successfully.', 'occidg' ),
						'metadata' => $metadata,
					)
				);
			} else {
				wp_send_json_error( $metadata );
			}
		} else {
			wp_send_json_error( __( 'Failed to generate metadata.', 'occidg' ) );
		}
	}

	/**
	 * AJAX handler to refresh the nonce.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function occidg_ajax_refresh_nonce() {
		if ( ! check_ajax_referer( 'occidg_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Nonce verification failed.', 'occidg' ) );
			return;
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'occidg' ) );
			return;
		}

		$new_nonce = wp_create_nonce( 'occidg_ajax_nonce' );

		wp_send_json_success(
			array(
				'nonce' => $new_nonce,
			)
		);
	}
}
