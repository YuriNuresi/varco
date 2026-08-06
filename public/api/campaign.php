<?php
/**
 * Modalità CAMPAGNA "Varco" 2.0 — draft → HUB delle 5 VALLI → roaming libero fra VILLAGGI tematici.
 *
 * Lo stato vive in $_SESSION['campaign'] (condiviso con battle.php, stessa sessione PHP):
 *   color    : colore del MAZZO (scelto al draft). Il mazzo roama tutte le valli.
 *   deck     : array di carte (Engine::card) = il mazzo UNICO condiviso, parte piccolo e cresce.
 *   progress : { W,U,B,R,G } = passo MASSIMO superato in ogni valle (0 = nessuno).
 *   phase    : 'drafting' | 'hub' | 'pending_reward' | 'fighting'
 *   draft    : { round, options[] }            (solo 'drafting')
 *   current  : { valley, step }                (livello in corso: ENTER lo imposta, battle.php lo legge)
 *   reward   : { options[], valley, step }     (solo 'pending_reward')
 *
 * Flusso:
 *   NEW {color}      -> azzera, draft round 1; inizializza progress a 0.
 *   PICK {ids}       -> aggiunge le scelte; round successivo o fine draft -> 'hub'.
 *   ENTER {valley,step} -> valida lo sblocco (step <= progress+1), imposta `current` -> si va in battaglia.
 *   FINISH {outcome} -> a fine battaglia: vittoria -> progress = max(progress, step) e bottino; altrimenti -> 'hub'.
 *   REWARD {id}      -> aggiunge la carta del bottino -> 'hub'.
 *   STATE            -> stato corrente (per campaign.php).
 *   RESET            -> abbandona la campagna.
 *
 * I VILLAGGI/livelli vivono in src/Scenarios.php (tabella magic_scenarios o default di config).
 */

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/Engine.php';
require_once __DIR__ . '/../../src/Scenarios.php';
require_once __DIR__ . '/../../src/campaign_store.php';

// sessione persa (scaduta su OVH / browser chiuso / altro device)? riprendi dal DB
campaign_restore_session();

// --- Endpoint GET leggeri (usati dall'editor campagna) ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $gAction = (string) ($_GET['action'] ?? '');

    if ($gAction === 'scenarios') {
        $valley = strtoupper(substr((string) ($_GET['valley'] ?? ''), 0, 1));
        if (!Scenarios::isValley($valley)) { json_err('Valle non valida'); }
        $villages = Scenarios::villages($valley);
        json_out(['ok' => true, 'villages' => $villages]);
    }

    json_err('Azione GET sconosciuta', 400);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_err('Solo GET o POST', 405);
}

$in     = read_input();
$action = (string) ($in['action'] ?? $_GET['action'] ?? '');

function camp(): ?array { return $_SESSION['campaign'] ?? null; }

/** Tutte le creature del colore (Engine::card form). Con $monoOnly esclude le multicolore. */
function color_cards(string $color, bool $monoOnly = false): array
{
    $sql = 'SELECT * FROM ' . TBL_CARDS . " WHERE enabled = 1 AND source = 'custom' AND colors "
         . ($monoOnly ? '= ?' : 'LIKE ?');
    $stmt = db()->prepare($sql);
    $stmt->execute([$monoOnly ? $color : '%' . $color . '%']);
    return array_map([Engine::class, 'card'], $stmt->fetchAll());
}

/** Pool del DRAFT iniziale: solo carte mono-colore; se sono troppo poche, fallback al pool completo. */
function initial_draft_pool(string $color): array
{
    $mono = color_cards($color, true);
    return count($mono) >= CAMPAIGN_DRAFT_OPTIONS ? $mono : color_cards($color);
}

/**
 * Estrae $n carte vicine all'ancora di mana $anchor, pesate per RARITY_WEIGHTS, senza duplicati.
 * Se $guaranteeRare, garantisce che almeno una sia rara/mitica (chance di "bomba"), se esiste.
 */
function draft_options(array $cards, int $anchor, int $n, bool $guaranteeRare): array
{
    if (!$cards) { return []; }

    usort($cards, static fn($a, $b) => abs($a['mana_value'] - $anchor) <=> abs($b['mana_value'] - $anchor));
    $window = array_slice($cards, 0, max($n * 4, 18));

    $weights = RARITY_WEIGHTS;
    $picked  = [];
    $usedIds = [];

    if ($guaranteeRare) {
        $isRare = static fn($c) => in_array($c['rarity'], ['rare', 'mythic'], true);
        $rares  = array_values(array_filter($window, $isRare));
        if (!$rares) { $rares = array_values(array_filter($cards, $isRare)); }
        if ($rares) {
            $g = $rares[array_rand($rares)];
            $picked[] = $g;
            $usedIds[(string) $g['id']] = true;
        }
    }

    $pool = array_values(array_filter($window, static fn($c) => empty($usedIds[(string) $c['id']])));
    while (count($picked) < $n && $pool) {
        $tot = 0;
        foreach ($pool as $c) { $tot += $weights[$c['rarity']] ?? 1; }
        $r = mt_rand(1, max(1, $tot));
        $idx = array_key_last($pool);
        foreach ($pool as $i => $c) { $r -= ($weights[$c['rarity']] ?? 1); if ($r <= 0) { $idx = $i; break; } }
        $picked[] = $pool[$idx];
        array_splice($pool, $idx, 1);
    }

    shuffle($picked);
    return array_slice($picked, 0, $n);
}

function empty_progress(): array
{
    return ['W' => 0, 'U' => 0, 'B' => 0, 'R' => 0, 'G' => 0];
}

const CAMPAIGN_COLOR_NAMES = ['W' => 'Bianco', 'U' => 'Blu', 'B' => 'Nero', 'R' => 'Rosso', 'G' => 'Verde'];

/**
 * Salva/aggiorna il mazzo della campagna fra i "Mazzi salvati" (tabella magic_decks), così è
 * giocabile anche fuori dalla campagna e RIFLETTE le carte vinte: lo richiamiamo a ogni PICK del
 * draft e a ogni REWARD. Il legame riga<->run vive in $c['deck_db_id'] (mutato qui). Best-effort:
 * se il DB rifiuta il salvataggio non blocchiamo la campagna. NB: niente vincoli min-size/copie qui
 * (il mazzo campagna parte piccolo e può avere >2 copie), per questo non passa dall'API decks.php.
 */
function sync_campaign_deck(array &$c): void
{
    if (empty($c['deck'])) { return; }
    $ids   = array_values(array_map(static fn($card) => (string) $card['id'], $c['deck']));
    $color = strtoupper(substr((string) ($c['color'] ?? 'C'), 0, 1));
    if (!preg_match('/^[WUBRG]$/', $color)) { $color = 'C'; }
    $name  = '🏔 Campagna ' . (CAMPAIGN_COLOR_NAMES[$color] ?? $color);

    try {
        if (!empty($c['deck_db_id'])) {
            $stmt = db()->prepare('UPDATE ' . TBL_DECKS . ' SET card_ids = ?, name = ?, color = ? WHERE id = ?');
            $stmt->execute([json_encode($ids), $name, $color, (int) $c['deck_db_id']]);
            // Se la riga esiste ancora (anche con 0 modifiche reali) siamo a posto; se è stata
            // cancellata a mano (rowCount 0 e assente) ricreiamola sotto.
            $exists = db()->prepare('SELECT 1 FROM ' . TBL_DECKS . ' WHERE id = ?');
            $exists->execute([(int) $c['deck_db_id']]);
            if ($exists->fetchColumn()) { return; }
        }
        $stmt = db()->prepare('INSERT INTO ' . TBL_DECKS . ' (name, color, card_ids) VALUES (?, ?, ?)');
        $stmt->execute([$name, $color, json_encode($ids)]);
        $c['deck_db_id'] = (int) db()->lastInsertId();
    } catch (Throwable $e) {
        // salvataggio mazzo non riuscito: la campagna prosegue comunque
    }
}

/** Le 5 valli annotate con lo stato di ogni passo (done/open/locked) in base a progress. */
function valleys_view(array $progress): array
{
    $out = [];
    foreach (Scenarios::VALLEYS as $v) {
        $prog  = (int) ($progress[$v] ?? 0);
        $steps = Scenarios::steps($v);
        $total = count($steps);
        $rows  = [];
        foreach ($steps as $s) {
            $st = $s['step'] <= $prog ? 'done' : ($s['step'] === $prog + 1 ? 'open' : 'locked');
            $rows[] = [
                'step'          => $s['step'],
                'village_index' => $s['village_index'],
                'village_name'  => $s['village_name'],
                'tribe'         => $s['tribe'],
                'level_name'    => $s['level_name'],
                'boss'          => $s['boss'],
                'inn_after'     => $s['inn_after'],
                'status'        => $st,
                'playable'      => $st !== 'locked', // done = rigiocabile, open = frontiera
            ];
        }
        $tribes = [];
        foreach ($steps as $s) { if ($s['tribe'] !== '') { $tribes[$s['tribe']] = true; } }
        $out[] = [
            'valley'  => $v,
            'tribes'  => array_keys($tribes),
            'total'   => $total,
            'cleared' => $prog >= $total && $total > 0,
            'done'    => min($prog, $total),
            'steps'   => $rows,
        ];
    }
    return $out;
}

/** Riepilogo compatto di UNA run (per la schermata "scegli il tuo valico"). */
function campaign_summary(array $c): array
{
    $progress = $c['progress'] ?? empty_progress();
    $done = 0;
    $total = 0;
    foreach (Scenarios::VALLEYS as $v) {
        $t = Scenarios::totalSteps($v);
        $total += $t;
        $done  += min((int) ($progress[$v] ?? 0), $t);
    }
    return [
        'phase'     => $c['phase'] ?? 'hub',
        'deck_size' => count($c['deck'] ?? []),
        'done'      => $done,
        'total'     => $total,
    ];
}

/** Vista JSON dello stato campagna (consumata da campaign.php / campaign.js). */
function campaign_view(): array
{
    $c = camp();
    if (!$c) { return ['ok' => true, 'phase' => 'none']; }

    $progress = $c['progress'] ?? empty_progress();
    $view = [
        'ok'        => true,
        'phase'     => $c['phase'],
        'color'     => $c['color'],
        'progress'  => $progress,
        'deck'      => array_values($c['deck'] ?? []),
        'deck_size' => count($c['deck'] ?? []),
    ];

    if (($c['phase'] ?? '') === 'drafting') {
        $round = (int) $c['draft']['round'];
        $view['draft'] = [
            'round'   => $round,
            'rounds'  => count(CAMPAIGN_DRAFT_ANCHORS),
            'anchor'  => CAMPAIGN_DRAFT_ANCHORS[$round - 1],
            'pick'    => CAMPAIGN_DRAFT_PICK,
            'options' => array_values($c['draft']['options']),
        ];
    }

    // Hub: dall'inizio dopo il draft e ogni volta che si torna a scegliere.
    if (in_array($c['phase'] ?? '', ['hub', 'pending_reward', 'fighting'], true)) {
        $view['valleys'] = valleys_view($progress);
    }

    if (isset($c['current'])) {
        $valley = (string) $c['current']['valley'];
        $step   = (int) $c['current']['step'];
        $sdef   = Scenarios::stepDef($valley, $step);
        $view['current'] = [
            'valley'       => $valley,
            'step'         => $step,
            'tribe'        => $sdef['tribe'] ?? '',
            'level_name'   => $sdef['level_name'] ?? '',
            'village_name' => $sdef['village_name'] ?? '',
            'boss'         => $sdef['boss'] ?? false,
        ];
    }

    if (($c['phase'] ?? '') === 'pending_reward') {
        $view['reward'] = [
            'pick'    => CAMPAIGN_REWARD_PICK,
            'options' => array_values($c['reward']['options']),
        ];
    }

    return $view;
}

// --- Azione admin: aggiorna levels di uno scenario -------------------------
if ($action === 'update_scenario') {
    require_once __DIR__ . '/../../src/auth.php';
    $key = $_GET['key'] ?? '';
    if ($key !== INSTALL_KEY && !is_admin()) { json_err('Accesso riservato agli admin', 403); }

    $scId   = $in['id'] ?? null;
    $levels = $in['levels'] ?? null;
    if (!$scId || !is_array($levels)) { json_err('id e levels richiesti'); }

    $levelsJson = json_encode($levels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt = db()->prepare('UPDATE ' . TBL_SCENARIOS . ' SET levels = ? WHERE id = ?');
    $stmt->execute([$levelsJson, $scId]);

    if ($stmt->rowCount() === 0) { json_err('Scenario non trovato', 404); }
    json_out(['ok' => true]);
}

switch ($action) {

    case 'NEW': {
        $color = strtoupper(substr((string) ($in['color'] ?? ''), 0, 1));
        if (!in_array($color, ['W', 'U', 'B', 'R', 'G'], true)) { json_err('Colore non valido (W/U/B/R/G)'); }

        $cards = initial_draft_pool($color);
        if (count($cards) < CAMPAIGN_DRAFT_OPTIONS) { json_err('Pool insufficiente per il colore ' . $color); }

        $opts = draft_options($cards, CAMPAIGN_DRAFT_ANCHORS[0], CAMPAIGN_DRAFT_OPTIONS, false);
        $_SESSION['campaign'] = [
            'color'    => $color,
            'deck'     => [],
            'progress' => empty_progress(),
            'phase'    => 'drafting',
            'draft'    => ['round' => 1, 'options' => $opts],
        ];
        campaign_store_save($_SESSION['campaign']);
        json_out(campaign_view());
    }

    case 'PICK': {
        $c = camp();
        if (!$c || ($c['phase'] ?? '') !== 'drafting') { json_err('Nessun draft in corso'); }

        $ids   = array_map('strval', (array) ($in['ids'] ?? []));
        $optBy = [];
        foreach ($c['draft']['options'] as $o) { $optBy[(string) $o['id']] = $o; }
        $chosen = [];
        foreach ($ids as $id) { if (isset($optBy[$id])) { $chosen[] = $optBy[$id]; } }
        if (count($chosen) !== CAMPAIGN_DRAFT_PICK) {
            json_err('Scegli esattamente ' . CAMPAIGN_DRAFT_PICK . ' carte fra le ' . CAMPAIGN_DRAFT_OPTIONS . ' proposte');
        }

        foreach ($chosen as $ch) { $c['deck'][] = $ch; }

        $round = (int) $c['draft']['round'];
        if ($round < count(CAMPAIGN_DRAFT_ANCHORS)) {
            $round++;
            $anchor = CAMPAIGN_DRAFT_ANCHORS[$round - 1];
            $isLast = ($round === count(CAMPAIGN_DRAFT_ANCHORS));
            $cards  = initial_draft_pool($c['color']);
            $c['draft'] = ['round' => $round, 'options' => draft_options($cards, $anchor, CAMPAIGN_DRAFT_OPTIONS, $isLast)];
        } else {
            unset($c['draft']);
            $c['phase'] = 'hub'; // draft finito: davanti all'hub delle 5 valli
        }

        sync_campaign_deck($c); // il mazzo è cresciuto: aggiorna il mazzo salvato collegato
        $_SESSION['campaign'] = $c;
        campaign_store_save($c);
        json_out(campaign_view());
    }

    case 'ENTER': {
        $c = camp();
        if (!$c || empty($c['deck'])) { json_err('Nessuna campagna attiva'); }
        if (!in_array($c['phase'] ?? '', ['hub', 'fighting'], true)) { json_err('Non sei all\'hub'); }

        $valley = strtoupper(substr((string) ($in['valley'] ?? ''), 0, 1));
        $step   = (int) ($in['step'] ?? 0);
        if (!Scenarios::isValley($valley)) { json_err('Valle non valida'); }

        $total = Scenarios::totalSteps($valley);
        if ($step < 1 || $step > $total) { json_err('Livello fuori range per la valle ' . $valley); }

        $prog = (int) ($c['progress'][$valley] ?? 0);
        if ($step > $prog + 1) { json_err('Livello bloccato: supera prima i precedenti'); }

        $c['current'] = ['valley' => $valley, 'step' => $step];
        // niente cambio di fase qui: la battaglia (battle.php START) imposterà 'fighting'.
        $_SESSION['campaign'] = $c;
        campaign_store_save($c);
        json_out(campaign_view());
    }

    case 'FINISH': {
        $c = camp();
        if (!$c) { json_err('Nessuna campagna attiva'); }
        // Idempotente: agiamo solo se eravamo davvero in battaglia (evita doppi bottini su refresh).
        if (($c['phase'] ?? '') !== 'fighting') { json_out(campaign_view()); }

        $cur     = $c['current'] ?? null;
        $valley  = (string) ($cur['valley'] ?? '');
        $step    = (int) ($cur['step'] ?? 0);
        $outcome = (string) ($in['outcome'] ?? '');

        if ($outcome === Engine::PLAYER && Scenarios::isValley($valley) && $step >= 1) {
            $c['progress'][$valley] = max((int) ($c['progress'][$valley] ?? 0), $step);
            $cards = color_cards($c['color']); // bottino dal colore del MAZZO
            $c['reward'] = ['options' => draft_options($cards, mt_rand(2, 5), CAMPAIGN_REWARD_OPTIONS, true), 'valley' => $valley, 'step' => $step];
            $c['phase']  = 'pending_reward';
        } else {
            // sconfitta/pareggio: niente permadeath. Teniamo `current` così "Riprova" (reload→START) rigioca
            // lo stesso livello; tornando all'hub e scegliendo un altro livello, ENTER sovrascrive current.
            $c['phase'] = 'hub';
        }
        $_SESSION['campaign'] = $c;
        campaign_store_save($c);
        json_out(campaign_view());
    }

    case 'REWARD': {
        $c = camp();
        if (!$c || ($c['phase'] ?? '') !== 'pending_reward') { json_err('Nessun bottino da scegliere'); }

        $id    = (string) ($in['id'] ?? '');
        $found = null;
        foreach ($c['reward']['options'] as $o) { if ((string) $o['id'] === $id) { $found = $o; break; } }
        if (!$found) { json_err('Carta non fra le opzioni'); }

        $c['deck'][] = $found;
        unset($c['reward'], $c['current']);
        $c['phase'] = 'hub';
        sync_campaign_deck($c); // carta vinta aggiunta: aggiorna il mazzo salvato collegato
        $_SESSION['campaign'] = $c;
        campaign_store_save($c);
        json_out(campaign_view());
    }

    case 'STATE': {
        json_out(campaign_view());
    }

    case 'LIST': {
        $raw = campaign_store_list_raw();
        $saves = [];
        foreach (CAMPAIGN_SAVE_COLORS as $col) {
            $saves[$col] = $raw[$col] !== null ? campaign_summary($raw[$col]) : null;
        }
        $active = camp();
        json_out([
            'ok'     => true,
            'saves'  => $saves,
            'active' => $active ? strtoupper(substr((string) ($active['color'] ?? ''), 0, 1)) : null,
        ]);
    }

    case 'SWITCH': {
        $color = strtoupper(substr((string) ($in['color'] ?? ''), 0, 1));
        if (!in_array($color, CAMPAIGN_SAVE_COLORS, true)) { json_err('Colore non valido (W/U/B/R/G)'); }

        $c = campaign_store_load($color);
        if (!$c) { json_err('Nessun salvataggio per questo valico'); }
        if (($c['phase'] ?? '') === 'fighting') { $c['phase'] = 'hub'; } // la battaglia vive solo in sessione

        $_SESSION['campaign'] = $c;
        campaign_store_set_active($color);
        json_out(campaign_view());
    }

    case 'RESET': {
        $c = camp();
        $color = $c ? strtoupper(substr((string) ($c['color'] ?? ''), 0, 1)) : null;
        unset($_SESSION['campaign']);
        if ($color) { campaign_store_save(null, $color); } // cancella solo il salvataggio di QUESTA run
        json_out(['ok' => true, 'phase' => 'none']);
    }

    default:
        json_err('Azione sconosciuta: ' . $action);
}
