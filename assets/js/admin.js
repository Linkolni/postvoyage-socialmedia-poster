/**
 * PostVoyage admin scripts (no build step, no dependencies).
 *
 * @package PostVoyage
 */
( function () {
	'use strict';

	var config = window.postvoyageAdmin || { i18n: {} };

	/**
	 * Account form: show only the fields of the selected network.
	 */
	function initNetworkSwitch() {
		var select = document.getElementById( 'postvoyage-network' );
		if ( ! select ) {
			return;
		}
		function update() {
			document.querySelectorAll( '.postvoyage-network-fields' ).forEach( function ( fieldset ) {
				var active = fieldset.getAttribute( 'data-network' ) === select.value;
				fieldset.hidden = ! active;
				fieldset.disabled = ! active;
			} );
		}
		select.addEventListener( 'change', update );
		update();
	}

	/**
	 * Rule form: insert variables at the cursor of the last focused template field.
	 */
	function initVariables() {
		var templates = document.querySelectorAll( '[data-postvoyage-template]' );
		if ( ! templates.length ) {
			return;
		}
		var target = templates[ 0 ];
		templates.forEach( function ( textarea ) {
			textarea.addEventListener( 'focus', function () {
				target = textarea;
			} );
		} );

		document.querySelectorAll( '[data-postvoyage-variable]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var variable = button.getAttribute( 'data-postvoyage-variable' );
				var start = target.selectionStart || 0;
				var end = target.selectionEnd || 0;
				target.value = target.value.slice( 0, start ) + variable + target.value.slice( end );
				target.focus();
				target.selectionStart = target.selectionEnd = start + variable.length;
			} );
		} );
	}

	/**
	 * Ask before delete links.
	 */
	function initDeleteConfirm() {
		document.querySelectorAll( '.postvoyage-delete' ).forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				// eslint-disable-next-line no-alert
				if ( ! window.confirm( config.i18n.confirmDelete ) ) {
					event.preventDefault();
				}
			} );
		} );
	}

	/**
	 * Editor box: "Share now".
	 */
	function initShareNow() {
		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-postvoyage-share-now]' );
			if ( ! button ) {
				return;
			}
			var box = button.closest( '.postvoyage-share-now' );
			var output = box.querySelector( '.postvoyage-share-result' );
			var body = new window.FormData();

			body.append( 'action', 'postvoyage_share_now' );
			body.append( 'post_id', button.getAttribute( 'data-post' ) );
			body.append( 'nonce', button.getAttribute( 'data-nonce' ) );
			box.querySelectorAll( '.postvoyage-share-account:checked' ).forEach( function ( input ) {
				body.append( 'accounts[]', input.value );
			} );

			button.disabled = true;
			output.textContent = config.i18n.sharing;

			window.fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( json ) {
					output.textContent = '';
					if ( ! json.success ) {
						output.textContent = ( json.data && json.data.message ) || config.i18n.error;
						return;
					}
					if ( ! json.data.results.length ) {
						output.textContent = config.i18n.nothing;
						return;
					}
					var list = document.createElement( 'ul' );
					json.data.results.forEach( function ( result ) {
						var item = document.createElement( 'li' );
						item.textContent = ( result.success ? '✓ ' : '✗ ' ) + result.account + ( result.message ? ': ' + result.message : '' ) + ' ';
						if ( result.url ) {
							var link = document.createElement( 'a' );
							link.href = result.url;
							link.target = '_blank';
							link.rel = 'noopener noreferrer';
							link.textContent = config.i18n.view;
							item.appendChild( link );
						}
						list.appendChild( item );
					} );
					output.appendChild( list );
				} )
				.catch( function () {
					output.textContent = config.i18n.error;
				} )
				.finally( function () {
					button.disabled = false;
				} );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initNetworkSwitch();
		initVariables();
		initDeleteConfirm();
		initShareNow();
	} );
}() );
