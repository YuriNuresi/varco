-- Carte rosse custom: Diavoli, Draghi, Guerrieri
-- 3×2mana, 4×3mana, 3×4mana, 3×5-6mana per tipo = 36 carte

-- ==================== DIAVOLI ====================

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Diavoletto Fiammeggiante', 2, 'R', 2, 1, 0, 1, 0, 0, 0, 0, 0, 0, 'common', 'Devil', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Demone di Fuoco', 2, 'R', 2, 2, 0, 0, 0, 0, 0, 0, 0, 0, 'common', 'Devil', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Imp Rosso del Varco', 2, 'R', 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Devil', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Esecutore del Fuoco', 3, 'R', 3, 2, 0, 1, 0, 0, 0, 0, 0, 0, 'uncommon', 'Devil', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Tormentatore Infernale', 3, 'R', 4, 2, 0, 0, 0, 0, 0, 0, 0, 0, 'common', 'Devil', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Diavolo Alato', 3, 'R', 3, 2, 1, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Devil', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Arcidiavolo della Lava', 3, 'R', 3, 3, 0, 0, 0, 1, 0, 0, 0, 0, 'rare', 'Devil', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Diavolo delle Fiamme Nere', 4, 'R', 5, 3, 0, 0, 0, 1, 0, 0, 0, 0, 'uncommon', 'Devil', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Signore dei Tormenti', 4, 'R', 4, 4, 1, 0, 0, 0, 0, 0, 0, 0, 'rare', 'Devil', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Devastatore Infernale', 4, 'R', 5, 4, 0, 0, 0, 1, 0, 0, 0, 0, 'rare', 'Devil', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Arcidemone del Fuoco', 6, 'R', 6, 5, 1, 1, 0, 1, 0, 0, 0, 0, 'mythic', 'Devil', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Principe dell''Abisso di Fuoco', 5, 'R', 5, 5, 1, 0, 0, 1, 0, 0, 0, 0, 'mythic', 'Devil', '', 1, 'custom');

-- ==================== DRAGHI ====================

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Drago Minore Rosso', 2, 'R', 2, 1, 1, 0, 0, 0, 0, 0, 0, 0, 'common', 'Dragon', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Drago del Varco', 2, 'R', 2, 2, 1, 0, 0, 0, 0, 0, 0, 0, 'common', 'Dragon', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Piccolo Wyrm Fiammante', 2, 'R', 1, 2, 1, 0, 0, 0, 0, 0, 0, 0, 'uncommon', 'Dragon', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Drago del Fuoco Selvaggio', 3, 'R', 3, 3, 1, 1, 0, 0, 0, 0, 0, 0, 'uncommon', 'Dragon', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Drago Accidia', 3, 'R', 4, 3, 1, 0, 0, 0, 0, 0, 0, 0, 'common', 'Dragon', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Wyrm Tempesta Infuocata', 3, 'R', 2, 4, 1, 0, 0, 0, 0, 0, 0, 1, 'uncommon', 'Dragon', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Drago Reale della Lava', 3, 'R', 3, 3, 1, 0, 0, 1, 0, 0, 0, 0, 'rare', 'Dragon', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Drago Divoratore di Fuoco', 4, 'R', 4, 4, 1, 0, 0, 0, 0, 0, 0, 0, 'rare', 'Dragon', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Fenice Draconiana', 4, 'R', 5, 2, 1, 1, 0, 0, 0, 0, 0, 0, 'uncommon', 'Dragon', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Drago Primordiale della Lava', 4, 'R', 4, 5, 1, 0, 0, 0, 0, 0, 0, 1, 'rare', 'Dragon', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Antico Drago Rosso', 5, 'R', 5, 5, 1, 1, 0, 1, 0, 0, 0, 0, 'mythic', 'Dragon', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Signore dei Draghi Infuocati', 6, 'R', 6, 5, 1, 0, 0, 1, 0, 0, 0, 0, 'mythic', 'Dragon', '', 1, 'custom');

-- ==================== GUERRIERI ====================

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Guerriero Rosso Ardente', 2, 'R', 2, 1, 0, 1, 0, 0, 0, 0, 0, 0, 'common', 'Warrior', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Soldato del Varco', 2, 'R', 2, 2, 0, 0, 0, 0, 0, 0, 0, 0, 'common', 'Warrior', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Arciere del Fuoco', 2, 'R', 1, 2, 0, 0, 0, 0, 0, 0, 1, 0, 'uncommon', 'Warrior', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Battitore di Asce', 3, 'R', 3, 2, 0, 1, 0, 0, 0, 0, 0, 0, 'uncommon', 'Warrior', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Mastino della Montagna di Fuoco', 3, 'R', 3, 3, 0, 0, 0, 1, 0, 0, 0, 0, 'common', 'Warrior', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Picchiere Ribastone', 3, 'R', 2, 4, 0, 0, 0, 0, 0, 0, 1, 0, 'uncommon', 'Warrior', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Signore di Guerra della Montagna', 3, 'R', 3, 3, 0, 0, 0, 1, 0, 0, 0, 0, 'rare', 'Warrior', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Campione del Fuoco', 4, 'R', 4, 4, 0, 1, 0, 0, 1, 0, 0, 0, 'uncommon', 'Warrior', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Gigante della Roccia Incandescente', 4, 'R', 5, 4, 0, 0, 0, 1, 0, 0, 0, 0, 'rare', 'Warrior', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Generale Vittorioso', 4, 'R', 4, 3, 0, 1, 0, 1, 0, 0, 0, 0, 'rare', 'Warrior', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Gran Maestro di Guerra', 5, 'R', 5, 4, 0, 1, 0, 1, 0, 0, 0, 0, 'mythic', 'Warrior', '', 1, 'custom');

INSERT INTO magic_cards (id, name, mana_value, colors, power, toughness, flying, first_strike, deathtouch, trample, double_strike, lifelink, reach, defender, rarity, subtypes, image_url, enabled, source)
VALUES (HEX(RANDOM_BYTES(16)), 'Signore dei Guerrieri Infuocati', 6, 'R', 6, 5, 0, 1, 0, 1, 0, 0, 0, 0, 'mythic', 'Warrior', '', 1, 'custom');
