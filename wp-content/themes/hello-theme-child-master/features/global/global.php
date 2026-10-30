<?php
/**
 * Asset presenti su tutto il sito
 *
 * Menu mobile e off-canvas, script.js, carosello immagini di Elementor e notice
 * WooCommerce. Stanno qui e non in un modulo di pagina perché il markup che stilano
 * lo stampano Elementor o WooCommerce su qualsiasi pagina: non c'è un conditional
 * tag su cui restringere l'enqueue.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'wp_enqueue_scripts', function () {
	laviadelleterme_enqueue_style( 'custom-style-menu', 'features/global/mobile-menu.css', [ 'hello-elementor-child-style' ] );

	// Caroselli immagini di Elementor: il markup lo stampa il widget, quindi può
	// comparire su qualsiasi pagina.
	laviadelleterme_enqueue_style( 'elementor-carousel-style', 'features/global/elementor-carousel.css', [ 'hello-elementor-child-style' ] );

	laviadelleterme_enqueue_script( 'custom-js', 'features/global/script.js' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Notice WooCommerce (errore / info / conferma): compaiono su carrello, checkout,
	// order-pay, my-account e pagine prodotto, quindi lo stile è caricato ovunque.
	laviadelleterme_enqueue_style( 'wc-notices-style', 'features/global/wc-notices.css' );
}, 20 );
