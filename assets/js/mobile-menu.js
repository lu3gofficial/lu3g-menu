/**
 * LU3G Mobile Menu — comportamento del widget (JavaScript vanilla, nessuna dipendenza).
 *
 * - apre e chiude il pannello (hamburger, pulsante chiudi, overlay, ESC, click su un link);
 * - sposta overlay e pannello in fondo al <body>, così un'intestazione con transform o filtri non ne blocca
 *   il position: fixed; il contenitore porta le classi Elementor del widget, quindi gli stili restano validi;
 * - blocca lo scroll della pagina, intrappola il focus nel pannello e lo restituisce all'hamburger alla chiusura;
 * - apre e chiude i sottomenu (aria-expanded), con l'opzione "un sottomenu aperto alla volta";
 * - frecce su/giù, Home e Fine per spostarsi tra le voci.
 * Le opzioni arrivano dal PHP nell'attributo data-lu3g-mm.
 */
( function () {
	'use strict';

	var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
	var current = null; // menu aperto in questo momento (uno solo per volta)

	function reducedMotion() {
		return window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	/** Elementi raggiungibili da tastiera e visibili dentro un contenitore. */
	function focusables( container ) {
		return Array.prototype.filter.call( container.querySelectorAll( FOCUSABLE ), function ( el ) {
			return el.getClientRects().length > 0 && window.getComputedStyle( el ).visibility !== 'hidden';
		} );
	}

	/* ------------------------------------------------------------------------------------- blocco scroll */

	function lockScroll() {
		var root = document.documentElement;
		root.style.setProperty( '--lu3g-mm-scrollbar', ( window.innerWidth - root.clientWidth ) + 'px' );
		root.classList.add( 'lu3g-mm-locked' );
	}

	function unlockScroll() {
		var root = document.documentElement;
		root.classList.remove( 'lu3g-mm-locked' );
		root.style.removeProperty( '--lu3g-mm-scrollbar' );
	}

	/* ------------------------------------------------------------------------------------------- widget */

	function MobileMenu( root ) {
		var opts = {};
		try {
			opts = JSON.parse( root.getAttribute( 'data-lu3g-mm' ) || '{}' );
		} catch ( e ) {}

		this.root = root;
		this.opts = opts;
		this.toggle = root.querySelector( '.lu3g-mm__toggle' );
		this.layer = root.querySelector( '.lu3g-mm__layer' );
		if ( ! this.toggle || ! this.layer ) {
			return;
		}
		this.panel = this.layer.querySelector( '.lu3g-mm__panel' );
		this.isOpen = false;
		this.timer = 0;

		this.portal();
		this.bind();

		if ( opts.editorOpen ) {
			this.open( true );
		}
	}

	/** Sposta overlay e pannello in fondo al <body>, dentro un contenitore con le classi del widget. */
	MobileMenu.prototype.portal = function () {
		var id = this.root.getAttribute( 'data-id' );
		var doc = this.root.closest( '[data-elementor-id]' );
		var outer = document.createElement( 'div' );
		var inner = document.createElement( 'div' );

		// nell'editor il widget viene ridisegnato a ogni modifica: si toglie la copia precedente
		Array.prototype.forEach.call( document.querySelectorAll( '.lu3g-mm-portal[data-id="' + id + '"]' ), function ( old ) {
			old.parentNode.removeChild( old );
		} );

		outer.className = 'lu3g-mm-portal' + ( doc ? ' elementor elementor-' + doc.getAttribute( 'data-elementor-id' ) : '' );
		outer.setAttribute( 'data-id', id );
		inner.className = 'elementor-element elementor-element-' + id;
		inner.appendChild( this.layer );
		outer.appendChild( inner );
		document.body.appendChild( outer );
		this.portalNode = outer;

		// editor: se il widget viene eliminato, il pannello spostato non deve restare nella pagina
		if ( this.opts.editor ) {
			var self = this;
			this.watch = window.setInterval( function () {
				if ( ! document.documentElement.contains( self.root ) ) {
					window.clearInterval( self.watch );
					if ( current === self ) {
						self.close( true );
					}
					if ( self.portalNode.parentNode ) {
						self.portalNode.parentNode.removeChild( self.portalNode );
					}
				}
			}, 1000 );
		}
	};

	MobileMenu.prototype.bind = function () {
		var self = this;

		this.toggle.addEventListener( 'click', function () {
			if ( self.isOpen ) {
				self.close();
			} else {
				self.open();
			}
		} );

		this.layer.addEventListener( 'click', function ( e ) {
			var t = e.target;
			if ( t.closest( '.lu3g-mm__close' ) ) {
				self.close();
				return;
			}
			if ( t.closest( '.lu3g-mm__overlay' ) ) {
				if ( self.opts.overlayClose ) {
					self.close();
				}
				return;
			}
			var sub = t.closest( '[data-sub-toggle]' );
			if ( sub ) {
				e.preventDefault();
				self.toggleSub( sub );
				return;
			}
			var link = t.closest( 'a[href]' );
			if ( link && self.opts.linkClose ) {
				self.onLink( link, e );
			}
		} );

		// sopra il breakpoint il widget sparisce: un pannello rimasto aperto si chiude (es. rotazione del tablet)
		if ( this.opts.breakpoint > 0 && window.matchMedia ) {
			var mq = window.matchMedia( '(min-width: ' + this.opts.breakpoint + 'px)' );
			var onChange = function ( ev ) {
				if ( ev.matches && self.isOpen ) {
					self.close( true );
				}
			};
			if ( mq.addEventListener ) {
				mq.addEventListener( 'change', onChange );
			} else if ( mq.addListener ) {
				mq.addListener( onChange );
			}
		}
	};

	/** Durata dell'animazione di chiusura (variabile CSS --lu3g-mm-dur), 0 con "riduci movimento". */
	MobileMenu.prototype.duration = function () {
		if ( reducedMotion() ) {
			return 0;
		}
		var raw = window.getComputedStyle( this.layer ).getPropertyValue( '--lu3g-mm-dur' ).trim();
		if ( ! raw ) {
			return 450;
		}
		var n = parseFloat( raw );
		return isNaN( n ) ? 450 : ( /ms$/.test( raw ) ? n : n * 1000 );
	};

	/** preview = aperto nell'editor: niente blocco dello scroll né spostamento del focus. */
	MobileMenu.prototype.open = function ( preview ) {
		if ( this.isOpen ) {
			return;
		}
		if ( current && current !== this ) {
			current.close( true );
		}
		current = this;
		this.isOpen = true;
		this.preview = !! preview;
		this.lastFocus = document.activeElement;
		window.clearTimeout( this.timer );

		this.panel.removeAttribute( 'inert' );
		this.layer.classList.add( 'is-visible' );
		void this.layer.offsetWidth; // reflow: la transizione parte dallo stato chiuso
		this.layer.classList.add( 'is-open' );
		this.root.classList.add( 'is-open' );
		this.toggle.setAttribute( 'aria-expanded', 'true' );

		if ( preview ) {
			return;
		}
		if ( this.opts.lock ) {
			lockScroll();
		}
		var first = focusables( this.panel )[ 0 ];
		if ( first ) {
			first.focus( { preventScroll: true } );
		}
	};

	/** silent = senza restituire il focus all'hamburger (es. si sta navigando verso un'altra pagina). */
	MobileMenu.prototype.close = function ( silent ) {
		if ( ! this.isOpen ) {
			return;
		}
		var self = this;
		var hadFocus = this.panel.contains( document.activeElement );

		this.isOpen = false;
		if ( current === this ) {
			current = null;
		}
		this.layer.classList.remove( 'is-open' );
		this.root.classList.remove( 'is-open' );
		this.toggle.setAttribute( 'aria-expanded', 'false' );
		this.panel.setAttribute( 'inert', '' );
		if ( ! this.preview ) {
			unlockScroll();
		}
		this.preview = false;

		this.timer = window.setTimeout( function () {
			self.layer.classList.remove( 'is-visible' );
		}, this.duration() );

		if ( hadFocus && ! silent ) {
			this.toggle.focus( { preventScroll: true } );
		}
	};

	/* ---------------------------------------------------------------------------------------- sottomenu */

	MobileMenu.prototype.toggleSub = function ( btn ) {
		var item = btn.closest( '.lu3g-mm__item' );
		if ( ! item ) {
			return;
		}
		var open = btn.getAttribute( 'aria-expanded' ) !== 'true';
		var self = this;

		if ( open && this.opts.accordion && item.parentNode ) {
			Array.prototype.forEach.call( item.parentNode.children, function ( sibling ) {
				if ( sibling !== item && sibling.classList.contains( 'is-sub-open' ) ) {
					self.setSub( sibling, false );
				}
			} );
		}
		this.setSub( item, open );
	};

	MobileMenu.prototype.setSub = function ( item, open ) {
		item.classList.toggle( 'is-sub-open', open );
		var row = item.querySelector( '.lu3g-mm__row' );
		var btn = row && row.querySelector( '[data-sub-toggle]' );
		if ( btn ) {
			btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}
	};

	/* -------------------------------------------------------------------------------------------- link */

	MobileMenu.prototype.onLink = function ( link, e ) {
		var href = link.getAttribute( 'href' );
		if ( ! href || href === '#' ) {
			return;
		}
		// nuova scheda o tasti modificatori: la pagina resta qui, il menu resta aperto
		if ( link.target === '_blank' || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button > 0 ) {
			return;
		}

		var url;
		try {
			url = new URL( link.href, window.location.href );
		} catch ( err ) {
			return;
		}
		var samePage = url.origin === window.location.origin && url.pathname === window.location.pathname && url.search === window.location.search;

		if ( samePage && url.hash ) {
			// ancora nella stessa pagina: prima si chiude il menu (e si sblocca lo scroll), poi si scorre
			e.preventDefault();
			this.close( true );
			var target = document.getElementById( decodeURIComponent( url.hash.slice( 1 ) ) );
			if ( window.history && window.history.pushState ) {
				window.history.pushState( null, '', url.hash );
			}
			if ( target ) {
				window.requestAnimationFrame( function () {
					target.scrollIntoView( { behavior: reducedMotion() ? 'auto' : 'smooth', block: 'start' } );
					if ( ! target.hasAttribute( 'tabindex' ) ) {
						target.setAttribute( 'tabindex', '-1' );
					}
					target.focus( { preventScroll: true } );
				} );
			}
			return;
		}

		this.close( true );
	};

	/* ------------------------------------------------------------------------------------------ tastiera */

	MobileMenu.prototype.onKey = function ( e ) {
		if ( e.key === 'Escape' || e.key === 'Esc' ) {
			if ( this.opts.esc ) {
				e.preventDefault();
				this.close();
			}
			return;
		}

		var list = focusables( this.panel );
		if ( ! list.length ) {
			return;
		}
		var active = document.activeElement;
		var index = list.indexOf( active );

		// focus trap: Tab e Maiusc+Tab girano solo dentro il pannello
		if ( e.key === 'Tab' ) {
			if ( e.shiftKey && ( index <= 0 ) ) {
				e.preventDefault();
				list[ list.length - 1 ].focus();
			} else if ( ! e.shiftKey && ( index === -1 || index === list.length - 1 ) ) {
				e.preventDefault();
				list[ 0 ].focus();
			}
			return;
		}

		// frecce, Home e Fine tra le voci (non dentro i campi di testo)
		if ( index === -1 || /^(INPUT|TEXTAREA|SELECT)$/.test( active.tagName ) ) {
			return;
		}
		var next = null;
		if ( e.key === 'ArrowDown' ) {
			next = list[ ( index + 1 ) % list.length ];
		} else if ( e.key === 'ArrowUp' ) {
			next = list[ ( index - 1 + list.length ) % list.length ];
		} else if ( e.key === 'Home' ) {
			next = list[ 0 ];
		} else if ( e.key === 'End' ) {
			next = list[ list.length - 1 ];
		}
		if ( next ) {
			e.preventDefault();
			next.focus();
		}
	};

	document.addEventListener( 'keydown', function ( e ) {
		if ( current && ! current.preview ) {
			current.onKey( e );
		}
	} );

	/* -------------------------------------------------------------------------------------------- avvio */

	function init( scope ) {
		var base = scope && scope.querySelectorAll ? scope : document;
		var nodes = base.matches && base.matches( '.lu3g-mm' ) ? [ base ] : base.querySelectorAll( '.lu3g-mm' );
		Array.prototype.forEach.call( nodes, function ( node ) {
			if ( ! node.lu3gMobileMenu ) {
				node.lu3gMobileMenu = new MobileMenu( node );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init( document );
		} );
	} else {
		init( document );
	}

	// editor di Elementor: il widget viene ridisegnato a ogni modifica e va riagganciato.
	// L'evento elementor/frontend/init è emesso da Elementor con jQuery: qui jQuery serve solo ad ascoltarlo.
	function hookElementor() {
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/lu3g-mobile-menu.default', function ( $scope ) {
				init( $scope && $scope[ 0 ] ? $scope[ 0 ] : $scope );
			} );
			return true;
		}
		return false;
	}
	if ( ! hookElementor() && window.jQuery ) {
		window.jQuery( window ).on( 'elementor/frontend/init', hookElementor );
	}
} )();
