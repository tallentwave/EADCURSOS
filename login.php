<?php
require __DIR__ . '/app/bootstrap.php';
remember_next();
if (current_user()) redirect(safe_next());

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    if (too_many_attempts($email)) {
        $error = 'Muitas tentativas. Aguarde 15 minutos e tente novamente.';
    } else {
        $u = q_one('SELECT * FROM users WHERE email = ?', [$email]);
        // verificação em tempo constante mesmo quando o usuário não existe
        $hash = $u['password_hash'] ?? password_hash('dummy-' . bin2hex(random_bytes(4)), PASSWORD_DEFAULT);
        if ($u && password_verify($pass, $hash) && $u['status'] === 'active') {
            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
            }
            login_user($u);
            redirect(safe_next());
        }
        record_attempt($email);
        $error = 'E-mail ou senha inválidos.';
    }
}
$title = 'Entrar';
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar · <?= e(site_name()) ?></title><link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"></head>
<body><div class="auth-wrap"><form class="auth-box" method="post" autocomplete="on">
<h1>&#9670; <?= e(site_name()) ?></h1><p class="sub">Acesse sua área de membros</p>
<?= flashes() ?>
<?php if ($error): ?><div class="alert alert-err"><?= e($error) ?></div><?php endif; ?>
<?= csrf_field() ?>
<label for="email">E-mail</label><input id="email" type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
<label for="password">Senha</label><input id="password" type="password" name="password" required>
<br><button class="btn btn-block">Entrar</button>
<div class="links"><a href="<?= e(url('esqueci.php')) ?>">Esqueci minha senha</a><br><br>Ainda não tem conta? <a href="<?= e(url('registro.php')) ?>">Cadastre-se</a><br><br><small><a href="<?= e(url('verificar.php')) ?>">Validar certificado</a> · <a href="<?= e(url('privacidade.php')) ?>">Privacidade</a></small></div>
</form></div></body></html>
