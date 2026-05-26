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
- **Opt-in uninstall cleanup.** A "Delete plugin data on uninstall" checkbox in Settings controls whether `uninstall.php` drops all block definitions and plugin options when the plugin is deleted. Unchecked by default, so deactivate-and-delete is non-destructive.
- **No external server calls.** The WP Engine plugin update server integration (`scripts/PluginUpdater.php` and `scripts/create-info.js`, which fetched from `wpe-plugin-updates.wpengine.com`) has been removed. Updates are now pulled directly from this repository's GitHub Releases.
- **No "Genesis Pro" upgrade nag.** The submenu that pointed at WP Engine's signup page is gone.
- **Documentation links** point at this repository instead of `developer.wpengine.com`.
- **Branding** (plugin header, author, package metadata) is set to Coywolf.

Internal PHP namespaces, the `genesis-custom-blocks` text domain, and the `genesis_custom_block` post type are unchanged so existing block content keeps working.

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
