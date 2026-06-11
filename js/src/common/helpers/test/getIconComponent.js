/**
 * Internal dependencies
 */
import { getIconComponent } from '../';
import { DefaultIcon, loadLibrary } from '../../icons';

global.coywolfCcbEditor = { controls: {} };

/*
 * Test order matters: the icon cache is module-level, so the
 * "before the library loads" assertions must run before any test that
 * awaits `loadLibrary( 'lu' )` and primes the cache for the rest of
 * the file.
 */
describe( 'getIconComponent', () => {
	it( 'returns a lazy wrapper for Lucide slugs before the library loads', () => {
		const component = getIconComponent( 'lu/LuHeart' );
		expect( component ).not.toBeNull();
		expect( component.displayName ).toEqual( 'LazyIcon(lu/LuHeart)' );
	} );

	it( 'falls back to the local default glyph when the slug is empty or malformed', () => {
		expect( getIconComponent( '' ) ).toBe( DefaultIcon );
		expect( getIconComponent( null ) ).toBe( DefaultIcon );
		expect( getIconComponent( undefined ) ).toBe( DefaultIcon );
	} );

	it.each( [
		[ 'lu/LuSquareCode', 'LuSquareCode' ],
		[ 'lu/LuHeart', 'LuHeart' ],
		[ 'lu/LuUser', 'LuUser' ],
	] )( 'resolves %s to the real %s component once Lucide has loaded',
		async ( slug, expectedName ) => {
			await loadLibrary( 'lu' );
			const component = getIconComponent( slug );
			expect( component ).not.toBeNull();
			expect( component.name ).toEqual( expectedName );
		}
	);

	it( 'falls back to the local default glyph for unknown names in a loaded library', async () => {
		await loadLibrary( 'lu' );
		expect( getIconComponent( 'lu/LuDoesNotExist' ) ).toBe( DefaultIcon );
	} );

	it( 'returns a lazy wrapper for slugs in not-yet-loaded libraries', () => {
		// `bi` is never loaded in this file, so the wrapper renders the
		// default glyph until the chunk lands and BiBox takes over.
		const component = getIconComponent( 'bi/BiBox' );
		expect( component ).not.toBeNull();
		expect( component.displayName ).toEqual( 'LazyIcon(bi/BiBox)' );
	} );
} );
