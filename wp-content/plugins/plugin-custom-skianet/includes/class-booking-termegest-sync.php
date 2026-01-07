<?php
/**
 * Gestisce la sincronizzazione delle prenotazioni con l'API TermeGest
 * Invia i dati dopo l'assegnazione dei codici
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
        // Costruttore vuoto 
    }

    /**
     * Sincronizza prenotazione con TermeGest
    */
    public function sync_booking_to_termegest($order_id) {
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
        
        // Processa ogni item con prenotazione
        foreach ($order->get_items() as $item_id => $item) {
            $booking_id = $item->get_meta('_booking_id');
            
            if ($booking_id) {
                $this->sync_item_to_termegest($order, $item, $item_id);
            }
        }
    }

    /**
     * Sincronizza singolo item con TermeGest
    */
    private function sync_item_to_termegest($order, $item, $item_id) {
        $order_id = $order->get_id();
        $booking_id = $item->get_meta('_booking_id');
        
        error_log("Sync item {$item_id} - Prenotazione {$booking_id}");
        
        // Recupera i codici assegnati
        $codes = $this->get_license_codes_for_item($item);
        
        if (empty($codes)) {
            $error_msg = sprintf(
                'Errore ordine %s: nessun codice trovato per %s',
                $order_id,
                $item->get_name()
            );
            
            error_log("❌ {$error_msg}");
            $order->add_order_note("ERRORE: {$error_msg}");
            
            // ⚠️ NON fallire l'ordine, solo log errore
            // L'ordine è già pagato, non possiamo fallirlo
            return;
        }
        
        error_log("Trovati " . count($codes) . " codici per item {$item_id}");
        
        // Recupera dati prenotazione per validazione
        $booking_data = Booking_Cart_Handler::get_booking_data_from_order_item($item);
        $expected_codes = (int)$booking_data['total_guests'];
        
        // ✅ Valida numero codici vs numero ospiti
        if (count($codes) !== $expected_codes) {
            $warning_msg = sprintf(
                'ATTENZIONE: Trovati %d codici ma richiesti %d ospiti per %s',
                count($codes),
                $expected_codes,
                $item->get_name()
            );
            
            error_log("⚠️ {$warning_msg}");
            $order->add_order_note($warning_msg);
        }
        
        // Dati cliente
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $customer_email = $order->get_billing_email();
        $item_price = $item->get_total();
        
        // Calcola prezzo per singolo codice
        $price_per_code = count($codes) > 0 ? ($item_price / count($codes)) : $item_price;
        
        // Invia ogni codice a TermeGest
        $success_count = 0;
        $error_count = 0;
        
        foreach ($codes as $index => $code) {
            $result = $this->send_code_to_termegest(
                $code, 
                $price_per_code, 
                $customer_name, 
                $customer_email, 
                $order_id, 
                $item_id,
                $index + 1, // Numero progressivo codice
                count($codes) // Totale codici
            );
            
            if ($result) {
                $success_count++;
            } else {
                $error_count++;
            }
        }
        
        // Aggiungi nota all'ordine
        if ($success_count > 0) {
            $order->add_order_note(
                sprintf(
                    'TermeGest: %d/%d codici sincronizzati per %s',
                    $success_count,
                    count($codes),
                    $item->get_name()
                )
            );
        }
        
        if ($error_count > 0) {
            $order->add_order_note(
                sprintf(
                    'ATTENZIONE: %d/%d codici NON sincronizzati con TermeGest per %s',
                    $error_count,
                    count($codes),
                    $item->get_name()
                )
            );
        }
        
        error_log("✅ Sync completato: {$success_count} successi, {$error_count} errori");
    }

    /**
     * Invia singolo codice a TermeGest
    */
    private function send_code_to_termegest($code, $price, $customer_name, $customer_email, $order_id, $item_id, $code_number = 1, $total_codes = 1) {
        $params = array(
            'codice' => $code,
            'prezzo' => $price,
            'nome_cliente' => $customer_name,
            'email_cliente' => $customer_email
        );
        
        error_log("🔄 Chiamata setVenduto [{$code_number}/{$total_codes}] per codice {$code}:");
        error_log("   Ordine: {$order_id}, Item: {$item_id}");
        error_log("   Parametri: " . print_r($params, true));
        
        try {
            $response = skianet_termegest_set_venduto(
                $code,
                $price,
                $customer_name,
                $customer_email
            );
            
            if ($response && !is_wp_error($response)) {
                error_log("✅ Codice {$code} sincronizzato con TermeGest");
                return true;
            } else {
                $error_msg = is_wp_error($response) ? $response->get_error_message() : 'Risposta non valida';
                error_log("❌ Errore sync codice {$code}: {$error_msg}");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("❌ Eccezione sync codice {$code}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Recupera i codici licenza assegnati a un order item
    */
    private function get_license_codes_for_item($item) {
        $item_id = $item->get_id();
        
        // Recupera gli ID dei codici dal meta dell'order item
        $code_ids = wc_get_order_item_meta($item_id, '_license_code_ids');
        
        if (empty($code_ids) || !is_array($code_ids)) {
            error_log("❌ Nessun ID codice trovato per item {$item_id}");
            return array();
        }
        
        error_log("Trovati " . count($code_ids) . " ID codici per item {$item_id}");
        
        $codes = array();
        
        // Recupera ogni codice dal database tramite WC License Delivery
        foreach ($code_ids as $code_id) {
            $code_data = WC_LD_Model::get_codes_by_id($code_id);
            
            if (!empty($code_data[0]) && !empty($code_data[0]['license_code1'])) {
                $codes[] = $code_data[0]['license_code1'];
            } else {
                error_log("⚠️ Codice ID {$code_id} non trovato o vuoto");
            }
        }
        
        if (empty($codes)) {
            error_log("❌ Nessun codice valido recuperato per item {$item_id}");
        } else {
            error_log("✅ Recuperati " . count($codes) . " codici: " . implode(', ', $codes));
        }
        
        return $codes;
    }
}