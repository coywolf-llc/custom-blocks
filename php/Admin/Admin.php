<?php
/**
 * WP Admin resources.
 *
 * @package   Coywolf\CustomBlocks
 * @copyright Copyright(c) 2022, Genesis Custom Blocks
 * @license   http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 */

namespace Coywolf\CustomBlocks\Admin;

use Coywolf\CustomBlocks\ComponentAbstract;

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
	 * Intentionally empty: the only thing this component used to do was
	 * enqueue `css/admin.css` on every wp-admin page, but its sole rule
	 * styled a `coywolf-custom-blocks-pro` admin-menu item that no
	 * longer exists (the Genesis Pro upsell was removed during the
	 * fork). The dead stylesheet and its site-wide enqueue are gone.
	 */
	public function register_hooks() {}
}
