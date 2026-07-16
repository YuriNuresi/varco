<?php
/**
 * Faro — briefing mattutino via mail. Cron OVH ogni mattina.
 *
 *   GET /faro/digest.php?key=FARO_CRON_KEY               invia il briefing
 *   GET /faro/digest.php?key=...&preview=1               mostra l'HTML (non invia)
 *   GET /faro/digest.php?key=...&demo=1                  usa i dati demo (test)
 *   GET /faro/digest.php?key=...&days=7&to=mail@x.it     override finestra/destinatario
 *
 * Include-as-lib: define('FARO_DIGEST_AS_LIB',true); require → solo faro_run_digest().
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/metrics.php';
require_once __DIR__ . '/lib/llm.php';
require_once __DIR__ . '/lib/mailer.php';

function faro_e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function faro_eur_it(float $v): string { return '€' . number_format($v, 2, ',', '.'); }
function faro_eur_it4(float $v): string { return '€' . number_format($v, 4, ',', '.'); }

/**
 * Costruisce ed eventualmente invia il briefing. Ritorna array di esito
 * (con 'html'). $preview=true non invia.
 */
function faro_run_digest(int $days = 7, bool $demo = false, bool $preview = false, ?string $to = null): array
{
    $days = max(1, min(90, $days));
    $to   = $to ?: FARO_MAIL_TO;

    $pdo = faro_db();
    $rep = faro_report($pdo, $days, $demo);
    $ov  = $rep['overview'];
    $topSrc = $rep['acquisition'][0] ?? null;
    $topNet = $rep['mediation'][0] ?? null;

    // Briefing scritto dall'Analyst.
    try {
        $briefing = trim(faro_llm_chat([
            ['role' => 'system', 'content' =>
                "Sei l'analista della console Faro (traffico e monetizzazione di 3 giochi web: varco, intercity, helios). "
                . "Scrivi il BRIEFING MATTUTINO in italiano, asciutto, SENZA markdown. Formato: 1 frase di sintesi, poi 3-4 righe "
                . "che iniziano con '- ' coi numeri chiave (visitatori, revenue, sorgente migliore, network migliore, retention), "
                . "segnala UN alert se qualcosa peggiora, e chiudi con 'Azione: ...' con UNA mossa concreta. Usa SOLO i numeri forniti."],
            ['role' => 'user', 'content' =>
                "Finestra: ultimi $days giorni" . ($demo ? ' (DATI DEMO)' : '') . ".\nReport JSON:\n"
                . json_encode($rep, JSON_UNESCAPED_UNICODE)],
        ], ['temperature' => 0.4, 'max_tokens' => 450]));
    } catch (Throwable $ex) {
        $briefing = "Briefing automatico non disponibile (LLM: " . $ex->getMessage() . ").\n"
            . "- Visitatori: {$ov['visitors']} · Revenue: " . faro_eur_it($ov['revenue_eur']);
    }

    $dateLabel = date('d/m/Y');
    $subject = 'Faro · briefing del ' . $dateLabel . ($demo ? ' [demo]' : '');
    $kpis = [
        ['visitatori', number_format($ov['visitors'], 0, ',', '.')],
        ['revenue', faro_eur_it($ov['revenue_eur'])],
        ['revenue / visitatore', faro_eur_it4($ov['rev_per_visitor'])],
        ['rewarded completion', $ov['rewarded_completion'] . '%'],
        ['retention D1 / D7', $rep['retention']['d1'] . '% / ' . $rep['retention']['d7'] . '%'],
    ];

    ob_start(); ?>
<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:560px;margin:0 auto;color:#1d2129">
  <div style="display:flex;align-items:center;gap:10px;padding:18px 0;border-bottom:2px solid #1d9e75">
    <span style="font-size:22px">◎</span>
    <div>
      <div style="font-size:18px;font-weight:700">Faro <?php if ($demo): ?><span style="font-size:11px;background:#fae0a0;color:#7a5400;padding:2px 7px;border-radius:5px">dati demo</span><?php endif; ?></div>
      <div style="font-size:12px;color:#6b7280">briefing del <?= faro_e($dateLabel) ?> · ultimi <?= (int) $days ?> giorni</div>
    </div>
  </div>
  <div style="font-size:15px;line-height:1.65;padding:18px 0"><?= nl2br(faro_e($briefing)) ?></div>
  <table style="width:100%;border-collapse:collapse;font-size:14px">
    <?php foreach ($kpis as [$l, $v]): ?>
    <tr>
      <td style="padding:9px 0;border-bottom:1px solid #eceef1;color:#6b7280"><?= faro_e($l) ?></td>
      <td style="padding:9px 0;border-bottom:1px solid #eceef1;text-align:right;font-weight:600"><?= faro_e($v) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if ($topSrc): ?>
    <tr><td style="padding:9px 0;border-bottom:1px solid #eceef1;color:#6b7280">sorgente migliore</td>
        <td style="padding:9px 0;border-bottom:1px solid #eceef1;text-align:right;font-weight:600"><?= faro_e($topSrc['source']) ?> (<?= faro_eur_it4((float) $topSrc['rev_per_visitor']) ?>/vis)</td></tr>
    <?php endif; ?>
    <?php if ($topNet): ?>
    <tr><td style="padding:9px 0;color:#6b7280">network migliore</td>
        <td style="padding:9px 0;text-align:right;font-weight:600"><?= faro_e($topNet['network']) ?> (eCPM <?= faro_eur_it((float) $topNet['ecpm']) ?>)</td></tr>
    <?php endif; ?>
  </table>
  <div style="padding:20px 0">
    <a href="https://portale3d.it/faro/dashboard.php" style="background:#1d9e75;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-size:14px;font-weight:600">Apri la console</a>
  </div>
  <div style="font-size:11px;color:#9aa3b2;padding-top:12px;border-top:1px solid #eceef1">Faro · gestionale traffico e monetizzazione · portale3d.it</div>
</div>
<?php
    $html = ob_get_clean();

    if ($preview) {
        return ['preview' => true, 'html' => $html];
    }
    [$ok, $info] = faro_send_mail($to, $subject, $html);
    return [
        'ok'        => $ok,
        'to'        => $to,
        'transport' => FARO_SMTP_HOST !== '' ? 'smtp' : 'mail()',
        'info'      => $info,
        'html'      => $html,
    ];
}

// --- Handler diretto (web o CLI). Saltato quando incluso come libreria. --------
if (!defined('FARO_DIGEST_AS_LIB')) {
    $isCli = PHP_SAPI === 'cli';
    $key = (string) ($_GET['key'] ?? '');
    if (!$isCli && $key !== FARO_CRON_KEY && $key !== FARO_ADMIN_PASSWORD) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'forbidden';
        exit;
    }
    $res = faro_run_digest(
        (int) ($_GET['days'] ?? 7),
        !empty($_GET['demo']),
        !empty($_GET['preview']),
        isset($_GET['to']) ? (string) $_GET['to'] : null
    );
    if (!empty($res['preview'])) {
        header('Content-Type: text/html; charset=utf-8');
        echo $res['html'];
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    unset($res['html']);
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
}
