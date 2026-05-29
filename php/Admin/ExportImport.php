<?php
/**
 * Export and import Coywolf Custom Blocks as JSON.
 *
 * Adds a "Export / Import" submenu under Custom Blocks, plus row + bulk
 * actions on the block list table. Exports produce a single JSON file
 * whose top-level keys are block namespaces (e.g.
 * `coywolf-custom-blocks/hero`) and whose values are the same block
 * config object stored in the source post's `post_content`. The format
 * round-trips cleanly: a file produced by Export can be uploaded back
 * via Import on another site without conversion.
 *
 * Imports accept the same envelope shape. When an uploaded file
 * contains a slug that already exists on this site, the importer asks
 * whether to replace the existing block or create a renamed copy
 * (see render_confirm_form()); nothing is ever silently overwritten.
 *
 * @package   Coywolf\CustomBlocks
 * @copyright Copyright(c) 2026, Coywolf LLC
 * @license http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 */

namespace Coywolf\CustomBlocks\Admin;

use Coywolf\CustomBlocks\ComponentAbstract;

/**
 * Class ExportImport
 */
class ExportImport extends ComponentAbstract {

	/**
	 * Submenu page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'coywolf-custom-blocks-export-import';

	/**
	 * admin-post action for the export download.
	 *
	 * @var string
	 */
	const EXPORT_ACTION = 'coywolf_ccb_export';

	/**
	 * admin-post action for the JSON import upload.
	 *
	 * @var string
	 */
	const IMPORT_ACTION = 'coywolf_ccb_import';

	/**
	 * Bulk action key used on the post list table.
	 *
	 * @var string
	 */
	const BULK_EXPORT_ACTION = 'coywolf_ccb_bulk_export';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', [ $this, 'add_submenu_page' ] );
		add_action( 'admin_post_' . self::EXPORT_ACTION, [ $this, 'handle_export' ] );
		add_action( 'admin_post_' . self::IMPORT_ACTION, [ $this, 'handle_import' ] );

		// Block list table row + bulk actions. Hooked at priority 11 so they
		// run AFTER BlockPost::bulk_actions() (priority 10) strips 'Edit'.
		$post_type = coywolf_custom_blocks()->get_post_type_slug();
		add_filter( 'bulk_actions-edit-' . $post_type, [ $this, 'add_bulk_export_action' ], 11 );
		add_filter( 'handle_bulk_actions-edit-' . $post_type, [ $this, 'handle_bulk_export' ], 10, 3 );
		// Hook both row-action filters. `coywolf_custom_block` is
		// registered with `hierarchical => true`, so WordPress applies
		// `page_row_actions` (not `post_row_actions`) for its list
		// table — which silently dropped this row link in v1.0.6
		// through v1.0.18. We hook the page filter too, and keep the
		// post filter for forward-compat if the post type is ever
		// changed back to non-hierarchical.
		add_filter( 'post_row_actions', [ $this, 'add_row_export_action' ], 10, 2 );
		add_filter( 'page_row_actions', [ $this, 'add_row_export_action' ], 10, 2 );
		add_action( 'admin_notices', [ $this, 'maybe_render_bulk_notice' ] );
	}

	/**
	 * Add the submenu under Custom Blocks.
	 */
	public function add_submenu_page() {
		add_submenu_page(
			'edit.php?post_type=' . coywolf_custom_blocks()->get_post_type_slug(),
			__( 'Export & Import', 'coywolf-custom-blocks' ),
			__( 'Export & Import', 'coywolf-custom-blocks' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Render the admin page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'coywolf-custom-blocks' ) );
		}

		$total_blocks = $this->count_blocks();
		$result       = isset( $_GET['result'] ) ? sanitize_key( wp_unslash( $_GET['result'] ) ) : '';
		$imported_csv = isset( $_GET['imported'] ) ? sanitize_text_field( wp_unslash( $_GET['imported'] ) ) : '';
		$errors_csv   = isset( $_GET['errors'] ) ? sanitize_text_field( wp_unslash( $_GET['errors'] ) ) : '';
		$confirm_key  = isset( $_GET['confirm_key'] ) ? sanitize_key( wp_unslash( $_GET['confirm_key'] ) ) : '';
		$confirm_slugs = isset( $_GET['confirm_slugs'] ) && is_array( $_GET['confirm_slugs'] )
			? array_values( array_filter( array_map( 'sanitize_text_field', wp_unslash( $_GET['confirm_slugs'] ) ) ) )
			: [];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Coywolf Custom Blocks — Export & Import', 'coywolf-custom-blocks' ); ?></h1>

			<?php $this->render_notice( $result, $imported_csv, $errors_csv ); ?>

			<h2><?php esc_html_e( 'Export', 'coywolf-custom-blocks' ); ?></h2>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of blocks defined on this site */
						_n(
							'Download a single JSON file containing %d block.',
							'Download a single JSON file containing all %d blocks.',
							$total_blocks,
							'coywolf-custom-blocks'
						),
						$total_blocks
					)
				);
				?>
				<?php esc_html_e( 'The file can be imported on any other site running Coywolf Custom Blocks.', 'coywolf-custom-blocks' ); ?>
			</p>

			<?php if ( 0 === $total_blocks ) : ?>
				<p><em><?php esc_html_e( 'No blocks to export yet — create one first.', 'coywolf-custom-blocks' ); ?></em></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::EXPORT_ACTION ); ?>" />
					<?php wp_nonce_field( self::EXPORT_ACTION ); ?>
					<p class="submit">
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Export all blocks', 'coywolf-custom-blocks' ); ?>
						</button>
					</p>
					<p class="description">
						<?php esc_html_e( 'To export a subset, go to All Blocks, tick the blocks you want, and pick "Export selected" from the Bulk Actions dropdown — or use the "Export" link on a single row.', 'coywolf-custom-blocks' ); ?>
					</p>
				</form>
			<?php endif; ?>

			<hr style="margin: 2em 0;" />

			<h2><?php esc_html_e( 'Import', 'coywolf-custom-blocks' ); ?></h2>
			<p>
				<?php esc_html_e( 'Upload a JSON file produced by Export on this or another Coywolf Custom Blocks site. If a block in the file has the same slug as a block that already exists here, you\'ll be asked whether to replace it or create a new copy.', 'coywolf-custom-blocks' ); ?>
			</p>

			<?php if ( 'confirm' === $result && '' !== $confirm_key && ! empty( $confirm_slugs ) ) : ?>
				<?php $this->render_confirm_form( $confirm_key, $confirm_slugs ); ?>
			<?php else : ?>
				<form
					method="post"
					enctype="multipart/form-data"
					action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::IMPORT_ACTION ); ?>" />
					<?php wp_nonce_field( self::IMPORT_ACTION ); ?>
					<p>
						<input
							type="file"
							name="ccb_import_file"
							accept="application/json,.json"
							required
						/>
					</p>
					<p class="submit">
						<button type="submit" class="button button-primary">
							<?php esc_html_e( 'Upload and import', 'coywolf-custom-blocks' ); ?>
						</button>
					</p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the per-import confirmation prompt that's shown when at
	 * least one block in the uploaded JSON file collides with a slug
	 * that already exists on this site.
	 *
	 * @param string   $confirm_key The transient token from `stash_envelope()`.
	 * @param string[] $slugs       Colliding slugs to display.
	 */
	protected function render_confirm_form( $confirm_key, $slugs ) {
		?>
		<div class="notice notice-warning inline" style="margin: 1em 0; padding: 1em 1.2em;">
			<p>
				<strong><?php esc_html_e( 'Some blocks in this file already exist on this site.', 'coywolf-custom-blocks' ); ?></strong>
			</p>
			<ul style="list-style: disc; padding-left: 1.5em; margin: 0.5em 0 1em;">
				<?php foreach ( $slugs as $slug ) : ?>
					<li><code><?php echo esc_html( $slug ); ?></code></li>
				<?php endforeach; ?>
			</ul>

			<form
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::IMPORT_ACTION ); ?>" />
				<input type="hidden" name="ccb_payload_key" value="<?php echo esc_attr( $confirm_key ); ?>" />
				<?php wp_nonce_field( self::IMPORT_ACTION ); ?>

				<p>
					<label style="display:block; margin-bottom:0.5em;">
						<input type="radio" name="ccb_import_mode" value="replace" checked />
						<strong><?php esc_html_e( 'Replace the existing blocks', 'coywolf-custom-blocks' ); ?></strong>
						—
						<?php esc_html_e( 'Overwrite each matching block\'s configuration with the version from the file.', 'coywolf-custom-blocks' ); ?>
					</label>
					<label style="display:block;">
						<input type="radio" name="ccb_import_mode" value="copy" />
						<strong><?php esc_html_e( 'Create new copies', 'coywolf-custom-blocks' ); ?></strong>
						—
						<?php esc_html_e( 'Keep the existing blocks. Imported copies are renamed by appending a number (e.g. test-block → test-block-2).', 'coywolf-custom-blocks' ); ?>
					</label>
				</p>
				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Continue import', 'coywolf-custom-blocks' ); ?>
					</button>
					<a class="button" href="<?php echo esc_url( $this->page_url() ); ?>">
						<?php esc_html_e( 'Cancel', 'coywolf-custom-blocks' ); ?>
					</a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the redirect-target notice for export/import results.
	 *
	 * @param string $result       'exported', 'imported', 'nothing', or 'error'.
	 * @param string $imported_csv Pipe-separated titles imported.
	 * @param string $errors_csv   Pipe-separated error strings.
	 */
	protected function render_notice( $result, $imported_csv, $errors_csv ) {
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
						<br /><?php echo esc_html( implode( ', ', $imported ) ); ?>
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
		} elseif ( 'error' === $result ) {
			$msg = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php echo esc_html( '' !== $msg ? $msg : __( 'Import failed.', 'coywolf-custom-blocks' ) ); ?></p>
			</div>
			<?php
		} elseif ( 'nothing' === $result ) {
			?>
			<div class="notice notice-warning is-dismissible">
				<p><?php esc_html_e( 'Nothing to do.', 'coywolf-custom-blocks' ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Handle the export download. Streams a JSON file and exits.
	 *
	 * Accepts an optional `post[]` array of post IDs from the bulk action
	 * form; with no IDs, exports every block on the site.
	 */
	public function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export blocks.', 'coywolf-custom-blocks' ) );
		}

		check_admin_referer( self::EXPORT_ACTION );

		// Accept a single ID from the per-row GET link (`?post=123`) or an
		// array from any future bulk-style POST form (`post[]=123&post[]=456`).
		$post_ids = [];
		if ( isset( $_POST['post'] ) && is_array( $_POST['post'] ) ) {
			$post_ids = array_filter( array_map( 'intval', wp_unslash( $_POST['post'] ) ) );
		} elseif ( isset( $_REQUEST['post'] ) ) {
			$raw      = wp_unslash( $_REQUEST['post'] );
			$post_ids = array_filter( array_map( 'intval', is_array( $raw ) ? $raw : [ $raw ] ) );
		}

		$envelope = $this->build_export_envelope( $post_ids );

		if ( empty( $envelope ) ) {
			wp_safe_redirect( $this->page_url( [ 'result' => 'nothing' ] ) );
			exit;
		}

		$this->send_json_download( $envelope, $this->build_filename( $post_ids, $envelope ) );
		exit;
	}

	/**
	 * Add an "Export" link to each block's row actions on the list table.
	 *
	 * @param array    $actions Row action links.
	 * @param \WP_Post $post    The post for the row.
	 * @return array
	 */
	public function add_row_export_action( $actions, $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return $actions;
		}
		if ( coywolf_custom_blocks()->get_post_type_slug() !== $post->post_type ) {
			return $actions;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}

		// A row action is a link, not a form. To get a single-block download
		// from a GET we need a one-off nonced URL that POSTs on submit; use
		// an inline form via the data-href pattern instead.
		$url = wp_nonce_url(
			add_query_arg(
				[
					'action' => self::EXPORT_ACTION,
					'post'   => $post->ID,
				],
				admin_url( 'admin-post.php' )
			),
			self::EXPORT_ACTION
		);

		$export_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Export', 'coywolf-custom-blocks' )
		);

		// Slot the link in between Edit and Trash so the row reads
		// "Edit | Export | Trash" rather than "Edit | Trash | Export"
		// (the natural result of just `$actions['ccb-export'] = …`,
		// which appends to the end of the associative array).
		//
		// WP's default row-action keys for a hierarchical CPT are
		// `edit`, `inline hide-if-no-js` (Quick Edit, which BlockPost
		// strips elsewhere), `trash`, and `view`. Walk the existing
		// array and rebuild it in the right order so the result is
		// deterministic regardless of which keys are present.
		$reordered = [];
		foreach ( $actions as $key => $markup ) {
			$reordered[ $key ] = $markup;
			if ( 'edit' === $key ) {
				$reordered['ccb-export'] = $export_link;
			}
		}
		// Defensive: if there was no `edit` key (e.g. a custom row
		// action filter ran before us and removed it), still surface
		// our Export at the tail rather than dropping it on the floor.
		if ( ! isset( $reordered['ccb-export'] ) ) {
			$reordered['ccb-export'] = $export_link;
		}

		return $reordered;
	}

	/**
	 * Add the "Export selected" entry to the bulk actions dropdown.
	 *
	 * @param array $actions Current bulk actions.
	 * @return array
	 */
	public function add_bulk_export_action( $actions ) {
		$actions[ self::BULK_EXPORT_ACTION ] = __( 'Export selected', 'coywolf-custom-blocks' );
		return $actions;
	}

	/**
	 * Bulk action handler — fires when the user submits the bulk form with
	 * our action selected. Returns a redirect URL pointing at admin-post.php
	 * with a POST payload would be ideal, but WordPress's bulk handler is
	 * GET-only; we instead build the export inline here and stream the
	 * file, then exit so WordPress does not follow up with its own redirect.
	 *
	 * @param string $redirect_to The default redirect URL.
	 * @param string $doaction    The bulk action that fired.
	 * @param int[]  $post_ids    Selected post IDs.
	 * @return string Redirect URL (unused when we stream + exit).
	 */
	public function handle_bulk_export( $redirect_to, $doaction, $post_ids ) {
		if ( self::BULK_EXPORT_ACTION !== $doaction ) {
			return $redirect_to;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return $redirect_to;
		}

		$ids      = array_filter( array_map( 'intval', (array) $post_ids ) );
		$envelope = $this->build_export_envelope( $ids );

		if ( empty( $envelope ) ) {
			return add_query_arg(
				[
					'ccb_export_status' => 'nothing',
				],
				$redirect_to
			);
		}

		$this->send_json_download( $envelope, $this->build_filename( $ids, $envelope ) );
		exit;
	}

	/**
	 * Render a one-line admin notice on the post list after a bulk export
	 * that produced an empty file (e.g. no rows selected). Only shows on
	 * the relevant screen and only when our query arg is present.
	 */
	public function maybe_render_bulk_notice() {
		if ( ! isset( $_GET['ccb_export_status'] ) ) {
			return;
		}
		$status = sanitize_key( wp_unslash( $_GET['ccb_export_status'] ) );
		if ( 'nothing' !== $status ) {
			return;
		}
		?>
		<div class="notice notice-warning is-dismissible">
			<p><?php esc_html_e( 'No blocks were selected to export.', 'coywolf-custom-blocks' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Handle the JSON upload import. Decodes the envelope, inserts/replaces
	 * each `coywolf_custom_block` post, and redirects back to the page.
	 */
	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to import blocks.', 'coywolf-custom-blocks' ) );
		}

		check_admin_referer( self::IMPORT_ACTION );

		// Two entry points share this handler:
		//
		//   1. First upload — the form posts a `ccb_import_file`. We
		//      parse the file, detect slug collisions, and either run
		//      the import (if no collisions) or stash the parsed
		//      envelope in a transient and redirect to a confirmation
		//      screen.
		//
		//   2. Confirmation submit — the user picked a mode (replace
		//      or copy) and posted the transient key + chosen mode
		//      back. We retrieve the envelope and run the import.
		$confirm_key = isset( $_POST['ccb_payload_key'] )
			? sanitize_key( wp_unslash( $_POST['ccb_payload_key'] ) )
			: '';
		$mode        = isset( $_POST['ccb_import_mode'] )
			? sanitize_key( wp_unslash( $_POST['ccb_import_mode'] ) )
			: '';
		if ( ! in_array( $mode, [ '', 'replace', 'copy' ], true ) ) {
			$mode = '';
		}

		if ( '' !== $confirm_key ) {
			$envelope = $this->load_stashed_envelope( $confirm_key );
			if ( null === $envelope ) {
				wp_safe_redirect(
					$this->page_url(
						[
							'result'  => 'error',
							'message' => __( 'Your import session expired. Please upload the file again.', 'coywolf-custom-blocks' ),
						]
					)
				);
				exit;
			}
			$this->delete_stashed_envelope( $confirm_key );
		} else {
			$envelope = $this->envelope_from_upload();
			if ( is_wp_error( $envelope ) ) {
				wp_safe_redirect(
					$this->page_url(
						[
							'result'  => 'error',
							'message' => $envelope->get_error_message(),
						]
					)
				);
				exit;
			}
		}

		// Detect collisions — if any of the incoming block slugs match
		// existing posts AND the user hasn't told us how to handle them,
		// stash the envelope and bounce to the confirmation screen.
		$collisions = $this->find_slug_collisions( $envelope );
		if ( ! empty( $collisions ) && '' === $mode ) {
			$key = $this->stash_envelope( $envelope );
			wp_safe_redirect(
				$this->page_url(
					[
						'result'         => 'confirm',
						'confirm_key'    => $key,
						'confirm_slugs'  => $collisions,
					]
				)
			);
			exit;
		}

		// Default mode when there are no collisions: just import.
		if ( '' === $mode ) {
			$mode = 'replace';
		}

		$imported = [];
		$errors   = [];

		foreach ( $envelope as $namespace_key => $block_config ) {
			if ( ! is_string( $namespace_key ) || ! is_array( $block_config ) ) {
				$errors[] = __( 'Skipped a malformed entry (not an object).', 'coywolf-custom-blocks' );
				continue;
			}
			if ( empty( $block_config['name'] ) || ! is_string( $block_config['name'] ) ) {
				$errors[] = sprintf(
					/* translators: %s: the namespace key from the JSON file */
					__( 'Skipped "%s" — missing block name.', 'coywolf-custom-blocks' ),
					$namespace_key
				);
				continue;
			}

			$original_slug = (string) $block_config['name'];
			$slug          = $original_slug;
			$renamed       = false;

			// Rename to a free slug when the user picked "create new
			// copies" and the original slug is already in use. Updates
			// the `name` field inside the block config and the envelope
			// key so register_block_type lines up with the post we'll
			// insert. Also append the same numeric suffix to the
			// block's title so the All Blocks list and inserter make
			// the copies easy to tell apart ("Test block" → "Test
			// block 2").
			if ( 'copy' === $mode && in_array( $slug, $collisions, true ) ) {
				$slug                 = $this->find_unique_slug( $slug );
				$block_config['name'] = $slug;
				$renamed              = ( $slug !== $original_slug );
				if ( $renamed && ! empty( $block_config['title'] ) ) {
					$suffix = substr( $slug, strlen( $original_slug ) + 1 );
					if ( '' !== $suffix ) {
						$block_config['title'] = (string) $block_config['title'] . ' ' . $suffix;
					}
				}
			}

			$canonical_key = 'coywolf-custom-blocks/' . $slug;

			$result = $this->insert_or_replace_block( $canonical_key, $block_config );
			if ( is_wp_error( $result ) ) {
				$errors[] = sprintf( '%s — %s', $slug, $result->get_error_message() );
				continue;
			}

			$title      = ! empty( $block_config['title'] ) ? (string) $block_config['title'] : $slug;
			$imported[] = $renamed ? sprintf( '%s (%s)', $title, $slug ) : $title;
		}

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
	 * Read the upload, decode the JSON, return the normalised envelope.
	 *
	 * @return array|\WP_Error Envelope on success, WP_Error with a
	 *                        translated user-facing message on failure.
	 */
	protected function envelope_from_upload() {
		if ( ! isset( $_FILES['ccb_import_file']['tmp_name'] ) || '' === $_FILES['ccb_import_file']['tmp_name'] ) {
			return new \WP_Error( 'ccb_no_file', __( 'No file was uploaded.', 'coywolf-custom-blocks' ) );
		}
		if ( ! empty( $_FILES['ccb_import_file']['error'] ) ) {
			return new \WP_Error( 'ccb_upload_failed', __( 'Upload failed; the file was rejected by the server.', 'coywolf-custom-blocks' ) );
		}

		$tmp_path = sanitize_text_field( $_FILES['ccb_import_file']['tmp_name'] );
		if ( '' === $tmp_path || ! is_uploaded_file( $tmp_path ) ) {
			return new \WP_Error( 'ccb_unreadable', __( 'Could not read the uploaded file.', 'coywolf-custom-blocks' ) );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw = @file_get_contents( $tmp_path );
		if ( false === $raw || '' === $raw ) {
			return new \WP_Error( 'ccb_unreadable', __( 'Could not read the uploaded file.', 'coywolf-custom-blocks' ) );
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded ) ) {
			return new \WP_Error( 'ccb_bad_json', __( 'The file is not valid Coywolf Custom Blocks JSON.', 'coywolf-custom-blocks' ) );
		}

		// Tolerate two shapes:
		//   1. Direct envelope:  { "coywolf-custom-blocks/foo": {...}, ... }
		//   2. Wrapped envelope: { "blocks": { ... } } from a future format
		// Always normalise to (1).
		if ( isset( $decoded['blocks'] ) && is_array( $decoded['blocks'] ) ) {
			return $decoded['blocks'];
		}
		return $decoded;
	}

	/**
	 * Returns the subset of block slugs in `$envelope` that already
	 * have a matching `coywolf_custom_block` post.
	 *
	 * @param array $envelope
	 * @return string[]
	 */
	protected function find_slug_collisions( $envelope ) {
		$post_type  = coywolf_custom_blocks()->get_post_type_slug();
		$collisions = [];
		foreach ( $envelope as $block_config ) {
			if ( ! is_array( $block_config ) || empty( $block_config['name'] ) ) {
				continue;
			}
			$slug = (string) $block_config['name'];
			if ( $slug !== '' && get_page_by_path( $slug, OBJECT, $post_type ) instanceof \WP_Post ) {
				$collisions[] = $slug;
			}
		}
		return array_values( array_unique( $collisions ) );
	}

	/**
	 * Find the next free `{slug}-{n}` for `$slug`, starting at -2.
	 *
	 * @param string $slug
	 * @return string
	 */
	protected function find_unique_slug( $slug ) {
		$post_type = coywolf_custom_blocks()->get_post_type_slug();
		$slug      = (string) $slug;
		if ( '' === $slug ) {
			return $slug;
		}
		if ( ! get_page_by_path( $slug, OBJECT, $post_type ) instanceof \WP_Post ) {
			return $slug;
		}
		$n = 2;
		while ( get_page_by_path( $slug . '-' . $n, OBJECT, $post_type ) instanceof \WP_Post ) {
			$n++;
		}
		return $slug . '-' . $n;
	}

	/**
	 * Stash a parsed envelope in a 10-minute transient keyed by a random
	 * token + the current user id (so two admins can't collide on the
	 * same key). Returns the token for the confirmation URL.
	 *
	 * @param array $envelope
	 * @return string Transient key fragment.
	 */
	protected function stash_envelope( $envelope ) {
		// Drop any previous unconfirmed stashes for this user before
		// writing a new one. Without this, every uploaded-but-not-yet-
		// confirmed file would leave an envelope row in wp_options
		// until its 10-minute TTL elapsed — M4 in the 1.0.42 perf
		// audit. We DELETE rather than wait for `delete_expired_transients`
		// so a user re-uploading a different file doesn't bloat the
		// options table.
		$this->delete_user_stashes();

		$token = wp_generate_password( 16, false, false );
		$key   = $this->stash_transient_name( $token );
		set_transient( $key, $envelope, 10 * MINUTE_IN_SECONDS );
		return $token;
	}

	/**
	 * Delete every still-live stash transient for the current user.
	 * Called before writing a new stash and (defensively) when the
	 * user finalises the import. Uses a direct DB query because there
	 * isn't a `delete_transient_pattern()` in core.
	 */
	protected function delete_user_stashes() {
		global $wpdb;
		$prefix = '_transient_ccb_import_stash_' . get_current_user_id() . '_';
		$like   = $wpdb->esc_like( $prefix ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- targeted cleanup; transient API has no prefix-delete.
		$names = $wpdb->get_col(
			$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
		);
		if ( empty( $names ) ) {
			return;
		}
		foreach ( $names as $name ) {
			// Strip the `_transient_` prefix to get the bare transient
			// key; let `delete_transient` deal with the timeout row.
			delete_transient( substr( (string) $name, strlen( '_transient_' ) ) );
		}
	}

	/**
	 * Reverse of `stash_envelope()`. Returns null if the transient is
	 * gone (expired or wrong user).
	 *
	 * @param string $token
	 * @return array|null
	 */
	protected function load_stashed_envelope( $token ) {
		$envelope = get_transient( $this->stash_transient_name( $token ) );
		return is_array( $envelope ) ? $envelope : null;
	}

	/**
	 * Drop a stashed envelope once it's been consumed.
	 *
	 * @param string $token
	 */
	protected function delete_stashed_envelope( $token ) {
		delete_transient( $this->stash_transient_name( $token ) );
	}

	/**
	 * Compute the transient name for a given token. User-scoped so two
	 * admins importing at the same time can't read each other's stash.
	 *
	 * @param string $token
	 * @return string
	 */
	protected function stash_transient_name( $token ) {
		return 'ccb_import_stash_' . get_current_user_id() . '_' . preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );
	}

	/**
	 * Build the export envelope keyed by canonical block namespace.
	 *
	 * @param int[] $post_ids Optional restrict to these post IDs; empty = all blocks.
	 * @return array<string,array> Map of "coywolf-custom-blocks/{slug}" => block config.
	 */
	protected function build_export_envelope( $post_ids = [] ) {
		$args = [
			'post_type'              => coywolf_custom_blocks()->get_post_type_slug(),
			'post_status'            => [ 'publish', 'draft', 'private', 'pending' ],
			'posts_per_page'         => -1,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];
		if ( ! empty( $post_ids ) ) {
			$args['post__in'] = $post_ids;
			$args['orderby']  = 'post__in';
		}

		$query    = new \WP_Query( $args );
		$envelope = [];

		foreach ( $query->posts as $post ) {
			$config = $this->decode_block_config( $post->post_content );
			if ( ! is_array( $config ) || empty( $config['name'] ) ) {
				continue;
			}
			$slug                                            = (string) $config['name'];
			$envelope[ 'coywolf-custom-blocks/' . $slug ]    = $config;
		}

		return $envelope;
	}

	/**
	 * Unwrap the namespace-keyed envelope stored in post_content. Returns
	 * the inner block config array or null on malformed input.
	 *
	 * @param string $post_content Raw post content.
	 * @return array|null
	 */
	protected function decode_block_config( $post_content ) {
		$decoded = json_decode( (string) $post_content, true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}
		foreach ( $decoded as $key => $value ) {
			if ( is_string( $key ) && false !== strpos( $key, '/' ) && is_array( $value ) ) {
				return $value;
			}
		}
		return $decoded;
	}

	/**
	 * Insert or replace a single block from an import file.
	 *
	 * @param string $namespace_key Canonical envelope key, e.g.
	 *                              "coywolf-custom-blocks/hero".
	 * @param array  $block_config  The block config.
	 * @return int|\WP_Error New/updated post ID on success.
	 */
	protected function insert_or_replace_block( $namespace_key, $block_config ) {
		$slug      = (string) $block_config['name'];
		$post_type = coywolf_custom_blocks()->get_post_type_slug();

		$existing = get_page_by_path( $slug, OBJECT, $post_type );

		$post_data = [
			'post_type'    => $post_type,
			'post_status'  => 'publish',
			'post_title'   => ! empty( $block_config['title'] ) ? (string) $block_config['title'] : $slug,
			'post_name'    => $slug,
			'post_content' => wp_slash( wp_json_encode( [ $namespace_key => $block_config ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ),
		];

		if ( $existing instanceof \WP_Post ) {
			$post_data['ID'] = $existing->ID;
		}

		return wp_insert_post( $post_data, true );
	}

	/**
	 * Stream a JSON file to the browser as an attachment download.
	 *
	 * @param array  $envelope The data to encode.
	 * @param string $filename Suggested filename for the download.
	 */
	protected function send_json_download( $envelope, $filename ) {
		// Drop any output buffers WordPress may have started so the JSON
		// body is the only thing the browser receives.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		echo wp_json_encode( $envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
	}

	/**
	 * Build a human-readable filename for the export download.
	 *
	 * @param int[] $post_ids Post IDs being exported (empty = all).
	 * @param array $envelope The envelope being downloaded.
	 * @return string
	 */
	protected function build_filename( $post_ids, $envelope ) {
		$count = count( $envelope );
		$date  = gmdate( 'Y-m-d' );
		if ( 1 === $count ) {
			$keys     = array_keys( $envelope );
			$parts    = explode( '/', (string) $keys[0] );
			$slug     = end( $parts );
			$basename = 'coywolf-custom-block-' . sanitize_title( $slug ) . '-' . $date;
		} elseif ( empty( $post_ids ) ) {
			$basename = 'coywolf-custom-blocks-all-' . $date;
		} else {
			$basename = 'coywolf-custom-blocks-' . $count . '-' . $date;
		}
		return $basename . '.json';
	}

	/**
	 * Count published Coywolf blocks for the page summary.
	 *
	 * @return int
	 */
	protected function count_blocks() {
		$counts = wp_count_posts( coywolf_custom_blocks()->get_post_type_slug() );
		$total  = 0;
		foreach ( [ 'publish', 'draft', 'private', 'pending' ] as $status ) {
			if ( isset( $counts->$status ) ) {
				$total += (int) $counts->$status;
			}
		}
		return $total;
	}

	/**
	 * Build the page URL with optional query args.
	 *
	 * @param array $args Extra query args.
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
