/**
 * Internal dependencies
 */
import { registerBlocks } from '../';

const mockRegisterBlockType = jest.fn();
jest.mock( '@wordpress/blocks', () => {
	return {
		...jest.requireActual( '@wordpress/blocks' ),
		registerBlockType: ( ...args ) => mockRegisterBlockType( ...args ),
	};
} );
const Edit = () => {};
const expectedArgs = {
	apiVersion: 3,
	title: expect.any( String ),
	category: expect.any( String ),
	icon: expect.any( Function ),
	keywords: expect.any( Array ),
	attributes: expect.any( Object ),
	edit: expect.any( Function ),
	save: expect.any( Function ),
};

describe( 'registerBlocks', () => {
	it( 'should not register any block if there is no Genesis Custom Blocks block passed', () => {
		registerBlocks( {}, {}, Edit );
		expect( mockRegisterBlockType ).toHaveBeenCalledTimes( 0 );
	} );

	it( 'should register a single block', () => {
		const blockName = 'coywolf-custom-blocks/test-post';
		const ccbBlocks = {};
		ccbBlocks[ blockName ] = {
			title: 'Test Post',
			category: 'widget',
			keywords: [ 'foobaz', 'example' ],
			icon: 'camera_alt',
		};

		registerBlocks( {}, ccbBlocks, Edit );
		expect( mockRegisterBlockType ).toHaveBeenCalledWith(
			blockName,
			expect.objectContaining( expectedArgs )
		);
	} );

	it( 'should register two blocks', () => {
		registerBlocks(
			{},
			{
				'coywolf-custom-blocks/example-post': {
					title: 'An Example Post',
					category: 'widget',
					keywords: [ 'foobaz', 'example' ],
					icon: 'coywolf_custom_blocks',
				},
				'coywolf-custom-blocks/example-email': {
					title: 'Example Email',
					category: 'widget',
					keywords: [ 'example-keyword', 'another' ],
					icon: 'add_circle_outline',
				},
			},
			Edit
		);

		expect( mockRegisterBlockType ).toHaveBeenNthCalledWith(
			2,
			expect.any( String ),
			expect.objectContaining( expectedArgs )
		);
	} );
} );
