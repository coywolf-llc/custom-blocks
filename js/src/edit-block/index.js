/* global ccbEditor, __webpack_public_path__ */

/**
 * Tell webpack where to fetch dynamic-import chunks from at runtime.
 *
 * The build pipeline emits the entry bundle to `js/dist/edit-block.js`
 * and the on-demand chunks (the per-library icon bundles produced by
 * dynamic imports in `common/icons/libraries.js`) to
 * `js/dist/[name].chunk.js`. Webpack needs a public path matching the
 * plugin's URL on the current site so its runtime can resolve those
 * chunk paths against an http(s) origin.
 *
 * `document.currentScript.src` resolves to the URL WordPress used when
 * it enqueued this entry bundle (e.g.
 * `https://example.com/wp-content/plugins/coywolf-custom-blocks/js/dist/edit-block.js`).
 * Strip the `js/dist/edit-block.js` suffix and what remains is the
 * plugin folder URL; webpack appends `chunkFilename` to that to fetch
 * each chunk.
 *
 * Must run before any other import that could trigger a dynamic load.
 */
( () => {
	if ( typeof document === 'undefined' ) {
		return;
	}
	const script = document.currentScript;
	if ( ! script || ! script.src ) {
		return;
	}
	const marker = '/js/dist/';
	const idx = script.src.indexOf( marker );
	if ( idx === -1 ) {
		return;
	}
	// eslint-disable-next-line no-global-assign
	__webpack_public_path__ = script.src.slice( 0, idx + 1 );
} )();

/**
 * WordPress dependencies
 */
import domReady from '@wordpress/dom-ready';
import { addFilter } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import { initializeEditor } from './helpers';
import { addControls } from '../block-editor/helpers';

addFilter( 'coywolfCustomBlocks.controls', 'coywolfCustomBlocks/addControls', addControls );

// Renders the app in the container.
domReady( () => {
	let container = document.querySelector( 'body > div:first-child' );
	if ( ! container ) {
		container = document.querySelector( 'body' );
	}

	// @ts-ignore
	initializeEditor( ccbEditor, container );
} );
