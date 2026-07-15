<?php
/**
 * Tests for OCCIDG admin settings helpers.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/admin/class-occidg-admin-settings.php';

/**
 * Covers provider normalization helpers.
 */
final class Test_Occidg_Admin_Settings extends TestCase {

	/**
	 * Reset test options before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['occidg_options'] = array();
	}

	/**
	 * Invoke a non-public method on the settings class.
	 *
	 * @param Occidg_Admin_Settings $settings    The instance to call on.
	 * @param string                $method_name The method to invoke.
	 * @param array                 $args        Method arguments.
	 * @return mixed
	 */
	private function invoke_private_method( $settings, $method_name, $args = array() ) {
		$method = new ReflectionMethod( Occidg_Admin_Settings::class, $method_name );
		$method->setAccessible( true );

		return $method->invokeArgs( $settings, $args );
	}

	/**
	 * Gate state should default to OpenAI and require a saved key.
	 *
	 * @return void
	 */
	public function test_get_generation_gate_state_defaults_to_openai_without_key() {
		$state = Occidg_Admin_Settings::get_generation_gate_state();

		$this->assertSame( 'openai', $state['provider'] );
		$this->assertSame( 'OpenAI', $state['provider_label'] );
		$this->assertFalse( $state['has_openai_key'] );
		$this->assertFalse( $state['has_selected_provider_key'] );
		$this->assertStringContainsString( 'OpenAI API key', $state['missing_key_message'] );
	}

	/**
	 * Gate state should allow generation when the selected provider has a saved key.
	 *
	 * @return void
	 */
	public function test_get_generation_gate_state_uses_selected_provider_key() {
		$GLOBALS['occidg_options']['occidg_provider']       = 'gemini';
		$GLOBALS['occidg_options']['occidg_gemini_api_key'] = 'gemini-test-key';

		$state = Occidg_Admin_Settings::get_generation_gate_state();

		$this->assertSame( 'gemini', $state['provider'] );
		$this->assertSame( 'Gemini', $state['provider_label'] );
		$this->assertTrue( $state['has_gemini_key'] );
		$this->assertTrue( $state['has_selected_provider_key'] );
		$this->assertStringContainsString( 'Gemini API key', $state['missing_key_message'] );
	}

	/**
	 * OpenAI model normalization should keep compatible chat models only.
	 *
	 * @return void
	 */
	public function test_normalize_openai_model_choices_filters_supported_models() {
		$settings = new Occidg_Admin_Settings();
		$choices  = $this->invoke_private_method(
			$settings,
			'normalize_openai_model_choices',
			array(
				array(
					'data' => array(
						array( 'id' => 'gpt-4o-mini' ),
						array( 'id' => 'gpt-4.1-mini' ),
						array( 'id' => 'gpt-5-mini' ),
						array( 'id' => 'gpt-5.5' ),
						array( 'id' => 'gpt-5.6' ),
						array( 'id' => 'gpt-5.6-sol' ),
						array( 'id' => 'gpt-5.6-terra' ),
						array( 'id' => 'gpt-5.6-luna' ),
						array( 'id' => 'gpt-image-1' ),
						array( 'id' => 'gpt-realtime' ),
						array( 'id' => 'chatgpt-4o-latest' ),
						array( 'id' => 'ft:gpt-4o-mini:custom' ),
					),
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'value' => 'gpt-4.1-mini',
					'label' => 'gpt-4.1-mini',
				),
				array(
					'value' => 'gpt-4o-mini',
					'label' => 'gpt-4o-mini',
				),
				array(
					'value' => 'gpt-5-mini',
					'label' => 'gpt-5-mini',
				),
				array(
					'value' => 'gpt-5.5',
					'label' => 'gpt-5.5',
				),
				array(
					'value' => 'gpt-5.6',
					'label' => 'gpt-5.6',
				),
				array(
					'value' => 'gpt-5.6-luna',
					'label' => 'gpt-5.6-luna',
				),
				array(
					'value' => 'gpt-5.6-sol',
					'label' => 'gpt-5.6-sol',
				),
				array(
					'value' => 'gpt-5.6-terra',
					'label' => 'gpt-5.6-terra',
				),
			),
			$choices
		);
	}

	/**
	 * Gemini model normalization should use base model IDs and dedupe variants.
	 *
	 * @return void
	 */
	public function test_normalize_gemini_model_choices_uses_base_model_id() {
		$settings = new Occidg_Admin_Settings();
		$choices  = $this->invoke_private_method(
			$settings,
			'normalize_gemini_model_choices',
			array(
				array(
					'models' => array(
						array(
							'baseModelId'                => 'gemini-2.0-flash',
							'displayName'                => 'Gemini 2.0 Flash',
							'supportedGenerationMethods' => array( 'generateContent', 'countTokens' ),
						),
						array(
							'baseModelId'                => 'gemini-2.0-flash',
							'displayName'                => 'Gemini 2.0 Flash Variant',
							'supportedGenerationMethods' => array( 'generateContent' ),
						),
						array(
							'baseModelId'                => 'gemini-1.5-pro',
							'displayName'                => 'Gemini 1.5 Pro',
							'supportedGenerationMethods' => array( 'generateContent' ),
						),
						array(
							'baseModelId'                => 'embedding-001',
							'displayName'                => 'Embedding',
							'supportedGenerationMethods' => array( 'embedContent' ),
						),
					),
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'value' => 'gemini-1.5-pro',
					'label' => 'Gemini 1.5 Pro (gemini-1.5-pro)',
				),
				array(
					'value' => 'gemini-2.0-flash',
					'label' => 'Gemini 2.0 Flash (gemini-2.0-flash)',
				),
			),
			$choices
		);
	}

	/**
	 * Sanitize provider should fall back to OpenAI.
	 *
	 * @return void
	 */
	public function test_sanitize_provider_allows_supported_values_only() {
		$settings = new Occidg_Admin_Settings();

		$this->assertSame( 'openai', $settings->sanitize_provider( 'openai' ) );
		$this->assertSame( 'gemini', $settings->sanitize_provider( 'gemini' ) );
		$this->assertSame( 'openai', $settings->sanitize_provider( 'azure' ) );
	}

	/**
	 * Generated metadata should normalize to the expected saved shape.
	 *
	 * @return void
	 */
	public function test_normalize_generated_metadata_returns_expected_shape() {
		$settings = new Occidg_Admin_Settings();
		$method   = new ReflectionMethod( Occidg_Admin_Settings::class, 'normalize_generated_metadata' );
		$method->setAccessible( true );

		$normalized = $method->invoke(
			$settings,
			array(
				'title'       => '  Sample Title  ',
				'description' => "  Sample description\n",
				'alt_text'    => '  Sample alt  ',
				'caption'     => "  Sample caption\n",
			)
		);

		$this->assertSame(
			array(
				'title'       => 'Sample Title',
				'description' => 'Sample description',
				'alt_text'    => 'Sample alt',
				'caption'     => 'Sample caption',
			),
			$normalized
		);
	}

	/** Empty captions should fall back to the first factual description sentence. */
	public function test_normalize_generated_metadata_derives_empty_caption() {
		$settings = new Occidg_Admin_Settings();
		$method   = new ReflectionMethod( Occidg_Admin_Settings::class, 'normalize_generated_metadata' );
		$method->setAccessible( true );

		$normalized = $method->invoke(
			$settings,
			array(
				'title'       => 'Castle visit',
				'description' => 'Two costumed characters stand before a castle. Blue spires rise behind them.',
				'alt_text'    => 'Two costumed characters in front of a castle.',
				'caption'     => '',
			)
		);

		$this->assertSame( 'Two costumed characters stand before a castle.', $normalized['caption'] );
	}

	/**
	 * Empty metadata should be rejected.
	 *
	 * @return void
	 */
	public function test_normalize_generated_metadata_rejects_empty_payloads() {
		$settings = new Occidg_Admin_Settings();
		$method   = new ReflectionMethod( Occidg_Admin_Settings::class, 'normalize_generated_metadata' );
		$method->setAccessible( true );

		$this->assertFalse(
			$method->invoke(
				$settings,
				array(
					'title'       => '',
					'description' => '',
					'alt_text'    => '',
					'caption'     => '',
				)
			)
		);
	}

	/**
	 * Sanitize language should be limited to supported codes.
	 *
	 * @return void
	 */
	public function test_sanitize_language() {
		$settings = new Occidg_Admin_Settings();

		$this->assertSame( 'en', $settings->sanitize_language( 'EN' ) );
		$this->assertSame( 'fr', $settings->sanitize_language( 'fr' ) );
		$this->assertSame( 'en', $settings->sanitize_language( 'zz' ) );
	}

	/**
	 * API key sanitizer should remove whitespace and sanitize non-scalars.
	 *
	 * @return void
	 */
	public function test_sanitize_api_key() {
		$settings = new Occidg_Admin_Settings();

		$this->assertSame( 'sk-test', $settings->sanitize_api_key( " \tsk-test\n" ) );
		$this->assertSame( '', $settings->sanitize_api_key( array( 'bad' ) ) );
	}

	/** A blank masked OpenAI field must not erase an existing installation's key. */
	public function test_blank_masked_openai_key_preserves_existing_option() {
		$GLOBALS['occidg_options']['occidg_openai_api_key'] = 'existing-openai-key';
		$settings = new Occidg_Admin_Settings();

		$this->assertSame( 'existing-openai-key', $settings->sanitize_openai_api_key_option( '' ) );
		$this->assertSame( 'replacement-openai-key', $settings->sanitize_openai_api_key_option( " replacement-openai-key\n" ) );
	}

	/** A blank masked Gemini field must not erase an existing installation's key. */
	public function test_blank_masked_gemini_key_preserves_existing_option() {
		$GLOBALS['occidg_options']['occidg_gemini_api_key'] = 'existing-gemini-key';
		$settings = new Occidg_Admin_Settings();

		$this->assertSame( 'existing-gemini-key', $settings->sanitize_gemini_api_key_option( '' ) );
		$this->assertSame( '', ( new Occidg_Admin_Settings() )->sanitize_openai_api_key_option( '' ) );
	}

	/**
	 * Model sanitizer callbacks should trim and keep a safe fallback value.
	 *
	 * @return void
	 */
	public function test_sanitize_model_helpers() {
		$settings = new Occidg_Admin_Settings();

		$this->assertSame( 'gpt-4o-mini', $settings->sanitize_openai_model( '' ) );
		$this->assertSame( 'gemini-1.5-flash', $settings->sanitize_gemini_model( "\t\n" ) );
		$this->assertSame( 'custommodel', $settings->sanitize_openai_model( 'custom model' ) );
	}

	/**
	 * Decode JSON payload should recover from fenced or mixed payloads.
	 *
	 * @return void
	 */
	public function test_decode_json_payload_recovers_nested_json() {
		$settings = new Occidg_Admin_Settings();

		$decoded = $this->invoke_private_method(
			$settings,
			'decode_json_payload',
			array( "```json\n{\"title\":\"A\",\"description\":\"B\"}\n```" )
		);
		$this->assertSame(
			array(
				'title'       => 'A',
				'description' => 'B',
			),
			$decoded
		);

		$decoded = $this->invoke_private_method(
			$settings,
			'decode_json_payload',
			array( 'Leading text {"title":"C","description":"D"} trailing text' )
		);
		$this->assertSame(
			array(
				'title'       => 'C',
				'description' => 'D',
			),
			$decoded
		);
	}

	/**
	 * Decode JSON payload should recover from markdown and trailing-comma payload formats.
	 *
	 * @return void
	 */
	public function test_decode_json_payload_recovers_from_markdown_and_trailing_commas() {
		$settings = new Occidg_Admin_Settings();

		$decoded = $this->invoke_private_method(
			$settings,
			'decode_json_payload',
			array( "Here is the metadata:\n```json\n{\"title\":\"E\",\"description\":\"F\",\"alt_text\":\"G\",\"caption\":\"H\"}\n```\nUse this response." )
		);
		$this->assertSame(
			array(
				'title'       => 'E',
				'description' => 'F',
				'alt_text'    => 'G',
				'caption'     => 'H',
			),
			$decoded
		);

		$decoded = $this->invoke_private_method(
			$settings,
			'decode_json_payload',
			array( '{"title":"I","description":"J","alt_text":"K","caption":"L",}' )
		);
		$this->assertSame(
			array(
				'title'       => 'I',
				'description' => 'J',
				'alt_text'    => 'K',
				'caption'     => 'L',
			),
			$decoded
		);
	}

	/**
	 * Decode provider response should gracefully report valid provider errors.
	 *
	 * @return void
	 */
	public function test_decode_provider_response_reports_error_payloads() {
		$settings   = new Occidg_Admin_Settings();
		$decode     = new ReflectionMethod( Occidg_Admin_Settings::class, 'decode_provider_response' );
		$mock_error = array(
			'response' => array( 'code' => 401 ),
			'body'     => '{"error":{"message":"Invalid API key"}}',
		);

		$decode->setAccessible( true );
		$result = $decode->invoke( $settings, $mock_error, 'openai' );

		$this->assertSame( false, $result['success'] );
		$this->assertSame( 'Openai returned an error response.', $result['error'] );
		$this->assertSame( 'Invalid API key', $result['details'] );
	}

	/**
	 * Decode provider response should expose refusal details when metadata extraction fails.
	 *
	 * @return void
	 */
	public function test_decode_provider_response_reports_openai_refusal_details() {
		$settings          = new Occidg_Admin_Settings();
		$decode            = new ReflectionMethod( Occidg_Admin_Settings::class, 'decode_provider_response' );
		$provider_response = array(
			'response' => array( 'code' => 200 ),
			'body'     => '{"choices":[{"message":{"refusal":"Unable to describe this image."}}]}',
		);

		$decode->setAccessible( true );
		$result = $decode->invoke( $settings, $provider_response, 'openai' );

		$this->assertSame( false, $result['success'] );
		$this->assertSame( 'Unable to extract metadata from the Openai response.', $result['error'] );
		$this->assertSame( 'Unable to describe this image.', $result['details'] );
	}

	/**
	 * Decode provider response should normalize JSON-in-text from provider content.
	 *
	 * @return void
	 */
	public function test_decode_provider_response_extracts_json_from_nested_text() {
		$settings          = new Occidg_Admin_Settings();
		$decode            = new ReflectionMethod( Occidg_Admin_Settings::class, 'decode_provider_response' );
		$provider_response = array(
			'response' => array( 'code' => 200 ),
			'body'     => '{"choices":[{"message":{"content":"Sure, here is the JSON:\n```json\n{\\"title\\":\\"Recovered\\",\\"description\\":\\"Works\\",\\"alt_text\\":\\"Text\\",\\"caption\\":\\"Image\\"}\n```"}}]}',
		);

		$decode->setAccessible( true );
		$result = $decode->invoke( $settings, $provider_response, 'openai' );

		$this->assertSame( 'Recovered', $result['title'] );
		$this->assertSame( 'Works', $result['description'] );
		$this->assertSame( 'Text', $result['alt_text'] );
		$this->assertSame( 'Image', $result['caption'] );
	}

	/**
	 * OpenAI structured response format should use a strict schema.
	 *
	 * @return void
	 */
	public function test_get_openai_response_format_uses_strict_schema() {
		$settings        = new Occidg_Admin_Settings();
		$response_format = $this->invoke_private_method( $settings, 'get_openai_response_format' );

		$this->assertSame( 'json_schema', $response_format['type'] );
		$this->assertSame( 'generate_image_metadata', $response_format['json_schema']['name'] );
		$this->assertTrue( $response_format['json_schema']['strict'] );
		$this->assertFalse( $response_format['json_schema']['schema']['additionalProperties'] );
		$this->assertSame(
			array( 'title', 'description', 'alt_text', 'caption' ),
			$response_format['json_schema']['schema']['required']
		);
	}

	/**
	 * Metadata normalization should support wrapper payloads and aliases.
	 *
	 * @return void
	 */
	public function test_normalize_generated_metadata_supports_aliases_and_wrappers() {
		$settings = new Occidg_Admin_Settings();
		$method   = new ReflectionMethod( Occidg_Admin_Settings::class, 'normalize_generated_metadata' );
		$method->setAccessible( true );

		$normalized = $method->invoke(
			$settings,
			array(
				'metadata' => array(
					'name'          => 'Wrapped title',
					'summary'       => 'Wrapped description',
					'alt'           => 'Wrapped alt',
					'image_caption' => '  Wrapped caption  ',
				),
			)
		);

		$this->assertSame(
			array(
				'title'       => 'Wrapped title',
				'description' => 'Wrapped description',
				'alt_text'    => 'Wrapped alt',
				'caption'     => 'Wrapped caption',
			),
			$normalized
		);
	}

	/**
	 * Image type sanitizer should normalize known extensions.
	 *
	 * @return void
	 */
	public function test_sanitize_image_type() {
		$settings = new Occidg_Admin_Settings();

		$this->assertSame( 'jpeg', $this->invoke_private_method( $settings, 'sanitize_image_type', array( 'jpg' ) ) );
		$this->assertSame( 'png', $this->invoke_private_method( $settings, 'sanitize_image_type', array( '', 'image/png' ) ) );
		$this->assertSame( '', $this->invoke_private_method( $settings, 'sanitize_image_type', array( 'bmp' ) ) );
	}

	/** Row-level generation should treat its click as caption approval. */
	public function test_explicit_generation_does_not_queue_caption_for_review() {
		$GLOBALS['occidg_options']['occ_idg_require_caption_review'] = true;
		$settings = new Occidg_Admin_Settings();

		$this->assertFalse(
			$this->invoke_private_method(
				$settings,
				'should_queue_caption_for_review',
				array( 'caption', array( 'caption_review_confirmed' => true ) )
			)
		);
	}

	/** Automatic generation should keep caption review enabled. */
	public function test_automatic_generation_still_queues_caption_for_review() {
		$GLOBALS['occidg_options']['occ_idg_require_caption_review'] = true;
		$settings = new Occidg_Admin_Settings();

		$this->assertTrue( $this->invoke_private_method( $settings, 'should_queue_caption_for_review', array( 'caption', array() ) ) );
		$this->assertFalse( $this->invoke_private_method( $settings, 'should_queue_caption_for_review', array( 'alt_text', array() ) ) );
	}
}
