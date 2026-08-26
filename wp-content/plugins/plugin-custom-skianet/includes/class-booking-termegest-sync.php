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
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Verifica funzioni API disponibili
        if (!function_exists('skianet_termegest_set_venduto')) {
            return;
        }
        
        $has_prenotazione_function = function_exists('skianet_termegest_set_prenotazione');
        
        // Processa TUTTI gli items dell'ordine
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_booking_id')) {
                $this->sync_booking_item($order, $item, $has_prenotazione_function);
            } else {
                $this->sync_nonbooking_item($order, $item);
            }
        }
    }

    /**
     * Sincronizza item CON prenotazione (setVenduto + setPrenotazione)
     */
    private function sync_booking_item($order, $item, $has_prenotazione_function) {
        // Recupera codici direttamente dal DB (usa_db_query = true).
        // La lettura da order item meta (_license_code_ids) fallisce perché WC License
        // Delivery scrive su wc_ld_license_codes bypassando la WP object cache,
        // quindi l'oggetto WC_Order_Item in memoria risulta ancora vuoto nella stessa request.
        $codes = $this->get_license_codes_for_item($item, true);

        if (empty($codes)) {
            $order->add_order_note("ERRORE: Nessun codice per " . $item->get_name());
            return;
        }
        
        // Recupera dati prenotazione
        $booking_data = Booking_Cart_Handler::get_booking_data_from_order_item($item);
        
        // Invia codici con prenotazione
        $results = $this->send_booking_codes($order, $item, $codes, $booking_data, $has_prenotazione_function);
        
        // Log risultati
        $this->log_sync_results($order, $item, $results, true);
    }

    /**
     * Sincronizza item SENZA prenotazione (solo setVenduto)
     */
    private function sync_nonbooking_item($order, $item) {
        // Recupera codici
        $codes = $this->get_license_codes_for_item($item, true);
        
        if (empty($codes)) {
            return;
        }
        
        // Recupera dati cliente
        $customer = $this->get_customer_data($order);
        
        // Calcola prezzo per codice
        $price_per_code = $this->calculate_price_per_code($item, $item->get_quantity());
        
        // Invia codici
        $results = array('venduto_success' => 0, 'venduto_error' => 0, 'venduto_messages' => array());
        
        foreach ($codes as $index => $code) {
            $venduto_result = $this->send_venduto($code, $price_per_code, $customer['name'], $customer['email']);

            if ($venduto_result['status']) {
                $results['venduto_success']++;
            } else {
                $results['venduto_error']++;
                $results['venduto_messages'][] = $venduto_result['message'];
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
            return array(
                'venduto_success' => 0,
                'venduto_error' => 0,
                'prenotazione_success' => 0,
                'prenotazione_error' => 0
            );
        }
        

        // Calcola prezzo per codice
        $price_per_code = $this->calculate_price_per_code($item, count($codes));
        
        $results = array(
            'venduto_success' => 0,
            'venduto_error' => 0,
            'venduto_messages' => array(),
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
            
            
            // STEP 1: setVenduto
            $venduto_result = $this->send_venduto($code, $price_per_code, $customer['name'], $customer['email']);

            if ($venduto_result['status']) {
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
                $results['venduto_messages'][] = $venduto_result['message'];
            }
        }
        
        return $results;
    }

    /**
     * Invia setVenduto
     */
    private function send_venduto($code, $price, $customer_name, $customer_email) {
        try {
            // set_venduto ritorna array{status, message}: in caso di eccezione SOAP
            // status e' false e message contiene l'errore TermeGest.
            return skianet_termegest_set_venduto(
                $code,
                $price,
                $customer_name,
                $customer_email
            );

        } catch (Exception $e) {
            return array('status' => false, 'message' => $e->getMessage());
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

            $allInclusive = in_array(strtolower($categoria), array('p3', 'p4'), true);

            // Log parametri per debug
            
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
                $allInclusive,
                $categoria,
                '',
                $protection
            );
            
            if ($response['status']) {
                return true;
            } else {
                return false;
            }
            
        } catch (Exception $e) {
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
        // Primi errori TermeGest (deduplicati) da allegare alla nota, se ce ne sono.
        $error_detail = '';
        $messages = array_filter(array_unique($results['venduto_messages'] ?? array()));
        if (!empty($messages)) {
            $error_detail = ' - TermeGest: ' . implode(' | ', array_slice($messages, 0, 3));
        }

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
                        'ATTENZIONE: Errori sync per %s - Venduto: %d errori, Prenotazione: %d errori%s',
                        $item->get_name(),
                        $results['venduto_error'],
                        $results['prenotazione_error'],
                        $error_detail
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
                        'ATTENZIONE: %d/%d codici NON sincronizzati per %s%s',
                        $results['venduto_error'],
                        $total,
                        $item->get_name(),
                        $error_detail
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
            // ✅ Usa metodo condiviso (già scoped al singolo order item, non solo al prodotto)
            return Booking_Cart_Handler::get_item_license_codes($item);
        }
        
        $item_id = $item->get_id();
        $code_ids = wc_get_order_item_meta($item_id, '_license_code_ids');
        
        if (empty($code_ids) || !is_array($code_ids)) {
            return array();
        }
        
        $codes = array();
        foreach ($code_ids as $code_id) {
            $code_data = WC_LD_Model::get_codes_by_id($code_id);
            
            if (!empty($code_data[0]['license_code1'])) {
                $codes[] = $code_data[0]['license_code1'];
            }
        }

        return $codes;
    }

}