<?php
/**
 * Classe per gestire il form di prenotazione con codice acquisto
 */

class Booking_Code_Form_Handler {
    
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
     * Costruttore - registra hooks e shortcode
     */
    private function __construct() {
        add_shortcode('render_booking_only_form_code', array($this, 'render_booking_only_form_code')); 
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_check_availability_api', array($this, 'check_availability_api'));
        add_action('wp_ajax_nopriv_check_availability_api', array($this, 'check_availability_api'));
        add_action('wp_ajax_submit_booking_with_code_ajax', array($this, 'submit_booking_with_code'));
        add_action('wp_ajax_nopriv_submit_booking_with_code_ajax', array($this, 'submit_booking_with_code'));
    }
    
    /**
     * Renderizza il form tramite shortcode
     */
    public function render_booking_only_form_code() {
        
        // Inizia il buffer output
        ob_start();
        
        // Includi il template del form
        include plugin_dir_path(dirname(__FILE__)) . 'templates/booking-only-form.php';
        
        return ob_get_clean();
    }

    /**
     * Carica CSS e JS
     */
    public function enqueue_assets() {
        global $post;
        
        // Carica solo se lo shortcode è presente
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'render_booking_only_form_code')) {
            return;
        }
        
        // CSS
        // wp_enqueue_style(
        //     'booking-code-form-css',
        //     plugin_dir_url(dirname(__FILE__)) . 'assets/css/booking-form.css',
        //     array(),
        //     '1.0.0'
        // );
        
        // JavaScript
        wp_enqueue_script(
            'booking-code-form-js',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/booking-only-form.js',
            array(),
            '1.0.0',
            true
        );
        
        // Localizza script per AJAX
        wp_localize_script('booking-code-form-js', 'bookingCodeAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('booking_code_nonce')
        ));
    }

    /**
     * Check availability API (riusa la logica esistente)
     */
    public function check_availability_api() {
        // Delega alla classe principale Booking_Handler
        if (class_exists('Booking_Handler')) {
            $booking_handler = Booking_Handler::get_instance();
            if (method_exists($booking_handler, 'check_availability_api')) {
                $booking_handler->check_availability_api();
                return;
            }
        }
        
        wp_send_json_error(array(
            'message' => 'Servizio non disponibile'
        ));
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
