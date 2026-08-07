/* player.js — barra musicale di sottofondo di varco (base: il player del footer di portale3d,
   ri-tematizzato oro/scuro). Playlist scelta per CONTESTO:
     • battaglia (body.page-battle): round 1-3 → varco1/2 (random), round 4+ → varco3/4 (random);
     • altre pagine: varco5/6 (random).
   Volume di default molto basso. Espone window.Music (usato da app.js: Music.setRound). */
(function () {
  'use strict';
  const bar = document.getElementById('varco-player');
  if (!bar) return;

  const pPlay = document.getElementById('pPlay'), pEq = document.getElementById('pEq'),
        pTitle = document.getElementById('pTitle'), pSkip = document.getElementById('pSkip'),
        pVol = document.getElementById('pVol'), pVolN = document.getElementById('pVolN');

  const VOL_KEY = 'varco_music_vol', PAUSE_KEY = 'varco_music_paused';
  const DIR = '/assets/music/';
  const POOLS = {
    early: [{ f: 'varco1.mp3', t: 'Varco I' },   { f: 'varco2.mp3', t: 'Varco II' }],   // round 1-3
    late:  [{ f: 'varco3.mp3', t: 'Varco III' }, { f: 'varco4.mp3', t: 'Varco IV' }],   // round 4+
    pages: [{ f: 'varco5.mp3', t: 'Varco V' },   { f: 'varco6.mp3', t: 'Varco VI' }],   // fuori battaglia
  };

  const isBattle = document.body.classList.contains('page-battle');
  let phase = isBattle ? 'early' : 'pages';
  let current = null;
  let userPaused = localStorage.getItem(PAUSE_KEY) === '1';

  const audio = new Audio();
  audio.preload = 'auto';

  // Volume: default molto basso (15%), memorizzato.
  const savedVol = parseInt(localStorage.getItem(VOL_KEY), 10);
  let vol = isFinite(savedVol) ? savedVol : 15;
  pVol.value = vol; pVolN.textContent = vol; audio.volume = vol / 100;

  bar.hidden = false;

  function pick() {
    const pool = POOLS[phase] || POOLS.pages;
    let t = pool[Math.floor(Math.random() * pool.length)];
    if (pool.length > 1 && current && t.f === current.f) t = pool[(pool.indexOf(t) + 1) % pool.length];
    return t;
  }
  function load(t, play) {
    current = t;
    audio.src = DIR + t.f;
    pTitle.textContent = t.t;
    if (play) tryPlay();
  }
  function tryPlay() {
    if (userPaused) return;
    audio.play().catch(function () {
      const g = function () { if (!userPaused) audio.play().catch(function () {}); window.removeEventListener('pointerdown', g); window.removeEventListener('keydown', g); };
      window.addEventListener('pointerdown', g);
      window.addEventListener('keydown', g);
    });
  }

  audio.addEventListener('ended', function () { load(pick(), true); }); // varietà: nuova random dalla fase
  function setPlaying(on) { pPlay.textContent = on ? '❚❚' : '▶'; pEq.classList.toggle('paused', !on); }
  audio.addEventListener('play', function () { setPlaying(true); });
  audio.addEventListener('pause', function () { setPlaying(false); });
  audio.addEventListener('error', function () { pTitle.textContent = '—'; });

  pPlay.onclick = function () {
    if (!audio.src) load(pick(), false);
    if (audio.paused) { userPaused = false; localStorage.setItem(PAUSE_KEY, '0'); tryPlay(); }
    else { userPaused = true; localStorage.setItem(PAUSE_KEY, '1'); audio.pause(); }
  };
  pSkip.onclick = function () { load(pick(), true); };
  pVol.oninput = function () {
    vol = parseInt(pVol.value, 10) || 0;
    pVolN.textContent = vol; audio.volume = vol / 100;
    localStorage.setItem(VOL_KEY, vol);
  };

  // In background: metti in pausa; al ritorno riprendi (se non messo in pausa a mano).
  document.addEventListener('visibilitychange', function () {
    if (!audio.src) return;
    if (document.hidden) { if (!audio.paused) { audio._wp = true; audio.pause(); } }
    else if (audio._wp) { if (!userPaused) audio.play().catch(function () {}); audio._wp = false; }
  });

  // Avvio: carica un brano della fase corrente e prova a suonare (parte al primo gesto se bloccato).
  load(pick(), true);

  // API per la battaglia (app.js chiama Music.setRound a ogni cambio round).
  window.Music = {
    setRound: function (n) {
      if (!isBattle) return;
      const ph = n >= 3 ? 'late' : 'early'; // dal turno 3 (quando si ricevono i bonus)
      if (ph !== phase) { phase = ph; load(pick(), true); } // cambia colonna sonora con la fase
    },
    phase: function () { return phase; },
  };
})();
