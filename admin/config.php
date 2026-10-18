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

    // instituição, assinatura do certificado e LGPD
    foreach (['company_name' => 120, 'company_cnpj' => 24, 'cert_signer_name' => 120, 'cert_signer_role' => 120] as $k => $len) set_setting($k, mb_substr(trim($_POST[$k] ?? ''), 0, $len));
    set_setting('dpo_email', filter_var($_POST['dpo_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '');

    // aparência do certificado: tema, assinatura e logo
    set_setting('cert_theme', ($_POST['cert_theme'] ?? '') === 'light' ? 'light' : 'dark');
    try {
        if ($sig = save_signature($_FILES['cert_signature'] ?? [])) { @unlink(ROOT . '/uploads/' . basename(setting('cert_signature'))); set_setting('cert_signature', $sig); }
        if ($logo = save_logo_image($_FILES['cert_logo'] ?? [], 'logo')) { if (setting('cert_logo')) @unlink(ROOT . '/uploads/' . basename(setting('cert_logo'))); set_setting('cert_logo', $logo); }
        if ($sl = save_logo_image($_FILES['site_logo'] ?? [], 'sitelogo')) { if (setting('site_logo')) @unlink(ROOT . '/uploads/' . basename(setting('site_logo'))); set_setting('site_logo', $sl); }
    } catch (RuntimeException $ex) { flash('err', $ex->getMessage()); }
    set_setting('site_logo_only', isset($_POST['site_logo_only']) ? '1' : '0');
    foreach (['cert_signature', 'cert_logo', 'site_logo'] as $k) if (isset($_POST['remove_' . $k]) && setting($k) !== '') { @unlink(ROOT . '/uploads/' . basename(setting($k))); set_setting($k, ''); }

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
<form class="formbox" method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<label>Nome da plataforma</label><input type="text" name="site_name" value="<?= e(site_name()) ?>">
<label>Logo da plataforma (aparece no topo de todas as páginas, na página inicial e nas telas de login)</label>
<input type="file" name="site_logo" accept="image/png,image/jpeg,image/webp">
<div class="hint">PNG com fundo transparente fica melhor sobre o fundo escuro. Será reduzido automaticamente. Altura exibida no topo: ~38px.</div>
<?php if (setting('site_logo')): ?><div class="hint"><img src="<?= e(url('uploads/' . setting('site_logo'))) ?>" style="height:38px;background:#07080c;padding:6px;border-radius:6px;display:inline-block;vertical-align:middle;border:1px solid var(--line)">
<label class="check" style="display:inline-flex;margin-left:10px"><input type="checkbox" name="remove_site_logo"> remover</label></div><?php endif; ?>
<label class="check"><input type="checkbox" name="site_logo_only" <?= setting('site_logo_only') === '1' ? 'checked' : '' ?>> Mostrar apenas o logo (ocultar o nome ao lado)</label>

<h2 style="margin:26px 0 0;color:var(--gold)">Instituição, certificado e LGPD</h2>
<div class="hint">Aparecem no certificado, na página pública de validação e na Política de Privacidade.</div>
<div class="grid2"><div><label>Razão social / nome da instituição</label><input type="text" name="company_name" value="<?= e(setting('company_name')) ?>"></div>
<div><label>CNPJ</label><input type="text" name="company_cnpj" value="<?= e(setting('company_cnpj')) ?>" placeholder="00.000.000/0001-00"></div>
<div><label>Assinatura do certificado — nome</label><input type="text" name="cert_signer_name" value="<?= e(setting('cert_signer_name')) ?>"></div>
<div><label>Assinatura — cargo</label><input type="text" name="cert_signer_role" value="<?= e(setting('cert_signer_role')) ?>" placeholder="Diretor(a) Acadêmico(a)"></div>
<div><label>E-mail do encarregado de dados (DPO)</label><input type="email" name="dpo_email" value="<?= e(setting('dpo_email')) ?>" placeholder="privacidade@seudominio.com.br"></div></div>

<h2 style="margin:26px 0 0;color:var(--gold)">Aparência do certificado</h2>
<div class="grid2"><div><label>Tema</label><select name="cert_theme"><option value="dark" <?= setting('cert_theme', 'dark') === 'dark' ? 'selected' : '' ?>>Escuro (preto, dourado e azul)</option><option value="light" <?= setting('cert_theme') === 'light' ? 'selected' : '' ?>>Claro (marfim, dourado e azul) — economiza tinta</option></select></div>
<div><label>Logo da instituição (opcional)</label><input type="file" name="cert_logo" accept="image/png,image/jpeg,image/webp"><?php if (setting('cert_logo')): ?><div class="hint"><img src="<?= e(url('uploads/' . setting('cert_logo'))) ?>" style="height:34px;background:#222;padding:4px;border-radius:4px;display:inline-block;vertical-align:middle"> <label class="check" style="display:inline-flex"><input type="checkbox" name="remove_cert_logo"> remover</label></div><?php endif; ?></div>
<div><label>Assinatura da responsável (imagem)</label><input type="file" name="cert_signature" accept="image/png,image/jpeg,image/webp"><div class="hint">Foto ou scan da assinatura em tinta escura sobre papel claro (PNG com fundo transparente é o ideal). O sistema remove o fundo e a pinta na cor do tema. Sem imagem, o nome é exibido em letra cursiva.</div>
<?php if (setting('cert_signature')): ?><div class="hint"><span style="display:inline-block;height:34px;width:120px;vertical-align:middle;background:#d4af37;-webkit-mask:url('<?= e(url('uploads/' . setting('cert_signature'))) ?>') center/contain no-repeat;mask:url('<?= e(url('uploads/' . setting('cert_signature'))) ?>') center/contain no-repeat"></span> <label class="check" style="display:inline-flex"><input type="checkbox" name="remove_cert_signature"> remover</label></div><?php endif; ?></div></div>
<p style="margin-top:10px"><a class="btn btn-blue btn-sm" target="_blank" href="<?= e(url('admin/certificado_previa.php')) ?>">Pré-visualizar certificado (salve antes)</a></p>

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
