=== Coywolf Custom Blocks ===
Contributors: coywolf
Tags: gutenberg, blocks, block editor, fields, template
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.0
Stable tag: 1.0.26
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build custom Gutenberg blocks in the WordPress admin — no SFTP, no theme files. Privacy-respecting fork of Genesis Custom Blocks.

== Description ==

Coywolf Custom Blocks lets you define your own Gutenberg blocks (fields +
markup) entirely in the WordPress admin. Each block lives as a
`coywolf_custom_block` post: configure its fields in the editor, write its
front-end markup in the in-admin Custom HTML field (no
`blocks/block-{slug}.php` theme file required), and it renders site-wide.

* Inline Custom HTML editor — a textarea below the fields grid on the block
  editor screen. The HTML is saved on the block's post record and renders
  verbatim. `<script>`, `<iframe>`, and inline event handlers pass through.
  Theme template files continue to work as a fallback.
* No external server calls, no analytics. The WP Engine plugin update
  server integration, the dormant Google Analytics client, and the Genesis
  Pro upgrade nag have all been removed. The only outbound request this
  plugin ever makes is a check for new releases on the GitHub API.
* Self-updates from GitHub Releases. Plugin updates appear on Dashboard ->
  Updates and install with the standard one-click flow. Downloads are
  restricted to a GitHub-owned host allowlist.
* Renamespaced to coexist with upstream. Every identifier that could
  collide with Genesis Custom Blocks has been renamed, so both plugins can
  be active simultaneously.
* Import from upstream Genesis Custom Blocks. The "Import from Genesis"
  page lists every genesis_custom_block on the site with per-row checkboxes
  and a "select all" toggle. Imported blocks get a best-effort translation
  of their theme template files into the in-admin {{field-slug}} syntax.
  An optional checkbox rewrites every wp:genesis-custom-blocks/foo block
  comment across all post content to wp:coywolf-custom-blocks/foo so
  existing pages keep rendering after upstream is removed.
* Native JSON export and import. The "Export & Import" page downloads one
  or more blocks as a single JSON file. Upload that file on another
  Coywolf Custom Blocks site to recreate the blocks; existing slugs are
  replaced rather than duplicated.
* Opt-in uninstall cleanup. A checkbox in Settings controls whether
  uninstall.php drops every block definition and plugin option when the
  plugin is deleted. Unchecked by default — deactivate-and-delete is
  non-destructive.

== Installation ==

1. Go to the latest GitHub release:
   https://github.com/coywolf-llc/custom-blocks/releases/latest
2. Under Assets, download coywolf-custom-blocks.zip -- NOT the
   auto-generated "Source code (zip)" link. The release zip contains
   the built JS/CSS bundles; the source zip does not, and uploading it
   will break the block editor.
3. In WordPress, go to Plugins -> Add New -> Upload Plugin, upload the
   zip, and click Activate.
4. Go to Custom Blocks -> Add New to define your first block.

Once installed, updates surface on Dashboard -> Updates just like a
plugin installed from wordpress.org.

== Frequently Asked Questions ==

= Does this call home to WP Engine, StudioPress, Google Analytics, or anyone else? =

No. The only outbound request the plugin makes is to api.github.com to
check for new releases of this repository. Update package downloads are
restricted to GitHub-owned hosts.

= Can I run this alongside the original Genesis Custom Blocks plugin? =

Yes. Every identifier that could collide has been renamed, so both
plugins activate cleanly and keep their own data. Blocks you create in
one are not visible in the other — they are distinct post types. Use
"Custom Blocks -> Import from Genesis" to copy blocks across and
optionally rewrite existing post content site-wide.

= Do I need to work with the Genesis Framework or any other Genesis plugin/theme? =

No. You can use this plugin completely independently. All you need is the
WordPress block editor.

= Does the Custom HTML field allow scripts and inline JavaScript? =

Yes. The field is intended for use by site administrators (editing
coywolf_custom_block posts requires manage_options, the same capability
as Appearance -> Theme File Editor). Markup is rendered verbatim with no
kses filtering.

= What is the upstream relationship? =

This is a fork of Genesis Custom Blocks
(https://github.com/studiopress/genesis-custom-blocks) by WP Engine /
StudioPress, originally created by Luke Carbis, Ryan Kienstra, Stino11,
Rheinard Korf, and the StudioPress / WP Engine team. All credit for the
original plugin and its design belongs to them; this fork exists to keep
the codebase alive and self-contained for Coywolf sites. Released under
the same GPL-2.0-or-later license.

== Changelog ==

= 1.0.26 =
* Strip the broken logo image from the Documentation page (#35).

= 1.0.26 =
* Strip the leading logo `<img>` from the Documentation page — its `.wordpress-org/` asset isn't shipped in the plugin install, so it rendered as a broken image.

= 1.0.25 =
* Security + performance audit pass (#34).

= 1.0.24 =
* Render Preview HTML in the Editor Preview tab (#33).

= 1.0.23 =
* Remove the standalone Template Editor page (#32).

= 1.0.22 =
* Fix block icons rendering as solid black squares in Post editor (#31).

= 1.0.21 =
* Restore Preview support: in-admin Preview HTML panel + Genesis import (#30).

= 1.0.20 =
* Drop "Imported from Genesis Custom Blocks" review notice from imported Custom HTML (#29).

= 1.0.19 =
* Execute PHP in Custom HTML; show Export row action between Edit and Trash (#28).

= 1.0.18 =
* Default icon library → Lucide; default icon → LuSquareCode (#27).

= 1.0.17 =
* Guard Loader::editor_assets() require()s the same way EditBlock does (#26).

= 1.0.16 =
* Commit webpack's *.asset.php companion files (#25).

= 1.0.15 =
* Fix Import from Genesis: count, name list, theme templates, menu visibility (#24).

= 1.0.14 =
* Localize Documentation page; add per-block progress bar to Genesis importer (#23).

= 1.0.13 =
* Restore a default block icon that matches the wp-admin nav glyph (#22).

= 1.0.12 =
* Add Posts/Pages usage columns to the Custom Blocks list table (#21).

= 1.0.11 =
* Surface all react-icons libraries in the picker via code-split chunks (#20).

= 1.0.10 =
* Replace hand-rolled icon set with the full react-icons/bi BoxIcons library (#19).

= 1.0.9 =
* Switch Custom HTML insert affordance from button row to a dropdown menu (#18).

= 1.0.8 =
* Add 'Insert field' toolbar above the Custom HTML textarea (#17).

= 1.0.7 =
* Flush update_plugins transient + refresh installed version after self-update (#16).

= 1.0.6 =
* Ship built JS/CSS bundles in main so source installs work (#15).

= 1.0.5 =
* Fix "Refusing to download a plugin update from an untrusted host." when uploading a plugin zip via Plugins -> Add New -> Upload Plugin. The upgrader_pre_download guard in the GitHub self-updater was meant to reject remote package URLs not on the GitHub host allowlist, but was also firing for local uploads (where $package is a filesystem path with no scheme). The guard now short-circuits for non-URL packages.

= 1.0.4 =
* Fix critical error ("Failed opening required js/dist/edit-block.asset.php") when the plugin is installed from the GitHub source archive instead of the release zip. EditBlock now detects missing build artefacts and renders an in-page notice instead of fataling.
* Replace the Genesis-branded "G" admin menu icon with the WordPress core dashicons-block-default glyph.
* Delete orphan Genesis Pro background images and the upgrade/onboarding stylesheets that referenced them.
* README installation steps now spell out that users must download coywolf-custom-blocks.zip from the release Assets, not the "Source code (zip)" link.

= 1.0.3 =
* Add the canonical release workflow: every PR merge to main now auto-bumps the patch version, prepends a changelog entry, builds the JS/CSS bundles, packages a slim plugin zip, and publishes a GitHub Release tagged vX.Y.Z. The Coywolf Reset Plugin Update button can now surface updates to this plugin.

= 1.0.2 =
* Remove the Genesis-branded "Hi, and welcome!" notice that showed on the Plugins screen after activation, along with the Onboarding component that displayed it and auto-inserted an "Example Block" post.
* Remove the legacy Tools -> Import wizard; superseded by Custom Blocks -> Export & Import.
* Rewrite remaining admin-visible "Genesis Custom Blocks" strings to "Coywolf Custom Blocks".
* uninstall.php now also cleans up the legacy Onboarding option and welcome transient.

= 1.0.1 =
* Fix fatal error on activation when installed from a fresh GitHub checkout / zipball (no vendor/ directory). The main file now falls back to a PSR-4 autoloader scoped to the plugin's namespace when Composer hasn't been run.

= 1.0.0 =
* Initial release as Coywolf Custom Blocks, forked from Genesis Custom Blocks 1.7.3.
* Removed the WP Engine plugin update server, the dormant Google Analytics client, and the Genesis Pro upgrade nag.
* Renamespaced every collision-prone identifier so the fork coexists with upstream.
* Added a Yoast-style Custom HTML textarea on the block editor screen so block markup can be authored entirely in the admin.
* Added "Import from Genesis" with per-row checkboxes, select-all, theme-template translation, and an optional site-wide post-content rewriter.
* Added native JSON export and import (per-row link, bulk action, export-all button, and a matching import form).
* Added an opt-in "Delete plugin data on uninstall" setting and matching uninstall.php.
* Added a GitHub-Releases self-updater scoped to a GitHub host allowlist.
