<?php
require __DIR__ . '/_init.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    set_setting('site_name', mb_substr(trim($_POST['site_name'] ?? '') ?: 'EAD Premium', 0, 60));
    set_setting('mail_from', filter_var($_POST['mail_from'] ?? '', FILTER_VALIDATE_EMAIL) ?: '');
    set_setting('mp_sandbox', isset($_POST['mp_sandbox']) ? '1' : '0');
    // segredos: só substitui se o campo for preenchido (não exibimos o valor salvo)
    foreach (['mp_access_token', 'mp_webhook_secret'] as $k) {
        $v = trim($_POST[$k] ?? '');
        if ($v !== '') set_setting($k, $v);
        if (isset($_POST['clear_' . $k])) set_setting($k, '');
    }
    flash('ok', 'Configurações salvas.');
    back('config.php');
}
require ROOT . '/app/views/admin_header.php';
?>
<h1>Configurações</h1>
<form class="formbox" method="post"><?= csrf_field() ?>
<label>Nome da plataforma</label><input type="text" name="site_name" value="<?= e(site_name()) ?>">
<label>E-mail remetente (recuperação de senha)</label><input type="email" name="mail_from" value="<?= e(setting('mail_from')) ?>" placeholder="no-reply@seudominio.com.br">
<h2 style="margin:26px 0 0;color:var(--gold)">Mercado Pago</h2>
<label>Access Token <?= setting('mp_access_token') !== '' ? '<span class="tag-s tag-ok">configurado</span>' : '<span class="tag-s tag-bad">não configurado</span>' ?></label>
<input type="password" name="mp_access_token" autocomplete="off" placeholder="APP_USR-... (deixe em branco para manter)">
<label>Chave secreta do webhook (opcional, recomendado) <?= setting('mp_webhook_secret') !== '' ? '<span class="tag-s tag-ok">configurada</span>' : '' ?></label>
<input type="password" name="mp_webhook_secret" autocomplete="off" placeholder="deixe em branco para manter">
<label class="check"><input type="checkbox" name="mp_sandbox" <?= setting('mp_sandbox') === '1' ? 'checked' : '' ?>> Modo de teste (sandbox)</label>
<label class="check"><input type="checkbox" name="clear_mp_access_token"> Apagar access token salvo</label>
<div class="hint" style="margin-top:14px">URL de notificação (Webhooks &rarr; evento "Pagamentos"): <strong><?= e(url('webhook.php')) ?></strong></div>
<br><button class="btn">Salvar</button></form>
<?php require ROOT . '/app/views/admin_footer.php'; ?>
