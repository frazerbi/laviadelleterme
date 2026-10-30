document.addEventListener( 'DOMContentLoaded', function () {

	/* ------------------------------------------------------------------
	 * Pagine protette da password (plugin PPWP)
	 * Il campo viene mostrato in chiaro di proposito: la password è unica e
	 * condivisa, non è un dato personale. L'input viene normalizzato in
	 * minuscolo perché su mobile la prima lettera parte maiuscola.
	 * ------------------------------------------------------------------ */

	function preparaCampoPassword( selettore ) {
		var campo = document.querySelector( selettore );

		if ( ! campo ) {
			return;
		}

		campo.setAttribute( 'type', 'text' );
		campo.addEventListener( 'input', function ( event ) {
			event.target.value = event.target.value.toLowerCase().trim();
		} );
	}

	var campiPassword = {
		'/promozioni-speciali/': [
			'.page-id-10925 #pwbox-10925',
			'.page-id-10925 .ppw-password-input.ppw-pcp-pf-password-input'
		],
		'/associazione-albergatori-della-valle-daosta/': [
			'.page-id-1326 input.ppw-password-input.ppw-pcp-pf-password-input'
		]
	};

	( campiPassword[ window.location.pathname ] || [] ).forEach( preparaCampoPassword );


	/* ------------------------------------------------------------------
	 * Icona hamburger: resta sincronizzata con lo stato del menu off-canvas.
	 * L'apertura e la chiusura le gestisce Elementor, qui si aggiorna solo
	 * la classe .open sull'icona, senza toccare l'evento.
	 * ------------------------------------------------------------------ */

	var toggles = [
		document.querySelector( '.icon-menu-mobile-toggle' ),
		document.querySelector( '.icon-menu-mobile-toggle-sticky' )
	].filter( Boolean );

	var offCanvasContainers = document.querySelectorAll( '[id^="off-canvas-"]' );
	var isMenuOpen = false;

	function aggiornaIconaMenu( isOpen ) {
		isMenuOpen = isOpen;

		toggles.forEach( function ( toggle ) {
			toggle.classList.toggle( 'open', isOpen );
		} );
	}

	function leggiStatoMenu() {
		if ( ! offCanvasContainers.length ) {
			return;
		}

		aggiornaIconaMenu( offCanvasContainers[ 0 ].getAttribute( 'aria-hidden' ) === 'false' );
	}

	if ( toggles.length && offCanvasContainers.length ) {
		toggles.forEach( function ( toggle ) {
			// Nessun preventDefault/stopPropagation: l'evento deve arrivare a Elementor.
			// Il ritardo serve perché lo stato lo scrive Elementor dopo il click; resta
			// come rete di sicurezza, la sincronizzazione vera la fa il MutationObserver.
			toggle.addEventListener( 'click', function () {
				setTimeout( leggiStatoMenu, 100 );
			} );
		} );

		if ( 'MutationObserver' in window ) {
			offCanvasContainers.forEach( function ( container ) {
				new MutationObserver( function () {
					var nuovoStato = container.getAttribute( 'aria-hidden' ) === 'false';

					if ( nuovoStato !== isMenuOpen ) {
						aggiornaIconaMenu( nuovoStato );
					}
				} ).observe( container, {
					attributes: true,
					attributeFilter: [ 'aria-hidden', 'class' ]
				} );
			} );
		}

		leggiStatoMenu();
	}


	/* ------------------------------------------------------------------
	 * Secondo header: compare dopo 250px di scroll.
	 * Listener passivo e scrittura solo al cambio di stato: prima girava a
	 * ogni evento di scroll toccando le classi ogni volta.
	 * ------------------------------------------------------------------ */

	var secondHeader = document.getElementById( 'header_main_sub_container' );

	if ( secondHeader ) {
		var headerVisibile = null;

		var aggiornaSecondHeader = function () {
			var mostra = window.scrollY >= 250;

			if ( mostra === headerVisibile ) {
				return;
			}

			headerVisibile = mostra;
			secondHeader.classList.toggle( 'hidden', ! mostra );
			secondHeader.classList.toggle( 'show', mostra );
		};

		window.addEventListener( 'scroll', aggiornaSecondHeader, { passive: true } );
		aggiornaSecondHeader();
	}

} );
