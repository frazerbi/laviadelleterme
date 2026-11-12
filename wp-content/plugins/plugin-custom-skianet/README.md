# Custom Skianet Plugin

Plugin di La Via delle Terme per TermeGest: prenotazioni, assegnazione dei codici, sync `setVenduto` /
`setPrenotazione`, email di conferma e coupon PDF. Lo stesso codice gira su **laviadelleterme.it** e su
**termeshop.com**, che differiscono solo per il profilo scelto in `wp-config.php` (`includes/site-config.php`).

Su laviadelleterme non serve nessuna configurazione: senza costanti vale il profilo `laviadelleterme`.

## Attivarlo su termeshop

Su termeshop il plugin prende il posto di `plugin-custom-termeshop` per tutto il flusso coupon. Le prenotazioni restano
spente (`bookings => false` nel profilo).

### 1. Prerequisiti

- **PHP 8.2 o superiore** con `ext-soap`.
- **Stato ordine `not-booked`** registrato (Custom Order Status), con slug esattamente `not-booked`. Il plugin porta
  ogni ordine pagato `processing` → `completed` → `not-booked`. Se lo stato non esiste, WooCommerce lo sostituisce con
  `pending` e gli ordini pagati risultano "In attesa di pagamento".
- **`not-booked` contato come stato pagato**: se Custom Order Status ha l'opzione, va attivata. Senza, `is_paid()` e'
  falso per tutti gli ordini e l'evento GA4 `purchase` non parte (stesso guasto risolto ad agosto 2026 per
  `coupon-sent`).
- **WC License Delivery**: annotare lo stato di consegna dei codici (`processing` su laviadelleterme, il flusso
  testato). Ogni prodotto in vendita deve avere un blocco di codici libero caricato, altrimenti il carrello si ferma
  con un avviso.

### 2. `wp-config.php`

Prima della riga "That's all, stop editing!":

```php
define( 'WP_ENVIRONMENT_TYPE', 'production' );
define( 'SKIANET_SITE', 'termeshop' );
define( 'SKIANET_TERMEGEST_SECURITY_KEY', '…' );
```

- `WP_ENVIRONMENT_TYPE` e' obbligatoria: senza, il plugin dichiara l'ambiente `staging` e WordPress (e altri plugin)
  trattano il sito come uno di prova.
- La chiave e' quella del canale TermeGest di termeshop. Se nello stesso wp-config c'e' gia' un
  `TERMEGEST_SECURITY_TOKEN`, e' il valore che usa oggi il vecchio plugin; altrimenti va chiesta a TermeGest. Il
  profilo termeshop non ha una chiave di riserva: senza la costante `setVenduto` non parte e l'ordine riceve una nota
  "ATTENZIONE … NON sincronizzati".

### 3. Caricare il plugin (senza attivarlo)

Costruire `vendor/` in locale, senza i pacchetti di sviluppo:

```bash
cd wp-content/plugins/plugin-custom-skianet
composer install --no-dev --optimize-autoloader --no-scripts
```

Caricare la cartella in `wp-content/plugins/plugin-custom-skianet/` **senza `node_modules/`**, con `vendor/`,
`lib/fpdf/` e `assets/img/header-coupon-termeshop.jpg`.

### 4. Il passaggio

**Non disattivare `plugin-custom-termeshop`**: registra lo stato `coupon-sent` di tutti gli ordini storici, che senza di
lui diventerebbero ordini con uno stato sconosciuto e non pagato. Si tolgono solo i suoi `require` operativi. In
`plugin-terme.php` deve restare:

```php
require_once(  PLUGIN_TERME_PATH . '/Order Management/add_status.php' );
// require_once(  PLUGIN_TERME_PATH . '/Order Management/autocomplete-order.php' );
// require_once(  PLUGIN_TERME_PATH . '/Order Management/codes-to-termegest.php' );
// require_once(  PLUGIN_TERME_PATH . '/Order Management/send-emails.php' );
// require_once(  PLUGIN_TERME_PATH . '/Order Management/send-emails-to-admin.php' );
```

Caricare quel file e **subito dopo** attivare "Custom Skianet Plugin". I due flussi attivi insieme mandano due email e
due `setVenduto` per ordine, e le azioni ordine `send_coupons` / `send_coupons_to_admin` hanno la stessa chiave nei due
plugin. Un ordine che cade nei secondi fra le due operazioni riceve comunque i codici da WC License Delivery; email e
sync si recuperano a mano dalle azioni dell'ordine.

### 5. Verifica

Un ordine di prova consuma comunque un codice vero su TermeGest (con uno sconto del 100% il flusso parte lo stesso, ma
il codice risulta venduto a 0). Sull'ordine:

- stato finale `not-booked`;
- nota "TermeGest: n/n codici sincronizzati" (se compare "ATTENZIONE … NON sincronizzati", la nota contiene la
  risposta di TermeGest: di solito chiave mancante o rifiutata);
- email al cliente con il PDF: intestazione termeshop, titolo azzurro, firma "Il team Termeshop", "I coupon NON sono
  prorogabili";
- azione ordine "Send coupons to admin": deve arrivare a `info@termeshop.com`;
- in Strumenti → Eventi cron (o `wp cron event list`) **non** deve esserci `termegest_check_availability`.

Log: WooCommerce → Stato → Log, sorgente `skianet-codici`.

### Tornare indietro

Disattivare "Custom Skianet Plugin" e togliere i commenti dai `require` in `plugin-terme.php`.
