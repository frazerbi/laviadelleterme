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
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/booking-only-form.js',
            ['jquery'],
            PLUGIN_SKIANET_VERSION,
            true
        );
        
        // Localizza script per AJAX
        wp_localize_script('booking-only-form-script', 'bookingOnlyAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('booking_only_form_action'),
        ]);
    }
}