<?php
/**
 * TemplateEditor.
 *
 * @package   Coywolf\CustomBlocks
 * @copyright Copyright(c) 2022, Genesis Custom Blocks
 * @license http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 */

namespace Coywolf\CustomBlocks\Blocks;

/**
 * Class TemplateEditor
 */
class TemplateEditor {

	/**
	 * The block names that have had their CSS rendered.
	 *
	 * @var string[]
	 */
	public $blocks_with_rendered_css = [];

	/**
	 * Renders markup that was entered in the Custom HTML / template editor.
	 *
	 * @param string $markup The markup to render.
	 */
	public function render_markup( $markup ) {
		$rendered = preg_replace_callback(
			'#{{(\S+?)}}#',
			static function ( $matches ) {
				ob_start();
				block_field( $matches[1] );
				return ob_get_clean();
			},
			$markup
		);

		// Escape characters before { should be stripped, like \{\{example\}\}.
		// Like if they have a tutorial on Mustache and need the template to render {{example}}.
		$rendered = preg_replace( '#\\\{\\\{(\S+?)\\\}\\\}#', '{{\1}}', $rendered );

		// Echo raw — the Custom HTML field is authored by admins for their own
		// site and must round-trip arbitrary markup including <script>, <iframe>,
		// inline event handlers, etc. wp_kses_post() would strip all of those.
		// Editing coywolf_custom_block posts requires manage_options, so this is
		// gated on the same trust level as editing theme files via Appearance →
		// Theme File Editor.
		echo $rendered; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Renders CSS that was entered in the template editor.
	 *
	 * @param string $css        The CSS to render, if any.
	 * @param string $block_name The block name, without the coywolf-custom-blocks/ namespace.
	 */
	public function render_css( $css, $block_name ) {
		if ( empty( $css ) || in_array( $block_name, $this->blocks_with_rendered_css, true ) ) {
			return;
		}

		$this->blocks_with_rendered_css[] = $block_name;

		?>
		<style><?php echo wp_strip_all_tags( $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></style>
		<?php
	}
}
