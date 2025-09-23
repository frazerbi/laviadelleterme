<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly for security
}


function send_codes_to_termegest($order_id) {
    global $wpdb;

    // Early validation
    if ( empty( $order_id ) ) {
        return;
    }

    // Get order
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }  

    // Get user
    $user = wp_get_current_user();
    if ( ! $user instanceof WP_User ) {
        return;
    }

    $items = $order->get_items();  
    // $tolog .= print_r($items, true)."\n";
    foreach ( $items as $item ) {
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $quantity = $item->get_quantity();
        $item_total = $item->get_total();


        if ($variation_id == 0) {
            $tickets = $wpdb->get_results(
            $wpdb->prepare("SELECT license_code1 FROM {$wpdb->wc_ld_license_codes} WHERE order_id = %d AND product_id = %d ", array($order_id, $product_id))
            );
        } else {
           $tickets = $wpdb->get_results(
            $wpdb->prepare("SELECT license_code1 FROM {$wpdb->wc_ld_license_codes} WHERE order_id = %d AND product_id = %d ", array($order_id, $variation_id))
            );
        }

        foreach ($tickets as $tick) {
            $codice_ingresso = $tick->license_code1;

            $tot = (float)($item_total / $quantity);

            $url = "https://www.termegest.it/setinfo.asmx?WSDL";
            $client = new SoapClient($url, array('cache_wsdl' => WSDL_CACHE_NONE));
            $res = $client->SetVenduto(array('codice' => $codice_ingresso, 'prezzo' =>  $tot, 'nome' => $user->user_login, 'email' => $user->user_email, 'security' => 'qpoz79nt1z3p2vcllpt2iqnz66c7zk3'));
            $result = $res->SetVendutoResult;

        }
    }
}
add_action('woocommerce_thankyou', 'send_codes_to_termegest', 1, 1);



