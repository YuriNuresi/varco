<?php
/**
 * CPU "stupida" (placer deterministico) per il modello vita/para-subisci.
 *
 * Ruoli:
 *  - Corsia 0: la CPU DIFENDE (il giocatore attacca) -> pickDefense (carta + scelta para/subisci).
 *  - Corsia 1: la CPU ATTACCA -> pickBest (power alto).
 *  - Corsia 2: alla cieca -> pickBest.
 */

declare(strict_types=1);

require_once __DIR__ . '/Engine.php';

final class AI
{
    /**
     * Carta migliore affordable (power alto) che lascia budget per le corsie successive.
     * Fallback: la più economica.
     * @param array<int,array<string,mixed>> $hand
     * @return array<string,mixed>|null
     */
    public static function pickBest(array $hand, int $budget, int $lanesRemainingAfter): ?array
    {
        $best = null;
        foreach ($hand as $idx => $card) {
            $cost = (int) $card['mana_value'];
            if ($cost > $budget) {
                continue;
            }
            $others = $hand; unset($others[$idx]);
            $reserve = Engine::reserveCost($others, $lanesRemainingAfter); // budget per le corsie restanti
            $leavesEnough = ($budget - $cost) >= $reserve;
            $atk = self::atkValue($card);
            if ($best === null || self::better($atk, $leavesEnough, $best, $cost)) {
                $best = ['card' => $card, 'le' => $leavesEnough, 'p' => $atk, 'c' => $cost];
            }
        }
        return $best['card'] ?? self::cheapest($hand, $budget);
    }

    /** Potenza d'attacco effettiva per la scelta: Difensore = 0 (non attacca), Doppio attacco = ×2. */
    private static function atkValue(array $card): int
    {
        if (!empty($card['defender'])) { return 0; }
        $p = max(0, (int) $card['power']);
        return !empty($card['double_strike']) ? $p * 2 : $p;
    }

    /** @param array<string,mixed> $best */
    private static function better(int $atk, bool $leavesEnough, array $best, int $cost): bool
    {
        if ($leavesEnough !== $best['le']) { return $leavesEnough; }
        if ($atk !== $best['p']) { return $atk > $best['p']; }
        return $cost < $best['c'];
    }

    /**
     * Corsia 0: la CPU difende contro la carta-attaccante del giocatore (attacker = PLAYER).
     *
     * Per ogni candidato valuta due opzioni, dal punto di vista del difensore (più alto = meglio):
     *   - SUBIRE: incassa `attacker.power` in faccia, tiene la creatura. Valore = -attacker.power
     *             (uguale per ogni candidato → fra i "subisci" si sceglie il più economico).
     *   - PARARE (se legale): simula lo scontro con l'Engine. Valore =
     *             (+attacker.power se l'attaccante muore)  − (creatura difensore se muore)
     *             − (eventuale Travolgere che passa comunque in faccia).
     * Sceglie carta + scelta col valore migliore, rispettando la riserva di budget.
     *
     * @param array<int,array<string,mixed>> $hand
     * @param array<string,mixed> $attacker  carta del giocatore (attaccante)
     * @return array{card:array<string,mixed>,choice:string}|null
     */
    public static function pickDefense(array $hand, array $attacker, int $budget, int $lanesRemainingAfter): ?array
    {
        $atkPower = (int) $attacker['power'];
        $bestRank = null;
        $bestPick = null;

        foreach ($hand as $idx => $card) {
            $cost = (int) $card['mana_value'];
            if ($cost > $budget) {
                continue;
            }
            $others = $hand; unset($others[$idx]);
            $reserve = Engine::reserveCost($others, $lanesRemainingAfter);
            $leavesEnough = ($budget - $cost) >= $reserve;

            // SUBIRE: il difensore incassa, la creatura resta a guardia.
            $valueFace = -$atkPower;
            $choice    = Engine::FACE;
            $value     = $valueFace;

            // PARARE: scontro fra creature (solo se legale).
            if (Engine::canBlock($card, $attacker)) {
                $r = Engine::resolveLane($attacker, $card, Engine::PLAYER, Engine::BLOCK);
                $attackerDead = $r['player_dead'];   // l'attaccante è la carta del player
                $defenderDead = $r['ai_dead'];        // il difensore è la nostra carta
                $valueBlock = ($attackerDead ? $atkPower : 0)
                            - ($defenderDead ? (int) $card['power'] : 0)
                            - (int) $r['dmg_ai'];     // Travolgere dell'attaccante in faccia alla CPU
                if ($valueBlock > $value) {
                    $value  = $valueBlock;
                    $choice = Engine::BLOCK;
                }
            }

            // Ordina: prima chi lascia budget, poi valore alto, poi carta più economica.
            $rank = [$leavesEnough ? 1 : 0, $value, -$cost];
            if ($bestRank === null || $rank > $bestRank) {
                $bestRank = $rank;
                $bestPick = ['card' => $card, 'choice' => $choice];
            }
        }

        if ($bestPick === null) {
            $c = self::cheapest($hand, $budget);
            return $c ? ['card' => $c, 'choice' => Engine::FACE] : null;
        }
        return $bestPick;
    }

    /**
     * Fallback: la carta più economica giocabile col budget; altrimenti la più economica in mano.
     * @param array<int,array<string,mixed>> $hand
     * @return array<string,mixed>|null
     */
    public static function cheapest(array $hand, int $budget): ?array
    {
        $affordable = array_filter($hand, static fn($c) => (int) $c['mana_value'] <= $budget);
        $pool = $affordable ?: $hand;
        if (!$pool) {
            return null;
        }
        usort($pool, static fn($a, $b) => (int) $a['mana_value'] <=> (int) $b['mana_value']);
        return $pool[0];
    }
}
