DELETE t1 FROM magic_cards t1
INNER JOIN magic_cards t2
ON t1.name = t2.name AND t1.source = t2.source AND t1.id > t2.id
WHERE t1.source = 'custom' AND t1.colors = 'B';
