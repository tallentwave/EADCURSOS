<?php
require __DIR__ . '/app/bootstrap.php';
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$row = preg_match('/^[a-f0-9]{64}$/', $token)
    ? q_one('SELECT * FROM password_resets WHERE token_hash = ? AND used = 0 AND expires_at > ?', [hash('sha256', $token), now()])
    : null;
$error = '';
if ($row && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pass = (string)($_POST['password'] ?? '');
    if ($err = password_ok($pass)) $error = $err;
    elseif ($pass !== ($_POST['password2'] ?? '')) $error = 'As senhas não conferem.';
    else {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $row['user_id']]);
        q('UPDATE password_resets SET used = 1 WHERE id = ?', [$row['id']]);
        flash('ok', 'Senha alterada. Faça login.');
        redirect('login.php');
    }
}
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nova senha · <?= e(site_name()) ?></title><link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"></head>
<body><div class="auth-wrap"><form class="auth-box" method="post">
<?= auth_logo_html() ?><h1>Nova senha</h1>
<?php if (!$row): ?><div class="alert alert-err">Link inválido ou expirado.</div>
<?php else: ?>
<?php if ($error): ?><div class="alert alert-err"><?= e($error) ?></div><?php endif; ?>
<?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
<label>Nova senha</label><input type="password" name="password" required minlength="8">
<label>Repita</label><input type="password" name="password2" required><br><button class="btn btn-block">Salvar</button>
<?php endif; ?>
<div class="links"><a href="<?= e(url('login.php')) ?>">Voltar ao login</a></div>
</form></div></body></html>
