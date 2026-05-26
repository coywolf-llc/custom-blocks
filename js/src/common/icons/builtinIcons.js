/**
 * External dependencies
 */
import * as React from 'react';

/**
 * Plugin-shipped default block icon.
 *
 * Exact replica of the WordPress `dashicons-block-default` glyph — the
 * outline frame with two placeholder dots and a content bar — that the
 * wp-admin sidebar uses for the **Custom Blocks** menu item. Keeping the
 * two surfaces visually identical means a newly-created block with no
 * icon picked, or an existing block whose stored slug no longer
 * resolves (e.g. a legacy `coywolf_custom_blocks` value from before the
 * react-icons swap), still renders with the same glyph the user sees
 * in the nav.
 *
 * Exposed as the sole entry in a synthetic `coywolf` library so it
 * shows up in the picker's library dropdown like the react-icons sets,
 * and uses the same `{libKey}/{ComponentName}` slug format.
 *
 * @param {Object} [props]
 * @return {React.ReactElement}
 */
const CcbBlockDefault = ( props ) => (
	<svg
		viewBox="0 0 20 20"
		xmlns="http://www.w3.org/2000/svg"
		fill="currentColor"
		aria-hidden="true"
		focusable="false"
		{ ...props }
	>
		<path d="M0 0v20h20V0H0zm18.5 18.5h-17v-17h17v17zM4 5.5C4 4.673 4.673 4 5.5 4S7 4.673 7 5.5 6.327 7 5.5 7 4 6.327 4 5.5zM12 8c-1.105 0-2-.895-2-2s.895-2 2-2 2 .895 2 2-.895 2-2 2zM3 11h14v6H3v-6z" />
	</svg>
);
CcbBlockDefault.displayName = 'CcbBlockDefault';

/**
 * Synthetic library module exposed to the picker and the icon cache.
 * Shape mirrors react-icons modules (object of named-export React
 * components) so callers don't need a special case.
 */
const builtinIcons = {
	CcbBlockDefault,
};

export default builtinIcons;
export { CcbBlockDefault };
