<?php
/**
 * Color control.
 *
 * @package   Coywolf\CustomBlocks
 * @copyright Copyright(c) 2026, Coywolf LLC
 * @license http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 */

namespace Coywolf\CustomBlocks\Blocks\Controls;

/**
 * Class Color
 */
class Color extends ControlAbstract {

	/**
	 * Control name.
	 *
	 * @var string
	 */
	public $name = 'color';

	/**
	 * Text constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
		$this->label = __( 'Color', 'coywolf-custom-blocks' );
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		foreach ( [ 'location', 'width', 'help', 'default' ] as $setting ) {
			$this->settings[] = new ControlSetting( $this->settings_config[ $setting ] );
		}
	}

	/**
	 * Constrain a stored color to a safe CSS color value.
	 *
	 * Defense-in-depth: a color value is frequently emitted into a
	 * `style="…"` attribute, so an unvalidated value could break out of the
	 * declaration or the attribute. A plain hex color is normalised via
	 * sanitize_hex_color(); other common formats (8-digit hex, rgb()/rgba(),
	 * hsl()/hsla(), named colors, and `var(--custom-property)`) are allowed
	 * only when they carry no characters that could escape a style/HTML
	 * context and no `url()`/`expression()`. Anything else becomes ''.
	 *
	 * @param mixed $value   The stored value.
	 * @param bool  $is_echo Unused — the same constrained value serves both
	 *                       block_field() and block_value().
	 * @return string
	 */
	public function validate( $value, $is_echo ) {
		unset( $is_echo );
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return '';
		}

		$hex = sanitize_hex_color( $value );
		if ( is_string( $hex ) && '' !== $hex ) {
			return $hex;
		}

		if ( preg_match( '#^[A-Za-z0-9()%.,/\s\#_-]+$#', $value )
			&& ! preg_match( '/url\s*\(|expression\s*\(|[<>"\'{};]/i', $value ) ) {
			return $value;
		}

		return '';
	}
}
