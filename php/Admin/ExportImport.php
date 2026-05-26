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
 * Imports reuse the JSON envelope shape — keying by block namespace —
 * so the legacy Tools → Import flow (php/Admin/Import.php) and this
 * page accept the same files. Existing blocks of the same slug are
 * replaced; never silently duplicated.
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
		add_filter( 'post_row_actions', [ $this, 'add_row_export_action' ], 10, 2 );
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
				<?php esc_html_e( 'Upload a JSON file produced by Export on this or another Coywolf Custom Blocks site. Blocks whose slug already exists on this site will be replaced with the version from the file.', 'coywolf-custom-blocks' ); ?>
			</p>

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

		$actions['ccb-export'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Export', 'coywolf-custom-blocks' )
		);

		return $actions;
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

		if ( ! isset( $_FILES['ccb_import_file']['tmp_name'] ) || '' === $_FILES['ccb_import_file']['tmp_name'] ) {
			wp_safe_redirect(
				$this->page_url(
					[
						'result'  => 'error',
						'message' => __( 'No file was uploaded.', 'coywolf-custom-blocks' ),
					]
				)
			);
			exit;
		}

		if ( ! empty( $_FILES['ccb_import_file']['error'] ) ) {
			wp_safe_redirect(
				$this->page_url(
					[
						'result'  => 'error',
						'message' => __( 'Upload failed; the file was rejected by the server.', 'coywolf-custom-blocks' ),
					]
				)
			);
			exit;
		}

		// The uploaded temp file is on the local filesystem; safe to read directly.
		$tmp_path = isset( $_FILES['ccb_import_file']['tmp_name'] ) ? sanitize_text_field( $_FILES['ccb_import_file']['tmp_name'] ) : '';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw = '' !== $tmp_path && is_uploaded_file( $tmp_path ) ? @file_get_contents( $tmp_path ) : false;
		if ( false === $raw || '' === $raw ) {
			wp_safe_redirect(
				$this->page_url(
					[
						'result'  => 'error',
						'message' => __( 'Could not read the uploaded file.', 'coywolf-custom-blocks' ),
					]
				)
			);
			exit;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded ) ) {
			wp_safe_redirect(
				$this->page_url(
					[
						'result'  => 'error',
						'message' => __( 'The file is not valid Coywolf Custom Blocks JSON.', 'coywolf-custom-blocks' ),
					]
				)
			);
			exit;
		}

		// Tolerate two shapes:
		//   1. Direct envelope:  { "coywolf-custom-blocks/foo": {...}, ... }
		//   2. Wrapped envelope: { "blocks": { ... } } from a future format
		// Always normalize to (1).
		if ( isset( $decoded['blocks'] ) && is_array( $decoded['blocks'] ) ) {
			$envelope = $decoded['blocks'];
		} else {
			$envelope = $decoded;
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

			$slug = (string) $block_config['name'];

			// Normalize the namespace key — accept anything ending in `/{slug}`
			// (including upstream's `genesis-custom-blocks/foo`) and rewrite it.
			$canonical_key = 'coywolf-custom-blocks/' . $slug;

			$result = $this->insert_or_replace_block( $canonical_key, $block_config );
			if ( is_wp_error( $result ) ) {
				$errors[] = sprintf( '%s — %s', $slug, $result->get_error_message() );
			} else {
				$imported[] = ! empty( $block_config['title'] ) ? (string) $block_config['title'] : $slug;
			}
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
