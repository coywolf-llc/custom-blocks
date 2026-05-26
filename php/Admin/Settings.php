<?php
/**
 * Genesis Custom Blocks Settings.
 *
 * @package   Coywolf\CustomBlocks
 * @copyright Copyright(c) 2022, Genesis Custom Blocks
 * @license   http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 */

namespace Coywolf\CustomBlocks\Admin;

use Coywolf\CustomBlocks\ComponentAbstract;

/**
 * Class Settings
 */
class Settings extends ComponentAbstract {

	/**
	 * Option name for the notices.
	 *
	 * @var string
	 */
	const NOTICES_OPTION_NAME = 'coywolf_custom_blocks_notices';

	/**
	 * Settings group to opt into analytics.
	 *
	 * @var string
	 */
	const SETTINGS_GROUP = 'coywolf-custom-blocks-settings-page';

	/**
	 * Option name controlling whether uninstall.php should drop plugin data.
	 *
	 * @var string
	 */
	const DELETE_ON_UNINSTALL_OPTION_NAME = 'coywolf_custom_blocks_delete_on_uninstall';

	/**
	 * The value stored when the user has opted into uninstall cleanup.
	 *
	 * @var string
	 */
	const DELETE_ON_UNINSTALL_VALUE = '1';

	/**
	 * Page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'coywolf-custom-blocks-settings';

	/**
	 * Register any hooks that this component needs.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', [ $this, 'add_submenu_pages' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Add submenu pages to the Genesis Custom Blocks menu.
	 */
	public function add_submenu_pages() {
		add_submenu_page(
			'edit.php?post_type=' . coywolf_custom_blocks()->get_post_type_slug(),
			__( 'Coywolf Custom Blocks Settings', 'coywolf-custom-blocks' ),
			__( 'Settings', 'coywolf-custom-blocks' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Renders the Settings page.
	 */
	public function render_page() {
		include coywolf_custom_blocks()->get_path() . 'php/Views/Settings.php';
	}

	/**
	 * Register Genesis Custom Blocks settings.
	 */
	public function register_settings() {
		register_setting( self::SETTINGS_GROUP, self::DELETE_ON_UNINSTALL_OPTION_NAME );
	}
}
