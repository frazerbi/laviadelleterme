<?php

// Define a constant to use with html emails
if (! defined('HTML_EMAIL_HEADERS')) {
  define("HTML_EMAIL_HEADERS", array('Content-Type: text/html; charset=UTF-8'));
}
if (! defined('LOGO_TERME_FOOTER_PATH')) {
  define( 'LOGO_TERME_FOOTER_PATH', plugin_dir_path( PLUGIN_TERME__FILE__ ).'assets/footer-coupon1.JPG');
}


require_once(PLUGIN_TERME_PATH . 'fpdf_extend/fpdf_extend.php');

require_once(PLUGIN_TERME_PATH . 'html2text/html2text.php');


function coupon_sent_order_status($order) {

    if (!$order) {
        return;
    }

    $order_data = wc_get_order($order);

    if (empty($order_data)) {
        return;
    }

    $order_id  = $order_data->get_id();
    $order_status  = $order_data->get_status();

    if ($order_status != 'Not Booked') {
        // // Get woocommerce mailer from instance
        // $mailer = WC()->mailer();

        // // Create new WC_Email instance
        // $wc_email = new WC_Email;

        // $heading ='Errore nel inviare dettagli ordine';
        // $body = 'L\'ordine '.$order_id.' ha lo status: "'.$order_status.'" e quindi non è stato possibile inviare i dettagli dell\'ordine. ';

        // // Wrap message using woocommerce html email template
        // $wrapped_message1 = $mailer->wrap_message($heading, $body);
        // $html_message1 = $wc_email->style_inline($wrapped_message1);


        // $mailer->send('biglietti@laviadelleterme.it',$heading ,$html_message1, HTML_EMAIL_HEADERS );
        return;
    }

    $billing_email  = $order_data->get_billing_email();

    $date = $order_data->get_date_completed();

    if (empty($date)) {
        $date = date('Y-m-d H:i:s');
    } else {
        $date = $date->format('Y-m-d H:i:s');
    }
    $order_date = date("d/m/Y G:i", strtotime($date));

    foreach ($order_data->get_items() as $lineItem) {
        $product_id = $lineItem['product_id'];
        $variation_id = $lineItem->get_variation_id();

        $product_name = $lineItem['name'];

        $condizioni_vendita = get_field('condizioni_vendita_pdf', $product_id);
        $come_prenotarsi = get_field('come_prenotarsi', $product_id);

        $codes = '';

        if ($variation_id == 0) {
            global $wpdb;
            $allcodes = $wpdb->get_results(

                $wpdb->prepare("SELECT * FROM {$wpdb->wc_ld_license_codes} WHERE order_id = %d  AND product_id =  %d", array($order_id, $product_id))
            );
            foreach ($allcodes as $current) {
                $codes .= '<span>' . $current->license_code1 . '</span> ';
                $codes .= '<span>' . $current->license_code2 . '</span>';
                $codes .= '<span>' . $current->license_code3 . '</span>';
                $codes .= '<span>' . $current->license_code4 . '</span>';
                $codes .= '<span>' . $current->license_code5 . '</span>';
            }
        } else {
            $allcodes = $wpdb->get_results(
           
            $wpdb->prepare("SELECT * FROM {$wpdb->wc_ld_license_codes} WHERE order_id = %d  AND product_id =  %d", array($order_id, $variation_id))
            );

            foreach ($allcodes as $current) {
                $codes .= '<span>' . $current->license_code1 . '</span> ';
                $codes .= '<span>' . $current->license_code2 . '</span>';
                $codes .= '<span>' . $current->license_code3 . '</span>';
                $codes .= '<span>' . $current->license_code4 . '</span>';
                $codes .= '<span>' . $current->license_code5 . '</span>';
            }
        }


        $heading = "$product_name, ecco il tuo coupon";

        $body_pdf = "
            <p>Questi sono i codici che hai acquistato:<br/>$codes</p>

            <p>$condizioni_vendita</p>
            <p>Coupon emesso il " . $order_date . "</p>
        ";

        $body_intro = "
            <p>Gentile Cliente,<br>
            grazie per l'acquisto effettuato.</p>
            <p>Nel documento allegato troverai il coupon con le condizioni di utilizzo.</p>
            <p>Cordiali saluti</p>
            <p>Il team La Via Delle Terme</p>
        ";
        $body_cond = "
            <p style='margin-top:60px;font-size:12px;'>Alcuni link utili: <br>
            Prenotazione obbligatoria online al seguente link: <a href='https://www.termegest.it/prenota.aspx' target='_blank' style='color:#0074A0; text-decoration:underline;''>termegest.it/prenota.aspx </a>.<br>
            Il coupon è prorogabile di 90 giorni con supplemento al link: <a href='https://laviadelleterme.it/shop/proroghe/' target='_blank' style='color:#0074A0; text-decoration:underline;'>https://laviadelleterme.it/shop/proroghe/</a></p>
            <p style='font-size:12px;'>P.s. Potrai sempre recuperare i codici di accesso sulla tua area riservata a questo link:<a href='https://laviadelleterme.it/my-account/' target='_blank' style='color:#0074A0; text-decoration:underline;'>https://laviadelleterme.it/my-account/</a>.
            </p>
        ";

        if (in_array($variation_id, array(28747, 1677, 1678, 1690, 392, 393, 394, 1907, 1908, 1637, 1639, 1640, 21800))) {
            $body = $body_intro;
        } else {
            $body = $body_intro.$body_cond;
        }

        $subject = "$product_name, ecco il tuo biglietto";

        // Get woocommerce mailer from instance
        $mailer = WC()->mailer();

        // Wrap message using woocommerce html email template
        $wrapped_message = $mailer->wrap_message($heading, $body);

        // Create new WC_Email instance
        $wc_email = new WC_Email();

        // Style the wrapped message with woocommerce inline styles
        $html_message = $wc_email->style_inline($wrapped_message);
        @$text = convert_html_to_text($body_pdf);
        @$str = utf8_decode($text);
        @$str_new = html_entity_decode($str, ENT_QUOTES, "ISO-8859-1");
        $pdf = new PDF('P', 'mm', 'A4');

        $pdf->AddPage();

        $product_name_pdf = str_replace('/', ' ', $product_name);
        $title = "Coupon per: $product_name_pdf";
        $link_footer ="<a>www.laviadelleterme.it</a>";
        @$link_footer_converted = convert_html_to_text($link_footer);
        @$link_footer_str = utf8_decode($link_footer_converted);

        @$text_title = convert_html_to_text($title);
        @$decoded_title = utf8_decode($text_title);

        // $pdf->Cell(0,10,$title,0,1,'C');
        $pdf->Ln(10);
        $pdf->SetFont('Arial','B',24);
        $pdf->SetTextColor(0, 116, 160);
        @$pdf->MultiCell(0, 10, $decoded_title, 0, C);
        // $pdf->Write($title);
        $pdf->Ln(10);
        $pdf->SetFont('Helvetica', '', 13);
        $pdf->SetTextColor(0, 0, 0);
        // $pdf->Ln(10);
        $pdf->SetAuthor('La Via Delle Terme');
        @$pdf->MultiCell(0, 6, $str_new, 0, L);
        $pdf->Ln(5);
        @$pdf->MultiCell(0, 6, $link_footer_str, 0, C);

        $pdf->Ln(10);
        $pdf->Image(LOGO_TERME_FOOTER_PATH, 0, $pdf->GetY(),210, 0);
        $pdf->Ln(10);
        $pdf->SetAutoPageBreak(true, 10);
        $filename = "Coupon $product_name_pdf - Ordine $order_id.pdf";

        $pdf->Output('F', $filename);

        $attachments = array($filename);

        // Send the email using wordpress mail function
        $mailer->send($billing_email, $subject, $html_message, HTML_EMAIL_HEADERS, $attachments);

        unlink($filename); // Deletion of the created file.
    }


}
add_action('woocommerce_thankyou', 'coupon_sent_order_status');
// process the custom order meta box order action
add_action('woocommerce_order_action_send_coupons', 'coupon_sent_order_status');



// add our own item to the order actions meta box
add_action('woocommerce_order_actions', 'add_order_meta_box_actions');
function add_order_meta_box_actions($actions)
{

    $actions['send_coupons'] = 'Send Coupons To Customer';
    $actions['send_coupons_to_admin'] = 'Send Coupons To Admin';

    return $actions;
}
