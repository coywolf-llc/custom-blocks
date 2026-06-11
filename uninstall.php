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

$coywolf_delete_on_uninstall_option = 'coywolf_custom_blocks_delete_on_uninstall';

if ( '1' !== (string) get_option( $coywolf_delete_on_uninstall_option ) ) {
	return;
}

/**
 * Delete every custom block post and its meta/revisions.
 *
 * `wp_delete_post( $id, true )` bypasses the trash and cleans up post meta,
 * term relationships, attachments-as-parent, and revisions.
 */
$coywolf_block_post_ids = get_posts(
	array(
		'post_type'      => 'coywolf_custom_block',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	)
);

if ( is_array( $coywolf_block_post_ids ) ) {
	foreach ( $coywolf_block_post_ids as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}
}

/**
 * Plugin options to drop. Includes the opt-in flag itself so a reinstall
 * starts from a clean slate.
 *
 * Only `coywolf_*` keys are listed. If the upstream plugin this fork came
 * from happens to be co-installed, its own (differently prefixed) options
 * belong to it and must be left alone.
 */
$coywolf_options = array(
	'coywolf_custom_blocks_delete_on_uninstall',
	'coywolf_custom_blocks_notices',
	'coywolf_custom_blocks_example_post_id', // Legacy: previously written by the now-removed Onboarding component.
	'coywolf_custom_blocks_php_executor_missing', // Flag behind the "install the PHP Templates companion" notice.
);

foreach ( $coywolf_options as $coywolf_option_name ) {
	delete_option( $coywolf_option_name );
	if ( is_multisite() ) {
		delete_site_option( $coywolf_option_name );
	}
}

/**
 * Legacy welcome-nag transient.
 */
delete_transient( 'coywolf_custom_blocks_show_welcome' );
/* wporg-strip:start — GitHub self-updater cache (removed from the WordPress.org build) */
/*
 * GitHub-updater release cache. The site-transient names match the updater's
 * TRANSIENT_KEY (`coywolf_custom_blocks_gh_release` + `_neg`). They previously
 * used a `coywolf_ccb_gh_release` prefix that the updater never wrote, so the
 * real release-cache transients were orphaned on uninstall.
 */
delete_site_transient( 'coywolf_custom_blocks_gh_release' );
delete_site_transient( 'coywolf_custom_blocks_gh_release_neg' );
/* wporg-strip:end */

/**
 * Compiled-template cache directory.
 *
 * Through 1.0.69, `TemplateEditor::render_markup()` wrote per-content
 * PHP files to `wp-content/uploads/coywolf-custom-blocks/templates/`;
 * since 1.0.70 the same directory is written by the separate
 * "Coywolf Custom Blocks — PHP Templates" companion plugin. Drop it
 * here too — the files are regenerable caches either way, and if the
 * companion remains installed it simply recreates the directory on the
 * next PHP-template render.
 */
$coywolf_uploads = wp_get_upload_dir();
if ( isset( $coywolf_uploads['basedir'] ) && is_string( $coywolf_uploads['basedir'] ) ) {
	$coywolf_dir = rtrim( $coywolf_uploads['basedir'], '/' ) . '/coywolf-custom-blocks';
	if ( is_dir( $coywolf_dir ) ) {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		global $wp_filesystem;
		if ( WP_Filesystem() ) {
			$wp_filesystem->rmdir( $coywolf_dir, true );
		}
	}
}
