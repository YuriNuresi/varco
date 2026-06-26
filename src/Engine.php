<?php
/**
 * Motore di combat — modello "vita dei maghi" (v2).
 *
 * Ogni corsia produce DANNI ai maghi (player/ai), non più "punti corsia".
 * Regole:
 *  - Corsie con attaccante/difensore: il difensore sceglie PARARE o SUBIRE.
 *      PARARE  -> le creature si scontrano (mutuo danno, first strike, deathtouch). Nessun danno ai maghi.
 *                 Legale solo se il difensore PUÒ bloccare (una creatura di terra non può parare un volante).
 *      SUBIRE  -> SOLO l'attaccante colpisce il mago difensore; il difensore incassa e la sua
 *                 creatura NON contrattacca (resta a guardia). Nessuna morte.
 *  - Corsia cieca: le creature si scontrano; MA se c'è asimmetria di volo i colpi vanno in faccia
 *                 a entrambi i maghi (la volante "vola sopra" lo scontro).
 *  - Vincitore del match: chi resta con più vita. (MAGE_LIFE iniziale.)
 *
 * Nota: in una risoluzione a colpo singolo, First strike/Deathtouch incidono solo su CHI muore
 * nello scontro (cosmetico finché non si aggiunge Travolgere/persistenza).
 */

declare(strict_types=1);

final class Engine
{
    public const PLAYER = 'PLAYER';
    public const AI     = 'AI';
    public const DRAW   = 'DRAW';

    public const BLOCK  = 'BLOCK';  // parare
    public const FACE   = 'FACE';   // subire / andare in faccia

    /** @param array<string,mixed> $c @return array<string,mixed> */
    public static function card(array $c): array
    {
        return [
            'id'           => $c['id']           ?? null,
            'name'         => (string) ($c['name'] ?? '???'),
            'mana_value'   => (int)    ($c['mana_value'] ?? 0),
            'colors'       => (string) ($c['colors'] ?? ''),
            'power'        => (int)    ($c['power'] ?? 0),
            'toughness'    => (int)    ($c['toughness'] ?? 0),
            'flying'        => (bool)   ($c['flying'] ?? false),
            'first_strike'  => (bool)   ($c['first_strike'] ?? false),
            'deathtouch'    => (bool)   ($c['deathtouch'] ?? false),
            'trample'       => (bool)   ($c['trample'] ?? false),
            'double_strike' => (bool)   ($c['double_strike'] ?? false), // doppio attacco: colpisce in FS e nel passo normale (≈ danno ×2, timing first strike)
            'lifelink'      => (bool)   ($c['lifelink'] ?? false),      // legame vitale: il danno inflitto fa guadagnare vita al controllore
            'reach'         => (bool)   ($c['reach'] ?? false),        // raggiungere: può bloccare i volanti
            'defender'      => (bool)   ($c['defender'] ?? false),     // difensore: non può attaccare (potenza 0 in attacco), ma para normalmente
            'rarity'       => (string) ($c['rarity'] ?? 'common'),
            'image_url'    => $c['image_url'] ?? null,
            'wounds'       => (int)    ($c['wounds'] ?? 0), // danno persistente subìto (multi-round)
        ];
    }

    /** Le 8 abilità stampate (keyword) che il motore applica. */
    public const KEYWORDS = ['flying', 'first_strike', 'deathtouch', 'trample', 'double_strike', 'lifelink', 'reach', 'defender'];

    /** @return string[] le keyword attive della carta. */
    public static function keywordsOf(array $c): array
    {
        return array_values(array_filter(self::KEYWORDS, static fn($k) => !empty($c[$k])));
    }

    /**
     * Regola "una sola abilità": se la creatura ha 2+ keyword stampate, alla messa in gioco se ne
     * tiene UNA SOLA ($keep) e le altre si spengono. Con 0/1 keyword la carta resta invariata.
     * (I "doni" del round 3/6 si sommano DOPO, a parte, e fanno eccezione.)
     */
    public static function keepOneKeyword(array $c, ?string $keep): array
    {
        $kws = self::keywordsOf($c);
        if (count($kws) < 2) { return $c; }
        if ($keep === null || !in_array($keep, $kws, true)) { $keep = $kws[0]; } // fallback: la prima
        foreach (self::KEYWORDS as $k) {
            if ($k !== $keep) { $c[$k] = false; }
        }
        return $c;
    }

    /** Scelta CPU dell'abilità da tenere: per priorità, diversa fra attacco e difesa. */
    public static function pickAiKeyword(array $c, bool $attacking): ?string
    {
        $kws = self::keywordsOf($c);
        if (count($kws) < 2) { return $kws[0] ?? null; }
        $pri = $attacking
            ? ['double_strike', 'deathtouch', 'trample', 'first_strike', 'lifelink', 'flying', 'reach', 'defender']
            : ['deathtouch', 'first_strike', 'reach', 'double_strike', 'lifelink', 'flying', 'trample', 'defender'];
        foreach ($pri as $k) { if (in_array($k, $kws, true)) { return $k; } }
        return $kws[0];
    }

    /** Costituzione efficace = stampata − ferite persistenti (mai sotto 0). */
    public static function effToughness(array $card): int
    {
        return max(0, (int) ($card['toughness'] ?? 0) - (int) ($card['wounds'] ?? 0));
    }

    /**
     * Budget da riservare per riempire $n corsie restanti: somma dei costi delle $n carte
     * PIÙ ECONOMICHE in $cards. Serve a non sforare il tetto di mana (anti-overspend reale,
     * invece di assumere COSTO_MINIMO fisso per corsia).
     * @param array<int,array<string,mixed>> $cards
     */
    public static function reserveCost(array $cards, int $n): int
    {
        if ($n <= 0 || !$cards) {
            return 0;
        }
        $costs = array_map(static fn($c) => (int) ($c['mana_value'] ?? 0), $cards);
        sort($costs);
        return array_sum(array_slice($costs, 0, $n));
    }

    /** Una creatura di terra non può bloccare un volante — a meno che non abbia Raggiungere (reach). */
    public static function canBlock(array $blocker, array $attacker): bool
    {
        $blocker  = self::card($blocker);
        $attacker = self::card($attacker);
        return !($attacker['flying'] && !$blocker['flying'] && empty($blocker['reach']));
    }

    /**
     * Potenza d'attacco EFFETTIVA di una creatura quando colpisce (creatura o mago):
     *  - Difensore (defender): non può attaccare -> 0 in attacco (ma para normalmente, vedi $attacking).
     *  - Doppio attacco (double strike): colpisce in due passi -> danno ×2 (modello sintetico).
     * $attacking = true se sta ATTACCANDO; false se sta PARANDO (il difensore para a potenza piena).
     */
    private static function strikePower(array $c, bool $attacking): int
    {
        if ($attacking && !empty($c['defender'])) { return 0; }
        $p = max(0, (int) $c['power']);
        return !empty($c['double_strike']) ? $p * 2 : $p;
    }

    /** Danno letale: >0 e (deathtouch del dealer) oppure (danno >= toughness del receiver). */
    private static function letale(array $dealer, array $receiver, int $danno): bool
    {
        if ($danno <= 0) {
            return false;
        }
        if (!empty($dealer['deathtouch'])) {
            return true;
        }
        return $danno >= self::effToughness($receiver);
    }

    /** Eccesso (danno − costituzione letale del bersaglio) che il Travolgere riversa sul mago. */
    private static function trampleOver(array $receiver, int $danno, bool $deathtouch): int
    {
        $lethal = $deathtouch ? 1 : self::effToughness($receiver);
        return max(0, $danno - $lethal);
    }

    /**
     * Scontro fra due creature ingaggiate (entrambe infliggono danno; il volo NON conta qui:
     * una volta ingaggiate si danneggiano normalmente). Gestisce first/double strike, deathtouch,
     * Travolgere, Difensore (potenza 0 se attacca) e calcola il danno inflitto per il Legame vitale.
     * $tX/$tY: se true, il rispettivo Travolgere riversa l'eccesso sul mago avversario.
     * $xAtk/$yAtk: se la creatura sta attaccando (true) o parando (false) — conta per il Difensore.
     * @return array{Xv:bool,Yv:bool,faceX:int,faceY:int,woundX:int,woundY:int,dealtX:int,dealtY:int}
     *         dealtX/dealtY = danno TOTALE inflitto dalla creatura (per il Legame vitale).
     */
    private static function combat(array $X, array $Y, bool $tX = false, bool $tY = false, bool $xAtk = true, bool $yAtk = true): array
    {
        $X = self::card($X);
        $Y = self::card($Y);
        $xFirst = $X['first_strike'] || $X['double_strike'];
        $yFirst = $Y['first_strike'] || $Y['double_strike'];
        $X_first = $xFirst && !$yFirst;
        $Y_first = $yFirst && !$xFirst;

        $px = self::strikePower($X, $xAtk);
        $py = self::strikePower($Y, $yAtk);

        $Xv = true; $Yv = true; $faceX = 0; $faceY = 0; $dmgToX = 0; $dmgToY = 0; $dealtX = 0; $dealtY = 0;

        $dealX = function () use ($X, $Y, $px, $tX, &$Yv, &$faceX, &$dmgToY, &$dealtX): void {
            $dealtX = $px;                 // danno totale inflitto da X (per lifelink)
            $dmgToY = $px;                 // danno alla creatura Y
            if (self::letale($X, $Y, $px)) { $Yv = false; }
            if ($tX) { $faceX = self::trampleOver($Y, $px, !empty($X['deathtouch'])); }
        };
        $dealY = function () use ($X, $Y, $py, $tY, &$Xv, &$faceY, &$dmgToX, &$dealtY): void {
            $dealtY = $py;
            $dmgToX = $py;
            if (self::letale($Y, $X, $py)) { $Xv = false; }
            if ($tY) { $faceY = self::trampleOver($X, $py, !empty($Y['deathtouch'])); }
        };

        if ($X_first) {
            $dealX();
            if ($Yv) { $dealY(); } // Y restituisce solo se sopravvive al first/double strike
        } elseif ($Y_first) {
            $dealY();
            if ($Xv) { $dealX(); }
        } else {
            $dealX(); $dealY();
        }
        // La ferita persiste solo per chi sopravvive.
        return [
            'Xv' => $Xv, 'Yv' => $Yv, 'faceX' => $faceX, 'faceY' => $faceY,
            'woundX' => $Xv ? $dmgToX : 0, 'woundY' => $Yv ? $dmgToY : 0,
            'dealtX' => $dealtX, 'dealtY' => $dealtY,
        ];
    }

    /**
     * Risolve una corsia. Restituisce i danni ai due maghi e le morti.
     *
     * @param array<string,mixed> $playerCard
     * @param array<string,mixed> $aiCard
     * @param string|null $attacker  Engine::PLAYER | Engine::AI | null (corsia cieca)
     * @param string|null $choice    Engine::BLOCK | Engine::FACE | null (scelta del difensore)
     * @param bool $blind            true per la corsia alla cieca
     * @return array{dmg_player:int,dmg_ai:int,player_dead:bool,ai_dead:bool,mode:string}
     */
    public static function resolveLane(array $playerCard, array $aiCard, ?string $attacker, ?string $choice, bool $blind = false): array
    {
        $P = self::card($playerCard);
        $A = self::card($aiCard);

        $res = ['dmg_player' => 0, 'dmg_ai' => 0, 'player_dead' => false, 'ai_dead' => false,
                'player_wound' => 0, 'ai_wound' => 0, 'player_gain' => 0, 'ai_gain' => 0, 'mode' => ''];

        if ($blind) {
            // Corsia cieca ("ultimo turno"): ENTRAMBE attaccano. Il VOLO torna in funzione.
            //  - stesso stato di volo (terra/terra o volante/volante) -> SI SCONTRANO: mutuo danno
            //    e l'ECCESSO (potenza − costituzione avversaria) va in faccia ai maghi (come prima).
            //  - volo ASIMMETRICO (volante/terra) -> NON si scontrano: si "superano". Ognuna colpisce
            //    direttamente il mago avversario con tutta la potenza e NON subisce danno (niente morti/ferite).
            $pFly = !empty($P['flying']);
            $aFly = !empty($A['flying']);

            if ($pFly === $aFly) {
                $c = self::combat($P, $A, true, true, true, true);
                $res['player_dead']  = !$c['Xv'];
                $res['ai_dead']      = !$c['Yv'];
                $res['dmg_ai']       = $c['faceX']; // eccesso della creatura del giocatore -> mago CPU
                $res['dmg_player']   = $c['faceY']; // eccesso della creatura CPU -> mago giocatore
                $res['player_wound'] = $c['woundX'];
                $res['ai_wound']     = $c['woundY'];
                if (!empty($P['lifelink'])) { $res['player_gain'] += $c['dealtX']; }
                if (!empty($A['lifelink'])) { $res['ai_gain']     += $c['dealtY']; }
                $res['mode']         = 'BLIND_CLASH';
            } else {
                // Si superano: colpo pieno al mago avversario, nessun danno reciproco.
                $pPow = self::strikePower($P, true); // difensore->0, doppio attacco->×2
                $aPow = self::strikePower($A, true);
                $res['dmg_ai']     = $pPow; // creatura giocatore -> mago CPU
                $res['dmg_player'] = $aPow; // creatura CPU      -> mago giocatore
                if (!empty($P['lifelink'])) { $res['player_gain'] += $pPow; }
                if (!empty($A['lifelink'])) { $res['ai_gain']     += $aPow; }
                $res['mode']       = 'BLIND_PASS';
            }
            return $res;
        }

        // Corsie con attaccante/difensore.
        $attackerCard = $attacker === self::PLAYER ? $P : $A;
        $defenderCard = $attacker === self::PLAYER ? $A : $P;
        $canBlock = self::canBlock($defenderCard, $attackerCard);

        if ($choice === self::BLOCK && $canBlock) {
            // Scontro: l'attaccante attacca (xAtk=true), il difensore PARA (yAtk=false → un difensore
            // para a potenza piena). Solo l'attaccante può travolgere.
            $c = self::combat($attackerCard, $defenderCard, (bool) $attackerCard['trample'], false, true, false);
            $attGain = !empty($attackerCard['lifelink']) ? $c['dealtX'] : 0;
            $defGain = !empty($defenderCard['lifelink']) ? $c['dealtY'] : 0;
            if ($attacker === self::PLAYER) {
                $res['player_dead']  = !$c['Xv'];
                $res['ai_dead']      = !$c['Yv'];
                $res['dmg_ai']      += $c['faceX']; // travolgere del giocatore -> mago CPU
                $res['player_wound'] = $c['woundX'];
                $res['ai_wound']     = $c['woundY'];
                $res['player_gain'] += $attGain;
                $res['ai_gain']     += $defGain;
            } else {
                $res['ai_dead']      = !$c['Xv'];
                $res['player_dead']  = !$c['Yv'];
                $res['dmg_player']  += $c['faceX']; // travolgere CPU -> mago giocatore
                $res['ai_wound']     = $c['woundX'];
                $res['player_wound'] = $c['woundY'];
                $res['ai_gain']     += $attGain;
                $res['player_gain'] += $defGain;
            }
            $res['mode'] = $c['faceX'] ? 'BLOCK_TRAMPLE' : 'BLOCK';
        } else {
            // SUBIRE (o blocco illegale): SOLO l'attaccante colpisce il mago difensore.
            // Il difensore "incassa": la sua creatura resta a guardia e NON contrattacca.
            $pow = self::strikePower($attackerCard, true); // difensore->0, doppio attacco->×2
            if ($attacker === self::PLAYER) {
                $res['dmg_ai'] = $pow; // player attacca -> mago CPU
                if (!empty($attackerCard['lifelink'])) { $res['player_gain'] += $pow; }
            } else {
                $res['dmg_player'] = $pow; // CPU attacca -> mago player
                if (!empty($attackerCard['lifelink'])) { $res['ai_gain'] += $pow; }
            }
            $res['mode'] = ($choice === self::BLOCK && !$canBlock) ? 'FACE_FORCED' : 'FACE';
        }

        return $res;
    }

    /**
     * Risolve UN ROUND (modello multi-round, §1bis), CORSIA PER CORSIA.
     *
     * La vita dei maghi viene aggiornata DOPO OGNI CORSIA: se un mago va a ≤ 0 la battaglia
     * finisce SUBITO (le corsie successive non si giocano), anche a metà round. Solo se entrambi
     * i maghi cadono nello STESSO scontro di corsia si ha pareggio simultaneo. Questo riduce
     * drasticamente i doppi-KO rispetto al sommare i danni di tutte le corsie e controllare in fondo.
     *
     * @param array<int,array<string,mixed>> $lanes  config corsia: ['player','ai','attacker','choice','blind']
     * @param int  $playerLife   vita corrente del mago giocatore (entra nel round)
     * @param int  $aiLife       vita corrente del mago CPU
     * @param bool $forceTrample  dal round 3 in poi: ogni creatura ottiene Travolgere (anti-stallo).
     * @return array{player_life:int,ai_life:int,ended:bool,winner:?string,lanes:array<int,array<string,mixed>>}
     *         winner: PLAYER|AI|DRAW (DRAW = doppio KO sulla stessa corsia) oppure null se il round
     *         si chiude senza morti.
     */
    public static function resolveRound(array $lanes, int $playerLife, int $aiLife, bool $forceTrample = false): array
    {
        $details = [];
        $ended   = false;
        $winner  = null;

        foreach ($lanes as $i => $cfg) {
            $pc = self::card($cfg['player']);
            $ac = self::card($cfg['ai']);
            if ($forceTrample) {
                $pc['trample'] = true;
                $ac['trample'] = true;
            }
            $r = self::resolveLane(
                $pc,
                $ac,
                $cfg['attacker'] ?? null,
                $cfg['choice'] ?? null,
                !empty($cfg['blind'])
            );

            $playerLife -= $r['dmg_player'];
            $aiLife     -= $r['dmg_ai'];
            $playerLife += $r['player_gain'] ?? 0; // Legame vitale
            $aiLife     += $r['ai_gain'] ?? 0;

            $details[$i] = [
                'lane'             => $i,
                'player'           => $pc,
                'ai'               => $ac,
                'attacker'         => $cfg['attacker'] ?? null,
                'choice'           => $cfg['choice'] ?? null,
                'mode'             => $r['mode'],
                'dmg_player'       => $r['dmg_player'],
                'dmg_ai'           => $r['dmg_ai'],
                'player_dead'      => $r['player_dead'],
                'ai_dead'          => $r['ai_dead'],
                'player_wound'     => $r['player_wound'],
                'ai_wound'         => $r['ai_wound'],
                'player_life_after' => $playerLife,
                'ai_life_after'     => $aiLife,
            ];

            $pDown = $playerLife <= 0;
            $aDown = $aiLife <= 0;
            if ($pDown || $aDown) {
                $ended = true;
                if ($pDown && $aDown) {
                    // Doppio-KO: vince chi è "meno morto" (vita più alta); pari = DRAW.
                    $winner = $playerLife > $aiLife ? self::PLAYER : ($aiLife > $playerLife ? self::AI : self::DRAW);
                } else {
                    $winner = $aDown ? self::PLAYER : self::AI;
                }
                break; // le corsie rimanenti non si giocano: la battaglia è finita
            }
        }

        return [
            'player_life' => $playerLife,
            'ai_life'     => $aiLife,
            'ended'       => $ended,
            'winner'      => $winner,
            'lanes'       => $details,
        ];
    }

    /**
     * Risolve il match. $lanes: 3 config corsia.
     * Ogni config: ['player'=>card,'ai'=>card,'attacker'=>?,'choice'=>?,'blind'=>bool]
     *
     * @return array{outcome:string,player_life:int,ai_life:int,lanes:array<int,array<string,mixed>>}
     */
    public static function resolveMatch(array $lanes): array
    {
        $playerLife = MAGE_LIFE;
        $aiLife     = MAGE_LIFE;
        $playerAlive = 0;
        $aiAlive     = 0;
        $details    = [];

        foreach ($lanes as $i => $cfg) {
            $r = self::resolveLane(
                $cfg['player'],
                $cfg['ai'],
                $cfg['attacker'] ?? null,
                $cfg['choice'] ?? null,
                !empty($cfg['blind'])
            );
            $playerLife -= $r['dmg_player'];
            $aiLife     -= $r['dmg_ai'];
            $playerLife += $r['player_gain'] ?? 0; // Legame vitale
            $aiLife     += $r['ai_gain'] ?? 0;
            if (!$r['player_dead']) { $playerAlive++; }
            if (!$r['ai_dead'])     { $aiAlive++; }
            $details[$i] = [
                'lane'        => $i,
                'player'      => self::card($cfg['player']),
                'ai'          => self::card($cfg['ai']),
                'attacker'    => $cfg['attacker'] ?? null,
                'choice'      => $cfg['choice'] ?? null,
                'mode'        => $r['mode'],
                'dmg_player'  => $r['dmg_player'],
                'dmg_ai'      => $r['dmg_ai'],
                'player_dead' => $r['player_dead'],
                'ai_dead'     => $r['ai_dead'],
            ];
        }

        // Esito: 1) più vita; 2) a parità, più creature vive; 3) pareggio vero.
        $tiebreak = false;
        if ($playerLife > $aiLife) {
            $outcome = self::PLAYER;
        } elseif ($aiLife > $playerLife) {
            $outcome = self::AI;
        } elseif ($playerAlive > $aiAlive) {
            $outcome = self::PLAYER; $tiebreak = true;
        } elseif ($aiAlive > $playerAlive) {
            $outcome = self::AI; $tiebreak = true;
        } else {
            $outcome = self::DRAW;
        }

        return [
            'outcome'      => $outcome,
            'player_life'  => $playerLife,
            'ai_life'      => $aiLife,
            'player_alive' => $playerAlive,
            'ai_alive'     => $aiAlive,
            'tiebreak'     => $tiebreak,
            'lanes'        => $details,
        ];
    }
}
