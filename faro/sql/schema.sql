-- Faro — schema Fase 0: il "libro mastro" degli eventi.
-- Importare in MySQL (phpMyAdmin su OVH). Convive nel DB condiviso accanto a
-- magic_* (varco) e h7_* (helios) grazie al prefisso faro_.
SET NAMES utf8mb4;

-- =============================================================================
-- faro_events — append-only. Ogni riga è un fatto immutabile; nessun UPDATE.
-- Tutte le metriche (DAU, retention, revenue/sorgente, eCPM) sono SELECT a valle.
-- =============================================================================
CREATE TABLE IF NOT EXISTS faro_events (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  app            VARCHAR(32)  NOT NULL,             -- 'varco' | 'intercity' | 'helios'
  client_id      CHAR(36)     NOT NULL,             -- identità anonima persistente (per dominio)
  session_id     CHAR(36)     NOT NULL,             -- una sessione di gioco
  event          VARCHAR(48)  NOT NULL,             -- 'pageview','game_start','ad_completed',...

  -- src = cittadino di prima classe: trasforma lo "spam alla cieca" in ROI misurabile.
  src            VARCHAR(64)  NOT NULL DEFAULT '',  -- sorgente acquisizione: 'reddit','itch',...

  props          JSON         NULL,                 -- payload libero specifico dell'evento

  -- Ricavo in MICRO-euro (1e-6). L'eCPM per impression è frazioni di centesimo:
  -- i micros sono lo standard dei network (AdSense/AdMob) e non perdono precisione.
  -- Valorizzato SOLO dal canale 's2s' (callback firmato). Mai dal client.
  revenue_micros BIGINT       NOT NULL DEFAULT 0,

  channel        ENUM('server','client','s2s') NOT NULL DEFAULT 'client',

  -- Anti-spam/dedup SENZA dati personali (GDPR-friendly): solo hash con salt.
  ip_hash        CHAR(16)     NULL,
  ua_hash        CHAR(16)     NULL,

  -- User-agent grezzo (troncato): NON è un identificatore personale, ma è il
  -- segnale più forte per distinguere bot/crawler da utenti reali nella
  -- tabella "ultime visite". Valorizzato a render-time (track.php) e dal beacon.
  ua             VARCHAR(255) NULL,

  -- Idempotenza: il beacon può rispedire lo stesso evento (retry / reload).
  -- UNIQUE + INSERT IGNORE => i duplicati non entrano. NULL ammessi multipli
  -- (gli eventi server senza uid non collidono mai tra loro).
  event_uid      CHAR(36)     NULL,

  ts             DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

  -- Dati dimostrativi (seed per demo/portfolio), tenuti separati dai dati reali.
  is_demo        TINYINT(1)   NOT NULL DEFAULT 0,

  UNIQUE KEY uq_event_uid (event_uid),
  KEY idx_demo (is_demo),
  KEY idx_app_ts       (app, ts),            -- finestra temporale per gioco
  KEY idx_app_src_ts   (app, src, ts),       -- acquisizione per sorgente
  KEY idx_app_event_ts (app, event, ts),     -- funnel / conteggi per evento
  KEY idx_session      (session_id),         -- ricostruzione sessione
  KEY idx_iphash_ts    (ip_hash, ts)         -- rate-limit anti-spam
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
