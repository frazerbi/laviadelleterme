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

// Classe sul <body> quando l'ordine non è confermato, per nascondere via CSS
// il box istruzioni Mollie solo in questo caso (altrimenti resta visibile)
add_filter( 'body_class', function ( $classes ) {
	if ( is_wc_endpoint_url( 'order-received' ) ) {
		$order_id = absint( get_query_var( 'order-received' ) );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! laviadelleterme_thankyou_order_is_confirmed( $order ) ) {
			$classes[] = 'thankyou-awaiting-payment';
		}
	}
	return $classes;
} );

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