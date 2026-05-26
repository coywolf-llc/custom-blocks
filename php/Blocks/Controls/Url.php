<?php
/**
 * Url control.
 *
 * @package   Coywolf\CustomBlocks
 * @copyright Copyright(c) 2022, Genesis Custom Blocks
 * @license http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 */

namespace Coywolf\CustomBlocks\Blocks\Controls;

/**
 * Class Url
 */
class Url extends ControlAbstract {

	/**
	 * Control name.
	 *
	 * @var string
	 */
	public $name = 'url';

	/**
	 * Text constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
		$this->label = __( 'URL', 'coywolf-custom-blocks' );
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		$this->settings[] = new ControlSetting( $this->settings_config['location'] );
		$this->settings[] = new ControlSetting( $this->settings_config['width'] );
		$this->settings[] = new ControlSetting( $this->settings_config['help'] );
		$this->settings[] = new ControlSetting(
			[
				'name'    => 'default',
				'label'   => __( 'Default Value', 'coywolf-custom-blocks' ),
				'type'    => 'url',
				'default' => '',
			]
		);
		$this->settings[] = new ControlSetting( $this->settings_config['placeholder'] );
	}
}
