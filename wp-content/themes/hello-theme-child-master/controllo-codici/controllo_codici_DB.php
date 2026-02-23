<?php

function funzione_controllo_codici() {
    
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

    if ($conn->connect_error) {
        return '<p>Errore di connessione al database.</p>';
    }

    $prodotti = [
        1677  => 'P5 Hotel + Ingresso alle Terme (Valido per 2 persone) - Mezza giornata',
        1678  => 'P6 Hotel + Ingresso alle Terme (Valido per 2 persone) - Giornaliero',
        1604  => 'P7 Hotel + 2 Ingressi Terme (Valido per 2 persone)',
        394   => 'PL Suite Privata con Massaggio di Coppia',
        392   => 'PI Suite Privata con SPA - Bagno al Vapore + Idromassaggio',
        393   => 'PH Suite Privata con SPA - Bagno al Vapore + Sauna Finlandese',
        229   => 'P2 Ingresso Lunedì - Domenica - Mezza giornata',
        230   => 'P4 Ingresso Lunedì - Domenica - Giornaliero',
        225   => 'P1 Ingresso Lunedì - Venerdì - Mezza giornata',
        224   => 'P3 Ingresso Lunedì - Venerdì - Giornaliero',
        98149 => 'Bonus Terme and Wellness by LVDT',
        1690  => 'M5 Massaggi',
        1243  => 'W1 Proroghe Ingressi',
        1244  => 'W2 Proroghe Hotel + Ingressi',
        27370 => 'PM Ingresso Lunedì - Domenica 4 Ore Per Festività Natalizie',
        28750 => 'Veglione di Capodanno in Accappatoio - Monterosa',
        28749 => 'Veglione di Capodanno in Accappatoio - Saint Vincent',
        28748 => 'Veglione di Capodanno in Accappatoio - Genova',
        29044 => 'Hotel De La Ville 3 Notti + 2 Ingressi Terme + Veglione Di Capodanno',
    ];

    $stmt = $conn->prepare("SELECT COUNT(*) FROM `wp_wc_ld_license_codes` WHERE order_id = 0 AND product_id = ?");
    
    if (!$stmt) {
        return '<p>Errore nella preparazione della query.</p>';
    }

    $output = '<ul>';

    foreach ($prodotti as $product_id => $label) {
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        
        $output .= '<li><strong>' . esc_html($label) . ':</strong> ' . (int)$count . '</li>';
    }

    $output .= '</ul>';

    $stmt->close();
    $conn->close();

    return $output;
}

add_shortcode('controllo_codici_prezzo_pieno', 'funzione_controllo_codici');