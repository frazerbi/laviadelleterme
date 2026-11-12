<?php
/**
 * Configurazione per sito.
 *
 * Lo stesso plugin gira su laviadelleterme.it e su termeshop: quello che cambia
 * fra i due (marchio, email admin, testi dell'email coupon, chiave TermeGest) sta
 * in un profilo, scelto in wp-config.php con
 *
 *     define( 'SKIANET_SITE', 'termeshop' );
 *
 * Senza la costante vale 'laviadelleterme', quindi su laviadelleterme non serve
 * toccare il wp-config: i valori del profilo sono quelli che erano scritti nel codice.
 *
 * La chiave TermeGest si puo' sovrascrivere a parte con
 * SKIANET_TERMEGEST_SECURITY_KEY. Il profilo termeshop non ne ha una di default:
 * li' la costante e' obbligatoria, perche' ripiegare sulla chiave di
 * laviadelleterme manderebbe i codici al canale sbagliato senza che nessuno se ne
 * accorga. Senza chiave setVenduto non parte e l'ordine riceve la nota di errore.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Profilo del sito corrente.
 *
 * @return array{
 *     name: string,
 *     admin_email: string,
 *     header_image: string,
 *     pdf_title_rgb: int[],
 *     coupon_links_html: string,
 *     no_links_variations: int[],
 *     termegest_security_key: string,
 *     bookings: bool
 * }
 */
function skianet_site(): array {
    static $site = null;

    if (null !== $site) {
        return $site;
    }

    $profiles = array(
        'laviadelleterme' => array(
            'name'                   => 'La Via Delle Terme',
            'admin_email'            => 'biglietti@laviadelleterme.it',
            'header_image'           => 'header-la-via-delle-terme.jpg',
            // Colore del titolo nel PDF coupon (RGB), #0074A0 come i link dell'email.
            'pdf_title_rgb'          => array(0, 116, 160),
            'coupon_links_html'      => "
            <p style='margin-top:60px;font-size:12px;'>Alcuni link utili: <br>
            Solo per i coupon di ingresso SPA, la prenotazione è obbligatoria al seguente link:
            <a href='https://www.termegest.it/prenota.aspx' target='_blank' style='color:#0074A0; text-decoration:underline;'>termegest.it/prenota.aspx</a>.<br>
            Il coupon è prorogabile di 90 giorni con supplemento al link:
            <a href='https://laviadelleterme.it/shop/proroghe/' target='_blank' style='color:#0074A0; text-decoration:underline;'>laviadelleterme.it/shop/proroghe/</a>
            </p>
        ",
            // Variazioni (id di laviadelleterme.it) per cui l'email non mostra i link utili.
            'no_links_variations'    => array(28747, 1677, 1678, 1690, 392, 393, 394, 1907, 1908, 1637, 1639, 1640, 21800),
            'termegest_security_key' => 'qpoz79nt1z3p2vcllpt2iqnz66c7zk3',
            'bookings'               => true,
        ),
        'termeshop' => array(
            'name'                   => 'Termeshop',
            'admin_email'            => 'info@termeshop.com',
            'header_image'           => 'header-coupon-termeshop.jpg',
            // L'azzurro di termeshop, #60A4B7: lo stesso dei link nell'email.
            'pdf_title_rgb'          => array(96, 164, 183),
            // Stesso blocco che mandava plugin-custom-termeshop.
            'coupon_links_html'      => "
            <p style='margin-top:60px;font-size:12px;'>Alcuni link utili: <br>
            Prenotazione obbligatoria online al seguente link: <a href='https://www.termegest.it/prenota.aspx' target='_blank' style='color:#60A4B7; text-decoration:underline;'>termegest.it/prenota.aspx</a>.<br>
            <span style='font-weight: bold;text-decoration: underline;'>I coupon NON sono prorogabili.</span></p>
            <p style='font-size:12px;'>P.s. Potrai sempre recuperare i codici di accesso sulla tua area riservata a questo link: <a href='https://termeshop.com/my-account/' target='_blank' style='color:#60A4B7; text-decoration:underline;'>https://termeshop.com/my-account/</a>.</p>
        ",
            'no_links_variations'    => array(),
            'termegest_security_key' => '',
            // Solo coupon: niente form di prenotazione, redirect PDP, cron di
            // disponibilita' e dichiarazione sanitaria al checkout.
            'bookings'               => false,
        ),
    );

    $key = defined('SKIANET_SITE') ? (string) SKIANET_SITE : 'laviadelleterme';

    if (!isset($profiles[$key])) {
        error_log(sprintf('[skianet] SKIANET_SITE "%s" sconosciuto, uso laviadelleterme.', $key));
        $key = 'laviadelleterme';
    }

    $site = $profiles[$key];

    if (defined('SKIANET_TERMEGEST_SECURITY_KEY') && '' !== (string) SKIANET_TERMEGEST_SECURITY_KEY) {
        $site['termegest_security_key'] = (string) SKIANET_TERMEGEST_SECURITY_KEY;
    }

    return $site;
}
