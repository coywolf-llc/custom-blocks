<?php
/**
 * TemplateInterpreter.
 *
 * @package   Coywolf\CustomBlocks
 * @copyright Copyright(c) 2026, Coywolf LLC
 * @license http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 */

namespace Coywolf\CustomBlocks\Blocks;

/**
 * Interprets the Custom HTML template mini-language.
 *
 * Everything the template can do happens through a fixed grammar
 * evaluated right here in PHP — no code generation, no eval(), no
 * files written to disk — which is what keeps the main plugin eligible
 * for the WordPress.org directory. Supported constructs:
 *
 *   {{name}}                       Field output (block_field(): cast to
 *                                  string and passed through
 *                                  wp_kses_post(), as it always has been).
 *   {{name|esc_html|upper}}        The RAW field value (block_value())
 *                                  piped through whitelisted filters —
 *                                  the author opts into their own
 *                                  escaping: esc_html, esc_attr,
 *                                  esc_url, upper, lower, trim, nl2br,
 *                                  wpautop, length, json.
 *   {{#if name}} … {{else}} … {{/if}}
 *                                  Conditional on the raw value's PHP
 *                                  truthiness (empty string/array, 0,
 *                                  '0', false, null are all false).
 *                                  Nestable.
 *   {{#each name}} … {{/each}}     Loops an array value (a Multiselect
 *                                  field). Inside the block, {{item}}
 *                                  is the current value (kses'd, or
 *                                  filtered via {{item|esc_html}}) and
 *                                  {{@index}} the 0-based position.
 *   {{post:title}} {{post:permalink}} {{post:date}} {{post:author}}
 *   {{post:id}} {{site:name}} {{site:url}}
 *                                  Whitelisted context values, escaped
 *                                  for output (esc_url for the URLs,
 *                                  esc_html otherwise). Filters apply
 *                                  to the unescaped value.
 *   \{\{literal\}\}                Renders as {{literal}} (unchanged
 *                                  from previous versions — for showing
 *                                  the syntax itself).
 *
 * Forgiving by design — a template must never fatal:
 *   - Unknown plain {{tags}} render as '' (matches the old
 *     block_field() behaviour for unknown fields).
 *   - Unknown {{#block}} tags render literally (surfaces typos).
 *   - A stray {{else}}/{{/if}}/{{/each}} with no open block renders ''.
 *   - A block left unclosed at the end of the template renders its
 *     opening tag literally and its contents normally.
 *
 * Field access is injected as callables so the parser/renderer can be
 * unit-tested without WordPress: `$echo_field( $name )` returns the
 * kses'd display string (the block_field() echo path) and
 * `$get_value( $name )` the raw value (block_value()).
 */
final class TemplateInterpreter {

	/**
	 * Sentinels for the `\{\{` / `\}\}` literal-brace escapes. Control
	 * characters can't appear in a textarea-authored template, so the
	 * round-trip is collision-free.
	 */
	const ESC_OPEN  = "\x01\x01";
	const ESC_CLOSE = "\x02\x02";

	/**
	 * Returns the kses'd display string for a field (block_field() echo path).
	 *
	 * @var callable
	 */
	private $echo_field;

	/**
	 * Returns the raw value for a field (block_value()).
	 *
	 * @var callable
	 */
	private $get_value;

	/**
	 * Default sanitiser for {{item}} output.
	 *
	 * @var callable
	 */
	private $sanitize;

	/**
	 * Constructor.
	 *
	 * @param callable      $echo_field Maps a field name to its display string.
	 * @param callable      $get_value  Maps a field name to its raw value.
	 * @param callable|null $sanitize   Default sanitiser for loop items;
	 *                                  wp_kses_post when available.
	 */
	public function __construct( callable $echo_field, callable $get_value, $sanitize = null ) {
		$this->echo_field = $echo_field;
		$this->get_value  = $get_value;
		if ( null === $sanitize ) {
			$sanitize = function_exists( 'wp_kses_post' )
				? 'wp_kses_post'
				: static function ( $value ) {
					return $value;
				};
		}
		$this->sanitize = $sanitize;
	}

	/**
	 * Render a template.
	 *
	 * @param string $template The admin-authored template markup.
	 * @return string The interpreted output.
	 */
	public function render( $template ) {
		$template = str_replace( [ '\{\{', '\}\}' ], [ self::ESC_OPEN, self::ESC_CLOSE ], (string) $template );
		$output   = $this->render_nodes( $this->parse( $template ), [] );
		return str_replace( [ self::ESC_OPEN, self::ESC_CLOSE ], [ '{{', '}}' ], $output );
	}

	// ----- Parsing ----------------------------------------------------------

	/**
	 * Parse a template into a node tree.
	 *
	 * Stack-based: {{#if}}/{{#each}} push, {{/if}}/{{/each}} pop,
	 * {{else}} flips the current if-node's target list. Any block still
	 * open at the end of the template is unwound into literal text plus
	 * its already-parsed children, so malformed templates degrade
	 * gracefully instead of swallowing content.
	 *
	 * @param string $template Template with literal-brace escapes already replaced.
	 * @return object[] The root node list.
	 */
	private function parse( $template ) {
		$tokens = preg_split( '/({{[^{}\r\n]*}})/', $template, -1, PREG_SPLIT_DELIM_CAPTURE );
		$root   = (object) [
			'type'     => 'seq',
			'children' => [],
		];
		$stack  = [ $root ];

		foreach ( $tokens as $token ) {
			if ( '' === $token ) {
				continue;
			}
			$top = $stack[ count( $stack ) - 1 ];

			// Same shape the tokenizer split on — spaces/tabs only, so a
			// text chunk that merely looks like a tag across a newline
			// can never be promoted to one.
			if ( ! preg_match( '/^{{[ \t]*([^{}\r\n]*?)[ \t]*}}$/', $token, $m ) ) {
				$this->append( $top, (object) [
					'type' => 'text',
					'text' => $token,
				] );
				continue;
			}
			$inner = $m[1];

			if ( preg_match( '/^#if\s+(\S+)$/', $inner, $mm ) ) {
				$node = (object) [
					'type'          => 'if',
					'field'         => $mm[1],
					'children'      => [],
					'else_children' => null,
					'in_else'       => false,
					'raw'           => $token,
				];
				$this->append( $top, $node );
				$stack[] = $node;
				continue;
			}

			if ( preg_match( '/^#each\s+(\S+)$/', $inner, $mm ) ) {
				$node = (object) [
					'type'     => 'each',
					'field'    => $mm[1],
					'children' => [],
					'raw'      => $token,
				];
				$this->append( $top, $node );
				$stack[] = $node;
				continue;
			}

			if ( 'else' === $inner ) {
				if ( 'if' === $top->type && ! $top->in_else ) {
					$top->in_else       = true;
					$top->else_children = [];
				}
				// Stray {{else}}: drop it — an unknown plain tag rendered
				// as '' in previous versions, so this stays invisible.
				continue;
			}

			if ( '/if' === $inner || '/each' === $inner ) {
				$wants = '/if' === $inner ? 'if' : 'each';
				if ( $top->type === $wants ) {
					array_pop( $stack );
				}
				// Stray closer with nothing open: drop it (see {{else}}).
				continue;
			}

			$this->append( $top, (object) [
				'type' => 'var',
				'expr' => $inner,
				'raw'  => $token,
			] );
		}

		// Unwind anything left open: the opening tag becomes literal
		// text and the children it captured rejoin the parent.
		while ( count( $stack ) > 1 ) {
			$node   = array_pop( $stack );
			$parent = $stack[ count( $stack ) - 1 ];
			$list   = ( 'if' === $parent->type && $parent->in_else ) ? 'else_children' : 'children';
			array_pop( $parent->{$list} ); // Remove the unclosed node itself.
			$this->append( $parent, (object) [
				'type' => 'text',
				'text' => $node->raw,
			] );
			foreach ( $node->children as $child ) {
				$this->append( $parent, $child );
			}
			if ( 'if' === $node->type && null !== $node->else_children ) {
				$this->append( $parent, (object) [
					'type' => 'text',
					'text' => '{{else}}',
				] );
				foreach ( $node->else_children as $child ) {
					$this->append( $parent, $child );
				}
			}
		}

		return $root->children;
	}

	/**
	 * Append a child node to a block node's currently-active list.
	 *
	 * @param object $node  The open block (or root sequence).
	 * @param object $child The node to append.
	 */
	private function append( $node, $child ) {
		if ( 'if' === $node->type && $node->in_else ) {
			$node->else_children[] = $child;
			return;
		}
		$node->children[] = $child;
	}

	// ----- Rendering --------------------------------------------------------

	/**
	 * Render a node list.
	 *
	 * @param object[] $nodes Nodes from parse().
	 * @param array    $ctx   Loop context: ['item' => mixed, 'index' => int] inside {{#each}}.
	 * @return string
	 */
	private function render_nodes( $nodes, $ctx ) {
		$out = '';
		foreach ( $nodes as $node ) {
			switch ( $node->type ) {
				case 'text':
					$out .= $node->text;
					break;
				case 'var':
					$out .= $this->render_var( $node->expr, $node->raw, $ctx );
					break;
				case 'if':
					$value  = $this->resolve_value( $node->field, $ctx );
					$branch = $value ? $node->children : ( null !== $node->else_children ? $node->else_children : [] );
					$out   .= $this->render_nodes( $branch, $ctx );
					break;
				case 'each':
					$value = $this->resolve_value( $node->field, $ctx );
					if ( is_array( $value ) ) {
						$index = 0;
						foreach ( $value as $item ) {
							$out .= $this->render_nodes( $node->children, [
								'item'  => $item,
								'index' => $index,
							] );
							$index++;
						}
					}
					break;
			}
		}
		return $out;
	}

	/**
	 * Render a {{variable}} expression.
	 *
	 * @param string $expr The trimmed text between the braces.
	 * @param string $raw  The original token, for literal fallbacks.
	 * @param array  $ctx  Loop context.
	 * @return string
	 */
	private function render_var( $expr, $raw, $ctx ) {
		$parts = array_map( 'trim', explode( '|', $expr ) );
		$name  = array_shift( $parts );

		// Unknown {{#block}} tags stay literal so typos are visible.
		if ( '' !== $name && '#' === $name[0] ) {
			return $raw;
		}

		if ( '@index' === $name ) {
			return isset( $ctx['index'] ) ? (string) $ctx['index'] : '';
		}

		// Whitelisted post/site context values.
		if ( preg_match( '/^(post|site):([a-z_]+)$/', $name, $m ) ) {
			$provided = $this->provider_value( $m[1], $m[2] );
			if ( empty( $parts ) ) {
				return $provided['escaped'];
			}
			return $this->apply_filters_chain( $parts, $provided['value'] );
		}

		// Plain field — block_field()'s kses'd echo path, '' for unknown
		// names, exactly as before.
		if ( empty( $parts ) ) {
			if ( 'item' === $name && array_key_exists( 'item', $ctx ) ) {
				return (string) call_user_func( $this->sanitize, $this->to_string( $ctx['item'] ) );
			}
			return (string) call_user_func( $this->echo_field, $name );
		}

		// Filtered: the author takes over escaping from the raw value.
		$value = ( 'item' === $name && array_key_exists( 'item', $ctx ) )
			? $ctx['item']
			: call_user_func( $this->get_value, $name );
		return $this->apply_filters_chain( $parts, $value );
	}

	/**
	 * Resolve a name to its raw value, honouring the loop context.
	 *
	 * @param string $name Field name, or 'item' inside {{#each}}.
	 * @param array  $ctx  Loop context.
	 * @return mixed
	 */
	private function resolve_value( $name, $ctx ) {
		if ( 'item' === $name && array_key_exists( 'item', $ctx ) ) {
			return $ctx['item'];
		}
		return call_user_func( $this->get_value, $name );
	}

	/**
	 * Run a value through a chain of whitelisted filters.
	 *
	 * @param string[] $filters Filter names, in order.
	 * @param mixed    $value   The starting value.
	 * @return string
	 */
	private function apply_filters_chain( $filters, $value ) {
		foreach ( $filters as $filter ) {
			switch ( $filter ) {
				case 'esc_html':
					$value = esc_html( $this->to_string( $value ) );
					break;
				case 'esc_attr':
					$value = esc_attr( $this->to_string( $value ) );
					break;
				case 'esc_url':
					$value = esc_url( $this->to_string( $value ) );
					break;
				case 'upper':
					$value = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $this->to_string( $value ) ) : strtoupper( $this->to_string( $value ) );
					break;
				case 'lower':
					$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $this->to_string( $value ) ) : strtolower( $this->to_string( $value ) );
					break;
				case 'trim':
					$value = trim( $this->to_string( $value ) );
					break;
				case 'nl2br':
					$value = nl2br( $this->to_string( $value ) );
					break;
				case 'wpautop':
					$value = wpautop( $this->to_string( $value ) );
					break;
				case 'length':
					$value = is_array( $value ) ? (string) count( $value ) : (string) strlen( $this->to_string( $value ) );
					break;
				case 'json':
					$value = (string) wp_json_encode( $value );
					break;
				default:
					// Unknown filter: pass the value through untouched.
					break;
			}
		}
		return $this->to_string( $value );
	}

	/**
	 * Whitelisted {{post:*}} / {{site:*}} values.
	 *
	 * @param string $scope 'post' or 'site'.
	 * @param string $key   The value key.
	 * @return array{value:string, escaped:string} Raw + display-escaped forms.
	 */
	private function provider_value( $scope, $key ) {
		$value   = '';
		$escaped = null;
		if ( 'post' === $scope ) {
			switch ( $key ) {
				case 'id':
					$value = (string) (int) get_the_ID();
					break;
				case 'title':
					$value = (string) get_the_title();
					break;
				case 'permalink':
					$value   = (string) get_permalink();
					$escaped = esc_url( $value );
					break;
				case 'date':
					$value = (string) get_the_date();
					break;
				case 'author':
					$value = (string) get_the_author();
					break;
			}
		} elseif ( 'site' === $scope ) {
			switch ( $key ) {
				case 'name':
					$value = (string) get_bloginfo( 'name' );
					break;
				case 'url':
					$value   = (string) home_url( '/' );
					$escaped = esc_url( $value );
					break;
			}
		}
		return [
			'value'   => $value,
			'escaped' => null !== $escaped ? $escaped : esc_html( $value ),
		];
	}

	/**
	 * Cast any value to a display string. Arrays join with ', '.
	 *
	 * @param mixed $value The value.
	 * @return string
	 */
	private function to_string( $value ) {
		if ( is_array( $value ) ) {
			return implode( ', ', array_map( [ $this, 'to_string' ], $value ) );
		}
		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}
		if ( null === $value || is_scalar( $value ) ) {
			return (string) $value;
		}
		return '';
	}
}
