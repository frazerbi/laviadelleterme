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
	}

	// Esegui sia su DOMContentLoaded che su window.load (fallback per Elementor async)
	document.addEventListener( 'DOMContentLoaded', fixThankYouPage );
	window.addEventListener( 'load', fixThankYouPage );

} )();
