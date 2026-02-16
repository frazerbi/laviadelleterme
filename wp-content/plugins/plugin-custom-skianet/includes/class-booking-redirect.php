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
     * Product ID, variazioni e categoria TermeGest in base a giorno settimana + ticket_type
     */
    private static $product_config = array(
        'p1' => array(
            'product_id'   => 14,
            'variation_id' => 225
        ),
        'p2' => array(
            'product_id'   => 228,
            'variation_id' => 229
        ),
        'p3' => array(
            'product_id'   => 14,
            'variation_id' => 224
        ),
        'p4' => array(
            'product_id'   => 228,
            'variation_id' => 230
        ),
        'pm' => array(
            'product_id'   => 27370,
            'variation_id' => null
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
     * Verifica se una data è nel periodo natalizio (25 dic - 6 gen)
     */
    public static function is_christmas_period($date_string) {
        $date = DateTime::createFromFormat('Y-m-d', $date_string);
        if (!$date) {
            return false;
        }
        
        $month = (int) $date->format('m');
        $day = (int) $date->format('d');
        
        // 25-31 Dicembre
        if ($month === 12 && $day >= 25) {
            return true;
        }
        
        // 1-6 Gennaio
        if ($month === 1 && $day <= 6) {
            return true;
        }
        
        return false;
    }

    /**
     * Ottieni mapping prodotto da categoria TermeGest
     */
    private function get_product_mapping_from_category($category) {

        $category = strtolower($category);

        if (!isset(self::$product_config[$category])) {
            error_log("Categoria {$category} non trovata in product_config");
            return false;
        }

        return self::$product_config[$category];
    }

    /**
     * Genera URL di redirect al prodotto WooCommerce
     */
    public function redirect_to_product($booking_id, $booking_data) {

        $category = is_array($booking_data['categorie']) ? $booking_data['categorie'][0] : $booking_data['categorie'];

        $mapping = $this->get_product_mapping_from_category($category);

        if (!$mapping) {
            error_log("ERRORE: impossibile mappare categoria {$category}");
            return false;
        }

        $product_id   = $mapping['product_id'];
        $variation_id = $mapping['variation_id'];

        $product = wc_get_product($product_id);
        if (!$product) {
            error_log("ERRORE: Prodotto ID {$product_id} non trovato");
            return false;
        }

        $product_url = get_permalink($product_id);

        // Parametri URL
        $params = array(
            'location'        => $booking_data['location'],
            'date'            => $booking_data['booking_date'],
            'time_slot_label' => $booking_data['time_slot_label'] ?? '',
            'num_male'        => $booking_data['num_male'],
            'num_female'      => $booking_data['num_female'],
            'total_guests'    => $booking_data['total_guests'],
            'ticket_type'     => $booking_data['ticket_type'],
            'category'        => $category
        );

        $redirect_url = add_query_arg($params, $product_url);

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
            plugin_dir_url(PLUGIN_SKIANET_FILE) . 'assets/js/woocommerce-variation-preselect.js',
            array('jquery'),
            '1.0.0',
            true
        );

        $url_params = array();
        if (isset($_GET['variation_id'])) {
            $url_params['variation_id'] = sanitize_text_field($_GET['variation_id']);
        }
        if (isset($_GET['ticket_type'])) {
            $url_params['ticket_type'] = sanitize_text_field($_GET['ticket_type']);
        }
        if (isset($_GET['total_guests'])) {
            $url_params['total_guests'] = intval($_GET['total_guests']);
        }
        
        wp_localize_script('woocommerce-variation-preselect', 'bookingParams', $url_params);
    }

}
