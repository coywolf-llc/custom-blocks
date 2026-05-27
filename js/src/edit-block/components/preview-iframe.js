/* global ccbEditor */

/**
 * External dependencies
 */
import * as React from 'react';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Renders a Coywolf block's server-side HTML inside an isolated iframe
 * that has the active theme's editor stylesheets injected. Replaces the
 * inline `<ServerSideRender>` we used in 1.0.35 — the iframe boundary
 * means theme CSS can't leak into the wp-admin chrome of the block
 * builder page, so unscoped editor.css rules are safe.
 *
 * On mount (and whenever block / attributes / renderMode change) we
 * `apiFetch` the same `/wp/v2/block-renderer` endpoint `<ServerSideRender>`
 * would call, then assemble an HTML document for the iframe's `srcdoc`:
 *
 *   <!DOCTYPE html>
 *   <html>
 *     <head>
 *       <base target="_parent">
 *       <link rel="stylesheet" href="…editor-style.css">   (one per URL)
 *       <style>…wp_get_global_stylesheet()…</style>          (inline)
 *     </head>
 *     <body class="editor-styles-wrapper">
 *       <div class="wp-block">… rendered HTML …</div>
 *     </body>
 *   </html>
 *
 * The iframe auto-sizes to its content via a ResizeObserver on the body
 * (same-origin `srcdoc` content is reachable from the parent frame).
 *
 * @param {Object}        props
 * @param {string}        props.blockName    Full block name including namespace (e.g. `coywolf-custom-blocks/review`).
 * @param {Object}        [props.attributes] Attributes to send to the renderer.
 * @param {Object}        [props.urlQueryArgs] Extra query args to append to the renderer URL (e.g. `{ ccb_render_mode: 'view' }`).
 * @param {string}        [props.className]
 * @return {React.ReactElement}
 */
const PreviewIframe = ( { blockName, attributes = {}, urlQueryArgs = {}, className = '' } ) => {
	const iframeRef = useRef( null );
	const [ html, setHtml ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ height, setHeight ] = useState( 0 );

	const previewStyles = ( typeof ccbEditor !== 'undefined' && ccbEditor && ccbEditor.previewStyles )
		? ccbEditor.previewStyles
		: { urls: [], inline: '' };

	// Re-fetch when block name, attributes, or render mode change.
	const attributesKey = JSON.stringify( attributes );
	const queryKey = JSON.stringify( urlQueryArgs );

	useEffect( () => {
		let cancelled = false;

		// Debounce the fetch by 500 ms so typing in a `previewAttributes`
		// field doesn't fire one render per keystroke. Mirrors the
		// behaviour of `@wordpress/server-side-render`, which we used
		// before the iframe refactor (PR #45).
		const timer = window.setTimeout( () => {
			setError( null );
			setHtml( null );

			// Don't encode `blockName` — its `namespace/slug` form contains a
			// literal `/` that's part of the WP REST route pattern. Encoding
			// it to `%2F` makes the router miss the route and return
			// "No route was found matching the URL and request method".
			// Both halves of the name already match `[a-z0-9-]+` (validated
			// by WP and on insert), so passing them raw is safe.
			const path = '/wp/v2/block-renderer/' + blockName + buildQueryString( {
				context: 'edit',
				...urlQueryArgs,
			} );

			apiFetch( {
				path,
				method: 'POST',
				data: { attributes },
			} ).then( ( response ) => {
				if ( cancelled ) {
					return;
				}
				const rendered = response && 'string' === typeof response.rendered ? response.rendered : '';
				setHtml( rendered );
			} ).catch( ( err ) => {
				if ( cancelled ) {
					return;
				}
				setError( err && err.message ? err.message : __( 'Unknown error.', 'coywolf-custom-blocks' ) );
			} );
		}, 500 );

		return () => {
			cancelled = true;
			window.clearTimeout( timer );
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ blockName, attributesKey, queryKey ] );

	// Auto-size the iframe whenever its body height changes.
	useEffect( () => {
		const frame = iframeRef.current;
		if ( ! frame || null === html ) {
			return undefined;
		}

		const measure = () => {
			const doc = frame.contentDocument;
			if ( ! doc || ! doc.body ) {
				return;
			}
			// scrollHeight rounds down; +2 keeps a small buffer for
			// sub-pixel content and avoids a perpetual scrollbar.
			const newHeight = doc.body.scrollHeight + 2;
			setHeight( ( prev ) => ( Math.abs( prev - newHeight ) > 1 ? newHeight : prev ) );
		};

		const onLoad = () => {
			measure();
			const doc = frame.contentDocument;
			if ( ! doc || ! doc.body || 'undefined' === typeof window.ResizeObserver ) {
				return;
			}
			const observer = new window.ResizeObserver( measure );
			observer.observe( doc.body );
			// Stash on the frame so the cleanup can disconnect.
			frame._ccbObserver = observer;
		};

		frame.addEventListener( 'load', onLoad );
		return () => {
			frame.removeEventListener( 'load', onLoad );
			if ( frame._ccbObserver ) {
				frame._ccbObserver.disconnect();
				delete frame._ccbObserver;
			}
		};
	}, [ html ] );

	if ( error ) {
		return (
			<div className={ className } style={ { padding: '1em', color: '#b32d2e' } }>
				{ sprintf(
					/* translators: %s: error message */
					__( 'Error loading block: %s', 'coywolf-custom-blocks' ),
					error
				) }
			</div>
		);
	}

	if ( null === html ) {
		return (
			<div className={ className } style={ { padding: '1em' } }>
				<Spinner />
			</div>
		);
	}

	const srcDoc = buildSrcDoc( html, previewStyles );

	return (
		<iframe
			ref={ iframeRef }
			title={ __( 'Block preview', 'coywolf-custom-blocks' ) }
			className={ className }
			srcDoc={ srcDoc }
			style={ {
				width: '100%',
				border: 'none',
				display: 'block',
				height: height ? `${ height }px` : '120px',
			} }
		/>
	);
};

/**
 * Assemble the full HTML document the iframe loads. Kept out of the
 * component body so the work stays out of every render.
 *
 * @param {string} renderedHtml The server-rendered block HTML.
 * @param {{urls: string[], inline: string}} previewStyles
 * @return {string}
 */
const buildSrcDoc = ( renderedHtml, previewStyles ) => {
	const styleLinks = ( previewStyles.urls || [] )
		.map( ( url ) => `<link rel="stylesheet" href="${ escapeAttr( url ) }">` )
		.join( '' );

	// Escape any literal `</style` inside the inline payload before
	// interpolating into the `<style>…</style>` block — without this an
	// attacker who can write to `wp_get_global_stylesheet()` (any user
	// with `edit_theme_options` on multi-author sites) could break out
	// of the style element with `</style><script>…` and execute JS in
	// the same-origin iframe. M1 finding from the 1.0.42 security
	// audit. Case-insensitive because HTML parses both `</STYLE` and
	// `</style` as end tags.
	const inlineStyle = previewStyles.inline
		? `<style>${ escapeStyleEnders( previewStyles.inline ) }</style>`
		: '';

	// `<base target="_parent">` keeps any anchor clicks from trying to
	// navigate inside the iframe sandbox.
	return [
		'<!DOCTYPE html>',
		'<html>',
		'<head>',
		'<meta charset="utf-8">',
		'<base target="_parent">',
		'<style>html,body{margin:0;padding:0;background:transparent}body{padding:16px}</style>',
		styleLinks,
		inlineStyle,
		'</head>',
		'<body class="editor-styles-wrapper">',
		renderedHtml,
		'</body>',
		'</html>',
	].join( '' );
};

/**
 * Build a URL query-string fragment (including the leading `?`) from a
 * plain object. Kept local so we don't have to pull in @wordpress/url.
 *
 * @param {Object} params
 * @return {string}
 */
const buildQueryString = ( params ) => {
	const pairs = [];
	for ( const key in params ) {
		if ( ! Object.prototype.hasOwnProperty.call( params, key ) ) {
			continue;
		}
		const value = params[ key ];
		if ( null === value || 'undefined' === typeof value ) {
			continue;
		}
		pairs.push( encodeURIComponent( key ) + '=' + encodeURIComponent( String( value ) ) );
	}
	return pairs.length ? '?' + pairs.join( '&' ) : '';
};

/**
 * Minimal HTML-attribute escape for the `<link href>` interpolation.
 *
 * @param {string} value
 * @return {string}
 */
const escapeAttr = ( value ) => String( value )
	.replace( /&/g, '&amp;' )
	.replace( /"/g, '&quot;' )
	.replace( /</g, '&lt;' )
	.replace( />/g, '&gt;' );

/**
 * Defang any literal `</style` (case-insensitive) inside a string so it
 * can't close the surrounding `<style>` element when interpolated. The
 * backslash splits the token across the HTML parser's view of the
 * stylesheet without changing the CSS itself — `</st\yle` is still a
 * syntactically empty rule that CSS ignores.
 *
 * @param {string} value
 * @return {string}
 */
const escapeStyleEnders = ( value ) => String( value ).replace( /<\/style/gi, '<\\/style' );

export default PreviewIframe;
