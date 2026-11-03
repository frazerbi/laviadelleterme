<?php
/**
 * Importazione una tantum dei disiscritti di MailUp come consenso revocato sul sito.
 *
 * Prova a vuoto (non scrive nulla):
 *   wp eval-file importa-disiscritti-mailup.php /percorso/export.csv
 * Scrittura:
 *   wp eval-file importa-disiscritti-mailup.php /percorso/export.csv esegui
 *
 * - Considera solo le righe con "Stato email" = UNSUBSCRIBED.
 * - Scrive solo sugli utenti senza alcuno stato newsletter.
 * - Non tocca mai chi risulta iscritto sul sito: lo elenca, perché l'export non dà la
 *   data della disiscrizione e quindi non si sa quale scelta sia la più recente.
 * - Non chiama MailUp: scrive i meta direttamente e non lancia
 *   laviadelleterme_newsletter_cambiato. Rilanciarlo è sicuro.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

global $wpdb;

if ( ! defined( 'LAVIADELLETERME_NEWSLETTER_META' ) ) {
	WP_CLI::error( 'Modulo newsletter non caricato: il tema attivo non è quello giusto?' );
}

$percorso = $args[0] ?? '';
$esegui   = 'esegui' === ( $args[1] ?? '' );
$file     = '' !== $percorso && is_readable( $percorso ) ? fopen( $percorso, 'r' ) : false;

if ( ! $file ) {
	WP_CLI::error( 'File CSV non leggibile: ' . $percorso );
}

$intestazione = fgetcsv( $file, 0, ',', '"', '' );
if ( $intestazione ) {
	$intestazione[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $intestazione[0] );
}

$colonna_email = $intestazione ? array_search( 'Email', $intestazione, true ) : false;
$colonna_stato = $intestazione ? array_search( 'Stato email', $intestazione, true ) : false;

if ( false === $colonna_email || false === $colonna_stato ) {
	WP_CLI::error( 'Nel CSV mancano le colonne "Email" e "Stato email".' );
}

// Una query per tutti invece di una per riga: il file ha oltre 100 mila contatti.
$utenti = array();
foreach ( $wpdb->get_results( "SELECT ID, user_email FROM {$wpdb->users}" ) as $riga ) {
	$utenti[ strtolower( trim( $riga->user_email ) ) ] = (int) $riga->ID;
}

$stati = array();
foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", LAVIADELLETERME_NEWSLETTER_META ) ) as $riga ) {
	$stati[ (int) $riga->user_id ] = $riga->meta_value;
}

// Senza questa i meta di ogni utente scritto resterebbero in memoria fino alla fine.
wp_suspend_cache_addition( true );

$conteggi  = array();
$conflitti = array();
$esiti     = array(
	'righe'           => 0,
	'email_non_valide' => 0,
	'senza_utente'    => 0,
	'gia_revocati'    => 0,
	'da_importare'    => 0,
	'errori'          => 0,
);
$data = current_time( 'mysql' );

while ( false !== ( $riga = fgetcsv( $file, 0, ',', '"', '' ) ) ) {
	if ( array( null ) === $riga ) {
		continue; // Riga vuota.
	}

	++$esiti['righe'];

	$stato_mailup = trim( (string) ( $riga[ $colonna_stato ] ?? '' ) );
	$email        = strtolower( trim( (string) ( $riga[ $colonna_email ] ?? '' ) ) );

	$conteggi[ $stato_mailup ] = ( $conteggi[ $stato_mailup ] ?? 0 ) + 1;

	if ( 'UNSUBSCRIBED' !== $stato_mailup ) {
		continue;
	}

	if ( ! is_email( $email ) ) {
		++$esiti['email_non_valide'];
		continue;
	}

	$user_id = $utenti[ $email ] ?? 0;
	if ( ! $user_id ) {
		++$esiti['senza_utente'];
		continue;
	}

	$stato_sito = $stati[ $user_id ] ?? '';

	if ( 'si' === $stato_sito ) {
		$conflitti[ $user_id ] = $email;
		continue;
	}

	if ( 'no' === $stato_sito ) {
		++$esiti['gia_revocati'];
		continue;
	}

	++$esiti['da_importare'];
	$stati[ $user_id ] = 'no'; // Una stessa email ripetuta nel file conta una volta sola.

	if ( ! $esegui ) {
		continue;
	}

	// Stessi meta di laviadelleterme_newsletter_imposta(), che però da "nessuno stato" a
	// "no" non scrive nulla perché per lei non è un cambiamento.
	update_user_meta( $user_id, LAVIADELLETERME_NEWSLETTER_META, 'no' );
	update_user_meta( $user_id, LAVIADELLETERME_NEWSLETTER_META_DATA, $data );
	update_user_meta( $user_id, LAVIADELLETERME_NEWSLETTER_META_FONTE, 'importazione-mailup' );

	$scritto = add_user_meta( $user_id, LAVIADELLETERME_NEWSLETTER_META_STORICO, array(
		'data'      => $data,
		'stato'     => 'no',
		'fonte'     => 'importazione-mailup',
		'operatore' => 0,
		'testo'     => '',
	) );

	if ( ! $scritto ) {
		++$esiti['errori'];
		WP_CLI::warning( "Storico non scritto per l'utente $user_id" );
	}
}

fclose( $file );

WP_CLI::log( $esegui ? '--- SCRITTURA ESEGUITA ---' : '--- PROVA A VUOTO: nessuna scrittura ---' );
WP_CLI::log( 'Righe lette: ' . $esiti['righe'] );
WP_CLI::log( 'Utenti sul sito: ' . count( $utenti ) );
WP_CLI::log( 'Valori di "Stato email":' );
foreach ( $conteggi as $valore => $numero ) {
	WP_CLI::log( sprintf( '  %s: %d', '' === $valore ? '(vuoto)' : $valore, $numero ) );
}
WP_CLI::log( 'Tra gli UNSUBSCRIBED:' );
WP_CLI::log( '  ' . ( $esegui ? 'importati' : 'da importare' ) . ': ' . $esiti['da_importare'] );
WP_CLI::log( '  senza utente sul sito (ignorati): ' . $esiti['senza_utente'] );
WP_CLI::log( '  già revocati sul sito (ignorati): ' . $esiti['gia_revocati'] );
WP_CLI::log( '  email non valide (ignorate): ' . $esiti['email_non_valide'] );
WP_CLI::log( '  iscritti sul sito (NON toccati, da controllare): ' . count( $conflitti ) );
foreach ( $conflitti as $user_id => $email ) {
	WP_CLI::log( "    utente $user_id <$email>" );
}
if ( $esiti['errori'] ) {
	WP_CLI::warning( 'Righe di storico non scritte: ' . $esiti['errori'] );
}
