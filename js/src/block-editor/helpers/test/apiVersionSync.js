/**
 * This test ensures that the API version declared in JavaScript block registration
 * matches the API version declared in the PHP Loader class.
 *
 * This prevents bugs where blocks are registered with different API versions on
 * the client (JS) and server (PHP) sides, which can cause editor issues like
 * broken prop communication in WordPress 7.0+ iframe editor.
 */

/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );

describe( 'API Version Sync', () => {
	it( 'should have matching API versions in PHP Loader and JS registerBlocks', () => {
		// Read the PHP Loader file
		const phpLoaderPath = path.join( __dirname, '../../../../../php/Blocks/Loader.php' );
		const phpContent = fs.readFileSync( phpLoaderPath, 'utf8' );

		// Extract API version from PHP (looking for 'api_version' => NUMBER)
		const phpMatch = phpContent.match( /'api_version'\s*=>\s*(\d+)/ );
		expect( phpMatch ).not.toBeNull();
		const phpApiVersion = parseInt( phpMatch[ 1 ], 10 );

		// Read the JS registerBlocks file
		const jsRegisterBlocksPath = path.join( __dirname, '../registerBlocks.js' );
		const jsContent = fs.readFileSync( jsRegisterBlocksPath, 'utf8' );

		// Extract API version from JS (looking for apiVersion: NUMBER)
		const jsMatch = jsContent.match( /apiVersion:\s*(\d+)/ );
		expect( jsMatch ).not.toBeNull();
		const jsApiVersion = parseInt( jsMatch[ 1 ], 10 );

		// They must match
		expect( jsApiVersion ).toBe( phpApiVersion );
		expect( jsApiVersion ).toBe( 3 ); // Also explicitly check it's v3
	} );
} );
