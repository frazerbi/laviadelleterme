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
     * Product ID e variazioni per categoria TermeGest — PRODUZIONE.
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
        ),
        'v3' => array(
            'product_id'   => 109134,
            'variation_id' => null
        )
    );

    /**
     * Stessa mappa per STAGING: fra i due ambienti cambiano solo gli ID.
     *
     * Si sceglie con WP_ENVIRONMENT_TYPE, definita in plugin-custom-skianet.php.
     * Si legge la costante e non wp_get_environment_type() perche' quella
     * funzione memorizza il valore alla prima chiamata, che puo' capitare prima
     * del caricamento dei plugin.
     */
    private static $product_config_staging = array(
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
        ),
        'v3' => array(
            'product_id'   => 93933,
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
        // Pagina prodotto con i dati di prenotazione: tutto viene stampato gia' nell'HTML,
        // senza JS. Ogni callback esce subito se get_pdp_booking() restituisce null.
        add_filter('post_class', array($this, 'add_pdp_class'), 10, 3);
        add_filter('elementor/widget/render_content', array($this, 'render_pdp_title'), 10, 2);
        add_filter('woocommerce_dropdown_variation_attribute_options_args', array($this, 'preselect_variation'));
        add_filter('woocommerce_quantity_input_args', array($this, 'preset_quantity'), 10, 2);
        add_action('woocommerce_before_add_to_cart_button', array($this, 'render_pdp_summary'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_location_image'));
    }

    /**
     * Dati di prenotazione della pagina prodotto corrente, null se non ce ne sono.
     * Calcolati una volta per richiesta.
     */
    private $pdp_booking = false;

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

        $config = (\defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'staging')
            ? self::$product_config_staging
            : self::$product_config;

        // Una voce senza product_id e' una categoria non mappata su questo
        // ambiente: meglio fermarsi qui che mandare wc_get_product() su null.
        if (empty($config[$category]['product_id'])) {
            return false;
        }

        return $config[$category];
    }

    /**
     * Genera URL di redirect al prodotto WooCommerce
     */
    public function redirect_to_product($booking_id, $booking_data) {

        $category = is_array($booking_data['categorie']) ? $booking_data['categorie'][0] : $booking_data['categorie'];

        $mapping = $this->get_product_mapping_from_category($category);

        if (!$mapping) {
            return false;
        }

        $product_id   = $mapping['product_id'];
        $variation_id = $mapping['variation_id'];

        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }

        $product_url = get_permalink($product_id);

        // Salva product_id e variation_id nella sessione per validazione
        if (!session_id()) {
            session_start();
        }
        if (isset($_SESSION['termegest_booking'])) {
            $_SESSION['termegest_booking']['product_id']   = $product_id;
            $_SESSION['termegest_booking']['variation_id'] = $variation_id;
        }

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


        return $redirect_url;
    }


    /**
     * Legge e valida i parametri che redirect_to_product() mette nell'URL.
     *
     * La variazione (e quindi il prezzo) viene dalla stessa mappa categoria → ID usata
     * per la sessione: Booking_Cart_Handler::add_booking_data_to_cart() scarta la
     * prenotazione se nel carrello arriva un'altra variazione, quindi e' l'unica fonte
     * giusta. Se la categoria punta a un prodotto diverso da quello aperto, o un
     * parametro non torna, si restituisce null e la pagina resta quella normale.
     */
    public function get_pdp_booking() {
        if (false !== $this->pdp_booking) {
            return $this->pdp_booking;
        }
        $this->pdp_booking = null;

        if (!function_exists('is_product') || !is_product() || empty($_GET['category'])) {
            return null;
        }

        $get       = wp_unslash($_GET);
        $location  = sanitize_key($get['location'] ?? '');
        $ticket    = sanitize_key($get['ticket_type'] ?? '');
        $date      = (string) ($get['date'] ?? '');
        $time      = trim((string) ($get['time_slot_label'] ?? ''));
        $guests    = absint($get['total_guests'] ?? 0);
        $locations = Booking_Handler::get_available_locations();
        $tickets   = Booking_Handler::get_ticket_types();

        if (!isset($locations[$location], $tickets[$ticket]) || $guests < 1 || $guests > 99) {
            return null;
        }

        $date_obj = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
            return null;
        }

        // Le etichette fascia arrivano da TermeGest e possono avere accenti; tutto il
        // resto (in particolare < > " ' &) resta fuori. In output passa comunque da esc_html().
        if (!preg_match('/^[\p{L}\p{N}\s:.\-]{1,40}$/u', $time)) {
            $time = '';
        }

        $mapping = $this->get_product_mapping_from_category(sanitize_key($get['category']));
        if (!$mapping || (int) $mapping['product_id'] !== get_queried_object_id()) {
            return null;
        }

        $sellable = wc_get_product($mapping['variation_id'] ?: $mapping['product_id']);
        if (!$sellable) {
            return null;
        }

        $unit_price = (float) wc_get_price_to_display($sellable);

        $this->pdp_booking = array(
            'product_id'  => (int) $mapping['product_id'],
            'attributes'  => $sellable->is_type('variation') ? $sellable->get_variation_attributes() : array(),
            'location_id' => $location,
            'location'    => $locations[$location],
            'date'        => $date_obj->format('d/m/Y'),
            'time'        => $time,
            'ticket'      => $tickets[$ticket],
            'guests'      => $guests,
            'unit_price'  => $unit_price,
            'total_price' => $unit_price * $guests,
        );

        return $this->pdp_booking;
    }

    /**
     * Formato breve dei prezzi nel riepilogo: "73€", "24,50€".
     */
    private static function format_price($value) {
        $decimals = (floor($value) == $value) ? 0 : 2;
        return number_format($value, $decimals, wc_get_price_decimal_separator(), wc_get_price_thousand_separator()) . '€';
    }

    /**
     * Classe sul contenitore .product (il documento Elementor stampa get_post_class()):
     * pdp.css la usa per nascondere select, quantita' e prezzo standard.
     */
    public function add_pdp_class($classes, $class, $post_id) {
        $booking = $this->get_pdp_booking();
        if ($booking && (int) $post_id === $booking['product_id']) {
            $classes[] = 'booking-prefilled';
        }
        return $classes;
    }

    /**
     * Riscrive il widget Elementor "Titolo prodotto": frase di prenotazione al posto del
     * nome, e sotto prezzo totale + nome originale del prodotto.
     */
    public function render_pdp_title($content, $widget) {
        if ('woocommerce-product-title' !== $widget->get_name()) {
            return $content;
        }

        $booking = $this->get_pdp_booking();
        if (!$booking) {
            return $content;
        }

        $time  = '' !== $booking['time'] ? ' alle ore <b>' . esc_html($booking['time']) . '</b>' : '';
        $title = sprintf(
            'Prenota a <b>%s</b> per il giorno <b>%s</b>%s per %d %s (%s).',
            esc_html($booking['location']),
            esc_html($booking['date']),
            $time,
            $booking['guests'],
            1 === $booking['guests'] ? 'ospite' : 'ospiti',
            esc_html($booking['ticket'])
        );

        $price = 1 === $booking['guests']
            ? esc_html(self::format_price($booking['total_price']))
            : '<span>' . esc_html(self::format_price($booking['total_price'])) . '</span>'
              . '<span class="note">(' . esc_html(self::format_price($booking['unit_price'])) . ' x ' . $booking['guests'] . ')</span>';

        return preg_replace_callback(
            '#(<(h[1-6]|div|p|span)\b[^>]*\bproduct_title\b[^>]*>)(.*?)(</\2>)#s',
            function ($m) use ($title, $price) {
                return $m[1] . $title . $m[4]
                    . '<div class="secondary-info">'
                    . '<span class="price">' . $price . '</span>'
                    . '<span class="note">' . esc_html(wp_strip_all_tags(html_entity_decode($m[3], ENT_QUOTES, 'UTF-8'))) . '</span>'
                    . '</div>';
            },
            $content,
            1
        );
    }

    /**
     * Variazione gia' selezionata nella select: add-to-cart-variation.js la mostra
     * da solo all'avvio, come fa con gli attributi di default.
     */
    public function preselect_variation($args) {
        $booking = $this->get_pdp_booking();
        if (!$booking || empty($args['product']) || $args['product']->get_id() !== $booking['product_id']) {
            return $args;
        }

        $key = 'attribute_' . sanitize_title($args['attribute']);
        if (isset($booking['attributes'][$key])) {
            $args['selected'] = $booking['attributes'][$key];
        }
        return $args;
    }

    /**
     * Quantita' = numero di ospiti. Se c'e' una quantita' in POST (form ristampato dopo
     * un errore) vince quella, come fa WooCommerce.
     */
    public function preset_quantity($args, $product) {
        $booking = $this->get_pdp_booking();
        if ($booking && $product && $product->get_id() === $booking['product_id'] && !isset($_POST['quantity'])) {
            $args['input_value'] = $booking['guests'];
        }
        return $args;
    }

    /**
     * Riepilogo della prenotazione, dentro il form subito prima di quantita' e pulsante
     * (per i prodotti variabili cade dopo la descrizione della variazione).
     */
    public function render_pdp_summary() {
        global $product;

        $booking = $this->get_pdp_booking();
        if (!$booking || !$product || $product->get_id() !== $booking['product_id']) {
            return;
        }

        $price = 1 === $booking['guests']
            ? self::format_price($booking['unit_price'])
            : self::format_price($booking['unit_price']) . ' x ' . $booking['guests'] . ' = ' . self::format_price($booking['total_price']);

        $rows = array(
            'Struttura' => $booking['location'],
            'Data'      => $booking['date'],
            'Orario'    => $booking['time'],
            'Tipologia' => $booking['ticket'],
            'Ospiti'    => $booking['guests'],
            'Prezzo'    => $price,
        );

        echo '<div class="custom-data-ui"><ul>';
        foreach ($rows as $label => $value) {
            if ('' === $value) {
                continue;
            }
            echo '<li>' . esc_html($label . ': ' . $value) . '</li>';
        }
        echo '</ul></div>';
    }

    /**
     * Foto della struttura scelta nella colonna immagine. CSS e non JS, cosi' il browser
     * scarica subito quella giusta invece della foto di default. Il selettore per id
     * (1,0,0) batte le regole di Elementor/DCE sullo sfondo, che sono solo classi.
     */
    public function enqueue_location_image() {
        $booking = $this->get_pdp_booking();
        if (!$booking) {
            return;
        }

        $url = plugin_dir_url(PLUGIN_SKIANET_FILE) . 'assets/img/strutture/fig_' . $booking['location_id'] . '.jpg';

        wp_register_style('booking-pdp-location', false, array(), PLUGIN_SKIANET_VERSION);
        wp_enqueue_style('booking-pdp-location');
        wp_add_inline_style('booking-pdp-location', '#colonna-img-prodotto{background-image:url("' . esc_url($url) . '")}');
    }

}
