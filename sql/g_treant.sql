-- Carte verdi custom: Treant (2a edizione)
-- 3×2mana, 4×3mana, 3×4mana, 1×5mana, 1×6mana = 12 carte

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Alberello Risvegliato', 2, 'G', 1, 2, 0, 0, 0, 0, 0, 0, 1, 0, 'common', 'Treefolk', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Treant del Varco', 2, 'G', 2, 1, 0, 0, 0, 0, 0, 0, 1, 0, 'common', 'Treefolk', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Treant Giovane', 2, 'G', 1, 3, 0, 0, 0, 0, 0, 0, 1, 1, 'uncommon', 'Treefolk', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Treant delle Radici', 3, 'G', 2, 3, 0, 0, 0, 0, 0, 0, 1, 0, 'common', 'Treefolk', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Treant Custode', 3, 'G', 2, 4, 0, 0, 0, 0, 0, 0, 1, 1, 'uncommon', 'Treefolk', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Treant Spinoso', 3, 'G', 3, 2, 0, 0, 0, 1, 0, 0, 1, 0, 'uncommon', 'Treefolk', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Treant Antico', 3, 'G', 3, 3, 0, 0, 0, 0, 0, 0, 1, 0, 'rare', 'Treefolk', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Treant delle Querce', 4, 'G', 3, 4, 0, 0, 0, 1, 0, 0, 1, 0, 'uncommon', 'Treefolk', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Treant Colossale', 4, 'G', 4, 3, 0, 0, 0, 1, 0, 0, 1, 0, 'rare', 'Treefolk', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Treant Sacro', 4, 'G', 3, 5, 0, 0, 0, 0, 0, 1, 1, 0, 'rare', 'Treefolk', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Re dei Treant', 5, 'G', 4, 5, 0, 0, 0, 1, 0, 0, 1, 0, 'rare', 'Treefolk', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Treant Primordiale', 6, 'G', 6, 6, 0, 0, 0, 1, 0, 0, 1, 0, 'mythic', 'Treefolk', '', 1, 'custom');
