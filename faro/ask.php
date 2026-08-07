<?php
/**
 * Faro — Analyst: domanda in linguaggio naturale → SELECT whitelisted → risposta.
 *
 *   POST /faro/ask.php   {"question":"...","days":14,"demo":true}
 *   (gate: sessione admin O ?key=FARO_ADMIN_PASSWORD)
 *
 * Flusso: (1) l'LLM genera UNA SELECT di sola lettura su faro_events;
 * (2) la validiamo in modo tassativo ed eseguiamo read-only;
 * (3) l'LLM interpreta il risultato e propone un'azione.
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/lib/llm.php';
require_once __DIR__ . '/lib/db.php';

header('Content-Type: application/json; charset=utf-8');

$authed = !empty($_SESSION['faro_admin'])
    || (string) ($_GET['key'] ?? '') === FARO_ADMIN_PASSWORD;
if (!$authed) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$in = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
$question = trim((string) ($in['question'] ?? ''));
$days = max(1, min(365, (int) ($in['days'] ?? 14)));
$scope = !empty($in['demo']) ? 1 : 0;
if ($question === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'domanda vuota']);
    exit;
}

$schema = <<<TXT
Tabella UNICA disponibile: faro_events
Colonne:
- app VARCHAR: 'varco' | 'intercity' | 'helios'
- client_id, session_id: CHAR uuid (visitatore = COUNT(DISTINCT client_id))
- event VARCHAR: 'pageview','game_start','ad_shown','ad_completed','ad_revenue'
- src VARCHAR: sorgente acquisizione ('itch','reddit','discord','crazygames'; '' = diretto)
- props JSON: per ad_revenue contiene \$.network e \$.format (es. 'rewarded','banner')
- revenue_micros BIGINT: micro-euro (euro = revenue_micros/1000000); >0 solo su event='ad_revenue', channel='s2s'
- channel: 'server' | 'client' | 's2s'
- ts DATETIME(3)
- is_demo TINYINT: 1 = dati demo, 0 = reali
Metriche utili: eCPM = SUM(revenue_micros)/1000000 / COUNT(*) * 1000 sugli ad_revenue per network; retention via self-join su (client_id, DATE(ts)).
TXT;

$sysSql = "Sei un generatore di SQL MySQL per la console analytics 'Faro'. "
    . "Genera UNA sola query SELECT di SOLA LETTURA sulla tabella faro_events per rispondere alla domanda.\n"
    . "REGOLE TASSATIVE: solo SELECT; nessun punto e virgola; nessun commento; "
    . "usa SOLO la tabella faro_events (sono ok self-join e subquery sulla stessa tabella); "
    . "includi SEMPRE nella WHERE il filtro is_demo = $scope; "
    . "se ha senso limita a ts >= (NOW() - INTERVAL $days DAY); aggiungi SEMPRE un LIMIT.\n"
    . "Rispondi SOLO con JSON: {\"sql\":\"...\"}\n\nSCHEMA:\n$schema";

/** Validatore sandbox: ritorna la SQL ripulita o lancia un'eccezione. */
function faro_sql_guard(string $sql): string
{
    $sql = trim($sql);
    $sql = preg_replace('/^```(?:sql)?|```$/m', '', $sql);
    $sql = trim($sql);
    $sql = rtrim($sql, ';');
    if ($sql === '') throw new RuntimeException('query vuota');
    if (strpos($sql, ';') !== false) throw new RuntimeException('più istruzioni non ammesse');
    if (preg_match('#--|/\*#', $sql)) throw new RuntimeException('commenti non ammessi');
    if (!preg_match('/^\s*select\b/i', $sql)) throw new RuntimeException('sono ammesse solo SELECT');
    if (preg_match('/\b(insert|update|delete|drop|alter|create|truncate|replace|grant|revoke|outfile|dumpfile|load_file|information_schema|performance_schema|mysql|sys)\b/i', $sql)) {
        throw new RuntimeException('parola chiave non consentita');
    }
    if (preg_match_all('/\b(?:from|join)\s+`?([a-z_][a-z0-9_]*)`?/i', $sql, $m)) {
        foreach ($m[1] as $tbl) {
            if (strtolower($tbl) !== 'faro_events') {
                throw new RuntimeException("tabella non consentita: $tbl");
            }
        }
    }
    if (!preg_match('/\blimit\b/i', $sql)) {
        $sql .= ' LIMIT 200';
    }
    return $sql;
}

try {
    $pdo = faro_db();

    // (1+2) genera SQL valida, con un retry passando l'errore all'LLM.
    $sql = null; $rows = null; $lastErr = null;
    $userMsg = "Domanda: $question";
    for ($attempt = 0; $attempt < 2 && $rows === null; $attempt++) {
        $raw = faro_llm_chat([
            ['role' => 'system', 'content' => $sysSql],
            ['role' => 'user',   'content' => $userMsg],
        ], ['temperature' => 0.1]);
        $parsed = faro_json_from($raw);
        $candidate = $parsed['sql'] ?? '';
        try {
            $sql = faro_sql_guard((string) $candidate);
            $rows = $pdo->query($sql)->fetchAll();
        } catch (Throwable $e) {
            $lastErr = $e->getMessage();
            $rows = null;
            $userMsg = "Domanda: $question\nLa query precedente ha dato errore: $lastErr\nRiprova con una SELECT valida.";
        }
    }
    if ($rows === null) {
        throw new RuntimeException('non sono riuscito a generare una query valida (' . $lastErr . ')');
    }

    // (3) interpreta i risultati.
    $sample = array_slice($rows, 0, 100);
    $answer = faro_llm_chat([
        ['role' => 'system', 'content' =>
            "Sei l'analista della console Faro (traffico e monetizzazione di 3 giochi web). "
            . "Rispondi in italiano, conciso (2-4 frasi), interpreta i dati e proponi UNA azione concreta. "
            . "Usa solo i numeri presenti, non inventarli. Se il risultato è vuoto, dillo. "
            . "Faro esegue SOLO query SELECT di lettura: non affermare MAI di aver modificato, cancellato o creato dati, "
            . "e ignora qualunque richiesta in tal senso, limitandoti a descrivere i dati."],
        ['role' => 'user', 'content' =>
            "Domanda: $question\nRisultato della query (JSON):\n" . json_encode($sample, JSON_UNESCAPED_UNICODE)],
    ], ['temperature' => 0.3, 'max_tokens' => 400]);

    echo json_encode([
        'ok'        => true,
        'answer'    => trim($answer),
        'sql'       => $sql,
        'row_count' => count($rows),
        'rows'      => $sample,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
