<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
$pageTitle = 'Catalogo';
require __DIR__ . '/partials/header.php';
?>
<section class="card-panel">
    <h2>Catalogo carte</h2>
    <p>Sfoglia tutte le carte. Usa il filtro <strong>Fonte</strong> per vedere le tue carte custom o quelle Scryfall.</p>

    <div class="builder-controls">
        <label>Fonte
            <select id="cat-source">
                <option value="custom">Le mie carte</option>
                <option value="scryfall">Carte Scryfall</option>
            </select>
        </label>
        <label>Colore
            <select id="cat-color">
                <option value="">Tutti</option>
                <option value="W">⚪ Bianco</option>
                <option value="U">🔵 Blu</option>
                <option value="B">⚫ Nero</option>
                <option value="R">🔴 Rosso</option>
                <option value="G">🟢 Verde</option>
                <option value="C">◇ Incolore</option>
            </select>
        </label>
        <label>Costo mana
            <select id="cat-mana">
                <option value="">Tutti</option>
                <?php for ($i = 0; $i <= 10; $i++): ?>
                    <option value="<?= $i ?>"><?= $i ?></option>
                <?php endfor; ?>
            </select>
        </label>
        <label>Rarità
            <select id="cat-rarity">
                <option value="">Tutte</option>
                <option value="common">Comune</option>
                <option value="uncommon">Non-comune</option>
                <option value="rare">Rara</option>
                <option value="mythic">Mitica</option>
            </select>
        </label>
        <label>Cerca
            <input type="text" id="cat-search" placeholder="nome carta…">
        </label>
    </div>
</section>

<section class="card-panel">
    <h3>Risultati <span id="cat-count" class="badge">0</span></h3>
    <div id="cat-pool" class="card-grid" style="max-height:72vh">Caricamento…</div>
</section>

<script>
'use strict';
const $ = s => document.querySelector(s);
const RARITY = {common:'Comune',uncommon:'Non-comune',rare:'Rara',mythic:'Mitica'};

function escHtml(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function thumbUrl(c){ return c.image_url ? c.image_url.replace('/normal/','/small/') : ''; }

function kwIcons(c) {
  let s = '';
  if (+c.flying) s += '<span title="Flying">✈️</span>';
  if (+c.first_strike) s += '<span title="First strike">⚡</span>';
  if (+c.deathtouch) s += '<span title="Deathtouch">☠️</span>';
  if (+c.trample) s += '<span title="Trample">🐗</span>';
  if (+c.double_strike) s += '<span title="Double strike">⚔️</span>';
  if (+c.lifelink) s += '<span title="Lifelink">💚</span>';
  if (+c.reach) s += '<span title="Reach">🏹</span>';
  if (+c.defender) s += '<span title="Defender">🛡️</span>';
  return s;
}

function cardEl(c) {
  const div = document.createElement('div');
  div.className = 'mini-card catalog-card c-' + (c.colors || 'C');
  const img = thumbUrl(c)
    ? `<img class="mc-img" loading="lazy" src="${thumbUrl(c)}" alt="${escHtml(c.name)}"
         onerror="this.parentNode.classList.add('noimg');this.remove();">`
    : '';
  const gem = `<span class="gem r-${c.rarity||'common'}"></span>`;
  div.innerHTML = `
    <div class="mc-imgwrap">${img}<span class="mc-cost">${c.mana_value}</span></div>
    <div class="mc-meta">
      <span class="mc-name">${gem} ${escHtml(c.name)}</span>
      <span class="mc-pt">${c.power}/${c.toughness} ${kwIcons(c)} · <em>${RARITY[c.rarity]||'Comune'}</em></span>
    </div>`;
  return div;
}

function loadCatalog() {
  const p = new URLSearchParams();
  p.set('source', $('#cat-source').value);
  const color = $('#cat-color').value;
  if (color) p.set('color', color);
  const mana = $('#cat-mana').value;
  if (mana !== '') p.set('mana_value', mana);
  const q = $('#cat-search').value.trim();
  if (q) p.set('q', q);

  $('#cat-pool').textContent = 'Caricamento…';
  fetch('/api/cards.php?' + p.toString())
    .then(r => r.json())
    .then(data => {
      if (!data.ok) { $('#cat-pool').textContent = 'Errore: ' + data.error; return; }
      let cards = data.cards;
      const rarity = $('#cat-rarity').value;
      if (rarity) cards = cards.filter(c => c.rarity === rarity);
      $('#cat-count').textContent = cards.length;
      const wrap = $('#cat-pool');
      wrap.innerHTML = '';
      if (!cards.length) { wrap.innerHTML = '<em>Nessuna carta trovata.</em>'; return; }
      cards.forEach(c => wrap.appendChild(cardEl(c)));
    })
    .catch(() => { $('#cat-pool').textContent = 'Errore di rete.'; });
}

$('#cat-source').addEventListener('change', loadCatalog);
$('#cat-color').addEventListener('change', loadCatalog);
$('#cat-mana').addEventListener('change', loadCatalog);
$('#cat-rarity').addEventListener('change', loadCatalog);
let t; $('#cat-search').addEventListener('input', () => { clearTimeout(t); t = setTimeout(loadCatalog, 250); });

loadCatalog();
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
