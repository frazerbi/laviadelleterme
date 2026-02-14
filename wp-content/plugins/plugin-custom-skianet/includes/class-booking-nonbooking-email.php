<?php
/**
 * Gestisce l'invio email con coupon PDF per prodotti SENZA prenotazione
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

class Booking_Nonbooking_Email {

    /**
     * Istanza singleton
     */
    private static $instance = null;

    /**
     * Ottieni l'istanza della classe
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Costruttore
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Inizializza hooks
     */
    private function init_hooks() {
        // Invia email quando ordine diventa "Not-Booked"
        // add_action('woocommerce_thankyou', array($this, 'send_on_status_not_booked'), 10, 1);
        
        // Azioni manuali admin
        add_action('woocommerce_order_actions', array($this, 'add_order_actions'));
        add_action('woocommerce_order_action_send_coupons', array($this, 'send_to_customer'));
        add_action('woocommerce_order_action_send_coupons_to_admin', array($this, 'send_to_admin'));
    }

    /**
     * Invia email quando ordine diventa "Not-Booked"
     */
    public function send_on_status_not_booked($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            error_log("Ordine {$order_id} non trovato");
            return;
        }

        $this->send_coupon_email($order);    
    
    }

    /**
     * Aggiungi azioni al dropdown ordine admin
     */
    public function add_order_actions($actions) {
        $actions['send_coupons'] = 'Send Coupons To Customer Not Booked';
        $actions['send_coupons_to_admin'] = 'Send Coupons To Admin Not Booked';
        return $actions;
    }

    /**
     * Invia email al cliente (azione manuale)
     */
    public function send_to_customer($order) {
        if (!$order) {
            error_log('No order object provided for customer coupon email');
            return;
        }
        
        try {
            $this->send_coupon_email($order, false);
        } catch (Exception $e) {
            error_log("Error sending coupon email: " . $e->getMessage());
        }
    }

    /**
     * Invia email all'admin (azione manuale)
     */
    public function send_to_admin($order) {
        if (!$order) {
            error_log('No order object provided for admin coupon email');
            return;
        }
        
        try {
            $this->send_coupon_email($order, true);
        } catch (Exception $e) {
            error_log("Error sending admin coupon email: " . $e->getMessage());
        }
    }

    /**
     * Invia email con coupon PDF
     */
    private function send_coupon_email($order, $send_to_admin = false) {
        if (!$order) {
            error_log('Order is not valid in send_coupon_email');
            return;
        }

        $order = is_object($order) ? $order : wc_get_order($order);
        if (!$order) {
            error_log('Unable to load order');
            return;
        }

        $order_id = $order->get_id();
        
        error_log("Processing coupon email for order {$order_id}");

        // Genera PDF per ogni prodotto senza prenotazione
        $attachment_paths = array();
        $nonbooking_items = array();

        foreach ($order->get_items() as $item) {
            // Skip prodotti CON prenotazione
            if ($item->get_meta('_booking_id')) {
                error_log("Skipped booking product: " . $item->get_name());
                continue;
            }

            $nonbooking_items[] = $item;

            // Genera PDF per questo prodotto
            $pdf_path = $this->generate_coupon_pdf($order, $item);
            
            if ($pdf_path) {
                $attachment_paths[] = $pdf_path;
            }
        }

        // Invia email solo se ci sono PDF
        if (empty($attachment_paths)) {
            error_log("No non-booking items found for order {$order_id}");
            return;
        }

        // Prepara contenuto email
        $email_data = $this->prepare_email_content($attachment_paths, $nonbooking_items);

        // Invia email
        $this->send_email(
            $order->get_billing_email(),
            $email_data['subject'],
            $email_data['heading'],
            $email_data['body'],
            $attachment_paths,
            $send_to_admin
        );

        // Pulisci file temporanei
        $this->cleanup_attachments($attachment_paths);
    }

    /**
     * Genera PDF coupon per un prodotto
     */
    private function generate_coupon_pdf($order, $item) {
        $order_id = $order->get_id();
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $product_name = $item->get_name();
        
        // Recupera codici licenza        
        $codes = Booking_Cart_Handler::get_item_license_codes($order_id, $product_id, $variation_id);
    
        if (empty($codes)) {
            error_log("No license codes found for product {$product_name}");
            return false;
        }

        // Recupera ACF fields
        $condizioni_vendita = get_field('condizioni_vendita_pdf', $product_id);
        $come_prenotarsi = get_field('come_prenotarsi', $product_id);

        // Formatta codici
        $formatted_codes = implode("\n", $codes);
        if (count($codes) > 1) {
            $formatted_codes = "\n" . $formatted_codes;
        }

        $heading_pdf = count($codes) === 1 
            ? "Questo è il codice che hai acquistato:" 
            : "Questi sono i codici che hai acquistato:";

        $date = $order->get_date_completed();
        $order_date = $date ? $date->format('d/m/Y H:i') : date('d/m/Y H:i');

        $body_pdf = "
            $heading_pdf 
            $formatted_codes
            
            $condizioni_vendita
            <p>Coupon emesso il $order_date</p>
        ";

        // Pulisci HTML
        $body_pdf = $this->clean_html_for_pdf($body_pdf);

        // Genera PDF
        return $this->create_pdf($product_name, $body_pdf, $order_id, $variation_id);
    }

    /**
     * Crea file PDF
     */
    private function create_pdf($product_name, $body_pdf, $order_id, $variation_id = null) {
        if (!class_exists('FPDF')) {
            error_log('FPDF class not found');
            return false;
        }

        try {
            $pdf = new FPDF('P', 'mm', 'A4');
            $pdf->AddPage();

            // Logo header
            if (defined('LOGO_TERME_PATH') && file_exists(LOGO_TERME_PATH)) {
                $pdf->Image(LOGO_TERME_PATH, 0, 0, 210);
            }
            $pdf->Ln(40);

            // Titolo
            $title = "Coupon per: $product_name";
            $pdf->SetFont('Helvetica', 'B', 24);
            $pdf->SetTextColor(0, 116, 160);
            $pdf->MultiCell(0, 10, iconv('UTF-8', 'CP1252//IGNORE', $title), 0, 'C');

            // Corpo
            $pdf->Ln(10);
            $pdf->SetFont('Helvetica', '', 13);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->MultiCell(0, 6, iconv('UTF-8', 'CP1252//IGNORE', $body_pdf), 0, 'L');
            $pdf->Ln(10);

            // Logo footer
            if (defined('LOGO_TERME_FOOTER_PATH') && file_exists(LOGO_TERME_FOOTER_PATH)) {
                $pdf->Image(LOGO_TERME_FOOTER_PATH, 0, $pdf->GetY(), 210, 0);
            }
            
            $pdf->SetAutoPageBreak(true, 10);

            // Salva PDF
            $upload_dir = wp_upload_dir()['path'];
            $safe_filename = "Coupon_Ordine_{$order_id}";
            if ($variation_id) {
                $safe_filename .= "_Var_{$variation_id}";
            }
            $filename = $upload_dir . "/{$safe_filename}.pdf";

            $pdf->Output('F', $filename);

            return file_exists($filename) ? $filename : false;

        } catch (Exception $e) {
            error_log("Error generating PDF: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Prepara contenuto email
     */
    private function prepare_email_content($attachment_paths, $items) {
        $attachment_count = count($attachment_paths);
        $is_plural = $attachment_count > 1;
        
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
            Solo per i coupon di ingresso SPA, la prenotazione è obbligatoria al seguente link: 
            <a href='https://www.termegest.it/prenota.aspx' target='_blank' style='color:#0074A0; text-decoration:underline;'>termegest.it/prenota.aspx</a>.<br>
            Il coupon è prorogabile di 90 giorni con supplemento al link: 
            <a href='https://laviadelleterme.it/shop/proroghe/' target='_blank' style='color:#0074A0; text-decoration:underline;'>laviadelleterme.it/shop/proroghe/</a>
            </p>
        ";

        // Verifica se servono condizioni aggiuntive
        $needs_conditions = false;
        $excluded_variations = array(28747, 1677, 1678, 1690, 392, 393, 394, 1907, 1908, 1637, 1639, 1640, 21800);
        
        foreach ($items as $item) {
            $variation_id = $item->get_variation_id();
            if (!in_array($variation_id, $excluded_variations)) {
                $needs_conditions = true;
                break;
            }
        }

        $body = $needs_conditions ? $body_intro . $body_cond : $body_intro;

        return array(
            'subject' => $subject,
            'heading' => $heading,
            'body' => $body
        );
    }

    /**
     * Invia email
     */
    private function send_email($to_email, $subject, $heading, $body, $attachments, $send_to_admin = false) {
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
        
        $recipients = $send_to_admin ? array('biglietti@laviadelleterme.it') : array($to_email);
        $success = true;

        foreach ($recipients as $recipient) {
            $result = $mailer->send($recipient, $subject, $html_message, '', $attachments);
            if ($result) {
                error_log("Coupon email sent to {$recipient}");
            } else {
                error_log("Failed to send coupon email to {$recipient}");
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Pulisci HTML per PDF
     */
    private function clean_html_for_pdf($html_content) {
        $content = preg_replace('/<\/p>/', "\n", $html_content);
        $content = preg_replace('/<p[^>]*>/', '', $content);
        $content = preg_replace('/[ \t]+/', ' ', $content);
        $content = preg_replace('/\n{3,}/', "\n", $content);
        $content = strip_tags($content);
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        return trim($content);
    }

    /**
     * Pulisci file allegati
     */
    private function cleanup_attachments($attachment_paths) {
        foreach ($attachment_paths as $path) {
            if (file_exists($path)) {
                if (!unlink($path)) {
                    error_log("Failed to delete attachment: {$path}");
                }
            }
        }
    }

    public function send_mixed_order_coupons($order) {
        error_log("=== INVIO COUPON PER ORDINE MISTO ===");
        
        if (!$order) {
            error_log('Order non valido per ordine misto');
            return;
        }
        
        // ✅ Usa lo stesso metodo ma in modalità "mista"
        // send_coupon_email già skippa automaticamente prodotti con _booking_id
        $this->send_coupon_email($order, false);
    }

}