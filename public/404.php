<?php
declare(strict_types=1);
http_response_code(404);
$pageTitle = 'Pagina non trovata';
require __DIR__ . '/partials/header.php';
?>
<section class="card-panel" style="text-align:center;padding:3rem 1rem">
    <h1 style="font-size:3rem;margin-bottom:.5rem">404</h1>
    <h2>Pagina non trovata</h2>
    <p>Il varco che cerchi non esiste o è stato chiuso.</p>
    <div style="margin-top:2rem">
        <a class="btn primary" href="index.php">Torna alla Soglia</a>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
