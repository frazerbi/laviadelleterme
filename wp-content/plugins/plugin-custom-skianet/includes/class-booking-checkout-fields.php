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
        
        // Salva nell'ordine
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_health_certificate'));
        
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
        <div class="health-certificate-wrapper" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border: 2px solid #0074A0; border-radius: 5px;">
            <h3 style="margin-top: 0; color: #0074A0;">
                <?php _e('Dichiarazione di Buona Salute', 'text-domain'); ?>
            </h3>
            
            <p style="font-size: 14px; line-height: 1.6; margin-bottom: 15px;">
                <?php _e('L\'accesso alle strutture termali è consentito solo a persone in buone condizioni di salute. Sono esclusi dall\'accesso coloro che presentano:', 'text-domain'); ?>
            </p>
            
            <ul style="font-size: 13px; line-height: 1.8; margin-bottom: 15px; padding-left: 20px;">
                <li><?php _e('Malattie infettive o contagiose in atto', 'text-domain'); ?></li>
                <li><?php _e('Ferite aperte, lesioni cutanee o dermatiti', 'text-domain'); ?></li>
                <li><?php _e('Disturbi cardiaci o respiratori gravi', 'text-domain'); ?></li>
                <li><?php _e('Gravidanza in corso (consultare il medico)', 'text-domain'); ?></li>
                <li><?php _e('Altre condizioni che potrebbero essere aggravate dall\'uso delle strutture termali', 'text-domain'); ?></li>
            </ul>
            
            <div style="margin-top: 15px;">
                <label for="health_certificate_accepted" style="display: flex; align-items: flex-start; cursor: pointer;">
                    <input 
                        type="checkbox" 
                        name="health_certificate_accepted" 
                        id="health_certificate_accepted" 
                        value="1" 
                        style="margin-right: 10px; margin-top: 4px; width: 18px; height: 18px; cursor: pointer;"
                    />
                    <span style="font-size: 14px; font-weight: 600; color: #333;">
                        <?php _e('Dichiaro di essere in buone condizioni di salute e di non rientrare in nessuna delle condizioni sopra elencate. Accetto la piena responsabilità per l\'utilizzo delle strutture termali.', 'text-domain'); ?>
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
     * Salva l'accettazione nell'ordine
     */
    public function save_health_certificate($order_id) {
        if (isset($_POST['health_certificate_accepted']) && $_POST['health_certificate_accepted'] == '1') {
            update_post_meta($order_id, '_health_certificate_accepted', 'yes');
            update_post_meta($order_id, '_health_certificate_date', current_time('mysql'));
            update_post_meta($order_id, '_health_certificate_ip', $_SERVER['REMOTE_ADDR']);
            
            error_log("✅ Certificato salute accettato per ordine {$order_id}");
        }
    }

    /**
     * Mostra nei dettagli ordine admin
     */
    public function display_health_certificate_in_admin($order) {
        $accepted = get_post_meta($order->get_id(), '_health_certificate_accepted', true);
        
        if ($accepted === 'yes') {
            $date = get_post_meta($order->get_id(), '_health_certificate_date', true);
            $ip = get_post_meta($order->get_id(), '_health_certificate_ip', true);
            
            ?>
            <div class="health-certificate-info" style="margin-top: 20px; padding: 10px; background: #f0f8ff; border-left: 3px solid #0074A0;">
                <h3><?php _e('Dichiarazione di Buona Salute', 'text-domain'); ?></h3>
                <p>
                    <strong><?php _e('Accettato:', 'text-domain'); ?></strong> ✅ Sì<br>
                    <strong><?php _e('Data:', 'text-domain'); ?></strong> <?php echo esc_html($date); ?><br>
                    <strong><?php _e('IP:', 'text-domain'); ?></strong> <?php echo esc_html($ip); ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Mostra nei dettagli ordine cliente
     */
    public function display_health_certificate_in_order($order) {
        $accepted = get_post_meta($order->get_id(), '_health_certificate_accepted', true);
        
        if ($accepted === 'yes') {
            ?>
            <section class="health-certificate-confirmation" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px;">
                <h2><?php _e('Dichiarazione di Buona Salute', 'text-domain'); ?></h2>
                <p style="margin: 0; color: #28a745; font-weight: 600;">
                    ✅ <?php _e('Hai confermato di essere in buone condizioni di salute.', 'text-domain'); ?>
                </p>
            </section>
            <?php
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