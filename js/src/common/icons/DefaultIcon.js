/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Standalone copy of Lucide's `LuSquareCode` glyph as a plain SVG
 * component.
 *
 * The icon registry needs a synchronously-available fallback that
 * renders while a dynamically-imported react-icons library is in flight
 * (or when a stored slug doesn't resolve). Importing `LuSquareCode` by
 * name from `react-icons/lu` looks tree-shakeable, but in practice the
 * static import chains the entire Lucide module (~5,000 icons, hundreds
 * of KiB) into the main bundle — and once that module is in the main
 * bundle, webpack can no longer split it into the lazy `icons-lu` chunk.
 * Inlining the SVG breaks that chain: the fallback weighs a few hundred
 * bytes, and the full Lucide library only ships when the picker actually
 * needs it.
 *
 * The path data and stroke attributes match the upstream icon byte-for-
 * byte (Lucide 0.x — last copied 2026-05). The icon's behaviour in the
 * UI (sizing via the `size` prop, currentColor stroke) mirrors the
 * react-icons component so call sites don't need a branch.
 *
 * @param {Object} props
 * @param {string|number} [props.size]
 * @param {string} [props.className]
 * @param {Object} [props.style]
 * @return {React.ReactElement}
 */
const DefaultIcon = ( { size, className, style, ...rest } ) => {
	const computedSize = size || '1em';
	return (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
			strokeLinejoin="round"
			width={ computedSize }
			height={ computedSize }
			className={ className }
			style={ style }
			{ ...rest }
		>
			<path d="M10 9.5 8 12l2 2.5" />
			<path d="m14 9.5 2 2.5-2 2.5" />
			<rect width="18" height="18" x="3" y="3" rx="2" />
		</svg>
	);
};

export default DefaultIcon;
