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
import { QuestionIcon } from './';

/**
 * The bottom notice section.
 *
 * @return {React.ReactElement} The bottom notice.
 */
const BottomNotice = () => {
	const textClasses = 'text-sm mt-2 ml-2';
	const fieldTypesUrl = 'https://github.com/coywolf-llc/custom-blocks#fields';

	return (
		<div className="mt-4 p-5 bg-blue-100 text-blue-700 border-l-4 border-blue-700 rounded-sm">
			<h2 className="text-lg font-semibold text-blue-900">
				<span role="img" className="mr-2" aria-label={ __( 'Eyeglass emoji', 'coywolf-custom-blocks' ) }>🧐</span>
				{ __( 'Try adding a field.', 'coywolf-custom-blocks' ) }
			</h2>
			<p className={ textClasses }>
				{ __( 'Fields let you define the options you see when adding your block to a post or page.', 'coywolf-custom-blocks' ) }
			</p>
			<p className={ textClasses }>
				{ __( 'There are lots of different field types that let you build powerfully dynamic custom blocks.', 'coywolf-custom-blocks' ) }
			</p>
			<div className="flex items-center mt-5" >
				<QuestionIcon />
				<p className="text-xs font-semibold ml-1">
					{ __( 'Learn more:', 'coywolf-custom-blocks' ) }
				</p>
				&nbsp;
				<a className="text-xs font-semibold underline" href={ fieldTypesUrl } target="_blank" rel="noreferrer noopener">
					{ __( 'Field Types', 'coywolf-custom-blocks' ) }
				</a>
			</div>
		</div>
	);
};

export default BottomNotice;
