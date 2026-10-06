<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
$pageTitle = 'Deck editor';
require __DIR__ . '/partials/header.php';
?>
<section class="card-panel">
    <h2>Costruisci un mazzo</h2>
    <p>Scegli <strong>un colore</strong> e aggiungi creature di quel colore.
       Regole: <strong><?= DECK_MIN_SIZE ?>–<?= DECK_MAX_SIZE ?> carte</strong>,
       max <strong><?= MAX_COPIES ?> copie</strong> per carta.</p>

    <div class="builder-controls">
        <label>Colore mazzo
            <select id="color">
                <option value="W">⚪ Bianco</option>
                <option value="U">🔵 Blu</option>
                <option value="B">⚫ Nero</option>
                <option value="R">🔴 Rosso</option>
                <option value="G">🟢 Verde</option>
                <option value="C">◇ Incolore</option>
            </select>
        </label>
        <label>Costo mana
            <select id="mana">
                <option value="">Tutti</option>
                <?php for ($i = 0; $i <= 10; $i++): ?>
                    <option value="<?= $i ?>"><?= $i ?></option>
                <?php endfor; ?>
            </select>
        </label>
        <label>Cerca
            <input type="text" id="search" placeholder="nome carta…">
        </label>
    </div>
</section>

<div class="builder-grid">
    <section class="card-panel">
        <h3>Carte disponibili <span id="pool-count" class="badge"></span></h3>
        <div id="pool" class="card-grid">Caricamento…</div>
    </section>

    <section class="card-panel deck-panel">
        <h3>Il tuo mazzo <span id="deck-count" class="badge">0</span></h3>
        <input type="text" id="deck-name" placeholder="Nome del mazzo">
        <div id="deck" class="card-grid small"></div>
        <button id="save-deck" class="btn primary">Salva mazzo</button>
        <p id="save-msg" class="msg"></p>
    </section>
</div>

<script src="assets/deckbuilder.js"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
