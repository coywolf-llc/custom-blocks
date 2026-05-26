<?php
/**
 * Tests for class Documentation.
 *
 * @package Coywolf\CustomBlocks
 */

use Coywolf\CustomBlocks\Admin\Documentation;
use function Brain\Monkey\setup;
use function Brain\Monkey\tearDown;
use function Brain\Monkey\Functions\expect;

/**
 * Tests for class Documentation.
 */
class TestDocumentation extends \WP_UnitTestCase {

	/**
	 * Instance of Documentation.
	 *
	 * @var Admin\Documentation
	 */
	public $instance;

	/**
	 * The slug of the parent of the submenu.
	 *
	 * @var string
	 */
	const SUBMENU_PARENT_SLUG = 'edit.php?post_type=coywolf_custom_block';

	/**
	 * Setup.
	 *
	 * @inheritdoc
	 */
	public function set_up() {
		parent::set_up();
		setUp();
		$this->instance = new Documentation();
		$this->instance->set_plugin( coywolf_custom_blocks() );
	}

	/**
	 * Teardown.
	 *
	 * @inheritdoc
	 */
	public function tear_down() {
		global $submenu;

		unset( $submenu[ self::SUBMENU_PARENT_SLUG ] );
		tearDown();
		parent::tear_down();
	}

	/**
	 * Test register_hooks.
	 *
	 * @covers \Coywolf\CustomBlocks\Admin\Documentation::register_hooks()
	 */
	public function test_register_hooks() {
		$this->instance->register_hooks();
		$this->assertEquals( 10, has_action( 'admin_menu', [ $this->instance, 'add_submenu_page' ] ) );
		$this->assertEquals( 10, has_action( 'admin_enqueue_scripts', [ $this->instance, 'enqueue_styles' ] ) );
	}

	/**
	 * Test add_submenu_page.
	 *
	 * @covers \Coywolf\CustomBlocks\Admin\Documentation::add_submenu_page()
	 */
	public function test_add_submenu_page() {
		global $submenu;

		$expected_submenu = [
			'Documentation',
			'manage_options',
			$this->instance->slug,
			'Documentation',
		];

		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'author' ] ) );
		$this->instance->add_submenu_page();

		// Because the current user doesn't have 'manage_options' permissions, this shouldn't add the submenu.
		$this->assertFalse( isset( $submenu ) && array_key_exists( self::SUBMENU_PARENT_SLUG, $submenu ) );

		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->instance->add_submenu_page();

		// Now that the user has 'manage_options' permissions, this should add the submenu.
		$this->assertEquals( [ $expected_submenu ], $submenu[ self::SUBMENU_PARENT_SLUG ] );
	}

	/**
	 * Documentation now renders the bundled readme.md inline instead
	 * of redirecting off-site. Smoke-test that render_page produces
	 * some HTML when the readme file is present.
	 *
	 * @covers \Coywolf\CustomBlocks\Admin\Documentation::render_page()
	 */
	public function test_render_page_outputs_html() {
		ob_start();
		$this->instance->render_page();
		$html = ob_get_clean();

		$this->assertNotEmpty( $html );
		$this->assertStringContainsString( '<div class="wrap"><div class="coywolf-docs">', $html );
	}
}
