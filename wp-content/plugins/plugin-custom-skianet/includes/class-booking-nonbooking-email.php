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
            return;
        }
        
        try {
            $this->send_coupon_email($order, false);
        } catch (Exception $e) {
            // Eccezione ignorata: l'invio email non deve interrompere il flusso chiamante.
        }
    }

    /**
     * Invia email all'admin (azione manuale)
     */
    public function send_to_admin($order) {
        if (!$order) {
            return;
        }
        
        try {
            $this->send_coupon_email($order, true);
        } catch (Exception $e) {
            // Eccezione ignorata: l'invio email non deve interrompere il flusso chiamante.
        }
    }

    /**
     * Invia email con coupon PDF
     */
    private function send_coupon_email($order, $send_to_admin = false) {
        if (!$order) {
            return;
        }

        $order = is_object($order) ? $order : wc_get_order($order);
        if (!$order) {
            return;
        }

        // Raccoglie i codici dei prodotti con prenotazione per escluderli dal PDF non-booked
        $booked_codes = array();
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_booking_id')) {
                $codes = Booking_Cart_Handler::get_item_license_codes($item);
                $booked_codes = array_merge($booked_codes, $codes);
            }
        }

        // Genera PDF per ogni prodotto senza prenotazione
        $attachment_paths = array();
        $nonbooking_items = array();

        foreach ($order->get_items() as $item) {
            // Skip prodotti CON prenotazione
            if ($item->get_meta('_booking_id')) {
                continue;
            }

            $nonbooking_items[] = $item;

            // Genera PDF per questo prodotto
            $pdf_path = $this->generate_coupon_pdf($order, $item, $booked_codes);
            
            if ($pdf_path) {
                $attachment_paths[] = $pdf_path;
            }
        }

        // Invia email solo se ci sono PDF
        if (empty($attachment_paths)) {
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
    private function generate_coupon_pdf($order, $item, $excluded_codes = array()) {
        $order_id = $order->get_id();
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $product_name = $item->get_name();

        // Recupera codici licenza ed esclude quelli dei prodotti booked
        $codes = Booking_Cart_Handler::get_item_license_codes($item);
        if (!empty($excluded_codes)) {
            $codes = array_values(array_diff($codes, $excluded_codes));
        }

        if (empty($codes)) {
            return false;
        }

        // Recupera ACF fields
        $condizioni_vendita = get_field('condizioni_vendita_pdf', $product_id);

        // Formatta codici
        $formatted_codes = implode("\n", $codes);
        if (count($codes) > 1) {
            $formatted_codes = "\n" . $formatted_codes;
        }

        $heading_pdf = count($codes) === 1 
            ? "Questo è il codice che hai acquistato:" 
            : "Questi sono i codici che hai acquistato:";

        $date = $order->get_date_completed();
        // Ora del sito, non UTC: format() di WC_DateTime ignora l'offset quando il fuso
        // e' impostato come "UTC+1" invece che come citta', e date() usa sempre UTC
        // (WordPress forza il default di PHP). date_i18n / wp_date valgono in entrambi i casi.
        $order_date = $date ? $date->date_i18n('d/m/Y H:i') : wp_date('d/m/Y H:i');

        $body_pdf = "
            $heading_pdf 
            $formatted_codes
            
            $condizioni_vendita
            <p>Coupon emesso il $order_date</p>
        ";

        // Pulisci HTML
        $body_pdf = $this->clean_html_for_pdf($body_pdf);

        // Genera PDF
        return $this->create_pdf($product_name, $body_pdf, $order_id, $product_id, $variation_id);
    }

    /**
     * Crea file PDF
     */
    private function create_pdf($product_name, $body_pdf, $order_id, $product_id, $variation_id = null) {
        // FPDF sta in lib/ e non in vendor/: un composer install rigenera vendor/ da zero.
        // Si carica solo qui, l'unico punto che genera PDF.
        if (!class_exists('FPDF')) {
            require_once PLUGIN_SKIANET_PATH . '/lib/fpdf/fpdf.php';
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
            $pdf->SetTextColor(...skianet_site()['pdf_title_rgb']);
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
            $safe_filename = "Coupon_Ordine_{$order_id}_Prod_{$product_id}";
            if ($variation_id) {
                $safe_filename .= "_Var_{$variation_id}";
            }
            $filename = $upload_dir . "/{$safe_filename}.pdf";

            $pdf->Output('F', $filename);

            return file_exists($filename) ? $filename : false;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Prepara contenuto email
     */
    private function prepare_email_content($attachment_paths, $items) {
        $site = skianet_site();
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
            <p>Il team {$site['name']}</p>
        ";

        // Link utili (prenotazione, proroghe / area riservata): dipendono dal sito.
        $body_cond = $site['coupon_links_html'];

        // Verifica se servono condizioni aggiuntive
        $needs_conditions = false;
        $excluded_variations = $site['no_links_variations'];
        
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
            return false;
        }

        $mailer = WC()->mailer();
        if (!$mailer) {
            return false;
        }

        $wrapped_message = $mailer->wrap_message($heading, $body);
        $wc_email = new WC_Email();
        $html_message = $wc_email->style_inline($wrapped_message);
        
        $admin = skianet_site()['admin_email'];

        $recipients = $send_to_admin ?  array($admin) : array($to_email);
        $success = true;

        foreach ($recipients as $recipient) {
            $result = $mailer->send($recipient, $subject, $html_message, '', $attachments);
            if ($result) {
            } else {
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
                unlink($path);
            }
        }
    }

    public function send_mixed_order_coupons($order) {
        
        if (!$order) {
            return;
        }
        
        // ✅ Usa lo stesso metodo ma in modalità "mista"
        // send_coupon_email già skippa automaticamente prodotti con _booking_id
        $this->send_coupon_email($order, false);
    }

}