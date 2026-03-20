<?php
/**
 * Gestisce l'invio delle email di conferma prenotazione
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

class Booking_Email_Notification {

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
        // Invia email quando ordine viene pagato
        add_action('woocommerce_payment_complete', array($this, 'send_on_status_booked'), 20, 1);

        // Azioni manuali admin
        add_action('woocommerce_order_actions', array($this, 'add_order_actions'));
        add_action('woocommerce_order_action_send_booking_confirmation', array($this, 'send_to_customer'));
        add_action('woocommerce_order_action_send_booking_confirmation_to_admin', array($this, 'send_to_admin'));

        // Footer email
        add_filter('woocommerce_email_footer_text', array($this, 'custom_email_footer_text'));
    }

    public function custom_email_footer_text() {
        return 'La Via Delle Terme';
    }

    /**
     * Invia email quando ordine diventa "Booked"
     */
    public function send_on_status_booked($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            error_log("Ordine {$order_id} non trovato");
            return;
        }

        $this->send_booking_details($order);

    }

    /**
     * Aggiungi azioni al dropdown ordine admin
     */
    public function add_order_actions($actions) {
        $actions['send_booking_confirmation'] = 'Send Booking Confirmation To Customer';
        $actions['send_booking_confirmation_to_admin'] = 'Send Booking Confirmation To Admin';
        return $actions;
    }

    /**
     * Invia email al cliente (azione manuale)
     */
    public function send_to_customer($order) {
        if (!$order) {
            error_log('No order object provided for customer email');
            return;
        }
        
        try {
            $this->send_booking_details($order);
        } catch (Exception $e) {
            error_log("Error sending booking email: " . $e->getMessage());
        }
    }

    /**
     * Invia email all'admin (azione manuale)
     */
    public function send_to_admin($order) {
        if (!$order) {
            error_log('No order object provided for admin email');
            return;
        }
        
        try {
            $this->send_booking_details($order, true);
        } catch (Exception $e) {
            error_log("Error sending admin booking email: " . $e->getMessage());
        }
    }

    /**
     * Invia email con dettagli prenotazione
     */
    private function send_booking_details($order, $send_to_admin = false) {
        error_log('send_booking_details called');

        if (!$order) {
            error_log('Order object is not valid');
            return;
        }

        $order = is_object($order) ? $order : wc_get_order($order);

        if (!$order) {
            error_log('Unable to load order');
            return;
        }

        $order_id = $order->get_id();
        
        // Verifica se l'ordine ha prenotazioni
        $has_booking = false;
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_booking_id')) {
                $has_booking = true;
                break;
            }
        }

        // Email per prodotti con prenotazione
        if ($has_booking) {
            error_log("Order {$order_id} has booking items - sending booking email");

            // Prepara dettagli ordine
            $order_details = $this->build_order_details($order);
            
            if (empty($order_details)) {
                error_log("No booking details to send for order {$order_id}");
                return;
            }

            // Costruisci email
            $email_body = $this->build_email_body($order_details, $order);
            
            // Invia email
            $this->send_email(
                $order->get_billing_email(),
                'Conferma Prenotazione',
                'Conferma della tua prenotazione',
                $email_body,
                $send_to_admin
            );       
        }

        // Email per prodotti senza prenotazione (ordini misti o solo non-booking)
        $nonbooking_email = Booking_Nonbooking_Email::get_instance();
        $nonbooking_email->send_mixed_order_coupons($order);

    }

    /**
     * Costruisce i dettagli dell'ordine
     */
    private function build_order_details($order) {
        $order_details = array();

        foreach ($order->get_items() as $item) {
            $booking_id = $item->get_meta('_booking_id');
            
            if (!$booking_id) {
                continue; // Skip prodotti senza prenotazione
            }
            
            if (!class_exists('Booking_Cart_Handler')) {
                error_log('Booking_Cart_Handler NON caricata');
            }

            // Recupera codici
            $booking_data = Booking_Cart_Handler::get_booking_data_from_order_item($item);
        
            // ✅ Usa metodo condiviso
            $codes = Booking_Cart_Handler::get_item_license_codes(
                $order->get_id(), 
                $item->get_product_id(), 
                $item->get_variation_id()
            );
        

            // Formatta data
            $date = DateTime::createFromFormat('Y-m-d', $booking_data['booking_date']);
            $formatted_date = $date ? $date->format('d/m/Y') : $booking_data['booking_date'];
            
            // Costruisci dettaglio
            $detail = "<div style='margin-bottom: 15px; padding: 10px; border-left: 3px solid #0074A0;'>";
            $detail .= "<strong>Prodotto:</strong> <span style='color: #0074A0;'>" . $item->get_name() . "</span><br>";
            $detail .= "<strong>Quantità Coupon:</strong> <span style='color: #0074A0;'>" . $item->get_quantity() . "</span><br>";
            $detail .= "<strong>Totale:</strong> <span style='color: #0074A0;'>€" . $item->get_total() . "</span><br>";

            // Codici
            if (!empty($codes)) {
                $detail .= "<strong>Codici Coupon:</strong> <span style='color: #0074A0;'>" . implode(", ", $codes) . "</span><br>";
            }

            // Dati prenotazione
            $detail .= "<strong>Location preferita:</strong> <span style='color: #0074A0;'>" . $booking_data['location_name'] . "</span><br>";
            $detail .= "<strong>Data prenotazione:</strong> <span style='color: #0074A0;'>" . $formatted_date . "</span><br>";

            // Orario
            if (!empty($booking_data['time_slot_label'])) {
                $detail .= "<strong>Orario prenotazione:</strong> <span style='color: #0074A0;'>" . esc_html($booking_data['time_slot_label']) . "</span><br>";
            }

            $ticket_labels = array('4h' => '4 Ore', 'giornaliero' => 'Giornaliero', 'serale' => 'Serale');
            $detail .= "<strong>Tipo Ingresso:</strong> <span style='color: #0074A0;'>" . ($ticket_labels[$booking_data['ticket_type']] ?? $booking_data['ticket_type']) . "</span><br>";

            // Ospiti
            if ($booking_data['num_male'] > 0) {
                $detail .= "<strong>Uomini:</strong> <span style='color: #0074A0;'>" . $booking_data['num_male'] . "</span><br>";
            }
            if ($booking_data['num_female'] > 0) {
                $detail .= "<strong>Donne:</strong> <span style='color: #0074A0;'>" . $booking_data['num_female'] . "</span><br>";
            }

            $detail .= "<strong>Scadenza Coupon:</strong> <span style='color: #0074A0;'>sei mesi dalla data di emissione del Coupon</span><br>";

            $detail .= "</div>";
            
            $order_details[] = $detail;
        }

        return $order_details;
    }

    /**
     * Costruisce il corpo dell'email
     */
    private function build_email_body($order_details, $order = null) {
        $order_details_text = implode("<br>", $order_details);

        // Saluto con nome cliente
        $greeting = "<p>Grazie per la tua prenotazione presso le nostre Strutture.";
        if ($order) {
            $first_name = $order->get_billing_first_name();
            $last_name = $order->get_billing_last_name();
            if ($first_name || $last_name) {
                $greeting = "<p>Gentile <strong>" . trim($first_name . ' ' . $last_name) . "</strong>, grazie per la tua prenotazione presso le nostre Strutture.";
            }
        }

        $policy_info = "<br><br><strong>Dichiarazioni e Informative:</strong><br>";
        $policy_info .= "<p>Il/la Sottoscritto/a dichiara, sotto la propria responsabilità, di avere preso visione del Regolamento per l'accesso ai servizi offerti dalle nostre strutture scaricabile al link: <a href=\"https://laviadelleterme.it/regolamento-strutture/\">https://laviadelleterme.it/regolamento-strutture/</a>, e di:</p>";
        $policy_info .= "<ul>";
        $policy_info .= "<li>essere a conoscenza che l'utilizzo di sauna e bagno turco non sono idonei per persone con disturbi di pressione arteriosa e la presenza di patologie a carico del sistema venoso superficiale e profondo.</li>";
        $policy_info .= "<li>godere di sana e robusta costituzione e di essersi sottoposto di recente a visita medica per accertare la propria idoneità fisica ed esonera pertanto la struttura da qualsiasi responsabilità.</li>";
        $policy_info .= "</ul>";
        $policy_info .= "<p><strong>Cambio della Struttura prenotata</strong><br>";
        $policy_info .= "L'acquisto del Coupon le dà diritto di modificare la scelta della Struttura qui prenotata con un'altra delle nostre due strutture che trova nella nostra Home page fino a 48 ore precedenti alla data della prenotazione, salvo disponibilità. Dopo non sarà possibile modificare la scelta della Struttura.</p>";
        $policy_info .= "<p><strong>Cancellazione e modifica della Prenotazione</strong><br>";
        $policy_info .= "La sua prenotazione è cancellabile, o modificabile, fino a 48 ore precedenti alla data della prenotazione. Dopo non sarà possibile modificarla.<br>";
        $policy_info .= "Per cancellare la prenotazione accedi al link: <a href=\"https://www.termegest.it/annullapren.aspx\">https://www.termegest.it/annullapren.aspx</a></p>";
        $policy_info .= "<p><strong>Privacy</strong><br>";
        $policy_info .= "Ai sensi degli articoli 13 e 23 del DLgs. 196/03, del Regolamento EU 679/2016 e del Dlgs 101/2018 (Codice sulla protezione dei dati personali), l'interessato dichiara di essere stato adeguatamente informato ed esprime il proprio consenso all'utilizzo dei dati personali che lo riguardano, con particolare riferimento ai dati che la legge definisce come \"sensibili\", nei limiti di quanto indicato nell'informativa.</p>";

        $email_body = $greeting . " Ecco i dettagli del tuo Coupon:</p><br>";
        $email_body .= $order_details_text . $policy_info;
        
        return $email_body;
    }

    /**
     * Invia email
     */
    private function send_email($to_email, $subject, $heading, $body, $send_to_admin = false) {
        $recipient = $send_to_admin ? 'francesco.zerbinato@gmail.com' : $to_email;

        if (!$recipient) {
            error_log('No email address provided');
            return;
        }
        
        $mailer = WC()->mailer();
        $wrapped_message = $mailer->wrap_message($heading, $body);
        $wc_email = new WC_Email();
        $html_message = $wc_email->style_inline($wrapped_message);
        
        try {
            $mailer->send($recipient, $subject, $html_message);
            error_log("Booking email sent to {$recipient}");
        } catch (Exception $e) {
            error_log("Error sending email to {$recipient}: " . $e->getMessage());
        }
    }

}