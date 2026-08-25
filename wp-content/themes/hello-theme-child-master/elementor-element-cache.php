<?php
/**
 * Compatibilità con "Element Caching" di Elementor
 *
 * Con quella funzionalità attiva Elementor salva l'HTML già renderizzato dei widget
 * nei post meta del documento e lo ristampa senza rieseguire Widget_Base::render_content():
 * i filtri su 'elementor/widget/render_content' non girano più. Il tema ne usa due, in
 * my-account/my-account.php e in thankyou/thankyou.php, e in entrambi i casi il testo
 * dipende dalla richiesta (l'endpoint WooCommerce, lo stato dell'ordine) mentre la cache
 * è per elemento e vale per tutto il documento: il primo render vince e resta congelato.
 * Verificato in produzione su staging2 — dopo aver aperto /my-account/lost-password/
 * anche la dashboard mostrava "Password dimenticata".
 *
 * Element_Base::should_render_shortcode() prevede però una via d'uscita ufficiale: gli
 * elementi marcati come dinamici non finiscono nell'HTML salvato, vengono stampati come
 * segnaposto [elementor-element data="…"] e renderizzati a ogni richiesta. Qui si marcano
 * i tre widget interessati.
 *
 * Il criterio deve dipendere solo dai dati del widget, mai dal contesto della richiesta:
 * il filtro è valutato quando la cache viene costruita, non quando viene servita.
 *
 * Quando Element Caching è disattivo questo file è un no-op: should_render_shortcode()
 * esce subito sul filtro 'elementor/element/should_render_shortcode', che di default è false.
 *
 * Dopo una modifica qui va svuotata la cache esistente (Elementor → Strumenti →
 * Rigenera file e dati), altrimenti resta a video l'HTML congelato da prima.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * @param bool  $is_dynamic Valore corrente.
 * @param array $raw_data   Dati grezzi dell'elemento (id, elType, settings…).
 * @param mixed $element    Istanza Element_Base / Widget_Base.
 * @return bool
 */
function laviadelleterme_widget_da_non_mettere_in_cache( $is_dynamic, $raw_data, $element ) {
	if ( $is_dynamic ) {
		return true;
	}

	$nome = is_object( $element ) && method_exists( $element, 'get_name' ) ? $element->get_name() : '';

	$impostazioni = isset( $raw_data['settings'] ) && is_array( $raw_data['settings'] )
		? $raw_data['settings']
		: array();

	if ( 'heading' === $nome ) {
		// Titolo dell'area account: riscritto in "Password dimenticata" sull'endpoint
		// lost-password. Si riconosce dalla classe CSS del widget; il confronto sul testo
		// è una seconda rete, nel caso la classe venisse tolta dall'editor.
		$classi = isset( $impostazioni['_css_classes'] ) ? (string) $impostazioni['_css_classes'] : '';
		if ( in_array( 'title-my-account', preg_split( '/\s+/', trim( $classi ) ), true ) ) {
			return true;
		}

		$titolo = isset( $impostazioni['title'] ) ? trim( wp_strip_all_tags( (string) $impostazioni['title'] ) ) : '';

		if ( 'Il mio account' === $titolo ) {
			return true;
		}

		// Titolo della thank-you page: diventa "Ordine ricevuto!" oppure "Ordine in attesa
		// di pagamento" a seconda dello stato dell'ordine. È il caso più delicato: in cache
		// un ordine non pagato può mostrare "Ordine ricevuto!".
		if ( 'Pagamento' === $titolo ) {
			return true;
		}
	}

	// Sottotitolo della thank-you page, rimosso lato server sulla order-received.
	if ( 'text-editor' === $nome ) {
		$testo = isset( $impostazioni['editor'] ) ? (string) $impostazioni['editor'] : '';
		if ( false !== strpos( $testo, 'Completa il pagamento' ) ) {
			return true;
		}
	}

	return $is_dynamic;
}
add_filter( 'elementor/element/is_dynamic_content', 'laviadelleterme_widget_da_non_mettere_in_cache', 10, 3 );
