<?php
/**
 * Gestisce la sincronizzazione completa con l'API TermeGest
 * - Prodotti CON prenotazione: invia setVenduto + setPrenotazione
 * - Prodotti SENZA prenotazione: invia solo setVenduto
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
     * Sincronizza tutti i prodotti dell'ordine con TermeGest
     */
    public function sync_order_to_termegest($order_id) {
        error_log("=== TERMEGEST SYNC: Ordine {$order_id} ===");
        
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("Ordine {$order_id} non trovato");
            return;
        }
        
        // Verifica funzioni API disponibili
        if (!function_exists('skianet_termegest_set_venduto')) {
            error_log("❌ Funzione skianet_termegest_set_venduto non disponibile");
            return;
        }
        
        $has_prenotazione_function = function_exists('skianet_termegest_set_prenotazione');
        if (!$has_prenotazione_function) {
            error_log("⚠️ Funzione skianet_termegest_set_prenotazione non disponibile - skip prenotazioni");
        }
        
        // Processa TUTTI gli items dell'ordine
        $booking_items = 0;
        $nonbooking_items = 0;
        
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_booking_id')) {
                $this->sync_booking_item($order, $item, $has_prenotazione_function);
                $booking_items++;
            } else {
                $this->sync_nonbooking_item($order, $item);
                $nonbooking_items++;
            }
        }
        
        error_log("✅ Sync completato: {$booking_items} con prenotazione, {$nonbooking_items} senza prenotazione");
    }

    /**
     * Sincronizza item CON prenotazione (setVenduto + setPrenotazione)
     */
    private function sync_booking_item($order, $item, $has_prenotazione_function) {
        $item_id = $item->get_id();
        $booking_id = $item->get_meta('_booking_id');
        
        error_log("Sync BOOKING item {$item_id} - Prenotazione {$booking_id}");
        
        // Recupera codici
        $codes = $this->get_license_codes_for_item($item);

        error_log("=== CODICI RECUPERATI PER ITEM {$item_id} ===");
        error_log("Totale codici: " . count($codes));
        foreach ($codes as $index => $code) {
            error_log(sprintf(
                "  Codice [%d]: '%s' (lunghezza: %d, hex: %s)",
                $index + 1,
                $code,
                strlen($code),
                bin2hex($code)
            ));
        }
        error_log("===========================================");

        if (empty($codes)) {
            error_log("❌ Nessun codice trovato per booking item {$item_id}");
            $order->add_order_note("ERRORE: Nessun codice per " . $item->get_name());
            return;
        }
        
        // Recupera dati prenotazione
        $booking_data = Booking_Cart_Handler::get_booking_data_from_order_item($item);
        
        error_log('=== BOOKING DATA FROM ORDER ITEM === ' . var_export($booking_data, true));
        error_log(print_r($booking_data, true));
        // Valida numero codici
        if (count($codes) !== (int)$booking_data['total_guests']) {
            error_log("⚠️ Codici: " . count($codes) . " vs Ospiti: " . $booking_data['total_guests']);
        }
        
        // Invia codici con prenotazione
        $results = $this->send_booking_codes($order, $item, $codes, $booking_data, $has_prenotazione_function);
        
        // Log risultati
        $this->log_sync_results($order, $item, $results, true);
    }

    /**
     * Sincronizza item SENZA prenotazione (solo setVenduto)
     */
    private function sync_nonbooking_item($order, $item) {
        $item_id = $item->get_id();
        error_log("Sync NON-BOOKING item {$item_id}: " . $item->get_name());
        
        // Recupera codici
        $codes = $this->get_license_codes_for_item($item, true);
        
        if (empty($codes)) {
            error_log("⚠️ Nessun codice per non-booking item {$item_id} - skip");
            return;
        }
        
        // Recupera dati cliente
        $customer = $this->get_customer_data($order);
        
        // Calcola prezzo per codice
        $price_per_code = $this->calculate_price_per_code($item, $item->get_quantity());
        
        // Invia codici
        $results = array('venduto_success' => 0, 'venduto_error' => 0);
        
        foreach ($codes as $index => $code) {
            error_log("[NON-BOOKING] Codice [" . ($index + 1) . "/" . count($codes) . "]: {$code}");
            error_log("   Ordine: {$order->get_id()}, Item: {$item_id}, Prezzo: {$price_per_code}");
            
            if ($this->send_venduto($code, $price_per_code, $customer['name'], $customer['email'])) {
                error_log("✅ [NON-BOOKING] Codice {$code} sincronizzato");
                $results['venduto_success']++;
            } else {
                $results['venduto_error']++;
            }
        }
        
        // Log risultati
        $this->log_sync_results($order, $item, $results, false);
    }

    /**
     * Invia codici con prenotazione (setVenduto + setPrenotazione)
     */
    private function send_booking_codes($order, $item, $codes, $booking_data, $has_prenotazione_function) {
        $order_id = $order->get_id();
        
        // Recupera dati cliente
        $customer = $this->get_customer_data($order);
        
        // Cripta location per protection
        $encryption = TermeGest_Encryption::get_instance();
        $protection = $encryption->encrypt($booking_data['location_name']);

        if (empty($protection)) {
            error_log("❌ Impossibile criptare location per protection");
            return array(
                'venduto_success' => 0,
                'venduto_error' => 0,
                'prenotazione_success' => 0,
                'prenotazione_error' => 0
            );
        }
        
        error_log("Protection generato per location {$booking_data['location_name']}: {$protection}");

        // Calcola prezzo per codice
        $price_per_code = $this->calculate_price_per_code($item, count($codes));
        
        $results = array(
            'venduto_success' => 0,
            'venduto_error' => 0,
            'prenotazione_success' => 0,
            'prenotazione_error' => 0
        );
        
        // Determina sesso ospiti (primi X = maschi, restanti = femmine)
        $num_male = (int)$booking_data['num_male'];
        
        $order_notes = $order->get_customer_note();
        if (empty($order_notes)) {
            $order_notes = "Prenotazione online - Ordine #{$order_id}";
        }

        foreach ($codes as $index => $code) {
            $is_male = $index < $num_male;
            
            error_log("[BOOKING] Codice [" . ($index + 1) . "/" . count($codes) . "]: {$code}");
            error_log("   Ordine: {$order_id}, Sesso: " . ($is_male ? 'M' : 'F'));
            
            // STEP 1: setVenduto
            if ($this->send_venduto($code, $price_per_code, $customer['name'], $customer['email'])) {
                error_log("✅ [BOOKING] setVenduto OK per codice {$code}");
                $results['venduto_success']++;
                
                // STEP 2: setPrenotazione
                if ($has_prenotazione_function) {
                    $prenotazione_result = $this->send_prenotazione(
                        $code,
                        $booking_data,
                        $customer,
                        $is_male,
                        $protection,
                        $order_id,
                        $order_notes
                    );
                    
                    if ($prenotazione_result) {
                        $results['prenotazione_success']++;
                    } else {
                        $results['prenotazione_error']++;
                    }
                }
                
            } else {
                $results['venduto_error']++;
            }
        }
        
        return $results;
    }

    /**
     * Invia setVenduto
     */
    private function send_venduto($code, $price, $customer_name, $customer_email) {
        try {
            $response = skianet_termegest_set_venduto(
                $code,
                $price,
                $customer_name,
                $customer_email
            );
            
            if ($response && !is_wp_error($response)) {
                return true;
            }
            
            $error_msg = is_wp_error($response) ? $response->get_error_message() : 'Risposta non valida';
            error_log("❌ setVenduto ERRORE: {$error_msg}");
            return false;
            
        } catch (Exception $e) {
            error_log("❌ Eccezione setVenduto: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Invia setPrenotazione
     */
    private function send_prenotazione($code, $booking_data, $customer, $is_male, $protection, $order_id, $order_notes) {
        
            // Estrai categoria validata (sempre array tipo ['p3'])
            $categoria = is_array($booking_data['categorie'])
                ? $booking_data['categorie'][0]
                : $booking_data['categorie'];

            // Log parametri per debug
            error_log("SetPrenotazione params:");
            error_log("  idDisponibilita: " . (int)$booking_data['fascia_id']);
            error_log("  codice: {$code}");
            error_log("  Cognome: {$customer['last_name']} (" . strlen($customer['last_name']) . " chars)");
            error_log("  Nome: {$customer['first_name']} (" . strlen($customer['first_name']) . " chars)");
            error_log("  Telefono: {$customer['phone']} (" . strlen($customer['phone']) . " chars)");
            error_log("  Note: {$order_notes} (" . strlen($order_notes) . " chars)");
            error_log("  Provincia: '{$customer['state']}' (" . strlen($customer['state']) . " chars)");
            error_log("  uomodonna: " . ($is_male ? 'true' : 'false'));
            error_log("  Email: {$customer['email']} (" . strlen($customer['email']) . " chars)");
            error_log("  AllInclusive: false");
            error_log(" Categoria: {$categoria} (" . strlen($categoria) . " chars)");
            error_log("  Protection: " . strlen($protection) . " chars");
            
        try {
            $response = skianet_termegest_set_prenotazione(
                (int)$booking_data['fascia_id'],
                $code,
                $customer['last_name'],
                $customer['first_name'],
                $customer['phone'],
                $order_notes,
                $customer['state'],
                $is_male,
                $customer['email'],
                false,
                $categoria,
                '',
                $protection
            );
            
            if ($response['status']) {
                error_log("✅ [BOOKING] setPrenotazione OK per codice {$code}");
                return true;
            } else {
                error_log("❌ [BOOKING] setPrenotazione ERRORE: " . $response['message']);
                return false;
            }
            
        } catch (Exception $e) {
            error_log("❌ Eccezione setPrenotazione: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Recupera dati cliente dall'ordine
     */
    private function get_customer_data($order) {
        $user = wp_get_current_user();
        
        return array(
            'name' => $user && $user->exists() ? 
                $user->user_login : 
                $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'email' => $user && $user->exists() ? 
                $user->user_email : 
                $order->get_billing_email(),
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'phone' => $order->get_billing_phone(),
            'state' => $order->get_billing_state(),
            'city' => $order->get_billing_city(),
            'postcode' => $order->get_billing_postcode()
        );
    }

    /**
     * Calcola prezzo per singolo codice
     */
    private function calculate_price_per_code($item, $divisor) {
        $item_total = $item->get_total();
        return $divisor > 0 ? ($item_total / $divisor) : $item_total;
    }

    /**
     * Log risultati sync
     */
    private function log_sync_results($order, $item, $results, $is_booking) {
        if ($is_booking) {
            // Risultati con prenotazione
            $total_venduto = $results['venduto_success'] + $results['venduto_error'];
            $total_prenotazione = $results['prenotazione_success'] + $results['prenotazione_error'];
            
            if ($results['venduto_success'] > 0) {
                $order->add_order_note(
                    sprintf(
                        'TermeGest setVenduto: %d/%d codici sincronizzati per %s',
                        $results['venduto_success'],
                        $total_venduto,
                        $item->get_name()
                    )
                );
            }
            
            if ($results['prenotazione_success'] > 0) {
                $order->add_order_note(
                    sprintf(
                        'TermeGest setPrenotazione: %d/%d prenotazioni create per %s',
                        $results['prenotazione_success'],
                        $total_prenotazione,
                        $item->get_name()
                    )
                );
            }
            
            if ($results['venduto_error'] > 0 || $results['prenotazione_error'] > 0) {
                $order->add_order_note(
                    sprintf(
                        'ATTENZIONE: Errori sync per %s - Venduto: %d errori, Prenotazione: %d errori',
                        $item->get_name(),
                        $results['venduto_error'],
                        $results['prenotazione_error']
                    )
                );
            }
            
        } else {
            // Risultati senza prenotazione
            $total = $results['venduto_success'] + $results['venduto_error'];
            
            if ($results['venduto_success'] > 0) {
                $order->add_order_note(
                    sprintf(
                        'TermeGest: %d/%d codici sincronizzati per %s',
                        $results['venduto_success'],
                        $total,
                        $item->get_name()
                    )
                );
            }
            
            if ($results['venduto_error'] > 0) {
                $order->add_order_note(
                    sprintf(
                        'ATTENZIONE: %d/%d codici NON sincronizzati per %s',
                        $results['venduto_error'],
                        $total,
                        $item->get_name()
                    )
                );
            }
        }
    }

    /**
     * Recupera codici licenza per item
     */
    private function get_license_codes_for_item($item, $use_db_query = false) {
        if ($use_db_query) {
            return $this->get_codes_from_db($item);
        }
        
        $item_id = $item->get_id();
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

        // ✅ Pulisci tutti i codici da BOM e caratteri invisibili
        $codes = array_map(array($this, 'clean_license_code'), $codes);
        
        // ✅ Rimuovi eventuali valori vuoti
        $codes = array_filter($codes);
        
        // ✅ Re-index array (importante!)
        $codes = array_values($codes);
        
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
                $code = $row->license_code1;
            
                // ✅ Pulisci codice da BOM e caratteri invisibili
                $code = str_replace("\xEF\xBB\xBF", '', $code); // BOM UTF-8
                $code = preg_replace('/[\x00-\x1F\x7F\xA0\xAD]/u', '', $code); // Caratteri invisibili
                $code = trim($code);

                if (!empty($code)) {
                    $codes[] = $code;
                }
            }
        }
        
        return $codes;
    }
}