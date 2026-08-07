/* Deck editor — vanilla JS. */
'use strict';

const state = {
  pool: [],
  deck: [], // array di carte (oggetti)
};

// Regole mazzo (devono combaciare con config.php).
const RULES = { min: 15, max: 20, copies: 2 };

// Rarità: etichetta + gemma colorata.
const RARITY = {
  common:   { lab: 'Comune',     cls: 'r-common' },
  uncommon: { lab: 'Non-comune', cls: 'r-uncommon' },
  rare:     { lab: 'Rara',       cls: 'r-rare' },
  mythic:   { lab: 'Mitica',     cls: 'r-mythic' },
};
function rarityGem(c) {
  const r = RARITY[c.rarity] || RARITY.common;
  return `<span class="gem ${r.cls}" title="${r.lab}"></span>`;
}

const $ = sel => document.querySelector(sel);

const colorSel  = $('#color');
const manaSel   = $('#mana');
const searchInp = $('#search');

function kwIcons(c) {
  let s = '';
  if (+c.flying)       s += '<span title="Flying">✈️</span>';
  if (+c.first_strike) s += '<span title="First strike">⚡</span>';
  if (+c.deathtouch)   s += '<span title="Deathtouch">☠️</span>';
  if (+c.trample)      s += '<span title="Trample">🐗</span>';
  return s;
}

function escapeHtml(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

// Miniatura leggera (Scryfall "small") per il pool; fallback alla normal.
function thumbUrl(c){ return c.image_url ? c.image_url.replace('/normal/','/small/') : ''; }

function cardEl(c, inDeck) {
  const div = document.createElement('div');
  div.className = 'mini-card c-' + (c.colors || 'C');
  const img = thumbUrl(c)
    ? `<img class="mc-img" loading="lazy" src="${thumbUrl(c)}" alt="${escapeHtml(c.name)}"
         onerror="this.parentNode.classList.add('noimg');this.remove();">`
    : '';
  div.innerHTML = `
    <div class="mc-imgwrap">${img}
      <span class="mc-cost">${c.mana_value}</span>
    </div>
    <div class="mc-meta">
      <span class="mc-name">${rarityGem(c)} ${escapeHtml(c.name)}</span>
      <span class="mc-pt">${c.power}/${c.toughness} ${kwIcons(c)} · <em>${(RARITY[c.rarity]||RARITY.common).lab}</em></span>
    </div>
    <button class="mc-btn">${inDeck ? '− Rimuovi' : '+ Aggiungi'}</button>`;
  div.querySelector('.mc-btn').addEventListener('click', () => {
    inDeck ? removeFromDeck(c) : addToDeck(c);
  });
  return div;
}

function loadPool() {
  const params = new URLSearchParams();
  params.set('color', colorSel.value);
  if (manaSel.value !== '') params.set('mana_value', manaSel.value);
  if (searchInp.value.trim() !== '') params.set('q', searchInp.value.trim());

  $('#pool').textContent = 'Caricamento…';
  params.set('source', 'custom');
  fetch('/api/cards.php?' + params.toString())
    .then(r => r.json())
    .then(data => {
      if (!data.ok) { $('#pool').textContent = 'Errore: ' + data.error; return; }
      state.pool = data.cards;
      renderPool();
    })
    .catch(() => { $('#pool').textContent = 'Errore di rete.'; });
}

function renderPool() {
  const wrap = $('#pool');
  wrap.innerHTML = '';
  $('#pool-count').textContent = state.pool.length;
  if (!state.pool.length) { wrap.innerHTML = '<em>Nessuna carta.</em>'; return; }
  state.pool.forEach(c => wrap.appendChild(cardEl(c, false)));
}

function copies(id) { return state.deck.filter(x => x.id === id).length; }

function renderDeck() {
  const wrap = $('#deck');
  wrap.innerHTML = '';
  // Raggruppa per id mostrando "xN".
  const seen = new Set();
  state.deck.forEach(c => {
    if (seen.has(c.id)) return;
    seen.add(c.id);
    const el = cardEl(c, true);
    const n = copies(c.id);
    if (n > 1) {
      const tag = document.createElement('span');
      tag.className = 'copies-tag';
      tag.textContent = '×' + n;
      el.querySelector('.mc-imgwrap')?.appendChild(tag);
    }
    wrap.appendChild(el);
  });

  const n = state.deck.length;
  const cnt = $('#deck-count');
  cnt.textContent = `${n} / ${RULES.min}–${RULES.max}`;
  const valid = n >= RULES.min && n <= RULES.max;
  cnt.className = 'badge ' + (valid ? 'ok' : 'warn');
  $('#save-deck').disabled = !valid;
}

function flash(msg, ok = false) {
  const m = $('#save-msg');
  m.textContent = msg;
  m.className = 'msg ' + (ok ? 'ok' : 'err');
}

function addToDeck(c) {
  if (state.deck.length >= RULES.max) { flash(`Mazzo pieno (max ${RULES.max} carte).`); return; }
  if (copies(c.id) >= RULES.copies) { flash(`Massimo ${RULES.copies} copie di "${c.name}".`); return; }
  state.deck.push(c);
  flash('', true);
  renderDeck();
}

function removeFromDeck(c) {
  const i = state.deck.findIndex(x => x.id === c.id);
  if (i >= 0) state.deck.splice(i, 1);
  renderDeck();
}

function saveDeck() {
  const name = $('#deck-name').value.trim();
  const msg  = $('#save-msg');
  if (!name) { msg.textContent = 'Dai un nome al mazzo.'; msg.className = 'msg err'; return; }
  const ids = state.deck.map(c => c.id);
  fetch('/api/decks.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, color: colorSel.value, card_ids: ids })
  })
    .then(r => r.json())
    .then(data => {
      if (data.ok) {
        msg.textContent = 'Mazzo salvato! Vai in Home per giocare.';
        msg.className = 'msg ok';
      } else {
        msg.textContent = 'Errore: ' + data.error;
        msg.className = 'msg err';
      }
    })
    .catch(() => { msg.textContent = 'Errore di rete.'; msg.className = 'msg err'; });
}

// Cambiando colore si svuota il mazzo (un solo colore consentito).
colorSel.addEventListener('change', () => { state.deck = []; renderDeck(); loadPool(); });
manaSel.addEventListener('change', loadPool);
let t; searchInp.addEventListener('input', () => { clearTimeout(t); t = setTimeout(loadPool, 250); });
$('#save-deck').addEventListener('click', saveDeck);

loadPool();
renderDeck();
