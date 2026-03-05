document.addEventListener( 'DOMContentLoaded', function () {

	// Sostituisce il titolo H1 Elementor hardcoded ("Pagamento" → "Ordine ricevuto!")
	var heading = document.querySelector( '.elementor-heading-title' );
	if ( heading ) {
		heading.textContent = 'Ordine ricevuto!';
	}

	// Nasconde il sottotitolo Elementor "Completa il pagamento..."
	document.querySelectorAll( '.elementor-widget-text-editor p' ).forEach( function ( p ) {
		if ( p.textContent.includes( 'Completa il pagamento' ) ) {
			p.closest( '.elementor-widget-text-editor' ).style.display = 'none';
		}
	} );

} );
