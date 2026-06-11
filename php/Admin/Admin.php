<?php
/**
 * WP Admin resources.
 *
 * @package   Coywolf\CustomBlocks
 * @copyright Copyright(c) 2026, Coywolf LLC
 * @license   http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 */

namespace Coywolf\CustomBlocks\Admin;

use Coywolf\CustomBlocks\ComponentAbstract;
use Coywolf\CustomBlocks\Blocks\TemplateEditor;

/**
 * Class Admin
 */
class Admin extends ComponentAbstract {

	/**
	 * Plugin settings.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * Plugin documentation.
	 *
	 * @var Documentation
	 */
	public $documentation;

	/**
	 * The 'Edit Block' UI.
	 *
	 * @var EditBlock
	 */
	public $edit_block;

	/**
	 * Import-from-Genesis admin page.
	 *
	 * @var ImportFromGenesis
	 */
	public $import_from_genesis;

	/**
	 * Native Coywolf JSON export/import admin page.
	 *
	 * @var ExportImport
	 */
	public $export_import;

	/**
	 * Initialise the Admin component.
	 */
	public function init() {
		$this->settings = new Settings();
		coywolf_custom_blocks()->register_component( $this->settings );

		$this->documentation = new Documentation();
		coywolf_custom_blocks()->register_component( $this->documentation );

		$this->edit_block = new EditBlock();
		coywolf_custom_blocks()->register_component( $this->edit_block );

		// Migration tool: list and import upstream genesis_custom_block posts.
		$this->import_from_genesis = new ImportFromGenesis();
		coywolf_custom_blocks()->register_component( $this->import_from_genesis );

		// Native JSON export/import — its own submenu page + row/bulk actions
		// on the block list table.
		$this->export_import = new ExportImport();
		coywolf_custom_blocks()->register_component( $this->export_import );
	}

	/**
	 * Register any hooks that this component needs.
	 *
	 * (The historical site-wide `css/admin.css` enqueue is gone — its
	 * sole rule styled the removed upstream Pro menu item.)
	 */
	public function register_hooks() {
		add_action( 'admin_notices', [ $this, 'maybe_render_php_executor_notice' ] );
		add_action( 'admin_init', [ $this, 'maybe_dismiss_php_executor_notice' ] );
	}

	/**
	 * Warn when a block template containing PHP rendered without the
	 * "PHP Templates" companion plugin hooked.
	 *
	 * The flag option is written by TemplateEditor when its fallback
	 * fires; the notice self-heals (deletes the flag) as soon as an
	 * executor is present, so installing the companion clears it with
	 * no further interaction.
	 */
	public function maybe_render_php_executor_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! get_option( TemplateEditor::EXECUTOR_MISSING_OPTION ) ) {
			return;
		}
		if ( has_filter( 'coywolf_custom_blocks_execute_php_template' ) ) {
			// An executor is hooked now — the condition resolved itself.
			delete_option( TemplateEditor::EXECUTOR_MISSING_OPTION );
			return;
		}

		$dismiss_url = wp_nonce_url(
			add_query_arg( 'coywolf_ccb_dismiss_php_notice', '1' ),
			'coywolf_ccb_dismiss_php_notice'
		);
		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s</p><p><a class="button button-primary" href="%s" target="_blank" rel="noopener noreferrer">%s</a> <a class="button" href="%s">%s</a></p></div>',
			esc_html__( 'Coywolf Custom Blocks:', 'coywolf-custom-blocks' ),
			esc_html__( 'at least one block template contains PHP, which no longer executes without the free "PHP Templates" companion plugin. Those blocks currently render an HTML comment instead of their output.', 'coywolf-custom-blocks' ),
			esc_url( 'https://github.com/coywolf-llc/custom-blocks-php-templates/releases/latest' ),
			esc_html__( 'Get the companion plugin', 'coywolf-custom-blocks' ),
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss', 'coywolf-custom-blocks' )
		);
	}

	/**
	 * Handle the notice's Dismiss link. The flag re-arms on the next
	 * fallback render, so dismissal is per-incident, not permanent.
	 */
	public function maybe_dismiss_php_executor_notice() {
		if ( ! isset( $_GET['coywolf_ccb_dismiss_php_notice'] ) ) {
			return;
		}
		check_admin_referer( 'coywolf_ccb_dismiss_php_notice' );
		if ( current_user_can( 'manage_options' ) ) {
			delete_option( TemplateEditor::EXECUTOR_MISSING_OPTION );
		}
		wp_safe_redirect( remove_query_arg( [ 'coywolf_ccb_dismiss_php_notice', '_wpnonce' ] ) );
		exit;
	}
}
