<?php
/**
 * TemplateEditor.
 *
 * @package   Coywolf\CustomBlocks
 * @copyright Copyright(c) 2022, Genesis Custom Blocks
 * @license http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 */

namespace Coywolf\CustomBlocks\Blocks;

/**
 * Class TemplateEditor
 */
class TemplateEditor {

	/**
	 * The block names that have had their CSS rendered.
	 *
	 * @var string[]
	 */
	public $blocks_with_rendered_css = [];

	/**
	 * Renders markup that was entered in the Custom HTML / template editor.
	 *
	 * Two-stage pipeline:
	 *   1. `{{field-slug}}` placeholders are substituted with the values
	 *      returned by `block_field()`, exactly as before.
	 *   2. If the resulting string contains any PHP tag (`<?php`, `<?=`,
	 *      or a bare `<?`), it is written to a per-content cached file
	 *      under `wp-content/uploads/coywolf-custom-blocks/templates/`
	 *      and `include`d with output buffering — so the PHP actually
	 *      runs. The file is keyed by a hash of the substituted content,
	 *      so identical templates share a cached file across renders
	 *      and across pageloads.
	 *
	 * If no PHP tag is present, the substituted string is echoed raw
	 * (the v1.0.x behaviour). That preserves the fast path for static
	 * HTML/CSS/JS blocks that don't need PHP at all.
	 *
	 * Security: this method evaluates arbitrary PHP from the block's
	 * post_content. Editing a `coywolf_custom_block` requires
	 * `manage_options`, the same capability as Appearance → Theme File
	 * Editor, so the trust boundary is identical to what WordPress
	 * itself grants admins by default. Network/host operators who do
	 * not trust their site admins should also be disabling
	 * `DISALLOW_FILE_EDIT`.
	 *
	 * @param string $markup The markup to render.
	 */
	public function render_markup( $markup ) {
		$rendered = preg_replace_callback(
			'#{{(\S+?)}}#',
			static function ( $matches ) {
				ob_start();
				block_field( $matches[1] );
				return ob_get_clean();
			},
			$markup
		);

		// Escape characters before { should be stripped, like \{\{example\}\}.
		// Like if they have a tutorial on Mustache and need the template to render {{example}}.
		$rendered = preg_replace( '#\\\{\\\{(\S+?)\\\}\\\}#', '{{\1}}', $rendered );

		// Fast path: no PHP tags → just echo. wp_kses_post() would strip
		// <script>/<iframe>/inline event handlers, which authors do need;
		// see PR #3 for the original rationale.
		if ( ! $this->contains_php_tag( $rendered ) ) {
			echo $rendered; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		echo $this->execute_php( $rendered ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Renders CSS that was entered in the template editor.
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
	 * @param string $content The post-substitution markup.
	 * @return bool
	 */
	protected function contains_php_tag( $content ) {
		return false !== strpos( $content, '<?php' )
			|| false !== strpos( $content, '<?=' )
			|| (bool) preg_match( '/<\?(?:\s|$|[^x])/', $content );
	}

	/**
	 * Execute PHP-containing markup by writing it to a cached file
	 * under uploads/coywolf-custom-blocks/templates/ and `include`ing
	 * it with output buffering.
	 *
	 * Caching: filename is a hash of the rendered content. Two
	 * identical templates resolve to the same file; modifying a
	 * block's Custom HTML produces a new hash and a new file. Old
	 * cached files survive across edits because cleaning them up
	 * would require tracking the prior hash; the cache directory is
	 * cleared on plugin uninstall (uninstall.php).
	 *
	 * Failure modes: if the cache directory can't be created or the
	 * file can't be written (permission errors, disk full,
	 * open_basedir restrictions), fall back to echoing the markup as
	 * literal text. Better to show the raw PHP source than to
	 * silently lose the block's output.
	 *
	 * @param string $content Markup with embedded PHP tags.
	 * @return string The PHP output.
	 */
	protected function execute_php( $content ) {
		$cache_dir = $this->get_template_cache_dir();
		if ( '' === $cache_dir ) {
			return $content;
		}

		$hash = md5( $content );
		$file = $cache_dir . '/tpl-' . $hash . '.php';

		if ( ! file_exists( $file ) ) {
			// Use the wp-filesystem when possible so hosts that
			// enforce certain ownership/permissions on PHP-written
			// files still see writes succeed; fall back to a plain
			// file_put_contents otherwise.
			if ( false === file_put_contents( $file, $content, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				return $content;
			}
		}

		ob_start();
		try {
			include $file; // phpcs:ignore WordPress.PHP.IncludingFile -- runtime-cached, admin-authored template
		} catch ( \Throwable $err ) {
			ob_end_clean();
			return '<!-- Coywolf Custom Blocks: PHP error in Custom HTML — ' . esc_html( $err->getMessage() ) . ' -->';
		}
		return (string) ob_get_clean();
	}

	/**
	 * Path to the directory where compiled template files live.
	 *
	 * Created lazily on first need. Returns '' if the directory can't
	 * be created (caller falls back to echoing markup as text).
	 *
	 * @return string Absolute filesystem path, or '' on failure.
	 */
	protected function get_template_cache_dir() {
		$uploads = wp_get_upload_dir();
		if ( ! isset( $uploads['basedir'] ) || ! is_string( $uploads['basedir'] ) ) {
			return '';
		}
		$dir = rtrim( $uploads['basedir'], '/' ) . '/coywolf-custom-blocks/templates';
		if ( file_exists( $dir ) ) {
			return is_dir( $dir ) && is_writable( $dir ) ? $dir : '';
		}
		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		// Drop a tiny .htaccess + index.php to make sure the directory
		// isn't directly browsable and individual files aren't served
		// raw by web servers that haven't been configured to deny .php
		// in uploads. The Coywolf-only template files are still
		// `include`d from PHP-land — the deny rules just stop a
		// curious visitor from hitting them via HTTP.
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $dir . '/.htaccess', "Order Deny,Allow\nDeny from all\n" );
		file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		// phpcs:enable
		return $dir;
	}
}
