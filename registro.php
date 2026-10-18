<?php
require __DIR__ . '/app/bootstrap.php';
if (current_user()) redirect('home.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    if (too_many_attempts('reg:' . client_ip())) {
        $error = 'Muitas tentativas. Aguarde alguns minutos.';
    } elseif ($name === '' || mb_strlen($name) > 120) {
        $error = 'Informe seu nome.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $error = 'E-mail inválido.';
    } elseif ($err = password_ok($pass)) {
        $error = $err;
    } elseif ($pass !== ($_POST['password2'] ?? '')) {
        $error = 'As senhas não conferem.';
    } elseif (q_val('SELECT 1 FROM users WHERE email = ?', [$email])) {
        record_attempt('reg:' . client_ip());
        $error = 'Este e-mail já está cadastrado. Faça login.';
    } else {
        q('INSERT INTO users (name, email, password_hash, role, created_at) VALUES (?, ?, ?, "student", ?)',
            [$name, $email, password_hash($pass, PASSWORD_DEFAULT), now()]);
        $u = q_one('SELECT * FROM users WHERE id = ?', [db()->lastInsertId()]);
        login_user($u);
        redirect('home.php');
    }
}
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cadastro · <?= e(site_name()) ?></title><link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"></head>
<body><div class="auth-wrap"><form class="auth-box" method="post">
<h1>Criar conta</h1><p class="sub">Cadastre-se para adquirir cursos</p>
<?php if ($error): ?><div class="alert alert-err"><?= e($error) ?></div><?php endif; ?>
<?= csrf_field() ?>
<label>Nome completo</label><input type="text" name="name" required maxlength="120" value="<?= e($_POST['name'] ?? '') ?>">
<label>E-mail</label><input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
<label>Senha</label><input type="password" name="password" required minlength="8"><div class="hint">Mínimo 8 caracteres, com letras e números.</div>
<label>Repita a senha</label><input type="password" name="password2" required>
<br><button class="btn btn-block">Cadastrar</button>
<div class="links">Já tem conta? <a href="<?= e(url('login.php')) ?>">Entrar</a></div>
</form></div></body></html>
