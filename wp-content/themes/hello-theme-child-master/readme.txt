=== Hello Elementor Child — La Via delle Terme ===

Template: hello-elementor
Requires PHP: 8.0
License: GNU General Public License v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Child theme di Hello Elementor per laviadelleterme.it.

== Struttura ==

Una cartella per modulo funzionale, ognuna con il proprio PHP e i propri asset,
inclusa singolarmente da functions.php:

* checkout/       stile della checkout (parti condivise anche con order-pay)
* order-pay/      evidenziazione della checkbox termini e layout della pagina
* thankyou/       pagina order-received: stato reale del pagamento e polling
* booking-status/ badge di riga ordine, condiviso fra order-received e order-pay
* satispay/       recupero dell'ordine quando l'utente annulla il pagamento Satispay
* my-account/     URL di login/registrazione, redirect post accesso e relativo stile
                 (lost-password.css: stile della sola pagina password dimenticata)
* controllo-codici/  shortcode con le giacenze dei codici licenza (solo staff)
* promozioni-speciali/ form di sblocco PPWP della pagina /promozioni-speciali/

Alla radice del tema, oltre a style.css e functions.php:

* ga4-elementor-compat.php     eventi GA4 che gli hook WooCommerce non fanno scattare
                               sulle pagine costruite con Elementor
* elementor-element-cache.php  esclude dalla cache degli elementi di Elementor i widget
                               il cui testo il tema riscrive in base alla richiesta
* performance-optimization.php disattiva Gutenberg, emoji, oEmbed, XML-RPC, commenti

assets/css e assets/js contengono gli asset globali e quelli condivisi da più pagine:

* style.css                  solo regole realmente globali (header, mini carrello,
                             pulsanti WooCommerce). Non è una discarica: se una regola
                             appartiene a una pagina, va nel file di quella pagina.
                             Qui stanno anche le variabili --lvdt-button-* con
                             l'aspetto standard dei pulsanti (colori del kit
                             Elementor, Muli maiuscolo, padding, raggio): le
                             riusano checkout, order-pay, thank you, notice e
                             password dimenticata, così i pulsanti che WooCommerce
                             stampa fuori da Elementor restano tutti uguali.
                             Qui sta anche la scala --lvdt-radius-sm/md/lg dei
                             raggi degli angoli (controlli / messaggi /
                             contenitori): nessun foglio scrive più un valore a
                             mano, ne giravano sette diversi. Fuori scala restano
                             solo le forme — pillole e cerchi — che non sono una
                             misura ma una geometria.
                             Il blocco dei pulsanti è dichiarato su :root e su body: i colori
                             globali del kit Elementor sono definiti sul body, su
                             :root non si risolverebbero.
                             Le regole che applicano queste variabili sono scritte
                             SENZA !important e con la specificità minima che
                             basta a battere woocommerce.css: sono il default del
                             pulsante, non l'ultima parola, così quello che si
                             imposta dall'editor Elementor sul widget vince.
                             L'unica eccezione, spiegata sul posto, è il blocco
                             button.woocommerce-Button in style.css.
* assets/css/shop.css        catalogo, scheda prodotto, carrello
* assets/css/promo-pages.css pagine protette da password (plugin PPWP): base
                             condivisa, caricata ovunque perché PPWP può
                             proteggere qualsiasi pagina. Gli scostamenti della
                             singola pagina stanno nel modulo di quella pagina
                             (vedi promozioni-speciali/)
* assets/css/wc-notices.css  notice WooCommerce, in tutto il sito
* assets/css/stripe-upe-appearance.css  campi carta dentro l'iframe Stripe
* assets/css/mobile-menu-style.css      menu mobile e off-canvas
* assets/css/elementor-carousel.css     caroselli immagini di Elementor: slide
                                        di altezza uniforme (clamp in vh, con i
                                        breakpoint predefiniti di Elementor) e
                                        frecce prev/next come dischi blu in
                                        basso a destra

Un foglio sta in assets/css, e non in una cartella di modulo, quando il markup che
stila è stampato su più pagine oppure da contenuti Elementor / plugin di terze parti,
cioè quando non esiste un conditional tag affidabile su cui restringere l'enqueue.

woocommerce/ è riservata agli override dei template WooCommerce e ai template PDF:
non aggiungere lì cartelle di modulo, i percorsi sono riservati da WooCommerce.

== Versioning degli asset ==

functions.php accoda ogni file con la versione dichiarata in style.css: va alzata
a ogni modifica di CSS o JS, altrimenti i browser continuano a servire la copia
in cache dopo il deploy.
