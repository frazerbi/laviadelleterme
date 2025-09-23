<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly for security
}

function process_order_status($order) {
    
    if (!$order) {
        error_log('Order ID is not valid in process_order_status');
        return;
    }

    $order_data = wc_get_order($order);
    if (empty($order_data)) {
        error_log('Order data is empty for order ID: ' . $order);
        return;
    }

    $order_status = $order_data->get_status();
    
    if ($order_status === 'booked') {
        send_booking_details($order);
    } elseif ($order_status === 'not-booked') {
        coupon_sent_order_status_not_booked($order);
    } else {
        error_log("Order #{$order} has status '{$order_status}' which is not 'booked' or 'not-booked'");
    }
}

// Trigger email when order status changes to "not-booked"
add_action('woocommerce_thankyou', 'process_order_status');


