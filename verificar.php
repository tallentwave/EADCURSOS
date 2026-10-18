<?php
require __DIR__ . '/app/bootstrap.php';
$code = strtoupper(trim((string)($_GET['codigo'] ?? '')));
$cert = null;
if ($code !== '' && preg_match('/^[A-F0-9]{12}$/', $code)) {
    $cert = q_one('SELECT ce.*, u.name, c.title FROM certificates ce JOIN users u ON u.id = ce.user_id JOIN courses c ON c.id = ce.course_id WHERE ce.code = ?', [$code]);
}
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Verificar certificado · <?= e(site_name()) ?></title><link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"></head>
<body><div class="auth-wrap"><form class="auth-box" method="get">
<h1>Verificar certificado</h1>
<label>Código</label><input type="text" name="codigo" maxlength="12" value="<?= e($code) ?>" placeholder="Ex.: A1B2C3D4E5F6"><br>
<button class="btn btn-block">Verificar</button>
<?php if ($code !== ''): ?>
  <?php if ($cert): ?><div class="alert alert-ok"><strong><?= e($cert['name']) ?></strong> concluiu o curso <strong><?= e($cert['title']) ?></strong> em <?= e(date('d/m/Y', strtotime($cert['issued_at']))) ?>. Certificado válido.</div>
  <?php else: ?><div class="alert alert-err">Certificado não encontrado.</div><?php endif; ?>
<?php endif; ?>
</form></div></body></html>
