<?php

declare(strict_types=1);

if (! \defined('PLUGIN_SKIANET_FILE')) {
    exit();
}

add_filter('woocommerce_add_to_cart_form_action', 'skianet_termegest_booking_add_to_cart_form_action', \PHP_INT_MAX);
function skianet_termegest_booking_add_to_cart_form_action(string $action): string
{
    if (
        ! doing_filter('wp_ajax_'.SKIANET_ACTION_GET_BOOK_DIALOG)
        && ! doing_filter('wp_ajax_nopriv_'.SKIANET_ACTION_GET_BOOK_DIALOG)
    ) {
        return $action;
    }

    return wc_get_cart_url();
}

add_action('wp_ajax_'.SKIANET_ACTION_BOOKING_ADD_CART, 'skianet_termegest_booking_add_to_cart_handler');
add_action('wp_ajax_nopriv_'.SKIANET_ACTION_BOOKING_ADD_CART, 'skianet_termegest_booking_add_to_cart_handler');
function skianet_termegest_booking_add_to_cart_handler(): void
{
    if (! empty($_REQUEST['add_to_cart'])) {
        $_REQUEST['add-to-cart'] = $_REQUEST['add_to_cart'];
        unset($_REQUEST['add_to_cart']);
    }

    if (! empty($_POST['add_to_cart'])) {
        $_POST['add-to-cart'] = $_POST['add_to_cart'];
        unset($_POST['add_to_cart']);
    }

    if (! empty($_GET['add_to_cart'])) {
        $_GET['add-to-cart'] = $_GET['add_to_cart'];
        unset($_GET['add_to_cart']);
    }

    add_filter('woocommerce_add_to_cart_redirect', '__return_false', \PHP_INT_MAX);
    add_filter('wp_redirect', '__return_false', \PHP_INT_MAX);
    update_option('woocommerce_cart_redirect_after_add', 'no');

    WC_Form_Handler::add_to_cart_action();

    update_option('woocommerce_cart_redirect_after_add', 'yes');

    WC_AJAX::get_refreshed_fragments();
}

add_filter('woocommerce_add_to_cart_fragments', 'skianet_termegest_booking_ajax_add_to_cart_add_fragments');
/**
 * @param string[] $fragments
 * @return string[]
 */
function skianet_termegest_booking_ajax_add_to_cart_add_fragments(array $fragments): array
{
    $all_notices = WC()->session->get('wc_notices', []);
    $notice_types = apply_filters('woocommerce_notice_types', ['error', 'success', 'notice']);

    ob_start();
    foreach ($notice_types as $notice_type) {
        if (wc_notice_count($notice_type) > 0) {
            wc_get_template(\sprintf('notices/%s.php', $notice_type), [
                'notices' => array_filter($all_notices[$notice_type]),
            ]);
        }
    }

    $fragments['notices_html'] = ob_get_clean();

    wc_clear_notices();

    return $fragments;
}

add_filter('woocommerce_quantity_input_args', 'skianet_termegest_booking_quantity_input_args', \PHP_INT_MAX, 2);
function skianet_termegest_booking_quantity_input_args(array $args, WC_Product $wcProduct): array
{
    if (
        ! doing_filter('wp_ajax_'.SKIANET_ACTION_GET_BOOK_DIALOG)
        && ! doing_filter('wp_ajax_nopriv_'.SKIANET_ACTION_GET_BOOK_DIALOG)
        && ! is_cart()
    ) {
        return $args;
    }

    $pars = skianet_termegest_booking_get_form_parameters();
    $isCustomProduct = false;

    if (is_cart()) {
        $cartContent = WC()->cart->get_cart();
        $cartProduct = array_filter(
            $cartContent,
            static fn (array $item): bool => (int) ($item['data']->get_type() === 'variation' ? $item['variation_id'] : $item['product_id']) === $wcProduct->get_id()
        );
        if (\count($cartProduct) !== 1) {
            return $args;
        }

        $cartProduct = array_shift($cartProduct);
        $isCustomProduct = ! empty($cartProduct[SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM['location']['key']])
            && ! empty($cartProduct[SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM['event']['key']]);
        if ($isCustomProduct) {
            $pars['qty'] = (int) $cartProduct['quantity'];
        }
    }

    if (empty($pars['qty']) && ! $isCustomProduct) {
        return $args;
    }

    $args['input_value'] = (int) $pars['qty'];
    $args['min_value'] = (int) $pars['qty'];
    $args['max_value'] = (int) $pars['qty'];
    if (is_cart()) {
        $args['max_value'] = $pars['qty'] + 0.01;
    }

    $args['readonly'] = true;
    $args['type'] = 'hidden';

    return $args;
}
