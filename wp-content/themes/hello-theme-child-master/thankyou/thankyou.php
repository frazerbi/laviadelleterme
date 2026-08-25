<?php
/**
 * Pagina order-received (thank you)
 *
 * Titolo e notice coerenti con lo stato reale del pagamento, box "Completa il
 * pagamento" e polling che copre la race fra il redirect di ritorno del gateway
 * e il suo webhook asincrono. Il badge per riga ordine sta in
 * booking-status/booking-status.php: compare anche su order-pay.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Cambia il titolo H1 Elementor hardcoded "Pagamento" → "Ordine ricevuto!" lato server,
// solo se l'ordine risulta davvero pagato (altrimenti resterebbe "successo" anche per ordini in attesa di pagamento)
add_filter( 'elementor/widget/render_content', function ( $content, $widget ) {
	// Il filtro Elementor gira su qualsiasi pagina: is_wc_endpoint_url() esiste solo con
	// WooCommerce attivo.
	if ( ! function_exists( 'is_wc_endpoint_url' ) ) {
		return $content;
	}

	if ( ! is_wc_endpoint_url( 'order-received' ) ) {
		return $content;
	}

	// Sottotitolo hardcoded del widget Elementor ("Completa il pagamento…"): sulla
	// order-received non ha senso. Rimosso qui e non più via CSS/JS: il display:none
	// lato client lo faceva comparire per un istante prima di sparire.
	if ( 'text-editor' === $widget->get_name() ) {
		return false !== strpos( $content, 'Completa il pagamento' ) ? '' : $content;
	}

	if ( 'heading' !== $widget->get_name() ) {
		return $content;
	}

	// Nessun controllo su is_user_logged_in(): la pagina order-received è la stessa anche
	// per gli ordini ospite (l'accesso è già protetto da WooCommerce con la order key), e
	// con quel gate l'heading restava il "Pagamento" hardcoded del widget Elementor.
	$order_id = absint( get_query_var( 'order-received' ) );
	$order    = $order_id ? wc_get_order( $order_id ) : false;

	$titolo = laviadelleterme_order_is_confirmed( $order )
		? 'Ordine ricevuto!'
		: 'Ordine in attesa di pagamento';

	// Si sostituisce solo il nodo di testo uguale esattamente a "Pagamento": uno
	// str_replace secco colpirebbe qualsiasi heading della pagina che contenga quella
	// parola (es. "Metodo di Pagamento").
	$replaced = preg_replace( '/>\s*Pagamento\s*</u', '>' . $titolo . '<', $content, 1 );

	return null === $replaced ? $content : $replaced;
}, 10, 2 );

// Cambia il notice generico WooCommerce "Grazie. Il tuo ordine è stato ricevuto."
// quando il pagamento non risulta ancora confermato
add_filter( 'woocommerce_thankyou_order_received_text', function ( $text, $order ) {
	if ( ! laviadelleterme_order_is_confirmed( $order ) ) {
		return 'Il tuo ordine è stato ricevuto, ma il pagamento non risulta ancora completato.';
	}
	return $text;
}, 10, 2 );

// Endpoint AJAX per il polling di stato (vedi sotto): usato solo dalla thank-you page quando
// l'ordine risulta ancora "in attesa" ma necessita di pagamento. La order key va validata come
// fa il webhook del gateway stesso ($order->key_is_valid()), altrimenti chiunque potrebbe
// interrogare lo stato di un ordine arbitrario passando solo un order_id.
add_action( 'wp_ajax_laviadelleterme_check_order_confirmed', 'laviadelleterme_ajax_check_order_confirmed' );
add_action( 'wp_ajax_nopriv_laviadelleterme_check_order_confirmed', 'laviadelleterme_ajax_check_order_confirmed' );
function laviadelleterme_ajax_check_order_confirmed() {
	check_ajax_referer( 'laviadelleterme_order_status', 'nonce' );

	$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
	$key      = isset( $_POST['key'] ) ? wc_clean( wp_unslash( $_POST['key'] ) ) : '';
	$order    = $order_id ? wc_get_order( $order_id ) : false;

	if ( ! $order || ! $order->key_is_valid( $key ) ) {
		wp_send_json_error( null, 403 );
	}

	wp_send_json_success( array( 'confirmed' => laviadelleterme_order_is_confirmed( $order ) ) );
}

// Box "Completa il pagamento" + polling automatico dello stato ordine, SOLO quando l'ordine
// è ancora "in attesa" ma richiede davvero un'azione (niente per gli ordini già confermati né
// per quelli che non necessitano pagamento, es. bonifico). Il polling copre la race condition
// per cui il browser torna sulla thank-you page prima che il webhook asincrono del gateway
// (richiesta separata dal redirect del browser) abbia aggiornato lo stato dell'ordine:
// diagnosticata su Mollie ma vale per qualsiasi gateway asincrono, Stripe e Satispay inclusi.
// Se nel frattempo l'ordine viene confermato la pagina si ricarica da sola; per chi resta
// davvero in attesa a lungo (es. bonifico) il polling si ferma da solo dopo ~30s.
add_action( 'woocommerce_thankyou', function ( $order_id ) {
	$order = wc_get_order( $order_id );

	if ( ! $order || laviadelleterme_order_is_confirmed( $order ) || ! $order->needs_payment() ) {
		return;
	}

	echo '<div class="thankyou-complete-payment">'
		. '<p class="thankyou-complete-payment__text">Il pagamento non è ancora andato a buon fine. Completa il pagamento per confermare l\'ordine.</p>'
		. '<a href="' . esc_url( $order->get_checkout_payment_url() ) . '" class="button thankyou-complete-payment__button">Completa il pagamento</a>'
		. '</div>';

	$data = array(
		'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
		'orderId'  => $order->get_id(),
		'orderKey' => $order->get_order_key(),
		'nonce'    => wp_create_nonce( 'laviadelleterme_order_status' ),
	);
	?>
	<script>
	( function ( cfg ) {
		var attempts    = 0;
		var maxAttempts = 12; // ~30s totali (intervallo 2.5s), poi si ferma senza refresh

		function poll() {
			attempts++;

			var body = new URLSearchParams( {
				action:   'laviadelleterme_check_order_confirmed',
				order_id: cfg.orderId,
				key:      cfg.orderKey,
				nonce:    cfg.nonce
			} );

			fetch( cfg.ajaxUrl, { method: 'POST', body: body } )
				.then( function ( res ) { return res.json(); } )
				.then( function ( data ) {
					if ( data && data.success && data.data && data.data.confirmed ) {
						window.location.reload();
						return;
					}
					if ( attempts < maxAttempts ) {
						setTimeout( poll, 2500 );
					}
				} )
				.catch( function () {
					if ( attempts < maxAttempts ) {
						setTimeout( poll, 2500 );
					}
				} );
		}

		setTimeout( poll, 2500 );
	} )( <?php echo wp_json_encode( $data ); ?> );
	</script>
	<?php
}, 20 );
