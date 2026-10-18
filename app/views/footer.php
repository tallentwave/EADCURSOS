</main>
<footer class="foot">&copy; <?= date('Y') ?> <?= e(site_name()) ?> · Todos os direitos reservados · <a href="<?= e(url('privacidade.php')) ?>">Privacidade</a> · <a href="<?= e(url('verificar.php')) ?>">Validar certificado</a></footer>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php if (!empty($withExam)): ?><script src="<?= e(asset('js/exam.js')) ?>"></script><?php endif; ?>
<?php if (!empty($withPlayer)): ?><script src="<?= e(asset('js/player.js')) ?>"></script><?php endif; ?>
</body>
</html>
