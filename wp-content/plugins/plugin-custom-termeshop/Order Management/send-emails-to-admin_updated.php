<?php

// Define a constant to use with html emails
if (! defined('HTML_EMAIL_HEADERS')) {
	define("HTML_EMAIL_HEADERS", array('Content-Type: text/html; charset=UTF-8'));
}

if (! defined('LOGO_TERME_FOOTER_PATH')) {
    define( 'LOGO_TERME_FOOTER_PATH', plugin_dir_path( PLUGIN_TERME__FILE__ ).'assets/footer-coupon1.JPG');
}


require_once(PLUGIN_TERME_PATH . 'fpdf/fpdf.php');
require_once(PLUGIN_TERME_PATH . 'html2pdf/html2pdf.php');


if (class_exists('WooCommerce')) {
    add_action('woocommerce_order_action_send_coupons_to_admin_updated', 'send_coupon_email');

}
add_action('woocommerce_order_action_send_coupons_to_admin_updated', 'send_coupon_email');

function send_coupon_email($order) {

    if (!$order) return;
    $order_data = wc_get_order($order);
    if (empty($order_data)) return;

    $order_id = $order_data->get_id();
    $order_status = $order_data->get_status();

    if ($order_status !== 'coupon-sent') {
        notify_admin_about_error($order_id, $order_status);
        return;
    }

    foreach ($order_data->get_items() as $line_item) {
        $product_id = $line_item->get_product_id();
        $variation_id = $line_item->get_variation_id();
        $codes = retrieve_coupon_codes($order_id, $product_id, $variation_id);
        $email_data = prepare_email_data($order_data, $line_item, $codes);
        $pdf_path = generate_pdf($email_data);

        if ($pdf_path) {
            send_email_to_customer($email_data, $pdf_path);
            if (file_exists($pdf_path)) {
                unlink($pdf_path); // Clean up the PDF file.
            } else {
                error_log("Failed to clean up PDF: $pdf_path");
            }
        }
    }
}

function notify_admin_about_error($order_id, $order_status) {
    $admin_email = 'biglietti@laviadelleterme.it';
    $heading = 'Error in Sending Coupon Email';
    $body = "Order ID: {$order_id} has status '{$order_status}', and no email was sent.";
    
    $mailer = WC()->mailer();
    $wc_email = new WC_Email();
    $wrapped_message = $mailer->wrap_message($heading, $body);
    $html_message = $wc_email->style_inline($wrapped_message);
    $mailer->send($admin_email, $heading, $html_message, HTML_EMAIL_HEADERS);
}

function generate_pdf($email_data) {
    
    $upload_dir = wp_upload_dir();
    $pdf_dir = $upload_dir['basedir'] . '/generated-pdfs';

    if (!is_dir($pdf_dir)) {
        mkdir($pdf_dir, 0755, true);
    }

    $filename = $pdf_dir . "/Coupon_{$email_data['product_name']} - Ordine_{$email_data['order_id']}.pdf";

    try {
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 24);
        $pdf->SetTextColor(0, 116, 160);
    
        // Title
        $product_name = str_replace('/', ' ', $email_data['product_name']);
        $title = "Coupon per: $product_name";
        $pdf->MultiCell(0, 10, utf8_decode($title), 0, 'C');
        $pdf->Ln(10);
    
        // Content
        $pdf->SetFont('Helvetica', '', 13);
        $pdf->SetTextColor(0, 0, 0);
        $html_content = $email_data['pdf_content'];

        // Ensure WriteHTML method exists
        if (method_exists($pdf, 'WriteHTML')) {
            $pdf->WriteHTML($html_content);
        } else {
            throw new Exception('WriteHTML method is not available in the PDF class.');
        }

        $pdf->Ln(5);
    
        // Footer
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->MultiCell(0, 6, utf8_decode("www.laviadelleterme.it"), 0, 'C');
        $pdf->Ln(10);
        $pdf->Image(LOGO_TERME_FOOTER_PATH, 0, $pdf->GetY(), 210, 0);
        $pdf->SetAutoPageBreak(TRUE, 10);

        $pdf->Output('F', $filename);

    } catch (Exception $e) {
        error_log("Failed to create PDF: " . $e->getMessage());
        return false;
    }

    
    if (!file_exists($filename)) {
        error_log("Failed to create PDF: $filename");
        return false;
    }

    
    return $filename;
}


function prepare_email_data($order_data, $line_item, $codes) {
    $product_name = $line_item->get_name();
    $order_date = date("d/m/Y G:i", strtotime($order_data->get_date_completed()));
    $variation_id = $line_item->get_variation_id(); // Get the variation ID

    // Introductory part of the email body
    $body_intro = "
        <p>Gentile Cliente,<br>
        grazie per l'acquisto effettuato.</p>
        <p>Nel documento allegato troverai il coupon con le condizioni di utilizzo.</p>
        <p>Cordiali saluti,</p>
        <p>Il team La Via Delle Terme</p>
    ";

    // Additional links and information
    $body_cond = "
        <p style='margin-top:60px;font-size:12px;'>Alcuni link utili: <br>
        Prenotazione obbligatoria online al seguente link: <a href='https://www.termegest.it/prenota.aspx' target='_blank' style='color:#0074A0; text-decoration:underline;'>termegest.it/prenota.aspx</a>.<br>
        Il coupon è prorogabile di 90 giorni con supplemento al link: <a href='https://laviadelleterme.it/shop/proroghe/' target='_blank' style='color:#0074A0; text-decoration:underline;'>https://laviadelleterme.it/shop/proroghe/</a></p>
        <p style='font-size:12px;'>P.s. Potrai sempre recuperare i codici di accesso sulla tua area riservata a questo link: <a href='https://laviadelleterme.it/my-account/' target='_blank' style='color:#0074A0; text-decoration:underline;'>https://laviadelleterme.it/my-account/</a>.
        </p>
    ";

    $body_pdf = "
        <p>Questi sono i codici che hai acquistato:<br/>$codes</p>
        <p>Coupon emesso il $order_date</p>
    ";
    
    
    // Variation-specific logic
    $special_variations = [
        28747, 1677, 1678, 1690, 392, 393, 394, 1907, 1908, 1637, 1639, 1640, 21800
    ];

    if (in_array($variation_id, $special_variations)) {
        $body = $body_intro;
    } else {
        $body = $body_intro . $body_cond;
    }

    return [
        'subject' => "$product_name, ecco il tuo biglietto - Ordine: {$order_data->get_id()}",
        'heading' => "$product_name, ecco il tuo coupon",
        'email_body' => $body,
        'pdf_content' => $body_pdf,
        'product_name' => $product_name,
        'order_id' => $order_data->get_id(),
    ];
}

function retrieve_coupon_codes($order_id, $product_id, $variation_id) {
    global $wpdb;

    $product_to_check = $variation_id ?: $product_id;

    // Query to fetch only license_code1
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT license_code1 
            FROM {$wpdb->wc_ld_license_codes} 
            WHERE order_id = %d AND product_id = %d",
            $order_id,
            $product_to_check
        )
    );
    if (!$results) {
        error_log("Failed to retrieve coupon codes for Order ID: $order_id, Product ID: $product_to_check: " . print_r($results, true));
        return '';
    }

    // Initialize an empty string to concatenate the codes
    $codes = '';
    foreach ($results as $current) {
        $codes .= '<span>' . $current->license_code1 . '</span> ';
    }

    return $codes;
}


function send_email_to_customer($email_data, $attachment) {
    $mailer = WC()->mailer();
    $wc_email = new WC_Email();

    // Wrap message using WooCommerce template
    $wrapped_message = $mailer->wrap_message($email_data['heading'], $email_data['email_body']);
    $html_message = $wc_email->style_inline($wrapped_message);

    $admin_email = 'biglietti@laviadelleterme.it';
    $super_admin_email = 'francesco.zerbinato@gmail.com';

    $headers = HTML_EMAIL_HEADERS;

    // Send email with attachment
    $mailer->send($super_admin_email, $email_data['subject'], $html_message, $headers, [$attachment]);
}

