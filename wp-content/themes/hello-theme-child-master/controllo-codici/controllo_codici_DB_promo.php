<?php


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Renderer e controllo permessi stanno in controllo_codici_DB.php, richiesto prima
// di questo file da functions.php.
function laviadelleterme_controllo_codici_promo() {

	return laviadelleterme_render_controllo_codici( [
		1907   => 'A3 Hotel + Ingresso alle Terme (Valido per 2 persone) - Mezza giornata',
		1908   => 'A8 Hotel + Ingresso alle Terme (Valido per 2 persone) - Giornaliero',
		1636   => 'A7 Hotel + 2 Ingressi Terme (Valido per 2 persone)',
		1637   => 'AE Suite Privata con Massaggio di Coppia',
		1640   => 'AC Suite Privata con SPA - Bagno al Vapore + Sauna Finlandese',
		1639   => 'AD Suite Privata con SPA - Bagno al Vapore + Idromassaggio',
		1630   => 'A2 Ingresso Lunedì - Domenica - Mezza giornata',
		1631   => 'A6 Ingresso Lunedì - Domenica - Giornaliero',
		1616   => 'A1 Ingresso Lunedì - Venerdì - Mezza giornata',
		1617   => 'A5 Ingresso Lunedì - Venerdì - Giornaliero',
		109182 => 'V2 Ingresso Promo Serale da 3 ore',
		21800  => 'M4 Massaggi promo',
		27378  => 'PN Ingresso Promo Lunedì - Domenica 4 Ore Per Festività Natalizie',
	] );
}

add_shortcode('controllo_codici_promo', 'laviadelleterme_controllo_codici_promo');
