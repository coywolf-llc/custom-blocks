<?php
/**
 * Coywolf Custom Blocks Settings page.
 *
 * @package   Genesis\CustomBlocks
 * @copyright Copyright(c) 2022, Genesis Custom Blocks; (c) 2026, Coywolf LLC
 * @license   http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 */

use Genesis\CustomBlocks\Admin\Settings;

?>
<div class="wrap genesis-custom-blocks-settings">
	<h1><?php esc_html_e( 'Coywolf Custom Blocks Settings', 'genesis-custom-blocks' ); ?></h1>
	<form method="post" action="options.php">
		<?php
		settings_fields( Settings::SETTINGS_GROUP );
		do_settings_sections( Settings::SETTINGS_GROUP );
		?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">
					<label for="<?php echo esc_attr( Settings::DELETE_ON_UNINSTALL_OPTION_NAME ); ?>">
						<?php esc_html_e( 'Delete plugin data on uninstall', 'genesis-custom-blocks' ); ?>
					</label>
				</th>
				<td>
					<input
						type="checkbox"
						value="<?php echo esc_attr( Settings::DELETE_ON_UNINSTALL_VALUE ); ?>"
						<?php checked( get_option( Settings::DELETE_ON_UNINSTALL_OPTION_NAME ), Settings::DELETE_ON_UNINSTALL_VALUE ); ?>
						name="<?php echo esc_attr( Settings::DELETE_ON_UNINSTALL_OPTION_NAME ); ?>"
						id="<?php echo esc_attr( Settings::DELETE_ON_UNINSTALL_OPTION_NAME ); ?>"
					/>
					<p class="description">
						<?php esc_html_e( 'When enabled, deleting this plugin from the Plugins screen will permanently remove every custom block definition (the genesis_custom_block post type) and all plugin options from the database. Leave unchecked if you might reinstall later and want your blocks back — deactivating without this checked is non-destructive.', 'genesis-custom-blocks' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php

		/**
		 * Runs when rendering the page /wp-admin > Custom Blocks > Settings.
		 */
		do_action( 'genesis_custom_blocks_render_settings_page' );

		submit_button();
		?>
	</form>
</div>
