<?php
/**
 * Campagna 2.0 — modello VALLI → VILLAGGI → LIVELLI (vedi config.php CAMPAIGN_*).
 *
 * Una VALLE è un colore (W/U/B/R/G) e contiene una sequenza ordinata di VILLAGGI; un VILLAGGIO è uno
 * scenario a tribù unica con N livelli crescenti (l'ultimo è il boss). I "passi" (step) sono i livelli
 * appiattiti su tutta la valle: step 1 = primo livello del primo villaggio, e così via attraverso i
 * villaggi in ordine. Il progresso del giocatore è uno step massimo superato per valle.
 *
 * Gli scenari vivono in tabella `magic_scenarios` (editabile/schedulabile). Se la tabella manca o una
 * valle non ha villaggi abilitati, si ripiega sui DEFAULT di config (un villaggio tematico per colore).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

final class Scenarios
{
    public const VALLEYS = ['W', 'U', 'B', 'R', 'G'];

    public static function isValley(string $v): bool
    {
        return in_array($v, self::VALLEYS, true);
    }

    /** Normalizza un def di livello aggiungendo i campi mancanti dai default. */
    private static function normLevel(array $lvl, int $i, int $count): array
    {
        return [
            'name'      => (string) ($lvl['name'] ?? ('Livello ' . ($i + 1))),
            'rarities'  => array_values((array) ($lvl['rarities'] ?? ['common'])),
            'max_power' => (int) ($lvl['max_power'] ?? 99),
            'max_mv'    => (int) ($lvl['max_mv'] ?? 9),
            'size'      => (int) ($lvl['size'] ?? 12),
            'boss'      => (bool) ($lvl['boss'] ?? ($i === $count - 1)), // ultimo livello = boss di default
            'card_ids'  => array_values(array_filter((array) ($lvl['card_ids'] ?? []), 'is_string')), // mazzo "a mano" (editor)
        ];
    }

    /** Villaggio di default per una valle (un solo villaggio tematico, dalla mappa tribù di config). */
    private static function defaultVillages(string $valley): array
    {
        $t = CAMPAIGN_VALLEY_TRIBES[$valley] ?? ['tribe' => '', 'name' => 'Valle ' . $valley];
        $count  = count(CAMPAIGN_VILLAGE_LEVELS);
        $levels = [];
        foreach (CAMPAIGN_VILLAGE_LEVELS as $i => $lvl) { $levels[] = self::normLevel($lvl, $i, $count); }

        return [[
            'id'     => 'def-' . $valley . '-1',
            'valley' => $valley,
            'name'   => $t['name'],
            'tribe'  => $t['tribe'],
            'ord'    => 1,
            'levels' => $levels,
        ]];
    }

    /**
     * Villaggi abilitati di una valle, ordinati per `ord`. Dal DB se possibile, altrimenti default.
     * @return array<int,array{id:string|int,valley:string,name:string,tribe:string,ord:int,levels:array}>
     */
    public static function villages(string $valley): array
    {
        if (!self::isValley($valley)) { return []; }

        try {
            $stmt = db()->prepare('SELECT id,name,tribe,ord,levels FROM ' . TBL_SCENARIOS
                . ' WHERE valley = ? AND enabled = 1 ORDER BY ord ASC, id ASC');
            $stmt->execute([$valley]);
            $rows = $stmt->fetchAll();
        } catch (Throwable $e) {
            return self::defaultVillages($valley); // tabella mancante o non installata: fallback
        }

        if (!$rows) { return self::defaultVillages($valley); }

        $out = [];
        foreach ($rows as $r) {
            $levels = json_decode((string) $r['levels'], true);
            if (!is_array($levels) || !$levels) { continue; }
            $count = count($levels);
            $norm  = [];
            foreach (array_values($levels) as $i => $lvl) {
                if (is_array($lvl)) { $norm[] = self::normLevel($lvl, $i, $count); }
            }
            if (!$norm) { continue; }
            $out[] = [
                'id'     => $r['id'],
                'valley' => $valley,
                'name'   => (string) $r['name'],
                'tribe'  => (string) $r['tribe'],
                'ord'    => (int) $r['ord'],
                'levels' => $norm,
            ];
        }
        return $out ?: self::defaultVillages($valley);
    }

    /**
     * Appiattisce i villaggi di una valle nei "passi" giocabili (1-based, continui attraverso i villaggi).
     * Ogni passo conosce villaggio/tribù/livello, se è boss, e se è l'ultimo livello del villaggio
     * (dopo il quale c'è una LOCANDA, tranne all'ultimo villaggio della valle).
     * @return array<int,array>
     */
    public static function steps(string $valley): array
    {
        $villages = self::villages($valley);
        $steps = [];
        $n = 0;
        $vi = 0;
        $lastVi = count($villages) - 1;
        foreach ($villages as $vidx => $v) {
            $lvlCount = count($v['levels']);
            foreach ($v['levels'] as $li => $lvl) {
                $n++;
                $lastInVillage = ($li === $lvlCount - 1);
                $steps[] = [
                    'step'           => $n,
                    'village_id'     => $v['id'],
                    'village_index'  => $vidx,
                    'village_name'   => $v['name'],
                    'tribe'          => $v['tribe'],
                    'level_index'    => $li,
                    'level_name'     => $lvl['name'],
                    'boss'           => $lvl['boss'],
                    'level'          => $lvl,
                    'last_in_village'=> $lastInVillage,
                    // c'è una locanda DOPO questo passo se chiude un villaggio e non è l'ultimo della valle
                    'inn_after'      => $lastInVillage && $vidx < $lastVi,
                ];
            }
            $vi++;
        }
        return $steps;
    }

    public static function totalSteps(string $valley): int
    {
        return count(self::steps($valley));
    }

    /** Il def del passo richiesto (1-based), o null se fuori range. */
    public static function stepDef(string $valley, int $step): ?array
    {
        foreach (self::steps($valley) as $s) {
            if ($s['step'] === $step) { return $s; }
        }
        return null;
    }
}
