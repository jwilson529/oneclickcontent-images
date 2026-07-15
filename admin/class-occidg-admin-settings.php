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
		if ( ! $this->can_manage_settings() ) {
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
				'sanitize_callback' => array( $this, 'sanitize_openai_api_key_option' ),
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
				'sanitize_callback' => array( $this, 'sanitize_gemini_api_key_option' ),
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

	/** Return the capability required by WordPress to submit this settings group. */
	public function settings_page_capability() {
		return 'occ_idg_manage_settings';
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
			__( 'Choose Metadata Fields', 'occidg' ),
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
		echo '<p>' . esc_html__( 'Choose which details the plugin should generate. Existing values stay protected unless overwrite mode is deliberately enabled.', 'occidg' ) . '</p>';
	}

	/**
	 * Callback to render the Override Existing Metadata checkbox.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function occidg_override_metadata_callback() {
		$overwrite_allowed = (bool) get_option( 'occ_idg_allow_overwrite', false );
		$checked           = $overwrite_allowed && get_option( 'occidg_override_metadata', false );
		$checked_value     = $checked ? 1 : 0;
		?>
		<label class="occidg-toggle-card" for="occidg_override_metadata">
			<input type="checkbox" id="occidg_override_metadata" name="occidg_override_metadata" value="1" <?php checked( 1, esc_attr( $checked_value ) ); ?> <?php disabled( ! $overwrite_allowed ); ?> />
			<span class="occidg-toggle-copy">
				<span class="occidg-toggle-title"><?php esc_html_e( 'Overwrite existing metadata', 'occidg' ); ?></span>
				<span class="occidg-toggle-description"><?php esc_html_e( 'Replace titles, alt text, descriptions, and captions that already exist on attachments.', 'occidg' ); ?></span>
			</span>
		</label>
		<?php if ( ! $overwrite_allowed ) : ?>
			<p class="description"><?php esc_html_e( 'Enable overwrite mode under Safety and Review → Advanced workflow settings before using this control.', 'occidg' ); ?></p>
		<?php endif; ?>
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
		$has_openai_key            = '' !== self::get_provider_api_key( 'openai' );
		$has_gemini_key            = '' !== self::get_provider_api_key( 'gemini' );
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
	 * Return a provider key from an environment constant before the database.
	 *
	 * @param string $provider Provider slug.
	 * @return string Provider API key.
	 */
	public static function get_provider_api_key( $provider ) {
		$provider = 'gemini' === $provider ? 'gemini' : 'openai';
		$constant = 'gemini' === $provider ? 'OCC_IDG_GEMINI_API_KEY' : 'OCC_IDG_OPENAI_API_KEY';
		if ( defined( $constant ) && is_string( constant( $constant ) ) && '' !== trim( constant( $constant ) ) ) {
			return trim( constant( $constant ) );
		}
		return (string) get_option( 'occidg_' . $provider . '_api_key', '' );
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
	 * Preserve a stored OpenAI key when the masked settings field is unchanged.
	 *
	 * @param mixed $api_key Submitted API key value.
	 * @return string Sanitized new key or the existing stored key.
	 */
	public function sanitize_openai_api_key_option( $api_key ) {
		return $this->sanitize_masked_api_key_option( $api_key, 'occidg_openai_api_key' );
	}

	/**
	 * Preserve a stored Gemini key when the masked settings field is unchanged.
	 *
	 * @param mixed $api_key Submitted API key value.
	 * @return string Sanitized new key or the existing stored key.
	 */
	public function sanitize_gemini_api_key_option( $api_key ) {
		return $this->sanitize_masked_api_key_option( $api_key, 'occidg_gemini_api_key' );
	}

	/**
	 * Treat a blank masked field as unchanged during a normal settings save.
	 *
	 * API keys are rendered as empty password inputs so their values are not sent
	 * back to the browser. The dedicated provider-key AJAX action owns explicit
	 * replacement and clearing; a normal settings submission must therefore keep
	 * the existing option when the masked field is blank.
	 *
	 * @param mixed  $api_key    Submitted API key value.
	 * @param string $option_name Provider API key option name.
	 * @return string Sanitized new key or the existing stored key.
	 */
	private function sanitize_masked_api_key_option( $api_key, $option_name ) {
		$api_key = $this->sanitize_api_key( $api_key );

		if ( '' !== $api_key ) {
			return $api_key;
		}

		return (string) get_option( $option_name, '' );
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
		$config   = $this->get_provider_settings_config( $provider );
		$value    = self::get_provider_api_key( $provider );
		$constant = 'gemini' === $provider ? 'OCC_IDG_GEMINI_API_KEY' : 'OCC_IDG_OPENAI_API_KEY';
		$external = defined( $constant ) && '' !== (string) constant( $constant );
		?>
		<div class="occidg-provider-setting-field" data-provider="<?php echo esc_attr( $config['provider'] ); ?>">
			<input
				type="password"
				id="<?php echo esc_attr( $config['api_key_option'] ); ?>"
				name="<?php echo esc_attr( $config['api_key_option'] ); ?>"
				value=""
				placeholder="<?php echo $value ? esc_attr__( 'Saved key (enter a new key to replace)', 'occidg' ) : ''; ?>"
				class="regular-text occidg-api-key-field"
				autocomplete="off"
				data-provider="<?php echo esc_attr( $config['provider'] ); ?>"
				<?php disabled( $external ); ?>
			/>
			<?php if ( $external ) : ?>
				<?php /* translators: %s: environment constant name. */ ?>
				<p class="description"><strong><?php echo esc_html( sprintf( __( 'Managed externally by %s.', 'occidg' ), $constant ) ); ?></strong></p>
			<?php endif; ?>
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
		$has_saved_key  = '' !== self::get_provider_api_key( $provider );
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

		if ( ! $this->can_manage_settings() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Permission denied.', 'occidg' ),
				)
			);
			return;
		}

		$provider = isset( $_POST['provider'] ) ? $this->sanitize_provider( sanitize_text_field( wp_unslash( $_POST['provider'] ) ) ) : 'openai';
		$api_key  = isset( $_POST['api_key'] ) ? $this->sanitize_api_key( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : '';
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

		if ( ! $this->can_manage_settings() ) {
			wp_send_json_error(
				array(
					'message' => __( 'Permission denied.', 'occidg' ),
				)
			);
			return;
		}

		$provider = isset( $_POST['provider'] ) ? $this->sanitize_provider( sanitize_text_field( wp_unslash( $_POST['provider'] ) ) ) : 'openai';
		$model    = isset( $_POST['model'] ) ? $this->sanitize_model_identifier( sanitize_text_field( wp_unslash( $_POST['model'] ) ) ) : '';
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
			'gpt-5.6',
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
		if ( ! $this->can_manage_settings() ) {
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

		$override = get_option( 'occ_idg_allow_overwrite', false ) && current_user_can( 'occ_idg_overwrite_metadata' ) && isset( $settings['occidg_override_metadata'] ) && '1' === $settings['occidg_override_metadata'] ? '1' : '0';
		update_option( 'occidg_override_metadata', $override );

		$language = isset( $settings['occidg_language'] ) ? $this->sanitize_language( $settings['occidg_language'] ) : 'en';
		update_option( 'occidg_language', $language );

		$provider = isset( $settings['occidg_provider'] ) ? $this->sanitize_provider( $settings['occidg_provider'] ) : 'openai';
		update_option( 'occidg_provider', $provider );

		$openai_api_key = isset( $settings['occidg_openai_api_key'] ) ? $this->sanitize_api_key( $settings['occidg_openai_api_key'] ) : '';
		if ( '' !== $openai_api_key && ! defined( 'OCC_IDG_OPENAI_API_KEY' ) ) {
			update_option( 'occidg_openai_api_key', $openai_api_key );
		}

		$openai_model = isset( $settings['occidg_openai_model'] ) ? $this->sanitize_openai_model( $settings['occidg_openai_model'] ) : 'gpt-4o-mini';
		update_option( 'occidg_openai_model', $openai_model );

		$gemini_api_key = isset( $settings['occidg_gemini_api_key'] ) ? $this->sanitize_api_key( $settings['occidg_gemini_api_key'] ) : '';
		if ( '' !== $gemini_api_key && ! defined( 'OCC_IDG_GEMINI_API_KEY' ) ) {
			update_option( 'occidg_gemini_api_key', $gemini_api_key );
		}

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
		$selected_fields           = isset( $generation_context['selected_fields'] ) && is_array( $generation_context['selected_fields'] )
			? $generation_context['selected_fields']
			: get_option( 'occidg_metadata_fields', array() );
		$is_non_persistent_request = array_key_exists( 'persist', $generation_context ) && false === $generation_context['persist'];
		$override_metadata         = array_key_exists( 'override_metadata', $generation_context )
			? ! empty( $generation_context['override_metadata'] ) && ( $is_non_persistent_request || ( get_option( 'occ_idg_allow_overwrite', false ) && ! empty( $generation_context['overwrite_confirmed'] ) ) )
			: false;

		if ( Occidg_Image_Support::is_svg_attachment( $image_id ) ) {
			return array(
				'success'   => false,
				'skipped'   => true,
				'reason'    => 'unsupported_svg',
				'temporary' => false,
				'error'     => Occidg_Image_Support::get_svg_generation_message(),
			);
		}

		// Start from the original attachment. Unsupported provider formats may be
		// converted to a short-lived JPEG through WordPress's image editor.
		$image_path = get_attached_file( $image_id );

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

		$file_type      = wp_check_filetype( $image_path );
		$file_extension = strtolower( (string) pathinfo( $image_path, PATHINFO_EXTENSION ) );
		$detected_ext   = ! empty( $file_type['ext'] ) ? $file_type['ext'] : $file_extension;
		$detected_mime  = ! empty( $file_type['type'] ) ? $file_type['type'] : (string) get_post_mime_type( $image_id );
		$image_payload  = $this->prepare_provider_image( $image_path, $detected_ext, $detected_mime, $image_id );
		if ( empty( $image_payload['success'] ) ) {
			return $image_payload;
		}
		$image_type = $image_payload['image_type'];
		$image_data = $image_payload['image_data'];
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Provider APIs accept the image payload encoded as base64.
		$image_base64 = base64_encode( $image_data );

		$provider_metadata = $this->request_provider_metadata( $image_base64, $image_type, $generation_context );
		if ( isset( $provider_metadata['success'] ) && ! $provider_metadata['success'] ) {
			return $provider_metadata;
		}

		$processed_metadata = ! empty( $generation_context['persist'] ) || ! array_key_exists( 'persist', $generation_context )
			? $this->process_and_save_metadata( $image_id, $provider_metadata, $generate_metadata, $generation_context )
			: $this->normalize_generated_metadata( $provider_metadata );
		if ( $processed_metadata ) {
			$confidence = array_fill_keys( array_keys( $processed_metadata ), 'medium' );
			return array(
				'success'    => true,
				'metadata'   => $processed_metadata,
				'confidence' => $confidence,
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
		$api_key = self::get_provider_api_key( 'openai' );
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
			'max_completion_tokens' => max( 100, min( 4000, (int) get_option( 'occ_idg_max_response_tokens', 500 ) ) ),
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => isset( $generation_context['request_timeout'] ) ? max( 5, min( 300, (int) $generation_context['request_timeout'] ) ) : max( 5, min( 300, (int) get_option( 'occ_idg_request_timeout', 60 ) ) ),
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
		$api_key = self::get_provider_api_key( 'gemini' );
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

		$language_instruction = $this->build_generation_instruction( $generation_context );

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
				'maxOutputTokens'  => max( 100, min( 4000, (int) get_option( 'occ_idg_max_response_tokens', 500 ) ) ),
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
				'timeout' => isset( $generation_context['request_timeout'] ) ? max( 5, min( 300, (int) $generation_context['request_timeout'] ) ) : max( 5, min( 300, (int) get_option( 'occ_idg_request_timeout', 60 ) ) ),
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
					/* translators: %s: provider name. */
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
					/* translators: %s: provider name. */
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
						/* translators: %s: provider name. */
						__( '%s returned an error response.', 'occidg' ),
						ucfirst( $provider )
					),
					'details' => $this->get_body_excerpt( $raw_body ),
				);
			}

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
		if ( '' === $normalized['caption'] ) {
			$normalized['caption'] = $this->derive_caption_fallback( $normalized );
		}

		foreach ( $normalized as $value ) {
			if ( '' !== $value ) {
				return $normalized;
			}
		}

		return false;
	}

	/**
	 * Derive a conservative caption when a provider returns an empty caption.
	 *
	 * The provider already generated the source text from the same image. Prefer
	 * the first factual description sentence, followed by alt text and title, so
	 * a blank structured-output field does not silently leave caption generation
	 * incomplete.
	 *
	 * @param array $metadata Normalized generated metadata.
	 * @return string
	 */
	private function derive_caption_fallback( $metadata ) {
		$description = isset( $metadata['description'] ) ? trim( (string) $metadata['description'] ) : '';
		if ( '' !== $description ) {
			$sentences = preg_split( '/(?<=[.!?])\s+/u', $description, 2 );
			$caption   = isset( $sentences[0] ) ? $sentences[0] : $description;
			return $this->trim_generated_caption( $caption );
		}

		foreach ( array( 'alt_text', 'title' ) as $field ) {
			if ( ! empty( $metadata[ $field ] ) ) {
				return $this->trim_generated_caption( $metadata[ $field ] );
			}
		}

		return '';
	}

	/**
	 * Limit a generated caption without depending on front-end template helpers.
	 *
	 * @param string $caption Candidate caption.
	 * @return string
	 */
	private function trim_generated_caption( $caption ) {
		$words = preg_split( '/\s+/u', trim( (string) $caption ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $words ) ) {
			return '';
		}

		return sanitize_textarea_field( implode( ' ', array_slice( $words, 0, 30 ) ) );
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
	 * Read an image in a provider-supported format.
	 *
	 * AVIF is converted to a temporary JPEG through WordPress's configured image
	 * editor. The temporary file is removed before this method returns. This
	 * keeps support portable across GD, Imagick, and host-specific editors while
	 * failing safely on hosts that cannot decode AVIF.
	 *
	 * @since 2.0.0
	 * @param string $image_path Image file path.
	 * @param string $image_ext  Detected file extension.
	 * @param string $mime_type  Detected MIME type.
	 * @param int    $image_id   Attachment ID for diagnostics.
	 * @return array{success: bool, image_type?: string, image_data?: string, error?: string}
	 */
	private function prepare_provider_image( $image_path, $image_ext, $mime_type, $image_id ) {
		$image_type = $this->sanitize_image_type( $image_ext, $mime_type );
		$read_path  = $image_path;
		$temp_path  = '';

		$is_avif = 'avif' === strtolower( trim( (string) $image_ext ) ) || 'image/avif' === strtolower( trim( (string) $mime_type ) );
		if ( ! $image_type && $is_avif ) {
			if ( ! function_exists( 'wp_tempnam' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			$editor = wp_get_image_editor( $image_path );
			if ( is_wp_error( $editor ) ) {
				Occidg_Logger::warning(
					'AVIF metadata preview could not initialize a WordPress image editor.',
					array(
						'image_id' => $image_id,
						'error'    => $editor->get_error_message(),
					)
				);
				return array(
					'success' => false,
					'error'   => __( 'This server cannot prepare this AVIF file for AI preview. You can still edit its metadata manually.', 'occidg' ),
				);
			}

			$temp_seed = wp_tempnam( 'occidg-avif-preview.jpg' );
			if ( ! $temp_seed ) {
				return array(
					'success' => false,
					'error'   => __( 'WordPress could not create a temporary image for this AVIF preview.', 'occidg' ),
				);
			}

			$saved = $editor->save( $temp_seed, 'image/jpeg' );
			if ( is_wp_error( $saved ) ) {
				wp_delete_file( $temp_seed );
				Occidg_Logger::warning(
					'AVIF metadata preview could not create a temporary JPEG.',
					array(
						'image_id' => $image_id,
						'error'    => $saved->get_error_message(),
					)
				);
				return array(
					'success' => false,
					'error'   => __( 'This server could not convert the AVIF file for AI preview. You can still edit its metadata manually.', 'occidg' ),
				);
			}

			$read_path  = ! empty( $saved['path'] ) ? $saved['path'] : $temp_seed;
			$temp_path  = $read_path;
			$image_type = 'jpeg';
			if ( $temp_seed !== $temp_path ) {
				wp_delete_file( $temp_seed );
			}
		}

		if ( ! $image_type ) {
			return array(
				'success' => false,
				'error'   => __( 'Unable to detect a supported image format for metadata generation.', 'occidg' ),
			);
		}

		$image_data = file_get_contents( $read_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( $temp_path ) {
			wp_delete_file( $temp_path );
		}

		if ( false === $image_data ) {
			Occidg_Logger::warning(
				'Metadata generation skipped because the image file could not be read.',
				array(
					'image_id' => $image_id,
				)
			);
			return array(
				'success' => false,
				'error'   => __( 'WordPress could not read this image for AI metadata generation.', 'occidg' ),
			);
		}

		return array(
			'success'    => true,
			'image_type' => $image_type,
			'image_data' => $image_data,
		);
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

		if ( isset( $selected_fields['alt_text'] ) && '1' === (string) $selected_fields['alt_text'] ) {
			if ( $override_metadata || Occidg_Metadata::is_empty( get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ) ) {
				$generate_metadata['alt_text'] = true;
			}
		}

		if ( isset( $selected_fields['title'] ) && '1' === (string) $selected_fields['title'] ) {
			if ( $override_metadata || Occidg_Metadata::is_empty( get_the_title( $image_id ) ) ) {
				$generate_metadata['title'] = true;
			}
		}

		if ( isset( $selected_fields['description'] ) && '1' === (string) $selected_fields['description'] ) {
			if ( $override_metadata || Occidg_Metadata::is_empty( get_post_field( 'post_content', $image_id ) ) ) {
				$generate_metadata['description'] = true;
			}
		}

		if ( isset( $selected_fields['caption'] ) && '1' === (string) $selected_fields['caption'] ) {
			if ( $override_metadata || Occidg_Metadata::is_empty( get_post_field( 'post_excerpt', $image_id ) ) ) {
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
		$selected_language              = isset( $generation_context['language'] ) && is_string( $generation_context['language'] )
			? $generation_context['language']
			: get_option( 'occidg_language', 'en' );
		$generation_context['language'] = $selected_language;
		$language_instruction           = $this->build_generation_instruction( $generation_context );

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
	 * Build the shared accessibility-first provider instruction.
	 *
	 * @param array $context Minimal attachment and editorial context.
	 * @return string
	 */
	private function build_generation_instruction( $context ) {
		$language     = isset( $context['language'] ) ? $context['language'] : get_option( 'occidg_language', 'en' );
		$safe_context = array_intersect_key(
			(array) $context,
			array_flip( array( 'filename', 'current_metadata', 'parent_title', 'parent_excerpt', 'parent_post_type', 'site_name', 'organization_name', 'site_description', 'editorial_tone', 'editorial_guidance', 'preferred_terms', 'prohibited_terms' ) )
		);
		return sprintf(
			'Generate factual image metadata in %1$s and return only JSON matching the schema. Alt text must be concise, natural, accessibility-first, and describe meaningful visible information. Do not start with "image of" or "picture of", keyword-stuff, repeat a caption, or guess identities, locations, medical conditions, sensitive traits, emotions, roles, dates, or events. Use a person\'s name only when reliable supplied context identifies them. For text-heavy graphics, summarize purpose and avoid transcribing everything. An empty alt value may be recommended for a decorative image, but never mark it decorative automatically. Titles must be searchable and human-readable without extensions or raw camera strings. Captions are visible editorial content: always return a non-empty, conservative caption grounded in visible content and never fabricate context. Descriptions must remain factual. Minimal trusted context: %2$s',
			$this->get_language_label( $language ),
			wp_json_encode( $safe_context )
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
	 * @param array $generation_context Audit context.
	 * @return array|false The saved metadata or false on failure.
	 */
	private function process_and_save_metadata( $image_id, $data, $generate_metadata, $generation_context = array() ) {
		$sanitized_metadata = $this->normalize_generated_metadata( $data );

		if ( false === $sanitized_metadata ) {
			return false;
		}

		if ( class_exists( 'Occidg_Metadata' ) && class_exists( 'Occidg_Database' ) ) {
			$metadata_service = new Occidg_Metadata( new Occidg_Database() );
			$provider         = isset( $generation_context['provider'] ) ? $generation_context['provider'] : get_option( 'occidg_provider', 'openai' );
			$model            = isset( $generation_context['model'] ) ? $generation_context['model'] : get_option( 'gemini' === $provider ? 'occidg_gemini_model' : 'occidg_openai_model', '' );
			$processing_mode  = ! empty( $generation_context['override_metadata'] ) ? 'overwrite' : 'fill_missing';
			foreach ( array_keys( $generate_metadata ) as $field ) {
				if ( $this->should_queue_caption_for_review( $field, $generation_context ) ) {
					( new Occidg_Database() )->insert_suggestion(
						array(
							'attachment_id'   => $image_id,
							'field_name'      => $field,
							'current_value'   => get_post_field( 'post_excerpt', $image_id, 'raw' ),
							'suggested_value' => $sanitized_metadata[ $field ],
							'confidence'      => 'medium',
							'provider'        => $provider,
							'model'           => $model,
							'prompt_version'  => Occidg_Workflow::PROMPT_VERSION,
						)
					);
					update_post_meta( $image_id, '_occ_idg_review_status', 'suggestion_ready' );
					continue;
				}
				$metadata_service->update_field(
					$image_id,
					$field,
					$sanitized_metadata[ $field ],
					array(
						'provider'        => $provider,
						'model'           => $model,
						'confidence'      => 'medium',
						'processing_mode' => $processing_mode,
						'approved_by'     => isset( $generation_context['approved_by'] ) ? absint( $generation_context['approved_by'] ) : null,
						'prompt_version'  => Occidg_Workflow::PROMPT_VERSION,
					)
				);
			}
		} else {
			if ( isset( $generate_metadata['alt_text'] ) ) {
				update_post_meta( $image_id, '_wp_attachment_image_alt', $sanitized_metadata['alt_text'] );
			}
			$post_update = array( 'ID' => $image_id );
			foreach ( array(
				'title'       => 'post_title',
				'description' => 'post_content',
				'caption'     => 'post_excerpt',
			) as $field => $post_key ) {
				if ( isset( $generate_metadata[ $field ] ) ) {
					$post_update[ $post_key ] = $sanitized_metadata[ $field ];
				}
			}
			if ( count( $post_update ) > 1 ) {
				wp_update_post( $post_update );
			}
		}

		return $sanitized_metadata;
	}

	/**
	 * Decide whether a generated caption must remain in the review queue.
	 *
	 * A row-level Generate click is an explicit approval to apply every eligible
	 * generated field, so it may confirm the caption during that request. Passive
	 * and background generation continue to honor the caption review setting.
	 *
	 * @since 2.0.0
	 * @param string $field              Metadata field.
	 * @param array  $generation_context Generation request context.
	 * @return bool Whether the caption should be queued instead of saved.
	 */
	private function should_queue_caption_for_review( $field, $generation_context ) {
		return 'caption' === $field
			&& (bool) get_option( 'occ_idg_require_caption_review', true )
			&& empty( $generation_context['caption_review_confirmed'] );
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

		if ( ! current_user_can( 'occ_idg_generate_metadata' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'occidg' ) );
			return;
		}

		$image_id               = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;
		$apply_configured_rules = isset( $_POST['apply_configured_rules'] ) && ! is_array( $_POST['apply_configured_rules'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['apply_configured_rules'] ) );

		if ( ! $image_id || ! current_user_can( 'edit_post', $image_id ) ) {
			wp_send_json_error( __( 'Invalid image ID or insufficient permission.', 'occidg' ) );
			return;
		}

		$generation_context = array();
		$overwrite_mode     = false;
		if ( $apply_configured_rules ) {
			$overwrite_mode     = (bool) get_option( 'occidg_override_metadata', false )
				&& (bool) get_option( 'occ_idg_allow_overwrite', false )
				&& current_user_can( 'occ_idg_overwrite_metadata' );
			$generation_context = array(
				'override_metadata'        => $overwrite_mode,
				'overwrite_confirmed'      => $overwrite_mode,
				'caption_review_confirmed' => true,
				'approved_by'              => get_current_user_id(),
				'mode'                     => $overwrite_mode ? 'overwrite' : 'fill_missing',
			);
		}

		$metadata = $this->occidg_generate_metadata( $image_id, $generation_context );

		if ( is_array( $metadata ) && isset( $metadata['success'] ) ) {
			if ( $metadata['success'] ) {
				$current_metadata = class_exists( 'Occidg_Metadata' ) && class_exists( 'Occidg_Database' )
					? ( new Occidg_Metadata( new Occidg_Database() ) )->get_all( $image_id )
					: array(
						'title'       => get_the_title( $image_id ),
						'alt_text'    => get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
						'description' => get_post_field( 'post_content', $image_id, 'raw' ),
						'caption'     => get_post_field( 'post_excerpt', $image_id, 'raw' ),
					);
				$message          = $apply_configured_rules
					? ( $overwrite_mode
						? __( 'Generated using your overwrite settings.', 'occidg' )
						: __( 'Generated. Existing metadata was protected.', 'occidg' ) )
					: __( 'Metadata generated successfully.', 'occidg' );
				wp_send_json_success(
					array(
						'message'          => $message,
						'metadata'         => $metadata,
						'current_metadata' => $current_metadata,
						'mode'             => $overwrite_mode ? 'overwrite' : 'fill_missing',
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
	 * Generate non-persistent candidate metadata for one Bulk Edit row.
	 *
	 * The preview deliberately requests every enabled field, including fields
	 * that already contain a value. Nothing is written until the user approves
	 * a specific suggestion through the Bulk Edit interface.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function occidg_ajax_preview_metadata() {
		if ( ! check_ajax_referer( 'occidg_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Nonce verification failed.', 'occidg' ) );
			return;
		}

		if ( ! current_user_can( 'occ_idg_generate_metadata' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'occidg' ) );
			return;
		}

		$image_id = isset( $_POST['image_id'] ) ? absint( $_POST['image_id'] ) : 0;
		if ( ! $image_id || ! current_user_can( 'edit_post', $image_id ) ) {
			wp_send_json_error( __( 'Invalid image ID or insufficient permission.', 'occidg' ) );
			return;
		}

		$selected_fields = get_option( 'occidg_metadata_fields', array() );
		$fields          = Occidg_Metadata::normalize_fields( $selected_fields );
		if ( empty( $fields ) ) {
			wp_send_json_error( __( 'Choose at least one metadata field in Settings before generating a preview.', 'occidg' ) );
			return;
		}

		$result = $this->occidg_generate_metadata(
			$image_id,
			array(
				'persist'           => false,
				'override_metadata' => true,
				'selected_fields'   => array_fill_keys( $fields, '1' ),
			)
		);

		if ( ! is_array( $result ) || empty( $result['success'] ) || empty( $result['metadata'] ) ) {
			wp_send_json_error( is_array( $result ) ? $result : __( 'Failed to generate metadata preview.', 'occidg' ) );
			return;
		}

		$metadata_service = new Occidg_Metadata( new Occidg_Database() );
		$current          = $metadata_service->get_all( $image_id );
		$suggestions      = array_intersect_key( $result['metadata'], array_flip( $fields ) );
		$preview_fields   = array();
		foreach ( $fields as $field ) {
			$preview_fields[ $field ] = array(
				'current'       => isset( $current[ $field ] ) ? $current[ $field ] : '',
				'current_empty' => Occidg_Metadata::is_empty( isset( $current[ $field ] ) ? $current[ $field ] : '' ),
				'suggested'     => isset( $suggestions[ $field ] ) ? $suggestions[ $field ] : '',
			);
		}

		wp_send_json_success(
			array(
				'image_id' => $image_id,
				'fields'   => $preview_fields,
				'message'  => __( 'Preview ready. Nothing has been changed.', 'occidg' ),
			)
		);
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

		if ( ! current_user_can( 'occ_idg_generate_metadata' ) ) {
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

	/** Determine whether the current user may change plugin settings. */
	private function can_manage_settings() {
		return current_user_can( 'occ_idg_manage_settings' ) || current_user_can( 'manage_options' );
	}
}
