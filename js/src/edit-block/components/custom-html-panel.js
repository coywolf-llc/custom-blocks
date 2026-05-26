/**
 * External dependencies
 */
import * as React from 'react';

/**
 * WordPress dependencies
 */
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
 * server-side in TemplateEditor::render_markup().
 *
 * @return {React.ReactElement} The Custom HTML panel.
 */
const CustomHtmlPanel = () => {
	const { block, changeBlock } = useBlock();
	const { getFields } = useField();
	const templateMarkup = block.templateMarkup || '';
	const exampleFieldName = getFields()?.shift()?.name ?? 'foo-baz';

	return (
		<div className="w-full max-w-4xl mx-auto mt-8 mb-8 p-6 bg-white border border-gray-300 rounded-sm shadow-sm">
			<h2 className="text-lg font-semibold mb-2">
				{ __( 'Custom HTML', 'genesis-custom-blocks' ) }
			</h2>
			<p className="text-sm text-gray-700 mb-3">
				{ __( 'Define the front-end markup for this block here. This is saved in the database — no theme file is required. Any HTML, CSS, or JavaScript you enter is rendered as-is.', 'genesis-custom-blocks' ) }
			</p>
			<p className="text-sm text-gray-700 mb-3">
				{
					sprintf(
						/* translators: %1$s: an example field name in the {{name}} syntax. */
						__( 'Reference a field by wrapping its slug in double curly braces, e.g. %1$s.', 'genesis-custom-blocks' ),
						`{{${ exampleFieldName }}}`
					)
				}
			</p>
			<textarea
				className="w-full font-mono text-sm p-3 border border-gray-300 rounded-sm"
				rows={ 16 }
				spellCheck={ false }
				value={ templateMarkup }
				placeholder={ `<div class="my-block">\n  <h2>{{${ exampleFieldName }}}</h2>\n</div>` }
				onChange={ ( event ) => {
					changeBlock( { templateMarkup: event.target.value } );
				} }
				aria-label={ __( 'Custom HTML for this block', 'genesis-custom-blocks' ) }
			/>
			<p className="text-xs text-gray-600 mt-2">
				{ __( 'Leave empty to fall back to the theme template file (blocks/block-{slug}.php), if one exists.', 'genesis-custom-blocks' ) }
			</p>
		</div>
	);
};

export default CustomHtmlPanel;
