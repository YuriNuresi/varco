<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
$pageTitle = 'Campagna';
$bodyClass = 'page-campaign';
require __DIR__ . '/partials/header.php';
?>
<section class="campaign"
         data-draft-options="<?= CAMPAIGN_DRAFT_OPTIONS ?>" data-draft-pick="<?= CAMPAIGN_DRAFT_PICK ?>"
         data-reward-pick="<?= CAMPAIGN_REWARD_PICK ?>">
    <div id="camp-root" class="camp-root">
        <p class="hint">Carico la valle…</p>
    </div>
</section>

<style>
.camp-root{margin-top:1rem}
.camp-head{text-align:center;margin-bottom:1.3rem}
.camp-head h1{font-family:"Cinzel",serif;color:var(--gold-bright);font-size:2.1rem;margin:.2rem 0}
.camp-sub{color:var(--muted);font-style:italic}
.camp-meta{display:flex;gap:1.2rem;justify-content:center;flex-wrap:wrap;margin:.6rem 0 0;
  font-family:"Cinzel",serif;font-size:.78rem;letter-spacing:.12em;text-transform:uppercase;color:var(--gold)}
.camp-meta b{color:var(--gold-bright)}

.color-grid{display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;margin:1.5rem 0}
.valico{display:flex;flex-direction:column;align-items:center;gap:.5rem;cursor:pointer;
  padding:1.1rem 1.3rem;border:1px solid var(--line);border-radius:12px;background:rgba(196,162,89,.04);
  transition:transform .15s,border-color .15s,box-shadow .15s}
.valico:hover{transform:translateY(-3px);border-color:var(--gold);box-shadow:0 0 18px rgba(196,162,89,.25)}
.valico .deck-color{width:2.6rem;height:2.6rem;font-size:1.2rem}
.valico .vname{font-family:"Cinzel",serif;letter-spacing:.1em;color:var(--gold-bright)}

.card-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;
  max-width:780px;margin:1.4rem auto}
.pick-card{position:relative;cursor:pointer;border-radius:10px;transition:transform .15s;
  outline:2px solid transparent;outline-offset:3px}
.pick-card:hover{transform:translateY(-4px)}
.pick-card.sel{outline-color:var(--gold-bright);box-shadow:0 0 22px rgba(236,210,141,.5)}
.pick-card.sel::after{content:"✓";position:absolute;top:-12px;right:-12px;z-index:6;
  width:28px;height:28px;border-radius:50%;display:grid;place-items:center;
  background:linear-gradient(180deg,#ecd28d,#c4a259);color:#150f08;font-weight:700;
  box-shadow:0 0 0 2px var(--ink),0 3px 8px rgba(0,0,0,.6)}
.pick-card .rar{position:absolute;left:6px;top:6px;z-index:6;font-family:"Cinzel",serif;font-size:.6rem;
  letter-spacing:.1em;text-transform:uppercase;padding:.1rem .4rem;border-radius:4px;
  background:rgba(8,5,16,.85);border:1px solid var(--gold-dim);color:var(--parch)}
.pick-card .rar.r-rare{color:var(--gold-bright);border-color:var(--gold)}
.pick-card .rar.r-mythic{color:#ff9a55;border-color:#ff9a55}

.camp-bar{display:flex;gap:.8rem;justify-content:center;align-items:center;flex-wrap:wrap;margin:1.4rem 0}
.deck-strip{display:grid;grid-template-columns:repeat(auto-fill,minmax(82px,1fr));gap:.5rem;
  max-width:760px;margin:1rem auto}
.deck-strip .play-card{font-size:.8rem}
.curve{display:flex;gap:.4rem;justify-content:center;align-items:flex-end;height:60px;margin:.8rem 0}
.curve .bar{width:26px;background:linear-gradient(180deg,var(--gold-bright),var(--gold-dim));
  border-radius:3px 3px 0 0;position:relative;min-height:3px}
.curve .bar span{position:absolute;bottom:-1.2rem;left:0;right:0;text-align:center;font-size:.7rem;color:var(--muted)}
.curve .bar b{position:absolute;top:-1.1rem;left:0;right:0;text-align:center;font-size:.7rem;color:var(--gold-bright)}

/* HUB SU MAPPA: l'immagine a tutta larghezza, i nodi-livello posizionati lungo le valli. */
.hub-map{position:relative;width:100%;margin:1.2rem 0;border:1px solid var(--line);border-radius:14px;
  overflow:hidden;box-shadow:0 0 26px rgba(0,0,0,.55)}
.hub-map img{display:block;width:100%;height:auto}
/* Valle completata: la mappa "devastata" sovrapposta, ritagliata sullo spicchio della valle. */
.hub-map-ruin{position:absolute;inset:0;z-index:1;pointer-events:none;
  background:url(/assets/bg/campaign_victory.jpg) center/100% 100% no-repeat}
.map-node{position:absolute;transform:translate(-50%,-50%);background:none;border:none;padding:0;cursor:pointer;
  display:flex;flex-direction:column;align-items:center;gap:.18rem;font:inherit;z-index:2;--glow:#ecd28d;
  -webkit-tap-highlight-color:transparent}
.map-node.v-R{--glow:#ff7a3c} .map-node.v-W{--glow:#ecd28d} .map-node.v-G{--glow:#74c45a}
.map-node.v-U{--glow:#5aa6e8} .map-node.v-B{--glow:#b06fe0}
.map-node .mn-dot{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;
  font-family:"Cinzel",serif;font-weight:700;font-size:.92rem;color:#fff;border:2px solid rgba(255,255,255,.8);
  background:rgba(12,8,12,.82);box-shadow:0 2px 10px rgba(0,0,0,.85);transition:transform .15s,box-shadow .15s}
.map-node .mn-cap{font-family:"Cinzel",serif;font-size:.6rem;letter-spacing:.04em;text-transform:uppercase;color:#fff;
  white-space:nowrap;text-shadow:0 1px 3px #000,0 0 6px #000;background:rgba(8,5,16,.62);padding:.05rem .35rem;
  border-radius:4px;opacity:0;transition:opacity .15s}
.map-node:hover .mn-cap,.map-node.open .mn-cap,.map-node.done .mn-cap{opacity:1}
/* SBLOCCATO (frontiera): bordo colorato + pulsa */
.map-node.open .mn-dot{border-color:var(--glow);animation:mapPulse 1.7s ease-in-out infinite}
/* VINTO: medaglione d'oro con spunta */
.map-node.done .mn-dot{background:linear-gradient(180deg,#ecd28d,#c4a259);color:#150f08;border-color:#fff}
/* BLOCCATO: medaglione scuro BEN VISIBILE (bordo chiaro + numero), spento ma leggibile */
.map-node.locked .mn-dot{opacity:.92;border-color:rgba(255,255,255,.5);background:rgba(6,5,9,.85);
  box-shadow:0 0 0 1px rgba(0,0,0,.5),0 2px 10px rgba(0,0,0,.9)}
.map-node.boss .mn-dot{width:42px;height:42px;font-size:1.15rem;border-style:double;border-width:4px}
.map-node[disabled]{cursor:not-allowed}
.map-node:not([disabled]):hover .mn-dot{transform:scale(1.14)}
@keyframes mapPulse{0%,100%{box-shadow:0 0 8px var(--glow),0 2px 10px rgba(0,0,0,.85)}
  50%{box-shadow:0 0 22px var(--glow),0 2px 10px rgba(0,0,0,.85)}}
.hub-todo{text-align:center;margin:1.2rem 0 .2rem}

/* HUB classico (pannelli) — usato per le valli non ancora sulla mappa. */
.hub-grid{display:flex;flex-direction:column;gap:1rem;max-width:900px;margin:1.2rem auto}
.hub-valley{padding:.9rem 1rem;border:1px solid var(--line);border-radius:12px;background:rgba(196,162,89,.04)}
.hub-valley.cleared{border-color:var(--gold);box-shadow:0 0 14px rgba(196,162,89,.18)}
.hv-title{display:flex;align-items:center;gap:.7rem;margin-bottom:.7rem}
.hv-title .deck-color{width:2rem;height:2rem;font-size:1rem}
.hv-vname{font-family:"Cinzel",serif;letter-spacing:.08em;color:var(--gold-bright)}
.hv-prog{margin-left:auto;font-family:"Cinzel",serif;font-size:.72rem;letter-spacing:.1em;
  text-transform:uppercase;color:var(--gold)}
.hub-villages{display:flex;align-items:flex-start;flex-wrap:wrap;gap:.5rem}
.hub-village{padding:.5rem .6rem;border:1px solid var(--line);border-radius:10px;background:rgba(8,5,16,.25)}
.hv-head{font-family:"Cinzel",serif;font-size:.7rem;letter-spacing:.05em;text-transform:uppercase;
  color:var(--muted);margin-bottom:.45rem}
.hv-tribe{color:var(--gold-bright)}
.hv-track{display:flex;align-items:flex-start;gap:.35rem;flex-wrap:wrap}
.hub-inn{align-self:center;font-size:1.3rem;opacity:.8;padding:0 .1rem}

.hub-step{display:flex;flex-direction:column;align-items:center;gap:.25rem;width:4.6rem;text-align:center;
  cursor:pointer;background:none;border:none;padding:.1rem;font:inherit}
.hub-step .hs-dot{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;
  font-family:"Cinzel",serif;font-weight:700;font-size:.9rem;border:1px solid var(--gold-dim);
  background:var(--ink);color:var(--parch);transition:transform .15s,box-shadow .15s}
.hub-step .hs-name{font-family:"Cinzel",serif;font-size:.58rem;letter-spacing:.04em;
  text-transform:uppercase;color:var(--muted)}
.hub-step.done .hs-dot{background:linear-gradient(180deg,#ecd28d,#c4a259);color:#150f08;border-color:var(--gold)}
.hub-step.done .hs-name{color:var(--parch)}
.hub-step.open .hs-dot{border-color:var(--gold-bright);color:var(--gold-bright);
  box-shadow:0 0 14px rgba(236,210,141,.45);animation:hubpulse 1.7s ease-in-out infinite}
.hub-step.open .hs-name{color:var(--gold-bright)}
.hub-step.boss .hs-dot{border-style:double;border-width:3px}
.hub-step[disabled]{cursor:not-allowed;opacity:.4}
.hub-step:not([disabled]):hover .hs-dot{transform:translateY(-3px) scale(1.06)}
@keyframes hubpulse{0%,100%{box-shadow:0 0 10px rgba(236,210,141,.3)}50%{box-shadow:0 0 20px rgba(236,210,141,.65)}}

.hub-deck{max-width:760px;margin:1rem auto}
.hub-deck summary{cursor:pointer;font-family:"Cinzel",serif;letter-spacing:.08em;color:var(--gold);
  text-align:center;text-transform:uppercase;font-size:.78rem}
</style>

<script src="/assets/campaign.js?v=5-ruin"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>
