# Pagina shop — proposta di layout (bozza, 2026-11-11)

Mockup della nuova `/shop/`, non implementato. Esclusa dal deploy (`--exclude=design/` in `deploy.yml`).

- **Canvas vivo**: https://claude.ai/artifact/8H6PMdGdjHEaEbyNUYtSCW (privato)
- **`shop-mockup.dc.html`**: copia del canvas a questa data. È un file del formato Design di claude.ai, non si
  apre da solo nel browser: serve come riferimento per il markup e i testi.

## Problema di partenza

Le card dello shop mostrano solo nome, prezzo (o fascia di prezzo) e pulsante. Quello che distingue i biglietti —
durata, kit, piatto light, giorni, Splash & Spa Tamaro — sta solo nelle condizioni dentro le schede prodotto, e
"Ingresso Lun–Ven € 49–79" nasconde due prodotti diversi (mezza giornata senza kit, giornata intera con kit e piatto).

## Struttura proposta

1. Titolo "Regala un coupon" + intro.
2. **Ingressi SPA** (sezione principale, fascia azzurra a tutta larghezza): interruttore "Lunedì – venerdì / Tutti
   i giorni" che cambia i prezzi, poi 4 card per durata in quest'ordine: Mezza giornata, Serale, Giornata intera,
   Gold Ticket. Ogni card: durata, ✓/✗ per kit, piatto light e Tamaro, periodo escluso.
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
