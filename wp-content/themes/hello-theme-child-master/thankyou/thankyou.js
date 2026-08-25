( function () {

	function fixThankYouPage() {
		// Riformatta indirizzo: "Via X, CAP Città Provincia"
		// (il sottotitolo "Completa il pagamento…" è rimosso lato server dal filtro
		// elementor/widget/render_content in thankyou/thankyou.php)
		var address = document.querySelector( '.woocommerce-customer-details address' );

		// La funzione gira sia su DOMContentLoaded sia su window.load: senza questo
		// controllo il secondo giro rileggerebbe un indirizzo già riscritto.
		if ( address && ! address.querySelector( '.address-customer-name' ) ) {
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

				address.insertBefore( nameSpan, firstP || null );
				address.insertBefore( addrSpan, firstP || null );
			}
		}

		// Classe dinamica su license-codes-table: is-booked / is-pending
		document.querySelectorAll( '.license-codes-table' ).forEach( function ( table ) {
			var cell = table.closest( '.product-name' );
			if ( ! cell || table.classList.contains( 'is-booked' ) || table.classList.contains( 'is-pending' ) ) return;
			var isBooked = cell.querySelector( '.thankyou-booking-status--confirmed' ) !== null;
			table.classList.add( isBooked ? 'is-booked' : 'is-pending' );
		} );
	}

	// Esegui sia su DOMContentLoaded che su window.load (fallback per Elementor async)
	document.addEventListener( 'DOMContentLoaded', fixThankYouPage );
	window.addEventListener( 'load', fixThankYouPage );

} )();
