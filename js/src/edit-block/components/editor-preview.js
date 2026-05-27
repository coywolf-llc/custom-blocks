/**
 * External dependencies
 */
import * as React from 'react';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Internal dependencies
 */
import { PreviewNotice } from './';
import { BUILDER_EDITING_MODE } from '../constants';
import { useBlock, useField } from '../hooks';
import { Fields } from '../../block-editor/components';
import { getFieldsAsArray } from '../../common/helpers';
import { EDIT_BLOCK_CONTEXT } from '../../common/constants';

/**
 * @typedef {Object} EditorPreviewProps The component props.
 * @property {import('./editor').SetEditorMode} setEditorMode Sets the editor mode.
 */

/**
 * The editor preview.
 *
 * @param {EditorPreviewProps} props
 * @return {React.ReactElement} The editor preview.
 */
const EditorPreview = ( { setEditorMode } ) => {
	const { block, changeBlock } = useBlock();
	const { getFields } = useField();
	const { previewAttributes = {} } = block;
	const fields = getFields();
	const hasFields = getFieldsAsArray( fields ).length > 0;
	const hasAnyMarkup = Boolean( block.templateMarkup || block.previewMarkup );

	/** @param {Object} newAttributes Attribute (field) names and values. */
	const setAttributes = ( newAttributes ) => {
		changeBlock( {
			previewAttributes: {
				...previewAttributes,
				...newAttributes,
			},
		} );
	};

	if ( ! hasFields && ! hasAnyMarkup ) {
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

	return (
		<div className="gcb-editor-form">
			{ hasFields
				? (
					<Fields
						key="example-fields"
						fields={ fields }
						parentBlockProps={ {
							setAttributes,
							attributes: previewAttributes,
						} }
						parentBlock={ {} }
						context={ EDIT_BLOCK_CONTEXT }
					/>
				) : null
			}
			{ hasAnyMarkup && block.name
				? (
					// Default `context=edit` URL arg sends the PHP renderer
					// down the `['preview', 'block']` priority — so Preview
					// HTML is used when `showPreview` is on, otherwise the
					// Custom HTML falls through. Mirrors what a post-editor
					// user sees when this block is inserted into a post.
					<ServerSideRender
						block={ `coywolf-custom-blocks/${ block.name }` }
						attributes={ previewAttributes }
						className="coywolf-custom-blocks-editor__ssr mt-6 w-full"
						httpMethod="POST"
					/>
				) : null
			}
		</div>
	);
};

export default EditorPreview;
