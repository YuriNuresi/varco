<?php
/**
 * Logger diagnostico delle battaglie — scrive JSONL in magic/logs/battle.jsonl.
 *
 * Pensato per debug "il combattimento non torna": ogni evento (matchup, corsia risolta, passo,
 * fine round, esito) è una riga JSON autoconsistente con TUTTO ciò che serve a rifare i conti
 * a mano (carte effettive con keyword/doni applicati, danni, morti, ferite, vita prima→dopo).
 *
 * Codici keyword in `kw`:  F=Flying  S=First strike  D=Deathtouch  T=Trample
 *                          X=Double strike  L=Lifelink  R=Reach  W=Defender (wall).
 * Disattivabile con LOG_BATTLE=false in config.php. Scaricabile via /api/logs.php?key=INSTALL_KEY.
 */

declare(strict_types=1);

final class Logger
{
    private static function file(): ?string
    {
        $dir = __DIR__ . '/../logs';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        return $dir . '/battle.jsonl';
    }

    /** Descrittore compatto di una carta (null-safe), per i log. */
    public static function brief(?array $c): ?array
    {
        if (!$c) { return null; }
        $kw = (!empty($c['flying']) ? 'F' : '')
            . (!empty($c['first_strike']) ? 'S' : '')
            . (!empty($c['deathtouch']) ? 'D' : '')
            . (!empty($c['trample']) ? 'T' : '')
            . (!empty($c['double_strike']) ? 'X' : '')
            . (!empty($c['lifelink']) ? 'L' : '')
            . (!empty($c['reach']) ? 'R' : '')
            . (!empty($c['defender']) ? 'W' : '');
        return [
            'id'   => $c['id'] ?? null,
            'name' => (string) ($c['name'] ?? '?'),
            'mv'   => (int) ($c['mana_value'] ?? 0),
            'pt'   => ((int) ($c['power'] ?? 0)) . '/' . ((int) ($c['toughness'] ?? 0)),
            'w'    => (int) ($c['wounds'] ?? 0),       // ferite persistenti
            'kw'   => $kw,
        ];
    }

    /** Lista compatta "Nome(mv)" per loggare la composizione di un mazzo. */
    public static function deckBrief(array $cards): array
    {
        return array_map(static fn($c) => ($c['name'] ?? '?') . '(' . (int) ($c['mana_value'] ?? 0) . ')', $cards);
    }

    /** Appende una riga JSON al log (no-op se LOG_BATTLE è false o la cartella non è scrivibile). */
    public static function log(array $entry): void
    {
        if (!defined('LOG_BATTLE') || !LOG_BATTLE) { return; }
        $file = self::file();
        if ($file === null) { return; }

        $sid = function_exists('session_id') ? (string) session_id() : '';
        $row = array_merge(['t' => date('Y-m-d H:i:s'), 'sid' => substr($sid !== '' ? $sid : '-', 0, 8)], $entry);

        @file_put_contents(
            $file,
            json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}
