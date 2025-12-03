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
     * Gestisce il submit del form
     */
    public function handle_form_submission() {
        // Verifica nonce
        if (!isset($_POST['booking_form_nonce']) || 
            !wp_verify_nonce($_POST['booking_form_nonce'], 'booking_form_action')) {
            wp_die('Errore di sicurezza. Riprova.');
        }

        // Verifica submit
        if (!isset($_POST['submit_booking'])) {
            wp_die('Richiesta non valida.');
        }

        // Sanitizza i dati
        $location = sanitize_text_field($_POST['location']);
        $booking_date = sanitize_text_field($_POST['booking_date']);
        $ticket_type = sanitize_text_field($_POST['ticket_type']);

        // Validazione
        $validation = $this->validate_booking_data($location, $booking_date, $ticket_type);
        
        if (is_wp_error($validation)) {
            wp_die($validation->get_error_message());
        }

        if (empty($booking_date)) {
            // Successo - redirect con messaggio
            $redirect_url = add_query_arg('booking_success', '1', wp_get_referer());
            wp_safe_redirect($redirect_url);
            exit;
        } else {
            wp_die('Errore durante il salvataggio della prenotazione. Riprova.');
        }
    }

    /**
     * Valida i dati del form
     */
    private function validate_booking_data($location, $booking_date, $ticket_type) {
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

        $valid_types = array('4h', 'giornaliero');
        if (empty($ticket_type) || !in_array($ticket_type, $valid_types)) {
            $errors->add('invalid_type', 'Seleziona un tipo di ingresso valido.');
        }


        // Controlla disponibilità (esempio)
        if (!$this->check_availability($location, $booking_date)) {
            $errors->add('not_available', 'La location non è disponibile per questa data.');
        }

        if ($errors->has_errors()) {
            return $errors;
        }

        return true;
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