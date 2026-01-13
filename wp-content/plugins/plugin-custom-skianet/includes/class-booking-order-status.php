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
            error_log('Auto Complete: Order ID non valido');
            return;
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order || is_wp_error($order)) {
            error_log("Auto Complete: Impossibile ottenere ordine {$order_id}");
            return;
        }
        
        try {
            // Verifica se l'ordine è stato pagato
            if ($order->is_paid()) {
                $old_status = $order->get_status();
                $result = $order->update_status('completed', 'Ordine completato automaticamente (pagato).');
                
                if (is_wp_error($result)) {
                    error_log("Auto Complete: Errore ordine {$order_id}: " . $result->get_error_message());
                } else {
                    error_log("✅ Ordine {$order_id}: {$old_status} → completed");
                }
            } else {
                error_log("Auto Complete: Ordine {$order_id} non pagato - skip");
            }
        } catch (Exception $e) {
            error_log("Auto Complete: Eccezione ordine {$order_id}: " . $e->getMessage());
        }
    }

    /**
     * Sposta ordine a Booked o Not-Booked in base alle prenotazioni
     */
    public function move_to_booked_status($order_id) {
        if (!$order_id) {
            error_log('Move to Booked: Order ID non valido');
            return;
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order || is_wp_error($order)) {
            error_log("Move to Booked: Impossibile ottenere ordine {$order_id}");
            return;
        }
        

        // ✅ Verifica se l'ordine ha prenotazioni e/o prodotti non-booking
        $has_booking = $this->order_has_booking($order);
        $has_nonbooking = $this->order_has_nonbooking($order);
        
        if ($has_booking) {
            error_log("✅ Ordine {$order_id} CON prenotazioni → Booked");
            $order->update_status('booked', 'Ordine con prenotazioni TermeGest.');
            
            // ✅ Se ci sono ANCHE prodotti non-booking, invia coupon per quelli
            if ($has_nonbooking) {
                error_log("✅ Ordine {$order_id} è MISTO - invio anche coupon non-booking");
                $this->send_nonbooking_coupons_for_mixed_order($order);
            }
        } else {
            error_log("✅ Ordine {$order_id} SENZA prenotazioni → Not-Booked");
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
                error_log("Item {$item->get_id()} ha prenotazione: " . $item->get_meta('_booking_id'));
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
                error_log("Item {$item->get_id()} NON ha prenotazione: " . $item->get_name());
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
            error_log("⚠️ Booking_Nonbooking_Email non disponibile");
            return;
        }
        
        try {
            $nonbooking_email = Booking_Nonbooking_Email::get_instance();
            $nonbooking_email->send_mixed_order_coupons($order);
        } catch (Exception $e) {
            error_log("❌ Errore invio coupon ordine misto: " . $e->getMessage());
        }
    }
}