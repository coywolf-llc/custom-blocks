/* global ccbEditor */

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
