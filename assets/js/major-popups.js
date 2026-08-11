/**
 * Major Popups — un seul popup affiché par page, le premier dont la condition
 * (délai ou scroll) se déclenche. Contenu = code d'embed Lead Manager, injecté
 * dynamiquement (document.createElement) pour qu'un éventuel <script> s'exécute
 * réellement — une insertion via innerHTML ne l'exécuterait pas.
 *
 * Exclu du "Delay JS" de WP Rocket (cf. class-cache-compat.php) : ce script doit
 * démarrer ses minuteurs dès le vrai chargement de page, pas après une interaction.
 */
( function () {
	'use strict';

	var configEl = document.getElementById( 'mpp-config' );
	if ( ! configEl ) {
		return;
	}

	var configs;
	try {
		configs = JSON.parse( configEl.textContent );
	} catch ( e ) {
		return;
	}

	var shown = false;
	var lastFocused = null;
	var overlay = null;

	function getCookie( name ) {
		var match = document.cookie.match( '(^|; )' + name + '=([^;]*)' );
		return match ? decodeURIComponent( match[ 2 ] ) : null;
	}

	function setCookie( name, days ) {
		var expires = new Date( Date.now() + days * 24 * 60 * 60 * 1000 ).toUTCString();
		document.cookie = name + '=1; expires=' + expires + '; path=/; SameSite=Lax';
	}

	function arm( config ) {
		if ( config.cookieEnabled && getCookie( config.cookieName ) ) {
			return; // déjà fermé récemment, on n'arme même pas le déclencheur
		}

		if ( config.triggerType === 'scroll' ) {
			var onScroll = function () {
				var scrollable = document.documentElement.scrollHeight - window.innerHeight;
				var percent = scrollable > 0 ? ( window.scrollY / scrollable ) * 100 : 100;
				if ( percent >= config.scrollPercent ) {
					window.removeEventListener( 'scroll', onScroll );
					show( config );
				}
			};
			window.addEventListener( 'scroll', onScroll, { passive: true } );
		} else {
			setTimeout( function () {
				show( config );
			}, Math.max( 0, config.delaySeconds ) * 1000 );
		}
	}

	function show( config ) {
		if ( shown ) {
			return; // un popup déjà affiché sur cette page vue
		}
		shown = true;

		var template = document.getElementById( 'mpp-content-' + config.id );
		if ( ! template ) {
			return;
		}

		lastFocused = document.activeElement;

		overlay = document.createElement( 'div' );
		overlay.className = 'mpp-overlay';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );

		var card = document.createElement( 'div' );
		card.className = 'mpp-card';

		var closeBtn = document.createElement( 'button' );
		closeBtn.className = 'mpp-close';
		closeBtn.setAttribute( 'aria-label', 'Fermer' );
		closeBtn.innerHTML = '&times;';
		closeBtn.addEventListener( 'click', function () {
			close( config );
		} );

		var body = document.createElement( 'div' );
		body.className = 'mpp-card-body';
		appendEmbedContent( body, template.content );

		var dismiss = document.createElement( 'button' );
		dismiss.className = 'mpp-dismiss';
		dismiss.textContent = 'Non merci, je continue ma lecture';
		dismiss.addEventListener( 'click', function () {
			close( config );
		} );
		body.appendChild( dismiss );

		card.appendChild( closeBtn );
		card.appendChild( body );
		overlay.appendChild( card );
		document.body.appendChild( overlay );

		overlay.addEventListener( 'mousedown', function ( e ) {
			if ( e.target === overlay ) {
				close( config );
			}
		} );

		document.addEventListener( 'keydown', onKeydown );
		closeBtn.focus();

		function onKeydown( e ) {
			if ( e.key === 'Escape' ) {
				close( config );
				return;
			}
			if ( e.key === 'Tab' ) {
				trapFocus( e, card );
			}
		}

		overlay._mppKeydownHandler = onKeydown;
	}

	function trapFocus( e, card ) {
		var focusable = card.querySelectorAll( 'button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])' );
		if ( ! focusable.length ) {
			return;
		}
		var first = focusable[ 0 ];
		var last = focusable[ focusable.length - 1 ];

		if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	}

	/** Clone le fragment du <template> en recréant les <script> pour qu'ils s'exécutent. */
	function appendEmbedContent( container, fragment ) {
		fragment.childNodes.forEach( function ( node ) {
			if ( node.nodeName === 'SCRIPT' ) {
				var script = document.createElement( 'script' );
				Array.prototype.forEach.call( node.attributes, function ( attr ) {
					script.setAttribute( attr.name, attr.value );
				} );
				script.textContent = node.textContent;
				container.appendChild( script );
			} else {
				container.appendChild( node.cloneNode( true ) );
			}
		} );
	}

	function close( config ) {
		if ( config.cookieEnabled ) {
			setCookie( config.cookieName, config.cookieDays );
		}
		if ( overlay ) {
			document.removeEventListener( 'keydown', overlay._mppKeydownHandler );
			overlay.remove();
			overlay = null;
		}
		if ( lastFocused && typeof lastFocused.focus === 'function' ) {
			lastFocused.focus();
		}
	}

	configs.forEach( arm );
} )();
