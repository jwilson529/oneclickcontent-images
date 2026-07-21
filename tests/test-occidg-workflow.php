<?php
/**
 * Tests for metadata workflow review decisions.
 *
 * @package Occidg
 */

defined( 'ABSPATH' ) || exit;

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/includes/class-occidg-workflow.php';

/** Covers workflow review settings. */
final class Test_Occidg_Workflow extends TestCase {

	/** Reset test options before each test. */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['occidg_options'] = array();
	}

	/**
	 * Invoke the non-public review decision helper.
	 *
	 * @param string $field      Metadata field.
	 * @param string $confidence Normalized confidence level.
	 * @param string $mode       Processing mode.
	 * @param array  $context    Generation context.
	 * @return bool
	 */
	private function should_queue_for_review( $field, $confidence, $mode = 'fill_missing', $context = array() ) {
		$workflow = new Occidg_Workflow( null, null, null );
		$method   = new ReflectionMethod( Occidg_Workflow::class, 'should_queue_for_review' );
		$method->setAccessible( true );

		return $method->invoke( $workflow, $field, $confidence, $mode, $context );
	}

	/** Low-confidence values should be held with the recommended default. */
	public function test_low_confidence_review_defaults_to_enabled() {
		$this->assertTrue( $this->should_queue_for_review( 'alt_text', 'low' ) );
	}

	/** Disabling low-confidence review should allow fill-missing values to apply. */
	public function test_low_confidence_review_can_be_disabled() {
		$GLOBALS['occidg_options']['occ_idg_require_low_confidence_review'] = false;

		$this->assertFalse( $this->should_queue_for_review( 'alt_text', 'low' ) );
	}

	/** Caption review remains independent from the low-confidence setting. */
	public function test_caption_review_still_holds_automatic_captions() {
		$GLOBALS['occidg_options']['occ_idg_require_low_confidence_review'] = false;
		$GLOBALS['occidg_options']['occ_idg_require_caption_review']        = true;

		$this->assertTrue( $this->should_queue_for_review( 'caption', 'low' ) );
	}

	/** Disabling both protections should allow an automatic low-confidence caption. */
	public function test_low_confidence_caption_applies_when_both_reviews_are_disabled() {
		$GLOBALS['occidg_options']['occ_idg_require_low_confidence_review'] = false;
		$GLOBALS['occidg_options']['occ_idg_require_caption_review']        = false;

		$this->assertFalse( $this->should_queue_for_review( 'caption', 'low' ) );
	}
}
