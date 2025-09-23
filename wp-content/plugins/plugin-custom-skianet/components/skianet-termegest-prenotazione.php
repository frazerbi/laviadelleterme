<?php

declare(strict_types=1);

use TermeGest\Type\TermeGestLogger;

function skianet_termegest_get_category_from_ticket(string $code): ?string
{
    global $wpdb;

    $tickets = $wpdb->get_results($wpdb->prepare('SELECT * FROM `'.$wpdb->wc_ld_license_codes.'` WHERE `license_code1` = %s;', $code));
    if (\count($tickets) !== 1) {
        return null;
    }

    $ticket = array_shift($tickets);
    $product = wc_get_product($ticket->product_id);
    if (! $product instanceof WC_Product) {
        error_log("Product not found for ticket code: {$code}, product_id: {$ticket->product_id}");
        return null;
    }

    $productName = $product->get_name();

    // USA LA STESSA MAPPATURA delle altre funzioni
    $productCategoryMap = [
        'ingresso lunedì-venerdì-mezza giornata' => 'p1',   
        'ingresso lunedì-domenica-mezza giornata' => 'p2',  
        'ingresso lunedì-venerdì-giornaliero' => 'p3',
        'ingresso lunedì-domenica-giornaliero' => 'p4'
    ];

    // Normalizza il nome del prodotto
    $normalizedProductName = normalize_product_name($productName);

    // Controlla se c'è una mappatura diretta
    if (array_key_exists($normalizedProductName, $productCategoryMap)) {
        $categoria = $productCategoryMap[$normalizedProductName];
        // error_log("Found direct mapping: {$productName} -> {$categoria}");
        return $categoria;
    }

    // Se non trova mappatura, ritorna null
    return null;
    
}

add_action('woocommerce_payment_complete', 'skianet_termegest_send_order_to_termegest', 10, 2);
function skianet_termegest_send_order_to_termegest($orderId): void
{   
    error_log("funzione chiamata");
    
    $wcOrder = wc_get_order($orderId);
    if (!$wcOrder) {
        return;
    }

    $termeGestLogger = TermeGestLogger::getInstance();

    $fromCalendar = (int) $wcOrder->get_meta(SKIANET_CUSTOM_BOOKING_PARAMS['fromCalendar']);
    if ($fromCalendar === 0) {
        $msg = \sprintf(__('Ordine %s non proveniente da calendario', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_id());
        $termeGestLogger->send($msg);
        $termeGestLogger->flushLog();

        return;
    }

    $codeAssign = new WC_LD_Code_Assignment();
    $codeAssign->assign_license_codes_to_order($wcOrder->get_id());

    $male = (int) get_post_meta($wcOrder->get_id(), SKIANET_CUSTOM_BOOKING_PARAMS['male'], true);
    $female = (int) get_post_meta($wcOrder->get_id(), SKIANET_CUSTOM_BOOKING_PARAMS['female'], true);

    if ($male < 0 || $female < 0) {
        $msg = sprintf(__('ERROR: Dati gender non validi - Male: %d, Female: %d', PLUGIN_SKIANET_TEXT_DOMAIN), $male, $female);
        $termeGestLogger->send($msg);
        $wcOrder->update_status('failed', $msg);
        return;
    }
    
    if ($male + $female === 0) {
        $msg = sprintf(__('ERROR: Nessun dato gender trovato per ordine %s', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_id());
        $termeGestLogger->send($msg);
        $wcOrder->update_status('failed', $msg);
        return;
    }

    $sexArray = array_merge(
        array_fill(0, $male, true),    // true = maschio
        array_fill(0, $female, false)  // false = femmina
    );
    
    error_log("SKIANET: Dati gender preparati - Male: {$male}, Female: {$female}, Total: " . count($sexArray));
    $termeGestLogger->send("👥 Dati gender: {$male} uomini, {$female} donne (totale: " . count($sexArray) . ")");

    $allInclusive = false;

    $successfulCodes = [];
    $failedCodes = [];
    $totalCodes = 0;
    $processedCodes = 0;
    $currentSexIndex = 0; 

    foreach ($wcOrder->get_items() as $item) {

        $itemPars = [];
        $itemName = $item->get_name();
        $itemPrice = (float) $item->get_product()->get_price();

        // CONTROLLO: Verifica se questo item ha metadati di prenotazione
        $itemHasBooking = false;
        foreach (['skn-custom-id', 'skn-custom-location', 'skn-custom-event', 'skn-custom-qty'] as $bookingMeta) {
            if (!empty($item->get_meta($bookingMeta))) {
                $itemHasBooking = true;
                break;
            }
        }

        if (!$itemHasBooking) {
            // error_log("SKIPPED non-booking item: {$itemName}");
            continue; // Salta gli item senza prenotazione
        }

        $codes = wc_get_order_item_meta($item->get_id(), '_license_code_ids');
        if (!empty($codes)) {
            $totalCodes += count($codes); // ← Questo conta i codici totali
        }

        foreach (SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM as $param) {
            error_log("SKIANET: Controllo campo {$param['key']} ({$param['value']}) per item {$itemName}");
            
            // Prima prova a prendere dall'item
            $itemMeta = $item->get_meta($param['key']);

            if (!empty($itemMeta)) {
                $itemPars[$param['key']] = $itemMeta;
                error_log("SKIANET: Campo {$param['key']} trovato nell'item {$itemName}: {$itemMeta}");
            } else {
                // Se non c'è nell'item, prendi dall'ordine generale
                $orderMeta = $wcOrder->get_meta($param['key']);
                if (!empty($orderMeta)) {
                    $itemPars[$param['key']] = $orderMeta;
                    error_log("SKIANET: Campo {$param['key']} preso dall'ordine per item {$itemName}: {$orderMeta}");
                } else {
                    $msg = \sprintf(__('Errore checkout %s: campo %s non presente per item %s', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_id(), $param['value'], $itemName);
                    $termeGestLogger->send($msg);
                    $termeGestLogger->flushLog();
                    $wcOrder->update_status('failed', $msg);
                    throw new Exception($msg);
                }
            }
        }

        $itemLocation = $itemPars[SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM['location']['key']];
        $encryptedLocation = skianet_termegest_encrypt($itemLocation);
        error_log("SKIANET: Location per item {$itemName}: {$itemLocation} (crittografata)");

        $codes = wc_get_order_item_meta($item->get_id(), '_license_code_ids');

        if (empty($codes)) {
            $msg = \sprintf(__('Errore checkout %s: codici non presenti', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_id());
            $termeGestLogger->send($msg);
            $termeGestLogger->flushLog();
            $wcOrder->update_status('failed', $msg);
            throw new Exception($msg);
        }

        foreach ($codes as $code) {
            $processedCodes++;
            $code = WC_LD_Model::get_codes_by_id($code);
            
            if (empty($code[0]) || empty($code[0]['license_code1'])) {
                $msg = \sprintf(__('Errore checkout %s: codice non presente', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_id());
                $termeGestLogger->send($msg);
                $termeGestLogger->flushLog();
                $wcOrder->update_status('failed', $msg);
                throw new Exception($msg);
            }
            $currentCode = $code[0]['license_code1'];

            if ($currentSexIndex >= count($sexArray)) {
                $msg = sprintf(__('ERROR: Insufficienti dati gender (codice %d di %d, ma solo %d gender disponibili)', PLUGIN_SKIANET_TEXT_DOMAIN), 
                    $processedCodes, $totalCodes, count($sexArray));
                $termeGestLogger->send($msg);
                $wcOrder->update_status('failed', $msg);
                throw new Exception($msg);
            }
            
            $currentGender = $sexArray[$currentSexIndex];
            $currentSexIndex++; // ✅ INCREMENTA per il prossimo codice
            
            error_log("SKIANET: Codice {$processedCodes}/{$totalCodes}: {$currentCode} - Gender: " . ($currentGender ? 'M' : 'F'));

            $categoria = skianet_termegest_get_category_from_ticket( $currentCode );

            if ($categoria === null) {
                $msg = sprintf(
                    __('Errore checkout %s: impossibile determinare categoria per codice %s', PLUGIN_SKIANET_TEXT_DOMAIN), 
                    $wcOrder->get_id(), 
                    $currentCode
                );
                $termeGestLogger->send($msg);
                $termeGestLogger->flushLog();
                $wcOrder->update_status('failed', $msg);
                throw new Exception($msg);
            }

            if (!is_string($categoria) || trim($categoria) === '') {
                $msg = \sprintf(__('Errore checkout %s: categoria biglietto non formattata correttamente', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_id());
                $termeGestLogger->send($msg);
                $termeGestLogger->flushLog();
                $wcOrder->update_status('failed', $msg);
                throw new Exception($msg);
            }

            // Sanitizzazione per SOAP/XML
            $categoria = trim($categoria);

            if ($categoria === 'p3' || $categoria === 'p4') {
                $allInclusive = true;
            } else {
                $allInclusive = false;
            }

            $allInclusive = (bool) $allInclusive;

            $idDisponibilita = (int) $itemPars[SKIANET_BOOKING_AJAX_EVENT_PARS_CUSTOM['id']['key']];
            error_log("SKIANET: Invio prenotazione per codice {$code[0]['license_code1']} - Item: {$itemName}, ID: {$idDisponibilita}, Location: {$itemLocation}");

            // ELABORAZIONE DEL SINGOLO CODICE - Con tracking completo
            $codeSuccess = false;
            $codeError = '';
            
            try {
                 // STEP 1: Chiama prima setVenduto
                $vendutoParams = [
                    'codice' => $currentCode,
                    'prezzo' => $itemPrice,
                    'nome_cliente' => $wcOrder->get_billing_first_name() . ' ' . $wcOrder->get_billing_last_name(),
                    'email_cliente' => $wcOrder->get_billing_email()
                ];
                
                // LOG PARAMETRI CHIAMATA setVenduto
                $termeGestLogger->send("🔄 Chiamata setVenduto per codice {$currentCode}:");
                $termeGestLogger->send("   Parametri: " . print_r($vendutoParams, true));
                error_log("SKIANET setVenduto - Parametri: " . print_r($vendutoParams, true));

                // STEP 1: Chiama prima setVenduto
                $vendutoResponse = skianet_termegest_set_venduto(
                    $currentCode,
                    $itemPrice,
                    $wcOrder->get_billing_first_name() . ' ' . $wcOrder->get_billing_last_name(),
                    $wcOrder->get_billing_email()
                );
                
                // LOG RISPOSTA setVenduto
                $termeGestLogger->send("📥 Risposta setVenduto: " . print_r($vendutoResponse, true));
                error_log("SKIANET setVenduto - Risposta completa: " . print_r($vendutoResponse, true));
                error_log("SKIANET setVenduto - Tipo risposta: " . gettype($vendutoResponse));

                // La funzione setVenduto ritorna una stringa, non un array
                // Controlla se la risposta contiene un errore
                if (strpos(strtolower($vendutoResponse), 'error') !== false || 
                    strpos(strtolower($vendutoResponse), 'errore') !== false) {
                    throw new Exception("setVenduto fallito: {$vendutoResponse}");
                }
                
                $msg = \sprintf(__('Ticket marcato come venduto %s: %s - Risposta: %s', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_id(), $currentCode, $vendutoResponse);
                $termeGestLogger->send($msg);

                // STEP 2: Poi chiama setPrenotazione
                $parameters = [
                    'idDisponibilita' => $idDisponibilita,
                    'codice' => $currentCode,
                    'Cognome' => $wcOrder->get_billing_last_name(),
                    'Nome' => $wcOrder->get_billing_first_name(),
                    'Telefono' => $wcOrder->get_billing_phone(),
                    'Note' => $wcOrder->get_customer_note(),
                    'Provincia' => $wcOrder->get_billing_state(),
                    'uomodonna' => (bool) $currentGender,
                    'Email' => $wcOrder->get_billing_email(),
                    'AllInclusive' => $allInclusive,
                    'Categoria' => $categoria,
                    'CodControllo' => '',
                    'protection' => $encryptedLocation,
                ]; 

                // LOG PARAMETRI CHIAMATA setPrenotazione
                $termeGestLogger->send("🔄 Chiamata setPrenotazione per codice {$currentCode}:");
                $termeGestLogger->send("   Parametri: " . print_r($parameters, true));
                error_log("SKIANET setPrenotazione - Parametri: " . print_r($parameters, true));


                $response = skianet_termegest_set_prenotazione(...array_values($parameters));

                 // LOG RISPOSTA setPrenotazione
                $termeGestLogger->send("📥 Risposta setPrenotazione: " . print_r($response, true));
                error_log("SKIANET setPrenotazione - Risposta completa: " . print_r($response, true));
                error_log("SKIANET setPrenotazione - Tipo risposta: " . gettype($response));

                if (! isset($response['status']) || $response['status'] === false) {
                    $errorMsg = isset($response['message']) ? $response['message'] : 'Errore sconosciuto';
                    throw new Exception("setPrenotazione fallita: {$errorMsg}");
                }

                // SE ARRIVIAMO QUI = TUTTO OK
                $codeSuccess = true;
                
                $msg = \sprintf(__('✅ Codice %s elaborato con successo (€%.2f)', PLUGIN_SKIANET_TEXT_DOMAIN), 
                    $currentCode, $itemPrice);
                if (isset($response['message'])) {
                    $msg .= ' - ' . $response['message'];
                }
                $termeGestLogger->send($msg);
                
            } catch (Throwable $throwable) {
                // ERRORE - Salva dettagli ma continua
                $codeSuccess = false;
                $codeError = $throwable->getMessage();
                
                $msg = \sprintf(__('❌ Codice %s fallito: %s', PLUGIN_SKIANET_TEXT_DOMAIN), 
                    $currentCode, $codeError);
                $termeGestLogger->send($msg);
                $termeGestLogger->flushLog();
                
                // NON fare throw - continua con il prossimo codice
                error_log("SKIANET: Continuo con il prossimo codice dopo errore: {$codeError}");
            }

            // TRACKING DEI RISULTATI
            if ($codeSuccess) {
                $successfulCodes[] = [
                    'code' => $currentCode,
                    'item' => $itemName,
                    'price' => $itemPrice
                ];
            } else {
                $failedCodes[] = [
                    'code' => $currentCode,
                    'item' => $itemName,
                    'price' => $itemPrice,
                    'error' => $codeError
                ];
            }

            $termeGestLogger->send($msg);
            $termeGestLogger->flushLog();

        }
    }

    $successCount = count($successfulCodes);
    $failedCount = count($failedCodes);

    if ($failedCount === 0) {

        $msg = \sprintf(__('Tutti i %d codici elaborati con successo per ordine %s', PLUGIN_SKIANET_TEXT_DOMAIN), $totalCodes, $wcOrder->get_id());
        $wcOrder->add_order_note($msg);

        // skianet_send_booking_success_email($wcOrder, $successfulCodes, $totalCodes);

    } else if ($successCount > 0) {

        $msg = \sprintf(__('⚠️ Elaborazione parziale ordine %s: %d/%d codici riusciti', PLUGIN_SKIANET_TEXT_DOMAIN), 
        $wcOrder->get_id(), $successCount, $totalCodes);
        $wcOrder->add_order_note($msg);

        skianet_send_booking_partial_email($wcOrder, $successfulCodes, $failedCodes, $successCount, $totalCodes);

    } else {
        $msg = \sprintf(__('💥 Tutti i %d codici sono falliti per ordine %s', PLUGIN_SKIANET_TEXT_DOMAIN), $totalCodes, $wcOrder->get_id());
        $wcOrder->add_order_note($msg);

        skianet_send_booking_failure_email($wcOrder, $failedCodes, $totalCodes);
    }
    
    $wcOrder->save(); // 💾 SALVA I META DATA!


    $termeGestLogger->send($msg);
    $termeGestLogger->flushLog();
    
    error_log("SKIANET: Elaborazione completata per ordine {$orderId} - Successi: {$successCount}, Fallimenti: {$failedCount}");
}
