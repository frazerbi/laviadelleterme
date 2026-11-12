# Pagina shop — proposta di layout (bozza, 2026-11-11)

Mockup della nuova `/shop/`, non implementato. Esclusa dal deploy (`--exclude=design/` in `deploy.yml`).

- **Canvas vivo**: https://claude.ai/artifact/8H6PMdGdjHEaEbyNUYtSCW (privato)
- **`shop-mockup.dc.html`**: copia del canvas a questa data. È un file del formato Design di claude.ai, non si
  apre da solo nel browser: serve come riferimento per il markup e i testi.
- **Pagina per il cliente**: https://claude.ai/artifact/TzV4qZ6uFiUofwxdCXf8u2 (privata finché non la si condivide
  dal menu Share). Copia in **`proposta-shop.html`**: HTML semplice e navigabile, con le foto incorporate in base64;
  l'interruttore funziona e si apre già su un'opzione con `#lun-ven`, `#tutti-i-giorni` o `#festivita-natalizie`;
  i pulsanti aprono le schede prodotto reali del sito. Le descrizioni brevi di Massaggio, Private SPA e Hotel lì
  sono già scritte (ricavate dai nomi prodotto), al posto dei segnaposto del canvas: da far confermare al cliente.

## Link ai prodotti variabili (deciso 2026-11-12)

Mezza giornata e Giornata intera sono **variazioni dello stesso prodotto** (14 Lun–Ven: 225 mezza / 224 giornaliero;
228 Lun–Dom: 229 / 230). Ogni card porta alla scheda prodotto con la variazione già scelta via query string, che
WooCommerce legge da sé (`wc_dropdown_variation_attribute_options()`):

```
/shop/ingresso-terme-lunedi-venerdi/?attribute_pa_tipologia-ingressi=mezza-giornata
/shop/ingresso-terme-lunedi-venerdi/?attribute_pa_tipologia-ingressi=giornaliero
```

Senza parametro la scheda si apre sulla mezza giornata: era il bug segnalato dal cliente sulla card Giornata intera.
Scartato il link diretto `?add-to-cart=…&variation_id=…`: salta le condizioni e aggiunge al carrello a ogni
ricarica o prefetch. Verificato sul sito vero: `Booking_Redirect::preselect_variation()` non interferisce (agisce
solo con `category` nell'URL); SiteGround non mette in cache gli URL con query string (sempre `MISS`, risultato
corretto) — **non** aggiungere `attribute_*` ai parametri ignorati dalla cache, o servirebbe a tutti la stessa
variazione. Il link dipende dagli slug (`mezza-giornata`, `giornaliero`): se si rinominano, ricade in silenzio sulla
variazione di default.

## Problema di partenza

Le card dello shop mostrano solo nome, prezzo (o fascia di prezzo) e pulsante. Quello che distingue i biglietti —
durata, kit, piatto light, giorni, Splash & Spa Tamaro — sta solo nelle condizioni dentro le schede prodotto, e
"Ingresso Lun–Ven € 49–79" nasconde due prodotti diversi (mezza giornata senza kit, giornata intera con kit e piatto).

## Struttura proposta

1. Titolo "Regala un coupon" + intro.
2. **Ingressi SPA** (sezione principale, fascia azzurra a tutta larghezza): interruttore "Lunedì – venerdì / Tutti
   i giorni / Festività natalizie" che cambia i prezzi, poi 4 card per durata in quest'ordine: Mezza giornata,
   Serale, Giornata intera, Gold Ticket. Ogni card: durata, ✓/✗ per kit, piatto light e Tamaro, periodo escluso
   (con il rimando a "Festività natalizie" per 24 dic–6 gen). La terza opzione mostra solo i due biglietti
   natalizi, gli unici validi dal 24 dicembre al 6 gennaio (il 25 le SPA sono chiuse): **27370** Mezza giornata
   4 ore Festività Natalizie, € 65, e **124174** Serale da 3 ore Festività Natalizie, € 45. Da decidere se
   l'opzione si vede tutto l'anno o solo nel periodo di vendita.
3. Fascia blu "Sai già quando venire? Prenota direttamente" → `/prenota/` (form di prenotazione, 4 passi).
4. SPA private & massaggi.
5. Pacchetti e buoni valore (Hotel + ingresso, Mega Bonus).
6. "Hai già un coupon?": prenota su TermeGest + acquista una proroga.

## Punti aperti

- **Gold Ticket vs mezza giornata**: stesso prezzo (49/59), il Gold aggiunge Tamaro e ha solo un'esclusione
  natalizia più lunga (21 dic–11 gen invece di 24 dic–6 gen). Così la mezza giornata semplice non ha quasi motivo
  di esistere: va deciso se resta, sparisce o cambia prezzo.
- Descrizioni brevi mancanti per Massaggio, Private SPA, Private SPA con massaggio di coppia, Hotel + ingresso
  (nel mockup sono segnaposto `[…]`).
- **Implementazione**: l'interruttore dei giorni non si fa con il widget prodotti di Elementor. Ipotesi: shortcode in
  un nuovo modulo del tema (`features/shop-ingressi/`) che legge prezzi da prodotti/variazioni WooCommerce (228,
  14, 109134, 120150, 120140), così i prezzi non si duplicano a mano; il resto della pagina resta Elementor.
- **Biglietti natalizi nel codice** (visti cercando i due prodotti, non toccati):
  - ~~Un serale prenotato dal form a Natale finiva sul serale normale (109134)~~ — corretto il 2026-11-12: nel
    periodo natalizio la fascia serale di TermeGest ha categoria **`v4`**, mappata sul 124174 in entrambe le mappe
    di `Booking_Redirect`; `validate_category()` e il form accettano solo la categoria serale del periodo.
  - ~~`Booking_Redirect::is_christmas_period()` e `get_christmas_dates()` partono dal **25** dicembre~~ — corretto
    il 2026-11-12: il periodo è definito una volta sola in `Booking_Redirect::CHRISTMAS_START` / `CHRISTMAS_END`
    (24 dic – 6 gen) e lo leggono entrambe.
  - ~~124174 non è nella mappa di `controllo-codici`~~ — aggiunto il 2026-11-12 al listino a prezzo pieno.
