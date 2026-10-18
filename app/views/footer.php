</main>
<footer class="foot">&copy; <?= date('Y') ?> <?= e(site_name()) ?> · Todos os direitos reservados</footer>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php if (!empty($withPlayer)): ?><script src="<?= e(asset('js/player.js')) ?>"></script><?php endif; ?>
</body>
</html>
