<?php
/**
 * State machine del match — modello MULTI-ROUND con risoluzione CORSIA PER CORSIA (§1bis).
 *
 * Differenza chiave: ogni corsia si RISOLVE appena entrambi i lati hanno schierato (morti, ferite,
 * danni ai maghi applicati subito), non tutto insieme alla fine. La battaglia può finire a metà round.
 *
 * Struttura corsie FISSA per round: c0 attacchi tu, c1 attacca la CPU, c2 alla cieca.
 *
 * POST { action, ... }:
 *   START        { deck_id }|{ color } -> costruisce mazzi, pesca mani. Ritorna mano.
 *   LANE0_PLAYER { card_id }            -> tu attacchi c0; la CPU difende; RISOLVE c0.
 *   LANE1_AI     {}                     -> la CPU attacca c1 (rivelata, niente risoluzione).
 *   LANE1_PLAYER { card_id, choice }    -> tu difendi c1; RISOLVE c1.
 *   LANE2_BLIND  { card_id }            -> c2 alla cieca; RISOLVE c2; poi fine round (ricicla/ripesca).
 */

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/http.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/Engine.php';
require_once __DIR__ . '/../../src/AI.php';
require_once __DIR__ . '/../../src/Logger.php';
require_once __DIR__ . '/../../src/Scenarios.php';
require_once __DIR__ . '/../../src/campaign_store.php';

// Punti Portale (XP comuni a tutti i giochi): solo su games.portale3d.it, solo utenti loggati.
$__p3dGames = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/core/games.php';
if (is_file($__p3dGames)) { require_once $__p3dGames; }

/**
 * Vittoria contro la CPU -> XP. L'esito arriva dal motore (non dal client), e le chiavi
 * sono uniche (INSERT IGNORE): campagna = ogni livello una volta sola (5 + passo, +50 a valle
 * completata); partita libera = 10 punti una volta al giorno per colore/mazzo.
 */
function varco_xp_on_end(array $m, string $outcome): void
{
    if ($outcome !== Engine::PLAYER || !function_exists('p3d_games_xp')) { return; }
    try {
        $player = p3d_games_player();
        if (!$player) { return; }
        $cur = $m['camp_cur'] ?? null;
        if (!empty($m['campaign']) && is_array($cur)) {
            $v = (string) ($cur['valley'] ?? '');
            $step = (int) ($cur['step'] ?? 0);
            if ($step < 1) { return; }
            p3d_games_xp('varco', "varco:camp:{$v}:{$step}", 5 + $step, "Valle {$v} livello {$step}", $player);
            if ($step >= count(Scenarios::steps($v))) {
                p3d_games_xp('varco', "varco:camp:{$v}:done", 50, "Valle {$v} completata", $player);
            }
            return;
        }
        $bucket = (string) ($m['xp_bucket'] ?? 'duel');
        $today = date('Y-m-d');
        p3d_games_xp('varco', "varco:duel:{$today}:{$bucket}", 10, "Vittoria {$bucket} del {$today}", $player);
    } catch (Throwable $e) {
        error_log('[varco xp] ' . $e->getMessage());
    }
}

// sessione persa fra hub e battaglia? riprendi la campagna dal DB
campaign_restore_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_err('Solo POST', 405);
}

$in     = read_input();
$action = (string) ($in['action'] ?? '');

const AI_DECK_SIZE = 20;
const QUICK_CURVE  = [1 => 3, 2 => 5, 3 => 5, 4 => 4, 5 => 2, 6 => 1];

function match_state(): ?array { return $_SESSION['match'] ?? null; }

/** @return array<string,array> */
function load_cards_by_ids(array $ids): array
{
    if (!$ids) { return []; }
    $place = implode(',', array_fill(0, count($ids), '?'));
    $stmt  = db()->prepare('SELECT * FROM ' . TBL_CARDS . " WHERE id IN ($place) AND enabled = 1");
    $stmt->execute(array_values($ids));
    $out = [];
    foreach ($stmt->fetchAll() as $row) { $out[$row['id']] = Engine::card($row); }
    return $out;
}

/** Impacchetta i candidati in un mazzo che segue la curva di mana QUICK_CURVE (bottom-heavy). */
function pack_curve(array $cands, int $size): array
{
    $byMv = [1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => []];
    foreach ($cands as $c) { $byMv[min(max(1, (int) $c['mana_value']), 6)][] = $c; }

    $baseSum = array_sum(QUICK_CURVE);
    $target = [];
    $acc = 0;
    foreach (QUICK_CURVE as $b => $n) { $t = (int) floor($n * $size / $baseSum); $target[$b] = $t; $acc += $t; }
    for ($b = 1; $acc < $size; $b = ($b % 6) + 1) { $target[$b]++; $acc++; }

    $deck = [];
    $deficit = 0;
    foreach ($target as $b => $want) {
        $take = array_splice($byMv[$b], 0, $want);
        $deck = array_merge($deck, $take);
        $deficit += $want - count($take);
    }
    for ($b = 1; $b <= 6 && $deficit > 0; $b++) {
        while ($deficit > 0 && $byMv[$b]) { $deck[] = array_shift($byMv[$b]); $deficit--; }
    }
    shuffle($deck);
    return $deck;
}

/** Mazzo mono-colore a CURVA di mana (molte economiche, poche costose). */
function build_curved_deck(string $color, int $size): array
{
    $color = strtoupper(substr($color, 0, 1));
    if (!in_array($color, ['W', 'U', 'B', 'R', 'G'], true)) { return []; }

    $stmt = db()->prepare('SELECT * FROM ' . TBL_CARDS
        . " WHERE enabled = 1 AND source = 'custom' AND colors LIKE ? ORDER BY RAND() LIMIT 400");
    $stmt->execute(['%' . $color . '%']);
    $cands = array_map([Engine::class, 'card'], $stmt->fetchAll());
    if (count($cands) < HAND_SIZE) { return []; }

    return pack_curve($cands, $size);
}

/** Candidati di una valle filtrati per tribù (opzionale) + cap di difficoltà del livello. */
function campaign_card_query(string $color, string $tribe, array $rarities, int $maxPower, int $maxMv): array
{
    $rarities = $rarities ?: ['common'];
    $place = implode(',', array_fill(0, count($rarities), '?'));
    $sql   = 'SELECT * FROM ' . TBL_CARDS . " WHERE enabled = 1 AND source = 'custom' AND colors LIKE ?"
           . ' AND rarity IN (' . $place . ') AND power <= ? AND mana_value <= ?';
    $args  = array_merge(['%' . $color . '%'], $rarities, [$maxPower, $maxMv]);
    if ($tribe !== '') { $sql .= ' AND FIND_IN_SET(?, subtypes)'; $args[] = $tribe; }
    $sql .= ' ORDER BY RAND() LIMIT 400';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return array_map([Engine::class, 'card'], $stmt->fetchAll());
}

/**
 * Mazzo CPU TEMATICO di un VILLAGGIO (campagna 2.0): ristretto alla tribù del villaggio e scalato dal
 * def del livello (forza/rarità/dimensione). Carte DISTINTE (i pool tribali sono ampi); fallback
 * graduale se un pool è magro: tribù+cap → tribù senza cap → solo colore. Vedi src/Scenarios.php.
 * NB: i mazzi "a mano" (editor, card_ids con ripetizioni) arriveranno con gli instance-id in Fase 2.
 */
function build_scenario_ai_deck(string $valley, string $tribe, array $lvl): array
{
    $valley = strtoupper(substr($valley, 0, 1));
    if (!in_array($valley, ['W', 'U', 'B', 'R', 'G'], true)) { return []; }

    $size  = max(HAND_SIZE + LANES, (int) ($lvl['size'] ?? 12));
    $rar   = (array) ($lvl['rarities'] ?? ['common']);
    $maxP  = (int) ($lvl['max_power'] ?? 99);
    $maxMv = (int) ($lvl['max_mv'] ?? 9);
    $floor = HAND_SIZE + LANES; // minimo di candidati distinti per restare nel tema

    // Mazzo "a mano" (editor): lista esplicita di id. Distinti per ora (no ripetizioni finché niente instance-id).
    if (!empty($lvl['card_ids'])) {
        $pool  = load_cards_by_ids((array) $lvl['card_ids']);
        $cards = [];
        $seen  = [];
        foreach ((array) $lvl['card_ids'] as $id) {
            if (isset($pool[$id]) && empty($seen[$id])) { $cards[] = $pool[$id]; $seen[$id] = true; }
        }
        if (count($cards) >= HAND_SIZE) { shuffle($cards); return array_slice($cards, 0, $size); }
    }

    // 1) tribù + cap di difficoltà (il caso tipico, mantiene il tema)
    $cands = $tribe !== '' ? campaign_card_query($valley, $tribe, $rar, $maxP, $maxMv) : [];
    // 2) tribù senza cap di forza/rarità (pool stretto troppo magro)
    if (count($cands) < $floor && $tribe !== '') {
        $cands = campaign_card_query($valley, $tribe, ['common', 'uncommon', 'rare', 'mythic'], 99, 9);
    }
    // 3) solo colore con i cap (ultima spiaggia: tema perso ma battaglia possibile)
    if (count($cands) < $floor) {
        $cands = campaign_card_query($valley, '', $rar, $maxP, $maxMv);
    }
    if (count($cands) < HAND_SIZE) { return build_curved_deck($valley, $size); }

    return pack_curve($cands, $size);
}

/**
 * Assegna a OGNI carta del mazzo un id d'istanza univoco (`uid`), così due copie con lo stesso
 * `id` restano distinguibili in mano. Il vero `id` resta intatto per identità/immagini/log. Il
 * contatore è statico: nello stesso request (START) gli uid sono univoci anche tra mazzi diversi.
 */
function assign_uids(array $deck): array
{
    static $seq = 0;
    foreach ($deck as &$c) { $c['uid'] = 'u' . (++$seq); }
    unset($c);
    return $deck;
}

/** Identità d'istanza: `uid` se presente (mazzi nuovi), fallback su `id` per robustezza. */
function card_uid(array $c): string
{
    return (string) ($c['uid'] ?? $c['id']);
}

function find_in_hand(array $hand, string $uid): ?array
{
    foreach ($hand as $c) { if (card_uid($c) === $uid) { return $c; } }
    return null;
}

function remove_from_hand(array $hand, string $uid): array
{
    return array_values(array_filter($hand, static fn($c) => card_uid($c) !== $uid));
}

function refill(array &$hand, array &$pile, int $size): void
{
    while (count($hand) < $size && $pile) { $hand[] = array_shift($pile); }
}

/** Regola "stretta" del §6: riserva COSTO_MINIMO per ogni corsia rimanente. Null se valida. */
function validate_play(array $card, int $budget, int $lanesRemainingAfter): ?string
{
    $cost    = (int) $card['mana_value'];
    $reserve = $lanesRemainingAfter * COSTO_MINIMO;
    if ($cost > $budget) {
        return 'Mana insufficiente: costa ' . $cost . ', budget ' . $budget;
    }
    if ($cost > $budget - $reserve) {
        return 'Troppo costosa: tieni almeno ' . COSTO_MINIMO . ' mana per ognuna delle '
             . $lanesRemainingAfter . ' corsie rimanenti';
    }
    return null;
}

/** Risolve la corsia $i (già schierata da entrambi), applica i danni ai maghi, salva il risultato. */
/** Applica i "doni" (keyword scelte ogni 3 round) alle creature di un lato. */
function apply_boons(?array $card, array $boons): ?array
{
    if (!$card) return $card;
    foreach ($boons as $b) { $card[$b] = true; }
    return $card;
}

function resolve_and_apply(array &$m, int $i): array
{
    $cfg = $m['lanes'][$i];
    $pc = apply_boons($cfg['player'], $m['player_boons'] ?? []);
    $ac = apply_boons($cfg['ai'],     $m['ai_boons'] ?? []);

    $lifeBeforeP = $m['player_life'];
    $lifeBeforeA = $m['ai_life'];

    $r = Engine::resolveLane($pc, $ac, $cfg['attacker'] ?? null, $cfg['choice'] ?? null, !empty($cfg['blind']));
    $m['player_life'] -= $r['dmg_player'];
    $m['ai_life']     -= $r['dmg_ai'];
    $m['player_life'] += $r['player_gain'] ?? 0; // Legame vitale
    $m['ai_life']     += $r['ai_gain'] ?? 0;
    $m['lane_results'][$i] = $r;

    Logger::log([
        'ev'       => 'lane',
        'round'    => $m['round'],
        'lane'     => $i,
        'attacker' => $cfg['attacker'] ?? null,
        'choice'   => $cfg['choice'] ?? null,
        'blind'    => !empty($cfg['blind']),
        'player'   => Logger::brief($pc),   // carta EFFETTIVA (con doni applicati)
        'ai'       => Logger::brief($ac),
        'pboons'   => $m['player_boons'] ?? [],
        'aboons'   => $m['ai_boons'] ?? [],
        'mode'     => $r['mode'],
        'dmg'      => ['p' => $r['dmg_player'], 'a' => $r['dmg_ai']],
        'gain'     => ['p' => $r['player_gain'] ?? 0, 'a' => $r['ai_gain'] ?? 0],
        'dead'     => ['p' => $r['player_dead'], 'a' => $r['ai_dead']],
        'wound'    => ['p' => $r['player_wound'], 'a' => $r['ai_wound']],
        'life'     => ['p' => $lifeBeforeP . '->' . $m['player_life'], 'a' => $lifeBeforeA . '->' . $m['ai_life']],
    ]);

    return [
        'lane'        => $i,
        'mode'        => $r['mode'],
        'attacker'    => $cfg['attacker'] ?? null,
        'choice'      => $cfg['choice'] ?? null,
        'player'      => $cfg['player'],
        'ai'          => $cfg['ai'],
        'dmg_player'  => $r['dmg_player'],
        'dmg_ai'      => $r['dmg_ai'],
        'player_dead' => $r['player_dead'],
        'ai_dead'     => $r['ai_dead'],
        'player_wound' => $r['player_wound'],
        'ai_wound'     => $r['ai_wound'],
        'player_life_after' => $m['player_life'],
        'ai_life_after'     => $m['ai_life'],
    ];
}

/** Esito se un mago è a ≤0. A doppio-KO vince chi è "meno morto" (vita più alta); pari = DRAW. */
function battle_outcome(array $m): ?string
{
    $p = $m['player_life'] <= 0;
    $a = $m['ai_life'] <= 0;
    if ($p && $a) {
        if ($m['player_life'] > $m['ai_life']) { return Engine::PLAYER; }
        if ($m['ai_life'] > $m['player_life']) { return Engine::AI; }
        return Engine::DRAW;
    }
    if ($a) { return Engine::PLAYER; }
    if ($p) { return Engine::AI; }
    return null;
}

/** Stato di vita/round per la risposta. */
function life_meta(array $m): array
{
    return ['round' => $m['round'], 'player_life' => $m['player_life'], 'ai_life' => $m['ai_life']];
}

/**
 * Il giocatore PASSA su una corsia (non schiera carte): subisce per intero l'eventuale
 * attacco avversario (corsia 1 = la CPU attacca, corsia 2 = cieca). Sulla corsia 0 passare
 * significa solo non attaccare (nessun danno). Nessuna creatura del giocatore in campo.
 */
function resolve_pass(array &$m, int $i): array
{
    $cfg = $m['lanes'][$i];
    $ai  = $cfg['ai'] ?? null;
    $dmgPlayer = ($ai && ($i === 1 || $i === 2)) ? (int) $ai['power'] : 0;

    $lifeBeforeP = $m['player_life'];
    $r = ['dmg_player' => $dmgPlayer, 'dmg_ai' => 0, 'player_dead' => false, 'ai_dead' => false,
          'player_wound' => 0, 'ai_wound' => 0, 'mode' => 'PASS'];
    $m['player_life'] -= $dmgPlayer;
    $m['lane_results'][$i] = $r;

    Logger::log([
        'ev'    => 'pass', 'round' => $m['round'], 'lane' => $i,
        'ai'    => Logger::brief($ai), 'dmg' => ['p' => $dmgPlayer, 'a' => 0],
        'life'  => ['p' => $lifeBeforeP . '->' . $m['player_life'], 'a' => $m['ai_life']],
    ]);

    return [
        'lane' => $i, 'mode' => 'PASS', 'attacker' => $cfg['attacker'] ?? null, 'choice' => null,
        'player' => null, 'ai' => $ai, 'dmg_player' => $dmgPlayer, 'dmg_ai' => 0,
        'player_dead' => false, 'ai_dead' => false, 'player_wound' => 0, 'ai_wound' => 0,
        'player_life_after' => $m['player_life'], 'ai_life_after' => $m['ai_life'],
    ];
}

/** Fine round: superstiti (feriti) tornano in mano, morti fuori, ripesca, fatica. Ritorna {outcome,reason} se finisce, else null. */
function round_end(array &$m): ?array
{
    foreach ([0, 1, 2] as $i) {
        $cfg = $m['lanes'][$i];
        $r   = $m['lane_results'][$i];
        if (!$r['player_dead'] && !empty($cfg['player'])) {
            $pc = $cfg['player'];
            $pc['wounds'] = (int) ($pc['wounds'] ?? 0) + (int) $r['player_wound'];
            $m['player_hand'][] = $pc;
        }
        if (!$r['ai_dead'] && !empty($cfg['ai'])) {
            $ac = $cfg['ai'];
            $ac['wounds'] = (int) ($ac['wounds'] ?? 0) + (int) $r['ai_wound'];
            $m['ai_hand'][] = $ac;
        }
    }
    refill($m['player_hand'], $m['player_deck'], HAND_SIZE);
    refill($m['ai_hand'],     $m['ai_deck'],     HAND_SIZE);

    Logger::log([
        'ev' => 'round_end', 'round' => $m['round'],
        'hand'  => ['p' => count($m['player_hand']), 'a' => count($m['ai_hand'])],
        'deck'  => ['p' => count($m['player_deck']), 'a' => count($m['ai_deck'])],
        'life'  => ['p' => $m['player_life'], 'a' => $m['ai_life']],
    ]);

    if (count($m['player_hand']) < LANES) {
        Logger::log(['ev' => 'end', 'reason' => 'deckout_player', 'outcome' => Engine::AI, 'round' => $m['round']]);
        return ['outcome' => Engine::AI, 'reason' => 'deckout_player'];
    }
    if (count($m['ai_hand']) < LANES) {
        Logger::log(['ev' => 'end', 'reason' => 'deckout_ai', 'outcome' => Engine::PLAYER, 'round' => $m['round']]);
        return ['outcome' => Engine::PLAYER, 'reason' => 'deckout_ai'];
    }

    // Round successivo.
    $m['round']++;
    $m['remaining_budget'] = MANA_CAP;
    $m['ai_budget']        = MANA_CAP;
    $m['lanes']            = [null, null, null];
    $m['lane_results']     = [null, null, null];
    // Ogni 3 round: scelta di un dono (keyword), finché il pool ha ≥2 keyword (1 a te, 1 alla CPU).
    $needBoon = ($m['round'] % 3 === 0) && count($m['boon_pool'] ?? []) >= 2;
    $m['state']            = $needBoon ? 'BOON_PICK' : 'LANE0_PLAYER';

    // Fatica: dal round FATIGUE_FROM, danno crescente a entrambi i maghi (anti-stallo garantito).
    if ($m['round'] >= FATIGUE_FROM) {
        $fat = $m['round'] - FATIGUE_FROM + 1;
        $m['player_life'] -= $fat;
        $m['ai_life']     -= $fat;
        $m['fatigue'] = $fat;
        Logger::log(['ev' => 'fatigue', 'round' => $m['round'], 'amount' => $fat,
                     'life' => ['p' => $m['player_life'], 'a' => $m['ai_life']]]);
        $o = battle_outcome($m);
        if ($o !== null) {
            Logger::log(['ev' => 'end', 'reason' => 'fatigue', 'outcome' => $o, 'round' => $m['round'],
                         'life' => ['p' => $m['player_life'], 'a' => $m['ai_life']]]);
            return ['outcome' => $o, 'reason' => 'fatigue'];
        }
    }
    return null;
}

/** Info per la scelta del dono, se lo stato corrente la richiede (altrimenti null). */
function boon_pick_info(array $m): ?array
{
    return (($m['state'] ?? '') === 'BOON_PICK')
        ? ['round' => $m['round'], 'available' => array_values($m['boon_pool'])]
        : null;
}

switch ($action) {

    case 'START': {
        $deckId = (int) ($in['deck_id'] ?? 0);
        $color  = (string) ($in['color'] ?? '');
        $playerDeck = [];
        $campaignColor = null; // valorizzato solo in modalità campagna

        if ($deckId > 0) {
            $stmt = db()->prepare('SELECT * FROM ' . TBL_DECKS . ' WHERE id = ?');
            $stmt->execute([$deckId]);
            $deck = $stmt->fetch();
            if (!$deck) { json_err('Mazzo non trovato', 404); }
            $cardIds = json_decode($deck['card_ids'], true);
            if (!is_array($cardIds)) { json_err('Mazzo corrotto'); }
            $pool = load_cards_by_ids($cardIds);
            foreach ($cardIds as $id) { if (isset($pool[$id])) { $playerDeck[] = $pool[$id]; } }
        } elseif (!empty($in['from_campaign'])) {
            $camp = $_SESSION['campaign'] ?? null;
            if (!$camp || empty($camp['deck'])) { json_err('Nessuna campagna attiva (fai prima il draft)'); }
            $cur = $camp['current'] ?? null;
            if (!$cur || !Scenarios::isValley((string) ($cur['valley'] ?? ''))) {
                json_err('Nessun livello selezionato (torna all\'hub delle valli)');
            }
            foreach ($camp['deck'] as $cd) { $playerDeck[] = Engine::card($cd); }
            $campaignColor = (string) $cur['valley']; // valle = colore dei nemici
        } elseif ($color !== '') {
            $playerDeck = build_curved_deck($color, AI_DECK_SIZE);
        } else {
            json_err('Serve deck_id, color o from_campaign');
        }
        if (count($playerDeck) < HAND_SIZE) { json_err('Mazzo troppo piccolo (servono ' . HAND_SIZE . ' carte)'); }

        if ($campaignColor !== null) {
            // Nemico TEMATICO del villaggio: tribù del passo scelto, forza scalata dal livello.
            $step = (int) ($cur['step'] ?? 1);
            $sdef = Scenarios::stepDef($campaignColor, $step);
            if (!$sdef) { json_err('Livello non valido nella valle ' . $campaignColor); }
            $campaignLevel = $step;
            $campaignTribe = (string) $sdef['tribe'];
            $aiDeck = build_scenario_ai_deck($campaignColor, $campaignTribe, $sdef['level']);
            if (count($aiDeck) < HAND_SIZE) { json_err('Pool CPU insufficiente per ' . $campaignColor . '/' . ($campaignTribe ?: 'colore')); }
        } else {
            $aiColors = ['W', 'U', 'B', 'R', 'G'];
            shuffle($aiColors);
            $aiDeck = [];
            foreach ($aiColors as $col) {
                $aiDeck = build_curved_deck($col, AI_DECK_SIZE);
                if (count($aiDeck) >= HAND_SIZE) { break; }
            }
            if (count($aiDeck) < HAND_SIZE) { json_err('Pool carte insufficiente per la CPU (importa seed.sql?)'); }
        }

        // Id d'istanza univoco a ogni carta (mano + pescate): due copie con stesso id restano distinte.
        $playerDeck = assign_uids($playerDeck);
        $aiDeck     = assign_uids($aiDeck);

        // Immagini di TUTTO il mazzo del giocatore (mano + pesche future): il client le precarica
        // prima di mostrare il tavolo, così non "pop-ano" a scatti durante il match.
        $preloadImages = array_values(array_unique(array_filter(array_map(
            static fn($c) => (string) ($c['image_url'] ?? ''), $playerDeck
        ))));

        shuffle($playerDeck);
        shuffle($aiDeck);
        $playerHand = array_splice($playerDeck, 0, HAND_SIZE);
        $aiHand     = array_splice($aiDeck, 0, HAND_SIZE);

        $_SESSION['match'] = [
            'state'            => 'LANE0_PLAYER',
            'round'            => 1,
            'player_life'      => MAGE_LIFE,
            'ai_life'          => MAGE_LIFE,
            'remaining_budget' => MANA_CAP,
            'ai_budget'        => MANA_CAP,
            'player_hand'      => $playerHand,
            'ai_hand'          => $aiHand,
            'player_deck'      => $playerDeck,
            'ai_deck'          => $aiDeck,
            'lanes'            => [null, null, null],
            'lane_results'     => [null, null, null],
            'player_boons'     => [],
            'ai_boons'         => [],
            'boon_pool'        => ['trample', 'flying', 'first_strike', 'deathtouch'],
            'campaign'         => $campaignColor !== null,
            'camp_cur'         => $campaignColor !== null ? ($_SESSION['campaign']['current'] ?? null) : null,
            'xp_bucket'        => $deckId > 0 ? 'deck' . $deckId : ($color !== '' ? 'color' . preg_replace('/[^A-Z]/', '', strtoupper($color)) : 'duel'),
        ];

        // In campagna: marca lo stato come "in battaglia" (FINISH assegnerà il bottino alla vittoria).
        if ($campaignColor !== null && isset($_SESSION['campaign'])) {
            $_SESSION['campaign']['phase'] = 'fighting';
            campaign_store_save($_SESSION['campaign']);
        }

        Logger::log([
            'ev'       => 'start',
            'mode'     => $campaignColor !== null ? 'campaign' : ($deckId ? 'deck' : 'quick'),
            'mage_life'=> MAGE_LIFE, 'mana_cap' => MANA_CAP, 'hand_size' => HAND_SIZE,
            'campaign_color' => $campaignColor,
            'step'     => $campaignColor !== null ? ($campaignLevel ?? null) : null,
            'tribe'    => $campaignColor !== null ? ($campaignTribe ?? null) : null,
            'player_hand' => Logger::deckBrief($playerHand),
            'player_deck' => Logger::deckBrief($playerDeck),
            'ai_deck_size'=> count($aiHand) + count($aiDeck),
        ]);

        json_out(array_merge([
            'ok' => true, 'state' => 'LANE0_PLAYER', 'hand' => $playerHand,
            'remaining_budget' => MANA_CAP, 'mage_life' => MAGE_LIFE, 'mana_cap' => MANA_CAP,
            'lanes' => LANES, 'ai_hand_count' => count($aiHand),
            'player_deck_count' => count($playerDeck),
            'preload_images' => $preloadImages,
            'prompt' => 'Round 1 — Corsia 0: ATTACCHI tu. Scegli una creatura.',
        ], life_meta($_SESSION['match'])));
    }

    case 'LANE0_PLAYER': {
        $m = match_state();
        if (!$m || $m['state'] !== 'LANE0_PLAYER') { json_err('Stato non valido'); }
        if (!empty($in['pass'])) {
            $m['lanes'][0] = ['player' => null, 'attacker' => Engine::PLAYER, 'passed' => true];
            $detail = resolve_pass($m, 0);
            $m['state'] = 'LANE1_AI';
            $_SESSION['match'] = $m;
            json_out(array_merge(['ok' => true, 'state' => 'LANE1_AI', 'lane' => 0,
                'ai_card' => null, 'ai_choice' => null, 'resolution' => $detail,
                'remaining_budget' => $m['remaining_budget'],
                'prompt' => 'Hai passato: nessun attacco sulla corsia 0.'], life_meta($m)));
        }
        $cardId = (string) ($in['card_id'] ?? '');
        $card   = find_in_hand($m['player_hand'], $cardId);
        if (!$card) { json_err('Carta non in mano'); }
        if ($e = validate_play($card, (int) $m['remaining_budget'], 2)) { json_err($e); }

        // Regola "una sola abilità": se la carta ha 2+ keyword, tieni solo quella scelta (kw).
        $card = Engine::keepOneKeyword($card, isset($in['kw']) ? (string) $in['kw'] : null);
        $m['lanes'][0] = ['player' => $card, 'attacker' => Engine::PLAYER];
        $m['player_hand'] = remove_from_hand($m['player_hand'], $cardId);
        $m['remaining_budget'] -= (int) $card['mana_value'];

        $def = AI::pickDefense($m['ai_hand'], $card, (int) $m['ai_budget'], 2);
        if (!$def) { json_err('La CPU non può difendere (mano vuota)'); }
        $def['card'] = Engine::keepOneKeyword($def['card'], Engine::pickAiKeyword($def['card'], false)); // CPU difende
        $m['lanes'][0]['ai']     = $def['card'];
        $m['lanes'][0]['choice'] = $def['choice'];
        $m['ai_hand']    = remove_from_hand($m['ai_hand'], card_uid($def['card']));
        $m['ai_budget'] -= (int) $def['card']['mana_value'];

        // RISOLVE la corsia 0 subito.
        $detail = resolve_and_apply($m, 0);
        $outcome = battle_outcome($m);
        if ($outcome !== null) {
            varco_xp_on_end($m, $outcome);
            unset($_SESSION['match']);
            json_out(array_merge(['ok' => true, 'state' => 'DONE', 'lane' => 0,
                'player_card' => $card, 'ai_card' => $def['card'], 'ai_choice' => $def['choice'],
                'resolution' => $detail, 'outcome' => $outcome, 'reason' => 'ko'], life_meta($m)));
        }

        $m['state'] = 'LANE1_AI';
        $_SESSION['match'] = $m;
        json_out(array_merge([
            'ok' => true, 'state' => 'LANE1_AI', 'lane' => 0,
            'player_card' => $card, 'ai_card' => $def['card'], 'ai_choice' => $def['choice'], 'resolution' => $detail,
            'remaining_budget' => $m['remaining_budget'],
            'prompt' => 'Corsia 0 risolta. Ora attacca la CPU.',
        ], life_meta($m)));
    }

    case 'LANE1_AI': {
        $m = match_state();
        if (!$m || $m['state'] !== 'LANE1_AI') { json_err('Stato non valido'); }
        $aiCard = AI::pickBest($m['ai_hand'], (int) $m['ai_budget'], 1);
        if (!$aiCard) { json_err('La CPU non può attaccare sulla corsia 1'); }
        $aiCard = Engine::keepOneKeyword($aiCard, Engine::pickAiKeyword($aiCard, true)); // CPU attacca
        $m['lanes'][1] = ['ai' => $aiCard, 'attacker' => Engine::AI];
        $m['ai_hand']  = remove_from_hand($m['ai_hand'], card_uid($aiCard));
        $m['ai_budget'] -= (int) $aiCard['mana_value'];

        $m['state'] = 'LANE1_PLAYER';
        $_SESSION['match'] = $m;
        json_out([
            'ok' => true, 'state' => 'LANE1_PLAYER', 'lane' => 1,
            'ai_card' => $aiCard, 'remaining_budget' => $m['remaining_budget'],
            'prompt' => 'Corsia 1: la CPU ti attacca. Scegli una creatura e decidi: PARA o SUBISCI.',
        ]);
    }

    case 'LANE1_PLAYER': {
        $m = match_state();
        if (!$m || $m['state'] !== 'LANE1_PLAYER') { json_err('Stato non valido'); }
        if (!empty($in['pass'])) {
            $m['lanes'][1]['player'] = null;
            $m['lanes'][1]['choice'] = Engine::FACE;
            $m['lanes'][1]['passed'] = true;
            $detail  = resolve_pass($m, 1);
            $outcome = battle_outcome($m);
            if ($outcome !== null) {
                varco_xp_on_end($m, $outcome);
                unset($_SESSION['match']);
                json_out(array_merge(['ok' => true, 'state' => 'DONE', 'lane' => 1,
                    'player_card' => null, 'resolution' => $detail,
                    'outcome' => $outcome, 'reason' => 'ko'], life_meta($m)));
            }
            $m['state'] = 'LANE2_BLIND';
            $_SESSION['match'] = $m;
            json_out(array_merge(['ok' => true, 'state' => 'LANE2_BLIND', 'lane' => 1,
                'player_card' => null, 'resolution' => $detail, 'remaining_budget' => $m['remaining_budget'],
                'prompt' => 'Hai subito l\'attacco. Corsia 2: alla cieca.'], life_meta($m)));
        }
        $cardId = (string) ($in['card_id'] ?? '');
        $choice = (string) ($in['choice'] ?? Engine::FACE);
        $card   = find_in_hand($m['player_hand'], $cardId);
        if (!$card) { json_err('Carta non in mano'); }
        if ($e = validate_play($card, (int) $m['remaining_budget'], 1)) { json_err($e); }

        if (!in_array($choice, [Engine::BLOCK, Engine::FACE], true)) { $choice = Engine::FACE; }
        $card = Engine::keepOneKeyword($card, isset($in['kw']) ? (string) $in['kw'] : null); // una sola abilità
        if ($choice === Engine::BLOCK && !Engine::canBlock($card, $m['lanes'][1]['ai'])) { $choice = Engine::FACE; }

        $m['lanes'][1]['player'] = $card;
        $m['lanes'][1]['choice'] = $choice;
        $m['player_hand'] = remove_from_hand($m['player_hand'], $cardId);
        $m['remaining_budget'] -= (int) $card['mana_value'];

        // RISOLVE la corsia 1 subito.
        $detail = resolve_and_apply($m, 1);
        $outcome = battle_outcome($m);
        if ($outcome !== null) {
            varco_xp_on_end($m, $outcome);
            unset($_SESSION['match']);
            json_out(array_merge(['ok' => true, 'state' => 'DONE', 'lane' => 1,
                'player_card' => $card, 'choice' => $choice,
                'resolution' => $detail, 'outcome' => $outcome, 'reason' => 'ko'], life_meta($m)));
        }

        $m['state'] = 'LANE2_BLIND';
        $_SESSION['match'] = $m;
        json_out(array_merge([
            'ok' => true, 'state' => 'LANE2_BLIND', 'lane' => 1,
            'player_card' => $card, 'choice' => $choice, 'resolution' => $detail,
            'remaining_budget' => $m['remaining_budget'],
            'prompt' => 'Corsia 1 risolta. Corsia 2: alla cieca.',
        ], life_meta($m)));
    }

    case 'LANE2_BLIND': {
        $m = match_state();
        if (!$m || $m['state'] !== 'LANE2_BLIND') { json_err('Stato non valido'); }
        if (!empty($in['pass'])) {
            $aiCard = AI::pickBest($m['ai_hand'], (int) $m['ai_budget'], 0);
            if ($aiCard) { $aiCard = Engine::keepOneKeyword($aiCard, Engine::pickAiKeyword($aiCard, true)); }
            $m['lanes'][2] = ['player' => null, 'blind' => true, 'passed' => true];
            if ($aiCard) {
                $m['lanes'][2]['ai'] = $aiCard;
                $m['ai_hand']    = remove_from_hand($m['ai_hand'], card_uid($aiCard));
                $m['ai_budget'] -= (int) $aiCard['mana_value'];
            }
            $detail  = resolve_pass($m, 2);
            $outcome = battle_outcome($m);
            if ($outcome !== null) {
                varco_xp_on_end($m, $outcome);
                unset($_SESSION['match']);
                json_out(array_merge(['ok' => true, 'state' => 'DONE', 'lane' => 2,
                    'ai_card' => $aiCard, 'resolution' => $detail,
                    'outcome' => $outcome, 'reason' => 'ko'], life_meta($m)));
            }
            $end = round_end($m);
            if ($end !== null) {
                varco_xp_on_end($m, $end['outcome']);
                unset($_SESSION['match']);
                json_out(array_merge(['ok' => true, 'state' => 'DONE', 'lane' => 2,
                    'ai_card' => $aiCard, 'resolution' => $detail,
                    'outcome' => $end['outcome'], 'reason' => $end['reason']], life_meta($m)));
            }
            $_SESSION['match'] = $m;
            json_out(array_merge(['ok' => true, 'state' => 'CONTINUE', 'lane' => 2,
                'ai_card' => $aiCard, 'resolution' => $detail, 'fatigue' => $m['fatigue'] ?? 0,
                'hand' => $m['player_hand'], 'remaining_budget' => $m['remaining_budget'],
                'mana_cap' => MANA_CAP, 'ai_hand_count' => count($m['ai_hand']),
                'player_deck_count' => count($m['player_deck']),
                'boon_pick' => boon_pick_info($m),
                'prompt' => 'Round ' . $m['round'] . ' — Corsia 0: attacchi tu.'], life_meta($m)));
        }
        $cardId = (string) ($in['card_id'] ?? '');
        $card   = find_in_hand($m['player_hand'], $cardId);
        if (!$card) { json_err('Carta non in mano'); }
        if ($e = validate_play($card, (int) $m['remaining_budget'], 0)) { json_err($e); }

        $card = Engine::keepOneKeyword($card, isset($in['kw']) ? (string) $in['kw'] : null); // una sola abilità
        $m['lanes'][2] = ['player' => $card, 'blind' => true];
        $m['player_hand'] = remove_from_hand($m['player_hand'], $cardId);
        $m['remaining_budget'] -= (int) $card['mana_value'];

        $aiCard = AI::pickBest($m['ai_hand'], (int) $m['ai_budget'], 0);
        if (!$aiCard) { json_err('La CPU non può schierare sulla corsia 2'); }
        $aiCard = Engine::keepOneKeyword($aiCard, Engine::pickAiKeyword($aiCard, true)); // cieca = attacco
        $m['lanes'][2]['ai'] = $aiCard;
        $m['ai_hand']    = remove_from_hand($m['ai_hand'], card_uid($aiCard));
        $m['ai_budget'] -= (int) $aiCard['mana_value'];

        // RISOLVE la corsia 2 subito.
        $detail = resolve_and_apply($m, 2);
        $outcome = battle_outcome($m);
        if ($outcome !== null) {
            varco_xp_on_end($m, $outcome);
            unset($_SESSION['match']);
            json_out(array_merge(['ok' => true, 'state' => 'DONE', 'lane' => 2,
                'ai_card' => $aiCard, 'resolution' => $detail,
                'outcome' => $outcome, 'reason' => 'ko'], life_meta($m)));
        }

        // Nessun KO: fine round (ricicla/ripesca/fatica) -> deck-out/fatica o round successivo.
        $end = round_end($m);
        if ($end !== null) {
            varco_xp_on_end($m, $end['outcome']);
            unset($_SESSION['match']);
            json_out(array_merge(['ok' => true, 'state' => 'DONE', 'lane' => 2,
                'ai_card' => $aiCard, 'resolution' => $detail,
                'outcome' => $end['outcome'], 'reason' => $end['reason']], life_meta($m)));
        }

        $_SESSION['match'] = $m;
        json_out(array_merge([
            'ok' => true, 'state' => 'CONTINUE', 'lane' => 2,
            'player_card' => $card, 'ai_card' => $aiCard, 'resolution' => $detail, 'fatigue' => $m['fatigue'] ?? 0,
            'hand' => $m['player_hand'], 'remaining_budget' => $m['remaining_budget'],
            'mana_cap' => MANA_CAP, 'ai_hand_count' => count($m['ai_hand']),
            'player_deck_count' => count($m['player_deck']),
            'boon_pick' => boon_pick_info($m),
            'prompt' => 'Round ' . $m['round'] . ' — Corsia 0: attacchi tu.',
        ], life_meta($m)));
    }

    case 'PICK_BOON': {
        $m = match_state();
        if (!$m || ($m['state'] ?? '') !== 'BOON_PICK') { json_err('Stato non valido'); }
        $kw = (string) ($in['keyword'] ?? '');
        if (!in_array($kw, $m['boon_pool'], true)) { json_err('Dono non disponibile'); }

        // Il giocatore prende $kw; la CPU prende un dono DIVERSO (a caso fra i restanti).
        $m['player_boons'][] = $kw;
        $m['boon_pool'] = array_values(array_filter($m['boon_pool'], static fn($k) => $k !== $kw));

        $aiKw = null;
        if ($m['boon_pool']) {
            $aiKw = $m['boon_pool'][array_rand($m['boon_pool'])];
            $m['ai_boons'][] = $aiKw;
            $m['boon_pool'] = array_values(array_filter($m['boon_pool'], static fn($k) => $k !== $aiKw));
        }

        $m['state'] = 'LANE0_PLAYER';
        $_SESSION['match'] = $m;
        json_out(array_merge([
            'ok' => true, 'state' => 'LANE0_PLAYER',
            'player_boons' => $m['player_boons'], 'ai_boons' => $m['ai_boons'],
            'player_pick' => $kw, 'ai_pick' => $aiKw,
            'prompt' => 'Round ' . $m['round'] . ' — Corsia 0: attacchi tu.',
        ], life_meta($m)));
    }

    case 'STATE': {
        json_out(['ok' => true, 'match' => match_state()]);
    }

    default:
        json_err('Azione sconosciuta: ' . $action);
}
