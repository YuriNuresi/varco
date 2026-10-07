<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
$pageTitle = 'Riepilogo';
$bodyClass = 'page-end';
require __DIR__ . '/partials/header.php';
?>
<section class="endpage">
    <!-- #share-card è il bersaglio dello screenshot/immagine: tutto ciò che deve finire
         nello scatto sta qui dentro; i controlli (sotto) restano fuori. -->
    <div id="share-card" class="share-card">
        <div class="sc-brand">⟡ VARCO</div>
        <div id="sc-body">
            <p class="hint" style="text-align:center">Carico il riepilogo…</p>
        </div>
        <div class="sc-foot">varco — battaglia di corsie</div>
    </div>

    <div class="end-actions">
        <button id="save-img" class="btn primary">📷 Salva immagine</button>
        <button id="send-img" class="btn">📤 Invia alla gallery</button>
        <a class="btn" href="campaign.php">🏔 Campagna</a>
        <a class="btn" href="index.php">← Home</a>
        <button id="clear-log" class="btn ghost">🗑 Azzera</button>
    </div>
    <div id="end-toast" class="screenshot-toast" hidden></div>
</section>

<style>
.endpage{max-width:540px;margin:1.4rem auto}
.share-card{position:relative;background:linear-gradient(180deg,rgba(36,23,56,.92),rgba(14,10,24,.96));
  border:1px solid var(--line);border-radius:16px;padding:1.6rem 1.4rem 1.2rem;
  box-shadow:0 0 34px rgba(0,0,0,.6),inset 0 1px 0 rgba(255,255,255,.05)}
.sc-brand{text-align:center;font-family:"Cinzel",serif;font-weight:700;letter-spacing:.34em;
  font-size:1rem;color:var(--gold-bright);text-shadow:0 0 16px rgba(196,162,89,.4);margin-bottom:1rem}
.sc-foot{text-align:center;font-family:"Cinzel",serif;font-size:.6rem;letter-spacing:.22em;
  text-transform:uppercase;color:var(--gold-dim);margin-top:1.2rem}

.sc-verdict{font-family:"Grenze Gotisch",serif;font-weight:700;letter-spacing:.08em;font-size:2.3rem;
  text-align:center;padding:.5rem;border-radius:12px;margin-bottom:.3rem}
.sc-verdict.win{background:rgba(95,201,138,.14);color:var(--win);box-shadow:0 0 0 1px rgba(95,201,138,.4)}
.sc-verdict.lose{background:rgba(224,106,74,.14);color:var(--lose);box-shadow:0 0 0 1px rgba(224,106,74,.4)}
.sc-verdict.draw{background:rgba(196,162,89,.14);color:var(--draw);box-shadow:0 0 0 1px rgba(196,162,89,.4)}
.sc-record{text-align:center;font-family:"Cinzel",serif;letter-spacing:.16em;color:var(--parch);
  font-size:.82rem;text-transform:uppercase;margin-bottom:1.1rem}

.sc-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:.5rem;margin-bottom:1.2rem}
.sc-stat{text-align:center;padding:.6rem .2rem;border:1px solid var(--line);border-radius:10px;
  background:rgba(196,162,89,.04)}
.sc-stat b{display:block;font-family:"Cinzel",serif;font-size:1.5rem;color:var(--gold-bright);line-height:1.1}
.sc-stat span{font-size:.58rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted)}

.sc-list{display:flex;flex-direction:column;gap:.4rem}
.sc-match{display:flex;align-items:center;gap:.6rem;padding:.5rem .65rem;border:1px solid var(--line);
  border-radius:9px;background:rgba(8,5,16,.32)}
.sc-match .m-icon{font-size:1.1rem;width:1.4rem;text-align:center}
.sc-match .m-main{flex:1;min-width:0}
.sc-match .m-label{font-family:"Cinzel",serif;font-size:.78rem;letter-spacing:.04em;color:var(--parch);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sc-match .m-sub{font-size:.7rem;color:var(--muted);font-style:italic}
.sc-match .m-score{font-family:"Cinzel",serif;font-size:.74rem;color:var(--gold);white-space:nowrap;text-align:right}
.sc-match .m-score .ms-you{color:var(--gold-bright)}
.sc-match.win{border-color:rgba(95,201,138,.5)} .sc-match.win .m-icon{color:var(--win)}
.sc-match.lose{border-color:rgba(224,106,74,.4)} .sc-match.lose .m-icon{color:var(--lose)}
.sc-match.draw .m-icon{color:var(--draw)}

.sc-empty{text-align:center;color:var(--muted);font-style:italic;padding:1.4rem 0}
.sc-empty a{display:inline-block;margin-top:.6rem}

.end-actions{display:flex;gap:.7rem;justify-content:center;flex-wrap:wrap;margin:1.2rem 0 .4rem}
</style>

<script>
'use strict';
(function () {
  const KEY = 'varco_runlog';
  const body = document.getElementById('sc-body');
  const toast = document.getElementById('end-toast');

  function showToast(msg, ms = 3000) {
    toast.textContent = msg; toast.hidden = false;
    clearTimeout(toast._t); toast._t = setTimeout(() => { toast.hidden = true; }, ms);
  }
  function esc(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}

  function load() {
    try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; }
  }

  function verdict(wins, losses) {
    if (wins > losses) return { cls: 'win',  txt: '🏆 Vittoria' };
    if (wins < losses) return { cls: 'lose', txt: '💀 Sconfitta' };
    return { cls: 'draw', txt: '⚔️ In equilibrio' };
  }

  const OUT_MAP = {
    PLAYER: { cls: 'win',  icon: '🏆', word: 'Vinta' },
    AI:     { cls: 'lose', icon: '💀', word: 'Persa' },
    DRAW:   { cls: 'draw', icon: '🤝', word: 'Pari'  },
  };

  function render() {
    const log = load();
    if (!log.length) {
      body.innerHTML = `<div class="sc-empty">Nessuna partita ancora registrata.<br>
        <a class="btn primary" href="campaign.php">Gioca la campagna</a></div>`;
      return;
    }
    const wins   = log.filter(m => m.outcome === 'PLAYER').length;
    const losses = log.filter(m => m.outcome === 'AI').length;
    const draws  = log.filter(m => m.outcome === 'DRAW').length;
    const rounds = log.reduce((s, m) => s + (parseInt(m.round, 10) || 0), 0);
    const rate   = Math.round(wins / log.length * 100);
    const v = verdict(wins, losses);

    const rows = log.slice().reverse().map(m => {
      const o = OUT_MAP[m.outcome] || OUT_MAP.DRAW;
      return `<div class="sc-match ${o.cls}">
        <span class="m-icon">${o.icon}</span>
        <span class="m-main">
          <span class="m-label">${esc(m.label || 'Match')}</span>
          <span class="m-sub">${o.word} · ${parseInt(m.round, 10) || 0} round</span>
        </span>
        <span class="m-score"><span class="ms-you">${Math.max(0, +m.player_life || 0)}</span>
          &nbsp;·&nbsp;${Math.max(0, +m.ai_life || 0)}</span>
      </div>`;
    }).join('');

    body.innerHTML = `
      <div class="sc-verdict ${v.cls}">${v.txt}</div>
      <div class="sc-record">${wins} vinte · ${losses} perse${draws ? ' · ' + draws + ' pari' : ''}</div>
      <div class="sc-stats">
        <div class="sc-stat"><b>${log.length}</b><span>Match</span></div>
        <div class="sc-stat"><b>${wins}</b><span>Vittorie</span></div>
        <div class="sc-stat"><b>${rate}%</b><span>Win rate</span></div>
        <div class="sc-stat"><b>${rounds}</b><span>Round</span></div>
      </div>
      <div class="sc-list">${rows}</div>`;
  }

  // Renderizza #share-card in un canvas, caricando html2canvas dalla CDN al primo uso.
  async function renderCard() {
    if (!window.html2canvas) {
      showToast('⏳ Carico il renderer…');
      await new Promise((res, rej) => {
        const s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
        s.onload = res; s.onerror = rej; document.head.appendChild(s);
      }).catch(() => {});
      if (!window.html2canvas) throw new Error('html2canvas non disponibile');
    }
    const card = document.getElementById('share-card');
    return html2canvas(card, { backgroundColor: '#080510', scale: 2, useCORS: true, logging: false });
  }

  // Round totali della run: usati come metadato dell'upload (per nominare il file gallery).
  function totalRounds() {
    return load().reduce((s, m) => s + (parseInt(m.round, 10) || 0), 0);
  }

  // --- Salva come immagine (download JPG) ---
  document.getElementById('save-img').addEventListener('click', async function () {
    const btn = this;
    if (!load().length) { showToast('Nessuna partita da salvare.'); return; }
    btn.disabled = true; const old = btn.textContent; btn.textContent = '⏳ Genero…';
    try {
      const canvas = await renderCard();
      const a = document.createElement('a');
      a.href = canvas.toDataURL('image/jpeg', 0.92);
      a.download = 'varco-riepilogo.jpg';
      document.body.appendChild(a); a.click(); a.remove();
      showToast('✅ Immagine salvata!');
    } catch (e) {
      console.error('[end] save-img', e);
      showToast('❌ Generazione fallita — fai uno screenshot a mano');
    } finally {
      btn.disabled = false; btn.textContent = old;
    }
  });

  // --- Invia alla gallery (POST a screenshot_upload.php → pending/) ---
  document.getElementById('send-img').addEventListener('click', async function () {
    const btn = this;
    if (!load().length) { showToast('Nessuna partita da inviare.'); return; }
    btn.disabled = true; const old = btn.textContent; btn.textContent = '⏳ Invio…';
    try {
      const canvas = await renderCard();
      const blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', 0.9));
      if (!blob) throw new Error('blob nullo');
      const fd = new FormData();
      fd.append('shot', blob, 'riepilogo.jpg');
      fd.append('round', String(totalRounds()));
      fd.append('deck_id', '0');
      const resp = await fetch('screenshot_upload.php', { method: 'POST', body: fd });
      const json = await resp.json();
      showToast(json.ok ? '✅ Inviato alla gallery! Grazie 🙏' : ('⚠️ ' + (json.error || 'Errore upload')), 4000);
    } catch (e) {
      console.error('[end] send-img', e);
      showToast('❌ Invio fallito — riprova');
    } finally {
      btn.disabled = false; btn.textContent = old;
    }
  });

  // --- Azzera lo storico ---
  document.getElementById('clear-log').addEventListener('click', function () {
    if (!confirm('Azzerare il riepilogo dei match?')) return;
    try { localStorage.removeItem(KEY); } catch (e) {}
    render();
    showToast('Riepilogo azzerato.');
  });

  render();
})();
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
</content>
</invoke>
