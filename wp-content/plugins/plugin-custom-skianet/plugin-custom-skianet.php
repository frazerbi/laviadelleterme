<?php

declare(strict_types=1);

/*
 * Plugin Name: Custom Skianet Plugin
 * Description: Custom Skianet code for TermeGest.
 * Author: Francesco Zerbinato
 * Version: 2.0
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * Text Domain: skianet-custom
 * Domain Path: /languages
 */

if (! \defined('ABSPATH')) {
    exit();
}

const PLUGIN_SKIANET_FILE = __FILE__;
const PLUGIN_SKIANET_VERSION = '2.0.6';
const PLUGIN_SKIANET_TEXT_DOMAIN = 'skianet-custom';

\define('PLUGIN_SKIANET_PATH', untrailingslashit(plugin_dir_path(PLUGIN_SKIANET_FILE)));
\define('PLUGIN_SKIANET_URL', untrailingslashit(plugin_dir_url(PLUGIN_SKIANET_FILE)));

// Profilo del sito (laviadelleterme / termeshop), scelto con SKIANET_SITE in wp-config.
require_once PLUGIN_SKIANET_PATH . '/includes/site-config.php';

if ( ! defined( 'LOGO_TERME_PATH' ) ) {
    define( 'LOGO_TERME_PATH', PLUGIN_SKIANET_PATH . '/assets/img/' . skianet_site()['header_image'] );
}
if ( ! defined( 'LOGO_TERME_FOOTER_PATH' ) ) {
    define( 'LOGO_TERME_FOOTER_PATH', PLUGIN_SKIANET_PATH . '/assets/img/footer-coupon.JPG' );
}

// Ambiente: decide quale mappa product ID usa Booking_Redirect. Il default e'
// 'staging' (staging2 non lo definisce altrove); la produzione dichiara
// 'production' nel proprio wp-config, caricato prima, che qui vince grazie al guard.
// Il file si carica quindi uguale in entrambi gli ambienti.
if (! \defined('WP_ENVIRONMENT_TYPE')) {
    \define('WP_ENVIRONMENT_TYPE', 'staging');
}

require_once PLUGIN_SKIANET_PATH.'/vendor/autoload.php';

require_once PLUGIN_SKIANET_PATH . '/includes/class-termegest-api.php';
require_once PLUGIN_SKIANET_PATH . '/includes/termegest-api-functions.php';
require_once PLUGIN_SKIANET_PATH . '/includes/class-booking-handler.php';
require_once PLUGIN_SKIANET_PATH . '/includes/class-termegest-encryption.php';
require_once PLUGIN_SKIANET_PATH . '/includes/class-availability-checker.php';
require_once PLUGIN_SKIANET_PATH . '/includes/class-booking-redirect.php';
require_once PLUGIN_SKIANET_PATH . '/includes/class-booking-cart-handler.php';
require_once PLUGIN_SKIANET_PATH . '/includes/class-booking-code-assignment.php';
require_once PLUGIN_SKIANET_PATH . '/includes/class-booking-termegest-sync.php';
require_once PLUGIN_SKIANET_PATH . '/includes/class-booking-order-status.php';
require_once PLUGIN_SKIANET_PATH . '/includes/class-booking-email-notification.php'; 
require_once PLUGIN_SKIANET_PATH . '/includes/class-booking-nonbooking-email.php';
require_once PLUGIN_SKIANET_PATH . '/includes/class-booking-checkout-fields.php';
require_once PLUGIN_SKIANET_PATH . '/includes/class-booking-only-handler.php';


// Hook activation - registra il cron (solo dove ci sono le prenotazioni)
register_activation_hook(__FILE__, function() {
    if (skianet_site()['bookings'] && !wp_next_scheduled('termegest_check_availability')) {
        wp_schedule_event(time(), 'daily', 'termegest_check_availability');
    }
});

// Hook deactivation - rimuovi il cron
register_deactivation_hook(__FILE__, array('Availability_Checker', 'deactivate'));

add_action('plugins_loaded', 'init_booking_handler_plugin');
function init_booking_handler_plugin() {

    // Prenotazioni: form, redirect della PDP, cron di disponibilita', dati di
    // prenotazione nel carrello, dichiarazione sanitaria, form "solo prenotazione".
    // Su un sito solo coupon (profilo con bookings => false) non si istanziano:
    // i file restano caricati perche' il resto usa i loro metodi statici
    // (Booking_Cart_Handler::get_item_license_codes(), Booking_Handler::get_ticket_types()).
    if (skianet_site()['bookings']) {
        Booking_Handler::get_instance();
        Booking_Redirect::get_instance();
        Availability_Checker::get_instance();
        Booking_Cart_Handler::get_instance();
        Booking_Checkout_Fields::get_instance();
        Booking_Only_Handler::get_instance();
    } elseif (wp_next_scheduled('termegest_check_availability')) {
        // Evento rimasto da un'attivazione precedente: senza Availability_Checker
        // girerebbe a vuoto ogni giorno.
        Availability_Checker::deactivate();
    }

    // Coupon e codici: servono su tutti i siti.
    Booking_Code_Assignment::get_instance();
    Booking_Termegest_Sync::get_instance();
    Booking_Order_Status::get_instance();
    Booking_Email_Notification::get_instance();
    Booking_Nonbooking_Email::get_instance();
}

add_action('init', 'skianet_plugin_load_textdomain', \PHP_INT_MAX);
function skianet_plugin_load_textdomain(): void
{
    load_plugin_textdomain(
        PLUGIN_SKIANET_TEXT_DOMAIN,
        false,
        \dirname(plugin_basename(PLUGIN_SKIANET_FILE)).'/languages'
    );
}
