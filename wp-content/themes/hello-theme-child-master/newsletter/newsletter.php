<?php
/**
 * Consenso alla newsletter
 *
 * Per ora il consenso viene solo registrato sull'utente, non inviato a nessuna
 * piattaforma: l'elenco si esporta a mano (vedi CLAUDE.md, sezione Newsletter).
 *
 * Tre punti d'ingresso, un solo stato in user meta:
 *  - registrazione: checkbox "newsletter" nel Form Elementor di /login-e-registrazione/,
 *  - area account: checkbox in Dettagli account, l'unica strada per chi si registra
 *    col social login (Nextend crea l'utente senza passare dal form),
 *  - admin: colonna "Newsletter" nell'elenco utenti.
 *
 * Assenza del meta = nessun consenso. Il meta viene scritto solo quando il valore
 * cambia, così la data resta quella dell'ultima scelta effettiva dell'utente.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// ID del campo Acceptance nel Form Elementor (Avanzate → ID): il POST arriva come form_fields[newsletter].
const LAVIADELLETERME_NEWSLETTER_CAMPO_FORM = 'newsletter';

const LAVIADELLETERME_NEWSLETTER_META       = 'laviadelleterme_newsletter';       // 'si' | 'no'
const LAVIADELLETERME_NEWSLETTER_META_DATA  = 'laviadelleterme_newsletter_data';  // ultima modifica, ora del sito
const LAVIADELLETERME_NEWSLETTER_META_FONTE = 'laviadelleterme_newsletter_fonte'; // 'registrazione' | 'account'

function laviadelleterme_newsletter_ha_consenso( $user_id ) {
	return 'si' === get_user_meta( $user_id, LAVIADELLETERME_NEWSLETTER_META, true );
}

/**
 * Unico punto che scrive il consenso. Non fa nulla se lo stato non cambia: risalvare
 * Dettagli account per cambiare la password non deve spostare la data del consenso.
 */
function laviadelleterme_newsletter_imposta( $user_id, $consenso, $fonte ) {
	if ( (bool) $consenso === laviadelleterme_newsletter_ha_consenso( $user_id ) ) {
		return;
	}

	update_user_meta( $user_id, LAVIADELLETERME_NEWSLETTER_META, $consenso ? 'si' : 'no' );
	update_user_meta( $user_id, LAVIADELLETERME_NEWSLETTER_META_DATA, current_time( 'mysql' ) );
	update_user_meta( $user_id, LAVIADELLETERME_NEWSLETTER_META_FONTE, $fonte );
}

/**
 * Registrazione dal Form Elementor.
 *
 * L'utente non lo crea Elementor ma l'azione del form (plugin esterno al repo), quindi
 * ci si aggancia a user_register, che scatta per qualunque creazione utente, e si legge
 * la checkbox dal POST dello stesso invio. Così non conta né quale plugin crea l'utente
 * né l'ordine fra le sue azioni e gli hook di Elementor. Non si cerca l'utente per email
 * nel record del form: un invio con l'email di un account esistente fallisce la
 * registrazione ma il record arriva lo stesso, e chiunque potrebbe iscrivere un altro.
 *
 * Una checkbox Acceptance non spuntata non viene inviata affatto: in quel caso non si
 * scrive nulla, che equivale già a "nessun consenso".
 */
add_action( 'user_register', function ( $user_id ) {
	if ( ! wp_doing_ajax() || ! isset( $_POST['action'] ) || 'elementor_pro_forms_send_form' !== $_POST['action'] ) {
		return;
	}

	if ( empty( $_POST['form_fields'][ LAVIADELLETERME_NEWSLETTER_CAMPO_FORM ] ) ) {
		return;
	}

	laviadelleterme_newsletter_imposta( $user_id, true, 'registrazione' );
} );

/**
 * Checkbox in My Account → Dettagli account, prima del pulsante di salvataggio.
 */
add_action( 'woocommerce_edit_account_form', function () {
	$consenso = laviadelleterme_newsletter_ha_consenso( get_current_user_id() );
	?>
	<fieldset class="laviadelleterme-newsletter">
		<legend><?php esc_html_e( 'Newsletter', 'hello-elementor-child' ); ?></legend>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label class="woocommerce-form__label woocommerce-form__label-for-checkbox">
				<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox" name="laviadelleterme_newsletter" value="1" <?php checked( $consenso ); ?> />
				<span><?php esc_html_e( 'Desidero ricevere la newsletter de La Via delle Terme con offerte e novità.', 'hello-elementor-child' ); ?></span>
			</label>
		</p>
	</fieldset>
	<?php
} );

// Scatta solo dopo che WooCommerce ha verificato nonce e campi e salvato l'account.
add_action( 'woocommerce_save_account_details', function ( $user_id ) {
	laviadelleterme_newsletter_imposta( $user_id, ! empty( $_POST['laviadelleterme_newsletter'] ), 'account' );
} );

/**
 * Colonna "Newsletter" in Utenti: chi ha dato il consenso, quando e da dove.
 */
add_filter( 'manage_users_columns', function ( $columns ) {
	$columns['laviadelleterme_newsletter'] = __( 'Newsletter', 'hello-elementor-child' );
	return $columns;
} );

add_filter( 'manage_users_custom_column', function ( $output, $column_name, $user_id ) {
	if ( 'laviadelleterme_newsletter' !== $column_name ) {
		return $output;
	}

	$stato = get_user_meta( $user_id, LAVIADELLETERME_NEWSLETTER_META, true );
	if ( '' === $stato ) {
		return '—';
	}

	$data  = get_user_meta( $user_id, LAVIADELLETERME_NEWSLETTER_META_DATA, true );
	$fonte = get_user_meta( $user_id, LAVIADELLETERME_NEWSLETTER_META_FONTE, true );

	return sprintf(
		'%s<br><small>%s · %s</small>',
		'si' === $stato ? esc_html__( 'Iscritto', 'hello-elementor-child' ) : esc_html__( 'Revocato', 'hello-elementor-child' ),
		esc_html( $data ? mysql2date( 'd/m/Y H:i', $data ) : '' ),
		esc_html( $fonte )
	);
}, 10, 3 );
