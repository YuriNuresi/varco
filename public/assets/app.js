/* Battaglia — modello MULTI-ROUND (vita persistente, para/subisci). Vanilla JS. */
'use strict';

const $  = sel => document.querySelector(sel);
const battleEl = $('.battle');
const deckId   = parseInt(battleEl.dataset.deckId, 10) || 0;
const startColor = (battleEl.dataset.color || '').toUpperCase();
const CAMPAIGN = battleEl.dataset.campaign === '1';
const TUTORIAL = battleEl.dataset.tutorial === '1';
const MAGE_LIFE0 = parseInt(battleEl.dataset.mageLife, 10) || 10;
const MANA_CAP   = parseInt(battleEl.dataset.manaCap, 10) || 10;

const G = {
  state: null,
  round: 1,
  hand: [],
  budget: 0,
  life: { player: MAGE_LIFE0, ai: MAGE_LIFE0 },
  selectableLane: null,
  lane1Attacker: null, // carta CPU che attacca la corsia 1 (per legalità del blocco)
  aiHandCount: 6,
  blindRevealed: false,
};

const wait = ms => new Promise(r => setTimeout(r, ms));

function api(action, payload = {}) {
  return fetch('/api/battle.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(Object.assign({ action }, payload))
  }).then(r => r.text()).then(text => {
    let res;
    try { res = JSON.parse(text); }
    catch (e) {
      console.error('[battle] risposta non-JSON dal server:', text);
      return { ok: false, error: 'Risposta non valida dal server (vedi console).' };
    }
    if (res._warn) console.warn('[battle] warning PHP:', res._warn);
    return res;
  });
}

function escapeHtml(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

function kwIcons(c){let s='';if(+c.flying)s+='<span title="Volare">✈️</span>';if(+c.first_strike)s+='<span title="Attacco improvviso">⚡</span>';if(+c.deathtouch)s+='<span title="Tocco letale">☠️</span>';if(+c.trample)s+='<span title="Travolgere">🐗</span>';if(+c.double_strike)s+='<span title="Doppio attacco">⚔️</span>';if(+c.lifelink)s+='<span title="Legame vitale">💖</span>';if(+c.reach)s+='<span title="Raggiungere">🏹</span>';if(+c.defender)s+='<span title="Difensore">🧱</span>';return s;}
function effT(c){ return (+c.toughness || 0) - (+c.wounds || 0); }   // costituzione efficace
function ptHtml(c){ const w=(+c.wounds||0)>0; return `${c.power}/<span class="${w?'pt-wounded':''}">${effT(c)}</span>`; }
function cardImg(c){if(!c||!c.image_url)return '';return `<img class="pc-img" loading="lazy" draggable="false" src="${c.image_url}" alt="${escapeHtml(c.name)}" onerror="this.parentNode.classList.add('noimg');this.remove();">`;}

const RARITY_LABEL = { common: 'Comune', uncommon: 'Non-comune', rare: 'Rara', mythic: 'Mitica' };

function cardMarkup(c, extraClass = '') {
  const isWounded = (+c.wounds || 0) > 0;
  const kw = kwIcons(c);
  const img = cardImg(c);
  const color = c.colors || 'C';
  return `<div class="card c-${color} ${extraClass}${isWounded ? ' wounded' : ''}">
    <div class="card-header c-${color}">
      <div class="card-cost">${c.mana_value}</div>
      <div class="card-name">${escapeHtml(c.name)}</div>
    </div>
    <div class="card-image${!img ? ' noimg' : ''}">
      ${img}
      <div class="pc-fallback">
        <div class="pc-top"><span class="pc-name">${escapeHtml(c.name)}</span><span class="pc-cost">${c.mana_value}</span></div>
        <div class="pc-pt">${ptHtml(c)}${isWounded ? '<span style="color:var(--blood-soft)"> ⚠</span>' : ''}</div>
        <div class="pc-kw">${kw || '–'}</div>
      </div>
    </div>
    <div class="card-footer c-${color}">
      <div class="card-subtype">${c.subtypes || '–'}</div>
      <div class="card-rarity">${RARITY_LABEL[c.rarity] || 'Comune'}</div>
    </div>
    <div class="card-stats">
      <div class="card-keywords">${kw || '–'}</div>
      <div class="card-pt">${ptHtml(c)}</div>
    </div>
  </div>`;
}

/* --- Popup "ispeziona carta": PV originali + TUTTE le abilità native.
   In gioco una creatura con più abilità ne mostra solo UNA (quella scelta); qui le vedi tutte. --- */
function showCardPopup(card){
  if (!card) return;
  closeCardPopup();
  const kws = cardKeywords(card);
  const abil = kws.length
    ? kws.map(k => `<li><span class="cp-ic">${KW_ICON[k]}</span><span class="cp-ab"><b>${KW_NAME[k]}</b><span class="cp-d">${KW_DESC[k] || ''}</span></span></li>`).join('')
    : '<li class="cp-none">Nessuna abilità speciale.</li>';
  const ov = document.createElement('div');
  ov.className = 'card-popup';
  ov.innerHTML = `
    <div class="cp-back"></div>
    <div class="cp-body" role="dialog" aria-modal="true">
      <button class="cp-x" aria-label="Chiudi">✕</button>
      <div class="cp-card">${cardMarkup(card)}</div>
      <div class="cp-info">
        <div class="cp-title">${escapeHtml(card.name)}</div>
        <div class="cp-meta">${escapeHtml(card.subtypes || '—')} · ${RARITY_LABEL[card.rarity] || 'Comune'} · costo ${card.mana_value}</div>
        <div class="cp-pt">Forza <b>${card.power}</b> · PV originali <b>${+card.toughness || 0}</b></div>
        <div class="cp-abtitle">Abilità native${kws.length > 1 ? ' (in gioco se ne usa solo una)' : ''}</div>
        <ul class="cp-ablist">${abil}</ul>
      </div>
    </div>`;
  document.body.appendChild(ov);
  ov.querySelector('.cp-back').onclick = closeCardPopup;
  ov.querySelector('.cp-x').onclick = closeCardPopup;
  document.addEventListener('keydown', cpEsc);
  requestAnimationFrame(() => ov.classList.add('open'));
}
function cpEsc(e){ if (e.key === 'Escape') closeCardPopup(); }
function closeCardPopup(){
  const ov = document.querySelector('.card-popup');
  if (ov) ov.remove();
  document.removeEventListener('keydown', cpEsc);
}

/* --- Ferita "a coltellata": striscia di sangue obliqua che colpisce il bersaglio e svanisce in ~1s. --- */
function bloodSlash(el){
  if (!el) return;
  const s = document.createElement('div');
  s.className = 'blood-slash';
  el.appendChild(s);
  setTimeout(() => s.remove(), 1000);
}
function bloodSlashCard(lane, side){
  bloodSlash(document.querySelector(`.slot[data-lane="${lane}"][data-side="${side}"] :is(.card,.play-card)`));
}

function setPrompt(t){ $('#prompt').textContent = t; }
function renderMana(b){
  const wrap = $('#mana-shards'); if (!wrap) return;
  let h = '';
  for (let i = 0; i < MANA_CAP; i++) h += '<span class="sh' + (i < b ? ' on' : '') + '"></span>';
  wrap.innerHTML = h;
}
function setBudget(b){
  G.budget = b;
  const el = $('#budget'); if (el) el.textContent = b;
  renderMana(b);
}
// Barra vita: il segmento perso lampeggia 3s, poi si ritrae fino al nuovo valore.
function setMeter(side, life){
  const fill = document.getElementById('meter-' + side);
  const loss = document.getElementById('loss-' + side);
  if (!fill) return;
  const cap = MAGE_LIFE0 || 10;
  const pct = v => Math.max(0, Math.min(100, v / cap * 100));
  if (!G.lifeShown) G.lifeShown = {};
  const prev = (G.lifeShown[side] != null) ? G.lifeShown[side] : life;
  const newW = pct(life), prevW = pct(prev);
  G.lifeShown[side] = life;
  fill.style.width = newW + '%';

  if (!loss) return;
  if (life < prev) {
    loss.style.left = newW + '%';
    loss.style.width = (prevW - newW) + '%';
    loss.style.opacity = '1';
    loss.classList.add('flashing');
    clearTimeout(loss._t);
    loss._t = setTimeout(() => {
      loss.classList.remove('flashing');
      loss.style.opacity = '0';
      loss.style.width = '0%';
    }, 3000);
  } else {
    clearTimeout(loss._t);
    loss.classList.remove('flashing');
    loss.style.opacity = '0';
    loss.style.width = '0%';
  }
}
function setLife(){
  const lp = $('#life-player'), la = $('#life-ai');
  if (lp) lp.textContent = '❤️ ' + Math.max(0, G.life.player);
  if (la) la.textContent = '❤️ ' + Math.max(0, G.life.ai);
  setMeter('player', G.life.player);
  setMeter('ai', G.life.ai);
}
function setRound(n){ G.round = n; const el = $('#round-tag'); if (el) el.textContent = 'Round ' + n; window.Music && Music.setRound(n); }

// Doni (keyword) scelti ogni 3 round: 1 al giocatore, 1 diverso alla CPU.
const BOON_ICON = { trample:'🐗', flying:'✈️', first_strike:'⚡', deathtouch:'☠️' };
const BOON_NAME = { trample:'Travolgere', flying:'Volare', first_strike:'Attacco fulmineo', deathtouch:'Tocco letale' };
const BOON_DESC = {
  trample:'i danni in eccesso passano al mago',
  flying:'evade le creature di terra',
  first_strike:'colpisce per primo',
  deathtouch:'uccide qualunque creatura tocchi',
};

/* Regola "una sola abilità": una creatura con 2+ keyword stampate ne usa SOLO una (scelta a mano).
   I doni del round 3/6 (sopra) restano cumulabili e fanno eccezione. */
const KW_KEYS = ['flying','first_strike','deathtouch','trample','double_strike','lifelink','reach','defender'];
const KW_ICON = { flying:'✈️', first_strike:'⚡', deathtouch:'☠️', trample:'🐗', double_strike:'⚔️', lifelink:'💖', reach:'🏹', defender:'🧱' };
const KW_NAME = { flying:'Volare', first_strike:'Attacco improvviso', deathtouch:'Tocco letale', trample:'Travolgere', double_strike:'Doppio attacco', lifelink:'Legame vitale', reach:'Raggiungere', defender:'Difensore' };
const KW_DESC = {
  flying:'Può essere bloccata solo da creature con Volare o Raggiungere.',
  first_strike:'Infligge il suo danno per prima: se uccide, non subisce il colpo.',
  deathtouch:'Qualsiasi danno che infligge basta a uccidere la creatura colpita.',
  trample:'I danni in eccesso oltre la difesa passano al mago avversario.',
  double_strike:'Colpisce due volte: un colpo improvviso e uno normale.',
  lifelink:'Il danno che infligge cura il tuo mago della stessa quantità.',
  reach:'Può bloccare le creature con Volare.',
  defender:'Non può attaccare: serve solo a difendere.',
};
function cardKeywords(c){ return KW_KEYS.filter(k => +c[k]); }
function reduceCardClient(card, kw){ const c = Object.assign({}, card); KW_KEYS.forEach(k => { if (k !== kw) c[k] = false; }); return c; }
// Mostra la scelta dell'abilità (riusa il pannello #boon-pick). Chiama onChosen(kw) alla scelta.
function showKeywordChoice(card, onChosen){
  const box = $('#boon-pick');
  const kws = cardKeywords(card);
  if (!box || kws.length < 2) { onChosen(null); return; }
  box.innerHTML =
    `<div class="bp-title">${escapeHtml(card.name)} ha più abilità — scegline UNA da usare</div>
     <div class="bp-grid">` +
    kws.map(k => `<button class="bp-card" data-k="${k}">
        <span class="bp-ic">${KW_ICON[k]}</span><span class="bp-name">${KW_NAME[k]}</span>
      </button>`).join('') +
    `</div><div class="bp-note">Le altre si spengono per questa creatura (i doni del round 3/6 fanno eccezione).</div>`;
  box.hidden = false;
  box.querySelectorAll('.bp-card').forEach(b => b.onclick = () => { box.hidden = true; onChosen(b.dataset.k); });
}
function applyBoons(){
  const corr = document.querySelector('.corridor');
  if (corr) corr.classList.toggle('empowered', G.round >= 3);
  renderBoonMarks();
}
function setBoonMark(col, list){
  let m = col.querySelector('.boon-mark');
  if (!list || !list.length){ if (m) m.remove(); return; }
  if (!m){ m = document.createElement('div'); m.className = 'boon-mark'; col.appendChild(m); }
  m.innerHTML = list.map(k => `<span class="bi" title="${BOON_NAME[k]}: ${BOON_DESC[k]}">${BOON_ICON[k]}</span>`).join('');
}
function renderBoonMarks(){
  document.querySelectorAll('.side-ai .lane-col').forEach(c => setBoonMark(c, G.aiBoons || []));
  document.querySelectorAll('.side-player .lane-col').forEach(c => setBoonMark(c, G.playerBoons || []));
}

function renderAiHand() {
  const wrap = $('#ai-hand');
  wrap.innerHTML = '';
  for (let i = 0; i < G.aiHandCount; i++) {
    const b = document.createElement('div');
    b.className = 'hand-back';
    wrap.appendChild(b);
  }
}
function aiPlayed() { if (G.aiHandCount > 0) G.aiHandCount--; renderAiHand(); }

const COSTO_MINIMO = 1;
function lanesRemainingAfter(){ if(G.selectableLane===0)return 2; if(G.selectableLane===1)return 1; return 0; }

function canBlockClient(attacker, blocker){ return !(+attacker.flying && !+blocker.flying); }

function renderHand() {
  const wrap = $('#hand');
  wrap.innerHTML = '';
  const reserve = (G.selectableLane === null ? 0 : lanesRemainingAfter()) * COSTO_MINIMO;
  const cards = [...G.hand].sort((a, b) => a.mana_value - b.mana_value || a.name.localeCompare(b.name));
  const deal = G.dealNext;
  cards.forEach((c, idx) => {
    const div = document.createElement('div');
    const affordable = c.mana_value <= (G.budget - reserve);
    const wounded = (+c.wounds || 0) > 0;
    const kw = kwIcons(c);
    const color = c.colors || 'C';
    div.className = 'card hand-card c-' + color + (affordable ? '' : ' unaffordable') + (wounded ? ' wounded' : '') + (deal ? ' dealing' : '');
    if (deal) div.style.animationDelay = (idx * 0.09) + 's';
    div.innerHTML = `
      <div class="card-header c-${color}">
        <div class="card-cost">${c.mana_value}</div>
        <div class="card-name">${escapeHtml(c.name)}</div>
      </div>
      <div class="card-image">
        ${cardImg(c)}
      </div>
      <div class="card-footer c-${color}">
        <div class="card-subtype">${c.subtypes || '–'}</div>
        <div class="card-rarity">–</div>
      </div>
      <div class="card-stats">
        <div class="card-keywords">${kw || '–'}</div>
        <div class="card-pt">${ptHtml(c)}</div>
      </div>`;
    if (G.selectableLane !== null && affordable) {
      makeCardPlayable(div, c);
    }
    wrap.appendChild(div);
  });
  G.dealNext = false; // l'animazione di pesca avviene solo a inizio round
}

/* La carta giocabile si può usare in DUE modi (identici nell'effetto):
   - CLICK: la giochi al volo sulla corsia attiva;
   - DRAG&DROP: la trascini sulla piazzola evidenziata e la rilasci lì.
   Il target è sempre la piazzola della corsia attiva (G.selectableLane), lato giocatore.
   Usiamo i Pointer Events così funziona sia con mouse che col dito (tablet). */
function makeCardPlayable(div, c) {
  div.classList.add('playable');
  let startX = 0, startY = 0, dragging = false, ghost = null, target = null, suppressClick = false;

  const targetSlot = () =>
    G.selectableLane === null ? null
      : document.querySelector(`.slot[data-lane="${G.selectableLane}"][data-side="player"]`);

  const isOver = (el, x, y) => {
    if (!el) return false;
    const r = el.getBoundingClientRect();
    return x >= r.left && x <= r.right && y >= r.top && y <= r.bottom;
  };

  const moveGhost = (x, y) => { if (ghost) { ghost.style.left = x + 'px'; ghost.style.top = y + 'px'; } };

  const startDrag = () => {
    dragging = true;
    target = targetSlot();
    div.classList.add('dragging');
    target?.classList.add('drop-target');
    ghost = div.cloneNode(true);
    ghost.className = 'card hand-card drag-ghost ' + (c.colors ? 'c-' + c.colors : 'c-C');
    ghost.style.cssText = ''; // via eventuali stili inline ereditati (es. animation-delay)
    ghost.style.width = div.getBoundingClientRect().width + 'px'; // stessa dimensione della carta reale
    document.body.appendChild(ghost);
  };

  const cleanup = () => {
    dragging = false;
    div.classList.remove('dragging');
    if (ghost) { ghost.remove(); ghost = null; }
    document.querySelectorAll('.slot.drop-target').forEach(s => s.classList.remove('drop-target', 'drop-over'));
    target = null;
  };

  const onMove = (e) => {
    if (!dragging) {
      if (Math.hypot(e.clientX - startX, e.clientY - startY) < 8) return;
      startDrag();
    }
    moveGhost(e.clientX, e.clientY);
    if (target) target.classList.toggle('drop-over', isOver(target, e.clientX, e.clientY));
  };

  const onUp = (e) => {
    div.removeEventListener('pointermove', onMove);
    div.removeEventListener('pointerup', onUp);
    div.removeEventListener('pointercancel', onUp);
    if (!dragging) return;
    const dropped = isOver(target, e.clientX, e.clientY);
    cleanup();
    suppressClick = true;
    setTimeout(() => { suppressClick = false; }, 60);
    if (dropped && G.selectableLane !== null) onPick(c);
  };

  div.addEventListener('pointerdown', (e) => {
    if (e.button != null && e.button !== 0) return; // solo tasto sinistro / tocco
    if (G.selectableLane === null) return;
    e.preventDefault(); // blocca il drag nativo dell'immagine/selezione testo (rubava il gesto su PC)
    startX = e.clientX; startY = e.clientY;
    dragging = false;
    try { div.setPointerCapture(e.pointerId); } catch (_) {}
    div.addEventListener('pointermove', onMove);
    div.addEventListener('pointerup', onUp);
    div.addEventListener('pointercancel', onUp);
  });

  // Il click resta valido per il gioco "al volo"; se è stato un drag, lo sopprimiamo.
  div.addEventListener('click', (e) => {
    if (suppressClick) { e.stopImmediatePropagation(); e.preventDefault(); return; }
    onPick(c);
  });
}

// detail = carta NATIVA da mostrare nel popup (PV originali + tutte le abilità).
//   - se passato: lo salvo sulla piazzola;
//   - se NON passato (undefined): conservo il detail già presente (ri-piazzate "ferite" di animateLane);
//   - la carta CPU è già nativa, quindi come default uso la carta stessa.
function placeCard(lane, side, card, faceDown = false, animate = false, detail) {
  const slot = document.querySelector(`.slot[data-lane="${lane}"][data-side="${side}"]`);
  if (!slot) return;
  if (!faceDown && !card) { slot.innerHTML = slotRole(lane, side); slot._cardDetail = null; return; } // passata: piazzola vuota
  if (detail !== undefined) slot._cardDetail = detail;
  else if (slot._cardDetail == null && !faceDown) slot._cardDetail = card;
  slot.innerHTML = faceDown ? '<div class="play-card facedown"></div>' : cardMarkup(card, animate ? 'reveal' : '');
  if (!faceDown && slot._cardDetail) {
    const el = slot.querySelector('.card,.play-card');
    if (el) { el.classList.add('inspectable'); el.addEventListener('click', () => showCardPopup(slot._cardDetail)); }
  }
}

function badge(lane, side, text, cls) {
  const slot = document.querySelector(`.slot[data-lane="${lane}"][data-side="${side}"] :is(.card,.play-card)`);
  if (!slot) return;
  const b = document.createElement('span');
  b.className = 'lane-badge ' + (cls || '');
  b.textContent = text;
  slot.appendChild(b);
}

// Rimuove per uid (id d'istanza): con 2 copie dello stesso id ne toglie UNA sola.
function removeFromHand(uid){ const i=G.hand.findIndex(c=>(c.uid||c.id)===uid); if(i>=0)G.hand.splice(i,1); }

function cpuThink(ms = 900, msg = '🤖 La CPU sta pensando…') {
  setPrompt(msg);
  const p = $('#prompt'); p.classList.add('thinking');
  return new Promise(r => setTimeout(() => { p.classList.remove('thinking'); r(); }, ms));
}

/* Icone "ruolo" nelle piazzole vuote: lancia=attacco, scudo=difesa, doppia lancia=cieca. */
const ICON_LANCE  = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20L17 7"/><path d="M17 7l-5 .4"/><path d="M17 7l-.4 5"/></svg>';
const ICON_LANCE2 = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20L17 7"/><path d="M17 7l-5 .4"/><path d="M17 7l-.4 5"/><path d="M20 20L7 7"/><path d="M7 7l5 .4"/><path d="M7 7l.4 5"/></svg>';
const ICON_SHIELD = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 3v6c0 5-4 8-8 9-4-1-8-4-8-9V6z"/><path d="M8.5 12l2.5 2.5L15.5 10"/></svg>';
function slotRole(lane, side) {
  if (lane === 2) return `<span class="slot-role attack" title="attacco alla cieca">${ICON_LANCE2}</span>`;
  const attacker = (lane === 0 && side === 'player') || (lane === 1 && side === 'ai');
  return attacker
    ? `<span class="slot-role attack" title="attacca">${ICON_LANCE}</span>`
    : `<span class="slot-role defend" title="difende">${ICON_SHIELD}</span>`;
}

function resetBoard() {
  clearLaneChoice();
  document.querySelectorAll('.slot').forEach(s => {
    s.innerHTML = slotRole(+s.dataset.lane, s.dataset.side);
    s._cardDetail = null;
  });
  document.querySelectorAll('.lane-col').forEach(el => el.classList.remove('win-player', 'win-ai', 'draw'));
  G.blindRevealed = false;
}

/* Mazzetto: conteggio + stato "vuoto". */
function renderDeck(n) {
  if (n != null) G.deckCount = n;
  const c = document.getElementById('deck-count');
  if (c) c.textContent = G.deckCount;
  const pile = document.getElementById('deck-pile');
  if (pile) pile.classList.toggle('empty', G.deckCount <= 0);
}

/* --- Scelta carta del giocatore --- */

function onPick(card) {
  window.SFX && SFX.pick();
  // Se avevi lasciato aperta la scelta abilità di un'altra carta (poi cambiata idea), chiudila.
  const kwBox = $('#boon-pick');
  if (kwBox) kwBox.hidden = true;
  // Se la carta ha 2+ abilità, prima scegli quale tenere; poi prosegui con la carta "ridotta".
  const go = (chosen) => {
    const c = chosen ? reduceCardClient(card, chosen) : card;
    if (chosen) c._kw = chosen;
    c._native = card; // carta ORIGINALE (tutte le abilità + PV base) per il popup
    if (G.selectableLane === 1) showChoice(c);
    else playLane(c, null);
  };
  if (cardKeywords(card).length >= 2) showKeywordChoice(card, go);
  else go(null);
}

function clearLaneChoice() { document.querySelectorAll('.lane-choice').forEach(e => e.remove()); }
function clearSlot(lane, side) {
  const slot = document.querySelector(`.slot[data-lane="${lane}"][data-side="${side}"]`);
  if (slot) { slot.innerHTML = slotRole(lane, side); slot._cardDetail = null; }
}

// I pulsanti para/subisci appaiono SOPRA la carta scelta (corsia 1, lato giocatore),
// così è chiaro che la scelta riguarda quella creatura.
function showChoice(card) {
  clearLaneChoice();
  placeCard(1, 'player', card, false, true, card._native || card); // schiera (tentativo) la carta sulla corsia
  const laneCol = document.querySelector('.side-player .lane-col[data-lane="1"]');
  const legalBlock = canBlockClient(G.lane1Attacker, card);
  setPrompt(legalBlock
    ? `${card.name} schierato. Scegli sopra la carta: PARA (scontro) o SUBISCI (in faccia).`
    : `${card.name} non può parare un volante: puoi solo SUBIRE.`);
  coachOnce('parasubisci', '<b>Para</b>: le creature si scontrano (può morire una o entrambe). <b>Subisci</b>: il colpo va al tuo mago ma la creatura resta in gioco.', 'pensa');

  const bar = document.createElement('div');
  bar.className = 'lane-choice';
  if (legalBlock) {
    const para = document.createElement('button');
    para.className = 'btn'; para.textContent = '🛡️ Para';
    para.onclick = () => { clearLaneChoice(); playLane(card, 'BLOCK'); };
    bar.appendChild(para);
  }
  const subisci = document.createElement('button');
  subisci.className = 'btn primary'; subisci.textContent = '⚔️ Subisci';
  subisci.onclick = () => { clearLaneChoice(); playLane(card, 'FACE'); };
  bar.appendChild(subisci);
  const annulla = document.createElement('button');
  annulla.className = 'btn ghost'; annulla.textContent = '↩︎ Cambia';
  annulla.onclick = () => { clearLaneChoice(); clearSlot(1, 'player'); setPrompt('Corsia 1: scegli una creatura e decidi.'); };
  bar.appendChild(annulla);

  laneCol.insertBefore(bar, laneCol.firstChild);
}

function showPass(){ const b = $('#pass-btn'); if (b) b.hidden = false; }
function hidePass(){ const b = $('#pass-btn'); if (b) b.hidden = true; }

// "Passa": non schieri nessuna carta su questa corsia e subisci l'eventuale attacco avversario.
function passLane() {
  const lane = G.selectableLane;
  if (lane === null) return;
  G.selectableLane = null;
  clearLaneChoice();
  hidePass();
  const kwBox = $('#boon-pick');
  if (kwBox) kwBox.hidden = true;
  clearSlot(lane, 'player');
  const action = lane === 0 ? 'LANE0_PLAYER' : (lane === 1 ? 'LANE1_PLAYER' : 'LANE2_BLIND');
  api(action, { pass: true }).then(res => {
    if (!res.ok) { setPrompt('⚠️ ' + res.error); G.selectableLane = lane; renderHand(); showPass(); return; }
    if (res.remaining_budget != null) setBudget(res.remaining_budget);
    if (lane === 0) afterLane0(res);
    else if (lane === 1) afterLane1(res);
    else afterLane2(res);
  });
}

function playLane(card, choice) {
  const lane = G.selectableLane;
  G.selectableLane = null;
  clearLaneChoice();
  hidePass();
  const action = lane === 0 ? 'LANE0_PLAYER' : (lane === 1 ? 'LANE1_PLAYER' : 'LANE2_BLIND');
  const payload = { card_id: card.uid || card.id }; // il server identifica la carta per uid (istanza)
  if (choice) payload.choice = choice;
  if (card._kw) payload.kw = card._kw; // abilità scelta (carte con 2+ keyword)

  api(action, payload).then(res => {
    if (!res.ok) { setPrompt('⚠️ ' + res.error); G.selectableLane = lane; renderHand(); return; }
    removeFromHand(card.uid || card.id);
    window.SFX && SFX.play();
    placeCard(lane, 'player', res.player_card || card, false, true, card._native || card);
    if (lane === 1) badge(1, 'player', res.choice === 'BLOCK' ? 'PARA' : 'SUBISCE', res.choice === 'BLOCK' ? 'b-block' : 'b-face');
    if (res.remaining_budget != null) setBudget(res.remaining_budget);
    if (lane === 0) afterLane0(res);
    else if (lane === 1) afterLane1(res);
    else afterLane2(res);
  });
}

/* --- Risoluzione CORSIA PER CORSIA (ogni corsia si conclude subito) --- */

// Corsia 0: tu attacchi → la CPU difende → la corsia si risolve subito.
async function afterLane0(res) {
  await cpuThink(1000, '🤖 L\'avversario valuta la tua carta…');
  if (res.ai_card) {
    placeCard(0, 'ai', res.ai_card, false, true);
    aiPlayed();
    badge(0, 'ai', res.ai_choice === 'BLOCK' ? 'PARA' : 'SUBISCE', res.ai_choice === 'BLOCK' ? 'b-block' : 'b-face');
  }
  await animateLane(res.resolution);
  if (res.state === 'DONE') { showFinal(res); return; }
  // La CPU attacca la corsia 1.
  await cpuThink(1000, '🤖 L\'avversario contrattacca sulla corsia 2…');
  const r = await api('LANE1_AI');
  if (!r.ok) { setPrompt('⚠️ ' + r.error); return; }
  placeCard(1, 'ai', r.ai_card, false, true);
  aiPlayed();
  badge(1, 'ai', 'ATTACCA', 'b-atk');
  advance(r);
}

// Corsia 1: tu difendi → la corsia si risolve subito.
async function afterLane1(res) {
  await animateLane(res.resolution);
  if (res.state === 'DONE') { showFinal(res); return; }
  advance(res); // -> LANE2_BLIND
}

// Corsia 2: alla cieca → si rivela e si risolve → fine round.
async function afterLane2(res) {
  await cpuThink(900, '🤖 L\'avversario scopre la carta alla cieca…');
  if (res.ai_card) { placeCard(2, 'ai', res.ai_card, false, true); aiPlayed(); }
  await animateLane(res.resolution);
  if (res.state === 'DONE') { showFinal(res); return; }
  showRoundEnd(res); // CONTINUE -> prossimo round
}

/* --- Setup delle azioni del giocatore --- */

function advance(res) {
  G.state = res.state;
  $('#next-btn').hidden = true;
  $('#choice-bar').hidden = true; clearLaneChoice();

  switch (res.state) {
    case 'LANE0_PLAYER':
      setPrompt(res.prompt || '');
      G.selectableLane = 0; renderHand();
      coachOnce('lane0', 'Corsia 0: <b>attacchi tu</b> per primo. Tocca una carta illuminata in mano per schierarla. La CPU reagirà dopo.', 'indica');
      break;
    case 'LANE1_PLAYER':
      G.lane1Attacker = res.ai_card;
      setPrompt(res.prompt || '');
      G.selectableLane = 1; renderHand();
      coachOnce('lane1', 'Corsia 1: la <b>CPU ti ha attaccato</b> (carta scoperta in alto). Scegli una tua creatura per rispondere.', 'indica');
      break;
    case 'LANE2_BLIND':
      placeCard(2, 'ai', null, true);
      setPrompt(res.prompt || '');
      G.selectableLane = 2; renderHand();
      coachOnce('lane2', 'Corsia 2: <b>alla cieca</b>. Schieri senza vedere la carta della CPU: si rivelano insieme. Puoi anche passare.', 'pensa');
      break;
  }
  if (G.selectableLane !== null) showPass(); else hidePass();
}

// Anima la conclusione di UNA corsia: stat ridotte dei sopravvissuti, danni, morte, vita.
function woundedCopy(card, dead, wound) {
  return Object.assign({}, card, { wounds: (+card.wounds || 0) + (dead ? 0 : (wound || 0)) });
}
async function animateLane(l) {
  if (!l) return;
  setPrompt(`Piazzola ${l.lane + 1}: ${laneStory(l)}`);
  const clash = !['FACE', 'FACE_FORCED', 'BLIND_PASS', 'PASS'].includes(l.mode);

  if (l.player) {
    placeCard(l.lane, 'player', woundedCopy(l.player, l.player_dead, l.player_wound));
    cardFx(l.lane, 'player', l.dmg_ai, clash, l.player_dead);   // danno della TUA creatura al mago CPU
    if (l.player_dead || (+l.player_wound || 0) > 0) bloodSlashCard(l.lane, 'player'); // ha subìto ferite
  } else {
    clearSlot(l.lane, 'player'); // hai passato: piazzola vuota
  }
  if (l.ai) {
    placeCard(l.lane, 'ai', woundedCopy(l.ai, l.ai_dead, l.ai_wound));
    cardFx(l.lane, 'ai', l.dmg_player, clash, l.ai_dead);       // danno della creatura CPU al tuo mago
    if (l.ai_dead || (+l.ai_wound || 0) > 0) bloodSlashCard(l.lane, 'ai');
  }

  G.life.player = l.player_life_after;
  G.life.ai     = l.ai_life_after;
  setLife();
  const mageHurt = (+l.dmg_player || 0) > 0 || (+l.dmg_ai || 0) > 0;
  if ((+l.dmg_player || 0) > 0) bloodSlash(document.querySelector('.hud.me .vit'));  // il TUO mago è stato colpito
  if ((+l.dmg_ai || 0) > 0)     bloodSlash(document.querySelector('.hud.foe .vit')); // il mago avversario è stato colpito

  if (window.SFX) {
    if (clash) SFX.clash();                              // scontro di creature (metallo)
    if (l.player_dead || l.ai_dead) SFX.wound();         // coltellata: una creatura muore
    if (mageHurt) SFX.mageHit();                         // colpo alla vita di un mago
  }

  const net = l.dmg_ai - l.dmg_player;
  tintLane(l.lane, net > 0 ? 'win-player' : (net < 0 ? 'win-ai' : 'draw'));
  await wait(1200);
}

function startNextRound(r) {
  resetBoard();
  applyBoons();
  G.hand = r.hand;
  G.dealNext = true;
  renderDeck(r.player_deck_count);
  G.aiHandCount = r.ai_hand_count;
  setBudget(r.remaining_budget);
  renderAiHand();
  advance({ state: 'LANE0_PLAYER', prompt: r.prompt });
}

function showRoundEnd(r) {
  hidePass();
  setRound(r.round);
  coachOnce('roundend', 'Round concluso! I superstiti tornano in <b>mano</b> (feriti), i morti escono. Si continua finché un mago va a <b>0</b>.', 'spiega');
  const fat = r.fatigue ? ` ⚡ Fatica −${r.fatigue} a entrambi i maghi.` : '';
  setPrompt(`Round ${r.round - 1} concluso — vita 🧙 Tu ${Math.max(0, r.player_life)} · 🧙 CPU ${Math.max(0, r.ai_life)}.${fat}`);
  const nextBtn = $('#next-btn');
  nextBtn.hidden = false;
  const pickPending = r.boon_pick && r.boon_pick.available && r.boon_pick.available.length;
  const fireOnce = (fn) => () => {
    if (nextBtn.disabled) return;
    nextBtn.disabled = true;
    nextBtn.hidden = true;
    fn();
  };
  if (pickPending) {
    nextBtn.textContent = `🎁 Round ${r.round}: scegli il dono`;
    nextBtn.onclick = fireOnce(() => openBoonPick(r));
  } else {
    nextBtn.textContent = `▶️ Inizia il Round ${r.round}`;
    nextBtn.onclick = fireOnce(() => startNextRound(r));
  }
  nextBtn.disabled = false;
}

/* --- Scelta del dono (ogni 3 round) --- */
function openBoonPick(r) {
  const box = $('#boon-pick');
  if (!box) { startNextRound(r); return; }
  const avail = r.boon_pick.available;
  box.innerHTML =
    `<div class="bp-title">Round ${r.round} — scegli un dono per le tue creature</div>
     <div class="bp-grid">` +
    avail.map(k => `<button class="bp-card" data-k="${k}">
        <span class="bp-ic">${BOON_ICON[k]}</span>
        <span class="bp-name">${BOON_NAME[k]}</span>
        <span class="bp-desc">${BOON_DESC[k]}</span>
      </button>`).join('') +
    `</div><div class="bp-note">La CPU ne prenderà uno diverso.</div>`;
  box.hidden = false;
  box.querySelectorAll('.bp-card').forEach(b => b.onclick = () => pickBoon(b.dataset.k, r));
}

function pickBoon(kw, r) {
  const box = $('#boon-pick');
  box.querySelectorAll('.bp-card').forEach(b => b.disabled = true);
  api('PICK_BOON', { keyword: kw }).then(res => {
    if (!res.ok) { setPrompt('⚠️ ' + res.error); box.querySelectorAll('.bp-card').forEach(b => b.disabled = false); return; }
    G.playerBoons = res.player_boons || [];
    G.aiBoons = res.ai_boons || [];
    const aiTxt = res.ai_pick ? `${BOON_ICON[res.ai_pick]} ${BOON_NAME[res.ai_pick]}` : '—';
    box.innerHTML =
      `<div class="bp-title">Doni assegnati</div>
       <div class="bp-reveal">
         <div><span class="bp-side">Tu</span> ${BOON_ICON[kw]} ${BOON_NAME[kw]}</div>
         <div><span class="bp-side foe">CPU</span> ${aiTxt}</div>
       </div>
       <button class="btn primary" id="bp-go">▶️ Inizia il Round ${r.round}</button>`;
    $('#bp-go').onclick = () => { box.hidden = true; startNextRound(r); };
  });
}

/* --- Esito finale --- */

// Storico dei match della "partita" (run) in localStorage: lo legge /end.php per il riepilogo
// condivisibile. Voce = { outcome, round, vita finali, modalità, etichetta, timestamp }.
const RUNLOG_KEY = 'varco_runlog';
function logMatch(res, label) {
  try {
    const log = JSON.parse(localStorage.getItem(RUNLOG_KEY) || '[]');
    log.push({
      outcome: res.outcome,
      round: res.round,
      player_life: Math.max(0, res.player_life),
      ai_life: Math.max(0, res.ai_life),
      reason: res.reason || '',
      mode: CAMPAIGN ? 'campaign' : 'sfida',
      label: label || (CAMPAIGN ? 'Campagna' : 'Sfida rapida'),
      ts: Date.now(),
    });
    localStorage.setItem(RUNLOG_KEY, JSON.stringify(log.slice(-40))); // tieni gli ultimi 40
  } catch (e) { /* localStorage non disponibile: il riepilogo semplicemente non si aggiorna */ }
}

function showFinal(res) {
  hidePass();
  const box = $('#result');
  box.hidden = false;
  let title, cls;
  if (res.outcome === 'PLAYER') { title = '🏆 Vittoria!'; cls = 'win'; window.SFX && SFX.win(); }
  else if (res.outcome === 'AI') { title = '💀 Sconfitta'; cls = 'lose'; window.SFX && SFX.lose(); }
  else { title = '🤝 Pareggio'; cls = 'draw'; }

  const reasons = {
    ko: 'KO sul campo',
    deckout_player: 'sei rimasto senza creature (deck-out)',
    deckout_ai: 'la CPU è rimasta senza creature (deck-out)',
    fatigue: 'fatica: i maghi hanno ceduto (nessuno chiudeva)',
  };
  const why = reasons[res.reason] || '';

  const scoreHtml = `
    <p class="result-score">
      Round giocati: <strong>${res.round}</strong> · ${escapeHtml(why)}<br>
      Vita finale — 🧙 Tu <strong>${Math.max(0, res.player_life)}</strong> · 🧙 CPU <strong>${Math.max(0, res.ai_life)}</strong>
    </p>`;

  if (CAMPAIGN) {
    box.innerHTML = `<div class="result-banner ${cls}">${title}</div>${scoreHtml}
      <div class="result-actions"><span class="hint">Aggiorno la valle…</span></div>`;
    setPrompt('Battaglia conclusa.');
    box.scrollIntoView({ behavior: 'smooth' });
    fetch('/api/campaign.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'FINISH', outcome: res.outcome, round: res.round }),
    }).then(r => r.json()).then(cv => {
      const cur = (cv && cv.current) || {};
      logMatch(res, cur.level_name ? `Valle ${cur.valley || ''} · ${cur.level_name}` : 'Campagna');
      const won = res.outcome === 'PLAYER';
      const actions = won
        ? `<a class="btn primary" href="/campaign.php">🎁 Bottino — pesca una carta</a>
           <a class="btn" href="/end.php">📜 Riepilogo</a>`
        : `<button class="btn primary" onclick="location.reload()">↻ Riprova la battaglia</button>
           <a class="btn" href="/campaign.php">🏔 Torna alla valle</a>
           <a class="btn" href="/end.php">📜 Riepilogo</a>`;
      box.querySelector('.result-actions').innerHTML = actions;
    }).catch(() => {
      logMatch(res, 'Campagna');
      box.querySelector('.result-actions').innerHTML =
        `<a class="btn primary" href="/campaign.php">🏔 Torna alla valle</a>
         <a class="btn" href="/end.php">📜 Riepilogo</a>`;
    });
    return;
  }

  logMatch(res, TUTORIAL ? 'Tutorial' : 'Sfida rapida');

  box.innerHTML = `
    <div class="result-banner ${cls}">${title}</div>${scoreHtml}
    <div class="result-actions">
      <button class="btn primary" onclick="location.reload()">↻ Rivincita</button>
      <a class="btn" href="/end.php">📜 Riepilogo</a>
      <a class="btn" href="/index.php">← Home</a>
    </div>`;
  setPrompt('Battaglia conclusa.');
  box.scrollIntoView({ behavior: 'smooth' });
}

// ─── Screenshot 9:16 ────────────────────────────────────────────────────────

(function initScreenshot() {
  const btn   = document.getElementById('screenshot-btn');
  const toast = document.getElementById('screenshot-toast');
  if (!btn) return;

  function showToast(msg, ms = 3000) {
    toast.textContent = msg;
    toast.hidden = false;
    clearTimeout(toast._t);
    toast._t = setTimeout(() => { toast.hidden = true; }, ms);
  }

  btn.addEventListener('click', async () => {
    if (!window.html2canvas) {
      showToast('⏳ Carico il renderer…');
      await new Promise((res, rej) => {
        const s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
        s.onload = res; s.onerror = rej;
        document.head.appendChild(s);
      });
    }

    btn.classList.add('loading');
    btn.textContent = '⏳ Cattura…';
    showToast('📷 Cattura in corso…', 8000);

    try {
      // Cattura il solo .board (il campo da gioco) per evitare UI sopra/sotto
      const target = document.querySelector('.battle') || document.body;
      const raw    = await html2canvas(target, {
        backgroundColor: '#080510',
        useCORS: true,
        allowTaint: false,
        scale: 1,
        logging: false,
      });

      // Ritaglia / letterbox a 9:16
      const W = 1080, H = 1920;
      const out = document.createElement('canvas');
      out.width = W; out.height = H;
      const ctx = out.getContext('2d');
      ctx.fillStyle = '#080510';
      ctx.fillRect(0, 0, W, H);

      const rw = raw.width, rh = raw.height;
      const scale = Math.max(W / rw, H / rh);
      const dw = rw * scale, dh = rh * scale;
      ctx.drawImage(raw, (W - dw) / 2, (H - dh) / 2, dw, dh);

      // Upload come blob
      const blob = await new Promise(r => out.toBlob(r, 'image/jpeg', 0.88));
      const fd   = new FormData();
      fd.append('shot', blob, 'screenshot.jpg');
      fd.append('round', String(G.round));
      fd.append('deck_id', String(deckId));

      const resp = await fetch('/screenshot_upload.php', { method: 'POST', body: fd });
      const json = await resp.json();

      if (json.ok) {
        showToast('✅ Screenshot inviato! Grazie 🙏', 4000);
      } else {
        showToast('⚠️ ' + (json.error || 'Errore upload'), 4000);
      }
    } catch (e) {
      console.error('[screenshot]', e);
      showToast('❌ Cattura fallita — riprova', 3000);
    } finally {
      btn.classList.remove('loading');
      btn.textContent = '📷 Screenshot';
    }
  });
})();

function laneStory(l) {
  if (l.mode === 'BLOCK') return 'parata: le creature si scontrano';
  if (l.mode === 'BLOCK_TRAMPLE') return 'parata, ma l\'eccesso passa in faccia';
  if (l.mode === 'BLIND_CLASH') return 'stesso volo: scontro, l\'eccesso va in faccia';
  if (l.mode === 'BLIND_PASS')  return 'volo asimmetrico: si superano, colpi diretti in faccia';
  if (l.mode === 'PASS')        return 'hai passato: subisci il colpo avversario';
  if (l.mode === 'FACE_FORCED') return 'volante: colpo in faccia inevitabile';
  return 'colpi in faccia';
}

/** Effetti di risoluzione: mostra il danno che la creatura ha inflitto al MAGO avversario + morte. */
function cardFx(lane, side, mageDmg, clash, dead) {
  const el = document.querySelector(`.slot[data-lane="${lane}"][data-side="${side}"] :is(.card,.play-card)`);
  if (!el) return;
  el.classList.add(clash ? 'clashing' : 'attacking');
  if (mageDmg > 0) {
    const n = document.createElement('div');
    n.className = 'dmg-big to-mage';
    const arrow = side === 'player' ? '↑' : '↓';
    n.innerHTML = `<span class="dmg-arrow">${arrow}</span>${mageDmg}`;
    el.appendChild(n);
  }
  if (dead) el.classList.add('dead');
}
function tintLane(lane, cls) {
  document.querySelectorAll(`.lane-col[data-lane="${lane}"]`).forEach(el => {
    el.classList.remove('win-player', 'win-ai', 'draw');
    el.classList.add(cls);
  });
}

/* --- Preload: prima di mostrare il tavolo, carica in cache le immagini che serviranno subito
   (sfondi + tutte le carte del mazzo del giocatore), con una barra 0→100%. Niente più "pop-in"
   delle carte a scatti quando la mano viene disegnata. --- */
const STATIC_PRELOAD = ['/assets/bg/arena.png', '/assets/bg/void.png'];
if (TUTORIAL) STATIC_PRELOAD.push('/assets/tutor/mago-spiega.png', '/assets/tutor/mago-indica.png', '/assets/tutor/mago-pensa.png');

function preloadImages(urls, onProgress) {
  const unique = [...new Set(urls.filter(Boolean))];
  const total = unique.length;
  if (!total) { onProgress(1, 1); return Promise.resolve(); }
  let done = 0;
  const loaders = unique.map(src => new Promise(resolve => {
    let settled = false;
    const finish = () => { if (settled) return; settled = true; done++; onProgress(done, total); resolve(); };
    const img = new Image();
    img.onload = finish;
    img.onerror = finish;
    img.src = src;
    setTimeout(finish, 6000); // un'immagine lenta/rotta non deve bloccare l'inizio del match
  }));
  return Promise.all(loaders);
}

function setPreloadPct(pct) {
  const fill = document.getElementById('preload-fill');
  const pctEl = document.getElementById('preload-pct');
  if (fill) fill.style.width = pct + '%';
  if (pctEl) pctEl.textContent = pct + '%';
}

function hidePreload() {
  const ov = document.getElementById('preload-overlay');
  if (ov) { ov.classList.add('done'); setTimeout(() => { ov.hidden = true; }, 400); }
}

/* --- Avvio --- */

function start() {
  const pb = $('#pass-btn'); if (pb) pb.onclick = passLane;
  const payload = CAMPAIGN ? { from_campaign: true }
                : deckId ? { deck_id: deckId }
                : startColor ? { color: startColor }
                : TUTORIAL ? { color: 'R' } // tutorial: mazzo rosso costruito al volo
                : null;
  if (!payload) { setPrompt('Nessun mazzo o colore selezionato. Torna alla home.'); hidePreload(); return; }
  api('START', payload).then(res => {
    if (!res.ok) { setPrompt('⚠️ ' + res.error); hidePreload(); return; }

    const ov = document.getElementById('preload-overlay');
    if (ov) { ov.hidden = false; ov.classList.remove('done'); }
    const urls = STATIC_PRELOAD.concat(res.preload_images || []);
    setPreloadPct(0);
    preloadImages(urls, (done, total) => setPreloadPct(Math.round(done / total * 100))).then(() => {
      hidePreload();
      G.hand = res.hand;
      G.dealNext = true;
      renderDeck(res.player_deck_count ?? 0);
      setBudget(res.remaining_budget);
      G.life = { player: res.player_life ?? res.mage_life, ai: res.ai_life ?? res.mage_life };
      setLife();
      setRound(res.round || 1);
      G.playerBoons = res.player_boons || [];
      G.aiBoons = res.ai_boons || [];
      applyBoons();
      G.aiHandCount = res.ai_hand_count || 6;
      renderAiHand();
      advance(res);
    });
  });
}

// ─── Tutorial: intro a slide + coach contestuale (solo con ?tutorial=1) ──────
const TUT_SLIDES = [
  { t: '⟡ Benvenuto nel Varco',
    h: 'Sei un <b>arcano</b>: schieri creature su <b>3 corsie</b> per azzerare la vita ❤️ del mago avversario. ' +
       'La partita dura <b>più round</b> — chi arriva a 0 per primo perde.' },
  { t: '🃏 Mano e mana',
    h: 'A ogni round peschi una <b>mano</b> e hai un budget di <b>mana</b> (in basso a destra). ' +
       'Il numero sull’angolo di ogni carta è il suo <b>costo</b>; le carte che puoi permetterti si illuminano.' },
  { t: '⚔️ Le 3 corsie e i ruoli',
    h: '<b>Corsia 0</b>: attacchi <b>tu</b> per primo (scoperto), poi la CPU reagisce.<br>' +
       '<b>Corsia 1</b>: attacca <b>la CPU</b> per prima, poi reagisci tu.<br>' +
       '<b>Corsia 2</b>: <b>alla cieca</b>, rivelazione simultanea.<br>' +
       'Le icone 🗡️ lancia / 🛡️ scudo nelle piazzole mostrano chi attacca e chi difende.' },
  { t: '🛡️ Para o Subisci',
    h: 'Quando <b>difendi</b>, scegli:<br>• <b>Para</b> — la tua creatura si scontra con quella nemica.<br>' +
       '• <b>Subisci</b> — lasci passare il colpo: il danno va in faccia al tuo mago, ma tieni la creatura per altro.<br>' +
       'Leggi l’avversario e bluffa.' },
  { t: '🔁 Round multipli e doni',
    h: 'I superstiti tornano in <b>mano</b> (feriti), i morti escono. Si continua finché un mago va a <b>0</b>. ' +
       'Dal <b>3° round</b> arrivano i <b>doni</b> (keyword come Volare ✈️, Travolgere 🐗). Pronto? Si comincia!' },
];

function showTutIntro() {
  const box = document.getElementById('tut-intro');
  if (!box) { start(); return; }
  let i = 0;
  const draw = () => {
    const s = TUT_SLIDES[i];
    const last = i === TUT_SLIDES.length - 1;
    box.innerHTML = `
      <div class="tut-card">
        <div class="tut-dots">${TUT_SLIDES.map((_, k) => `<span class="${k === i ? 'on' : ''}"></span>`).join('')}</div>
        <h2 class="tut-title">${s.t}</h2>
        <p class="tut-body">${s.h}</p>
        <div class="tut-nav">
          ${i > 0 ? '<button class="btn ghost" id="tut-prev">← Indietro</button>' : '<span></span>'}
          <button class="btn primary" id="tut-next">${last ? '🎓 Inizia la partita' : 'Avanti →'}</button>
        </div>
        <button class="tut-skip" id="tut-skip">salta introduzione</button>
      </div>`;
    const prev = document.getElementById('tut-prev');
    if (prev) prev.onclick = () => { i--; draw(); };
    document.getElementById('tut-next').onclick = () => {
      if (last) { box.hidden = true; start(); } else { i++; draw(); }
    };
    document.getElementById('tut-skip').onclick = () => { box.hidden = true; start(); };
  };
  box.hidden = false;
  draw();
}

// Coach: il MAGO NARRATORE con un fumetto, mostrato UNA volta per chiave ai momenti-chiave.
// Le 3 pose sono PNG trasparenti in /assets/tutor/. Se un'immagine manca, l'<img> si nasconde
// (onerror) e resta solo il fumetto: il tutorial funziona comunque.
const tutSeen = new Set();
const POSE_IMG = {
  spiega: '/assets/tutor/mago-spiega.png', // libro aperto, accogliente
  indica: '/assets/tutor/mago-indica.png', // indica col bastone
  pensa:  '/assets/tutor/mago-pensa.png',  // mano sulla barba, pensieroso
};
function coach(msg, pose) {
  const box = document.getElementById('coach');
  if (!box) return;
  const img = POSE_IMG[pose] || POSE_IMG.spiega;
  box.innerHTML = `<img class="coach-mago" src="${img}" alt="Mago narratore"
       onerror="this.style.display='none'">
    <div class="coach-bubble">
      <span class="coach-msg">${msg}</span>
      <button class="coach-x" aria-label="ho capito">✓</button>
    </div>`;
  box.hidden = false;
  box.querySelector('.coach-x').onclick = () => { box.hidden = true; };
}
function coachOnce(key, msg, pose) {
  if (!TUTORIAL || tutSeen.has(key)) return;
  tutSeen.add(key);
  coach(msg, pose);
}

if (TUTORIAL) showTutIntro(); else start();
