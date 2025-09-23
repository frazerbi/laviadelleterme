<?php

function order_splitter($order) {
	$cats = array(49);
	$enableDebugLog = false;

	$logFilePath = ABSPATH . "/log_splitter.txt";
	if ($enableDebugLog) file_put_contents($logFilePath, "Inside order splitter \n");

	if (!($order instanceof WC_Order)) {
		$order = new WC_Order($order);
	}
	$order = wc_get_order($order);

	if (empty($order)) {
		return;
	}

	if ($enableDebugLog) {
		$order_id = $order->get_id();
		file_put_contents($logFilePath, "Got the order ID:" . print_r($order_id, true) . "\n");
	}

	$tmp_products = array();

	$orderItems = $order->get_items();
	$totalOrderItems = count($orderItems);

	foreach ($orderItems as $item_key => $item) {
		$item_data = $item->get_data();

		if (empty($item_data['product_id']) || empty($item_data['quantity']) || empty($item_data['subtotal'])) continue;

		$terms_post = get_the_terms($item_data['product_id'], 'product_cat');
		if ($enableDebugLog) file_put_contents($logFilePath, "term post are:" . print_r($terms_post, true) . "\n");

		if (($terms_post instanceof WP_Error) || ($terms_post === false)) continue;

		foreach ($terms_post as $term_cat) {
			if (in_array($term_cat->term_id, $cats)) {
				$tmp_products[] = array(
					'productId' => $item->get_product_id(),
					'qty' => $item_data['quantity'],
					'itemKeyToDelete' => $item_key,
					'subtotalToSubtract' => $item_data['subtotal']
				);
			}
		}
	}

	// If the main order is composed by all "creme" items
	if (!empty($tmp_products) && count($tmp_products) == $totalOrderItems) {
		// Only update the main order status
		$order->update_status('wc-completed_creme');
	// If the main order has some "creme" items and some "normal" items
	} else if (!empty($tmp_products) && count($tmp_products) != $totalOrderItems) {
		// Split the order into two orders
		$new_order = wc_create_order(array(
			'customer_id' => $order->get_customer_id(),
			'status' => 'wc-pending',
		));
		foreach ($tmp_products as $current) {
			// Add product to new order
			$product_to_add = wc_get_product($current['productId']);
			$new_order->add_product($product_to_add, $current['qty'], array());
		}
		$order_data = $order->get_data();
		/**
		 * @todo check existence of $order_data['shipping'] and related contents
		 */
		$shipping_address = array(
			'first_name' => $order_data['shipping']['first_name'],
			'last_name' => $order_data['shipping']['last_name'],
			'company' => $order_data['shipping']['company'],
			'email' => $order_data['shipping']['email'],
			'phone' => $order_data['shipping']['phone'],
			'address_1' => $order_data['shipping']['address_1'],
			'address_2' => $order_data['shipping']['address_2'],
			'city' => $order_data['shipping']['city'],
			'state' => $order_data['shipping']['state'],
			'postcode' => $order_data['shipping']['postcode'],
			'country' => $order_data['shipping']['country']
		);
		/**
		 * @todo check existence of $order_data['billing'] and related contents
		 */
		$billing_address = array(
			'first_name' => $order_data['billing']['first_name'],
			'last_name' => $order_data['billing']['last_name'],
			'company' => $order_data['billing']['company'],
			'email' => $order_data['billing']['email'],
			'phone' => $order_data['billing']['phone'],
			'address_1' => $order_data['billing']['address_1'],
			'address_2' => $order_data['billing']['address_2'],
			'city' => $order_data['billing']['city'],
			'state' => $order_data['billing']['state'],
			'postcode' => $order_data['billing']['postcode'],
			'country' => $order_data['billing']['country']
		);
		$new_order->set_address($billing_address, 'billing');
		$new_order->set_address($shipping_address, 'shipping');
		$new_order->update_status('wc-completed_creme');
		$new_order->add_order_note('Ordine creato automaticamente');
		$new_order->calculate_totals();
		$new_order->calculate_taxes();
		/**
		 * @todo check this "save()" operation is fine before proceed with the update of the main order
		 */
		$new_order->save();

		// Update the main order only after the save of the secondary order (in this way if an exception occours during the secondary order save operation the main order was not modified)
		$subtotal = $order->get_subtotal();
		foreach ($tmp_products as $current) {
			// Delete the product from the main order
			wc_delete_order_item($current['itemKeyToDelete']);
			// Update the subtotal of the main order
			$subtotal -= $current['subtotalToSubtract'];
		}
		$order->set_total($subtotal);
		$order->save();
		$order->update_status('wc-completed');
	// If the main order has only "normal" items
	} else {
		// Updated its status
		$order->update_status('wc-completed');
	}
}
// add_action('woocommerce_order_status_processing', 'order_splitter', 1, 1);
