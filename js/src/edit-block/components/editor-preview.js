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
					// `ccb_render_mode=editor` makes the PHP renderer walk
					// `previewMarkup → templateMarkup` *without* the
					// `showPreview` gate — i.e. show whatever's in Preview
					// HTML if it's set, otherwise the Custom HTML. The
					// builder's preview tabs need to ignore the toggle
					// since it controls post-editor behaviour, not the
					// developer-facing preview tabs themselves.
					//
					// Wrapping in `.editor-styles-wrapper` lets the
					// theme's editor stylesheets (loaded by
					// EditBlock::enqueue_theme_preview_styles) cascade
					// onto the rendered block — most editor CSS is
					// written scoped under that class, so the preview
					// approximates what the post editor shows.
					<div className="editor-styles-wrapper mt-6 w-full">
						<ServerSideRender
							block={ `coywolf-custom-blocks/${ block.name }` }
							attributes={ previewAttributes }
							className="coywolf-custom-blocks-editor__ssr"
							httpMethod="POST"
							urlQueryArgs={ { ccb_render_mode: 'editor' } }
						/>
					</div>
				) : null
			}
		</div>
	);
};

export default EditorPreview;
