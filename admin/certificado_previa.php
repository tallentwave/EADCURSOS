<?php
require __DIR__ . '/_init.php';
$theme = ($_GET['tema'] ?? setting('cert_theme', 'dark')) === 'light' ? 'light' : 'dark';
$other = $theme === 'light' ? 'dark' : 'light';
cert_render([
    'title' => 'Prévia do certificado',
    'org' => setting('company_name') ?: site_name(), 'cnpj' => setting('company_cnpj'),
    'name' => 'Maria Aparecida Souza Lima', 'cpf' => '***.982.247-**',
    'course' => 'Marketing Digital Avançado', 'workload' => '12h', 'score' => 92.5,
    'completed' => date('Y-m-d H:i:s'), 'issued' => date('Y-m-d H:i:s'),
    'code' => 'A1B2C3D4E5F60718', 'public_url' => cert_public_url('A1B2C3D4E5F60718'),
    'signer' => setting('cert_signer_name'), 'signer_role' => setting('cert_signer_role'),
    'signature_url' => cert_signature_url($theme),
    'logo_url' => setting('cert_logo') ? url('uploads/' . setting('cert_logo')) : '',
    'theme' => $theme, 'hide_tip' => true,
    'extra_actions' => '<a class="btn btn-ghost" href="' . e(url('admin/certificado_previa.php?tema=' . $other)) . '">Ver tema ' . ($other === 'light' ? 'claro' : 'escuro') . '</a><a class="btn btn-ghost" href="' . e(url('admin/config.php')) . '">Voltar às configurações</a>',
]);
