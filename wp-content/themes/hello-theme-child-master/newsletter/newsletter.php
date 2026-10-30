<?php
/**
 * Consenso alla newsletter
 *
 * Qui si registra il consenso sull'utente; l'invio a MailUp sta in mailup.php, che
 * ascolta l'azione laviadelleterme_newsletter_cambiato.
 *
 * Punti d'ingresso, un solo stato in user meta:
 *  - registrazione: checkbox "newsletter" nel Form Elementor di /login-e-registrazione/,
 *  - area account: checkbox in Dettagli account, l'unica strada per chi si registra
 *    col social login (Nextend crea l'utente senza passare dal form),
 *  - checkout: checkbox nei dati di fatturazione, spuntata secondo il consenso attuale,
 *  - admin: box nel profilo utente, che può solo *revocare* (su richiesta del cliente),
 *    mai dare il consenso: un consenso impostato dallo staff non dimostrerebbe nulla,
 *  - MailUp: webhook di disiscrizione (mailup.php).
 *
 * Assenza del meta = nessun consenso. Lo stato corrente (tre meta) serve a colonne e
 * query; la prova del consenso è lo storico, una riga di user meta per ogni cambio,
 * scritta e mai modificata.
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// ID del campo Acceptance nel Form Elementor (Avanzate → ID): il POST arriva come form_fields[newsletter].
const LAVIADELLETERME_NEWSLETTER_CAMPO_FORM = 'newsletter';

// Testo della checkbox in Dettagli account e al checkout. Quello del form di registrazione si legge dal widget Elementor.
const LAVIADELLETERME_NEWSLETTER_TESTO = 'Desidero ricevere la newsletter de La Via delle Terme con offerte e novità.';

const LAVIADELLETERME_NEWSLETTER_META         = 'laviadelleterme_newsletter';         // 'si' | 'no'
const LAVIADELLETERME_NEWSLETTER_META_DATA    = 'laviadelleterme_newsletter_data';    // ultima modifica, ora del sito
const LAVIADELLETERME_NEWSLETTER_META_FONTE   = 'laviadelleterme_newsletter_fonte';   // 'registrazione' | 'account' | 'checkout' | 'admin' | 'mailup'
const LAVIADELLETERME_NEWSLETTER_META_STORICO = 'laviadelleterme_newsletter_storico'; // una riga per cambio (meta non unico)

/**
 * Etichetta leggibile della provenienza. Nel meta resta il codice breve, che serve a
 * query ed export; il testo esteso è solo per l'admin.
 */
function laviadelleterme_newsletter_fonte_label( $fonte ) {
	$etichette = array(
		'registrazione' => __( 'Form di registrazione', 'hello-elementor-child' ),
		'account'       => __( 'Dettagli account (area clienti)', 'hello-elementor-child' ),
		'checkout'      => __( 'Checkout, all\'acquisto', 'hello-elementor-child' ),
		'admin'         => __( 'Amministrazione, su richiesta del cliente', 'hello-elementor-child' ),
		'mailup'        => __( 'Disiscrizione dal link nella newsletter (MailUp)', 'hello-elementor-child' ),
	);

	return $etichette[ $fonte ] ?? $fonte;
}

function laviadelleterme_newsletter_ha_consenso( $user_id ) {
	return 'si' === get_user_meta( $user_id, LAVIADELLETERME_NEWSLETTER_META, true );
}

/**
 * Unico punto che scrive il consenso. Non fa nulla se lo stato non cambia: risalvare
 * Dettagli account per cambiare la password non deve spostare la data né sporcare lo storico.
 *
 * @param string $testo Testo della checkbox che l'utente aveva davanti ('' per revoche admin/MailUp).
 */
function laviadelleterme_newsletter_imposta( $user_id, $consenso, $fonte, $testo = '' ) {
	if ( (bool) $consenso === laviadelleterme_newsletter_ha_consenso( $user_id ) ) {
		return;
	}

	$stato = $consenso ? 'si' : 'no';
	$data  = current_time( 'mysql' );

	update_user_meta( $user_id, LAVIADELLETERME_NEWSLETTER_META, $stato );
	update_user_meta( $user_id, LAVIADELLETERME_NEWSLETTER_META_DATA, $data );
	update_user_meta( $user_id, LAVIADELLETERME_NEWSLETTER_META_FONTE, $fonte );

	// add_user_meta non unico: ogni cambio è una riga a sé, nessuna lettura-modifica-scrittura.
	add_user_meta( $user_id, LAVIADELLETERME_NEWSLETTER_META_STORICO, array(
		'data'      => $data,
		'stato'     => $stato,
		'fonte'     => $fonte,
		'operatore' => 'admin' === $fonte ? get_current_user_id() : 0,
		'testo'     => $testo,
	) );

	do_action( 'laviadelleterme_newsletter_cambiato', $user_id, (bool) $consenso, $fonte );
}

/**
 * Testo della checkbox newsletter così com'è configurato nel widget Form che ha
 * inviato la richiesta. Elementor manda nel POST l'id del documento che contiene il
 * widget (post_id, anche quando il form sta in un template) e l'id dell'elemento
 * (form_id): con quelli si ritrova il campo dentro _elementor_data.
 *
 * @return string '' se non si trova: meglio uno storico che dice "non rilevato" di uno
 *                che riporta un testo forse diverso da quello mostrato.
 */
function laviadelleterme_newsletter_testo_form( $post_id, $form_id ) {
	$dati = json_decode( (string) get_post_meta( $post_id, '_elementor_data', true ), true );
	$form = is_array( $dati ) ? laviadelleterme_newsletter_trova_elemento( $dati, $form_id ) : null;

	foreach ( $form['settings']['form_fields'] ?? array() as $campo ) {
		if ( LAVIADELLETERME_NEWSLETTER_CAMPO_FORM === ( $campo['custom_id'] ?? '' ) ) {
			return trim( wp_strip_all_tags( $campo['acceptance_text'] ?? '' ) );
		}
	}

	return '';
}

function laviadelleterme_newsletter_trova_elemento( $elementi, $id ) {
	foreach ( $elementi as $elemento ) {
		if ( ( $elemento['id'] ?? '' ) === $id ) {
			return $elemento;
		}
		if ( ! empty( $elemento['elements'] ) ) {
			$trovato = laviadelleterme_newsletter_trova_elemento( $elemento['elements'], $id );
			if ( $trovato ) {
				return $trovato;
			}
		}
	}

	return null;
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

	$testo = laviadelleterme_newsletter_testo_form(
		isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0,
		isset( $_POST['form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['form_id'] ) ) : ''
	);

	laviadelleterme_newsletter_imposta( $user_id, true, 'registrazione', $testo );
} );

/**
 * Riquadro newsletter condiviso da checkout e Dettagli account: casella spuntata
 * secondo il consenso attuale, nota "consenso dato il …" se iscritto, e il campo
 * nascosto che dice al salvataggio che la casella era davvero a schermo — una
 * checkbox vuota non viene inviata, e senza quel segnale un invio in cui la casella
 * non è stata stampata sembrerebbe una revoca. Stesso markup nelle due pagine, così
 * newsletter.css le copre entrambe.
 *
 * Preselezionare la casella per chi è già iscritto non è un consenso dato al suo
 * posto: è lo stato che ha scelto lui.
 */
function laviadelleterme_newsletter_campo( $user_id ) {
	$consenso = laviadelleterme_newsletter_ha_consenso( $user_id );
	?>
	<div class="laviadelleterme-newsletter-box">
		<?php
		woocommerce_form_field( 'laviadelleterme_newsletter', array(
			'type'  => 'checkbox',
			'class' => array( 'form-row-wide', 'laviadelleterme-newsletter' ),
			'label' => esc_html( LAVIADELLETERME_NEWSLETTER_TESTO ),
		), $consenso ? 1 : 0 );

		$nota = $consenso ? laviadelleterme_newsletter_nota_consenso( $user_id ) : '';
		if ( $nota ) :
			?>
			<p class="laviadelleterme-newsletter-box__nota"><?php echo esc_html( $nota ); ?></p>
		<?php endif; ?>
		<input type="hidden" name="laviadelleterme_newsletter_mostrato" value="1" />
	</div>
	<?php
}

// Salva solo se il riquadro era nel form inviato (vedi laviadelleterme_newsletter_campo()).
function laviadelleterme_newsletter_salva_da_form( $user_id, $fonte ) {
	if ( ! $user_id || empty( $_POST['laviadelleterme_newsletter_mostrato'] ) ) {
		return;
	}

	laviadelleterme_newsletter_imposta( $user_id, ! empty( $_POST['laviadelleterme_newsletter'] ), $fonte, LAVIADELLETERME_NEWSLETTER_TESTO );
}

/**
 * Nota per il cliente sotto la casella: quando e dove ha dato il consenso. Testi
 * propri e non le etichette dell'admin, che sono scritte per lo staff. Le fonti sono
 * solo quelle da cui può nascere un consenso: admin e MailUp possono solo revocare.
 */
function laviadelleterme_newsletter_nota_consenso( $user_id ) {
	$dove = array(
		'registrazione' => __( 'in fase di registrazione', 'hello-elementor-child' ),
		'account'       => __( 'dal tuo account', 'hello-elementor-child' ),
		'checkout'      => __( 'durante un acquisto', 'hello-elementor-child' ),
	);

	$data  = get_user_meta( $user_id, LAVIADELLETERME_NEWSLETTER_META_DATA, true );
	$fonte = get_user_meta( $user_id, LAVIADELLETERME_NEWSLETTER_META_FONTE, true );
	if ( ! $data ) {
		return '';
	}

	return sprintf(
		/* translators: 1: data, 2: provenienza, es. "in fase di registrazione" */
		__( 'Consenso dato il %1$s %2$s. Puoi revocarlo togliendo la spunta.', 'hello-elementor-child' ),
		mysql2date( 'd/m/Y', $data ),
		$dove[ $fonte ] ?? ''
	);
}

/**
 * My Account → Dettagli account, prima del pulsante di salvataggio. Il fieldset con
 * legenda ricalca quello "Cambio password" del template WooCommerce.
 */
add_action( 'woocommerce_edit_account_form', function () {
	?>
	<fieldset class="laviadelleterme-newsletter-fieldset">
		<legend><?php esc_html_e( 'Newsletter', 'hello-elementor-child' ); ?></legend>
		<?php laviadelleterme_newsletter_campo( get_current_user_id() ); ?>
	</fieldset>
	<?php
} );

// Scatta solo dopo che WooCommerce ha verificato nonce e campi e salvato l'account.
add_action( 'woocommerce_save_account_details', function ( $user_id ) {
	laviadelleterme_newsletter_salva_da_form( $user_id, 'account' );
} );

/**
 * Checkout: stesso riquadro di Dettagli account, in fondo ai dati di fatturazione.
 *
 * Non sta vicino ai termini, dove sarebbe più naturale: quella parte (#payment) viene
 * ricaricata via AJAX a ogni update_checkout e una spunta già messa sparirebbe in
 * silenzio — è il motivo per cui la dichiarazione sanitaria del plugin deve appoggiarsi
 * alla sessione. I dati di fatturazione non vengono ricaricati.
 */
add_action( 'woocommerce_after_checkout_billing_form', function () {
	if ( is_user_logged_in() ) {
		laviadelleterme_newsletter_campo( get_current_user_id() );
	}
} );

/**
 * Scatta all'invio dell'ordine, a validazione superata, indipendentemente dall'esito
 * del pagamento. imposta() non scrive nulla se lo stato non cambia, quindi un iscritto
 * che lascia la spunta com'è non aggiunge righe allo storico.
 */
add_action( 'woocommerce_checkout_update_user_meta', function ( $customer_id ) {
	laviadelleterme_newsletter_salva_da_form( $customer_id, 'checkout' );
} );

/**
 * Box nel profilo utente in wp-admin: stato e storico in sola lettura, più la sola
 * revoca. Mostrato solo a chi può modificare quell'utente.
 */
function laviadelleterme_newsletter_box_profilo( $user ) {
	if ( ! current_user_can( 'edit_user', $user->ID ) ) {
		return;
	}

	$storico = get_user_meta( $user->ID, LAVIADELLETERME_NEWSLETTER_META_STORICO );
	?>
	<h2><?php esc_html_e( 'Newsletter', 'hello-elementor-child' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><?php esc_html_e( 'Consenso', 'hello-elementor-child' ); ?></th>
			<td>
				<?php if ( laviadelleterme_newsletter_ha_consenso( $user->ID ) ) : ?>
					<strong><?php esc_html_e( 'Iscritto', 'hello-elementor-child' ); ?></strong>
					<p>
						<label>
							<input type="checkbox" name="laviadelleterme_newsletter_revoca" value="1" />
							<?php esc_html_e( 'Revoca il consenso (solo su richiesta del cliente)', 'hello-elementor-child' ); ?>
						</label>
					</p>
				<?php else : ?>
					<?php esc_html_e( 'Non iscritto', 'hello-elementor-child' ); ?>
					<p class="description"><?php esc_html_e( 'Il consenso può darlo solo il cliente, dalla registrazione o da Dettagli account.', 'hello-elementor-child' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Storico', 'hello-elementor-child' ); ?></th>
			<td>
				<?php if ( ! $storico ) : ?>
					—
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Data', 'hello-elementor-child' ); ?></th>
								<th><?php esc_html_e( 'Evento', 'hello-elementor-child' ); ?></th>
								<th><?php esc_html_e( 'Provenienza', 'hello-elementor-child' ); ?></th>
								<th><?php esc_html_e( 'Testo mostrato', 'hello-elementor-child' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $storico as $riga ) : ?>
								<?php
								$fonte = laviadelleterme_newsletter_fonte_label( $riga['fonte'] ?? '' );
								if ( ! empty( $riga['operatore'] ) ) {
									$operatore = get_userdata( $riga['operatore'] );
									$fonte    .= ' (' . ( $operatore ? $operatore->display_name : '#' . $riga['operatore'] ) . ')';
								}
								?>
								<tr>
									<td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $riga['data'] ?? '' ) ); ?></td>
									<td><?php echo 'si' === ( $riga['stato'] ?? '' ) ? esc_html__( 'Consenso', 'hello-elementor-child' ) : esc_html__( 'Revoca', 'hello-elementor-child' ); ?></td>
									<td><?php echo esc_html( $fonte ); ?></td>
									<td><?php echo esc_html( ( $riga['testo'] ?? '' ) ?: ( 'si' === ( $riga['stato'] ?? '' ) ? __( '(non rilevato)', 'hello-elementor-child' ) : '' ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'laviadelleterme_newsletter_box_profilo' );
add_action( 'edit_user_profile', 'laviadelleterme_newsletter_box_profilo' );

// Il nonce del profilo lo ha già verificato user-edit.php prima di questi hook.
function laviadelleterme_newsletter_salva_revoca( $user_id ) {
	if ( empty( $_POST['laviadelleterme_newsletter_revoca'] ) || ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	laviadelleterme_newsletter_imposta( $user_id, false, 'admin' );
}
add_action( 'personal_options_update', 'laviadelleterme_newsletter_salva_revoca' );
add_action( 'edit_user_profile_update', 'laviadelleterme_newsletter_salva_revoca' );

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
		esc_html( laviadelleterme_newsletter_fonte_label( $fonte ) )
	);
}, 10, 3 );
