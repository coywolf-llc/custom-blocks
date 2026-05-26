<?php
/**
 * Coywolf Custom Blocks uninstall handler.
 *
 * Runs when the user clicks "Delete" on the Plugins screen (NOT on simple
 * deactivate). WordPress can't display a UI prompt at delete time, so the
 * destructive cleanup is gated on an opt-in setting the user toggles in
 * Custom Blocks → Settings → "Delete plugin data on uninstall" before
 * removing the plugin.
 *
 * Without that opt-in, every block definition and plugin option is left
 * untouched so a future reinstall picks up where the user left off.
 *
 * @package Coywolf\CustomBlocks
 */

// WP defines this constant when invoking uninstall.php. Refuse to run otherwise.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$delete_on_uninstall_option = 'coywolf_custom_blocks_delete_on_uninstall';

if ( '1' !== (string) get_option( $delete_on_uninstall_option ) ) {
	return;
}

/**
 * Delete every custom block post and its meta/revisions.
 *
 * `wp_delete_post( $id, true )` bypasses the trash and cleans up post meta,
 * term relationships, attachments-as-parent, and revisions.
 */
$block_post_ids = get_posts(
	array(
		'post_type'      => 'coywolf_custom_block',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	)
);

if ( is_array( $block_post_ids ) ) {
	foreach ( $block_post_ids as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}
}

/**
 * Plugin options to drop. Includes the opt-in flag itself so a reinstall
 * starts from a clean slate.
 *
 * Only `coywolf_*` keys are listed. If the upstream Genesis Custom Blocks
 * plugin happens to be co-installed, its `genesis_custom_blocks_*` options
 * belong to it and must be left alone.
 */
$options = array(
	'coywolf_custom_blocks_delete_on_uninstall',
	'coywolf_custom_blocks_notices',
	'coywolf_custom_blocks_example_post_id', // Legacy: previously written by the now-removed Onboarding component.
);

foreach ( $options as $option_name ) {
	delete_option( $option_name );
	if ( is_multisite() ) {
		delete_site_option( $option_name );
	}
}

/**
 * Plugin transients (GitHub updater cache + the legacy welcome-nag flag).
 */
delete_transient( 'coywolf_custom_blocks_show_welcome' );
delete_site_transient( 'coywolf_ccb_gh_release' );
delete_site_transient( 'coywolf_ccb_gh_release_neg' );

/**
 * Compiled-template cache directory.
 *
 * `TemplateEditor::render_markup()` writes per-content PHP files
 * to `wp-content/uploads/coywolf-custom-blocks/templates/` so the
 * embedded PHP in a block's Custom HTML can be `include`d at render
 * time. Drop the whole directory on uninstall — its only callers
 * are gone.
 */
$uploads = wp_get_upload_dir();
if ( isset( $uploads['basedir'] ) && is_string( $uploads['basedir'] ) ) {
	$dir = rtrim( $uploads['basedir'], '/' ) . '/coywolf-custom-blocks';
	if ( is_dir( $dir ) ) {
		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $it as $node ) {
			if ( $node->isDir() ) {
				@rmdir( $node->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			} else {
				@unlink( $node->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
}
