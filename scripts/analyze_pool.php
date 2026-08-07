<?php
/** Analisi una-tantum della cache bulk per stimare l'ampliamento del pool. Non scrive nulla. */
declare(strict_types=1);
ini_set('memory_limit', '3072M');

$cards = json_decode((string) file_get_contents(__DIR__ . '/oracle_cards.json'), true);
echo 'Carte nel bulk: ' . count($cards) . "\n\n";

$BANNED = ['token','double_faced_token','emblem','art_series','scheme','planar','vanguard','meld','modal_dfc','transform','flip','split','adventure','class','saga','leveler'];

// keyword "combat-friendly" candidate all'ampliamento
$EXPANDED = ['Flying','First strike','Deathtouch','Trample','Vigilance','Lifelink','Reach','Menace',
             'Defender','Haste','Double strike','Indestructible','Hexproof','Ward','Flash','Protection'];

function textClean(string $text, array $allowed): bool {
    $text = trim($text);
    if ($text === '') return true;
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $line = trim(preg_replace('/\([^)]*\)/', '', trim($line)));
        $line = rtrim($line, " .,;");
        if ($line === '') continue;
        foreach (array_map('trim', explode(',', $line)) as $part) {
            $ok = false;
            foreach ($allowed as $kw) { if (strcasecmp($part, $kw) === 0) { $ok = true; break; } }
            if (!$ok) return false;
        }
    }
    return true;
}

function passes(array $card, array $BANNED): bool {
    if (strpos($card['type_line'] ?? '', 'Creature') === false) return false;
    if (in_array($card['layout'] ?? 'normal', $BANNED, true)) return false;
    if (($card['set_type'] ?? '') === 'funny' || !empty($card['funny'])) return false;
    $p = $card['power'] ?? null; $t = $card['toughness'] ?? null;
    if (!is_string($p) || !preg_match('/^\d+$/', $p)) return false;
    if (!is_string($t) || !preg_match('/^\d+$/', $t)) return false;
    return true;
}

function tally(array $cards, array $BANNED, array $allowed, bool $requireKwSubset): array {
    $seen = []; $byColor = ['W'=>0,'U'=>0,'B'=>0,'R'=>0,'G'=>0,'multi'=>0,'colorless'=>0];
    $byRar = ['common'=>0,'uncommon'=>0,'rare'=>0,'mythic'=>0]; $total = 0;
    foreach ($cards as $c) {
        if (!passes($c, $BANNED)) continue;
        if ($requireKwSubset) {
            $kwOk = true;
            foreach (($c['keywords'] ?? []) as $k) {
                $ok = false; foreach ($allowed as $a) { if (strcasecmp($k,$a)===0){$ok=true;break;} }
                if (!$ok) { $kwOk = false; break; }
            }
            if (!$kwOk) continue;
            if (!textClean((string)($c['oracle_text'] ?? ''), $allowed)) continue;
        }
        $oid = $c['oracle_id'] ?? null;
        if ($oid === null || isset($seen[$oid])) continue;
        $seen[$oid] = true;
        $total++;
        $cols = $c['colors'] ?? [];
        if (count($cols) === 0) $byColor['colorless']++;
        elseif (count($cols) > 1) $byColor['multi']++;
        else $byColor[$cols[0]]++;
        $r = $c['rarity'] ?? 'common'; if (isset($byRar[$r])) $byRar[$r]++;
    }
    return ['total'=>$total,'byColor'=>$byColor,'byRar'=>$byRar];
}

function show(string $label, array $r): void {
    echo "== $label ==\n  TOT {$r['total']}  |  ";
    foreach ($r['byColor'] as $k=>$v) echo "$k:$v ";
    echo "\n  rarità: ";
    foreach ($r['byRar'] as $k=>$v) echo "$k:$v ";
    echo "\n\n";
}

show('ATTUALE (4 keyword: Flying/FS/DT/Trample)', tally($cards,$BANNED,['Flying','First strike','Deathtouch','Trample'],true));
show('ESPANSO (16 keyword combat)',               tally($cards,$BANNED,$EXPANDED,true));
show('TUTTE le creature P/T intero (ignora abilità)', tally($cards,$BANNED,[],false));

// quante creature aggiunge OGNI singola keyword extra (oltre le 4 attuali)
$base = ['Flying','First strike','Deathtouch','Trample'];
echo "Guadagno per keyword aggiunta (French-vanilla che diventerebbero ammissibili):\n";
foreach (array_diff($EXPANDED, $base) as $extra) {
    $set = array_merge($base, [$extra]);
    $r = tally($cards, $BANNED, $set, true);
    echo "  +$extra => " . $r['total'] . "\n";
}
