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
        
        // Valida che sia stato accettato
        add_action('woocommerce_checkout_process', array($this, 'validate_health_certificate'));
        
        // Salva nei metadati degli order items (non dell'ordine)
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_health_certificate_to_item'), 10, 4);

        // Mostra nei dettagli ordine (admin e email)
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_health_certificate_in_admin'));
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_health_certificate_in_order'));
    }

    /**
     * Mostra checkbox certificato salute nel checkout
     */
    public function display_health_certificate_field() {
        // ✅ Mostra solo se il carrello contiene prodotti con prenotazione
        if (!$this->cart_has_booking()) {
            return;
        }

        ?>
        <div class="health-certificate-content" style="max-height: 25vh; overflow-y: auto; padding: 15px; font-family: 'Muli', Sans-serif; font-size: 1rem; line-height: 1.7; color: #333; background: #fff;">

            <h3 style="margin-top: 0; color: #0074A0;">
                <?php _e('Dichiarazione di Idoneità', 'text-domain'); ?>
            </h3>
            
            <div style="font-size: 13px; line-height: 1.7; margin-bottom: 20px; color: #333;">
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
     * Valida che il certificato sia stato accettato
     */
    public function validate_health_certificate() {
        // ✅ Valida solo se il carrello contiene prodotti con prenotazione
        if (!$this->cart_has_booking()) {
            return;
        }

        if (!isset($_POST['health_certificate_accepted']) || $_POST['health_certificate_accepted'] != '1') {
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
        // ✅ Salva solo per prodotti con prenotazione
        if (!isset($values['booking_id'])) {
            return;
        }

        // Verifica che sia stato accettato
        if (isset($_POST['health_certificate_accepted']) && $_POST['health_certificate_accepted'] == '1') {
            // ✅ Salva nei metadati dell'item
            $item->add_meta_data('_health_certificate_accepted', 'yes', true);
            $item->add_meta_data('_health_certificate_date', current_time('mysql'), true);
            $item->add_meta_data('_health_certificate_ip', $_SERVER['REMOTE_ADDR'], true);
            
            // Campo visibile per l'utente (opzionale)
            $item->add_meta_data('Certificato Salute', 'Accettato ✅', true);
            
            error_log("✅ Certificato salute salvato per item {$item->get_id()} - Booking: {$values['booking_id']}");
        }
    }

    /**
     * Mostra nei dettagli ordine admin
     */
    public function display_health_certificate_in_admin($order) {
        $has_certificate = false;
        
        foreach ($order->get_items() as $item) {
            $accepted = $item->get_meta('_health_certificate_accepted');
            
            if ($accepted === 'yes') {
                $has_certificate = true;
                break;
            }
        }
        
        if (!$has_certificate) {
            return;
        }
        
        ?>
        <div class="health-certificate-info" style="margin-top: 20px; padding: 10px; background: #f0f8ff; border-left: 3px solid #0074A0;">
            <h3><?php _e('Dichiarazione di Buona Salute', 'text-domain'); ?></h3>
            <?php
            foreach ($order->get_items() as $item) {
                $accepted = $item->get_meta('_health_certificate_accepted');
                
                if ($accepted === 'yes') {
                    $date = $item->get_meta('_health_certificate_date');
                    $ip = $item->get_meta('_health_certificate_ip');
                    $booking_id = $item->get_meta('_booking_id');
                    
                    ?>
                    <div style="margin-bottom: 10px; padding: 8px; background: white; border-radius: 3px;">
                        <p style="margin: 0;">
                            <strong><?php echo esc_html($item->get_name()); ?></strong><br>
                            <small>
                                <strong><?php _e('Booking ID:', 'text-domain'); ?></strong> <?php echo esc_html($booking_id); ?><br>
                                <strong><?php _e('Accettato:', 'text-domain'); ?></strong> ✅ Sì<br>
                                <strong><?php _e('Data:', 'text-domain'); ?></strong> <?php echo esc_html($date); ?><br>
                                <strong><?php _e('IP:', 'text-domain'); ?></strong> <?php echo esc_html($ip); ?>
                            </small>
                        </p>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
        <?php
    }

    /**
     * Mostra nei dettagli ordine cliente
     */
    public function display_health_certificate_in_order($order) {
        $has_certificate = false;
        
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_health_certificate_accepted') === 'yes') {
                $has_certificate = true;
                break;
            }
        }
        
        if (!$has_certificate) {
            return;
        }
        
        ?>
        <section class="health-certificate-confirmation" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px;">
            <h2><?php _e('Dichiarazione di Buona Salute', 'text-domain'); ?></h2>
            <p style="margin: 0; color: #28a745; font-weight: 600;">
                ✅ <?php _e('Hai confermato di essere in buone condizioni di salute per i prodotti prenotati.', 'text-domain'); ?>
            </p>
        </section>
        <?php
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