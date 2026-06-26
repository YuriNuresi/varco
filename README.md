# Battaglia di corsie — MVP v1

Gioco di carte 1-contro-CPU su 3 corsie. PHP puro + JS vanilla, niente framework/build.
Vedi [`PIANO_LAVORO_v1.md`](PIANO_LAVORO_v1.md) per lo spec completo.

## Struttura

```
config.php              credenziali DB (via .env) + costanti di gioco
cli_test.php            test del MOTORE (6 casi del piano)  -> php cli_test.php
cli_sim.php             simulazione match completa senza DB  -> php cli_sim.php
src/
  db.php                connessione PDO
  Engine.php            risoluzione combat (puro, testato)
  AI.php                CPU "stupida" (placer deterministico)
  http.php              helper JSON per le API
scripts/
  import_scryfall.php   IN LOCALE: scarica Scryfall + genera seed.sql
seed.sql                generato dall'import (506 creature pulite)
public/                 WEB ROOT su OVH
  index.php             home: scegli mazzo
  game.php              schermata battaglia
  deckbuilder.php       editor mazzi
  api/                  cards.php, decks.php, battle.php
  assets/               app.js, deckbuilder.js, style.css
  partials/             header/footer (disclaimer legale)
```

## 1. Generare i dati (in locale)

```bash
php scripts/import_scryfall.php
```

Scarica il bulk "Oracle Cards" da Scryfall (~150MB, messo in cache in
`scripts/oracle_cards.json`), filtra le creature pulite (P/T interi, solo le
keyword Flying / First strike / Deathtouch) e genera `seed.sql`.

## 2. Database (MySQL su OVH)

Importa `seed.sql` da phpMyAdmin. Crea le tabelle `cards` e `decks` e popola `cards`.
Per disabilitare una carta: `UPDATE cards SET enabled = 0 WHERE id = '...';`

## 3. Configurazione

Copia `.env.example` in `.env` e compila le credenziali, **oppure** modifica
direttamente i `define()` in `config.php`. In produzione metti `DEBUG = false`.

## 4. Test del motore (prima della UI)

```bash
php cli_test.php   # 7/7 devono passare
php cli_sim.php    # simulazione match completa
```

## 5. Deploy su OVH

- La **web root** del dominio deve puntare a `public/`.
- Carica `public/`, `src/`, `config.php` (e `.env`) via FTP.
- `src/` e `config.php` stanno **un livello sopra** `public/` (i path usano `__DIR__/../..`).
- Le URL nel front-end sono assolute (`/api/...`, `/assets/...`): se il sito sta in
  una sottocartella, adegua i path o usa un dominio/subdominio dedicato.

## 6. Provare in locale (Laravel Herd)

1. Aggiungi un sito Herd che punta alla cartella `public/`.
2. Crea un database MySQL (feature DB di Herd) e importa `seed.sql`.
3. Compila `.env` con le credenziali locali.
4. Apri il sito: Home → Deck editor (crea un mazzo) → torna in Home → gioca.

## Note di gioco (v1)

- 3 corsie, 1 carta per corsia, tetto di mana progressivo (`MANA_CAP = 8`).
- Rivelazione asimmetrica: corsia 0 cali tu per primo, corsia 1 la CPU, corsia 2 alla cieca.
- Vince chi prende più corsie.

## Scelte di design segnalate (scostamenti dallo spec)

1. **Tiebreak corsia per evasione.** Lo pseudocodice del §3 ritorna `DRAW` quando
   entrambe le creature sopravvivono, ma il test §3-#2 (`2/2 Flying vs 8/8`) richiede
   la vittoria della 2/2. Risolto: a parità di sopravvivenza, se una creatura *può*
   colpire l'altra mentre l'avversaria *non può* (caso Flying), prende la corsia.
   Situazioni simmetriche restano `DRAW`. (`src/Engine.php`)
2. **Regola di mana con riserva.** Adottata la formula "stretta" del §6
   (`costo ≤ budget − corsie_rimanenti × COSTO_MINIMO`) invece di quella semplice,
   per evitare che il giocatore resti senza budget per riempire l'ultima corsia
   (dead-end della UI). (`public/api/battle.php`, riflessa in `app.js`)

_Unofficial Fan Content. Not approved/endorsed by Wizards of the Coast._
