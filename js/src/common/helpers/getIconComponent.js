/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies
 */
import { LazyIcon, getCachedLibrary, parseIconSlug } from '../icons';

/**
 * Resolves a stored icon slug to a React component suitable for passing
 * as the `icon` prop on `<Icon>` or as the `src` of a Gutenberg block's
 * icon registration.
 *
 * If the slug's library is already cached, the direct exported component
 * is returned synchronously so the caller mounts the real icon with no
 * extra wrapper. If the library hasn't been loaded yet, a thin
 * `<LazyIcon slug={ … } />` wrapper is returned — it kicks off the
 * dynamic import on mount and re-renders into the real icon when the
 * library lands.
 *
 * Both branches return a React component, so every existing callsite
 * (`<Icon icon={ getIconComponent( … ) } />`, `registerBlockType` icon
 * src, etc.) keeps working without a code change at the call site.
 *
 * @param {string} iconSlug Stored icon slug. Canonical form is
 *                          `{libKey}/{ComponentName}`; legacy
 *                          snake_case BoxIcons-only slugs from v1.0.10
 *                          are still accepted via `parseIconSlug`'s
 *                          fallback.
 * @return {React.FunctionComponent|null} The icon component, or `null`
 *                                        when the slug is malformed
 *                                        (which lets WP's `<Icon>` skip
 *                                        rendering a slot).
 */
const getIconComponent = ( iconSlug ) => {
	if ( ! iconSlug || 'string' !== typeof iconSlug ) {
		return null;
	}

	const { lib, name } = parseIconSlug( iconSlug );
	if ( ! lib || ! name ) {
		return null;
	}

	const cached = getCachedLibrary( lib );
	if ( cached ) {
		// Library loaded — return the component synchronously, or null
		// for a misspelled / unknown icon name in this library (so WP's
		// <Icon /> falls back to its empty-slot rendering).
		return cached[ name ] || null;
	}

	// Library not loaded yet — return the lazy wrapper. The wrapper
	// component handles its own dynamic import + re-render on resolve.
	const Wrapped = ( props ) => <LazyIcon slug={ iconSlug } { ...props } />;
	Wrapped.displayName = `LazyIcon(${ iconSlug })`;
	return Wrapped;
};

export default getIconComponent;
