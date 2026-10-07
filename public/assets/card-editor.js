/* Fucina delle Carte — creazione/modifica carte custom + generazione arte AI. */
'use strict';

const RARITY = {
  common:   { lab: 'Comune',     cls: 'r-common' },
  uncommon: { lab: 'Non-comune', cls: 'r-uncommon' },
  rare:     { lab: 'Rara',       cls: 'r-rare' },
  mythic:   { lab: 'Mitica',     cls: 'r-mythic' },
};
function rarityGem(rarity) {
  const r = RARITY[rarity] || RARITY.common;
  return `<span class="gem ${r.cls}" title="${r.lab}"></span>`;
}
function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

const $ = sel => document.querySelector(sel);
const $$ = sel => Array.from(document.querySelectorAll(sel));

let editingId = null; // id della carta in modifica (null = si sta creando una carta nuova)
let currentId = null;  // id della carta attiva (creata o in modifica) per la generazione arte
let allCustomCards = []; // tutte le carte custom caricate dal server (il filtro/ricerca lavora su questa copia)

const KW_ICON = {
  flying: '✈️', first_strike: '⚡', deathtouch: '☠️', trample: '🐗',
  double_strike: '⚔️', lifelink: '❤️', reach: '🏹', defender: '🛡️',
};
function kwIcons(card) {
  let s = '';
  for (const k in KW_ICON) if (+card[k]) s += `<span title="${k}">${KW_ICON[k]}</span>`;
  return s;
}

/* ---- ricerca tollerante a singolare/plurale (es. "angeli" trova "Angel") ---- */
function normWord(w) {
  return w.toLowerCase().replace(/(ies|es|i|e|s)$/, '');
}
function tokenMatch(query, target) {
  if (target.includes(query) || query.includes(target)) return true;
  const qn = normWord(query), tn = normWord(target);
  if (!qn || !tn) return false;
  return qn === tn || target.includes(qn) || query.includes(tn);
}
function cardMatchesSearch(card, query) {
  query = query.trim().toLowerCase();
  if (!query) return true;
  const haystack = (card.name + ' ' + (card.subtypes || '')).toLowerCase();
  const hWords = haystack.split(/[\s,]+/).filter(Boolean);
  const qWords = query.split(/[\s,]+/).filter(Boolean);
  return qWords.every(qw => hWords.some(hw => tokenMatch(qw, hw)));
}
function cardMatchesColor(card, color) {
  if (!color) return true;
  const colors = String(card.colors || '');
  return color === 'C' ? colors === '' : colors.includes(color);
}

function getFormCard() {
  const colors = $$('#f-colors input:checked').map(i => i.value).join('');
  const kw = {};
  $$('#f-keywords input').forEach(i => { kw[i.value] = i.checked ? 1 : 0; });
  return Object.assign({
    name: $('#f-name').value.trim() || 'Nuova carta',
    mana_value: parseInt($('#f-mana').value, 10) || 0,
    power: parseInt($('#f-power').value, 10) || 0,
    toughness: parseInt($('#f-toughness').value, 10) || 0,
    rarity: $('#f-rarity').value,
    subtypes: $('#f-subtypes').value.trim(),
    art_note: $('#f-artnote').value.trim(),
    colors,
    image_url: null,
  }, kw);
}

function fillFormFromCard(c) {
  $('#f-name').value = c.name || '';
  $('#f-mana').value = c.mana_value ?? 0;
  $('#f-power').value = c.power ?? 0;
  $('#f-toughness').value = c.toughness ?? 0;
  $('#f-rarity').value = c.rarity || 'common';
  $('#f-subtypes').value = c.subtypes || '';
  $('#f-artnote').value = c.art_note || '';
  const colors = String(c.colors || '');
  $$('#f-colors input').forEach(i => { i.checked = colors.includes(i.value); });
  $$('#f-keywords input').forEach(i => { i.checked = !!(+c[i.value]); });
}

function resetForm() {
  $('#card-form').reset();
  $$('#f-colors input, #f-keywords input').forEach(i => { i.checked = false; });
  $('#f-mana').value = 2; $('#f-power').value = 1; $('#f-toughness').value = 1;
  $('#f-artnote').value = '';
}

function cardEl(c, opts) {
  opts = opts || {};
  const div = document.createElement('div');
  div.className = 'mini-card c-' + (c.colors || 'C');
  const img = c.image_url
    ? `<img class="mc-img" loading="lazy" src="${c.image_url}" alt="${escapeHtml(c.name)}">`
    : '';
  div.innerHTML = `
    <div class="mc-imgwrap${img ? '' : ' noimg'}">${img}
      <span class="mc-cost">${c.mana_value}</span>
    </div>
    <div class="mc-meta">
      <span class="mc-name">${rarityGem(c.rarity)} ${escapeHtml(c.name)}</span>
      <span class="mc-pt">${c.power}/${c.toughness} ${kwIcons(c)}${c.subtypes ? ' · <em>' + escapeHtml(c.subtypes) + '</em>' : ''}</span>
    </div>
    ${opts.deletable ? `
    <div class="mc-actions">
      <button type="button" class="mc-btn mc-edit">✎ Modifica</button>
      <button type="button" class="mc-btn mc-regen">🎨 Rigenera arte</button>
      <button type="button" class="mc-btn mc-del">− Elimina</button>
    </div>` : ''}`;
  if (opts.deletable) {
    div.querySelector('.mc-edit').addEventListener('click', () => startEdit(c));
    div.querySelector('.mc-regen').addEventListener('click', (e) => regenArt(c.id, e.target, div));
    div.querySelector('.mc-del').addEventListener('click', () => deleteCard(c.id, div));
  }
  return div;
}

function updatePreview(imageUrl) {
  const wrap = $('#preview');
  wrap.innerHTML = '';
  const c = getFormCard();
  if (currentId) c.id = currentId;
  if (imageUrl) c.image_url = imageUrl;
  wrap.appendChild(cardEl(c, {}));
}

function flash(msg, ok) {
  const m = $('#form-msg');
  m.textContent = msg;
  m.className = 'msg ' + (ok ? 'ok' : 'err');
}

function renderCustomGrid() {
  const grid = $('#custom-grid');
  const color = $('#custom-filter-color').value;
  const query = $('#custom-filter-search').value;
  const filtered = allCustomCards.filter(c => cardMatchesColor(c, color) && cardMatchesSearch(c, query));

  $('#custom-count').textContent = (color || query)
    ? `${filtered.length} / ${allCustomCards.length}`
    : String(allCustomCards.length);

  grid.innerHTML = '';
  if (!allCustomCards.length) { grid.innerHTML = '<em>Nessuna carta custom ancora.</em>'; return; }
  if (!filtered.length) { grid.innerHTML = '<em>Nessuna carta corrisponde al filtro.</em>'; return; }
  filtered.forEach(c => grid.appendChild(cardEl(c, { deletable: true })));
}

function loadCustomCards() {
  const grid = $('#custom-grid');
  grid.textContent = 'Caricamento…';
  fetch('api/card_create.php')
    .then(r => r.json())
    .then(data => {
      if (!data.ok) { grid.textContent = 'Errore: ' + data.error; return; }
      allCustomCards = data.cards;
      renderCustomGrid();
    })
    .catch(() => { grid.textContent = 'Errore di rete.'; });
}

function startEdit(c) {
  editingId = c.id;
  currentId = c.id;
  fillFormFromCard(c);
  $('#form-title').textContent = 'Modifica carta';
  $('#btn-create').textContent = 'Salva modifiche';
  $('#btn-cancel-edit').hidden = false;
  $('#btn-genart').disabled = false;
  updatePreview(c.image_url);
  flash('Stai modificando "' + c.name + '".', true);
  $('#card-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function cancelEdit() {
  editingId = null;
  currentId = null;
  resetForm();
  $('#form-title').textContent = 'Nuova carta';
  $('#btn-create').textContent = 'Crea carta';
  $('#btn-cancel-edit').hidden = true;
  $('#btn-genart').disabled = true;
  flash('', true);
  updatePreview();
}

function deleteCard(id, el) {
  if (!confirm('Eliminare questa carta? Non è reversibile.')) return;
  fetch('api/card_create.php?id=' + encodeURIComponent(id), { method: 'DELETE' })
    .then(r => r.json())
    .then(data => {
      if (data.ok) {
        el.remove();
        if (editingId === id) cancelEdit();
        loadCustomCards();
      } else alert('Errore: ' + data.error);
    });
}

function regenArt(id, btn, cardDiv) {
  const orig = btn.textContent;
  btn.disabled = true;
  btn.textContent = '🎨 Generazione…';
  fetch('api/card_art.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id }),
  })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.textContent = orig;
      if (!data.ok) { alert('Errore generazione arte: ' + data.error); return; }
      const img = cardDiv.querySelector('.mc-img');
      const wrap = cardDiv.querySelector('.mc-imgwrap');
      // data.image_url è già versionato (?v=timestamp) dal server: niente bust manuale,
      // altrimenti si finisce con due "?" nello stesso URL (malformato).
      if (img) { img.src = data.image_url; }
      else if (wrap) { wrap.classList.remove('noimg'); wrap.insertAdjacentHTML('afterbegin', `<img class="mc-img" src="${data.image_url}" alt="">`); }
      if (currentId === id) updatePreview(data.image_url);
      const found = allCustomCards.find(c => c.id === id);
      if (found) found.image_url = data.image_url;
    })
    .catch(() => { btn.disabled = false; btn.textContent = orig; alert('Errore di rete durante la generazione.'); });
}

$('#card-form').addEventListener('submit', (e) => {
  e.preventDefault();
  const payload = getFormCard();
  const isEdit = !!editingId;
  if (isEdit) payload.id = editingId;

  fetch('api/card_create.php', {
    method: isEdit ? 'PUT' : 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
    .then(r => r.json())
    .then(data => {
      if (!data.ok) { flash('Errore: ' + data.error, false); return; }
      currentId = data.id;
      if (isEdit) {
        flash('Modifiche salvate!', true);
      } else {
        flash("Carta creata! Ora genera l'arte, oppure creane un'altra.", true);
        $('#btn-genart').disabled = false;
      }
      updatePreview();
      loadCustomCards();
    })
    .catch(() => flash('Errore di rete.', false));
});

$('#btn-genart').addEventListener('click', () => {
  if (!currentId) return;
  const btn = $('#btn-genart');
  btn.disabled = true;
  btn.textContent = '🎨 Generazione in corso…';
  fetch('api/card_art.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: currentId }),
  })
    .then(r => r.json())
    .then(data => {
      btn.textContent = '🎨 Genera arte con AI';
      btn.disabled = false;
      if (!data.ok) { flash('Errore generazione arte: ' + data.error, false); return; }
      flash('Arte generata!', true);
      updatePreview(data.image_url);
      loadCustomCards();
    })
    .catch(() => {
      flash('Errore di rete durante la generazione.', false);
      btn.textContent = '🎨 Genera arte con AI';
      btn.disabled = false;
    });
});

$('#btn-cancel-edit').addEventListener('click', cancelEdit);
$$('#card-form input, #card-form select, #card-form textarea').forEach(el => el.addEventListener('input', () => updatePreview()));

$('#custom-filter-color').addEventListener('change', renderCustomGrid);
let searchDebounce;
$('#custom-filter-search').addEventListener('input', () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(renderCustomGrid, 150);
});

updatePreview();
loadCustomCards();
