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
        // Hook per utenti loggati
        // add_action('admin_post_submit_booking', array($this, 'handle_form_submission'));
        
        // Hook per utenti non loggati
        // add_action('admin_post_nopriv_submit_booking', array($this, 'handle_form_submission'));
        
        // Shortcode per il form
        add_shortcode('booking_form', array($this, 'render_booking_form'));
        
        // Enqueue scripts e styles
        // add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
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

        // wp_enqueue_script(
        //     'booking-form-script',
        //     plugin_dir_url(dirname(__FILE__)) . 'public/js/booking-form.js',
        //     array('jquery'),
        //     '1.0.0',
        //     true
        // );

        // // Passa variabili a JavaScript
        // wp_localize_script('booking-form-script', 'bookingAjax', array(
        //     'ajaxurl' => admin_url('admin-ajax.php'),
        //     'nonce' => wp_create_nonce('booking_ajax_nonce')
        // ));
    }

    /**
     * Renderizza il form tramite shortcode
     */
    public function render_booking_form() {
        ob_start();
        
        // Mostra messaggio di successo se presente
        if (isset($_GET['booking_success']) && $_GET['booking_success'] == '1') {
            echo '<div class="booking-response success">Prenotazione effettuata con successo!</div>';
        }
        
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

        // Salva la prenotazione
        $booking_id = $this->save_booking($location, $booking_date, $ticket_type);

        if ($booking_id) {
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
        $valid_locations = array('location1', 'location2', 'location3');
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

    /**
     * Salva la prenotazione nel database
     */
    private function save_booking($location, $booking_date, $ticket_type) {
        // Opzione 1: Salva come Custom Post Type
        $booking_id = wp_insert_post(array(
            'post_type' => 'booking',
            'post_title' => sprintf('Prenotazione %s - %s', $location, $booking_date),
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
            'meta_input' => array(
                '_booking_location' => $location,
                '_booking_date' => $booking_date,
                '_booking_ticket_type' => $ticket_type,
                '_booking_status' => 'pending',
                '_booking_created_at' => current_time('mysql')
            )
        ));

        // Opzione 2: Salva in una tabella custom (se preferisci)
        // global $wpdb;
        // $table_name = $wpdb->prefix . 'bookings';
        // $wpdb->insert($table_name, array(
        //     'location' => $location,
        //     'booking_date' => $booking_date,
        //     'ticket_type' => $ticket_type,
        //     'user_id' => get_current_user_id(),
        //     'status' => 'pending',
        //     'created_at' => current_time('mysql')
        // ));
        // $booking_id = $wpdb->insert_id;

        // Invia email di conferma (opzionale)
        if ($booking_id) {
            $this->send_confirmation_email($booking_id);
        }

        return $booking_id;
    }

    /**
     * Invia email di conferma
     */
    private function send_confirmation_email($booking_id) {
        $user = wp_get_current_user();
        $to = $user->user_email;
        $subject = 'Conferma Prenotazione';
        $message = sprintf(
            'La tua prenotazione #%d è stata registrata con successo.',
            $booking_id
        );

        wp_mail($to, $subject, $message);
    }

    /**
     * Ottieni i dettagli di una prenotazione
     */
    public function get_booking($booking_id) {
        $booking = get_post($booking_id);
        
        if (!$booking || $booking->post_type !== 'booking') {
            return false;
        }

        return array(
            'id' => $booking->ID,
            'location' => get_post_meta($booking->ID, '_booking_location', true),
            'date' => get_post_meta($booking->ID, '_booking_date', true),
            'ticket_type' => get_post_meta($booking->ID, '_booking_ticket_type', true),
            'status' => get_post_meta($booking->ID, '_booking_status', true),
            'created_at' => get_post_meta($booking->ID, '_booking_created_at', true)
        );
    }
}