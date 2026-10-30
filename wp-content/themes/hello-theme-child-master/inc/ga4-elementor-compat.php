<?php
/**
 * GA4 Elementor compatibility — fallback for events that rely on WooCommerce
 * template hooks bypassed when Elementor Pro replaces the WC template via
 * template_include.
 *
 * Each fallback uses did_action() to guard against double-tracking: if the
 * standard WC hook already fired (classic WC or blocks), the fallback does
 * nothing. Runs at wp_footer priority 5, before print_tracking_calls (prio 10).
 *
 * Events covered:
 *   - view_item      → woocommerce_after_single_product_summary never fires on
 *                       Elementor single-product templates.
 *   - view_item_list → woocommerce_product_loop_end never fires when Elementor
 *                       renders the shop/archive with its own product widgets
 *                       instead of the WC loop. Fallback reads products directly
 *                       from the global WP_Query.
 *
 * Events NOT covered (safe or unfixable):
 *   - select_item: injects click listeners bound to WC loop DOM (.products
 *     .post-{id} a). Elementor product widgets use a different DOM structure;
 *     no generic fallback is possible without a custom Elementor widget adapter.
 *   - view_cart, begin_checkout, add_shipping_info, add_payment_info,
 *     provide_billing_email, view_account, view_order, view_sign_up:
 *     safe — fire inside WC shortcodes that Elementor embeds unchanged.
 *   - All data/action events (login, add_to_cart, purchase, refund, etc.):
 *     safe — no dependency on which template rendered the page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Fallback: view_item on Elementor single-product pages.
 */
add_action( 'wp_footer', function () {

	if (
		! is_product() ||
		did_action( 'woocommerce_after_single_product_summary' ) > 0 ||
		! function_exists( 'wc_google_analytics_pro' )
	) {
		return;
	}

	$plugin = wc_google_analytics_pro();
	if ( ! $plugin || ! method_exists( $plugin, 'get_tracking_instance' ) ) {
		return;
	}

	$event = $plugin->get_tracking_instance()->get_event_tracking_instance()->get_event( 'view_item' );
	if ( ! $event || ! $event->is_enabled() ) {
		return;
	}

	global $product;
	if ( ! $product ) {
		$product = wc_get_product( get_the_ID() );
	}

	if ( $product ) {
		$event->track( $product );
	}

}, 5 );


/**
 * Fallback: view_item_list on Elementor shop/archive pages.
 *
 * Reads products directly from the global WP_Query, which WordPress populates
 * regardless of which template Elementor uses. The native path (WC loop) builds
 * the product list incrementally via woocommerce_before_shop_loop_item and then
 * fires the event on woocommerce_product_loop_end; if that loop never ran, the
 * query posts are the next best source of truth.
 */
add_action( 'wp_footer', function () {

	if (
		! ( is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy() ) ||
		did_action( 'woocommerce_product_loop_end' ) > 0 ||
		! function_exists( 'wc_google_analytics_pro' )
	) {
		return;
	}

	global $wp_query;
	if ( empty( $wp_query->posts ) ) {
		return;
	}

	$products = array_filter( array_map( 'wc_get_product', wp_list_pluck( $wp_query->posts, 'ID' ) ) );
	if ( empty( $products ) ) {
		return;
	}

	$plugin = wc_google_analytics_pro();
	if ( ! $plugin || ! method_exists( $plugin, 'get_tracking_instance' ) ) {
		return;
	}

	$event = $plugin->get_tracking_instance()->get_event_tracking_instance()->get_event( 'view_item_list' );
	if ( ! $event || ! $event->is_enabled() ) {
		return;
	}

	$event->track( $products );

}, 5 );
