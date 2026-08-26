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
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Verifica che WC License Delivery sia attivo
        if (!class_exists('WC_LD_Code_Assignment')) {
            $order->add_order_note('ERRORE: WooCommerce License Delivery non attivo.');
            return;
        }
        
        // WC_Order::payment_complete() lancia questo hook solo se l'ordine e'
        // ancora in uno stato pagabile, quindi un secondo webhook che arriva a
        // distanza non rifa' nulla. Restano pero' due strade per una doppia
        // esecuzione: due callback concorrenti che leggono entrambi lo stato
        // vecchio, e il payment_complete() replicato da satispay/satispay.php
        // nel tema. Senza questa guardia si tradurrebbero in codici assegnati
        // due volte e in prenotazioni doppie su TermeGest.
        if ($order->get_meta('_skianet_payment_processed')) {
            $order->add_order_note('Pagamento gia\' elaborato: assegnazione codici e sincronizzazione TermeGest saltate.');
            return;
        }

        // Il flag si salva PRIMA del lavoro: e' una prenotazione del turno, non
        // un attestato di riuscita. Un fallimento a meta' strada non si ripara
        // da solo, resta la nota d'ordine - e' la scelta voluta, un doppione su
        // TermeGest e' piu' difficile da correggere di un sync mancato.
        $order->update_meta_data('_skianet_payment_processed', current_time('mysql'));
        $order->save();

        // ✅ STEP 1: Assegna codici
        try {
            $codeAssign = new WC_LD_Code_Assignment();
            $codeAssign->assign_license_codes_to_order($order_id);
            
            $order->add_order_note('Codici di accesso alle terme assegnati.');
            
        } catch (Exception $e) {
            $order->add_order_note('ERRORE: Impossibile assegnare codici - ' . $e->getMessage());
        }

        // ✅ STEP 2: Sincronizza con TermeGest
        if (class_exists('Booking_TermeGest_Sync')) {
            $sync = Booking_TermeGest_Sync::get_instance();
            $sync->sync_order_to_termegest($order_id);
        }
    }
}