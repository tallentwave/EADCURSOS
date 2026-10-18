<?php
require __DIR__ . '/app/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $row = q_one('SELECT * FROM users WHERE id = ?', [$uid]);
    $a = $_POST['action'] ?? '';
    if ($a === 'name') {
        $n = trim($_POST['name'] ?? '');
        if ($n === '' || mb_strlen($n) > 120) flash('err', 'Nome inválido.');
        else { q('UPDATE users SET name = ? WHERE id = ?', [$n, $uid]); flash('ok', 'Nome atualizado. Certificados já emitidos mantêm o nome da época da emissão.'); }
    } elseif ($a === 'password') {
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify((string)($_POST['current'] ?? ''), $row['password_hash'])) flash('err', 'Senha atual incorreta.');
        elseif ($err = password_ok($new)) flash('err', $err);
        elseif ($new !== ($_POST['new2'] ?? '')) flash('err', 'As senhas não conferem.');
        else { q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $uid]); session_regenerate_id(true); flash('ok', 'Senha alterada.'); }
    } elseif ($a === 'cpf') {
        if (q_val('SELECT 1 FROM certificates WHERE user_id = ?', [$uid])) flash('err', 'O CPF não pode ser alterado depois de emitir um certificado. Fale com o suporte.');
        elseif ($err = set_user_cpf($uid, (string)($_POST['cpf'] ?? ''))) flash('err', $err);
        else flash('ok', 'CPF salvo com segurança.');
        redirect('perfil.php#cpf');
    } elseif ($a === 'cert_visibility') {
        q('UPDATE certificates SET show_details = ? WHERE id = ? AND user_id = ?', [isset($_POST['show']) ? 1 : 0, (int)($_POST['id'] ?? 0), $uid]);
        flash('ok', 'Preferência de privacidade atualizada.');
        redirect('perfil.php#certificados');
    } elseif ($a === 'export') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="meus-dados.json"');
        header('Cache-Control: no-store');
        echo json_encode(export_user_data($uid), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    } elseif ($a === 'delete_account') {
        if ($u['role'] === 'admin') flash('err', 'Contas de administrador não podem ser excluídas por aqui.');
        elseif (!password_verify((string)($_POST['password'] ?? ''), $row['password_hash']) || trim($_POST['confirm'] ?? '') !== 'EXCLUIR') flash('err', 'Confirme sua senha e digite EXCLUIR.');
        else { erase_user($uid); logout_user(); session_start(); flash('ok', 'Sua conta e dados pessoais foram excluídos.'); redirect('login.php'); }
    }
    redirect('perfil.php');
}
$me = q_one('SELECT * FROM users WHERE id = ?', [$uid]);
$cpfMasked = cpf_mask(user_cpf($me));
$orders = q_all('SELECT o.*, c.title FROM orders o JOIN courses c ON c.id = o.course_id WHERE o.user_id = ? ORDER BY o.id DESC', [$uid]);
$certs = q_all('SELECT * FROM certificates WHERE user_id = ? ORDER BY id DESC', [$uid]);
$title = 'Minha conta';
require ROOT . '/app/views/header.php';
$st = ['pending' => ['Pendente', 'warn'], 'paid' => ['Pago', 'ok'], 'cancelled' => ['Cancelado', 'bad'], 'refunded' => ['Reembolsado', 'bad']];
?>
<div class="container" style="max-width:900px">
  <h1 style="color:var(--gold);margin-bottom:16px">Minha conta</h1>
  <div class="grid2">
    <form class="formbox" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="name">
      <label>Nome</label><input type="text" name="name" value="<?= e($me['name']) ?>" required>
      <label>E-mail</label><input type="email" value="<?= e($me['email']) ?>" disabled><br><button class="btn btn-sm">Salvar</button></form>
    <form class="formbox" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password">
      <label>Senha atual</label><input type="password" name="current" required>
      <label>Nova senha</label><input type="password" name="new" required minlength="8">
      <label>Repita a nova senha</label><input type="password" name="new2" required><br><button class="btn btn-sm">Alterar senha</button></form>
  </div>

  <form class="formbox" method="post" id="cpf"><?= csrf_field() ?><input type="hidden" name="action" value="cpf">
    <h2 style="margin:14px 0 4px">CPF</h2>
    <p class="hint">Necessário para emitir certificados. Fica <strong>criptografado</strong> e só é exibido de forma parcial (ex.: ***.123.456-**).</p>
    <?php if ($cpfMasked): ?><p style="margin:10px 0"><span class="tag-s tag-ok">Cadastrado</span> &nbsp;<?= e($cpfMasked) ?></p><?php endif; ?>
    <?php if (!$certs): ?><label><?= $cpfMasked ? 'Substituir CPF' : 'Informe seu CPF' ?></label><input type="text" name="cpf" inputmode="numeric" maxlength="14" autocomplete="off" placeholder="000.000.000-00" required><br><button class="btn btn-sm">Salvar CPF</button>
    <?php else: ?><p class="hint">Bloqueado para alteração porque você já emitiu certificado.</p><?php endif; ?></form>

  <h2 style="margin:10px 0 12px" id="certificados">Certificados</h2>
  <?php foreach ($certs as $ce): ?>
  <div class="formbox"><p style="margin-top:14px">&#127942; <strong><?= e($ce['course_title']) ?></strong> <small style="color:var(--muted)">· emitido em <?= e(date('d/m/Y', strtotime($ce['issued_at']))) ?> · código <?= e(cert_code_display($ce['code'])) ?></small>
    <?= $ce['status'] === 'revoked' ? ' <span class="tag-s tag-bad">Revogado</span>' : '' ?></p>
    <div class="toolbar" style="margin-top:8px">
      <?php if ($ce['status'] !== 'revoked'): ?><a class="btn btn-sm" href="<?= e(url('certificado.php?curso=' . $ce['course_id'])) ?>">Ver / imprimir</a><?php endif; ?>
      <a class="btn btn-ghost btn-sm" target="_blank" href="<?= e(cert_public_url($ce['code'])) ?>">Ver página pública</a></div>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cert_visibility"><input type="hidden" name="id" value="<?= (int)$ce['id'] ?>">
      <label class="check"><input type="checkbox" name="show" value="1" <?= $ce['show_details'] ? 'checked' : '' ?> data-autosubmit> Exibir meu nome completo, CPF parcial e aproveitamento na página pública de validação</label>
      <noscript><button class="btn btn-sm">Salvar</button></noscript>
      <div class="hint">Desmarcado: a página pública mostra só o nome abreviado, o curso e as datas — sem CPF.</div></form></div>
  <?php endforeach; if (!$certs): ?><p class="hint" style="margin-bottom:14px">Você ainda não emitiu certificados.</p><?php endif; ?>

  <h2 style="margin:10px 0 12px">Meus pedidos</h2>
  <div class="table-wrap"><table><tr><th>#</th><th>Curso</th><th>Valor</th><th>Status</th><th>Data</th></tr>
  <?php foreach ($orders as $o): ?><tr><td><?= (int)$o['id'] ?></td><td><?= e($o['title']) ?></td><td><?= money($o['amount']) ?></td>
    <td><span class="tag-s tag-<?= $st[$o['status']][1] ?>"><?= $st[$o['status']][0] ?></span></td><td><?= e(date('d/m/Y H:i', strtotime($o['created_at']))) ?></td></tr>
  <?php endforeach; if (!$orders): ?><tr><td colspan="5">Nenhum pedido.</td></tr><?php endif; ?></table></div>

  <h2 style="margin:28px 0 12px">Privacidade e meus dados (LGPD)</h2>
  <div class="grid2">
    <form class="formbox" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="export">
      <h3 style="margin:14px 0 6px;color:var(--gold)">Baixar meus dados</h3><p class="hint">Cópia em JSON de tudo o que guardamos sobre você.</p><br><button class="btn btn-ghost btn-sm">Baixar arquivo</button></form>
    <?php if ($u['role'] !== 'admin'): ?>
    <form class="formbox" method="post" data-confirm="Esta ação é definitiva. Excluir sua conta, progresso e certificados?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_account">
      <h3 style="margin:14px 0 6px;color:var(--danger)">Excluir minha conta</h3><p class="hint">Remove seus dados pessoais, progresso, comentários e certificados. Registros de pedidos são mantidos de forma anonimizada (obrigação fiscal).</p>
      <label>Sua senha</label><input type="password" name="password" required><label>Digite EXCLUIR para confirmar</label><input type="text" name="confirm" required autocomplete="off"><br><button class="btn btn-danger btn-sm">Excluir definitivamente</button></form>
    <?php endif; ?>
  </div>
  <p class="hint"><a href="<?= e(url('privacidade.php')) ?>">Ler a Política de Privacidade</a></p>
</div>
<?php require ROOT . '/app/views/footer.php'; ?>
