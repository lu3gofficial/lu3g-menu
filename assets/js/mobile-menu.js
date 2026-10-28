/**
 * Menu LU3G — comportamento del widget (JavaScript vanilla, nessuna dipendenza).
 *
 * - apre e chiude il pannello (hamburger, pulsante chiudi, overlay, ESC, click su un link);
 * - sposta overlay e pannello in fondo al <body>, così un'intestazione con transform o filtri non ne blocca
 *   il position: fixed; il contenitore porta le classi Elementor del widget, quindi gli stili restano validi;
 * - blocca lo scroll della pagina, intrappola il focus nel pannello e lo restituisce all'hamburger alla chiusura;
 * - apre e chiude i sottomenu (aria-expanded): a espansione, con l'opzione "un sottomenu aperto alla volta",
 *   oppure a slide, dove ogni sottomenu entra da destra come una nuova schermata con il pulsante Indietro;
 * - frecce su/giù, Home e Fine per spostarsi tra le voci; nei sottomenu a slide freccia destra entra, sinistra torna indietro;
 * - barra desktop (.lu3g-mb): tendine al passaggio del mouse o al click, primo tocco che apre la tendina sui touch,
 *   ESC e uscita del focus che chiudono, tendine laterali che non escono dallo schermo, mega menu largo quanto
 *   il contenitore, lo schermo o la larghezza scelta.
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
			return el.getClientRects().length > 0 && window.getComputedStyle( el ).visibility !== 'hidden' && ! el.closest( '[inert]' );
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
		this.nav = this.layer.querySelector( '.lu3g-mm__nav' );
		this.slide = this.layer.classList.contains( 'lu3g-mm__layer--slide' ) && !! this.nav;
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

		this.toggle.addEventListener( 'click', function ( e ) {
			if ( self.isOpen ) {
				self.close();
			} else {
				// e.detail === 0: "click" generato da Invio o Spazio, cioè apertura da tastiera
				self.open( false, e.detail === 0 );
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
			var back = t.closest( '[data-sub-back]' );
			if ( back ) {
				self.slideBack( back.closest( '.lu3g-mm__item.is-sub-open' ) );
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

		// a slide l'altezza del menu è quella della schermata attiva: si ricalcola se cambia la larghezza
		if ( this.slide ) {
			window.addEventListener( 'resize', function () {
				if ( self.isOpen && self.nav.style.height ) {
					self.nav.style.height = self.activeView().offsetHeight + 'px';
				}
			} );
		}

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

	/**
	 * preview = aperto nell'editor: niente blocco dello scroll né spostamento del focus.
	 * keyboard = aperto da tastiera: il focus va sulla prima voce (con il suo contorno); con tocco o click va sul
	 * pannello stesso, senza contorni visibili, e il primo Tab porta comunque alla prima voce.
	 */
	MobileMenu.prototype.open = function ( preview, keyboard ) {
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
		this.setOrigin();
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
		var first = keyboard ? focusables( this.panel )[ 0 ] : null;
		( first || this.panel ).focus( { preventScroll: true } );
	};

	/** Apertura "Cerchio dal pulsante": il cerchio parte dal centro dell'hamburger, ovunque sia nella pagina. */
	MobileMenu.prototype.setOrigin = function () {
		if ( this.layer.getAttribute( 'data-animation' ) !== 'circle' ) {
			return;
		}
		var r = this.toggle.getBoundingClientRect();
		if ( ! r.width && ! r.height ) {
			return; // hamburger nascosto (es. editor): resta il valore predefinito, in alto a destra
		}
		this.layer.style.setProperty( '--lu3g-mm-ox', Math.round( r.left + r.width / 2 ) + 'px' );
		this.layer.style.setProperty( '--lu3g-mm-oy', Math.round( r.top + r.height / 2 ) + 'px' );
	};

	/** silent = senza restituire il focus all'hamburger (es. si sta navigando verso un'altra pagina). */
	MobileMenu.prototype.close = function ( silent ) {
		if ( ! this.isOpen ) {
			return;
		}
		var self = this;
		// il focus torna all'hamburger se era nel pannello, o se il click sull'overlay l'ha spostato sul <body>
		var active = document.activeElement;
		var hadFocus = ! active || active === document.body || this.panel.contains( active );

		this.isOpen = false;
		if ( current === this ) {
			current = null;
		}
		this.setOrigin();
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
			// a slide si riparte dal primo livello alla prossima apertura
			if ( self.slide ) {
				self.resetSlides();
			}
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

		if ( this.slide ) {
			if ( open ) {
				this.slideIn( item );
			}
			return;
		}

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

	/* ------------------------------------------------------------------------------- sottomenu a slide */

	/** Durata dello scorrimento (variabile CSS --lu3g-mm-sub-dur), 0 con "riduci movimento". */
	MobileMenu.prototype.subDuration = function () {
		if ( reducedMotion() ) {
			return 0;
		}
		var raw = window.getComputedStyle( this.layer ).getPropertyValue( '--lu3g-mm-sub-dur' ).trim();
		var n = parseFloat( raw );
		return isNaN( n ) ? 300 : ( /ms$/.test( raw ) ? n : n * 1000 );
	};

	/** Schermata visibile: il sottomenu aperto più in profondità, oppure il primo livello. */
	MobileMenu.prototype.activeView = function () {
		var open = this.nav.querySelectorAll( '.lu3g-mm__item.is-sub-open' );
		if ( ! open.length ) {
			return this.nav.querySelector( '.lu3g-mm__menu' );
		}
		return this.subWrap( open[ open.length - 1 ] );
	};

	MobileMenu.prototype.subWrap = function ( item ) {
		for ( var i = 0; i < item.children.length; i++ ) {
			if ( item.children[ i ].classList.contains( 'lu3g-mm__sub-wrap' ) ) {
				return item.children[ i ];
			}
		}
		return null;
	};

	/** Porta il menu all'altezza della schermata indicata (partendo dall'altezza attuale, per la transizione). */
	MobileMenu.prototype.fitHeight = function ( view ) {
		if ( ! this.nav.style.height ) {
			this.nav.style.height = this.nav.offsetHeight + 'px';
			void this.nav.offsetHeight;
		}
		this.nav.style.height = view.offsetHeight + 'px';
	};

	/** Se il pannello è scorso più in basso dell'inizio del menu, lo riporta su: la nuova schermata parte dall'alto. */
	MobileMenu.prototype.revealNav = function () {
		var top = this.nav.getBoundingClientRect().top - this.panel.getBoundingClientRect().top;
		if ( top < 0 ) {
			this.panel.scrollTop += top;
		}
	};

	/** Le voci del livello che esce non devono essere raggiungibili con Tab né dagli screen reader. */
	MobileMenu.prototype.setLevelInert = function ( item, inert ) {
		var row = item.querySelector( '.lu3g-mm__row' );
		var parts = Array.prototype.filter.call( item.parentNode.children, function ( el ) {
			return el !== item;
		} );
		if ( row ) {
			parts.push( row );
		}
		parts.forEach( function ( el ) {
			if ( inert ) {
				el.setAttribute( 'inert', '' );
			} else {
				el.removeAttribute( 'inert' );
			}
		} );
	};

	MobileMenu.prototype.slideIn = function ( item ) {
		var wrap = this.subWrap( item );
		if ( ! wrap ) {
			return;
		}
		this.layer.classList.add( 'lu3g-mm__layer--nav' );
		item.parentNode.classList.add( 'is-sub-parent' );
		this.setSub( item, true );
		this.setLevelInert( item, true );
		this.fitHeight( wrap );
		this.revealNav();

		var back = wrap.querySelector( '[data-sub-back]' );
		if ( back ) {
			back.focus( { preventScroll: true } );
		}
	};

	MobileMenu.prototype.slideBack = function ( item ) {
		if ( ! item ) {
			return;
		}
		var list = item.parentNode;
		list.classList.remove( 'is-sub-parent' );
		this.setSub( item, false );
		this.setLevelInert( item, false );
		this.fitHeight( list.closest( '.lu3g-mm__sub-wrap' ) || list );
		this.revealNav();

		var toggle = item.querySelector( '.lu3g-mm__row [data-sub-toggle]' );
		if ( toggle ) {
			toggle.focus( { preventScroll: true } );
		}

		// tornati al primo livello il menu riprende l'altezza naturale
		if ( ! this.nav.querySelector( '.is-sub-open' ) ) {
			var nav = this.nav;
			window.clearTimeout( this.heightTimer );
			this.heightTimer = window.setTimeout( function () {
				if ( ! nav.querySelector( '.is-sub-open' ) ) {
					nav.style.height = '';
				}
			}, this.subDuration() );
		}
	};

	/** Tutti i sottomenu chiusi, senza animazione visibile (il pannello è già nascosto). */
	MobileMenu.prototype.resetSlides = function () {
		var self = this;
		Array.prototype.forEach.call( this.nav.querySelectorAll( '.is-sub-open' ), function ( item ) {
			self.setSub( item, false );
		} );
		Array.prototype.forEach.call( this.nav.querySelectorAll( '.is-sub-parent' ), function ( list ) {
			list.classList.remove( 'is-sub-parent' );
		} );
		Array.prototype.forEach.call( this.nav.querySelectorAll( '[inert]' ), function ( el ) {
			el.removeAttribute( 'inert' );
		} );
		this.nav.style.height = '';
		this.layer.classList.remove( 'lu3g-mm__layer--nav' );
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
		if ( /^(INPUT|TEXTAREA|SELECT)$/.test( active.tagName ) ) {
			return;
		}
		if ( this.slide && index !== -1 ) {
			if ( e.key === 'ArrowRight' && active.hasAttribute( 'data-sub-toggle' ) && active.getAttribute( 'aria-expanded' ) !== 'true' ) {
				e.preventDefault();
				this.slideIn( active.closest( '.lu3g-mm__item' ) );
				return;
			}
			var openItem = e.key === 'ArrowLeft' && this.nav.contains( active ) ? active.closest( '.lu3g-mm__item.is-sub-open' ) : null;
			if ( openItem ) {
				e.preventDefault();
				this.slideBack( openItem );
				return;
			}
		}
		if ( index === -1 ) {
			// focus sul pannello (apertura con tocco o click): la prima freccia giù porta alla prima voce
			if ( active === this.panel && e.key === 'ArrowDown' ) {
				e.preventDefault();
				list[ 0 ].focus();
			}
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

	/* ----------------------------------------------------------------------------------- barra desktop */

	function MenuBar( nav ) {
		var opts = {};
		try {
			opts = JSON.parse( nav.getAttribute( 'data-lu3g-mb' ) || '{}' );
		} catch ( e ) {}

		this.nav = nav;
		this.opts = opts;
		this.hover = opts.trigger !== 'click';
		this.delay = typeof opts.delay === 'number' ? opts.delay : 250;
		this.pointer = 'mouse'; // ultimo tipo di puntatore usato nella barra (mouse, touch, pen)
		this.bind();
	}

	function children( el, cls ) {
		return Array.prototype.filter.call( el.children, function ( c ) {
			return c.classList.contains( cls );
		} );
	}

	MenuBar.prototype.toggleOf = function ( li ) {
		var t = Array.prototype.filter.call( li.children, function ( c ) {
			return c.hasAttribute( 'data-mb-toggle' );
		} );
		return t[ 0 ] || null;
	};

	MenuBar.prototype.bind = function () {
		var self = this;
		var nav = this.nav;

		nav.addEventListener( 'pointerdown', function ( e ) {
			self.pointer = e.pointerType || 'mouse';
		}, true );

		// apertura al passaggio del mouse (solo mouse: sui touch decide il tocco)
		if ( this.hover ) {
			Array.prototype.forEach.call( nav.querySelectorAll( '.lu3g-mb__item--has-drop' ), function ( li ) {
				li.addEventListener( 'pointerenter', function ( e ) {
					if ( e.pointerType !== 'mouse' ) {
						return;
					}
					window.clearTimeout( li.lu3gTimer );
					self.open( li );
				} );
				li.addEventListener( 'pointerleave', function ( e ) {
					if ( e.pointerType !== 'mouse' ) {
						return;
					}
					li.lu3gTimer = window.setTimeout( function () {
						self.close( li );
					}, self.delay );
				} );
			} );
		}

		nav.addEventListener( 'click', function ( e ) {
			var toggle = e.target.closest( '[data-mb-toggle]' );
			if ( toggle && nav.contains( toggle ) ) {
				e.preventDefault();
				self.toggle( toggle.closest( '.lu3g-mb__item' ) );
				return;
			}
			// touch con apertura "al passaggio": il primo tocco su una voce con tendina la apre, il secondo segue il link
			var link = e.target.closest( 'a.lu3g-mb__link' );
			if ( link && self.hover && self.pointer !== 'mouse' ) {
				var li = link.parentNode;
				if ( li.classList.contains( 'lu3g-mb__item--has-drop' ) && ! li.classList.contains( 'is-open' ) ) {
					e.preventDefault();
					self.open( li );
				}
			}
		} );

		// click fuori dalla barra: tutto chiuso
		document.addEventListener( 'click', function ( e ) {
			if ( ! nav.contains( e.target ) ) {
				self.closeAll();
			}
		} );

		nav.addEventListener( 'keydown', function ( e ) {
			if ( e.key !== 'Escape' && e.key !== 'Esc' ) {
				return;
			}
			var li = e.target.closest( '.lu3g-mb__item.is-open' ) || nav.querySelector( '.lu3g-mb__item.is-open' );
			if ( ! li ) {
				return;
			}
			// si chiude la tendina più interna aperta e il focus torna al suo pulsante
			var inner = li.querySelectorAll( '.lu3g-mb__item.is-open' );
			if ( inner.length ) {
				li = inner[ inner.length - 1 ];
			}
			e.preventDefault();
			self.close( li );
			var t = self.toggleOf( li );
			if ( t ) {
				t.focus();
			}
		} );

		// con la tastiera: uscendo con Tab da una voce aperta, la sua tendina si chiude
		nav.addEventListener( 'focusout', function ( e ) {
			var to = e.relatedTarget;
			if ( ! to ) {
				return; // click su un'area vuota: ci pensa il click fuori
			}
			Array.prototype.forEach.call( nav.querySelectorAll( '.lu3g-mb__item.is-open' ), function ( li ) {
				if ( ! li.contains( to ) ) {
					self.close( li );
				}
			} );
		} );

		window.addEventListener( 'resize', function () {
			Array.prototype.forEach.call( nav.querySelectorAll( '.lu3g-mb__item.is-open' ), function ( li ) {
				self.place( li );
			} );
		} );
	};

	MenuBar.prototype.setExpanded = function ( li, open ) {
		var t = this.toggleOf( li );
		if ( t ) {
			t.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}
	};

	MenuBar.prototype.open = function ( li ) {
		var self = this;
		Array.prototype.forEach.call( li.parentNode.children, function ( sibling ) {
			if ( sibling !== li && sibling.classList.contains( 'is-open' ) ) {
				self.close( sibling );
			}
		} );
		window.clearTimeout( li.lu3gTimer );
		this.place( li );
		li.classList.add( 'is-open' );
		this.setExpanded( li, true );
	};

	MenuBar.prototype.close = function ( li ) {
		var self = this;
		window.clearTimeout( li.lu3gTimer );
		li.classList.remove( 'is-open' );
		this.setExpanded( li, false );
		Array.prototype.forEach.call( li.querySelectorAll( '.lu3g-mb__item.is-open' ), function ( inner ) {
			inner.classList.remove( 'is-open' );
			self.setExpanded( inner, false );
		} );
	};

	MenuBar.prototype.toggle = function ( li ) {
		if ( li.classList.contains( 'is-open' ) ) {
			this.close( li );
		} else {
			this.open( li );
		}
	};

	MenuBar.prototype.closeAll = function () {
		var self = this;
		Array.prototype.forEach.call( this.nav.querySelectorAll( '.lu3g-mb__menu > .lu3g-mb__item.is-open' ), function ( li ) {
			self.close( li );
		} );
	};

	/** Riferimento per la larghezza del mega menu "come il contenitore": il contenitore Elementor della barra. */
	MenuBar.prototype.container = function () {
		var n = this.nav;
		return n.closest( '.e-con-inner' ) || n.closest( '.e-con' ) || n.closest( '.elementor-container' ) || n.closest( '.elementor-section' ) || document.documentElement;
	};

	/** Posizione della tendina prima di aprirla: mega menu (larghezza e allineamento) o tendine che non escano dallo schermo. */
	MenuBar.prototype.place = function ( li ) {
		var drop = children( li, 'lu3g-mb__dropdown' )[ 0 ];
		if ( ! drop ) {
			return;
		}
		var vw = document.documentElement.clientWidth;
		var r = li.getBoundingClientRect();

		if ( drop.classList.contains( 'lu3g-mb__mega' ) ) {
			var left;
			var width;
			if ( this.opts.mega === 'custom' ) {
				drop.style.width = '';
				width = Math.min( drop.offsetWidth, vw - 16 );
				left = Math.max( 8, Math.min( r.left + r.width / 2 - width / 2, vw - width - 8 ) );
			} else if ( this.opts.mega === 'full' ) {
				width = vw;
				left = 0;
			} else {
				var c = this.container().getBoundingClientRect();
				width = c.width;
				left = c.left;
			}
			drop.style.width = width + 'px';
			drop.style.left = ( left - r.left ) + 'px';
			return;
		}

		if ( li.classList.contains( 'lu3g-mb__item--level-0' ) ) {
			li.classList.remove( 'is-align-right' );
			if ( r.left + drop.offsetWidth > vw - 8 ) {
				li.classList.add( 'is-align-right' );
			}
		} else {
			li.classList.remove( 'is-flip' );
			if ( r.right + drop.offsetWidth + 8 > vw ) {
				li.classList.add( 'is-flip' );
			}
		}
	};

	/* -------------------------------------------------------------------------------------------- avvio */

	function init( scope ) {
		var base = scope && scope.querySelectorAll ? scope : document;
		var nodes = base.matches && base.matches( '.lu3g-mm' ) ? [ base ] : base.querySelectorAll( '.lu3g-mm' );
		Array.prototype.forEach.call( nodes, function ( node ) {
			if ( ! node.lu3gMobileMenu ) {
				node.lu3gMobileMenu = new MobileMenu( node );
			}
		} );
		var bars = base.matches && base.matches( '.lu3g-mb' ) ? [ base ] : base.querySelectorAll( '.lu3g-mb' );
		Array.prototype.forEach.call( bars, function ( node ) {
			if ( ! node.lu3gMenuBar ) {
				node.lu3gMenuBar = new MenuBar( node );
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
