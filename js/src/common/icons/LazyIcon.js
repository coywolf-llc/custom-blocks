/**
 * External dependencies
 */
import * as React from 'react';

/**
 * WordPress dependencies
 */
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { getCachedLibrary, loadLibrary, parseIconSlug } from './iconCache';

/**
 * Lazy-loading wrapper for an icon stored as a `{lib}/{ComponentName}`
 * slug. Looks up the icon in the cache; if absent, triggers the
 * library's dynamic import and re-renders once it lands.
 *
 * Renders `null` while the library is loading — the icon slot is small
 * enough that a blank flash is less disruptive than a placeholder
 * spinner. Once the library is cached, subsequent uses of the same slug
 * (or any slug from that library) are synchronous.
 *
 * The component is intentionally prop-light so it can be dropped in
 * anywhere the previous `getIconComponent()` return value was used.
 *
 * @param {Object} props
 * @param {string} props.slug Stored icon slug (e.g. 'bi/BiBox').
 * @param {string} [props.className]
 * @param {Object} [props.style]
 * @param {string|number} [props.size]
 * @return {React.ReactElement|null}
 */
const LazyIcon = ( { slug, className, style, size } ) => {
	const { lib, name } = parseIconSlug( slug );

	// Read straight from the cache on the first render so any already-
	// loaded library renders synchronously with no flash.
	const cachedLib = getCachedLibrary( lib );
	const initialComponent = cachedLib ? cachedLib[ name ] || null : null;

	const [ IconComponent, setIconComponent ] = useState( () => initialComponent );

	useEffect( () => {
		if ( IconComponent ) {
			return undefined;
		}
		if ( ! lib || ! name ) {
			return undefined;
		}

		let cancelled = false;
		loadLibrary( lib )
			.then( ( mod ) => {
				if ( cancelled ) {
					return;
				}
				if ( mod && mod[ name ] ) {
					setIconComponent( () => mod[ name ] );
				}
			} )
			.catch( () => {
				/* swallow — `null` icon is the recovery path */
			} );

		return () => {
			cancelled = true;
		};
	}, [ lib, name, IconComponent ] );

	if ( ! IconComponent ) {
		return null;
	}

	return <IconComponent className={ className } style={ style } size={ size } />;
};

export default LazyIcon;
