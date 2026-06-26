# varco — Stato del progetto

> **Documento di riferimento permanente.** Leggi questo file all'inizio di ogni sessione
> per capire **dove siamo arrivati** e **dove vogliamo andare**.
> Per lo spec originale dettagliato vedi [`PIANO_LAVORO_v1.md`](PIANO_LAVORO_v1.md).
> Questo file riflette lo stato **reale del codice**, che si è già discostato dallo spec.
>
> _Ultimo aggiornamento: 2026-06-24_

---

## 0. Visione & strategia (leggere per primo)

> Questa sezione tiene la **bussola** del progetto: perché lo facciamo e dove può andare.
> Le sezioni successive (1+) dicono *cosa* c'è nel codice; questa dice *perché*.

### L'asset vero: il MOTORE, non le carte
Il valore non sono le carte di Magic (gratis, ce le hanno tutti). Il valore è il **motore + la
formula**: 3 corsie, rivelazione asimmetrica (cali per primo / reagisci / cieca), vita dei maghi,
scelta para/subisci, partite da ~2 minuti. **Questa meccanica è originale, non è Magic.**
Magic ci ha solo regalato ~506 creature già bilanciate per prototipare in una settimana: è
**impalcatura**, non il prodotto. Nel DB le carte sono solo righe (nome, costo, P/T, keyword, art):
il motore è **agnostico al contenuto**.

### Il bivio (engine vs contenuto) — DECISO ✅
**Strategia scelta (2026-06-24):** continuare a evolvere il **fan project Magic** (Path A); *se*
diventa bello, **estendere a prodotto nostro** con **carte originali** (simili ma non uguali a
quelle di Magic) — Path B. I due path sono in sequenza, non alternativi.

- **Path A — fan project Magic (ora):** gratis per sempre. La Fan Content Policy di Wizards vieta
  ogni guadagno → **niente banner, niente soldi, mai.** La parte più a rischio è l'**art** delle
  carte (usiamo `image_url` da Scryfall: è dove arriverebbe per primo un eventuale C&D).
- **Path B — IP originale (dopo, se regge):** reskin con creature/mondo/art **nostri**. Il motore
  passa al 100%, zero lavoro buttato, e a quel punto si può monetizzare/mettere banner/vendere.
  Nota legale: **le meccaniche non sono coperte da copyright** (si possono replicare), ma **nomi,
  art e flavor delle carte vanno originali**; statistiche/keyword simili sono ok.
- **La storia è il ponte fra A e B:** Magic in-game ha poca narrativa → la storia che scriviamo è
  roba **nostra e originale**. Più investiamo in storia/mondo, più il prodotto si stacca da Magic e
  diventa **ownable**. La storia non è un orpello estetico: è la leva strategica (e il motore di
  retention).

> **Implicazione operativa:** mentre siamo in Path A, tenere il **contenuto disaccoppiato dal
> motore** (carte come dati nel DB, niente regole hardcoded legate a carte specifiche), così il
> giorno del reskin si sostituisce solo il layer dati.

### La prossima domanda da chiudere (una sola)
> **"La meccanica è divertente e fa tornare?"**

Tutto il resto (monetizzazione, IP originale, banner) ha senso **solo dopo** un sì a questa.
Con Magic possiamo rispondere **gratis e subito**: Magic è il carburante per arrivare al punto di
decisione, non la destinazione.

### Pre-mortem (è passato un anno, il gioco è morto — perché?)
In ordine di probabilità:
1. **Retention zero (killer #1):** partite veloci + solo-vs-CPU = giochi 3 volte e sparisci. Senza
   posta in gioco (storia/campagna, avversari nuovi) non c'è motivo di tornare.
   → *La campagna/storia è LA feature di retention, non un "v2 nice-to-have".*
2. **Profondità di skill sottile:** First strike/Deathtouch sono cosmetici a colpo singolo. Se
   para/subisci + bluff non creano decisioni difficili, è "carino" per 10 minuti.
   → *Fare 20 partite vere e chiedersi onestamente: "ho mai dovuto pensare?".*
3. **CPU troppo stupida → bluff inutile:** la rivelazione asimmetrica crolla se l'avversario non
   legge e non punisce. → *Non serve geniale, ma deve punire le giocate ovvie.*
4. **Scope creep:** draft + storia + progressione + lista v2 mangiano lo slancio, non esce niente.
   → *Una cosa alla volta: ora "è divertente?", non "ha il draft?".*
5. **Zero distribuzione:** costruito bene, non lo sa nessuno. → *"Perché un amico dovrebbe condividerlo?"*
6. **Takedown Wizards:** basso se resta privato e gratis; sale appena prende visibilità o *sembra*
   monetizzato. → *Argomento a favore del Path B (IP nostra).*

---

## 1. Cos'è il gioco

**varco** — gioco di carte 1‑contro‑CPU su **3 corsie**, ispirato a Magic ma con regole proprie.
Stack: **PHP puro + JS vanilla**, niente framework/build/composer/npm. DB MySQL via PDO.
Online su **`varco.portale3d.it`** (OVH). Progetto fan non commerciale.

### Concept di una battaglia
- Ogni mago parte con **10 di vita** (`MAGE_LIFE`). **Vince chi azzera la vita dell'avversario.**
- Una battaglia è una **serie di round** (vedi §1bis): si schierano 3 corsie, si risolve, i
  superstiti tornano in mano, si rigioca finché un mago non muore.
- Si schiera **1 creatura per corsia** (3 totali) entro un tetto di mana (`MANA_CAP = 8`).
- **Rivelazione asimmetrica** (il cuore del bluff): chi "parte per primo" attacca scoperto la
  corsia 0, l'altro attacca la corsia 1, la corsia 2 è alla cieca. Il primo si decide a
  **testa-o-croce** al round 1 e poi si **alterna** ogni round.
- Schieramento **progressivo**: il mana speso su una corsia riduce il budget per le successive.

---

## 1bis. Modello di combat v2 — MULTI-ROUND (deciso 2026-06-24, DA COSTRUIRE)

> Evoluzione concordata del combat. Sostituisce il modello "una sola risoluzione, vince chi ha
> più vita". Tira dentro nello scope ciò che il piano chiamava "v2 — multi-round / persistenza":
> è una scelta consapevole, perché è proprio ciò che dà **profondità di skill**.

**Una BATTAGLIA = serie di ROUND. Vince chi porta l'avversario a vita ≤ 0.**

Ogni round:
1. **Reset budget** a `MANA_CAP`. **Ripeschi** la mano fino a `HAND_SIZE`.
2. Si schierano le **3 corsie** con la rivelazione asimmetrica (chi parte per primo, sotto).
3. **Risoluzione corsia per corsia, VISIBILE:** i danni ai maghi si applicano subito, la vita
   scende a vista, le creature morte si vedono morire. La **vita si controlla dopo OGNI corsia**:
   se un mago va a **≤ 0 la battaglia finisce subito**, anche a metà round (corsia 0, 1 o 2 — e
   soprattutto al round 3). Solo se entrambi i maghi cadono nello *stesso* scontro di corsia è
   pareggio. (Questo dimezza i doppi-KO: vedi finding §3.)
4. **Le creature morte ESCONO dalla battaglia** (opzione B — non tornano). I **superstiti tornano
   in mano FERITI**: il danno da combattimento subìto **resta** (costituzione efficace = stampata −
   ferite) per tutta la battaglia, quindi una creatura che vince spesso muore prima o poi → i mazzi
   si consumano (aiuta anche contro gli stalli). Le 3 corsie si liberano. *(Le ferite si azzerano
   tra una battaglia e l'altra: il mazzo si rigenera.)*
5. Se un mago è a **vita ≤ 0** → fine battaglia. Altrimenti round successivo.

**Chi parte per primo:** testa-o-croce al round 1, poi si **alterna** ogni round (round 1 io,
round 2 avversario, …). Il "primo" attacca la corsia 0 (scoperto, l'altro reagisce/difende); il
"secondo" attacca la corsia 1; la corsia 2 è **sempre cieca**. Così l'info-advantage si alterna.

**Anti-stallo (tre livelli):**
- (a) **Le creature morte non tornano** + **ferite persistenti** → il mazzo si consuma. Deck-out se
  non puoi più schierare 3 creature.
- (b) Da **round 3** in poi, TUTTE le creature ottengono **Travolgere** (l'eccesso passa in faccia).
- (c) **Fatica** (`FATIGUE_FROM=6`): dal round 6 in poi entrambi i maghi subiscono danno crescente
  (round 6:−1, 7:−2, …) → terminazione **garantita**, nessuno stallo infinito.

**Spareggio doppio-KO:** se entrambi i maghi vanno a ≤0 nello stesso momento (scontro o fatica),
vince chi è **"meno morto"** (vita più alta); solo a vita pari è pareggio.

**Conseguenza di design (il punto):** con la morte che **persiste** tra i round, First strike /
Deathtouch / Travolgere diventano **decisivi**, non più cosmetici a colpo singolo.

**Economia carte (default scelti, da verificare in playtest):**
- Mazzo della battaglia = mazzo di campagna mescolato; pesca `HAND_SIZE` in mano, il resto è pila.
- Ogni round schieri 3 creature dalla mano. A fine round: morte → scartata (fuori per questa
  battaglia); superstite → torna in mano; poi ripeschi fino a `HAND_SIZE` dalla pila.
- **Deck-out:** se a inizio round non puoi schierare 3 creature → perdi.
- Il mazzo si "rigenera" tra una battaglia e l'altra (è la tua collezione della run).

### Variante "muro" (tower-defense) — RINVIATA, in piano come nemico speciale
Nemico/boss particolare: creature-muro difensive (es. **2/8**) che **restano in campo** tra i round
(board persistente) e vanno smontate sacrificando più creature in turni diversi. Bella tensione,
ma è l'opzione (a) del bivio §0 → la teniamo per dopo.

---

## 1ter. Campagna / Storia "Varco" (deciso 2026-06-24, DA COSTRUIRE)

Ambientazione: **Varco**, paese d'alta montagna su un altopiano circondato da vette. **5 valichi**
(= i 5 colori) portano a **5 valli** lunghe e piene di insidie. I nemici di una valle sono del
**colore** di quella valle.

- **Pre-valle (draft mazzo):** scegli un valico/colore → ricevi **5 comuni + 1 "epica" (il tuo
  capitano)** random di quel colore = mazzo iniziale piccolo.
  *La regola mazzo 15–20 vale per il deckbuilder libero; la campagna **parte piccola e cresce**.*
- **Avanzamento:** livelli in serie. Vinci → scegli **1 carta fra 3** proposte (non-comuni del tuo
  colore) da aggiungere al mazzo. Lungo il percorso **punti di scambio/baratto** (carte scarse →
  migliori). *[baratto = dettaglio rinviato]*
- **Valle infinita + check-point ("ristori"):** si avanza a oltranza; la morte ti riporta
  all'**ultimo ristoro**. Tra due ristori c'è una **serie di battaglie** da superare prima di
  salvare l'avanzamento; le carte vinte restano → rigiochi più forte.
- **Capitano:** sempre in **mano d'apertura**; se muore **torna ferito** (es. 5/5 → 4/4 → 3/3 …).
  *[meccanica capitano = campaign-layer, da definire: il degrado è entro la singola battaglia o
  permanente lungo la run? Default proposto: entro la battaglia, ripristino a fine battaglia.]*
- **Sconfitta:** non è permadeath totale → ritorni all'ultimo ristoro.

---

## 2. Stato attuale (✅ FATTO)

> ⚠️ Il modello di combat è stato **riscritto** rispetto al PIANO_LAVORO_v1.md (che parlava di
> "punti corsia" senza vita). Ora c'è la **vita dei maghi**. Quando il piano e questo file
> divergono, **vale questo file**.

### Motore di combat — `src/Engine.php` ✅
- Modello **vita dei maghi** (`MAGE_LIFE = 10`).
- Ogni corsia ha attaccante e difensore. Il **difensore** sceglie:
  - **PARARE** → le creature si scontrano, nessun danno ai maghi. *Illegale* parare una creatura
    con volo (Flying) usando una creatura di terra.
  - **SUBIRE** → **solo l'attaccante** colpisce il mago difensore; il difensore **incassa** e la
    sua creatura non contrattacca (resta a guardia). *(Rivisto il 2026-06-24: prima il difensore
    colpiva di rimando, il che esponeva chi attaccava — "attacco con un 1/1 e mi becco 5".)*
- **Corsia cieca (2):** le creature si **scontrano sempre** e in faccia ai maghi va solo l'**eccesso**
  (potenza − costituzione avversaria). Il **volo NON dà evasione** qui (rivisto 2026-06-24: prima
  "volo = danno pieno", troppo punitivo).
- Il **volo** conta solo sulla *legalità del blocco*; una volta ingaggiate le creature si
  danneggiano normalmente.
- `Engine::resolveLane` ritorna **danni ai maghi + morti** (non più PLAYER/AI/DRAW per corsia).
- **Spareggio match** (`resolveMatch`): 1) più vita; 2) a parità, più creature vive; 3) pareggio.
  Ritorna `player_alive` / `ai_alive` / `tiebreak`.
- **Keyword (8, dal 2026-06-24):** Flying ✈️, First strike ⚡, Deathtouch ☠️, Travolgere 🐗,
  **Doppio attacco ⚔️** (≈ danno ×2 con timing first-strike), **Legame vitale 💖** (il danno inflitto
  fa guadagnare vita al controllore, anche oltre il massimo), **Raggiungere 🏹** (para i volanti),
  **Difensore 🧱** (potenza 0 in attacco, ma para a potenza piena). Tutte applicate in `Engine` e
  loggate. Le altre abilità delle carte restano ignorate (testo coperto in UI).
- Testato: `php cli_test.php` (**26/26**, inclusi i casi delle 4 nuove keyword) e `php cli_sim.php`.

### CPU — `src/AI.php` ✅
- "Placer" deterministico: rispetta il tetto di mana, riempie le 3 corsie, riusa l'Engine per
  valutare i contrasti. Non è IA intelligente (quella è v2).

### Dati / Import — `scripts/import_scryfall.php` ✅ (IMPORT MASSIVO dal 2026-06-24)
- Gira **in locale**. Usa il bulk "Oracle Cards" (cache `scripts/oracle_cards.json`, ~178 MB).
- **Scelta 2026-06-24: import MASSIVO.** Niente più filtro testo/keyword: include **TUTTE le creature
  cartacee con P/T intero** (esclusi layout strani, funny set e carte solo-digitali Arena/alchemy "A-…").
  Pool risultante: **16.327 creature** (da ~506). Il motore usa solo **P/T + le 4 keyword** che sa
  applicare (Flying/First strike/Deathtouch/Trample, parse-ate da `keywords`); **le altre abilità
  stampate sono IGNORATE** e in UI il testo della carta è **coperto dalle nostre icone** (`.pc-cover`).
- DB rigenerato su OVH via `reinstall.php` (poi cancellato): 16.327 carte, 840 con Travolgere.
- `scripts/analyze_pool.php` (una-tantum) stima il pool per varianti di filtro.
- ⏳ **Possibile evoluzione:** allargare le keyword che il motore *applica davvero* (oltre le 4) →
  più icone mostrate invece che coperte. Richiede logica di combat per ciascuna.

### Web app — `public/` ✅
- `index.php` — home: scegli mazzo / vai al deck editor / draft.
- `game.php` + `assets/app.js` — schermata battaglia. Layout tavolo a due metà speculari
  (avversario sopra, tu sotto, corridoio centrale). Animazioni di rivelazione, pause "la CPU sta
  pensando…", risoluzione scaglionata corsia per corsia, bordi/frecce per danni-in-faccia vs
  scontro, nastro "MORTA".
- `deckbuilder.php` + `assets/deckbuilder.js` — editor mazzi mono-colore, con rarità mostrata.
- `draft.php` + `assets/draft.js` — modalità draft "apri buste" (vedi §3, parzialmente fatta).
- `api/` — `cards.php`, `decks.php`, `battle.php` (state machine match), `draft.php`.
- `partials/` — header/footer con disclaimer legale.

### Regole di gioco decise (scostamenti dallo spec)
- **Mazzo:** mono-colore, **15–20 carte**, **max 2 copie** (`DECK_MIN_SIZE`/`DECK_MAX_SIZE`/`MAX_COPIES`).
  La CPU costruisce mono-colore con le stesse regole.
- **Regola mana con riserva:** giocata valida se `costo ≤ budget − corsie_rimanenti × COSTO_MINIMO`
  (anti dead-end della UI), invece della regola semplice del §6 dello spec.
- **Tiebreak corsia per evasione:** a parità di sopravvivenza, se una creatura *può* colpire
  l'altra mentre l'avversaria *non può* (Flying), prende il vantaggio.

### Deploy — OVH `varco.portale3d.it` ✅
- File su OVH in `magic/`, con `magic/public/` come docroot del sottodominio.
- `config.php`, `src/`, `seed.sql`, `.env` stanno in `magic/` (sopra la docroot, non web-accessibili).
- DB: MySQL condiviso di Helios (host **interno**, non raggiungibile da fuori). Tabelle dedicate
  **`magic_cards`** / **`magic_decks`** (prefisso per non collidere con le `h7_*` di Helios).
  Credenziali in `magic/.env`.
- Setup DB via `install.php?key=...` (one-shot, perché il MySQL non è accessibile da locale per
  importare seed.sql). Esiste anche `reinstall.php`.
- PHP 8.2 globale via `.ovhconfig` di root.

---

## 3. In sospeso / da rifinire (🔧)

### Multi-round ONLINE (playtest) ✅ — fatto il 2026-06-24
- ✅ **`Engine::resolveRound`** (risoluzione per-corsia, terminazione anticipata, Travolgere dal round 3).
- ✅ **`battle.php` multi-round**: vita persistente, mazzi+pila in sessione, ripesca a `HAND_SIZE`,
  deck-out, reset mana per round. **Avvio rapido per colore** (`?color=W…G`, niente deckbuilder).
- ✅ **UI** (`app.js`/`game.php`/`index.php`): vita che scende corsia per corsia ai valori reali,
  schermata fine-round con "Inizia Round N", tag round, fine-battaglia (KO/deck-out). `MAGE_LIFE=15`.
- ✅ **Risoluzione CORSIA PER CORSIA durante lo schieramento (2026-06-24):** ogni corsia si conclude
  (morti, ferite, danni, vita) appena entrambi i lati hanno schierato — non più un "Risolvi" finale.
  La battaglia può finire a metà round. `battle.php` risolve in `LANE0_PLAYER`/`LANE1_PLAYER`/
  `LANE2_BLIND` (helper `resolve_and_apply` + `round_end`); l'azione `RESOLVE` è stata rimossa.
- ✅ **Deploy su OVH** (`varco.portale3d.it`) + smoke-test pagine (home + battaglia rendono, no errori PHP).
- ✅ **Mazzi quick-play a CURVA di mana** (`build_curved_deck`, curva `QUICK_CURVE` 1:3 2:5 3:5 4:4
  5:2 6:1): risolve i mazzi random ingiocabili (mani tutte costose). Verificato: ~99.7% delle mani
  iniziali può schierare 3 carte entro `MANA_CAP=10` (`cli_curve_check.php`). Rarità non forzata
  (il pool è ~90% comuni → "molte comuni, qualche rara" da sé). Stessa curva per la CPU.
- ✅ **Hardening API** (`http.php`): i warning PHP non corrompono più il JSON (catturati in `_warn`,
  loggati in console); errori fatali restituiti come JSON; client robusto a risposte non-JSON.
- ✅ **UX battaglia:** auto-rivelazione attacco CPU (niente click "Rivela"); carta CPU coperta sulla
  corsia cieca; **dorso vero di Magic** (hotlink Scryfall + fallback CSS); pulsanti para/subisci in colonna.
- ⚠️ **Semplificazioni di questa prima versione giocabile (da evolvere):**
  - struttura corsie **FISSA** ogni round (c0 attacchi tu, c1 CPU, c2 cieca) → **alternanza
    testa-o-croce + iniziativa-letale NON ancora attive**.
  - quick-play = mazzo a curva mono-colore (non è ancora il draft pre-valle §1ter).
- ⏳ **Da fare:** raccogliere il **feedback di playtest** dell'utente; poi alternanza+iniziativa,
  poi draft pre-valle e scaffold campagna.

### Campagna "Varco" — PRIMA VERSIONE GIOCABILE ✅ (2026-06-24)
- ✅ **Pagina `campaign.php` + `assets/campaign.js`** (state machine client su fetch STATE):
  scelta valico/colore → draft → pronto → battaglia → bottino → battaglia…
- ✅ **API `public/api/campaign.php`** (stato in `$_SESSION['campaign']`, condiviso con `battle.php`):
  - `NEW {color}` → primo round di draft.
  - `PICK {ids}` → aggiunge le scelte, round successivo o fine draft (`ready`).
  - `FINISH {outcome}` → a fine battaglia: vittoria → +1 win + bottino (1 su 6); sconfitta/pareggio → `ready` (riprovi).
  - `REWARD {id}` → aggiunge la carta del bottino. `STATE` / `RESET`.
- ✅ **Draft "per mana" (scelta utente, sostituisce il "5 comuni + 1 epica" del §1ter):** 6 round
  ancorati a fasce di mana crescenti (`CAMPAIGN_DRAFT_ANCHORS = [1,2,2,3,4,5]`); ogni round mostra
  **6 carte mono-colore pesate per rarità, ne scegli 2** → mazzo iniziale di **12 carte** a curva.
  Rara **garantita** nell'ultimo round e in ogni bottino (`draft_options($guaranteeRare)`); verificato
  con test isolato (curva corretta, nessun duplicato nel batch, 1000/1000 rara nell'ultimo round).
- ✅ **Bottino post-vittoria:** dopo OGNI battaglia vinta peschi **1 carta su 6** (rara garantita) → il mazzo cresce.
- ✅ **Integrazione battaglia:** `game.php?campaign=1` → `app.js` fa START `{from_campaign:true}`;
  `battle.php` carica il mazzo di campagna, costruisce la CPU **dello stesso colore della valle**, mazzo
  CPU = dimensione del tuo + `wins` (**difficoltà crescente**); a fine battaglia `app.js` chiama `FINISH`.
- ✅ **Difficoltà crescente per livello (2026-06-24):** livello = vittorie + 1 (max `CAMPAIGN_LEVELS=5`).
  La CPU è costruita con `build_campaign_ai_deck($color,$level)` filtrando per `CAMPAIGN_DIFFICULTY`:
  L1 = solo comuni, forza ≤2, mv ≤3, 10 carte → … → L5 = tutte le rarità, forza libera, 18 carte.
  Fallback a `build_curved_deck` se il pool filtrato è < HAND_SIZE. (`pack_curve` estratto e condiviso.)
- ✅ **Minimappa della valle (2026-06-24):** 5 tappe (Sentiero→Vetta) con stato done/current/locked e
  barra "forza nemici" crescente; mostrata su pronto/bottino/in-battaglia (`minimapHtml` in campaign.js,
  stile in campaign.php). `campaign_view` espone `level`/`levels`/`cleared`.
- ⏳ **Da fare (campagna):** capitano/epica in mano d'apertura (degrado se muore), **ristori/check-point**
  funzionali (ora la minimappa li prefigura ma non salvano), valle infinita con permadeath→ultimo ristoro,
  baratto. Per ora: niente permadeath (sconfitta = riprova la stessa battaglia); dopo L5 resta al max.

### Pool carte / rarità — ✅ AMPLIATO (2026-06-24)
- Import massivo: **16.327 creature** (tutte le rarità, ora ci sono anche mitiche). Risolve il
  "vedo sempre le stesse carte". Le abilità extra non gestite dal motore sono **coperte in UI**
  dalle nostre icone (vedi import sopra). Niente nomi in italiano per ora (scelta utente).

### Mana / overspend
- ✅ **Riserva mana intelligente (2026-06-24):** la CPU (e il giocatore-sim) non assume più
  `COSTO_MINIMO=1` per corsia ma calcola la riserva reale = somma delle carte più economiche per le
  corsie restanti (`Engine::reserveCost`, usata in `AI::pickBest`/`pickDefense`). Risolve il bug
  "CPU sfora il tetto" nel caso comune (es. 5+4+2 con tetto 10).
- ⏳ **Residuo (~2% dei round, fine partita):** se la mano è troppo costosa (carte economiche
  esaurite) lo sforamento resta possibile. **Fix completo da fare:** "corsia vuota" — se non puoi
  permetterti una creatura, la corsia resta scoperta e l'avversario colpisce il mago lì. Tocca
  Engine/battle/UI → feature a parte.

### Log diagnostico battaglie ✅ (2026-06-24)
- **`src/Logger.php`** scrive JSONL in `magic/logs/battle.jsonl` (sopra la docroot). Eventi:
  `start` (matchup: mani+mazzi), `lane` (carte EFFETTIVE coi doni, mode, danni, morti, ferite,
  vita prima→dopo), `pass`, `round_end`, `fatigue`, `end`. Codici kw: F/S/D/T.
- **`public/api/logs.php?key=INSTALL_KEY`** scarica il log (`&tail=N` per le ultime righe, `&clear=1` per azzerare).
- Attivo con `LOG_BATTLE=true` in config. **A regime: `LOG_BATTLE=false` + cancellare `logs.php` e la cartella `logs/`.**

### Igiene / pulizia
- **`DEBUG = true`** e **`LOG_BATTLE = true`** in `config.php`, anche su OVH → **metterli `false` a regime.**
- **`install.php` / `reinstall.php`** one-shot → **cancellarli dal server** dopo l'uso.
- **Playtest / bilanciamento:** costanti (`HAND_SIZE`, `MANA_CAP`, `MAGE_LIFE`, dimensioni mazzo)
  da tarare. Verificare che il gioco non si "incastri" e che le partite siano divertenti.
- **Finding sim multi-round (`cli_battle.php 500`, AI-vs-AI):**
  - ✅ Zero stalli infiniti (anti-stallo ok).
  - ✅ **Controllo vita dopo OGNI corsia** (terminazione anticipata): pareggi **54% → 28%**.
  - ⏳ Restano **~28% pareggi** = doppi-KO **sulla stessa corsia** (SUBIRE / volo / Travolgere
    bilaterale che mandano entrambi i maghi a ≤0 nello stesso scontro), e **~2.3 round/battaglia**
    (partite corte con `MAGE_LIFE=10`). Caveat: mazzi a specchio + euristiche identiche gonfiano i
    pareggi rispetto al gioco reale.
  - **Decisione aperta (ora più piccola):** se i pareggi danno fastidio, *iniziativa letale* sul
    doppio-KO **della stessa corsia** (chi ha l'iniziativa infligge per primo → l'altro, se muore,
    non restituisce).
- **Finding sim con SUBISCI rivisto + difesa AI migliorata (`cli_battle.php 500`):** bilanciato
  **~50/50** (TU 229 / CPU 228), ma le partite si **allungano** (~10 round) e **~3.6% vanno molto
  lunghe** (60+ round) con difesa ottimale da entrambi i lati. Causa: difendere bene è forte; il
  Travolgere del round 3 non rompe lo stallo se il difensore para con creature ad alta costituzione
  (eccesso 0). **Tuning da valutare dopo il playtest umano:** `MAGE_LIFE` più basso, o anti-stallo
  più aggressivo (es. Travolgere/chip crescente dal round 4-5). Per ora non blocca il gioco.
- **Ferite persistenti (aggiunte 2026-06-24):** i sopravvissuti tornano in mano con la costituzione
  ridotta dal danno subìto (Engine: `wounds` + `effToughness`). Migliora: round medi ~10 → **~8.8**,
  stalli ~3.6% → **~1%**, bilanciamento sempre ~50/50 (`cli_battle.php`, 18/18 test). UI: P/T ridotto
  in rosso + bordo sulla creatura ferita.

---

## 4. Dove vogliamo andare (🎯 roadmap)

### Priorità immediate (la svolta in corso)
1. **Motore multi-round** + test CLI (questa è la validazione del "fun" / profondità skill).
2. **Draft mazzo pre-valle** (5 comuni + 1 capitano per colore).
3. **Rework `battle.php` + UI** per il multi-round (vita persistente, round su round).
4. **Scaffold campagna:** valle, livelli in serie, ricompensa 1-su-3, ristori/check-point.
5. Poi: ampliare il pool ad alta rarità; **playtest**; igiene deploy.

### Tirato DENTRO lo scope (era "v2", ora lo facciamo)
- Multi-round / danno persistente. Travolgere. Struttura campagna/run con progressione carte.

### Ancora v2 — NON ora (rischio scope)
- Board persistente "tower-defense" (nemico speciale, §1bis) — *in piano, ma dopo.*
- Panchinari (bench), Lifesteal/Vigilance e altre keyword oltre le 4.
- IA intelligente (counter-picking, lettura board).
- Mappa/overworld con scelta dei rami; editor di scenari/carte (CRUD UI).
- PvP / multiplayer real-time.
- Baratto evoluto, economia, meta-progressione tra valli.

---

## 5. Mappa dei file (riferimento rapido)

```
config.php              credenziali DB (.env) + costanti di gioco
PIANO_LAVORO_v1.md      spec originale (alcune parti superate da questo file)
STATO_PROGETTO.md       ← questo file
README.md               istruzioni setup/deploy
cli_test.php            test del MOTORE (7 casi)        -> php cli_test.php
cli_sim.php             simulazione match completa      -> php cli_sim.php
seed.sql                ~506 creature pulite (generato dall'import)
src/
  db.php                connessione PDO
  Engine.php            risoluzione combat (modello vita maghi, puro, testato)
  AI.php                CPU "stupida" (placer deterministico)
  http.php              helper JSON per le API
scripts/
  import_scryfall.php   IN LOCALE: scarica Scryfall + genera seed.sql
  oracle_cards.json     cache del bulk Scryfall (~178 MB, non versionare)
public/                 WEB ROOT su OVH
  index.php             home
  game.php  + assets/app.js          schermata battaglia
  deckbuilder.php + assets/deckbuilder.js   editor mazzi
  draft.php + assets/draft.js        draft "apri buste" (WIP)
  api/                  cards.php, decks.php, battle.php, draft.php
  assets/               app.js, deckbuilder.js, draft.js, style.css
  partials/             header.php, footer.php (disclaimer legale)
  install.php / reinstall.php   installer one-shot DB (da cancellare dopo l'uso)
```

### Costanti di gioco (`config.php`)
| Costante | Valore | Significato |
|---|---|---|
| `HAND_SIZE` | 6 | carte pescate a inizio match (ripescate ogni round) |
| `MANA_CAP` | 10 | tetto di mana **per round** sulle 3 corsie |
| `LANES` | 3 | numero corsie |
| `MAGE_LIFE` | 10 | vita iniziale di ogni mago (multi-round) |
| `FATIGUE_FROM` | 6 | dal round N i maghi subiscono danno crescente (anti-stallo) |
| `COSTO_MINIMO` | 1 | costo minimo per corsia (regola mana con riserva) |
| `DECK_MIN_SIZE` / `DECK_MAX_SIZE` | 15 / 20 | dimensione mazzo |
| `MAX_COPIES` | 2 | copie max della stessa carta |
| `PACK_SIZE` / `PACKS_PER_DRAFT` | 15 / 2 | draft buste |
| `RARITY_WEIGHTS` | 70/22/6/2 | pesi common/uncommon/rare/mythic |
| `CAMPAIGN_DRAFT_ANCHORS` | [1,2,2,3,4,5] | fascia di mana "ancora" per round di draft (→ 12 carte, curva) |
| `CAMPAIGN_DRAFT_OPTIONS` / `_PICK` | 6 / 2 | carte mostrate / scelte per round di draft |
| `CAMPAIGN_REWARD_OPTIONS` / `_PICK` | 6 / 1 | bottino dopo ogni battaglia vinta (rara garantita) |
| `DEBUG` | **true** | ⚠️ mettere false in produzione |

---

## 6. Legale

Progetto fan **non commerciale**. Disclaimer in footer:
> _Unofficial Fan Content permitted under the Wizards of the Coast Fan Content Policy.
> Not approved/endorsed by Wizards. Portions of the materials used are property of
> Wizards of the Coast. ©Wizards of the Coast LLC._
