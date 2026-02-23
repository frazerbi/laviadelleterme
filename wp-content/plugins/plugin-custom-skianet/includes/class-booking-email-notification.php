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
        // Invia email quando ordine diventa "Booked"
        // add_action('woocommerce_order_status_booked', array($this, 'send_on_status_booked'), 10, 2);
        // add_action('woocommerce_order_status_changed', array($this, 'send_on_status_booked'), 10, 4);
        // add_action('woocommerce_thankyou', array($this, 'send_on_thankyou_test'), 10, 1);
        add_action('woocommerce_thankyou', array($this, 'send_on_status_booked'), 10, 1);

        // Azioni manuali admin
        add_action('woocommerce_order_actions', array($this, 'add_order_actions'));
        add_action('woocommerce_order_action_send_booking_confirmation', array($this, 'send_to_customer'));
        add_action('woocommerce_order_action_send_booking_confirmation_to_admin', array($this, 'send_to_admin'));
    }

    /**
     * Test hook per thankyou page
     * ⚠️ SOLO PER TEST - Commentare in produzione
     */
    public function send_on_thankyou_test($order_id) {
        error_log("=== TEST THANKYOU: Ordine {$order_id} ===");
        
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("Ordine {$order_id} non trovato");
            return;
        }

        $has_booking = false;
        $has_nonbooking = false;

        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_booking_id')) {
                $has_booking = true;
            } else {
                $has_nonbooking = true;
            }
        }
                
        // Invia email test
        $this->send_booking_details($order);
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
            $detail .= "<strong>Quantità:</strong> <span style='color: #0074A0;'>" . $item->get_quantity() . "</span><br>";
            $detail .= "<strong>Totale:</strong> <span style='color: #0074A0;'>€" . $item->get_total() . "</span><br>";
            
            // Codici
            if (!empty($codes)) {
                $detail .= "<strong>Codici Coupon:</strong> <span style='color: #0074A0;'>" . implode(", ", $codes) . "</span><br>";
            }
            
            // Dati prenotazione
            $detail .= "<strong>Location:</strong> <span style='color: #0074A0;'>" . $booking_data['location_name'] . "</span><br>";
            $detail .= "<strong>Data:</strong> <span style='color: #0074A0;'>" . $formatted_date . "</span><br>";

            // Orario
            if (!empty($booking_data['time_slot_label'])) {
                $detail .= "<strong>Orario:</strong> <span style='color: #0074A0;'>" . esc_html($booking_data['time_slot_label']) . "</span><br>";
            }

            $detail .= "<strong>Tipo Ingresso:</strong> <span style='color: #0074A0;'>" . ($booking_data['ticket_type'] === '4h' ? '4 Ore' : 'Giornaliero') . "</span><br>";
            
            // Ospiti
            if ($booking_data['num_male'] > 0) {
                $detail .= "<strong>Uomini:</strong> <span style='color: #0074A0;'>" . $booking_data['num_male'] . "</span><br>";
            }
            if ($booking_data['num_female'] > 0) {
                $detail .= "<strong>Donne:</strong> <span style='color: #0074A0;'>" . $booking_data['num_female'] . "</span><br>";
            }
            
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
        $greeting = "<p>Grazie per la tua prenotazione.";
        if ($order) {
            $first_name = $order->get_billing_first_name();
            $last_name = $order->get_billing_last_name();
            if ($first_name || $last_name) {
                $greeting = "<p>Gentile <strong>" . trim($first_name . ' ' . $last_name) . "</strong>, grazie per la tua prenotazione.";
            }
        }

        $policy_info = "<br><br><strong>Dichiarazioni e Informative:</strong><br>";
        $policy_info .= "<p>Il Sottoscritto/a <strong>DICHIARA</strong> sotto la propria responsabilità di avere preso visione delle norme comportamentali per i servizi offerti dalla struttura, a disposizione alla reception della spa, ed in particolare:</p>";
        $policy_info .= "<ul>";
        $policy_info .= "<li>Di essere a conoscenza che l'uso di sauna e bagno turco non sono idonei a coloro che hanno disturbi di pressione arteriosa e presenza di patologia a carico del sistema venoso superficiale e profondo.</li>";
        $policy_info .= "<li>Dichiara altresì di godere di sana e robusta costituzione e di essersi sottoposto di recente a visita medica per accertare la propria idoneità fisica ed esonera pertanto la struttura da qualsiasi responsabilità.</li>";
        $policy_info .= "<li>Dichiara di non accusare sintomi quali: febbre, tosse, difficoltà respiratorie.</li>";
        $policy_info .= "<li>Dichiara di non aver soggiornato in zone e/o Paesi con presunta trasmissione comunitaria.</li>";
        $policy_info .= "</ul>";
        $policy_info .= "<p><strong>Politica di cancellazione:</strong> È possibile cancellare la prenotazione fino a 48 ore prima tramite il seguente link: <a href=\"https://www.termegest.it/annullapren.aspx\">https://www.termegest.it/annullapren.aspx</a>. Dopo tale termine non sarà più possibile annullarla e il coupon verrà riscattato automaticamente.</p>";
        $policy_info .= "<p>Ai sensi degli articoli 13 e 23 del DLgs. 196/03, del Regolamento EU 679/2016 e del Dlgs 101/2018 (Codice sulla protezione dei dati personali), l'interessato dichiara di essere stato adeguatamente informato ed esprime il proprio consenso all'utilizzo dei dati personali che lo riguardano, con particolare riferimento ai dati che la legge definisce come \"sensibili\", nei limiti di quanto indicato nell'informativa.</p>";
        $policy_info .= "<p>Dichiaro di avere preso visione del regolamento interno che definisce le modalità per la custodia dei valori accettandone i contenuti.</p>";

        $email_body = $greeting . " Di seguito i dettagli dell'ordine:</p><br>";
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