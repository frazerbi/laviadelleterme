<?php
/**
 * Thank You page customization
 */

// Cambia il titolo WooCommerce endpoint
add_filter( 'woocommerce_endpoint_order-received_title', function() {
	return 'Ordine ricevuto!';
} );

// Cambia il titolo della pagina (H1 Elementor) quando si è sulla thank you page
add_filter( 'the_title', function( $title, $id ) {
	if ( is_wc_endpoint_url( 'order-received' ) && $id === wc_get_page_id( 'checkout' ) ) {
		return 'Ordine ricevuto!';
	}
	return $title;
}, 10, 2 );
