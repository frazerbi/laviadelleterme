<?php
/**
 * Gestisce il redirect ai prodotti WooCommerce dopo la prenotazione
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

class Booking_Redirect {

    /**
     * Product ID e variazioni in base a giorno settimana + ticket_type
     */
    private static $product_config = array(
        'feriale' => array(
            'product_id' => 14,
            'variations' => array(
                '4h' => 225,           // Variazione ID 1
                'giornaliero' => 224   // Variazione ID 2
            )
        ),
        'weekend' => array(
            'product_id' => 228,
            'variations' => array(
                '4h' => 229,           // Variazione ID 1
                'giornaliero' => 230  // Variazione ID 2
            )
        )
    );

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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_variation_script'));
    }

    /**
     * Verifica se una data è weekend (sabato o domenica)
     */
    private function is_weekend($date_string) {
        $date = DateTime::createFromFormat('Y-m-d', $date_string);
        if (!$date) {
            return false;
        }
        
        $day_of_week = (int) $date->format('N'); // 1 (lunedì) - 7 (domenica)
        
        return ($day_of_week === 6 || $day_of_week === 7); // Sabato o Domenica
    }

    /**
     * Ottieni Product ID e Variation ID
     */
    private function get_product_config($booking_date, $ticket_type) {
        // Determina se è weekend o feriale
        $day_type = $this->is_weekend($booking_date) ? 'weekend' : 'feriale';
        
        error_log("Data: {$booking_date} - Tipo giorno: {$day_type}");
        
        // Ottieni configurazione
        $config = self::$product_config[$day_type];
        
        if (!isset($config['variations'][$ticket_type])) {
            error_log("ERRORE: Nessuna variazione trovata per {$ticket_type}");
            return null;
        }
        
        return array(
            'product_id' => $config['product_id'],
            'variation_id' => $config['variations'][$ticket_type],
            'day_type' => $day_type
        );
    }

    /**
     * Redirect al prodotto WooCommerce
     */
    public function redirect_to_product($booking_id, $booking_data) {
        $config = $this->get_product_config(
            $booking_data['booking_date'],
            $booking_data['ticket_type']
        );

        if (!$config) {
            error_log("ERRORE: Impossibile ottenere configurazione prodotto");
            return false;
        }

        $product_id = $config['product_id'];
        $variation_id = $config['variation_id'];

        // Verifica che il prodotto esista
        $product = wc_get_product($product_id);
        if (!$product) {
            error_log("ERRORE: Prodotto ID {$product_id} non trovato in WooCommerce");
            return false;
        }

        error_log("Prodotto: {$product_id}, Variazione: {$variation_id}, Giorno: {$config['day_type']}");

        // Costruisci URL prodotto con parametri
        $product_url = get_permalink($product_id);
        
        // Aggiungi parametri query string
        $redirect_url = add_query_arg(array(
            'booking_id' => $booking_id,
            'variation_id' => $variation_id,
            'location' => $booking_data['location'],
            'date' => $booking_data['booking_date'],
            'fascia_id' => $booking_data['fascia_id'],
            'num_male' => $booking_data['num_male'],
            'num_female' => $booking_data['num_female'],
            'total_guests' => $booking_data['total_guests'],
            'ticket_type' => $booking_data['ticket_type']
        ), $product_url);

        error_log("Redirect URL: {$redirect_url}");

        return $redirect_url;
    }

    /**
     * Carica script per pre-selezione variazione
     */
    public function enqueue_variation_script() {
        // Solo su pagine prodotto
        if (!is_product()) {
            return;
        }
        
        wp_enqueue_script(
            'woocommerce-variation-preselect',
            PLUGIN_SKIANET_FILE . 'assets/js/woocommerce-variation-preselect.js',
            array('jquery'),
            '1.0.0',
            true
        );
    }

}
