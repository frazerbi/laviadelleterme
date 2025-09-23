<?php 

/**
 * Auto Complete all WooCommerce orders.
 */
function custom_woocommerce_auto_complete_order( $order_id ) { 
    if ( ! $order_id ) {
        error_log( 'WooCommerce Auto Complete: Order ID non valido' );
        return;
    }
    
    $order = wc_get_order( $order_id );
    
    if ( ! $order || is_wp_error( $order ) ) {
        error_log( 'WooCommerce Auto Complete: Impossibile ottenere l\'ordine ID ' . $order_id );
        return;
    }
    
    try {
        // Check if the order has been paid
        if ( $order->is_paid() ) {
            $old_status = $order->get_status();
            $result = $order->update_status( 'completed', __( 'Ordine completato automaticamente perché pagato.', 'custom-plugin-termeshop' ) );
            
            if ( is_wp_error( $result ) ) {
                error_log( 'WooCommerce Auto Complete: Errore nell\'aggiornamento dello stato dell\'ordine ' . $order_id . ': ' . $result->get_error_message() );
            } else {
                return;
            }
        } else {
            error_log( 'WooCommerce Auto Complete: Ordine ' . $order_id . ' non pagato, stato non aggiornato' );
            return;
        }
    } catch ( Exception $e ) {
        error_log( 'WooCommerce Auto Complete: Eccezione durante l\'elaborazione dell\'ordine ' . $order_id . ': ' . $e->getMessage() );
    }
}
add_action('woocommerce_order_status_processing', 'custom_woocommerce_auto_complete_order');

/**
 * Check all WooCommerce orders and move them to Booked or Not Booked.
 */
function move_order_status_to_booked($order_id) {

    if ( ! $order_id ) {
        error_log( 'WooCommerce Auto Complete: Order ID non valido' );
        return;
    }
    
    $order = wc_get_order( $order_id );
    
    if ( ! $order || is_wp_error( $order ) ) {
        error_log( 'WooCommerce Auto Complete: Impossibile ottenere l\'ordine ID ' . $order_id );
        return;
    }

    if ( $order->is_paid() ) {
         // Check if the order status is 'completed'
        if ($order && $order->get_status() === 'completed') {
            
            // Retrieve the meta fields associated with the order
            $custom_id = get_post_meta($order_id, 'skn-custom-id', true);
            $custom_location = get_post_meta($order_id, 'skn-custom-location', true);
            $custom_event = get_post_meta($order_id, 'skn-custom-event', true);
            $custom_qty = get_post_meta($order_id, 'skn-custom-qty', true);
            
            // Check if all required meta fields exist and are not empty
            if (!empty($custom_id) && !empty($custom_location) && !empty($custom_event) && !empty($custom_qty)) {
                // Update the order status to 'booked'
                $order->update_status('booked', __('Order status changed to Booked.', 'custom-plugin-termeshop'));
            }
            if ( empty($custom_id) &&  empty($custom_location) &&  empty($custom_event) &&  empty($custom_qty)) {
                // Update the order status to 'booked'
                $order->update_status('not-booked', __('Order status changed to Not Booked.', 'custom-plugin-termeshop'));
            }
        }
    }

}
// Automatically change order status from 'Completed' to 'Booked' if specific meta fields exist
add_action('woocommerce_order_status_completed', 'move_order_status_to_booked');
