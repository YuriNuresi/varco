# Faro — gestionale traffico e monetizzazione cross-game

Console unica per analizzare traffico e ricavi dei giochi (varco, intercity, helios)
e capire **dove ri-postare** (acquisizione per sorgente) e **quale network rende di
più** (mediation). Hook centrale: un Analyst AI che interroga i dati in linguaggio
naturale e scrive un briefing via mail ogni mattina.

Questa cartella è la **Fase 0**: il fondamento dati (raccolta eventi). Si deploya
sul proprio sottodominio OVH e riusa il MySQL condiviso (prefisso tabella `faro_`).

## Architettura della raccolta (ibrida, first-party)

Tre canali, un solo collector → un'unica tabella append-only `faro_events`:

| Canale | File | Cosa raccoglie | Perché |
|---|---|---|---|
| server | `track.php` (`faro_track()`) | pageview + `src` | immune ad adblock = verità di base |
| client | `public/sdk.js` → `public/collect.php` | engagement, click, rewarded | ciò che il server non vede |
| s2s | `public/revenue.php` | `revenue_micros` reale dai network | i soldi non passano mai dal client |

Il writer condiviso è `src/collector.php` (validazione, anti-spam, idempotenza).

## File

```
faro/
  config.php            credenziali (dal .env condiviso) + costanti
  src/db.php            PDO singleton (faro_db())
  src/collector.php     COLLECTOR: validazione + idempotenza + scrittura
  public/collect.php    entrypoint beacon client (CORS, batch)
  public/revenue.php    entrypoint callback revenue S2S (HMAC)
  public/sdk.js         tracker client first-party (~3KB)
  track.php             helper server-side da require nei giochi
  sql/schema.sql        tabella faro_events
```

## Deploy su OVH

1. Importa `sql/schema.sql` nel MySQL condiviso (phpMyAdmin).
2. Carica la cartella `faro/` sul sottodominio (es. `faro.tuodominio.it` → docroot su `faro/public`).
3. Nel `.env` di root (condiviso) compila: `FARO_IP_SALT`, `FARO_S2S_SECRET`,
   `FARO_ALLOWED_ORIGINS`. Imposta `FARO_DEBUG = false` in `config.php`.

## Integrare un gioco (2 righe)

Lato server, in cima alla pagina (canale affidabile):
```php
<?php require __DIR__ . '/../faro/track.php'; faro_track('varco'); ?>
```

Lato client, prima di `</body>` (engagement):
```html
<script src="//faro.tuodominio.it/sdk.js" data-app="varco"
        data-endpoint="//faro.tuodominio.it/collect.php"></script>
<script>
  faro.track('game_start', { level: 1 });
  // alla fine di una rewarded:
  faro.track('ad_completed', { format: 'rewarded', placement: 'continue' });
</script>
```

Quando posti il link su un canale, tagga la sorgente: `...?src=reddit`. Faro la
cattura (first-touch) e la lega a tutti gli eventi e ai ricavi di quel visitatore.

## Eventi consigliati (convenzione)

`pageview` (auto, server) · `game_start` · `level_complete` · `session_end` ·
`ad_request` · `ad_shown` · `ad_completed` · `ad_revenue` (solo via S2S).

## Note di sicurezza

- `revenue_micros` accettato **solo** dal canale `s2s`, con firma HMAC verificata.
- IP/UA salvati solo come hash con salt (no PII → GDPR-friendly).
- Idempotenza via `event_uid` UNIQUE + `INSERT IGNORE` (i retry non duplicano).
- Rate-limit per IP (`FARO_RATE_*`). Upgrade futuro: anti-adblock "vero" servendo
  `sdk.js` e `collect.php` da un path first-party sul dominio del gioco (reverse proxy).

## Prossime fasi

1. `metrics.php` — query DAU/retention/revenue-per-sorgente/eCPM.
2. dashboard (i mockup già visti).
3. `ask.php` — Analyst NL→SELECT whitelisted.
4. `digest.php` — briefing mattutino via cron.
