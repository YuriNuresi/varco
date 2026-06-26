# Piano di lavoro — Gioco di carte (MVP v1)

> Spec per Claude Code. Obiettivo: **v1 giocabile online (browser vs CPU) entro venerdì.**
> Scrivi il motore PRIMA della UI e validalo da CLI. Non implementare nulla della sezione "v2 — NON FARE".

---

## 0. Vincoli tecnici (non negoziabili)

- **Stack: PHP puro + JavaScript vanilla. NIENTE framework, NIENTE build step, NIENTE composer/npm in produzione.**
- DB: **MySQL** (hosting condiviso OVH). Accesso via **PDO**.
- Deploy: upload via FTP della cartella `public/` + `src/`. Deve girare senza CLI sul server.
- Lo script di import dati gira **in locale** (memoria), non su OVH.
- Front-end: HTML + JS vanilla (eventuale Alpine.js via CDN se serve reattività, ma non obbligatorio). Niente bundler.
- Stato del match: tenuto in **sessione PHP** (singolo giocatore vs CPU, nessun PvP, nessuna persistenza match).

---

## 1. Concept (cosa è il gioco in v1)

Battaglia 1-contro-CPU su **3 corsie**. Ogni giocatore pesca una mano e schiera **1 carta per corsia** (3 totali), entro un **tetto di mana**. La risoluzione è **singola** (uno scontro per corsia, niente turni multipli). Vince chi prende più corsie.

La meccanica distintiva è **l'ordine di rivelazione asimmetrico**, che crea lettura e bluff:

- **Corsia 0** → il **giocatore** cala per primo (scoperto), poi la **CPU reagisce** vedendo.
- **Corsia 1** → la **CPU** cala per prima (scoperto), poi il **giocatore reagisce** vedendo.
- **Corsia 2** → entrambi calano **alla cieca**, rivelazione simultanea.

Lo schieramento è **progressivo** (una corsia alla volta), quindi il mana speso su una corsia riduce il budget per le successive: è qui che nasce la tensione (spreco la bomba sulla corsia dove verrò contrato, o la tengo per la corsia dove reagisco io?).

---

## 2. Carte e keyword (v1)

Ogni carta = **creatura** con: `name`, `mana_value`, `colors`, `power`, `toughness`, e fino a 3 keyword booleane.

**Keyword v1 (solo queste 3, sono keyword MTG reali presenti in Scryfall):**

| Keyword | Semantica nel combat pairwise |
|---|---|
| **Flying** | Può essere danneggiata **solo** da una creatura che ha anch'essa Flying. Se A ha Flying e B no → B non infligge danno ad A (A però colpisce B normalmente). |
| **First strike** | Infligge il proprio danno **prima** dell'avversario. Se l'avversario muore, non restituisce danno. Se entrambe hanno First strike → simultaneo. |
| **Deathtouch** | Qualsiasi danno ≥ 1 inflitto da questa creatura **uccide** la creatura colpita, a prescindere dalla toughness. |

> Niente totale di vita / Nexus in v1. Il match si decide a **punti corsia**, non riducendo una vita. (Lifesteal/Trample/totale-vita → v2.)

---

## 3. Motore di combat (CUORE — fare per primo, testare da CLI)

File: `src/Engine.php`. Funzione pura, nessuna dipendenza da DB/UI.

### Risoluzione di una corsia
A = carta del giocatore, B = carta della CPU.

```
resolveLane(A, B):
    A_puo_colpire_B = NOT (B.flying AND NOT A.flying)
    B_puo_colpire_A = NOT (A.flying AND NOT B.flying)

    letale(dealer, receiver, danno):
        if danno <= 0: return false
        if dealer.deathtouch: return true
        return danno >= receiver.toughness

    A_prima = A.first_strike AND NOT B.first_strike
    B_prima = B.first_strike AND NOT A.first_strike
    A_viva = true; B_viva = true

    if A_prima:
        if A_puo_colpire_B AND letale(A, B, A.power): B_viva = false
        if B_viva AND B_puo_colpire_A AND letale(B, A, B.power): A_viva = false
    elif B_prima:
        if B_puo_colpire_A AND letale(B, A, B.power): A_viva = false
        if A_viva AND A_puo_colpire_B AND letale(A, B, A.power): B_viva = false
    else: # simultaneo
        B_muore = A_puo_colpire_B AND letale(A, B, A.power)
        A_muore = B_puo_colpire_A AND letale(B, A, B.power)
        if B_muore: B_viva = false
        if A_muore: A_viva = false

    if A_viva AND NOT B_viva: return "PLAYER"
    if B_viva AND NOT A_viva: return "AI"
    return "DRAW"   # entrambe vive o entrambe morte
```

### Esito match
- Conta le corsie: `PLAYER` vs `AI`. Chi ne ha di più vince. Pari → `DRAW`.
- Restituisci sempre il **dettaglio corsia per corsia** (chi ha vinto ogni corsia + stato finale delle carte) così la UI può raccontarlo.

### Test CLI obbligatorio (prima di toccare la UI)
File: `cli_test.php`. Casi minimi che DEVONO passare:
1. 2/2 vanilla vs 3/3 vanilla → vince la 3/3.
2. 2/2 Flying vs 8/8 vanilla → vince la 2/2 (la 8/8 non la tocca).
3. 2/2 Flying vs 8/8 Flying → vince la 8/8.
4. 1/1 Deathtouch vs 10/10 vanilla → **DRAW** (si uccidono a vicenda: la 1/1 fa danno letale per deathtouch, la 10/10 la stritola).
5. 2/2 First strike vs 2/2 vanilla → vince la First strike (uccide prima di subire).
6. 5/1 First strike vs 2/6 vanilla → vince la 5/1 (colpisce prima, non basta a uccidere la 2/6? no: 5<6 → la 2/6 sopravvive e restituisce 2 ≥1 → muore la 5/1). Verifica l'esito corretto = vince la 2/6.

---

## 4. CPU "stupida" (`src/AI.php`)

NON è l'IA intelligente (quella è v2). È un **placer deterministico** che rispetta il tetto di mana e riempie le 3 corsie. Logica:

- **Corsia 1 (CPU cala per prima):** scegli una carta affordabile "decente" dalla mano CPU (es. quella con `power` più alto che lascia budget per riempire le altre 2 corsie). Deterministica.
- **Corsia 0 (CPU reagisce alla carta del giocatore):** scegli la carta affordabile che **meglio contrasta** la carta del giocatore secondo questo ordine:
  1. una carta che **vince** la corsia (sopravvive e uccide) → preferisci la più economica tra queste;
  2. altrimenti una che **pareggia** (DRAW);
  3. altrimenti la più economica disponibile.
  (Riusa `Engine.resolveLane` per valutare i candidati.)
- **Corsia 2 (alla cieca):** carta affordabile rimanente (la migliore per `power` entro budget).

Vincolo: la somma dei `mana_value` delle 3 carte CPU ≤ `MANA_CAP`, e deve sempre riuscire a riempire tutte e 3 le corsie (fallback: carta più economica).

---

## 5. Flusso match (state machine, stato in sessione PHP)

Endpoint: `public/api/battle.php` (POST con `action` + payload). Stati:

1. **START** — input: `deck_id`. Server: pesca `HAND_SIZE` carte per il giocatore e per la CPU dai rispettivi mazzi, inizializza match in sessione (`remaining_budget = MANA_CAP`). Output: mano del giocatore.
2. **LANE0_PLAYER** — input: `card_id` per corsia 0. Validazione mana (vedi §6). Server registra, poi fa reagire la CPU (corsia 0). Output: carta CPU corsia 0 rivelata.
3. **LANE1_AI** — server rivela la carta CPU per corsia 1. Input: `card_id` del giocatore per corsia 1 (reazione). Output: ok.
4. **LANE2_BLIND** — input: `card_id` del giocatore per corsia 2. Server fa scegliere alla CPU la corsia 2 alla cieca. Output: ok.
5. **RESOLVE** — server rivela tutto, esegue `Engine`, restituisce esito + dettaglio corsia per corsia. Pulisce lo stato match.

---

## 6. Tetto di mana (progressivo)

- `remaining_budget` parte da `MANA_CAP`.
- A ogni schieramento: `card.mana_value` viene sottratto.
- Validazione di una giocata: `card.mana_value <= remaining_budget`. (Regola semplice per MVP; se in playtest risulta che ci si "incastra" senza poter riempire le corsie, stringere a: `card.mana_value <= remaining_budget - (corsie_rimanenti * COSTO_MINIMO)`.)

Costanti iniziali (in `config.php`, da tarare in playtest):
```php
const HAND_SIZE = 6;
const MANA_CAP  = 8;
const LANES     = 3;
```

---

## 7. Wrapper 1 — Deck editor (`public/deckbuilder.php` + `api/decks.php`)

- Mostra le carte **`enabled = 1`** (lista con `name`, P/T, colore, keyword). Filtro per colore e per `mana_value`. Ricerca per nome.
- Il giocatore sceglie **un colore** per il mazzo e aggiunge/rimuove carte di quel colore.
- Salva il mazzo: `POST api/decks.php` → riga in `decks` (`name`, `color`, `card_ids` JSON).
- `GET api/decks.php` → lista mazzi salvati (per la schermata di avvio match).

Dimensione mazzo: parametrizzabile, default minimo 12 carte (abbastanza per pescare `HAND_SIZE` con varietà). Da tarare.

---

## 8. Wrapper 2 — Schermata battaglia (`public/game.php` + `assets/app.js`)

- Scelta mazzo → avvia match (START).
- Rendering 3 corsie. Carta = riquadro con `name`, P/T, icone keyword, colore. **Art opzionale** (vedi §10): in v1 basta nome + stat + keyword.
- Interazione che segue la state machine §5: il giocatore clicca una carta dalla mano per la corsia richiesta; le carte CPU si rivelano nei momenti giusti (corsia 0 dopo la sua giocata, corsia 1 prima della sua reazione, corsia 2 solo al RESOLVE).
- Schermata esito: vittoria/sconfitta/pareggio + dettaglio corsia per corsia (chi ha vinto, chi è sopravvissuto).
- La **"sandbox"** NON è un modulo separato: è semplicemente "scegli mazzo → avvia match vs CPU". Per provare combinazioni, costruisci mazzi diversi nel deck editor.

---

## 9. Import dati Scryfall (`scripts/import_scryfall.php` — gira IN LOCALE)

1. Scarica il bulk **"Oracle Cards"**: chiama `https://api.scryfall.com/bulk-data`, prendi l'oggetto con `type = oracle_cards`, scarica il suo `download_uri` (~150MB JSON). Sii educato coi rate limit (un download, non loop).
2. **Filtro "creature pulite":**
   - `type_line` contiene `Creature`;
   - `power` e `toughness` sono **numeri interi** (regex `^\d+$` — escludi `*`, `X`, ecc.);
   - **nessuna abilità fuori dal nostro set**: l'array `keywords` della carta deve essere ⊆ `{Flying, First strike, Deathtouch}`, **e** l'`oracle_text`, tolte le righe che sono solo una di quelle keyword (+ testo promemoria tra parentesi), deve risultare vuoto. (In pratica: creature vanilla o con solo le nostre 3 keyword.)
   - escludi token, funny set / Un-set, e carte con layout non standard.
3. **Estrai** per ogni carta valida: `oracle_id` (chiave), `name`, `cmc`→`mana_value`, `colors` (es. `"WU"`, `""` per incolore), `power`, `toughness`, i 3 flag keyword, `image_uris.normal`→`image_url` (opzionale).
4. **Output:** un file `seed.sql` (INSERT) da importare a mano nel MySQL di OVH (phpMyAdmin). Il risultato del filtro **È** la lista "carte permesse".
5. **Pruning manuale:** flag `enabled` (default 1) + possibilità di mettere a 0 le carte indesiderate. Nessuna CRUD UI in v1.

---

## 10. Schema DB (MySQL)

```sql
CREATE TABLE cards (
  id           VARCHAR(40)  PRIMARY KEY,            -- oracle_id Scryfall
  name         VARCHAR(255) NOT NULL,
  mana_value   TINYINT UNSIGNED NOT NULL,
  colors       VARCHAR(10)  NOT NULL DEFAULT '',    -- "W","U","B","R","G","WU",... "" = incolore
  power        TINYINT UNSIGNED NOT NULL,
  toughness    TINYINT UNSIGNED NOT NULL,
  flying       TINYINT(1)   NOT NULL DEFAULT 0,
  first_strike TINYINT(1)   NOT NULL DEFAULT 0,
  deathtouch   TINYINT(1)   NOT NULL DEFAULT 0,
  image_url    VARCHAR(255) DEFAULT NULL,
  enabled      TINYINT(1)   NOT NULL DEFAULT 1,
  INDEX (enabled), INDEX (mana_value), INDEX (colors)
);

CREATE TABLE decks (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  color      CHAR(1)      NOT NULL,                 -- colore scelto del mazzo
  card_ids   JSON         NOT NULL,                 -- array di id carta
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);
```

---

## 11. Struttura progetto

```
/config.php                 # credenziali DB + costanti (HAND_SIZE, MANA_CAP, LANES)
/cli_test.php               # test del motore (eseguire PRIMA della UI)
/src/
  db.php                    # connessione PDO
  Engine.php                # risoluzione combat (puro, testabile)
  AI.php                    # CPU stupida
/public/                    # web root su OVH
  index.php                 # home: scegli mazzo / vai a deck editor
  game.php                  # UI battaglia
  deckbuilder.php           # editor mazzi
  /api/
    cards.php               # GET carte enabled (con filtri)
    decks.php               # GET/POST mazzi
    battle.php              # POST: transizioni di stato del match
  /assets/
    app.js
    style.css
/scripts/
  import_scryfall.php       # IN LOCALE: filtro + genera seed.sql
```

---

## 12. Ordine di esecuzione (critical path)

1. `import_scryfall.php` in locale → `seed.sql` → import su MySQL.
2. `src/Engine.php` + `cli_test.php` → **far passare tutti i test §3 prima di proseguire.**
3. `src/AI.php` (CPU stupida).
4. `api/battle.php` (state machine) + `game.php` + `app.js` (UI battaglia).
5. `deckbuilder.php` + `api/decks.php` (+ `api/cards.php`).
6. Deploy su OVH + buffer bug.

---

## 13. Legale (mettere in pagina)

Progetto fan **non commerciale**, mai monetizzato. Niente loghi/marchi Wizards. Disclaimer in footer:

> *Unofficial Fan Content permitted under the Wizards of the Coast Fan Content Policy. Not approved/endorsed by Wizards. Portions of the materials used are property of Wizards of the Coast. ©Wizards of the Coast LLC.*

**Art (immagini carte):** è la parte più sensibile come IP. In v1 l'art è **opzionale** — la UI può mostrare la carta con solo nome + P/T + keyword. Se si usa `image_url`, restare in ambito privato/non-commerciale.

---

## 14. v2 — NON FARE in questa fase

Da NON implementare ora (rischio scope, rovinano la deadline):
- Multi-round / danno persistente / panchinari (bench).
- Totale di vita / Nexus, e quindi keyword Lifesteal, Trample/Overwhelm, Vigilance, ecc.
- IA intelligente (counter-picking sofisticato, lettura board).
- Mappa / esplorazione / node-graph alla Slay the Spire.
- Editor di scenari, editor di carte (CRUD UI per `enabled`).
- PvP / multiplayer real-time.
- Progressione, collezione, ricompense tra battaglie, tier di nemici.
