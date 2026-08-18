( function () {
	'use strict';

	document.querySelectorAll( '.fcsa-import-widget' ).forEach( function ( widget ) {
		var toggle    = widget.querySelector( '.fcsa-import-toggle' );
		var popover   = widget.querySelector( '.fcsa-import-popover' );
		var fileInput = widget.querySelector( '.fcsa-import-file' );
		var submit    = widget.querySelector( '.fcsa-import-submit' );
		var spinner   = widget.querySelector( '.fcsa-import-spinner' );
		var message   = widget.querySelector( '.fcsa-import-message' );

		// Move the popover to <body> so it renders above the list table
		// instead of being clipped by (or drawn under) an ancestor's
		// stacking/overflow context, then position it via JS on open.
		document.body.appendChild( popover );

		function positionPopover() {
			var rect = toggle.getBoundingClientRect();
			popover.style.top  = rect.bottom + 4 + 'px';
			popover.style.left = rect.left + 'px';
		}

		function closePopover() {
			popover.hidden = true;
		}

		toggle.addEventListener( 'click', function () {
			if ( popover.hidden ) {
				positionPopover();
			}
			popover.hidden = ! popover.hidden;
		} );

		document.addEventListener( 'click', function ( event ) {
			if ( event.target !== toggle && ! popover.contains( event.target ) ) {
				closePopover();
			}
		} );

		window.addEventListener( 'scroll', closePopover, true );
		window.addEventListener( 'resize', closePopover );

		submit.addEventListener( 'click', function () {
			if ( ! fileInput.files.length ) {
				message.textContent = window.fcsaImport.i18n.noFile;
				return;
			}

			var formData = new FormData();
			formData.append( 'action', 'fcsa_import' );
			formData.append( 'nonce', window.fcsaImport.nonce );
			formData.append( 'fcsa_file', fileInput.files[ 0 ] );

			submit.disabled = true;
			spinner.classList.add( 'is-active' );
			message.textContent = window.fcsaImport.i18n.importing;

			fetch( window.fcsaImport.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin',
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( json ) {
					if ( json.success ) {
						window.location.href = json.data.editUrl;
						return;
					}

					submit.disabled = false;
					spinner.classList.remove( 'is-active' );
					message.textContent = ( json.data && json.data.message ) || window.fcsaImport.i18n.failed;
				} )
				.catch( function () {
					submit.disabled = false;
					spinner.classList.remove( 'is-active' );
					message.textContent = window.fcsaImport.i18n.failed;
				} );
		} );
	} );
} )();
