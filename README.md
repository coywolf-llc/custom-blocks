# Coywolf Custom Blocks

Tags: gutenberg, blocks, block editor, fields, template
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.0
Stable tag: 1.7.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl

Custom blocks for WordPress made easy.

## About this fork

**Coywolf Custom Blocks is a fork of [Genesis Custom Blocks](https://github.com/studiopress/genesis-custom-blocks)** by WP Engine / StudioPress, originally created by Luke Carbis, Ryan Kienstra, Stino11, Rheinard Korf, and the StudioPress / WP Engine team. All credit for the original plugin and its design belongs to them, and this fork would not exist without their open-source work under GPL-2.0.

This fork exists to keep the plugin alive and self-contained for Coywolf sites. The changes vs. upstream are:

- **Inline Custom HTML editing.** Block markup can be authored directly in the block editor screen — no need to SFTP a `blocks/block-{slug}.php` file into the active theme. The HTML is saved on the block's post record and renders verbatim, including `<script>`, `<iframe>`, and inline event handlers. Theme template files still work as a fallback when the in-admin field is empty.
- **Native JSON export/import.** Custom Blocks → **Export & Import** lets you download every block (or only the ones selected via row/bulk actions on the list table) as a single JSON file, and upload that file on another Coywolf Custom Blocks site to recreate the blocks. Imports match by slug — existing blocks are replaced, new ones are inserted. Files produced by upstream Genesis Custom Blocks' Pro exporter are also accepted; the importer normalizes the namespace.
- **Import from upstream.** Custom Blocks → **Import from Genesis** copies every `genesis_custom_block` post on this site (whether or not upstream is currently active) into Coywolf Custom Blocks, with per-row checkboxes and a "select all" toggle. If a matching theme template file exists, its `block_field()` / `block_value()` calls are best-effort translated into the in-admin `{{field-slug}}` syntax.
- **Opt-in uninstall cleanup.** A "Delete plugin data on uninstall" checkbox in Settings controls whether `uninstall.php` drops all block definitions and plugin options when the plugin is deleted. Unchecked by default, so deactivate-and-delete is non-destructive.
- **No external server calls.** The WP Engine plugin update server integration (`scripts/PluginUpdater.php` and `scripts/create-info.js`, which fetched from `wpe-plugin-updates.wpengine.com`) has been removed. Updates are now pulled directly from this repository's GitHub Releases.
- **No analytics, no telemetry.** The dormant Google Analytics client (`GAClient.js`) and its `window.GcbAnalytics` global, the `genesis-custom-blocks-analytics#async` script handle, and every "enable analytics" code path have been deleted. The only outbound request this plugin ever makes is `GET api.github.com/repos/coywolf-llc/custom-blocks/releases/latest` for update checks.
- **No "Genesis Pro" upgrade nag.** The submenu that pointed at WP Engine's signup page is gone.
- **Documentation links** point at this repository instead of `developer.wpengine.com`.
- **Renamespaced to coexist with upstream.** Every identifier that would collide with the original Genesis Custom Blocks plugin has been renamed: PHP namespace (`Coywolf\CustomBlocks`), post type (`coywolf_custom_block`), block prefix (`coywolf-custom-blocks/`), text domain, option keys, hook names, REST routes, script/style handles, and JS globals (`ccbEditor`, `ccbBlocks`, `coywolfCustomBlocks`). Both plugins can be active simultaneously without fatal errors or shared state. Blocks created in one are not visible in the other — they're distinct post types — and post content containing `<!-- wp:genesis-custom-blocks/foo -->` will not render through this fork (rename the block names to `coywolf-custom-blocks/foo` if you're migrating off upstream). Global template helpers (`block_field()`, `block_value()`, etc.) are loaded conditionally — whichever plugin loads first defines them, and theme code calling them resolves against that plugin's render data.
- **Branding** (plugin header, author, package metadata) is set to Coywolf.

## Description

Coywolf Custom Blocks provides WordPress developers with the tools they need to take control of the block-first reality of modern WordPress.

The WordPress block editor (AKA Gutenberg) opens up a whole new world for the way we build pages, posts, and websites with WordPress. This plugin makes it easy to harness that and build custom blocks the way you want them to be built. Whether you want to implement a custom design, deliver unique functionality, or even remove your dependence on other plugins, Coywolf Custom Blocks equips you with the tools you need to hit "Publish" sooner.

**Take control of design** — Implement beautiful, custom designs with fine-tuned front-end templating control.

**Build unique functionality** — Build blocks that function and behave exactly as you need.

**Extend & Integrate** — Easily extend your custom blocks to integrate with third-party apps and plugins.

## Features

### A Familiar Experience
Work within the WordPress admin with an interface you already know.

### Block Fields
Add from a growing list of available fields to your custom blocks.

### Simple Templating
Let the plugin do the heavy lifting so you can use the built-in editor, or familiar WordPress development practices, to build block templates.

### Developer Friendly Functions
As an alternative to the built-in editor, there are simple functions, ready to render and work with the data stored through your custom block fields.

## Currently available block fields
* Inner Blocks Field
* File Field
* Text Field
* Image Field
* URL Field
* Toggle Field
* Textarea Field
* Select Field
* Range Field
* Radio Field
* Number Field
* Multi-select Field
* Email Field
* Color Field
* Checkbox Field

## Installation
1. Download the latest release zip from the [GitHub Releases page](https://github.com/coywolf-llc/custom-blocks/releases).
2. In WordPress, go to Plugins → Add New → Upload Plugin and upload the zip.
3. Activate the plugin.

Once installed, the plugin will check GitHub for new releases and surface updates on Dashboard → Updates just like a plugin installed from wordpress.org.

## Frequently Asked Questions

### Do I need to work with the Genesis Framework or any of the other Genesis plugins/themes to use this plugin?
No. You can use this plugin completely independently. All you need is the block editor on your WordPress site.

### Do I need to change to the new built-in Template Editor in /wp-admin?
No. You can keep using your PHP block templates like `block-example.php`.

### Does this plugin call home to WP Engine, StudioPress, or anyone else?
No. The only outbound request it makes is to `api.github.com` to check for new releases of this repository. Update package downloads are restricted to GitHub-owned hosts (`github.com`, `codeload.github.com`, `objects.githubusercontent.com`, etc.).

## Links
* [GitHub repository](https://github.com/coywolf-llc/custom-blocks)
* [Upstream project (Genesis Custom Blocks)](https://github.com/studiopress/genesis-custom-blocks)

## Changelog
See the [GitHub Releases page](https://github.com/coywolf-llc/custom-blocks/releases).

For the pre-fork history through v1.7.3, see the [upstream releases](https://github.com/studiopress/genesis-custom-blocks/releases).

## License

GPL-2.0-or-later. Original work copyright © 2020-2024 WP Engine / StudioPress. Fork changes copyright © 2026 Coywolf LLC.
