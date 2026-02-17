<?php
/**
 * Customer completed order email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-completed-order.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 9.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php /* translators: %s: Customer first name */ ?>
<p><?php printf( esc_html__( 'Ciao,', 'woocommerce' ), esc_html( $order->get_billing_first_name() ) ); ?></p>
<?php /* translators: %s: Site title */ ?>
<p><?php esc_html_e( 'Il tuo ordine è stato processato correttamente. Qui sotto trovi il riepilogo con tutti i dettagli. ', 'woocommerce' ); ?></p>
<p>
Riceverai un'ulteriore email con il coupon in pdf con le istruzioni per accedere alle nostre strutture.
<br><br></p>
<?php

/*
 * Filter item meta to show only selected fields in this email.
 */
$allowed_meta_keys = array(
	'Location',
	'Data Prenotazione',
	'Orario',
	'Tipo Ingresso',
	'Ingressi Uomo',
	'Ingressi Donna',
	'Certificato Salute',
);

$completed_order_filter_item_meta = function ( $formatted_meta ) use ( $allowed_meta_keys ) {
	$filtered = array();
	foreach ( $formatted_meta as $meta_id => $meta ) {
		if ( in_array( $meta->display_key, $allowed_meta_keys, true ) ) {
			$filtered[ $meta_id ] = $meta;
		}
	}
	return $filtered;
};
add_filter( 'woocommerce_order_item_get_formatted_meta_data', $completed_order_filter_item_meta, 10 );

/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 * @since 2.5.0
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

remove_filter( 'woocommerce_order_item_get_formatted_meta_data', $completed_order_filter_item_meta, 10 );

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
// do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );
do_action('wc_ld_license_instructions', $order);

$text_align = is_rtl() ? 'right' : 'left';
$address    = $order->get_formatted_billing_address();

?><table id="addresses" cellspacing="0" cellpadding="0" style="width: 100%; vertical-align: top; margin-bottom: 40px; padding:0;" border="0">
    <tr>
        <td style="text-align:<?php echo esc_attr( $text_align ); ?>; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; border:0; padding:0;" valign="top" width="100%">
            <h2><?php esc_html_e( 'Acquirente del coupon', 'woocommerce' ); ?></h2>

            <address class="address">
                <?php echo wp_kses_post( $address ? $address : esc_html__( 'N/A', 'woocommerce' ) ); ?>
                <?php if ( $order->get_billing_phone() ) : ?>
                    <br/><?php echo wc_make_phone_clickable( $order->get_billing_phone() ); ?>
                <?php endif; ?>
                <?php if ( $order->get_billing_email() ) : ?>
                    <br/><?php echo esc_html( $order->get_billing_email() ); ?>
                <?php endif; ?>
            </address>
        </td>
    </tr>
</table>

<?php


/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
} ?>
<p style="font-size: 12px; font-style: italic; text-align: center; ">Operazione fuori campo IVA ai sensi dell'art. 2 DPR 633/72 e succ. modifiche (lo scontrino fiscale sarà emesso dall'azienda presso la quale l'utilizzatore del coupon sceglierà di fruire del servizio).</p>
<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );