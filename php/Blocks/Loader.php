<?php
/**
 * Loader initiates the loading of new blocks.
 *
 * @package Coywolf\CustomBlocks
 */

namespace Coywolf\CustomBlocks\Blocks;

use WP_REST_Server;
use WP_Query;
use Coywolf\CustomBlocks\ComponentAbstract;
use Coywolf\CustomBlocks\Admin\Settings;

/**
 * Class Loader
 */
class Loader extends ComponentAbstract {


	/**
	 * Asset paths and urls for blocks.
	 *
	 * @var array
	 */
	protected $assets = [];

	/**
	 * An associative array of block config data for the blocks that will be registered.
	 *
	 * The key of each item in the array is the block name.
	 *
	 * @var array
	 */
	protected $blocks = [];

	/**
	 * Map of block slug => post_author id, populated alongside `$this->blocks`.
	 *
	 * Used by editor_assets() to derive the current user's authored-block
	 * slugs without firing a second `get_posts()` on every editor pageview.
	 *
	 * @var array<string,int>
	 */
	protected $block_authors = [];

	/**
	 * Object-cache group used for the assembled blocks payload.
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'coywolf-custom-blocks';

	/**
	 * A data store for sharing data to helper functions.
	 *
	 * @var array
	 */
	protected $data = [];

	/**
	 * The template editor.
	 *
	 * @var TemplateEditor
	 */
	protected $template_editor;

	/**
	 * Load the Loader.
	 *
	 * @return $this
	 */
	public function init() {
		$this->template_editor = new TemplateEditor();
		$this->assets          = [
			'path' => [
				'entry'        => $this->plugin->get_path( 'js/dist/block-editor.js' ),
				'editor_style' => $this->plugin->get_path( 'css/dist/blocks.editor.css' ),
			],
			'url'  => [
				'entry'        => $this->plugin->get_url( 'js/dist/block-editor.js' ),
				'editor_style' => $this->plugin->get_url( 'css/dist/blocks.editor.css' ),
			],
		];

		return $this;
	}

	/**
	 * Register all the hooks.
	 */
	public function register_hooks() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'editor_assets' ] );
		add_action( 'init', [ $this, 'retrieve_blocks' ] );
		add_action( 'init', [ $this, 'dynamic_block_loader' ] );
		add_filter( 'rest_endpoints', [ $this, 'add_rest_method' ] );

		// TODO: once 'Requires at least' is bumped to 5.8, delete these conditionals and just use 'block_categories_all'.
		if ( is_wp_version_compatible( '5.8' ) ) {
			add_filter( 'block_categories_all', [ $this, 'register_categories' ] );
		} else {
			add_filter( 'block_categories', [ $this, 'register_categories' ] );
		}
	}

	/**
	 * Retrieve data from the Loader's data store.
	 *
	 * @param string $key The data key to retrieve.
	 * @return mixed
	 */
	public function get_data( $key ) {
		$data = false;

		if ( isset( $this->data[ $key ] ) ) {
			$data = $this->data[ $key ];
		}

		/**
		 * Filters the data that gets returned.
		 *
		 * @param mixed  $data The data from the Loader's data store.
		 * @param string $key  The key for the data being retrieved.
		 */
		$data = apply_filters( 'coywolf_custom_blocks_data', $data, $key );

		/**
		 * Filters the data that gets returned, specifically for a single key.
		 *
		 * @param mixed $data The data from the Loader's data store.
		 */
		return apply_filters( "coywolf_custom_blocks_data_{$key}", $data );
	}

	/**
	 * Launch the blocks inside Gutenberg.
	 */
	public function editor_assets() {
		$js_handle  = 'coywolf-custom-blocks-blocks';
		$css_handle = 'coywolf-custom-blocks-editor-css';

		// Same guard as EditBlock::enqueue_assets — when the plugin is
		// installed from the GitHub source archive (custom-blocks-main.zip)
		// rather than the release zip, the .asset.php manifests aren't
		// on disk and an unguarded `require` would E_ERROR right here,
		// dropping a "critical error on this website" page on every
		// post editor view. Bail out cleanly and let WordPress render
		// the editor without our dynamic blocks instead. The user gets
		// a one-time admin notice via maybe_render_post_editor_notice()
		// telling them what to do.
		$js_asset  = $this->plugin->get_path( 'js/dist/block-editor.asset.php' );
		$css_asset = $this->plugin->get_path( 'css/dist/blocks.editor.asset.php' );
		if ( ! file_exists( $js_asset ) || ! file_exists( $css_asset ) ) {
			// Schedule a notice on the next admin_notices fire so the
			// user sees a friendly explanation instead of staring at a
			// half-loaded editor with no Coywolf blocks in the inserter.
			$missing = [];
			if ( ! file_exists( $js_asset ) ) {
				$missing[] = 'js/dist/block-editor.asset.php';
			}
			if ( ! file_exists( $this->plugin->get_path( 'js/dist/block-editor.js' ) ) ) {
				$missing[] = 'js/dist/block-editor.js';
			}
			if ( ! file_exists( $css_asset ) ) {
				$missing[] = 'css/dist/blocks.editor.asset.php';
			}
			if ( ! file_exists( $this->plugin->get_path( 'css/dist/blocks.editor.css' ) ) ) {
				$missing[] = 'css/dist/blocks.editor.css';
			}
			add_action(
				'admin_notices',
				static function () use ( $missing ) {
					echo '<div class="notice notice-error"><p><strong>';
					echo esc_html__( 'Coywolf Custom Blocks: build artefacts are missing — its blocks will not appear in the inserter.', 'coywolf-custom-blocks' );
					echo '</strong></p><p>';
					printf(
						/* translators: %s: anchor opening tag for the GitHub releases page */
						esc_html__( 'This usually means the plugin was installed from a source archive (e.g. custom-blocks-main.zip) rather than the release zip. Re-upload %scoywolf-custom-blocks.zip%s from the latest GitHub release.', 'coywolf-custom-blocks' ),
						'<a href="https://github.com/coywolf-llc/custom-blocks/releases/latest" target="_blank" rel="noopener noreferrer"><code>',
						'</code></a>'
					);
					echo '</p><ul style="list-style: disc; padding-left: 1.5em;">';
					foreach ( $missing as $path ) {
						echo '<li><code>' . esc_html( $path ) . '</code></li>';
					}
					echo '</ul></div>';
				}
			);
			return;
		}

		$js_config  = require $js_asset;
		$css_config = require $css_asset;

		wp_enqueue_script(
			$js_handle,
			$this->assets['url']['entry'],
			$js_config['dependencies'],
			$js_config['version'],
			true
		);

		// Add dynamic Gutenberg blocks.
		wp_add_inline_script(
			$js_handle,
			'const ccbBlocks = ' . wp_json_encode( $this->blocks ),
			'before'
		);

		// Derive the current user's block slugs from the in-memory author map
		// that retrieve_blocks() built — no second get_posts() needed.
		$current_user_id    = (int) get_current_user_id();
		$author_block_slugs = array_keys(
			array_filter(
				$this->block_authors,
				static function ( $author_id ) use ( $current_user_id ) {
					return $author_id === $current_user_id;
				}
			)
		);
		wp_localize_script(
			$js_handle,
			'coywolfCustomBlocks',
			[
				'authorBlocks' => $author_block_slugs,
				'postType'     => get_post_type(), // To conditionally exclude blocks from certain post types.
			]
		);

		// Enqueue optional editor only styles.
		wp_enqueue_style(
			$css_handle,
			$this->assets['url']['editor_style'],
			$css_config['dependencies'],
			$css_config['version']
		);

	}

	/**
	 * Loads dynamic blocks via render_callback for each block.
	 */
	public function dynamic_block_loader() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		foreach ( $this->blocks as $block_name => $block_config ) {
			$block = new Block();
			$block->from_array( $block_config );
			$this->register_block( $block_name, $block );
		}
	}

	/**
	 * Registers a block.
	 *
	 * @param string $block_name The name of the block, including namespace.
	 * @param Block  $block      The block to register.
	 */
	protected function register_block( $block_name, $block ) {
		$attributes = $this->get_block_attributes( $block );

		// sanitize_title() allows underscores, but register_block_type doesn't.
		$block_name = str_replace( '_', '-', $block_name );

		// register_block_type doesn't allow slugs starting with a number.
		if ( isset( $block_name[0] ) && is_numeric( $block_name[0] ) ) {
			$block_name = 'block-' . $block_name;
		}

		register_block_type(
			$block_name,
			[
				'api_version'     => 3,
				'attributes'      => $attributes,
				'editor_style'    => 'coywolf-custom-blocks-editor-css',
				// @see https://github.com/WordPress/gutenberg/issues/4671
				'render_callback' => function ( $attributes, $content ) use ( $block ) {
					return $this->render_block_template( $block, $attributes, $content );
				},
			]
		);
	}

	/**
	 * Register custom block categories.
	 *
	 * @param array $categories Array of block categories.
	 *
	 * @return array
	 */
	public function register_categories( $categories ) {
		foreach ( $this->blocks as $block_config ) {
			if ( ! isset( $block_config['category'] ) ) {
				continue;
			}

			/*
			 * This is a backwards compatibility fix.
			 *
			 * Block categories used to be saved as strings, but were always included in
			 * the default list of categories, so it's safe to skip them.
			 */
			if ( ! is_array( $block_config['category'] ) || empty( $block_config['category'] ) ) {
				continue;
			}

			if ( ! in_array( $block_config['category'], $categories, true ) ) {
				$categories[] = $block_config['category'];
			}
		}

		return $categories;
	}

	/**
	 * Gets block attributes.
	 *
	 * @param Block $block The block to get attributes from.
	 *
	 * @return array
	 */
	protected function get_block_attributes( $block ) {
		$attributes = [];

		// Default Editor attributes (applied to all blocks).
		$attributes['className'] = [ 'type' => 'string' ];

		foreach ( $block->fields as $field_name => $field ) {
			$attributes = $this->get_attributes_from_field( $attributes, $field_name, $field );
		}

		/**
		 * Filters a given block's attributes.
		 *
		 * These are later passed to register_block_type() in $args['attributes'].
		 * Removing attributes here can cause 'Error loading block...' in the editor.
		 *
		 * @param array[] $attributes The attributes for a block.
		 * @param array   $block      Block data, including its name at $block['name'].
		 */
		return apply_filters( 'coywolf_custom_blocks_get_block_attributes', $attributes, $block );
	}

	/**
	 * Sets the field values in the attributes, enabling them to appear in the block.
	 *
	 * @param array  $attributes The attributes in which to store the field value.
	 * @param string $field_name The name of the field, like 'home-hero'.
	 * @param Field  $field      The Field to set the attributes from.
	 * @return array $attributes The attributes, with the new field value set.
	 */
	protected function get_attributes_from_field( $attributes, $field_name, $field ) {
		$attributes[ $field_name ] = [
			'type' => $field->type,
		];

		if ( ! empty( $field->settings['default'] ) ) {
			$attributes[ $field_name ]['default'] = $field->settings['default'];
		}

		if ( 'array' === $field->type ) {
			/**
			 * This is a workaround to allow empty array values. We unset the default value before registering the
			 * block so that the default isn't used to auto-correct empty arrays. This allows the default to be
			 * used only when creating the form.
			 */
			unset( $attributes[ $field_name ]['default'] );
			$items_type                         = 'repeater' === $field->control ? 'object' : 'string';
			$attributes[ $field_name ]['items'] = [ 'type' => $items_type ];
		}

		return $attributes;
	}

	/**
	 * Renders the block provided a template is provided.
	 *
	 * @param Block  $block The block to render.
	 * @param array  $attributes Attributes to render.
	 * @param string $content The block InnerContent, if any.
	 * @return mixed
	 */
	protected function render_block_template( $block, $attributes, $content ) {
		// Default to front-end behaviour: only the Custom HTML branch is
		// consulted, Preview HTML is skipped.
		$type = 'block';

		// `ccb_render_mode` (set by the block builder's preview tabs) is
		// the explicit override. WP REST hard-codes `context=edit` for
		// the block-renderer endpoint's enum, so we can't reuse `context`
		// to differentiate the two tabs — they both have to send
		// `context=edit` to pass validation. The custom arg sidesteps
		// that and tells us which preview the request is coming from:
		//   'editor' — render Preview HTML if set, otherwise Custom HTML.
		//              `showPreview` is ignored on this path so the
		//              Editor Preview tab always renders something when
		//              the block has any markup at all.
		//   'view'   — render Custom HTML only. Mirrors live front-end.
		// Falls back to the legacy `context=edit` behaviour for any
		// caller that doesn't set the custom arg (which is still how
		// the actual post editor flags itself).
		$mode = filter_input( INPUT_GET, 'ccb_render_mode' );
		if ( 'editor' === $mode ) {
			$type = [ 'preview', 'block' ];
		} elseif ( 'view' === $mode ) {
			$type = 'block';
		} elseif ( 'edit' === filter_input( INPUT_GET, 'context' ) ) {
			$type = [ 'preview', 'block' ];
		}

		if ( ! is_admin() ) {
			// The block has been added, but its values weren't saved (not even the defaults).
			// This is unique to frontend output, as the editor fetches its attributes from the form fields themselves.
			$missing_schema_attributes = array_diff_key( $block->fields, $attributes );
			foreach ( $missing_schema_attributes as $attribute_name => $schema ) {
				if ( isset( $schema->settings['default'] ) ) {
					$attributes[ $attribute_name ] = $schema->settings['default'];
				}
			}
		}

		/**
		 * The block attributes to be sent to the template.
		 *
		 * @param array   $attributes The block attributes.
		 * @param Field[] $fields     The block fields.
		 */
		$this->data['attributes'] = apply_filters( 'coywolf_custom_blocks_template_attributes', $attributes, $block->fields );
		$this->data['config']     = $block;
		$this->data['content']    = $content;

		if ( ! is_admin() && ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) && ! wp_doing_ajax() ) {

			/**
			 * Runs in the 'render_callback' of the block, and only on the front-end, not in the editor.
			 *
			 * The block's name (slug) is in $block->name.
			 * If a block depends on a JavaScript file,
			 * this action is a good place to call wp_enqueue_script().
			 * In that case, pass true as the 5th argument ($in_footer) to wp_enqueue_script().
			 *
			 * @param Block $block The block that is rendered.
			 * @param array $attributes The block attributes.
			 */
			do_action( 'coywolf_custom_blocks_render_template', $block, $attributes );

			/**
			 * Runs in a block's 'render_callback', and only on the front-end.
			 *
			 * Same as the action above, but with a dynamic action name that has the block name.
			 *
			 * @param Block $block The block that is rendered.
			 * @param array $attributes The block attributes.
			 */
			do_action( "coywolf_custom_blocks_render_template_{$block->name}", $block, $attributes );
		}

		ob_start();
		$this->block_template( $block->name, $type );

		// Render legacy `templateCss` if present — the Template Editor UI
		// that wrote it was removed in 1.0.23 but stored data is still
		// honoured so existing blocks keep their styles. Authors who
		// want block CSS now embed a `<style>` block inside the Custom
		// HTML panel itself.
		$this->template_editor->render_css(
			isset( $this->blocks[ "coywolf-custom-blocks/{$block->name}" ]['templateCss'] )
				? $this->blocks[ "coywolf-custom-blocks/{$block->name}" ]['templateCss']
				: '',
			$block->name
		);

		return ob_get_clean();
	}

	/**
	 * Loads a block template to render the block.
	 *
	 * @param string       $name The name of the block (slug as defined in UI).
	 * @param string|array $type The type of template to load.
	 */
	protected function block_template( $name, $type = 'block' ) {
		$types        = (array) $type;
		$block_config = isset( $this->blocks[ "coywolf-custom-blocks/{$name}" ] )
			? $this->blocks[ "coywolf-custom-blocks/{$name}" ]
			: [];

		// Walk each requested type in priority order. For the editor
		// preview path we get ['preview', 'block'] from
		// render_block_template, so the preview source is consulted
		// first and falls through to the regular block source only when
		// no preview is set. For frontend rendering we get just
		// ['block'] and the preview source is skipped entirely.
		//
		// As of 1.0.31, the theme-file fallback (`blocks/block-{slug}.php`,
		// `blocks/preview-{slug}.php`) is gone. The Custom HTML and
		// Preview HTML panels on the Builder page are the only render
		// sources — keeping block authoring fully inside wp-admin.
		//
		// The `showPreview` gate is bypassed when the request is coming
		// from the block builder's Editor Preview tab (signalled by
		// `?ccb_render_mode=editor`): the tab is a developer-facing
		// preview, so we want to see whatever's in the Preview HTML
		// field regardless of whether the author has flipped the
		// "Show preview in post editor" toggle yet.
		$is_editor_preview = 'editor' === filter_input( INPUT_GET, 'ccb_render_mode' );
		foreach ( $types as $current_type ) {
			$db_markup = '';
			if ( 'preview' === $current_type
				&& ( $is_editor_preview || ! empty( $block_config['showPreview'] ) )
				&& ! empty( $block_config['previewMarkup'] )
			) {
				$db_markup = $block_config['previewMarkup'];
			} elseif ( 'block' === $current_type && ! empty( $block_config['templateMarkup'] ) ) {
				$db_markup = $block_config['templateMarkup'];
			}
			if ( '' !== $db_markup ) {
				$this->template_editor->render_markup( $db_markup );
				return;
			}
		}

		// Nothing to render — the block has no Custom HTML and (when
		// previewed in the editor) no Preview HTML either. Surface a
		// hint to logged-in editors so they aren't left wondering why
		// the block looks blank; visitors see nothing.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		if ( is_admin() || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			printf(
				'<div class="notice notice-warning">%s</div>',
				wp_kses_post(
					/* translators: %s: block name */
					sprintf( __( 'No Custom HTML is set for the %s block. Edit the block in Custom Blocks → Builder to add some.', 'coywolf-custom-blocks' ), '<code>' . esc_html( $name ) . '</code>' )
				)
			);
		}
	}

	/**
	 * Load all the published blocks and blocks/block.json files.
	 *
	 * The DB-driven portion (the `WP_Query` over `coywolf_custom_block`
	 * posts plus the per-post JSON decode) is cached in the object cache
	 * keyed by `get_lastpostmodified()`, so subsequent requests skip the
	 * query entirely until any block post is saved/trashed. Without this
	 * the plugin was running an extra `WP_Query` on every WordPress
	 * request — admin, frontend, REST, AJAX — even when no Coywolf block
	 * could possibly render on that page.
	 *
	 * The `coywolf_custom_blocks_add_blocks` action and the
	 * `coywolf_custom_blocks_available_blocks` filter still fire on every
	 * call so plugins that register blocks dynamically keep working.
	 */
	public function retrieve_blocks() {
		$is_edit_context = 'edit' === filter_input( INPUT_GET, 'context' );
		$post_type       = coywolf_custom_blocks()->get_post_type_slug();
		$last_modified   = get_lastpostmodified( 'GMT', $post_type );
		$cache_key       = 'blocks_v1_' . md5( ( $last_modified ?: '0' ) . '|' . ( $is_edit_context ? 'edit' : 'pub' ) );

		$cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( is_array( $cached ) && isset( $cached['blocks'], $cached['authors'] ) ) {
			$this->blocks        = $cached['blocks'];
			$this->block_authors = $cached['authors'];
		} else {
			// Reverse to preserve order of preference when using array_merge.
			$blocks_files = array_reverse( coywolf_custom_blocks()->locate_template( 'blocks/blocks.json', '', false ) );
			foreach ( $blocks_files as $blocks_file ) {
				// This is expected to be on the local filesystem, so file_get_contents() is ok to use here.
				$json       = file_get_contents( $blocks_file ); // @codingStandardsIgnoreLine
				$block_data = json_decode( $json, true );

				// Merge if no json_decode error occurred.
				if ( json_last_error() == JSON_ERROR_NONE ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
					$this->blocks = array_merge( $this->blocks, $block_data );
				}
			}

			$block_posts = new WP_Query(
				[
					'post_type'              => $post_type,
					'post_status'            => $is_edit_context ? 'any' : 'publish',
					'posts_per_page'         => 100,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				]
			);

			if ( $block_posts->post_count > 0 ) {
				foreach ( $block_posts->posts as $post ) {
					$block_data = json_decode( $post->post_content, true );

					if ( json_last_error() != JSON_ERROR_NONE ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
						continue;
					}

					$this->blocks = array_merge( $this->blocks, $block_data );

					// Remember which user authored each block so editor_assets()
					// can derive `authorBlocks` without a second get_posts().
					foreach ( array_keys( $block_data ) as $block_name ) {
						$slug = $this->slug_from_block_name( (string) $block_name );
						if ( '' !== $slug ) {
							$this->block_authors[ $slug ] = (int) $post->post_author;
						}
					}
				}
			}

			wp_cache_set(
				$cache_key,
				[
					'blocks'  => $this->blocks,
					'authors' => $this->block_authors,
				],
				self::CACHE_GROUP,
				HOUR_IN_SECONDS
			);
		}

		/**
		 * Use this action to add new blocks and fields with the Coywolf\CustomBlocks\add_block and Coywolf\CustomBlocks\add_field helper functions.
		 */
		do_action( 'coywolf_custom_blocks_add_blocks' );

		/**
		 * Filter the available blocks.
		 *
		 * This is used internally by the Coywolf\CustomBlocks\add_block and Coywolf\CustomBlocks\add_field helper functions,
		 * but it can also be used to hide certain blocks if desired.
		 *
		 * @param array $blocks An associative array of blocks.
		 */
		$this->blocks = apply_filters( 'coywolf_custom_blocks_available_blocks', $this->blocks );
	}

	/**
	 * Strip the `coywolf-custom-blocks/` namespace prefix off a block name
	 * so it lines up with `post_name` slugs.
	 *
	 * @param string $block_name e.g. `coywolf-custom-blocks/hero` or `hero`.
	 * @return string
	 */
	protected function slug_from_block_name( $block_name ) {
		$prefix = 'coywolf-custom-blocks/';
		if ( 0 === strpos( $block_name, $prefix ) ) {
			return substr( $block_name, strlen( $prefix ) );
		}
		return $block_name;
	}

	/**
	 * Add a new block.
	 *
	 * This method should be called during the coywolf_custom_blocks_add_blocks action, to ensure
	 * that the block isn't added too late.
	 *
	 * @param array $block_config The config of the block to add.
	 */
	public function add_block( $block_config ) {
		if ( ! isset( $block_config['name'] ) ) {
			return;
		}

		$this->blocks[ "coywolf-custom-blocks/{$block_config['name']}" ] = $block_config;
	}

	/**
	 * Add a new field to an existing block.
	 *
	 * This method should be called during the coywolf_custom_blocks_add_blocks action, to ensure
	 * that the block isn't added too late.
	 *
	 * @param string $block_name   The name of the block that the field is added to.
	 * @param array  $field_config The config of the field to add.
	 */
	public function add_field( $block_name, $field_config ) {
		if ( ! isset( $this->blocks[ "coywolf-custom-blocks/{$block_name}" ] ) ) {
			return;
		}
		if ( ! isset( $field_config['name'] ) ) {
			return;
		}

		$this->blocks[ "coywolf-custom-blocks/{$block_name}" ]['fields'][ $field_config['name'] ] = $field_config;
	}

	/**
	 * Adds 'POST' to the allowed REST methods for GCB blocks.
	 *
	 * The <ServerSideRender> uses the httpMethod of 'POST' to handle a larger attributes object.
	 * That is already added in WP 5.6+, so no need to add it there.
	 *
	 * @todo: Delete when this plugin's 'Requires at least' is bumped to 5.6.
	 * @see https://core.trac.wordpress.org/ticket/49680#comment:15
	 *
	 * @param array $endpoints The REST API endpoints, an associative array of $route => $handlers.
	 * @return array The filtered endpoints, with the GCB endpoints allowing POST requests.
	 */
	public function add_rest_method( $endpoints ) {
		if ( is_wp_version_compatible( '5.5' ) ) {
			return $endpoints;
		}

		foreach ( $endpoints as $route => $handler ) {
			if ( 0 === strpos( $route, '/wp/v2/block-renderer/(?P<name>coywolf-custom-blocks/' ) && isset( $endpoints[ $route ][0] ) ) {
				$endpoints[ $route ][0]['methods'] = [ WP_REST_Server::READABLE, WP_REST_Server::CREATABLE ];
			}
		}

		return $endpoints;
	}
}
