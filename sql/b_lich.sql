-- Carte nere custom: Lich (2a edizione)
-- 3×2mana, 4×3mana, 3×4mana, 1×5mana, 1×6mana = 12 carte

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Lich Minore', 2, 'B', 1, 1, 0, 0, 1, 0, 0, 0, 0, 0, 'common', 'Lich', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Lich del Varco', 2, 'B', 2, 1, 0, 0, 1, 0, 0, 0, 0, 0, 'common', 'Lich', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Lich Strisciante', 2, 'B', 1, 2, 0, 0, 1, 0, 0, 0, 0, 0, 'uncommon', 'Lich', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Lich Oscuro', 3, 'B', 2, 2, 0, 0, 1, 0, 0, 0, 0, 0, 'common', 'Lich', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Lich Maledetto', 3, 'B', 2, 3, 0, 0, 1, 0, 0, 0, 0, 0, 'uncommon', 'Lich', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Lich Affamato', 3, 'B', 3, 1, 0, 0, 1, 0, 0, 1, 0, 0, 'uncommon', 'Lich', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Lich Antico', 3, 'B', 3, 2, 0, 0, 1, 0, 0, 0, 0, 0, 'rare', 'Lich', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Lich Supremo', 4, 'B', 3, 3, 0, 0, 1, 0, 0, 0, 0, 0, 'uncommon', 'Lich', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Lich Immortale', 4, 'B', 4, 3, 0, 0, 1, 0, 0, 0, 0, 0, 'rare', 'Lich', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Lich del Gelo', 4, 'B', 3, 4, 0, 0, 1, 0, 0, 1, 0, 0, 'rare', 'Lich', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Signore dei Lich', 5, 'B', 4, 4, 0, 0, 1, 0, 0, 1, 0, 0, 'rare', 'Lich', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Lich Primordiale', 6, 'B', 5, 5, 0, 0, 1, 0, 0, 1, 0, 0, 'mythic', 'Lich', '', 1, 'custom');
