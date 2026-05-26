<?php
/**
 * Textarea control.
 *
 * @package   Coywolf\CustomBlocks
 * @copyright Copyright(c) 2022, Genesis Custom Blocks
 * @license http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 */

namespace Coywolf\CustomBlocks\Blocks\Controls;

/**
 * Class Textarea
 */
class Textarea extends ControlAbstract {

	/**
	 * Control name.
	 *
	 * @var string
	 */
	public $name = 'textarea';

	/**
	 * Field variable type.
	 *
	 * @var string
	 */
	public $type = 'string';

	/**
	 * Textarea constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
		$this->label = __( 'Textarea', 'coywolf-custom-blocks' );
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		foreach ( [ 'location', 'width', 'help' ] as $setting ) {
			$this->settings[] = new ControlSetting( $this->settings_config[ $setting ] );
		}

		$this->settings[] = new ControlSetting(
			[
				'name'    => 'default',
				'label'   => __( 'Default Value', 'coywolf-custom-blocks' ),
				'type'    => 'textarea',
				'default' => '',
			]
		);
		$this->settings[] = new ControlSetting( $this->settings_config['placeholder'] );
		$this->settings[] = new ControlSetting(
			[
				'name'    => 'maxlength',
				'label'   => __( 'Character Limit', 'coywolf-custom-blocks' ),
				'type'    => 'number_non_negative',
				'default' => '',
			]
		);
		$this->settings[] = new ControlSetting(
			[
				'name'    => 'number_rows',
				'label'   => __( 'Number of Rows', 'coywolf-custom-blocks' ),
				'type'    => 'number_non_negative',
				'default' => 4,
			]
		);
		$this->settings[] = new ControlSetting(
			[
				'name'    => 'new_lines',
				'label'   => __( 'New Lines', 'coywolf-custom-blocks' ),
				'type'    => 'new_line_format',
				'default' => 'autop',
			]
		);
	}
}
