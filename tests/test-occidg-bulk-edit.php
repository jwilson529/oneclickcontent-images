<?php
/**
 * Tests for the bulk metadata editor.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/admin/class-occidg-bulk-edit.php';

/**
 * Covers bulk-editor metadata transport behavior.
 */
final class Test_Occidg_Bulk_Edit extends TestCase {

	/**
	 * JSON row values should not be HTML-escaped before client-side rendering.
	 *
	 * @return void
	 */
	public function test_prepare_metadata_row_preserves_unescaped_text() {
		$bulk_edit = new Occidg_Bulk_Edit();
		$method    = new ReflectionMethod( Occidg_Bulk_Edit::class, 'prepare_metadata_row' );
		$metadata  = array(
			'title'       => 'Person\'s portrait',
			'alt_text'    => 'Teal silhouette of a person\'s head and shoulders.',
			'description' => 'A person\'s silhouette on a pale mint background.',
			'caption'     => 'Person\'s silhouette',
		);

		$method->setAccessible( true );
		$row = $method->invoke(
			$bulk_edit,
			42,
			'<img src="thumbnail.jpg" alt="">',
			$metadata,
			'image/svg+xml'
		);

		$this->assertSame( $metadata['title'], $row['title'] );
		$this->assertSame( $metadata['alt_text'], $row['alt_text'] );
		$this->assertSame( $metadata['description'], $row['description'] );
		$this->assertSame( $metadata['caption'], $row['caption'] );
		$this->assertSame( 'image/svg+xml', $row['mime_type'] );
		$this->assertFalse( $row['generation_supported'] );
		$this->assertStringContainsString( 'manually', $row['generation_message'] );
		$this->assertSame(
			array(
				'alt_text'    => false,
				'title'       => false,
				'caption'     => false,
				'description' => false,
			),
			$row['empty_fields']
		);
		$this->assertStringNotContainsString( '&#039;', implode( ' ', array_intersect_key( $row, $metadata ) ) );
	}

	/** Effectively blank values should be identified for the preview UI. */
	public function test_prepare_metadata_row_reports_effectively_empty_values() {
		$bulk_edit = new Occidg_Bulk_Edit();
		$method    = new ReflectionMethod( Occidg_Bulk_Edit::class, 'prepare_metadata_row' );
		$metadata  = array(
			'title'       => 'A title',
			'alt_text'    => "\u{200B}",
			'description' => '<span> </span>',
			'caption'     => '&nbsp;',
		);

		$method->setAccessible( true );
		$row = $method->invoke( $bulk_edit, 42, '', $metadata, 'image/svg+xml' );

		$this->assertFalse( $row['empty_fields']['title'] );
		$this->assertTrue( $row['empty_fields']['alt_text'] );
		$this->assertTrue( $row['empty_fields']['description'] );
		$this->assertTrue( $row['empty_fields']['caption'] );
	}
}
