<?php
require __DIR__ . '/app/bootstrap.php';
$u = require_login();
$cid = (int)($_GET['curso'] ?? 0);
$cert = card_cert((int)$u['id'], $cid);
if (!$cert || !has_access((int)$u['id'], $cid)) { http_response_code(404); exit('Carteirinha indisponível.'); }
$photo = user_photo_bytes(q_val('SELECT photo FROM users WHERE id = ?', [$u['id']]));
if (!$photo) { flash('info', 'Envie sua foto para gerar a carteirinha.'); redirect('carteirinha.php?curso=' . $cid); }
$d = card_data($cert, $photo);
$pdf = card_pdf(card_jpeg($d, 'front'), card_jpeg($d, 'back'));
header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($pdf));
header('Content-Disposition: attachment; filename="carteirinha.pdf"');
header('Cache-Control: private, no-store');
echo $pdf;
