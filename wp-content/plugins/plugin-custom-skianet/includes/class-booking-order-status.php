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
        
        // Verifica che l'ordine sia pagato e completato
        if (!$order->is_paid() || $order->get_status() !== 'completed') {
            error_log("Move to Booked: Ordine {$order_id} non pagato/completato - skip");
            return;
        }
        
        // ✅ Verifica se l'ordine ha prenotazioni TermeGest
        if ($this->order_has_booking($order)) {
            error_log("✅ Ordine {$order_id} CON prenotazioni → Booked");
            $order->update_status('booked', 'Ordine con prenotazioni TermeGest.');
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
}