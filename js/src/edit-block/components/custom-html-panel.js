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
 * Always-visible "Custom HTML" panel that lives below the fields grid.
 *
 * Binds to the same `templateMarkup` storage as the ACE-based TemplateEditor
 * mode in the header, but exposes it as a plain textarea so authors don't
 * have to discover the mode toggle. This is the inline replacement for the
 * `{theme}/blocks/block-{name}.php` template file workflow.
 *
 * Field references use the `{{field-name}}` syntax; substitution happens
 * server-side in TemplateEditor::render_markup(). The toolbar above the
 * textarea lets the user click a button to insert the right token at the
 * current caret position instead of having to remember the slug spelling.
 *
 * @return {React.ReactElement} The Custom HTML panel.
 */
const CustomHtmlPanel = () => {
	const { block, changeBlock } = useBlock();
	const { getFields } = useField();
	const templateMarkup = block.templateMarkup || '';
	const fields = getFields() || [];
	const exampleFieldName = fields[ 0 ]?.name ?? 'foo-baz';
	const textareaRef = useRef( null );

	/**
	 * Splice `{{slug}}` into the textarea at the current selection.
	 *
	 * Uses a ref to the underlying <textarea> so we can read
	 * selectionStart/End, replace the selected range (if any) with the
	 * token, push the new value through `changeBlock`, and then — after
	 * the value flows back as a prop and React commits — refocus the
	 * field and put the caret right after the inserted token so the user
	 * can keep typing without their hand leaving the keyboard.
	 *
	 * @param {string} slug Field slug to insert as `{{slug}}`.
	 */
	const insertField = ( slug ) => {
		const ta = textareaRef.current;
		const token = `{{${ slug }}}`;
		const start = ta ? ta.selectionStart : templateMarkup.length;
		const end   = ta ? ta.selectionEnd : templateMarkup.length;
		const next  = templateMarkup.slice( 0, start ) + token + templateMarkup.slice( end );

		changeBlock( { templateMarkup: next } );

		// Defer focus/caret-restore until after the controlled value
		// re-renders, otherwise setSelectionRange runs against the pre-
		// update string and the caret lands in the wrong spot.
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
		<div className="w-full max-w-4xl mx-auto mt-8 mb-8 p-6 bg-white border border-gray-300 rounded-sm shadow-sm">
			<h2 className="text-lg font-semibold mb-2">
				{ __( 'Custom HTML', 'coywolf-custom-blocks' ) }
			</h2>
			<p className="text-sm text-gray-700 mb-3">
				{ __( 'Define the front-end markup for this block here. This is saved in the database — no theme file is required. Any HTML, CSS, or JavaScript you enter is rendered as-is.', 'coywolf-custom-blocks' ) }
			</p>
			<p className="text-sm text-gray-700 mb-3">
				{
					sprintf(
						/* translators: %1$s: an example field name in the {{name}} syntax. */
						__( 'Reference a field by wrapping its slug in double curly braces, e.g. %1$s. Use the buttons below to insert one at the cursor.', 'coywolf-custom-blocks' ),
						`{{${ exampleFieldName }}}`
					)
				}
			</p>

			<div
				className="flex flex-wrap items-center gap-2 mb-2"
				role="toolbar"
				aria-label={ __( 'Insert field reference', 'coywolf-custom-blocks' ) }
			>
				<span className="text-xs uppercase tracking-wide text-gray-600 mr-1">
					{ __( 'Insert field:', 'coywolf-custom-blocks' ) }
				</span>
				{ fields.length > 0
					? fields.map( ( field ) => {
						const label = field.label && field.label !== '' ? field.label : field.name;
						return (
							<button
								key={ field.name }
								type="button"
								onClick={ () => insertField( field.name ) }
								className="inline-flex items-center px-2 py-1 text-xs font-mono bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded-sm"
								title={ sprintf(
									/* translators: %s: the {{slug}} token that will be inserted. */
									__( 'Insert %s at the cursor', 'coywolf-custom-blocks' ),
									`{{${ field.name }}}`
								) }
							>
								<span className="text-gray-500 mr-1">{ '{{' }</span>
								<span className="text-gray-900">{ field.name }</span>
								<span className="text-gray-500 ml-1">{ '}}' }</span>
								{ label !== field.name
									? <span className="ml-2 text-gray-500 font-sans normal-case">{ label }</span>
									: null
								}
							</button>
						);
					} )
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
				rows={ 16 }
				spellCheck={ false }
				value={ templateMarkup }
				placeholder={ `<div class="my-block">\n  <h2>{{${ exampleFieldName }}}</h2>\n</div>` }
				onChange={ ( event ) => {
					changeBlock( { templateMarkup: event.target.value } );
				} }
				aria-label={ __( 'Custom HTML for this block', 'coywolf-custom-blocks' ) }
			/>
			<p className="text-xs text-gray-600 mt-2">
				{ __( 'Leave empty to fall back to the theme template file (blocks/block-{slug}.php), if one exists.', 'coywolf-custom-blocks' ) }
			</p>
		</div>
	);
};

export default CustomHtmlPanel;
