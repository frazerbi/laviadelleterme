<?php
/**
 * Gestisce gli status personalizzati degli ordini in base alle prenotazioni
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

class Booking_Order_Status {

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
        $this->init_hooks();
    }

    /**
     * Inizializza gli hooks
     */
    private function init_hooks() {
        // STEP 1: Processing → Completed (auto-complete ordini pagati)
        add_action('woocommerce_order_status_processing', array($this, 'auto_complete_paid_order'), 10, 1);
        
        // STEP 2: Completed → Booked/Not-Booked (in base a prenotazioni)
        add_action('woocommerce_order_status_completed', array($this, 'move_to_booked_status'), 10, 1);
    }

    /**
     * Auto-completa ordini pagati (Processing → Completed)
     */
    public function auto_complete_paid_order($order_id) {
        if (!$order_id) {
            return;
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order || is_wp_error($order)) {
            return;
        }
        
        try {
            // Verifica se l'ordine è stato pagato
            if ($order->is_paid()) {
                $order->update_status('completed', 'Ordine completato automaticamente (pagato).');
            }
        } catch (Exception $e) {
            // Nessuna azione: l'ordine resta in processing e resta recuperabile a mano.
        }
    }

    /**
     * Sposta ordine a Booked o Not-Booked in base alle prenotazioni
     */
    public function move_to_booked_status($order_id) {
        if (!$order_id) {
            return;
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order || is_wp_error($order)) {
            return;
        }
        

        // ✅ Verifica se l'ordine ha prenotazioni e/o prodotti non-booking
        $has_booking = $this->order_has_booking($order);
        $has_nonbooking = $this->order_has_nonbooking($order);
        
        if ($has_booking) {
            $order->update_status('booked', 'Ordine con prenotazioni TermeGest.');
            
            // ✅ Se ci sono ANCHE prodotti non-booking, invia coupon per quelli
            if ($has_nonbooking) {
                $this->send_nonbooking_coupons_for_mixed_order($order);
            }
        } else {
            $order->update_status('not-booked', 'Ordine senza prenotazioni.');
        }

    }

    /**
     * Verifica se l'ordine contiene prenotazioni
     * Controlla presenza di _booking_id negli order items
     */
    private function order_has_booking($order) {
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_booking_id')) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Verifica se l'ordine contiene prodotti SENZA prenotazione
     */
    private function order_has_nonbooking($order) {
        foreach ($order->get_items() as $item) {
            if (!$item->get_meta('_booking_id')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Invia coupon per prodotti non-booking in ordini misti
     */
    private function send_nonbooking_coupons_for_mixed_order($order) {
        if (!class_exists('Booking_Nonbooking_Email')) {
            return;
        }
        
        try {
            $nonbooking_email = Booking_Nonbooking_Email::get_instance();
            $nonbooking_email->send_mixed_order_coupons($order);
        } catch (Exception $e) {
            // Eccezione ignorata: l'invio email non deve interrompere il flusso chiamante.
        }
    }
}