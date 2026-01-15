<?php
/**
 * Classe per gestire il form di prenotazione con codice acquisto
 */

class Booking_Code_Form_Handler {
    
    /**
     * Costruttore - registra hooks e shortcode
     */
    public function __construct() {
        add_shortcode('booking_form_code', array($this, 'render_booking_only_form_code'));
    }
    
    /**
     * Renderizza il form tramite shortcode
     */
    public function render_booking_only_form_code($atts) {
        
        // Inizia il buffer output
        ob_start();
        
        // Includi il template del form
        include plugin_dir_path(dirname(__FILE__)) . 'templates/booking-only-form.php';
        
        return ob_get_clean();
    }
    
    /**
     * Verifica il codice acquisto via AJAX
     */
    public function verify_purchase_code() {
        // Verifica nonce
        check_ajax_referer('booking_code_form_action', 'nonce');
        
        $code = sanitize_text_field($_POST['code']);
        
        if (empty($code)) {
            wp_send_json_error(array(
                'message' => 'Codice non valido'
            ));
        } else {
            wp_send_json_success(array(
                'message' => 'Codice valido'
            ));
        }
    }

}

// Inizializza la classe
new Booking_Code_Form_Handler();
