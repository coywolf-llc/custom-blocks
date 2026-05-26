<?php
/**
 * Coywolf Custom Blocks
 *
 * @package   Coywolf\CustomBlocks
 * @copyright Copyright(c) 2022, Genesis Custom Blocks; (c) 2026, Coywolf LLC
 * @license http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 *
 * Plugin Name: Coywolf Custom Blocks
 * Plugin URI: https://github.com/coywolf-llc/custom-blocks
 * Description: The easy way to build custom blocks for Gutenberg. A fork of Genesis Custom Blocks by WP Engine / StudioPress, with telemetry and external update servers removed.
 * Version: 1.0.19
 * Author: Coywolf
 * Author URI: https://coywolf.com
 * License: GPL2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: coywolf-custom-blocks
 * Domain Path: languages
 */

use Coywolf\CustomBlocks\Plugin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class autoloading.
 *
 * Prefer Composer's PSR-4 autoloader when `vendor/` is present (development
 * checkouts that ran `composer install`, plus release zips built with the
 * vendor directory included). When the directory is missing — e.g. a fresh
 * clone, a zipball downloaded straight from GitHub's auto-archives, or any
 * other "just drop the repo into wp-content/plugins" install path — fall
 * back to a tiny PSR-4 autoloader scoped to this plugin's namespace.
 *
 * Without this fallback, `require_once 'vendor/autoload.php'` is a fatal
 * error on activation when Composer hasn't been run, which is exactly the
 * symptom users hit when installing from the GitHub UI.
 */
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( $class ) {
			$prefix = 'Coywolf\\CustomBlocks\\';
			if ( 0 !== strpos( $class, $prefix ) ) {
				return;
			}
			$relative = substr( $class, strlen( $prefix ) );
			$path     = __DIR__ . '/php/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	);
}

/**
 * Get the plugin object.
 *
 * @return Plugin
 */
function coywolf_custom_blocks() {
	static $instance;

	if ( null === $instance ) {
		$instance = new Plugin();
	}

	return $instance;
}

/**
 * Set up the plugin instance.
 */
coywolf_custom_blocks()
	->set_basename( plugin_basename( __FILE__ ) )
	->set_directory( plugin_dir_path( __FILE__ ) )
	->set_file( __FILE__ )
	->set_slug( 'coywolf-custom-blocks' )
	->set_url( plugin_dir_url( __FILE__ ) )
	->set_version( __FILE__ )
	->init();

add_action( 'plugins_loaded', [ coywolf_custom_blocks(), 'plugin_loaded' ] );
add_action( 'plugins_loaded', [ coywolf_custom_blocks(), 'require_deprecated' ], 11 );

/**
 * GitHub-releases-based self-updater. Replaces the original WP Engine update
 * server integration so this plugin only ever talks to github.com.
 *
 * Initialised inline (not on a hook) so the
 * `pre_set_site_transient_update_plugins` filter is registered before
 * WordPress's first call to `wp_update_plugins()` in the request — without
 * this, a forced re-check (e.g. via Coywolf Reset Plugin Update) that runs
 * before our `init` callback would set the transient with no entry for
 * this plugin and the Dashboard → Updates screen would skip it.
 */
require_once __DIR__ . '/includes/class-github-updater.php';
( new Coywolf_Custom_Blocks_GitHub_Updater(
	__FILE__,
	get_file_data( __FILE__, [ 'Version' => 'Version' ] )['Version']
) )->init();
