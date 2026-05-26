<?php
/**
 * Testing Blocks
 *
 * @package Coywolf\CustomBlocks
 *
 * Plugin Name: Testing Blocks
 * Plugin URI: https://github.com/studiopress/genesis-custom-blocks
 * Author: Genesis Custom Blocks contributors
 */

use function Coywolf\CustomBlocks\add_block;
use function Coywolf\CustomBlocks\add_field;

add_filter(
	'coywolf_custom_blocks_template_path',
	static function ( $path ) {
		unset( $path );
		return __DIR__;
	}
);

add_action(
	'coywolf_custom_blocks_add_blocks',
	static function () {
		$url_block_slug  = 'test-url';
		$text_block_slug = 'test-text';

		add_block(
			$url_block_slug,
			[
				'title'    => __( 'Test Url', 'coywolf-custom-blocks' ),
				'category' => 'common',
				'icon'     => 'waves',
				'excluded' => [ 'page' ],
				'keywords' => [ 'example', 'foo' ],
			]
		);
		add_field(
			$url_block_slug,
			'url',
			[
				'label'   => 'Url Here',
				'control' => 'url',
				'width'   => '50',
			]
		);

		add_block(
			$text_block_slug,
			[
				'title'    => __( 'Test Text', 'coywolf-custom-blocks' ),
				'category' => 'common',
				'icon'     => 'bi/BiBox',
				'excluded' => [ 'page' ],
				'keywords' => [ 'example', 'foo' ],
			]
		);
		add_field(
			$text_block_slug,
			'text',
			[
				'label'   => 'Enter some text here',
				'control' => 'text',
				'width'   => '50',
			]
		);
	}
);
