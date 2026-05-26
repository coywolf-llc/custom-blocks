<?php
/**
 * Block Post Type.
 *
 * @package   Coywolf\CustomBlocks
 * @copyright Copyright(c) 2022, Genesis Custom Blocks
 * @license http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 */

namespace Coywolf\CustomBlocks\PostTypes;

use Coywolf\CustomBlocks\ComponentAbstract;
use Coywolf\CustomBlocks\Blocks\Block;
use Coywolf\CustomBlocks\Blocks\Field;
use Coywolf\CustomBlocks\Blocks\Controls\ControlAbstract;

/**
 * Class Block
 */
class BlockPost extends ComponentAbstract {

	/**
	 * Slug used for the custom post type.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Registered controls.
	 *
	 * @var ControlAbstract[]
	 */
	public $controls = [];

	/**
	 * Block Post constructor.
	 */
	public function __construct() {
		$this->slug = coywolf_custom_blocks()->get_post_type_slug();
	}

	/**
	 * Register any hooks that this component needs.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'plugins_loaded', [ $this, 'add_caps' ] );
		add_action( 'edit_form_before_permalink', [ $this, 'template_location' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'init', [ $this, 'register_controls' ] );
		add_filter( 'coywolf_custom_blocks_field_value', [ $this, 'get_field_value' ], 10, 3 );

		// Clean up the list table.
		add_filter( 'disable_months_dropdown', '__return_true', 10, $this->slug );
		add_filter( 'page_row_actions', [ $this, 'page_row_actions' ] );
		add_filter( 'bulk_actions-edit-' . $this->slug, [ $this, 'bulk_actions' ] );
		add_filter( 'manage_edit-' . $this->slug . '_columns', [ $this, 'list_table_columns' ] );
		add_action( 'manage_' . $this->slug . '_posts_custom_column', [ $this, 'list_table_content' ], 10, 2 );
	}

	/**
	 * Register the controls.
	 *
	 * @return void
	 */
	public function register_controls() {
		$control_names = [
			'text',
			'textarea',
			'url',
			'email',
			'file',
			'number',
			'color',
			'image',
			'inner_blocks',
			'select',
			'multiselect',
			'toggle',
			'range',
			'checkbox',
			'radio',
		];

		$controls = [];
		foreach ( $control_names as $control_name ) {
			$control = $this->get_control( $control_name );
			if ( $control ) {
				$controls[ $control->name ] = $control;
			}
		}

		/**
		 * Filters the available controls.
		 *
		 * @param array $controls {
		 *     An associative array of the available controls.
		 *
		 *     @type string $control_name The name of the control, like 'user'.
		 *     @type object $control      The control object, extending ControlAbstract.
		 * }
		 */
		$this->controls = apply_filters( 'coywolf_custom_blocks_controls', $controls );
	}

	/**
	 * Gets an instantiated control.
	 *
	 * @param string $control_name The name of the control.
	 * @return object|null The instantiated control, or null.
	 */
	public function get_control( $control_name ) {
		if ( isset( $this->controls[ $control_name ] ) ) {
			return $this->controls[ $control_name ];
		}

		$separator     = '_';
		$class_name    = str_replace( $separator, '', ucwords( $control_name, $separator ) );
		$control_class = 'Coywolf\\CustomBlocks\\Blocks\\Controls\\' . $class_name;
		if ( class_exists( $control_class ) ) {
			return new $control_class();
		}
	}

	/**
	 * Gets the registered controls.
	 *
	 * @return ControlAbstract[] The block controls.
	 */
	public function get_controls() {
		if ( ! did_action( 'init' ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Must be called after the init action so the controls are registered', 'coywolf-custom-blocks' ),
				'1.0.0'
			);
		}

		return $this->controls;
	}

	/**
	 * Gets the field value to be made available or echoed on the front-end template.
	 *
	 * Gets the value based on the control type.
	 * For example, a 'user' control can return a WP_User, a string, or false.
	 * The $echo parameter is whether the value will be echoed on the front-end template,
	 * or simply made available.
	 *
	 * @param mixed  $value The field value.
	 * @param string $control The type of the control, like 'user'.
	 * @param bool   $is_echo Whether or not this value will be echoed.
	 * @return mixed $value The filtered field value.
	 */
	public function get_field_value( $value, $control, $is_echo ) {
		if ( isset( $this->controls[ $control ] ) && method_exists( $this->controls[ $control ], 'validate' ) ) {
			return call_user_func( [ $this->controls[ $control ], 'validate' ], $value, $is_echo );
		}

		return $value;
	}

	/**
	 * Register the custom post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = [
			'name'               => _x( 'Content Blocks', 'post type general name', 'coywolf-custom-blocks' ),
			'singular_name'      => _x( 'Content Block', 'post type singular name', 'coywolf-custom-blocks' ),
			'menu_name'          => _x( 'Custom Blocks', 'admin menu', 'coywolf-custom-blocks' ),
			'name_admin_bar'     => _x( 'Block', 'add new on admin bar', 'coywolf-custom-blocks' ),
			'add_new'            => _x( 'Add New', 'block', 'coywolf-custom-blocks' ),
			'add_new_item'       => __( 'Add New Block', 'coywolf-custom-blocks' ),
			'new_item'           => __( 'New Block', 'coywolf-custom-blocks' ),
			'edit_item'          => __( 'Edit Block', 'coywolf-custom-blocks' ),
			'view_item'          => __( 'View Block', 'coywolf-custom-blocks' ),
			'all_items'          => __( 'All Blocks', 'coywolf-custom-blocks' ),
			'search_items'       => __( 'Search Blocks', 'coywolf-custom-blocks' ),
			'parent_item_colon'  => __( 'Parent Blocks:', 'coywolf-custom-blocks' ),
			'not_found'          => __( 'No blocks found.', 'coywolf-custom-blocks' ),
			'not_found_in_trash' => __( 'No blocks found in Trash.', 'coywolf-custom-blocks' ),
		];

		$args = [
			'labels'        => $labels,
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'show_in_rest'  => current_user_can( 'edit_posts' ),
			'menu_position' => 100,
			// Use the WordPress core "block-default" dashicon — a generic
			// custom-blocks glyph that fits the rest of the wp-admin nav
			// without any Genesis branding.
			'menu_icon'     => 'dashicons-block-default',
			'query_var'     => true,
			'rewrite'       => [ 'slug' => $this->slug ],
			'hierarchical'  => true,
			'capabilities'  => $this->get_capabilities(),
			'map_meta_cap'  => true,
			'supports'      => [ 'editor', 'title' ],
		];

		register_post_type( $this->slug, $args );
	}

	/**
	 * Add custom capabilities
	 *
	 * @return void
	 */
	public function add_caps() {
		if ( ! is_admin() ) {
			return;
		}

		$admin = get_role( 'administrator' );
		if ( ! $admin ) {
			return;
		}

		foreach ( $this->get_capabilities() as $capability => $custom_capability ) {
			$admin->add_cap( $custom_capability );
		}
	}

	/**
	 * Gets the mapping of capabilities for the custom post type.
	 *
	 * @return array An associative array of capability key => custom capability value.
	 */
	public function get_capabilities() {
		return [
			'edit_post'          => "{$this->slug}_edit_block",
			'edit_posts'         => "{$this->slug}_edit_blocks",
			'edit_others_posts'  => "{$this->slug}_edit_others_blocks",
			'publish_posts'      => "{$this->slug}_publish_blocks",
			'read_post'          => "{$this->slug}_read_block",
			'read_private_posts' => "{$this->slug}_read_private_blocks",
			'delete_post'        => "{$this->slug}_delete_block",
		];
	}

	/**
	 * Enqueue scripts and styles used by the Block post type.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();

		if ( ! is_object( $screen ) ) {
			return;
		}

		if ( $this->slug === $screen->post_type && 'edit' === $screen->base ) {
			wp_enqueue_style(
				'coywolf-custom-blocks-edit-block',
				$this->plugin->get_url( 'css/admin.edit-block.css' ),
				[],
				$this->plugin->get_version()
			);
		}
	}

	/**
	 * Display the template location below the title.
	 */
	public function template_location() {
		$post   = get_post();
		$screen = get_current_screen();

		if ( ! is_object( $screen ) || $this->slug !== $screen->post_type ) {
			return;
		}

		if ( ! isset( $post->post_name ) || empty( $post->post_name ) ) {
			return;
		}

		$locations = coywolf_custom_blocks()->get_template_locations( $post->post_name, 'block' );
		$template  = coywolf_custom_blocks()->locate_template( $locations, '', true );

		if ( ! $template ) {
			return;
		}

		// Formatting to make the template paths easier to understand.
		$template_short  = str_replace( WP_CONTENT_DIR, basename( WP_CONTENT_DIR ), $template );
		$template_parts  = explode( '/', $template_short );
		$filename        = array_pop( $template_parts );
		$template_breaks = '/' . trailingslashit( implode( '/', $template_parts ) );

		if ( $template ) {
			?>
			<div id="edit-slug-box">
				<strong><?php esc_html_e( 'Template:', 'coywolf-custom-blocks' ); ?></strong>
				<?php echo esc_html( $template_breaks ); ?><strong><?php echo esc_html( $filename ); ?></strong>
			</div>
			<?php
		}
	}

	/**
	 * Change the columns in the Custom Blocks list table
	 *
	 * @param array $columns An array of column name ⇒ label. The name is passed to functions to identify the column.
	 *
	 * @return array
	 */
	public function list_table_columns( $columns ) {
		$new_columns = [
			'cb'       => $columns['cb'],
			'title'    => $columns['title'],
			'template' => __( 'Template', 'coywolf-custom-blocks' ),
			'category' => __( 'Category', 'coywolf-custom-blocks' ),
			'keywords' => __( 'Keywords', 'coywolf-custom-blocks' ),
			'posts'    => __( 'Posts', 'coywolf-custom-blocks' ),
			'pages'    => __( 'Pages', 'coywolf-custom-blocks' ),
		];
		return $new_columns;
	}

	/**
	 * Output custom column data into the table
	 *
	 * @param string $column  The name of the column to display.
	 * @param int    $post_id The ID of the current post.
	 *
	 * @return void
	 */
	public function list_table_content( $column, $post_id ) {
		if ( 'template' === $column ) {
			$block     = new Block( $post_id );
			$locations = coywolf_custom_blocks()->get_template_locations( $block->name, 'block' );
			$template  = coywolf_custom_blocks()->locate_template( $locations, '', true );

			if ( $template ) {
				// Formatting to make the template path easier to understand.
				$template_short  = str_replace( WP_CONTENT_DIR . '/themes/', '', $template );
				$template_parts  = explode( '/', $template_short );
				$template_breaks = implode( '/', $template_parts );
				echo wp_kses(
					'<code>' . $template_breaks . '</code>',
					[
						'code' => [],
						'wbr'  => [],
					]
				);
			} elseif ( ! empty( $block->template_markup ) ) {
				esc_html_e( 'Template Editor markup found', 'coywolf-custom-blocks' );
			} else {
				esc_html_e( 'No Template Editor markup or template found', 'coywolf-custom-blocks' );
			}
		}

		if ( 'keywords' === $column ) {
			$block = new Block( $post_id );
			echo esc_html( implode( ', ', $block->keywords ) );
		}
		if ( 'category' === $column ) {
			$block = new Block( $post_id );
			echo esc_html( $block->category['title'] );
		}

		if ( 'posts' === $column ) {
			$block = new Block( $post_id );
			echo $this->render_usage_cell( $block->name, 'post' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes
		}

		if ( 'pages' === $column ) {
			$block = new Block( $post_id );
			echo $this->render_usage_cell( $block->name, 'page' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes
		}
	}

	/**
	 * Render the count cell for the Posts / Pages columns.
	 *
	 * Counts published, draft, pending, and private entries of the given
	 * post type whose post_content contains a block comment for this
	 * Custom Block. When the count is zero, renders a plain `0`. When
	 * the count is one or more, renders the number as a link to the
	 * post-type list table filtered to that block — using WordPress's
	 * `?s=…` search parameter, which already searches post_content and
	 * is the closest thing to a built-in "filter by block" query.
	 *
	 * @param string $block_name The block's name (slug), e.g. `hero`.
	 * @param string $post_type  Target post type — `post` or `page`.
	 * @return string Escaped HTML for the table cell.
	 */
	protected function render_usage_cell( $block_name, $post_type ) {
		if ( ! is_string( $block_name ) || '' === $block_name ) {
			return '0';
		}

		$count       = $this->count_posts_using_block( $block_name, $post_type );
		$count_label = number_format_i18n( $count );

		if ( 0 === $count ) {
			return esc_html( $count_label );
		}

		// The block-comment opening marker — the search string WP uses
		// to filter `post_content` on the list table. Including the
		// leading `<!-- ` and trailing space narrows the match to the
		// block delimiter itself, so unrelated plain-text mentions of
		// the slug aren't false positives in the filtered view.
		$needle = sprintf( '<!-- wp:coywolf-custom-blocks/%s', $block_name );

		$url = add_query_arg(
			[
				'post_type' => $post_type,
				's'         => $needle,
			],
			admin_url( 'edit.php' )
		);

		return sprintf(
			'<a href="%1$s" title="%2$s">%3$s</a>',
			esc_url( $url ),
			esc_attr(
				sprintf(
					/* translators: %1$d count, %2$s post type label (e.g. "post(s)") */
					_n(
						'View the %1$d %2$s using this block',
						'View the %1$d %2$ss using this block',
						$count,
						'coywolf-custom-blocks'
					),
					$count,
					$post_type
				)
			),
			esc_html( $count_label )
		);
	}

	/**
	 * Count posts/pages of the given type whose post_content includes
	 * the opening block-comment marker for the block.
	 *
	 * Backed by `get_block_usage_tally()` so all blocks on the list
	 * table share a single full-table scan instead of issuing two
	 * unindexed LIKE queries per row (which on a 50-blocks × 100k-posts
	 * site added seconds of admin lag).
	 *
	 * @param string $block_name The block name (slug).
	 * @param string $post_type  `post` or `page`.
	 * @return int
	 */
	protected function count_posts_using_block( $block_name, $post_type ) {
		$tally = $this->get_block_usage_tally();
		if ( ! isset( $tally[ $post_type ][ $block_name ] ) ) {
			return 0;
		}
		return (int) $tally[ $post_type ][ $block_name ];
	}

	/**
	 * Per-request memo of the block-usage tally returned by load_block_usage_tally().
	 *
	 * @var array<string,array<string,int>>|null
	 */
	protected $block_usage_tally;

	/**
	 * Returns a [`post`|`page` => [block-slug => count]] tally of which
	 * post/page entries reference each Coywolf block.
	 *
	 * Reads from a transient keyed by the latest post + page modification
	 * timestamps, so a single full-table scan of `wp_posts` is amortised
	 * across the entire list-table render. Falls through to a fresh scan
	 * when no posts have been modified since the cached tally was built.
	 *
	 * @return array<string,array<string,int>>
	 */
	protected function get_block_usage_tally() {
		if ( null !== $this->block_usage_tally ) {
			return $this->block_usage_tally;
		}

		$last_post = get_lastpostmodified( 'GMT', 'post' );
		$last_page = get_lastpostmodified( 'GMT', 'page' );
		$cache_key = 'coywolf_ccb_usage_v1_' . md5( ( $last_post ?: '0' ) . '|' . ( $last_page ?: '0' ) );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			$this->block_usage_tally = $cached;
			return $this->block_usage_tally;
		}

		$this->block_usage_tally = $this->load_block_usage_tally();
		set_transient( $cache_key, $this->block_usage_tally, DAY_IN_SECONDS );

		return $this->block_usage_tally;
	}

	/**
	 * Scans wp_posts once for every `<!-- wp:coywolf-custom-blocks/{slug}`
	 * occurrence in `post` and `page` content, and tallies per slug. The
	 * regex matches the same marker that render_usage_cell()'s `?s=` link
	 * uses, so counts and the filtered view agree.
	 *
	 * Excludes auto-draft / inherit / trash so the tally reflects real
	 * editorial content (published, draft, pending, private, future) —
	 * matches the list-table default visible set.
	 *
	 * @return array<string,array<string,int>>
	 */
	protected function load_block_usage_tally() {
		global $wpdb;

		$tally = [
			'post' => [],
			'page' => [],
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- aggregate scan; result is cached in a transient.
		$rows = $wpdb->get_results(
			"SELECT post_type, post_content
			FROM {$wpdb->posts}
			WHERE post_type IN ( 'post', 'page' )
			AND post_status NOT IN ( 'auto-draft', 'inherit', 'trash' )
			AND post_content LIKE '%<!-- wp:coywolf-custom-blocks/%'"
		);

		if ( ! is_array( $rows ) ) {
			return $tally;
		}

		foreach ( $rows as $row ) {
			$type = (string) $row->post_type;
			if ( ! isset( $tally[ $type ] ) ) {
				continue;
			}
			if ( ! preg_match_all( '/<!-- wp:coywolf-custom-blocks\/([a-z0-9-]+)/i', (string) $row->post_content, $matches ) ) {
				continue;
			}
			// Count each block at most once per post so the tally matches
			// the list-table filter (which shows posts containing the
			// block, not occurrences of the block).
			foreach ( array_unique( $matches[1] ) as $slug ) {
				if ( ! isset( $tally[ $type ][ $slug ] ) ) {
					$tally[ $type ][ $slug ] = 0;
				}
				++$tally[ $type ][ $slug ];
			}
		}

		return $tally;
	}

	/**
	 * Hide the Quick Edit row action.
	 *
	 * @param array $actions An array of row action links.
	 *
	 * @return array
	 */
	public function page_row_actions( $actions = [] ) {
		$post = get_post();

		// Abort if the post type is incorrect.
		if ( $this->slug !== $post->post_type ) {
			return $actions;
		}

		// Remove the Quick Edit link.
		if ( isset( $actions['inline hide-if-no-js'] ) ) {
			unset( $actions['inline hide-if-no-js'] );
		}

		// Return the set of links without Quick Edit.
		return $actions;
	}

	/**
	 * Remove Edit from the Bulk Actions menu
	 *
	 * @param array $actions An array of bulk actions.
	 *
	 * @return array
	 */
	public function bulk_actions( $actions ) {
		unset( $actions['edit'] );

		return $actions;
	}
}
