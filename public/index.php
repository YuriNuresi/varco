<?php
declare(strict_types=1);
$pageTitle = 'Home';
require __DIR__ . '/partials/header.php';
?>
<section class="hero">
    <h1>Varco — Battaglia di corsie</h1>
    <p>1 contro CPU su 3 corsie, a <strong>round multipli</strong>: vince chi azzera la vita dell'avversario.
       L'ordine di rivelazione è asimmetrico: leggi, contra, bluffa.</p>
</section>

<section class="card-panel">
    <h2>📖 Tutorial — partita guidata</h2>
    <p>Mai giocato? Parti da qui: una <strong>partita demo</strong> con spiegazioni passo-passo che ti
       insegnano le 3 corsie, la rivelazione asimmetrica, <strong>Para/Subisci</strong> e i round multipli.</p>
    <div class="quick-play">
        <a class="btn primary" href="game.php?tutorial=1">🎓 Inizia il tutorial</a>
    </div>
</section>

<section class="card-panel">
    <h2>🎮 Gioca subito (playtest)</h2>
    <p>Scegli un colore: ti viene costruito al volo un mazzo mono-colore e parte la battaglia.</p>
    <div class="quick-play">
        <a class="deck-chip" href="game.php?color=W"><span class="deck-color c-W">W</span><span class="deck-name">Bianco</span></a>
        <a class="deck-chip" href="game.php?color=U"><span class="deck-color c-U">U</span><span class="deck-name">Blu</span></a>
        <a class="deck-chip" href="game.php?color=B"><span class="deck-color c-B">B</span><span class="deck-name">Nero</span></a>
        <a class="deck-chip" href="game.php?color=R"><span class="deck-color c-R">R</span><span class="deck-name">Rosso</span></a>
        <a class="deck-chip" href="game.php?color=G"><span class="deck-color c-G">G</span><span class="deck-name">Verde</span></a>
    </div>
</section>

<section class="card-panel">
    <h2>🏔 Campagna "Varco"</h2>
    <p>Scegli un valico (colore), <strong>draftalo il mazzo</strong> (6 carte, ne scegli 2, per fasce di mana)
       e parti. Dopo ogni battaglia vinta peschi <strong>1 nuova carta su 6</strong>: il mazzo cresce, la valle si fa più dura.
       Il mazzo della campagna viene <strong>salvato fra i tuoi mazzi</strong> e si aggiorna con le carte vinte.</p>
    <div class="quick-play">
        <a class="btn primary" href="campaign.php">⚔️ Entra nella valle</a>
    </div>
</section>

<section class="card-panel">
    <h2>Mazzi salvati</h2>
    <div id="deck-list" class="deck-list">Caricamento mazzi…</div>
    <p class="hint">Nessun mazzo? Aprine uno col <a href="draft.php"><strong>🎴 Draft (apri buste)</strong></a>
       oppure costruiscilo a mano nel <a href="deckbuilder.php">Deck editor</a>.</p>
</section>

<section class="card-panel">
    <h2>Come funziona</h2>
    <ul class="rules">
        <li><strong>Corsia 0</strong> — cali tu per primo (scoperto), poi la CPU reagisce.</li>
        <li><strong>Corsia 1</strong> — cala la CPU per prima (scoperto), poi reagisci tu.</li>
        <li><strong>Corsia 2</strong> — entrambi alla cieca, rivelazione simultanea.</li>
        <li>Da difensore scegli <strong>Para</strong> (scontro fra creature) o <strong>Subisci</strong> (le creature vanno in faccia ai maghi).</li>
        <li><strong>Round multipli</strong>: la vita scende corsia per corsia; i superstiti tornano in mano, i morti escono. Vince chi azzera l'avversario. Dal 3° round tutti <strong>Travolgere</strong> 🐗 (anti-stallo).</li>
    </ul>
    <p class="hint">Keyword: <strong>Flying</strong> ✈️, <strong>First strike</strong> ⚡, <strong>Deathtouch</strong> ☠️, <strong>Travolgere</strong> 🐗.</p>
</section>

<script>
fetch('api/decks.php')
  .then(r => r.json())
  .then(data => {
    const el = document.getElementById('deck-list');
    if (!data.ok || !data.decks.length) {
      el.innerHTML = '<em>Nessun mazzo salvato.</em>';
      return;
    }
    el.innerHTML = '';
    data.decks.forEach(d => {
      const a = document.createElement('a');
      a.className = 'deck-chip';
      a.href = 'game.php?deck_id=' + d.id;
      a.innerHTML = `<span class="deck-color c-${d.color}">${d.color}</span>
                     <span class="deck-name">${escapeHtml(d.name)}</span>
                     <span class="deck-size">${d.size} carte</span>`;
      el.appendChild(a);
    });
  })
  .catch(() => { document.getElementById('deck-list').textContent = 'Errore nel caricamento mazzi.'; });

function escapeHtml(s){return s.replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
