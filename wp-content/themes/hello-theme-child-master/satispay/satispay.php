<?php
/**
 * Satispay: recupero dell'ordine quando l'utente annulla il pagamento.
 *
 * Il plugin woo-satispay (wc-satispay.php), al ritorno dall'app:
 *  - chiama Payment::get() SENZA try/catch -> qualsiasi errore API = fatal, pagina bianca;
 *  - se il pagamento non è ACCEPTED lo annulla lato Satispay e reindirizza al CHECKOUT,
 *    lasciando però l'ordine in on-hold;
 *  - alla callback asincrona con status CANCELED mette l'ordine in `wc-cancelled`,
 *    stato NON pagabile (needs_payment() accetta solo pending/failed): l'ordine diventa
 *    irrecuperabile e l'utente non può più cambiare metodo di pagamento.
 *
 * Qui intercettiamo `woocommerce_api_wc_gateway_satispay` a priorità 5, cioè PRIMA
 * dell'handler del plugin (priorità 10, registrato nel costruttore del gateway), e
 * usciamo con exit dove gestiamo noi il caso. Così non serve modificare il plugin,
 * che verrebbe sovrascritto ad ogni aggiornamento.
 *
 * Comportamento nuovo: ordine riportato a `pending` (pagabile, nessuna email al cliente
 * né all'admin) e utente reindirizzato alla pagina order-pay dello stesso ordine, dove
 * può pagare con carta senza rifare il checkout (i meta di prenotazione sono già
 * scritti sugli order item, vedi Booking_Cart_Handler).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Log su WooCommerce -> Stato -> Log, source `satispay-lvdt`.
 *
 * @param string $message Messaggio.
 * @param array  $context Dati extra.
 */
function laviadelleterme_satispay_log( $message, $context = array() ) {
	if ( ! function_exists( 'wc_get_logger' ) ) {
		return;
	}

	if ( ! empty( $context ) ) {
		$message .= ' | ' . wp_json_encode( $context );
	}

	wc_get_logger()->info( $message, array( 'source' => 'satispay-lvdt' ) );
}

/**
 * Riporta l'ordine a uno stato pagabile dopo un pagamento Satispay annullato/non riuscito.
 *
 * @param WC_Order $order Ordine.
 * @param string   $reason Motivo, finisce nella nota ordine.
 */
function laviadelleterme_satispay_restore_payable_status( $order, $reason ) {
	// Se nel frattempo l'ordine è stato pagato (callback arrivata prima), non tocchiamo nulla.
	if ( $order->has_status( wc_get_is_paid_statuses() ) ) {
		laviadelleterme_satispay_log( 'Ordine già pagato, stato non modificato', array(
			'order_id' => $order->get_id(),
			'status'   => $order->get_status(),
		) );
		return;
	}

	if ( $order->has_status( 'pending' ) ) {
		return;
	}

	$order->update_status(
		'pending',
		'Pagamento Satispay non completato (' . $reason . '): ordine riportato in attesa di pagamento per consentire un nuovo tentativo.'
	);

	laviadelleterme_satispay_log( 'Ordine riportato a pending', array(
		'order_id' => $order->get_id(),
		'reason'   => $reason,
	) );
}

/**
 * Pulisce l'id pagamento Satispay dalla sessione WC (il plugin non lo fa mai).
 */
function laviadelleterme_satispay_clear_session_payment_id() {
	if ( WC()->session ) {
		WC()->session->set( 'satispay_payment_id', null );
	}
}

/**
 * Redirect + stop dell'esecuzione, prima che giri l'handler del plugin.
 *
 * @param string $url URL di destinazione.
 */
function laviadelleterme_satispay_redirect( $url ) {
	laviadelleterme_satispay_log( 'Redirect', array( 'url' => $url ) );

	// L'handler wc-api gira dentro un ob_start(): puliamo prima di inviare gli header.
	if ( ob_get_length() ) {
		ob_end_clean();
	}

	wp_safe_redirect( $url );
	exit;
}

/**
 * Handler anticipato dell'endpoint `?wc-api=wc_gateway_satispay`.
 */
function laviadelleterme_satispay_gateway_api() {
	$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

	if ( ! in_array( $action, array( 'redirect', 'callback' ), true ) ) {
		return;
	}

	// Garantisce che il costruttore di WC_Satispay sia girato: è lì che vengono
	// impostate le chiavi statiche dell'SDK (Api::setKeyId/setPrivateKey/setPublicKey).
	WC()->payment_gateways();

	if ( ! class_exists( '\SatispayGBusiness\Payment' ) ) {
		return;
	}

	if ( 'callback' === $action ) {
		laviadelleterme_satispay_handle_callback();
		return;
	}

	laviadelleterme_satispay_handle_return();
}
add_action( 'woocommerce_api_wc_gateway_satispay', 'laviadelleterme_satispay_gateway_api', 5 );

/**
 * Ritorno del browser dall'app Satispay (`action=redirect`).
 */
function laviadelleterme_satispay_handle_return() {
	$payment_id = WC()->session ? WC()->session->get( 'satispay_payment_id' ) : '';

	// Ordine di fallback: WooCommerce lo salva in sessione all'avvio del pagamento.
	$fallback_order_id = WC()->session ? absint( WC()->session->get( 'order_awaiting_payment' ) ) : 0;
	$fallback_order    = $fallback_order_id ? wc_get_order( $fallback_order_id ) : false;

	laviadelleterme_satispay_log( 'Ritorno da Satispay', array(
		'payment_id'        => $payment_id,
		'fallback_order_id' => $fallback_order_id,
	) );

	if ( ! $payment_id ) {
		// Senza id pagamento il plugin manderebbe alla thank-you page di un ordine
		// inesistente: se conosciamo l'ordine, mandiamo l'utente dove può pagarlo.
		if ( $fallback_order ) {
			laviadelleterme_satispay_restore_payable_status( $fallback_order, 'sessione senza payment id' );
			laviadelleterme_satispay_redirect( $fallback_order->get_checkout_payment_url() );
		}

		return; // Nessun dato utile: lascia fare al plugin.
	}

	try {
		$payment = \SatispayGBusiness\Payment::get( $payment_id );
	} catch ( \Exception $e ) {
		// Causa della pagina bianca: nel plugin questa chiamata non è protetta.
		laviadelleterme_satispay_log( 'Errore API Payment::get', array(
			'payment_id' => $payment_id,
			'error'      => $e->getMessage(),
		) );

		laviadelleterme_satispay_clear_session_payment_id();

		if ( $fallback_order ) {
			laviadelleterme_satispay_restore_payable_status( $fallback_order, 'errore API: ' . $e->getMessage() );
			laviadelleterme_satispay_redirect( $fallback_order->get_checkout_payment_url() );
		}

		laviadelleterme_satispay_redirect( wc_get_checkout_url() );
	}

	$order_id = isset( $payment->metadata->order_id ) ? absint( $payment->metadata->order_id ) : 0;
	$order    = $order_id ? wc_get_order( $order_id ) : $fallback_order;

	if ( ! $order ) {
		laviadelleterme_satispay_log( 'Ordine non trovato per il pagamento', array( 'payment_id' => $payment_id ) );
		return; // Lascia fare al plugin.
	}

	$status = isset( $payment->status ) ? $payment->status : '';

	laviadelleterme_satispay_log( 'Stato pagamento al ritorno', array(
		'payment_id'   => $payment_id,
		'order_id'     => $order->get_id(),
		'payment'      => $status,
		'order_status' => $order->get_status(),
	) );

	if ( 'ACCEPTED' === $status ) {
		// Percorso ok: stessa destinazione del plugin (get_return_url($order)), gestita
		// qui per non dipendere dall'ordine di esecuzione degli hook.
		laviadelleterme_satispay_clear_session_payment_id();
		laviadelleterme_satispay_redirect( $order->get_checkout_order_received_url() );
	}

	// Pagamento non accettato: annulliamo lato Satispay come farebbe il plugin...
	try {
		\SatispayGBusiness\Payment::update( $payment_id, array( 'action' => 'CANCEL' ) );
	} catch ( \Exception $e ) {
		laviadelleterme_satispay_log( 'Errore API Payment::update CANCEL', array(
			'payment_id' => $payment_id,
			'error'      => $e->getMessage(),
		) );
	}

	// ...ma invece di lasciare l'ordine in on-hold (e farlo poi annullare dalla
	// callback) lo riportiamo pagabile e mandiamo l'utente alla pagina order-pay.
	laviadelleterme_satispay_clear_session_payment_id();
	laviadelleterme_satispay_restore_payable_status( $order, 'annullato dall\'utente, stato ' . ( $status ? $status : 'sconosciuto' ) );

	wc_add_notice(
		'Il pagamento con Satispay è stato annullato. Puoi completare l\'ordine qui sotto scegliendo un altro metodo di pagamento.',
		'notice'
	);

	laviadelleterme_satispay_redirect( $order->get_checkout_payment_url() );
}

/**
 * Callback server-to-server di Satispay (`action=callback`).
 *
 * Intercettiamo SOLO il caso CANCELED, per evitare che l'ordine finisca in
 * `wc-cancelled` (non più pagabile) mentre l'utente sta ritentando con un altro
 * metodo. Il caso ACCEPTED resta interamente gestito dal plugin (payment_complete).
 */
function laviadelleterme_satispay_handle_callback() {
	$payment_id = isset( $_GET['payment_id'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_id'] ) ) : '';

	if ( ! $payment_id ) {
		return;
	}

	try {
		$payment = \SatispayGBusiness\Payment::get( $payment_id );
	} catch ( \Exception $e ) {
		laviadelleterme_satispay_log( 'Callback: errore API Payment::get', array(
			'payment_id' => $payment_id,
			'error'      => $e->getMessage(),
		) );

		// Anche qui il plugin non ha try/catch: usciamo puliti invece del fatal.
		if ( ob_get_length() ) {
			ob_end_clean();
		}
		exit;
	}

	$status   = isset( $payment->status ) ? $payment->status : '';
	$order_id = isset( $payment->metadata->order_id ) ? absint( $payment->metadata->order_id ) : 0;
	$order    = $order_id ? wc_get_order( $order_id ) : false;

	laviadelleterme_satispay_log( 'Callback', array(
		'payment_id' => $payment_id,
		'order_id'   => $order_id,
		'payment'    => $status,
	) );

	if ( ! $order ) {
		return; // Nessun ordine: lascia fare al plugin.
	}

	if ( $order->has_status( wc_get_is_paid_statuses() ) ) {
		if ( ob_get_length() ) {
			ob_end_clean();
		}
		exit;
	}

	if ( 'ACCEPTED' === $status ) {
		// Stessa logica del plugin, replicata qui per non dipendere dall'ordine
		// di esecuzione degli hook (noi usciamo con exit).
		$order->payment_complete( $payment_id );

		if ( ob_get_length() ) {
			ob_end_clean();
		}
		exit;
	}

	if ( 'CANCELED' !== $status ) {
		return; // Stati intermedi (PENDING): gestisce il plugin.
	}

	laviadelleterme_satispay_restore_payable_status( $order, 'callback CANCELED' );

	if ( ob_get_length() ) {
		ob_end_clean();
	}
	exit;
}
