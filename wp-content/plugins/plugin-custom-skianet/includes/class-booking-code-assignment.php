<?php
/**
 * Gestisce l'assegnazione automatica dei codici dopo il pagamento
 * Integra con WooCommerce License Delivery
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

class Booking_Code_Assignment {

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
        add_action('woocommerce_payment_complete', array($this, 'assign_codes_on_payment'), 10, 1);
    }

    /**
     * Assegna codici quando il pagamento è completato
     */
    public function assign_codes_on_payment($order_id) {
        error_log("=== PAGAMENTO COMPLETATO: Ordine {$order_id} ===");
        
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("Ordine {$order_id} non trovato");
            return;
        }
        
        // Verifica che WC License Delivery sia attivo
        if (!class_exists('WC_LD_Code_Assignment')) {
            error_log("❌ WooCommerce License Delivery non disponibile");
            $order->add_order_note('ERRORE: WooCommerce License Delivery non attivo.');
            return;
        }
        
        // Verifica che l'ordine contenga prenotazioni
        $has_booking = false;
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_booking_id')) {
                $has_booking = true;
                break;
            }
        }
        
        if (!$has_booking) {
            error_log("Ordine {$order_id} senza prenotazioni - skip");
            return;
        }
        
        // ✅ STEP 1: Assegna codici
        try {
            $codeAssign = new WC_LD_Code_Assignment();
            $codeAssign->assign_license_codes_to_order($order_id);
            
            error_log("✅ Codici assegnati all'ordine {$order_id}");
            $order->add_order_note('Codici di accesso alle terme assegnati.');
            
        } catch (Exception $e) {
            error_log("❌ ERRORE assegnazione codici: " . $e->getMessage());
            $order->add_order_note('ERRORE: Impossibile assegnare codici - ' . $e->getMessage());
        }

        // ✅ STEP 2: Sincronizza con TermeGest
        if (class_exists('Booking_TermeGest_Sync')) {
            error_log("Avvio sincronizzazione TermeGest...");
            
            $sync = Booking_TermeGest_Sync::get_instance();
            $sync->sync_order_to_termegest($order_id);
            
        } else {
            error_log("⚠️ Booking_TermeGest_Sync non disponibile");
        }
    }
}