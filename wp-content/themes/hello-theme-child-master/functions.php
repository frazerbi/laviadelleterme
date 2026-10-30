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
 * Versione unica per tutti gli asset del tema: l'header Version di style.css.
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

// L'ordine dei require è l'ordine dei <link> in pagina, quindi del cascade a parità di
// specificità: i moduli agganciano tutti wp_enqueue_scripts a priorità 20 e WordPress li
// esegue nell'ordine in cui sono stati registrati. global/ viene dopo shop/ e promo-pages/
// perché wc-notices.css deve seguire shop.css: `.cont-prodotti a.button` e
// `.woocommerce-message a.button` pesano entrambi (0,2,1) e deve vincere il notice.
//
// I moduli che usano funzioni WooCommerce nel proprio enqueue (is_checkout(),
// is_wc_endpoint_url()…) controllano da sé class_exists( 'WooCommerce' ): chiamarle col
// plugin spento è un fatal error, e durante i deploy via FTP può capitare per qualche istante.
$laviadelleterme_moduli = [
	'features/shop/shop.php',
	'features/promo-pages/promo-pages.php',
	'features/global/global.php',
	'features/controllo-codici/controllo-codici.php',
	'features/booking-status/booking-status.php',
	'features/newsletter/newsletter.php',
	'features/newsletter/mailup.php',
	'features/thankyou/thankyou.php',
	'features/my-account/my-account.php',
	'features/checkout/checkout.php',
	'features/order-pay/order-pay.php',
	'features/satispay/satispay.php',
	'inc/ga4-elementor-compat.php',
	'inc/elementor-element-cache.php',
	'inc/performance-optimization.php',
];

foreach ( $laviadelleterme_moduli as $laviadelleterme_modulo ) {
	require_once get_theme_file_path( $laviadelleterme_modulo );
}
unset( $laviadelleterme_moduli, $laviadelleterme_modulo );
