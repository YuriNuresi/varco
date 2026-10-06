-- Carte verdi custom: Idre (2a edizione)
-- 3×2mana, 4×3mana, 3×4mana, 1×5mana, 1×6mana = 12 carte

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Idra Minore', 2, 'G', 1, 1, 0, 0, 0, 1, 0, 0, 0, 0, 'common', 'Hydra', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Idra del Varco', 2, 'G', 2, 1, 0, 0, 0, 1, 0, 0, 0, 0, 'common', 'Hydra', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Idra Bicipite', 2, 'G', 1, 2, 0, 0, 0, 1, 0, 0, 0, 0, 'uncommon', 'Hydra', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Idra Feroce', 3, 'G', 2, 2, 0, 0, 0, 1, 0, 0, 0, 0, 'common', 'Hydra', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Idra Velenosa', 3, 'G', 2, 3, 0, 0, 0, 1, 0, 0, 0, 0, 'uncommon', 'Hydra', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Idra delle Paludi', 3, 'G', 3, 1, 0, 0, 0, 1, 0, 0, 1, 0, 'uncommon', 'Hydra', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Idra Corazzata', 3, 'G', 3, 2, 0, 0, 0, 1, 0, 0, 0, 0, 'rare', 'Hydra', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Idra Furiosa', 4, 'G', 3, 3, 0, 0, 0, 1, 0, 0, 0, 0, 'uncommon', 'Hydra', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Idra Ancestrale', 4, 'G', 4, 3, 0, 0, 0, 1, 0, 0, 0, 0, 'rare', 'Hydra', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Idra Titanica', 4, 'G', 3, 4, 0, 0, 0, 1, 0, 1, 0, 0, 'rare', 'Hydra', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Idra Suprema', 5, 'G', 4, 4, 0, 0, 0, 1, 0, 0, 1, 0, 'rare', 'Hydra', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Idra Primordiale', 6, 'G', 5, 5, 0, 0, 0, 1, 0, 1, 1, 0, 'mythic', 'Hydra', '', 1, 'custom');
