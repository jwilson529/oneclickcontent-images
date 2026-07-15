<?php
/**
 * Tests for image-format generation support rules.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

use PHPUnit\Framework\TestCase;

/**
 * Covers the host-independent SVG generation policy.
 */
final class Test_Occidg_Image_Support extends TestCase {

	/**
	 * SVG MIME matching should be strict and case-insensitive.
	 *
	 * @return void
	 */
	public function test_svg_mime_type_detection() {
		$this->assertTrue( Occidg_Image_Support::is_svg_mime_type( 'image/svg+xml' ) );
		$this->assertTrue( Occidg_Image_Support::is_svg_mime_type( ' IMAGE/SVG+XML ' ) );
		$this->assertFalse( Occidg_Image_Support::is_svg_mime_type( 'image/png' ) );
		$this->assertFalse( Occidg_Image_Support::is_svg_mime_type( '' ) );
	}

	/**
	 * SVG generation should produce a successful skipped result for queues.
	 *
	 * @return void
	 */
	public function test_svg_skipped_result() {
		$result = Occidg_Image_Support::get_svg_skipped_result();

		$this->assertTrue( $result['success'] );
		$this->assertTrue( $result['skipped'] );
		$this->assertFalse( $result['temporary'] );
		$this->assertSame( 'unsupported_svg', $result['reason'] );
		$this->assertStringContainsString( 'edit', $result['message'] );
	}
}
