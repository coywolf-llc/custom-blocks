/**
 * Block icon library entry point.
 *
 * Surfaces every react-icons library through one set of helpers. The
 * picker on the block editor reads from `LIBRARIES` / `LIBRARY_OPTIONS`
 * to populate its dropdown, and uses `loadLibrary( key )` to fetch the
 * selected library's named-exports module on demand. Components that
 * just need to render a single stored icon use `<LazyIcon slug={ … } />`,
 * which handles cache lookup + dynamic import + re-render transparently.
 *
 * @see ./libraries.js   Registry of every library with display name + dynamic loader.
 * @see ./iconCache.js   Module-level cache + slug parsing/formatting helpers.
 * @see ./LazyIcon.js    Lazy-render React component for a single stored slug.
 */

export { default as LazyIcon } from './LazyIcon';
export { default as DefaultIcon } from './DefaultIcon';
export {
	LIBRARIES,
	LIBRARY_OPTIONS,
	LIBRARY_STORAGE_KEY,
	DEFAULT_LIBRARY,
} from './libraries';
export {
	formatIconSlug,
	getCachedLibrary,
	loadLibrary,
	parseIconSlug,
} from './iconCache';
