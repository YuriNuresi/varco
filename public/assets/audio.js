/* audio.js — effetti sonori SINTETIZZATI (Web Audio API). Solo battaglia (game.php).
   Nessun file: generati a runtime. La musica di sottofondo è gestita da player.js (barra in basso). */
(function () {
  'use strict';

  const SFX_VOL = 0.9; // volume master degli effetti
  let ctx = null, master = null;

  function ensure() {
    if (ctx) return ctx;
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return null;
    ctx = new AC();
    master = ctx.createGain();
    master.gain.value = SFX_VOL;
    master.connect(ctx.destination);
    return ctx;
  }
  function resume() { if (ctx && ctx.state === 'suspended') ctx.resume(); }

  // Inviluppo percussivo (attacco rapido, coda esponenziale).
  function env(g, t0, dur, peak) {
    g.gain.setValueAtTime(0.0001, t0);
    g.gain.exponentialRampToValueAtTime(Math.max(0.0002, peak), t0 + 0.008);
    g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
  }
  function tone(o) {
    const c = ensure(); if (!c) return;
    const t0 = c.currentTime + (o.when || 0);
    const dur = o.dur || 0.15;
    const osc = c.createOscillator(), g = c.createGain();
    osc.type = o.type || 'sine';
    osc.frequency.setValueAtTime(o.freq, t0);
    if (o.slideTo) osc.frequency.exponentialRampToValueAtTime(o.slideTo, t0 + dur);
    env(g, t0, dur, o.gain || 0.2);
    osc.connect(g); g.connect(master);
    osc.start(t0); osc.stop(t0 + dur + 0.03);
  }
  function noise(o) {
    const c = ensure(); if (!c) return;
    const t0 = c.currentTime + (o.when || 0);
    const dur = o.dur || 0.2;
    const n = Math.max(1, Math.floor(dur * c.sampleRate));
    const buf = c.createBuffer(1, n, c.sampleRate);
    const d = buf.getChannelData(0);
    for (let i = 0; i < n; i++) d[i] = (Math.random() * 2 - 1) * (1 - i / n); // rumore in decadimento
    const src = c.createBufferSource(); src.buffer = buf;
    const f = c.createBiquadFilter(); f.type = o.filter || 'highpass'; f.frequency.value = o.freq || 1200;
    const g = c.createGain(); env(g, t0, dur, o.gain || 0.2);
    src.connect(f); f.connect(g); g.connect(master);
    src.start(t0); src.stop(t0 + dur);
  }

  // Set effetti di battaglia (stile arcade/retro-fantasy).
  const SFX = {
    pick()    { tone({ freq: 520, type: 'square',   dur: 0.05, gain: 0.08 }); },
    play()    { tone({ freq: 330, type: 'triangle', dur: 0.12, gain: 0.20 });
                tone({ freq: 660, type: 'triangle', dur: 0.14, gain: 0.12, when: 0.05 }); },
    clash()   { noise({ dur: 0.16, gain: 0.26, filter: 'bandpass', freq: 2400 });
                tone({ freq: 220, type: 'sawtooth', dur: 0.20, gain: 0.16, slideTo: 90 }); },
    wound()   { noise({ dur: 0.22, gain: 0.24, filter: 'highpass', freq: 1600, when: 0.06 });
                tone({ freq: 160, type: 'sawtooth', dur: 0.16, gain: 0.12, slideTo: 70, when: 0.06 }); },
    mageHit() { tone({ freq: 120, type: 'sine', dur: 0.32, gain: 0.30, slideTo: 55 });
                noise({ dur: 0.14, gain: 0.14, filter: 'lowpass', freq: 500 }); },
    win()     { [523, 659, 784, 1047].forEach((f, i) => tone({ freq: f, type: 'triangle', dur: 0.30, gain: 0.22, when: i * 0.12 })); },
    lose()    { [392, 330, 262, 174].forEach((f, i) => tone({ freq: f, type: 'sine',     dur: 0.36, gain: 0.22, when: i * 0.14 })); },
  };

  // Sblocca l'AudioContext al primo gesto utente (policy autoplay).
  const unlock = () => { resume(); window.removeEventListener('pointerdown', unlock); window.removeEventListener('keydown', unlock); };
  window.addEventListener('pointerdown', unlock);
  window.addEventListener('keydown', unlock);

  window.SFX = SFX;
})();
