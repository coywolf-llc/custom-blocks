/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Internal dependencies
 */
import { LazyIcon, getCachedLibrary, parseIconSlug } from '../icons';
import { CcbBlockDefault } from '../icons/builtinIcons';

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
 * When the slug resolves to nothing (empty input, a legacy pre-BoxIcons
 * name like `coywolf_custom_blocks` that no longer maps to a component,
 * or an unknown name in a loaded library), this returns the built-in
 * `CcbBlockDefault` glyph — the same wp-admin block-default icon
 * rendered in the sidebar nav. That keeps the picker preview and the
 * inserter from showing a blank slot whenever a stored value drifts
 * out of sync with the available icon set.
 *
 * Both the resolved-icon and the fallback branches return a React
 * component, so every existing callsite (`<Icon icon={
 * getIconComponent( … ) } />`, `registerBlockType` icon src, etc.)
 * keeps working without a code change at the call site.
 *
 * @param {string} iconSlug Stored icon slug. Canonical form is
 *                          `{libKey}/{ComponentName}` (e.g.
 *                          `bi/BiBox`, `fa6/Fa6Heart`). Legacy
 *                          snake_case BoxIcons-only slugs from v1.0.10
 *                          are accepted via `parseIconSlug`'s
 *                          fallback.
 * @return {React.FunctionComponent} A renderable icon component —
 *                                   never null, falls back to
 *                                   `CcbBlockDefault` on every miss.
 */
const getIconComponent = ( iconSlug ) => {
	if ( ! iconSlug || 'string' !== typeof iconSlug ) {
		return CcbBlockDefault;
	}

	const { lib, name } = parseIconSlug( iconSlug );
	if ( ! lib || ! name ) {
		return CcbBlockDefault;
	}

	const cached = getCachedLibrary( lib );
	if ( cached ) {
		// Library loaded — return the component synchronously, or the
		// built-in default for a misspelled / unknown icon name in
		// this library so the slot always renders something instead
		// of a blank box.
		return cached[ name ] || CcbBlockDefault;
	}

	// Library not loaded yet — return the lazy wrapper. The wrapper
	// component handles its own dynamic import + re-render on resolve,
	// and itself falls back to CcbBlockDefault while loading or on
	// failure (see LazyIcon for the in-flight render).
	const Wrapped = ( props ) => <LazyIcon slug={ iconSlug } { ...props } />;
	Wrapped.displayName = `LazyIcon(${ iconSlug })`;
	return Wrapped;
};

export default getIconComponent;
