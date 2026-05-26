<?php
/**
 * Tests for class Admin.
 *
 * @package Coywolf\CustomBlocks
 */

use Coywolf\CustomBlocks\Admin\Admin;

/**
 * Tests for class Admin.
 */
class TestAdmin extends \WP_UnitTestCase {

	use TestingHelper;

	/**
	 * Instance of Admin.
	 *
	 * @var Admin
	 */
	public $instance;

	/**
	 * The slug of the Pro page.
	 *
	 * @var string
	 */
	const PRO_PAGE = 'coywolf-custom-blocks-pro';

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function set_up() {
		parent::set_up();
		$this->instance = new Admin();
	}

	/**
	 * Test init.
	 *
	 * @covers \Coywolf\CustomBlocks\Admin\Admin::init()
	 */
	public function test_init() {
		$this->instance->init();
		$documentation_class = 'Coywolf\CustomBlocks\Admin\Documentation';
		$this->assertEquals( $documentation_class, get_class( $this->instance->documentation ) );

		$reflection = new ReflectionObject( coywolf_custom_blocks() );
		$components = $reflection->getProperty( 'components' );
		$components->setAccessible( true );
		$components_value = $components->getValue( coywolf_custom_blocks() );

		// The settings should have been added to the plugin components.
		$this->assertEquals( $this->instance->documentation->slug, $components_value[ $documentation_class ]->slug );
		$this->assertArrayHasKey( $documentation_class, $components_value );
	}

	/**
	 * Test register_hooks.
	 *
	 * @covers \Coywolf\CustomBlocks\Admin\Admin::register_hooks()
	 */
	public function test_register_hooks() {
		$this->instance->register_hooks();
		$this->assertEquals( 10, has_action( 'admin_enqueue_scripts', [ $this->instance, 'enqueue_scripts' ] ) );
	}

	/**
	 * Test enqueue_scripts.
	 *
	 * @covers \Coywolf\CustomBlocks\Admin\Admin::enqueue_scripts()
	 */
	public function test_enqueue_scripts() {
		coywolf_custom_blocks()->register_component( $this->instance );
		$this->instance->set_plugin( coywolf_custom_blocks() );
		$this->instance->enqueue_scripts();
		$styles     = wp_styles();
		$handle     = 'coywolf-custom-blocks';
		$stylesheet = $styles->registered[ $handle ];

		$this->assertEquals( $handle, $stylesheet->handle );
		$this->assertStringContainsString( 'css/admin.css', $stylesheet->src );
		$this->assertEquals( [], $stylesheet->deps );
		$this->assertEquals( [], $stylesheet->extra );
		$this->assertTrue( in_array( $handle, $styles->queue, true ) );
	}
}
