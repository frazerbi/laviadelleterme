<?php
/**
 * Pagine protette da password (PPWP)
 *
 * promo-pages.css è la base condivisa dei form PPWP, caricata ovunque perché PPWP può
 * proteggere qualsiasi pagina; stila anche la pagina ADAVA. promozioni-speciali.css
 * tiene solo le deviazioni di /promozioni-speciali/, l'unica con un conditional tag.
 * Il campo password in chiaro e in minuscolo lo fa features/global/script.js.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'wp_enqueue_scripts', function () {
	laviadelleterme_enqueue_style( 'promo-pages-style', 'features/promo-pages/promo-pages.css', [ 'hello-elementor-child-style' ] );

	if ( is_page( 'promozioni-speciali' ) ) {
		laviadelleterme_enqueue_style( 'promozioni-speciali-style', 'features/promo-pages/promozioni-speciali.css', [ 'promo-pages-style' ] );
	}
}, 20 );
