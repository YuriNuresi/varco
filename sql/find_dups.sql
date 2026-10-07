SELECT name, colors, COUNT(*) as cnt, GROUP_CONCAT(id) as ids FROM magic_cards WHERE source = 'custom' GROUP BY name, colors HAVING cnt > 1 ORDER BY name;
