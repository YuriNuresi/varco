-- Aggiunge la colonna `source` per distinguere carte Scryfall da carte custom.
-- Eseguire una sola volta (idempotente grazie a IF NOT EXISTS su colonna).

ALTER TABLE magic_cards
  ADD COLUMN IF NOT EXISTS source VARCHAR(10) NOT NULL DEFAULT 'scryfall' AFTER enabled;

CREATE INDEX IF NOT EXISTS idx_source ON magic_cards (source);

-- Marca tutte le carte esistenti come scryfall (sicurezza).
UPDATE magic_cards SET source = 'scryfall' WHERE source = '';
