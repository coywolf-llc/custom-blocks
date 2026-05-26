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
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * REST endpoints that back the JS progress UI on the importer page.
	 *
	 * The page also POSTs to admin-post.php as a noscript fallback —
	 * these routes are the chunkable equivalents the in-page JS calls
	 * one block at a time so the user gets per-block progress instead
	 * of a long opaque page load.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'coywolf-custom-blocks/v1',
			'/import-genesis/block',
			[
				'methods'             => 'POST',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => [ $this, 'rest_import_block' ],
				'args'                => [
					'post_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			'coywolf-custom-blocks/v1',
			'/import-genesis/rewrite',
			[
				'methods'             => 'POST',
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'callback'            => [ $this, 'rest_rewrite_post_content' ],
				'args'                => [
					'slugs' => [
						'required' => true,
						'type'     => 'array',
					],
				],
			]
		);
	}

	/**
	 * REST callback: import a single Genesis Custom Block by post ID.
	 *
	 * Mirrors handle_submit()'s per-block path. The JS progress UI
	 * calls this once per selected ID and accumulates the responses
	 * to drive the progress bar + per-row results panel.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function rest_import_block( $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$source  = get_post( $post_id );
		if ( ! $source || self::SOURCE_POST_TYPE !== $source->post_type ) {
			return new \WP_REST_Response(
				[
					'status' => 'error',
					'error'  => sprintf(
						/* translators: %d: source post ID */
						__( 'Post #%d is not a Genesis Custom Blocks post.', 'coywolf-custom-blocks' ),
						$post_id
					),
				],
				200
			);
		}

		$result = $this->import_one( $source );
		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				[
					'status' => 'error',
					'title'  => $source->post_title,
					'error'  => $result->get_error_message(),
				],
				200
			);
		}

		return new \WP_REST_Response(
			[
				'status'  => $result['created'] ? 'imported' : 'skipped',
				'title'   => $source->post_title !== '' ? $source->post_title : $result['slug'],
				'slug'    => $result['slug'],
				'post_id' => $result['post_id'],
			],
			200
		);
	}

	/**
	 * REST callback: run the post-content rewrite for the given slugs.
	 *
	 * Currently a single call rather than batched — the underlying
	 * sweep is bounded by a `post_content LIKE '%wp:genesis-custom-blocks/%'`
	 * SQL prefilter that eliminates most rows at the database level, so
	 * even sites with tens of thousands of posts typically finish in
	 * seconds. If a real-world site hits multi-minute runs here we'll
	 * split into offset-based batches.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function rest_rewrite_post_content( $request ) {
		$raw   = (array) $request->get_param( 'slugs' );
		$slugs = array_values( array_unique( array_filter( array_map( 'strval', $raw ) ) ) );
		if ( empty( $slugs ) ) {
			return new \WP_REST_Response( [ 'updated' => 0 ], 200 );
		}
		$count = $this->rewrite_post_content( $slugs );
		return new \WP_REST_Response( [ 'updated' => (int) $count ], 200 );
	}

	/**
	 * Add the submenu page under the Custom Blocks menu.
	 *
	 * Only registered when the upstream Genesis Custom Blocks plugin is
	 * actually present and active — otherwise the menu item would link
	 * to an importer that has nothing to import. Detection uses
	 * `is_plugin_active()` against the canonical upstream basename, and
	 * pulls in wp-admin/includes/plugin.php first because that file
	 * isn't loaded on every admin pageview.
	 */
	public function add_submenu_page() {
		if ( ! $this->is_genesis_custom_blocks_active() ) {
			return;
		}
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
	 * Whether the upstream Genesis Custom Blocks plugin is currently
	 * installed and active on this site.
	 *
	 * @return bool
	 */
	protected function is_genesis_custom_blocks_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( 'genesis-custom-blocks/genesis-custom-blocks.php' );
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

		// `imported[]`, `skipped[]`, and `errors[]` now come through as
		// PHP arrays rather than a delimited string — see the redirect
		// in handle_submit() for why. The fallback `(array) … : []`
		// keeps the page robust if a stray legacy URL still uses the
		// old `?imported=A|B` shape (it'll show up as a 1-element list
		// with the names jammed together, but at least it won't crash).
		$imported_titles = isset( $_GET['imported'] ) && is_array( $_GET['imported'] )
			? array_values( array_filter( array_map( 'sanitize_text_field', wp_unslash( $_GET['imported'] ) ) ) )
			: [];
		$skipped_titles  = isset( $_GET['skipped'] ) && is_array( $_GET['skipped'] )
			? array_values( array_filter( array_map( 'sanitize_text_field', wp_unslash( $_GET['skipped'] ) ) ) )
			: [];
		$error_lines     = isset( $_GET['errors'] ) && is_array( $_GET['errors'] )
			? array_values( array_filter( array_map( 'sanitize_text_field', wp_unslash( $_GET['errors'] ) ) ) )
			: [];
		$rewrite_count   = isset( $_GET['rewrite_count'] ) && '' !== $_GET['rewrite_count'] ? (int) $_GET['rewrite_count'] : null;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import from Genesis Custom Blocks', 'coywolf-custom-blocks' ); ?></h1>

			<?php $this->render_notice( $result, $imported_titles, $skipped_titles, $error_lines, $rewrite_count ); ?>

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

			<div id="ccb-import-progress" style="display: none; margin-top: 1.5em;">
				<h2 id="ccb-import-progress-heading" style="margin-top: 0;"><?php esc_html_e( 'Importing…', 'coywolf-custom-blocks' ); ?></h2>
				<p id="ccb-import-progress-status" class="description"></p>
				<progress id="ccb-import-progress-bar" value="0" max="100" style="width: 100%; height: 1.5em;"></progress>
				<div id="ccb-import-progress-log" style="margin-top: 1em; max-height: 240px; overflow-y: auto; font-size: 13px;"></div>
			</div>

			<form
				id="ccb-import-form"
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				data-rest-block-url="<?php echo esc_url( rest_url( 'coywolf-custom-blocks/v1/import-genesis/block' ) ); ?>"
				data-rest-rewrite-url="<?php echo esc_url( rest_url( 'coywolf-custom-blocks/v1/import-genesis/rewrite' ) ); ?>"
				data-rest-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
				data-page-url="<?php echo esc_url( $this->page_url() ); ?>"
			>
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

				<p style="margin-top: 1.5em;">
					<label>
						<input type="checkbox" name="rewrite_post_content" value="1" />
						<strong><?php esc_html_e( 'After importing, also rewrite post content site-wide to use the new block names.', 'coywolf-custom-blocks' ); ?></strong>
					</label>
				</p>
				<p class="description" style="max-width: 60em;">
					<?php esc_html_e( 'Scans every post, page, custom post type entry, reusable block, and block template on this site for the imported blocks and rewrites their HTML comments from wp:genesis-custom-blocks/{slug} to wp:coywolf-custom-blocks/{slug}. Only block names that were imported in this run are rewritten; references to blocks you did not import are left alone so they keep rendering through the upstream plugin (if it is still active). Post content updates cannot be undone from this screen — back up your database before proceeding on a production site.', 'coywolf-custom-blocks' ); ?>
				</p>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Import selected blocks', 'coywolf-custom-blocks' ); ?>
					</button>
				</p>
			</form>

			<script>
				( function () {
					// Header checkbox: tick/untick every row.
					var toggle = document.getElementById( 'ccb-import-toggle-all' );
					if ( toggle ) {
						toggle.addEventListener( 'change', function () {
							var rows = document.querySelectorAll( '.ccb-import-row' );
							for ( var i = 0; i < rows.length; i++ ) {
								rows[ i ].checked = toggle.checked;
							}
						} );
					}

					// Progressive enhancement: when JS is available, intercept the
					// import form submit, call the REST endpoints one block at a
					// time, and drive an inline progress bar instead of a single
					// opaque page POST. Falls back to the regular POST when JS is
					// disabled or fetch() is missing.
					var form = document.getElementById( 'ccb-import-form' );
					if ( ! form || typeof window.fetch !== 'function' ) {
						return;
					}

					var progressUi = document.getElementById( 'ccb-import-progress' );
					var bar        = document.getElementById( 'ccb-import-progress-bar' );
					var status     = document.getElementById( 'ccb-import-progress-status' );
					var heading    = document.getElementById( 'ccb-import-progress-heading' );
					var log        = document.getElementById( 'ccb-import-progress-log' );

					var restBlockUrl   = form.getAttribute( 'data-rest-block-url' );
					var restRewriteUrl = form.getAttribute( 'data-rest-rewrite-url' );
					var restNonce      = form.getAttribute( 'data-rest-nonce' );
					var pageUrl        = form.getAttribute( 'data-page-url' );

					var appendLog = function ( message, type ) {
						var row = document.createElement( 'div' );
						row.textContent = message;
						if ( 'error' === type ) {
							row.style.color = '#b32d2e';
						} else if ( 'skipped' === type ) {
							row.style.color = '#a06d00';
						}
						log.appendChild( row );
						log.scrollTop = log.scrollHeight;
					};

					var setProgress = function ( done, total, label ) {
						var pct = total > 0 ? Math.round( ( done / total ) * 100 ) : 0;
						bar.value = pct;
						bar.max = 100;
						status.textContent = label;
					};

					form.addEventListener( 'submit', function ( event ) {
						var checked = form.querySelectorAll( 'input[name="block_ids[]"]:checked' );
						if ( 0 === checked.length ) {
							// Let the noscript POST flow handle the empty-state notice.
							return;
						}

						event.preventDefault();

						var ids = [];
						for ( var i = 0; i < checked.length; i++ ) {
							ids.push( parseInt( checked[ i ].value, 10 ) );
						}
						var rewriteEl   = form.querySelector( '[name="rewrite_post_content"]' );
						var wantRewrite = !! ( rewriteEl && rewriteEl.checked );

						// Disable the form + reveal the progress UI.
						var submitBtn = form.querySelector( 'button[type="submit"]' );
						if ( submitBtn ) { submitBtn.disabled = true; }
						form.style.opacity = '0.5';
						form.style.pointerEvents = 'none';
						progressUi.style.display = '';
						log.innerHTML = '';
						setProgress( 0, ids.length, 'Starting…' );

						// Run imports sequentially. Parallel would be faster but
						// the progress bar would jump around, and the underlying
						// SQL inserts are cheap enough that serial keeps the
						// per-block animation honest.
						var imported = [], skipped = [], errors = [], slugs = [];

						var importNext = function ( index ) {
							if ( index >= ids.length ) {
								return Promise.resolve();
							}
							setProgress(
								index,
								ids.length,
								'Importing block ' + ( index + 1 ) + ' of ' + ids.length + '…'
							);
							return fetch( restBlockUrl, {
								method: 'POST',
								credentials: 'same-origin',
								headers: {
									'Content-Type': 'application/json',
									'X-WP-Nonce':   restNonce
								},
								body: JSON.stringify( { post_id: ids[ index ] } )
							} ).then( function ( res ) {
								return res.json();
							} ).then( function ( data ) {
								if ( ! data ) {
									errors.push( 'Empty response from server' );
									appendLog( 'Empty response from server', 'error' );
								} else if ( 'imported' === data.status ) {
									imported.push( data.title );
									if ( data.slug ) { slugs.push( data.slug ); }
									appendLog( '✓ Imported "' + data.title + '"' );
								} else if ( 'skipped' === data.status ) {
									skipped.push( data.title );
									if ( data.slug ) { slugs.push( data.slug ); }
									appendLog( '↷ Skipped "' + data.title + '" (already imported)', 'skipped' );
								} else {
									errors.push( ( data.title || '#' + ids[ index ] ) + ' — ' + ( data.error || 'unknown error' ) );
									appendLog( '✗ ' + ( data.title || '#' + ids[ index ] ) + ': ' + ( data.error || 'unknown error' ), 'error' );
								}
							} ).catch( function ( err ) {
								errors.push( 'Block #' + ids[ index ] + ' — ' + err.message );
								appendLog( '✗ Block #' + ids[ index ] + ': ' + err.message, 'error' );
							} ).then( function () {
								return importNext( index + 1 );
							} );
						};

						importNext( 0 ).then( function () {
							if ( ! wantRewrite || 0 === slugs.length ) {
								return null;
							}
							setProgress( ids.length, ids.length, 'Rewriting post content site-wide…' );
							appendLog( '— Rewriting block names in post content…' );
							return fetch( restRewriteUrl, {
								method: 'POST',
								credentials: 'same-origin',
								headers: {
									'Content-Type': 'application/json',
									'X-WP-Nonce':   restNonce
								},
								body: JSON.stringify( { slugs: slugs } )
							} ).then( function ( res ) {
								return res.json();
							} ).then( function ( data ) {
								var count = data && typeof data.updated === 'number' ? data.updated : 0;
								appendLog( '✓ Rewrote block names in ' + count + ' post(s)' );
								return count;
							} );
						} ).then( function ( rewriteCount ) {
							setProgress( ids.length, ids.length, 'Done.' );
							heading.textContent = 'Import complete';
							appendLog(
								'— Summary: ' +
								imported.length + ' imported, ' +
								skipped.length + ' skipped, ' +
								errors.length + ' error(s).'
							);

							// Bounce back to the page with each title as its
							// own array entry — managed-host WAFs (Rocket,
							// Cloudflare, ModSecurity) sometimes strip
							// URL-encoded `|` characters on suspicion of
							// SQL injection, which collapsed all titles
							// into one un-separated blob and made the
							// count read as "1 block." Array-form params
							// (`imported[]=A&imported[]=B`) sidestep that.
							var encodeArrayParam = function ( key, values ) {
								if ( ! values || ! values.length ) {
									return '';
								}
								var encodedKey = encodeURIComponent( key ) + '%5B%5D=';
								return values.map( function ( v ) {
									return '&' + encodedKey + encodeURIComponent( v );
								} ).join( '' );
							};
							var query = '&result=imported' +
								encodeArrayParam( 'imported', imported ) +
								encodeArrayParam( 'skipped',  skipped ) +
								encodeArrayParam( 'errors',   errors );
							if ( wantRewrite ) {
								query += '&rewrite_count=' + encodeURIComponent( rewriteCount || 0 );
							}
							window.setTimeout( function () {
								window.location.href = pageUrl + query;
							}, 1200 );
						} );
					} );
				} )();
			</script>
		</div>
		<?php
	}

	/**
	 * Render the success / error notice after an import.
	 *
	 * @param string   $result        Status flag from the redirect query string.
	 * @param string[] $imported      Titles newly created on this run.
	 * @param string[] $skipped       Titles that already existed and were left alone.
	 * @param string[] $errors        Per-block error strings.
	 * @param int|null $rewrite_count Number of posts rewritten, or null if the
	 *                                rewrite option was not selected.
	 */
	protected function render_notice( $result, $imported, $skipped, $errors, $rewrite_count ) {
		if ( '' === $result ) {
			return;
		}

		if ( 'imported' === $result ) {
			$imported = (array) $imported;
			$skipped  = (array) $skipped;
			$errors   = (array) $errors;
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
				</p>
				<?php if ( ! empty( $imported ) ) : ?>
					<ul style="list-style: disc; padding-left: 1.5em; margin: 0;">
						<?php foreach ( $imported as $title ) : ?>
							<li><?php echo esc_html( $title ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( null !== $rewrite_count ) : ?>
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of posts/pages whose content was rewritten */
								_n(
									'Rewrote block names in %d post.',
									'Rewrote block names in %d posts.',
									$rewrite_count,
									'coywolf-custom-blocks'
								),
								$rewrite_count
							)
						);
						?>
					</p>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $skipped ) ) : ?>
				<div class="notice notice-info is-dismissible">
					<p>
						<strong>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: number of skipped blocks */
									_n(
										'%d block already existed and was left alone:',
										'%d blocks already existed and were left alone:',
										count( $skipped ),
										'coywolf-custom-blocks'
									),
									count( $skipped )
								)
							);
							?>
						</strong>
					</p>
					<ul style="list-style: disc; padding-left: 1.5em; margin: 0;">
						<?php foreach ( $skipped as $title ) : ?>
							<li><?php echo esc_html( $title ); ?></li>
						<?php endforeach; ?>
					</ul>
					<?php if ( null !== $rewrite_count ) : ?>
						<p>
							<?php esc_html_e( 'Block-name rewrites in post content still ran for these slugs so any pages using them now resolve to your Coywolf blocks.', 'coywolf-custom-blocks' ); ?>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
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

		$should_rewrite = ! empty( $_POST['rewrite_post_content'] );

		$imported       = []; // Newly created — shown in success notice.
		$skipped        = []; // Already existed — shown as a soft warning.
		$rewrite_slugs  = []; // Both — eligible for the content sweep.
		$errors         = [];

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
				continue;
			}

			$title           = $source->post_title !== '' ? $source->post_title : $result['slug'];
			$rewrite_slugs[] = $result['slug'];
			if ( $result['created'] ) {
				$imported[] = $title;
			} else {
				$skipped[] = $title;
			}
		}

		$rewrite_count = 0;
		if ( $should_rewrite && ! empty( $rewrite_slugs ) ) {
			$rewrite_count = $this->rewrite_post_content( array_values( array_unique( $rewrite_slugs ) ) );
		}

		// Send each title as its own `imported[]=…` array entry rather
		// than a delimited blob — managed-host WAFs (Rocket, Cloudflare,
		// ModSecurity) sometimes strip the URL-encoded `|` (%7C) on
		// suspicion of SQL-injection, which on this page collapsed every
		// title into one un-separated string and made the count read as
		// "1 block." PHP unpacks `?imported[]=A&imported[]=B` into an
		// array on its own; no delimiter parsing needed downstream.
		wp_safe_redirect(
			add_query_arg(
				[
					'result'        => 'imported',
					'imported'      => $imported,
					'skipped'       => $skipped,
					'errors'        => $errors,
					'rewrite_count' => $should_rewrite ? $rewrite_count : '',
				],
				$this->page_url()
			)
		);
		exit;
	}

	/**
	 * Copy a single source post into a new `coywolf_custom_block` post.
	 *
	 * Returns an array on success or skip, so the caller can distinguish
	 * "newly created" from "already exists, left alone" — both cases are
	 * eligible for the post-content rewrite step because a Coywolf block
	 * with that slug now exists on the site either way.
	 *
	 * @param \WP_Post $source The upstream block post.
	 * @return array{post_id:int,slug:string,created:bool}|\WP_Error
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

		// Theme-template translation: if the active theme has a matching
		// `blocks/block-{slug}.php` file, use ITS translated content as
		// the imported block's templateMarkup — even if the upstream
		// block already had its own templateMarkup value. Upstream
		// Genesis Custom Blocks sometimes pre-seeds that field with
		// auto-generated boilerplate (or a stale earlier copy of the
		// theme file), and the theme file is the source of truth for
		// how the block actually rendered on the live site. Falling
		// back only when the upstream field was empty caused users to
		// land on Coywolf blocks with placeholder markup that didn't
		// reflect the theme's actual template.
		$template_path  = $this->locate_theme_template( $slug );
		$theme_markup   = '';
		if ( $template_path ) {
			$theme_markup = $this->translate_php_template( $this->read_file_safely( $template_path ) );
		}

		// Same lookup for the optional `blocks/preview-{slug}.php` —
		// upstream Genesis's convention for "what should this block
		// look like in the editor when its real output is invisible
		// (e.g. Schema.org JSON-LD)". When found, we import it as the
		// new block's previewMarkup AND flip showPreview on so the
		// Preview HTML panel appears on the edit-block UI.
		$preview_path   = $this->locate_theme_template( $slug, 'preview' );
		$preview_markup = '';
		if ( $preview_path ) {
			$preview_markup = $this->translate_php_template( $this->read_file_safely( $preview_path ) );
		}

		// Existing-block handling. The slug already exists in Coywolf
		// (typical when re-running the importer to pick up theme
		// templates added between runs, or simply to back-fill a block
		// whose first import missed the template). If the existing
		// block has an empty templateMarkup AND the theme file
		// translated to something useful, patch the existing post's
		// post_content to fold the theme markup in. Otherwise leave
		// the existing block alone — the user may have hand-edited
		// its Custom HTML and we don't want to clobber that.
		$existing = get_page_by_path( $slug, OBJECT, coywolf_custom_blocks()->get_post_type_slug() );
		if ( $existing instanceof \WP_Post ) {
			// Always re-key the icon to the Lucide default + maybe also
			// back-fill the templateMarkup from the theme file. The icon
			// update fires unconditionally because the user's explicit
			// ask is "every block imported from Genesis uses
			// LuSquareCode" — including blocks imported by a previous
			// run that still carry stale upstream icon slugs.
			$this->update_existing_block( $existing, $slug, $theme_markup, $preview_markup );
			return [
				'post_id' => (int) $existing->ID,
				'slug'    => $slug,
				'created' => false,
			];
		}

		if ( '' !== $theme_markup ) {
			$config['templateMarkup'] = $theme_markup;
		}

		if ( '' !== $preview_markup ) {
			$config['previewMarkup'] = $preview_markup;
			$config['showPreview']   = true;
		}

		// Force the icon to the plugin's default Lucide glyph for every
		// imported block. Upstream Genesis icons referenced a Dashicons
		// string ('genesis_custom_blocks', 'attach_file', etc.) that
		// doesn't resolve in our react-icons-based picker, so without
		// this override the imported block would land with a missing
		// icon — and the user-facing intent here is "give me a fresh
		// Coywolf block with the standard glyph, the upstream icon
		// choice doesn't carry over."
		$config['icon'] = 'lu/LuSquareCode';

		$new_envelope = [ 'coywolf-custom-blocks/' . $slug => $config ];

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

		return [
			'post_id' => (int) $post_id,
			'slug'    => $slug,
			'created' => true,
		];
	}

	/**
	 * Update an existing Coywolf block to align it with the latest
	 * importer behaviour. Two changes can land here:
	 *
	 *   1. Icon re-key. ALWAYS overwrites the stored icon with the
	 *      Lucide default `lu/LuSquareCode`. The user's explicit ask
	 *      is "every block imported from Genesis uses LuSquareCode" —
	 *      including blocks imported by an older version of this
	 *      plugin where the importer preserved upstream's Dashicons-
	 *      style icon name (which never resolved in the react-icons
	 *      picker and showed as blank).
	 *
	 *   2. Optional templateMarkup back-fill. Only fires when the
	 *      theme file is present AND the existing block's
	 *      templateMarkup is empty. Never clobbers user-authored
	 *      Custom HTML — re-runs stay idempotent for blocks the
	 *      admin has hand-edited.
	 *
	 * @param \WP_Post $existing       The existing Coywolf block post.
	 * @param string   $slug           The block slug (also its post_name).
	 * @param string   $theme_markup   Translated `block-{slug}.php` content,
	 *                                 or '' if no theme file was found.
	 * @param string   $preview_markup Translated `preview-{slug}.php` content,
	 *                                 or '' if no preview file was found.
	 * @return void
	 */
	protected function update_existing_block( $existing, $slug, $theme_markup, $preview_markup = '' ) {
		$config = $this->decode_block_config( $existing->post_content );
		if ( ! is_array( $config ) ) {
			$config = [ 'name' => $slug ];
		}
		$config['name'] = $slug; // safety: ensure name is set

		$dirty = false;

		// Icon: always re-key.
		if ( ! isset( $config['icon'] ) || 'lu/LuSquareCode' !== $config['icon'] ) {
			$config['icon'] = 'lu/LuSquareCode';
			$dirty          = true;
		}

		// Strip the legacy "Imported from Genesis Custom Blocks
		// template. Untranslated PHP remains below — review and
		// convert ..." notice from any pre-v1.0.20 imports that still
		// carry it at the top of templateMarkup. v1.0.19 made PHP
		// execute through the Custom HTML pipeline, so the notice
		// became misleading (it says "review and convert" when the
		// PHP would just run fine). Only the exact notice line is
		// removed; the rest of the markup is untouched.
		$raw_markup = isset( $config['templateMarkup'] ) ? (string) $config['templateMarkup'] : '';
		$cleaned    = preg_replace(
			'#^<!-- Imported from Genesis Custom Blocks template\.[^>]*-->\r?\n?#',
			'',
			$raw_markup
		);
		if ( null !== $cleaned && $cleaned !== $raw_markup ) {
			$config['templateMarkup'] = $cleaned;
			$dirty                    = true;
		}

		// Template markup: only back-fill an empty field from a
		// translated theme file. Never overwrite user content.
		$current_markup = isset( $config['templateMarkup'] ) ? trim( (string) $config['templateMarkup'] ) : '';
		if ( '' === $current_markup && '' !== $theme_markup ) {
			$config['templateMarkup'] = $theme_markup;
			$dirty                    = true;
		}

		// Same conservative back-fill for the editor preview. Empty
		// previewMarkup + a theme `preview-{slug}.php` translation in
		// hand → fold the preview into the existing block AND flip
		// `showPreview` so the panel opens up on the next edit. Never
		// overwrite a previewMarkup the admin has already authored.
		$current_preview = isset( $config['previewMarkup'] ) ? trim( (string) $config['previewMarkup'] ) : '';
		if ( '' === $current_preview && '' !== $preview_markup ) {
			$config['previewMarkup'] = $preview_markup;
			$config['showPreview']   = true;
			$dirty                   = true;
		}

		if ( ! $dirty ) {
			return;
		}

		$envelope = [ 'coywolf-custom-blocks/' . $slug => $config ];
		wp_update_post(
			[
				'ID'           => (int) $existing->ID,
				'post_content' => wp_slash( wp_json_encode( $envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ),
			]
		);
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
	 * Pass `'preview'` for `$prefix` to find the matching
	 * `blocks/preview-{slug}.php` upstream-style placeholder template
	 * (used to render something visible in the editor when the real
	 * block output is invisible, e.g. Schema.org JSON-LD only).
	 *
	 * @param string $slug   Block slug (e.g. "hero").
	 * @param string $prefix `'block'` (default, → `block-{slug}.php`) or
	 *                       `'preview'` (→ `preview-{slug}.php`).
	 * @return string Absolute path or '' if not found.
	 */
	protected function locate_theme_template( $slug, $prefix = 'block' ) {
		$filename   = $prefix . '-' . $slug . '.php';
		$candidates = [
			get_stylesheet_directory() . '/blocks/' . $filename,
			get_template_directory() . '/blocks/' . $filename,
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
		// Inner-call: the literal `block_field('foo')` or `block_value('foo')`
		// invocation, with optional 2nd-arg (true|false|1|0). Used as a
		// sub-pattern in each whole-statement form below.
		$call_re = 'block_(?:field|value)\s*\(\s*[\'"](' . $slug_re . ')[\'"](?:\s*,\s*(?:true|false|1|0))?\s*\)';

		// Common WP escape wrappers that templates wrap around the block
		// helper output. The Custom HTML renderer doesn't escape its own
		// output (admin-authored content, manage_options-only), so we
		// strip the wrapper and substitute `{{slug}}` regardless of which
		// escape function was used. Patterns also accept calls with no
		// wrapper at all.
		$escape_fns = 'esc_html|esc_attr|esc_url|esc_url_raw|esc_textarea|esc_js|esc_html__|esc_attr__|wp_kses_post|wp_kses_data|wp_kses|html_entity_decode|trim|wp_strip_all_tags';

		// Patterns below match PHP open/close-tag boundaries; we deliberately
		// do not write example tag forms in comments because a literal
		// close-tag sequence inside a "//" comment terminates PHP parsing
		// mid-file.
		$patterns = [
			// 1. Standalone helper call inside php tags.
			'#<\?(?:php\s+)?' . $call_re . '\s*;?\s*\?' . '>#',

			// 2. echo + helper, no escape wrapper.
			'#<\?(?:php\s+)?echo\s+' . $call_re . '\s*;?\s*\?' . '>#',

			// 3. echo + single escape wrapper around helper (covers
			//    esc_html / esc_attr / esc_url / wp_kses_post / etc.).
			'#<\?(?:php\s+)?echo\s+(?:' . $escape_fns . ')\s*\(\s*' . $call_re . '\s*\)\s*;?\s*\?' . '>#',

			// 4. echo + double-wrapped helper (e.g. esc_url wrapping esc_url_raw).
			'#<\?(?:php\s+)?echo\s+(?:' . $escape_fns . ')\s*\(\s*(?:' . $escape_fns . ')\s*\(\s*' . $call_re . '\s*\)\s*\)\s*;?\s*\?' . '>#',

			// 5. Short-echo form, optionally with one escape wrapper.
			'#<\?=\s*(?:(?:' . $escape_fns . ')\s*\(\s*)?' . $call_re . '\s*\)?\s*;?\s*\?' . '>#',
		];

		foreach ( $patterns as $re ) {
			$result = preg_replace( $re, '{{$1}}', $result );
		}

		// Untranslated PHP tags are left as-is. Pre-v1.0.19 we
		// prepended a "<!-- review and convert -->" notice because
		// raw PHP wouldn't execute through the renderer; v1.0.19's
		// PHP-execution pipeline runs whatever PHP survives, so the
		// notice is misleading — nothing to fix.
		return $result;
	}

	/**
	 * Sweep every post on the site, rewriting block-comment markers for the
	 * given slugs from `wp:genesis-custom-blocks/{slug}` to
	 * `wp:coywolf-custom-blocks/{slug}`. Both opening (`<!-- wp:...`) and
	 * closing (`<!-- /wp:...`) forms are handled by a single replacement
	 * because the literal `wp:genesis-custom-blocks/{slug}` substring
	 * appears verbatim in both.
	 *
	 * The candidate set is restricted via a single LIKE query on
	 * `post_content` so most rows are skipped at the SQL level. Each row
	 * is then processed in PHP with a per-slug regex that uses a negative
	 * lookahead `(?![A-Za-z0-9_-])` to prevent partial matches when one
	 * imported slug is a prefix of another (e.g. importing both `hero`
	 * and `hero-two` — the `hero` pattern must not eat into `hero-two`).
	 *
	 * Posts whose content actually changed are updated via the WordPress
	 * post API (so revision/cache hooks fire); posts whose content is
	 * unchanged after substitution are skipped to avoid creating noisy
	 * revisions. Returns the number of posts updated.
	 *
	 * Covers every post type with status `any` except auto-drafts,
	 * revisions, inherit, and trash. That picks up regular posts, pages,
	 * custom post types, reusable blocks (`wp_block`), and FSE template
	 * artifacts (`wp_template`, `wp_template_part`).
	 *
	 * @param string[] $slugs The block slugs to rewrite.
	 * @return int Number of posts updated.
	 */
	protected function rewrite_post_content( $slugs ) {
		global $wpdb;

		$slugs = array_values( array_unique( array_filter( array_map( 'strval', $slugs ) ) ) );
		if ( empty( $slugs ) ) {
			return 0;
		}

		// Process longer slugs first so a longer slug is rewritten before any
		// shorter slug that happens to be its prefix. (We also use a negative
		// lookahead, but ordering is belt-and-braces.)
		usort(
			$slugs,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		// Find candidate post IDs in chunks so a site with hundreds of
		// thousands of posts doesn't blow up memory. The LIKE is intentional
		// — `wp:genesis-custom-blocks/` is the only substring guaranteed to
		// appear inside an upstream block comment, and the underscore in
		// `wp_posts.post_content` doesn't need any escaping here because the
		// pattern itself has no SQL wildcards beyond our own `%`s.
		$batch_size = 200;
		$offset     = 0;
		$updated    = 0;

		do {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					WHERE post_status NOT IN ( 'auto-draft', 'inherit', 'trash' )
					AND post_content LIKE %s
					ORDER BY ID ASC
					LIMIT %d OFFSET %d",
					'%wp:genesis-custom-blocks/%',
					$batch_size,
					$offset
				)
			);

			if ( empty( $ids ) ) {
				break;
			}

			foreach ( $ids as $post_id ) {
				$post_id = (int) $post_id;
				$post    = get_post( $post_id );
				if ( ! $post instanceof \WP_Post ) {
					continue;
				}

				$original = (string) $post->post_content;
				$rewritten = $this->rewrite_block_namespaces_in( $original, $slugs );

				if ( $rewritten === $original ) {
					continue;
				}

				$result = wp_update_post(
					[
						'ID'           => $post_id,
						'post_content' => wp_slash( $rewritten ),
					],
					true
				);
				if ( ! is_wp_error( $result ) ) {
					$updated++;
				}
			}

			$offset += $batch_size;
		} while ( true );

		return $updated;
	}

	/**
	 * Rewrite block-comment markers in a single content string.
	 *
	 * Public so callers and tests can exercise the substitution directly
	 * without booting the post query.
	 *
	 * @param string   $content Raw post_content.
	 * @param string[] $slugs   Slugs whose namespace should be rewritten.
	 * @return string
	 */
	public function rewrite_block_namespaces_in( $content, $slugs ) {
		if ( '' === $content || false === strpos( $content, 'wp:genesis-custom-blocks/' ) ) {
			return $content;
		}

		foreach ( $slugs as $slug ) {
			if ( '' === $slug ) {
				continue;
			}
			$pattern = '#wp:genesis-custom-blocks/' . preg_quote( $slug, '#' ) . '(?![A-Za-z0-9_\-])#';
			$content = preg_replace( $pattern, 'wp:coywolf-custom-blocks/' . $slug, $content );
		}

		return $content;
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
