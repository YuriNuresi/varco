<?php
declare(strict_types=1);
$pageTitle = 'Privacy & Cookie Policy';
$pageDescription = 'Informativa privacy e cookie policy di Varco, gioco di carte fan-made non commerciale.';
require __DIR__ . '/partials/header.php';
?>
<section class="card-panel">
    <h1>Privacy & Cookie Policy</h1>
    <p><strong>Ultimo aggiornamento:</strong> 25 luglio 2026</p>

    <h2>Titolare del trattamento</h2>
    <p>Varco è un progetto fan non commerciale, mai monetizzato, gestito a titolo personale.<br>
       Contatto: <a href="mailto:info@portale3d.it">info@portale3d.it</a></p>

    <h2>Dati raccolti</h2>
    <ul class="rules">
        <li><strong>Autenticazione Google (opzionale):</strong> se scegli di accedere con Google, riceviamo nome, email e foto profilo.
            Questi dati servono esclusivamente a salvare i tuoi mazzi e progressi. Non vengono condivisi con terzi.</li>
        <li><strong>Analytics (Faro):</strong> utilizziamo un sistema di analytics proprietario (Faro) ospitato sullo stesso server.
            Raccoglie: pagine visitate, timestamp, user-agent, referrer. Non raccoglie dati personali identificativi e non utilizza cookie di profilazione.</li>
        <li><strong>Dati di gioco:</strong> mazzi, campagne e punteggi sono salvati nel database e associati alla tua sessione o al tuo account Google.</li>
    </ul>

    <h2>Cookie utilizzati</h2>
    <ul class="rules">
        <li><strong>PHPSESSID</strong> — cookie tecnico di sessione, necessario per il funzionamento del sito. Scade alla chiusura del browser.</li>
        <li><strong>Faro</strong> — cookie tecnico di analytics anonimo (first-party). Nessun tracciamento cross-site.</li>
    </ul>
    <p>Non utilizziamo cookie di profilazione, pubblicitari o di terze parti.</p>

    <h2>Base giuridica</h2>
    <p>Il trattamento dei dati tecnici si basa sul legittimo interesse (funzionamento del sito).
       L'autenticazione Google è facoltativa e si basa sul consenso esplicito dell'utente.</p>

    <h2>Conservazione dei dati</h2>
    <p>I dati di sessione vengono eliminati alla chiusura del browser.
       I dati di account (mazzi, progressi) vengono conservati fino alla cancellazione dell'account.
       I log di analytics sono aggregati e anonimizzati entro 90 giorni.</p>

    <h2>Diritti dell'utente</h2>
    <p>Ai sensi del GDPR (Reg. UE 2016/679), puoi richiedere l'accesso, la rettifica, la cancellazione
       o la portabilità dei tuoi dati scrivendo a <a href="mailto:info@portale3d.it">info@portale3d.it</a>.</p>

    <h2>Modifiche</h2>
    <p>Questa informativa può essere aggiornata. La data di ultimo aggiornamento è indicata in cima alla pagina.</p>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
