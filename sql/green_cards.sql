-- Carte verdi custom: Bestie, Dinosauri, Elementali, Ragni
-- 9 carte per tipo = 36 carte totali (mini-mazzo)

-- ==================== BESTIE ====================

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Bestia della Foresta', 2, 'G', 2, 2, 0, 0, 0, 0, 0, 0, 0, 0, 'common', 'Beast', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Bestia del Varco', 2, 'G', 1, 3, 0, 0, 0, 0, 0, 0, 0, 0, 'common', 'Beast', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Predatore della Giungla', 2, 'G', 2, 1, 0, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Beast', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Bestia Selvaggia', 3, 'G', 3, 2, 0, 0, 0, 1, 0, 0, 0, 0, 'common', 'Beast', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Cacciatore Primitivo', 3, 'G', 2, 3, 0, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Beast', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Signore delle Bestie', 3, 'G', 3, 3, 0, 0, 0, 1, 0, 0, 0, 0, 'rare', 'Beast', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Bestia Titano', 4, 'G', 4, 4, 0, 0, 0, 1, 0, 0, 0, 0, 'rare', 'Beast', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Mostro Primordiale', 5, 'G', 5, 4, 0, 0, 0, 1, 0, 0, 0, 0, 'mythic', 'Beast', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Sovrano della Foresta Selvaggia', 6, 'G', 6, 5, 0, 0, 0, 1, 0, 0, 0, 0, 'mythic', 'Beast', '', 1, 'custom');

-- ==================== DINOSAURI ====================

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Dinosauro Minore', 2, 'G', 2, 1, 0, 0, 0, 0, 0, 0, 0, 0, 'common', 'Dinosaur', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Tirannosauro del Varco', 2, 'G', 3, 1, 0, 0, 0, 0, 0, 0, 0, 0, 'common', 'Dinosaur', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Sauro della Preistoria', 2, 'G', 2, 2, 0, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Dinosaur', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Dinosauro della Giungla', 3, 'G', 3, 3, 0, 0, 0, 1, 0, 0, 0, 0, 'common', 'Dinosaur', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Rettile Antico', 3, 'G', 2, 4, 0, 0, 0, 0, 0, 0, 0, 1, 'uncommon', 'Dinosaur', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Dinosauro Terribile', 3, 'G', 4, 2, 0, 0, 0, 1, 0, 0, 0, 0, 'rare', 'Dinosaur', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Dinosauro Colossale', 4, 'G', 5, 4, 0, 0, 0, 1, 0, 0, 0, 0, 'rare', 'Dinosaur', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Re dei Dinosauri', 5, 'G', 5, 5, 0, 0, 0, 1, 0, 0, 0, 0, 'mythic', 'Dinosaur', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Tiranno della Preistoria', 6, 'G', 7, 5, 0, 0, 0, 1, 0, 0, 0, 0, 'mythic', 'Dinosaur', '', 1, 'custom');

-- ==================== ELEMENTALI ====================

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Elementale della Natura', 2, 'G', 2, 2, 0, 0, 0, 0, 0, 0, 0, 0, 'common', 'Elemental', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Elementale del Varco', 2, 'G', 1, 2, 0, 0, 0, 0, 0, 0, 0, 0, 'common', 'Elemental', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Spirito della Terra', 2, 'G', 2, 1, 0, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Elemental', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Elementale Vegetale', 3, 'G', 2, 3, 0, 0, 0, 0, 0, 0, 1, 0, 'common', 'Elemental', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Elementale del Bosco', 3, 'G', 3, 2, 0, 0, 0, 1, 0, 0, 0, 0, 'uncommon', 'Elemental', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Signore Elementale della Natura', 3, 'G', 3, 3, 0, 0, 0, 1, 0, 0, 0, 0, 'rare', 'Elemental', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Elementale Titano', 4, 'G', 4, 5, 0, 0, 0, 0, 0, 0, 0, 1, 'rare', 'Elemental', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Avatar della Natura', 5, 'G', 5, 5, 0, 0, 0, 1, 0, 0, 0, 0, 'mythic', 'Elemental', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Spirito Primordiale del Bosco', 6, 'G', 5, 6, 0, 0, 0, 1, 0, 0, 0, 0, 'mythic', 'Elemental', '', 1, 'custom');

-- ==================== RAGNI ====================

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Ragno della Foresta', 2, 'G', 1, 2, 0, 0, 0, 0, 0, 0, 1, 0, 'common', 'Spider', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Ragno del Varco', 2, 'G', 2, 2, 0, 0, 0, 0, 0, 0, 1, 0, 'common', 'Spider', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Aracnide Velenoso', 2, 'G', 1, 3, 0, 0, 1, 0, 0, 0, 1, 0, 'uncommon', 'Spider', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Ragno Tessitore', 3, 'G', 2, 3, 0, 0, 0, 0, 0, 0, 1, 1, 'common', 'Spider', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Ragno Gigante', 3, 'G', 3, 2, 0, 0, 0, 0, 0, 0, 1, 0, 'uncommon', 'Spider', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Signore dei Ragni', 3, 'G', 2, 4, 0, 0, 1, 0, 0, 0, 1, 0, 'rare', 'Spider', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Ragno Colossale', 4, 'G', 4, 4, 0, 0, 0, 0, 0, 0, 1, 0, 'rare', 'Spider', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Regina dei Ragni', 5, 'G', 4, 5, 0, 0, 1, 0, 0, 0, 1, 0, 'mythic', 'Spider', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Matriarca della Ragnatela', 6, 'G', 5, 6, 0, 0, 1, 0, 0, 0, 1, 0, 'mythic', 'Spider', '', 1, 'custom');
