/**
 * Internal dependencies
 */
import getBlock from '../getBlock';

describe( 'getBlock', () => {
	it.each( [
		[
			'non-JSON string',
			{},
		],
		[
			'{"example":"Example here"}',
			{ example: 'Example here' },
		],
		[
			'{"coywolf-custom-blocks/test-email":{"name":"test-email","title":"Test Email","category":{"icon":null,"slug":"text","title":"Text"},"icon":"coywolf_custom_blocks","keywords":[],"excluded":[],"fields":{"email":{}}}}',
			{
				'coywolf-custom-blocks/test-email': {
					name: 'test-email',
					title: 'Test Email',
					category: {
						icon: null,
						slug: 'text',
						title: 'Text',
					},
					icon: 'coywolf_custom_blocks',
					keywords: [],
					excluded: [],
					fields: {
						email: {},
					},
				},
			},
		],
	] )( 'should return a populated object if passed valid JSON',
		( postContent, expected ) => {
			expect( getBlock( postContent ) ).toEqual( expected );
		}
	);
} );
