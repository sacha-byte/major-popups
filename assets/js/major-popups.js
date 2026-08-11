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
	var preloaded = {}; // config.id -> { holder, loading, loaded }

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

		preload( config );

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

	/**
	 * Charge le contenu du popup en arrière-plan, hors écran, dès l'armement du
	 * déclencheur — pas seulement à l'affichage. Le délai/scroll d'attente avant
	 * déclenchement (1 à 20s selon le popup) est un temps mort qu'on utilise pour
	 * précharger le formulaire Lead Manager ; `show()` réutilise ce conteneur au
	 * lieu d'en créer un nouveau, pour une ouverture instantanée le cas échéant.
	 */
	function preload( config ) {
		var template = document.getElementById( 'mpp-content-' + config.id );
		if ( ! template ) {
			return;
		}

		var holder = document.createElement( 'div' );
		holder.style.position = 'absolute';
		holder.style.left = '-9999px';
		holder.style.top = '0';
		document.body.appendChild( holder );

		var loading = document.createElement( 'div' );
		loading.className = 'mpp-loading';
		loading.textContent = 'Chargement du formulaire…';
		holder.appendChild( loading );

		appendEmbedContent( holder, template.content );

		var state = { holder: holder, loading: loading, loaded: false };
		preloaded[ config.id ] = state;

		watchRealContent( holder, function () {
			state.loaded = true;
			if ( state.loading.parentNode ) {
				state.loading.remove();
			}
		} );
	}

	function show( config ) {
		if ( shown ) {
			return; // un popup déjà affiché sur cette page vue
		}
		shown = true;

		var state = preloaded[ config.id ];
		if ( ! state ) {
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

		var body = state.holder;
		body.style.position = '';
		body.style.left = '';
		body.style.top = '';
		body.className = 'mpp-card-body';

		if ( ! state.loaded ) {
			// Filet de sécurité : le préchargement n'a pas eu le temps de finir avant
			// le déclenchement (délai/scroll trop court) — on abandonne l'indicateur
			// au bout de 8s à partir de l'AFFICHAGE (pas de l'armement, qui peut être
			// bien antérieur), pour ne jamais laisser un spinner tourner indéfiniment
			// sous les yeux du visiteur.
			setTimeout( function () {
				if ( ! state.loaded && state.loading.parentNode ) {
					state.loading.remove();
				}
			}, 8000 );
		}

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

	/**
	 * Clone le fragment du <template> en recréant les <script> pour qu'ils s'exécutent
	 * (une insertion via innerHTML ne les exécuterait pas). Ajoute automatiquement
	 * data-layout="compact" sur le script d'embed Lead Manager : un popup veut
	 * toujours la mise en page compacte (Email/Téléphone/Région sur une ligne), pas
	 * besoin que la personne qui colle le code d'embed s'en souvienne.
	 */
	function appendEmbedContent( container, fragment ) {
		fragment.childNodes.forEach( function ( node ) {
			if ( node.nodeName === 'SCRIPT' ) {
				var script = document.createElement( 'script' );
				Array.prototype.forEach.call( node.attributes, function ( attr ) {
					script.setAttribute( attr.name, attr.value );
				} );
				script.dataset.layout = 'compact';
				script.textContent = node.textContent;
				container.appendChild( script );
			} else {
				container.appendChild( node.cloneNode( true ) );
			}
		} );
	}

	/**
	 * Le code d'embed Lead Manager (script src externe) s'exécute de façon asynchrone
	 * (chargement réseau) — le conteneur + Shadow DOM qu'il crée n'existent donc pas
	 * encore juste après l'insertion du <script>. On observe l'arrivée de ce conteneur,
	 * puis le premier changement dans son Shadow Root (fragment chargé, ou message
	 * d'erreur), et on appelle `onLoaded` à ce moment précis — pas de délai arbitraire
	 * ici, la gestion du "j'abandonne d'attendre" se fait au moment de l'affichage
	 * (cf. show()), puisque le préchargement peut démarrer bien avant.
	 */
	function watchRealContent( body, onLoaded ) {
		var containerObserver = new MutationObserver( function () {
			var container = findShadowHost( body );
			if ( ! container ) {
				return;
			}
			containerObserver.disconnect();

			var shadowObserver = new MutationObserver( function () {
				shadowObserver.disconnect();
				onLoaded();
			} );
			shadowObserver.observe( container.shadowRoot, { childList: true, subtree: true } );
		} );
		containerObserver.observe( body, { childList: true } );
	}

	function findShadowHost( body ) {
		for ( var i = 0; i < body.children.length; i++ ) {
			if ( body.children[ i ].shadowRoot ) {
				return body.children[ i ];
			}
		}
		return null;
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
