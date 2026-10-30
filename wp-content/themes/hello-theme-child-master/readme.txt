=== Hello Elementor Child — La Via delle Terme ===

Template: hello-elementor
Requires PHP: 8.0
License: GNU General Public License v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Child theme di Hello Elementor per laviadelleterme.it.

== Struttura ==

Una cartella = una funzionalità. Ogni cartella in features/ contiene il PHP, i CSS,
i JS e il proprio hook wp_enqueue_scripts con le condizioni su cui caricarli: per
capire o modificare una funzionalità basta aprire la sua cartella. functions.php
carica solo style.css e l'elenco dei moduli (vedi lì il perché dell'ordine).

* features/global/          asset di tutto il sito: menu mobile e off-canvas,
                            script.js, notice WooCommerce, caroselli immagini di
                            Elementor (slide di altezza uniforme e frecce a disco
                            sotto la foto)
* features/shop/            catalogo, scheda prodotto, carrello
* features/promo-pages/     pagine protette da password (plugin PPWP): base
                            condivisa caricata ovunque, perché PPWP può proteggere
                            qualsiasi pagina, più gli scostamenti della sola
                            /promozioni-speciali/
* features/checkout/        stile della checkout (parti condivise con order-pay) e
                            aspetto dei campi carta dentro l'iframe Stripe
* features/order-pay/       evidenziazione della checkbox termini e layout della pagina
* features/thankyou/        pagina order-received: stato reale del pagamento e polling
* features/booking-status/  badge di riga ordine, condiviso fra order-received e order-pay
* features/satispay/        recupero dell'ordine quando l'utente annulla il pagamento Satispay
* features/my-account/      URL di login/registrazione, redirect post accesso e relativo stile
                            (lost-password.css: stile della sola pagina password dimenticata)
* features/newsletter/      consenso newsletter (registrazione, account, checkout) e
                            sincronizzazione con MailUp
* features/controllo-codici/ shortcode con le giacenze dei codici licenza (solo staff)

Un modulo può essere caricato su tutto il sito (global, shop, promo-pages): quando il
markup che stila lo stampano contenuti Elementor o plugin di terze parti non esiste un
conditional tag affidabile su cui restringere l'enqueue. Resta comunque un modulo a sé.

inc/ contiene il codice trasversale, che non appartiene a nessuna pagina:

* inc/ga4-elementor-compat.php      eventi GA4 che gli hook WooCommerce non fanno
                                    scattare sulle pagine costruite con Elementor
* inc/elementor-element-cache.php   esclude dalla cache degli elementi di Elementor i
                                    widget il cui testo il tema riscrive in base alla
                                    richiesta
* inc/performance-optimization.php  disattiva Gutenberg, emoji, oEmbed, XML-RPC, commenti

Alla radice, oltre a functions.php:

* style.css                  solo regole realmente globali (header, mini carrello,
                             pulsanti WooCommerce). Non è una discarica: se una regola
                             appartiene a una pagina, va nel modulo di quella pagina.
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

woocommerce/ è riservata agli override dei template WooCommerce e ai template PDF:
non aggiungere lì cartelle di modulo, i percorsi sono riservati da WooCommerce.

== Aggiungere un modulo ==

1. Creare features/<nome>/ con <nome>.php, che aggancia wp_enqueue_scripts a
   priorità 20 e accoda i propri file con laviadelleterme_enqueue_style() /
   laviadelleterme_enqueue_script() (percorso relativo alla radice del tema).
2. Se l'enqueue usa funzioni WooCommerce (is_checkout(), is_wc_endpoint_url()…),
   uscire prima con ! class_exists( 'WooCommerce' ).
3. Aggiungere il file all'elenco in functions.php, nel punto giusto: l'ordine
   dell'elenco è l'ordine dei fogli in pagina.

== Versioning degli asset ==

Ogni file è accodato con la versione dichiarata in style.css: va alzata a ogni
modifica di CSS o JS, altrimenti i browser continuano a servire la copia in cache
dopo il deploy.
