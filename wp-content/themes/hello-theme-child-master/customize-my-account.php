<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


// Personalizza l'URL di registrazione
function custom_register_url( $url ) {
    return home_url('/login-e-registrazione/');
    
}
add_filter( 'register_url', 'custom_register_url', 10, 1 );


// Personalizza l'URL di login
function custom_login_url( $url, $redirect = '', $force_reauth = false ) {
    // URL personalizzato per la pagina di login
    $custom_url = home_url('/login-e-registrazione/');
    
    // Aggiungi i parametri se necessario
    if ( !empty( $redirect ) ) {
        $custom_url = add_query_arg( 'redirect_to', urlencode($redirect), $custom_url );
    }
    
    if ( $force_reauth ) {
        $custom_url = add_query_arg( 'reauth', '1', $custom_url );
    }
    
    return $custom_url;
}
add_filter( 'login_url', 'custom_login_url', 10, 3 );

/**
 * Reindirizza utenti non loggati dalla pagina my-account alla pagina di login
 * escludendo la pagina di recupero password
 */
function reindirizza_utenti_non_loggati_myaccount() {
    // Controlla se siamo in una pagina my-account
    if (is_page('my-account') || (strpos($_SERVER['REQUEST_URI'], '/my-account/') !== false)) {
        
        // Esclude la pagina di recupero password
        if (strpos($_SERVER['REQUEST_URI'], '/my-account/lost-password/') !== false) {
            return; // Non fare nulla, permetti l'accesso alla pagina di recupero password
        }
        
        // Verifica se l'utente non è loggato
        if (!is_user_logged_in()) {
            // Ottieni l'URL della pagina di login
            $login_url = home_url('/login-e-registrazione/');
            // Reindirizza alla pagina di login
            wp_redirect($login_url);
            exit;
        }
    }
}
// Aggiungi l'azione all'hook template_redirect
add_action('template_redirect', 'reindirizza_utenti_non_loggati_myaccount');

/**
 * Reindirizza utenti già loggati dalla pagina login-e-registrazione alla pagina my-account
 */
function reindirizza_utenti_loggati_login_page() {

    // Non applicare il redirect in modalità anteprima di Elementor o nell'editor
    if (isset($_GET['elementor-preview']) || 
        (isset($_REQUEST['action']) && $_REQUEST['action'] == 'elementor') ||
        is_admin()) {
        return;
    }

    // Verifica se siamo nella pagina login-e-registrazione
    if (is_page('login-e-registrazione') || $_SERVER['REQUEST_URI'] == '/login-e-registrazione/') {
        // Verifica se l'utente è già loggato
        if (is_user_logged_in()) {
            // Reindirizza alla pagina my-account
            wp_redirect(home_url('/my-account/'));
            exit;
        }
    }
}
// Aggiungi l'azione all'hook template_redirect
add_action('template_redirect', 'reindirizza_utenti_loggati_login_page');