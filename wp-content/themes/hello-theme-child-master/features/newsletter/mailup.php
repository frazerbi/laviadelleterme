<?php
/**
 * Sincronizzazione del consenso newsletter con MailUp
 *
 * Usa l'API HTTPS "Frontend" di MailUp (xmlSubscribe.aspx / Xmlunsubscribe.aspx), non
 * le REST: niente OAuth né password di un utente MailUp da conservare, basta l'host
 * della console e la lista. In cambio MailUp accetta le chiamate solo dagli IP
 * registrati in Developer's corner → Web services → Frontend (altrimenti -1011).
 *
 * Due versi:
 *  - sito → MailUp: ogni cambio di consenso accoda un job Action Scheduler (incluso in
 *    WooCommerce), così la registrazione non aspetta MailUp e non fallisce se è giù;
 *    lo stesso job allinea email, nome e cognome quando cambiano sul profilo;
 *  - MailUp → sito: il webhook "Unsubscribe" della lista chiama la rotta REST qui sotto
 *    quando qualcuno si disiscrive dal link nell'email.
 *
 * Niente double opt-in (confirm=0): il contatto entra subito attivo, così il sito e
 * MailUp non divergono su chi non clicca la conferma. La prova del consenso è lo storico
 * in newsletter.php.
 *
 * Configurazione per ambiente in wp-config.php (staging2 su una lista di test):
 *   LAVIADELLETERME_MAILUP_HOST          es. 'https://xxxxx.emailsp.com' (host della console)
 *   LAVIADELLETERME_MAILUP_LIST          ID numerico della lista (Impostazioni → Tabella codici)
 *   LAVIADELLETERME_MAILUP_LIST_GUID     GUID della lista, obbligatorio per la disiscrizione
 *   LAVIADELLETERME_MAILUP_GROUP         facoltativo, ID del gruppo
 *   LAVIADELLETERME_MAILUP_WEBHOOK_TOKEN segreto nell'URL del webhook
 * Senza le prime tre l'integrazione è spenta e il consenso resta solo sul sito.
 *
 * Log: WooCommerce → Stato → Log, sorgente "mailup-lvdt".
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

const LAVIADELLETERME_MAILUP_JOB              = 'laviadelleterme_mailup_sincronizza';
const LAVIADELLETERME_MAILUP_JOB_DISISCRIVI   = 'laviadelleterme_mailup_disiscrivi_email';
const LAVIADELLETERME_MAILUP_TENTATIVI        = 3;
const LAVIADELLETERME_MAILUP_META_STATO       = 'laviadelleterme_mailup_stato'; // 'in coda' | 'ok' | 'errore'
const LAVIADELLETERME_MAILUP_META_DATA        = 'laviadelleterme_mailup_data';
const LAVIADELLETERME_MAILUP_META_ESITO       = 'laviadelleterme_mailup_esito';
// Email, nome e cognome inviati all'ultima iscrizione riuscita: ciò che MailUp ha in lista.
const LAVIADELLETERME_MAILUP_META_CONTATTO    = 'laviadelleterme_mailup_contatto';

function laviadelleterme_mailup_configurato() {
	return defined( 'LAVIADELLETERME_MAILUP_HOST' ) && LAVIADELLETERME_MAILUP_HOST
		&& defined( 'LAVIADELLETERME_MAILUP_LIST' ) && LAVIADELLETERME_MAILUP_LIST
		&& defined( 'LAVIADELLETERME_MAILUP_LIST_GUID' ) && LAVIADELLETERME_MAILUP_LIST_GUID;
}

function laviadelleterme_mailup_log( $livello, $messaggio ) {
	if ( function_exists( 'wc_get_logger' ) ) {
		// La lista in testa: staging e produzione puntano a liste diverse.
		$lista = defined( 'LAVIADELLETERME_MAILUP_LIST' ) ? '[lista ' . LAVIADELLETERME_MAILUP_LIST . '] ' : '';
		wc_get_logger()->log( $livello, $lista . $messaggio, array( 'source' => 'mailup-lvdt' ) );
	}
}

/**
 * Chiama una pagina dell'API Frontend. Le pagine "xml" rispondono con un codice
 * numerico nel corpo (0 = fatto); qualsiasi altra cosa diventa un WP_Error.
 *
 * @return int|WP_Error
 */
function laviadelleterme_mailup_chiama( $pagina, $parametri ) {
	// add_query_arg() non codifica i valori: email e nomi vanno codificati qui.
	$url = add_query_arg(
		array_map( 'rawurlencode', $parametri ),
		untrailingslashit( LAVIADELLETERME_MAILUP_HOST ) . '/frontend/' . $pagina
	);

	$risposta = wp_remote_get( $url, array( 'timeout' => 15 ) );
	if ( is_wp_error( $risposta ) ) {
		return $risposta;
	}

	$http  = wp_remote_retrieve_response_code( $risposta );
	$corpo = trim( wp_remote_retrieve_body( $risposta ) );
	if ( 200 !== $http || ! is_numeric( $corpo ) ) {
		return new WP_Error( 'mailup_risposta', sprintf( 'HTTP %d: %s', $http, substr( wp_strip_all_tags( $corpo ), 0, 200 ) ) );
	}

	return (int) $corpo;
}

/**
 * Iscrive l'utente alla lista con nome e cognome (campo1/campo2 sono i campi predefiniti
 * di MailUp). I campi vuoti non si mandano, per non sovrascrivere quelli già in MailUp.
 *
 * Codici: 0 iscritto, 3 già iscritto → entrambi ok; 1 errore, 2 email non valida,
 * -1011 IP del server non registrato.
 */
function laviadelleterme_mailup_iscrivi( WP_User $user ) {
	$parametri = array(
		'list'    => LAVIADELLETERME_MAILUP_LIST,
		'email'   => $user->user_email,
		'confirm' => 0,
		'retCode' => 1,
	);

	if ( defined( 'LAVIADELLETERME_MAILUP_GROUP' ) && LAVIADELLETERME_MAILUP_GROUP ) {
		$parametri['group'] = LAVIADELLETERME_MAILUP_GROUP;
	}

	$campi = array_filter( array(
		'campo1' => $user->first_name,
		'campo2' => $user->last_name,
	) );
	if ( $campi ) {
		// Il ';' separa i valori: dentro un nome spezzerebbe la riga.
		$parametri['csvFldNames']  = implode( ';', array_keys( $campi ) );
		$parametri['csvFldValues'] = implode( ';', str_replace( ';', ' ', $campi ) );
	}

	$codice = laviadelleterme_mailup_chiama( 'xmlSubscribe.aspx', $parametri );

	return is_wp_error( $codice ) || in_array( $codice, array( 0, 3 ), true ) ? $codice : new WP_Error( 'mailup_codice', 'xmlSubscribe codice ' . $codice );
}

/**
 * Per email e non per utente: serve anche quando l'utente sta per essere cancellato.
 * Codici: 0 disiscritto, 3 già disiscritto, 1 mai stato in lista (verificato su staging2
 * con un indirizzo inventato: non è l'errore generico che dice la documentazione) →
 * tutti ok, l'indirizzo non è in lista.
 */
function laviadelleterme_mailup_disiscrivi( $email ) {
	$codice = laviadelleterme_mailup_chiama( 'Xmlunsubscribe.aspx', array(
		'ListGuid' => LAVIADELLETERME_MAILUP_LIST_GUID,
		'List'     => LAVIADELLETERME_MAILUP_LIST,
		'Email'    => $email,
	) );

	return is_wp_error( $codice ) || in_array( $codice, array( 0, 1, 3 ), true ) ? $codice : new WP_Error( 'mailup_codice', 'Xmlunsubscribe codice ' . $codice );
}

/**
 * Accoda un job; con $ritardo lo programma più avanti (usato per i nuovi tentativi).
 * $chiave è l'id utente per la sincronizzazione, l'email per la disiscrizione di un
 * utente cancellato.
 */
function laviadelleterme_mailup_accoda_job( $job, $chiave, $tentativo = 1, $ritardo = 0 ) {
	if ( ! laviadelleterme_mailup_configurato() ) {
		return;
	}

	if ( ! function_exists( 'as_enqueue_async_action' ) ) {
		laviadelleterme_mailup_log( 'error', "Action Scheduler non disponibile: $job per $chiave non accodato." );
		return;
	}

	$argomenti = array( $chiave, (int) $tentativo );

	// Un salvataggio di Dettagli account fa scattare profile_update più volte nella stessa
	// richiesta. Se un job uguale è ancora in attesa basta quello: leggerà i dati di quando
	// parte. Solo "pending": uno già in esecuzione può aver letto i dati vecchi.
	if ( ! $ritardo && as_get_scheduled_actions( array(
		'hook'     => $job,
		'args'     => $argomenti,
		'group'    => 'laviadelleterme',
		'status'   => ActionScheduler_Store::STATUS_PENDING,
		'per_page' => 1,
	), 'ids' ) ) {
		return;
	}

	if ( $ritardo ) {
		as_schedule_single_action( time() + $ritardo, $job, $argomenti, 'laviadelleterme' );
	} else {
		as_enqueue_async_action( $job, $argomenti, 'laviadelleterme' );
	}
}

function laviadelleterme_mailup_contatto( WP_User $user ) {
	return array(
		'email'   => $user->user_email,
		'nome'    => $user->first_name,
		'cognome' => $user->last_name,
	);
}

/**
 * L'indirizzo con cui l'utente sta nella lista: quello dell'ultima iscrizione riuscita,
 * che dopo un cambio email non è più quello del profilo. Senza iscrizioni registrate, il
 * suo indirizzo attuale.
 */
function laviadelleterme_mailup_email_in_lista( WP_User $user ) {
	$contatto = get_user_meta( $user->ID, LAVIADELLETERME_MAILUP_META_CONTATTO, true );

	return ! empty( $contatto['email'] ) ? $contatto['email'] : $user->user_email;
}

function laviadelleterme_mailup_accoda( $user_id ) {
	if ( laviadelleterme_mailup_configurato() ) {
		update_user_meta( $user_id, LAVIADELLETERME_MAILUP_META_STATO, 'in coda' );
	}
	laviadelleterme_mailup_accoda_job( LAVIADELLETERME_MAILUP_JOB, (int) $user_id );
}

// Un cambio arrivato dal webhook MailUp lo conosce già: rimandarlo indietro è inutile.
add_action( 'laviadelleterme_newsletter_cambiato', function ( $user_id, $consenso, $fonte ) {
	if ( 'mailup' !== $fonte ) {
		laviadelleterme_mailup_accoda( $user_id );
	}
}, 10, 3 );

/**
 * Email, nome o cognome cambiati, da qualunque parte (Dettagli account, profilo in
 * wp-admin, plugin): se l'utente è iscritto e MailUp ha dati diversi, si risincronizza.
 * Il confronto è con quanto inviato all'ultima iscrizione e non con i dati di prima del
 * salvataggio, perché nome e cognome sono user meta e qui sono già stati sovrascritti.
 * WooCommerce chiama wp_update_user() a ogni checkout: senza cambi non parte niente.
 */
add_action( 'profile_update', function ( $user_id ) {
	if ( ! laviadelleterme_mailup_configurato() || ! laviadelleterme_newsletter_ha_consenso( $user_id ) ) {
		return;
	}

	$user = get_userdata( $user_id );
	if ( $user && get_user_meta( $user_id, LAVIADELLETERME_MAILUP_META_CONTATTO, true ) !== laviadelleterme_mailup_contatto( $user ) ) {
		laviadelleterme_mailup_accoda( $user_id );
	}
} );

/**
 * Utente cancellato: l'email esce da MailUp. delete_user scatta prima della
 * cancellazione, quando email e meta sono ancora leggibili; il job però girerà dopo,
 * quindi riceve l'email e non l'id. Anche senza consenso, se in lista è rimasto un
 * contatto (una revoca la cui disiscrizione non è andata a buon fine).
 */
add_action( 'delete_user', function ( $user_id ) {
	$user = get_userdata( $user_id );
	if ( $user && ( laviadelleterme_newsletter_ha_consenso( $user_id ) || get_user_meta( $user_id, LAVIADELLETERME_MAILUP_META_CONTATTO, true ) ) ) {
		laviadelleterme_mailup_accoda_job( LAVIADELLETERME_MAILUP_JOB_DISISCRIVI, laviadelleterme_mailup_email_in_lista( $user ) );
	}
} );

/**
 * Il job legge lo stato *attuale* del consenso, non quello del momento in cui è stato
 * accodato: se l'utente cambia idea due volte di fila, vince l'ultima scelta.
 *
 * Se l'email è cambiata dall'ultima iscrizione, il vecchio indirizzo esce dalla lista
 * prima che entri il nuovo; con una revoca esce quello che MailUp conosce, non quello
 * del profilo.
 */
add_action( LAVIADELLETERME_MAILUP_JOB, function ( $user_id, $tentativo = 1 ) {
	$user = get_userdata( $user_id );
	if ( ! $user || ! laviadelleterme_mailup_configurato() ) {
		return;
	}

	$iscritto     = laviadelleterme_newsletter_ha_consenso( $user_id );
	$email_lista  = laviadelleterme_mailup_email_in_lista( $user );
	$cambio_email = $iscritto && 0 !== strcasecmp( $email_lista, $user->user_email );

	if ( $iscritto ) {
		$esito = $cambio_email ? laviadelleterme_mailup_disiscrivi( $email_lista ) : 0;
		if ( ! is_wp_error( $esito ) ) {
			$esito = laviadelleterme_mailup_iscrivi( $user );
		}
	} else {
		$esito = laviadelleterme_mailup_disiscrivi( $email_lista );
	}

	$azione = $iscritto ? ( $cambio_email ? "cambio email da <$email_lista>" : 'iscrizione' ) : 'disiscrizione';

	update_user_meta( $user_id, LAVIADELLETERME_MAILUP_META_DATA, current_time( 'mysql' ) );

	if ( ! is_wp_error( $esito ) ) {
		if ( $iscritto ) {
			update_user_meta( $user_id, LAVIADELLETERME_MAILUP_META_CONTATTO, laviadelleterme_mailup_contatto( $user ) );
		} else {
			delete_user_meta( $user_id, LAVIADELLETERME_MAILUP_META_CONTATTO );
		}

		update_user_meta( $user_id, LAVIADELLETERME_MAILUP_META_STATO, 'ok' );
		// 1 arriva solo dalla disiscrizione: per l'iscrizione è un errore.
		$nota = array( 1 => ' (non era in lista)', 3 => ' (già allineato)' );
		update_user_meta( $user_id, LAVIADELLETERME_MAILUP_META_ESITO, $azione . ( $nota[ $esito ] ?? '' ) );
		laviadelleterme_mailup_log( 'info', "Utente $user_id <{$user->user_email}>: $azione ok, codice $esito." );
		return;
	}

	update_user_meta( $user_id, LAVIADELLETERME_MAILUP_META_STATO, 'errore' );
	update_user_meta( $user_id, LAVIADELLETERME_MAILUP_META_ESITO, $azione . ': ' . $esito->get_error_message() );
	laviadelleterme_mailup_log( 'error', "Utente $user_id <{$user->user_email}>: $azione fallita al tentativo $tentativo — " . $esito->get_error_message() );

	// Action Scheduler non ritenta da solo: un nuovo giro fra un'ora, fino al limite.
	if ( $tentativo < LAVIADELLETERME_MAILUP_TENTATIVI ) {
		laviadelleterme_mailup_accoda_job( LAVIADELLETERME_MAILUP_JOB, (int) $user_id, $tentativo + 1, HOUR_IN_SECONDS );
	}
}, 10, 2 );

/**
 * L'utente non esiste più: niente meta da aggiornare, l'esito resta solo nel log.
 * Se nel frattempo qualcuno si è registrato con la stessa email (i nuovi tentativi
 * partono anche un'ora dopo), il contatto è suo e decide il suo consenso.
 */
add_action( LAVIADELLETERME_MAILUP_JOB_DISISCRIVI, function ( $email, $tentativo = 1 ) {
	if ( ! laviadelleterme_mailup_configurato() ) {
		return;
	}

	$nuovo = get_user_by( 'email', $email );
	if ( $nuovo ) {
		laviadelleterme_mailup_log( 'info', "Utente cancellato <$email>: disiscrizione saltata, l'email ora è dell'utente {$nuovo->ID}." );
		return;
	}

	$esito = laviadelleterme_mailup_disiscrivi( $email );

	if ( ! is_wp_error( $esito ) ) {
		laviadelleterme_mailup_log( 'info', "Utente cancellato <$email>: disiscrizione ok, codice $esito." );
		return;
	}

	laviadelleterme_mailup_log( 'error', "Utente cancellato <$email>: disiscrizione fallita al tentativo $tentativo — " . $esito->get_error_message() );

	if ( $tentativo < LAVIADELLETERME_MAILUP_TENTATIVI ) {
		laviadelleterme_mailup_accoda_job( LAVIADELLETERME_MAILUP_JOB_DISISCRIVI, $email, $tentativo + 1, HOUR_IN_SECONDS );
	}
}, 10, 2 );

/**
 * Webhook "Unsubscribe" della lista MailUp → consenso revocato sul sito.
 *
 * URL da impostare in MailUp (il segnaposto [Email] lo sostituisce MailUp):
 *   https://<sito>/wp-json/laviadelleterme/v1/mailup-disiscrizione?token=<TOKEN>&email=[Email]
 * Risponde 200 anche per email che non corrispondono a nessun utente: la lista può
 * contenere contatti arrivati da altre fonti, e un errore farebbe solo ritentare MailUp.
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'laviadelleterme/v1', '/mailup-disiscrizione', array(
		'methods'             => array( 'GET', 'POST' ),
		'permission_callback' => function ( WP_REST_Request $request ) {
			$ok = defined( 'LAVIADELLETERME_MAILUP_WEBHOOK_TOKEN' ) && '' !== (string) LAVIADELLETERME_MAILUP_WEBHOOK_TOKEN
				&& hash_equals( (string) LAVIADELLETERME_MAILUP_WEBHOOK_TOKEN, (string) $request->get_param( 'token' ) );

			// Senza questa riga un webhook configurato male in MailUp non lascia traccia. Il
			// token ricevuto non si scrive, solo i nomi dei parametri: un '?' di troppo
			// nell'URL si vede da lì.
			if ( ! $ok ) {
				laviadelleterme_mailup_log( 'warning', sprintf(
					'Webhook disiscrizione respinto (token mancante o errato). Parametri ricevuti: %s',
					implode( ', ', array_keys( $request->get_params() ) ) ?: 'nessuno'
				) );
			}

			return $ok;
		},
		'callback'            => function ( WP_REST_Request $request ) {
			$email = sanitize_email( (string) $request->get_param( 'email' ) );
			$user  = $email ? get_user_by( 'email', $email ) : false;

			if ( $user ) {
				laviadelleterme_newsletter_imposta( $user->ID, false, 'mailup' );
			}

			laviadelleterme_mailup_log( 'info', sprintf( 'Webhook disiscrizione <%s>: %s', $email, $user ? 'utente ' . $user->ID : 'nessun utente' ) );

			return new WP_REST_Response( array( 'ok' => true ), 200 );
		},
	) );
} );

/**
 * Colonna "MailUp" in Utenti, accanto a "Newsletter": esito dell'ultima sincronizzazione.
 */
add_filter( 'manage_users_columns', function ( $columns ) {
	if ( laviadelleterme_mailup_configurato() ) {
		$columns['laviadelleterme_mailup'] = __( 'MailUp', 'hello-elementor-child' );
	}
	return $columns;
} );

add_filter( 'manage_users_custom_column', function ( $output, $column_name, $user_id ) {
	if ( 'laviadelleterme_mailup' !== $column_name ) {
		return $output;
	}

	$stato = get_user_meta( $user_id, LAVIADELLETERME_MAILUP_META_STATO, true );
	if ( '' === $stato ) {
		return '—';
	}

	$data = get_user_meta( $user_id, LAVIADELLETERME_MAILUP_META_DATA, true );

	return sprintf(
		'%s<br><small>%s%s</small>',
		esc_html( $stato ),
		esc_html( get_user_meta( $user_id, LAVIADELLETERME_MAILUP_META_ESITO, true ) ),
		esc_html( $data ? ' · ' . mysql2date( 'd/m/Y H:i', $data ) : '' )
	);
}, 10, 3 );
