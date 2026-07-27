<?php
/**
 * Order-pay page (checkout/order-pay/...): notice quando un tentativo di pagamento
 * non va a buon fine e il gateway non reindirizza altrove (es. Mollie blocca un nuovo
 * pagamento se uno precedente per lo stesso ordine è ancora attivo, senza mostrare errore).
 */

// Priorità 20: gira DOPO WC_Form_Handler::pay_action() (priorità 10 su 'template_redirect').
// Se il pagamento fosse andato a buon fine, pay_action() avrebbe già fatto redirect + exit
// prima che questo codice venga eseguito: se siamo ancora qui, il tentativo non è riuscito.
add_action( 'template_redirect', function () {
	if ( ! function_exists( 'is_checkout_pay_page' ) || ! is_checkout_pay_page() ) {
		return;
	}

	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['woocommerce_pay'] ) ) {
		return;
	}

	wc_add_notice(
		'Il pagamento non risulta ancora completato. Se hai già avviato un pagamento con l\'app (es. Satispay), controlla se c\'è una richiesta in sospeso: finché resta attiva non è possibile avviarne una nuova per lo stesso ordine. Attendi 10 minuti e riprova, oppure contattaci se il problema persiste.',
		'notice'
	);
}, 20 );
