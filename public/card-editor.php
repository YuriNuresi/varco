<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';

if (!is_admin()) {
    $pageTitle = 'Accesso riservato';
    require __DIR__ . '/partials/header.php';
    echo '<section class="card-panel" style="max-width:420px;margin:4rem auto">
        <h2>Fucina delle Carte</h2>
        <p>Accedi con Google per usare l\'editor.</p>
        <a href="/auth_google.php?next=/card-editor.php" class="btn primary" style="margin-top:1rem;display:inline-block">Accedi con Google</a>
    </section>';
    require __DIR__ . '/partials/footer.php'; exit;
}
$pageTitle = 'Fucina delle Carte'; require __DIR__ . '/partials/header.php'; ?>

<style>
/* ===== Fucina — override locali ===== */
.fucina-header{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem}

/* form grid */
.f-grid{display:grid;gap:.8rem}
.f-grid-2{grid-template-columns:1fr 1fr}
.f-grid-3{grid-template-columns:1fr 1fr 1fr}
.f-grid-4{grid-template-columns:1fr 1fr 1fr 1fr}
@media(max-width:700px){.f-grid-2,.f-grid-3,.f-grid-4{grid-template-columns:1fr 1fr}}
@media(max-width:480px){.f-grid-2,.f-grid-3,.f-grid-4{grid-template-columns:1fr}}

.f-field{display:flex;flex-direction:column;gap:.25rem}
.f-field label{font-family:"Cinzel",serif;font-size:.72rem;letter-spacing:.1em;text-transform:uppercase;color:var(--parch)}
.f-field input,.f-field select,.f-field textarea{font-family:"Cormorant Garamond",serif;font-size:1.05rem;
  background:var(--panel-solid);color:var(--bone);border:1px solid var(--line);border-radius:6px;padding:.5rem .7rem;width:100%;box-sizing:border-box}
.f-field input:focus,.f-field select:focus,.f-field textarea:focus{outline:none;border-color:var(--gold)}
.f-field textarea{resize:vertical;min-height:60px}
.f-field small{font-size:.8rem;color:var(--muted);text-transform:none;letter-spacing:0}

/* colori WUBRG con spunte */
.color-picks{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.3rem}
.color-picks label{display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.95rem;color:var(--bone)}
.color-picks input[type="checkbox"]{display:none}
.color-dot{width:28px;height:28px;border-radius:50%;border:2px solid rgba(255,255,255,.15);display:grid;place-items:center;
  transition:.15s;font-family:"Cinzel",serif;font-weight:700;font-size:.7rem;color:#15110a}
.color-dot.w{background:var(--W)} .color-dot.u{background:var(--U)} .color-dot.b{background:var(--B)}
.color-dot.r{background:var(--R)} .color-dot.g{background:var(--G)}
.color-picks input:checked + .color-dot{border-color:var(--gold-bright);box-shadow:0 0 12px var(--gold-bright);transform:scale(1.15)}

/* abilità con icone */
.kw-picks{display:flex;flex-wrap:wrap;gap:.5rem .9rem;margin-top:.3rem}
.kw-picks label{display:flex;align-items:center;gap:.35rem;cursor:pointer;font-size:.92rem;color:var(--bone);
  padding:.25rem .5rem;border-radius:5px;border:1px solid transparent;transition:.15s}
.kw-picks label:hover{border-color:var(--line);background:rgba(196,162,89,.06)}
.kw-picks input[type="checkbox"]{display:none}
.kw-picks input:checked + .kw-icon{transform:scale(1.2)}.kw-picks label:has(input:checked){border-color:var(--gold);background:rgba(196,162,89,.12)}
.kw-icon{font-size:1.2rem;width:1.4rem;text-align:center;transition:.15s}

/* preview immagine */
#img-preview img{max-width:280px;border-radius:10px;border:1px solid var(--line);box-shadow:0 8px 24px rgba(0,0,0,.6)}

/* multi-provider gallery */
.gen-gallery{display:grid;grid-template-columns:repeat(3,1fr);gap:.8rem;margin-top:.8rem}
@media(max-width:700px){.gen-gallery{grid-template-columns:1fr}}
.gen-option{text-align:center;border:2px solid var(--line);border-radius:10px;padding:.6rem;background:var(--panel-solid);transition:.2s}
.gen-option:hover{border-color:var(--gold)}
.gen-option.selected{border-color:var(--gold-bright);box-shadow:0 0 16px rgba(196,162,89,.3)}
.gen-option img{max-width:100%;border-radius:8px;margin-bottom:.4rem}
.gen-option .prov-label{font-family:"Cinzel",serif;font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;color:var(--parch);margin-bottom:.3rem}
.gen-option .prov-error{font-size:.8rem;color:var(--lose);padding:1rem .5rem}
.gen-option .btn{font-size:.82rem;padding:.3rem .8rem}

/* sezione illustrazione */
.illust-section{display:grid;grid-template-columns:1fr 280px;gap:1rem;align-items:start}
@media(max-width:700px){.illust-section{grid-template-columns:1fr}}

/* bottoni azione */
.f-actions{display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;margin-top:1rem}
.f-actions .spacer{flex:1}
</style>

<section class="card-panel">
    <div class="fucina-header">
        <h2>Fucina delle Carte</h2>
    </div>
</section>

<!-- ========== FORM ========== -->
<section class="card-panel">
    <h3 id="form-title">Nuova carta</h3>
    <form id="card-form" autocomplete="off">
        <input type="hidden" id="f-id" value="">

        <!-- riga 1: nome + sottotipo -->
        <div class="f-grid f-grid-2" style="grid-template-columns:2fr 1fr">
            <div class="f-field">
                <label for="f-name">Nome *</label>
                <input type="text" id="f-name" required placeholder="es. Guardia del Varco">
            </div>
            <div class="f-field">
                <label for="f-subtypes">Sottotipo</label>
                <input type="text" id="f-subtypes" placeholder="es. Goblin, Warrior">
            </div>
        </div>

        <!-- riga 2: costo, forza, resistenza, rarità -->
        <div class="f-grid f-grid-4" style="margin-top:.8rem">
            <div class="f-field">
                <label for="f-mana">Costo mana</label>
                <input type="number" id="f-mana" min="0" max="20" value="1">
            </div>
            <div class="f-field">
                <label for="f-power">Forza</label>
                <input type="number" id="f-power" min="0" max="99" value="1">
            </div>
            <div class="f-field">
                <label for="f-toughness">Resistenza</label>
                <input type="number" id="f-toughness" min="0" max="99" value="1">
            </div>
            <div class="f-field">
                <label for="f-rarity">Rarità</label>
                <select id="f-rarity">
                    <option value="common">Comune</option>
                    <option value="uncommon">Non-comune</option>
                    <option value="rare">Rara</option>
                    <option value="mythic">Mitica</option>
                </select>
            </div>
        </div>

        <!-- colori -->
        <fieldset class="ed-keywords" style="margin-top:.8rem">
            <legend>Colori</legend>
            <div class="color-picks">
                <label><input type="checkbox" value="W"><span class="color-dot w">W</span> Bianco</label>
                <label><input type="checkbox" value="U"><span class="color-dot u">U</span> Blu</label>
                <label><input type="checkbox" value="B"><span class="color-dot b">B</span> Nero</label>
                <label><input type="checkbox" value="R"><span class="color-dot r">R</span> Rosso</label>
                <label><input type="checkbox" value="G"><span class="color-dot g">G</span> Verde</label>
            </div>
        </fieldset>

        <!-- abilità -->
        <fieldset class="ed-keywords" style="margin-top:.4rem">
            <legend>Abilità</legend>
            <div class="kw-picks">
                <label><input type="checkbox" id="f-flying"><span class="kw-icon">✈️</span> Volare</label>
                <label><input type="checkbox" id="f-first_strike"><span class="kw-icon">⚡</span> Primo colpo</label>
                <label><input type="checkbox" id="f-deathtouch"><span class="kw-icon">☠️</span> Tocco letale</label>
                <label><input type="checkbox" id="f-trample"><span class="kw-icon">🐗</span> Travolgere</label>
                <label><input type="checkbox" id="f-double_strike"><span class="kw-icon">⚔️</span> Doppio colpo</label>
                <label><input type="checkbox" id="f-lifelink"><span class="kw-icon">❤️</span> Legame vitale</label>
                <label><input type="checkbox" id="f-reach"><span class="kw-icon">🏹</span> Portata</label>
                <label><input type="checkbox" id="f-defender"><span class="kw-icon">🛡️</span> Difensore</label>
            </div>
        </fieldset>

        <!-- illustrazione -->
        <fieldset class="ed-keywords" style="margin-top:.4rem">
            <legend>Illustrazione</legend>
            <div class="illust-section">
                <div>
                    <div class="f-field">
                        <label for="f-img-desc">Descrivi l'immagine da generare</label>
                        <textarea id="f-img-desc" rows="3" placeholder="es. Un goblin feroce con armatura di ossa, circondato da fiamme, in una caverna oscura…"></textarea>
                    </div>
                    <div style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin-top:.6rem">
                        <button type="button" class="btn" id="btn-gen-img">Genera con 3 motori</button>
                        <span id="gen-status" class="msg" style="font-size:.85rem"></span>
                    </div>
                    <div id="gen-gallery" class="gen-gallery" style="display:none"></div>
                    <input type="hidden" id="f-image">
                </div>
                <div id="img-preview" style="text-align:center;padding-top:.5rem"></div>
            </div>
        </fieldset>

        <!-- azioni -->
        <div class="f-actions">
            <button type="submit" class="btn primary" id="btn-save">Crea carta</button>
            <button type="button" class="btn ghost" id="btn-reset">Annulla</button>
            <span class="spacer"></span>
            <button type="button" class="btn" id="btn-delete" style="display:none;color:var(--lose)">Elimina</button>
        </div>
        <p id="form-msg" class="msg"></p>
    </form>
</section>

<!-- ========== LISTA CARTE ========== -->
<section class="card-panel">
    <h3>Le tue carte <span id="card-count" class="badge">0</span></h3>
    <div class="builder-controls">
        <label>Colore
            <select id="filter-color">
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
        <label>Sottotipo
            <!-- popolato dinamicamente dai sottotipi reali delle carte (buildSubtypeFilter) -->
            <select id="filter-subtype">
                <option value="">Tutti</option>
            </select>
        </label>
        <label>Rarità
            <select id="filter-rarity">
                <option value="">Tutti</option>
                <option value="common">Comune</option>
                <option value="uncommon">Non-comune</option>
                <option value="rare">Rara</option>
                <option value="mythic">Mitica</option>
            </select>
        </label>
        <label>Cerca <input type="text" id="search-cards" placeholder="nome carta…"></label>
    </div>
    <div id="card-list" class="card-grid" style="max-height:none">Caricamento…</div>
</section>

<script>
'use strict';
const API = '/api/cards_admin.php';
const $ = s => document.querySelector(s);
const $$ = s => document.querySelectorAll(s);
const KEYWORDS = ['flying','first_strike','deathtouch','trample','double_strike','lifelink','reach','defender'];
const RARITY_LABEL = {common:'Comune',uncommon:'Non-comune',rare:'Rara',mythic:'Mitica'};
const COLOR_NAMES = {W:'white',U:'blue',B:'black',R:'red',G:'green'};

let cards = [];
let editId = null;

function escHtml(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

function getColors() {
  return Array.from($$('.color-picks input:checked')).map(cb => cb.value).join('');
}
function setColors(str) {
  $$('.color-picks input').forEach(cb => { cb.checked = str.includes(cb.value); });
}

function kwIcons(c) {
  let s = '';
  if (+c.flying) s += '✈️';
  if (+c.first_strike) s += '⚡';
  if (+c.deathtouch) s += '☠️';
  if (+c.trample) s += '🐗';
  if (+c.double_strike) s += '⚔️';
  if (+c.lifelink) s += '❤️';
  if (+c.reach) s += '🏹';
  if (+c.defender) s += '🛡️';
  return s;
}

function thumbUrl(c){ return c.image_url ? c.image_url.replace('/normal/','/small/') : ''; }

function renderList() {
  const wrap = $('#card-list');
  const q = $('#search-cards').value.trim().toLowerCase();
  const fc = $('#filter-color').value;
  const fs = $('#filter-subtype').value;
  const fr = $('#filter-rarity').value;
  let filtered = cards;
  if (fc === 'C') filtered = filtered.filter(c => !c.colors || c.colors === '');
  else if (fc) filtered = filtered.filter(c => (c.colors || '').includes(fc));
  if (fs) filtered = filtered.filter(c => (c.subtypes || '').includes(fs));
  if (fr) filtered = filtered.filter(c => (c.rarity || 'common') === fr);
  if (q) filtered = filtered.filter(c => c.name.toLowerCase().includes(q));
  wrap.innerHTML = '';
  $('#card-count').textContent = filtered.length;
  if (!filtered.length) { wrap.innerHTML = '<em>Nessuna carta custom. Forgia la prima!</em>'; return; }
  filtered.forEach(c => {
    const div = document.createElement('div');
    div.className = 'card c-' + (c.colors || 'C');
    if (!thumbUrl(c)) div.classList.add('noimg');

    const img = thumbUrl(c)
      ? `<img loading="lazy" src="${thumbUrl(c)}" alt="${escHtml(c.name)}"
           onerror="this.parentNode.classList.add('noimg');this.remove();">`
      : '';
    const gem = `<span class="gem r-${c.rarity||'common'}"></span>`;
    const kw = kwIcons(c);

    div.innerHTML = `
      <div class="card-header c-${c.colors || 'C'}">
        <div class="card-cost">${c.mana_value}</div>
        <div class="card-name">${escHtml(c.name)}</div>
      </div>
      <div class="card-image${!thumbUrl(c) ? ' noimg' : ''}">
        ${img}
      </div>
      <div class="card-footer c-${c.colors || 'C'}">
        <div class="card-subtype">${gem} ${c.subtypes || '–'}</div>
        <div class="card-rarity">${RARITY_LABEL[c.rarity]||'Comune'}</div>
      </div>
      <div class="card-stats">
        <div class="card-keywords">${kw ? Array.from(kw).map(k => `<span>${k}</span>`).join('') : '–'}</div>
        <div class="card-pt">${c.power}/${c.toughness}</div>
      </div>
      <button class="card-btn">Modifica</button>`;

    div.querySelector('.card-btn').addEventListener('click', () => loadIntoForm(c));
    wrap.appendChild(div);
  });
}

// --- Filtro sottotipi dinamico: costruito dai sottotipi reali delle carte ----
const SUBTYPE_IT = {
  Soldier:'Soldato', Lion:'Leone', Angel:'Angelo', Knight:'Templare', Bird:'Uccello',
  Spirit:'Spirito', Wizard:'Mago', Triton:'Tritone', Leviathan:'Mostro Marino',
  Faerie:'Fata', Sphinx:'Sfinge', Zombie:'Zombi', Devil:'Diavolo', Dragon:'Drago',
  Warrior:'Guerriero', Dwarf:'Nano', Beast:'Bestia', Dinosaur:'Dinosauro',
  Elemental:'Elementale', Spider:'Ragno', Snake:'Serpente', Cleric:'Chierico',
  Demon:'Demone', Fungus:'Fungo', Mutant:'Mutante', 'Beast Warrior':'Bestia Selvaggia',
  Elephant:'Elefante', Berserker:'Berserker', Rogue:'Furfante', Vampire:'Vampiro',
  Skeleton:'Scheletro', Rat:'Ratto', Horror:'Orrore', Specter:'Spettro',
  Imp:'Diavoletto', Wraith:'Presenza', Shade:'Ombra', Nightmare:'Incubo',
  Bat:'Pipistrello', Assassin:'Assassino', Witch:'Strega', Goblin:'Goblin',
  Wolf:'Lupo', Elf:'Elfo', Golem:'Golem', Giant:'Gigante', Griffin:'Grifone',
  Merfolk:'Tritone', Hydra:'Idra', Insect:'Insetto', Treefolk:'Silvantropo',
  Wurm:'Wurm', Ogre:'Ogre', Minotaur:'Minotauro', Pegasus:'Pegaso', Unicorn:'Unicorno',
  Cat:'Felino', Boar:'Cinghiale', Bear:'Orso', Crocodile:'Coccodrillo', Plant:'Pianta',
  Drake:'Draghetto', Phoenix:'Fenice', Kraken:'Kraken', Octopus:'Polpo', Turtle:'Tartaruga',
  Serpent:'Serpe Marina', Illusion:'Illusione', Shapeshifter:'Polimorfo', Gargoyle:'Gargoyle',
  Vedalken:'Vedalken', Human:'Umano', Ghost:'Fantasma', Gorgon:'Gorgone', Naga:'Naga',
};
const COLOR_GROUP = {W:'Bianco', U:'Blu', B:'Nero', R:'Rosso', G:'Verde'};

function buildSubtypeFilter() {
  const sel = $('#filter-subtype');
  const current = sel.value;

  // sottotipo -> insieme dei colori delle carte che lo usano
  const colorsBySub = {};
  cards.forEach(c => {
    (c.subtypes || '').split(',').map(s => s.trim()).filter(Boolean).forEach(st => {
      (colorsBySub[st] ??= new Set());
      (c.colors || '').split('').forEach(l => colorsBySub[st].add(l));
    });
  });

  // gruppo: un solo colore -> quel colore; più colori -> Multicolore; nessuno -> Incolore
  const groups = {};
  Object.entries(colorsBySub).forEach(([st, colSet]) => {
    const cols = [...colSet];
    const g = cols.length === 1 ? (COLOR_GROUP[cols[0]] || 'Incolore')
            : cols.length === 0 ? 'Incolore' : 'Multicolore';
    (groups[g] ??= []).push(st);
  });

  sel.innerHTML = '<option value="">Tutti</option>';
  ['Bianco','Blu','Nero','Rosso','Verde','Multicolore','Incolore'].forEach(g => {
    if (!groups[g]) return;
    const og = document.createElement('optgroup');
    og.label = g;
    groups[g]
      .map(st => [st, SUBTYPE_IT[st] || st])
      .sort((a, b) => a[1].localeCompare(b[1], 'it'))
      .forEach(([st, label]) => {
        const o = document.createElement('option');
        o.value = st;
        o.textContent = label;
        og.appendChild(o);
      });
    sel.appendChild(og);
  });
  sel.value = current;                 // preserva la selezione dopo un reload
  if (sel.value !== current) sel.value = '';
}

function loadCards() {
  fetch(API).then(r=>r.json()).then(d => {
    if (!d.ok) { $('#card-list').textContent = 'Errore: ' + d.error; return; }
    cards = d.cards;
    buildSubtypeFilter();
    renderList();
  });
}

function loadIntoForm(c) {
  editId = c.id;
  $('#form-title').textContent = 'Modifica carta';
  $('#btn-save').textContent = 'Salva modifiche';
  $('#btn-delete').style.display = '';
  $('#f-id').value = c.id;
  $('#f-name').value = c.name;
  $('#f-subtypes').value = c.subtypes || '';
  $('#f-mana').value = c.mana_value;
  setColors(c.colors || '');
  $('#f-power').value = c.power;
  $('#f-toughness').value = c.toughness;
  $('#f-rarity').value = c.rarity;
  $('#f-image').value = c.image_url || '';
  $('#f-img-desc').value = '';
  KEYWORDS.forEach(k => { $('#f-' + k).checked = !!+c[k]; });
  previewImage();
  window.scrollTo({top: 0, behavior: 'smooth'});
}

function resetForm() {
  editId = null;
  $('#form-title').textContent = 'Nuova carta';
  $('#btn-save').textContent = 'Crea carta';
  $('#btn-delete').style.display = 'none';
  $('#card-form').reset();
  $$('.color-picks input').forEach(cb => cb.checked = false);
  $('#f-id').value = '';
  $('#img-preview').innerHTML = '';
  $('#gen-gallery').style.display = 'none';
  $('#gen-gallery').innerHTML = '';
  $('#form-msg').textContent = '';
  $('#gen-status').textContent = '';
}

function previewImage() {
  const url = $('#f-image').value.trim();
  $('#img-preview').innerHTML = url
    ? `<img src="${escHtml(url)}" onerror="this.remove()">`
    : '';
}

function flash(msg, ok) {
  const m = $('#form-msg');
  m.textContent = msg;
  m.className = 'msg ' + (ok ? 'ok' : 'err');
}

// --- Generazione immagine AI (multi-provider) --------------------------------
const PROV_LABELS = {cloudflare:'Cloudflare FLUX',hf_space:'HF Space FLUX (ZeroGPU)',pollinations:'Pollinations',gemini:'Gemini Nano Banana'};

// Palette artistica per colore: carte dello stesso colore condividono l'atmosfera,
// le bicolore fondono le due palette. (Il seed resta casuale: varietà nei soggetti.)
const COLOR_PALETTES = {
  W: 'radiant ivory and antique gold palette, holy sunlight rays, alabaster marble and white feathers',
  U: 'deep sapphire blue and cyan palette, glowing arcane glyphs, sea mist and cold starlight',
  B: 'obsidian black and necrotic violet palette, eerie green ghost-light, bone and creeping shadow',
  R: 'crimson red and ember orange palette, flying sparks, volcanic smoke and molten light',
  G: 'verdant emerald green and moss palette, living vines and roots, golden forest sunbeams',
};
const PALETTE_COLORLESS = 'muted silver and steel grey palette, pale colorless arcane glow';

$('#btn-gen-img').addEventListener('click', async () => {
  const name = $('#f-name').value.trim();
  if (!name) { $('#gen-status').textContent = 'Inserisci almeno il nome.'; $('#gen-status').className = 'msg err'; return; }

  const colors = getColors();
  const subtypes = $('#f-subtypes').value.trim();
  const rarity = $('#f-rarity').value;
  const userDesc = $('#f-img-desc').value.trim();

  const colorWords = colors.split('').map(c => COLOR_NAMES[c]).filter(Boolean).join(' and ') || 'colorless';
  const creatureDesc = subtypes || 'fantasy creature';
  const rarityWord = rarity === 'mythic' ? 'legendary epic' : rarity === 'rare' ? 'powerful majestic' : '';

  const palettes = colors.split('').map(c => COLOR_PALETTES[c]).filter(Boolean);
  const paletteDesc = palettes.length ? palettes.join(', blended with ') : PALETTE_COLORLESS;

  let prompt = `${rarityWord} ${creatureDesc} called "${name}", ${colorWords} magic`.trim();
  if (userDesc) prompt += '. ' + userDesc;
  prompt += '. Dark fantasy trading card game illustration, painterly style, '
    + paletteDesc + ', '
    + 'dramatic cinematic lighting, highly detailed, dark vignette edges, '
    + 'no text, no watermark, no logo, no card frame, portrait composition';

  const btn = $('#btn-gen-img');
  const status = $('#gen-status');
  const gallery = $('#gen-gallery');
  btn.disabled = true;
  gallery.style.display = 'none';
  gallery.innerHTML = '';
  status.textContent = 'Generazione con tutti i motori disponibili… (30-60s)';
  status.className = 'msg';

  try {
    const r = await fetch(API + '?action=generate_image_multi', {
      method: 'POST', headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ prompt, card_name: name })
    });
    const d = await r.json();
    if (!d.ok) { status.textContent = 'Errore: ' + (d.error || 'sconosciuto'); status.className = 'msg err'; return; }

    const results = d.results || [];
    const okCount = results.filter(r => r.ok).length;
    status.textContent = okCount + '/' + results.length + ' immagini generate — scegli la migliore';
    status.className = okCount ? 'msg ok' : 'msg err';

    gallery.style.display = 'grid';
    results.forEach(res => {
      const div = document.createElement('div');
      div.className = 'gen-option';
      if (res.ok) {
        div.innerHTML = `<div class="prov-label">${PROV_LABELS[res.provider] || res.provider}</div>`
          + `<img src="${escHtml(res.url)}" alt="${escHtml(res.provider)}">`
          + `<button type="button" class="btn">Usa questa</button>`;
        div.querySelector('.btn').addEventListener('click', () => {
          $('#f-image').value = res.url;
          previewImage();
          gallery.querySelectorAll('.gen-option').forEach(o => o.classList.remove('selected'));
          div.classList.add('selected');
          status.textContent = 'Selezionata: ' + (PROV_LABELS[res.provider] || res.provider);
          status.className = 'msg ok';
        });
      } else {
        div.innerHTML = `<div class="prov-label">${PROV_LABELS[res.provider] || res.provider}</div>`
          + `<div class="prov-error">${escHtml(res.error)}</div>`;
      }
      gallery.appendChild(div);
    });
  } catch { status.textContent = 'Errore di rete.'; status.className = 'msg err'; }
  finally { btn.disabled = false; }
});

// --- Salvataggio -------------------------------------------------------------
$('#card-form').addEventListener('submit', async e => {
  e.preventDefault();
  const body = {
    name: $('#f-name').value.trim(),
    subtypes: $('#f-subtypes').value.trim(),
    mana_value: +$('#f-mana').value,
    colors: getColors(),
    power: +$('#f-power').value,
    toughness: +$('#f-toughness').value,
    rarity: $('#f-rarity').value,
    image_url: $('#f-image').value.trim(),
  };
  KEYWORDS.forEach(k => { body[k] = $('#f-' + k).checked ? 1 : 0; });
  const method = editId ? 'PUT' : 'POST';
  if (editId) body.id = editId;

  try {
    const r = await fetch(API, { method, headers:{'Content-Type':'application/json'}, body: JSON.stringify(body) });
    const d = await r.json();
    if (d.ok) { flash(editId ? 'Carta aggiornata!' : 'Carta forgiata!', true); resetForm(); loadCards(); }
    else flash('Errore: ' + d.error, false);
  } catch { flash('Errore di rete.', false); }
});

$('#btn-delete').addEventListener('click', async () => {
  if (!editId || !confirm('Eliminare questa carta?')) return;
  try {
    const r = await fetch(API, { method:'DELETE', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id:editId}) });
    const d = await r.json();
    if (d.ok) { flash('Carta eliminata.', true); resetForm(); loadCards(); }
    else flash('Errore: ' + d.error, false);
  } catch { flash('Errore di rete.', false); }
});

$('#btn-reset').addEventListener('click', resetForm);
$('#f-image').addEventListener('change', previewImage);
$('#filter-color').addEventListener('change', renderList);
$('#filter-subtype').addEventListener('change', renderList);
$('#filter-rarity').addEventListener('change', renderList);
let t; $('#search-cards').addEventListener('input', () => { clearTimeout(t); t = setTimeout(renderList, 200); });

loadCards();
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
