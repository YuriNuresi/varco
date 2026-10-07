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

function cardMarkup(c, extra=''){
  const kw = kwIcons(c);
  return `<div class="play-card c-${c.colors||'C'} ${extra}">
    ${cardImg(c)}
    <span class="pc-name-overlay">${esc(c.name)}</span>
    <span class="pc-cost-overlay">${c.mana_value}</span>
    ${kw ? `<span class="pc-kw-overlay">${kw}</span>` : ''}
    <span class="pc-pt-overlay">${c.power}/${c.toughness}</span>
    <div class="pc-fallback">
      <div class="pc-top"><span class="pc-name">${esc(c.name)}</span><span class="pc-cost">${c.mana_value}</span></div>
      <div class="pc-pt">${c.power}/${c.toughness}</div>
      <div class="pc-kw">${kwIcons(c)}</div>
    </div>
  </div>`;
}

/* Carta in formato "standard" (stesso layout della Fucina/card-editor):
   header costo+nome | immagine | footer gemma+sottotipo+rarità | stats keyword+P/T. */
const RARITY_LABEL = { common: 'Comune', uncommon: 'Non-comune', rare: 'Rara', mythic: 'Mitica' };

function stdCardMarkup(c){
  const col = c.colors || 'C';
  const noimg = c.image_url ? '' : ' noimg';
  const img = c.image_url
    ? `<img loading="lazy" src="${c.image_url}" alt="${esc(c.name)}" onerror="this.parentNode.classList.add('noimg');this.remove();">`
    : '';
  const kw = kwIcons(c);
  return `<div class="card c-${col}${noimg}">
    <div class="card-header c-${col}">
      <div class="card-cost">${c.mana_value}</div>
      <div class="card-name">${esc(c.name)}</div>
    </div>
    <div class="card-image${noimg}">${img}</div>
    <div class="card-footer c-${col}">
      <div class="card-subtype"><span class="gem r-${c.rarity || 'common'}"></span> ${esc(c.subtypes || '–')}</div>
      <div class="card-rarity">${RARITY_LABEL[c.rarity] || 'Comune'}</div>
    </div>
    <div class="card-stats">
      <div class="card-keywords">${kw || '–'}</div>
      <div class="card-pt">${c.power}/${c.toughness}</div>
    </div>
  </div>`;
}

/* --- Preload immagini: prima di mostrare una griglia di carte nuove (draft/bottino), le carica
   in cache con una barra 0→100%, così non "pop-ano" a scatti su connessioni lente. --- */
function preloadImages(urls, onProgress){
  const unique = [...new Set((urls || []).filter(Boolean))];
  const total = unique.length;
  if (!total) { onProgress(1, 1); return Promise.resolve(); }
  let done = 0;
  const loaders = unique.map(src => new Promise(resolve => {
    let settled = false;
    const finish = () => { if (settled) return; settled = true; done++; onProgress(done, total); resolve(); };
    const img = new Image();
    img.onload = finish; img.onerror = finish; img.src = src;
    setTimeout(finish, 6000); // un'immagine lenta/rotta non deve bloccare la schermata
  }));
  return Promise.all(loaders);
}
function campPreloadShow(){
  const ov = document.getElementById('camp-preload');
  if (!ov) return;
  ov.hidden = false; ov.classList.remove('done');
  campPreloadPct(0);
}
function campPreloadPct(pct){
  const fill = document.getElementById('camp-preload-fill');
  const pctEl = document.getElementById('camp-preload-pct');
  if (fill) fill.style.width = pct + '%';
  if (pctEl) pctEl.textContent = pct + '%';
}
function campPreloadHide(){
  const ov = document.getElementById('camp-preload');
  if (!ov) return;
  ov.classList.add('done');
  setTimeout(() => { ov.hidden = true; }, 350);
}
/** Precarica le immagini di $cards, poi esegue $draw(). */
function withCardPreload(cards, draw){
  campPreloadShow();
  const urls = (cards || []).map(c => c.image_url);
  preloadImages(urls, (done, total) => campPreloadPct(Math.round(done / total * 100))).then(() => {
    campPreloadHide();
    draw();
  });
}

function api(action, body){
  return fetch('api/campaign.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(Object.assign({ action }, body || {})),
  }).then(r => r.json());
}

function render(v){
  // Il mago consigliere vive solo nell'hub: nascondilo in ogni altra fase.
  const cc = document.getElementById('coach');
  if (cc) cc.hidden = true;
  if (!v || !v.ok)                  return renderError(v && v.error);
  if (v.phase === 'none')           return renderSaveSelect();
  if (v.phase === 'drafting')       return renderDraft(v);
  if (v.phase === 'pending_reward') return renderReward(v);
  if (v.phase === 'fighting')       return renderFighting(v);
  return renderHub(v); // 'hub'
}

function renderError(msg){
  root.innerHTML = `<div class="camp-head"><h1>Ahia</h1>
    <p class="camp-sub">${esc(msg || 'Errore imprevisto.')}</p></div>
    <div class="camp-bar"><a class="btn" href="index.php">← Home</a></div>`;
}

/* --- Bottone "cambia valico", mostrato in ogni fase per saltare a un'altra run --- */
function switchBtnHtml(){
  return `<button class="btn ghost" id="switch-camp">🔄 Cambia valico</button>`;
}
function wireSwitchBtn(){
  const el = $('#switch-camp');
  if (el) el.onclick = renderSaveSelect;
}

/* --- Schermata "scegli il tuo valico": una run per colore, max 5 salvate --- */
function saveCardHtml(x, s){
  if (!s) {
    return `<div class="valico" data-c="${x.c}" data-action="new">
      <span class="deck-color c-${x.c}">${x.c}</span>
      <span class="vname">${x.name}</span>
      <span class="valico-status">Nessuna run — inizia</span>
    </div>`;
  }
  const label = s.phase === 'drafting' ? 'Draft in corso' : `${s.done}/${s.total} livelli`;
  return `<div class="valico has-save" data-c="${x.c}" data-action="continue">
    <span class="deck-color c-${x.c}">${x.c}</span>
    <span class="vname">${x.name}</span>
    <span class="valico-status">${esc(label)} · ${s.deck_size} carte</span>
    <button type="button" class="valico-new" data-c="${x.c}" title="Ricomincia da zero">🔄 nuova run</button>
  </div>`;
}

function renderSaveSelect(){
  root.innerHTML = `<div class="camp-head"><h1>I cinque valichi</h1>
    <p class="camp-sub">Carico i salvataggi…</p></div>`;
  api('LIST').then(r => {
    if (!r || !r.ok) return renderError(r && r.error);
    root.innerHTML = `
      <div class="camp-head">
        <h1>I cinque valichi</h1>
        <p class="camp-sub">Ogni valico ha una run distinta: mazzo e progressi separati. Continua o inizia da capo.</p>
      </div>
      <div class="color-grid">
        ${COLORS.map(x => saveCardHtml(x, r.saves[x.c])).join('')}
      </div>`;

    const startNew = (c) => {
      try { localStorage.removeItem('varco_runlog'); } catch (e) {} // nuova run: azzera il riepilogo
      api('NEW', { color: c }).then(render);
    };

    root.querySelectorAll('.valico').forEach(el => {
      el.onclick = (ev) => {
        if (ev.target.closest('.valico-new')) return; // gestito a parte
        const c = el.dataset.c;
        el.style.pointerEvents = 'none';
        if (el.dataset.action === 'continue') { api('SWITCH', { color: c }).then(render); }
        else { startNew(c); }
      };
    });
    root.querySelectorAll('.valico-new').forEach(el => {
      el.onclick = (ev) => {
        ev.stopPropagation();
        const c = el.dataset.c;
        if (!confirm(`Ricominciare la run ${COLOR_NAME[c]}? Perdi il mazzo e i progressi attuali di quel valico.`)) return;
        startNew(c);
      };
    });
  });
}

function renderDraft(v){
  root.innerHTML = `<div class="camp-head"><h1>Draft del mazzo</h1><p class="camp-sub">Preparo le carte…</p></div>`;
  withCardPreload(v.draft.options, () => doRenderDraft(v));
}

function doRenderDraft(v){
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
      ${d.options.map((c, i) => `<div class="pick-card" data-i="${i}">${stdCardMarkup(c)}</div>`).join('')}
    </div>
    <div class="camp-bar">
      <button class="btn primary" id="confirm" disabled>Conferma (0/${d.pick})</button>
      <button class="btn ghost" id="abandon">Abbandona</button>
      ${switchBtnHtml()}
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
  wireSwitchBtn();

  adviseDraft(v); // il mago suggerisce quali carte prenderebbe
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

function mapNode(valley, s, per){
  // Le posizioni si RIUSANO a cicli di `per` (=6): finiti i passi 1-6, gli stessi punti
  // ospitano i passi 7-12 (nuovi villaggi), poi 13-18, ecc.
  const pos = VALLEY_NODES[valley] && VALLEY_NODES[valley][(s.step - 1) % per];
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
  // Ogni valle mostra una "pagina" di nodi pari alle posizioni disponibili (6): la pagina
  // corrente è quella del primo passo non ancora superato; a valle finita resta l'ultima.
  const nodesHtml = mapped.map(V => {
    const per     = VALLEY_NODES[V.valley].length;
    const maxPage = Math.max(0, Math.ceil((V.total || V.steps.length) / per) - 1);
    const page    = Math.min(Math.floor((V.done || 0) / per), maxPage);
    return V.steps
      .filter(s => Math.floor((s.step - 1) / per) === page)
      .map(s => mapNode(V.valley, s, per)).join('');
  }).join('');
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
      <img src="assets/bg/campaign.jpg" alt="Mappa delle cinque valli">
      ${ruinHtml}
      ${nodesHtml}
    </div>
    ${unmapped.length ? `<p class="hint hub-todo">Valli non ancora posizionate sulla mappa — giocabili qui sotto:</p>
      <div class="hub-grid">${unmapped.map(valleyHtml).join('')}</div>` : ''}
    ${curveHtml(deck)}
    <div class="camp-bar">
      <button class="btn ghost" id="abandon">Abbandona la run</button>
      ${switchBtnHtml()}
    </div>
    <details class="hub-deck"><summary>Il tuo mazzo (${v.deck_size})</summary>
      <div class="deck-strip">${deck.map(c => cardMarkup(c)).join('')}</div></details>`;

  root.querySelectorAll('.map-node:not([disabled]), .hub-step:not([disabled])').forEach(el => {
    el.onclick = () => {
      el.disabled = true;
      api('ENTER', { valley: el.dataset.valley, step: +el.dataset.step })
        .then(r => { if (r && r.ok) { location.href = 'game.php?campaign=1'; } else { renderError(r && r.error); } });
    };
  });
  $('#abandon').onclick = abandon;
  wireSwitchBtn();

  suggestAttack(v); // il mago consiglia il prossimo livello da affrontare
}

/* --- Mago consigliere dell'hub: suggerisce CHI attaccare ed evidenzia il nodo --- */
const POSE_IMG = {
  spiega: 'assets/tutor/mago-spiega.png',
  indica: 'assets/tutor/mago-indica.png',
  pensa:  'assets/tutor/mago-pensa.png',
};
let campCoachOff = false; // se l'utente chiude il fumetto, non lo ririproponiamo in questa sessione

function showCampCoach(msg, pose){
  if (campCoachOff) return;
  let box = document.getElementById('coach');
  if (!box){ box = document.createElement('div'); box.id = 'coach'; document.body.appendChild(box); }
  box.className = 'coach coach-camp';
  const img = POSE_IMG[pose] || POSE_IMG.indica;
  box.innerHTML = `<img class="coach-mago" src="${img}" alt="Mago narratore" onerror="this.style.display='none'">
    <div class="coach-bubble">
      <span class="coach-msg">${msg}</span>
      <button class="coach-x" aria-label="ho capito">✓</button>
    </div>`;
  box.hidden = false;
  box.querySelector('.coach-x').onclick = () => { box.hidden = true; campCoachOff = true; };
}

function suggestAttack(v){
  const valleys = v.valleys || [];
  // Passi di FRONTIERA (status 'open' = prossimo sbloccabile) di tutte le valli.
  const open = [];
  valleys.forEach(V => (V.steps || []).forEach(s => { if (s.status === 'open') open.push({ valley: V.valley, s }); }));

  // Preferisci la valle del MAZZO (casa), poi un livello non-boss, poi qualsiasi frontiera.
  const pick = open.find(o => o.valley === v.color && !o.s.boss)
            || open.find(o => !o.s.boss)
            || open[0] || null;

  if (!pick){
    showCampCoach('Hai forzato <b>tutte le valli</b>! 🏔 Non resta più nessun varco aperto da sfidare.', 'spiega');
    return;
  }
  const cn = COLOR_NAME[pick.valley] || pick.valley;
  const msg = `Ti conviene attaccare <b>${esc(pick.s.level_name)}</b> a <b>${esc(pick.s.village_name)}</b> (Valle ${cn})` +
              (pick.s.boss ? ' — ma è il <b>BOSS</b> 👑, fatti trovare pronto!' : '. È il prossimo varco da forzare.');
  showCampCoach(msg, pick.s.boss ? 'pensa' : 'indica');

  // Evidenzia il nodo consigliato sulla mappa (o nel pannello hub classico).
  const node = root.querySelector(
    `.map-node[data-valley="${pick.valley}"][data-step="${pick.s.step}"],` +
    `.hub-step[data-valley="${pick.valley}"][data-step="${pick.s.step}"]`);
  if (node) node.classList.add('suggested');
}

/* --- Mago consigliere del DRAFT: "io prenderei..." + evidenzia le carte migliori --- */
// Punteggio grezzo: statistiche per mana + bonus rarità + keyword. Solo per dare una dritta.
function cardScore(c){
  const mv    = Math.max(1, +c.mana_value || 1);
  const stats = (+c.power || 0) + (+c.toughness || 0);
  const rar   = { common: 0, uncommon: 1, rare: 3, mythic: 5 }[c.rarity] || 0;
  let kw = 0;
  ['flying','deathtouch','double_strike','lifelink','first_strike','trample','reach','defender']
    .forEach(k => { if (+c[k]) kw++; });
  return stats / mv * 2 + rar + kw * 1.2 + (+c.power || 0) * 0.3;
}

function adviseDraft(v){
  const d = v.draft;
  if (!d || !Array.isArray(d.options) || !d.options.length) return;

  const ranked = d.options.map((c, i) => ({ c, i, s: cardScore(c) })).sort((a, b) => b.s - a.s);
  const top    = ranked.slice(0, Math.max(1, d.pick || 1));
  const names  = top.map(t => `<b>${esc(t.c.name)}</b>`);
  const list   = names.length > 1
    ? names.slice(0, -1).join(', ') + ' e ' + names[names.length - 1]
    : names[0];

  const best = top[0].c;
  let why = ' (ottime statistiche per il costo)';
  if (['rare', 'mythic'].includes(best.rarity)) why = ' (è una carta rara, una potenziale bomba 💥)';
  else if (+best.flying)     why = ' (vola ✈️, difficile da bloccare)';
  else if (+best.deathtouch) why = ' (tocco letale ☠️, abbatte qualsiasi cosa)';
  else if (+best.double_strike) why = ' (doppio attacco ⚔️)';

  showCampCoach(`Io prenderei ${list}${why}. Ma scegli col tuo stile!`, 'pensa');

  top.forEach(t => {
    const el = root.querySelector(`.pick-card[data-i="${t.i}"]`);
    if (el && !el.querySelector('.advised-tag')) {
      el.classList.add('advised');
      const tag = document.createElement('span');
      tag.className = 'advised-tag';
      tag.textContent = '👍';
      tag.title = 'Consiglio del mago';
      el.appendChild(tag);
    }
  });
}

function renderReward(v){
  root.innerHTML = `<div class="camp-head"><h1>🎁 Bottino</h1><p class="camp-sub">Preparo le carte…</p></div>`;
  withCardPreload(v.reward.options, () => doRenderReward(v));
}

function doRenderReward(v){
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
      ${r.options.map((c, i) => `<div class="pick-card" data-i="${i}">${stdCardMarkup(c)}</div>`).join('')}
    </div>
    <div class="camp-bar">
      <button class="btn primary" id="take" disabled>Prendi la carta</button>
      ${switchBtnHtml()}
    </div>`;

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
  wireSwitchBtn();
}

function renderFighting(v){
  const cur = v.current || {};
  root.innerHTML = `
    <div class="camp-head">
      <h1>Battaglia in corso</h1>
      <p class="camp-sub">Sei nel mezzo di uno scontro: <b>${esc(cur.village_name || '')}</b> — ${esc(cur.level_name || '')}${cur.boss ? ' 👑' : ''} (valle <b>${cur.valley || ''}</b>).</p>
    </div>
    <div class="camp-bar">
      <a class="btn primary" href="game.php?campaign=1">↩ Torna alla battaglia</a>
      <button class="btn ghost" id="abandon">Abbandona la run</button>
      ${switchBtnHtml()}
    </div>`;
  $('#abandon').onclick = abandon;
  wireSwitchBtn();
}

function abandon(){
  if (!confirm('Abbandonare la campagna? Perdi il mazzo e i progressi.')) return;
  api('RESET').then(render);
}

api('STATE').then(render).catch(() => renderError('Connessione fallita.'));
