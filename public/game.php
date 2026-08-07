<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
$pageTitle = 'Battaglia';
$bodyClass = 'page-battle';
$deckId   = (int) ($_GET['deck_id'] ?? 0);
$color    = strtoupper(substr((string) ($_GET['color'] ?? ''), 0, 1));
$campaign = !empty($_GET['campaign']) ? 1 : 0;
$tutorial = !empty($_GET['tutorial']) ? 1 : 0;
require __DIR__ . '/partials/header.php';

$crestSvg = '<svg viewBox="0 0 32 32" aria-hidden="true"><path d="M16 3C20 12 28 14 28 16C28 18 20 20 16 29C12 20 4 18 4 16C4 14 12 12 16 3Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="16" cy="16" r="2.3" fill="currentColor"/></svg>';

// Icone "ruolo" mostrate nelle piazzole vuote: lancia = chi attacca, scudo = chi difende.
// Corsia 2 (cieca, "ultimo turno"): entrambi attaccano -> doppia lancia su ogni piazzola.
$ICON_LANCE  = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20L17 7"/><path d="M17 7l-5 .4"/><path d="M17 7l-.4 5"/></svg>';
$ICON_LANCE2 = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20L17 7"/><path d="M17 7l-5 .4"/><path d="M17 7l-.4 5"/><path d="M20 20L7 7"/><path d="M7 7l5 .4"/><path d="M7 7l.4 5"/></svg>';
$ICON_SHIELD = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 3v6c0 5-4 8-8 9-4-1-8-4-8-9V6z"/><path d="M8.5 12l2.5 2.5L15.5 10"/></svg>';
function slot_role_html(int $lane, string $side): string {
    global $ICON_LANCE, $ICON_LANCE2, $ICON_SHIELD;
    if ($lane === 2) return '<span class="slot-role attack" title="attacco alla cieca">' . $ICON_LANCE2 . '</span>';
    $attacker = ($lane === 0 && $side === 'player') || ($lane === 1 && $side === 'ai');
    return $attacker
        ? '<span class="slot-role attack" title="attacca">' . $ICON_LANCE . '</span>'
        : '<span class="slot-role defend" title="difende">' . $ICON_SHIELD . '</span>';
}
?>
<section class="battle" data-deck-id="<?= $deckId ?>" data-color="<?= htmlspecialchars($color) ?>" data-campaign="<?= $campaign ?>" data-tutorial="<?= $tutorial ?>" data-hand-size="<?= HAND_SIZE ?>" data-mage-life="<?= MAGE_LIFE ?>" data-mana-cap="<?= MANA_CAP ?>">

    <div id="preload-overlay" class="preload-overlay" hidden>
        <div class="preload-box">
            <div class="preload-crest"><?= $crestSvg ?></div>
            <div class="preload-title">Il varco si apre…</div>
            <div class="preload-bar"><div class="preload-fill" id="preload-fill"></div></div>
            <div class="preload-pct" id="preload-pct">0%</div>
        </div>
    </div>

    <div class="board">

        <!-- ===================== AVVERSARIO (in alto) ===================== -->
        <div class="side side-ai">
            <div class="lanes">
                <?php for ($i = 0; $i < LANES; $i++): ?>
                    <div class="lane-col" data-lane="<?= $i ?>">
                        <div class="slot" data-lane="<?= $i ?>" data-side="ai"><?= slot_role_html($i, 'ai') ?></div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- ===================== CORRIDOIO (centro) — ospita le barre HUD ===================== -->
        <div class="corridor">
            <div class="side-bar hud foe">
                <div class="crest"><?= $crestSvg ?></div>
                <div class="idblock">
                    <div class="rank">Arconte avversario</div>
                    <div class="idname">Avversario</div>
                    <div class="vit">
                        <span class="meter"><i class="fill" id="meter-ai" style="width:100%"></i><i class="loss" id="loss-ai"></i></span>
                        <span class="life" id="life-ai">❤️ <?= MAGE_LIFE ?></span>
                    </div>
                </div>
                <span class="spacer"></span>
                <div class="oppo-hand" id="ai-hand" title="Carte in mano all'avversario"></div>
            </div>

            <div class="round-bar"><span id="round-tag" class="round-tag">Round 1</span></div>
            <p id="prompt" class="prompt" hidden>Avvio match…</p>
            <div id="choice-bar" class="choice-bar" hidden></div>
            <button id="next-btn" class="btn primary" hidden></button>
            <button id="pass-btn" class="btn ghost" hidden>✋ Passa — non schierare</button>
            <div id="boon-pick" class="boon-pick" hidden></div>

            <div class="side-bar hud me">
                <div class="crest"><?= $crestSvg ?></div>
                <div class="idblock">
                    <div class="rank">Il tuo arcano</div>
                    <div class="idname">Tu</div>
                    <div class="vit">
                        <span class="meter"><i class="fill" id="meter-player" style="width:100%"></i><i class="loss" id="loss-player"></i></span>
                        <span class="life" id="life-player">❤️ <?= MAGE_LIFE ?></span>
                    </div>
                </div>
                <span class="spacer"></span>
                <div class="mana hud-mana">
                    <span class="shards" id="mana-shards"></span>
                    <span class="num"><b id="budget">—</b> / <?= MANA_CAP ?></span>
                </div>
            </div>
        </div>

        <!-- ===================== TU (in basso) ===================== -->
        <div class="side side-player">
            <div class="lanes">
                <?php for ($i = 0; $i < LANES; $i++): ?>
                    <div class="lane-col" data-lane="<?= $i ?>">
                        <div class="slot" data-lane="<?= $i ?>" data-side="player"><?= slot_role_html($i, 'player') ?></div>
                    </div>
                <?php endfor; ?>
            </div>
            <div class="hand-area">
                <div id="hand" class="hand"></div>
                <div class="deck-pile" id="deck-pile" title="Il tuo mazzo (carte da pescare)">
                    <span class="deck-card"></span><span class="deck-card"></span><span class="deck-card"></span>
                    <span class="deck-count" id="deck-count">—</span>
                </div>
            </div>
        </div>

    </div>

    <div class="battle-actions">
        <a href="/index.php" class="btn ghost">← Mazzi</a>
        <button id="screenshot-btn" class="btn ghost screenshot-btn" title="Cattura uno screenshot 9:16 da inviare alla gallery">📷 Screenshot</button>
    </div>
    <div id="screenshot-toast" class="screenshot-toast" hidden></div>

    <div id="result" class="result" hidden></div>

<?php if ($tutorial): ?>
    <!-- Tutorial: overlay introduttivo (slide) + coach contestuale durante la partita reale. -->
    <div id="tut-intro" class="tut-intro" hidden></div>
    <div id="coach" class="coach" hidden></div>
<?php endif; ?>
</section>

<script src="/assets/audio.js?v=dnd7"></script>
<script src="/assets/app.js?v=preload1"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
