/**
 * The plugin's canonical default block glyph: a local copy of Lucide's
 * `square-code` icon (path data from Lucide, ISC license), rendered the
 * same way react-icons renders it.
 *
 * This is the ONE icon that ships statically in the entry bundles. It
 * exists so the fallback never depends on `react-icons/lu` — a static
 * import from that module would anchor the entire Lucide library into
 * both admin entries and defeat the per-library lazy chunks (webpack
 * folds a module into the parent chunk whenever it is both statically
 * and dynamically imported, and the dynamic namespace import marks every
 * export as used, so nothing tree-shakes).
 *
 * Prop shape mirrors react-icons' IconBase closely enough for every
 * call site: `size` maps to width/height (default `1em`), everything
 * else spreads onto the `<svg>`.
 *
 * @param {Object} props      Component props.
 * @param {string} props.size Width/height, defaults to `1em`.
 * @return {React.ReactElement} The glyph.
 */
const DefaultIcon = ( { size = '1em', ...rest } ) => (
	<svg
		viewBox="0 0 24 24"
		fill="none"
		stroke="currentColor"
		strokeWidth="2"
		strokeLinecap="round"
		strokeLinejoin="round"
		width={ size }
		height={ size }
		xmlns="http://www.w3.org/2000/svg"
		aria-hidden="true"
		focusable="false"
		{ ...rest }
	>
		<path d="M10 9.5 8 12l2 2.5" />
		<path d="m14 9.5 2 2.5-2 2.5" />
		<rect width="18" height="18" x="3" y="3" rx="2" />
	</svg>
);

export default DefaultIcon;
