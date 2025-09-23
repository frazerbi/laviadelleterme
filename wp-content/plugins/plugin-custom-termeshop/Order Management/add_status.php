<?php 
//register Coupon Sent Order Status
function register_coupon_sent_order_status() {
    register_post_status( 'wc-coupon-sent', array(
        'label'                     => 'Coupon Sent',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'show_in_admin_all_list'    => true,
        'exclude_from_search'       => false,
        'label_count'               => _n_noop( 'Coupon Sent <span class="count">(%s)</span>', 'Coupon Sent <span class="count">(%s)</span>' )
    ) );
}
add_action( 'init', 'register_coupon_sent_order_status' );

//Add Order Status Coupon Sent & Creme after Completed 
function add_coupon_sent_to_order_statuses( $order_statuses ) {
    $new_order_statuses = array();
    foreach ( $order_statuses as $key => $status ) {
        $new_order_statuses[ $key ] = $status;
        if ( 'wc-completed' === $key ) {
            $new_order_statuses['wc-coupon-sent'] = 'Coupon Sent';
        }
    }
    return $new_order_statuses;
}
add_filter( 'wc_order_statuses', 'add_coupon_sent_to_order_statuses' );


// function register_completed_creme_order_status() {
//     register_post_status( 'wc-completed_creme', array(
//         'label'                     => 'Completato Creme',
//         'public'                    => true,
//         'show_in_admin_status_list' => true,
//         'show_in_admin_all_list'    => true,
//         'exclude_from_search'       => false,
//         'label_count'               => _n_noop( 'Completato Creme <span class="count">(%s)</span>', 'Completato Creme	 <span class="count">(%s)</span>' )
//     ) );
// }
// add_action( 'init', 'register_completed_creme_order_status' );


// add_filter( 'wc_order_statuses', 'creme_order_status');
// function creme_order_status( $order_statuses ) {
//     $order_statuses['wc-completed_creme'] = _x( 'Completato Creme', 'Order status', 'woocommerce' ); 
//     return $order_statuses;
// }
