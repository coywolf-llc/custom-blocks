/**
 * Internal dependencies
 */
import { getIconComponent } from '../';

global.ccbEditor = { controls: {} };

describe( 'getIconComponent', () => {
	it.each( [
		// Canonical {libKey}/{ComponentName} format.
		[ 'bi/BiBox', 'BiBox' ],
		[ 'bi/BiHeart', 'BiHeart' ],
		[ 'bi/BiUserCircle', 'BiUserCircle' ],
		// Legacy snake_case slugs from v1.0.10 — assume BoxIcons + snake→Pascal.
		[ 'bi_box', 'BiBox' ],
		[ 'bi_heart', 'BiHeart' ],
	] )( 'resolves %s to the %s react-icons component',
		( slug, expectedName ) => {
			const component = getIconComponent( slug );
			expect( component ).not.toBeNull();
			// react-icons named exports preserve their name on the
			// function for ergonomic dev-tools display.
			expect( component.name ).toEqual( expectedName );
		}
	);

	it( 'returns null when the library is loaded and the icon name does not exist', () => {
		// BoxIcons is eagerly imported, so a misspelled name resolves
		// synchronously to null rather than a lazy wrapper.
		expect( getIconComponent( 'bi/BiDoesNotExist' ) ).toBeNull();
	} );

	it( 'returns null for malformed input', () => {
		expect( getIconComponent( '' ) ).toBeNull();
		expect( getIconComponent( null ) ).toBeNull();
		expect( getIconComponent( undefined ) ).toBeNull();
	} );
} );
