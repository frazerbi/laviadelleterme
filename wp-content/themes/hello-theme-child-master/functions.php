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

function hello_elementor_child_enqueue_scripts() {
	$version = wp_get_theme()->get( 'Version' );

	wp_enqueue_style('hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		[
			'hello-elementor-theme-style',
		],
		$version
	);

	// Carica un secondo foglio di stile personalizzato
	wp_enqueue_style('custom-style-menu',
		get_stylesheet_directory_uri() . '/assets/css/mobile-menu-style.css',
		[
			'hello-elementor-child-style',
		],
		$version
	);

	wp_enqueue_script('custom-js',
		get_stylesheet_directory_uri() . '/assets/js/script.js',
		[
			'jquery',
		],
		$version,
		true
	);

	wp_enqueue_style('controllo-codici-style',
		get_stylesheet_directory_uri() . '/controllo-codici/controllo-codici.css',
		[],
		$version
	);

	// Da qui in poi tutto dipende da WooCommerce: is_wc_endpoint_url() e is_checkout()
	// sono funzioni sue, chiamarle senza il plugin attivo è un fatal error.
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Notice WooCommerce (errore / info / conferma): compaiono su carrello, checkout,
	// order-pay, my-account e pagine prodotto, quindi lo stile è caricato ovunque.
	wp_enqueue_style('wc-notices-style',
		get_stylesheet_directory_uri() . '/assets/css/wc-notices.css',
		[],
		$version
	);

	// Aspetto dei campi carta dentro l'iframe Stripe: il plugin lo ricava dal
	// computed style di #wc-stripe-hidden-style-input, stampato su checkout,
	// order-pay e "aggiungi metodo di pagamento" nell'area account.
	wp_enqueue_style('stripe-upe-appearance-style',
		get_stylesheet_directory_uri() . '/assets/css/stripe-upe-appearance.css',
		[],
		$version
	);

	// Badge stato prenotazione: stampato su woocommerce_order_item_meta_end, quindi
	// presente sia su order-received sia nella tabella riepilogo di order-pay.
	// Stesse due condizioni di laviadelleterme_show_booking_status_badge().
	if ( is_wc_endpoint_url( 'order-received' ) || is_checkout() ) {
		wp_enqueue_style('booking-status-style',
			get_stylesheet_directory_uri() . '/assets/css/booking-status.css',
			[],
			$version
		);
	}

	if ( is_wc_endpoint_url( 'order-received' ) ) {
		wp_enqueue_style('thankyou-style',
			get_stylesheet_directory_uri() . '/thankyou/thankyou.css',
			[],
			$version
		);

		wp_enqueue_script('thankyou-js',
			get_stylesheet_directory_uri() . '/thankyou/thankyou.js',
			[],
			$version,
			true
		);
	}

	if ( is_checkout() ) {
		wp_enqueue_style('checkout-style',
			get_stylesheet_directory_uri() . '/checkout/checkout.css',
			[],
			$version
		);
	}
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_scripts', 20 );

require_once get_stylesheet_directory() . '/thankyou/thankyou.php';
require_once get_stylesheet_directory() . '/order-pay/order-pay.php';
require_once get_stylesheet_directory() . '/satispay/satispay.php';
require_once get_stylesheet_directory() . '/my-account/my-account.php';
require_once get_stylesheet_directory() . '/controllo-codici/controllo_codici_DB.php';
require_once get_stylesheet_directory() . '/controllo-codici/controllo_codici_DB_promo.php';
require_once get_stylesheet_directory() . '/ga4-elementor-compat.php';
require_once get_stylesheet_directory() . '/performance-optimization.php';
