/**
 * External dependencies
 */
import * as React from 'react';

/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';
import { PostSavedState } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Internal dependencies
 */
import { PreviewNotice } from './';
import { BUILDER_EDITING_MODE } from '../constants';
import { useBlock } from '../hooks';

/**
 * @typedef {Object} FrontEndPreviewProps The component props.
 * @property {import('./editor').SetEditorMode} setEditorMode Sets the editor mode.
 */

/**
 * The front-end preview of the block.
 *
 * Renders the block's Custom HTML (templateMarkup) the way visitors
 * actually see it — sending `ccb_render_mode=view` so the PHP renderer
 * walks only the `['block']` priority and never falls into the Preview
 * HTML branch. (We can't reuse `context=view` for this because the WP
 * REST block-renderer endpoint validates `context` against an enum of
 * just `['edit']`, so the request would be rejected with "Invalid
 * parameter(s): context".)
 *
 * Attributes come from the in-memory edited block (`useBlock`) so
 * changes made on the Editor Preview tab are reflected here without
 * needing to save the block post first.
 *
 * @param {FrontEndPreviewProps} props
 * @return {React.ReactElement} The front-end preview.
 */
const FrontEndPreview = ( { setEditorMode } ) => {
	const { block } = useBlock();
	const isPostNew = useSelect( ( select ) => select( 'core/editor' ).isEditedPostNew() );
	const { previewAttributes = {} } = block;

	if ( ! block.templateMarkup ) {
		return (
			<PreviewNotice>
				<button
					className="underline"
					onClick={ () => setEditorMode( BUILDER_EDITING_MODE ) }
				>
					{ __( 'Builder', 'coywolf-custom-blocks' ) }
				</button>
			</PreviewNotice>
		);
	}

	// Server reads the registered block config from the saved post — a
	// brand-new unsaved block has no Coywolf registration yet, so SSR
	// would 404. Ask the user to save first.
	if ( isPostNew ) {
		return (
			<div className="mt-4 flex flex-row items-center">
				{ __( 'Please save your block to preview it:', 'coywolf-custom-blocks' ) }
				&nbsp;
				<PostSavedState />
			</div>
		);
	}

	return (
		<ServerSideRender
			block={ `coywolf-custom-blocks/${ block.name }` }
			attributes={ previewAttributes }
			className="coywolf-custom-blocks-editor__ssr"
			httpMethod="POST"
			urlQueryArgs={ { ccb_render_mode: 'view' } }
		/>
	);
};

export default FrontEndPreview;
