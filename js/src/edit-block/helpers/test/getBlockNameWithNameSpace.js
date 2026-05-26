/**
 * Internal dependencies
 */
import getBlockNameWithNameSpace from '../getBlockNameWithNameSpace';

describe( 'getBlockNameWithNameSpace', () => {
	it.each( [
		[
			{
				'coywolf-custom-blocks/example-here': { name: 'example-here', title: 'Example Here' },
			},
			'coywolf-custom-blocks/example-here',
		],
		[
			{
				'': { name: '', title: 'Example Block' },
			},
			'',
		],
		[
			{},
			'',
		],
	] )( 'should get the block name',
		( block, expected ) => {
			expect( getBlockNameWithNameSpace( block ) ).toEqual( expected );
		}
	);
} );
