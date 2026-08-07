-- Carte bianche custom: Angeli, Templari a Cavallo, Uccelli
-- 3×2mana, 4×3mana, 3×4mana, 2×5-6mana per tipo = 36 carte

-- ==================== ANGELI ====================

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Angelo della Luce', 2, 'W', 2, 1, 1, 0, 0, 0, 0, 1, 0, 0, 'common', 'Angel', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Sentinella Celeste', 2, 'W', 1, 2, 1, 0, 0, 0, 0, 0, 0, 0, 'common', 'Angel', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Angelo del Varco', 2, 'W', 2, 2, 1, 0, 0, 0, 0, 1, 0, 0, 'uncommon', 'Angel', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Angelo Guerriero', 3, 'W', 2, 2, 1, 1, 0, 0, 0, 0, 0, 0, 'uncommon', 'Angel', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Guardiano Alato', 3, 'W', 3, 2, 1, 0, 0, 0, 0, 1, 0, 0, 'common', 'Angel', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Angelo Benedetto', 3, 'W', 2, 3, 1, 0, 0, 0, 0, 1, 0, 0, 'uncommon', 'Angel', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Arcangelo Divino', 3, 'W', 3, 3, 1, 0, 0, 0, 0, 1, 0, 0, 'rare', 'Angel', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Angelo della Giustizia', 4, 'W', 4, 3, 1, 1, 0, 0, 0, 0, 0, 0, 'uncommon', 'Angel', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Signore degli Angeli', 4, 'W', 4, 4, 1, 0, 0, 0, 0, 1, 0, 0, 'rare', 'Angel', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Angelo Redemptore', 4, 'W', 3, 5, 1, 0, 0, 0, 0, 1, 0, 0, 'rare', 'Angel', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Arcangelo della Salvezza', 5, 'W', 5, 4, 1, 1, 0, 0, 0, 1, 0, 0, 'mythic', 'Angel', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Principe Celeste', 6, 'W', 6, 5, 1, 0, 0, 0, 0, 1, 0, 0, 'mythic', 'Angel', '', 1, 'custom');

-- ==================== TEMPLARI A CAVALLO ====================

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Cavaliere Templare', 2, 'W', 2, 1, 0, 1, 0, 0, 0, 0, 0, 0, 'common', 'Knight', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Paladino del Varco', 2, 'W', 1, 2, 0, 0, 0, 0, 0, 1, 0, 0, 'common', 'Knight', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Guerriero del Tempio', 2, 'W', 2, 2, 0, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Knight', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Cavaliere Santo', 3, 'W', 3, 2, 0, 1, 0, 0, 0, 0, 0, 0, 'uncommon', 'Knight', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Lanciere Devoto', 3, 'W', 2, 3, 0, 0, 0, 0, 0, 1, 0, 0, 'common', 'Knight', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Campione Cavalleresco', 3, 'W', 3, 2, 0, 1, 0, 0, 0, 1, 0, 0, 'uncommon', 'Knight', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Maestro Templare', 3, 'W', 3, 3, 0, 0, 0, 0, 0, 0, 0, 0, 'rare', 'Knight', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Cavaliere Cristallino', 4, 'W', 4, 3, 0, 1, 0, 0, 0, 0, 0, 0, 'uncommon', 'Knight', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Signore dei Cavalieri', 4, 'W', 4, 4, 0, 0, 0, 0, 0, 1, 0, 0, 'rare', 'Knight', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Cavaliere Redentore', 4, 'W', 3, 5, 0, 0, 0, 0, 0, 1, 0, 0, 'rare', 'Knight', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Grande Maestro dei Templari', 5, 'W', 5, 4, 0, 1, 0, 0, 0, 1, 0, 0, 'mythic', 'Knight', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Principe dei Cavalieri Templari', 6, 'W', 6, 5, 0, 1, 0, 0, 0, 1, 0, 0, 'mythic', 'Knight', '', 1, 'custom');

-- ==================== UCCELLI ====================

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Uccello della Pace', 2, 'W', 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 'common', 'Bird', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Colomba del Varco', 2, 'W', 2, 1, 1, 0, 0, 0, 0, 0, 0, 0, 'common', 'Bird', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Aquila Celestiale', 2, 'W', 2, 2, 1, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Bird', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Uccello Luminoso', 3, 'W', 2, 2, 1, 0, 0, 0, 0, 1, 0, 0, 'uncommon', 'Bird', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Corvo Divino', 3, 'W', 3, 2, 1, 0, 0, 0, 0, 0, 0, 0, 'common', 'Bird', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Uccello del Paradiso', 3, 'W', 2, 3, 1, 0, 0, 0, 0, 1, 0, 0, 'uncommon', 'Bird', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Regina degli Uccelli', 3, 'W', 3, 3, 1, 0, 0, 0, 0, 0, 0, 0, 'rare', 'Bird', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Aquila Benedetta', 4, 'W', 4, 3, 1, 1, 0, 0, 0, 0, 0, 0, 'uncommon', 'Bird', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Signore degli Uccelli', 4, 'W', 4, 4, 1, 0, 0, 0, 0, 1, 0, 0, 'rare', 'Bird', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Uccello Celeste Protettore', 4, 'W', 3, 5, 1, 0, 0, 0, 0, 1, 0, 0, 'rare', 'Bird', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Grande Aquila Divina', 5, 'W', 5, 4, 1, 1, 0, 0, 0, 1, 0, 0, 'mythic', 'Bird', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fenice Celeste', 6, 'W', 6, 5, 1, 0, 0, 0, 0, 1, 0, 0, 'mythic', 'Bird', '', 1, 'custom');
