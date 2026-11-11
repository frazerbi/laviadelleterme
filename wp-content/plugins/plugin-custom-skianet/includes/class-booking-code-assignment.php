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
     * Ordini gia' segnalati per codici mancanti in questa richiesta: l'assegnazione
     * passa due volte (hook di stato e woocommerce_payment_complete) e la seconda
     * ritroverebbe gli stessi articoli scoperti.
     */
    private $reported_orders = array();

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

        // Prima del pagamento: un articolo senza codici liberi blocca il checkout.
        add_action('woocommerce_check_cart_items', array($this, 'check_cart_codes_stock'));

        // WC License Delivery registra il proprio hook su plugins_loaded (priorita' 10),
        // dopo questo plugin che si carica prima in ordine alfabetico: lo si sostituisce
        // su init, quando c'e' gia'.
        add_action('init', array($this, 'replace_license_delivery_hook'), 1);
    }

    /**
     * Sostituisce l'assegnazione di WC License Delivery con assign_codes_safely().
     *
     * WC_LD_Code_Assignment::assign_license_to_item() (v2.1) costruisce l'array degli
     * id in un foreach e poi fa count() su di esso: se per il prodotto non c'e' nessun
     * codice libero il foreach non gira, la variabile non esiste e su PHP 8 count(null)
     * e' un TypeError. Succede dentro WC_Order::payment_complete() - l'hook di stato
     * scatta mentre l'ordine viene salvato come pagato - quindi l'errore risale fino al
     * checkout: il cliente ha gia' pagato ma vede "Si e' verificato un errore durante
     * l'elaborazione dell'ordine", l'ordine resta senza codici e senza sincronizzazione
     * TermeGest, e Booking_Order_Status l'ha gia' portato a booked/not-booked, quindi in
     * admin sembra a posto. Riprodotto su staging2 il 2026-11-11 con un prodotto senza
     * codici liberi.
     *
     * Se il callback non si trova (plugin aggiornato, hook cambiato) resta quello
     * originale: il percorso di woocommerce_payment_complete e' protetto comunque.
     */
    public function replace_license_delivery_hook() {
        if (!class_exists('WC_LD_Code_Assignment')) {
            return;
        }

        $status = get_option('wc_ld_delivery_order_status');
        $hook   = 'woocommerce_order_status_' . (empty($status) ? 'completed' : $status);

        global $wp_filter;
        if (empty($wp_filter[$hook])) {
            return;
        }

        foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $function = $callback['function'];
                if (is_array($function)
                    && $function[0] instanceof WC_LD_Code_Assignment
                    && 'assign_license_codes_to_order' === $function[1]) {
                    remove_action($hook, $function, $priority);
                    add_action($hook, array($this, 'assign_codes_safely'), $priority, 1);
                }
            }
        }
    }

    /**
     * Ferma il checkout se un articolo a codici non ne ha abbastanza di liberi.
     *
     * woocommerce_check_cart_items gira sulla pagina carrello, sul checkout e dentro
     * WC_Checkout::process_checkout() prima che l'ordine venga creato - anche per i
     * pagamenti express di Stripe, che passano da li'. Un errore qui significa
     * nessun ordine e nessun addebito. La rete di sicurezza dopo il pagamento
     * (assign_license_codes) resta per l'unico caso che questo controllo non copre:
     * due clienti che pagano insieme l'ultimo codice.
     */
    public function check_cart_codes_stock() {
        if (!function_exists('WC') || !WC()->cart || !class_exists('WC_LD_Code_Assignment')) {
            return;
        }

        // Piu' righe dello stesso prodotto (stesso biglietto per due date diverse)
        // pescano dallo stesso blocco di codici: si somma per prodotto.
        $needed = array();
        $names  = array();
        foreach (WC()->cart->get_cart() as $cart_item) {
            $is_license_code = get_post_meta($cart_item['product_id'], '_wc_ld_license_code', true);
            if (empty($is_license_code) || 'no' === $is_license_code) {
                continue;
            }
            $product_id = !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : $cart_item['product_id'];
            $needed[$product_id] = ($needed[$product_id] ?? 0) + (int) $cart_item['quantity'];
            $names[$product_id]  = $cart_item['data']->get_name();
        }

        foreach ($needed as $product_id => $qty) {
            $free = $this->count_free_codes($product_id);
            if ($free >= $qty) {
                continue;
            }
            $message = 0 === $free
                ? sprintf('«%s» al momento non è disponibile: rimuovilo dal carrello per completare l\'ordine.', $names[$product_id])
                : sprintf('«%s»: ne restano solo %d disponibili, riduci la quantità per completare l\'ordine.', $names[$product_id], $free);
            wc_add_notice($message, 'error');
        }
    }

    /**
     * Sostituto del callback di stato di WC License Delivery.
     */
    public function assign_codes_safely($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || !class_exists('WC_LD_Code_Assignment')) {
            return;
        }

        $this->assign_license_codes($order);
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

        // ✅ STEP 1: Assegna codici (di solito li ha gia' assegnati l'hook di stato:
        // qui si ripassano solo gli articoli rimasti scoperti)
        $missing = $this->assign_license_codes($order);
        if (empty($missing)) {
            $order->add_order_note('Codici di accesso alle terme assegnati.');
        }

        // ✅ STEP 2: Sincronizza con TermeGest - anche con codici mancanti: gli altri
        // articoli vanno inviati, e per quelli scoperti il sync lascia la sua nota.
        if (class_exists('Booking_TermeGest_Sync')) {
            $sync = Booking_TermeGest_Sync::get_instance();
            $sync->sync_order_to_termegest($order_id);
        }
    }

    /**
     * Stesso ciclo di WC_LD_Code_Assignment::assign_license_codes_to_order(), con
     * due differenze: un articolo senza abbastanza codici liberi viene saltato
     * invece di mandare in crash la richiesta, e ogni assegnazione e' protetta da
     * un catch su Throwable (un TypeError e' un Error, non una Exception).
     *
     * @return string[] Descrizione degli articoli rimasti senza codici.
     */
    private function assign_license_codes($order) {
        $assigner = new WC_LD_Code_Assignment();
        $missing  = array();

        foreach ($order->get_items() as $item_id => $item) {
            // Stessi due controlli di WC License Delivery: articolo gia' servito,
            // prodotto non gestito a codici.
            if (!empty($item['license_code_ids'])) {
                continue;
            }
            $is_license_code = get_post_meta($item->get_product_id(), '_wc_ld_license_code', true);
            if (empty($is_license_code) || 'no' === $is_license_code) {
                continue;
            }

            $product_id = $item->get_variation_id() ?: $item->get_product_id();
            $qty        = (int) $item->get_quantity();
            $free       = $this->count_free_codes($product_id);

            if ($free < $qty) {
                $missing[] = sprintf('%s (servono %d, liberi %d)', $item->get_name(), $qty, $free);
                continue;
            }

            try {
                $assigner->assign_license_to_item($item_id, $item, $order, $order->get_id());
            } catch (\Throwable $e) {
                // Due ordini concorrenti sull'ultimo codice: il conteggio era giusto,
                // la SELECT dentro WC_LD non trova piu' niente.
                $missing[] = sprintf('%s (%s)', $item->get_name(), $e->getMessage());
            }
        }

        if (!empty($missing)) {
            $this->report_missing_codes($order, $missing);
        }

        return $missing;
    }

    /**
     * Stessa condizione della SELECT di WC_LD_Code_Assignment::assign_license_to_item().
     */
    private function count_free_codes($product_id) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wc_ld_license_codes
             WHERE product_id = %d AND license_code1 <> '' AND license_status = '0'",
            $product_id
        ));
    }

    /**
     * Nota d'ordine, log ed email all'amministratore: lo stato dell'ordine resta
     * booked/not-booked come gli altri, quindi senza un avviso esplicito nessuno
     * se ne accorgerebbe.
     */
    private function report_missing_codes($order, array $missing) {
        $order_id = $order->get_id();
        if (isset($this->reported_orders[$order_id])) {
            return;
        }
        $this->reported_orders[$order_id] = true;

        $list = implode('; ', $missing);

        $order->add_order_note(
            'ERRORE: codici esauriti, non assegnati per: ' . $list
            . '. Caricare i codici in License Delivery e assegnarli a mano; per questi articoli TermeGest non e\' stato aggiornato.'
        );

        if (function_exists('wc_get_logger')) {
            wc_get_logger()->error(
                sprintf('Ordine #%d: codici esauriti per %s', $order_id, $list),
                array('source' => 'skianet-codici')
            );
        }

        wp_mail(
            get_option('admin_email'),
            sprintf('Ordine #%d pagato senza codici: codici esauriti', $order_id),
            sprintf(
                "L'ordine #%d e' stato pagato ma alcuni articoli non hanno ricevuto i codici perche' sono esauriti:\n\n%s\n\n"
                . "Caricare i codici in WooCommerce License Delivery, assegnarli all'ordine e inviare a TermeGest.\n\n%s",
                $order_id,
                implode("\n", $missing),
                $order->get_edit_order_url()
            )
        );
    }
}
