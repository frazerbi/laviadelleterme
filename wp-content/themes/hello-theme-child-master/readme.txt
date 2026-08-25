=== Hello Elementor Child — La Via delle Terme ===

Template: hello-elementor
Requires PHP: 8.0
License: GNU General Public License v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Child theme di Hello Elementor per laviadelleterme.it.

== Struttura ==

Una cartella per modulo funzionale, ognuna con il proprio PHP e i propri asset,
inclusa singolarmente da functions.php:

* checkout/       stile della checkout e della pagina order-pay
* order-pay/      evidenziazione della checkbox termini quando blocca il pagamento
* thankyou/       pagina order-received: stato reale del pagamento, badge, polling
* satispay/       recupero dell'ordine quando l'utente annulla il pagamento Satispay
* my-account/     URL di login/registrazione, redirect post accesso e relativo stile
* controllo-codici/  shortcode con le giacenze dei codici licenza (solo staff)

assets/css e assets/js contengono gli asset globali e quelli condivisi da più pagine:

* style.css                  solo regole realmente globali (header, mini carrello,
                             pulsanti WooCommerce). Non è una discarica: se una regola
                             appartiene a una pagina, va nel file di quella pagina.
* assets/css/shop.css        catalogo, scheda prodotto, carrello
* assets/css/promo-pages.css pagine protette da password (plugin PPWP)
* assets/css/wc-notices.css  notice WooCommerce, in tutto il sito
* assets/css/booking-status.css  badge prenotazione (order-received + order-pay)
* assets/css/stripe-upe-appearance.css  campi carta dentro l'iframe Stripe
* assets/css/mobile-menu-style.css      menu mobile e off-canvas

Un foglio sta in assets/css, e non in una cartella di modulo, quando il markup che
stila è stampato su più pagine oppure da contenuti Elementor / plugin di terze parti,
cioè quando non esiste un conditional tag affidabile su cui restringere l'enqueue.

woocommerce/ è riservata agli override dei template WooCommerce e ai template PDF:
non aggiungere lì cartelle di modulo, i percorsi sono riservati da WooCommerce.

== Versioning degli asset ==

functions.php accoda ogni file con la versione dichiarata in style.css: va alzata
a ogni modifica di CSS o JS, altrimenti i browser continuano a servire la copia
in cache dopo il deploy.
