<?php
/**
 * Tests for class TemplateInterpreter.
 *
 * @package Coywolf\CustomBlocks
 */

use Coywolf\CustomBlocks\Blocks\TemplateInterpreter;

/**
 * Tests for class TemplateInterpreter.
 *
 * The interpreter takes injected resolvers, so these tests run against
 * a fixed value map without needing block registration.
 */
class TestTemplateInterpreter extends WP_UnitTestCase {

	/**
	 * The instance to test.
	 *
	 * @var TemplateInterpreter
	 */
	public $instance;

	/**
	 * Fixed field values backing the injected resolvers.
	 *
	 * @var array
	 */
	public $values = [
		'title'    => 'My <em>Title</em> & more',
		'empty'    => '',
		'zero'     => '0',
		'flag-on'  => true,
		'flag-off' => false,
		'tags'     => [ 'php', 'css', 'a&b' ],
		'none'     => [],
		'spaces'   => '  pad  ',
	];

	/**
	 * Sets up before each test.
	 *
	 * @inheritdoc
	 */
	public function set_up() {
		parent::set_up();
		$values         = $this->values;
		$this->instance = new TemplateInterpreter(
			static function ( $name ) use ( $values ) {
				if ( ! array_key_exists( $name, $values ) ) {
					return '';
				}
				$value = $values[ $name ];
				if ( is_array( $value ) ) {
					$value = implode( ', ', $value );
				}
				if ( is_bool( $value ) ) {
					$value = $value ? '1' : '';
				}
				return wp_kses_post( (string) $value );
			},
			static function ( $name ) use ( $values ) {
				return array_key_exists( $name, $values ) ? $values[ $name ] : null;
			}
		);
	}

	/**
	 * Substitution, unknown fields, escapes, and filters.
	 *
	 * @covers \Coywolf\CustomBlocks\Blocks\TemplateInterpreter::render()
	 */
	public function test_render_substitution_and_filters() {
		$this->assertSame( '<h2>My <em>Title</em> & more</h2>', $this->instance->render( '<h2>{{title}}</h2>' ) );
		$this->assertSame( 'ab', $this->instance->render( 'a{{nope}}b' ) );
		$this->assertSame( 'Use {{title}} here', $this->instance->render( 'Use \{\{title\}\} here' ) );
		$this->assertSame( 'My &lt;em&gt;Title&lt;/em&gt; &amp; more', $this->instance->render( '{{title|esc_html}}' ) );
		$this->assertSame( 'PAD', $this->instance->render( '{{spaces|trim|upper}}' ) );
		$this->assertSame( 'PAD', $this->instance->render( '{{spaces|bogus|trim|upper}}' ) );
		$this->assertSame( '3', $this->instance->render( '{{tags|length}}' ) );
	}

	/**
	 * Conditionals, truthiness, and nesting.
	 *
	 * @covers \Coywolf\CustomBlocks\Blocks\TemplateInterpreter::render()
	 */
	public function test_render_conditionals() {
		$this->assertSame( 'YES', $this->instance->render( '{{#if title}}YES{{/if}}' ) );
		$this->assertSame( '', $this->instance->render( '{{#if empty}}YES{{/if}}' ) );
		$this->assertSame( 'B', $this->instance->render( '{{#if zero}}A{{else}}B{{/if}}' ) );
		$this->assertSame( 'A', $this->instance->render( '{{#if flag-on}}A{{else}}B{{/if}}' ) );
		$this->assertSame( 'B', $this->instance->render( '{{#if flag-off}}A{{else}}B{{/if}}' ) );
		$this->assertSame( 'B', $this->instance->render( '{{#if none}}A{{else}}B{{/if}}' ) );
		$this->assertSame( 'B', $this->instance->render( '{{#if missing}}A{{else}}B{{/if}}' ) );
		$this->assertSame( 'XB', $this->instance->render( '{{#if title}}X{{#if empty}}A{{else}}B{{/if}}{{/if}}' ) );
	}

	/**
	 * Loops over array values.
	 *
	 * @covers \Coywolf\CustomBlocks\Blocks\TemplateInterpreter::render()
	 */
	public function test_render_loops() {
		$this->assertSame(
			'<li>0:php</li><li>1:css</li><li>2:a&b</li>',
			$this->instance->render( '{{#each tags}}<li>{{@index}}:{{item}}</li>{{/each}}' )
		);
		$this->assertSame( 'php|css|a&amp;b|', $this->instance->render( '{{#each tags}}{{item|esc_html}}|{{/each}}' ) );
		$this->assertSame( '', $this->instance->render( '{{#each title}}X{{/each}}' ) );
		$this->assertSame( '', $this->instance->render( '{{#each none}}X{{/each}}' ) );
		$this->assertSame( '', $this->instance->render( '{{@index}}' ) );
	}

	/**
	 * Malformed templates degrade gracefully instead of fataling or
	 * swallowing content.
	 *
	 * @covers \Coywolf\CustomBlocks\Blocks\TemplateInterpreter::render()
	 */
	public function test_render_malformed_templates() {
		$this->assertSame( '{{#if title}}content', $this->instance->render( '{{#if title}}content' ) );
		$this->assertSame( 'ab', $this->instance->render( 'a{{/if}}b' ) );
		$this->assertSame( 'ab', $this->instance->render( 'a{{else}}b' ) );
		$this->assertSame( 'a{{#bogus x}}b', $this->instance->render( 'a{{#bogus x}}b' ) );
		$this->assertSame( "{{\ntitle}}", $this->instance->render( "{{\ntitle}}" ) );
	}
}
