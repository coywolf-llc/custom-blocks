<?php
/**
 * Tests for class TemplateEditor.
 *
 * @package Coywolf\CustomBlocks
 */

use Coywolf\CustomBlocks\Blocks\TemplateEditor;

/**
 * Tests for class TemplateEditor.
 */
class TestTemplateEditor extends WP_UnitTestCase {

	/**
	 * The instance to test.
	 *
	 * @var TemplateEditor
	 */
	public $instance;

	/**
	 * Sets up before each test.
	 *
	 * @inheritdoc
	 */
	public function set_up() {
		parent::set_up();
		$this->instance = new TemplateEditor();
	}

	/**
	 * Test render_css when there is no CSS to render.
	 *
	 * @covers \Coywolf\CustomBlocks\Blocks\TemplateEditor::render_css()
	 */
	public function test_render_css_empty_css() {
		ob_start();
		$this->instance->render_css( '', 'example-block' );

		$this->assertEmpty( ob_get_clean() );
	}

	/**
	 * Test render_css with 2 of the same block name.
	 *
	 * @covers \Coywolf\CustomBlocks\Blocks\TemplateEditor::render_css()
	 */
	public function test_render_css_same_block_name() {
		$block_name = 'example-block';
		$css        = '.baz { display: block; }';

		ob_start();
		$this->instance->render_css( $css, $block_name );

		$this->assertStringContainsString( "<style>{$css}</style>", ob_get_clean() );

		ob_start();
		$this->instance->render_css( $css, $block_name );

		// Once this has rendered the <style> for this block name, it shouldn't render it again.
		$this->assertEmpty( ob_get_clean() );
	}

	/**
	 * Test render_css when there are 2 different block names.
	 *
	 * @covers \Coywolf\CustomBlocks\Blocks\TemplateEditor::render_css()
	 */
	public function test_render_css_different_block_names() {
		$first_block_name  = 'text-block';
		$first_css         = '.gcb-text { background-color: #000000; }';
		$second_block_name = 'url-block';
		$second_css        = '.gcb-url { background-color: #742b2b; }';

		ob_start();
		$this->instance->render_css( $first_css, $first_block_name );

		$this->assertStringContainsString( "<style>{$first_css}</style>", ob_get_clean() );

		ob_start();
		$this->instance->render_css( $second_css, $second_block_name );

		$this->assertStringContainsString( "<style>{$second_css}</style>", ob_get_clean() );
	}

	/**
	 * A field placeholder inside a PHP span is never exposed for {{field}}
	 * substitution — so a field value (settable by a lower-privileged post
	 * author) can't be interpolated into executable PHP source.
	 *
	 * @covers \Coywolf\CustomBlocks\Blocks\TemplateEditor::mask_php_spans()
	 * @covers \Coywolf\CustomBlocks\Blocks\TemplateEditor::unmask_php_spans()
	 */
	public function test_php_spans_are_masked_from_substitution() {
		$mask   = new \ReflectionMethod( TemplateEditor::class, 'mask_php_spans' );
		$unmask = new \ReflectionMethod( TemplateEditor::class, 'unmask_php_spans' );
		$mask->setAccessible( true );
		$unmask->setAccessible( true );

		$payload = '";system("id");//';

		// Field inside a PHP string literal must not become interpretable,
		// and a literal close tag inside that string must not end the span.
		$template = 'Hi {{name}} <?php $x = "?>{{name}}"; echo "{{name}}"; ?>';
		$spans    = [];
		$masked   = $mask->invokeArgs( null, [ $template, &$spans ] );

		$this->assertSame( 1, substr_count( $masked, '{{name}}' ), 'Only the inline-HTML {{name}} stays interpretable.' );
		$this->assertCount( 1, $spans );

		// Simulate the interpreter substituting the (only) inline {{name}}.
		$rendered = $unmask->invoke( null, str_replace( '{{name}}', $payload, $masked ), $spans );

		$this->assertStringStartsWith( 'Hi ' . $payload . ' ', $rendered );
		$this->assertStringContainsString( 'echo "{{name}}"', $rendered, 'The in-PHP placeholder is restored verbatim.' );
		$this->assertSame( 0, preg_match( '/<\?php.*system\("id"\).*\?>/s', $rendered ), 'Payload is never inside a PHP span.' );

		// A no-PHP template passes through untouched with no spans.
		$plain  = '<div>{{name}}</div>';
		$spans2 = [];
		$this->assertSame( $plain, $mask->invokeArgs( null, [ $plain, &$spans2 ] ) );
		$this->assertSame( [], $spans2 );
	}
}
