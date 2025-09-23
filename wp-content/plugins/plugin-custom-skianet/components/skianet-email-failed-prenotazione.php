<?php

/**
 * 📧 Email di successo totale al cliente
 */
function skianet_send_booking_success_email($wcOrder, $successfulCodes, $totalCodes) {
    $mailer = WC()->mailer();
    
    $subject = sprintf(__('✅ Prenotazioni confermate per ordine #%s', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_order_number());
    
    $body = sprintf(__('Gentile %s,', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_billing_first_name()) . "\n\n";
    $body .= sprintf(__('Le sue %d prenotazioni sono state confermate con successo!', PLUGIN_SKIANET_TEXT_DOMAIN), $totalCodes) . "\n\n";
    
    $body .= __('DETTAGLI PRENOTAZIONI:', PLUGIN_SKIANET_TEXT_DOMAIN) . "\n";
    
    foreach ($successfulCodes as $success) {
        $body .= sprintf("✅ %s - Codice: %s (€%.2f)\n", $success['item'], $success['code'], $success['price']);
    }
    
    $body .= "\n" . sprintf(__('Ordine: #%s', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_order_number());
    $body .= "\n" . sprintf(__('Totale: €%.2f', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_total());
    
    $body .= "\n\n" . __('Grazie per aver scelto i nostri servizi!', PLUGIN_SKIANET_TEXT_DOMAIN);
    
    $mailer->send(
        $wcOrder->get_billing_email(),
        $subject,
        $mailer->wrap_message($subject, $body)
    );
    
    error_log("SKIANET: Email successo inviata a {$wcOrder->get_billing_email()}");
}

/**
 * ⚠️ Email di successo parziale (cliente + admin)
 */
function skianet_send_booking_partial_email($wcOrder, $successfulCodes, $failedCodes, $successCount, $totalCodes) {
    $mailer = WC()->mailer();
    
    // 📧 EMAIL AL CLIENTE
    $subject_customer = sprintf(__('⚠️ Prenotazioni parzialmente confermate - Ordine #%s', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_order_number());
    
    $body_customer = sprintf(__('Gentile %s,', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_billing_first_name()) . "\n\n";
    $body_customer .= sprintf(__('Abbiamo confermato %d su %d prenotazioni del suo ordine.', PLUGIN_SKIANET_TEXT_DOMAIN), $successCount, $totalCodes) . "\n\n";
    
    if (!empty($successfulCodes)) {
        $body_customer .= __('PRENOTAZIONI CONFERMATE:', PLUGIN_SKIANET_TEXT_DOMAIN) . "\n";
        foreach ($successfulCodes as $success) {
            $body_customer .= sprintf("✅ %s - Codice: %s (€%.2f)\n", $success['item'], $success['code'], $success['price']);
        }
        $body_customer .= "\n";
    }
    
    // Aggiunta sezione codici non prenotati/venduti
    if (!empty($failedCodes)) {
        $body_customer .= __('CODICI NON PRENOTATI/VENDUTI:', PLUGIN_SKIANET_TEXT_DOMAIN) . "\n";
        foreach ($failedCodes as $failed) {
            $body_customer .= sprintf("⚠️ %s - Codice: %s (€%.2f)\n", $failed['item'], $failed['code'], $failed['price']);
        }
        $body_customer .= "\n";
        $body_customer .= __('Per completare la prenotazione dei codici rimanenti, clicchi sul seguente link:', PLUGIN_SKIANET_TEXT_DOMAIN) . "\n";
        $body_customer .= "https://www.termegest.it/prenota.aspx\n\n";
    }

    $body_customer .= sprintf(__('Ordine: #%s', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_order_number()) . "\n";
    $body_customer .= __('Grazie per la pazienza.', PLUGIN_SKIANET_TEXT_DOMAIN);
    
    $mailer->send(
        $wcOrder->get_billing_email(),
        $subject_customer,
        $mailer->wrap_message($subject_customer, $body_customer)
    );
    
    // 📧 EMAIL ALL'ADMIN
    $subject_admin = sprintf(__('🚨 INTERVENTO RICHIESTO: Prenotazioni parziali ordine #%s', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_order_number());
    
    $body_admin = sprintf(__('Ordine: #%s (%s %s)', PLUGIN_SKIANET_TEXT_DOMAIN), 
        $wcOrder->get_order_number(),
        $wcOrder->get_billing_first_name(),
        $wcOrder->get_billing_last_name()) . "\n";
    $body_admin .= sprintf(__('Email: %s', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_billing_email()) . "\n\n";
    
    $body_admin .= sprintf(__('ELABORAZIONE PARZIALE: %d/%d codici riusciti', PLUGIN_SKIANET_TEXT_DOMAIN), $successCount, $totalCodes) . "\n\n";
    
    if (!empty($successfulCodes)) {
        $body_admin .= __('CODICI RIUSCITI:', PLUGIN_SKIANET_TEXT_DOMAIN) . "\n";
        foreach ($successfulCodes as $success) {
            $body_admin .= sprintf("✅ %s - %s (€%.2f)\n", $success['code'], $success['item'], $success['price']);
        }
        $body_admin .= "\n";
    }
    
    if (!empty($failedCodes)) {
        $body_admin .= __('CODICI FALLITI (RIPROCESSARE MANUALMENTE):', PLUGIN_SKIANET_TEXT_DOMAIN) . "\n";
        foreach ($failedCodes as $failed) {
            $body_admin .= sprintf("❌ %s - %s (€%.2f)\n", $failed['code'], $failed['item'], $failed['price']);
            $body_admin .= sprintf("   Errore: %s\n\n", $failed['error']);
        }
    }
    
    $body_admin .= sprintf(__('Link ordine: %s', PLUGIN_SKIANET_TEXT_DOMAIN), admin_url('post.php?post=' . $wcOrder->get_id() . '&action=edit'));
    
    $admin_email = get_option('admin_email');
    $mailer->send(
        $admin_email,
        $subject_admin,
        $mailer->wrap_message($subject_admin, $body_admin)
    );
    
    error_log("SKIANET: Email parziale inviata a cliente e admin");
}

/**
 * 💥 Email di fallimento totale al cliente
 */
function skianet_send_booking_failure_email($wcOrder, $failedCodes, $totalCodes) {
    $mailer = WC()->mailer();
    
    $subject = sprintf(__('⚠️ Fallimento totale prenotazioni ordine #%s', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_order_number());
    
    $body = sprintf(__('Ordine: #%s (%s %s)', PLUGIN_SKIANET_TEXT_DOMAIN), 
        $wcOrder->get_order_number(),
        $wcOrder->get_billing_first_name(),
        $wcOrder->get_billing_last_name()) . "\n";
    $body .= sprintf(__('Email: %s', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_billing_email()) . "\n";
    $body .= sprintf(__('Telefono: %s', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_billing_phone()) . "\n\n";
    
    $body .= sprintf(__('TUTTI I %d CODICI SONO FALLITI!', PLUGIN_SKIANET_TEXT_DOMAIN), $totalCodes) . "\n\n";
    
    $body .= __('DETTAGLI ERRORI:', PLUGIN_SKIANET_TEXT_DOMAIN) . "\n";
    foreach ($failedCodes as $failed) {
        $body .= sprintf("❌ %s - %s (€%.2f)\n", $failed['code'], $failed['item'], $failed['price']);
        $body .= sprintf("   Errore: %s\n\n", $failed['error']);
    }
    
    $body .= sprintf(__('Totale ordine: €%.2f', PLUGIN_SKIANET_TEXT_DOMAIN), $wcOrder->get_total()) . "\n";
    $body .= sprintf(__('Link ordine: %s', PLUGIN_SKIANET_TEXT_DOMAIN), admin_url('post.php?post=' . $wcOrder->get_id() . '&action=edit')) . "\n\n";
    
    $body .= "\n";
    $body .= __('Per completare la prenotazione dei codici rimanenti, clicchi sul seguente link:', PLUGIN_SKIANET_TEXT_DOMAIN) . "\n";
    $body .= "https://www.termegest.it/prenota.aspx\n\n";

    
    $admin_email = get_option('admin_email');
    $mailer->send(
        $admin_email,
        $subject,
        $mailer->wrap_message($subject, $body)
    );
    
    error_log("SKIANET: Email fallimento totale inviata all'admin");
}