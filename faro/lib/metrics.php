<?php
/**
 * Faro — query layer. Funzioni pure che leggono da faro_events e restituiscono
 * array già pronti. Riusate da dashboard.php, metrics.php (API JSON), ask.php
 * (Analyst) e digest.php (mail mattutina).
 *
 * $days è sempre sanificato a int in [1,365] e inlinato (lo controlliamo noi):
 * MySQL non accetta placeholder dentro INTERVAL n DAY con prepared non emulate.
 *
 * $demo: false (default) = solo dati reali (is_demo=0); true = include i dati
 * dimostrativi (seed). Mai mischiati per sbaglio.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function faro_days(int $days): int
{
    return max(1, min(365, $days));
}

function faro_eur(int $micros): float
{
    return round($micros / 1_000_000, 2);
}

/** Filtro demo da appendere a una WHERE già iniziata. */
function faro_demo_and(bool $demo): string
{
    return $demo ? '' : ' AND is_demo = 0';
}

/** KPI aggregati su tutta la finestra. */
function faro_overview(PDO $pdo, int $days, bool $demo = false): array
{
    $d = faro_days($days);
    $dm = faro_demo_and($demo);
    $row = $pdo->query(
        "SELECT
            COUNT(DISTINCT client_id)              AS visitors,
            COUNT(DISTINCT session_id)             AS sessions,
            SUM(event='pageview')                  AS pageviews,
            COALESCE(SUM(revenue_micros),0)        AS rev_micros,
            SUM(event='ad_shown')                  AS ad_shown,
            SUM(event='ad_completed')              AS ad_completed
         FROM faro_events
         WHERE ts >= (NOW() - INTERVAL $d DAY)$dm"
    )->fetch();

    $visitors  = (int) $row['visitors'];
    $sessions  = (int) $row['sessions'];
    $pageviews = (int) $row['pageviews'];
    $revEur    = faro_eur((int) $row['rev_micros']);
    $shown     = (int) $row['ad_shown'];
    $completed = (int) $row['ad_completed'];

    // Durata media sessione = spread temporale degli eventi di ogni sessione.
    $avgDur = (float) $pdo->query(
        "SELECT COALESCE(AVG(sd),0) FROM (
            SELECT TIMESTAMPDIFF(SECOND, MIN(ts), MAX(ts)) AS sd
            FROM faro_events
            WHERE ts >= (NOW() - INTERVAL $d DAY)$dm
            GROUP BY session_id
         ) t"
    )->fetchColumn();

    return [
        'visitors'        => $visitors,
        'sessions'        => $sessions,
        'pageviews'       => $pageviews,
        'pages_per_session' => $sessions ? round($pageviews / $sessions, 1) : 0.0,
        'avg_session_sec'   => (int) round($avgDur),
        'revenue_eur'     => $revEur,
        'rev_per_visitor' => $visitors ? round($revEur / $visitors, 4) : 0.0,
        'rewarded_completion' => $shown ? round($completed / $shown * 100, 1) : 0.0,
    ];
}

/** Stessi KPI ma per gioco. */
function faro_per_app(PDO $pdo, int $days, bool $demo = false): array
{
    $d = faro_days($days);
    $dm = faro_demo_and($demo);
    $rows = $pdo->query(
        "SELECT app,
            COUNT(DISTINCT client_id)       AS visitors,
            SUM(event='pageview')           AS pageviews,
            COALESCE(SUM(revenue_micros),0) AS rev_micros
         FROM faro_events
         WHERE ts >= (NOW() - INTERVAL $d DAY)$dm
         GROUP BY app
         ORDER BY visitors DESC"
    )->fetchAll();

    foreach ($rows as &$r) {
        $r['visitors']    = (int) $r['visitors'];
        $r['pageviews']   = (int) $r['pageviews'];
        $r['revenue_eur'] = faro_eur((int) $r['rev_micros']);
        $r['rev_per_visitor'] = $r['visitors'] ? round($r['revenue_eur'] / $r['visitors'], 4) : 0.0;
        unset($r['rev_micros']);
    }
    return $rows;
}

/** Acquisizione: sorgenti ordinate per revenue/visitatore (la metrica nord). */
function faro_acquisition(PDO $pdo, int $days, ?string $app = null, bool $demo = false): array
{
    $d = faro_days($days);
    $where = "ts >= (NOW() - INTERVAL $d DAY)" . faro_demo_and($demo);
    $params = [];
    if ($app !== null && $app !== '') {
        $where .= " AND app = :app";
        $params[':app'] = $app;
    }
    $st = $pdo->prepare(
        "SELECT COALESCE(NULLIF(src,''),'(diretto)') AS source,
                COUNT(DISTINCT client_id)            AS visitors,
                COALESCE(SUM(revenue_micros),0)      AS rev_micros
         FROM faro_events
         WHERE $where
         GROUP BY source
         ORDER BY (SUM(revenue_micros)/GREATEST(COUNT(DISTINCT client_id),1)) DESC, visitors DESC"
    );
    $st->execute($params);
    $rows = $st->fetchAll();

    foreach ($rows as &$r) {
        $r['visitors']    = (int) $r['visitors'];
        $r['revenue_eur'] = faro_eur((int) $r['rev_micros']);
        $r['rev_per_visitor'] = $r['visitors'] ? round($r['revenue_eur'] / $r['visitors'], 4) : 0.0;
        unset($r['rev_micros']);
    }
    return $rows;
}

/** Mediation: eCPM per network (1 callback s2s = 1 impression remunerata). */
function faro_mediation(PDO $pdo, int $days, bool $demo = false): array
{
    $d = faro_days($days);
    $dm = faro_demo_and($demo);
    $rows = $pdo->query(
        "SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(props,'$.network')),'(n/d)') AS network,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(props,'$.format')),'') AS format,
                COUNT(*)                        AS impressions,
                COALESCE(SUM(revenue_micros),0) AS rev_micros
         FROM faro_events
         WHERE channel='s2s' AND revenue_micros > 0
           AND ts >= (NOW() - INTERVAL $d DAY)$dm
         GROUP BY network, format
         ORDER BY (SUM(revenue_micros)/GREATEST(COUNT(*),1)) DESC"
    )->fetchAll();

    foreach ($rows as &$r) {
        $r['impressions'] = (int) $r['impressions'];
        $r['revenue_eur'] = faro_eur((int) $r['rev_micros']);
        $r['ecpm'] = $r['impressions'] ? round($r['revenue_eur'] / $r['impressions'] * 1000, 2) : 0.0;
        unset($r['rev_micros']);
    }
    return $rows;
}

/** Retention D1/D7 sui nuovi client della finestra (self-join, no CTE). */
function faro_retention(PDO $pdo, int $days, bool $demo = false): array
{
    $d = faro_days($days);
    $dm = faro_demo_and($demo);
    $sub = $demo ? '' : ' WHERE is_demo = 0';
    $row = $pdo->query(
        "SELECT
            COUNT(DISTINCT f.client_id) AS cohort,
            COUNT(DISTINCT CASE WHEN r1.client_id IS NOT NULL THEN f.client_id END) AS d1,
            COUNT(DISTINCT CASE WHEN r7.client_id IS NOT NULL THEN f.client_id END) AS d7
         FROM (
            SELECT client_id, MIN(DATE(ts)) AS f
            FROM faro_events
            WHERE ts >= (NOW() - INTERVAL $d DAY)$dm
            GROUP BY client_id
         ) f
         LEFT JOIN (SELECT DISTINCT client_id, DATE(ts) dd FROM faro_events$sub) r1
           ON r1.client_id = f.client_id AND r1.dd = DATE_ADD(f.f, INTERVAL 1 DAY)
         LEFT JOIN (SELECT DISTINCT client_id, DATE(ts) dd FROM faro_events$sub) r7
           ON r7.client_id = f.client_id AND r7.dd > f.f AND r7.dd <= DATE_ADD(f.f, INTERVAL 7 DAY)"
    )->fetch();

    $cohort = (int) $row['cohort'];
    return [
        'cohort' => $cohort,
        'd1'     => $cohort ? round((int) $row['d1'] / $cohort * 100, 1) : 0.0,
        'd7'     => $cohort ? round((int) $row['d7'] / $cohort * 100, 1) : 0.0,
    ];
}

/** Serie giornaliera (per il grafico): visitatori e revenue per giorno. */
function faro_timeseries(PDO $pdo, int $days, bool $demo = false): array
{
    $d = faro_days($days);
    $dm = faro_demo_and($demo);
    $rows = $pdo->query(
        "SELECT DATE(ts) AS day,
                COUNT(DISTINCT client_id)       AS visitors,
                COALESCE(SUM(revenue_micros),0) AS rev_micros
         FROM faro_events
         WHERE ts >= (NOW() - INTERVAL $d DAY)$dm
         GROUP BY DATE(ts)
         ORDER BY day"
    )->fetchAll();

    foreach ($rows as &$r) {
        $r['visitors']    = (int) $r['visitors'];
        $r['revenue_eur'] = faro_eur((int) $r['rev_micros']);
        unset($r['rev_micros']);
    }
    return $rows;
}

/**
 * Classifica una visita dal suo user-agent (segnale forte) con fallback
 * comportamentale. Ritorna: 'scanner' | 'ai' | 'bot' | 'utente' | 'sospetto' | 'ignoto'.
 *   - scanner : scanner di sicurezza o probe d'attacco (spesso UA falsificata)
 *   - ai      : agente/crawler LLM (Claude, GPTBot, Perplexity, CCBot, ...)
 *   - bot     : UA di crawler/scraper/automazione/SEO noti
 *   - utente  : UA riconducibile a un browser reale
 *   - sospetto: UA presente ma non è un browser noto (tool/script generico)
 *   - ignoto  : nessun UA (dati pre-migrazione o client che non lo invia)
 */
function faro_visit_kind(?string $ua): string
{
    $ua = trim((string) $ua);
    if ($ua === '') {
        return 'ignoto';
    }
    // Scanner di sicurezza e probe d'attacco: controllati PER PRIMI perché spesso
    // falsificano una UA "Mozilla" (es. exploit PoC) e altrimenti passerebbero per
    // browser/utente. Include ricognitori internet-wide e tool di vuln-scanning.
    $scanner = '/paloaltonetworks|cortex-xpanse|\bexpanse\b|censys|shodan|'
             . 'binaryedge|leakix|shadowserver|netsystemsresearch|'
             . 'internet-?measurement|stretchoid|criminalip|projectdiscovery|'
             . 'masscan|zmap|zgrab|\bnmap\b|nuclei|sqlmap|nikto|wpscan|'
             . 'acunetix|nessus|openvas|dirbuster|gobuster|feroxbuster|'
             . 'sppb|rce[-_]?poc|[-_]poc\b|\bfuzz|l9explore|l9tcpid/i';
    if (preg_match($scanner, $ua)) {
        return 'scanner';
    }
    // Agenti AI / crawler LLM (priorità sui bot generici): scraping per training,
    // assistenti che navigano per conto dell'utente, motori di risposta AI.
    $ai = '/gptbot|chatgpt-user|oai-searchbot|openai|'
        . 'claudebot|claude-user|claude-web|claude-code|anthropic|'
        . 'perplexitybot|perplexity-user|youbot|'
        . 'google-extended|googleother|bard|gemini|'
        . 'cohere-ai|ccbot|bytespider|bytedance|'
        . 'meta-externalagent|meta-externalfetcher|'
        . 'applebot-extended|amazonbot|diffbot|ai2bot|imagesiftbot|'
        . 'omgili|timpibot|webzio|img2dataset|firecrawl|scrapingbee/i';
    if (preg_match($ai, $ua)) {
        return 'ai';
    }
    // Bot/crawler/scraper/automazione/SEO-tools/scanner di sicurezza.
    $bot = '/bot\b|bot\/|crawl|spider|slurp|bingpreview|mediapartners|'
         . 'googlebot|adsbot|yandex|baidu|sogou|exabot|duckduck|'
         . 'facebookexternalhit|facebot|embedly|quora|pinterest|skypeuripreview|'
         . 'slackbot|slack-imgproxy|telegrambot|whatsapp|discordbot|twitterbot|'
         . 'linkedinbot|redditbot|vkshare|w3c_validator|'
         . 'headless|phantomjs|puppeteer|playwright|selenium|cypress|'
         . 'python-requests|python-urllib|aiohttp|httpx|curl\/|wget|libwww|'
         . 'java\/|okhttp|go-http-client|node-fetch|axios|guzzle|scrapy|'
         . 'semrush|ahrefs|mj12bot|dotbot|petalbot|bytespider|dataforseo|'
         . 'gptbot|claudebot|claude-web|ccbot|amazonbot|applebot|bytedance|'
         . 'censys|masscan|zgrab|nmap|nikto|wpscan|fuzz/i';
    if (preg_match($bot, $ua)) {
        return 'bot';
    }
    // Sembra un vero browser? (token tipici dei motori di rendering reali)
    if (preg_match('/mozilla|applewebkit|gecko|chrome|crios|safari|firefox|fxios|edg|opr\/|opera|samsungbrowser/i', $ua)) {
        return 'utente';
    }
    return 'sospetto';
}

/**
 * Ultime visite (= sessioni) nella finestra, una riga per session_id, con i
 * segnali utili a capire se sono bot, utenti o altro: tipo (da UA), pagine,
 * durata, sorgente, IP (hash) e user-agent. Ordina dalla più recente.
 */
function faro_recent_visits(PDO $pdo, int $days, bool $demo = false, int $limit = 100): array
{
    $d = faro_days($days);
    $dm = faro_demo_and($demo);
    $limit = max(1, min(500, $limit));
    $uaSel = faro_has_column($pdo, FARO_TBL_EVENTS, 'ua') ? 'MAX(ua)' : 'NULL';

    $rows = $pdo->query(
        "SELECT
            session_id,
            MIN(ts)                                 AS first_ts,
            MAX(ts)                                 AS last_ts,
            TIMESTAMPDIFF(SECOND, MIN(ts), MAX(ts)) AS dur,
            MAX(app)                                AS app,
            MAX(NULLIF(src,''))                     AS src,
            CAST(SUM(event='pageview') AS UNSIGNED) AS pageviews,
            COUNT(*)                                AS events,
            MAX(ip_hash)                            AS ip_hash,
            $uaSel                                  AS ua,
            COALESCE(SUM(revenue_micros),0)         AS rev_micros
         FROM faro_events
         WHERE ts >= (NOW() - INTERVAL $d DAY)$dm
         GROUP BY session_id
         ORDER BY first_ts DESC
         LIMIT $limit"
    )->fetchAll();

    foreach ($rows as &$r) {
        $r['pageviews']   = (int) $r['pageviews'];
        $r['events']      = (int) $r['events'];
        $r['dur']         = (int) $r['dur'];
        $r['revenue_eur'] = faro_eur((int) $r['rev_micros']);
        $r['kind']        = faro_visit_kind($r['ua'] ?? null);
        unset($r['rev_micros']);
    }
    return $rows;
}

/** True se esiste almeno una riga demo (per mostrare il toggle). */
function faro_has_demo(PDO $pdo): bool
{
    try {
        return (bool) $pdo->query("SELECT EXISTS(SELECT 1 FROM faro_events WHERE is_demo=1)")->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/** Tutto in un colpo: comodo per API JSON / Analyst / mail. */
function faro_report(PDO $pdo, int $days, bool $demo = false): array
{
    return [
        'days'        => faro_days($days),
        'demo'        => $demo,
        'overview'    => faro_overview($pdo, $days, $demo),
        'per_app'     => faro_per_app($pdo, $days, $demo),
        'acquisition' => faro_acquisition($pdo, $days, null, $demo),
        'mediation'   => faro_mediation($pdo, $days, $demo),
        'retention'   => faro_retention($pdo, $days, $demo),
        'timeseries'  => faro_timeseries($pdo, $days, $demo),
    ];
}
