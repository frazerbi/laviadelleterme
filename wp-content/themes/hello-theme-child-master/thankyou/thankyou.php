<?php
/**
 * Thank You page customization
 */

// Cambia il titolo H1 Elementor hardcoded "Pagamento" → "Ordine ricevuto!" lato server
add_filter( 'elementor/widget/render_content', function ( $content, $widget ) {
	if ( $widget->get_name() === 'heading' && is_wc_endpoint_url( 'order-received' ) && is_user_logged_in() ) {
		$content = str_replace( 'Pagamento', 'Ordine ricevuto!', $content );
	}
	return $content;
}, 10, 2 );

// Badge stato prenotazione dopo i meta di ogni item
add_action( 'woocommerce_order_item_meta_end', function( $item_id, $item, $order, $plain_text ) {
	if ( $plain_text ) {
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
