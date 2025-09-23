<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly for security
}

function send_booking_details($order, $send_to_admin = false) {

    error_log('send_booking_details called');

    if (!$order) {
        error_log('Order object is not valid in send_booking_details');
        return;
    }

    $order_data = is_object($order) ? $order : wc_get_order($order);
    if (empty($order_data)) {
        error_log('Order data is empty for order ID: ' . $order);
        return;
    }

    $order_id = $order_data->get_id();
    $billing_email = $order_data->get_billing_email();
    $order_date = $order_data->get_date_created()->format('d/m/Y G:i');
    
    $fromCalendar = (int) $order_data->get_meta('skn-custom-from-calendar');
    if ($fromCalendar === 0) {
        error_log("Order {$order_id} has no calendar bookings - skipping booking email");
        return;
    }

    // $skn_custom_location = get_post_meta($order_id, 'skn-custom-location', true);
    // $skn_custom_event = get_post_meta($order_id, 'skn-custom-event', true);
    // $skn_custom_qty = get_post_meta($order_id, 'skn-custom-qty', true);

    $order_details = [];
    $hasBookingItems = false;
    $nonBookingProducts = []; // AGGIUNTO: Array per prodotti senza metadati
    $hasNonBookingItems = false;
    $bookingData = []; // CORREZIONE: Nome variabile corretto

    $skn_custom_sex_female = get_post_meta($order_id, 'skn-custom-sex-female', true);
    $skn_custom_sex_male = get_post_meta($order_id, 'skn-custom-sex-male', true);
    $skn_custom_sex_male = !empty($skn_custom_sex_male) ? $skn_custom_sex_male : '0';
    $skn_custom_sex_female = !empty($skn_custom_sex_female) ? $skn_custom_sex_female : '0';

    foreach ($order_data->get_items() as $item) {
        $product_name = $item->get_name();
        $quantity = $item->get_quantity();
        $total = $item->get_total();

        $product_id = $item['product_id'];
        $variation_id = $item->get_variation_id();

        // Verifica se questo item ha dati prenotazione
        $itemHasBooking = false;
        $itemBookingData = [];

        foreach (['skn-custom-id', 'skn-custom-location', 'skn-custom-event', 'skn-custom-qty'] as $bookingMeta) {
            $metaValue = $item->get_meta($bookingMeta);
            if (!empty($metaValue)) {
                $itemHasBooking = true;
                $itemBookingData[$bookingMeta] = $metaValue;
            }
        }

        if ($itemHasBooking) {
            $hasBookingItems = true;
            // Salva i dati di prenotazione del primo item con booking trovato
            if (empty($bookingData)) {
                $bookingData = [
                    'location' => $itemBookingData['skn-custom-location'] ?? '',
                    'event' => $itemBookingData['skn-custom-event'] ?? '',
                    'qty' => $itemBookingData['skn-custom-qty'] ?? '',
                ];
                error_log("Using booking data from item: " . print_r($bookingData, true));
            }

             // Estrai i dati specifici di QUESTO prodotto
            $item_location = $itemBookingData['skn-custom-location'] ?? 'Non specificato';
            $item_event = $itemBookingData['skn-custom-event'] ?? 'Non specificato';
            $item_qty = $itemBookingData['skn-custom-qty'] ?? 'Non specificato';

            $codes = fetch_license_codes($order_id, $product_id, $variation_id);
             // Se abbiamo codici, aggiungiamoli al testo del dettaglio ordine
            $formatted_codes = '';
            if (!empty($codes) && is_array($codes)) {
                $formatted_codes = implode("\n", $codes);
            }
    
            $codes_info = '';
            if (!empty($formatted_codes)) {
                $codes_info = "<br><strong>Codici Coupon:</strong> <span style='color: #0074A0;'>$formatted_codes</span>";
            }
            
            // Aggiungi info gender (condivise per l'ordine)
            $gender_info = [];
            if ($skn_custom_sex_male > 0) {
                $gender_info[] = "<strong>Uomini:</strong> $skn_custom_sex_male";
            }
            if ($skn_custom_sex_female > 0) {
                $gender_info[] = "<strong>Donne:</strong> $skn_custom_sex_female";
            }
            $gender_text = !empty($gender_info) ? " (" . implode(', ', $gender_info) . ")" : "";

            // ✅ COSTRUISCI DETTAGLIO COMPLETO PER QUESTO PRODOTTO
            $product_detail = "<div style='margin-bottom: 15px; padding: 10px; border-left: 3px solid #0074A0;'>";
            $product_detail .= "<strong>Prodotto:</strong> <span style='color: #0074A0;'>$product_name</span><br>";
            $product_detail .= "<strong>Quantità:</strong> <span style='color: #0074A0;'>$quantity</span><br>";
            $product_detail .= "<strong>Totale:</strong> <span style='color: #0074A0;'>€$total</span>$codes_info<br>";
            $product_detail .= "<strong>Location:</strong> <span style='color: #0074A0;'>$item_location</span><br>";
            $product_detail .= "<strong>Data evento:</strong> <span style='color: #0074A0;'>$item_event</span><br>";
            $product_detail .= "</div>";
            
            $order_details[] = $product_detail;

        } else {
            $hasNonBookingItems = true;
            $nonBookingProducts[] = [
                'name' => $product_name,
                'quantity' => $quantity,
                'total' => $total,
                'product_id' => $product_id,
                'variation_id' => $variation_id
            ];
        }
    }

    if (!$hasBookingItems) {
        error_log("Order {$order_id} has no booking items - skipping booking email");
        return;
    }

    // CONTROLLO SPECIFICO per ordini misti
    if ($hasBookingItems && $hasNonBookingItems) {
        error_log("Order {$order_id} is MIXED ORDER");
        error_log("Non-booking products found: " . count($nonBookingProducts));
        
        // Log dei prodotti senza metadati
        foreach ($nonBookingProducts as $product) {
            error_log("Non-booking product: {$product['name']} (Qty: {$product['quantity']}, Total: €{$product['total']})");
        }
        
        // AGGIUNTO: Chiama la funzione per i prodotti non-booking
        error_log("Calling coupon_sent_order_status_not_booked for non-booking products");
        try {
            coupon_sent_order_status_not_booked($order_data, $send_to_admin, true); // forced = true per ordini misti
            error_log("Successfully called coupon function for mixed order {$order_id}");
        } catch (Exception $e) {
            error_log("Error calling coupon function for order {$order_id}: " . $e->getMessage());
        }
        
    } else {
        error_log("Order {$order_id} contains ONLY booking items");
        $nonBookingSection = "";
    }  

    $order_details_text = implode("<br>", $order_details);

    $policy_info = "<br><br><strong>Dichiarazioni e Informative:</strong><br>";
    $policy_info .= "<p>Il Sottoscritto/a <strong>DICHIARA</strong> sotto la propria responsabilità di avere preso visione delle norme comportamentali per i servizi offerti dalla struttura, a disposizione alla reception della spa, ed in particolare:</p>";
    $policy_info .= "<ul>";
    $policy_info .= "<li>Di essere a conoscenza che l'uso di sauna e bagno turco non sono idonei a coloro che hanno disturbi di pressione arteriosa e presenza di patologia a carico del sistema venoso superficiale e profondo.</li>";
    $policy_info .= "<li>Dichiara altresì di godere di sana e robusta costituzione e di essersi sottoposto di recente a visita medica per accertare la propria idoneità fisica ed esonera pertanto la struttura da qualsiasi responsabilità.</li>";
    $policy_info .= "<li>Dichiara di non accusare sintomi quali: febbre, tosse, difficoltà respiratorie.</li>";
    $policy_info .= "<li>Dichiara di non aver soggiornato in zone e/o Paesi con presunta trasmissione comunitaria.</li>";
    $policy_info .= "</ul>";
    $policy_info .= "<p><strong>Politica di cancellazione:</strong> E' possibile cancellare la prenotazione fino a 48 ORE prima, dopo non è più possibile ed il coupon sarà riscattato in automatico.</p>";

    $policy_info .= "<p>Ai sensi degli articoli 13 e 23 del DLgs. 196/03, del Regolamento EU 679/2016 e del Dlgs 101/2018 (Codice sulla protezione dei dati personali), l'interessato dichiara di essere stato adeguatamente informato ed esprime il proprio consenso all'utilizzo dei dati personali che lo riguardano, con particolare riferimento ai dati che la legge definisce come \"sensibili\", nei limiti di quanto indicato nell'informativa.</p>";
    $policy_info .= "<p>Dichiaro di avere preso visione del regolamento interno che definisce le modalità per la custodia dei valori accettandone i contenuti.</p>";

    $email_body = "<p>Grazie per la tua prenotazione. Di seguito i dettagli dell'ordine:</p><br>";
    $email_body .= $order_details_text . $policy_info;
        
    send_email_booked($billing_email, 'Conferma Prenotazione', 'Conferma della tua prenotazione', $email_body, $send_to_admin);
}

function send_email_booked($to_email, $subject, $heading, $body, $send_to_admin = false) {

    $recipient = $send_to_admin ? 'francesco.zerbinato@gmail.com' : $to_email;

    if (!$recipient) {
        error_log('No email address provided for send_email');
        return;
    }
    
    $mailer = WC()->mailer();
    $wrapped_message = $mailer->wrap_message($heading, $body);
    $wc_email = new WC_Email();
    $html_message = $wc_email->style_inline($wrapped_message);
    
    try {
        $mailer->send($recipient, $subject, $html_message);
    } catch (Exception $e) {
        error_log("Error sending email to {$recipient}: " . $e->getMessage());
    }
    
    if (empty($mailer)) {
        error_log("Failed to send email to {$recipient} for order");
    }
    
}
// Usa l'hook woocommerce_order_status_booked
// add_action('woocommerce_order_status_booked', 'trigger_email_when_booked', 10, 2);

function trigger_email_when_booked($order_id, $order) {
    // Qui non è necessario controllare lo stato precedente,
    // perché l'hook viene attivato solo quando lo stato è già "booked"
    send_booking_details($order);
}

// Add order actions to the order actions dropdown
add_action('woocommerce_order_actions', 'add_order_meta_box_actions_booked');
function add_order_meta_box_actions_booked($actions) {    
    $actions['send_booking_confirmation'] = 'Send Booking Confirmation To Customer';
    $actions['send_booking_confirmation_to_admin'] = 'Send Booking Confirmation To Admin';
    return $actions;
}

// Handle the "Send Booking Confirmation To Customer" order action
add_action('woocommerce_order_action_send_booking_confirmation', function($order) {
    if (!$order) {
        error_log('woocommerce_order_action_send_booking_confirmation: No order object provided.');
        return;
    }
    $order_id = is_object($order) ? $order->get_id() : $order;
    try {
        send_booking_details($order);
    } catch (Exception $e) {
        error_log("Error executing send_booking_details for order ID: {$order_id}. Error: " . $e->getMessage());
    }
});

// Handle the "Send Booking Confirmation To Admin" order action
add_action('woocommerce_order_action_send_booking_confirmation_to_admin', function($order) {
    if (!$order) {
        error_log('woocommerce_order_action_send_booking_confirmation_to_admin: No order object provided.');
        return;
    }
    $order_id = is_object($order) ? $order->get_id() : $order;
    try {
		send_booking_details($order, true);

    } catch (Exception $e) {
        error_log("Error executing send_booking_details for order ID: {$order_id}. Error: " . $e->getMessage());
    }
});
