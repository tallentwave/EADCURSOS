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
    } elseif (empty($_POST['lgpd'])) {
        $error = 'É necessário aceitar a Política de Privacidade para criar a conta.';
    } elseif (trim($_POST['cpf'] ?? '') !== '' && !cpf_valid($_POST['cpf'])) {
        $error = 'CPF inválido.';
    } elseif (trim($_POST['cpf'] ?? '') !== '' && q_val('SELECT 1 FROM users WHERE cpf_hash = ?', [cpf_hash($_POST['cpf'])])) {
        $error = 'Este CPF já está vinculado a outra conta.';
    } elseif (q_val('SELECT 1 FROM users WHERE email = ?', [$email])) {
        record_attempt('reg:' . client_ip());
        $error = 'Este e-mail já está cadastrado. Faça login.';
    } else {
        q('INSERT INTO users (name, email, password_hash, role, created_at, lgpd_consent_at) VALUES (?, ?, ?, "student", ?, ?)',
            [$name, $email, password_hash($pass, PASSWORD_DEFAULT), now(), now()]);
        $newId = (int)db()->lastInsertId();
        if (trim($_POST['cpf'] ?? '') !== '') set_user_cpf($newId, $_POST['cpf']);
        $u = q_one('SELECT * FROM users WHERE id = ?', [$newId]);
        login_user($u);
        mail_welcome($u);
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
<label>CPF <small>(opcional agora; exigido para emitir certificado)</small></label><input type="text" name="cpf" inputmode="numeric" maxlength="14" autocomplete="off" placeholder="000.000.000-00" value="<?= e($_POST['cpf'] ?? '') ?>">
<div class="hint">Guardado criptografado. Nunca exibimos o número completo.</div>
<label class="check"><input type="checkbox" name="lgpd" value="1" required> <span>Li e aceito a <a href="<?= e(url('privacidade.php')) ?>" target="_blank">Política de Privacidade</a>.</span></label>
<br><button class="btn btn-block">Cadastrar</button>
<div class="links">Já tem conta? <a href="<?= e(url('login.php')) ?>">Entrar</a></div>
</form></div></body></html>
