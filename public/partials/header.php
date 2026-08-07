<?php
/** Header HTML condiviso. Usa la variabile $pageTitle se definita. */
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'Battaglia di corsie';

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
$_varcoUser  = current_user();
$_varcoAdmin = is_admin();

// --- Faro: tracking server-side (pageview + src, immune adblock). --------------
// Guardia is_file: in locale (dove Faro non è raggiungibile) è un no-op innocuo.
$faroTrack = __DIR__ . '/../../../3d/faro/track.php';
if (is_file($faroTrack)) { require_once $faroTrack; faro_track('varco'); }
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> — varco</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,500&family=Grenze+Gotisch:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css?v=dnd7">
    <!-- Faro: analytics cross-game -->
    <script src="https://portale3d.it/faro/sdk.js" data-app="varco" data-endpoint="https://portale3d.it/faro/collect.php"></script>
    <script>window.faro && faro.track('app_open');</script>
</head>
<body class="<?= htmlspecialchars($bodyClass ?? '') ?>">
<div class="sky" aria-hidden="true">
    <div class="stars"></div>
    <svg class="astro" viewBox="0 0 760 760" xmlns="http://www.w3.org/2000/svg">
        <g fill="none" stroke="#c4a259" stroke-width="1">
            <circle cx="380" cy="380" r="372"/><circle cx="380" cy="380" r="318"/>
            <circle cx="380" cy="380" r="250"/><circle cx="380" cy="380" r="150"/>
            <g stroke-width=".7">
                <line x1="8" y1="380" x2="752" y2="380"/><line x1="380" y1="8" x2="380" y2="752"/>
                <line x1="118" y1="118" x2="642" y2="642"/><line x1="642" y1="118" x2="118" y2="642"/>
                <polygon points="380,62 642,256 542,562 218,562 118,256"/>
                <polygon points="380,130 560,300 490,520 270,520 200,300"/>
            </g>
        </g>
        <g fill="#c4a259">
            <circle cx="380" cy="62" r="3"/><circle cx="642" cy="256" r="3"/>
            <circle cx="542" cy="562" r="3"/><circle cx="218" cy="562" r="3"/><circle cx="118" cy="256" r="3"/>
        </g>
    </svg>
    <div class="grain"></div>
    <div class="vignette"></div>
</div>
<button class="hamburger" id="hamburger" aria-label="Menu" aria-expanded="false">☰</button>
<header class="topbar" id="topbar">
    <a class="brand" href="/index.php">Varco</a>
    <nav>
        <a href="/index.php">Soglia</a>
        <a href="/campaign.php">Campagna</a>
        <a href="/draft.php">Buste</a>
        <a href="/deckbuilder.php">Grimorio</a>
        <a href="/catalogo.php">Catalogo</a>
        <a href="/end.php">Riepilogo</a>
        <?php if ($_varcoAdmin): ?>
            <span class="nav-sep">|</span>
            <a href="/card-editor.php" class="nav-admin">Fucina</a>
            <a href="/campaign-editor.php" class="nav-admin">Editor Camp.</a>
        <?php endif; ?>
    </nav>
    <div class="auth-area">
        <?php if ($_varcoUser): ?>
            <?php if ($_varcoUser['picture']): ?>
                <img src="<?= htmlspecialchars($_varcoUser['picture']) ?>" alt="" class="auth-avatar">
            <?php endif; ?>
            <a href="/auth_logout.php" class="btn ghost btn-sm">Esci</a>
        <?php else: ?>
            <a href="/auth_google.php" class="btn ghost btn-sm">Accedi</a>
        <?php endif; ?>
    </div>
</header>
<script>
(function(){
  var h=document.getElementById('hamburger'),t=document.getElementById('topbar');
  if(!h)return;
  h.addEventListener('click',function(){
    var open=t.classList.toggle('open');
    h.setAttribute('aria-expanded',open);
    h.textContent=open?'✕':'☰';
  });
})();
</script>
<main class="container">
