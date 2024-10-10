<?php
/**
 * Plugin Name: OCC Images Admin Settings
 * Description: Settings and completion functionality for OCC Images plugin.
 * Version: 1.0.0
 * Author: OneClickContent
 * Author URI: https://oneclickcontent.com
 * Text Domain: oneclickcontent-images
 *
 * Handles the admin settings page, including generating image metadata using the OpenAI API.
 *
 * @package Occ_Images
 */

/**
 * OCC Images Admin Settings Class.
 *
 * This class handles the admin settings page, including generating image metadata using the OpenAI API.
 *
 * @since 1.0.0
 */
class Occ_Images_Admin_Settings {

	/**
	 * Register the plugin settings page in the WordPress admin menu.
	 *
	 * @return void
	 */
	public function occ_images_register_options_page() {
		add_options_page(
			__( 'OCC Images Settings', 'oneclickcontent-images' ),
			__( 'OCC Images', 'oneclickcontent-images' ),
			'manage_options',
			'oneclickcontent-images-settings',
			array( $this, 'occ_images_options_page' )
		);
	}

	/**
	 * Display the plugin options page.
	 *
	 * @return void
	 */
	public function occ_images_options_page() {
		?>
		<div id="occ_images" class="wrap">
			<h1><?php esc_html_e( 'OCC Images Settings', 'oneclickcontent-images' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'occ_images_settings' );
				do_settings_sections( 'occ_images_settings' );
				submit_button();
				?>
			</form>
			<h2><?php esc_html_e( 'Bulk Generate Metadata for Media Library', 'oneclickcontent-images' ); ?></h2>
			<p><?php esc_html_e( 'Automatically generate metadata for images in your media library based on your settings.', 'oneclickcontent-images' ); ?></p>
			<button id="bulk_generate_metadata_button" class="button button-primary">
				<?php esc_html_e( 'Generate Metadata for Media Library', 'oneclickcontent-images' ); ?>
			</button>
			<div id="bulk_generate_status" style="margin-top: 20px;">
				<!-- Status messages will appear here -->
			</div>
		</div>
		<?php
	}

	/**
	 * Display admin notices for settings errors or updates.
	 *
	 * @return void
	 */
	public function display_admin_notices() {
	    // Check if the current user can manage options and settings are updated.
	    $settings_updated = filter_input( INPUT_GET, 'settings-updated', FILTER_SANITIZE_STRING );

	    if ( current_user_can( 'manage_options' ) && $settings_updated && 'true' === $settings_updated ) {
	        add_settings_error( 'occ_images_messages', 'occ_images_message', __( 'Settings saved.', 'oneclickcontent-images' ), 'updated' );
	    }

	    // Display settings errors or messages.
	    settings_errors( 'occ_images_messages' );
	}




	/**
	 * Register plugin settings and add settings fields.
	 *
	 * @return void
	 */
	public function occ_images_register_settings() {
		// Register the OpenAI API key setting.
		register_setting(
			'occ_images_settings',
			'occ_images_openai_api_key',
			array(
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// Register the AI model setting.
		register_setting(
			'occ_images_settings',
			'occ_images_ai_model',
			array(
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// Register the Auto Add Details on Upload setting.
		register_setting(
			'occ_images_settings',
			'occ_images_auto_add_details',
			array(
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);

		// Register the metadata fields checkboxes.
		register_setting(
			'occ_images_settings',
			'occ_images_metadata_fields',
			array(
				'sanitize_callback' => array( $this, 'occ_images_sanitize_metadata_fields' ),
			)
		);

		// Register the Override Existing Metadata setting.
		register_setting(
		    'occ_images_settings',
		    'occ_images_override_metadata',
		    array(
		        'sanitize_callback' => 'rest_sanitize_boolean',
		    )
		);

		// Add settings sections and fields.
		$this->add_settings_sections_and_fields();
	}

	/**
	 * Adds settings sections and fields to the settings page.
	 *
	 * @return void
	 */
	private function add_settings_sections_and_fields() {
		// Metadata Fields section.
		add_settings_section(
			'occ_images_metadata_section',
			__( 'Select Metadata Fields to Replace', 'oneclickcontent-images' ),
			array( $this, 'occ_images_metadata_section_callback' ),
			'occ_images_settings'
		);

		add_settings_field(
			'occ_images_metadata_fields',
			__( 'Metadata Fields', 'oneclickcontent-images' ),
			array( $this, 'occ_images_metadata_fields_callback' ),
			'occ_images_settings',
			'occ_images_metadata_section'
		);

		// Main settings section.
		add_settings_section(
			'occ_images_settings_section',
			__( 'OCC Images Settings', 'oneclickcontent-images' ),
			array( $this, 'occ_images_settings_section_callback' ),
			'occ_images_settings'
		);

		add_settings_field(
			'occ_images_openai_api_key',
			__( 'OpenAI API Key', 'oneclickcontent-images' ),
			array( $this, 'occ_images_openai_api_key_callback' ),
			'occ_images_settings',
			'occ_images_settings_section',
			array( 'label_for' => 'occ_images_openai_api_key' )
		);

		add_settings_field(
			'occ_images_ai_model',
			__( 'AI Model', 'oneclickcontent-images' ),
			array( $this, 'occ_images_ai_model_callback' ),
			'occ_images_settings',
			'occ_images_settings_section',
			array( 'label_for' => 'occ_images_ai_model' )
		);

		add_settings_field(
			'occ_images_auto_add_details',
			__( 'Auto Add Details on Upload', 'oneclickcontent-images' ),
			array( $this, 'occ_images_auto_add_details_callback' ),
			'occ_images_settings',
			'occ_images_settings_section',
			array( 'label_for' => 'occ_images_auto_add_details' )
		);

		// Add the Override Existing Metadata checkbox field.
		add_settings_field(
		    'occ_images_override_metadata',
		    __( 'Override Existing Metadata', 'oneclickcontent-images' ),
		    array( $this, 'occ_images_override_metadata_callback' ),
		    'occ_images_settings',
		    'occ_images_settings_section',
		    array( 'label_for' => 'occ_images_override_metadata' )
		);
	}

	/**
	 * Callback for the Metadata Fields section description.
	 *
	 * @return void
	 */
	public function occ_images_metadata_section_callback() {
		echo '<p>' . esc_html__( 'Select which metadata fields you want to automatically generate and replace for images.', 'oneclickcontent-images' ) . '</p>';
	}

	/**
	 * Callback to render the Override Existing Metadata checkbox.
	 *
	 * @return void
	 */
	public function occ_images_override_metadata_callback() {
	    $checked = get_option( 'occ_images_override_metadata', false );
	    echo '<input type="checkbox" id="occ_images_override_metadata" name="occ_images_override_metadata" value="1" ' . checked( 1, $checked, false ) . ' />';
	    echo '<p class="description">' . esc_html__( 'Check this box if you want to override existing metadata when generating new metadata.', 'oneclickcontent-images' ) . '</p>';
	}

	/**
	 * Callback to display checkboxes for metadata fields selection.
	 *
	 * @return void
	 */
	public function occ_images_metadata_fields_callback() {
		$options = get_option( 'occ_images_metadata_fields', array() );
		$fields  = array(
			'title'       => __( 'Title', 'oneclickcontent-images' ),
			'description' => __( 'Description', 'oneclickcontent-images' ),
			'alt_text'    => __( 'Alt Text', 'oneclickcontent-images' ),
			'caption'     => __( 'Caption', 'oneclickcontent-images' ),
		);

		foreach ( $fields as $key => $label ) {
			$checked = isset( $options[ $key ] ) ? 'checked' : '';
			echo '<input type="checkbox" id="occ_images_metadata_fields_' . esc_attr( $key ) . '" name="occ_images_metadata_fields[' . esc_attr( $key ) . ']" value="1" ' . esc_attr( $checked ) . ' />';
			echo '<label for="occ_images_metadata_fields_' . esc_attr( $key ) . '"> ' . esc_html( $label ) . '</label><br>';
		}
	}

	/**
	 * Sanitize the metadata fields array.
	 *
	 * @param array $input The input array.
	 * @return array $valid The sanitized fields array.
	 */
	public function occ_images_sanitize_metadata_fields( $input ) {
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
	 * @return void
	 */
	public function occ_images_settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure the settings for the OCC Images plugin.', 'oneclickcontent-images' ) . '</p>';
	}

	/**
	 * Callback for the Auto Add Details on Upload checkbox.
	 *
	 * @return void
	 */
	public function occ_images_auto_add_details_callback() {
		$checked = get_option( 'occ_images_auto_add_details', false );
		echo '<input type="checkbox" id="occ_images_auto_add_details" name="occ_images_auto_add_details" value="1" ' . checked( 1, $checked, false ) . ' />';
		echo '<p class="description">' . esc_html__( 'Automatically generate and add metadata details when images are uploaded.', 'oneclickcontent-images' ) . '</p>';
	}

	/**
	 * Callback for the OpenAI API key field.
	 *
	 * @return void
	 */
	public function occ_images_openai_api_key_callback() {
		$value = get_option( 'occ_images_openai_api_key', '' );
		echo '<input type="password" id="occ_images_openai_api_key" name="occ_images_openai_api_key" value="' . esc_attr( $value ) . '" />';
		echo '<p class="description">' . wp_kses_post( __( 'Get your OpenAI API Key <a href="https://platform.openai.com/signup/">here</a>.', 'oneclickcontent-images' ) ) . '</p>';
	}

	/**
	 * Callback for the AI Model setting field.
	 *
	 * @return void
	 */
	public function occ_images_ai_model_callback() {
		$selected_model = get_option( 'occ_images_ai_model', 'gpt-4' );
		$api_key        = get_option( 'occ_images_openai_api_key' );

		if ( ! empty( $api_key ) ) {
			$models = self::validate_openai_api_key( $api_key );

			if ( $models && is_array( $models ) ) {
				echo '<select id="occ_images_ai_model" name="occ_images_ai_model">';
				echo '<option value="gpt-4o-mini"' . selected( $selected_model, 'gpt-4o-mini', false ) . '>' . esc_html__( 'Default (gpt-4o-mini)', 'oneclickcontent-images' ) . '</option>';

				foreach ( $models as $model ) {
					echo '<option value="' . esc_attr( $model ) . '"' . selected( $selected_model, $model, false ) . '>' . esc_html( $model ) . '</option>';
				}
				echo '</select>';
				echo '<p class="description">' . esc_html__( 'These models support the function calling ability required to use OCC Images.', 'oneclickcontent-images' ) . '</p>';
			} else {
				echo '<p class="oneclickcontent-images-alert">';
				esc_html_e( 'Unable to retrieve models. Please check your API key.', 'oneclickcontent-images' );
				echo '</p>';
			}
		} else {
			echo '<p class="oneclickcontent-images-alert">';
			esc_html_e( 'Please enter a valid OpenAI API key first.', 'oneclickcontent-images' );
		}
	}

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

			// Filter supported models.
			$supported_models = array_filter(
				$models,
				function ( $model ) {
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
	 * Generate metadata for an image using the OpenAI API.
	 *
	 * @param int $image_id The ID of the image attachment.
	 * @return array|false The generated metadata on success, false on failure.
	 */
	public function occ_images_generate_metadata( $image_id ) {
		$api_key = get_option( 'occ_images_openai_api_key' );
		$model   = get_option( 'occ_images_ai_model', 'gpt-4' );

		if ( empty( $api_key ) ) {
			return false;
		}

		// Get the selected fields to generate metadata for.
		$selected_fields = get_option( 'occ_images_metadata_fields', array() );

		// Get the custom 500x500 image size if it exists, otherwise use full size.
		$image_path = $this->get_custom_image_size_path( $image_id, 'occ-image-api' );

		if ( ! $image_path || ! file_exists( $image_path ) ) {
			$image_path = get_attached_file( $image_id );
		}

		if ( ! $image_path || ! file_exists( $image_path ) ) {
			return false;
		}

		// Prepare to check for and generate metadata only for selected fields.
		$generate_metadata = array();

		// Get the setting for overriding existing metadata.
		$override_metadata = get_option( 'occ_images_override_metadata', false );

		// Check if each field is selected in the settings and if we should generate metadata.
		if ( isset( $selected_fields['alt_text'] ) ) {
		    // Generate alt text metadata if the field is empty or override is enabled.
		    if ( $override_metadata || ! get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ) {
		        $generate_metadata['alt_text'] = true;
		    }
		}

		if ( isset( $selected_fields['title'] ) ) {
		    // Generate title metadata if the field is empty or override is enabled.
		    if ( $override_metadata || ! get_the_title( $image_id ) ) {
		        $generate_metadata['title'] = true;
		    }
		}

		if ( isset( $selected_fields['description'] ) ) {
		    // Generate description metadata if the field is empty or override is enabled.
		    if ( $override_metadata || ! get_post_field( 'post_content', $image_id ) ) {
		        $generate_metadata['description'] = true;
		    }
		}

		if ( isset( $selected_fields['caption'] ) ) {
		    // Generate caption metadata if the field is empty or override is enabled.
		    if ( $override_metadata || empty( get_post_field( 'post_excerpt', $image_id ) ) ) {
		        $generate_metadata['caption'] = true;
		    }
		}

		// If no fields need to be generated, skip the metadata generation.
		if ( empty( $generate_metadata ) ) {
		    return false;
		}

		// Encode the image in base64 and prepare for the API request.
		$image_data   = file_get_contents( $image_path );
		$image_base64 = base64_encode( $image_data );
		$image_type   = wp_check_filetype( $image_path )['ext'];

		$messages = array(
			array(
				'role'    => 'user',
				'content' => array(
					array(
						'type' => 'text',
						'text' => 'Generate image metadata including title, description, alt text, and caption for the provided image.',
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

		$function_definition = array(
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

		$body = array(
			'model'         => $model,
			'messages'      => $messages,
			'functions'     => array( $function_definition ),
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
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( isset( $data['error'] ) ) {
			return false;
		}

		if ( isset( $data['choices'][0]['message']['function_call']['arguments'] ) ) {
			$metadata_json = $data['choices'][0]['message']['function_call']['arguments'];
			$metadata      = json_decode( $metadata_json, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return false;
			}

			// Update only the missing metadata fields.
			if ( isset( $generate_metadata['alt_text'] ) ) {
				update_post_meta( $image_id, '_wp_attachment_image_alt', $metadata['alt_text'] );
			}

			if ( isset( $generate_metadata['title'] ) ) {
				wp_update_post(
					array(
						'ID'         => $image_id,
						'post_title' => $metadata['title'],
					)
				);
			}

			if ( isset( $generate_metadata['description'] ) ) {
				wp_update_post(
					array(
						'ID'           => $image_id,
						'post_content' => $metadata['description'],
					)
				);
			}

			if ( isset( $generate_metadata['caption'] ) ) {
				wp_update_post(
					array(
						'ID'           => $image_id,
						'post_excerpt' => $metadata['caption'], // Caption field is stored in post_excerpt.
					)
				);
			}

			return $metadata;
		}

		return false;
	}

	/**
	 * Retrieve the path of the specified image size.
	 *
	 * @param int    $image_id The image ID.
	 * @param string $size The image size to retrieve.
	 * @return string|false The path to the image, or false if not found.
	 */
	private function get_custom_image_size_path( $image_id, $size ) {
		$image_info = wp_get_attachment_image_src( $image_id, $size );
		return ( $image_info && isset( $image_info[0] ) ) ? get_attached_file( $image_id ) : false;
	}




	/**
	 * AJAX handler to generate metadata for an image.
	 */
	public function occ_images_ajax_generate_metadata() {
		// Verify nonce and user permissions.
		if ( ! check_ajax_referer( 'occ_images_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( 'Nonce verification failed.' );
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;
		if ( ! $image_id ) {
			wp_send_json_error( 'Invalid image ID.' );
		}

		$metadata = $this->occ_images_generate_metadata( $image_id );
		if ( $metadata ) {
			wp_send_json_success(
				array(
					'message'  => 'Metadata generated successfully.',
					'metadata' => $metadata,
				)
			);
		} else {
			wp_send_json_error( 'Failed to generate metadata.' );
		}
	}
}
