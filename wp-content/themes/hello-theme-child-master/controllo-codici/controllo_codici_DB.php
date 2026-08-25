<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/**
 * I due shortcode stampano le giacenze di codici licenza per prodotto: sono dati
 * interni, non devono dipendere solo dal fatto che la pagina che li ospita sia
 * protetta. Chi non ha il permesso vede un messaggio esplicito e non il silenzio,
 * così ci si accorge subito se serve allargare la capability.
 *
 * @return bool
 */
function laviadelleterme_puo_vedere_controllo_codici() {
	return is_user_logged_in() && current_user_can( 'manage_woocommerce' );
}

/**
 * Renderer condiviso dei due shortcode (prezzo pieno e promo): cambia solo la mappa
 * prodotti, il resto era duplicato riga per riga fra i due file.
 *
 * Una sola query con IN + GROUP BY al posto di una query per prodotto: erano 20 e 13
 * round trip al database per una pagina che ne richiede uno.
 *
 * @param array $prodotti Mappa product_id => etichetta da mostrare.
 * @return string HTML della lista.
 */
function laviadelleterme_render_controllo_codici( array $prodotti ) {
	if ( ! laviadelleterme_puo_vedere_controllo_codici() ) {
		return '<p class="controllo-codici-negato">Contenuto riservato allo staff.</p>';
	}

	if ( empty( $prodotti ) ) {
		return '';
	}

	// Lo stile non serve nel resto del sito: si carica solo dove lo shortcode è a video.
	wp_enqueue_style( 'controllo-codici-style' );

	global $wpdb;

	$ids          = array_map( 'intval', array_keys( $prodotti ) );
	$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

	$conteggi = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT product_id, COUNT(*) AS totale
			 FROM `{$wpdb->prefix}wc_ld_license_codes`
			 WHERE order_id = 0 AND product_id IN ($placeholders)
			 GROUP BY product_id",
			$ids
		),
		OBJECT_K
	);

	$output = '<ul class="controllo-codici-list">';

	foreach ( $prodotti as $product_id => $label ) {
		// I prodotti senza nemmeno una riga non tornano dalla GROUP BY: valgono 0.
		$count = isset( $conteggi[ $product_id ] ) ? (int) $conteggi[ $product_id ]->totale : 0;

		$output .= '<li><strong>' . esc_html( $label ) . '</strong><span class="codici-count">' . $count . '</span></li>';
	}

	return $output . '</ul>';
}

function laviadelleterme_controllo_codici() {

	return laviadelleterme_render_controllo_codici( [
		1677   => 'P5 Hotel + Ingresso alle Terme (Valido per 2 persone) - Mezza giornata',
		1678   => 'P6 Hotel + Ingresso alle Terme (Valido per 2 persone) - Giornaliero',
		1604   => 'P7 Hotel + 2 Ingressi Terme (Valido per 2 persone)',
		394    => 'PL Suite Privata con Massaggio di Coppia',
		392    => 'PI Suite Privata con SPA - Bagno al Vapore + Idromassaggio',
		393    => 'PH Suite Privata con SPA - Bagno al Vapore + Sauna Finlandese',
		229    => 'P2 Ingresso Lunedì - Domenica - Mezza giornata',
		230    => 'P4 Ingresso Lunedì - Domenica - Giornaliero',
		225    => 'P1 Ingresso Lunedì - Venerdì - Mezza giornata',
		224    => 'P3 Ingresso Lunedì - Venerdì - Giornaliero',
		109134 => 'V3 Ingresso Serale da 3 ore',
		98149  => 'Bonus Terme and Wellness by LVDT',
		1690   => 'M5 Massaggi',
		1243   => 'W1 Proroghe Ingressi',
		1244   => 'W2 Proroghe Hotel + Ingressi',
		27370  => 'PM Ingresso Lunedì - Domenica 4 Ore Per Festività Natalizie',
		28750  => 'Veglione di Capodanno in Accappatoio - Monterosa',
		28749  => 'Veglione di Capodanno in Accappatoio - Saint Vincent',
		28748  => 'Veglione di Capodanno in Accappatoio - Genova',
		29044  => 'Hotel De La Ville 3 Notti + 2 Ingressi Terme + Veglione Di Capodanno',
	] );
}

add_shortcode('controllo_codici_prezzo_pieno', 'laviadelleterme_controllo_codici');
