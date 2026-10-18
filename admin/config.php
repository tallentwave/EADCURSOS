<?php
require __DIR__ . '/_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'test_mail') {
        if (mail_send($admin['email'], 'Teste de e-mail', "Se você recebeu esta mensagem, o envio de e-mails está funcionando.\n\n" . site_name())) flash('ok', 'E-mail de teste enviado para ' . $admin['email'] . '.');
        else flash('err', 'Falha no envio: ' . mail_last_error());
        back('config.php');
    }
    set_setting('site_name', mb_substr(trim($_POST['site_name'] ?? '') ?: 'EAD Premium', 0, 60));
    set_setting('mp_sandbox', isset($_POST['mp_sandbox']) ? '1' : '0');

    // SMTP
    set_setting('mail_from', filter_var($_POST['mail_from'] ?? '', FILTER_VALIDATE_EMAIL) ?: '');
    $host = trim($_POST['smtp_host'] ?? '');
    set_setting('smtp_host', preg_match('/^[A-Za-z0-9.\-]*$/', $host) ? $host : '');
    set_setting('smtp_port', (string)max(1, min(65535, (int)($_POST['smtp_port'] ?? 587))));
    set_setting('smtp_enc', in_array($_POST['smtp_enc'] ?? '', ['ssl', 'tls', 'none'], true) ? $_POST['smtp_enc'] : 'tls');
    set_setting('smtp_user', trim($_POST['smtp_user'] ?? ''));

    // segredos criptografados: só substituem se o campo for preenchido
    foreach (['mp_access_token', 'mp_webhook_secret', 'smtp_pass'] as $k) {
        $v = trim($_POST[$k] ?? '');
        if ($v !== '') set_secret($k, $v);
        if (isset($_POST['clear_' . $k])) set_setting($k, '');
    }
    flash('ok', 'Configurações salvas.');
    back('config.php');
}
require ROOT . '/app/views/admin_header.php';
$enc = setting('smtp_enc', 'tls');
?>
<h1>Configurações</h1>
<form class="formbox" method="post"><?= csrf_field() ?>
<label>Nome da plataforma</label><input type="text" name="site_name" value="<?= e(site_name()) ?>">

<h2 style="margin:26px 0 0;color:var(--gold)">E-mail (SMTP)</h2>
<div class="hint">Usado em boas-vindas, recuperação de senha e confirmação de compra. Sem SMTP o sistema tenta a função mail() do servidor.</div>
<div class="grid2"><div><label>Servidor SMTP</label><input type="text" name="smtp_host" value="<?= e(setting('smtp_host')) ?>" placeholder="smtp.seudominio.com.br"></div>
<div><label>Porta</label><input type="number" name="smtp_port" value="<?= e(setting('smtp_port', '587')) ?>"></div>
<div><label>Segurança</label><select name="smtp_enc"><option value="tls" <?= $enc === 'tls' ? 'selected' : '' ?>>STARTTLS (587)</option><option value="ssl" <?= $enc === 'ssl' ? 'selected' : '' ?>>SSL/TLS (465)</option><option value="none" <?= $enc === 'none' ? 'selected' : '' ?>>Nenhuma (não recomendado)</option></select></div>
<div><label>Usuário</label><input type="text" name="smtp_user" value="<?= e(setting('smtp_user')) ?>" autocomplete="off"></div>
<div><label>Senha <?= setting('smtp_pass') !== '' ? '<span class="tag-s tag-ok">salva (criptografada)</span>' : '' ?></label><input type="password" name="smtp_pass" autocomplete="new-password" placeholder="deixe em branco para manter"></div>
<div><label>E-mail remetente</label><input type="email" name="mail_from" value="<?= e(setting('mail_from')) ?>" placeholder="contato@seudominio.com.br"></div></div>
<label class="check"><input type="checkbox" name="clear_smtp_pass"> Apagar senha SMTP salva</label>

<h2 style="margin:26px 0 0;color:var(--gold)">Mercado Pago</h2>
<label>Access Token <?= setting('mp_access_token') !== '' ? '<span class="tag-s tag-ok">configurado</span>' : '<span class="tag-s tag-bad">não configurado</span>' ?></label>
<input type="password" name="mp_access_token" autocomplete="new-password" placeholder="APP_USR-... (deixe em branco para manter)">
<label>Chave secreta do webhook (opcional, recomendado) <?= setting('mp_webhook_secret') !== '' ? '<span class="tag-s tag-ok">configurada</span>' : '' ?></label>
<input type="password" name="mp_webhook_secret" autocomplete="new-password" placeholder="deixe em branco para manter">
<label class="check"><input type="checkbox" name="mp_sandbox" <?= setting('mp_sandbox') === '1' ? 'checked' : '' ?>> Modo de teste (sandbox)</label>
<label class="check"><input type="checkbox" name="clear_mp_access_token"> Apagar access token salvo</label>
<div class="hint" style="margin-top:14px">URL de notificação (Webhooks &rarr; evento "Pagamentos"): <strong><?= e(url('webhook.php')) ?></strong></div>
<br><button class="btn">Salvar</button></form>

<form class="formbox" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="test_mail">
<h2 style="margin:14px 0 6px">Testar e-mail</h2><div class="hint">Salve as configurações antes. Envia uma mensagem para <?= e($admin['email']) ?>.</div><br><button class="btn btn-blue">Enviar e-mail de teste</button></form>
<?php require ROOT . '/app/views/admin_footer.php'; ?>
