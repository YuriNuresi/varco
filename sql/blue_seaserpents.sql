-- Carte blu custom: Mostri Marini
-- 3×2mana, 4×3mana, 3×4mana, 2×5-6mana = 12 carte

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Bestia Marina Minore', 2, 'U', 2, 2, 0, 0, 0, 0, 0, 0, 0, 0, 'common', 'Leviathan', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Mostro del Varco', 2, 'U', 1, 3, 0, 0, 0, 0, 0, 0, 0, 0, 'common', 'Leviathan', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Bestia Marina Predatrice', 2, 'U', 2, 1, 0, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Leviathan', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Serpente Marino', 3, 'U', 3, 2, 0, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Leviathan', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Mostro Abissale', 3, 'U', 4, 3, 0, 0, 0, 0, 0, 0, 0, 0, 'common', 'Leviathan', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Bestia Marina Terribile', 3, 'U', 3, 3, 0, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Leviathan', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Leviatano Antico', 3, 'U', 2, 4, 0, 0, 0, 0, 0, 0, 0, 1, 'rare', 'Leviathan', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Bestia Colossale del Mare', 4, 'U', 5, 4, 0, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Leviathan', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Mostro Primordiale del Mare', 4, 'U', 4, 5, 0, 0, 0, 0, 0, 0, 0, 0, 'rare', 'Leviathan', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Terrore degli Abissi', 4, 'U', 5, 5, 0, 0, 0, 0, 0, 0, 0, 0, 'rare', 'Leviathan', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Re dei Mostri Marini', 5, 'U', 5, 5, 0, 0, 0, 0, 0, 0, 0, 0, 'rare', 'Leviathan', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Leviatano Eterno', 6, 'U', 7, 6, 0, 0, 0, 0, 0, 0, 0, 0, 'mythic', 'Leviathan', '', 1, 'custom');
