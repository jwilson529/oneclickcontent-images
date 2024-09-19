<?php
/**
 * Class Occ_Images_Admin_Settings
 *
 * Manages the admin settings page and handles image metadata generation for the OCC Images plugin.
 *
 * @since 1.0.0
 * @package Occ_Images
 */

class Occ_Images_Admin_Settings {

	/*
	--------------------------------------------*
	 * Public Methods
	 *--------------------------------------------*/

	/**
	 * Register the plugin settings page in the WordPress admin menu.
	 */
	public function occ_images_register_options_page() {
		add_options_page(
			__( 'OCC Images Settings', 'occ-images' ),
			__( 'OCC Images', 'occ-images' ),
			'manage_options',
			'occ-images-settings',
			array( $this, 'occ_images_options_page' )
		);
	}

	/**
	 * Display the plugin options page.
	 */
	public function occ_images_options_page() {
		?>
		<div id="occ_images" class="wrap">
			<h1><?php esc_html_e( 'OCC Images Settings', 'occ-images' ); ?></h1>
			
			<form method="post" action="options.php">
				<?php
				settings_fields( 'occ_images_settings' );
				do_settings_sections( 'occ_images_settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Display admin notices for settings errors or updates.
	 */
	public function display_admin_notices() {
		settings_errors();
	}

	/**
	 * Register plugin settings and add settings fields.
	 */
	public function occ_images_register_settings() {
		// Register the API key setting with sanitization.
		register_setting(
			'occ_images_settings',
			'occ_images_openai_api_key',
			array(
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// Register the AI model setting with sanitization.
		register_setting(
			'occ_images_settings',
			'occ_images_ai_model',
			array(
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// Add the main settings section.
		add_settings_section(
			'occ_images_settings_section',
			__( 'OCC Images Settings', 'occ-images' ),
			array( $this, 'occ_images_settings_section_callback' ),
			'occ_images_settings'
		);

		// Add the OpenAI API key field.
		add_settings_field(
			'occ_images_openai_api_key',
			__( 'OpenAI API Key', 'occ-images' ),
			array( $this, 'occ_images_openai_api_key_callback' ),
			'occ_images_settings',
			'occ_images_settings_section',
			array( 'label_for' => 'occ_images_openai_api_key' )
		);

		// Add the AI Model selection field.
		add_settings_field(
			'occ_images_ai_model',
			__( 'AI Model', 'occ-images' ),
			array( $this, 'occ_images_ai_model_callback' ),
			'occ_images_settings',
			'occ_images_settings_section',
			array( 'label_for' => 'occ_images_ai_model' )
		);

		// Retrieve the API key.
		$api_key = get_option( 'occ_images_openai_api_key' );

		// Check if the API key is valid and register additional settings if needed.
		if ( ! empty( $api_key ) && self::validate_openai_api_key( $api_key ) ) {
			// Additional settings can be registered here if necessary.
		} else {
			// Display an error message if the API key is invalid.
			add_settings_error(
				'occ_images_openai_api_key',
				'invalid-api-key',
				sprintf(
					__( 'The OpenAI API key is invalid. Please enter a valid API key in the <a href="%s">OCC Images settings</a> to use OCC Images.', 'occ-images' ),
					esc_url( admin_url( 'options-general.php?page=occ-images-settings' ) )
				),
				'error'
			);
		}
	}

	/*
	--------------------------------------------*
	 * Settings Fields Callbacks
	 *--------------------------------------------*/

	/**
	 * Callback for the settings section description.
	 */
	public function occ_images_settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure the settings for the OCC Images plugin.', 'occ-images' ) . '</p>';
	}

	/**
	 * Callback for the OpenAI API key field.
	 */
	public function occ_images_openai_api_key_callback() {
		$value = get_option( 'occ_images_openai_api_key', '' );
		echo '<input type="password" id="occ_images_openai_api_key" name="occ_images_openai_api_key" value="' . esc_attr( $value ) . '" />';
		echo '<p class="description">' . wp_kses_post( __( 'Get your OpenAI API Key <a href="https://platform.openai.com/signup/">here</a>.', 'occ-images' ) ) . '</p>';
	}

	/**
	 * Callback for the AI Model setting field.
	 */
	public function occ_images_ai_model_callback() {
		$selected_model = get_option( 'occ_images_ai_model', 'gpt-4' );
		$api_key        = get_option( 'occ_images_openai_api_key' );

		if ( ! empty( $api_key ) ) {
			$models = self::validate_openai_api_key( $api_key );

			if ( $models && is_array( $models ) ) {
				echo '<select id="occ_images_ai_model" name="occ_images_ai_model">';
				echo '<option value="gpt-4"' . selected( $selected_model, 'gpt-4o-mini', false ) . '>' . esc_html__( 'Default (gpt-4o-mini)', 'occ-images' ) . '</option>';

				foreach ( $models as $model ) {
					echo '<option value="' . esc_attr( $model ) . '"' . selected( $selected_model, $model, false ) . '>' . esc_html( $model ) . '</option>';
				}
				echo '</select>';
				echo '<p class="description">';
				esc_html_e( 'These models support the function calling ability required to use OCC Images.', 'occ-images' );
				echo '</p>';
			} else {
				echo '<p class="occ-images-alert">';
				esc_html_e( 'Unable to retrieve models. Please check your API key.', 'occ-images' );
				echo '</p>';
			}
		} else {
			echo '<p class="occ-images-alert">';
			esc_html_e( 'Please enter a valid OpenAI API key first.', 'occ-images' );
			echo '</p>';
		}
	}

	/*
	--------------------------------------------*
	 * Helper Methods for OpenAI API Interaction
	 *--------------------------------------------*/

	/**
	 * Validates the OpenAI API key and fetches available models.
	 *
	 * @param string $api_key The API key to validate.
	 * @return array|bool List of models if successful, false otherwise.
	 */
	public static function validate_openai_api_key( $api_key ) {
		if ( empty( $api_key ) ) {
			return false;
		}

		$response = wp_remote_get(
			'https://api.openai.com/v1/models',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Error validating OpenAI API key: ' . $response->get_error_message() );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$models = array_map(
				function ( $model ) {
					return $model['id'];
				},
				$data['data']
			);

			// Optionally, filter models that support function calling or image inputs.
			$supported_models = array_filter(
				$models,
				function ( $model ) {
					// Define your supported models here.
					// For example, 'gpt-4', 'gpt-3.5-turbo', etc.
					$supported = array( 'gpt-4', 'gpt-4-vision', 'gpt-4-turbo', 'gpt-4o', 'gpt-4o-mini' );

					return in_array( $model, $supported, true );
				}
			);

			if ( ! empty( $supported_models ) ) {
				return $supported_models;
			}
		}

		return false;
	}

	/**
	 * Generate metadata for an image using the Chat Completion API.
	 *
	 * @param int $image_id The attachment ID of the image.
	 * @return bool True on success, false on failure.
	 */
	public function occ_images_generate_metadata( $image_id ) {
		error_log( 'Starting occ_images_generate_metadata for image ID ' . $image_id );

		$api_key        = get_option( 'occ_images_openai_api_key' );
		$selected_model = get_option( 'occ_images_ai_model', 'gpt-4' );

		if ( empty( $api_key ) ) {
			error_log( 'API key is empty in occ_images_generate_metadata.' );
			return false;
		}

		// Get the image URL
		$image_url = wp_get_attachment_url( $image_id );

		if ( ! $image_url ) {
			error_log( 'Failed to get image URL for image ID ' . $image_id );
			return false;
		}

		// Prepare the messages array
		$messages = array(
			array(
				'role'    => 'user',
				'content' => 'Generate image metadata including title, description, alt text, and caption for the provided image.',
			),
			array(
				'role'    => 'user',
				'name'    => 'image',
				'content' => $image_url,
			),
		);

		// Define the function for the assistant to use.
		$function_definition = array(
			'name'        => 'generate_image_metadata',
			'description' => 'Generate image metadata including title, description, alt text, and caption based on the provided image.',
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
						'description' => 'Alt text for accessibility purposes.',
					),
					'caption'     => array(
						'type'        => 'string',
						'description' => 'A caption to display alongside the image.',
					),
				),
				'required'   => array( 'title', 'description', 'alt_text', 'caption' ),
			),
		);

		// Prepare the request body
		$body = array(
			'model'         => $selected_model,
			'messages'      => $messages,
			'functions'     => array( $function_definition ),
			'function_call' => array( 'name' => 'generate_image_metadata' ),
			'max_tokens'    => 500,
		);

		// Make the API request
		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 120, // Adjust as needed
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Error in wp_remote_post in occ_images_generate_metadata: ' . $response->get_error_message() );
			return false;
		}

		$response_body    = wp_remote_retrieve_body( $response );
		$decoded_response = json_decode( $response_body, true );

		if ( isset( $decoded_response['error'] ) ) {
			error_log( 'API error in occ_images_generate_metadata: ' . print_r( $decoded_response['error'], true ) );
			return false;
		}

		// Extract the function call arguments
		if ( isset( $decoded_response['choices'][0]['message']['function_call']['arguments'] ) ) {
			$metadata_json = $decoded_response['choices'][0]['message']['function_call']['arguments'];
			$metadata      = json_decode( $metadata_json, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				error_log( 'Failed to decode metadata JSON: ' . json_last_error_msg() );
				return false;
			}

			error_log( 'Successfully retrieved metadata: ' . print_r( $metadata, true ) );

			// Save the metadata to the image
			update_post_meta( $image_id, '_wp_attachment_image_alt', $metadata['alt_text'] );
			wp_update_post(
				array(
					'ID'           => $image_id,
					'post_title'   => $metadata['title'],
					'post_content' => $metadata['description'],
					'post_excerpt' => $metadata['caption'],
				)
			);

			// Return the metadata
			return $metadata;
		} else {
			error_log( 'No function_call arguments in response: ' . $response_body );
			return false;
		}
	}


	/*
	--------------------------------------------*
	 * AJAX Handlers
	 *--------------------------------------------*/

	/**
	 * AJAX handler to generate metadata for an image.
	 */
	public function occ_images_ajax_generate_metadata() {
		// Check nonce and user permissions.
		if ( ! check_ajax_referer( 'occ_images_ajax_nonce', 'nonce', false ) ) {
			error_log( 'Nonce verification failed in occ_images_ajax_generate_metadata.' );
			wp_send_json_error( 'Nonce verification failed.' );
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			error_log( 'User does not have permission to upload files in occ_images_ajax_generate_metadata.' );
			wp_send_json_error( 'Permission denied.' );
		}

		// Get the image ID from the request.
		$image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;

		if ( ! $image_id ) {
			error_log( 'Invalid image ID in occ_images_ajax_generate_metadata.' );
			wp_send_json_error( 'Invalid image ID.' );
		}

		// Generate metadata.
		$metadata = $this->occ_images_generate_metadata( $image_id );

		if ( $metadata ) {
			// Send the metadata back in the AJAX response.
			wp_send_json_success(
				array(
					'message'  => 'Metadata generated successfully.',
					'metadata' => $metadata,
				)
			);
		} else {
			error_log( 'Failed to generate metadata for image ID ' . $image_id . ' in occ_images_ajax_generate_metadata.' );
			wp_send_json_error( 'Failed to generate metadata.' );
		}
	}
}
