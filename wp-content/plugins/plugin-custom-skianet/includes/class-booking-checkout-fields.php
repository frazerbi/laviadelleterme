<?php
/**
 * Gestisce campi personalizzati e consensi nel checkout
 */

if (!defined('ABSPATH')) {
    exit;
}

class Booking_Checkout_Fields {

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
     * Inizializza hooks
     */
    private function init_hooks() {
        // Mostra checkbox certificato salute nel checkout
        add_action('woocommerce_review_order_before_submit', array($this, 'display_health_certificate_field'), 10);

        // Valida che sia stato accettato (checkout standard)
        add_action('woocommerce_checkout_process', array($this, 'validate_health_certificate'));

        // Salva nei metadati degli order items
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_health_certificate_to_item'), 10, 4);

        // Salva accettazione anche a livello ordine
        add_action('woocommerce_checkout_order_created', array($this, 'save_health_certificate_to_order'), 10, 1);

        // Safety net: valida dopo creazione ordine (cattura anche express payments)
        add_action('woocommerce_checkout_order_processed', array($this, 'validate_order_health_certificate'), 10, 3);

        // AJAX per salvare accettazione in sessione WC (per express payments)
        add_action('wp_ajax_save_health_certificate_session', array($this, 'ajax_save_health_certificate_session'));
        add_action('wp_ajax_nopriv_save_health_certificate_session', array($this, 'ajax_save_health_certificate_session'));

        // Inline JS per bloccare express payments finché checkbox non è spuntato
        add_action('woocommerce_review_order_before_submit', array($this, 'output_express_payment_blocker_js'), 20);
    }

    /**
     * Mostra checkbox certificato salute nel checkout
     */
    public function display_health_certificate_field() {
        // Mostra solo se il carrello contiene prodotti con prenotazione
        if (!$this->cart_has_booking()) {
            return;
        }

        ?>
        <div class="health-certificate-content" style="max-height: 25vh; overflow:scroll; margin-bottom: 2rem; padding: 1.6rem; background: var(--e-global-color-29fcec7);">

            <h5 style="margin-top: 0; margin-bottom: 1.6rem; font-weight: bold; text-transform: uppercase;">
                <?php _e('Dichiarazione di Idoneità', 'text-domain'); ?>
            </h5>

            <div style="font-size: 1rem;">
                <p style="margin-bottom: 15px;">
                    <strong><?php _e('Il sottoscritto/la sottoscritta dichiara sotto la propria responsabilità di:', 'text-domain'); ?></strong>
                </p>

                <ol style="padding-left: 20px; margin-bottom: 15px;">
                    <li style="margin-bottom: 12px;">
                        <?php _e('trovarsi in condizioni psicofisiche idonee a usufruire dei trattamenti di benessere offerti dalle Strutture compresi bagno turco, sauna, vasche idromassaggio e, in particolare:', 'text-domain'); ?>
                        <ul style="margin-top: 8px; padding-left: 20px; list-style-type: disc;">
                            <li style="margin-bottom: 6px;"><?php _e('di essere a conoscenza che l\'uso di sauna e bagno turco non sono idonei a coloro che hanno disturbi di pressione arteriosa e presenza di patologie a carico del sistema venoso superficiale e profondo;', 'text-domain'); ?></li>
                            <li style="margin-bottom: 6px;"><?php _e('non accusare sintomi quali: febbre, tosse, difficoltà respiratorie;', 'text-domain'); ?></li>
                            <li style="margin-bottom: 6px;"><?php _e('di godere di sana e robusta costituzione e di essersi sottoposto di recente a visita medica per accertare la propria idoneità fisica;', 'text-domain'); ?></li>
                        </ul>
                    </li>

                    <li style="margin-bottom: 12px;">
                        <?php _e('dover informare correttamente il personale delle Strutture circa eventuali patologie, allergie, condizioni mediche, stati di gravidanza, terapie in corso, interventi chirurgici recenti o altre condizioni che possano costituire controindicazione, anche temporanea, ai trattamenti offerti dalle Strutture;', 'text-domain'); ?>
                    </li>

                    <li style="margin-bottom: 12px;">
                        <?php _e('essere consapevole che i trattamenti benessere hanno esclusiva finalità di benessere e rilassamento e non sostituiscono in alcun modo prestazioni mediche o terapeutiche;', 'text-domain'); ?>
                    </li>

                    <li style="margin-bottom: 12px;">
                        <?php _e('impegnarsi a comunicare tempestivamente alle Strutture qualsiasi variazione del proprio stato di salute che possa insorgere prima o durante l\'erogazione delle prestazioni dello Stabilimento;', 'text-domain'); ?>
                    </li>

                    <li style="margin-bottom: 12px;">
                        <?php _e('essere a conoscenza dell\'obbligo di indossare ciabattine durante il soggiorno nelle Strutture;', 'text-domain'); ?>
                    </li>

                    <li style="margin-bottom: 12px;">
                        <?php _e('esonerare da qualsivoglia responsabilità le Strutture, i suoi dipendenti e collaboratori per eventuali danni, malesseri o conseguenze derivanti da informazioni incomplete, inesatte o omesse sul proprio stato di salute; dal mancato rispetto delle indicazioni fornite dal personale delle Strutture da condizioni personali non risultanti dalla presente dichiarazione o non conosciute.', 'text-domain'); ?>
                    </li>
                </ol>
            </div>

            <!-- Checkbox Dichiarazione -->
            <div style="padding: 12px; background: #fff; border: 1px solid #dee2e6; border-radius: 4px;">
                <label for="health_certificate_accepted" style="display: flex; align-items: flex-start; cursor: pointer;">
                    <input
                        type="checkbox"
                        name="health_certificate_accepted"
                        id="health_certificate_accepted"
                        value="1"
                        style="margin-right: 10px; margin-top: 4px; width: 18px; height: 18px; cursor: pointer; flex-shrink: 0;"
                    />
                    <span style="font-size: 14px; font-weight: 600; color: #333;">
                        <?php _e('Confermo di dichiarare e accettare quanto sopra.', 'text-domain'); ?>
                        <span style="color: #d9534f;">*</span>
                    </span>
                </label>
            </div>
        </div>

        <style>
            .health-certificate-wrapper input[type="checkbox"]:focus {
                outline: 2px solid #0074A0;
                outline-offset: 2px;
            }
            .health-certificate-wrapper label:hover span {
                color: #0074A0;
            }
        </style>
        <?php
    }

    /**
     * JS: blocca express payments finché il checkbox non è spuntato + salva in sessione WC via AJAX
     */
    public function output_express_payment_blocker_js() {
        if (!$this->cart_has_booking()) {
            return;
        }
        ?>
        <script>
        (function() {
            'use strict';

            // Selettori dei contenitori express payment (Stripe, WC Payments, generici)
            var expressSelectors = [
                '#wc-stripe-payment-request-wrapper',
                '.wcpay-payment-request-wrapper',
                '#wcpay-express-checkout-wrapper',
                '#wc-stripe-express-checkout-wrapper',
                '.wc-stripe-payment-request-wrapper',
                '#payment-request-button'
            ];

            function getExpressContainers() {
                var containers = [];
                expressSelectors.forEach(function(sel) {
                    document.querySelectorAll(sel).forEach(function(el) {
                        containers.push(el);
                    });
                });
                return containers;
            }

            function toggleExpressPayments(enabled) {
                var containers = getExpressContainers();
                containers.forEach(function(el) {
                    if (enabled) {
                        el.style.pointerEvents = '';
                        el.style.opacity = '';
                        el.style.position = '';
                        // Rimuovi overlay
                        var overlay = el.querySelector('.health-cert-overlay');
                        if (overlay) overlay.remove();
                    } else {
                        el.style.pointerEvents = 'none';
                        el.style.opacity = '0.4';
                        el.style.position = 'relative';
                        // Aggiungi overlay con messaggio
                        if (!el.querySelector('.health-cert-overlay')) {
                            var overlay = document.createElement('div');
                            overlay.className = 'health-cert-overlay';
                            overlay.style.cssText = 'position:absolute;top:0;left:0;width:100%;height:100%;z-index:10;cursor:not-allowed;';
                            overlay.title = 'Accetta la dichiarazione di idoneità per procedere';
                            el.appendChild(overlay);
                        }
                    }
                });
            }

            function saveToSession(accepted) {
                var formData = new FormData();
                formData.append('action', 'save_health_certificate_session');
                formData.append('accepted', accepted ? '1' : '0');
                formData.append('nonce', '<?php echo wp_create_nonce('health_cert_session'); ?>');
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', body: formData });
            }

            function init() {
                var checkbox = document.getElementById('health_certificate_accepted');
                if (!checkbox) return;

                // Stato iniziale: bloccato
                toggleExpressPayments(false);

                checkbox.addEventListener('change', function() {
                    toggleExpressPayments(this.checked);
                    saveToSession(this.checked);
                });

                // Osserva DOM per express buttons caricati dopo (lazy loaded)
                var observer = new MutationObserver(function() {
                    if (!checkbox.checked) {
                        toggleExpressPayments(false);
                    }
                });
                var paymentArea = document.getElementById('payment') || document.body;
                observer.observe(paymentArea, { childList: true, subtree: true });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
        </script>
        <?php
    }

    /**
     * AJAX: salva accettazione health certificate nella sessione WC
     */
    public function ajax_save_health_certificate_session() {
        check_ajax_referer('health_cert_session', 'nonce');

        $accepted = isset($_POST['accepted']) && $_POST['accepted'] === '1';

        if ($accepted) {
            WC()->session->set('health_certificate_accepted', 'yes');
            WC()->session->set('health_certificate_date', current_time('mysql'));
            WC()->session->set('health_certificate_ip', $_SERVER['REMOTE_ADDR'] ?? '');
        } else {
            WC()->session->set('health_certificate_accepted', 'no');
        }

        wp_send_json_success();
    }

    /**
     * Valida che il certificato sia stato accettato (checkout standard)
     */
    public function validate_health_certificate() {
        if (!$this->cart_has_booking()) {
            return;
        }

        // Controlla POST (checkout standard) oppure sessione WC (express payments)
        $accepted_post = isset($_POST['health_certificate_accepted']) && $_POST['health_certificate_accepted'] == '1';
        $accepted_session = WC()->session && WC()->session->get('health_certificate_accepted') === 'yes';

        if (!$accepted_post && !$accepted_session) {
            wc_add_notice(
                __('Devi accettare la dichiarazione di buona salute per completare l\'ordine.', 'text-domain'),
                'error'
            );
        }
    }

    /**
     * Salva l'accettazione nei metadati degli order items con prenotazione
     */
    public function save_health_certificate_to_item($item, $cart_item_key, $values, $order) {
        if (!isset($values['booking_id'])) {
            return;
        }

        $accepted_post = isset($_POST['health_certificate_accepted']) && $_POST['health_certificate_accepted'] == '1';
        $accepted_session = WC()->session && WC()->session->get('health_certificate_accepted') === 'yes';

        if ($accepted_post || $accepted_session) {
            $cert_date = $accepted_session ? WC()->session->get('health_certificate_date') : current_time('mysql');
            $cert_ip = $accepted_session ? WC()->session->get('health_certificate_ip') : ($_SERVER['REMOTE_ADDR'] ?? '');

            $item->add_meta_data('_health_certificate_accepted', 'yes', true);
            $item->add_meta_data('_health_certificate_date', $cert_date, true);
            $item->add_meta_data('_health_certificate_ip', $cert_ip, true);
            $item->add_meta_data('Certificato Salute', 'Accettato', true);

            error_log("Certificato salute salvato per item - Booking: {$values['booking_id']}");
        }
    }

    /**
     * Salva accettazione certificato a livello ordine
     */
    public function save_health_certificate_to_order($order) {
        $accepted_post = isset($_POST['health_certificate_accepted']) && $_POST['health_certificate_accepted'] == '1';
        $accepted_session = WC()->session && WC()->session->get('health_certificate_accepted') === 'yes';

        if ($accepted_post || $accepted_session) {
            $cert_date = $accepted_session ? WC()->session->get('health_certificate_date') : current_time('mysql');
            $cert_ip = $accepted_session ? WC()->session->get('health_certificate_ip') : ($_SERVER['REMOTE_ADDR'] ?? '');

            $order->update_meta_data('_health_certificate_accepted', 'yes');
            $order->update_meta_data('_health_certificate_date', $cert_date);
            $order->update_meta_data('_health_certificate_ip', $cert_ip);
            $order->save();

            // Pulisci sessione
            if (WC()->session) {
                WC()->session->set('health_certificate_accepted', null);
                WC()->session->set('health_certificate_date', null);
                WC()->session->set('health_certificate_ip', null);
            }
        }
    }

    /**
     * Safety net: se ordine con booking non ha certificato, metti in on-hold
     */
    public function validate_order_health_certificate($order_id, $posted_data, $order) {
        $has_booking = false;
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_booking_id')) {
                $has_booking = true;
                break;
            }
        }

        if (!$has_booking) {
            return;
        }

        if ($order->get_meta('_health_certificate_accepted') !== 'yes') {
            $order->set_status('on-hold');
            $order->add_order_note(
                'Ordine in sospeso: dichiarazione di idoneità sanitaria non accettata (possibile pagamento express).'
            );
            $order->save();
            error_log("Order {$order_id}: health certificate not accepted - set to on-hold");
        }
    }

    /**
     * Verifica se il carrello contiene prodotti con prenotazione
     */
    private function cart_has_booking() {
        if (!WC()->cart) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['booking_id'])) {
                return true;
            }
        }

        return false;
    }
}
