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
}
