/**
 * Import-from-Genesis admin page (Coywolf → Import from Genesis):
 * progressive enhancement for the import and rewrite-only forms.
 *
 * Static admin script enqueued only on that page by
 * Admin\ImportFromGenesis::enqueue_assets() — raw <script> output is
 * not allowed on WordPress.org. All configuration (REST URLs, nonce,
 * return URL) is read from data-* attributes on the forms, so the file
 * needs no localisation payload. Each IIFE no-ops when its form isn't
 * in the DOM or fetch() is unavailable, leaving the regular
 * admin-post.php POST flow as the noscript fallback.
 */

/* Import form: per-block progress driver. */
( function () {
	// Header checkbox: tick/untick every row.
	var toggle = document.getElementById( 'ccb-import-toggle-all' );
	if ( toggle ) {
		toggle.addEventListener( 'change', function () {
			var rows = document.querySelectorAll( '.ccb-import-row' );
			for ( var i = 0; i < rows.length; i++ ) {
				rows[ i ].checked = toggle.checked;
			}
		} );
	}

	// Progressive enhancement: when JS is available, intercept the
	// import form submit, call the REST endpoints one block at a
	// time, and drive an inline progress bar instead of a single
	// opaque page POST. Falls back to the regular POST when JS is
	// disabled or fetch() is missing.
	var form = document.getElementById( 'ccb-import-form' );
	if ( ! form || typeof window.fetch !== 'function' ) {
		return;
	}

	var progressUi = document.getElementById( 'ccb-import-progress' );
	var bar        = document.getElementById( 'ccb-import-progress-bar' );
	var status     = document.getElementById( 'ccb-import-progress-status' );
	var heading    = document.getElementById( 'ccb-import-progress-heading' );
	var log        = document.getElementById( 'ccb-import-progress-log' );

	var restBlockUrl   = form.getAttribute( 'data-rest-block-url' );
	var restRewriteUrl = form.getAttribute( 'data-rest-rewrite-url' );
	var restNonce      = form.getAttribute( 'data-rest-nonce' );
	var pageUrl        = form.getAttribute( 'data-page-url' );

	var appendLog = function ( message, type ) {
		var row = document.createElement( 'div' );
		row.textContent = message;
		if ( 'error' === type ) {
			row.style.color = '#b32d2e';
		} else if ( 'skipped' === type ) {
			row.style.color = '#a06d00';
		}
		log.appendChild( row );
		log.scrollTop = log.scrollHeight;
	};

	var setProgress = function ( done, total, label ) {
		var pct = total > 0 ? Math.round( ( done / total ) * 100 ) : 0;
		bar.value = pct;
		bar.max = 100;
		status.textContent = label;
	};

	form.addEventListener( 'submit', function ( event ) {
		var checked = form.querySelectorAll( 'input[name="block_ids[]"]:checked' );
		if ( 0 === checked.length ) {
			// Let the noscript POST flow handle the empty-state notice.
			return;
		}

		event.preventDefault();

		var ids = [];
		for ( var i = 0; i < checked.length; i++ ) {
			ids.push( parseInt( checked[ i ].value, 10 ) );
		}
		var rewriteEl   = form.querySelector( '[name="rewrite_post_content"]' );
		var wantRewrite = !! ( rewriteEl && rewriteEl.checked );

		// Disable the form + reveal the progress UI.
		var submitBtn = form.querySelector( 'button[type="submit"]' );
		if ( submitBtn ) { submitBtn.disabled = true; }
		form.style.opacity = '0.5';
		form.style.pointerEvents = 'none';
		progressUi.style.display = '';
		log.innerHTML = '';
		setProgress( 0, ids.length, 'Starting…' );

		// Run imports sequentially. Parallel would be faster but
		// the progress bar would jump around, and the underlying
		// SQL inserts are cheap enough that serial keeps the
		// per-block animation honest.
		var imported = [], skipped = [], errors = [], slugs = [];

		var importNext = function ( index ) {
			if ( index >= ids.length ) {
				return Promise.resolve();
			}
			setProgress(
				index,
				ids.length,
				'Importing block ' + ( index + 1 ) + ' of ' + ids.length + '…'
			);
			return fetch( restBlockUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   restNonce
				},
				body: JSON.stringify( { post_id: ids[ index ] } )
			} ).then( function ( res ) {
				return res.json();
			} ).then( function ( data ) {
				if ( ! data ) {
					errors.push( 'Empty response from server' );
					appendLog( 'Empty response from server', 'error' );
				} else if ( 'imported' === data.status ) {
					imported.push( data.title );
					if ( data.slug ) { slugs.push( data.slug ); }
					appendLog( '✓ Imported "' + data.title + '"' );
				} else if ( 'skipped' === data.status ) {
					skipped.push( data.title );
					if ( data.slug ) { slugs.push( data.slug ); }
					appendLog( '↷ Skipped "' + data.title + '" (already imported)', 'skipped' );
				} else {
					errors.push( ( data.title || '#' + ids[ index ] ) + ' — ' + ( data.error || 'unknown error' ) );
					appendLog( '✗ ' + ( data.title || '#' + ids[ index ] ) + ': ' + ( data.error || 'unknown error' ), 'error' );
				}
			} ).catch( function ( err ) {
				errors.push( 'Block #' + ids[ index ] + ' — ' + err.message );
				appendLog( '✗ Block #' + ids[ index ] + ': ' + err.message, 'error' );
			} ).then( function () {
				return importNext( index + 1 );
			} );
		};

		importNext( 0 ).then( function () {
			if ( ! wantRewrite || 0 === slugs.length ) {
				return null;
			}
			setProgress( ids.length, ids.length, 'Rewriting post content site-wide…' );
			appendLog( '— Rewriting block names in post content…' );
			return fetch( restRewriteUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   restNonce
				},
				body: JSON.stringify( { slugs: slugs } )
			} ).then( function ( res ) {
				return res.json();
			} ).then( function ( data ) {
				var count = data && typeof data.updated === 'number' ? data.updated : 0;
				appendLog( '✓ Rewrote block names in ' + count + ' post(s)' );
				return count;
			} );
		} ).then( function ( rewriteCount ) {
			setProgress( ids.length, ids.length, 'Done.' );
			heading.textContent = 'Import complete';
			appendLog(
				'— Summary: ' +
				imported.length + ' imported, ' +
				skipped.length + ' skipped, ' +
				errors.length + ' error(s).'
			);

			// Bounce back to the page with each title as its
			// own array entry — managed-host WAFs (Rocket,
			// Cloudflare, ModSecurity) sometimes strip
			// URL-encoded `|` characters on suspicion of
			// SQL injection, which collapsed all titles
			// into one un-separated blob and made the
			// count read as "1 block." Array-form params
			// (`imported[]=A&imported[]=B`) sidestep that.
			var encodeArrayParam = function ( key, values ) {
				if ( ! values || ! values.length ) {
					return '';
				}
				var encodedKey = encodeURIComponent( key ) + '%5B%5D=';
				return values.map( function ( v ) {
					return '&' + encodedKey + encodeURIComponent( v );
				} ).join( '' );
			};
			var query = '&result=imported' +
				encodeArrayParam( 'imported', imported ) +
				encodeArrayParam( 'skipped',  skipped ) +
				encodeArrayParam( 'errors',   errors );
			if ( wantRewrite ) {
				query += '&rewrite_count=' + encodeURIComponent( rewriteCount || 0 );
			}
			window.setTimeout( function () {
				window.location.href = pageUrl + query;
			}, 1200 );
		} );
	} );
} )();

/* Rewrite-only form: batched site-wide rename driver. */
( function () {
	var form = document.getElementById( 'ccb-rewrite-form' );
	if ( ! form || typeof window.fetch !== 'function' ) {
		return;
	}

	var progressUi = document.getElementById( 'ccb-rewrite-progress' );
	var bar        = document.getElementById( 'ccb-rewrite-progress-bar' );
	var status     = document.getElementById( 'ccb-rewrite-progress-status' );
	var heading    = document.getElementById( 'ccb-rewrite-progress-heading' );

	var pendingUrl = form.getAttribute( 'data-rest-pending-url' );
	var batchUrl   = form.getAttribute( 'data-rest-batch-url' );
	var restNonce  = form.getAttribute( 'data-rest-nonce' );
	var pageUrl    = form.getAttribute( 'data-page-url' );

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();

		var submitBtn = form.querySelector( 'button[type="submit"]' );
		if ( submitBtn ) { submitBtn.disabled = true; }
		form.style.opacity = '0.5';
		form.style.pointerEvents = 'none';
		progressUi.style.display = '';
		bar.value = 0;
		bar.max = 100;
		status.textContent = 'Counting candidate posts…';

		var totalProcessed = 0;
		var totalPending   = 0;

		var setProgress = function ( examinedSoFar, label ) {
			var pct = totalPending > 0
				? Math.min( 100, Math.round( ( examinedSoFar / totalPending ) * 100 ) )
				: 0;
			bar.value = pct;
			status.textContent = label;
		};

		var runBatch = function ( after, examinedSoFar ) {
			return fetch( batchUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   restNonce
				},
				body: JSON.stringify( { after: after } )
			} ).then( function ( res ) {
				return res.json();
			} ).then( function ( data ) {
				totalProcessed += ( data.processed || 0 );
				examinedSoFar += ( data.examined || 0 );
				setProgress(
					examinedSoFar,
					'Scanned ' + examinedSoFar + ' of ~' + totalPending + ' posts (' + totalProcessed + ' rewritten)…'
				);
				if ( null === data.next_after || 'undefined' === typeof data.next_after ) {
					return;
				}
				return runBatch( data.next_after, examinedSoFar );
			} );
		};

		fetch( pendingUrl, {
			method: 'GET',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': restNonce }
		} ).then( function ( res ) {
			return res.json();
		} ).then( function ( data ) {
			totalPending = ( data && data.count ) || 0;
			if ( 0 === totalPending ) {
				heading.textContent = 'Nothing to rewrite';
				status.textContent = 'No posts contained wp:genesis-custom-blocks/ markers.';
				bar.value = 100;
				window.setTimeout( function () {
					window.location.href = pageUrl + '&result=rewrite_only&rewrite_count=0';
				}, 1200 );
				return;
			}
			setProgress( 0, 'Scanning ' + totalPending + ' candidate post(s)…' );
			return runBatch( 0, 0 );
		} ).then( function () {
			if ( 0 === totalPending ) {
				return;
			}
			bar.value = 100;
			heading.textContent = 'Rewrite complete';
			status.textContent = 'Rewrote block names in ' + totalProcessed + ' post(s).';
			window.setTimeout( function () {
				window.location.href = pageUrl + '&result=rewrite_only&rewrite_count=' + encodeURIComponent( totalProcessed );
			}, 1200 );
		} ).catch( function ( err ) {
			heading.textContent = 'Rewrite failed';
			status.textContent = err && err.message ? err.message : 'Unknown error.';
			if ( submitBtn ) { submitBtn.disabled = false; }
			form.style.opacity = '';
			form.style.pointerEvents = '';
		} );
	} );
} )();
