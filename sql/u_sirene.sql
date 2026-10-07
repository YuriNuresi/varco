-- Carte blu custom: Sirene (2a edizione)
-- 3×2mana, 4×3mana, 3×4mana, 1×5mana, 1×6mana = 12 carte

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Sirena Minore', 2, 'U', 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 'common', 'Siren', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Sirena del Varco', 2, 'U', 2, 1, 1, 0, 0, 0, 0, 0, 0, 0, 'common', 'Siren', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Sirena Melodica', 2, 'U', 1, 2, 1, 0, 0, 0, 0, 1, 0, 0, 'uncommon', 'Siren', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Sirena Notturna', 3, 'U', 2, 2, 1, 0, 0, 0, 0, 0, 0, 0, 'common', 'Siren', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Sirena Incantatrice', 3, 'U', 2, 3, 1, 0, 0, 0, 0, 1, 0, 0, 'uncommon', 'Siren', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Sirena delle Tempeste', 3, 'U', 3, 1, 1, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Siren', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Sirena Antica', 3, 'U', 3, 2, 1, 0, 0, 0, 0, 0, 0, 0, 'rare', 'Siren', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Sirena Glaciale', 4, 'U', 3, 3, 1, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Siren', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Sirena Abissale', 4, 'U', 4, 3, 1, 0, 0, 0, 0, 0, 0, 0, 'rare', 'Siren', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Sirena della Nebbia', 4, 'U', 3, 4, 1, 0, 0, 0, 0, 1, 0, 0, 'rare', 'Siren', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Regina delle Sirene', 5, 'U', 4, 4, 1, 0, 0, 0, 0, 1, 0, 0, 'rare', 'Siren', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Sirena Primordiale', 6, 'U', 5, 5, 1, 0, 0, 0, 0, 1, 0, 0, 'mythic', 'Siren', '', 1, 'custom');
