# Test da fare — merge tema su `main` (cfca6b7, 2026-08-25)

Checklist dei test live rimasti dopo il merge di `refactor/struttura-tema` e
`fix/bug-funzionali-tema` su `main`. Il push è già andato, quindi tutto è su
**staging2**; la produzione si aggiorna solo via FTP.

Già verificato: bottoni wallet express in riga da desktop/tablet e in colonna
da mobile (checkout normale).

---

## Priorità alta — raggio d'azione su tutto il sito

- [ ] **Menu mobile e off-canvas** — `assets/js/script.js` è stato riscritto
      senza jQuery. Apri e chiudi l'hamburger, controlla lo stato dell'icona e
      l'off-canvas.
- [ ] **Secondo header allo scroll** — scrolla una pagina lunga: l'header
      secondario deve comparire scendendo e sparire tornando in cima.
- [ ] **Pagine promo protette da password** — il form PPWP deve restare
      stilato (`assets/css/promo-pages.css`, globale) e il campo password deve
      mostrare il testo in chiaro (comportamento voluto, password condivisa).
- [ ] **My Account** — è l'unico CSS diventato condizionale
      (`is_account_page() || is_page('login-e-registrazione')` in
      `functions.php`). Controlla che escano stilate: dashboard, login,
      **registrazione** e **password dimenticata**. Una pagina senza stile
      significa che la condizione dell'enqueue non la copre.
- [ ] **Redirect dopo il login** — da sloggato apri `/my-account/view-order/<id>`:
      dopo il login devi tornare su quell'ordine, non sulla dashboard.
      (Logica riscritta nell'hardening: `laviadelleterme_is_local_url` +
      `is_account_page()` al posto della `strpos` su `REQUEST_URI`.)

## Priorità media — servono un ordine di prova

- [ ] **Thank-you, ordine pagato** — heading "Ordine ricevuto!", badge verde
      "Prenotazione confermata" (o ambra sui prodotti non prenotabili), nessun
      box CTA, nessun polling.
- [ ] **Thank-you, ordine non pagato** — heading "Ordine in attesa di
      pagamento", badge rosso, box "Completa il pagamento", polling attivo.
- [ ] **Thank-you da ospite** (non loggato) — il gate su `is_user_logged_in()`
      è stato tolto proprio qui: l'heading deve essere riscritto anche per gli
      ordini guest.
- [ ] **Il badge NON deve comparire** in tre posti — è il punto più fragile del
      modulo `booking-status/` estratto, perché usa lo stesso hook di queste
      superfici ed è tenuto fuori solo dalla funzione-gate:
    - [ ] email cliente
    - [ ] email admin "nuovo ordine" (parte a ordine ancora impagato)
    - [ ] My Account → Visualizza ordine
- [ ] **Order-pay** — badge di stato, layout a due colonne da ≥1024px, e il
      caso "termini non spuntati": riga rossa + scroll e focus sulla checkbox.
- [ ] **Controllo codici** — una volta con un utente `manage_woocommerce`
      (conteggi visibili) e una con un utente senza (deve uscire "Contenuto
      riservato allo staff.", non una pagina vuota). Il CSS ora arriva dal
      footer: guarda se c'è un lampo di contenuto non stilato.
- [ ] **PDF fattura e packing slip** da un ordine vero — nome articolo, meta
      della prenotazione e prezzi devono restare formattati (l'escaping è stato
      aggiunto solo su sku/peso/quantità/classi, di proposito).

## Sospetto concreto da verificare

- [ ] **Checkbox privacy fuori dal checkout** — la regola `.privacy-checkout` è
      passata da `style.css` (globale) a `checkout/checkout.css` (solo
      `is_checkout()`), assumendo che quel markup esca solo nel checkout. Quella
      classe non esiste da nessuna parte nel repo: la stampa Elementor o un
      plugin, quindi l'assunzione non è verificabile dal codice. Se c'è una
      checkbox privacy anche nel **form di registrazione** o in un form
      contatti, lì ha perso l'allineamento → in quel caso la regola va rimessa
      in un file globale.

## Decisioni ancora aperte (non sono test, sono da decidere)

- La capability degli shortcode `controllo-codici` è `manage_woocommerce`,
  scelta a tavolino: da confermare che chi consulta davvero quella pagina abbia
  quel permesso.
- `performance-optimization.php`: il deregister di `heartbeat` fuori da
  `post.php` lo toglie anche alle liste ordini in admin, e la disattivazione di
  Gutenberg chiude la porta ai blocchi Cart/Checkout di WooCommerce. Segnalati
  e **non** modificati: sono scelte di prodotto.
- I template PDF sono fork di quelli stock del plugin, che non è in questo
  repo: la divergenza si può misurare solo contro la copia installata sul
  server.

---

Promemoria: ogni volta che cambia un CSS/JS del tema va alzato `Version:` in
`style.css` (ora **1.0.42**), altrimenti dopo il deploy i browser continuano a
servire la copia in cache.
