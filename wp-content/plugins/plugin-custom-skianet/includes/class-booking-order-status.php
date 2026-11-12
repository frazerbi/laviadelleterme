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

        // booked / not-booked sono stati pagati
        add_filter('woocommerce_order_is_paid_statuses', array($this, 'add_paid_statuses'));
    }

    /**
     * Dichiara booked e not-booked come stati pagati.
     *
     * Li registra il plugin Custom Order Status, fuori dall'elenco di WooCommerce
     * (solo processing e completed), quindi senza questo filtro is_paid() e' falso per
     * ogni ordine arrivato a destinazione. Il caso concreto e' l'evento GA4 purchase:
     * WooCommerce Google Analytics Pro lo manda su woocommerce_payment_complete solo se
     * is_paid(), ma a quel punto la catena processing → completed → booked/not-booked
     * (gli hook qui sopra, dentro il save di payment_complete()) ha gia' spostato
     * l'ordine. E' lo stesso guasto che termeshop aveva con coupon-sent fino ad agosto 2026.
     *
     * Valutato a runtime: nessun effetto retroattivo, nessun evento per lo storico.
     *
     * @param string[] $statuses stati pagati, senza prefisso wc-
     * @return string[]
     */
    public function add_paid_statuses($statuses) {
        return array_values(array_unique(array_merge((array) $statuses, array('booked', 'not-booked'))));
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
        

        // Qui si cambia SOLO lo status. L'invio delle email (prenotazione + coupon
        // dei prodotti non prenotati) e' interamente a carico di
        // Booking_Email_Notification::send_booking_details(), su
        // woocommerce_payment_complete prio 20. Non reintrodurre qui una chiamata a
        // send_mixed_order_coupons(): WC_Order::payment_complete() fa set_status()+save()
        // PRIMA di lanciare woocommerce_payment_complete, quindi questo hook gira prima
        // che Booking_Code_Assignment abbia assegnato i codici licenza - manderebbe
        // un'email coupon doppia e senza codici.
        if ($this->order_has_booking($order)) {
            $order->update_status('booked', 'Ordine con prenotazioni TermeGest.');
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
}