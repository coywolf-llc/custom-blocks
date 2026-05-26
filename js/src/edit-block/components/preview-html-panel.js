/**
 * External dependencies
 */
import * as React from 'react';

/**
 * WordPress dependencies
 */
import { useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useBlock, useField } from '../hooks';

/**
 * Optional "Preview HTML" panel that sits below the Custom HTML panel.
 *
 * Hidden by default; the user opts in via the "Show Preview HTML
 * panel" checkbox in Block Settings. Bound to the block's
 * `previewMarkup` field; its content is rendered ONLY when the post
 * editor requests the block's editor preview (`?context=edit`), so
 * blocks whose real output is invisible — Schema.org JSON-LD,
 * meta-only blocks, etc. — can still show a visible placeholder in
 * the editor without affecting the frontend.
 *
 * Same `{{field-slug}}` substitution + PHP execution pipeline as
 * Custom HTML; same `Insert field` dropdown for ergonomics.
 *
 * @return {React.ReactElement} The Preview HTML panel.
 */
const PreviewHtmlPanel = () => {
	const { block, changeBlock } = useBlock();
	const { getFields } = useField();
	const previewMarkup = block.previewMarkup || '';
	const fields = getFields() || [];
	const exampleFieldName = fields[ 0 ]?.name ?? 'foo-baz';
	const textareaRef = useRef( null );

	const insertField = ( slug ) => {
		const ta = textareaRef.current;
		const token = `{{${ slug }}}`;
		const start = ta ? ta.selectionStart : previewMarkup.length;
		const end   = ta ? ta.selectionEnd : previewMarkup.length;
		const next  = previewMarkup.slice( 0, start ) + token + previewMarkup.slice( end );

		changeBlock( { previewMarkup: next } );

		window.requestAnimationFrame( () => {
			const current = textareaRef.current;
			if ( ! current ) {
				return;
			}
			const caret = start + token.length;
			current.focus();
			current.setSelectionRange( caret, caret );
		} );
	};

	return (
		<div className="w-full max-w-4xl mx-auto mt-2 mb-8 p-6 bg-white border border-gray-300 rounded-sm shadow-sm">
			<h2 className="text-lg font-semibold mb-2">
				{ __( 'Preview HTML', 'coywolf-custom-blocks' ) }
			</h2>
			<p className="text-sm text-gray-700 mb-3">
				{ __( 'Optional markup shown only in the post editor — useful when the block\'s real output is invisible (e.g. Schema.org JSON-LD) and you want a visible placeholder while editing. Leave blank to use the Custom HTML for the editor preview too. Frontend rendering is never affected by this field.', 'coywolf-custom-blocks' ) }
			</p>

			<div className="flex items-center gap-2 mb-2">
				<label
					htmlFor="ccb-preview-insert-field-select"
					className="text-xs uppercase tracking-wide text-gray-600"
				>
					{ __( 'Insert field:', 'coywolf-custom-blocks' ) }
				</label>
				{ fields.length > 0
					? (
						<select
							id="ccb-preview-insert-field-select"
							className="text-sm border border-gray-300 rounded-sm py-1 pl-2 pr-7 bg-white"
							value=""
							onChange={ ( event ) => {
								const slug = event.target.value;
								if ( '' === slug ) {
									return;
								}
								insertField( slug );
								event.target.value = '';
							} }
							aria-label={ __( 'Insert field reference at the cursor', 'coywolf-custom-blocks' ) }
						>
							<option value="">
								{ __( 'Choose a field…', 'coywolf-custom-blocks' ) }
							</option>
							{ fields.map( ( field ) => {
								const label = field.label && field.label !== '' ? field.label : field.name;
								return (
									<option key={ field.name } value={ field.name }>
										{ label !== field.name
											? `{{${ field.name }}} — ${ label }`
											: `{{${ field.name }}}`
										}
									</option>
								);
							} ) }
						</select>
					)
					: (
						<span className="text-xs text-gray-500 italic">
							{ __( "Add a field above and it'll appear here.", 'coywolf-custom-blocks' ) }
						</span>
					)
				}
			</div>

			<textarea
				ref={ textareaRef }
				className="w-full font-mono text-sm p-3 border border-gray-300 rounded-sm"
				rows={ 10 }
				spellCheck={ false }
				value={ previewMarkup }
				placeholder={ sprintf(
					/* translators: %s an example slug */
					__( '<p style="color:darkorange"><strong>%s</strong></p>', 'coywolf-custom-blocks' ),
					'Block title for the editor preview'
				) }
				onChange={ ( event ) => {
					changeBlock( { previewMarkup: event.target.value } );
				} }
				aria-label={ __( 'Preview HTML for this block', 'coywolf-custom-blocks' ) }
			/>
			<p className="text-xs text-gray-600 mt-2">
				{ __( 'If a theme template at blocks/preview-{slug}.php exists and this field is empty, that file is used as the editor preview instead.', 'coywolf-custom-blocks' ) }
			</p>
		</div>
	);
};

export default PreviewHtmlPanel;
