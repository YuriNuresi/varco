/* Draft "apri buste" — vanilla JS. */
'use strict';

const $ = s => document.querySelector(s);
const state = { color: '', pool: [], deck: [] };
const RULES = { min: 15, max: 20, copies: 2 };

const RARITY = {
  common:   { lab: 'Comune',     cls: 'r-common' },
  uncommon: { lab: 'Non-comune', cls: 'r-uncommon' },
  rare:     { lab: 'Rara',       cls: 'r-rare' },
  mythic:   { lab: 'Mitica',     cls: 'r-mythic' },
};
function rarityGem(c){const r=RARITY[c.rarity]||RARITY.common;return `<span class="gem ${r.cls}" title="${r.lab}"></span>`;}
function escapeHtml(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function thumb(c){return c.image_url?c.image_url.replace('/normal/','/small/'):'';}
function kwIcons(c){let s='';if(+c.flying)s+='✈️';if(+c.first_strike)s+='⚡';if(+c.deathtouch)s+='☠️';if(+c.trample)s+='🐗';return s;}

// indice univoco per il pool (può contenere doppioni): uso la posizione.
function cardEl(c, where, idx) {
  const div = document.createElement('div');
  div.className = 'mini-card c-' + (c.colors || 'C');
  const img = thumb(c) ? `<img class="mc-img" loading="lazy" src="${thumb(c)}" alt="${escapeHtml(c.name)}" onerror="this.parentNode.classList.add('noimg');this.remove();">` : '';
  div.innerHTML = `
    <div class="mc-imgwrap">${img}<span class="mc-cost">${c.mana_value}</span></div>
    <div class="mc-meta">
      <span class="mc-name">${rarityGem(c)} ${escapeHtml(c.name)}</span>
      <span class="mc-pt">${c.power}/${c.toughness} ${kwIcons(c)} · <em>${(RARITY[c.rarity]||RARITY.common).lab}</em></span>
    </div>
    <button class="mc-btn">${where === 'deck' ? '− Togli' : '+ Aggiungi'}</button>`;
  div.querySelector('.mc-btn').addEventListener('click', () => {
    where === 'deck' ? removeFromDeck(idx) : addToDeck(c);
  });
  return div;
}

function copies(id){ return state.deck.filter(x => x.id === id).length; }

function openPacks(color) {
  state.color = color;
  $('#draft-msg').textContent = 'Apro le buste…';
  fetch('api/draft.php?color=' + color)
    .then(r => r.json())
    .then(data => {
      if (!data.ok) { $('#draft-msg').textContent = 'Errore: ' + data.error; return; }
      state.pool = data.pool;
      state.deck = [];
      $('#draft-msg').textContent = '';
      $('#build-area').hidden = false;
      renderPool();
      renderDeck();
      document.querySelectorAll('.color-btn').forEach(b => b.classList.toggle('active', b.dataset.color === color));
    })
    .catch(() => { $('#draft-msg').textContent = 'Errore di rete.'; });
}

function renderPool() {
  const wrap = $('#pool');
  wrap.innerHTML = '';
  $('#pool-count').textContent = state.pool.length;
  state.pool.forEach(c => wrap.appendChild(cardEl(c, 'pool')));
}

function renderDeck() {
  const wrap = $('#deck');
  wrap.innerHTML = '';
  const seen = new Set();
  state.deck.forEach((c, i) => {
    if (seen.has(c.id)) return;
    seen.add(c.id);
    const el = cardEl(c, 'deck', state.deck.findIndex(x => x.id === c.id));
    const n = copies(c.id);
    if (n > 1) {
      const tag = document.createElement('span');
      tag.className = 'copies-tag'; tag.textContent = '×' + n;
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
  const m = $('#save-msg'); m.textContent = msg; m.className = 'msg ' + (ok ? 'ok' : 'err');
}

function addToDeck(c) {
  if (state.deck.length >= RULES.max) { flash(`Mazzo pieno (max ${RULES.max}).`); return; }
  if (copies(c.id) >= RULES.copies) { flash(`Max ${RULES.copies} copie di "${c.name}".`); return; }
  state.deck.push(c); flash('', true); renderDeck();
}
function removeFromDeck(idx) {
  if (idx >= 0) state.deck.splice(idx, 1);
  renderDeck();
}

function saveDeck() {
  const name = $('#deck-name').value.trim();
  if (!name) { flash('Dai un nome al mazzo.'); return; }
  fetch('api/decks.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, color: state.color, card_ids: state.deck.map(c => c.id) })
  })
    .then(r => r.json())
    .then(data => {
      if (data.ok) flash('Mazzo salvato! Vai in Home per giocare.', true);
      else flash('Errore: ' + data.error);
    })
    .catch(() => flash('Errore di rete.'));
}

document.querySelectorAll('.color-btn').forEach(b => b.addEventListener('click', () => openPacks(b.dataset.color)));
$('#save-deck').addEventListener('click', saveDeck);
