/**
 * Registry of every icon library available via react-icons.
 *
 * The default library — BoxIcons — is imported eagerly so the icon picker
 * (and any block whose icon comes from BoxIcons) renders synchronously
 * with no flash. All 30 other libraries use a dynamic `import()` so each
 * one becomes its own webpack chunk and only downloads when the user
 * actually picks it. Eagerly bundling all 50,000+ icons would put 20+ MB
 * of admin JS on the page; this code-splits to ~50 KB-1 MB per library.
 *
 * The chunk-name magic comment (`webpackChunkName: "icons-XX"`) keeps the
 * generated files predictable so a future workflow can pre-cache or pre-
 * fetch a known subset.
 */

import * as bi from 'react-icons/bi';
import builtinIcons from './builtinIcons';

/**
 * @typedef {Object} IconLibrary
 * @property {string}                                    name Display label used in the picker dropdown.
 * @property {() => Promise<Record<string, React.FC>>}   load Resolves to the library's named-exports module.
 */

/** @type {Record<string, IconLibrary>} */
export const LIBRARIES = {
	// Plugin-shipped icons (currently just the block-default glyph that
	// matches the wp-admin nav). Bundled into the main entry so it's
	// always resolvable — used as the default for new blocks and the
	// fallback when a stored slug doesn't resolve in any other library.
	coywolf: { name: 'Coywolf Custom Blocks', load: () => Promise.resolve( builtinIcons ) },
	ai:  { name: 'Ant Design Icons',  load: () => import( /* webpackChunkName: "icons-ai"  */ 'react-icons/ai'  ) },
	bi:  { name: 'BoxIcons',          load: () => Promise.resolve( bi ) },
	bs:  { name: 'Bootstrap Icons',   load: () => import( /* webpackChunkName: "icons-bs"  */ 'react-icons/bs'  ) },
	cg:  { name: 'css.gg',            load: () => import( /* webpackChunkName: "icons-cg"  */ 'react-icons/cg'  ) },
	ci:  { name: 'Circum Icons',      load: () => import( /* webpackChunkName: "icons-ci"  */ 'react-icons/ci'  ) },
	di:  { name: 'Devicons',          load: () => import( /* webpackChunkName: "icons-di"  */ 'react-icons/di'  ) },
	fa:  { name: 'Font Awesome 5',    load: () => import( /* webpackChunkName: "icons-fa"  */ 'react-icons/fa'  ) },
	fa6: { name: 'Font Awesome 6',    load: () => import( /* webpackChunkName: "icons-fa6" */ 'react-icons/fa6' ) },
	fc:  { name: 'Flat Color Icons',  load: () => import( /* webpackChunkName: "icons-fc"  */ 'react-icons/fc'  ) },
	fi:  { name: 'Feather',           load: () => import( /* webpackChunkName: "icons-fi"  */ 'react-icons/fi'  ) },
	gi:  { name: 'Game Icons',        load: () => import( /* webpackChunkName: "icons-gi"  */ 'react-icons/gi'  ) },
	go:  { name: 'GitHub Octicons',   load: () => import( /* webpackChunkName: "icons-go"  */ 'react-icons/go'  ) },
	gr:  { name: 'Grommet Icons',     load: () => import( /* webpackChunkName: "icons-gr"  */ 'react-icons/gr'  ) },
	hi:  { name: 'Heroicons',         load: () => import( /* webpackChunkName: "icons-hi"  */ 'react-icons/hi'  ) },
	hi2: { name: 'Heroicons 2',       load: () => import( /* webpackChunkName: "icons-hi2" */ 'react-icons/hi2' ) },
	im:  { name: 'IcoMoon Free',      load: () => import( /* webpackChunkName: "icons-im"  */ 'react-icons/im'  ) },
	io:  { name: 'Ionicons 4',        load: () => import( /* webpackChunkName: "icons-io"  */ 'react-icons/io'  ) },
	io5: { name: 'Ionicons 5',        load: () => import( /* webpackChunkName: "icons-io5" */ 'react-icons/io5' ) },
	lia: { name: 'Line Awesome',      load: () => import( /* webpackChunkName: "icons-lia" */ 'react-icons/lia' ) },
	lu:  { name: 'Lucide',            load: () => import( /* webpackChunkName: "icons-lu"  */ 'react-icons/lu'  ) },
	md:  { name: 'Material Design',   load: () => import( /* webpackChunkName: "icons-md"  */ 'react-icons/md'  ) },
	pi:  { name: 'Phosphor',          load: () => import( /* webpackChunkName: "icons-pi"  */ 'react-icons/pi'  ) },
	ri:  { name: 'Remix Icons',       load: () => import( /* webpackChunkName: "icons-ri"  */ 'react-icons/ri'  ) },
	rx:  { name: 'Radix Icons',       load: () => import( /* webpackChunkName: "icons-rx"  */ 'react-icons/rx'  ) },
	si:  { name: 'Simple Icons',      load: () => import( /* webpackChunkName: "icons-si"  */ 'react-icons/si'  ) },
	sl:  { name: 'Simple Line Icons', load: () => import( /* webpackChunkName: "icons-sl"  */ 'react-icons/sl'  ) },
	tb:  { name: 'Tabler Icons',      load: () => import( /* webpackChunkName: "icons-tb"  */ 'react-icons/tb'  ) },
	tfi: { name: 'Themify Icons',     load: () => import( /* webpackChunkName: "icons-tfi" */ 'react-icons/tfi' ) },
	ti:  { name: 'Typicons',          load: () => import( /* webpackChunkName: "icons-ti"  */ 'react-icons/ti'  ) },
	vsc: { name: 'VS Code Icons',     load: () => import( /* webpackChunkName: "icons-vsc" */ 'react-icons/vsc' ) },
	wi:  { name: 'Weather Icons',     load: () => import( /* webpackChunkName: "icons-wi"  */ 'react-icons/wi'  ) },
};

/** Library key used for the picker's initial state and as the migration target for legacy slugs. */
export const DEFAULT_LIBRARY = 'bi';

/** Picker's persisted preference for the user's last-selected library. */
export const LIBRARY_STORAGE_KEY = 'coywolf-custom-blocks/icon-library';

/** List of `[key, displayName]` tuples sorted alphabetically by display name, for stable dropdown ordering. */
export const LIBRARY_OPTIONS = Object.entries( LIBRARIES )
	.map( ( [ key, lib ] ) => [ key, lib.name ] )
	.sort( ( a, b ) => a[ 1 ].localeCompare( b[ 1 ] ) );
