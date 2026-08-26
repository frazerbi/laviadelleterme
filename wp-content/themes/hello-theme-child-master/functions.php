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

	// La dipendenza dal parent va dichiarata solo se quell'handle esiste davvero:
	// disattivando "Theme Style" nelle impostazioni di Hello Elementor l'handle non
	// viene registrato e WordPress salterebbe in silenzio tutto il foglio del child.
	$parent_style = wp_style_is( 'hello-elementor-theme-style', 'registered' )
		? [ 'hello-elementor-theme-style' ]
		: [];

	wp_enqueue_style('hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		$parent_style,
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

	// Catalogo/prodotto/carrello e pagine protette da password: le classi che
	// stilano arrivano dai contenuti Elementor e da plugin di terze parti, non
	// c'è un conditional tag affidabile su cui restringere l'enqueue.
	wp_enqueue_style('shop-style',
		get_stylesheet_directory_uri() . '/assets/css/shop.css',
		[
			'hello-elementor-child-style',
		],
		$version
	);

	wp_enqueue_style('promo-pages-style',
		get_stylesheet_directory_uri() . '/assets/css/promo-pages.css',
		[
			'hello-elementor-child-style',
		],
		$version
	);

	// Promozioni speciali: qui il conditional tag c'è (la pagina è una sola),
	// quindi le personalizzazioni di questa pagina stanno in un foglio a parte
	// caricato solo qui, dipendente dalla base condivisa dei form PPWP.
	if ( is_page( 'promozioni-speciali' ) ) {
		wp_enqueue_style('promozioni-speciali-style',
			get_stylesheet_directory_uri() . '/promozioni-speciali/promozioni-speciali.css',
			[
				'promo-pages-style',
			],
			$version
		);
	}

	wp_enqueue_script('custom-js',
		get_stylesheet_directory_uri() . '/assets/js/script.js',
		[],
		$version,
		true
	);

	// Solo registrato: lo carica laviadelleterme_render_controllo_codici() quando lo
	// shortcode è effettivamente a video, invece di pesare su ogni pagina del sito.
	wp_register_style('controllo-codici-style',
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
	// Stesse due condizioni del gate laviadelleterme_show_booking_status_badge().
	if ( is_wc_endpoint_url( 'order-received' ) || is_checkout() ) {
		wp_enqueue_style('booking-status-style',
			get_stylesheet_directory_uri() . '/booking-status/booking-status.css',
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

	// Area account: is_account_page() copre dashboard e password dimenticata,
	// ma NON la registrazione, che qui è una pagina a sé (vedi i filtri
	// login_url / register_url in my-account/my-account.php).
	if ( is_account_page() || is_page( 'login-e-registrazione' ) ) {
		wp_enqueue_style('my-account-style',
			get_stylesheet_directory_uri() . '/my-account/my-account.css',
			[],
			$version
		);

		// Password dimenticata: markup e impaginazione a sé rispetto al resto
		// dell'area account, quindi foglio dedicato e caricato solo qui.
		if ( is_wc_endpoint_url( 'lost-password' ) ) {
			wp_enqueue_style('lost-password-style',
				get_stylesheet_directory_uri() . '/my-account/lost-password.css',
				[
					'my-account-style',
				],
				$version
			);
		}
	}

	// is_checkout() è vero sia sulla checkout normale sia su order-pay: è lo stesso
	// template WooCommerce e non esiste una body class che le distingua (per questo
	// order-pay.css si aggancia a form#order_review). is_checkout_pay_page() era
	// stato provato per un enqueue più stretto ma il <link> non veniva stampato.
	if ( is_checkout() ) {
		wp_enqueue_style('checkout-style',
			get_stylesheet_directory_uri() . '/checkout/checkout.css',
			[],
			$version
		);

		wp_enqueue_style('order-pay-style',
			get_stylesheet_directory_uri() . '/order-pay/order-pay.css',
			[
				'checkout-style',
			],
			$version
		);
	}
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_scripts', 20 );

require_once get_stylesheet_directory() . '/booking-status/booking-status.php';
require_once get_stylesheet_directory() . '/thankyou/thankyou.php';
require_once get_stylesheet_directory() . '/order-pay/order-pay.php';
require_once get_stylesheet_directory() . '/satispay/satispay.php';
require_once get_stylesheet_directory() . '/my-account/my-account.php';
require_once get_stylesheet_directory() . '/controllo-codici/controllo-codici.php';
require_once get_stylesheet_directory() . '/ga4-elementor-compat.php';
require_once get_stylesheet_directory() . '/elementor-element-cache.php';
require_once get_stylesheet_directory() . '/performance-optimization.php';
