/**
 * External dependencies
 */
import * as React from 'react';
import classNames from 'classnames';

/**
 * WordPress dependencies
 */
import { Icon } from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useBlock } from '../hooks';
import { getIconComponent } from '../../common/helpers';
import {
	DEFAULT_LIBRARY,
	LIBRARIES,
	LIBRARY_OPTIONS,
	LIBRARY_STORAGE_KEY,
	formatIconSlug,
	loadLibrary,
	parseIconSlug,
} from '../../common/icons';
import { getDefaultBlock } from '../helpers';

const MAX_VISIBLE = 240;

/**
 * Read the user's last-used library from localStorage, falling back to
 * the BoxIcons default. Guarded against environments where
 * `localStorage` throws (private browsing, SSR snapshots).
 */
const readStoredLibrary = () => {
	try {
		if ( typeof window === 'undefined' || ! window.localStorage ) {
			return DEFAULT_LIBRARY;
		}
		const stored = window.localStorage.getItem( LIBRARY_STORAGE_KEY );
		return stored && LIBRARIES[ stored ] ? stored : DEFAULT_LIBRARY;
	} catch ( err ) {
		return DEFAULT_LIBRARY;
	}
};

const persistLibrary = ( key ) => {
	try {
		if ( typeof window === 'undefined' || ! window.localStorage ) {
			return;
		}
		window.localStorage.setItem( LIBRARY_STORAGE_KEY, key );
	} catch ( err ) {
		/* swallow */
	}
};

/**
 * The icon editor section.
 *
 * Renders the currently-selected icon next to a Choose/Close button.
 * Opening the picker shows a library dropdown (defaults to BoxIcons,
 * or the user's last-used library from localStorage; if the block
 * already has an icon, the picker first opens to that icon's library
 * so the existing selection is visible), a search input, and a grid
 * of icons from the active library, capped at MAX_VISIBLE for paint
 * performance.
 *
 * @return {React.ReactElement} The icon editor.
 */
const IconSection = () => {
	const { block, changeBlock } = useBlock();
	const currentIcon = block.icon || getDefaultBlock().icon;
	const { lib: currentIconLib } = parseIconSlug( currentIcon );

	const [ showIcons, setShowIcons ] = useState( false );
	const [ query, setQuery ] = useState( '' );

	// Initial library: prefer the currently-selected icon's library so
	// editing an existing block opens the picker in its own library;
	// fall back to localStorage; then to BoxIcons.
	const [ selectedLibrary, setSelectedLibrary ] = useState(
		() => ( LIBRARIES[ currentIconLib ] ? currentIconLib : readStoredLibrary() )
	);

	// `null` while the active library's module is in flight (first
	// render of a non-default library) so we can show a "Loading…"
	// state in the grid instead of an empty box.
	const [ libraryIcons, setLibraryIcons ] = useState( null );

	// Load the active library whenever it changes. The cache inside
	// loadLibrary() makes the eagerly-bundled BoxIcons call resolve
	// synchronously, so the default opens instantly.
	useEffect( () => {
		let cancelled = false;
		setLibraryIcons( null );
		loadLibrary( selectedLibrary )
			.then( ( mod ) => {
				if ( ! cancelled ) {
					setLibraryIcons( mod );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setLibraryIcons( {} );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [ selectedLibrary ] );

	const allIconNames = useMemo(
		() => ( libraryIcons ? Object.keys( libraryIcons ) : [] ),
		[ libraryIcons ]
	);

	const filteredIconNames = useMemo( () => {
		const needle = query.trim().toLowerCase();
		if ( '' === needle ) {
			return allIconNames.slice( 0, MAX_VISIBLE );
		}
		const matches = [];
		for ( let i = 0; i < allIconNames.length && matches.length < MAX_VISIBLE; i++ ) {
			if ( allIconNames[ i ].toLowerCase().indexOf( needle ) !== -1 ) {
				matches.push( allIconNames[ i ] );
			}
		}
		return matches;
	}, [ allIconNames, query ] );

	const onLibraryChange = ( event ) => {
		const next = event.target.value;
		setSelectedLibrary( next );
		persistLibrary( next );
		setQuery( '' );
	};

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
						<div className="flex flex-wrap items-center gap-2 mb-2">
							<label htmlFor="ccb-icon-library" className="text-xs uppercase tracking-wide text-gray-600">
								{ __( 'Library:', 'coywolf-custom-blocks' ) }
							</label>
							<select
								id="ccb-icon-library"
								className="text-sm border border-gray-600 rounded-sm py-1 pl-2 pr-7 bg-white"
								value={ selectedLibrary }
								onChange={ onLibraryChange }
							>
								{ LIBRARY_OPTIONS.map( ( [ key, displayName ] ) => (
									<option key={ key } value={ key }>
										{ displayName }
									</option>
								) ) }
							</select>
						</div>

						<input
							type="search"
							className="w-full text-sm border border-gray-600 rounded-sm px-2 py-1 mb-1"
							placeholder={ __( 'Search icons in this library…', 'coywolf-custom-blocks' ) }
							value={ query }
							onChange={ ( event ) => setQuery( event.target.value ) }
							aria-label={ __( 'Filter icons by name', 'coywolf-custom-blocks' ) }
						/>

						<div className="flex items-center justify-between text-xs text-gray-600 mb-1">
							<span>
								{ libraryIcons === null
									? __( 'Loading library…', 'coywolf-custom-blocks' )
									: '' === query.trim()
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
							{ libraryIcons !== null && filteredIconNames.length >= MAX_VISIBLE
								? <span className="italic">{ __( 'Refine to see more.', 'coywolf-custom-blocks' ) }</span>
								: null
							}
						</div>

						{ libraryIcons === null
							? (
								<div
									className="flex items-center justify-center border border-gray-600 rounded-sm h-40 text-sm text-gray-500"
									role="status"
								>
									{ __( 'Loading icons…', 'coywolf-custom-blocks' ) }
								</div>
							)
							: filteredIconNames.length > 0
								? (
									<div
										role="listbox"
										className="grid grid-cols-6 border border-gray-600 rounded-sm h-40 p-1 overflow-auto"
										aria-label={ __( 'Icons', 'coywolf-custom-blocks' ) }
									>
										{ filteredIconNames.map( ( iconName ) => {
											const iconSlug = formatIconSlug( selectedLibrary, iconName );
											const isSelected = currentIcon === iconSlug;
											const IconComp = libraryIcons[ iconName ];
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
													title={ iconName }
													onClick={ () => {
														changeBlock( { icon: iconSlug } );
													} }
												>
													<Icon className="w-5 h-5" size={ 24 } icon={ IconComp } />
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
