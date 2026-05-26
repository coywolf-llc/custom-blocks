/**
 * Block icon library.
 *
 * Re-exports every BoxIcon from `react-icons/bi` so the icon picker on the
 * block editor screen surfaces the full BoxIcons set (≈1,600 icons). The
 * picker iterates `Object.keys()` of this module and renders each entry,
 * so anything exported here becomes a selectable block icon.
 *
 * Persistence format: the block's `icon` field stores the snake_cased
 * version of the exported name — e.g. `BiBox` is saved as `bi_box`,
 * `BiUserCircle` as `bi_user_circle`. `getIconComponent()` round-trips
 * by snake-case → PascalCase → keyed lookup.
 *
 * @see https://react-icons.github.io/react-icons/icons/bi/
 */
export * from 'react-icons/bi';
