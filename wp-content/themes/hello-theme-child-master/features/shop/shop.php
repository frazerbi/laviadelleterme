<?php
/**
 * Catalogo, pagina prodotto e carrello
 *
 * Solo CSS. Caricato ovunque: le classi che stila arrivano dai contenuti Elementor e
 * da plugin di terze parti, non c'è un conditional tag affidabile su cui restringere.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'wp_enqueue_scripts', function () {
	laviadelleterme_enqueue_style( 'shop-style', 'features/shop/shop.css', [ 'hello-elementor-child-style' ] );
}, 20 );
