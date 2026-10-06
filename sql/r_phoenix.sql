-- Carte rosse custom: Fenici (2a edizione)
-- 3×2mana, 4×3mana, 3×4mana, 1×5mana, 1×6mana = 12 carte

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fenice Scintilla', 2, 'R', 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 'common', 'Phoenix', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fenice del Varco', 2, 'R', 2, 1, 1, 0, 0, 0, 0, 0, 0, 0, 'common', 'Phoenix', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fenice Ardente', 2, 'R', 1, 2, 1, 1, 0, 0, 0, 0, 0, 0, 'uncommon', 'Phoenix', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fenice di Fuoco', 3, 'R', 2, 2, 1, 1, 0, 0, 0, 0, 0, 0, 'common', 'Phoenix', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fenice Infuriata', 3, 'R', 3, 1, 1, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Phoenix', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fenice Solare', 3, 'R', 2, 3, 1, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Phoenix', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fenice Regale', 3, 'R', 3, 2, 1, 1, 0, 0, 0, 0, 0, 0, 'rare', 'Phoenix', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fenice Imperiale', 4, 'R', 3, 3, 1, 1, 0, 0, 0, 0, 0, 0, 'uncommon', 'Phoenix', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fenice Titanica', 4, 'R', 4, 3, 1, 0, 0, 0, 0, 0, 0, 0, 'rare', 'Phoenix', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fenice Divina', 4, 'R', 4, 4, 1, 1, 0, 0, 0, 0, 0, 0, 'rare', 'Phoenix', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Regina delle Fenici', 5, 'R', 4, 4, 1, 1, 0, 1, 0, 0, 0, 0, 'rare', 'Phoenix', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fenice Eterna', 6, 'R', 5, 5, 1, 1, 0, 1, 0, 0, 0, 0, 'mythic', 'Phoenix', '', 1, 'custom');
