<?php
require __DIR__ . '/app/bootstrap.php';
$u = require_login();
$done = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$ok, $msg] = voucher_redeem((int)$u['id'], (string)($_POST['code'] ?? ''));
    if ($ok) $done = $msg; else flash('err', $msg);
}
$title = 'Resgatar voucher';
require ROOT . '/app/views/header.php';
?>
<div class="container" style="max-width:560px;padding-top:50px">
<h1 style="color:var(--gold)">Resgatar voucher</h1>
<?php if ($done !== null): ?>
  <div class="alert alert-ok" style="margin:20px 0">Voucher resgatado! Você agora tem acesso ao curso <strong><?= e($done) ?></strong>.</div>
  <a class="btn" href="<?= e(url('home.php#meus-cursos')) ?>">Ir para meus cursos</a>
<?php else: ?>
  <p class="hint" style="margin:8px 0 0">Digite o código recebido (formato XXXX-XXXX-XXXX).</p>
  <form class="formbox" method="post" style="margin-top:16px"><?= csrf_field() ?>
    <label>Código do voucher</label><input type="text" name="code" required maxlength="20" autocomplete="off" style="text-transform:uppercase;letter-spacing:2px" placeholder="ABCD-EFGH-JKLM">
    <br><button class="btn">Resgatar</button></form>
<?php endif; ?>
</div>
<?php require ROOT . '/app/views/footer.php'; ?>
