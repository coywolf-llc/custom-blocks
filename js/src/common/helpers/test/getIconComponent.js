/**
 * Internal dependencies
 */
import { getIconComponent } from '../';

global.ccbEditor = { controls: {} };

describe( 'getIconComponent', () => {
	it.each( [
		// Lucide is eagerly bundled — these resolve synchronously to
		// the real react-icons component.
		[ 'lu/LuSquareCode', 'LuSquareCode' ],
		[ 'lu/LuHeart', 'LuHeart' ],
		[ 'lu/LuUser', 'LuUser' ],
	] )( 'resolves %s to the %s component synchronously',
		( slug, expectedName ) => {
			const component = getIconComponent( slug );
			expect( component ).not.toBeNull();
			expect( component.name ).toEqual( expectedName );
		}
	);

	it( 'falls back to LuSquareCode when the slug is empty or malformed', () => {
		expect( getIconComponent( '' ).name ).toEqual( 'LuSquareCode' );
		expect( getIconComponent( null ).name ).toEqual( 'LuSquareCode' );
		expect( getIconComponent( undefined ).name ).toEqual( 'LuSquareCode' );
	} );

	it( 'falls back to LuSquareCode when the icon name does not exist in Lucide', () => {
		const component = getIconComponent( 'lu/LuDoesNotExist' );
		expect( component.name ).toEqual( 'LuSquareCode' );
	} );

	it( 'returns a lazy wrapper for slugs in not-yet-loaded libraries', () => {
		// `bi` is dynamic-imported in v1.0.18+ — picker triggers its
		// load when the user opens that library. In the test env
		// nothing's loaded, so getIconComponent returns the LazyIcon
		// wrapper, which renders LuSquareCode while the chunk loads
		// and the real BiBox once it lands.
		const component = getIconComponent( 'bi/BiBox' );
		expect( component ).not.toBeNull();
		expect( component.displayName ).toEqual( 'LazyIcon(bi/BiBox)' );
	} );
} );
