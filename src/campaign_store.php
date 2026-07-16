<?php
/**
 * Salvataggio PERSISTENTE della campagna su DB (tabella magic_campaign_saves).
 *
 * Perché: lo stato campagna vive in $_SESSION, ma su OVH le sessioni PHP muoiono
 * dopo ~24 minuti di inattività e il cookie di sessione muore alla chiusura del
 * browser → "mi tocca ricominciare". Qui ogni mutazione viene specchiata su DB e,
 * quando la sessione è vuota, lo stato viene ripristinato in automatico.
 *
 * Identità del salvataggio (in quest'ordine):
 *   1. cookie `varco_save` (token random, durata 1 anno) — funziona anche da ospiti
 *   2. email dell'utente Google loggato — recupera la partita su un altro device
 *
 * Uso (campaign.php / battle.php, dopo session_start):
 *   campaign_restore_session();          se $_SESSION['campaign'] manca, prova dal DB
 *   campaign_store_save($c);             dopo ogni scrittura di $_SESSION['campaign']
 *   campaign_store_save(null);           al RESET (cancella il salvataggio)
 *
 * Tutto best-effort: se il DB è irraggiungibile la campagna continua in sessione.
 */

declare(strict_types=1);

const TBL_CAMPAIGN_SAVES = 'magic_campaign_saves';

function campaign_store_ensure(): void
{
    static $done = false;
    if ($done) { return; }
    $done = true;
    db()->exec('CREATE TABLE IF NOT EXISTS ' . TBL_CAMPAIGN_SAVES . ' (
        save_key   CHAR(32)     NOT NULL PRIMARY KEY,
        email      VARCHAR(190) NULL,
        state      MEDIUMTEXT   NOT NULL,
        updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
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

/** Specchia lo stato campagna su DB (null = cancella il salvataggio). */
function campaign_store_save(?array $c): void
{
    try {
        campaign_store_ensure();
        $key = campaign_store_key(true);
        if ($key === null) { return; }

        if ($c === null) {
            db()->prepare('DELETE FROM ' . TBL_CAMPAIGN_SAVES . ' WHERE save_key = ?')->execute([$key]);
            $email = campaign_store_email();
            if ($email !== null) {
                db()->prepare('DELETE FROM ' . TBL_CAMPAIGN_SAVES . ' WHERE email = ?')->execute([$email]);
            }
            return;
        }

        $stmt = db()->prepare('INSERT INTO ' . TBL_CAMPAIGN_SAVES . ' (save_key, email, state) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE email = COALESCE(VALUES(email), email), state = VALUES(state)');
        $stmt->execute([$key, campaign_store_email(), json_encode($c, JSON_UNESCAPED_UNICODE)]);
    } catch (Throwable $e) {
        // best-effort: la campagna continua in sessione anche senza persistenza
        error_log('[campaign_store] save fallita: ' . $e->getMessage());
    }
}

/** Carica il salvataggio: prima per cookie, poi per email (altro device). */
function campaign_store_load(): ?array
{
    try {
        campaign_store_ensure();

        $key = campaign_store_key(false);
        if ($key !== null) {
            $stmt = db()->prepare('SELECT state FROM ' . TBL_CAMPAIGN_SAVES . ' WHERE save_key = ?');
            $stmt->execute([$key]);
            $raw = $stmt->fetchColumn();
            if ($raw) {
                $c = json_decode((string) $raw, true);
                if (is_array($c) && !empty($c['phase'])) { return $c; }
            }
        }

        $email = campaign_store_email();
        if ($email !== null) {
            $stmt = db()->prepare('SELECT state FROM ' . TBL_CAMPAIGN_SAVES .
                ' WHERE email = ? ORDER BY updated_at DESC LIMIT 1');
            $stmt->execute([$email]);
            $raw = $stmt->fetchColumn();
            if ($raw) {
                $c = json_decode((string) $raw, true);
                if (is_array($c) && !empty($c['phase'])) {
                    campaign_store_save($c);   // ri-aggancia il salvataggio al cookie di QUESTO device
                    return $c;
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[campaign_store] load fallita: ' . $e->getMessage());
    }
    return null;
}

/** Se la sessione ha perso la campagna (scaduta/nuovo device), ripristina dal DB. */
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
