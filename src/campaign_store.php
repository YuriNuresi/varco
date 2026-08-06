<?php
/**
 * Salvataggio PERSISTENTE della campagna su DB (tabella magic_campaign_saves).
 *
 * Perché: lo stato campagna vive in $_SESSION, ma su OVH le sessioni PHP muoiono
 * dopo ~24 minuti di inattività e il cookie di sessione muore alla chiusura del
 * browser → "mi tocca ricominciare". Qui ogni mutazione viene specchiata su DB e,
 * quando la sessione è vuota, lo stato viene ripristinato in automatico.
 *
 * MULTI-CAMPAGNA: si può avere UN salvataggio per ognuno dei 5 colori (5 "valichi"
 * distinti, ognuno col proprio mazzo/progressi). La riga è quindi identificata da
 * (save_key, color); il colore ATTIVO (quello che la sessione mostra di default) vive
 * nel cookie `varco_active`.
 *
 * Identità del salvataggio (in quest'ordine):
 *   1. cookie `varco_save` (token random, durata 1 anno) — funziona anche da ospiti
 *   2. email dell'utente Google loggato — recupera la partita su un altro device
 *
 * Uso (campaign.php / battle.php, dopo session_start):
 *   campaign_restore_session();              se $_SESSION['campaign'] manca, prova dal DB (colore attivo)
 *   campaign_store_save($c);                 dopo ogni scrittura di $_SESSION['campaign'] (colore = $c['color'])
 *   campaign_store_save(null, $color);       abbandono di UNA run (cancella solo quel colore)
 *   campaign_store_load($color);             carica lo slot di un colore specifico (per SWITCH)
 *   campaign_store_list_raw();               stato grezzo di tutti e 5 gli slot (per la schermata di scelta)
 *
 * Tutto best-effort: se il DB è irraggiungibile la campagna continua in sessione.
 */

declare(strict_types=1);

const TBL_CAMPAIGN_SAVES = 'magic_campaign_saves';
const CAMPAIGN_SAVE_COLORS = ['W', 'U', 'B', 'R', 'G'];

function campaign_store_ensure(): void
{
    static $done = false;
    if ($done) { return; }
    $done = true;
    $pdo = db();

    $pdo->exec('CREATE TABLE IF NOT EXISTS ' . TBL_CAMPAIGN_SAVES . ' (
        save_key   CHAR(32)     NOT NULL,
        color      CHAR(1)      NOT NULL DEFAULT \'\',
        email      VARCHAR(190) NULL,
        state      MEDIUMTEXT   NOT NULL,
        updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (save_key, color),
        INDEX (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Migrazione da schema v1 (un solo salvataggio per save_key, niente colonna color):
    // idempotente, best-effort. Backfilla `color` dal JSON e ricompone la PK composita.
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM ' . TBL_CAMPAIGN_SAVES . " LIKE 'color'")->fetchAll();
        if (!$cols) {
            $pdo->exec('ALTER TABLE ' . TBL_CAMPAIGN_SAVES . ' ADD COLUMN color CHAR(1) NOT NULL DEFAULT \'\' AFTER save_key');
            $rows = $pdo->query('SELECT save_key, state FROM ' . TBL_CAMPAIGN_SAVES)->fetchAll();
            $upd  = $pdo->prepare('UPDATE ' . TBL_CAMPAIGN_SAVES . ' SET color = ? WHERE save_key = ?');
            foreach ($rows as $row) {
                $c   = json_decode((string) $row['state'], true);
                $col = strtoupper(substr((string) (is_array($c) ? ($c['color'] ?? '') : ''), 0, 1));
                if (!in_array($col, CAMPAIGN_SAVE_COLORS, true)) { $col = 'W'; }
                $upd->execute([$col, $row['save_key']]);
            }
            try {
                $pdo->exec('ALTER TABLE ' . TBL_CAMPAIGN_SAVES . ' DROP PRIMARY KEY, ADD PRIMARY KEY (save_key, color)');
            } catch (Throwable $e) {
                // PK già composita o non alterabile: non blocchiamo l'app per questo
                error_log('[campaign_store] migrazione PK fallita: ' . $e->getMessage());
            }
        }
    } catch (Throwable $e) {
        error_log('[campaign_store] migrazione colonna color fallita: ' . $e->getMessage());
    }
}

/** Token del salvataggio dal cookie; con $create lo genera e lo imposta (1 anno). */
function campaign_store_key(bool $create): ?string
{
    static $key = null;
    if ($key !== null) { return $key; }

    $k = (string) ($_COOKIE['varco_save'] ?? '');
    if (preg_match('/^[a-f0-9]{32}$/', $k)) { return $key = $k; }
    if (!$create) { return null; }

    $k = bin2hex(random_bytes(16));
    setcookie('varco_save', $k, [
        'expires'  => time() + 365 * 24 * 3600,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['varco_save'] = $k;
    return $key = $k;
}

function campaign_store_email(): ?string
{
    $email = $_SESSION['varco_user']['email'] ?? null;
    return is_string($email) && $email !== '' ? $email : null;
}

/** Colore "attivo" (quale valico mostrare di default) dal cookie `varco_active`. '' se ignoto. */
function campaign_store_active_color(): string
{
    $c = strtoupper(substr((string) ($_COOKIE['varco_active'] ?? ''), 0, 1));
    return in_array($c, CAMPAIGN_SAVE_COLORS, true) ? $c : '';
}

/** Marca $color come il valico attivo (cookie 1 anno). */
function campaign_store_set_active(string $color): void
{
    $color = strtoupper(substr($color, 0, 1));
    if (!in_array($color, CAMPAIGN_SAVE_COLORS, true)) { return; }
    if (campaign_store_active_color() === $color) { return; }
    setcookie('varco_active', $color, [
        'expires'  => time() + 365 * 24 * 3600,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['varco_active'] = $color;
}

/**
 * Specchia lo stato campagna su DB (null = cancella il salvataggio di $color).
 * Il colore dello slot è $color se passato, altrimenti $c['color'].
 */
function campaign_store_save(?array $c, ?string $color = null): void
{
    try {
        campaign_store_ensure();
        $key = campaign_store_key(true);
        if ($key === null) { return; }

        $col = strtoupper(substr((string) ($color ?? ($c['color'] ?? '')), 0, 1));
        if (!in_array($col, CAMPAIGN_SAVE_COLORS, true)) { return; }

        if ($c === null) {
            db()->prepare('DELETE FROM ' . TBL_CAMPAIGN_SAVES . ' WHERE save_key = ? AND color = ?')->execute([$key, $col]);
            $email = campaign_store_email();
            if ($email !== null) {
                db()->prepare('DELETE FROM ' . TBL_CAMPAIGN_SAVES . ' WHERE email = ? AND color = ?')->execute([$email, $col]);
            }
            return;
        }

        $stmt = db()->prepare('INSERT INTO ' . TBL_CAMPAIGN_SAVES . ' (save_key, color, email, state) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE email = COALESCE(VALUES(email), email), state = VALUES(state)');
        $stmt->execute([$key, $col, campaign_store_email(), json_encode($c, JSON_UNESCAPED_UNICODE)]);
        campaign_store_set_active($col);
    } catch (Throwable $e) {
        // best-effort: la campagna continua in sessione anche senza persistenza
        error_log('[campaign_store] save fallita: ' . $e->getMessage());
    }
}

/** Carica il salvataggio di $color (o del colore attivo se omesso): prima per cookie, poi per email. */
function campaign_store_load(?string $color = null): ?array
{
    try {
        campaign_store_ensure();
        $key = campaign_store_key(false);
        $col = $color !== null ? strtoupper(substr($color, 0, 1)) : campaign_store_active_color();

        // Utente pre-esistente alla migrazione multi-valico: nessun colore attivo noto ancora.
        // Se questo device ha UN SOLO salvataggio, è ovviamente quello.
        if ($col === '' && $key !== null) {
            $stmt = db()->prepare('SELECT color FROM ' . TBL_CAMPAIGN_SAVES . ' WHERE save_key = ?');
            $stmt->execute([$key]);
            $colors = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (count($colors) === 1) { $col = (string) $colors[0]; }
        }
        if (!in_array($col, CAMPAIGN_SAVE_COLORS, true)) { return null; }

        if ($key !== null) {
            $stmt = db()->prepare('SELECT state FROM ' . TBL_CAMPAIGN_SAVES . ' WHERE save_key = ? AND color = ?');
            $stmt->execute([$key, $col]);
            $raw = $stmt->fetchColumn();
            if ($raw) {
                $c = json_decode((string) $raw, true);
                if (is_array($c) && !empty($c['phase'])) {
                    campaign_store_set_active($col);
                    return $c;
                }
            }
        }

        $email = campaign_store_email();
        if ($email !== null) {
            $stmt = db()->prepare('SELECT state FROM ' . TBL_CAMPAIGN_SAVES .
                ' WHERE email = ? AND color = ? ORDER BY updated_at DESC LIMIT 1');
            $stmt->execute([$email, $col]);
            $raw = $stmt->fetchColumn();
            if ($raw) {
                $c = json_decode((string) $raw, true);
                if (is_array($c) && !empty($c['phase'])) {
                    campaign_store_save($c, $col);   // ri-aggancia il salvataggio al cookie di QUESTO device
                    campaign_store_set_active($col);
                    return $c;
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[campaign_store] load fallita: ' . $e->getMessage());
    }
    return null;
}

/**
 * Stato grezzo di tutti e 5 gli slot per l'identità corrente (save_key con fallback email
 * per i colori mancanti sul device). Usato dalla schermata "scegli il tuo valico".
 * @return array<string,?array>  color => stato decodificato o null se nessuna run
 */
function campaign_store_list_raw(): array
{
    $out = array_fill_keys(CAMPAIGN_SAVE_COLORS, null);
    try {
        campaign_store_ensure();

        $key = campaign_store_key(false);
        if ($key !== null) {
            $stmt = db()->prepare('SELECT color, state FROM ' . TBL_CAMPAIGN_SAVES . ' WHERE save_key = ?');
            $stmt->execute([$key]);
            foreach ($stmt->fetchAll() as $row) {
                if (!in_array($row['color'], CAMPAIGN_SAVE_COLORS, true)) { continue; }
                $c = json_decode((string) $row['state'], true);
                if (is_array($c) && !empty($c['phase'])) { $out[$row['color']] = $c; }
            }
        }

        $email = campaign_store_email();
        if ($email !== null) {
            $stmt = db()->prepare('SELECT color, state FROM ' . TBL_CAMPAIGN_SAVES . ' WHERE email = ?');
            $stmt->execute([$email]);
            foreach ($stmt->fetchAll() as $row) {
                if (!in_array($row['color'], CAMPAIGN_SAVE_COLORS, true)) { continue; }
                if ($out[$row['color']] !== null) { continue; } // il salvataggio del device vince
                $c = json_decode((string) $row['state'], true);
                if (is_array($c) && !empty($c['phase'])) { $out[$row['color']] = $c; }
            }
        }
    } catch (Throwable $e) {
        error_log('[campaign_store] list fallita: ' . $e->getMessage());
    }
    return $out;
}

/** Se la sessione ha perso la campagna (scaduta/nuovo device), ripristina dal DB (colore attivo). */
function campaign_restore_session(): void
{
    if (isset($_SESSION['campaign'])) { return; }
    $c = campaign_store_load();
    if ($c !== null) {
        // una battaglia in corso non è ripristinabile (il match vive solo in sessione):
        // si riparte dall'hub con `current` intatto, il livello si rigioca con "Entra"
        if (($c['phase'] ?? '') === 'fighting') { $c['phase'] = 'hub'; }
        $_SESSION['campaign'] = $c;
    }
}
