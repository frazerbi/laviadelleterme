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

// Disabilita la richiesta di conferma email admin (causa pagine bianche su login custom)
add_filter( 'admin_email_check_interval', '__return_false' );

add_filter( 'woocommerce_login_redirect', 'custom_login_redirect', 10, 2 );
function custom_login_redirect( $redirect, $user ) {

    // Controlla parametri redirect espliciti (WooCommerce usa 'redirect', WordPress usa 'redirect_to')
    $redirect_param = '';
    if ( isset( $_REQUEST['redirect'] ) && !empty( $_REQUEST['redirect'] ) ) {
        $redirect_param = wp_unslash( $_REQUEST['redirect'] );
    } elseif ( isset( $_REQUEST['redirect_to'] ) && !empty( $_REQUEST['redirect_to'] ) ) {
        $redirect_param = wp_unslash( $_REQUEST['redirect_to'] );
    }

    if ( !empty( $redirect_param ) ) {
        // Gestisci sia URL assoluti che relativi dello stesso sito
        $full_url = ( strpos( $redirect_param, 'http' ) === 0 )
            ? esc_url_raw( $redirect_param )
            : home_url( esc_url_raw( $redirect_param ) );

        if ( strpos( $full_url, home_url() ) !== false ) {
            return $full_url;
        }
    }

    // Usa il referer per capire da dove proviene l'utente
    $referer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';

    if ( !empty( $referer ) && strpos( $referer, home_url() ) !== false ) {
        // Se era nel checkout, rimanda al checkout
        if ( strpos( $referer, '/checkout' ) !== false ) {
            return wc_get_checkout_url();
        }
        // Se era nella my-account o nella pagina di login, rimanda alla my-account
        if ( strpos( $referer, '/my-account' ) !== false || strpos( $referer, '/login-e-registrazione' ) !== false ) {
            return wc_get_page_permalink( 'myaccount' );
        }
        // In tutti gli altri casi, rimanda alla pagina di provenienza
        return $referer;
    }

    // Fallback: my-account
    return wc_get_page_permalink( 'myaccount' );
}

?>