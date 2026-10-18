<?php
// Foto do próprio aluno (privada e criptografada em repouso). Nunca é pública nem aparece na validação do certificado.
require __DIR__ . '/app/bootstrap.php';
$u = current_user();
if (!$u) { http_response_code(401); exit; }
$jpg = user_photo_bytes(q_val('SELECT photo FROM users WHERE id = ?', [$u['id']]));
if ($jpg === null) { http_response_code(404); exit; }
header('Content-Type: image/jpeg');
header('Content-Length: ' . strlen($jpg));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
echo $jpg;
