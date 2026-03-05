<?php
/**
 * Thank You page customization
 */

// Cambia il titolo dell'endpoint WooCommerce (breadcrumb, <title> del browser)
add_filter( 'woocommerce_endpoint_order-received_title', function() {
	return 'Ordine ricevuto!';
} );

// Il titolo H1 Elementor è hardcoded nel widget → viene cambiato via JS (thankyou.js)
