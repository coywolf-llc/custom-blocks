<img src=".wordpress-org/icon-256x256.png" alt="Coywolf Custom Blocks logo" width="128" />

# Coywolf Custom Blocks

Build custom Gutenberg blocks in the WordPress admin — no SFTP, no theme files. A privacy-respecting fork of [Genesis Custom Blocks](https://github.com/studiopress/genesis-custom-blocks) with WP Engine telemetry, the WPE update server, and Genesis Pro upsells removed; ships an inline Custom HTML editor, native JSON export/import, and a one-shot importer for migrating off upstream.

- **Version:** 1.0.26
- **Requires WordPress:** 6.0 or later
- **Tested up to:** 7.0
- **Requires PHP:** 7.0 or later
- **License:** [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)

## Description

Coywolf Custom Blocks lets you define your own Gutenberg blocks (fields + markup) entirely in the WordPress admin. Each block lives as a `coywolf_custom_block` post: configure its fields in the editor, write its front-end markup in the in-admin Custom HTML field (no `blocks/block-{slug}.php` theme file required), and it renders site-wide.

- **Inline Custom HTML editor.** Block markup is authored as a textarea below the fields grid on the block editor screen. The HTML is saved on the block's post record and renders verbatim — `<script>`, `<iframe>`, inline event handlers all pass through. Theme template files at `{theme}/blocks/block-{slug}.php` continue to work as a fallback when the field is empty.
- **No external server calls, no analytics.** The WP Engine plugin update server integration, the dormant Google Analytics client (`GAClient.js` / `window.GcbAnalytics`), and the Genesis Pro upgrade nag have all been removed. The only outbound request this plugin ever makes is `GET api.github.com/repos/coywolf-llc/custom-blocks/releases/latest` for update checks.
- **Self-updates from GitHub Releases.** Plugin updates appear on Dashboard → Updates and install with the standard one-click flow. Downloads are restricted to a GitHub-owned host allowlist.
- **Renamespaced to coexist with upstream.** Every identifier that would collide with the original Genesis Custom Blocks plugin has been renamed (PHP namespace `Coywolf\CustomBlocks`, post type `coywolf_custom_block`, block prefix `coywolf-custom-blocks/`, text domain, options, hooks, REST routes, script/style handles, JS globals `ccbEditor` / `ccbBlocks` / `coywolfCustomBlocks`). Both plugins can be active simultaneously.
- **Import from upstream Genesis Custom Blocks.** Custom Blocks → Import from Genesis lists every `genesis_custom_block` post on the site with per-row checkboxes and a "select all" toggle. Imported blocks get a best-effort translation of their theme template files (the `block_field()` / `block_value()` calls are converted to the in-admin `{{field-slug}}` syntax). An optional checkbox additionally rewrites every `<!-- wp:genesis-custom-blocks/foo -->` comment across all post content to `<!-- wp:coywolf-custom-blocks/foo -->` so existing pages keep rendering after upstream is removed.
- **Native JSON export and import.** Custom Blocks → Export & Import downloads one or more blocks as a single JSON file (per-row "Export" link, "Export selected" bulk action, or "Export all" button). Upload that file on another Coywolf Custom Blocks site to recreate the blocks; existing slugs are replaced rather than duplicated.
- **Opt-in uninstall cleanup.** A "Delete plugin data on uninstall" checkbox in Settings controls whether `uninstall.php` drops every block definition and plugin option when the plugin is deleted. Unchecked by default — deactivate-and-delete is non-destructive.

## Installation

1. Go to the [latest GitHub release](https://github.com/coywolf-llc/custom-blocks/releases/latest).
2. Under **Assets**, download **`coywolf-custom-blocks.zip`** — _not_ the auto-generated "Source code (zip)" link. The release zip contains the built JS/CSS bundles; the source zip does not, and uploading it will break the editor.
3. In WordPress, go to **Plugins → Add New → Upload Plugin**, upload the zip, and click Activate.
4. Go to **Custom Blocks → Add New** to define your first block.

Once installed, updates surface on **Dashboard → Updates** just like a plugin installed from wordpress.org.

## Frequently Asked Questions

### Does this call home to WP Engine, StudioPress, Google Analytics, or anyone else?

No. The only outbound request the plugin makes is to `api.github.com` to check for new releases of this repository. Update package downloads are restricted to GitHub-owned hosts (`github.com`, `codeload.github.com`, `objects.githubusercontent.com`, etc.). The dormant Google Analytics wrapper that shipped in upstream has been deleted entirely.

### Can I run this alongside the original Genesis Custom Blocks plugin?

Yes. Every identifier that could collide has been renamed, so both plugins activate cleanly and keep their own data. Blocks you create in one are not visible in the other — they are distinct post types. Use **Custom Blocks → Import from Genesis** to copy blocks across and (optionally) rewrite existing post content site-wide.

### Do I need to work with the Genesis Framework or any other Genesis plugin/theme?

No. You can use this plugin completely independently. All you need is the WordPress block editor.

### Does the Custom HTML field allow `<script>` and inline JavaScript?

Yes. The field is intended for use by site administrators (editing `coywolf_custom_block` posts requires `manage_options`, the same capability as **Appearance → Theme File Editor**). Markup is rendered verbatim with no `kses` filtering, so scripts, iframes, and inline event handlers all work.

### What is the upstream relationship?

This is a fork of [Genesis Custom Blocks](https://github.com/studiopress/genesis-custom-blocks) by WP Engine / StudioPress, originally created by Luke Carbis, Ryan Kienstra, Stino11, Rheinard Korf, and the StudioPress / WP Engine team. All credit for the original plugin and its design belongs to them; this fork exists to keep the codebase alive and self-contained for Coywolf sites. Released under the same GPL-2.0-or-later license.

## Changelog

### 1.0.27
- Revert the Lucide code-split from 1.0.25: the new fallback path broke the block inserter in the post editor ("The editor has encountered an unexpected error" on opening). Bundles go back to their 1.0.24 sizes (block-editor.js 42 KiB → 717 KiB, edit-block.js 176 KiB → 852 KiB). All PHP caching/validation wins from 1.0.25 stay in place.

### 1.0.26
- Strip the broken logo image from the Documentation page (#35).

### 1.0.26
- Strip the leading logo `<img>` from the Documentation page — its `.wordpress-org/` asset isn't shipped in the plugin install, so it rendered as a broken image.

### 1.0.25
- Security + performance audit pass (#34).

### 1.0.24
- Render Preview HTML in the Editor Preview tab (#33).

### 1.0.23
- Remove the standalone Template Editor page (#32).

### 1.0.22
- Fix block icons rendering as solid black squares in Post editor (#31).

### 1.0.21
- Restore Preview support: in-admin Preview HTML panel + Genesis import (#30).

### 1.0.20
- Drop "Imported from Genesis Custom Blocks" review notice from imported Custom HTML (#29).

### 1.0.19
- Execute PHP in Custom HTML; show Export row action between Edit and Trash (#28).

### 1.0.18
- Default icon library → Lucide; default icon → LuSquareCode (#27).

### 1.0.17
- Guard Loader::editor_assets() require()s the same way EditBlock does (#26).

### 1.0.16
- Commit webpack's *.asset.php companion files (#25).

### 1.0.15
- Fix Import from Genesis: count, name list, theme templates, menu visibility (#24).

### 1.0.14
- Localize Documentation page; add per-block progress bar to Genesis importer (#23).

### 1.0.13
- Restore a default block icon that matches the wp-admin nav glyph (#22).

### 1.0.12
- Add Posts/Pages usage columns to the Custom Blocks list table (#21).

### 1.0.11
- Surface all react-icons libraries in the picker via code-split chunks (#20).

### 1.0.10
- Replace hand-rolled icon set with the full react-icons/bi BoxIcons library (#19).

### 1.0.9
- Switch Custom HTML insert affordance from button row to a dropdown menu (#18).

### 1.0.8
- Add 'Insert field' toolbar above the Custom HTML textarea (#17).

### 1.0.7
- Flush update_plugins transient + refresh installed version after self-update (#16).

### 1.0.6
- Ship built JS/CSS bundles in main so source installs work (#15).

### 1.0.5
- Fix "Refusing to download a plugin update from an untrusted host." when uploading a plugin zip via **Plugins → Add New → Upload Plugin**. The `upgrader_pre_download` guard in the GitHub self-updater was meant to reject *remote* package URLs that aren't on the GitHub host allowlist, but it was also firing for local uploads (where `$package` is a filesystem path with no scheme) and rejecting any zip whose filename contained the plugin slug. The guard now short-circuits when the package is not an `https?://` URL, leaving manual uploads alone.

### 1.0.4
- Fix critical error ("Failed opening required `js/dist/edit-block.asset.php`") when the plugin is installed from the GitHub source archive (`custom-blocks-main.zip`) instead of the release zip. `EditBlock::enqueue_assets()` now detects missing build artefacts and renders an in-page notice with a link to the latest release, rather than fataling. Also drops the orphaned `Onboarding::OPTION_NAME` reference that PR #10's onboarding-removal left behind.
- Replace the Genesis-branded "G" admin menu icon with the WordPress core `dashicons-block-default` glyph. Delete the orphan SVG (`admin-menu-icon.svg`, `logo-reversed.svg`), the four Genesis Pro background JPGs, and the matching `admin.upgrade.css` / `admin.onboarding.css` stylesheets that referenced them — none had any remaining callers.
- README installation steps now spell out that users must download `coywolf-custom-blocks.zip` from the release Assets, not the auto-generated "Source code (zip)" link.

### 1.0.3
- Add the canonical release workflow (`.github/workflows/release.yml`): on every PR merge to `main`, auto-bump the patch version (if the PR didn't bump it), prepend a changelog entry crediting the PR, build the JS/CSS bundles, package a slim plugin zip excluding `vendor/`, `js/src/`, `css/src/`, dev configs, and tests, and publish a GitHub Release tagged `vX.Y.Z`. Without this, the GitHub-Releases self-updater has no releases to surface — which is why Coywolf Reset Plugin Update couldn't find any updates to this plugin.

### 1.0.2
- Remove the Genesis-branded onboarding/welcome notice ("👋 Hi, and welcome! Genesis Custom Blocks makes it easy…") that showed on the Plugins screen after activation, along with the entire `Onboarding` component that displayed it and auto-inserted an "Example Block" post on activation.
- Remove the legacy Tools → Import wizard ("Genesis Custom Blocks") — superseded by Custom Blocks → Export & Import (PR #6), which accepts the same JSON shape.
- Rewrite the remaining admin-visible "Genesis Custom Blocks" deprecation strings to say "Coywolf Custom Blocks".
- Add `coywolf_custom_blocks_example_post_id` (legacy Onboarding option) and the `coywolf_custom_blocks_show_welcome` transient to the uninstall cleanup list.

### 1.0.1
- Fix fatal error on activation when the plugin is installed from a fresh GitHub checkout / zipball (no `vendor/` directory). The main file no longer hard-requires `vendor/autoload.php`; it falls back to a tiny PSR-4 autoloader scoped to the `Coywolf\CustomBlocks` namespace when Composer hasn't been run.

### 1.0.0
- Initial release as Coywolf Custom Blocks. Forked from [Genesis Custom Blocks 1.7.3](https://github.com/studiopress/genesis-custom-blocks/releases/tag/1.7.3), then:
  - Deleted the WP Engine plugin update server integration (`scripts/PluginUpdater.php`, `scripts/create-info.js`) and the Google Analytics client (`GAClient.js`, `window.GcbAnalytics`, the `genesis-custom-blocks-analytics#async` script handle).
  - Removed the Genesis Pro upgrade nag and rewrote every `developer.wpengine.com` documentation link to point at this repository.
  - Renamespaced every collision-prone identifier (PHP namespace, post type, block prefix, text domain, options, hooks, REST routes, script/style handles, JS globals) so the fork coexists with upstream.
  - Added a Yoast-style **Custom HTML** textarea below the fields grid so block markup can be authored entirely in the admin, with theme template files retained as a fallback.
  - Added **Custom Blocks → Import from Genesis** with per-row checkboxes, "select all", best-effort `block_field()` → `{{field-slug}}` translation of theme template files, and an optional site-wide post-content rewriter that swaps `wp:genesis-custom-blocks/{slug}` for `wp:coywolf-custom-blocks/{slug}` in posts, pages, reusable blocks, and FSE templates.
  - Added **Custom Blocks → Export & Import** with native JSON export (per-row link, bulk action, "export all" button) and matching import that replaces existing blocks by slug.
  - Added an opt-in **Delete plugin data on uninstall** setting and a matching `uninstall.php` that only runs when the box is checked.
  - Added a GitHub-Releases self-updater (downloads restricted to a GitHub host allowlist) so the plugin updates through **Dashboard → Updates** without any external service.
