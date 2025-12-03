<?php
/**
 * Gestisce la logica del form di prenotazione
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

class Booking_Handler {

    /**
     * Location disponibili
     */
    private static $locations = array(
        'terme-saint-vincent' => 'Terme di Saint-Vincent',
        'terme-genova' => 'Terme di Genova',
        'monterosa-spa' => 'Monterosa Spa'
    );

    /**
     * Tipi di ingresso disponibili
     */
    private static $ticket_types = array(
        '4h' => '4 Ore',
        'giornaliero' => 'Giornaliero'
    );

    /**
     * Fasce orarie disponibili
     */
    private static $time_slots = array(
        '09:00' => '09:00 - Mattina',
        '10:00' => '10:00 - Mattina',
        '11:00' => '11:00 - Tarda Mattina',
        '12:00' => '12:00 - Mezzogiorno',
        '13:00' => '13:00 - Primo Pomeriggio',
        '14:00' => '14:00 - Pomeriggio',
        '15:00' => '15:00 - Pomeriggio',
        '16:00' => '16:00 - Tardo Pomeriggio',
        '17:00' => '17:00 - Sera',
        '18:00' => '18:00 - Sera'
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
     * Ottieni le location disponibili
     */
    public static function get_available_locations() {
        return self::$locations;
    }

    /**
     * Ottieni i tipi di ingresso disponibili
     */
    public static function get_ticket_types() {
        return self::$ticket_types;
    }

    /**
     * Ottieni le fasce orarie disponibili
     */
    public static function get_time_slots() {
        return self::$time_slots;
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
        // Shortcode per il form
        add_shortcode('booking_form', array($this, 'render_booking_form'));
        
        // Hook per gestire il submit del form
        add_action('wp_ajax_submit_booking_ajax', array($this, 'handle_ajax_submission'));
        add_action('wp_ajax_nopriv_submit_booking_ajax', array($this, 'handle_ajax_submission'));
        
        // Hook AJAX per chiamata API esterna
        add_action('wp_ajax_check_availability_api', array($this, 'check_availability_api'));
        add_action('wp_ajax_nopriv_check_availability_api', array($this, 'check_availability_api'));

        // Enqueue scripts e styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Carica CSS e JS
     */
    public function enqueue_assets() {
        wp_enqueue_style(
            'booking-form-style',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/booking-form.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'booking-form-script',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/booking-form.js',
            array('jquery'),
            '1.0.0',
            true
        );

        // Passa variabili a JavaScript
        wp_localize_script('booking-form-script', 'bookingFormData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('booking_form_nonce'),
            'success_message' => __('Prenotazione effettuata con successo!', 'text-domain'),
            'error_message' => __('Si è verificato un errore. Riprova.', 'text-domain')
        ));
    }

    /**
     * Renderizza il form tramite shortcode
     */
    public function render_booking_form() {
        ob_start();
        
        include plugin_dir_path(dirname(__FILE__)) . 'templates/booking-form.php';
        
        return ob_get_clean();
    }

    /**
     * Gestisce il submit AJAX del form
     */
    public function handle_ajax_submission() {
        // Verifica nonce
        if (!isset($_POST['booking_form_nonce']) || 
            !wp_verify_nonce($_POST['booking_form_nonce'], 'booking_form_action')) {
            wp_send_json_error(array(
                'message' => 'Errore di sicurezza. Riprova.'
            ));
        }

        // Sanitizza i dati
        $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';
        $booking_date = isset($_POST['booking_date']) ? sanitize_text_field($_POST['booking_date']) : '';
        $ticket_type = isset($_POST['ticket_type']) ? sanitize_text_field($_POST['ticket_type']) : '';
        $time_slot = isset($_POST['time_slot']) ? sanitize_text_field($_POST['time_slot']) : '';
        $num_male = isset($_POST['num_male']) ? intval($_POST['num_male']) : 0;
        $num_female = isset($_POST['num_female']) ? intval($_POST['num_female']) : 0;

        // Validazione
        $validation = $this->validate_booking_data($location, $booking_date, $ticket_type, $time_slot, $num_male, $num_female);
        
        if (is_wp_error($validation)) {
            wp_send_json_error(array(
                'message' => $validation->get_error_message()
            ));
        }

        // TODO: Salva la prenotazione
        $booking_success = true;

        if ($booking_success) {
            wp_send_json_success(array(
                'message' => 'Prenotazione effettuata con successo!'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Errore durante il salvataggio. Riprova.'
            ));
        }
    }

    /**
     * Valida i dati del form
     */
    private function validate_booking_data($location, $booking_date, $ticket_type, $time_slot, $num_male, $num_female) {
        $errors = new WP_Error();

        // Valida location
        $valid_locations = array_keys(self::$locations);
        if (empty($location) || !in_array($location, $valid_locations)) {
            $errors->add('invalid_location', 'Seleziona una location valida.');
        }

        // Valida data
        if (empty($booking_date)) {
            $errors->add('empty_date', 'Inserisci una data.');
        } else {
            $date = DateTime::createFromFormat('Y-m-d', $booking_date);
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            
            if (!$date || $date < $today) {
                $errors->add('invalid_date', 'Inserisci una data valida (non nel passato).');
            }
        }

        // Valida tipo ingresso
        $valid_types = array_keys(self::$ticket_types);
        if (empty($ticket_type) || !in_array($ticket_type, $valid_types)) {
            $errors->add('invalid_type', 'Seleziona un tipo di ingresso valido.');
        }

        // Valida fascia oraria
        $valid_slots = array_keys(self::$time_slots);
        if (empty($time_slot) || !in_array($time_slot, $valid_slots)) {
            $errors->add('invalid_time_slot', 'Seleziona una fascia oraria valida.');
        }

        // Valida numero ingressi
        if ($num_male < 0 || $num_male > 10) {
            $errors->add('invalid_num_male', 'Numero ingressi uomo non valido (0-20).');
        }

        if ($num_female < 0 || $num_female > 10) {
            $errors->add('invalid_num_female', 'Numero ingressi donna non valido (0-20).');
        }

        $total_guests = $num_male + $num_female;
        if ($total_guests === 0) {
            $errors->add('no_guests', 'Devi selezionare almeno un ingresso.');
        }

        if ($total_guests > 20) {
            $errors->add('too_many_guests', 'Numero massimo di ingressi: 20.');
        }

        // Controlla disponibilità
        if (!$this->check_availability($location, $booking_date, $time_slot)) {
            $errors->add('not_available', 'La fascia oraria non è disponibile per questa data.');
        }

        if ($errors->has_errors()) {
            return $errors;
        }

        return true;
    }

    /**
     * Effettua chiamata API per verificare disponibilità
     */
    public function check_availability_api() {
        // Verifica nonce
        if (!isset($_POST['nonce']) || 
            !wp_verify_nonce($_POST['nonce'], 'booking_form_nonce')) {
            wp_send_json_error(array(
                'message' => 'Errore di sicurezza.'
            ));
        }

        // Sanitizza i dati
        $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';
        $booking_date = isset($_POST['booking_date']) ? sanitize_text_field($_POST['booking_date']) : '';

        // Valida i dati base
        if (empty($location) || empty($booking_date)) {
            wp_send_json_error(array(
                'message' => 'Location e data sono obbligatori.'
            ));
        }

        // Valida location
        if (!array_key_exists($location, self::$locations)) {
            wp_send_json_error(array(
                'message' => 'Location non valida.'
            ));
        }

        // Converti la data in giorno, mese, anno
        $date = DateTime::createFromFormat('Y-m-d', $booking_date);
        if (!$date) {
            wp_send_json_error(array(
                'message' => 'Data non valida.'
            ));
        }

        $day = (int) $date->format('d');
        $month = (int) $date->format('m');
        $year = (int) $date->format('Y');

        if (empty($location)) {
            wp_send_json_error(array(
                'message' => 'Codice location non trovato.'
            ));
        }
    
        error_log("Data convertita - Day: {$day}, Month: {$month}, Year: {$year}");
        error_log("Location code: {$location}");

        // Chiama l'API TermeGest
        $disponibilita = skianet_termegest_get_disponibilita_by_day($day, $month, $year, $location);

        error_log('Disponibilità ricevute: ' . print_r($disponibilita, true));

        // Ritorna i dati
        wp_send_json_success(array(
            'message' => 'Disponibilità verificata con successo.',
            'disponibilita_day' => $disponibilita
        ));
    }


    /**
     * Verifica disponibilità
     */
    private function check_availability($location, $booking_date) {
        // Qui puoi implementare la logica per verificare la disponibilità
        // Ad esempio controllando il database per prenotazioni esistenti
        
        // Per ora ritorna sempre true
        return true;
    }
    
}