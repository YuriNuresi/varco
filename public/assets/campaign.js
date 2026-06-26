/* Campagna "Varco" 2.0 — draft pre-valle → HUB delle 5 valli → roaming libero fra villaggi tematici.
   Stesso mazzo ovunque; ogni livello sbloccato è giocabile in qualsiasi ordine (rigioco dei vinti). */
'use strict';

const $ = sel => document.querySelector(sel);
const root = $('#camp-root');
const cfg = {
  draftOptions: parseInt($('.campaign').dataset.draftOptions, 10) || 6,
  draftPick:    parseInt($('.campaign').dataset.draftPick, 10) || 2,
  rewardPick:   parseInt($('.campaign').dataset.rewardPick, 10) || 1,
};

const COLORS = [
  { c: 'W', name: 'Bianco' }, { c: 'U', name: 'Blu' }, { c: 'B', name: 'Nero' },
  { c: 'R', name: 'Rosso' },  { c: 'G', name: 'Verde' },
];
const COLOR_NAME = { W: 'Bianco', U: 'Blu', B: 'Nero', R: 'Rosso', G: 'Verde' };

function esc(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function kwIcons(c){let s='';if(+c.flying)s+='<span title="Volare">✈️</span>';if(+c.first_strike)s+='<span title="Attacco improvviso">⚡</span>';if(+c.deathtouch)s+='<span title="Tocco letale">☠️</span>';if(+c.trample)s+='<span title="Travolgere">🐗</span>';if(+c.double_strike)s+='<span title="Doppio attacco">⚔️</span>';if(+c.lifelink)s+='<span title="Legame vitale">💖</span>';if(+c.reach)s+='<span title="Raggiungere">🏹</span>';if(+c.defender)s+='<span title="Difensore">🧱</span>';return s;}
function cardImg(c){return c&&c.image_url?`<img class="pc-img" loading="lazy" src="${c.image_url}" alt="${esc(c.name)}" onerror="this.parentNode.classList.add('noimg');this.remove();">`:'';}

function coverHtml(c){
  const k = kwIcons(c);
  return `<div class="pc-cover">${k ? `<span class="kw">${k}</span>` : '<span class="vanilla">✦</span>'}</div>`;
}
function cardMarkup(c, extra=''){
  return `<div class="play-card c-${c.colors||'C'} ${extra}">
    ${cardImg(c)}
    ${coverHtml(c)}
    <div class="pc-fallback">
      <div class="pc-top"><span class="pc-name">${esc(c.name)}</span><span class="pc-cost">${c.mana_value}</span></div>
      <div class="pc-pt">${c.power}/${c.toughness}</div>
      <div class="pc-kw">${kwIcons(c)}</div>
    </div>
  </div>`;
}

function api(action, body){
  return fetch('/api/campaign.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(Object.assign({ action }, body || {})),
  }).then(r => r.json());
}

function render(v){
  if (!v || !v.ok)                  return renderError(v && v.error);
  if (v.phase === 'none')           return renderColorPick();
  if (v.phase === 'drafting')       return renderDraft(v);
  if (v.phase === 'pending_reward') return renderReward(v);
  if (v.phase === 'fighting')       return renderFighting(v);
  return renderHub(v); // 'hub'
}

function renderError(msg){
  root.innerHTML = `<div class="camp-head"><h1>Ahia</h1>
    <p class="camp-sub">${esc(msg || 'Errore imprevisto.')}</p></div>
    <div class="camp-bar"><a class="btn" href="/index.php">← Home</a></div>`;
}

function renderColorPick(){
  root.innerHTML = `
    <div class="camp-head">
      <h1>I cinque valichi</h1>
      <p class="camp-sub">Scegli un valico: di lì nasce il tuo mazzo. Poi avrai davanti tutte e cinque le valli.</p>
    </div>
    <div class="color-grid">
      ${COLORS.map(x => `<div class="valico" data-c="${x.c}">
        <span class="deck-color c-${x.c}">${x.c}</span>
        <span class="vname">${x.name}</span>
      </div>`).join('')}
    </div>`;
  root.querySelectorAll('.valico').forEach(el => {
    el.onclick = () => {
      el.style.pointerEvents = 'none';
      api('NEW', { color: el.dataset.c }).then(render);
    };
  });
}

function renderDraft(v){
  const d = v.draft;
  const sel = new Set();
  root.innerHTML = `
    <div class="camp-head">
      <h1>Draft del mazzo</h1>
      <p class="camp-sub">Round <b>${d.round}/${d.rounds}</b> — carte attorno a <b>${d.anchor} mana</b>.
         Scegli <b>${d.pick}</b> carte su ${cfg.draftOptions}.</p>
      <div class="camp-meta"><span>Valico <b>${v.color}</b></span><span>Mazzo finora <b>${v.deck_size}</b></span></div>
    </div>
    <div class="card-grid" id="draft-grid">
      ${d.options.map((c, i) => `<div class="pick-card" data-i="${i}">
        <span class="rar r-${c.rarity}">${c.rarity}</span>${cardMarkup(c)}
      </div>`).join('')}
    </div>
    <div class="camp-bar">
      <button class="btn primary" id="confirm" disabled>Conferma (0/${d.pick})</button>
      <button class="btn ghost" id="abandon">Abbandona</button>
    </div>`;

  const confirm = $('#confirm');
  const sync = () => {
    confirm.textContent = `Conferma (${sel.size}/${d.pick})`;
    confirm.disabled = sel.size !== d.pick;
  };
  root.querySelectorAll('.pick-card').forEach(el => {
    el.onclick = () => {
      const i = +el.dataset.i;
      if (sel.has(i)) { sel.delete(i); el.classList.remove('sel'); }
      else if (sel.size < d.pick) { sel.add(i); el.classList.add('sel'); }
      sync();
    };
  });
  confirm.onclick = () => {
    confirm.disabled = true;
    const ids = [...sel].map(i => d.options[i].id);
    api('PICK', { ids }).then(render);
  };
  $('#abandon').onclick = abandon;
}

function curveHtml(deck){
  const buckets = [0,0,0,0,0,0,0]; // indice = mana 0..6+ (6 = "6 o più")
  deck.forEach(c => { const m = Math.min(6, Math.max(0, +c.mana_value)); buckets[m]++; });
  const max = Math.max(1, ...buckets);
  const labels = ['0','1','2','3','4','5','6+'];
  return `<div class="curve">${buckets.map((n,i) =>
    `<div class="bar" style="height:${Math.round(n/max*100)}%"><b>${n||''}</b><span>${labels[i]}</span></div>`
  ).join('')}</div>`;
}

/* --- HUB SU MAPPA: nodi-livello posizionati lungo le 5 valli di /assets/bg/campaign.jpg ---
   Coordinate in % (left,top) dell'immagine 1536x1024, una per livello (step 1..N).
   Crocevia centrale ~ (49%,51%). >>> Queste sono affinate a mano: modificare qui i punti. <<< */
const VALLEY_NODES = {
  R: [[58,61],[63,67],[60,75],[63,88],[75,78],[84,70]],   // lava, basso-destra
  W: [[41,46],[29,38],[22,27],[9,24],[14,17],[16,8]],     // pianure dorate, alto-sinistra
  G: [[50,42],[48,32],[46,23],[53,17],[48,10],[42,5]],    // foresta, in alto
  U: [[59,47],[63,40],[70,34],[80,40],[84,26],[77,16]],   // città glaciale, alto-destra
  B: [[43,63],[39,66],[29,61],[30,67],[26,78],[15,73]],   // palude scura, basso-sinistra
};

/* Spicchio (clip-path) di ogni valle sulla mappa: quando una valle è COMPLETATA si mostra lo sfondo
   "devastato" (campaign_victory.jpg) solo dentro questo poligono. I poligoni partono dal crocevia
   (49% 51%) e si aprono fino ai bordi lungo le creste montuose. Affinabili a vista come i nodi. */
const VALLEY_CLIP = {
  R: 'polygon(49% 51%, 100% 62%, 100% 100%, 49% 100%)',
  B: 'polygon(49% 51%, 49% 100%, 0% 100%, 0% 60%)',
  W: 'polygon(49% 51%, 0% 60%, 0% 0%, 22% 0%)',
  G: 'polygon(49% 51%, 22% 0%, 78% 0%)',
  U: 'polygon(49% 51%, 78% 0%, 100% 0%, 100% 62%)',
};

function mapNode(valley, s){
  const pos = VALLEY_NODES[valley] && VALLEY_NODES[valley][s.step - 1];
  if (!pos) return '';
  const mark = s.status === 'done' ? '✓' : (s.boss ? '👑' : s.step);
  return `<button class="map-node v-${valley} ${s.status}${s.boss?' boss':''}" ${s.playable ? '' : 'disabled'}
     style="left:${pos[0]}%;top:${pos[1]}%" data-valley="${valley}" data-step="${s.step}"
     title="${esc(s.village_name)} — ${esc(s.level_name)}${s.boss?' (BOSS)':''} · tribù ${esc(s.tribe||'—')}">
     <span class="mn-dot">${mark}</span>
     <span class="mn-cap">${esc(s.level_name)}</span>
   </button>`;
}

/* --- HUB classico (pannello per valle): usato per le valli non ancora sulla mappa --- */
function stepNode(valley, s){
  const mark = s.status === 'done' ? '✓' : (s.boss ? '👑' : s.step);
  return `<button class="hub-step ${s.status}${s.boss?' boss':''}" ${s.playable ? '' : 'disabled'}
     data-valley="${valley}" data-step="${s.step}"
     title="${esc(s.village_name)} — ${esc(s.level_name)}${s.boss?' (BOSS)':''} · tribù ${esc(s.tribe||'—')}">
     <span class="hs-dot">${mark}</span>
     <span class="hs-name">${esc(s.level_name)}</span>
   </button>`;
}

function valleyHtml(V){
  // raggruppa i passi per villaggio (in ordine)
  const villages = [];
  V.steps.forEach(s => {
    let g = villages[s.village_index];
    if (!g) g = villages[s.village_index] = { name: s.village_name, tribe: s.tribe, steps: [] };
    g.steps.push(s);
  });
  const list = villages.filter(Boolean);
  const villHtml = list.map((g, gi) => `
    <div class="hub-village">
      <div class="hv-head">${esc(g.name)}${g.tribe ? ` <span class="hv-tribe">${esc(g.tribe)}</span>` : ''}</div>
      <div class="hv-track">${g.steps.map(s => stepNode(V.valley, s)).join('')}</div>
    </div>
    ${gi < list.length - 1 ? '<div class="hub-inn" title="Locanda: cura e scambio carta">🏨</div>' : ''}`).join('');

  return `<div class="hub-valley c-bd-${V.valley} ${V.cleared ? 'cleared' : ''}">
    <div class="hv-title">
      <span class="deck-color c-${V.valley}">${V.valley}</span>
      <span class="hv-vname">Valle ${COLOR_NAME[V.valley] || V.valley}</span>
      <span class="hv-prog">${V.done}/${V.total}${V.cleared ? ' · superata 🏔' : ''}</span>
    </div>
    <div class="hub-villages">${villHtml}</div>
  </div>`;
}

function renderHub(v){
  const deck = (v.deck || []).slice().sort((a,b) => (a.mana_value-b.mana_value) || a.name.localeCompare(b.name));
  const valleys  = v.valleys || [];
  const mapped   = valleys.filter(V => VALLEY_NODES[V.valley]);
  const unmapped = valleys.filter(V => !VALLEY_NODES[V.valley]);
  const nodesHtml = mapped.map(V => V.steps.map(s => mapNode(V.valley, s)).join('')).join('');
  // Valli COMPLETATE: sfondo devastato ritagliato sul loro spicchio (le altre restano normali).
  // Anteprima per tarare i poligoni senza finire una valle: /campaign.php?ruin=all  oppure  ?ruin=R,B
  const rp = new URLSearchParams(location.search).get('ruin') || '';
  const preview = rp.toLowerCase() === 'all'
    ? new Set(valleys.map(V => V.valley))
    : new Set(rp.split(',').map(s => s.trim().toUpperCase()).filter(Boolean));
  const ruinHtml = valleys.filter(V => (V.cleared || preview.has(V.valley)) && VALLEY_CLIP[V.valley]).map(V =>
    `<div class="hub-map-ruin" style="clip-path:${VALLEY_CLIP[V.valley]};-webkit-clip-path:${VALLEY_CLIP[V.valley]}"
       title="Valle ${COLOR_NAME[V.valley] || V.valley} — devastata"></div>`).join('');

  root.innerHTML = `
    <div class="camp-head">
      <h1>Le cinque valli</h1>
      <p class="camp-sub">Lo stesso mazzo ovunque. Affronta qualunque livello <b>sbloccato</b>, in qualsiasi valle e ordine — puoi rigiocare i vinti.</p>
      <div class="camp-meta"><span>Mazzo <b>${v.color}</b></span><span><b>${v.deck_size}</b> carte</span></div>
    </div>
    <div class="hub-map">
      <img src="/assets/bg/campaign.jpg" alt="Mappa delle cinque valli">
      ${ruinHtml}
      ${nodesHtml}
    </div>
    ${unmapped.length ? `<p class="hint hub-todo">Valli non ancora posizionate sulla mappa — giocabili qui sotto:</p>
      <div class="hub-grid">${unmapped.map(valleyHtml).join('')}</div>` : ''}
    ${curveHtml(deck)}
    <div class="camp-bar"><button class="btn ghost" id="abandon">Abbandona la run</button></div>
    <details class="hub-deck"><summary>Il tuo mazzo (${v.deck_size})</summary>
      <div class="deck-strip">${deck.map(c => cardMarkup(c)).join('')}</div></details>`;

  root.querySelectorAll('.map-node:not([disabled]), .hub-step:not([disabled])').forEach(el => {
    el.onclick = () => {
      el.disabled = true;
      api('ENTER', { valley: el.dataset.valley, step: +el.dataset.step })
        .then(r => { if (r && r.ok) { location.href = '/game.php?campaign=1'; } else { renderError(r && r.error); } });
    };
  });
  $('#abandon').onclick = abandon;
}

function renderReward(v){
  const r = v.reward;
  const cur = v.current || {};
  const sel = new Set();
  root.innerHTML = `
    <div class="camp-head">
      <h1>🎁 Bottino</h1>
      <p class="camp-sub">Hai superato <b>${esc(cur.village_name || '')}</b> — ${esc(cur.level_name || '')}${cur.boss ? ' 👑' : ''}.
         Scegli <b>${r.pick}</b> carta da aggiungere al mazzo.</p>
      <div class="camp-meta"><span>Mazzo <b>${v.color}</b></span><span><b>${v.deck_size}</b> carte</span></div>
    </div>
    <div class="card-grid" id="reward-grid">
      ${r.options.map((c, i) => `<div class="pick-card" data-i="${i}">
        <span class="rar r-${c.rarity}">${c.rarity}</span>${cardMarkup(c)}
      </div>`).join('')}
    </div>
    <div class="camp-bar"><button class="btn primary" id="take" disabled>Prendi la carta</button></div>`;

  const take = $('#take');
  root.querySelectorAll('.pick-card').forEach(el => {
    el.onclick = () => {
      root.querySelectorAll('.pick-card').forEach(o => o.classList.remove('sel'));
      el.classList.add('sel');
      sel.clear(); sel.add(+el.dataset.i);
      take.disabled = false;
    };
  });
  take.onclick = () => {
    take.disabled = true;
    const id = r.options[[...sel][0]].id;
    api('REWARD', { id }).then(render);
  };
}

function renderFighting(v){
  const cur = v.current || {};
  root.innerHTML = `
    <div class="camp-head">
      <h1>Battaglia in corso</h1>
      <p class="camp-sub">Sei nel mezzo di uno scontro: <b>${esc(cur.village_name || '')}</b> — ${esc(cur.level_name || '')}${cur.boss ? ' 👑' : ''} (valle <b>${cur.valley || ''}</b>).</p>
    </div>
    <div class="camp-bar">
      <a class="btn primary" href="/game.php?campaign=1">↩ Torna alla battaglia</a>
      <button class="btn ghost" id="abandon">Abbandona la run</button>
    </div>`;
  $('#abandon').onclick = abandon;
}

function abandon(){
  if (!confirm('Abbandonare la campagna? Perdi il mazzo e i progressi.')) return;
  api('RESET').then(render);
}

api('STATE').then(render).catch(() => renderError('Connessione fallita.'));
