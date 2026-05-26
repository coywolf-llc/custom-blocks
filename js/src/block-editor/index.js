/* global coywolfCustomBlocks, ccbBlocks, __webpack_public_path__ */

/**
 * Resolve dynamic-import chunks against the plugin's actual URL at
 * runtime. See js/src/edit-block/index.js for the full rationale.
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
import { addFilter } from '@wordpress/hooks';
import { setLocaleData } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { addControls, registerBlocks } from './helpers';
import { Edit } from './components';

setLocaleData( { '': {} }, 'coywolf-custom-blocks' );
addFilter( 'coywolfCustomBlocks.controls', 'coywolfCustomBlocks/addControls', addControls );

// @ts-ignore
registerBlocks( coywolfCustomBlocks, ccbBlocks, Edit );
