<?php
/**
 * Tests for metadata safety helpers.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

use PHPUnit\Framework\TestCase;

/** Covers field selection and whitespace semantics. */
final class Test_Occidg_Metadata extends TestCase {
	/** Settings maps include only explicitly enabled supported fields. */
	public function test_normalize_fields_respects_enabled_values() {
		$this->assertSame(
			array( 'title', 'caption' ),
			Occidg_Metadata::normalize_fields(
				array(
					'title'       => '1',
					'alt_text'    => '0',
					'caption'     => true,
					'description' => false,
					'unsupported' => '1',
				)
			)
		);
	}

	/** Lists are de-duplicated and unsupported fields are removed. */
	public function test_normalize_fields_accepts_field_lists() {
		$this->assertSame( array( 'alt_text', 'description' ), Occidg_Metadata::normalize_fields( array( 'alt_text', 'description', 'alt_text', 'pdf' ) ) );
	}

	/** Whitespace and tags without text count as empty, filenames do not. */
	public function test_is_empty_uses_whitespace_semantics() {
		$this->assertTrue( Occidg_Metadata::is_empty( " \n\t" ) );
		$this->assertTrue( Occidg_Metadata::is_empty( '<span> </span>' ) );
		$this->assertTrue( Occidg_Metadata::is_empty( '&nbsp;' ) );
		$this->assertTrue( Occidg_Metadata::is_empty( "\u{00A0}" ) );
		$this->assertTrue( Occidg_Metadata::is_empty( "\u{200B}" ) );
		$this->assertTrue( Occidg_Metadata::is_empty( "\u{FEFF}" ) );
		$this->assertFalse( Occidg_Metadata::is_empty( 'IMG_4382.jpg' ) );
	}
}
