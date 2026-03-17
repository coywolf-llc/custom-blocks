/**
 * External dependencies
 */
import { getDocument, queries } from 'pptr-testing-library';

/**
 * WordPress dependencies
 */
import {
	createURL,
} from '@wordpress/e2e-test-utils';

/**
 * Get the editor canvas iframe if it exists (Block API v3).
 * Falls back to the main page if no iframe is found (older WP versions).
 */
const getEditorCanvas = async () => {
	const iframeSelector = 'iframe[name="editor-canvas"]';
	try {
		await page.waitForSelector( iframeSelector, { timeout: 3000 } );
		const iframe = await page.$( iframeSelector );
		if ( iframe ) {
			return await iframe.contentFrame();
		}
	} catch ( error ) {
		// No iframe found, use main page (WP 6.x or earlier)
	}
	return page;
};

/**
 * Get document from either a Page or Frame object.
 * The pptr-testing-library's getDocument() only works with Page objects,
 * so we need to handle Frame objects differently for the iframe editor.
 *
 * @param {Object} canvas - Either a Puppeteer Page or Frame object.
 * @return {Promise<Object>} Document element handle.
 */
const getDocumentFromCanvas = async ( canvas ) => {
	// If it's a Page object, use the standard getDocument
	if ( canvas === page ) {
		return getDocument( canvas );
	}
	// If it's a Frame object, get the document directly
	const documentHandle = await canvas.evaluateHandle( 'document' );
	return documentHandle.asElement();
};

/**
 * Test environment configuration (username/password, base URL).
 *
 * We use these directly rather than relying on the older `loginUser`
 * helper, whose stricter navigation heuristics (`networkidle0`) can
 * time out against newer WordPress versions. This is a temporary fix
 * to be removed when we migrate to Node > 14, newer WP packages, and
 * the @wordpress/e2e-test-utils-playwright package instead of
 * the @wordpress/e2e-test-utils one, which is deprecated.
 */
import {
	WP_USERNAME,
	WP_PASSWORD,
} from '@wordpress/e2e-test-utils/build/shared/config';

/**
 * Perform a simple login if the current request ends up on the login screen.
 */
async function loginIfNecessary() {
	// If we're not on wp-login.php, assume we're already logged in.
	if ( ! page.url().includes( 'wp-login.php' ) ) {
		return;
	}

	await page.focus( '#user_login' );
	await page.keyboard.down( 'Meta' );
	await page.keyboard.press( 'a' );
	await page.keyboard.up( 'Meta' );
	await page.type( '#user_login', WP_USERNAME );

	await page.focus( '#user_pass' );
	await page.keyboard.down( 'Meta' );
	await page.keyboard.press( 'a' );
	await page.keyboard.up( 'Meta' );
	await page.type( '#user_pass', WP_PASSWORD );

	await Promise.all( [
		page.click( '#wp-submit' ),
		page.waitForNavigation( {
			waitUntil: 'domcontentloaded',
		} ),
	] );
}

/**
 * Minimal, spec-local version of `createNewPost` which avoids the older
 * `visitAdminPage` / `loginUser` helpers. This is deliberately narrow:
 * it only supports opening a fresh post editor as the admin user.
 */
async function openNewPostEditor() {
	const editorUrl = createURL(
		'wp-admin/post-new.php',
		'post_type=post'
	);

	await page.goto( editorUrl, {
		waitUntil: 'domcontentloaded',
	} );

	// If we were redirected to the login screen, perform a minimal login
	// flow and then try again.
	if ( page.url().includes( 'wp-login.php' ) ) {
		await loginIfNecessary();

		await page.goto( editorUrl, {
			waitUntil: 'domcontentloaded',
		} );
	}

	await page.waitForSelector( '.edit-post-layout' );
}

describe( 'ApiBlocks', () => {
	it( 'displays PHP-registered blocks', async () => {
		const { findAllByText, findByLabelText } = queries;

		await openNewPostEditor();

		// Get the editor canvas (iframe for Block API v3, or main page for older versions)
		const editorCanvas = await getEditorCanvas();
		const $canvasDocument = await getDocumentFromCanvas( editorCanvas );

		/**
		 * Inserts a block by its human-readable name using the global block
		 * inserter. This is a local helper which avoids the older
		 * `insertBlock` implementation and its stricter assumptions about the
		 * inserter markup, which can lead to detached-node errors in newer
		 * WordPress versions.
		 *
		 * @param {string} blockName The block name as shown in the inserter.
		 */
		const insertBlockByName = async ( blockName ) => {
			// Ensure the global inserter is open.
			if (
				! ( await page.$(
					'.block-editor-inserter__block-list'
				) )
			) {
				const inserterToggle =
					( await page.$(
						'button[aria-label="Block Inserter"]'
					) ) ||
					( await page.$(
						'button[aria-label="Add block"]'
					) ) ||
					( await page.$(
						'button[aria-label="Toggle block inserter"]'
					) );

				if ( inserterToggle ) {
					await inserterToggle.click();
				}
			}

			await page.waitForSelector(
				'.block-editor-inserter__block-list',
				{ visible: true }
			);

			// Run the search and click entirely in the page context to avoid
			// holding onto stale element handles which can be detached when
			// the inserter rerenders.
			await page.evaluate( ( name ) => {
				const options = Array.from(
					document.querySelectorAll(
						'[role="option"]'
					)
				);

				const match = options.find( ( option ) => {
					const text =
						option.textContent ||
						option.innerText ||
						'';

					return text
						.toLowerCase()
						.includes( name.toLowerCase() );
				} );

				if ( match ) {
					match.click();
				} else {
					throw new Error(
						`Block "${ name }" not found in inserter`
					);
				}
			}, blockName );
		};

		await insertBlockByName( 'Test Url' );
		await findAllByText( $canvasDocument, 'Test Url' );
		await findByLabelText( $canvasDocument, 'Url Here' );

		await insertBlockByName( 'Test Text' );
		await findAllByText( $canvasDocument, 'Test Text' );
		await findByLabelText( $canvasDocument, 'Enter some text here' );
	} );
} );
