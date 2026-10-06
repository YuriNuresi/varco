</main>
<footer class="legal">
    <nav class="footer-links">
        <a href="privacy.php">Privacy & Cookie Policy</a>
        <span class="footer-sep">·</span>
        <a href="contatti.php">Contatti</a>
        <span class="footer-sep">·</span>
        <a href="index.php">Home</a>
    </nav>
    <p>
        Unofficial Fan Content permitted under the Wizards of the Coast Fan Content Policy.
        Not approved/endorsed by Wizards. Portions of the materials used are property of
        Wizards of the Coast. ©Wizards of the Coast LLC.
    </p>
    <p class="legal-small">Progetto fan non commerciale, mai monetizzato. © <?= date('Y') ?> Varco</p>
</footer>

<!-- Barra musicale di sottofondo (base: player footer di portale3d, tema oro) -->
<div class="player" id="varco-player" hidden>
    <button class="pbtn" id="pPlay" aria-label="Play / Pausa">▶</button>
    <div class="eqm paused" id="pEq"><i></i><i></i><i></i><i></i></div>
    <div class="ptit"><b>VARCO</b><span id="pTitle">Musica</span></div>
    <button class="pbtn" id="pSkip" aria-label="Traccia successiva">⏭</button>
    <div class="vol">VOL <input type="range" id="pVol" min="0" max="100" value="15" aria-label="Volume"><span id="pVolN">15</span></div>
</div>
<script src="assets/player.js?v=dnd7"></script>
</body>
</html>
