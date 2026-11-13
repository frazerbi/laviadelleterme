<?php
/**
 * Theme functions and definitions
 *
 * Qui stanno solo il foglio di stile del tema, i due helper di enqueue e l'elenco dei
 * moduli. Ogni cartella in features/ è una funzionalità completa: PHP, CSS, JS e il
 * proprio hook wp_enqueue_scripts con le condizioni su cui caricarli. In inc/ c'è il
 * codice trasversale che non appartiene a nessuna pagina.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Versione unica per tutti gli asset del tema: l'header Version di style.css
 * (generato: si modifica in scss/style.scss e si ricompila).
 * Va alzata a ogni modifica di un CSS/JS, altrimenti i browser tengono la copia in cache.
 *
 * @return string
 */
function laviadelleterme_theme_version() {
	static $version = null;

	if ( null === $version ) {
		$version = wp_get_theme()->get( 'Version' );
	}

	return $version;
}

/**
 * @param string   $handle
 * @param string   $file   Percorso relativo alla cartella del tema, es. 'features/checkout/checkout.css'.
 * @param string[] $deps
 */
function laviadelleterme_enqueue_style( $handle, $file, $deps = [] ) {
	wp_enqueue_style( $handle, get_theme_file_uri( $file ), $deps, laviadelleterme_theme_version() );
}

/**
 * Sempre in footer: nessuno script del tema serve prima del contenuto.
 *
 * @param string   $handle
 * @param string   $file   Percorso relativo alla cartella del tema.
 * @param string[] $deps
 */
function laviadelleterme_enqueue_script( $handle, $file, $deps = [] ) {
	wp_enqueue_script( $handle, get_theme_file_uri( $file ), $deps, laviadelleterme_theme_version(), true );
}

// Priorità 20, come gli enqueue dei moduli: dopo quello del parent (10), da cui dipende.
add_action( 'wp_enqueue_scripts', function () {
	// La dipendenza dal parent va dichiarata solo se quell'handle esiste davvero:
	// disattivando "Theme Style" nelle impostazioni di Hello Elementor l'handle non
	// viene registrato e WordPress salterebbe in silenzio tutto il foglio del child.
	$parent_style = wp_style_is( 'hello-elementor-theme-style', 'registered' )
		? [ 'hello-elementor-theme-style' ]
		: [];

	laviadelleterme_enqueue_style( 'hello-elementor-child-style', 'style.css', $parent_style );
}, 20 );

// Moduli. Se un foglio deve stare dopo un altro lo dichiara con $deps nel proprio
// modulo: l'ordine di queste righe non conta.
require_once __DIR__ . '/features/shop/shop.php';
require_once __DIR__ . '/features/promo-pages/promo-pages.php';
require_once __DIR__ . '/features/global/global.php';
require_once __DIR__ . '/features/controllo-codici/controllo-codici.php';
require_once __DIR__ . '/features/booking-status/booking-status.php';
require_once __DIR__ . '/features/newsletter/newsletter.php';
require_once __DIR__ . '/features/newsletter/mailup.php';
require_once __DIR__ . '/features/thankyou/thankyou.php';
require_once __DIR__ . '/features/my-account/my-account.php';
require_once __DIR__ . '/features/checkout/checkout.php';
require_once __DIR__ . '/features/order-pay/order-pay.php';
require_once __DIR__ . '/features/satispay/satispay.php';

// Codice trasversale, non legato a una pagina.
require_once __DIR__ . '/inc/ga4-elementor-compat.php';
require_once __DIR__ . '/inc/elementor-element-cache.php';
require_once __DIR__ . '/inc/performance-optimization.php';
