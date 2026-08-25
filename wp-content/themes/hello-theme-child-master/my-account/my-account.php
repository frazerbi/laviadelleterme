<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


// Personalizza l'URL di registrazione
function laviadelleterme_custom_register_url( $url ) {
    return home_url('/login-e-registrazione/');
    
}
add_filter( 'register_url', 'laviadelleterme_custom_register_url', 10, 1 );


// Personalizza l'URL di login
function laviadelleterme_custom_login_url( $url, $redirect = '', $force_reauth = false ) {
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
add_filter( 'login_url', 'laviadelleterme_custom_login_url', 10, 3 );

/**
 * Reindirizza utenti non loggati dalla pagina my-account alla pagina di login
 * escludendo la pagina di recupero password
 */
function laviadelleterme_reindirizza_utenti_non_loggati_myaccount() {
    // Non interferire con le richieste AJAX
    if ( wp_doing_ajax() || isset( $_GET['wc-ajax'] ) ) {
        return;
    }

    if ( is_user_logged_in() ) {
        return;
    }

    // is_account_page() di WooCommerce al posto di uno strpos su REQUEST_URI: quello
    // scattava su qualsiasi URL che contenesse la stringa '/my-account/' (es. un articolo
    // del blog) e non seguiva la pagina realmente impostata in WooCommerce → Impostazioni.
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
        return;
    }

    // Recupero e reimpostazione password devono restare raggiungibili da sloggati.
    if ( is_wc_endpoint_url( 'lost-password' ) ) {
        return;
    }

    // Salvagente contro il loop di redirect nel caso la pagina "Il mio account" di
    // WooCommerce venisse impostata sulla pagina di login stessa.
    if ( is_page( 'login-e-registrazione' ) ) {
        return;
    }

    $login_url = home_url( '/login-e-registrazione/' );

    // Conserva la pagina richiesta invece di scaricare tutti in dashboard: chi apre
    // /my-account/view-order/123 da sloggato ci torna dopo il login. Il valore viene
    // riletto da laviadelleterme_custom_login_redirect() e validato con
    // laviadelleterme_is_local_url(). Il path è ricostruito su home_url(), così un
    // REQUEST_URI manipolato non può spostare la destinazione su un altro host.
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    if ( '' !== $request_uri ) {
        $login_url = add_query_arg( 'redirect_to', rawurlencode( home_url( $request_uri ) ), $login_url );
    }

    wp_safe_redirect( $login_url );
    exit;
}
// Aggiungi l'azione all'hook template_redirect
add_action('template_redirect', 'laviadelleterme_reindirizza_utenti_non_loggati_myaccount');

// Disabilita la richiesta di conferma email admin (causa pagine bianche su login custom)
add_filter( 'admin_email_check_interval', '__return_false' );

/**
 * Verifica che un URL punti davvero al dominio del sito (confronto host, non substring:
 * "https://evil.tld/?x=" + home_url() supererebbe un semplice strpos()).
 */
function laviadelleterme_is_local_url( $url ) {
    if ( empty( $url ) ) {
        return false;
    }
    $url_host  = wp_parse_url( $url, PHP_URL_HOST );
    $site_host = wp_parse_url( home_url(), PHP_URL_HOST );
    return $url_host && $site_host && strtolower( $url_host ) === strtolower( $site_host );
}

/**
 * Destinazione dopo login o registrazione. Logica unica per i due filtri, che prima
 * avevano due copie identiche della stessa funzione.
 *
 * Ordine di preferenza:
 *  1. parametro esplicito ('redirect' per WooCommerce, 'redirect_to' per WordPress),
 *  2. pagina di provenienza (Referer), con scorciatoie per checkout e area account,
 *  3. fallback sulla my-account.
 *
 * Ogni URL passa da laviadelleterme_is_local_url(): niente redirect fuori dal dominio.
 *
 * @return string URL di destinazione.
 */
function laviadelleterme_destinazione_dopo_accesso() {

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

        if ( laviadelleterme_is_local_url( $full_url ) ) {
            return $full_url;
        }
    }

    // Usa il referer per capire da dove proviene l'utente
    $referer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';

    if ( laviadelleterme_is_local_url( $referer ) ) {
        // Se era nel checkout, rimanda al checkout
        if ( strpos( $referer, '/checkout' ) !== false ) {
            return wc_get_checkout_url();
        }
        // Se era nella my-account o nella pagina di login/registrazione, rimanda alla my-account
        if ( strpos( $referer, '/my-account' ) !== false || strpos( $referer, '/login-e-registrazione' ) !== false ) {
            return wc_get_page_permalink( 'myaccount' );
        }
        // In tutti gli altri casi, rimanda alla pagina di provenienza
        return $referer;
    }

    // Fallback: my-account
    return wc_get_page_permalink( 'myaccount' );
}

add_filter( 'woocommerce_login_redirect', 'laviadelleterme_custom_login_redirect', 10, 2 );
function laviadelleterme_custom_login_redirect( $redirect, $user ) {
    return laviadelleterme_destinazione_dopo_accesso();
}

add_filter( 'woocommerce_registration_redirect', 'laviadelleterme_custom_registration_redirect', 10, 1 );
function laviadelleterme_custom_registration_redirect( $redirect ) {
    return laviadelleterme_destinazione_dopo_accesso();
}

/**
 * Titolo della pagina "Password dimenticata".
 *
 * L'H1 arriva dal template Elementor dell'area account (lo stesso per dashboard,
 * ordini e recupero password), quindi è hardcoded "Il mio account". Il testo viene
 * riscritto qui lato server, come fa thankyou.php con "Pagamento": prima era simulato
 * in CSS nascondendo l'H1 e stampando la stringa in un ::after a 5rem fissi, che
 * saltava la tipografia responsive del widget e, senza larghezza propria, andava a capo
 * sotto il form.
 */
add_filter( 'elementor/widget/render_content', function ( $content, $widget ) {
    // Il filtro gira su ogni pagina del sito: is_wc_endpoint_url() esiste solo con
    // WooCommerce attivo.
    if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'lost-password' ) ) {
        return $content;
    }

    if ( 'heading' !== $widget->get_name() ) {
        return $content;
    }

    // Si sostituisce solo il nodo di testo esattamente uguale a "Il mio account": uno
    // str_replace secco colpirebbe qualsiasi altro heading che contenga quella stringa.
    $replaced = preg_replace( '/>\s*Il mio account\s*</u', '>Password dimenticata<', $content, 1 );

    return null === $replaced ? $content : $replaced;
}, 10, 2 );

/**
 * Body class per la schermata "link inviato" della password dimenticata.
 *
 * Dopo l'invio WooCommerce rimanda su .../lost-password/?reset-link-sent=true, dove il
 * form sparisce e resta il solo paragrafo di conferma. Nel markup non c'è nient'altro
 * che distingua i due stati e il CSS non vede la query string, quindi la distinzione
 * arriva da qui — stesso schema di 'order-pay-terms-error' in order-pay/order-pay.php.
 */
add_filter( 'body_class', function ( $classes ) {
    if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'lost-password' ) ) {
        return $classes;
    }

    if ( isset( $_GET['reset-link-sent'] ) ) {
        $classes[] = 'lost-password-link-sent';
    }

    return $classes;
} );
