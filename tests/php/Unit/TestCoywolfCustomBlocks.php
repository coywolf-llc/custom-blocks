<?php
/**
 * TestCoywolfCustomBlocks
 *
 * @package Coywolf\CustomBlocks
 */

/**
 * Class TestCoywolfCustomBlocks
 *
 * @package Coywolf\CustomBlocks
 */
class TestCoywolfCustomBlocks extends \WP_UnitTestCase {

	/**
	 * Test coywolf_custom_blocks().
	 *
	 * @covers \coywolf_custom_blocks()
	 */
	public function test_singleton() {
		$this->assertEquals( 'Coywolf\CustomBlocks\\Plugin', get_class( coywolf_custom_blocks() ) );

		// Calling coywolf_custom_blocks() twice should return the same instance.
		$this->assertEquals( coywolf_custom_blocks(), coywolf_custom_blocks() );
	}
}
