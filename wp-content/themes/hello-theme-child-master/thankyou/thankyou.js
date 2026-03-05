( function () {

	function fixThankYouPage() {
		// Cambia il titolo H1 Elementor hardcoded "Pagamento" → "Ordine ricevuto!"
		var heading = document.querySelector( '.elementor-heading-title' );
		if ( heading ) {
			heading.textContent = 'Ordine ricevuto!';
		}

		// Nasconde il sottotitolo "Completa il pagamento..."
		document.querySelectorAll( '.elementor-widget-text-editor p' ).forEach( function ( p ) {
			if ( p.textContent.indexOf( 'Completa il pagamento' ) !== -1 ) {
				p.closest( '.elementor-widget-text-editor' ).style.display = 'none';
			}
		} );

		// Riformatta indirizzo: "Via X, CAP Città Provincia"
		var address = document.querySelector( '.woocommerce-customer-details address' );
		if ( address ) {
			var nodes = [];
			var node = address.firstChild;
			while ( node ) {
				if ( node.nodeName === 'P' ) break;
				nodes.push( node );
				node = node.nextSibling;
			}

			var raw = '';
			nodes.forEach( function ( n ) {
				if ( n.nodeType === 3 ) raw += n.textContent;
				else if ( n.nodeName === 'BR' ) raw += '\n';
			} );

			var parts = raw.split( '\n' ).map( function ( p ) { return p.trim(); } ).filter( Boolean );

			if ( parts.length >= 4 ) {
				nodes.forEach( function ( n ) { address.removeChild( n ); } );

				var province = parts[ 4 ]
					? parts[ 4 ].charAt( 0 ).toUpperCase() + parts[ 4 ].slice( 1 ).toLowerCase()
					: '';
				var addrLine = parts[ 1 ] + ', ' + parts[ 2 ] + ' ' + parts[ 3 ] + ( province ? ' ' + province : '' );

				var firstP = address.querySelector( 'p' );

				var nameSpan = document.createElement( 'span' );
				nameSpan.className = 'address-customer-name';
				nameSpan.textContent = parts[ 0 ];

				var addrSpan = document.createElement( 'span' );
				addrSpan.className = 'address-customer-address';
				addrSpan.textContent = addrLine;

				address.insertBefore( addrSpan, firstP || null );
				address.insertBefore( nameSpan, firstP || null );
			}
		}

		// Classe dinamica su license-codes-table: is-booked / is-pending
		document.querySelectorAll( '.license-codes-table' ).forEach( function ( table ) {
			var cell = table.closest( '.product-name' );
			if ( ! cell ) return;
			var isBooked = cell.querySelector( '.thankyou-booking-status--confirmed' ) !== null;
			table.classList.add( isBooked ? 'is-booked' : 'is-pending' );
		} );
	}

	// Esegui sia su DOMContentLoaded che su window.load (fallback per Elementor async)
	document.addEventListener( 'DOMContentLoaded', fixThankYouPage );
	window.addEventListener( 'load', fixThankYouPage );

} )();
