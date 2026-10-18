<?php
require __DIR__ . '/app/bootstrap.php';
$u = require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $row = q_one('SELECT * FROM users WHERE id = ?', [$u['id']]);
    if (($_POST['action'] ?? '') === 'name') {
        $n = trim($_POST['name'] ?? '');
        if ($n === '' || mb_strlen($n) > 120) flash('err', 'Nome inválido.');
        else { q('UPDATE users SET name = ? WHERE id = ?', [$n, $u['id']]); flash('ok', 'Nome atualizado.'); }
    } elseif (($_POST['action'] ?? '') === 'password') {
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify((string)($_POST['current'] ?? ''), $row['password_hash'])) flash('err', 'Senha atual incorreta.');
        elseif ($err = password_ok($new)) flash('err', $err);
        elseif ($new !== ($_POST['new2'] ?? '')) flash('err', 'As senhas não conferem.');
        else {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            flash('ok', 'Senha alterada.');
        }
    }
    redirect('perfil.php');
}
$orders = q_all('SELECT o.*, c.title FROM orders o JOIN courses c ON c.id = o.course_id WHERE o.user_id = ? ORDER BY o.id DESC', [$u['id']]);
$certs = q_all('SELECT ce.*, c.title FROM certificates ce JOIN courses c ON c.id = ce.course_id WHERE ce.user_id = ?', [$u['id']]);
$title = 'Minha conta';
require ROOT . '/app/views/header.php';
$st = ['pending' => ['Pendente', 'warn'], 'paid' => ['Pago', 'ok'], 'cancelled' => ['Cancelado', 'bad'], 'refunded' => ['Reembolsado', 'bad']];
?>
<div class="container" style="max-width:900px">
  <h1 style="color:var(--gold);margin-bottom:16px">Minha conta</h1>
  <div class="grid2">
    <form class="formbox" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="name">
      <label>Nome</label><input type="text" name="name" value="<?= e($u['name']) ?>" required>
      <label>E-mail</label><input type="email" value="<?= e($u['email']) ?>" disabled><br><button class="btn btn-sm">Salvar</button></form>
    <form class="formbox" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password">
      <label>Senha atual</label><input type="password" name="current" required>
      <label>Nova senha</label><input type="password" name="new" required minlength="8">
      <label>Repita a nova senha</label><input type="password" name="new2" required><br><button class="btn btn-sm">Alterar senha</button></form>
  </div>
  <h2 style="margin:10px 0 12px">Meus pedidos</h2>
  <div class="table-wrap"><table><tr><th>#</th><th>Curso</th><th>Valor</th><th>Status</th><th>Data</th></tr>
  <?php foreach ($orders as $o): ?><tr><td><?= (int)$o['id'] ?></td><td><?= e($o['title']) ?></td><td><?= money($o['amount']) ?></td>
    <td><span class="tag-s tag-<?= $st[$o['status']][1] ?>"><?= $st[$o['status']][0] ?></span></td><td><?= e(date('d/m/Y H:i', strtotime($o['created_at']))) ?></td></tr>
  <?php endforeach; if (!$orders): ?><tr><td colspan="5">Nenhum pedido.</td></tr><?php endif; ?></table></div>
  <?php if ($certs): ?><h2 style="margin:24px 0 12px">Certificados</h2>
  <?php foreach ($certs as $ce): ?><p>&#127942; <a href="<?= e(url('certificado.php?curso=' . $ce['course_id'])) ?>"><?= e($ce['title']) ?></a> · <small>Código <?= e($ce['code']) ?></small></p><?php endforeach; endif; ?>
</div>
<?php require ROOT . '/app/views/footer.php'; ?>
