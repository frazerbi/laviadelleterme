<?php
/**
 * Badge di stato prenotazione / pagamento per riga ordine
 *
 * Si aggancia a woocommerce_order_item_meta_end, che NON è un hook di pagina:
 * il badge compare sulla order-received e nella tabella riepilogo di order-pay,
 * quindi non appartiene a nessuno dei due moduli. Qui vive anche l'unica
 * definizione di "ordine confermato", condivisa con thankyou/thankyou.php.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Stati ordine che indicano pagamento effettivamente concluso (dopo woocommerce_payment_complete).
// 'booked'/'not-booked' sono raggiunti solo passando per 'completed' (vedi Booking_Order_Status),
// quindi vanno inclusi esplicitamente: is_paid() da solo non li riconosce.
function laviadelleterme_order_is_confirmed( $order ) {
	return $order && $order->has_status( array( 'processing', 'completed', 'booked', 'not-booked' ) );
}

/**
 * Segnala se siamo dentro il rendering della tabella item di una email WooCommerce.
 * `woocommerce_email_before/after_order_table` avvolgono esattamente quella tabella
 * (templates/emails/email-order-details.php), quindi anche il nostro hook sugli item.
 *
 * @param bool|null $set true/false per impostare, null per leggere.
 * @return bool
 */
function laviadelleterme_rendering_email( $set = null ) {
	static $rendering = false;

	if ( null !== $set ) {
		$rendering = (bool) $set;
	}

	return $rendering;
}
add_action( 'woocommerce_email_before_order_table', function () { laviadelleterme_rendering_email( true ); }, 1 );
add_action( 'woocommerce_email_after_order_table', function () { laviadelleterme_rendering_email( false ); }, 999 );

/**
 * True solo nei due contesti in cui il badge ha senso ed è anche stilato: pagina
 * order-received e pagina order-pay (le stesse due condizioni con cui functions.php
 * accoda booking-status.css).
 *
 * Serve perché `woocommerce_order_item_meta_end` NON è un hook di pagina: WooCommerce lo
 * fa scattare anche in templates/emails/email-order-items.php (quindi dentro le email al
 * cliente e la "nuovo ordine" all'admin, che parte con ordine ancora da pagare) e in
 * templates/order/order-details-item.php (Il mio account → Visualizza ordine), dove il CSS
 * del badge non viene nemmeno caricato e resterebbe testo nudo.
 *
 * @return bool
 */
function laviadelleterme_show_booking_status_badge() {
	if ( is_admin() || laviadelleterme_rendering_email() || ! function_exists( 'is_checkout' ) ) {
		return false;
	}

	// wp_doing_ajax() esclude la POST `?wc-ajax=checkout`, dove is_checkout() sarebbe vera
	// ma l'unico output prodotto sono le email transazionali dell'ordine appena creato.
	if ( wp_doing_ajax() ) {
		return false;
	}

	return is_wc_endpoint_url( 'order-received' ) || is_checkout();
}

// Badge stato prenotazione dopo i meta di ogni item
add_action( 'woocommerce_order_item_meta_end', function( $item_id, $item, $order, $plain_text ) {
	if ( $plain_text || ! laviadelleterme_show_booking_status_badge() ) {
		return;
	}

	// _booking_id viene scritto in ordine già al checkout (prima di qualsiasi pagamento),
	// quindi va sempre incrociato con lo stato reale dell'ordine: senza pagamento confermato
	// né TermeGest né i codici licenza sono stati effettivamente assegnati.
	if ( ! laviadelleterme_order_is_confirmed( $order ) ) {
		echo '<p class="booking-status booking-status--awaiting">'
			. '<span class="booking-status__dot"></span>'
			. 'In attesa di pagamento'
			. '</p>';
		return;
	}

	$booking_id = $item->get_meta( '_booking_id' );

	if ( $booking_id ) {
		echo '<p class="booking-status booking-status--confirmed">'
			. '<span class="booking-status__dot"></span>'
			. 'Prenotazione confermata'
			. '</p>';
	} else {
		echo '<p class="booking-status booking-status--pending">'
			. '<span class="booking-status__dot"></span>'
			. 'Usa i codici per completare la prenotazione'
			. '</p>';
	}
}, 10, 4 );
