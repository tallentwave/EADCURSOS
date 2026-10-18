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

cert_render([
    'title' => 'Certificado · ' . ($cert['course_title'] ?: $c['title']),
    'org' => setting('company_name') ?: site_name(), 'cnpj' => setting('company_cnpj'),
    'name' => $cert['holder_name'] ?: $u['name'], 'cpf' => $cert['holder_cpf_masked'],
    'course' => $cert['course_title'] ?: $c['title'], 'workload' => fmt_workload((int)$cert['workload_min']),
    'score' => $cert['final_score'], 'completed' => $cert['completed_at'], 'issued' => $cert['issued_at'],
    'code' => $cert['code'], 'public_url' => cert_public_url($cert['code']),
    'signer' => setting('cert_signer_name'), 'signer_role' => setting('cert_signer_role'),
    'signature_url' => cert_signature_url(setting('cert_theme', 'dark')),
    'logo_url' => setting('cert_logo') ? url('uploads/' . setting('cert_logo')) : '',
    'theme' => setting('cert_theme', 'dark'),
    'extra_actions' => '<a class="btn btn-ghost" href="' . e(url('curso.php?id=' . $c['id'])) . '">Voltar ao curso</a><a class="btn btn-blue" href="' . e(url('carteirinha.php?curso=' . $c['id'])) . '">&#129706; Carteirinha</a><a class="btn btn-ghost" href="' . e(url('perfil.php#certificados')) . '">Privacidade do certificado</a>',
]);
