-- Conta le carte totali nel database
SELECT COUNT(*) as total_cards FROM magic_cards;

-- Mostra il numero di carte per fonte
SELECT source, COUNT(*) as cnt FROM magic_cards GROUP BY source ORDER BY source;

-- Mostra il numero di carte per colore
SELECT colors, COUNT(*) as cnt FROM magic_cards GROUP BY colors ORDER BY colors;
