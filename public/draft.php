<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
$pageTitle = 'Draft';
require __DIR__ . '/partials/header.php';
?>
<section class="card-panel">
    <h2>🎴 Draft — apri le buste</h2>
    <p>Scegli un colore: apri <strong><?= PACKS_PER_DRAFT ?> buste</strong> da <?= PACK_SIZE ?> carte
       (più rare = più difficili). Poi costruisci un mazzo di
       <strong><?= DECK_MIN_SIZE ?>–<?= DECK_MAX_SIZE ?></strong> carte (max <?= MAX_COPIES ?> copie) con ciò che esce.</p>

    <div class="draft-colors" id="color-pick">
        <button class="btn color-btn" data-color="W">⚪ Bianco</button>
        <button class="btn color-btn" data-color="U">🔵 Blu</button>
        <button class="btn color-btn" data-color="B">⚫ Nero</button>
        <button class="btn color-btn" data-color="R">🔴 Rosso</button>
        <button class="btn color-btn" data-color="G">🟢 Verde</button>
    </div>
    <p id="draft-msg" class="msg"></p>
</section>

<div class="builder-grid" id="build-area" hidden>
    <section class="card-panel">
        <h3>Carte aperte <span id="pool-count" class="badge"></span></h3>
        <div id="pool" class="card-grid"></div>
    </section>
    <section class="card-panel deck-panel">
        <h3>Il tuo mazzo <span id="deck-count" class="badge">0</span></h3>
        <input type="text" id="deck-name" placeholder="Nome del mazzo">
        <div id="deck" class="card-grid small"></div>
        <button id="save-deck" class="btn primary" disabled>Salva mazzo</button>
        <p id="save-msg" class="msg"></p>
    </section>
</div>

<script src="/assets/draft.js"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
