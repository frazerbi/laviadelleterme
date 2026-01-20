<?php
/**
 * Gestisce i dati della prenotazione nel carrello e checkout WooCommerce
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

class Booking_Cart_Handler {

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
     * Inizializza gli hooks
     */
    private function init_hooks() {
        // 1. Aggiungi dati personalizzati al carrello
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_booking_data_to_cart'), 10, 3);
        
        // 2. Carica dati prenotazione dalla sessione quando si carica il carrello
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 10, 2);
        
        // 3. Mostra dati prenotazione nel carrello
        add_filter('woocommerce_get_item_data', array($this, 'display_booking_data_in_cart'), 10, 2);
        
        // 4. Salva dati prenotazione nell'ordine
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_booking_data_to_order_items'), 10, 4);
        
        // 5. Mostra dati prenotazione nell'ordine (admin e email)
        add_filter('woocommerce_order_item_get_formatted_meta_data', array($this, 'format_order_item_meta'), 10, 2);
        
        // 6. Blocca modifica quantità per prodotti con prenotazione
        add_filter('woocommerce_cart_item_quantity', array($this, 'disable_quantity_change'), 10, 3);
    
    }

    /**
     * Aggiungi dati prenotazione al carrello
     */
    public function add_booking_data_to_cart($cart_item_data, $product_id, $variation_id) {
        // Avvia sessione
        if (!session_id()) {
            session_start();
        }
        
        // Recupera dati dalla sessione
        if (isset($_SESSION['termegest_booking'])) {
            $booking_data = $_SESSION['termegest_booking'];
            
            error_log('Aggiunta prenotazione al carrello: ' . print_r($booking_data, true));
            
            // Aggiungi tutti i dati della prenotazione
            $cart_item_data['booking_id'] = $booking_data['booking_id'];
            $cart_item_data['booking_location'] = $booking_data['location'];
            $cart_item_data['booking_location_name'] = $booking_data['location_name'];
            $cart_item_data['booking_date'] = $booking_data['booking_date'];
            $cart_item_data['booking_fascia_id'] = $booking_data['fascia_id'];
            $cart_item_data['booking_ticket_type'] = $booking_data['ticket_type'];
            $cart_item_data['booking_num_male'] = $booking_data['num_male'];
            $cart_item_data['booking_num_female'] = $booking_data['num_female'];
            $cart_item_data['booking_total_guests'] = $booking_data['total_guests'];
            $cart_item_data['booking_category'] = $booking_data['category'];
            
            // Rendi unico il cart item (così se aggiungi 2 prenotazioni diverse, sono 2 righe separate)
            $cart_item_data['unique_key'] = $booking_data['booking_id'];

            unset($_SESSION['termegest_booking']);
            error_log('Sessione prenotazione pulita dopo aggiunta al carrello');
        }
        
        return $cart_item_data;
    }

    /**
     * Carica dati dal carrello salvato in sessione
     */
    public function get_cart_item_from_session($cart_item, $values) {
        if (isset($values['booking_id'])) {
            $cart_item['booking_id'] = $values['booking_id'];
            $cart_item['booking_location'] = $values['booking_location'];
            $cart_item['booking_location_name'] = $values['booking_location_name'];
            $cart_item['booking_date'] = $values['booking_date'];
            $cart_item['booking_fascia_id'] = $values['booking_fascia_id'];
            $cart_item['booking_ticket_type'] = $values['booking_ticket_type'];
            $cart_item['booking_num_male'] = $values['booking_num_male'];
            $cart_item['booking_num_female'] = $values['booking_num_female'];
            $cart_item['booking_total_guests'] = $values['booking_total_guests'];
            $cart_item['booking_category'] = $values['booking_category'];
        }
        
        return $cart_item;
    }

    /**
     * Mostra dati prenotazione nel carrello
     */
    public function display_booking_data_in_cart($item_data, $cart_item) {

        if (isset($cart_item['booking_id'])) {
            // Formatta la data in italiano
            $date = DateTime::createFromFormat('Y-m-d', $cart_item['booking_date']);
            $formatted_date = $date ? $date->format('d/m/Y') : $cart_item['booking_date'];
            
            // ✅ Prenotazione + Location (una riga)
            $item_data[] = array(
                'key'   => 'Prenotazione',
                'value' => $cart_item['booking_location_name'] . ' - ' . $formatted_date
            );
            
            // ✅ Ospiti (compatto)
            $guests_text = '';
            if ($cart_item['booking_num_male'] > 0 && $cart_item['booking_num_female'] > 0) {
                $guests_text = sprintf('%d uomo, %d donna', $cart_item['booking_num_male'], $cart_item['booking_num_female']);
            } elseif ($cart_item['booking_num_male'] > 0) {
                $guests_text = sprintf('%d %s', $cart_item['booking_num_male'], $cart_item['booking_num_male'] === 1 ? 'uomo' : 'uomini');
            } elseif ($cart_item['booking_num_female'] > 0) {
                $guests_text = sprintf('%d %s', $cart_item['booking_num_female'], $cart_item['booking_num_female'] === 1 ? 'donna' : 'donne');
            }
            
            $item_data[] = array(
                'key'   => 'Ospiti',
                'value' => $guests_text
            );
        }
            
        return $item_data;
    }

    /**
     * Salva dati prenotazione nell'ordine
     */
    public function add_booking_data_to_order_items($item, $cart_item_key, $values, $order) {
        if (isset($values['booking_id'])) {
            // Salva come meta data dell'ordine item
            $item->add_meta_data('_booking_id', $values['booking_id'], true);
            $item->add_meta_data('Prenotazione', '#' . substr($values['booking_id'], -8), true);
            $item->add_meta_data('Location', $values['booking_location_name'], true);
            
            // Formatta data
            $date = DateTime::createFromFormat('Y-m-d', $values['booking_date']);
            $formatted_date = $date ? $date->format('d/m/Y') : $values['booking_date'];
            $item->add_meta_data('Data Prenotazione', $formatted_date, true);
            $item->add_meta_data('_booking_date', $values['booking_date'], true); // Raw per API
            
            $item->add_meta_data('Fascia ID', $values['booking_fascia_id'], true);
            $item->add_meta_data('_booking_fascia_id', $values['booking_fascia_id'], true); // ✅ Aggiungi raw

            $item->add_meta_data('Tipo Ingresso', $values['booking_ticket_type'] === '4h' ? '4 Ore' : 'Giornaliero', true);
            $item->add_meta_data('_booking_ticket_type', $values['booking_ticket_type'], true);
            
            if ($values['booking_num_male'] > 0) {
                $item->add_meta_data('Ingressi Uomo', $values['booking_num_male'], true);
            }
            
            if ($values['booking_num_female'] > 0) {
                $item->add_meta_data('Ingressi Donna', $values['booking_num_female'], true);
            }
            
            // ✅ Salva categoria TermeGest (P1/P2/P3/P4/PM)
            $item->add_meta_data('Categoria', $values['booking_category'], true); // Visibile
            $item->add_meta_data('_booking_category', $values['booking_category'], true); // Raw per API
            
            error_log('Dati prenotazione salvati nell\'ordine: ' . $values['booking_id']);
        }
    }

    /**
     * Formatta meta data per visualizzazione
     */
    public function format_order_item_meta($formatted_meta, $item) {
        // Nascondi campi interni (quelli che iniziano con _)
        foreach ($formatted_meta as $key => $meta) {
            if (strpos($meta->key, '_') === 0) {
                unset($formatted_meta[$key]);
            }
        }
        
        return $formatted_meta;
    }

    /**
     * Recupera dati prenotazione da order item
     */
    public static function get_booking_data_from_order_item($item) {
        return array(
            'booking_id' => $item->get_meta('_booking_id'),
            'location_name' => $item->get_meta('Location'),
            'booking_date' => $item->get_meta('_booking_date'),
            'fascia_id' => $item->get_meta('_booking_fascia_id'), // ✅ Usa il campo raw
            'ticket_type' => $item->get_meta('_booking_ticket_type'),
            'num_male' => (int)$item->get_meta('Ingressi Uomo'), // ✅ Cast a int
            'num_female' => (int)$item->get_meta('Ingressi Donna'), // ✅ Cast a int
            'total_guests' => (int)$item->get_meta('Totale Ospiti'),
            'categorie' => $item->get_meta('_booking_category')
        );
    }

    /**
     * Disabilita modifica quantità per prodotti con prenotazione
     */
    public function disable_quantity_change($product_quantity, $cart_item_key, $cart_item) {
        // Se il prodotto ha dati di prenotazione, mostra quantità come testo fisso
        if (isset($cart_item['booking_id'])) {
            $quantity = $cart_item['quantity'];
            
            // Mostra quantità come testo non modificabile con classe specifica
            return sprintf(
                '<div class="quantity quantity-readonly woocommerce-quantity" style="text-align: center; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px;">
                    <strong >%s</strong>
                    <input type="hidden" name="cart[%s][qty]" value="%s" />
                </div>',
                $quantity,
                $cart_item_key,
                $quantity
            );
        }
        
        return $product_quantity;
    }
    
    /** 
     * Recupera codici licenza per un item (metodo statico per uso condiviso)
     */
    public static function get_item_license_codes($order_id, $product_id, $variation_id) {
        global $wpdb;
        
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