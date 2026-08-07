/*
 * Faro SDK — tracker client first-party (~3KB non minificato).
 *
 * Uso nei giochi:
 *   <script src="//faro.tuodominio.it/sdk.js"
 *           data-app="varco"
 *           data-endpoint="//faro.tuodominio.it/collect.php"></script>
 *   <script> faro.track('game_start', { level: 1 }); </script>
 *
 * Cattura l'engagement che il server non vede (click, rewarded, tempo) e lo
 * spedisce in batch con navigator.sendBeacon, così l'evento parte anche alla
 * chiusura della scheda. Condivide cid/sid con il canale server (stessi cookie).
 */
(function () {
  "use strict";

  var s = document.currentScript || {};
  var ds = (s && s.dataset) || {};
  var cfg = window.FARO_CONFIG || {};
  var APP = cfg.app || ds.app || "";
  var ENDPOINT = cfg.endpoint || ds.endpoint || "/collect.php";
  var FLUSH_AT = cfg.flushAt || 10; // invia quando la coda raggiunge N eventi
  var queue = [];

  function uuid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function (c) {
      var r = (Math.random() * 16) | 0;
      return (c === "x" ? r : (r & 0x3) | 0x8).toString(16);
    });
  }

  function cookie(name) {
    var m = document.cookie.match("(?:^|; )" + name + "=([^;]*)");
    return m ? decodeURIComponent(m[1]) : "";
  }
  function setCookie(name, val, days) {
    var exp = days ? "; max-age=" + days * 86400 : "";
    document.cookie = name + "=" + encodeURIComponent(val) + "; path=/; samesite=lax" + exp;
  }

  // Identità: riusa i cookie del canale server; se assenti li crea (coerenza).
  function clientId() {
    var c = cookie("faro_cid");
    if (!c) { c = uuid(); setCookie("faro_cid", c, 365); }
    return c;
  }
  function sessionId() {
    var sid = cookie("faro_sid");
    if (!sid) { sid = uuid(); }
    setCookie("faro_sid", sid, 0); // rinnovato lato server a 30 min; qui solo fallback
    return sid;
  }

  // Sorgente: ?src=... nell'URL (first-touch) o cookie ereditato dal server.
  function src() {
    var q = (location.search.match(/[?&]src=([^&]+)/) || [])[1];
    if (q) { q = decodeURIComponent(q).toLowerCase(); setCookie("faro_src", q, 30); return q; }
    return cookie("faro_src") || "";
  }

  var flushTimer = null;
  // Invia presto gli eventi in coda (es. il pageview), senza aspettare la
  // chiusura della pagina: indispensabile per le anteprime in iframe, che
  // vengono distrutte (src=about:blank) senza un pagehide affidabile.
  function scheduleFlush() {
    if (flushTimer) return;
    flushTimer = setTimeout(function () { flushTimer = null; flush(); }, 1200);
  }

  function flush() {
    if (flushTimer) { clearTimeout(flushTimer); flushTimer = null; }
    if (!queue.length || !APP) return;
    var payload = {
      app: APP,
      cid: clientId(),
      sid: sessionId(),
      events: queue.splice(0, queue.length)
    };
    var data = JSON.stringify(payload);
    var ok = false;
    // text/plain = content-type "safelisted" => richiesta CORS semplice, niente
    // preflight: indispensabile perché il beacon cross-origin parta durante l'unload.
    if (navigator.sendBeacon) {
      ok = navigator.sendBeacon(ENDPOINT, new Blob([data], { type: "text/plain;charset=UTF-8" }));
    }
    if (!ok) {
      // Fallback: fetch keepalive (per chi non ha sendBeacon).
      try {
        fetch(ENDPOINT, { method: "POST", headers: { "Content-Type": "text/plain;charset=UTF-8" }, body: data, keepalive: true });
      } catch (e) { /* il tracking non deve mai rompere il gioco */ }
    }
  }

  function track(event, props) {
    if (!event) return;
    queue.push({ e: String(event), uid: uuid(), src: src(), props: props || null });
    if (queue.length >= FLUSH_AT) flush();
    else scheduleFlush();
  }

  // Tempo effettivo sulla pagina (conta solo mentre la scheda è visibile).
  var lastShow = Date.now(), engagedMs = 0, timeSent = false;
  function engagedSec() {
    var e = engagedMs;
    if (document.visibilityState !== "hidden") e += Date.now() - lastShow;
    return Math.round(e / 1000);
  }
  function sendTime() {
    if (timeSent) return; timeSent = true;
    track("page_time", { sec: engagedSec() });
  }

  // Invia alla chiusura/sospensione della pagina: è quando si perdono gli eventi.
  document.addEventListener("visibilitychange", function () {
    if (document.visibilityState === "hidden") { engagedMs += Date.now() - lastShow; sendTime(); flush(); }
    else { lastShow = Date.now(); }
  });
  window.addEventListener("pagehide", function () { sendTime(); flush(); });

  // Heartbeat: lascia un timestamp periodico finché la scheda è visibile. Così la
  // durata reale (MAX-MIN ts della sessione) si misura anche su una singola pagina
  // e anche quando l'evento di chiusura non parte — es. l'iframe dell'anteprima
  // distrutto con src=about:blank. Nessun prompt all'utente. Primo ping a 10s,
  // poi ogni 30s; salta quando la scheda è in background (non conta tempo morto).
  var beatN = 0;
  (function beat() {
    setTimeout(function () {
      if (document.visibilityState !== "hidden") track("ping", { sec: engagedSec() });
      beat();
    }, beatN++ === 0 ? 10000 : 30000);
  })();

  window.faro = { track: track, flush: flush };
})();
