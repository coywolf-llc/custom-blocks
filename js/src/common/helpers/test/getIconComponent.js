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
		// Built-in coywolf library — the block-default fallback glyph
		// that matches the wp-admin nav icon.
		[ 'coywolf/CcbBlockDefault', 'CcbBlockDefault' ],
	] )( 'resolves %s to the %s component',
		( slug, expectedName ) => {
			const component = getIconComponent( slug );
			expect( component ).not.toBeNull();
			expect( component.name ).toEqual( expectedName );
		}
	);

	it( 'falls back to CcbBlockDefault when the icon name does not exist in a loaded library', () => {
		// BoxIcons is eagerly imported, so a misspelled name resolves
		// synchronously to the built-in default rather than null.
		const component = getIconComponent( 'bi/BiDoesNotExist' );
		expect( component ).not.toBeNull();
		expect( component.name ).toEqual( 'CcbBlockDefault' );
	} );

	it( 'falls back to CcbBlockDefault for malformed input', () => {
		expect( getIconComponent( '' ).name ).toEqual( 'CcbBlockDefault' );
		expect( getIconComponent( null ).name ).toEqual( 'CcbBlockDefault' );
		expect( getIconComponent( undefined ).name ).toEqual( 'CcbBlockDefault' );
	} );

	it( 'falls back to CcbBlockDefault for legacy pre-BoxIcons slugs', () => {
		// 'coywolf_custom_blocks' was the default before the react-icons
		// swap and no longer maps to anything; falling back keeps blocks
		// rendering with the same glyph the user sees in the nav.
		expect( getIconComponent( 'coywolf_custom_blocks' ).name ).toEqual( 'CcbBlockDefault' );
		expect( getIconComponent( 'attach_file' ).name ).toEqual( 'CcbBlockDefault' );
	} );
} );
