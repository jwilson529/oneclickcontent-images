<?php
/**
 * Admin settings for the AI image metadata plugin.
 *
 * Handles the admin settings page, including generating image metadata from
 * configured AI providers.
 *
 * @package    One_Click_Images
 * @subpackage One_Click_Images/admin
 * @author     James Wilson
 * @since      1.0.0
 * @copyright  2025 OneClickContent
 * @license    GPL-2.0+
 * @link       https://github.com/jwilson529/oneclickcontent-images
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Occidg_Admin_Settings
 *
 * Manages the admin settings page for the AI image metadata plugin,
 * including metadata generation via OpenAI and Gemini.
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
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'default'           => '',
			)
		);

		register_setting(
			$option_group,
			'occidg_openai_model',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_openai_model' ),
				'default'           => 'gpt-4o-mini',
			)
		);

		register_setting(
			$option_group,
			'occidg_gemini_api_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_api_key' ),
				'default'           => '',
			)
		);

		register_setting(
			$option_group,
			'occidg_gemini_model',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_gemini_model' ),
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
				'sanitize_callback' => array( $this, 'sanitize_language' ),
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
		$languages = $this->get_supported_languages();

		$selected_language = get_option( 'occidg_language', 'en' );

		echo '<select id="occidg_language" name="occidg_language" class="occidg-select-field">';
		foreach ( $languages as $key => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $key ),
				selected( $selected_language, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Generate metadata in the language that best fits the site or audience you are optimizing for.', 'occidg' ) . '</p>';
	}

	/**
	 * Get the label for a language code.
	 *
	 * @since 1.0.0
	 * @param string $language_code The language code.
	 * @return string The human-readable label.
	 */
	private function get_language_label( $language_code ) {
		$languages = $this->get_supported_languages();

		return isset( $languages[ $language_code ] ) ? $languages[ $language_code ] : $languages['en'];
	}

	/**
	 * Return all supported language options.
	 *
	 * @since 1.1.16
	 * @return array<string, string> Supported language code-to-label map.
	 */
	private function get_supported_languages() {
		return array(
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
		<label class="occidg-toggle-card" for="occidg_override_metadata">
			<input type="checkbox" id="occidg_override_metadata" name="occidg_override_metadata" value="1" <?php checked( 1, esc_attr( $checked_value ) ); ?> />
			<span class="occidg-toggle-copy">
				<span class="occidg-toggle-title"><?php esc_html_e( 'Overwrite existing metadata', 'occidg' ); ?></span>
				<span class="occidg-toggle-description"><?php esc_html_e( 'Replace titles, alt text, descriptions, and captions that already exist on attachments.', 'occidg' ); ?></span>
			</span>
		</label>
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
			'title'       => array(
				'label'       => __( 'Title', 'occidg' ),
				'description' => __( 'Update the attachment title used throughout the Media Library.', 'occidg' ),
			),
			'description' => array(
				'label'       => __( 'Description', 'occidg' ),
				'description' => __( 'Fill the longer attachment description with richer context.', 'occidg' ),
			),
			'alt_text'    => array(
				'label'       => __( 'Alt Text', 'occidg' ),
				'description' => __( 'Improve accessibility and image SEO for screen readers and search.', 'occidg' ),
			),
			'caption'     => array(
				'label'       => __( 'Caption', 'occidg' ),
				'description' => __( 'Generate the visible caption field shown alongside the image.', 'occidg' ),
			),
		);

		echo '<div class="occidg-choice-grid occidg-metadata-fields-grid">';
		foreach ( $fields as $key => $label ) {
			$is_checked = isset( $options[ $key ] ) && '1' === $options[ $key ];
			printf(
				'<label class="occidg-choice-card occidg-metadata-field-card%1$s" for="occidg_metadata_fields_%2$s">',
				$is_checked ? ' is-checked' : '',
				esc_attr( $key ),
			);
			printf(
				'<input type="checkbox" class="metadata-field-checkbox" id="occidg_metadata_fields_%1$s" name="occidg_metadata_fields[%1$s]" value="1" %2$s>',
				esc_attr( $key ),
				checked( true, $is_checked, false )
			);
			echo '<span class="occidg-choice-copy">';
			printf(
				'<span class="occidg-choice-title">%s</span>',
				esc_html( $label['label'] )
			);
			printf(
				'<span class="occidg-choice-description">%s</span>',
				esc_html( $label['description'] )
			);
			echo '</span>';
			echo '</label>';
		}
		echo '</div>';
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
	 * Return the current provider generation gate state.
	 *
	 * @since 1.2.0
	 * @return array
	 */
	public static function get_generation_gate_state() {
		$provider                  = get_option( 'occidg_provider', 'openai' );
		$provider                  = 'gemini' === $provider ? 'gemini' : 'openai';
		$provider_label            = 'gemini' === $provider ? __( 'Gemini', 'occidg' ) : __( 'OpenAI', 'occidg' );
		$has_openai_key            = ! empty( get_option( 'occidg_openai_api_key', '' ) );
		$has_gemini_key            = ! empty( get_option( 'occidg_gemini_api_key', '' ) );
		$has_selected_provider_key = 'gemini' === $provider ? $has_gemini_key : $has_openai_key;

		return array(
			'provider'                  => $provider,
			'provider_label'            => $provider_label,
			'has_openai_key'            => $has_openai_key,
			'has_gemini_key'            => $has_gemini_key,
			'has_selected_provider_key' => $has_selected_provider_key,
			'missing_key_message'       => sprintf(
				/* translators: %s: provider name. */
				__( 'Add and save a %s API key in Settings to enable metadata generation.', 'occidg' ),
				$provider_label
			),
		);
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
	 * Sanitize a selected language code.
	 *
	 * @since 1.1.16
	 * @param string $language Raw language code.
	 * @return string
	 */
	public function sanitize_language( $language ) {
		$language  = strtolower( trim( (string) $language ) );
		$languages = array_keys( $this->get_supported_languages() );

		return in_array( $language, $languages, true ) ? $language : 'en';
	}

	/**
	 * Sanitize an API key.
	 *
	 * Keeps keys local-only and strips whitespace while preserving valid token characters.
	 *
	 * @since 1.1.16
	 * @param string $api_key Raw API key value.
	 * @return string
	 */
	public function sanitize_api_key( $api_key ) {
		if ( ! is_scalar( $api_key ) ) {
			return '';
		}

		$api_key = wp_unslash( $api_key );
		$api_key = preg_replace( '/\s+/', '', $api_key );

		return is_string( $api_key ) ? trim( $api_key ) : '';
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
		<select id="occidg_provider" name="occidg_provider" class="occidg-select-field">
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
		<label class="occidg-toggle-card" for="occidg_auto_add_details">
			<input type="checkbox" id="occidg_auto_add_details" name="occidg_auto_add_details" value="1" <?php checked( 1, $checked ); ?> />
			<span class="occidg-toggle-copy">
				<span class="occidg-toggle-title"><?php esc_html_e( 'Generate metadata on upload', 'occidg' ); ?></span>
				<span class="occidg-toggle-description"><?php esc_html_e( 'Apply your selected provider and field rules as soon as new images enter the Media Library.', 'occidg' ); ?></span>
			</span>
		</label>
		<?php
	}

	/**
	 * Render the OpenAI API key field.
	 *
	 * @since 1.1.16
	 * @return void
	 */
	public function occidg_openai_api_key_callback() {
		$this->render_provider_api_key_field( 'openai' );
	}

	/**
	 * Render the OpenAI model field.
	 *
	 * @since 1.1.16
	 * @return void
	 */
	public function occidg_openai_model_callback() {
		$this->render_provider_model_field( 'openai' );
	}

	/**
	 * Render the Gemini API key field.
	 *
	 * @since 1.1.16
	 * @return void
	 */
	public function occidg_gemini_api_key_callback() {
		$this->render_provider_api_key_field( 'gemini' );
	}

	/**
	 * Render the Gemini model field.
	 *
	 * @since 1.1.16
	 * @return void
	 */
	public function occidg_gemini_model_callback() {
		$this->render_provider_model_field( 'gemini' );
	}

	/**
	 * Return the settings config for a provider.
	 *
	 * @since 1.2.0
	 * @param string $provider Provider slug.
	 * @return array
	 */
	private function get_provider_settings_config( $provider ) {
		$provider = $this->sanitize_provider( $provider );

		if ( 'gemini' === $provider ) {
			return array(
				'provider'           => 'gemini',
				'label'              => __( 'Gemini', 'occidg' ),
				'api_key_option'     => 'occidg_gemini_api_key',
				'model_option'       => 'occidg_gemini_model',
				'model_cache_option' => 'occidg_gemini_models_cache',
				'default_model'      => 'gemini-1.5-flash',
				'key_description'    => __( 'Paste your Gemini API key. OCCIDG will validate it, save it, and load the available Gemini models for this site.', 'occidg' ),
				'model_description'  => __( 'Choose from the Gemini models returned for your saved key. Only Gemini models that support content generation are listed.', 'occidg' ),
			);
		}

		return array(
			'provider'           => 'openai',
			'label'              => __( 'OpenAI', 'occidg' ),
			'api_key_option'     => 'occidg_openai_api_key',
			'model_option'       => 'occidg_openai_model',
			'model_cache_option' => 'occidg_openai_models_cache',
			'default_model'      => 'gpt-4o-mini',
			'key_description'    => __( 'Paste your OpenAI API key. OCCIDG will validate it, save it, and load the available multimodal OpenAI models for this site.', 'occidg' ),
			'model_description'  => __( 'Choose from the compatible OpenAI models returned for your saved key. OCCIDG lists supported image-capable chat models only.', 'occidg' ),
		);
	}

	/**
	 * Render the AJAX-enabled API key field for a provider.
	 *
	 * @since 1.2.0
	 * @param string $provider Provider slug.
	 * @return void
	 */
	private function render_provider_api_key_field( $provider ) {
		$config = $this->get_provider_settings_config( $provider );
		$value  = get_option( $config['api_key_option'], '' );
		?>
		<div class="occidg-provider-setting-field" data-provider="<?php echo esc_attr( $config['provider'] ); ?>">
			<input
				type="password"
				id="<?php echo esc_attr( $config['api_key_option'] ); ?>"
				name="<?php echo esc_attr( $config['api_key_option'] ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				class="regular-text occidg-api-key-field"
				autocomplete="off"
				data-provider="<?php echo esc_attr( $config['provider'] ); ?>"
			/>
			<p class="description"><?php echo esc_html( $config['key_description'] ); ?></p>
			<p
				id="<?php echo esc_attr( $config['api_key_option'] ); ?>_status"
				class="occidg-provider-field-status"
				aria-live="polite"
			></p>
		</div>
		<?php
	}

	/**
	 * Render the provider model select field.
	 *
	 * @since 1.2.0
	 * @param string $provider Provider slug.
	 * @return void
	 */
	private function render_provider_model_field( $provider ) {
		$config         = $this->get_provider_settings_config( $provider );
		$current_model  = get_option( $config['model_option'], $config['default_model'] );
		$model_choices  = $this->get_provider_model_field_choices( $provider, $current_model );
		$has_saved_key  = ! empty( get_option( $config['api_key_option'], '' ) );
		$disabled_attrs = $has_saved_key ? '' : ' disabled="disabled" aria-disabled="true"';
		?>
		<div class="occidg-provider-setting-field" data-provider="<?php echo esc_attr( $config['provider'] ); ?>">
			<select
				id="<?php echo esc_attr( $config['model_option'] ); ?>"
				name="<?php echo esc_attr( $config['model_option'] ); ?>"
				class="occidg-select-field occidg-model-select"
				data-provider="<?php echo esc_attr( $config['provider'] ); ?>"
				data-model-count="<?php echo esc_attr( count( $model_choices ) ); ?>"<?php echo wp_kses_post( $disabled_attrs ); ?>
			>
				<?php foreach ( $model_choices as $model_choice ) : ?>
					<option value="<?php echo esc_attr( $model_choice['value'] ); ?>" <?php selected( $current_model, $model_choice['value'] ); ?>>
						<?php echo esc_html( $model_choice['label'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php echo esc_html( $config['model_description'] ); ?></p>
			<p
				id="<?php echo esc_attr( $config['model_option'] ); ?>_status"
				class="occidg-provider-field-status occidg-provider-field-status-model"
				aria-live="polite"
			></p>
		</div>
		<?php
	}

	/**
	 * Return model choices for rendering the provider select.
	 *
	 * @since 1.2.0
	 * @param string $provider       Provider slug.
	 * @param string $selected_model Current selected model.
	 * @return array
	 */
	private function get_provider_model_field_choices( $provider, $selected_model ) {
		$config         = $this->get_provider_settings_config( $provider );
		$choices        = $this->get_cached_provider_models( $provider );
		$selected_model = $this->sanitize_text_model( $selected_model, $config['default_model'] );

		if ( empty( $choices ) ) {
			return array(
				array(
					'value' => $selected_model,
					'label' => $selected_model,
				),
			);
		}

		$choice_values = wp_list_pluck( $choices, 'value' );
		if ( ! in_array( $selected_model, $choice_values, true ) ) {
			array_unshift(
				$choices,
				array(
					'value' => $selected_model,
					'label' => $selected_model,
				)
			);
		}

		return $choices;
	}

	/**
	 * Return cached provider model choices.
	 *
	 * @since 1.2.0
	 * @param string $provider Provider slug.
	 * @return array
	 */
	private function get_cached_provider_models( $provider ) {
		$config = $this->get_provider_settings_config( $provider );

		return $this->normalize_cached_model_choices( get_option( $config['model_cache_option'], array() ) );
	}

	/**
	 * Normalize model choices loaded from the options table.
	 *
	 * @since 1.2.0
	 * @param mixed $choices Raw choices value.
	 * @return array
	 */
	private function normalize_cached_model_choices( $choices ) {
		if ( ! is_array( $choices ) ) {
			return array();
		}

		$normalized_choices = array();

		foreach ( $choices as $choice ) {
			if ( ! is_array( $choice ) ) {
				continue;
			}

			$value = isset( $choice['value'] ) ? $this->sanitize_model_identifier( $choice['value'] ) : '';
			$label = isset( $choice['label'] ) ? sanitize_text_field( $choice['label'] ) : '';

			if ( '' === $value || '' === $label ) {
				continue;
			}

			$normalized_choices[] = array(
				'value' => $value,
				'label' => $label,
			);
		}

		return $normalized_choices;
	}

	/**
	 * Validate and save a provider API key, then return the available models.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function occidg_ajax_validate_provider_key() {
		if ( ! check_ajax_referer( 'occidg_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Nonce verification failed.', 'occidg' ),
				)
			);
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Permission denied.', 'occidg' ),
				)
			);
			return;
		}

		$provider = isset( $_POST['provider'] ) ? $this->sanitize_provider( wp_unslash( $_POST['provider'] ) ) : 'openai';
		$api_key  = isset( $_POST['api_key'] ) ? $this->sanitize_api_key( wp_unslash( $_POST['api_key'] ) ) : '';
		$config   = $this->get_provider_settings_config( $provider );

		if ( '' === $api_key ) {
			update_option( $config['api_key_option'], '' );

			$selected_model = get_option( $config['model_option'], $config['default_model'] );

			wp_send_json_success(
				array(
					'provider'       => $provider,
					'has_key'        => false,
					'message'        => sprintf(
						/* translators: %s: provider name. */
						__( '%s API key cleared.', 'occidg' ),
						$config['label']
					),
					'models'         => $this->get_provider_model_field_choices( $provider, $selected_model ),
					'selected_model' => $selected_model,
					'gate_state'     => self::get_generation_gate_state(),
				)
			);
			return;
		}

		$validation = $this->validate_provider_key_and_fetch_models( $provider, $api_key );
		if ( ! $validation['success'] ) {
			wp_send_json_error(
				array(
					'provider' => $provider,
					'message'  => $validation['error'],
					'details'  => isset( $validation['details'] ) ? $validation['details'] : '',
				)
			);
			return;
		}

		update_option( $config['api_key_option'], $api_key );
		update_option( $config['model_cache_option'], $validation['models'] );

		$selected_model = $this->choose_saved_or_default_provider_model( $provider, $validation['models'] );
		update_option( $config['model_option'], $selected_model );

		wp_send_json_success(
			array(
				'provider'       => $provider,
				'has_key'        => true,
				'message'        => sprintf(
					/* translators: %s: provider name. */
					__( '%s API key validated and saved.', 'occidg' ),
					$config['label']
				),
				'models'         => $this->get_provider_model_field_choices( $provider, $selected_model ),
				'selected_model' => $selected_model,
				'gate_state'     => self::get_generation_gate_state(),
			)
		);
	}

	/**
	 * Save the selected provider model via AJAX.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function occidg_ajax_save_provider_model() {
		if ( ! check_ajax_referer( 'occidg_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Nonce verification failed.', 'occidg' ),
				)
			);
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Permission denied.', 'occidg' ),
				)
			);
			return;
		}

		$provider = isset( $_POST['provider'] ) ? $this->sanitize_provider( wp_unslash( $_POST['provider'] ) ) : 'openai';
		$model    = isset( $_POST['model'] ) ? $this->sanitize_model_identifier( wp_unslash( $_POST['model'] ) ) : '';
		$config   = $this->get_provider_settings_config( $provider );

		if ( '' === $model ) {
			wp_send_json_error(
				array(
					'message' => __( 'A model selection is required.', 'occidg' ),
				)
			);
			return;
		}

		update_option( $config['model_option'], $model );

		wp_send_json_success(
			array(
				'provider'       => $provider,
				'selected_model' => $model,
				'message'        => sprintf(
					/* translators: %s: provider name. */
					__( '%s model saved.', 'occidg' ),
					$config['label']
				),
			)
		);
	}

	/**
	 * Validate a provider key by fetching available models.
	 *
	 * @since 1.2.0
	 * @param string $provider Provider slug.
	 * @param string $api_key  API key to validate.
	 * @return array
	 */
	private function validate_provider_key_and_fetch_models( $provider, $api_key ) {
		if ( 'gemini' === $provider ) {
			return $this->fetch_gemini_model_choices( $api_key );
		}

		return $this->fetch_openai_model_choices( $api_key );
	}

	/**
	 * Fetch compatible OpenAI models for the provided key.
	 *
	 * @since 1.2.0
	 * @param string $api_key OpenAI API key.
	 * @return array
	 */
	private function fetch_openai_model_choices( $api_key ) {
		$response = wp_remote_get(
			'https://api.openai.com/v1/models',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
				),
				'timeout' => 30,
			)
		);

		return $this->decode_provider_model_choices_response( $response, 'openai' );
	}

	/**
	 * Fetch compatible Gemini models for the provided key.
	 *
	 * @since 1.2.0
	 * @param string $api_key Gemini API key.
	 * @return array
	 */
	private function fetch_gemini_model_choices( $api_key ) {
		$response = wp_remote_get(
			sprintf(
				'https://generativelanguage.googleapis.com/v1beta/models?key=%s',
				rawurlencode( $api_key )
			),
			array(
				'timeout' => 30,
			)
		);

		return $this->decode_provider_model_choices_response( $response, 'gemini' );
	}

	/**
	 * Decode a provider model-list response into normalized model choices.
	 *
	 * @since 1.2.0
	 * @param array|WP_Error $response Provider HTTP response.
	 * @param string         $provider Provider slug.
	 * @return array
	 */
	private function decode_provider_model_choices_response( $response, $provider ) {
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: provider name. */
					__( 'Failed to communicate with %s.', 'occidg' ),
					ucfirst( $provider )
				),
				'details' => $response->get_error_message(),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$body        = $this->decode_json_payload( $raw_body );

		if ( ! is_array( $body ) ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: provider name. */
					__( 'Invalid response from %s.', 'occidg' ),
					ucfirst( $provider )
				),
				'details' => $this->get_body_excerpt( $raw_body ),
			);
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: provider name. */
					__( '%s returned an error response.', 'occidg' ),
					ucfirst( $provider )
				),
				'details' => $this->extract_provider_error_message( $body, $provider ),
			);
		}

		$current_model = get_option(
			$this->get_provider_settings_config( $provider )['model_option'],
			$this->get_provider_settings_config( $provider )['default_model']
		);
		$model_choices = 'gemini' === $provider
			? $this->normalize_gemini_model_choices( $body, $current_model )
			: $this->normalize_openai_model_choices( $body, $current_model );

		if ( empty( $model_choices ) ) {
			return array(
				'success' => false,
				'error'   => __( 'The provider key was valid, but no compatible models were returned.', 'occidg' ),
			);
		}

		return array(
			'success' => true,
			'models'  => $model_choices,
		);
	}

	/**
	 * Normalize OpenAI models into dropdown choices.
	 *
	 * @since 1.2.0
	 * @param array  $body          OpenAI models response body.
	 * @param string $current_model Currently saved model.
	 * @return array
	 */
	private function normalize_openai_model_choices( $body, $current_model = '' ) {
		if ( ! isset( $body['data'] ) || ! is_array( $body['data'] ) ) {
			return array();
		}

		$current_model = $this->sanitize_model_identifier( $current_model );
		$model_choices = array();

		foreach ( $body['data'] as $raw_model ) {
			if ( ! is_array( $raw_model ) ) {
				continue;
			}

			$model_id = isset( $raw_model['id'] ) ? $this->sanitize_model_identifier( $raw_model['id'] ) : '';
			if ( '' === $model_id ) {
				continue;
			}

			if ( $model_id !== $current_model && ! $this->should_include_openai_model( $model_id ) ) {
				continue;
			}

			$model_choices[ $model_id ] = array(
				'value' => $model_id,
				'label' => $model_id,
			);
		}

		uasort(
			$model_choices,
			static function ( $left, $right ) {
				return strnatcasecmp( $left['label'], $right['label'] );
			}
		);

		return array_values( $model_choices );
	}

	/**
	 * Normalize Gemini models into dropdown choices.
	 *
	 * @since 1.2.0
	 * @param array  $body          Gemini models response body.
	 * @param string $current_model Currently saved model.
	 * @return array
	 */
	private function normalize_gemini_model_choices( $body, $current_model = '' ) {
		if ( ! isset( $body['models'] ) || ! is_array( $body['models'] ) ) {
			return array();
		}

		$current_model = $this->sanitize_model_identifier( $current_model );
		$model_choices = array();

		foreach ( $body['models'] as $raw_model ) {
			if ( ! is_array( $raw_model ) ) {
				continue;
			}

			$supported_methods = isset( $raw_model['supportedGenerationMethods'] ) && is_array( $raw_model['supportedGenerationMethods'] )
				? $raw_model['supportedGenerationMethods']
				: array();
			if ( ! in_array( 'generateContent', $supported_methods, true ) ) {
				continue;
			}

			$model_id = isset( $raw_model['baseModelId'] ) ? $this->sanitize_model_identifier( $raw_model['baseModelId'] ) : '';
			if ( '' === $model_id && isset( $raw_model['name'] ) && is_string( $raw_model['name'] ) ) {
				$model_id = $this->sanitize_model_identifier( str_replace( 'models/', '', $raw_model['name'] ) );
			}

			if ( '' === $model_id || ( 0 !== strpos( $model_id, 'gemini' ) && $model_id !== $current_model ) ) {
				continue;
			}

			if ( isset( $model_choices[ $model_id ] ) ) {
				continue;
			}

			$display_name = isset( $raw_model['displayName'] ) ? sanitize_text_field( $raw_model['displayName'] ) : '';
			$label        = '' !== $display_name && $display_name !== $model_id
				? sprintf( '%1$s (%2$s)', $display_name, $model_id )
				: $model_id;

			$model_choices[ $model_id ] = array(
				'value' => $model_id,
				'label' => $label,
			);
		}

		uasort(
			$model_choices,
			static function ( $left, $right ) {
				return strnatcasecmp( $left['label'], $right['label'] );
			}
		);

		return array_values( $model_choices );
	}

	/**
	 * Choose a saved model when it is still available, otherwise use a safe default.
	 *
	 * @since 1.2.0
	 * @param string $provider      Provider slug.
	 * @param array  $model_choices Normalized model choices.
	 * @return string
	 */
	private function choose_saved_or_default_provider_model( $provider, $model_choices ) {
		$config        = $this->get_provider_settings_config( $provider );
		$selected      = get_option( $config['model_option'], $config['default_model'] );
		$selected      = $this->sanitize_text_model( $selected, $config['default_model'] );
		$choice_values = array();

		foreach ( $model_choices as $model_choice ) {
			if ( isset( $model_choice['value'] ) && is_string( $model_choice['value'] ) ) {
				$choice_values[] = $model_choice['value'];
			}
		}

		if ( in_array( $selected, $choice_values, true ) ) {
			return $selected;
		}

		if ( in_array( $config['default_model'], $choice_values, true ) ) {
			return $config['default_model'];
		}

		return ! empty( $choice_values ) ? $choice_values[0] : $config['default_model'];
	}

	/**
	 * Sanitize a model identifier without applying a fallback.
	 *
	 * @since 1.2.0
	 * @param string $model Raw model identifier.
	 * @return string
	 */
	private function sanitize_model_identifier( $model ) {
		$model = trim( (string) $model );
		if ( '' === $model ) {
			return '';
		}

		return preg_replace( '/\s+/', '', $model );
	}

	/**
	 * Determine whether an OpenAI model should appear in the UI select.
	 *
	 * @since 1.2.0
	 * @param string $model_id OpenAI model ID.
	 * @return bool
	 */
	private function should_include_openai_model( $model_id ) {
		$model_id = $this->sanitize_model_identifier( $model_id );
		if ( '' === $model_id || 0 === strpos( $model_id, 'ft:' ) ) {
			return false;
		}

		$allowed_prefixes = array(
			'gpt-5.5',
			'gpt-5.4',
			'gpt-5.3',
			'gpt-5.2',
			'gpt-5.1',
			'gpt-5',
			'gpt-4.1',
			'gpt-4o',
		);
		$disallowed_terms = array(
			'chatgpt',
			'computer-use',
			'embedding',
			'gpt-audio',
			'gpt-image',
			'gpt-realtime',
			'instruct',
			'moderation',
			'search-preview',
			'transcribe',
			'tts',
		);

		$is_allowed = false;
		foreach ( $allowed_prefixes as $allowed_prefix ) {
			if ( 0 === strpos( $model_id, $allowed_prefix ) ) {
				$is_allowed = true;
				break;
			}
		}

		if ( ! $is_allowed ) {
			return false;
		}

		foreach ( $disallowed_terms as $disallowed_term ) {
			if ( false !== strpos( $model_id, $disallowed_term ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Handles saving plugin settings via AJAX.
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

		$language = isset( $settings['occidg_language'] ) ? $this->sanitize_language( $settings['occidg_language'] ) : 'en';
		update_option( 'occidg_language', $language );

		$provider = isset( $settings['occidg_provider'] ) ? $this->sanitize_provider( $settings['occidg_provider'] ) : 'openai';
		update_option( 'occidg_provider', $provider );

		$openai_api_key = isset( $settings['occidg_openai_api_key'] ) ? $this->sanitize_api_key( $settings['occidg_openai_api_key'] ) : '';
		update_option( 'occidg_openai_api_key', $openai_api_key );

		$openai_model = isset( $settings['occidg_openai_model'] ) ? $this->sanitize_openai_model( $settings['occidg_openai_model'] ) : 'gpt-4o-mini';
		update_option( 'occidg_openai_model', $openai_model );

		$gemini_api_key = isset( $settings['occidg_gemini_api_key'] ) ? $this->sanitize_api_key( $settings['occidg_gemini_api_key'] ) : '';
		update_option( 'occidg_gemini_api_key', $gemini_api_key );

		$gemini_model = isset( $settings['occidg_gemini_model'] ) ? $this->sanitize_gemini_model( $settings['occidg_gemini_model'] ) : 'gemini-1.5-flash';
		update_option( 'occidg_gemini_model', $gemini_model );

		wp_send_json_success();
	}

	/**
	 * Generate metadata for an image using the configured AI provider.
	 *
	 * @since 1.0.0
	 * @param int   $image_id           The ID of the image attachment.
	 * @param array $generation_context Optional generation context overrides.
	 * @return array|false The generated metadata on success, or false/an error array on failure.
	 */
	public function occidg_generate_metadata( $image_id, $generation_context = array() ) {
		$selected_fields   = isset( $generation_context['selected_fields'] ) && is_array( $generation_context['selected_fields'] )
			? $generation_context['selected_fields']
			: get_option( 'occidg_metadata_fields', array() );
		$override_metadata = array_key_exists( 'override_metadata', $generation_context )
			? ! empty( $generation_context['override_metadata'] )
			: get_option( 'occidg_override_metadata', false );

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
		$file_type    = wp_check_filetype( $image_path );
		$image_type   = $this->sanitize_image_type( $file_type['ext'], $file_type['type'] ?? '' );

		if ( ! $image_type ) {
			return array(
				'success' => false,
				'error'   => __( 'Unable to detect a supported image format for metadata generation.', 'occidg' ),
			);
		}

		$provider_metadata = $this->request_provider_metadata( $image_base64, $image_type, $generation_context );
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
	 * @param string $image_base64       Base64 encoded image.
	 * @param string $image_type         Detected image extension.
	 * @param array  $generation_context Optional generation context overrides.
	 * @return array
	 */
	private function request_provider_metadata( $image_base64, $image_type, $generation_context = array() ) {
		$provider = isset( $generation_context['provider'] )
			? $this->sanitize_provider( $generation_context['provider'] )
			: get_option( 'occidg_provider', 'openai' );

		if ( 'gemini' === $provider ) {
			return $this->request_gemini_metadata( $image_base64, $image_type, $generation_context );
		}

		return $this->request_openai_metadata( $image_base64, $image_type, $generation_context );
	}

	/**
	 * Request metadata from OpenAI.
	 *
	 * @since 1.1.16
	 * @param string $image_base64       Base64 encoded image.
	 * @param string $image_type         Detected image extension.
	 * @param array  $generation_context Optional generation context overrides.
	 * @return array
	 */
	private function request_openai_metadata( $image_base64, $image_type, $generation_context = array() ) {
		$api_key = get_option( 'occidg_openai_api_key', '' );
		$model   = isset( $generation_context['model'] ) && is_string( $generation_context['model'] )
			? $generation_context['model']
			: get_option( 'occidg_openai_model', 'gpt-4o-mini' );
		$model   = $this->sanitize_text_model( $model, 'gpt-4o-mini' );

		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'error'   => __( 'OpenAI is selected, but no OpenAI API key is configured.', 'occidg' ),
			);
		}

		$messages = $this->prepare_messages_payload( $image_base64, $image_type, $generation_context );
		$body     = array(
			'model'                 => $model,
			'messages'              => $messages,
			'response_format'       => $this->get_openai_response_format(),
			'max_completion_tokens' => 500,
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

		$result = $this->decode_provider_response( $response, 'openai' );
		if ( isset( $result['success'] ) && ! $result['success'] ) {
			$this->log_provider_request_failure( 'openai', $model, $result, $response );
		}

		return $result;
	}

	/**
	 * Request metadata from Gemini.
	 *
	 * @since 1.1.16
	 * @param string $image_base64       Base64 encoded image.
	 * @param string $image_type         Detected image extension.
	 * @param array  $generation_context Optional generation context overrides.
	 * @return array
	 */
	private function request_gemini_metadata( $image_base64, $image_type, $generation_context = array() ) {
		$api_key = get_option( 'occidg_gemini_api_key', '' );
		$model   = isset( $generation_context['model'] ) && is_string( $generation_context['model'] )
			? $generation_context['model']
			: get_option( 'occidg_gemini_model', 'gemini-1.5-flash' );
		$model   = $this->sanitize_text_model( $model, 'gemini-1.5-flash' );

		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Gemini is selected, but no Gemini API key is configured.', 'occidg' ),
			);
		}

		$language_instruction = sprintf(
			'Generate image metadata including title, description, alt_text, and caption for the provided image in %s. Return valid JSON only.',
			$this->get_language_label(
				isset( $generation_context['language'] ) && is_string( $generation_context['language'] )
					? $generation_context['language']
					: get_option( 'occidg_language', 'en' )
			)
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

		$result = $this->decode_provider_response( $response, 'gemini' );
		if ( isset( $result['success'] ) && ! $result['success'] ) {
			$this->log_provider_request_failure( 'gemini', $model, $result, $response );
		}

		return $result;
	}

	/**
	 * Log a failed provider request with sanitized diagnostic context.
	 *
	 * @since 1.2.0
	 * @param string         $provider Provider slug.
	 * @param string         $model    Model identifier.
	 * @param array          $result   Normalized failure payload.
	 * @param array|WP_Error $response Raw HTTP response.
	 * @return void
	 */
	private function log_provider_request_failure( $provider, $model, $result, $response ) {
		$status_code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$details     = isset( $result['details'] ) && is_string( $result['details'] ) ? $result['details'] : '';
		$error       = isset( $result['error'] ) && is_string( $result['error'] ) ? $result['error'] : __( 'Unknown provider error.', 'occidg' );

		Occidg_Logger::warning(
			'Provider metadata request failed.',
			array(
				'provider'    => $this->sanitize_provider( $provider ),
				'model'       => $this->sanitize_model_identifier( $model ),
				'status_code' => $status_code,
				'error'       => sanitize_text_field( $error ),
				'details'     => sanitize_text_field( $this->get_body_excerpt( $details ) ),
			)
		);
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

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );

		if ( ! is_string( $raw_body ) ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: provider name */
					__( 'Invalid response from %s.', 'occidg' ),
					ucfirst( $provider )
				),
				'details' => 'Response body is not a string.',
			);
		}

		$body = $this->decode_json_payload( $raw_body );
		if ( false === $body ) {
			if ( $status_code < 200 || $status_code >= 300 ) {
				return array(
					'success' => false,
					'error'   => sprintf(
						/* translators: %s: provider name */
						__( '%s returned an error response.', 'occidg' ),
						ucfirst( $provider )
					),
					'details' => $this->get_body_excerpt( $raw_body ),
				);
			}

			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: provider name */
					__( 'Invalid response from %s.', 'occidg' ),
					ucfirst( $provider )
				),
				'details' => $this->get_body_excerpt( $raw_body ),
			);
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: provider name */
					__( '%s returned an error response.', 'occidg' ),
					ucfirst( $provider )
				),
				'details' => $this->extract_provider_error_message( $body, $provider ),
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
				'details' => $this->extract_provider_metadata_failure_details( $body, $provider, $raw_body ),
			);
		}

		return $metadata;
	}

	/**
	 * Extract a useful error message from a provider response body.
	 *
	 * @since 1.1.16
	 * @param array  $body     Provider response body.
	 * @param string $provider Provider slug.
	 * @return string
	 */
	private function extract_provider_error_message( $body, $provider ) {
		if ( ! is_array( $body ) ) {
			return __( 'Unknown provider error.', 'occidg' );
		}

		$error_message = $this->extract_nested_value( $body, array( 'error', 'message' ) );
		if ( is_string( $error_message ) && '' !== $error_message ) {
			return sanitize_text_field( $error_message );
		}

		if ( 'openai' === $provider && isset( $body['error']['code'] ) ) {
			$code = $this->extract_nested_value( $body, array( 'error', 'code' ) );
			if ( is_string( $code ) && '' !== $code ) {
				return sprintf(
					/* translators: %s: error code */
					__( 'OpenAI error: %s.', 'occidg' ),
					sanitize_text_field( $code )
				);
			}
		}

		if ( isset( $body['message'] ) && is_string( $body['message'] ) && '' !== trim( $body['message'] ) ) {
			return sanitize_text_field( $body['message'] );
		}

		if ( isset( $body['error']['errors'] ) && is_array( $body['error']['errors'] ) ) {
			$messages = array();
			foreach ( $body['error']['errors'] as $error ) {
				if ( is_array( $error ) && isset( $error['message'] ) && is_string( $error['message'] ) ) {
					$messages[] = $error['message'];
				}
			}

			if ( ! empty( $messages ) ) {
				return sanitize_text_field( implode( ' | ', $messages ) );
			}
		}

		return __( 'Unknown provider error.', 'occidg' );
	}

	/**
	 * Extract a useful detail message when a provider response succeeds but cannot be normalized.
	 *
	 * @since 1.2.0
	 * @param array  $body      Provider response body.
	 * @param string $provider  Provider slug.
	 * @param string $raw_body  Raw response body.
	 * @return string
	 */
	private function extract_provider_metadata_failure_details( $body, $provider, $raw_body ) {
		if ( 'openai' === $provider ) {
			$refusal = $this->extract_nested_value( $body, array( 'choices', 0, 'message', 'refusal' ) );
			if ( is_string( $refusal ) && '' !== trim( $refusal ) ) {
				return sanitize_text_field( $refusal );
			}
		}

		return $this->get_body_excerpt( $raw_body );
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
		if ( 'gemini' === $provider ) {
			$payload = $this->extract_provider_response_text(
				$data,
				array(
					array( 'candidates', 0, 'content', 'parts', 0, 'text' ),
				)
			);
		} else {
			$payload = $this->extract_provider_response_text(
				$data,
				array(
					array( 'choices', 0, 'message', 'function_call', 'arguments' ),
					array( 'choices', 0, 'message', 'tool_calls', 0, 'function', 'arguments' ),
					array( 'choices', 0, 'message', 'content' ),
				)
			);
		}

		if ( is_array( $payload ) ) {
			$metadata = $payload;
		} else {
			$metadata = $this->decode_json_payload( is_string( $payload ) ? $payload : '' );
		}

		if ( ! is_array( $metadata ) ) {
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
		$metadata = $this->extract_metadata_payload( $metadata );
		if ( false === $metadata ) {
			return false;
		}

		$normalized = array(
			'title'       => $this->extract_normalized_field( $metadata, array( 'title', 'headline', 'name' ), 'text' ),
			'description' => $this->extract_normalized_field( $metadata, array( 'description', 'desc', 'summary', 'details' ), 'textarea' ),
			'alt_text'    => $this->extract_normalized_field( $metadata, array( 'alt_text', 'alt', 'altText', 'alternative_text' ), 'text' ),
			'caption'     => $this->extract_normalized_field( $metadata, array( 'caption', 'subheadline', 'excerpt', 'image_caption' ), 'textarea' ),
		);

		foreach ( $normalized as $value ) {
			if ( '' !== $value ) {
				return $normalized;
			}
		}

		return false;
	}

	/**
	 * Decode a provider payload into an array.
	 *
	 * @since 1.1.16
	 * @param string $payload A raw payload string to decode.
	 * @return array|false
	 */
	private function decode_json_payload( $payload ) {
		if ( ! is_string( $payload ) ) {
			return false;
		}

		$payload = trim( (string) preg_replace( '/^\xEF\xBB\xBF/', '', $payload ) );
		if ( '' === $payload ) {
			return false;
		}

		$decode = function ( $candidate ) {
			$decoded = json_decode( $candidate, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				return $decoded;
			}

			$candidate = preg_replace( '/,\s*(\}|\])/u', '$1', $candidate );
			$decoded   = json_decode( $candidate, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				return $decoded;
			}

			return false;
		};

		$decoded = $decode( $payload );
		if ( false !== $decoded ) {
			return $decoded;
		}

		if ( preg_match( '/```(?:json)?\s*([\s\S]*?)\s*```/i', $payload, $matches ) ) {
			$payload = $matches[1];
			$decoded = $decode( $payload );
			if ( false !== $decoded ) {
				return $decoded;
			}
		}

		$start = strpos( $payload, '{' );
		$end   = strrpos( $payload, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$extracted = substr( $payload, $start, $end - $start + 1 );
			$decoded   = $decode( $extracted );
			if ( false !== $decoded ) {
				return $decoded;
			}
		}

		return false;
	}

	/**
	 * Extract a nested value from an array.
	 *
	 * @since 1.1.16
	 * @param array $data       Input array.
	 * @param array $path_parts Nested keys to follow.
	 * @return mixed
	 */
	private function extract_nested_value( $data, $path_parts ) {
		$current = $data;

		foreach ( $path_parts as $path_part ) {
			if ( is_array( $current ) && array_key_exists( $path_part, $current ) ) {
				$current = $current[ $path_part ];
				continue;
			}

			return null;
		}

		return $current;
	}

	/**
	 * Extract a nested text value from the provider response.
	 *
	 * @since 1.1.16
	 * @param array $data  Provider response.
	 * @param array $paths Paths to probe.
	 * @return mixed
	 */
	private function extract_provider_response_text( $data, $paths ) {
		foreach ( $paths as $path ) {
			$value = $this->extract_nested_value( $data, $path );
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return $value;
			}
			if ( is_array( $value ) ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Extract the metadata shape from provider output when a wrapper key exists.
	 *
	 * @since 1.1.16
	 * @param array $metadata Raw metadata payload.
	 * @return array|false
	 */
	private function extract_metadata_payload( $metadata ) {
		if ( ! is_array( $metadata ) ) {
			return false;
		}

		if ( isset( $metadata['metadata'] ) && is_array( $metadata['metadata'] ) ) {
			return $metadata['metadata'];
		}

		return $metadata;
	}

	/**
	 * Sanitize and normalize a single field from metadata aliases.
	 *
	 * @since 1.1.16
	 * @param array  $metadata    Metadata payload.
	 * @param array  $aliases     Candidate source keys.
	 * @param string $field_type  text|textarea.
	 * @return string
	 */
	private function extract_normalized_field( $metadata, $aliases, $field_type ) {
		foreach ( $aliases as $alias ) {
			if ( array_key_exists( $alias, $metadata ) && ! is_array( $metadata[ $alias ] ) ) {
				$value = is_scalar( $metadata[ $alias ] ) ? (string) $metadata[ $alias ] : '';
				$value = trim( $value );

				if ( '' === $value ) {
					continue;
				}

				if ( 'textarea' === $field_type ) {
					return sanitize_textarea_field( $value );
				}

				return sanitize_text_field( $value );
			}
		}

		return '';
	}

	/**
	 * Validate and normalize image file extensions for API usage.
	 *
	 * @since 1.1.16
	 * @param string $image_ext File extension from WordPress filetype lookup.
	 * @param string $mime_type MIME type from WordPress filetype lookup.
	 * @return string
	 */
	private function sanitize_image_type( $image_ext, $mime_type = '' ) {
		$image_ext = strtolower( trim( (string) $image_ext ) );
		if ( '' === $image_ext && '' !== $mime_type ) {
			$image_ext = strtolower( preg_replace( '/^image\//', '', trim( (string) $mime_type ) ) );
		}

		$allowed_types = array( 'jpg', 'jpeg', 'png', 'webp', 'gif' );
		if ( 'jpeg' === $image_ext ) {
			$image_ext = 'jpeg';
		}

		if ( 'jpg' === $image_ext ) {
			$image_ext = 'jpeg';
		}

		return in_array( $image_ext, $allowed_types, true ) ? $image_ext : '';
	}

	/**
	 * Sanitize a model string and apply a fallback.
	 *
	 * @since 1.1.16
	 * @param string $model           Raw model value.
	 * @param string $fallback_model  Fallback model value.
	 * @return string
	 */
	private function sanitize_text_model( $model, $fallback_model ) {
		$model = trim( (string) $model );
		if ( '' === $model ) {
			return $fallback_model;
		}

		return preg_replace( '/\s+/', '', $model );
	}

	/**
	 * Trim a long body string for diagnostics.
	 *
	 * @since 1.1.16
	 * @param string $body The response body.
	 * @return string
	 */
	private function get_body_excerpt( $body ) {
		$body = is_string( $body ) ? trim( $body ) : '';
		if ( '' === $body ) {
			return __( 'Response body was empty.', 'occidg' );
		}

		if ( strlen( $body ) > 500 ) {
			return substr( $body, 0, 500 ) . '...';
		}

		return $body;
	}

	/**
	 * Sanitize OpenAI model names before save/request.
	 *
	 * @since 1.1.16
	 * @param string $model OpenAI model name.
	 * @return string
	 */
	public function sanitize_openai_model( $model ) {
		return $this->sanitize_text_model( $model, 'gpt-4o-mini' );
	}

	/**
	 * Sanitize Gemini model names before save/request.
	 *
	 * @since 1.1.16
	 * @param string $model Gemini model name.
	 * @return string
	 */
	public function sanitize_gemini_model( $model ) {
		return $this->sanitize_text_model( $model, 'gemini-1.5-flash' );
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
	 * @param string $image_base64       The base64-encoded image data.
	 * @param string $image_type         The image file type.
	 * @param array  $generation_context Optional generation context overrides.
	 * @return array The messages payload.
	 */
	private function prepare_messages_payload( $image_base64, $image_type, $generation_context = array() ) {
		$selected_language    = isset( $generation_context['language'] ) && is_string( $generation_context['language'] )
			? $generation_context['language']
			: get_option( 'occidg_language', 'en' );
		$language_instruction = sprintf(
			'Generate image metadata including title, description, alt text, and caption for the provided image in %s. Return only structured JSON that matches the requested schema.',
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
	 * Get the metadata schema used for structured provider output.
	 *
	 * @since 1.2.0
	 * @return array
	 */
	private function get_metadata_schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
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
			'required'             => array( 'title', 'description', 'alt_text', 'caption' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Get the OpenAI structured response format for metadata generation.
	 *
	 * @since 1.2.0
	 * @return array
	 */
	private function get_openai_response_format() {
		return array(
			'type'        => 'json_schema',
			'json_schema' => array(
				'name'   => 'generate_image_metadata',
				'strict' => true,
				'schema' => $this->get_metadata_schema(),
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
	 * provider request payload size constraints.
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
