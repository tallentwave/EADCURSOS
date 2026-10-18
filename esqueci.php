<?php
require __DIR__ . '/app/bootstrap.php';
$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!too_many_attempts('rst:' . client_ip())) {
        record_attempt('rst:' . client_ip());
        $u = q_one('SELECT id, name, email FROM users WHERE email = ? AND status = "active"', [$email]);
        if ($u) {
            $token = bin2hex(random_bytes(32));
            q('DELETE FROM password_resets WHERE user_id = ?', [$u['id']]);
            q('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)',
                [$u['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);
            mail_send($u['email'], 'Redefinição de senha', "Olá, {$u['name']}!\n\nPara criar uma nova senha acesse (válido por 1 hora):\n" . url('redefinir.php?token=' . $token) . "\n\nSe você não solicitou, ignore este e-mail.");
        }
    }
    $sent = true; // resposta idêntica exista ou não o e-mail
}
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Recuperar senha · <?= e(site_name()) ?></title><link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"></head>
<body><div class="auth-wrap"><form class="auth-box" method="post">
<h1>Recuperar senha</h1>
<?php if ($sent): ?><div class="alert alert-ok">Se o e-mail estiver cadastrado, enviamos um link para redefinir a senha.</div>
<?php else: ?><p class="sub">Informe seu e-mail para receber o link.</p><?= csrf_field() ?>
<label>E-mail</label><input type="email" name="email" required><br><button class="btn btn-block">Enviar link</button><?php endif; ?>
<div class="links"><a href="<?= e(url('login.php')) ?>">Voltar ao login</a></div>
</form></div></body></html>
