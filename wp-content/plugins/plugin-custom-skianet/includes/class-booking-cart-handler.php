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

            // Verifica che il prodotto aggiunto corrisponda a quello della prenotazione in sessione
            if (isset($booking_data['product_id'])) {
                $session_product_id   = (int) $booking_data['product_id'];
                $session_variation_id = isset($booking_data['variation_id']) ? (int) $booking_data['variation_id'] : 0;
                $cart_product_id      = (int) $product_id;
                $cart_variation_id    = (int) $variation_id;

                $session_id = $session_variation_id ?: $session_product_id;
                $cart_id    = $cart_variation_id    ?: $cart_product_id;

                if ($session_id !== $cart_id) {
                    unset($_SESSION['termegest_booking']);
                    return $cart_item_data;
                }
            }

            
            // Aggiungi tutti i dati della prenotazione
            $cart_item_data['booking_id'] = $booking_data['booking_id'];
            $cart_item_data['booking_location'] = $booking_data['location'];
            $cart_item_data['booking_location_name'] = $booking_data['location_name'];
            $cart_item_data['booking_date'] = $booking_data['booking_date'];
            $cart_item_data['booking_fascia_id'] = $booking_data['fascia_id'];
            $cart_item_data['booking_time_slot_label'] = $booking_data['time_slot_label'] ?? '';
            $cart_item_data['booking_ticket_type'] = $booking_data['ticket_type'];
            $cart_item_data['booking_num_male'] = $booking_data['num_male'];
            $cart_item_data['booking_num_female'] = $booking_data['num_female'];
            $cart_item_data['booking_total_guests'] = $booking_data['total_guests'];
            $cart_item_data['booking_category'] = $booking_data['category'];
            
            // Rendi unico il cart item (così se aggiungi 2 prenotazioni diverse, sono 2 righe separate)
            $cart_item_data['unique_key'] = $booking_data['booking_id'];

            unset($_SESSION['termegest_booking']);
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
            $cart_item['booking_time_slot_label'] = $values['booking_time_slot_label'] ?? '';
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
            $item->add_meta_data('_booking_fascia_id', $values['booking_fascia_id'], true);
            if (!empty($values['booking_time_slot_label'])) {
                $item->add_meta_data('Orario', $values['booking_time_slot_label'], true);
                $item->add_meta_data('_booking_time_slot_label', $values['booking_time_slot_label'], true);
            }

            $ticket_labels = array('4h' => '4 Ore', 'giornaliero' => 'Giornaliero', 'serale' => 'Serale');
            $item->add_meta_data('Tipo Ingresso', $ticket_labels[$values['booking_ticket_type']] ?? $values['booking_ticket_type'], true);
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
            
        }
    }

    /**
     * Formatta meta data per visualizzazione
     */
    public function format_order_item_meta($formatted_meta, $item) {
        $is_booking_item = $item->get_meta('_booking_id') !== '';

        $visible_keys = ['Location', 'Data Prenotazione', 'Orario'];

        foreach ($formatted_meta as $key => $meta) {
            // Nascondi sempre i campi interni (iniziano con _)
            if (strpos($meta->key, '_') === 0) {
                unset($formatted_meta[$key]);
                continue;
            }
            // Per i prodotti prenotazione mostra solo i campi selezionati
            if ($is_booking_item && ! in_array($meta->key, $visible_keys, true)) {
                unset($formatted_meta[$key]);
            }
        }

        return $formatted_meta;
    }

    /**
     * Recupera dati prenotazione da order item
     */
    public static function get_booking_data_from_order_item($item) {
        $num_male   = (int)$item->get_meta('Ingressi Uomo');
        $num_female = (int)$item->get_meta('Ingressi Donna');

        return array(
            'booking_id' => $item->get_meta('_booking_id'),
            'location_name' => $item->get_meta('Location'),
            'booking_date' => $item->get_meta('_booking_date'),
            'fascia_id' => $item->get_meta('_booking_fascia_id'),
            'time_slot_label' => $item->get_meta('_booking_time_slot_label'),
            'ticket_type' => $item->get_meta('_booking_ticket_type'),
            'num_male' => $num_male,
            'num_female' => $num_female,
            // Nessun meta 'Totale Ospiti' viene mai scritto sull'item: si ricava dai due sessi.
            'total_guests' => $num_male + $num_female,
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
     * Cache per richiesta della lista completa dei codici per order_id:product_id.
     *
     * La stessa coppia viene interrogata piu' volte nella stessa richiesta: dentro
     * woocommerce_payment_complete la leggono email di conferma, sync TermeGest ed
     * email coupon. E' popolata sempre dopo Booking_Code_Assignment (priorita' 10),
     * quindi non puo' restituire una lista vuota antecedente all'assegnazione.
     *
     * @var array
     */
    private static $license_codes_cache = array();

    /**
     * Recupera i codici licenza spettanti a uno specifico order item (metodo statico per uso condiviso).
     *
     * wc_ld_license_codes associa i codici a order_id+product_id, senza distinguere
     * l'order item: se un ordine contiene più item dello stesso prodotto (es. stessa
     * tipologia di ingresso ma date/orari diversi), la query restituirebbe a ciascun
     * item TUTTI i codici del prodotto nell'ordine. Per evitare la duplicazione,
     * dividiamo la lista completa in base alla quantità cumulativa degli item con lo
     * stesso prodotto che precedono quello richiesto (stesso ordine di iterazione di
     * $order->get_items() usato da WC License Delivery per assegnare i codici).
     */
    public static function get_item_license_codes($item) {
        global $wpdb;

        $order_id     = $item->get_order_id();
        $product_id   = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        $check_id     = $variation_id > 0 ? $variation_id : $product_id;

        $cache_key = $order_id . ':' . $check_id;

        if (isset(self::$license_codes_cache[$cache_key])) {
            $all_codes = self::$license_codes_cache[$cache_key];

            return self::slice_codes_for_item($item, $check_id, $all_codes);
        }

        $query = $wpdb->prepare(
            "SELECT license_code1 FROM {$wpdb->prefix}wc_ld_license_codes
            WHERE order_id = %d AND product_id = %d",
            $order_id,
            $check_id
        );

        $results = $wpdb->get_results($query);

        $all_codes = array();
        foreach ($results as $row) {
            if (!empty($row->license_code1)) {
                $code = $row->license_code1;

                // ✅ Pulisci codice da BOM e caratteri invisibili
                $code = str_replace("\xEF\xBB\xBF", '', $code); // BOM UTF-8
                $code = preg_replace('/[\x00-\x1F\x7F\xA0\xAD]/u', '', $code); // Caratteri invisibili
                $code = trim($code);

                if (!empty($code)) {
                    $all_codes[] = $code;
                }
            }
        }

        self::$license_codes_cache[$cache_key] = $all_codes;

        return self::slice_codes_for_item($item, $check_id, $all_codes);
    }

    /**
     * Ritaglia dalla lista completa dei codici di order_id+product_id la porzione
     * spettante a questo item (vedi get_item_license_codes()).
     */
    private static function slice_codes_for_item($item, $check_id, array $all_codes) {
        $order = $item->get_order();
        if (!$order) {
            return $all_codes;
        }

        $offset = 0;
        foreach ($order->get_items() as $sibling) {
            if ($sibling->get_id() === $item->get_id()) {
                break;
            }

            $sibling_check_id = $sibling->get_variation_id() > 0 ? $sibling->get_variation_id() : $sibling->get_product_id();
            if ($sibling_check_id === $check_id) {
                $offset += (int) $sibling->get_quantity();
            }
        }

        return array_slice($all_codes, $offset, (int) $item->get_quantity());
    }
}