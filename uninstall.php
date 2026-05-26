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
 * @package Genesis\CustomBlocks
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
		'post_type'      => 'genesis_custom_block',
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
 */
$options = array(
	'coywolf_custom_blocks_delete_on_uninstall',
	'genesis_custom_blocks_analytics_opt_in', // Legacy upstream option, in case it lingers from a pre-rebrand install.
	'genesis_custom_blocks_notices',
);

foreach ( $options as $option_name ) {
	delete_option( $option_name );
	if ( is_multisite() ) {
		delete_site_option( $option_name );
	}
}

/**
 * GitHub updater transients (the only plugin-specific transients we set).
 */
delete_site_transient( 'coywolf_ccb_gh_release' );
delete_site_transient( 'coywolf_ccb_gh_release_neg' );
