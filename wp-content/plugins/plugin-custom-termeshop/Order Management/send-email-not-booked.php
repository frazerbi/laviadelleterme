<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly for security
}

require_once(PLUGIN_TERME_PATH . 'fpdf_extend/fpdf_extend.php');
require_once(PLUGIN_TERME_PATH . 'html2text/html2text.php');

function coupon_sent_order_status_not_booked($order, $send_to_admin = false, $forced = false ) {

    if (!$order) {
        error_log('Order is not valid in coupon_sent_order_status_not_booked');
        return;
    }

    // Normalize order input - handle both order ID and order object
    if (is_numeric($order)) {
        $order_data = wc_get_order($order);
        if (!$order_data) {
            error_log('Order data is empty for order ID: ' . $order);
            return;
        }
    } else {
        $order_data = $order;
    }

    $order_id = $order_data->get_id();
    $order_status = $order_data->get_status();

    error_log("=== FOREACH RESULTS DEBUG ===");
    error_log("Total items in order: " . count($order_data->get_items()));

    // MODIFICA: Controlla lo status solo se non è forced
    if (!$forced && $order_status != 'not-booked') {
        error_log("Order #{$order_id} has status '{$order_status}' instead of 'not-booked' - skipping (not forced)");
        return;
    }

    // Log per indicare se è forced o normale
    if ($forced) {
        error_log("Processing order #{$order_id} with FORCED mode (status: {$order_status})");
    } else {
        error_log("Processing order #{$order_id} with normal mode (status: {$order_status})");
    }

    $billing_email = $order_data->get_billing_email();
    $date = $order_data->get_date_completed();
    $order_date = empty($date) ? date('Y-m-d H:i:s') : $date->format('Y-m-d H:i:s');
    $order_date = date("d/m/Y G:i", strtotime($order_date));

    $attachment_paths = [];
    $all_codes = [];

    $nonBookingItems = []; // Per il secondo pass

    // First pass: Generate all PDFs and collect codes
    foreach ($order_data->get_items() as $lineItem) {
        $product_id = $lineItem['product_id'];
        $variation_id = $lineItem->get_variation_id();
        $product_name = $lineItem['name'];

        // CONTROLLO: Verifica se questo item ha metadati di prenotazione
        $itemHasBooking = false;
        foreach (['skn-custom-id', 'skn-custom-location', 'skn-custom-event', 'skn-custom-qty'] as $bookingMeta) {
            $metaValue = $lineItem->get_meta($bookingMeta);
            if (!empty($metaValue)) {
                $itemHasBooking = true;
                error_log("Found booking meta '{$bookingMeta}' = '{$metaValue}' for product: {$product_name}");
                break;
            }
        }
        if ($itemHasBooking) {
            error_log("SKIPPED booking product in forced mode: {$product_name}");
            continue;
        }

        $nonBookingItems[] = $lineItem;

        $condizioni_vendita = get_field('condizioni_vendita_pdf', $product_id);
        $come_prenotarsi = get_field('come_prenotarsi', $product_id);


        $codes = fetch_license_codes($order_id, $product_id, $variation_id);
        $all_codes = array_merge($all_codes, $codes);
        
        if (empty($codes)) {
            error_log("No license codes found for order #{$order_id}, product #{$product_id}");
            continue;
        }

        $formatted_codes = implode("\n", $codes);
        // Add a line break before the codes if there are more than 1 code
        if (count($codes) > 1) {
            $formatted_codes = "\n" . $formatted_codes;
        }

        // Set the heading for the PDF (singular or plural)
        $heading_pdf = count($codes) === 1 
            ? "Questo è il codice che hai acquistato:" 
            : "Questi sono i codici che hai acquistato:";

        $body_pdf = "
            $heading_pdf 
            $formatted_codes
            
            $condizioni_vendita
            <p>Coupon emesso il $order_date</p>
        ";

        // Clean up HTML content for PDF
        $body_pdf = clean_html_for_pdf($body_pdf);

        // Sanitize product name for filename
        $product_name_clean = sanitize_filename($product_name);
        
        // Generate PDF
        $filename = generate_pdf($product_name_clean, $body_pdf, $order_id, $variation_id);
        if ($filename) {
            $attachment_paths[] = $filename;
        }
    }

    // Second pass: Send email with all attachments (only if we have PDFs)
    if (!empty($attachment_paths)) {
        $email_data = prepare_email_content($attachment_paths, $nonBookingItems);

        // $email_data = prepare_email_content($attachment_paths, $order_data->get_items());
        send_email(
            $billing_email, 
            $email_data['subject'], 
            $email_data['heading'], 
            $email_data['body'], 
            $attachment_paths, 
            $send_to_admin
        );
    }

    // Cleanup attachment files
    cleanup_attachment_files($attachment_paths);
}

function prepare_email_content($attachment_paths, $order_items) {
    $attachment_count = count($attachment_paths);
    $is_plural = $attachment_count > 1;
    
    // Prepare email content based on singular/plural
    $subject = $is_plural ? "I tuoi coupon" : "Il tuo coupon";
    $heading = $is_plural ? "Ecco i tuoi coupon" : "Ecco il tuo coupon";
    $intro_text = $is_plural 
        ? "Nel documento allegato troverai i coupon con le condizioni di utilizzo."
        : "Nel documento allegato troverai il coupon con le condizioni di utilizzo.";

    $body_intro = "
        <p>Gentile Cliente,<br>grazie per l'acquisto effettuato.</p>
        <p>$intro_text</p>
        <p>Cordiali saluti</p>
        <p>Il team La Via Delle Terme</p>
    ";

    $body_cond = "
        <p style='margin-top:60px;font-size:12px;'>Alcuni link utili: <br>
        Prenotazione obbligatoria online al seguente link: 
        <a href='https://www.termegest.it/prenota.aspx' target='_blank' style='color:#0074A0; text-decoration:underline;'>termegest.it/prenota.aspx</a>.<br>
        Il coupon è prorogabile di 90 giorni con supplemento al link: 
        <a href='https://laviadelleterme.it/shop/proroghe/' target='_blank' style='color:#0074A0; text-decoration:underline;'>laviadelleterme.it/shop/proroghe/</a>
        </p>
    ";

    // Check if any item requires additional conditions
    $needs_conditions = false;
    $excluded_variations = [28747, 1677, 1678, 1690, 392, 393, 394, 1907, 1908, 1637, 1639, 1640, 21800];
    
    foreach ($order_items as $item) {
        $variation_id = $item->get_variation_id();
        if (!in_array($variation_id, $excluded_variations)) {
            $needs_conditions = true;
            break;
        }
    }

    $body = $needs_conditions ? $body_intro . $body_cond : $body_intro;

    return [
        'subject' => $subject,
        'heading' => $heading,
        'body' => $body
    ];
}

function clean_html_for_pdf($html_content) {
    // Replace </p> with two newlines (for proper paragraph separation)
    $content = preg_replace('/<\/p>/', "\n", $html_content);
    
    // Remove the opening <p> tags, but keep the text
    $content = preg_replace('/<p[^>]*>/', '', $content);
    
    // Remove extra spaces from each line, but keep newlines
    $content = preg_replace('/[ \t]+/', ' ', $content);
    
    // Remove extra blank lines (like double newlines) and keep only one blank line
    $content = preg_replace('/\n{3,}/', "\n", $content);
    
    // Remove all remaining HTML tags
    $content = strip_tags($content);
    
    // Decode HTML entities
    $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
    
    // Trim extra spaces from the start and end of the text
    return trim($content);
}

function sanitize_filename($filename) {
    // Remove HTML tags and decode entities
    $filename = strip_tags(html_entity_decode($filename, ENT_QUOTES, 'UTF-8'));

    // Limit length to prevent filesystem issues
    return substr($filename, 0, 100);
}

function fetch_license_codes($order_id, $product_id, $variation_id) {
    global $wpdb;

    // Validate input to prevent SQL errors
    if (!is_numeric($order_id) || !is_numeric($product_id)) {
        error_log("Invalid parameters passed to fetch_license_codes. Order ID: $order_id, Product ID: $product_id");
        return [];
    }

    // If variation ID is set, use it as the product ID
    $effective_product_id = $variation_id ?: $product_id;

    // Use proper table name with prefix
    $table_name = $wpdb->prefix . 'wc_ld_license_codes';
    
    // Check if table exists
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
    if (!$table_exists) {
        error_log("Table {$table_name} does not exist");
        return [];
    }

    // Prepare SQL query with proper table name
    $query = $wpdb->prepare(
        "SELECT license_code1 FROM `{$table_name}` WHERE order_id = %d AND product_id = %d",
        $order_id,
        $effective_product_id
    );

    // Execute the query
    $allcodes = $wpdb->get_results($query);

    // Check for SQL errors
    if ($wpdb->last_error) {
        error_log('Database error in fetch_license_codes: ' . $wpdb->last_error . ' - Query: ' . $query);
        return [];
    }

    // Extract and return the list of license codes
    $codes = [];
    foreach ($allcodes as $current) {
        if (!empty($current->license_code1)) {
            $codes[] = trim($current->license_code1);
        }
    }

    return $codes;
}

function generate_pdf($product_name, $body_pdf, $order_id, $variation_id = null) {
    try {
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();

        // Add the logo at the top of the page
        if (defined('LOGO_TERME_PATH') && file_exists(LOGO_TERME_PATH)) {
            $pdf->Image(LOGO_TERME_PATH, 0, 0, 210); // Full width (210mm for A4)
        }
        $pdf->Ln(40); // Move the cursor down by 40mm

        // Prepare title
        $title = "Coupon per: $product_name";

        // Set title
        $pdf->SetFont('Helvetica', 'B', 24);
        $pdf->SetTextColor(0, 116, 160);
        $pdf->MultiCell(0, 10, iconv('UTF-8', 'CP1252//IGNORE', $title), 0, 'C');

        // Set body content
        $pdf->Ln(10);
        $pdf->SetFont('Helvetica', '', 13);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->MultiCell(0, 6, iconv('UTF-8', 'CP1252//IGNORE', $body_pdf), 0, 'L');
        $pdf->Ln(10);

        // Add footer logo if exists
        if (defined('LOGO_TERME_FOOTER_PATH') && file_exists(LOGO_TERME_FOOTER_PATH)) {
            $pdf->Image(LOGO_TERME_FOOTER_PATH, 0, $pdf->GetY(), 210, 0);
        }
        
        $pdf->SetAutoPageBreak(true, 10);

        // Generate safe filename
        $upload_dir = wp_upload_dir()['path'];
        $safe_filename = "Coupon_Ordine_{$order_id}";
        if ($variation_id) {
            $safe_filename .= "_Var_{$variation_id}";
        }
        $filename = $upload_dir . "/{$safe_filename}.pdf";

        // Output PDF to file
        $pdf->Output('F', $filename);

        return file_exists($filename) ? $filename : false;

    } catch (Exception $e) {
        error_log("Error generating PDF for order #{$order_id}: " . $e->getMessage());
        return false;
    }
}

function send_email($to_email, $subject, $heading, $body, $attachments, $send_to_admin = false) {
    if (!is_email($to_email) && !$send_to_admin) {
        error_log("Invalid email address: {$to_email}");
        return false;
    }

    $mailer = WC()->mailer();
    if (!$mailer) {
        error_log("WooCommerce mailer not available");
        return false;
    }

    $wrapped_message = $mailer->wrap_message($heading, $body);
    $wc_email = new WC_Email();
    $html_message = $wc_email->style_inline($wrapped_message);
    
    $recipients = $send_to_admin ? ['biglietti@laviadelleterme.it'] : [$to_email];
    $success = true;

    foreach ($recipients as $recipient) {
        $result = $mailer->send($recipient, $subject, $html_message, HTML_EMAIL_HEADERS, $attachments);
        if (!$result) {
            error_log("Failed to send email to {$recipient}");
            $success = false;
        }
    }

    return $success;
}

function cleanup_attachment_files($attachment_paths) {
    foreach ($attachment_paths as $path) {
        if (file_exists($path)) {
            if (!unlink($path)) {
                error_log("Failed to delete attachment file: {$path}");
            }
        }
    }
}

// Trigger function for order status changes
function trigger_email_on_status_change($order_id, $old_status, $new_status, $order) {
    if ($new_status === 'not-booked') {
        coupon_sent_order_status_not_booked($order_id);
    }
}
// Uncomment the line below to enable automatic triggering on status change
// add_action('woocommerce_order_status_changed', 'trigger_email_on_status_change', 10, 4);

// Add order actions to the order actions dropdown
add_action('woocommerce_order_actions', 'add_order_meta_box_actions');
function add_order_meta_box_actions($actions) {    
    $actions['send_coupons'] = 'Send Coupons To Customer Not Booked';
    $actions['send_coupons_to_admin'] = 'Send Coupons To Admin Not Booked';
    return $actions;
}

// Handle the "Send Coupons To Customer" order action
add_action('woocommerce_order_action_send_coupons', function($order) {
    if (!$order) {
        error_log('woocommerce_order_action_send_coupons: No order object provided.');
        return;
    }

    try {
        coupon_sent_order_status_not_booked($order, false);
        // Add admin notice for success
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>Coupon inviati al cliente con successo.</p></div>';
        });
    } catch (Exception $e) {
        $order_id = is_object($order) ? $order->get_id() : $order;
        error_log("Error executing coupon_sent_order_status_not_booked for order ID: {$order_id}. Error: " . $e->getMessage());
        // Add admin notice for error
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p>Errore nell\'invio dei coupon: ' . esc_html($e->getMessage()) . '</p></div>';
        });
    }
});

// Handle the "Send Coupons To Admin" order action
add_action('woocommerce_order_action_send_coupons_to_admin', function($order) {
    if (!$order) {
        error_log('woocommerce_order_action_send_coupons_to_admin: No order object provided.');
        return;
    }

    try {
        coupon_sent_order_status_not_booked($order, true);
        // Add admin notice for success
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>Coupon inviati all\'admin con successo.</p></div>';
        });
    } catch (Exception $e) {
        $order_id = is_object($order) ? $order->get_id() : $order;
        error_log("Error executing coupon_sent_order_status_not_booked for order ID: {$order_id}. Error: " . $e->getMessage());
        // Add admin notice for error
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p>Errore nell\'invio dei coupon all\'admin: ' . esc_html($e->getMessage()) . '</p></div>';
        });
    }
});

?>