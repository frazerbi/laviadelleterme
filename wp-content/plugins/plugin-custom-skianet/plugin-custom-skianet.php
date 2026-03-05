<?php

declare(strict_types=1);

/*
 * Plugin Name: Custom Skianet Plugin
 * Description: Custom Skianet code for TermeGest.
 * Text Domain: skianet-custom
 * Domain Path: /languages
 * Version: 1.0
 */

if (! \defined('ABSPATH')) {
    exit();
}

const PLUGIN_SKIANET_FILE = __FILE__;
const PLUGIN_SKIANET_VERSION = '1.2.19';
const PLUGIN_SKIANET_TEXT_DOMAIN = 'skianet-custom';

\define('PLUGIN_SKIANET_PATH', untrailingslashit(plugin_dir_path(PLUGIN_SKIANET_FILE)));
\define('PLUGIN_SKIANET_URL', untrailingslashit(plugin_dir_url(PLUGIN_SKIANET_FILE)));

if ( ! defined( 'LOGO_TERME_PATH' ) ) {
    define( 'LOGO_TERME_PATH', PLUGIN_SKIANET_PATH . '/assets/img/header-la-via-delle-terme.jpg' );
}
if ( ! defined( 'LOGO_TERME_FOOTER_PATH' ) ) {
    define( 'LOGO_TERME_FOOTER_PATH', PLUGIN_SKIANET_PATH . '/assets/img/footer-coupon.JPG' );
}

add_filter('auto_update_core', '__return_true');
add_filter('automatic_updates_is_vcs_checkout', '__return_false', 1);
add_filter('auto_update_plugin', '__return_true');
add_filter('auto_update_theme', '__return_true');
add_filter('auto_update_translation', '__return_true');

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

require_once PLUGIN_SKIANET_PATH . '/vendor/fpdf/fpdf.php';


// Hook activation - registra il cron
register_activation_hook(__FILE__, function() {
    if (!wp_next_scheduled('termegest_check_availability')) {
        wp_schedule_event(time(), 'daily', 'termegest_check_availability');
    }
});

// Hook deactivation - rimuovi il cron
register_deactivation_hook(__FILE__, array('Availability_Checker', 'deactivate'));

// Inizializza la classe Booking Handler
add_action('plugins_loaded', 'init_booking_handler_plugin');
function init_booking_handler_plugin() {
    
    if (class_exists('Booking_Handler')) {
        Booking_Handler::get_instance();
    }

    if (class_exists('Booking_Redirect')) {
        Booking_Redirect::get_instance();
    }

    if (class_exists('Availability_Checker')) {
        Availability_Checker::get_instance();
    }

    if (class_exists('Booking_Cart_Handler')) {
        Booking_Cart_Handler::get_instance();
    }

    if (class_exists('Booking_Code_Assignment')) {
        Booking_Code_Assignment::get_instance();
    }
    
    if (class_exists('Booking_Termegest_Sync')) {
        Booking_Termegest_Sync::get_instance();
    }
    
    if (class_exists('Booking_Order_Status')) {
        Booking_Order_Status::get_instance();
    }

    if (class_exists('Booking_Email_Notification')) {
        Booking_Email_Notification::get_instance();
    }
    
    if (class_exists('Booking_Nonbooking_Email')) {
        Booking_Nonbooking_Email::get_instance();
    }

    if (class_exists('Booking_Checkout_Fields')) {
        Booking_Checkout_Fields::get_instance();
    }

    if (class_exists('Booking_Only_Handler')) {
        Booking_Only_Handler::get_instance();
    }
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
