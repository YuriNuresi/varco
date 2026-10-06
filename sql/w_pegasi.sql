-- Carte bianche custom: Pegasi (2a edizione)
-- 3×2mana, 4×3mana, 3×4mana, 1×5mana, 1×6mana = 12 carte

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Pegaso Minore', 2, 'W', 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 'common', 'Pegasus', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Pegaso del Varco', 2, 'W', 2, 1, 1, 0, 0, 0, 0, 0, 0, 0, 'common', 'Pegasus', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Pegaso Rapido', 2, 'W', 1, 2, 1, 0, 0, 0, 0, 1, 0, 0, 'uncommon', 'Pegasus', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Pegaso Guerriero', 3, 'W', 2, 2, 1, 0, 0, 0, 0, 0, 0, 0, 'common', 'Pegasus', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Pegaso Protettore', 3, 'W', 2, 3, 1, 0, 0, 0, 0, 1, 0, 0, 'uncommon', 'Pegasus', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Pegaso Radioso', 3, 'W', 3, 1, 1, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Pegasus', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Pegaso Maestoso', 3, 'W', 3, 2, 1, 0, 0, 0, 0, 0, 0, 0, 'rare', 'Pegasus', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Pegaso Celeste', 4, 'W', 3, 3, 1, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Pegasus', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Pegaso Divino', 4, 'W', 4, 3, 1, 0, 0, 0, 0, 0, 0, 0, 'rare', 'Pegasus', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Pegaso Sacro', 4, 'W', 3, 4, 1, 0, 0, 0, 0, 1, 0, 0, 'rare', 'Pegasus', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Re dei Pegasi', 5, 'W', 4, 4, 1, 0, 0, 0, 0, 1, 0, 0, 'rare', 'Pegasus', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Pegaso Eterno', 6, 'W', 5, 5, 1, 0, 0, 0, 0, 1, 0, 0, 'mythic', 'Pegasus', '', 1, 'custom');
