-- Carte blu custom: Fate
-- 3×2mana, 4×3mana, 3×4mana, 2×5-6mana = 12 carte

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fata della Magia', 2, 'U', 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 'common', 'Faerie', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fata del Varco', 2, 'U', 2, 1, 1, 0, 0, 0, 0, 0, 0, 0, 'common', 'Faerie', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fata Capricciosa', 2, 'U', 1, 2, 1, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Faerie', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fata Illusionista', 3, 'U', 2, 1, 1, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Faerie', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fata Maliarda', 3, 'U', 2, 2, 1, 0, 0, 0, 0, 0, 0, 0, 'common', 'Faerie', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fata Ingannatrice', 3, 'U', 3, 1, 1, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Faerie', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Regina delle Fate', 3, 'U', 2, 2, 1, 0, 0, 0, 0, 0, 0, 0, 'rare', 'Faerie', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fata Antica', 4, 'U', 3, 2, 1, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Faerie', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fata Titano', 4, 'U', 4, 3, 1, 0, 0, 0, 0, 0, 0, 0, 'rare', 'Faerie', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fata Eterna', 4, 'U', 3, 4, 1, 0, 0, 0, 0, 0, 0, 0, 'rare', 'Faerie', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Sovrana dei Regni Fatati', 5, 'U', 4, 4, 1, 0, 0, 0, 0, 0, 0, 0, 'rare', 'Faerie', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Titania la Fatata', 6, 'U', 5, 5, 1, 0, 0, 0, 0, 0, 0, 0, 'mythic', 'Faerie', '', 1, 'custom');
