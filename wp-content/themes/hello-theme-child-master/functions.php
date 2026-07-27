<?php
/**
 * Theme functions and definitions
 *
 * @package HelloElementorChild
 */
/**
 * Load child theme css and optional scripts
 *
 * @return void
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Versione di un asset del child theme basata sulla data di modifica del file,
 * cosi' il cache-busting e' automatico e non richiede di aggiornare a mano un
 * numero di versione a ogni deploy (utile visto che qui si fa anche upload FTP manuale).
 *
 * @param string $relative_path Percorso relativo alla root del child theme (es. '/style.css').
 * @return string
 */
function laviadelleterme_asset_version( $relative_path ) {
	$file = get_stylesheet_directory() . $relative_path;
	return file_exists( $file ) ? (string) filemtime( $file ) : '1.0.0';
}

function hello_elementor_child_enqueue_scripts() {
	wp_enqueue_style('hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		[
			'hello-elementor-theme-style',
		],
		laviadelleterme_asset_version( '/style.css' )
	);

	// Carica un secondo foglio di stile personalizzato
	wp_enqueue_style( 'custom-style-menu', get_stylesheet_directory_uri() . '/mobile-menu-style.css', ['hello-elementor-child-style'], laviadelleterme_asset_version( '/mobile-menu-style.css' ) );

	wp_enqueue_script( 'custom-js', get_stylesheet_directory_uri() . '/js/script.js', array( 'jquery' ), laviadelleterme_asset_version( '/js/script.js' ), true );

	wp_enqueue_style( 'controllo-codici-style', get_stylesheet_directory_uri() . '/controllo-codici/controllo-codici.css', [], laviadelleterme_asset_version( '/controllo-codici/controllo-codici.css' ) );

	if ( is_wc_endpoint_url( 'order-received' ) ) {
		wp_enqueue_style( 'thankyou-style', get_stylesheet_directory_uri() . '/thankyou/thankyou.css', [], laviadelleterme_asset_version( '/thankyou/thankyou.css' ) );
		wp_enqueue_script( 'thankyou-js', get_stylesheet_directory_uri() . '/thankyou/thankyou.js', [], laviadelleterme_asset_version( '/thankyou/thankyou.js' ), true );
	}

	if ( is_checkout() ) {
		wp_enqueue_style( 'checkout-style', get_stylesheet_directory_uri() . '/checkout/checkout.css', [], laviadelleterme_asset_version( '/checkout/checkout.css' ) );
	}
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_scripts', 20 );

require_once get_stylesheet_directory() . '/thankyou/thankyou.php';
require_once get_stylesheet_directory() . '/order-pay/order-pay.php';
require_once get_stylesheet_directory() . '/customize-my-account.php';
require_once get_stylesheet_directory() . '/controllo-codici/controllo_codici_DB.php';
require_once get_stylesheet_directory() . '/controllo-codici/controllo_codici_DB_promo.php';
require_once get_stylesheet_directory() . '/ga4-elementor-compat.php';
