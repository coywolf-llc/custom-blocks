/**
 * External dependencies
 */
import * as React from 'react';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { PreviewIframe, PreviewNotice } from './';
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
					// `ccb_render_mode=editor` makes the PHP renderer walk
					// `previewMarkup → templateMarkup` *without* the
					// `showPreview` gate — i.e. show whatever's in Preview
					// HTML if it's set, otherwise the Custom HTML.
					//
					// PreviewIframe renders the result inside an isolated
					// iframe with the theme's editor stylesheets and
					// theme.json global styles injected, so theme CSS
					// can't leak into the wp-admin chrome.
					<PreviewIframe
						className="mt-6 w-full"
						blockName={ `coywolf-custom-blocks/${ block.name }` }
						attributes={ previewAttributes }
						urlQueryArgs={ { ccb_render_mode: 'editor' } }
					/>
				) : null
			}
		</div>
	);
};

export default EditorPreview;
