<?php
require __DIR__ . '/app/bootstrap.php';
$u = require_login();
$uid = (int)$u['id'];
$c = course_by_id((int)($_GET['curso'] ?? 0));
if (!$c || !has_access($uid, (int)$c['id'])) { http_response_code(403); exit('Sem acesso.'); }

$cert = q_one('SELECT * FROM certificates WHERE user_id = ? AND course_id = ?', [$uid, $c['id']]);
if (!$cert) { // certificados já emitidos continuam válidos mesmo se provas forem criadas depois
    [$eligible, $why] = certificate_eligible($uid, (int)$c['id']);
    if (!$eligible) { flash('err', 'Certificado indisponível: ' . $why); redirect('curso.php?id=' . $c['id']); }
    if (!q_val('SELECT cpf_enc FROM users WHERE id = ?', [$uid])) {
        flash('info', 'Para emitir o certificado informe seu CPF. Ele fica criptografado e aparece no certificado apenas parcialmente.');
        redirect('perfil.php#cpf');
    }
}
$cert = issue_certificate($u, $c);
if ($cert['status'] === 'revoked') { flash('err', 'Este certificado foi revogado. Entre em contato com o suporte.'); redirect('curso.php?id=' . $c['id']); }

$publicUrl = cert_public_url($cert['code']);
$workload = fmt_workload((int)$cert['workload_min']);
$signer = setting('cert_signer_name'); $signerRole = setting('cert_signer_role');
$org = setting('company_name') ?: site_name();
$cnpj = setting('company_cnpj');
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Certificado · <?= e($cert['course_title'] ?: $c['title']) ?></title><link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>"></head>
<body class="cert-page">
<div class="cert">
  <div class="org"><?= e($org) ?></div>
  <h1>Certificado de Conclusão</h1>
  <small>Certificamos que</small>
  <div><span class="name"><?= e($cert['holder_name'] ?: $u['name']) ?></span></div>
  <?php if ($cert['holder_cpf_masked']): ?><small>CPF <?= e($cert['holder_cpf_masked']) ?></small><?php endif; ?>
  <small>concluiu com êxito o curso</small>
  <div class="course"><?= e($cert['course_title'] ?: $c['title']) ?></div>
  <small><?= $workload ? 'Carga horária: ' . e($workload) . ' · ' : '' ?><?= $cert['final_score'] !== null ? 'Aproveitamento: ' . rtrim(rtrim(number_format((float)$cert['final_score'], 2, ',', ''), '0'), ',') . '% · ' : '' ?>Emitido em <?= e(date('d/m/Y', strtotime($cert['issued_at']))) ?></small>
  <div class="cert-foot">
    <div class="cert-qr"><?= qr_svg($publicUrl, 118) ?></div>
    <div class="cert-ver"><small>Valide a autenticidade deste certificado<br>escaneando o QR Code ou acessando:</small>
      <small class="u"><?= e(url('verificar.php')) ?></small>
      <small>Código: <strong><?= e(cert_code_display($cert['code'])) ?></strong></small></div>
    <div class="cert-sign"><?php if ($signer): ?><div class="line"></div><strong><?= e($signer) ?></strong><small><?= e($signerRole) ?></small><?php endif; ?>
      <?php if ($cnpj): ?><small>CNPJ <?= e($cnpj) ?></small><?php endif; ?></div>
  </div>
</div>
<div class="no-print"><button class="btn" onclick="window.print()">Imprimir / Salvar PDF</button><a class="btn btn-ghost" style="color:#333" href="<?= e(url('curso.php?id=' . $c['id'])) ?>">Voltar</a>
  <a class="btn btn-ghost" style="color:#333" href="<?= e(url('perfil.php#certificados')) ?>">Privacidade do certificado</a></div>
</body></html>
