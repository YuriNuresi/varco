-- Cancella i duplicati senza immagine
-- Mantiene solo la versione con image_url per ogni carta duplicata
DELETE m1 FROM magic_cards m1
INNER JOIN (
    SELECT name, colors, COUNT(*) as cnt 
    FROM magic_cards
    WHERE source = 'custom'
    GROUP BY name, colors
    HAVING cnt > 1
) dupes ON m1.name = dupes.name AND m1.colors = dupes.colors
WHERE m1.source = 'custom'
AND m1.image_url = '';
