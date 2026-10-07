-- Trova tutte le carte duplicate (stesso name+colors) nel database
SELECT name, colors, COUNT(*) as cnt, GROUP_CONCAT(id SEPARATOR ', ') as ids 
FROM magic_cards 
WHERE source = 'custom' 
GROUP BY name, colors 
HAVING cnt > 1 
ORDER BY name;
