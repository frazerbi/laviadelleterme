<?php
/**
 * Checkout e campi di pagamento
 *
 * Solo CSS. checkout.css tiene ciò che checkout normale e order-pay condividono
 * (notice, metodi di pagamento, campi Stripe, termini, pulsante d'ordine); quello che
 * vale solo per order-pay sta in features/order-pay/.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Aspetto dei campi carta dentro l'iframe Stripe: il plugin lo ricava dal
	// computed style di #wc-stripe-hidden-style-input, stampato su checkout,
	// order-pay e "aggiungi metodo di pagamento" nell'area account. Per questo è
	// caricato ovunque e non solo su is_checkout().
	laviadelleterme_enqueue_style( 'stripe-upe-appearance-style', 'features/checkout/stripe-upe-appearance.css' );

	// is_checkout() è vero sia sulla checkout normale sia su order-pay: è lo stesso
	// template WooCommerce e non esiste una body class che le distingua (per questo
	// order-pay.css si aggancia a form#order_review). is_checkout_pay_page() era
	// stato provato per un enqueue più stretto ma il <link> non veniva stampato.
	if ( is_checkout() ) {
		laviadelleterme_enqueue_style( 'checkout-style', 'features/checkout/checkout.css' );
	}
}, 20 );
