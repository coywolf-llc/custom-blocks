<?php
/**
 * Custom editor header.
 *
 * Forked from wp-admin/admin-header.php.
 *
 * @package   Coywolf\CustomBlocks
 * @copyright Copyright(c) 2026, Coywolf LLC
 * @license   http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $title;

header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ) );
get_admin_page_title();
$stripped_title = wp_strip_all_tags( $title ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View mirrors wp-admin/admin-header.php, which uses this variable name.

/* translators: Admin screen title. %s: Admin screen name. */
$admin_title = sprintf( __( '%s &#8212; WordPress', 'coywolf-custom-blocks' ), $stripped_title ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View mirrors wp-admin/admin-header.php, which uses this variable name.

/** This filter is documented in wp-admin/admin-header.php */
$admin_title = apply_filters( 'admin_title', $admin_title, $stripped_title ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Mirrors wp-admin/admin-header.php: admin_title is a WordPress core filter and the variable name matches core.
_wp_admin_html_begin();
?>
<title><?php echo esc_html( $admin_title ); ?></title>
