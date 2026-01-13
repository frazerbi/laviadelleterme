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
     * Location disponibili (unica fonte di verità)
     */
    private static $locations = array(
        'terme-saint-vincent' => array(
            'label' => 'Terme di Saint-Vincent',
            'value' => 'Saint Vincent'
        ),
        'terme-genova' => array(
            'label' => 'Terme di Genova',
            'value' => 'Genova'
        ),
        'monterosa-spa' => array(
            'label' => 'Monterosa SPA',
            'value' => 'Monterosa'
        )
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
        return array_map(function($loc) {
            return $loc['label'];
        }, self::$locations);
    }

    /**
     * Ottieni tutte le location da criptare (statico per uso esterno)
     */
    public static function get_locations_to_encrypt() {
        return array_map(function($loc) {
            return $loc['value'];
        }, self::$locations);
    }

    /**
     * Ottieni il valore da criptare per una location specifica
     */
    private function get_location_value_for_encryption($location_slug) {
        return self::$locations[$location_slug]['value'] ?? '';
    }

    /**
     * Ottieni i tipi di ingresso disponibili
     */
    public static function get_ticket_types() {
        return self::$ticket_types;
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

        add_action('init', function() {
        if (!session_id()) {
                session_start();
            }
        });

        add_shortcode('booking_form', array($this, 'render_booking_form'));
        add_action('wp_ajax_submit_booking_ajax', array($this, 'handle_ajax_submission'));
        add_action('wp_ajax_nopriv_submit_booking_ajax', array($this, 'handle_ajax_submission'));
        add_action('wp_ajax_check_availability_api', array($this, 'check_availability_api'));
        add_action('wp_ajax_nopriv_check_availability_api', array($this, 'check_availability_api'));
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
            'error_message' => __('Si è verificato un errore. Riprova.', 'text-domain'),
            'christmas_dates' => $this->get_christmas_dates()
        ));
    }

    /**
     * Effettua chiamata API per verificare disponibilità
     */
    public function check_availability_api() {

        // Verifica nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'booking_form_nonce')) {
            wp_send_json_error(array('message' => 'Errore di sicurezza.'));
        }

        // Sanitizza i dati
        $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';
        $booking_date = isset($_POST['booking_date']) ? sanitize_text_field($_POST['booking_date']) : '';        

        // Valida location
        if (!$this->is_valid_location($location)) {
            wp_send_json_error(array('message' => 'Location non valida.'));
        }

        // Valida e parse data
        $date = $this->parse_date($booking_date);
        if (is_wp_error($date)) {
            wp_send_json_error(array('message' => $date->get_error_message()));
        }

        $location_value = $this->get_location_value_for_encryption($location);
        if (empty($location_value)) {
            wp_send_json_error(array('message' => 'Codice location non trovato.'));
        }

        $disponibilita = skianet_termegest_get_disponibilita_by_day(
            (int) $date->format('d'),
            (int) $date->format('m'),
            (int) $date->format('Y'),
            $location_value
        );
    
        if (empty($disponibilita)) {
            wp_send_json_error(array('message' => 'Nessuna disponibilità per la data selezionata.'));
        }
        

        // Formatta le fasce per il frontend
        $available_slots = $this->format_available_slots($disponibilita);
                        
        // Ritorna i dati
        wp_send_json_success(array(
            'message' => 'Disponibilità verificata con successo.',
            'available_slots' => $available_slots
        ));
                
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
        $categorie = isset($_POST['categorie']) ? sanitize_text_field($_POST['categorie']) : '';
        $disponibilita = isset($_POST['disponibilita']) ? intval($_POST['disponibilita']) : 0; // ✅ Recupera disponibilità

        // Log per debug
        error_log('=== DATI PRENOTAZIONE ===');
        error_log("Location: {$location}");
        error_log("Data: {$booking_date}");
        error_log("ID Fascia: {$fascia_id}");
        error_log("Tipo Ingresso: {$ticket_type}");
        error_log("Num Uomini: {$num_male}");
        error_log("Num Donne: {$num_female}");
        error_log("Categorie: {$categorie}");
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

        // Prepara dati per la prenotazione
        $booking_data = array(
            'location' => $location,
            'location_name' => $this->get_location_value_for_encryption($location),
            'booking_date' => $booking_date,
            'fascia_id' => $fascia_id,
            'ticket_type' => $ticket_type,
            'num_male' => $num_male,
            'num_female' => $num_female,
            'total_guests' => $num_male + $num_female,
            'disponibilita' => $disponibilita,
            'categorie' => $categorie,
            'user_id' => get_current_user_id(),
            'created_at' => current_time('mysql')
        );
        
        error_log('Booking data: ' . print_r($booking_data, true));

        // Salva la prenotazione
        $booking_id = $this->save_booking($booking_data);

        if ($booking_id) {

            $redirect_handler = Booking_Redirect::get_instance();
            $redirect_url = $redirect_handler->redirect_to_product($booking_id, $booking_data);

            if ($redirect_url) {
                wp_send_json_success(array(
                    'message' => 'Prenotazione effettuata con successo!',
                    'booking_id' => $booking_id,
                    'redirect_url' => $redirect_url  // ✅ Passa URL al frontend
                ));
            } else {
                wp_send_json_error(array('message' => 'Errore nel redirect al prodotto.'));
            }

        } else {
            wp_send_json_error(array(
                        'message' => 'Posti non più disponibili per questa fascia. Riprova con un\'altra fascia oraria.'
            ));        
        }
    }

    /** 
     * Valida i dati del form
     */
    private function validate_booking_data($location, $booking_date, $ticket_type, $fascia_id, $num_male, $num_female) {
        $errors = new WP_Error();

        // Valida location
        if (!$this->is_valid_location($location)) {
            $errors->add('invalid_location', 'Seleziona una location valida.');
        }

        // Valida data
        $date = $this->parse_date($booking_date);
        if (is_wp_error($date)) {
            $errors->add($date->get_error_code(), $date->get_error_message());
        }

        if ($this->is_christmas_period($booking_date) && $ticket_type === 'giornaliero') {
            $errors->add('invalid_ticket_natale', 'Nel periodo natalizio è disponibile solo l\'ingresso 4 ore.');
        }


        // Valida tipo ingresso
        if (!$this->is_valid_ticket_type($ticket_type)) {
            $errors->add('invalid_type', 'Seleziona un tipo di ingresso valido.');
        }

        // Valida fascia oraria (ID)
        if ($fascia_id <= 0) {
            $errors->add('invalid_fascia', 'Seleziona una fascia oraria valida.');
        }

        // Valida numero ingressi
        if ($num_male < 0 || $num_male > 10) {
            $errors->add('invalid_num_male', 'Numero ingressi uomo non valido (0-10).');
        }

        if ($num_female < 0 || $num_female > 10) {
            $errors->add('invalid_num_female', 'Numero ingressi donna non valido (0-10).');
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
     * Renderizza il form tramite shortcode
     */
    public function render_booking_form() {
        ob_start();
        
        include plugin_dir_path(dirname(__FILE__)) . 'templates/booking-form.php';
        
        return ob_get_clean();
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
                    'fascia_label' => $dispo->fascia ?? $time,
                    'categorie' => $dispo->categorie ?? ''
                );
            }
        }
        
        return $slots;
    }

    /**
     * Salva la prenotazione nella sessione
     */
    private function save_booking($data) {
        // Avvia sessione se non già avviata
        if (!session_id()) {
            session_start();
        }
        
        // Genera un ID univoco per la prenotazione
        $booking_id = uniqid('booking_', true);
        
        $posti_disponibili = $data['disponibilita'] ?? 0;
        $posti_richiesti = $data['total_guests'];
        
        if ($posti_disponibili < $posti_richiesti) {
            error_log("ERRORE: Posti insufficienti! Disponibili: {$posti_disponibili}, Richiesti: {$posti_richiesti}");
            return false;
        }

        // Prepara tutti i dati da salvare
        $booking_data = array(
            'booking_id' => $booking_id,
            'location' => $data['location'],
            'location_name' => $data['location_name'],
            'booking_date' => $data['booking_date'],
            'fascia_id' => $data['fascia_id'],
            'ticket_type' => $data['ticket_type'],
            'num_male' => $data['num_male'],
            'num_female' => $data['num_female'],
            'total_guests' => $data['total_guests'],
            'disponibilita' => $posti_disponibili,
            'categorie' => $data['categorie'],
            'user_id' => $data['user_id'],
            'status' => 'pending',
            'created_at' => $data['created_at'],
            'session_timestamp' => time()
        );
        
        // Salva nella sessione
        $_SESSION['termegest_booking'] = $booking_data;
        
        error_log('Prenotazione salvata in sessione: ' . print_r($booking_data, true));
        
        return $booking_id;
    }

    /**
     * Parse e valida una data
     */
    private function parse_date($booking_date) {
        if (empty($booking_date)) {
            return new WP_Error('empty_date', 'Inserisci una data.');
        }
        
        $date = DateTime::createFromFormat('Y-m-d', $booking_date);
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        
        if (!$date || $date < $today) {
            return new WP_Error('invalid_date', 'Inserisci una data valida (non nel passato).');
        }
        
        return $date;
    }

    /**
     * Recupera la prenotazione dalla sessione
     */
    private function get_booking_from_session() {
        if (!session_id()) {
            session_start();
        }
        
        return $_SESSION['termegest_booking'] ?? null;
    }

    /**
     * Cancella la prenotazione dalla sessione
     */
    private function clear_booking_session() {
        if (!session_id()) {
            session_start();
        }
        
        unset($_SESSION['termegest_booking']);
    }
    
    /**
     * Verifica se la location è valida
     */
    private function is_valid_location($location) {
        return !empty($location) && array_key_exists($location, self::$locations);
    }

    /**
     * Verifica se il tipo di ingresso è valido
     */
    private function is_valid_ticket_type($ticket_type) {
        return !empty($ticket_type) && array_key_exists($ticket_type, self::$ticket_types);
    }

    /**
     * Ottieni array di date del periodo natalizio (25 dic - 6 gen)
     */
    private function get_christmas_dates() {
        $dates = array();
        $current_year = (int) date('Y');
        
        // 25-31 Dicembre anno corrente
        for ($day = 25; $day <= 31; $day++) {
            $dates[] = sprintf('%04d-12-%02d', $current_year, $day);
        }
        
        // 1-6 Gennaio anno successivo
        $next_year = $current_year + 1;
        for ($day = 1; $day <= 6; $day++) {
            $dates[] = sprintf('%04d-01-%02d', $next_year, $day);
        }
        
        return $dates;
    }

    /**
     * Verifica se una data è nel periodo natalizio
     */
    private function is_christmas_period($date_string) {
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

}