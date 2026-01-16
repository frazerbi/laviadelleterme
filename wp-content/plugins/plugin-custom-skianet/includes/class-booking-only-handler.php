<?php
/**
 * Classe per gestire lo shortcode del form di prenotazione
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

class Booking_Only_Handler {
    

    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init();
    }

    /**
     * Inizializza lo shortcode
     */
    private function init() {
        // Registra lo shortcode
        add_shortcode('booking_only_form', array($this, 'render_booking_only_form'));
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Registra handler AJAX
        add_action('wp_ajax_submit_booking_only_ajax', [$this, 'handle_booking_only_submission']);
        add_action('wp_ajax_nopriv_submit_booking_only_ajax', [$this, 'handle_booking_only_submission']);
    }
    
    /**
     * Renderizza il form di prenotazione
     *
     * @param array $atts Attributi dello shortcode
     * @return string HTML del form
     */
    public function render_booking_only_form() {
        // Inizia output buffering
        ob_start();
        
        // Include il template del form
        include plugin_dir_path(dirname(__FILE__)) . 'templates/booking-only-form.php';
        
        // Restituisci il contenuto bufferizzato
        return ob_get_clean();
    }
    
    /**
     * Enqueue degli assets necessari per il form
     */
    public function enqueue_assets() {

        // Carica solo se lo shortcode è presente nella pagina
        if (is_singular()) {
            global $post;
            if (!has_shortcode($post->post_content, 'booking_only_form')) {
                return;
            }
        }

        // CSS del form
        wp_enqueue_style(
            'booking-only-form-style',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/booking-only-form.css',
            [],
            PLUGIN_SKIANET_VERSION
        );
        
        // JavaScript del form
        wp_enqueue_script(
            'booking-only-form-script',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/src/booking-only-form.js',
            [],
            PLUGIN_SKIANET_VERSION,
            true
        );
        
        // Localizza script per AJAX
        wp_localize_script('booking-only-form-script', 'bookingOnlyAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('booking_only_form_action'),
        ]);
    }

    /**
     * Gestisce il submit del form booking only
     */
    public function handle_booking_only_submission() {
        // Verifica nonce
        if (!isset($_POST['booking_only_form_nonce']) || !wp_verify_nonce($_POST['booking_only_form_nonce'], 'booking_only_form_action')) {
            wp_send_json_error(['message' => 'Errore di sicurezza.']);
        }
        
        // Sanitizza i dati
        $purchase_code = isset($_POST['purchase_code']) ? sanitize_text_field($_POST['purchase_code']) : '';
        $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';
        $booking_date = isset($_POST['booking-only_date']) ? sanitize_text_field($_POST['booking-only_date']) : '';
        $time_slot = isset($_POST['time_slot']) ? sanitize_text_field($_POST['time_slot']) : '';
        $gender = isset($_POST['gender']) ? sanitize_text_field($_POST['gender']) : '';
        
        // Validazione base
        if (empty($purchase_code) || empty($location) || empty($booking_date) || empty($time_slot) || empty($gender)) {
            wp_send_json_error(['message' => 'Tutti i campi sono obbligatori.']);
        }
        
        // Valida formato codice (16 caratteri alfanumerici)
        if (!preg_match('/^[A-Z0-9]{16}$/', $purchase_code)) {
            wp_send_json_error(['message' => 'Codice acquisto non valido.']);
        }

        // Valida location usando il metodo di Booking_Handler
        if (!Booking_Handler::is_valid_location($location)) {
            wp_send_json_error(['message' => 'Location non valida.']);
        }
        
        // TODO: Qui aggiungi la tua logica specifica
        // - Verifica codice nel database
        // - Salva prenotazione
        // - Invia email
        
        // Log per debug
        error_log(sprintf(
            'Booking Only - Dati ricevuti: Codice=%s, Location=%s, Data=%s, Slot=%s, Gender=%s',
            $purchase_code, $location, $booking_date, $time_slot, $gender
        ));
        
        // Risposta di successo
        wp_send_json_success([
            'message' => 'Prenotazione ricevuta con successo!',
            'redirect_url' => home_url('/conferma-prenotazione/') // Personalizza questo URL
        ]);
    }

}