<?php
/**
 * Gestisce la sincronizzazione completa con l'API TermeGest
 * - Prodotti CON prenotazione: invia dati venduto + prenotazione
 * - Prodotti SENZA prenotazione: invia solo dati venduto
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

class Booking_TermeGest_Sync {

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
        // Costruttore vuoto - viene chiamato direttamente da Booking_Code_Assignment
    }

    /**
     * Sincronizza tutti i prodotti dell'ordine con TermeGest
     * Gestisce sia prodotti con che senza prenotazione
     */
    public function sync_order_to_termegest($order_id) {
        error_log("=== TERMEGEST SYNC: Ordine {$order_id} ===");
        
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("Ordine {$order_id} non trovato");
            return;
        }
        
        // Verifica che la funzione API sia disponibile
        if (!function_exists('skianet_termegest_set_venduto')) {
            error_log("❌ Funzione skianet_termegest_set_venduto non disponibile");
            return;
        }
        
        // Processa TUTTI gli items dell'ordine
        $booking_items = 0;
        $nonbooking_items = 0;
        
        foreach ($order->get_items() as $item_id => $item) {
            $has_booking = $item->get_meta('_booking_id');
            
            if ($has_booking) {
                // ✅ Prodotto CON prenotazione
                $this->sync_booking_item($order, $item, $item_id);
                $booking_items++;
            } else {
                // ✅ Prodotto SENZA prenotazione
                $this->sync_nonbooking_item($order, $item, $item_id);
                $nonbooking_items++;
            }
        }
        
        error_log("✅ Sync completato: {$booking_items} con prenotazione, {$nonbooking_items} senza prenotazione");
    }

    /**
     * Sincronizza item CON prenotazione (venduto + dati prenotazione)
     */
    private function sync_booking_item($order, $item, $item_id) {
        $order_id = $order->get_id();
        $booking_id = $item->get_meta('_booking_id');
        
        error_log("Sync BOOKING item {$item_id} - Prenotazione {$booking_id}");
        
        // Recupera codici
        $codes = $this->get_license_codes_for_item($item);
        
        if (empty($codes)) {
            error_log("❌ Nessun codice trovato per booking item {$item_id}");
            $order->add_order_note("ERRORE: Nessun codice per " . $item->get_name());
            return;
        }
        
        // Recupera dati prenotazione
        $booking_data = Booking_Cart_Handler::get_booking_data_from_order_item($item);
        
        // Valida numero codici
        if (count($codes) !== (int)$booking_data['total_guests']) {
            error_log("⚠️ Codici: " . count($codes) . " vs Ospiti: " . $booking_data['total_guests']);
        }
        
        // Invia codici
        $results = $this->send_codes_to_termegest($order, $item, $codes, true, $booking_data);
        
        // Log risultati
        $this->log_sync_results($order, $item, $results, true);
    }

    /**
     * Sincronizza item SENZA prenotazione (solo venduto)
     */
    private function sync_nonbooking_item($order, $item, $item_id) {
        error_log("Sync NON-BOOKING item {$item_id}: " . $item->get_name());
        
        // Recupera codici
        $codes = $this->get_license_codes_for_item($item, true); // usa query DB diretta
        
        if (empty($codes)) {
            error_log("⚠️ Nessun codice per non-booking item {$item_id} - skip");
            return;
        }
        
        // Invia codici (senza dati prenotazione)
        $results = $this->send_codes_to_termegest($order, $item, $codes, false);
        
        // Log risultati
        $this->log_sync_results($order, $item, $results, false);
    }

    /**
     * Invia array di codici a TermeGest
     */
    private function send_codes_to_termegest($order, $item, $codes, $is_booking = false, $booking_data = null) {
        $order_id = $order->get_id();
        $item_id = $item->get_id();
        
        // Dati cliente
        $user = wp_get_current_user();
        $customer_name = $user && $user->exists() ? 
            $user->user_login : 
            $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $customer_email = $user && $user->exists() ? 
            $user->user_email : 
            $order->get_billing_email();
        
        // Calcola prezzo per codice
        $item_total = $item->get_total();
        $quantity = $item->get_quantity();
        $price_per_code = $quantity > 0 ? ($item_total / $quantity) : $item_total;
        
        $results = array(
            'success' => 0,
            'error' => 0
        );
        
        // Invia ogni codice
        foreach ($codes as $index => $code) {
            $label = $is_booking ? 'BOOKING' : 'NON-BOOKING';
            
            error_log("[{$label}] Codice [" . ($index + 1) . "/" . count($codes) . "]: {$code}");
            error_log("   Ordine: {$order_id}, Item: {$item_id}, Prezzo: {$price_per_code}");

            try {
                // TODO: Se is_booking, qui potresti anche chiamare setPrenotazione
                // oltre a setVenduto, se necessario
                
                $response = skianet_termegest_set_venduto(
                    $code,
                    $price_per_code,
                    $customer_name,
                    $customer_email
                );
                
                if ($response && !is_wp_error($response)) {
                    error_log("✅ [{$label}] Codice {$code} sincronizzato");
                    $results['success']++;
                } else {
                    $error_msg = is_wp_error($response) ? $response->get_error_message() : 'Risposta non valida';
                    error_log("❌ [{$label}] Errore codice {$code}: {$error_msg}");
                    $results['error']++;
                }
                
            } catch (Exception $e) {
                error_log("❌ [{$label}] Eccezione codice {$code}: " . $e->getMessage());
                $results['error']++;
            }
        }
        
        return $results;
    }

    /**
     * Log risultati sync e aggiungi note ordine
     */
    private function log_sync_results($order, $item, $results, $is_booking) {
        $label = $is_booking ? 'prenotazione' : 'prodotto';
        $total = $results['success'] + $results['error'];
        
        if ($results['success'] > 0) {
            $order->add_order_note(
                sprintf(
                    'TermeGest: %d/%d codici sincronizzati per %s (%s)',
                    $results['success'],
                    $total,
                    $item->get_name(),
                    $label
                )
            );
        }
        
        if ($results['error'] > 0) {
            $order->add_order_note(
                sprintf(
                    'ATTENZIONE: %d/%d codici NON sincronizzati per %s',
                    $results['error'],
                    $total,
                    $item->get_name()
                )
            );
        }
        
        error_log("Risultati {$label}: {$results['success']} successi, {$results['error']} errori");
    }

    /**
     * Recupera codici licenza per item
     * Supporta due metodi: WC License Delivery model o query DB diretta
     */
    private function get_license_codes_for_item($item, $use_db_query = false) {
        $item_id = $item->get_id();
        
        if ($use_db_query) {
            // Metodo DB diretto (per prodotti non-booking)
            return $this->get_codes_from_db($item);
        }
        
        // Metodo WC License Delivery (per prodotti booking)
        $code_ids = wc_get_order_item_meta($item_id, '_license_code_ids');
        
        if (empty($code_ids) || !is_array($code_ids)) {
            error_log("Nessun ID codice trovato per item {$item_id}");
            return array();
        }
        
        $codes = array();
        foreach ($code_ids as $code_id) {
            $code_data = WC_LD_Model::get_codes_by_id($code_id);
            
            if (!empty($code_data[0]['license_code1'])) {
                $codes[] = $code_data[0]['license_code1'];
            }
        }
        
        error_log("Recuperati " . count($codes) . " codici per item {$item_id}");
        return $codes;
    }

    /**
     * Recupera codici dal database direttamente
     */
    private function get_codes_from_db($item) {
        global $wpdb;
        
        $order_id = $item->get_order_id();
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $check_id = $variation_id > 0 ? $variation_id : $product_id;
        
        $query = $wpdb->prepare(
            "SELECT license_code1 FROM {$wpdb->prefix}wc_ld_license_codes 
            WHERE order_id = %d AND product_id = %d",
            $order_id,
            $check_id
        );
        
        $results = $wpdb->get_results($query);
        
        $codes = array();
        foreach ($results as $row) {
            if (!empty($row->license_code1)) {
                $codes[] = $row->license_code1;
            }
        }
        
        return $codes;
    }
}