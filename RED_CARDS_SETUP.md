# Setup Carte Rosse (Diavoli, Draghi, Guerrieri)

## File caricati
- `sql/red_cards.sql` — 36 carte rosse (12 Diavoli, 12 Draghi, 12 Guerrieri)
- Questo file

## Step 1: Importa le carte nel database

### Opzione A: Via phpMyAdmin (OVH)
1. Accedi a phpMyAdmin del tuo MySQL su OVH
2. Seleziona il database `magic_game`
3. Vai a "Importa"
4. Carica il file `sql/red_cards.sql`
5. Esegui

### Opzione B: Via linea di comando (SSH)
```bash
mysql -h localhost -u [user] -p magic_game < sql/red_cards.sql
```

## Step 2: Genera le immagini per le carte

### Opzione A: Via PHP CLI (se disponibile)
Usa lo script `scripts/generate_red_cards.php` (scarica il file dal repo locale):

```bash
php scripts/generate_red_cards.php
```

Con flag facoltativi:
```bash
php scripts/generate_red_cards.php --dry-run  # simula senza scrivere
php scripts/generate_red_cards.php --force     # rigenera anche esistenti
```

### Opzione B: Via API Web (senza CLI)
Se non hai accesso a PHP CLI, puoi usare l'API per generare le immagini una per una:

```bash
curl -X POST https://varco.portale3d.it/api/cards_admin.php?action=generate_image \
  -H "Content-Type: application/json" \
  -d '{
    "prompt": "A small scarlet imp wreathed in flames...",
    "card_name": "Diavoletto Fiammeggiante"
  }' \
  -H "X-Admin-Key: [INSTALL_KEY]"
```

Oppure usa un tool come Postman, Insomnia, o uno script bash.

### Prompts per tutte le carte
Vedi il file `scripts/generate_red_cards.php` (riga 86-128) per tutti i prompts.

## Schema delle carte

### DIAVOLI (12 carte)
- 3×2mana (common, common, uncommon)
- 4×3mana (uncommon, common, uncommon, rare)
- 3×4mana (uncommon, rare, rare)
- 2×5-6mana (mythic, mythic)

**Abilità tematiche**: flying, first_strike, lifelink, trample

### DRAGHI (12 carte)
- 3×2mana (common, common, uncommon)
- 4×3mana (uncommon, common, uncommon, rare)
- 3×4mana (rare, uncommon, rare)
- 2×5-6mana (mythic, mythic)

**Abilità tematiche**: flying (tutti), reach (alcuni)

### GUERRIERI (12 carte)
- 3×2mana (common, common, uncommon)
- 4×3mana (uncommon, common, uncommon, rare)
- 3×4mana (uncommon, rare, rare)
- 2×5-6mana (mythic, mythic)

**Abilità tematiche**: first_strike, trample, reach (alcuni)

## Verifica

Dopo l'import, controlla:

```sql
SELECT COUNT(*) as total, colors, subtypes 
FROM magic_cards 
WHERE source = 'custom' AND colors = 'R' 
GROUP BY subtypes;
```

Dovresti vedere:
- 12 carte per tipo (Devil, Dragon, Warrior)
- Tutte con color 'R'
- Tutte con source 'custom'

## Note

- Le carte mantengono lo STESSO schema di distribuzione di mana/rarity/abilità delle carte nere
- Totale: 36 carte (invece di 52 nere) = "mini-mazzo"
- Non sovrascrive le carte nere esistenti (colori diversi)
- Gli URL delle immagini si aggiungono al database nel campo `image_url` durante la generazione

Fatto!
