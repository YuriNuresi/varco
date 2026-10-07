-- Carte blu custom: Kraken (2a edizione)
-- 3×2mana, 4×3mana, 3×4mana, 1×5mana, 1×6mana = 12 carte

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Kraken Minore', 2, 'U', 1, 1, 0, 0, 0, 0, 0, 0, 1, 0, 'common', 'Kraken', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Kraken del Varco', 2, 'U', 2, 1, 0, 0, 0, 0, 0, 0, 1, 0, 'common', 'Kraken', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Kraken Sentinella', 2, 'U', 1, 2, 0, 0, 0, 0, 0, 0, 1, 0, 'uncommon', 'Kraken', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Kraken Cacciatore', 3, 'U', 2, 2, 0, 0, 0, 0, 0, 0, 1, 0, 'common', 'Kraken', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Kraken Strangolatore', 3, 'U', 2, 3, 0, 0, 1, 0, 0, 0, 1, 0, 'uncommon', 'Kraken', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Kraken Vorace', 3, 'U', 3, 1, 0, 0, 1, 0, 0, 0, 1, 0, 'uncommon', 'Kraken', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Kraken Corallo', 3, 'U', 3, 2, 0, 0, 0, 0, 0, 0, 1, 0, 'rare', 'Kraken', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Kraken Abissale', 4, 'U', 3, 3, 0, 0, 0, 0, 0, 0, 1, 0, 'uncommon', 'Kraken', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Kraken Gigante', 4, 'U', 4, 3, 0, 0, 0, 0, 0, 0, 1, 0, 'rare', 'Kraken', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Kraken delle Tempeste', 4, 'U', 3, 4, 0, 0, 0, 0, 0, 0, 1, 1, 'rare', 'Kraken', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Kraken Titanico', 5, 'U', 4, 4, 0, 0, 1, 0, 0, 0, 1, 0, 'rare', 'Kraken', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Kraken Primordiale', 6, 'U', 5, 5, 0, 0, 1, 1, 0, 0, 1, 0, 'mythic', 'Kraken', '', 1, 'custom');
