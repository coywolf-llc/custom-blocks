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
import { useBlock } from '../hooks';

/**
 * Block-settings checkbox that toggles the Preview HTML panel.
 *
 * Off by default; on means the Preview HTML panel appears below the
 * Custom HTML panel on the edit-block screen AND the block's stored
 * `previewMarkup` is consulted when the post editor renders an editor-
 * preview of this block. Off means the panel is hidden and the
 * `previewMarkup` value (if any) is preserved in storage but ignored
 * at render time — toggling the checkbox back on brings it right back.
 *
 * @return {React.ReactElement}
 */
const ShowPreviewSection = () => {
	const { block: { showPreview = false }, changeBlock } = useBlock();
	const id = 'ccb-show-preview-html';

	return (
		<div className="mt-5">
			<div className="mt-2">
				<input
					type="checkbox"
					id={ id }
					className="mr-2"
					value="1"
					checked={ showPreview }
					onChange={ ( event ) => {
						if ( event.target ) {
							// @ts-ignore
							changeBlock( { showPreview: Boolean( event.target.checked ) } );
						}
					} }
				/>
				<label className="text-sm" htmlFor={ id }>
					{ __( 'Show Preview HTML panel', 'coywolf-custom-blocks' ) }
				</label>
				<p className="text-xs text-gray-600 mt-1 ml-6">
					{ __( 'Adds an optional second textarea below Custom HTML. Use it to render a visible placeholder in the post editor when the block\'s real output is empty or invisible (e.g. Schema.org JSON-LD). Frontend rendering is unaffected.', 'coywolf-custom-blocks' ) }
				</p>
			</div>
		</div>
	);
};

export default ShowPreviewSection;
