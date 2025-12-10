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
    private static $locations_to_be_encrypted = array(
        'terme-saint-vincent' => 'Saint Vincent',
        'terme-genova' => 'Genova',
        'monterosa-spa' => 'Monterosa'
    );


    private static $locations_labels = array(
        'terme-saint-vincent' => 'Terme di Saint-Vincent',
        'terme-genova' => 'Terme di Genova',
        'monterosa-spa' => 'Monterosa SPA'
    );

    /**
     * Tipi di ingresso disponibili
     */
    private static $ticket_types = array(
        '4h' => '4 Ore',
        'giornaliero' => 'Giornaliero'
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
     * Ottieni le location per il form (slug => label)
     */
    public static function get_available_locations() {
        return self::$locations_labels;
    }

    /**
     * Ottieni il valore da criptare per TermeGest
     */
    private function get_location_value_for_encryption($location_slug) {
        return self::$locations_to_be_encrypted[$location_slug] ?? '';
    }

    /**
     * Ottieni tutte le location da criptare (statico)
     */
    public static function get_locations_to_encrypt() {
        return self::$locations_to_be_encrypted;
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
        return array();
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
        // CSS di vanilla-calendar-pro (compilato da esbuild)
        wp_enqueue_style(
            'vanilla-calendar-bundle',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/dist/booking-form.min.css',
            array(),
            '1.1.0'
        );

        wp_enqueue_style(
            'booking-form-style',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/booking-form.css',
            array('vanilla-calendar-bundle'),
            '1.0.0'
        );

        // Booking form script (compilato con esbuild, include vanilla-calendar-pro)
        wp_enqueue_script(
            'booking-form-script',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/dist/booking-form.min.js',
            array(),
            '1.1.0',
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
     * Valida i dati del form
     */
    private function validate_booking_data($location, $booking_date, $ticket_type, $fascia_id, $num_male, $num_female) {
        $errors = new WP_Error();

        // Valida location
        $valid_locations = array_keys(self::$locations_labels);
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

        // Valida fascia oraria (ID)
        if ($fascia_id <= 0) {
            $errors->add('invalid_fascia', 'Seleziona una fascia oraria valida.');
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
        if (empty($location) ) {
            wp_send_json_error(array(
                'message' => 'Location e data sono obbligatori.'
            ));
        }

        // Valida location
        if (!array_key_exists($location, self::$locations_labels)) {
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
    
        $location_value = $this->get_location_value_for_encryption($location);

        if (empty($location_value)) {
            error_log('ERRORE: Valore location non trovato per - ' . $location);
            wp_send_json_error(array(
                'message' => 'Codice location non trovato.'
            ));
        }
        
        error_log("Location value per criptazione: {$location_value}");
        
        error_log("Data convertita - Day: {$day}, Month: {$month}, Year: {$year}");

        // Chiama l'API TermeGest
        $disponibilita = skianet_termegest_get_disponibilita_by_day($day, $month, $year, $location_value);

        // Verifica risultati
        if (empty($disponibilita)) {
            wp_send_json_error(array(
                'message' => 'Nessuna disponibilità per la data selezionata.'
            ));
        }

        // Formatta le fasce per il frontend
        $available_slots = $this->format_available_slots($disponibilita);
        
        // error_log('Available slots formattati: ' . print_r($available_slots, true));

        // Ritorna i dati
        wp_send_json_success(array(
            'message' => 'Disponibilità verificata con successo.',
            'available_slots' => $available_slots
        ));
        
    }

    /**
     * Formatta le disponibilità per il frontend
     * 
     * @param array $disponibilita Array di oggetti disponibilità da TermeGest
     * @return array Array formattato per JavaScript
     */
    private function format_available_slots(array $disponibilita): array {
        $slots = array();
        
        foreach ($disponibilita as $dispo) {
            // Estrai l'ora dalla fascia (es. "Ingresso ore 9:00" -> "09:00")
            $time = '';
            if (isset($dispo->fascia) && preg_match('/(\d{1,2}):(\d{2})/', $dispo->fascia, $matches)) {
                $hour = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $minute = $matches[2];
                $time = $hour . ':' . $minute;
            }
            
            // Aggiungi solo se ha tutti i dati necessari
            if (!empty($time) && isset($dispo->iddispo, $dispo->disponibili)) {
                $slots[] = array(
                    'id' => $dispo->iddispo,
                    'time' => $time,
                    'disponibilita' => (int)$dispo->disponibili,
                    'fascia_label' => $dispo->fascia ?? $time
                );
            }
        }
        
        return $slots;
    }

    /**
     * Gestisce il submit AJAX del form
     */
    public function handle_ajax_submission() {
        // Verifica nonce
        if (!isset($_POST['booking_form_nonce']) || 
            !wp_verify_nonce($_POST['booking_form_nonce'], 'booking_form_action')) {
            wp_send_json_error(array('message' => 'Errore di sicurezza. Riprova.'));
        }

        // Sanitizza e recupera TUTTI i dati
        $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';
        $booking_date = isset($_POST['booking_date']) ? sanitize_text_field($_POST['booking_date']) : '';
        $ticket_type = isset($_POST['ticket_type']) ? sanitize_text_field($_POST['ticket_type']) : '';
        $fascia_id = isset($_POST['time_slot']) ? intval($_POST['time_slot']) : 0;
        $num_male = isset($_POST['num_male']) ? intval($_POST['num_male']) : 0;
        $num_female = isset($_POST['num_female']) ? intval($_POST['num_female']) : 0;

        // Log per debug
        error_log('=== DATI PRENOTAZIONE ===');
        error_log("Location: {$location}");
        error_log("Data: {$booking_date}");
        error_log("ID Fascia: {$fascia_id}");
        error_log("Tipo Ingresso: {$ticket_type}");
        error_log("Num Uomini: {$num_male}");
        error_log("Num Donne: {$num_female}");
        error_log("Totale: " . ($num_male + $num_female));

        // Validazione
        $validation = $this->validate_booking_data(
            $location, 
            $booking_date, 
            $ticket_type, 
            $fascia_id,
            $num_male, 
            $num_female
        );
        
        if (is_wp_error($validation)) {
            wp_send_json_error(array('message' => $validation->get_error_message()));
        }

        // TODO: Salva la prenotazione
        // Qui avrai tutti i dati disponibili per salvarli
        $booking_data = array(
            'location' => $location,
            'booking_date' => $booking_date,
            'fascia_id' => $fascia_id,
            'ticket_type' => $ticket_type,
            'num_male' => $num_male,
            'num_female' => $num_female,
            'total_guests' => $num_male + $num_female
        );
        
        error_log('Booking data: ' . print_r($booking_data, true));

        $booking_success = true;

        if ($booking_success) {
            wp_send_json_success(array('message' => 'Prenotazione effettuata con successo!'));
        } else {
            wp_send_json_error(array('message' => 'Errore durante il salvataggio. Riprova.'));
        }
    }
    
}