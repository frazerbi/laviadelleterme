<?php


// function add_expedited_order_woocommerce_email( $email_classes ) {

//     // include our custom email class
//     require( 'includes/class-wc-creme-order-email.php' );

//     // add the email class to the list of email classes that WooCommerce loads
//     $email_classes['WC_Expedited_Order_Email'] = new WC_Creme_Order_Email();

//     return $email_classes;

// }
// add_filter( 'woocommerce_email_classes', 'add_expedited_order_woocommerce_email' );


//Define a constant to use with html emails
if (! defined('HTML_EMAIL_HEADERS')) {
  define("HTML_EMAIL_HEADERS", array('Content-Type: text/html; charset=UTF-8'));
}
if (! defined('LOGO_TERME_FOOTER_PATH')) {
  define( 'LOGO_TERME_FOOTER_PATH', plugin_dir_path( PLUGIN_TERME__FILE__ ).'assets/footer-coupon1.JPG');
}

function send_email_creme ($order) {

    global $woocommerce;

    if (!$order) {
        return;
    }

    $order_data = wc_get_order($order);

    if (empty($order_data)) {
        return;
    }

    $order_id  = $order_data->get_id();
    $order_status  = $order_data->get_status();
    $billing_email  = $order_data->get_billing_email();
    $admin_email = ['cpiana76@gmail.com', 'info@laviadelleterme.it'];

    if ($order_status === 'failed') {
        return;
    }

    if ($order_status !== 'completed_creme') {
        return;
    }

    $heading = "Ordine Creme Completato";

    $subject = "La Via Delle Terme - Ordine Creme Completato";
    $subject_admin = "La Via Delle Terme - Nuovo Ordine Creme";


    $heading = "Ordine Creme Completato";

    $body = wc_get_template_html (
        'wp-content/themes/Astra_Child/woocommerce/emails/customer-creme-order.php',
         array(
             'order'         => $order_data,
             'sent_to_admin' => false,
             'plain_text'    => false,
             'email'         => $billing_email,
         ) , '',ABSPATH
    );
    $body_admin = wc_get_template_html (
    'wp-content/themes/Astra_Child/woocommerce/emails/admin-new-order-creme.php',
        array(
            'order'         => $order_data,
            'sent_to_admin' => false,
            'plain_text'    => false,
            'email'         => $billing_email,
        ) , '',ABSPATH
    );

    $mailer = WC()->mailer();

    // Wrap message using woocommerce html email template
    $wrapped_message = $mailer->wrap_message($heading, $body);
    $wrapped_message_admin = $mailer->wrap_message($heading, $body_admin);

    // Create new WC_Email instance
    $wc_email = new WC_Email();

    // Style the wrapped message with woocommerce inline styles
    $html_message = $wc_email->style_inline($wrapped_message);
    $html_message_admin = $wc_email->style_inline($wrapped_message_admin);


    $mailer->send($billing_email, $subject, $html_message, HTML_EMAIL_HEADERS);

    $mailer->send($admin_email, $subject_admin, $html_message_admin, HTML_EMAIL_HEADERS);

}
// add_action('woocommerce_order_status_completed_creme', 'send_email_creme');
