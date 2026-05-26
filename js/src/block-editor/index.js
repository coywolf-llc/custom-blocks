/* global coywolfCustomBlocks, ccbBlocks */

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
