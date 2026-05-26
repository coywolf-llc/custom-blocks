<?php
/**
 * Import from upstream Genesis Custom Blocks.
 *
 * Adds a "Import from Genesis Custom Blocks" submenu under the Custom Blocks
 * menu. Lists every `genesis_custom_block` post on this site — works whether
 * or not the upstream plugin is currently active, because the rows live in
 * `wp_posts` independent of post-type registration — lets the admin pick
 * which to import (with a "select all" toggle), and on submit copies each
 * one into a new `coywolf_custom_block` post.
 *
 * For each imported block the importer also looks for a theme template file
 * at `{stylesheet}/blocks/block-{slug}.php` (and the parent theme as a
 * fallback). If found, it is best-effort translated into the in-admin
 * Custom HTML / `{{field-slug}}` substitution format and stored as the
 * block's `templateMarkup`, so the imported block renders without SFTP
 * once upstream is removed. The original theme file is left in place.
 *
 * @package   Coywolf\CustomBlocks
 * @copyright Copyright(c) 2026, Coywolf LLC
 * @license http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 */

namespace Coywolf\CustomBlocks\Admin;

use Coywolf\CustomBlocks\ComponentAbstract;

/**
 * Class ImportFromGenesis
 */
class ImportFromGenesis extends ComponentAbstract {

	/**
	 * Submenu page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'coywolf-custom-blocks-import-from-genesis';

	/**
	 * Form nonce action.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'coywolf_ccb_import_from_genesis';

	/**
	 * The post type slug used by upstream Genesis Custom Blocks.
	 *
	 * @var string
	 */
	const SOURCE_POST_TYPE = 'genesis_custom_block';

	/**
	 * The block-name namespace prefix used by upstream Genesis Custom Blocks.
	 *
	 * @var string
	 */
	const SOURCE_BLOCK_NAMESPACE = 'genesis-custom-blocks';

	/**
	 * Register any hooks this component needs.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', [ $this, 'add_submenu_page' ] );
		add_action( 'admin_post_' . self::NONCE_ACTION, [ $this, 'handle_submit' ] );
	}

	/**
	 * Add the submenu page under the Custom Blocks menu.
	 */
	public function add_submenu_page() {
		add_submenu_page(
			'edit.php?post_type=' . coywolf_custom_blocks()->get_post_type_slug(),
			__( 'Import from Genesis Custom Blocks', 'coywolf-custom-blocks' ),
			__( 'Import from Genesis', 'coywolf-custom-blocks' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Render the import page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'coywolf-custom-blocks' ) );
		}

		$source_blocks = $this->get_source_blocks();
		$result        = isset( $_GET['result'] ) ? sanitize_key( wp_unslash( $_GET['result'] ) ) : '';
		$imported_csv  = isset( $_GET['imported'] ) ? sanitize_text_field( wp_unslash( $_GET['imported'] ) ) : '';
		$errors_csv    = isset( $_GET['errors'] ) ? sanitize_text_field( wp_unslash( $_GET['errors'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import from Genesis Custom Blocks', 'coywolf-custom-blocks' ); ?></h1>

			<?php $this->render_notice( $result, $imported_csv, $errors_csv ); ?>

			<p>
				<?php esc_html_e( 'This page lists every block stored on this site under the upstream Genesis Custom Blocks post type. Select the blocks you want to copy into Coywolf Custom Blocks. The original blocks are not modified — Genesis Custom Blocks can stay installed and active.', 'coywolf-custom-blocks' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'For each imported block, if your active theme contains a matching blocks/block-{slug}.php file, the importer makes a best-effort translation of its block_field()/block_value() calls into the {{field-slug}} substitution syntax and stores the result as the block\'s in-admin Custom HTML. Complex PHP (conditionals, loops, repeaters) survives as raw text and needs to be reviewed after import.', 'coywolf-custom-blocks' ); ?>
			</p>

			<?php if ( empty( $source_blocks ) ) : ?>
				<div class="notice notice-info inline">
					<p>
						<?php esc_html_e( 'No Genesis Custom Blocks posts were found on this site. Nothing to import.', 'coywolf-custom-blocks' ); ?>
					</p>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::NONCE_ACTION ); ?>" />
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>

				<table class="widefat striped" style="margin-top: 1em;">
					<thead>
						<tr>
							<td class="check-column" style="width:2.2em;">
								<input type="checkbox" id="ccb-import-toggle-all" />
							</td>
							<th scope="col"><?php esc_html_e( 'Block', 'coywolf-custom-blocks' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Slug', 'coywolf-custom-blocks' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Theme template', 'coywolf-custom-blocks' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'coywolf-custom-blocks' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $source_blocks as $row ) : ?>
						<tr>
							<th scope="row" class="check-column">
								<input type="checkbox" class="ccb-import-row" name="block_ids[]" value="<?php echo esc_attr( $row['post_id'] ); ?>" />
							</th>
							<td><strong><?php echo esc_html( $row['title'] ); ?></strong></td>
							<td><code><?php echo esc_html( $row['slug'] ); ?></code></td>
							<td>
								<?php if ( $row['template_path'] ) : ?>
									<span title="<?php echo esc_attr( $row['template_path'] ); ?>"><?php esc_html_e( 'Found', 'coywolf-custom-blocks' ); ?></span>
								<?php else : ?>
									<span class="description"><?php esc_html_e( 'None', 'coywolf-custom-blocks' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $row['target_exists'] ) : ?>
									<em><?php esc_html_e( 'Already exists in Coywolf — will be skipped', 'coywolf-custom-blocks' ); ?></em>
								<?php else : ?>
									<?php esc_html_e( 'New', 'coywolf-custom-blocks' ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Import selected blocks', 'coywolf-custom-blocks' ); ?>
					</button>
				</p>
			</form>

			<script>
				( function () {
					var toggle = document.getElementById( 'ccb-import-toggle-all' );
					if ( ! toggle ) { return; }
					toggle.addEventListener( 'change', function () {
						var rows = document.querySelectorAll( '.ccb-import-row' );
						for ( var i = 0; i < rows.length; i++ ) {
							rows[ i ].checked = toggle.checked;
						}
					} );
				} )();
			</script>
		</div>
		<?php
	}

	/**
	 * Render the success / error notice after an import POST.
	 *
	 * @param string $result       Status flag from the redirect query string.
	 * @param string $imported_csv Comma-separated titles that were imported.
	 * @param string $errors_csv   Comma-separated error strings.
	 */
	protected function render_notice( $result, $imported_csv, $errors_csv ) {
		if ( '' === $result ) {
			return;
		}

		if ( 'imported' === $result ) {
			$imported = '' === $imported_csv ? [] : array_filter( array_map( 'trim', explode( '|', $imported_csv ) ) );
			$errors   = '' === $errors_csv ? [] : array_filter( array_map( 'trim', explode( '|', $errors_csv ) ) );
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of imported blocks */
							_n( 'Imported %d block.', 'Imported %d blocks.', count( $imported ), 'coywolf-custom-blocks' ),
							count( $imported )
						)
					);
					?>
					<?php if ( ! empty( $imported ) ) : ?>
						<br />
						<?php echo esc_html( implode( ', ', $imported ) ); ?>
					<?php endif; ?>
				</p>
			</div>
			<?php if ( ! empty( $errors ) ) : ?>
				<div class="notice notice-warning is-dismissible">
					<p><strong><?php esc_html_e( 'Some blocks could not be imported:', 'coywolf-custom-blocks' ); ?></strong></p>
					<ul style="list-style: disc; padding-left: 1.5em;">
						<?php foreach ( $errors as $err ) : ?>
							<li><?php echo esc_html( $err ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php
			endif;
		} elseif ( 'nothing' === $result ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p><?php esc_html_e( 'No blocks were selected.', 'coywolf-custom-blocks' ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * List source blocks from the upstream post type.
	 *
	 * Queries `wp_posts` directly with a `suppress_filters` flag so the
	 * result is independent of whether upstream's plugin code is running
	 * (which would normally filter the post type registration). Returns
	 * an associative array per row with title, slug, the resolved theme
	 * template path (or empty string), and a flag for whether a
	 * `coywolf_custom_block` of the same slug already exists.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function get_source_blocks() {
		$query = new \WP_Query(
			[
				'post_type'              => self::SOURCE_POST_TYPE,
				'post_status'            => [ 'publish', 'draft', 'private', 'pending' ],
				'posts_per_page'         => -1,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				// Run the query even if the post type is not registered (upstream inactive).
				'suppress_filters'       => true,
			]
		);

		$out = [];
		foreach ( $query->posts as $post ) {
			$config = $this->decode_block_config( $post->post_content );
			$slug   = $post->post_name;

			// Prefer the slug stored inside the JSON config when present, since
			// that is what the original plugin used to register the block name.
			if ( is_array( $config ) && ! empty( $config['name'] ) ) {
				$slug = (string) $config['name'];
			}

			$out[] = [
				'post_id'       => (int) $post->ID,
				'title'         => $post->post_title !== '' ? $post->post_title : $slug,
				'slug'          => $slug,
				'template_path' => $this->locate_theme_template( $slug ),
				'target_exists' => $this->coywolf_block_exists( $slug ),
			];
		}

		return $out;
	}

	/**
	 * Handle the import form submission.
	 *
	 * Validates capability + nonce, copies each selected upstream block into
	 * a new `coywolf_custom_block` post, and redirects back to the page
	 * with a status query string. Blocks whose slug already exists in the
	 * Coywolf post type are skipped to avoid clobbering user changes.
	 */
	public function handle_submit() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to import blocks.', 'coywolf-custom-blocks' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$ids = isset( $_POST['block_ids'] ) && is_array( $_POST['block_ids'] )
			? array_map( 'intval', wp_unslash( $_POST['block_ids'] ) )
			: [];
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			wp_safe_redirect( $this->page_url( [ 'result' => 'nothing' ] ) );
			exit;
		}

		$imported = [];
		$errors   = [];

		foreach ( $ids as $post_id ) {
			$source = get_post( $post_id );
			if ( ! $source || self::SOURCE_POST_TYPE !== $source->post_type ) {
				$errors[] = sprintf(
					/* translators: %d: source post ID */
					__( 'Post #%d is not a Genesis Custom Blocks post.', 'coywolf-custom-blocks' ),
					$post_id
				);
				continue;
			}

			$result = $this->import_one( $source );
			if ( is_wp_error( $result ) ) {
				$errors[] = sprintf( '%s — %s', $source->post_title, $result->get_error_message() );
			} else {
				$imported[] = $source->post_title !== '' ? $source->post_title : $source->post_name;
			}
		}

		// Use | as the separator so block titles containing commas survive.
		wp_safe_redirect(
			$this->page_url(
				[
					'result'   => 'imported',
					'imported' => implode( '|', $imported ),
					'errors'   => implode( '|', $errors ),
				]
			)
		);
		exit;
	}

	/**
	 * Copy a single source post into a new `coywolf_custom_block` post.
	 *
	 * @param \WP_Post $source The upstream block post.
	 * @return int|\WP_Error New post ID on success.
	 */
	protected function import_one( $source ) {
		$config = $this->decode_block_config( $source->post_content );
		if ( ! is_array( $config ) || empty( $config['name'] ) ) {
			return new \WP_Error(
				'coywolf_ccb_invalid_source',
				__( 'The source post content is missing a block name.', 'coywolf-custom-blocks' )
			);
		}

		$slug = (string) $config['name'];

		if ( $this->coywolf_block_exists( $slug ) ) {
			return new \WP_Error(
				'coywolf_ccb_already_exists',
				__( 'A Coywolf Custom Block with this slug already exists. Skipped to avoid overwriting.', 'coywolf-custom-blocks' )
			);
		}

		// If the source has no in-admin templateMarkup but the active theme
		// has a matching template file, translate the PHP file to {{field}}
		// syntax and stash it as templateMarkup so the imported block can
		// render without depending on the theme file.
		if ( empty( $config['templateMarkup'] ) ) {
			$template_path = $this->locate_theme_template( $slug );
			if ( $template_path ) {
				$translated = $this->translate_php_template( $this->read_file_safely( $template_path ) );
				if ( '' !== $translated ) {
					$config['templateMarkup'] = $translated;
				}
			}
		}

		$envelope_key = self::SOURCE_BLOCK_NAMESPACE . '/' . $slug;
		$new_envelope = [ 'coywolf-custom-blocks/' . $slug => $config ];

		// If the source's envelope key was something exotic (e.g. a plugin
		// extended the namespace), still record the canonical mapping so the
		// imported block round-trips through Coywolf's render path.
		unset( $envelope_key );

		$post_id = wp_insert_post(
			[
				'post_type'    => coywolf_custom_blocks()->get_post_type_slug(),
				'post_status'  => 'publish',
				'post_title'   => $source->post_title !== '' ? $source->post_title : $slug,
				'post_name'    => $slug,
				'post_content' => wp_slash( wp_json_encode( $new_envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ),
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return (int) $post_id;
	}

	/**
	 * Pull the block config object out of a Genesis Custom Blocks post.
	 *
	 * Upstream stores `{ "genesis-custom-blocks/{slug}": { ...config } }`
	 * in post_content. Unwraps the namespace key and returns just the
	 * inner config; falls back to the raw decoded value if the shape is
	 * unexpected so a partial import still has something to work with.
	 *
	 * @param string $post_content Raw post_content JSON.
	 * @return array|null
	 */
	protected function decode_block_config( $post_content ) {
		$decoded = json_decode( (string) $post_content, true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}
		// Unwrap the single namespace envelope.
		foreach ( $decoded as $key => $value ) {
			if ( is_string( $key ) && false !== strpos( $key, '/' ) && is_array( $value ) ) {
				return $value;
			}
		}
		return $decoded;
	}

	/**
	 * Whether a `coywolf_custom_block` with the given slug already exists.
	 *
	 * @param string $slug Block slug.
	 * @return bool
	 */
	protected function coywolf_block_exists( $slug ) {
		$existing = get_page_by_path(
			$slug,
			OBJECT,
			coywolf_custom_blocks()->get_post_type_slug()
		);
		return null !== $existing;
	}

	/**
	 * Resolve the theme template path for a block slug, if one exists.
	 *
	 * Mirrors the upstream lookup: prefer the active (child) theme, fall
	 * back to the parent theme. Returns an empty string when no template
	 * file is present.
	 *
	 * @param string $slug Block slug (e.g. "hero").
	 * @return string Absolute path or '' if not found.
	 */
	protected function locate_theme_template( $slug ) {
		$candidates = [
			get_stylesheet_directory() . '/blocks/block-' . $slug . '.php',
			get_template_directory() . '/blocks/block-' . $slug . '.php',
		];
		foreach ( $candidates as $path ) {
			if ( file_exists( $path ) && is_readable( $path ) ) {
				return $path;
			}
		}
		return '';
	}

	/**
	 * Read a file's contents, defending against open_basedir / permission
	 * errors so a single broken template doesn't abort the whole import.
	 *
	 * @param string $path Absolute file path.
	 * @return string File contents or '' on failure.
	 */
	protected function read_file_safely( $path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = @file_get_contents( $path );
		return false === $contents ? '' : (string) $contents;
	}

	/**
	 * Best-effort translation of a Genesis Custom Blocks PHP template into
	 * the `{{field-slug}}` substitution syntax used by Coywolf's in-admin
	 * Custom HTML renderer.
	 *
	 * Handles the common patterns the upstream docs recommend:
	 *   <?php block_field( 'foo' ); ?>          ->  {{foo}}
	 *   <?php block_field( "foo" ); ?>          ->  {{foo}}
	 *   <?php echo block_value( 'foo' ); ?>     ->  {{foo}}
	 *   <?= block_field( 'foo' ); ?>            ->  {{foo}}
	 *
	 * Anything more complex (`block_field( 'foo', false )` with logic,
	 * `if`/`foreach`, repeater `block_rows()` loops, custom helpers) is
	 * left in place verbatim so the user can review it. The renderer
	 * outputs the markup as-is, so untranslated PHP tags will show in the
	 * source — that's the intended signal for "needs manual review."
	 *
	 * @param string $source Raw PHP file contents.
	 * @return string Translated markup; '' if nothing meaningful remains.
	 */
	protected function translate_php_template( $source ) {
		if ( '' === $source ) {
			return '';
		}

		// Strip a leading `<?php` header line if it is followed by nothing
		// but the doc-block comment — keep the body as-is otherwise so the
		// reviewer can see the structure.
		$result = $source;

		// `block_field( 'foo' )` and `block_value( 'foo' )` — single or double
		// quoted, with optional whitespace, optional `echo`/`print`/short echo
		// wrapper, and optional second arg (the `$echo` boolean). The capturing
		// group matches a slug of word chars, hyphens, and underscores.
		$slug_re = '[A-Za-z0-9_\-]+';

		// Note: each pattern matches a PHP open tag, the helper call, an
		// optional second boolean arg, an optional semicolon, and the PHP
		// close tag. We deliberately do not write the example tag forms in
		// comments here because a literal close-tag sequence inside a "//"
		// comment terminates PHP parsing mid-file.
		$patterns = [
			// block_field( 'foo' ) inside php open/close tags.
			'#<\?(?:php\s+)?block_field\s*\(\s*[\'"](' . $slug_re . ')[\'"](?:\s*,\s*(?:true|false|1|0))?\s*\)\s*;?\s*\?' . '>#',
			// block_value( 'foo' ) inside php open/close tags (rare; same shape).
			'#<\?(?:php\s+)?block_value\s*\(\s*[\'"](' . $slug_re . ')[\'"](?:\s*,\s*(?:true|false|1|0))?\s*\)\s*;?\s*\?' . '>#',
			// echo block_field/block_value( 'foo' ) inside php tags.
			'#<\?(?:php\s+)?echo\s+block_(?:field|value)\s*\(\s*[\'"](' . $slug_re . ')[\'"](?:\s*,\s*(?:true|false|1|0))?\s*\)\s*;?\s*\?' . '>#',
			// Short-echo form.
			'#<\?=\s*block_(?:field|value)\s*\(\s*[\'"](' . $slug_re . ')[\'"](?:\s*,\s*(?:true|false|1|0))?\s*\)\s*;?\s*\?' . '>#',
		];

		foreach ( $patterns as $re ) {
			$result = preg_replace( $re, '{{$1}}', $result );
		}

		// Add a heads-up comment when the file still contains PHP tags that we
		// did not translate, so the user is told to review it.
		if ( false !== strpos( $result, '<?' ) ) {
			$notice = "<!-- Imported from Genesis Custom Blocks template. Untranslated PHP remains below — review and convert to {{field-slug}} syntax or remove. -->\n";
			$result = $notice . $result;
		}

		return $result;
	}

	/**
	 * Build the URL of this admin page with extra query args.
	 *
	 * @param array $args Extra query args to merge.
	 * @return string
	 */
	protected function page_url( $args = [] ) {
		$base = add_query_arg(
			[
				'post_type' => coywolf_custom_blocks()->get_post_type_slug(),
				'page'      => self::PAGE_SLUG,
			],
			admin_url( 'edit.php' )
		);
		if ( ! empty( $args ) ) {
			$base = add_query_arg( $args, $base );
		}
		return $base;
	}
}
