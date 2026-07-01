<?php
/**
 * TemplateEditor.
 *
 * @package   Coywolf\CustomBlocks
 * @copyright Copyright(c) 2026, Coywolf LLC
 * @license http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 */

namespace Coywolf\CustomBlocks\Blocks;

/**
 * Class TemplateEditor
 *
 * Renders the Custom HTML / Preview HTML authored in the block builder.
 *
 * Since 1.0.70 the renderer itself is WordPress.org-compliant: plain
 * templates ({{field}} substitution plus the {{#if}}/{{#each}}/filter
 * mini-language — see {@see TemplateInterpreter}) are interpreted in
 * PHP with no code generation, no eval(), and no files written to
 * disk. Templates whose admin-authored markup contains a PHP open tag
 * are handed to the GitHub-distributed "Coywolf Custom Blocks — PHP
 * Templates" companion through the
 * `coywolf_custom_blocks_execute_php_template` filter; without the
 * companion they render an explanatory HTML comment (and an admin
 * notice points at the companion).
 *
 * Option name {@see self::EXECUTOR_MISSING_OPTION} records that a
 * PHP-containing template hit the fallback, which drives that notice.
 */
class TemplateEditor {

	/**
	 * Option set when a PHP-containing template rendered without an
	 * executor hooked — read by Admin::maybe_render_php_executor_notice().
	 *
	 * @var string
	 */
	const EXECUTOR_MISSING_OPTION = 'coywolf_custom_blocks_php_executor_missing';

	/**
	 * Sentinels that stand in for a masked PHP span (wrapping its index)
	 * while the template's inline-HTML portions run through the interpreter.
	 * Control characters can't be typed into the block editor's template
	 * field, so they can never collide with real template content, and they
	 * carry no `{{`/`}}` so the interpreter leaves them untouched as text.
	 *
	 * @var string
	 */
	const PHP_MASK_OPEN  = "\x05\x05";
	const PHP_MASK_CLOSE = "\x06\x06";

	/**
	 * The block names that have had their CSS rendered.
	 *
	 * @var string[]
	 */
	public $blocks_with_rendered_css = [];

	/**
	 * Lazily-built template interpreter.
	 *
	 * @var TemplateInterpreter|null
	 */
	private $interpreter;

	/**
	 * Renders markup that was entered in the Custom HTML / template editor.
	 *
	 * Pipeline:
	 *   1. Detect PHP open tags on the RAW admin-authored template.
	 *      Deciding before substitution means content supplied through
	 *      field values can never flip a plain-HTML template onto the
	 *      execute path (belt and braces — block_field()'s echo path is
	 *      wp_kses_post()'d anyway, so values can't carry tags).
	 *   2. No PHP → interpret ({{field}} substitution plus the
	 *      {{#if}}/{{#each}}/filter mini-language) and echo verbatim (no
	 *      kses): editing a `coywolf_custom_block` requires `manage_options`,
	 *      the same capability as Appearance → Theme File Editor, so
	 *      admin-authored <script>/<iframe> pass through by design (PR #3).
	 *   3. PHP present → MASK the `<?php … ?>` spans first, so {{field}}
	 *      substitution runs only over the surrounding inline HTML, then
	 *      unmask and offer the result to an executor via the
	 *      `coywolf_custom_blocks_execute_php_template` filter (the
	 *      GitHub-only companion plugin hooks it; WordPress.org forbids
	 *      executing database-stored PHP, which is why the executor cannot
	 *      ship in this plugin). No executor → HTML-comment fallback + flag
	 *      the admin notice. Masking is the security boundary: a field VALUE
	 *      (which a lower-privileged post author can set on a block instance)
	 *      is never interpolated into the PHP source that gets executed —
	 *      inside PHP, templates must read values via block_value()/
	 *      block_field() at runtime, not `{{field}}`.
	 *
	 * @param string $markup The markup to render.
	 */
	public function render_markup( $markup ) {
		$markup  = (string) $markup;
		$has_php = self::contains_php_tag( $markup );

		if ( ! $has_php ) {
			echo $this->get_interpreter()->render( $markup ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin-authored template, rendered verbatim by design (Theme File Editor trust level).
			return;
		}

		// PHP-mode: mask the PHP spans, interpret only the inline HTML around
		// them, then restore the PHP verbatim. Field values therefore reach
		// the executor as HTML content, never as PHP source.
		$spans    = [];
		$masked   = self::mask_php_spans( $markup, $spans );
		$rendered = self::unmask_php_spans( $this->get_interpreter()->render( $masked ), $spans );

		/**
		 * Filters a PHP-containing block template to its executed output.
		 *
		 * Applied only when the RAW admin-authored Custom HTML contains a
		 * PHP open tag. The "Coywolf Custom Blocks — PHP Templates"
		 * companion (github.com/coywolf-llc/custom-blocks-php-templates)
		 * hooks this and returns the executed result; the capability is a
		 * separate GitHub-distributed plugin because the WordPress.org
		 * directory forbids executing database-stored PHP in any form.
		 *
		 * @param string|null $output   Executed output, or null when no
		 *                              executor has handled the template.
		 * @param string      $rendered The template after {{field}}
		 *                              substitution and mini-language
		 *                              interpretation.
		 */
		$executed = apply_filters( 'coywolf_custom_blocks_execute_php_template', null, $rendered );
		if ( is_string( $executed ) ) {
			echo $executed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Executed admin-authored template (Theme File Editor trust level).
			return;
		}

		$this->flag_missing_executor();
		echo '<!-- Coywolf Custom Blocks: this block template contains PHP, which only runs with the free "Coywolf Custom Blocks - PHP Templates" companion plugin installed: https://github.com/coywolf-llc/custom-blocks-php-templates -->';
	}

	/**
	 * Replace each PHP span (`<?php … ?>`, `<?= … ?>`, `<? … ?>`) in a
	 * PHP-mode template with an indexed sentinel, so the interpreter's
	 * {{field}} substitution only ever touches the inline HTML around them.
	 *
	 * This is the security boundary for PHP templates: a block instance's
	 * field values can be set by any user who can edit the post the block
	 * lives in (an Author/Contributor, not only the `manage_options` admin
	 * who authored the template), so those values must never be interpolated
	 * into the PHP source the companion executor include()s — otherwise a
	 * value like `";system($_GET[0]);"` placed into `<?php echo "{{f}}"; ?>`
	 * would execute. Inside PHP, templates read values at runtime via
	 * block_value()/block_field() instead.
	 *
	 * token_get_all() is used rather than a regex so a literal `?>` inside a
	 * PHP string can't end a span early, and so the same short_open_tag
	 * behaviour the executor's include() applies is what decides here.
	 *
	 * @param string $markup The raw admin-authored template.
	 * @param array  $spans  Filled, by reference, with the extracted spans in order.
	 * @return string The template with each PHP span replaced by a sentinel.
	 */
	private static function mask_php_spans( $markup, array &$spans ) {
		$spans  = [];
		$out    = '';
		$buffer = '';
		$in_php = false;

		foreach ( @token_get_all( $markup ) as $token ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- token_get_all warns on malformed PHP but still returns usable tokens.
			$id   = is_array( $token ) ? $token[0] : null;
			$text = is_array( $token ) ? $token[1] : $token;

			if ( ! $in_php ) {
				if ( T_OPEN_TAG === $id || T_OPEN_TAG_WITH_ECHO === $id ) {
					$in_php = true;
					$buffer = $text;
				} else {
					$out .= $text; // Inline HTML — may carry {{fields}}.
				}
				continue;
			}

			$buffer .= $text;
			if ( T_CLOSE_TAG === $id ) {
				$spans[] = $buffer;
				$out    .= self::PHP_MASK_OPEN . ( count( $spans ) - 1 ) . self::PHP_MASK_CLOSE;
				$in_php  = false;
				$buffer  = '';
			}
		}

		if ( $in_php ) { // PHP left open through the end of the template.
			$spans[] = $buffer;
			$out    .= self::PHP_MASK_OPEN . ( count( $spans ) - 1 ) . self::PHP_MASK_CLOSE;
		}

		return $out;
	}

	/**
	 * Restore masked PHP spans after interpretation. A span may appear more
	 * than once (inside a {{#each}} loop) or not at all (a false {{#if}});
	 * both restore correctly by index.
	 *
	 * @param string $rendered Interpreted template still holding sentinels.
	 * @param array  $spans    The spans captured by mask_php_spans().
	 * @return string
	 */
	private static function unmask_php_spans( $rendered, array $spans ) {
		if ( empty( $spans ) ) {
			return $rendered;
		}
		return (string) preg_replace_callback(
			'/' . preg_quote( self::PHP_MASK_OPEN, '/' ) . '(\d+)' . preg_quote( self::PHP_MASK_CLOSE, '/' ) . '/',
			static function ( $m ) use ( $spans ) {
				$i = (int) $m[1];
				return isset( $spans[ $i ] ) ? $spans[ $i ] : '';
			},
			$rendered
		);
	}

	/**
	 * Renders CSS that was entered in the template editor.
	 *
	 * Deliberately prints a `<style>` element in the body, adjacent to
	 * the block being rendered: blocks render after `wp_head` has
	 * closed, so there is no head-enqueue path, and WordPress core's
	 * own style engine emits body `<style>` tags for block supports in
	 * exactly the same situation. Only legacy `templateCss` data (the
	 * Template Editor UI that wrote it was removed in 1.0.23) reaches
	 * this method.
	 *
	 * @param string $css        The CSS to render, if any.
	 * @param string $block_name The block name, without the coywolf-custom-blocks/ namespace.
	 */
	public function render_css( $css, $block_name ) {
		if ( empty( $css ) || in_array( $block_name, $this->blocks_with_rendered_css, true ) ) {
			return;
		}

		$this->blocks_with_rendered_css[] = $block_name;

		?>
		<style><?php echo wp_strip_all_tags( $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
		<?php
	}

	/**
	 * Heuristic detector for PHP tags in a string.
	 *
	 * Matches the four open-tag forms PHP recognises: `<?php`, `<?=`,
	 * the legacy `<?` shorthand (still valid when `short_open_tag` is
	 * enabled in php.ini), and the ASP-style `<%` (vanishingly rare,
	 * removed in PHP 7, but cheap to also flag for symmetry).
	 *
	 * Called on the RAW admin-authored template only — never on
	 * field-substituted output. Public + static so other components
	 * (e.g. the Genesis importer's "this block needs the PHP Templates
	 * companion" check) share one canonical detector.
	 *
	 * @param string $content The template markup.
	 * @return bool
	 */
	public static function contains_php_tag( $content ) {
		return false !== strpos( $content, '<?php' )
			|| false !== strpos( $content, '<?=' )
			|| (bool) preg_match( '/<\?(?:\s|$|[^x])/', $content );
	}

	/**
	 * The interpreter, wired to the real block_field()/block_value() API.
	 *
	 * @return TemplateInterpreter
	 */
	private function get_interpreter() {
		if ( null === $this->interpreter ) {
			$this->interpreter = new TemplateInterpreter(
				static function ( $name ) {
					ob_start();
					block_field( $name );
					return (string) ob_get_clean();
				},
				static function ( $name ) {
					return block_value( $name );
				}
			);
		}
		return $this->interpreter;
	}

	/**
	 * Record that a PHP template rendered without an executor, so the
	 * admin notice can point at the companion plugin. One autoloaded
	 * option read per render when already flagged; one write the first
	 * time only.
	 */
	private function flag_missing_executor() {
		if ( ! get_option( self::EXECUTOR_MISSING_OPTION ) ) {
			update_option( self::EXECUTOR_MISSING_OPTION, time() );
		}
	}
}
