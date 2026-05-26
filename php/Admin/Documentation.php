<?php
/**
 * Coywolf Custom Blocks Documentation page.
 *
 * Renders the contents of the plugin's `readme.md` as a localized
 * admin page rather than redirecting off to GitHub. The Markdown
 * converter is minimal and scoped to the patterns the readme
 * actually uses (headings, lists, paragraphs, inline code, fenced
 * code blocks, links, bold, and the leading `<img>` icon banner).
 *
 * @package   Coywolf\CustomBlocks
 * @copyright Copyright(c) 2026, Coywolf LLC
 * @license   http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 */

namespace Coywolf\CustomBlocks\Admin;

use Coywolf\CustomBlocks\ComponentAbstract;

/**
 * Class Documentation
 */
class Documentation extends ComponentAbstract {

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	public $slug = 'coywolf-custom-blocks-documentation';

	/**
	 * Register any hooks that this component needs.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', [ $this, 'add_submenu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
	}

	/**
	 * Add the Documentation submenu under the Custom Blocks menu.
	 */
	public function add_submenu_page() {
		$menu_title = __( 'Documentation', 'coywolf-custom-blocks' );
		add_submenu_page(
			'edit.php?post_type=' . coywolf_custom_blocks()->get_post_type_slug(),
			$menu_title,
			$menu_title,
			'manage_options',
			$this->slug,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Enqueue a small inline stylesheet on this page only.
	 *
	 * The readme renders fine with WP's default admin typography,
	 * but a few prose-specific tweaks (constrained max-width, nicer
	 * code blocks, sensible list indentation) make it easier to
	 * read than the raw cascade.
	 *
	 * @param string $hook_suffix The current admin page hook.
	 */
	public function enqueue_styles( $hook_suffix ) {
		// Submenu pages under a CPT show up as `{cpt}_page_{slug}`.
		$expected = coywolf_custom_blocks()->get_post_type_slug() . '_page_' . $this->slug;
		if ( $hook_suffix !== $expected ) {
			return;
		}

		$css = '
			.coywolf-docs { max-width: 880px; font-size: 14px; line-height: 1.6; }
			.coywolf-docs > p:first-of-type { margin-top: 0; }
			.coywolf-docs h1 { font-size: 28px; margin-top: 0; margin-bottom: 6px; }
			.coywolf-docs h2 { font-size: 22px; margin-top: 32px; padding-bottom: 6px; border-bottom: 1px solid #dcdcde; }
			.coywolf-docs h3 { font-size: 18px; margin-top: 24px; }
			.coywolf-docs h4 { font-size: 16px; margin-top: 20px; }
			.coywolf-docs ul { list-style: disc; padding-left: 1.6em; margin: 0.6em 0; }
			.coywolf-docs ol { list-style: decimal; padding-left: 1.6em; margin: 0.6em 0; }
			.coywolf-docs li { margin: 0.25em 0; }
			.coywolf-docs code { background: #f0f0f1; padding: 1px 5px; border-radius: 3px; font-size: 0.92em; }
			.coywolf-docs pre { background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px; padding: 12px 14px; overflow-x: auto; font-size: 13px; line-height: 1.5; }
			.coywolf-docs pre code { background: transparent; padding: 0; }
			.coywolf-docs a { color: #2271b1; }
			.coywolf-docs img { max-width: 128px; height: auto; margin-bottom: 12px; }
			.coywolf-docs table { border-collapse: collapse; margin: 12px 0; }
			.coywolf-docs th, .coywolf-docs td { padding: 6px 12px; border: 1px solid #dcdcde; }
		';
		wp_register_style( 'coywolf-custom-blocks-docs', false, [], '1.0' ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( 'coywolf-custom-blocks-docs' );
		wp_add_inline_style( 'coywolf-custom-blocks-docs', $css );
	}

	/**
	 * Render the Documentation page.
	 *
	 * Reads `readme.md` from the plugin root, converts it to HTML,
	 * and prints it inside a standard WP `.wrap` container.
	 */
	public function render_page() {
		// Defensive cap check — WordPress's menu loader already gates this
		// page on `manage_options`, but the other admin renderers in this
		// plugin (EditBlock, ExportImport, ImportFromGenesis) all re-check
		// inside their render methods so add_menu_page slip-ups don't
		// surface unauthorized content. Match that posture here.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'coywolf-custom-blocks' ) );
		}

		$path = coywolf_custom_blocks()->get_path() . 'readme.md';

		echo '<div class="wrap"><div class="coywolf-docs">';

		if ( ! is_readable( $path ) ) {
			echo '<h1>' . esc_html__( 'Documentation', 'coywolf-custom-blocks' ) . '</h1>';
			printf(
				'<p>%s</p>',
				esc_html__( 'The bundled readme.md file is missing from this plugin install. Reinstall the plugin from the latest GitHub release to restore it.', 'coywolf-custom-blocks' )
			);
			echo '</div></div>';
			return;
		}

		// Cache the rendered HTML keyed by the readme's mtime so the
		// Markdown→HTML pass (multiple preg_replace_callback runs over
		// the whole file) only fires once per release — the readme only
		// changes when the plugin is updated.
		$mtime     = (int) filemtime( $path );
		$cache_key = 'coywolf_ccb_docs_v1_' . $mtime;
		$html      = get_transient( $cache_key );
		if ( false === $html ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
			$markdown = @file_get_contents( $path );
			if ( '' === $markdown || false === $markdown ) {
				$html = '<h1>' . esc_html__( 'Documentation', 'coywolf-custom-blocks' ) . '</h1>';
			} else {
				$html = $this->markdown_to_html( $markdown );
			}
			set_transient( $cache_key, $html, WEEK_IN_SECONDS );
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside markdown_to_html.
		echo '</div></div>';
	}

	/**
	 * Minimal Markdown → HTML converter scoped to the readme's actual
	 * vocabulary. Not a general-purpose engine — handles only what the
	 * `readme.md` file uses today: headings, paragraphs, unordered
	 * lists, fenced code blocks, inline code, inline links, bold,
	 * and the single leading `<img>` icon banner. Anything else is
	 * passed through as escaped text.
	 *
	 * Output is escaped per-segment: inline code wraps its content in
	 * `esc_html`, link text and href are escaped too, plain prose
	 * goes through `esc_html` before re-injecting the matched
	 * formatting. The leading `<img>` is the only HTML allowed
	 * through verbatim and is rewritten to the plugin's URL.
	 *
	 * @param string $markdown Raw readme markdown.
	 * @return string HTML safe to print inside the admin page wrapper.
	 */
	protected function markdown_to_html( $markdown ) {
		$plugin_url = coywolf_custom_blocks()->get_url();
		$markdown   = (string) $markdown;

		// 1. Stash fenced code blocks. Replace with sentinels so the
		//    line-based processor below doesn't try to parse their
		//    contents as headings, lists, etc.
		$code_blocks = [];
		$markdown    = preg_replace_callback(
			'/```[a-zA-Z0-9_-]*\n(.*?)\n```/s',
			function ( $matches ) use ( &$code_blocks ) {
				$idx                 = count( $code_blocks );
				$code_blocks[ $idx ] = '<pre><code>' . esc_html( $matches[1] ) . '</code></pre>';
				return "\x00CODEBLOCK{$idx}\x00";
			},
			$markdown
		);

		// 2. Rewrite the leading `<img src="...">` so the plugin's
		//    own icon loads from the plugin URL rather than from
		//    /wp-admin's relative path.
		$markdown = preg_replace_callback(
			'#<img\s+([^>]+?)\s*/?>#i',
			function ( $matches ) use ( $plugin_url ) {
				$attrs = $matches[1];
				$attrs = preg_replace_callback(
					'/src\s*=\s*"([^"]+)"/i',
					function ( $src ) use ( $plugin_url ) {
						$url = $src[1];
						if ( ! preg_match( '#^https?://#i', $url ) ) {
							// Strip only an explicit `./` prefix — preserve a
							// leading dot on its own (e.g. `.wordpress-org/…`
							// is a real directory name, not a relative-path
							// indicator).
							$relative = preg_replace( '#^\./#', '', $url );
							$url      = rtrim( $plugin_url, '/' ) . '/' . ltrim( $relative, '/' );
						}
						return 'src="' . esc_url( $url ) . '"';
					},
					$attrs
				);
				return '<img ' . $attrs . ' />';
			},
			$markdown,
			1
		);

		// 3. Line-based processing. Each line is either a heading, a
		//    list item, a blank line (paragraph break), or a regular
		//    prose line. Code-block sentinels carry through as-is.
		$lines       = preg_split( '/\r\n|\n|\r/', $markdown );
		$out         = [];
		$list_state  = null; // 'ul' | 'ol' | null
		$buf         = [];

		$flush_paragraph = function () use ( &$buf, &$out ) {
			if ( empty( $buf ) ) {
				return;
			}
			$text = trim( implode( ' ', $buf ) );
			$buf  = [];
			if ( '' === $text ) {
				return;
			}
			// Pass-through markers (HTML img, code-block sentinel) stay raw.
			if ( 0 === strpos( $text, '<img ' ) || false !== strpos( $text, "\x00CODEBLOCK" ) ) {
				$out[] = $text;
				return;
			}
			$out[] = '<p>' . $this->inline_md( $text ) . '</p>';
		};

		$close_list = function () use ( &$list_state, &$out ) {
			if ( null !== $list_state ) {
				$out[]      = '</' . $list_state . '>';
				$list_state = null;
			}
		};

		foreach ( $lines as $line ) {
			$line = rtrim( $line );

			// Heading: 1–6 # followed by space.
			if ( preg_match( '/^(#{1,6})\s+(.+)$/', $line, $m ) ) {
				$flush_paragraph();
				$close_list();
				$level = strlen( $m[1] );
				$out[] = sprintf( '<h%1$d>%2$s</h%1$d>', $level, $this->inline_md( $m[2] ) );
				continue;
			}

			// Unordered list item: leading `-` or `*` + space.
			if ( preg_match( '/^[-*]\s+(.+)$/', $line, $m ) ) {
				$flush_paragraph();
				if ( 'ul' !== $list_state ) {
					$close_list();
					$out[]      = '<ul>';
					$list_state = 'ul';
				}
				$out[] = '<li>' . $this->inline_md( $m[1] ) . '</li>';
				continue;
			}

			// Ordered list item: leading `<digits>.` + space.
			if ( preg_match( '/^\d+\.\s+(.+)$/', $line, $m ) ) {
				$flush_paragraph();
				if ( 'ol' !== $list_state ) {
					$close_list();
					$out[]      = '<ol>';
					$list_state = 'ol';
				}
				$out[] = '<li>' . $this->inline_md( $m[1] ) . '</li>';
				continue;
			}

			// Blank line — ends current paragraph and list.
			if ( '' === trim( $line ) ) {
				$flush_paragraph();
				$close_list();
				continue;
			}

			// Continuation of paragraph (or pass-through).
			$buf[] = $line;
		}
		$flush_paragraph();
		$close_list();

		$html = implode( "\n", $out );

		// 4. Restore code blocks.
		$html = preg_replace_callback(
			"/\x00CODEBLOCK(\d+)\x00/",
			function ( $matches ) use ( $code_blocks ) {
				$idx = (int) $matches[1];
				return isset( $code_blocks[ $idx ] ) ? $code_blocks[ $idx ] : '';
			},
			$html
		);

		return $html;
	}

	/**
	 * Apply inline formatting to a single line: bold, inline code, and
	 * links. Plain text is escaped; the formatting wrappers add HTML
	 * back in around the escaped content.
	 *
	 * Order matters — code spans are extracted FIRST and replaced with
	 * sentinels so their contents aren't accidentally bolded or
	 * link-parsed. Then bold and links run against the rest.
	 *
	 * @param string $text Single line of markdown prose.
	 * @return string HTML.
	 */
	protected function inline_md( $text ) {
		// Pull out inline `code` spans first.
		$spans = [];
		$text  = preg_replace_callback(
			'/`([^`]+)`/',
			function ( $m ) use ( &$spans ) {
				$idx           = count( $spans );
				$spans[ $idx ] = '<code>' . esc_html( $m[1] ) . '</code>';
				return "\x01CODE{$idx}\x01";
			},
			$text
		);

		// Now escape the rest of the prose.
		$text = esc_html( $text );

		// Bold: **text** (escaped form is **text** since escapehtml
		// leaves * alone).
		$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );

		// Inline links: [text](https?://...). The brackets and parens
		// pass through esc_html unchanged.
		$text = preg_replace_callback(
			'/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
			function ( $m ) {
				return sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( $m[2] ),
					$m[1]
				);
			},
			$text
		);

		// Restore code spans.
		$text = preg_replace_callback(
			"/\x01CODE(\d+)\x01/",
			function ( $m ) use ( $spans ) {
				$idx = (int) $m[1];
				return isset( $spans[ $idx ] ) ? $spans[ $idx ] : '';
			},
			$text
		);

		return $text;
	}
}
