<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/auth.php';

if (!is_admin()) {
    $pageTitle = 'Accesso riservato';
    require __DIR__ . '/partials/header.php';
    echo '<section class="card-panel" style="max-width:420px;margin:4rem auto">
        <h2>Editor campagna</h2>
        <p>Accedi con Google per usare l\'editor.</p>
        <a href="/auth_google.php?next=/campaign-editor.php" class="btn primary" style="margin-top:1rem;display:inline-block">Accedi con Google</a>
    </section>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

$pageTitle = 'Editor campagna';
require __DIR__ . '/partials/header.php';
?>
<section class="card-panel">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem">
        <h2>Editor mazzi campagna</h2>
    </div>
    <p>Costruisci i mazzi nemici per ogni livello della campagna. Nessun vincolo su dimensione o copie.</p>
</section>

<section class="card-panel">
    <div class="builder-controls">
        <label>Valle
            <select id="ce-valley">
                <option value="W">⚪ Bianco — Soldati</option>
                <option value="U">🔵 Blu — Maghi</option>
                <option value="B">⚫ Nero — Zombi</option>
                <option value="R" selected>🔴 Rosso — Goblin</option>
                <option value="G">🟢 Verde — Elfi</option>
            </select>
        </label>
        <label>Villaggio
            <select id="ce-village"></select>
        </label>
        <label>Livello
            <select id="ce-level"></select>
        </label>
    </div>
</section>

<div class="builder-grid">
    <!-- Pool carte (tutte, senza vincoli) -->
    <section class="card-panel">
        <h3>Carte disponibili <span id="ce-pool-count" class="badge">0</span></h3>
        <div class="builder-controls">
            <label>Fonte
                <select id="ce-source">
                    <option value="">Tutte</option>
                    <option value="custom">Solo custom</option>
                    <option value="scryfall">Solo Scryfall</option>
                </select>
            </label>
            <label>Colore
                <select id="ce-color">
                    <option value="">Tutti</option>
                    <optgroup label="Monocromatici">
                        <option value="W">⚪ Bianco</option>
                        <option value="U">🔵 Blu</option>
                        <option value="B">⚫ Nero</option>
                        <option value="R">🔴 Rosso</option>
                        <option value="G">🟢 Verde</option>
                    </optgroup>
                    <optgroup label="Bicolore">
                        <option value="WU">⚪🔵 Bianco-Blu</option>
                        <option value="WB">⚪⚫ Bianco-Nero</option>
                        <option value="WR">⚪🔴 Bianco-Rosso</option>
                        <option value="WG">⚪🟢 Bianco-Verde</option>
                        <option value="UB">🔵⚫ Blu-Nero</option>
                        <option value="UR">🔵🔴 Blu-Rosso</option>
                        <option value="UG">🔵🟢 Blu-Verde</option>
                        <option value="BR">⚫🔴 Nero-Rosso</option>
                        <option value="BG">⚫🟢 Nero-Verde</option>
                        <option value="RG">🔴🟢 Rosso-Verde</option>
                    </optgroup>
                    <optgroup label="Altro">
                        <option value="C">◇ Incolore</option>
                    </optgroup>
                </select>
            </label>
            <label>Cerca
                <input type="text" id="ce-search" placeholder="nome carta…">
            </label>
        </div>
        <div id="ce-pool" class="card-grid" style="max-height:56vh">Caricamento…</div>
    </section>

    <!-- Mazzo del livello selezionato -->
    <section class="card-panel deck-panel">
        <h3>Mazzo nemico <span id="ce-deck-count" class="badge">0</span></h3>
        <div id="ce-deck" class="card-grid small" style="max-height:56vh"></div>
        <button id="ce-save" class="btn primary" style="margin-top:.8rem">Salva mazzo livello</button>
        <p id="ce-msg" class="msg"></p>
    </section>
</div>

<script>
'use strict';
const $ = s => document.querySelector(s);
const RARITY = {common:'Comune',uncommon:'Non-comune',rare:'Rara',mythic:'Mitica'};
const KEYWORDS = ['flying','first_strike','deathtouch','trample','double_strike','lifelink','reach','defender'];

function escHtml(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function thumbUrl(c){ return c.image_url ? c.image_url.replace('/normal/','/small/') : ''; }
function kwIcons(c) {
  let s = '';
  if (+c.flying) s += '✈️'; if (+c.first_strike) s += '⚡'; if (+c.deathtouch) s += '☠️';
  if (+c.trample) s += '🐗'; if (+c.double_strike) s += '⚔️'; if (+c.lifelink) s += '💚';
  if (+c.reach) s += '🏹'; if (+c.defender) s += '🛡️';
  return s;
}

let scenarios = [];   // villaggi della valle corrente
let deckCards = [];    // oggetti carta nel mazzo corrente

// --- Caricamento scenari via API campagna ------------------------------------
async function loadScenarios() {
  const valley = $('#ce-valley').value;
  try {
    const r = await fetch('/api/campaign.php?action=scenarios&valley=' + valley);
    const d = await r.json();
    scenarios = d.ok ? (d.villages || d.scenarios || []) : [];
  } catch { scenarios = []; }

  const vSel = $('#ce-village');
  vSel.innerHTML = '';
  scenarios.forEach((v, i) => {
    const opt = document.createElement('option');
    opt.value = i;
    opt.textContent = v.name + ' (' + (v.tribe || valley) + ')';
    vSel.appendChild(opt);
  });
  loadLevels();
}

function loadLevels() {
  const vi = +$('#ce-village').value;
  const v = scenarios[vi];
  const lSel = $('#ce-level');
  lSel.innerHTML = '';
  if (!v) return;
  (v.levels || []).forEach((l, i) => {
    const opt = document.createElement('option');
    opt.value = i;
    opt.textContent = l.name + (l.boss ? ' (BOSS)' : '');
    lSel.appendChild(opt);
  });
  loadLevelDeck();
}

async function loadLevelDeck() {
  const vi = +$('#ce-village').value;
  const li = +$('#ce-level').value;
  const v = scenarios[vi];
  if (!v) { deckCards = []; renderDeck(); return; }
  const level = (v.levels || [])[li];
  if (!level) { deckCards = []; renderDeck(); return; }

  const ids = level.card_ids || [];
  if (!ids.length) { deckCards = []; renderDeck(); return; }

  try {
    const r = await fetch('/api/cards.php?ids=' + ids.join(','));
    const d = await r.json();
    if (d.ok) {
      const map = {};
      d.cards.forEach(c => map[c.id] = c);
      deckCards = ids.map(id => map[id]).filter(Boolean);
    } else { deckCards = []; }
  } catch { deckCards = []; }
  renderDeck();
}

function cardEl(c, inDeck) {
  const div = document.createElement('div');
  const color = c.colors || 'C';
  div.className = 'card c-' + color;
  if (!thumbUrl(c)) div.classList.add('noimg');

  const img = thumbUrl(c)
    ? `<img loading="lazy" src="${thumbUrl(c)}" alt="${escHtml(c.name)}"
         onerror="this.parentNode.classList.add('noimg');this.remove();">`
    : '';
  const gem = `<span class="gem r-${c.rarity||'common'}"></span>`;
  const src = c.source === 'custom' ? ' <small style="color:var(--spectral)">[C]</small>' : '';
  const kw = kwIcons(c);

  div.innerHTML = `
    <div class="card-header c-${color}">
      <div class="card-cost">${c.mana_value}</div>
      <div class="card-name">${escHtml(c.name)}${src}</div>
    </div>
    <div class="card-image${!thumbUrl(c) ? ' noimg' : ''}">
      ${img}
    </div>
    <div class="card-footer c-${color}">
      <div class="card-subtype">${gem} ${c.subtypes || '–'}</div>
      <div class="card-rarity">${RARITY[c.rarity] || 'Comune'}</div>
    </div>
    <div class="card-stats">
      <div class="card-keywords">${kw || '–'}</div>
      <div class="card-pt">${c.power}/${c.toughness}</div>
    </div>
    <button class="card-btn">${inDeck ? '− Rimuovi' : '+ Aggiungi'}</button>`;

  div.querySelector('.card-btn').addEventListener('click', () => {
    if (inDeck) { removeDeck(c); } else { addDeck(c); }
  });
  return div;
}

function addDeck(c) { deckCards.push(c); renderDeck(); }
function removeDeck(c) {
  const i = deckCards.findIndex(x => x.id === c.id);
  if (i >= 0) deckCards.splice(i, 1);
  renderDeck();
}

function renderDeck() {
  const wrap = $('#ce-deck');
  wrap.innerHTML = '';
  $('#ce-deck-count').textContent = deckCards.length;
  if (!deckCards.length) { wrap.innerHTML = '<em>Mazzo vuoto.</em>'; return; }
  const seen = new Map();
  deckCards.forEach(c => { seen.set(c.id, (seen.get(c.id)||0)+1); });
  const shown = new Set();
  deckCards.forEach(c => {
    if (shown.has(c.id)) return;
    shown.add(c.id);
    const el = cardEl(c, true);
    const n = seen.get(c.id);
    if (n > 1) {
      const tag = document.createElement('span');
      tag.className = 'copies-tag';
      tag.textContent = '×' + n;
      el.querySelector('.mc-imgwrap')?.appendChild(tag);
    }
    wrap.appendChild(el);
  });
}

function loadPool() {
  const p = new URLSearchParams();
  const src = $('#ce-source').value;
  if (src) p.set('source', src);
  const col = $('#ce-color').value;
  if (col) p.set('color', col);
  const q = $('#ce-search').value.trim();
  if (q) p.set('q', q);

  $('#ce-pool').textContent = 'Caricamento…';
  fetch('/api/cards.php?' + p.toString())
    .then(r => r.json())
    .then(d => {
      if (!d.ok) { $('#ce-pool').textContent = 'Errore: ' + d.error; return; }
      const wrap = $('#ce-pool');
      wrap.innerHTML = '';
      $('#ce-pool-count').textContent = d.cards.length;
      if (!d.cards.length) { wrap.innerHTML = '<em>Nessuna carta.</em>'; return; }
      d.cards.forEach(c => wrap.appendChild(cardEl(c, false)));
    })
    .catch(() => { $('#ce-pool').textContent = 'Errore di rete.'; });
}

// --- Salva card_ids nel livello del villaggio (via API campagna) --------------
$('#ce-save').addEventListener('click', async () => {
  const vi = +$('#ce-village').value;
  const li = +$('#ce-level').value;
  const v = scenarios[vi];
  if (!v) { flash('Nessun villaggio selezionato.', false); return; }

  const ids = deckCards.map(c => c.id);
  const levels = [...(v.levels || [])];
  levels[li] = { ...levels[li], card_ids: ids };

  try {
    const r = await fetch('/api/campaign.php?action=update_scenario', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ id: v.id, levels })
    });
    const d = await r.json();
    if (d.ok) {
      flash('Mazzo livello salvato!', true);
      scenarios[vi].levels = levels;
    } else {
      flash('Errore: ' + d.error, false);
    }
  } catch { flash('Errore di rete.', false); }
});

function flash(msg, ok) {
  const m = $('#ce-msg');
  m.textContent = msg;
  m.className = 'msg ' + (ok ? 'ok' : 'err');
}

$('#ce-valley').addEventListener('change', () => { loadScenarios(); loadPool(); });
$('#ce-village').addEventListener('change', loadLevels);
$('#ce-level').addEventListener('change', loadLevelDeck);
$('#ce-source').addEventListener('change', loadPool);
$('#ce-color').addEventListener('change', loadPool);
let t; $('#ce-search').addEventListener('input', () => { clearTimeout(t); t = setTimeout(loadPool, 250); });

loadScenarios();
loadPool();
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
