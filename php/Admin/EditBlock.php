<?php
/**
 * The 'Edit Block' submenu page.
 *
 * @package   Coywolf\CustomBlocks
 * @copyright Copyright(c) 2022, Genesis Custom Blocks
 * @license   http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 */

namespace Coywolf\CustomBlocks\Admin;

use WP_Error;
use WP_Post;
use Coywolf\CustomBlocks\Blocks\Block;
use Coywolf\CustomBlocks\ComponentAbstract;

/**
 * Class EditBlock
 */
class EditBlock extends ComponentAbstract {

	/**
	 * The slug of the script.
	 *
	 * @var string
	 */
	const SCRIPT_SLUG = 'coywolf-custom-blocks-edit-block-script';

	/**
	 * The slug of the style.
	 *
	 * @var string
	 */
	const STYLE_SLUG = 'coywolf-custom-blocks-edit-block-style';

	/**
	 * The REST API capability type.
	 *
	 * @var string
	 */
	const CABAPILITY = 'edit_posts';

	/**
	 * Registers the hooks.
	 */
	public function register_hooks() {
		add_filter( 'replace_editor', [ $this, 'should_replace_editor' ], 10, 2 );
		add_filter( 'use_block_editor_for_post_type', [ $this, 'should_use_block_editor_for_post_type' ], 10, 2 );
		add_action( 'admin_footer', [ $this, 'enqueue_assets' ] );
		add_filter( 'admin_footer_text', [ $this, 'conditionally_prevent_footer_text' ] );
		add_filter( 'update_footer', [ $this, 'conditionally_prevent_update_text' ], 11 );
		add_action( 'rest_api_init', [ $this, 'register_route_template_file' ] );
	}

	/**
	 * Gets whether this should replace the native editor.
	 *
	 * Forked from the Web Stories For WordPress plugin.
	 * https://github.com/google/web-stories-wp/blob/a3648a06b57c1af90cd73a75d0b8448a9e5a3d2b/includes/Story_Post_Type.php#L399
	 * Since the 'replace_editor' filter can be run multiple times, only run
	 * some admin-header.php logic after the 'current_screen' action.
	 *
	 * @param bool    $replace Whether to replace the editor.
	 * @param WP_Post $post    The current post.
	 * @return bool Whether this should replace the editor.
	 */
	public function should_replace_editor( $replace, $post ) {
		if ( coywolf_custom_blocks()->get_post_type_slug() === get_post_type( $post ) ) {
			if ( did_action( 'current_screen' ) ) {
				require_once coywolf_custom_blocks()->get_path() . 'php/Views/EditorHeader.php';
			}

			return true;
		}

		return $replace;
	}

	/**
	 * Whether to use the block editor for a given post type.
	 *
	 * @param bool   $use_block_editor Whether this should use the block editor.
	 * @param string $post_type        The post type.
	 * @return bool Whether this should use the block editor.
	 */
	public function should_use_block_editor_for_post_type( $use_block_editor, $post_type ) {
		if ( coywolf_custom_blocks()->get_post_type_slug() === $post_type ) {
			return false;
		}

		return $use_block_editor;
	}

	/**
	 * Enqueues the assets.
	 *
	 * The action 'admin_enqueue_scripts' does not run for the 'Edit Block' page,
	 * as the native editor is disabled.
	 */
	public function enqueue_assets() {
		if ( ! $this->is_gcb_editor() ) {
			return;
		}

		// Hard-required dist files. Missing files most commonly mean the user
		// installed the GitHub source archive ("custom-blocks-main.zip") rather
		// than the release zip published by the .github/workflows/release.yml
		// pipeline. The source archive does not include js/dist or css/dist
		// because they are build artefacts (.gitignore excludes them). Render
		// an inline notice and stop — far better than the unhandled
		// `require` -> fatal -> "critical error on this website" page.
		$missing = $this->find_missing_dist_assets();
		if ( ! empty( $missing ) ) {
			$this->render_missing_assets_notice( $missing );
			return;
		}

		$js_config = require $this->plugin->get_path( 'js/dist/edit-block.asset.php' );
		wp_enqueue_script(
			self::SCRIPT_SLUG,
			$this->plugin->get_url( 'js/dist/edit-block.js' ),
			$js_config['dependencies'],
			$js_config['version'],
			true
		);

		$post_id = get_the_ID();
		$block   = new Block( $post_id );
		wp_add_inline_script(
			self::SCRIPT_SLUG,
			sprintf(
				'const ccbEditor = %s;',
				wp_json_encode(
					[
						'controls'         => coywolf_custom_blocks()->block_post->get_controls(),
						'postType'         => get_post_type(),
						'postId'           => $post_id,
						'settings'         => [
							'titlePlaceholder'   => __( 'Block title', 'coywolf-custom-blocks' ),
							'richEditingEnabled' => false,
						],
						'template'         => $this->get_template_file( $block->name ),
						'initialEdits'     => null,
						'isOnboardingPost' => false,
						'categories'       => get_block_categories( get_post() ),
					]
				)
			),
			'before'
		);

		$edit_block_style_path   = 'css/dist/edit-block.css';
		$edit_block_style_config = require $this->plugin->get_path( 'css/dist/edit-block.asset.php' );
		wp_enqueue_style(
			self::STYLE_SLUG,
			$this->plugin->get_url( $edit_block_style_path ),
			[ 'wp-components' ],
			$edit_block_style_config['version']
		);

		$editor_style_config = require $this->plugin->get_path( 'css/dist/blocks.editor.asset.php' );
		wp_enqueue_style(
			'coywolf-custom-blocks-editor-css',
			$this->plugin->get_url( 'css/dist/blocks.editor.css' ),
			$editor_style_config['dependencies'],
			$editor_style_config['version']
		);
	}

	/**
	 * Returns the list of required dist asset paths that are not on disk.
	 *
	 * @return string[] Plugin-relative paths missing from disk, empty if all present.
	 */
	protected function find_missing_dist_assets() {
		$required = [
			'js/dist/edit-block.js',
			'js/dist/edit-block.asset.php',
			'css/dist/edit-block.css',
			'css/dist/edit-block.asset.php',
			'css/dist/blocks.editor.css',
			'css/dist/blocks.editor.asset.php',
		];
		$missing = [];
		foreach ( $required as $rel ) {
			if ( ! file_exists( $this->plugin->get_path( $rel ) ) ) {
				$missing[] = $rel;
			}
		}
		return $missing;
	}

	/**
	 * Render an in-page notice explaining the missing build artefacts. The
	 * Edit Block screen mounts a Gutenberg-based React app into the page
	 * body, so a friendly notice replaces the empty editor surface that
	 * would otherwise appear once the require-fatal is removed.
	 *
	 * @param string[] $missing Paths reported by find_missing_dist_assets().
	 */
	protected function render_missing_assets_notice( $missing ) {
		$release_url = 'https://github.com/coywolf-llc/custom-blocks/releases/latest';
		?>
		<div class="notice notice-error" style="margin-top: 20px;">
			<h2 style="margin-top: 0.5em;">
				<?php esc_html_e( 'Coywolf Custom Blocks is missing its built JavaScript and CSS.', 'coywolf-custom-blocks' ); ?>
			</h2>
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: anchor opening tag for the GitHub releases page */
						__( 'This usually means the plugin was installed from a source archive (e.g. <code>custom-blocks-main.zip</code>) rather than the release zip. Download <code>coywolf-custom-blocks.zip</code> from the %1$slatest GitHub release%2$s and re-upload it.', 'coywolf-custom-blocks' ),
						'<a href="' . esc_url( $release_url ) . '" target="_blank" rel="noopener noreferrer">',
						'</a>'
					),
					[
						'a'    => [ 'href' => [], 'target' => [], 'rel' => [] ],
						'code' => [],
					]
				);
				?>
			</p>
			<p>
				<?php esc_html_e( 'Missing files:', 'coywolf-custom-blocks' ); ?>
			</p>
			<ul style="list-style: disc; padding-left: 1.5em;">
				<?php foreach ( $missing as $path ) : ?>
					<li><code><?php echo esc_html( $path ); ?></code></li>
				<?php endforeach; ?>
			</ul>
			<p class="description">
				<?php esc_html_e( 'If you are a developer working from a checkout, run npm ci && npm run build at the plugin root.', 'coywolf-custom-blocks' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Gets whether the current screen is the GCB editor.
	 *
	 * @return bool Whether this is the GCB editor.
	 */
	public function is_gcb_editor() {
		$screen = get_current_screen();

		return (
			is_object( $screen ) &&
			coywolf_custom_blocks()->get_post_type_slug() === $screen->post_type &&
			'post' === $screen->base
		);
	}

	/**
	 * Conditionally prevents footer text, as the GCB editor is React-driven.
	 *
	 * @param string $text The text to display in the footer.
	 * @return string The filtered footer text.
	 */
	public function conditionally_prevent_footer_text( $text ) {
		if ( $this->is_gcb_editor() ) {
			return '';
		}

		return $text;
	}

	/**
	 * Conditionally prevents WP update text, as the GCB editor is React-driven.
	 *
	 * @param string $text The update text.
	 * @return string The filtered update text.
	 */
	public function conditionally_prevent_update_text( $text ) {
		if ( $this->is_gcb_editor() ) {
			return '';
		}

		return $text;
	}

	/**
	 * Registers a route to get the template file.
	 */
	public function register_route_template_file() {
		register_rest_route(
			coywolf_custom_blocks()->get_slug(),
			'template-file',
			[
				'callback'            => [ $this, 'get_template_file_response' ],
				'permission_callback' => function () {
					return current_user_can( self::CABAPILITY );
				},
				'args'                => [
					'blockName' => [
						'description' => __( 'Block name', 'coywolf-custom-blocks' ),
						'type'        => 'string',
					],
				],
			]
		);
	}

	/**
	 * Gets the response for the `template-file` endpoint.
	 *
	 * @param array $data Data sent in the GET request.
	 * @return WP_REST_Response|WP_Error Response to the request.
	 */
	public function get_template_file_response( $data ) {
		if ( empty( $data['blockName'] ) ) {
			return new WP_Error(
				'no_block_name',
				__( 'Please pass a block name', 'coywolf-custom-blocks' )
			);
		}

		return rest_ensure_response( $this->get_template_file( $data['blockName'] ) );
	}


	/**
	 * Gets the template path and whether it exists.
	 *
	 * @param string $block_name The block name (slug).
	 * @return array Template file data.
	 */
	public function get_template_file( $block_name ) {
		$locations     = coywolf_custom_blocks()->get_template_locations( $block_name, 'block' );
		$template_path = coywolf_custom_blocks()->locate_template( $locations );

		$template_exists = ! empty( $template_path );
		if ( ! $template_exists ) {
			$template_path = get_stylesheet_directory() . "/blocks/block-{$block_name}.php";
		}

		$stylesheet_locations = coywolf_custom_blocks()->get_stylesheet_locations( $block_name );
		$stylesheet_path      = coywolf_custom_blocks()->locate_template( $stylesheet_locations );
		$stylesheet_url       = coywolf_custom_blocks()->get_url_from_path( $stylesheet_path );

		return [
			'templateExists' => $template_exists,
			'templatePath'   => str_replace(
				WP_CONTENT_DIR,
				basename( WP_CONTENT_DIR ),
				$template_path
			),
			'cssUrl'         => $stylesheet_url,
		];
	}
}
