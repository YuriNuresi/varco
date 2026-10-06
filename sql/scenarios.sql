-- Campagna 2.0 — tabella VILLAGGI (scenari) + 5 villaggi iniziali (uno per valle).
-- Lanciare in phpMyAdmin su OVH. Rilanciabile: l'INSERT iniziale è protetto da "non esiste già".
-- Modello: un villaggio = tribù unica, `levels` JSON = livelli crescenti (ultimo boss). Vedi src/Scenarios.php.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS magic_scenarios (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  valley   CHAR(1)      NOT NULL,                 -- W/U/B/R/G
  name     VARCHAR(120) NOT NULL,
  tribe    VARCHAR(40)  NOT NULL DEFAULT '',      -- sottotipo (es. 'Goblin'); vuoto = qualsiasi creatura del colore
  ord      INT          NOT NULL DEFAULT 1,       -- posizione del villaggio nella valle
  levels   JSON         NOT NULL,                 -- [{name,rarities[],max_power,max_mv,size,boss?,card_ids?}, ...]
  enabled  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at TIMESTAMP  DEFAULT CURRENT_TIMESTAMP,
  INDEX (valley), INDEX (enabled), INDEX (ord)
);

-- Villaggi iniziali: rispecchiano i DEFAULT di config (CAMPAIGN_VALLEY_TRIBES + CAMPAIGN_VILLAGE_LEVELS).
-- Inseriti solo se la tabella è ancora vuota, per non duplicare a ogni rilancio.
INSERT INTO magic_scenarios (valley, name, tribe, ord, levels)
SELECT * FROM (
  SELECT 'R' AS valley, 'Accampamento Goblin' AS name, 'Goblin' AS tribe, 1 AS ord,
    CAST('[{"name":"Reclute","rarities":["common"],"max_power":1,"max_mv":2,"size":10},{"name":"Guerrieri","rarities":["common"],"max_power":2,"max_mv":3,"size":11},{"name":"Veterani","rarities":["common","uncommon"],"max_power":3,"max_mv":4,"size":12},{"name":"Campioni","rarities":["common","uncommon"],"max_power":4,"max_mv":5,"size":13},{"name":"Élite","rarities":["common","uncommon","rare"],"max_power":6,"max_mv":6,"size":14},{"name":"Capo","rarities":["common","uncommon","rare","mythic"],"max_power":99,"max_mv":9,"size":16,"boss":true}]' AS JSON) AS levels
  UNION ALL SELECT 'W','Avamposto dei Soldati','Soldier',1,
    CAST('[{"name":"Reclute","rarities":["common"],"max_power":1,"max_mv":2,"size":10},{"name":"Guerrieri","rarities":["common"],"max_power":2,"max_mv":3,"size":11},{"name":"Veterani","rarities":["common","uncommon"],"max_power":3,"max_mv":4,"size":12},{"name":"Campioni","rarities":["common","uncommon"],"max_power":4,"max_mv":5,"size":13},{"name":"Élite","rarities":["common","uncommon","rare"],"max_power":6,"max_mv":6,"size":14},{"name":"Capo","rarities":["common","uncommon","rare","mythic"],"max_power":99,"max_mv":9,"size":16,"boss":true}]' AS JSON)
  UNION ALL SELECT 'U','Torre dei Maghi','Wizard',1,
    CAST('[{"name":"Reclute","rarities":["common"],"max_power":1,"max_mv":2,"size":10},{"name":"Guerrieri","rarities":["common"],"max_power":2,"max_mv":3,"size":11},{"name":"Veterani","rarities":["common","uncommon"],"max_power":3,"max_mv":4,"size":12},{"name":"Campioni","rarities":["common","uncommon"],"max_power":4,"max_mv":5,"size":13},{"name":"Élite","rarities":["common","uncommon","rare"],"max_power":6,"max_mv":6,"size":14},{"name":"Capo","rarities":["common","uncommon","rare","mythic"],"max_power":99,"max_mv":9,"size":16,"boss":true}]' AS JSON)
  UNION ALL SELECT 'B','Cripta degli Zombi','Zombie',1,
    CAST('[{"name":"Reclute","rarities":["common"],"max_power":1,"max_mv":2,"size":10},{"name":"Guerrieri","rarities":["common"],"max_power":2,"max_mv":3,"size":11},{"name":"Veterani","rarities":["common","uncommon"],"max_power":3,"max_mv":4,"size":12},{"name":"Campioni","rarities":["common","uncommon"],"max_power":4,"max_mv":5,"size":13},{"name":"Élite","rarities":["common","uncommon","rare"],"max_power":6,"max_mv":6,"size":14},{"name":"Capo","rarities":["common","uncommon","rare","mythic"],"max_power":99,"max_mv":9,"size":16,"boss":true}]' AS JSON)
  UNION ALL SELECT 'G','Boschetto degli Elfi','Elf',1,
    CAST('[{"name":"Reclute","rarities":["common"],"max_power":1,"max_mv":2,"size":10},{"name":"Guerrieri","rarities":["common"],"max_power":2,"max_mv":3,"size":11},{"name":"Veterani","rarities":["common","uncommon"],"max_power":3,"max_mv":4,"size":12},{"name":"Campioni","rarities":["common","uncommon"],"max_power":4,"max_mv":5,"size":13},{"name":"Élite","rarities":["common","uncommon","rare"],"max_power":6,"max_mv":6,"size":14},{"name":"Capo","rarities":["common","uncommon","rare","mythic"],"max_power":99,"max_mv":9,"size":16,"boss":true}]' AS JSON)
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM magic_scenarios);
