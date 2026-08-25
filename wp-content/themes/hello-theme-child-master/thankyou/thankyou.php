<?php
/**
 * Thank You page customization
 */

// Stati ordine che indicano pagamento effettivamente concluso (dopo woocommerce_payment_complete).
// 'booked'/'not-booked' sono raggiunti solo passando per 'completed' (vedi Booking_Order_Status),
// quindi vanno inclusi esplicitamente: is_paid() da solo non li riconosce.
function laviadelleterme_thankyou_order_is_confirmed( $order ) {
	return $order && $order->has_status( array( 'processing', 'completed', 'booked', 'not-booked' ) );
}

// Cambia il titolo H1 Elementor hardcoded "Pagamento" → "Ordine ricevuto!" lato server,
// solo se l'ordine risulta davvero pagato (altrimenti resterebbe "successo" anche per ordini in attesa di pagamento)
add_filter( 'elementor/widget/render_content', function ( $content, $widget ) {
	if ( $widget->get_name() === 'heading' && is_wc_endpoint_url( 'order-received' ) && is_user_logged_in() ) {
		$order_id = absint( get_query_var( 'order-received' ) );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( laviadelleterme_thankyou_order_is_confirmed( $order ) ) {
			$content = str_replace( 'Pagamento', 'Ordine ricevuto!', $content );
		} else {
			$content = str_replace( 'Pagamento', 'Ordine in attesa di pagamento', $content );
		}
	}
	return $content;
}, 10, 2 );

// Cambia il notice generico WooCommerce "Grazie. Il tuo ordine è stato ricevuto."
// quando il pagamento non risulta ancora confermato
add_filter( 'woocommerce_thankyou_order_received_text', function ( $text, $order ) {
	if ( ! laviadelleterme_thankyou_order_is_confirmed( $order ) ) {
		return 'Il tuo ordine è stato ricevuto, ma il pagamento non risulta ancora completato.';
	}
	return $text;
}, 10, 2 );

// Badge stato prenotazione dopo i meta di ogni item
add_action( 'woocommerce_order_item_meta_end', function( $item_id, $item, $order, $plain_text ) {
	if ( $plain_text ) {
		return;
	}

	// _booking_id viene scritto in ordine già al checkout (prima di qualsiasi pagamento),
	// quindi va sempre incrociato con lo stato reale dell'ordine: senza pagamento confermato
	// né TermeGest né i codici licenza sono stati effettivamente assegnati.
	if ( ! laviadelleterme_thankyou_order_is_confirmed( $order ) ) {
		echo '<p class="thankyou-booking-status thankyou-booking-status--awaiting">'
			. '<span class="thankyou-booking-status__dot"></span>'
			. 'In attesa di pagamento'
			. '</p>';
		return;
	}

	$booking_id = $item->get_meta( '_booking_id' );

	if ( $booking_id ) {
		echo '<p class="thankyou-booking-status thankyou-booking-status--confirmed">'
			. '<span class="thankyou-booking-status__dot"></span>'
			. 'Prenotazione confermata'
			. '</p>';
	} else {
		echo '<p class="thankyou-booking-status thankyou-booking-status--pending">'
			. '<span class="thankyou-booking-status__dot"></span>'
			. 'Usa i codici per completare la prenotazione'
			. '</p>';
	}
}, 10, 4 );

// Box con link per completare il pagamento, se l'ordine lo richiede ancora
add_action( 'woocommerce_thankyou', function ( $order_id ) {
	$order = wc_get_order( $order_id );

	if ( ! $order || laviadelleterme_thankyou_order_is_confirmed( $order ) || ! $order->needs_payment() ) {
		return;
	}

	echo '<div class="thankyou-complete-payment">'
		. '<p class="thankyou-complete-payment__text">Il pagamento non è ancora andato a buon fine. Completa il pagamento per confermare l\'ordine.</p>'
		. '<a href="' . esc_url( $order->get_checkout_payment_url() ) . '" class="button thankyou-complete-payment__button">Completa il pagamento</a>'
		. '</div>';
} );

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

	wp_send_json_success( array( 'confirmed' => laviadelleterme_thankyou_order_is_confirmed( $order ) ) );
}

// Polling automatico dello stato ordine, SOLO quando l'ordine è ancora "in attesa" ma richiede
// pagamento (stessa condizione del box "Completa il pagamento" sopra). Copre la race condition
// per cui il browser torna sulla thank-you page prima che il webhook asincrono del gateway
// (richiesta separata dal redirect del browser) abbia aggiornato lo stato dell'ordine:
// diagnosticata su Mollie ma vale per qualsiasi gateway asincrono, Stripe e Satispay inclusi.
// Se nel frattempo l'ordine viene confermato, la pagina si
// ricarica da sola. Nessun impatto per gli ordini già confermati (nessuno script stampato) né
// per chi resta davvero in attesa a lungo (es. bonifico): il polling si ferma da solo dopo ~30s.
add_action( 'woocommerce_thankyou', function ( $order_id ) {
	$order = wc_get_order( $order_id );

	if ( ! $order || laviadelleterme_thankyou_order_is_confirmed( $order ) || ! $order->needs_payment() ) {
		return;
	}

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