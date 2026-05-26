/**
 * External dependencies
 */
import * as React from 'react';
import classNames from 'classnames';

/**
 * WordPress dependencies
 */
import { Icon } from '@wordpress/components';
import { useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useBlock } from '../hooks';
import { getIconComponent, pascalCaseToSnakeCase } from '../../common/helpers';
import * as blockIcons from '../../common/icons';
import { getDefaultBlock } from '../helpers';

/**
 * The icon editor section.
 *
 * Surfaces the full BoxIcons set (react-icons/bi, ≈1,600 icons) as a
 * searchable picker. Without the search input, a 6-column grid of that
 * many icons is unbrowseable — the user types a keyword (e.g. "user",
 * "image", "arrow") and the grid narrows to matching names in real
 * time. Visible matches are capped so the DOM stays bounded even on
 * an empty search; a hint tells the user to type for more.
 *
 * @return {React.ReactElement} The icon editor.
 */
const IconSection = () => {
	const { block, changeBlock } = useBlock();
	const [ showIcons, setShowIcons ] = useState( false );
	const [ query, setQuery ] = useState( '' );
	const currentIcon = block.icon || getDefaultBlock().icon;

	// Maximum icons to render at once. Without a cap an empty search would
	// drop ~1,600 buttons into the DOM and slow down the first paint of
	// the icon picker noticeably. With the cap, an empty query shows the
	// first MAX_VISIBLE icons and the user is told to type to narrow.
	const MAX_VISIBLE = 240;

	// All icon export names, computed once.
	const allIconNames = useMemo( () => Object.keys( blockIcons ), [] );

	// Filter by lowercased substring on the snake-cased slug. The user
	// thinks in terms like "user" or "arrow-up", not "BiUserCircle", so
	// match against the slug rather than the PascalCase export name.
	const filteredIconNames = useMemo( () => {
		const needle = query.trim().toLowerCase();
		if ( '' === needle ) {
			return allIconNames.slice( 0, MAX_VISIBLE );
		}
		const matches = [];
		for ( let i = 0; i < allIconNames.length && matches.length < MAX_VISIBLE; i++ ) {
			const name = allIconNames[ i ];
			const slug = pascalCaseToSnakeCase( name );
			if ( slug.indexOf( needle ) !== -1 ) {
				matches.push( name );
			}
		}
		return matches;
	}, [ allIconNames, query ] );

	return (
		<div className="mt-5">
			<span className="text-sm">{ __( 'Icon', 'coywolf-custom-blocks' ) }</span>
			<button
				className="flex border border-gray-600 rounded-sm mt-2"
				onClick={ () => {
					setShowIcons( ( current ) => ! current );
				} }
			>
				<div className="flex items-center justify-center h-8 w-8 border-r border-gray-600">
					<Icon size={ 24 } icon={ getIconComponent( currentIcon ) } />
				</div>
				<div className="flex items-center h-8 px-3">
					{ showIcons ? __( 'Close', 'coywolf-custom-blocks' ) : __( 'Choose', 'coywolf-custom-blocks' ) }
				</div>
			</button>
			{ showIcons
				? (
					<div className="mt-2">
						<input
							type="search"
							className="w-full text-sm border border-gray-600 rounded-sm px-2 py-1 mb-1"
							placeholder={ __( 'Search icons (e.g. user, arrow, image)…', 'coywolf-custom-blocks' ) }
							value={ query }
							onChange={ ( event ) => setQuery( event.target.value ) }
							aria-label={ __( 'Filter icons by name', 'coywolf-custom-blocks' ) }
						/>
						<div className="flex items-center justify-between text-xs text-gray-600 mb-1">
							<span>
								{
									'' === query.trim()
										? sprintf(
											/* translators: %1$d visible, %2$d total */
											__( 'Showing %1$d of %2$d icons — type to filter.', 'coywolf-custom-blocks' ),
											filteredIconNames.length,
											allIconNames.length
										)
										: sprintf(
											/* translators: %1$d visible matches, %2$s the query */
											__( '%1$d match(es) for "%2$s".', 'coywolf-custom-blocks' ),
											filteredIconNames.length,
											query.trim()
										)
								}
							</span>
							{ filteredIconNames.length >= MAX_VISIBLE
								? <span className="italic">{ __( 'Refine to see more.', 'coywolf-custom-blocks' ) }</span>
								: null
							}
						</div>
						{ filteredIconNames.length > 0
							? (
								<div
									role="listbox"
									className="grid grid-cols-6 border border-gray-600 rounded-sm h-40 p-1 overflow-auto"
									aria-label={ __( 'Icons', 'coywolf-custom-blocks' ) }
								>
									{ filteredIconNames.map( ( iconName ) => {
										const snakeCaseIconName = pascalCaseToSnakeCase( iconName );
										const isSelected = currentIcon === snakeCaseIconName;
										return (
											<button
												key={ iconName }
												className={ classNames(
													'flex items-center justify-center h-8 w-8 hover:border-black border',
													isSelected ? 'border-blue-700' : 'border-transparent'
												) }
												type="button"
												role="option"
												aria-selected={ isSelected }
												title={ snakeCaseIconName }
												onClick={ () => {
													changeBlock( { icon: snakeCaseIconName } );
												} }
											>
												{ /* eslint-disable-next-line import/namespace */ }
												<Icon className="w-5 h-5" size={ 24 } icon={ blockIcons[ iconName ] } />
											</button>
										);
									} ) }
								</div>
							)
							: (
								<p className="text-sm text-gray-600 italic border border-gray-300 rounded-sm p-3">
									{ sprintf(
										/* translators: %s the search query */
										__( 'No icons match "%s".', 'coywolf-custom-blocks' ),
										query.trim()
									) }
								</p>
							)
						}
					</div>
				)
				: null
			}
		</div>
	);
};

export default IconSection;
