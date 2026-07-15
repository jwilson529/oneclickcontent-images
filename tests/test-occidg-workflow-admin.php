<?php
/**
 * Tests for workflow administration preferences.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/admin/class-occidg-workflow-admin.php';

/** Covers per-user batch planner preference normalization. */
final class Test_Occidg_Workflow_Admin extends TestCase {
	/** Reset option doubles before each test. */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['occidg_options'] = array();
	}

	/**
	 * Invoke the private preference normalizer.
	 *
	 * @param array $preferences Candidate preferences.
	 * @return array Normalized preferences.
	 */
	private function normalize( $preferences ) {
		$admin  = new Occidg_Workflow_Admin( null, null, null, null, null );
		$method = new ReflectionMethod( Occidg_Workflow_Admin::class, 'normalize_batch_planner_preferences' );
		$method->setAccessible( true );

		return $method->invoke( $admin, $preferences );
	}

	/** Valid user choices should survive normalization. */
	public function test_normalize_batch_planner_preferences_preserves_valid_choices() {
		$GLOBALS['occidg_options']['occ_idg_max_batch_size'] = 75;

		$this->assertSame(
			array(
				'fields'        => array( 'title', 'caption' ),
				'limit'         => 'custom',
				'custom_limit'  => 75,
				'mode'          => 'fill_missing',
				'order'         => 'random',
				'missing_field' => 'caption',
			),
			$this->normalize(
				array(
					'fields'        => array( 'title', 'caption', 'unsupported' ),
					'limit'         => 'custom',
					'custom_limit'  => 500,
					'mode'          => 'fill_missing',
					'order'         => 'random',
					'missing_field' => 'caption',
				)
			)
		);
	}

	/** Invalid stored values should return conservative defaults. */
	public function test_normalize_batch_planner_preferences_uses_safe_defaults() {
		$preferences = $this->normalize(
			array(
				'fields'        => array( 'unsupported' ),
				'limit'         => '5000',
				'custom_limit'  => 0,
				'mode'          => 'delete',
				'order'         => 'sideways',
				'missing_field' => 'filename',
			)
		);

		$this->assertSame( array(), $preferences['fields'] );
		$this->assertSame( '25', $preferences['limit'] );
		$this->assertSame( 1, $preferences['custom_limit'] );
		$this->assertSame( 'dry_run', $preferences['mode'] );
		$this->assertSame( 'oldest', $preferences['order'] );
		$this->assertSame( '', $preferences['missing_field'] );
	}

	/** Explicit Image Library selections should contain unique positive IDs only. */
	public function test_normalize_selected_image_ids_removes_duplicates_and_invalid_values() {
		$admin  = new Occidg_Workflow_Admin( null, null, null, null, null );
		$method = new ReflectionMethod( Occidg_Workflow_Admin::class, 'normalize_selected_image_ids' );
		$method->setAccessible( true );

		$this->assertSame( array( 42, 7 ), $method->invoke( $admin, array( 42, '42', 0, -7, 'invalid' ) ) );
		$this->assertSame( array(), $method->invoke( $admin, '42' ) );
	}
}
