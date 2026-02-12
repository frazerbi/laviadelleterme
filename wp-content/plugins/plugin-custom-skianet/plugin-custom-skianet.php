<?php

declare(strict_types=1);

/*
 * Plugin Name: Custom Skianet Plugin
 * Description: Custom Skianet code for TermeGest.
 * Text Domain: skianet-custom
 * Domain Path: /languages
 * Version: 1.0
 */

use ElementorPro\Modules\Forms\Registrars\Form_Actions_Registrar;

if (! \defined('ABSPATH')) {
    exit();
}

const PLUGIN_SKIANET_FILE = __FILE__;
const PLUGIN_SKIANET_VERSION = '1.2.19';
const PLUGIN_SKIANET_FULLCAL_VERSION = '6.1.15';
const PLUGIN_SKIANET_SELECT2_VERSION = '4.1.0-rc.0';
const PLUGIN_SKIANET_TEXT_DOMAIN = 'skianet-custom';
const SKIANET_ACTION_GET_DISPONIBILITA = 'skianet_calendar_get_disponibilita';
const SKIANET_ACTION_GET_BOOK_DIALOG = 'skianet_calendar_get_book_dialog';
const SKIANET_DISPONIBILITA_ELEMENT_ID = 'skn-disponibilita-calendar';
const SKIANET_DISPONIBILITA_CONTAIN_ID = SKIANET_DISPONIBILITA_ELEMENT_ID.'-container';
const SKIANET_ACTION_BOOKING_ADD_CART = 'skianet_booking_add_cart';

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

// require_once PLUGIN_SKIANET_PATH.'/components/skianet-custom-definitions.php';
// require_once PLUGIN_SKIANET_PATH.'/components/skianet-termegest-soap.php';
// require_once PLUGIN_SKIANET_PATH.'/components/skianet-shortcode-location-select.php';
// require_once PLUGIN_SKIANET_PATH.'/components/skianet-shortcode-disponibilita.php';
// require_once PLUGIN_SKIANET_PATH.'/components/skianet-shortcode-booking.php';
// require_once PLUGIN_SKIANET_PATH.'/components/skianet-actions-booking-form.php';
// require_once PLUGIN_SKIANET_PATH.'/components/skianet-termegest-custom-fields.php';
// require_once PLUGIN_SKIANET_PATH.'/components/skianet-termegest-prenotazione.php';
// require_once PLUGIN_SKIANET_PATH.'/components/skianet-custom-my-account.php';
// require_once PLUGIN_SKIANET_PATH.'/components/skianet-email-failed-prenotazione.php';

require_once PLUGIN_SKIANET_PATH.'/vendor/autoload.php';

require_once PLUGIN_SKIANET_PATH . '/includes/class-termegest-api.php';
require_once PLUGIN_SKIANET_PATH . '/includes/termegest-api-functions.php';

// Carica la classe
require_once PLUGIN_SKIANET_PATH . '/includes/class-booking-handler.php';
require_once PLUGIN_SKIANET_PATH . '/includes/class-termegest-encryption.php';
require_once PLUGIN_SKIANET_PATH . '/includes/class-availability-checker.php';
require_once PLUGIN_SKIANET_PATH . '/includes/class-booking-redirect.php';
require_once PLUGIN_SKIANET_PATH . '/includes/class-booking-cart-handler.php';
require_once PLUGIN_SKIANET_PATH . '/includes/class-booking-code-assignment.php';
require_once PLUGIN_SKIANET_PATH . '/includes/class-booking-termegest-sync.php';
// require_once PLUGIN_SKIANET_PATH . '/includes/class-booking-order-handler.php';
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

add_action('wp_loaded', 'skianet_plugin_loaded', \PHP_INT_MAX);
function skianet_plugin_loaded(): void
{
    if (\function_exists('acf_pro_update_license')) {
        $start = DateTime::createFromFormat('Y-m-d H:i:s', '2021-01-01 00:00:00')
            ->getTimestamp();
        $future = time() + (120 * MONTH_IN_SECONDS);

        acf_pro_update_license('active');
        acf_pro_update_license_status([
            'status' => 'active',
            'created' => $start,
            'expiry' => $future,
            'name' => 'Skianet',
            'lifetime' => true,
            'refunded' => false,
            'view_licenses_url' => '',
            'manage_subscription_url' => '',
            'error_msg' => '',
            'next_check' => $future,
        ]);
        acf_updates()->refresh_plugins_transient();
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

// add_action('wp_enqueue_scripts', 'skianet_termegest_calendar_enqueue_scripts', \PHP_INT_MAX);
function skianet_termegest_calendar_enqueue_scripts(): void
{
    skianet_termegest_calendar_check_dependencies();

    $suffix = \defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

    wp_enqueue_style(
        'skianet-select2-style',
        'https://cdn.jsdelivr.net/npm/select2/dist/css/select2'.$suffix.'.css',
        [],
        PLUGIN_SKIANET_SELECT2_VERSION
    );
    wp_enqueue_style(
        'skianet-style',
        PLUGIN_SKIANET_URL.'/assets/css/skianet-style'.$suffix.'.css',
        ['skianet-select2-style'],
        PLUGIN_SKIANET_VERSION
    );

    wp_enqueue_script(
        'skianet-fullcalendar-script',
        'https://cdn.jsdelivr.net/npm/fullcalendar/index.global'.$suffix.'.js',
        ['jquery-core', 'jquery-ui-core'],
        PLUGIN_SKIANET_FULLCAL_VERSION,
        true
    );
    wp_enqueue_script(
        'skianet-fullcalendar-script-it',
        'https://cdn.jsdelivr.net/npm/@fullcalendar/core/locales/it.global'.$suffix.'.js',
        ['jquery-core', 'jquery-ui-core', 'skianet-fullcalendar-script'],
        PLUGIN_SKIANET_FULLCAL_VERSION,
        true
    );

    wp_enqueue_script(
        'skianet-select2-script',
        'https://cdn.jsdelivr.net/npm/select2/dist/js/select2.full'.$suffix.'.js',
        ['jquery-core', 'jquery-ui-core'],
        PLUGIN_SKIANET_SELECT2_VERSION,
        true
    );
    wp_enqueue_script(
        'skianet-select2-script-it',
        'https://cdn.jsdelivr.net/npm/select2/dist/js/i18n/it.js',
        ['skianet-select2-script'],
        PLUGIN_SKIANET_SELECT2_VERSION,
        true
    );

    wp_enqueue_script(
        'skianet-script',
        PLUGIN_SKIANET_URL.'/assets/js/skianet-script'.$suffix.'.js',
        ['jquery-core', 'jquery-ui-core', 'skianet-fullcalendar-script-it', 'skianet-select2-script-it'],
        PLUGIN_SKIANET_VERSION,
        true
    );

    wp_localize_script('skianet-script', 'skianet_ajax_object', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'cart_url' => wc_get_cart_url(),
        'action_get_disp' => SKIANET_ACTION_GET_DISPONIBILITA,
        'action_get_book_modal' => SKIANET_ACTION_GET_BOOK_DIALOG,
        'action_booking_add_cart' => SKIANET_ACTION_BOOKING_ADD_CART,
        'calendar_container_id' => SKIANET_DISPONIBILITA_CONTAIN_ID,
        'calendar_element_id' => SKIANET_DISPONIBILITA_ELEMENT_ID,
        'calendar_timezone' => wp_timezone_string(),
        'calendar_dialog_error_add_to_cart' => __('Errore durante l\'aggiunta al carrello', PLUGIN_SKIANET_TEXT_DOMAIN),
        'calendar_my_account' => SKIANET_CUSTOM_MYACC_BOOKING,
    ]);
}

function skianet_termegest_calendar_check_dependencies(): bool
{
    $jqueryUiCore = wp_scripts()->query('jquery-ui-core');
    $jqueryUiCoreDialog = wp_scripts()->query('jquery-ui-dialog');
    $jqueryUiDialogCss = wp_styles()->query('wp-jquery-ui-dialog');
    $wcAddToCartVariation = wp_scripts()->query('wc-add-to-cart-variation');
    $imagesLoaded = wp_scripts()->query('imagesloaded');

    if (
        ! $jqueryUiCore instanceof _WP_Dependency
        || ! $jqueryUiCoreDialog instanceof _WP_Dependency
        || ! $jqueryUiDialogCss instanceof _WP_Dependency
        || ! $wcAddToCartVariation instanceof _WP_Dependency
        || ! $imagesLoaded instanceof _WP_Dependency
    ) {
        return false;
    }

    if (! wp_script_is($jqueryUiCore->handle)) {
        wp_enqueue_script($jqueryUiCore->handle);
    }

    if (! wp_script_is($jqueryUiCoreDialog->handle)) {
        wp_enqueue_script($jqueryUiCoreDialog->handle);
    }

    if (! wp_style_is($jqueryUiDialogCss->handle)) {
        wp_enqueue_style($jqueryUiDialogCss->handle);
    }

    if (! wp_script_is($wcAddToCartVariation->handle)) {
        wp_enqueue_script($wcAddToCartVariation->handle);
        WC_Frontend_Scripts::localize_printed_scripts();
    }

    if (! wp_script_is($imagesLoaded->handle)) {
        wp_enqueue_script($imagesLoaded->handle);
    }

    return true;
}

// add_action('elementor_pro/forms/actions/register', 'skianet_termegest_calendar_form_disponibilita_action', \PHP_INT_MAX);
function skianet_termegest_calendar_form_disponibilita_action(Form_Actions_Registrar $formActionsRegistrar): void
{
    require_once PLUGIN_SKIANET_PATH.'/components/skianet-disponibilita-book-dialog-action.php';

    $formActionsRegistrar->register(new SkianetBookCustomAction());
}
