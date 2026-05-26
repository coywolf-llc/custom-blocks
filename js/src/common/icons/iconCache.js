/**
 * Module-level cache of loaded icon libraries.
 *
 * Each library entry in `LIBRARIES` is a Promise-returning loader; on
 * first request we kick off the dynamic import (or resolve to the
 * eagerly-bundled BoxIcons module) and store the resolved module here so
 * subsequent calls are synchronous. Callers that need synchronous access
 * to an icon component check `getCachedLibrary( key )` first; if `null`
 * (not yet loaded), they call `loadLibrary( key )` and re-render once
 * its promise resolves.
 */

import { DEFAULT_LIBRARY, LIBRARIES } from './libraries';

/** @type {Record<string, Record<string, React.FC> | undefined>} */
const cache = {};

/** @type {Record<string, Promise<Record<string, React.FC>> | undefined>} */
const inflight = {};

/**
 * Returns the loaded module for a library, or `null` if it hasn't been
 * requested yet. Pure sync read; never triggers a network request.
 *
 * @param {string} libKey Library key (e.g. 'bi', 'fa6').
 * @return {Record<string, React.FC> | null}
 */
export const getCachedLibrary = ( libKey ) => cache[ libKey ] ?? null;

/**
 * Triggers (or joins) the load of a library and resolves to its
 * named-exports module. Idempotent — concurrent callers share a single
 * in-flight promise; subsequent callers get a resolved cache hit.
 *
 * @param {string} libKey Library key.
 * @return {Promise<Record<string, React.FC>>}
 */
export const loadLibrary = ( libKey ) => {
	if ( cache[ libKey ] ) {
		return Promise.resolve( cache[ libKey ] );
	}
	if ( inflight[ libKey ] ) {
		return inflight[ libKey ];
	}
	const lib = LIBRARIES[ libKey ];
	if ( ! lib ) {
		return Promise.reject( new Error( `Unknown icon library "${ libKey }"` ) );
	}
	inflight[ libKey ] = lib.load().then( ( mod ) => {
		cache[ libKey ] = mod;
		delete inflight[ libKey ];
		return mod;
	} ).catch( ( err ) => {
		delete inflight[ libKey ];
		throw err;
	} );
	return inflight[ libKey ];
};

/**
 * Parses an icon slug into its library + component-name parts.
 *
 * Current storage format: `{libKey}/{ComponentName}` — e.g.
 * `lu/LuSquareCode`, `bi/BiBox`, `fa6/Fa6Bell`, `hi2/HiHome`. The
 * component name is the literal react-icons named export, no case
 * conversion needed.
 *
 * Legacy fallbacks (route to BoxIcons specifically — the library the
 * legacy slug format originated in, NOT the current default):
 *  - `bi_box`, `bs_arrow_down_circle` — snake_case from the 1.0.10
 *    BoxIcons-only era. No `/` separator; convert snake_case →
 *    PascalCase and route to `bi`.
 *  - Anything else without a `/` resolves to BoxIcons too; if there's
 *    no matching export there, the caller's fallback (LuSquareCode)
 *    renders.
 *
 * @param {string} slug Stored icon slug.
 * @return {{ lib: string, name: string }}
 */
export const parseIconSlug = ( slug ) => {
	if ( typeof slug !== 'string' || slug === '' ) {
		return { lib: DEFAULT_LIBRARY, name: '' };
	}

	if ( slug.includes( '/' ) ) {
		const idx = slug.indexOf( '/' );
		return {
			lib:  slug.slice( 0, idx ),
			name: slug.slice( idx + 1 ),
		};
	}

	// Legacy snake_case → PascalCase. Always route to BoxIcons (`bi`)
	// because that's the library these slugs were originally written
	// against in the v1.0.10 era — not the current `DEFAULT_LIBRARY`,
	// which is `lu` (Lucide) and wouldn't have e.g. `BiBox`.
	const pascal = slug
		.split( '_' )
		.map( ( part ) => part.charAt( 0 ).toUpperCase() + part.slice( 1 ) )
		.join( '' );
	return { lib: 'bi', name: pascal };
};

/**
 * Serialise a library key + PascalCase component name back into the
 * canonical storage slug used everywhere else in the codebase.
 *
 * @param {string} libKey
 * @param {string} componentName
 * @return {string}
 */
export const formatIconSlug = ( libKey, componentName ) => `${ libKey }/${ componentName }`;
