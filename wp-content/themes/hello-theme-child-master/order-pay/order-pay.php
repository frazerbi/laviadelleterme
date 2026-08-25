<?php
/**
 * Order-pay page (checkout/order-pay/...): evidenzia e porta a schermo la checkbox
 * dei termini quando è quella a bloccare l'invio del pagamento.
 */

/**
 * True se questa richiesta è un tentativo di pagamento sulla pagina order-pay
 * fallito perché la checkbox dei termini non è stata spuntata.
 *
 * WooCommerce in questo caso non fa redirect: rimostra la stessa pagina con il
 * proprio errore, quindi $_POST è ancora disponibile durante il render.
 */
function laviadelleterme_order_pay_terms_missing() {
	if ( ! function_exists( 'is_checkout_pay_page' ) || ! is_checkout_pay_page() ) {
		return false;
	}

	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['woocommerce_pay'] ) ) {
		return false;
	}

	if ( ! function_exists( 'wc_terms_and_conditions_checkbox_enabled' ) || ! wc_terms_and_conditions_checkbox_enabled() ) {
		return false;
	}

	return empty( $_POST['terms'] );
}

// Classe sul body usata da checkout.css per colorare di rosso la riga dei termini.
add_filter( 'body_class', function ( $classes ) {
	if ( laviadelleterme_order_pay_terms_missing() ) {
		$classes[] = 'order-pay-terms-error';
	}

	return $classes;
} );

// Porta l'utente sulla checkbox: la pagina si ricarica in cima, con l'errore
// fuori schermo su desktop (form a due colonne) e ancora di più su mobile.
add_action( 'wp_footer', function () {
	if ( ! laviadelleterme_order_pay_terms_missing() ) {
		return;
	}
	?>
	<script>
	( function () {
		var focusTerms = function () {
			var checkbox = document.getElementById( 'terms' );
			if ( ! checkbox ) {
				return;
			}

			var row = checkbox.closest( '.form-row' ) || checkbox;
			row.scrollIntoView( { behavior: 'smooth', block: 'center' } );

			// preventScroll: lo scroll lo gestisce scrollIntoView qui sopra,
			// senza il focus salterebbe di colpo annullando l'animazione.
			try {
				checkbox.focus( { preventScroll: true } );
			} catch ( e ) {
				checkbox.focus();
			}

			// L'evidenziazione rossa sparisce appena l'utente accetta.
			checkbox.addEventListener( 'change', function () {
				if ( checkbox.checked ) {
					document.body.classList.remove( 'order-pay-terms-error' );
				}
			} );
		};

		if ( 'loading' === document.readyState ) {
			document.addEventListener( 'DOMContentLoaded', focusTerms );
		} else {
			focusTerms();
		}
	} )();
	</script>
	<?php
}, 20 );
